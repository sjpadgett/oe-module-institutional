<?php

/**
 * public/hbc/hbc_visit_sync.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

/**
 * public/hbc/hbc_visit_sync.php — HBC Visit Offline Sync Endpoint
 *
 * Called by the Service Worker's background sync tag 'oei-hbc-sync'
 * when connectivity is restored after an offline finalise or draft save.
 *
 * Accepts a batch of queued entries from the browser's IndexedDB
 * hbcVisitQueue store and applies each one to the database.
 *
 * Entry types:
 *   FINALISE — calls HbcVisitRepository::finalise() to complete the visit
 *   DRAFT    — calls HbcVisitRepository::saveDraft() to persist partial data
 *
 * POST JSON body:
 * {
 *   "facility_id": 1,
 *   "entries": [
 *     {
 *       "entry_type":      "FINALISE" | "DRAFT",
 *       "visit_id":        42,
 *       "facility_id":     1,
 *       "fields":          { visit_note, outcome_summary, mileage_miles, ... },
 *       "signature_data":  "data:image/png;base64,...",
 *       "captured_client": "2026-03-25T14:22:00Z"
 *     }
 *   ]
 * }
 *
 * Response:
 * {
 *   "ok": true,
 *   "processed": N,
 *   "failed": N,
 *   "results": [
 *     { "idb_id": N, "type": "FINALISE", "ok": true },
 *     ...
 *   ]
 * }
 *
 * CSRF: X-CSRF-Token header (same pattern as downtime_sync.php).
 * The SW reads the token from IDB 'meta' store, stored there by
 * _bootstrap.php on every page load.
 */

declare(strict_types=1);

// Clear ob buffer before JSON output — _bootstrap.php calls ob_start()
while (ob_get_level() > 0) { ob_end_clean(); }

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Repository\HbcVisitRepository;

header('Content-Type: application/json; charset=utf-8');

// ── Feature check ─────────────────────────────────────────────────────────────
if (!$manifest->featureEnabled('hbc_visit')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'hbc_visit feature disabled']);
    exit;
}

// ── Method guard ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required']);
    exit;
}

// ── CSRF — header token (same as downtime_sync.php) ──────────────────────────
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CsrfUtils::verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'CSRF validation failed']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$body = (string)file_get_contents('php://input');
$data = json_decode($body, true);

if (!is_array($data) || !isset($data['entries']) || !is_array($data['entries'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON body — entries array required']);
    exit;
}

$facilityId = (int)($data['facility_id'] ?? $_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;
$entries    = $data['entries'];

// ── Process each queued entry ─────────────────────────────────────────────────
$repo      = new HbcVisitRepository();
$processed = 0;
$failed    = 0;
$results   = [];

foreach ($entries as $entry) {
    $idbId    = $entry['idb_id']    ?? null;
    $type     = strtoupper(trim((string)($entry['entry_type'] ?? '')));
    $visitId  = (int)($entry['visit_id'] ?? 0);
    $fields   = is_array($entry['fields']) ? $entry['fields'] : [];
    $sigData  = ($entry['signature_data'] ?? '') ?: null;

    if ($visitId <= 0) {
        $failed++;
        $results[] = ['idb_id' => $idbId, 'type' => $type, 'ok' => false, 'error' => 'missing visit_id'];
        continue;
    }

    try {
        if ($type === 'FINALISE') {
            $sigObtained = $sigData !== null
                && str_starts_with($sigData, 'data:image/png;base64,');

            // Build draft JSON from fields so saveDraft is called first (idempotent)
            $draftJson = json_encode(array_filter($fields, fn($v) => $v !== '' && $v !== null),
                                     JSON_UNESCAPED_UNICODE);
            $repo->saveDraft($visitId, $draftJson, null, null);

            // Normalise completion status from queued fields
            $completionStatus = strtoupper(trim((string)($fields['completion_status'] ?? 'COMPLETE')));
            if (!in_array($completionStatus, ['COMPLETE', 'REFUSED', 'MISSED'], true)) {
                $completionStatus = 'COMPLETE';
            }
            $medRec = strtoupper(trim((string)($fields['med_reconciliation_status'] ?? 'NOT_DONE')));
            if (!in_array($medRec, ['NOT_DONE', 'NO_CHANGES', 'UPDATED', 'ISSUES_FOUND'], true)) {
                $medRec = 'NOT_DONE';
            }
            $nextVisitType = strtoupper(trim((string)($fields['next_visit_type'] ?? '')));

            $ok = $repo->finalise(
                visitId:                   $visitId,
                visitNote:                 (string)($fields['visit_note'] ?? ''),
                outcomeSummary:            (string)($fields['outcome_summary'] ?? ''),
                mileage:                   isset($fields['mileage_miles']) && $fields['mileage_miles'] !== ''
                                               ? (float)$fields['mileage_miles'] : null,
                actualStart:               '',
                actualEnd:                 date('Y-m-d H:i:s'),
                completionStatus:          $completionStatus,
                sigObtained:               $sigObtained,
                sigData:                   $sigObtained ? $sigData : null,
                lat:                       null,
                lng:                       null,
                medReconciliationStatus:   $medRec,
                medReconciliationSummary:  (string)($fields['med_reconciliation_summary'] ?? ''),
                woundSummary:              (string)($fields['wound_summary'] ?? ''),
                procedureSummary:          (string)($fields['procedure_summary'] ?? ''),
                homeSafetySummary:         (string)($fields['home_safety_summary'] ?? ''),
                careCoordinationNeeded:    !empty($fields['care_coordination_needed']),
                careCoordinationSummary:   (string)($fields['care_coordination_summary'] ?? ''),
                followupPlan:              (string)($fields['followup_plan'] ?? ''),
                nextVisitDueDate:          ($fields['next_visit_due_date'] ?? '') !== ''
                                               ? (string)$fields['next_visit_due_date'] : null,
                nextVisitType:             $nextVisitType !== '' ? $nextVisitType : null,
                userId:                    $userId,
            );

            if ($ok) {
                $processed++;
                $results[] = ['idb_id' => $idbId, 'type' => 'FINALISE', 'ok' => true, 'visit_id' => $visitId];
            } else {
                $failed++;
                $results[] = ['idb_id' => $idbId, 'type' => 'FINALISE', 'ok' => false, 'error' => 'finalise returned false'];
            }

        } elseif ($type === 'DRAFT') {
            $draftJson = json_encode(array_filter($fields, fn($v) => $v !== '' && $v !== null),
                                     JSON_UNESCAPED_UNICODE);
            $repo->saveDraft($visitId, $draftJson, null, null);
            $processed++;
            $results[] = ['idb_id' => $idbId, 'type' => 'DRAFT', 'ok' => true, 'visit_id' => $visitId];

        } else {
            $failed++;
            $results[] = ['idb_id' => $idbId, 'type' => $type, 'ok' => false, 'error' => 'unknown entry_type'];
        }

    } catch (\Throwable $e) {
        $failed++;
        $results[] = ['idb_id' => $idbId, 'type' => $type, 'ok' => false, 'error' => $e->getMessage()];
        error_log('[OEI HBC-SYNC] visit_id=' . $visitId . ' type=' . $type . ' error=' . $e->getMessage());
    }
}

// ── Record sync in oei_downtime_sync_queue for audit trail ───────────────────
if (function_exists('sqlStatement') && ($processed + $failed) > 0) {
    sqlStatement(
        "INSERT INTO oei_downtime_sync_queue
            (facility_id, entry_type, payload_json, captured_client,
             queued_datetime, synced_datetime, status, submitted_by_user_id)
         VALUES (?, 'HBC_VISIT_BATCH', ?, NOW(), NOW(), NOW(), ?, ?)",
        [
            $facilityId,
            json_encode(['count' => count($entries), 'processed' => $processed, 'failed' => $failed]),
            $failed === 0 ? 'SYNCED' : 'PARTIAL',
            $userId,
        ]
    );
}

echo json_encode([
    'ok'        => $failed === 0,
    'processed' => $processed,
    'failed'    => $failed,
    'results'   => $results,
]);







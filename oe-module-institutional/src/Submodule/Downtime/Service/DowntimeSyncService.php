<?php

/**
 * src/Submodule/Downtime/Service/DowntimeSyncService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Downtime\Service;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

/**
 * DowntimeSyncService
 *
 * Processes rows from oei_downtime_sync_queue one at a time.
 *
 * Each entry_type maps to an existing service or repository call so
 * offline writes land in exactly the same tables as online writes.
 *
 * Supported entry types
 * ─────────────────────
 *   ARRIVAL     → EpisodeRepository::createArrival()
 *   VITALS      → TriageRepository::upsert()
 *   STATUS_NOTE → EpisodeRepository::appendStatusHistory()
 *   TASK_NOTE   → inline SQL update on oei_task.payload_json
 */
final class DowntimeSyncService
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly TriageRepository  $triage,
        private readonly TaskRepository    $tasks
    ) {}

    /**
     * Process a single queue row.
     *
     * @param  array<string,mixed> $queueRow   Row from oei_downtime_sync_queue
     * @param  int|null            $userId
     * @return array{ok: bool, result?: string, error?: string}
     */
    public function processRow(array $queueRow, ?int $userId): array
    {
        $raw = (string)($queueRow['payload_json'] ?? '{}');
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return ['ok' => false, 'error' => 'Invalid JSON payload'];
        }

        try {
            return match ((string)($queueRow['entry_type'] ?? '')) {
                'ARRIVAL'     => $this->processArrival($payload, $userId),
                'VITALS'      => $this->processVitals($payload, $userId),
                'STATUS_NOTE' => $this->processStatusNote($payload, $userId),
                'TASK_NOTE'   => $this->processTaskNote($payload),
                default       => ['ok' => false, 'error' => 'Unknown entry_type'],
            };
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process all PENDING rows for a facility.
     * Updates queue status in-place.
     *
     * @return array{processed: int, synced: int, failed: int}
     */
    public function processFacilityQueue(int $facilityId, ?int $userId): array
    {
        if (!function_exists('sqlStatement') || !function_exists('sqlFetchArray')) {
            return ['processed' => 0, 'synced' => 0, 'failed' => 0];
        }

        $res = sqlStatement(
            "SELECT * FROM oei_downtime_sync_queue
             WHERE facility_id = ? AND status = 'PENDING'
             ORDER BY captured_client ASC, id ASC",
            [$facilityId]
        );

        $processed = $synced = $failed = 0;
        $now = date('Y-m-d H:i:s');

        while ($row = sqlFetchArray($res)) {
            $result = $this->processRow($row, $userId);
            $processed++;

            if ($result['ok']) {
                $synced++;
                sqlStatement(
                    "UPDATE oei_downtime_sync_queue
                     SET status='SYNCED', synced_datetime=?, result_note=?
                     WHERE id=?",
                    [$now, substr((string)($result['result'] ?? 'ok'), 0, 254), (int)$row['id']]
                );
            } else {
                $failed++;
                sqlStatement(
                    "UPDATE oei_downtime_sync_queue
                     SET status='FAILED', synced_datetime=?, result_note=?
                     WHERE id=?",
                    [$now, substr((string)($result['error'] ?? 'error'), 0, 254), (int)$row['id']]
                );
            }
        }

        return compact('processed', 'synced', 'failed');
    }

    // ── Entry type handlers ───────────────────────────────────────────────────

    /** @param array<string,mixed> $p */
    private function processArrival(array $p, ?int $userId): array
    {
        $pid    = (int)($p['pid']         ?? 0);
        $facId  = (int)($p['facility_id'] ?? 0);
        $cc     = trim((string)($p['chief_complaint'] ?? ''));
        $esi    = isset($p['acuity_esi']) && $p['acuity_esi'] !== '' ? (int)$p['acuity_esi'] : null;

        if ($pid <= 0 || $facId <= 0) {
            return ['ok' => false, 'error' => 'Missing pid or facility_id'];
        }

        $newId = $this->episodes->createArrival($pid, $facId, $cc ?: null, $esi, $userId);
        return $newId > 0
            ? ['ok' => true,  'result' => "Created episode #{$newId}"]
            : ['ok' => false, 'error'  => 'createArrival returned 0'];
    }

    /** @param array<string,mixed> $p */
    private function processVitals(array $p, ?int $userId): array
    {
        $episodeId  = (int)($p['episode_id']  ?? 0);
        $pid        = (int)($p['pid']         ?? 0);
        $facilityId = (int)($p['facility_id'] ?? 0);

        if ($episodeId <= 0 || $pid <= 0) {
            return ['ok' => false, 'error' => 'Missing episode_id or pid'];
        }

        $intOrNull   = static fn ($k) => isset($p[$k]) && $p[$k] !== '' ? (int)$p[$k]   : null;
        $floatOrNull = static fn ($k) => isset($p[$k]) && $p[$k] !== '' ? (float)$p[$k] : null;

        $this->triage->upsert(
            episodeId:   $episodeId,
            pid:         $pid,
            eid:         null,
            facilityId:  $facilityId,
            setNumber:   max(1, $intOrNull('set_number') ?? 1),
            bpSystolic:  $intOrNull('bp_systolic'),
            bpDiastolic: $intOrNull('bp_diastolic'),
            hr:          $intOrNull('hr'),
            rr:          $intOrNull('rr'),
            tempF:       $floatOrNull('temp_f'),
            spo2:        $intOrNull('spo2'),
            gcs:         $intOrNull('gcs'),
            painScore:   $intOrNull('pain_score'),
            weightKg:    $floatOrNull('weight_kg'),
            arrivalMode: trim((string)($p['arrival_mode'] ?? '')),
            esiSuggested: null,
            notes:       trim((string)($p['notes'] ?? '')) ?: null,
            userId:      $userId
        );

        return ['ok' => true, 'result' => 'Vitals applied'];
    }

    /** @param array<string,mixed> $p */
    private function processStatusNote(array $p, ?int $userId): array
    {
        $episodeId = (int)($p['episode_id'] ?? 0);
        $status    = trim((string)($p['status'] ?? 'WAITING'));
        $note      = trim((string)($p['note']   ?? '')) ?: null;

        if ($episodeId <= 0) {
            return ['ok' => false, 'error' => 'Missing episode_id'];
        }

        $this->episodes->appendStatusHistory($episodeId, $status, $userId, $note);
        return ['ok' => true, 'result' => 'Status note appended'];
    }

    /** @param array<string,mixed> $p */
    private function processTaskNote(array $p): array
    {
        $taskId = (int)($p['task_id'] ?? 0);
        $note   = trim((string)($p['note'] ?? ''));

        if ($taskId <= 0) {
            return ['ok' => false, 'error' => 'Missing task_id'];
        }

        if (function_exists('sqlStatement')) {
            sqlStatement(
                "UPDATE oei_task
                 SET payload_json = JSON_SET(COALESCE(payload_json, '{}'), '$.downtime_note', ?)
                 WHERE id = ?",
                [$note, $taskId]
            );
        }

        return ['ok' => true, 'result' => 'Task note appended'];
    }
}




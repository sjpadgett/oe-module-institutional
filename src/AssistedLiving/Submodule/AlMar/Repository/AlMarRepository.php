<?php

/**
 * src/AssistedLiving/Submodule/AlMar/Repository/AlMarRepository.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Repository;

use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service\MarService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

/**
 * AlMarRepository
 *
 * AL-specific MAR queries that extend the shared MAR infrastructure.
 *
 * AL MAR differs from ED/Obs/BH MAR in two key ways:
 *   1. Orders are STANDING (QD/BID/TID/QID over months, not one acute visit).
 *   2. The primary view is a rolling calendar window (today ± N days), not a
 *      single encounter/shift.
 *
 * All writes delegate to the shared MarOrderRepository + MarAdministrationRepository
 * via MarService to ensure scheduling, high-alert detection, slot generation,
 * and allergy checks apply equally across ALL episode contexts.
 * This is the single, authoritative write path — never bypass it.
 */
final class AlMarRepository
{
    private MarOrderRepository          $orders;
    private MarAdministrationRepository $admins;
    private MarService                  $service;
    private ?TaskRepository              $tasks = null;

    public function __construct()
    {
        $this->orders  = new MarOrderRepository();
        $this->admins  = new MarAdministrationRepository();
        $this->tasks   = class_exists(TaskRepository::class) ? new TaskRepository() : null;
        $this->service = new MarService($this->orders, $this->admins, $this->tasks);
    }

    // ------------------------------------------------------------------ reads

    /**
     * Active medication orders for an episode (scheduled + PRN).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listActiveOrders(int $episodeId): array
    {
        return $this->orders->listActiveByEpisode($episodeId);
    }

    /**
     * All orders (including discontinued) for history view.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listAllOrders(int $episodeId): array
    {
        return $this->orders->listByEpisode($episodeId);
    }


    /**
     * @return array{units:list<array{value:string,label:string}>,routes:list<array{value:string,label:string}>,frequencies:list<array{value:string,label:string}>}
     */
    public function getOrderVocabulary(): array
    {
        return $this->orders->getOrderVocabulary();
    }

    public function normalizeUnit(?string $value): ?string
    {
        $normalized = $this->orders->normalizeUnit((string)$value);
        return $normalized !== '' ? $normalized : null;
    }

    public function normalizeRoute(?string $value): ?string
    {
        $normalized = $this->orders->normalizeRoute((string)$value);
        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    public function buildWorkspace(int $episodeId): array
    {
        $pending = $this->admins->listPendingWorkspaceByEpisode($episodeId);
        return [
            'due_now' => $this->bucketPending($pending, 'due_now'),
            'due_soon' => $this->bucketPending($pending, 'due_soon'),
            'overdue' => $this->bucketPending($pending, 'overdue'),
            'awaiting_cosign' => array_slice($this->admins->listAwaitingCoSignByEpisode($episodeId), 0, 12),
            'recent_prn' => array_slice($this->admins->listRecentPrnByEpisode($episodeId), 0, 12),
            'exception_followup' => array_slice($this->tasks?->listOpenMarFollowUpByEpisode($episodeId) ?? [], 0, 12),
        ];
    }

    /**
     * Administration grid for a date window.
     *
     * Returns flat rows (ordered by scheduled_datetime ASC) covering every
     * administration slot — PENDING, GIVEN, HELD, REFUSED, OMITTED — for
     * the rolling calendar view.
     *
     * @param  int    $episodeId
     * @param  string $dateFrom  Y-m-d
     * @param  string $dateTo    Y-m-d
     * @return array<int,array<string,mixed>>
     */
    public function listAdminsByWindow(int $episodeId, string $dateFrom, string $dateTo): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.scheduled_datetime,
                    a.administered_datetime, a.outcome,
                    a.dose_given, a.unit_given, a.route_given, a.site,
                    a.is_high_alert, a.hold_reason, a.note,
                    a.administered_by_user_id,
                    a.witness_user_id, a.waste_amount, a.waste_unit,
                    a.co_sign_user_id, a.co_signed_datetime,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn, o.instructions,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS nurse_name,
                    CONCAT(COALESCE(w.fname,''),' ',COALESCE(w.lname,'')) AS witness_name,
                    CONCAT(COALESCE(cs.fname,''),' ',COALESCE(cs.lname,'')) AS co_sign_name
             FROM   oei_mar_administration a
             JOIN   oei_mar_order o ON o.id = a.mar_order_id
             LEFT   JOIN users u ON u.id = a.administered_by_user_id
                                AND u.active = 1 AND u.fname IS NOT NULL
             LEFT   JOIN users w  ON w.id  = a.witness_user_id
             LEFT   JOIN users cs ON cs.id = a.co_sign_user_id
             WHERE  a.episode_id = ?
               AND  DATE(a.scheduled_datetime) BETWEEN ? AND ?
             ORDER  BY a.scheduled_datetime ASC, o.drug_name ASC",
            [$episodeId, $dateFrom, $dateTo]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                   => (int)$r['id'],
                'mar_order_id'         => (int)$r['mar_order_id'],
                'drug_name'            => (string)$r['drug_name'],
                'ordered_dose'         => (string)$r['ordered_dose'],
                'ordered_unit'         => (string)$r['ordered_unit'],
                'ordered_route'        => (string)$r['ordered_route'],
                'frequency'            => (string)$r['frequency'],
                'is_prn'               => (bool)$r['is_prn'],
                'instructions'         => (string)($r['instructions'] ?? ''),
                'scheduled_datetime'   => (string)$r['scheduled_datetime'],
                'administered_datetime'=> $r['administered_datetime'] ?: null,
                'outcome'              => (string)$r['outcome'],
                'dose_given'           => (string)($r['dose_given'] ?? ''),
                'unit_given'           => (string)($r['unit_given'] ?? ''),
                'route_given'          => (string)($r['route_given'] ?? ''),
                'site'                 => (string)($r['site'] ?? ''),
                'is_high_alert'        => (bool)$r['is_high_alert'],
                'hold_reason'          => (string)($r['hold_reason'] ?? ''),
                'note'                 => (string)($r['note'] ?? ''),
                'nurse_name'           => trim((string)($r['nurse_name'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Today's administration summary for a facility (all AL episodes).
     * Used by the resident profile panel's quick MAR badge.
     *
     * @return array<int, array{pending:int, given:int, held:int, overdue:int}>
     *         keyed by episode_id
     */
    public function facilityTodaySummary(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $today  = date('Y-m-d');
        $cutoff = date('Y-m-d H:i:s', time() - 900); // 15 min grace period

        $res = sqlStatement(
            "SELECT a.episode_id, a.outcome, a.scheduled_datetime
             FROM   oei_mar_administration a
             JOIN   oei_episode e ON e.id = a.episode_id
             WHERE  e.facility_id = ? AND e.type = 'AL' AND e.status = 'ACTIVE'
               AND  DATE(a.scheduled_datetime) = ?",
            [$facilityId, $today]
        );

        $summary = [];
        while ($r = sqlFetchArray($res)) {
            $eid = (int)$r['episode_id'];
            if (!isset($summary[$eid])) {
                $summary[$eid] = ['pending' => 0, 'given' => 0, 'held' => 0, 'overdue' => 0];
            }
            $outcome = (string)$r['outcome'];
            if ($outcome === 'GIVEN') {
                $summary[$eid]['given']++;
            } elseif (in_array($outcome, ['HELD', 'REFUSED'], true)) {
                $summary[$eid]['held']++;
            } elseif ($outcome === 'PENDING') {
                $summary[$eid]['pending']++;
                if ((string)$r['scheduled_datetime'] < $cutoff) {
                    $summary[$eid]['overdue']++;
                }
            }
        }

        return $summary;
    }


    /**
     * @param array<int,array<string,mixed>> $pending
     * @return array<int,array<string,mixed>>
     */
    private function bucketPending(array $pending, string $bucket): array
    {
        $now = time();
        $rows = [];
        foreach ($pending as $row) {
            $ts = !empty($row['scheduled_datetime']) ? (strtotime((string)$row['scheduled_datetime']) ?: 0) : 0;
            $match = false;
            if ($bucket === 'overdue') {
                $match = ($ts > 0 && $ts < $now);
            } elseif ($bucket === 'due_now') {
                $match = ($ts <= 0 || ($ts >= $now && $ts <= $now + (15 * 60)));
            } elseif ($bucket === 'due_soon') {
                $match = ($ts > $now + (15 * 60) && $ts <= $now + (60 * 60));
            }
            if ($match) {
                $rows[] = $row;
            }
        }
        return array_slice($rows, 0, 12);
    }

    // ------------------------------------------------------------------ writes
    // All writes go through shared MarService — the single authoritative path.

    /**
     * Record an administration outcome on a scheduled slot.
     * Outcomes: GIVEN | HELD | REFUSED | OMITTED
     *
     * Delegates to MarService::recordAdministration() — same path used by
     * ED, Obs, and BH MARs.
     */
    public function administer(
        int     $administrationId,
        string  $outcome,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $holdReason,
        ?string $note,
        ?int    $userId,
        ?int    $witnessUserId = null,
        ?string $wasteAmount   = null,
        ?string $wasteUnit     = null,
        array   $followUp      = []
    ): bool {
        try {
            $this->service->recordAdministration(
                $administrationId,
                $outcome,
                null, // administered_datetime: default to now
                $doseGiven,
                $unitGiven,
                $routeGiven,
                $site,
                null, // lot_number: not captured in AL rolling grid
                $userId,
                $holdReason,
                $note,
                $witnessUserId,
                $wasteAmount,
                $wasteUnit,
                $followUp
            );
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Amend a previously-documented administration row.
     * Preserves the original record in the note field prefix (audit trail).
     *
     * Delegates to MarService::amendAdministration() — shared across all contexts.
     */
    public function amendAdministration(
        int     $administrationId,
        string  $outcome,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $holdReason,
        ?string $note,
        ?int    $userId,
        ?int    $witnessUserId = null,
        ?string $wasteAmount   = null,
        ?string $wasteUnit     = null,
        array   $followUp      = []
    ): bool {
        try {
            $this->service->amendAdministration(
                $administrationId,
                $outcome,
                null, // administered_datetime: preserve original
                $doseGiven,
                $unitGiven,
                $routeGiven,
                $site,
                null, // lot_number
                $userId,
                $holdReason,
                $note,
                $witnessUserId,
                $wasteAmount,
                $wasteUnit,
                $followUp
            );
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Record an as-needed (PRN) dose.
     *
     * Creates a fresh oei_mar_administration slot and immediately records it
     * as GIVEN. Delegates to MarService::givePrn() — the shared PRN path used
     * across AL, ED, Obs, and BH contexts.
     *
     * @param int     $marOrderId       The PRN mar_order.id
     * @param int     $episodeId
     * @param int     $pid
     * @param int     $facilityId
     * @param string  $drugName         Drug name for high-alert keyword check
     * @param bool    $isHighAlertOverride  True if nurse checked HA box explicitly
     * @param ?string $doseGiven
     * @param ?string $unitGiven
     * @param ?string $routeGiven
     * @param ?string $site
     * @param ?string $lotNumber
     * @param ?int    $userId
     * @param ?string $administeredDatetime  Nurse-supplied or null (defaults to now)
     * @param ?string $note
     */
    public function givePrn(
        int     $marOrderId,
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $drugName,
        bool    $isHighAlertOverride,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int    $userId,
        ?string $administeredDatetime,
        ?string $note
    ): void {
        $this->service->givePrn(
            $marOrderId,
            $episodeId,
            $pid,
            $facilityId,
            $drugName,
            $isHighAlertOverride,
            $doseGiven,
            $unitGiven,
            $routeGiven,
            $site,
            $lotNumber,
            $userId,
            $administeredDatetime,
            $note
        );
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Expose MarService hold reasons for the administration form dropdown.
     *
     * @return array<string,string>
     */
    public function holdReasons(): array
    {
        return MarService::HOLD_REASONS;
    }
    /**
     * Record a co-signer on a GIVEN high-alert administration.
     * Delegates to the shared MarAdministrationRepository::coSign(),
     * which enforces the GIVEN + is_high_alert + not-yet-signed WHERE guard.
     */
    public function coSign(int $adminId, int $coSignUserId): bool
    {
        return $this->admins->coSign($adminId, $coSignUserId);
    }
}










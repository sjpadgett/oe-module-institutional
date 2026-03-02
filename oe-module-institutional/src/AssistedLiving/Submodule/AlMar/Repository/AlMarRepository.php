<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Repository;

use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Service\MarService;

/**
 * AlMarRepository
 *
 * AL-specific MAR queries that extend the shared MAR infrastructure.
 *
 * AL MAR differs from ED MAR in two key ways:
 *   1. Orders are STANDING (QD/BID/TID/QID over months, not one acute visit).
 *   2. The primary view is a rolling window (today ± N days), not a
 *      single encounter/shift.
 *
 * All writes still go through the shared MarOrderRepository + MarService
 * to ensure scheduling, high-alert detection, and allergy checks apply equally.
 */
final class AlMarRepository
{
    private MarOrderRepository          $orders;
    private MarAdministrationRepository $admins;
    private MarService                  $service;

    public function __construct()
    {
        $this->orders  = new MarOrderRepository();
        $this->admins  = new MarAdministrationRepository();
        $this->service = new MarService($this->orders, $this->admins);
    }

    /**
     * Active medication orders for an episode.
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
     * Administration grid for a date window.
     *
     * Returns rows grouped by order_id → date → administrations
     * for the rolling calendar view.
     *
     * @param  int    $episodeId
     * @param  string $dateFrom  Y-m-d
     * @param  string $dateTo    Y-m-d
     * @return array<int,array<string,mixed>>  flat rows, sorted by scheduled_datetime ASC
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
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn, o.instructions,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS nurse_name
             FROM   oei_mar_administration a
             JOIN   oei_mar_order o ON o.id = a.mar_order_id
             LEFT   JOIN users u ON u.id = a.administered_by_user_id
                                AND u.active=1 AND u.fname IS NOT NULL
             WHERE  a.episode_id = ?
               AND  DATE(a.scheduled_datetime) BETWEEN ? AND ?
             ORDER  BY a.scheduled_datetime ASC, o.drug_name ASC",
            [$episodeId, $dateFrom, $dateTo]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                  => (int)$r['id'],
                'mar_order_id'        => (int)$r['mar_order_id'],
                'drug_name'           => (string)$r['drug_name'],
                'ordered_dose'        => (string)$r['ordered_dose'],
                'ordered_unit'        => (string)$r['ordered_unit'],
                'ordered_route'       => (string)$r['ordered_route'],
                'frequency'           => (string)$r['frequency'],
                'is_prn'              => (bool)$r['is_prn'],
                'instructions'        => (string)($r['instructions'] ?? ''),
                'scheduled_datetime'  => (string)$r['scheduled_datetime'],
                'administered_datetime'=> $r['administered_datetime'] ?: null,
                'outcome'             => (string)$r['outcome'],
                'dose_given'          => (string)($r['dose_given'] ?? ''),
                'unit_given'          => (string)($r['unit_given'] ?? ''),
                'route_given'         => (string)($r['route_given'] ?? ''),
                'site'                => (string)($r['site'] ?? ''),
                'is_high_alert'       => (bool)$r['is_high_alert'],
                'hold_reason'         => (string)($r['hold_reason'] ?? ''),
                'note'                => (string)($r['note'] ?? ''),
                'nurse_name'          => trim((string)($r['nurse_name'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Record an administration outcome (GIVEN, HELD, REFUSED, OMITTED).
     *
     * Delegates to MarService for consistent validation + high-alert logic.
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
        ?int    $userId
    ): bool {
        return $this->service->recordAdministration(
            $administrationId,
            $outcome,
            $doseGiven,
            $unitGiven,
            $routeGiven,
            $site,
            $holdReason,
            $note,
            $userId
        );
    }

    /**
     * Today's administration summary for a facility (all AL episodes).
     * Used by the profile panel's quick MAR badge.
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
        $cutoff = date('Y-m-d H:i:s', time() - 900); // 15 min grace

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
     * Expose MarService hold reasons for the administration form dropdown.
     *
     * @return array<string,string>
     */
    public function holdReasons(): array
    {
        return MarService::HOLD_REASONS;
    }
}

<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\MultiFacility\Repository;

/**
 * MultiFacilityRepository
 *
 * Aggregates census, alert, and throughput metrics for every facility
 * that has at least one active oei_episode record.
 *
 * Facility identity resolution — fully self-contained, OpenEMR-independent:
 *
 *   1. DISCOVER  — DISTINCT facility_id from oei_episode (our own data)
 *   2. NAME      — oei_settings.facility_name per facility_id (our own table)
 *   3. NAME fallback — OpenEMR `facility` table if available and name not set
 *   4. NAME fallback — "Facility N" if nothing else resolves
 *
 * This means the dashboard works correctly even when:
 *   - OpenEMR's facility table has different records
 *   - The module is running on a standalone install without OpenEMR facilities
 *   - Multiple facility IDs share an OpenEMR instance
 *
 * To set a facility name independently of OpenEMR:
 *   Go to Settings → set "Facility Display Name" for each facility_id.
 *
 * Returned metrics per facility (array keys):
 *   facility_id         int
 *   facility_name       string
 *   census              int      active episode count
 *   obs_count           int      active OBS episodes
 *   lwbs_count          int      WAITING > lwbs_threshold with no location
 *   bh_boarding_count   int      BH boarding in SEARCHING/PENDING
 *   pending_mar_count   int      overdue PENDING MAR slots
 *   avg_d2r_today       int|null avg door-to-room today (minutes)
 *   beds_occupied       int      locations with an active episode
 *   beds_total          int      total active locations for facility
 *   sepsis_risk_count   int      episodes with qSOFA >= 2 in latest vitals
 */
final class MultiFacilityRepository
{
    private int $lwbsThresholdMin;

    public function __construct(int $lwbsThresholdMin = 120)
    {
        $this->lwbsThresholdMin = $lwbsThresholdMin;
    }

    /**
     * Return one array per facility that has any institutional data.
     * Ordered by census DESC (busiest first).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $facilities = $this->loadFacilityList();
        if (empty($facilities)) {
            return [];
        }

        $results = [];
        foreach ($facilities as $fac) {
            $fid  = (int)$fac['id'];
            $name = (string)$fac['name'];

            $results[] = array_merge(
                ['facility_id' => $fid, 'facility_name' => $name],
                $this->metricsForFacility($fid)
            );
        }

        usort($results, fn($a, $b) => $b['census'] <=> $a['census']);

        return $results;
    }

    // -----------------------------------------------------------------------
    // Per-facility metric computation
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function metricsForFacility(int $facilityId): array
    {
        return [
            'census'            => $this->queryCensus($facilityId),
            'obs_count'         => $this->queryObsCount($facilityId),
            'lwbs_count'        => $this->queryLwbs($facilityId),
            'bh_boarding_count' => $this->queryBhBoarding($facilityId),
            'pending_mar_count' => $this->queryPendingMar($facilityId),
            'avg_d2r_today'     => $this->queryAvgD2rToday($facilityId),
            'beds_occupied'     => $this->queryBeds($facilityId)['occupied'],
            'beds_total'        => $this->queryBeds($facilityId)['total'],
            'sepsis_risk_count' => $this->querySepsisRisk($facilityId),
        ];
    }

    private function queryCensus(int $fid): int
    {
        $r = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode
             WHERE facility_id = ? AND status = 'ACTIVE'",
            [$fid]
        );
        return (int)($r['c'] ?? 0);
    }

    private function queryObsCount(int $fid): int
    {
        $r = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode
             WHERE facility_id = ? AND status = 'ACTIVE' AND type = 'OBS'",
            [$fid]
        );
        return (int)($r['c'] ?? 0);
    }

    private function queryBeds(int $fid): array
    {
        $total = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_location
             WHERE facility_id = ? AND is_active = 1",
            [$fid]
        );
        $occupied = sqlQuery(
            "SELECT COUNT(DISTINCT el.location_id) AS c
             FROM oei_episode_location el
             JOIN oei_episode e ON e.id = el.episode_id
             WHERE el.facility_id = ?
               AND el.end_datetime IS NULL
               AND e.status = 'ACTIVE'",
            [$fid]
        );
        return [
            'total'    => (int)($total['c'] ?? 0),
            'occupied' => (int)($occupied['c'] ?? 0),
        ];
    }

    private function queryLwbs(int $fid): int
    {
        if ($this->lwbsThresholdMin <= 0) {
            return 0;
        }
        $cutoff = date('Y-m-d H:i:s', time() - $this->lwbsThresholdMin * 60);
        $r = sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_episode e
             LEFT JOIN oei_episode_location el
                ON el.episode_id = e.id AND el.end_datetime IS NULL
             WHERE e.facility_id = ?
               AND e.status = 'ACTIVE'
               AND e.start_datetime <= ?
               AND el.location_id IS NULL",
            [$fid, $cutoff]
        );
        return (int)($r['c'] ?? 0);
    }

    private function queryBhBoarding(int $fid): int
    {
        $r = sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_bh_boarding bh
             JOIN oei_episode e ON e.id = bh.episode_id
             WHERE e.facility_id = ?
               AND e.status = 'ACTIVE'
               AND bh.placement_status IN ('SEARCHING','PENDING')",
            [$fid]
        );
        return (int)($r['c'] ?? 0);
    }

    private function queryPendingMar(int $fid): int
    {
        $cutoff = date('Y-m-d H:i:s', time() - 900);
        $r = sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_mar_administration
             WHERE facility_id = ?
               AND outcome = 'PENDING'
               AND scheduled_datetime <= ?",
            [$fid, $cutoff]
        );
        return (int)($r['c'] ?? 0);
    }

    private function queryAvgD2rToday(int $fid): ?int
    {
        $todayStart = date('Y-m-d') . ' 00:00:00';
        $r = sqlQuery(
            "SELECT AVG(TIMESTAMPDIFF(MINUTE, ev_arr.event_datetime, ev_room.event_datetime)) AS avg_min
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_episode_event ev_room
                ON ev_room.episode_id = e.id
               AND ev_room.event_type IN ('ROOM','ROOMED')
             WHERE e.facility_id = ?
               AND e.start_datetime >= ?
               AND TIMESTAMPDIFF(MINUTE, ev_arr.event_datetime, ev_room.event_datetime) >= 0",
            [$fid, $todayStart]
        );
        $v = $r['avg_min'] ?? null;
        return ($v !== null && $v !== '') ? (int)round((float)$v) : null;
    }

    private function querySepsisRisk(int $fid): int
    {
        $res = sqlStatement(
            "SELECT t.bp_systolic, t.rr, t.gcs
             FROM oei_triage t
             INNER JOIN (
                 SELECT episode_id, MAX(id) AS max_id
                 FROM oei_triage
                 WHERE facility_id = ?
                 GROUP BY episode_id
             ) latest ON latest.episode_id = t.episode_id AND latest.max_id = t.id
             JOIN oei_episode e ON e.id = t.episode_id AND e.status = 'ACTIVE'",
            [$fid]
        );

        $count = 0;
        while ($row = sqlFetchArray($res)) {
            $score = 0;
            $gcs = ($row['gcs'] !== null && $row['gcs'] !== '') ? (int)$row['gcs'] : null;
            $rr  = ($row['rr']  !== null && $row['rr']  !== '') ? (int)$row['rr']  : null;
            $sbp = ($row['bp_systolic'] !== null && $row['bp_systolic'] !== '') ? (int)$row['bp_systolic'] : null;
            if ($gcs !== null && $gcs < 15)   $score++;
            if ($rr  !== null && $rr  >= 22)  $score++;
            if ($sbp !== null && $sbp <= 100) $score++;
            if ($score >= 2) $count++;
        }

        return $count;
    }

    // -----------------------------------------------------------------------
    // Facility identity — self-contained, OpenEMR-optional
    // -----------------------------------------------------------------------

    /**
     * Build the list of facilities to show on the dashboard.
     *
     * Discovery:  DISTINCT facility_id from oei_episode  (always our data)
     * Naming tier 1:  oei_settings facility_name  (set via our Settings page)
     * Naming tier 2:  OpenEMR facility table  (if available, inactive=0)
     * Naming tier 3:  "Facility N"  (always works)
     *
     * @return array<int,array{id:int,name:string}>
     */
    private function loadFacilityList(): array
    {
        // ── Step 1: discover all facility IDs from our own episode data ───────
        $res = sqlStatement(
            "SELECT DISTINCT facility_id AS id
             FROM oei_episode
             ORDER BY facility_id ASC"
        );
        $facilityIds = [];
        while ($r = sqlFetchArray($res)) {
            $facilityIds[] = (int)$r['id'];
        }

        if (empty($facilityIds)) {
            return [];
        }

        // ── Step 2: load names from oei_settings (facility_name key) ─────────
        $placeholders = implode(',', array_fill(0, count($facilityIds), '?'));
        $res = sqlStatement(
            "SELECT facility_id, setting_value
             FROM oei_settings
             WHERE setting_key = 'facility_name'
               AND setting_value != ''
               AND facility_id IN ({$placeholders})",
            $facilityIds
        );
        $nameMap = [];
        while ($r = sqlFetchArray($res)) {
            $nameMap[(int)$r['facility_id']] = (string)$r['setting_value'];
        }

        // ── Step 3: fill remaining from OpenEMR facility table (optional) ─────
        $needNames = array_diff($facilityIds, array_keys($nameMap));
        if (!empty($needNames)) {
            $checkTable = sqlQuery(
                "SELECT COUNT(*) AS c
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE() AND table_name = 'facility'
                 LIMIT 1"
            );
            if ((int)($checkTable['c'] ?? 0) > 0) {
                $ph2 = implode(',', array_fill(0, count($needNames), '?'));
                $res = sqlStatement(
                    "SELECT id, name FROM facility
                     WHERE inactive = 0 AND id IN ({$ph2})
                     ORDER BY name ASC",
                    array_values($needNames)
                );
                while ($r = sqlFetchArray($res)) {
                    $nameMap[(int)$r['id']] = (string)$r['name'];
                }
            }
        }

        // ── Step 4: assemble final list, "Facility N" for anything still unresolved
        $result = [];
        foreach ($facilityIds as $fid) {
            $result[] = [
                'id'   => $fid,
                'name' => $nameMap[$fid] ?? 'Facility ' . $fid,
            ];
        }

        return $result;
    }
}



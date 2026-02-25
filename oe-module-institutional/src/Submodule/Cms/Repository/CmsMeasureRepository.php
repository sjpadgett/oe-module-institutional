<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Cms\Repository;

/**
 * CmsMeasureRepository
 *
 * Computes CMS pay-for-performance quality measures from existing
 * institutional data — no new tables required.
 *
 * Measures implemented:
 *
 *  1. Door-to-Room          Target: ≤ 30 min    (CMS OP-1/ED-1)
 *     Source: oei_episode_event ARRIVE → ROOM/ROOMED
 *
 *  2. Door-to-Provider      Target: ≤ 60 min    (CMS OP-2/ED-2)
 *     Source: oei_episode_event ARRIVE → PROVIDER
 *
 *  3. Door-to-ECG           Target: ≤ 10 min    (CMS OP-5)
 *     Source: earliest COMPLETE task WHERE task_type IN (EKG, ECG)
 *
 *  4. Sepsis Antibiotic Bundle ≤ 3 h  (CMS SEP-1)
 *     Numerator: episodes with first antibiotic GIVEN within 3h of ARRIVE
 *     Denominator: all episodes where an antibiotic was administered
 *     Source: oei_mar_administration outcome=GIVEN for recognised antibiotic drugs
 *
 * All measures return:
 *   - Individual episode rows for drill-down
 *   - Aggregate stats: n, n_met, rate_pct, avg_min, median_min, p90_min
 *
 * Raw timestamps come from oei_episode_event (ARRIVE) so the data model
 * must have ARRIVE events recorded (IntakeController fires one on episode create).
 */
final class CmsMeasureRepository
{
    /**
     * Drug name substrings considered antibiotics for SEP-1 bundle compliance.
     * Case-insensitive substring match against oei_mar_order.drug_name.
     */
    private const ANTIBIOTIC_KEYWORDS = [
        'vancomycin',  'piperacillin', 'tazobactam',   'cefazolin',
        'ceftriaxone', 'cefepime',     'meropenem',     'imipenem',
        'azithromycin','levofloxacin', 'ciprofloxacin', 'metronidazole',
        'clindamycin', 'doxycycline',  'ampicillin',    'amoxicillin',
        'gentamicin',  'tobramycin',   'linezolid',     'daptomycin',
        'tigecycline', 'ertapenem',    'doripenem',     'oxacillin',
        'nafcillin',   'trimethoprim', 'sulfamethoxazole',
    ];

    /**
     * Task type substrings considered an ECG/EKG for door-to-ECG measure.
     */
    private const ECG_TASK_KEYWORDS = ['EKG', 'ECG', '12-LEAD', '12LEAD'];

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Compute all four measures for a facility and date range.
     *
     * @return array{
     *   door_to_room:     array<string,mixed>,
     *   door_to_provider: array<string,mixed>,
     *   door_to_ecg:      array<string,mixed>,
     *   sepsis_bundle:    array<string,mixed>,
     * }
     */
    public function computeAll(int $facilityId, string $dateFrom, string $dateTo): array
    {
        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';

        return [
            'door_to_room'     => $this->doorToRoom($facilityId, $dateFrom, $dateToEnd),
            'door_to_provider' => $this->doorToProvider($facilityId, $dateFrom, $dateToEnd),
            'door_to_ecg'      => $this->doorToEcg($facilityId, $dateFrom, $dateToEnd),
            'sepsis_bundle'    => $this->sepsisBundle($facilityId, $dateFrom, $dateToEnd),
        ];
    }

    // -----------------------------------------------------------------------
    // Measure 1: Door-to-Room (CMS OP-1)
    // -----------------------------------------------------------------------

    private function doorToRoom(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-Room', 30);
        }

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime  AS arrive_dt,
                MIN(ev_room.event_datetime) AS room_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_episode_event ev_room
                ON ev_room.episode_id = e.id
               AND ev_room.event_type IN ('ROOM','ROOMED')
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            [$facilityId, $from, $to]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin($r['arrive_dt'], $r['room_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => $r['arrive_dt'],
                    'event_dt'   => $r['room_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 30,
                ];
            }
        }

        return $this->buildMeasure('Door-to-Room', 30, 'OP-1', $rows);
    }

    // -----------------------------------------------------------------------
    // Measure 2: Door-to-Provider (CMS OP-2)
    // -----------------------------------------------------------------------

    private function doorToProvider(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-Provider', 60);
        }

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime  AS arrive_dt,
                MIN(ev_prov.event_datetime) AS provider_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_episode_event ev_prov
                ON ev_prov.episode_id = e.id AND ev_prov.event_type = 'PROVIDER'
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            [$facilityId, $from, $to]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin($r['arrive_dt'], $r['provider_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => $r['arrive_dt'],
                    'event_dt'   => $r['provider_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 60,
                ];
            }
        }

        return $this->buildMeasure('Door-to-Provider', 60, 'OP-2', $rows);
    }

    // -----------------------------------------------------------------------
    // Measure 3: Door-to-ECG (CMS OP-5)
    // -----------------------------------------------------------------------

    private function doorToEcg(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-ECG', 10);
        }

        // Build LIKE clauses for task type matching
        $likeClauses = array_map(
            fn(string $kw) => "t.task_type LIKE ?",
            self::ECG_TASK_KEYWORDS
        );
        $likeParams  = array_map(fn(string $kw) => '%' . $kw . '%', self::ECG_TASK_KEYWORDS);

        $whereTask = implode(' OR ', $likeClauses);

        $params = array_merge([$facilityId, $from, $to], $likeParams);

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime        AS arrive_dt,
                MIN(t.completed_datetime)    AS ecg_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_task t
                ON t.episode_id = e.id
               AND t.status = 'COMPLETE'
               AND t.completed_datetime IS NOT NULL
               AND ({$whereTask})
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            // Note: facility + date params come first in the WHERE clause
            array_merge([$facilityId, $from, $to], $likeParams)
        );

        // Re-run with correct param order (facility/date in WHERE, LIKE in JOIN)
        // Rebuild to ensure parameter order matches the SQL above
        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime        AS arrive_dt,
                MIN(t.completed_datetime)    AS ecg_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_task t
                ON t.episode_id = e.id
               AND t.status = 'COMPLETE'
               AND t.completed_datetime IS NOT NULL
               AND (" . $whereTask . ")
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            array_merge($likeParams, [$facilityId, $from, $to])
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin($r['arrive_dt'], $r['ecg_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => $r['arrive_dt'],
                    'event_dt'   => $r['ecg_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 10,
                ];
            }
        }

        return $this->buildMeasure('Door-to-ECG', 10, 'OP-5', $rows);
    }

    // -----------------------------------------------------------------------
    // Measure 4: Sepsis Antibiotic Bundle ≤ 3h (CMS SEP-1)
    // -----------------------------------------------------------------------

    private function sepsisBundle(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Sepsis Antibiotic Bundle ≤3h', 180);
        }

        // Build LIKE clauses for antibiotic drug name matching
        $likeClauses = array_map(
            fn(string $kw) => "LOWER(o.drug_name) LIKE ?",
            self::ANTIBIOTIC_KEYWORDS
        );
        $likeParams  = array_map(fn(string $kw) => '%' . strtolower($kw) . '%', self::ANTIBIOTIC_KEYWORDS);

        $whereAbx = implode(' OR ', $likeClauses);

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime           AS arrive_dt,
                MIN(a.administered_datetime)    AS first_abx_dt,
                MIN(o.drug_name)                AS drug_name
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
                ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_mar_administration a
                ON a.episode_id = e.id
               AND a.outcome = 'GIVEN'
               AND a.administered_datetime IS NOT NULL
             JOIN oei_mar_order o
                ON o.id = a.mar_order_id
               AND (" . $whereAbx . ")
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            array_merge($likeParams, [$facilityId, $from, $to])
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin($r['arrive_dt'], $r['first_abx_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => $r['arrive_dt'],
                    'event_dt'   => $r['first_abx_dt'],
                    'minutes'    => $min,
                    'drug_name'  => (string)$r['drug_name'],
                    'met'        => $min <= 180,
                ];
            }
        }

        return $this->buildMeasure('Sepsis Antibiotic Bundle ≤3h', 180, 'SEP-1', $rows);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function diffMin(string $from, ?string $to): ?int
    {
        if (!$from || !$to) {
            return null;
        }
        $a = strtotime($from);
        $b = strtotime($to);
        if (!$a || !$b) {
            return null;
        }
        return (int)round(($b - $a) / 60);
    }

    /**
     * Build a measure result from episode rows.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function buildMeasure(string $label, int $targetMin, string $cmsId, array $rows): array
    {
        $n    = count($rows);
        $nMet = count(array_filter($rows, fn($r) => $r['met']));
        $mins = array_column($rows, 'minutes');

        sort($mins);

        $avg    = $n ? (int)round(array_sum($mins) / $n) : null;
        $median = $this->percentile($mins, 50);
        $p90    = $this->percentile($mins, 90);
        $rate   = $n ? round($nMet / $n * 100, 1) : null;

        return [
            'label'      => $label,
            'cms_id'     => $cmsId,
            'target_min' => $targetMin,
            'n'          => $n,
            'n_met'      => $nMet,
            'rate_pct'   => $rate,
            'avg_min'    => $avg,
            'median_min' => $median,
            'p90_min'    => $p90,
            'rows'       => $rows,
        ];
    }

    private function emptyMeasure(string $label, int $targetMin): array
    {
        return [
            'label' => $label, 'cms_id' => '', 'target_min' => $targetMin,
            'n' => 0, 'n_met' => 0, 'rate_pct' => null,
            'avg_min' => null, 'median_min' => null, 'p90_min' => null,
            'rows' => [],
        ];
    }

    /**
     * Compute percentile from a sorted array of values.
     */
    private function percentile(array $sorted, int $pct): ?int
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }
        $idx = (int)ceil($pct / 100 * $n) - 1;
        return (int)$sorted[max(0, min($idx, $n - 1))];
    }
}

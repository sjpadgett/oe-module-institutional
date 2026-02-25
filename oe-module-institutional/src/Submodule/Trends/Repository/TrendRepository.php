<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Trends\Repository;

/**
 * TrendRepository
 *
 * Aggregates historical episode data into periodic trend series for
 * operational intelligence dashboards.
 *
 * All queries read from tables that exist in v0.9.5 schema.
 * No new tables required.
 *
 * Series returned:
 *
 *  computeTrends()  — multi-metric time series (weekly or monthly)
 *    Per period:
 *      period_label    string   "W12 2026" or "Jan 2026"
 *      period_start    string   Y-m-d
 *      volume          int      total episodes in period
 *      lwbs_count      int      episodes closed as LWBS
 *      lwbs_rate       float    lwbs_count / volume %
 *      avg_d2r         int|null avg door-to-room minutes
 *      avg_d2p         int|null avg door-to-provider minutes
 *      obs_count       int      episodes with type OBS
 *      obs_rate        float    obs_count / volume %
 *      sepsis_count    int      episodes with any SEPSIS_RISK-equivalent vitals
 *                               (qSOFA >= 2 on any triage record in the period)
 *
 *  computeHeatmap()  — 7×24 volume heatmap
 *    Returns array[day_of_week 1-7][hour 0-23] = count
 *    Day 1 = Sunday per MySQL DAYOFWEEK()
 */
final class TrendRepository
{
    /**
     * Compute trend series for a facility.
     *
     * @param  int    $facilityId
     * @param  string $granularity  'week' or 'month'
     * @param  int    $periods      Number of periods to look back (default 13 weeks or 12 months)
     * @return array<int,array<string,mixed>>  Oldest first
     */
    public function computeTrends(
        int    $facilityId,
        string $granularity = 'week',
        int    $periods     = 13
    ): array {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $granularity = ($granularity === 'month') ? 'month' : 'week';
        $periods     = max(2, min(52, $periods));

        // Build period boundaries in PHP for consistent labelling
        $slots = $this->buildPeriodSlots($granularity, $periods);
        if (empty($slots)) {
            return [];
        }

        $earliest = $slots[0]['start'];
        $latest   = date('Y-m-d H:i:s'); // now

        // ── Base episode data ─────────────────────────────────────────────────
        $res = sqlStatement(
            "SELECT
                e.id,
                e.start_datetime,
                e.disposition,
                e.type,
                MIN(CASE WHEN ev.event_type = 'ARRIVE' THEN ev.event_datetime END) AS ts_arrive,
                MIN(CASE WHEN ev.event_type IN ('ROOM','ROOMED') THEN ev.event_datetime END) AS ts_room,
                MIN(CASE WHEN ev.event_type = 'PROVIDER' THEN ev.event_datetime END) AS ts_provider
             FROM oei_episode e
             LEFT JOIN oei_episode_event ev ON ev.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.start_datetime >= ?
               AND e.start_datetime <= ?
             GROUP BY e.id, e.start_datetime, e.disposition, e.type
             ORDER BY e.start_datetime ASC",
            [$facilityId, $earliest, $latest]
        );

        $episodes = [];
        while ($row = sqlFetchArray($res)) {
            $episodes[] = $row;
        }

        // ── Sepsis data (qSOFA >= 2 on any triage in period) ─────────────────
        $sepsisEpisodes = $this->querySepsisEpisodes($facilityId, $earliest, $latest);

        // ── Aggregate into slots ──────────────────────────────────────────────
        $result = [];

        foreach ($slots as $slot) {
            $slotStart = strtotime($slot['start']);
            $slotEnd   = strtotime($slot['end']);

            $vol    = 0;
            $lwbs   = 0;
            $obs    = 0;
            $d2r    = [];
            $d2p    = [];

            foreach ($episodes as $ep) {
                $ts = strtotime((string)$ep['start_datetime']);
                if ($ts < $slotStart || $ts >= $slotEnd) {
                    continue;
                }

                $vol++;

                $dispo = strtoupper((string)($ep['disposition'] ?? ''));
                if ($dispo === 'LWBS' || $dispo === 'ELOPE') {
                    $lwbs++;
                }

                if (strtoupper((string)($ep['type'] ?? '')) === 'OBS') {
                    $obs++;
                }

                // Door-to-room
                if ($ep['ts_arrive'] && $ep['ts_room']) {
                    $diff = (strtotime((string)$ep['ts_room']) - strtotime((string)$ep['ts_arrive'])) / 60;
                    if ($diff >= 0 && $diff < 480) { // cap at 8h to exclude outliers
                        $d2r[] = $diff;
                    }
                }

                // Door-to-provider
                if ($ep['ts_arrive'] && $ep['ts_provider']) {
                    $diff = (strtotime((string)$ep['ts_provider']) - strtotime((string)$ep['ts_arrive'])) / 60;
                    if ($diff >= 0 && $diff < 480) {
                        $d2p[] = $diff;
                    }
                }
            }

            // Sepsis count for slot
            $sepsis = 0;
            foreach ($sepsisEpisodes as $sep) {
                $ts = strtotime((string)$sep['start_datetime']);
                if ($ts >= $slotStart && $ts < $slotEnd) {
                    $sepsis++;
                }
            }

            $result[] = [
                'period_label' => $slot['label'],
                'period_start' => $slot['start'],
                'period_end'   => $slot['end'],
                'volume'       => $vol,
                'lwbs_count'   => $lwbs,
                'lwbs_rate'    => $vol > 0 ? round($lwbs / $vol * 100, 1) : 0.0,
                'avg_d2r'      => $d2r ? (int)round(array_sum($d2r) / count($d2r)) : null,
                'avg_d2p'      => $d2p ? (int)round(array_sum($d2p) / count($d2p)) : null,
                'obs_count'    => $obs,
                'obs_rate'     => $vol > 0 ? round($obs / $vol * 100, 1) : 0.0,
                'sepsis_count' => $sepsis,
                'sepsis_rate'  => $vol > 0 ? round($sepsis / $vol * 100, 1) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * Compute 7×24 patient volume heatmap from historical episodes.
     *
     * Returns a flat array of arrays with keys:
     *   dow   int   1 (Sun) – 7 (Sat) per MySQL DAYOFWEEK()
     *   hour  int   0–23
     *   count int   number of episodes starting in this dow/hour slot
     *
     * @return array<int,array{dow:int,hour:int,count:int}>
     */
    public function computeHeatmap(int $facilityId, int $lookbackDays = 90): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $cutoff = date('Y-m-d', strtotime("-{$lookbackDays} days"));

        $res = sqlStatement(
            "SELECT
                DAYOFWEEK(start_datetime) AS dow,
                HOUR(start_datetime)      AS hour,
                COUNT(*)                  AS cnt
             FROM oei_episode
             WHERE facility_id = ?
               AND start_datetime >= ?
             GROUP BY DAYOFWEEK(start_datetime), HOUR(start_datetime)
             ORDER BY dow ASC, hour ASC",
            [$facilityId, $cutoff]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = [
                'dow'   => (int)$row['dow'],
                'hour'  => (int)$row['hour'],
                'count' => (int)$row['cnt'],
            ];
        }

        return $rows;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Build period slot boundaries for the given granularity.
     *
     * @return array<int,array{label:string,start:string,end:string}>
     */
    private function buildPeriodSlots(string $granularity, int $periods): array
    {
        $slots = [];
        $now   = new \DateTimeImmutable();

        for ($i = $periods - 1; $i >= 0; $i--) {
            if ($granularity === 'month') {
                $start = $now->modify("-{$i} months")->modify('first day of this month')->setTime(0, 0, 0);
                $end   = $start->modify('first day of next month');
                $label = $start->format('M Y');
            } else {
                // ISO week: go back $i weeks from start of current week (Monday)
                $monday = $now->modify("Monday this week")->setTime(0, 0, 0);
                if ((int)$now->format('N') === 1) {
                    // already Monday
                    $monday = $now->setTime(0, 0, 0);
                }
                $start = $monday->modify("-{$i} weeks");
                $end   = $start->modify('+1 week');
                $label = 'W' . $start->format('W') . ' ' . $start->format('Y');
            }

            $slots[] = [
                'label' => $label,
                'start' => $start->format('Y-m-d H:i:s'),
                'end'   => $end->format('Y-m-d H:i:s'),
            ];
        }

        return $slots;
    }

    /**
     * Return episodes from a date range where any triage record has qSOFA >= 2.
     *
     * @return array<int,array{id:int,start_datetime:string}>
     */
    private function querySepsisEpisodes(int $facilityId, string $from, string $to): array
    {
        $res = sqlStatement(
            "SELECT DISTINCT e.id, e.start_datetime
             FROM oei_episode e
             JOIN oei_triage t ON t.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
               AND (
                   (t.gcs IS NOT NULL AND t.gcs < 15) +
                   (t.rr  IS NOT NULL AND t.rr  >= 22) +
                   (t.bp_systolic IS NOT NULL AND t.bp_systolic <= 100)
               ) >= 2
             ORDER BY e.start_datetime ASC",
            [$facilityId, $from, $to]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = ['id' => (int)$row['id'], 'start_datetime' => (string)$row['start_datetime']];
        }
        return $rows;
    }
}

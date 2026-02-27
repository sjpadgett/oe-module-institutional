<?php

namespace OpenEMR\Modules\Institutional\Submodule\Scorecard\Repository;

/**
 * Pulls per-provider throughput metrics from existing episode data.
 *
 * All metrics use oei_episode_event timestamps to compute intervals —
 * the same raw data Throughput uses, just grouped by provider_user_id.
 *
 * Metrics computed per provider:
 *   volume          - total episodes in range
 *   avg_d2r         - door-to-room (ARRIVAL → ROOMED), minutes
 *   avg_d2p         - door-to-provider (ARRIVAL → PROVIDER), minutes
 *   avg_d2d         - door-to-decision (ARRIVAL → DECISION), minutes
 *   avg_d2dc        - door-to-depart (ARRIVAL → DEPART), minutes
 *   lwbs_count      - episodes closed as LWBS
 *   lwbs_rate       - lwbs_count / volume %
 *   esi_dist        - ESI distribution array (1-5 counts)
 *   obs_count       - episodes that transitioned to OBS type
 *   obs_rate        - obs_count / volume %
 *   avg_obs_stay_h  - average OBS length of stay (hours) for OBS episodes
 */
final class ScorecardRepository
{
    /**
     * Returns array keyed by provider_user_id.
     * Each value is a metrics array for that provider.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byProvider(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $dateToEnd = rtrim($dateTo, ' 0:') . ' 23:59:59';

        // ── Episode base + event timestamps ──────────────────────────────────
        // We LEFT JOIN the four key event types so we can compute deltas.
        // event_type values from EpisodeEventRepository: ROOM, ROOMED, PROVIDER, DECISION, DEPART
        $res = sqlStatement(
            "SELECT
                e.id            AS episode_id,
                e.provider_user_id,
                e.start_datetime,
                e.end_datetime,
                e.disposition,
                e.type,
                e.acuity_esi,

                MIN(CASE WHEN ev.event_type = 'ROOMED'   THEN ev.event_datetime END) AS ts_roomed,
                MIN(CASE WHEN ev.event_type = 'PROVIDER' THEN ev.event_datetime END) AS ts_provider,
                MIN(CASE WHEN ev.event_type = 'DECISION' THEN ev.event_datetime END) AS ts_decision,
                MIN(CASE WHEN ev.event_type = 'DEPART'   THEN ev.event_datetime END) AS ts_depart

             FROM oei_episode e
             LEFT JOIN oei_episode_event ev ON ev.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.start_datetime >= ?
               AND e.start_datetime <= ?
               AND e.provider_user_id IS NOT NULL
             GROUP BY e.id, e.provider_user_id, e.start_datetime, e.end_datetime,
                      e.disposition, e.type, e.acuity_esi
             ORDER BY e.provider_user_id, e.start_datetime",
            [$facilityId, $dateFrom, $dateToEnd]
        );

        // Aggregate per provider in PHP
        $providers = [];

        while ($row = sqlFetchArray($res)) {
            $uid = (int)($row['provider_user_id'] ?? 0);
            if ($uid === 0) {
                continue;
            }
            if (!isset($providers[$uid])) {
                $providers[$uid] = $this->emptyMetrics();
            }
            $m =& $providers[$uid];

            $m['volume']++;

            $arrTs = $row['start_datetime'] ? strtotime((string)$row['start_datetime']) : null;

            // Door-to-room
            if ($arrTs && $row['ts_roomed']) {
                $diff = (strtotime((string)$row['ts_roomed']) - $arrTs) / 60;
                if ($diff >= 0) {
                    $m['_d2r'][] = $diff;
                }
            }
            // Door-to-provider
            if ($arrTs && $row['ts_provider']) {
                $diff = (strtotime((string)$row['ts_provider']) - $arrTs) / 60;
                if ($diff >= 0) {
                    $m['_d2p'][] = $diff;
                }
            }
            // Door-to-decision
            if ($arrTs && $row['ts_decision']) {
                $diff = (strtotime((string)$row['ts_decision']) - $arrTs) / 60;
                if ($diff >= 0) {
                    $m['_d2d'][] = $diff;
                }
            }
            // Door-to-depart
            if ($arrTs && $row['ts_depart']) {
                $diff = (strtotime((string)$row['ts_depart']) - $arrTs) / 60;
                if ($diff >= 0) {
                    $m['_d2dc'][] = $diff;
                }
            }

            // LWBS
            $dispo = strtoupper((string)($row['disposition'] ?? ''));
            if ($dispo === 'LWBS' || $dispo === 'ELOPE') {
                $m['lwbs_count']++;
            }

            // Obs
            if (strtoupper((string)($row['type'] ?? '')) === 'OBS') {
                $m['obs_count']++;
                // Obs length of stay
                if ($row['end_datetime'] && $row['start_datetime']) {
                    $h = (strtotime((string)$row['end_datetime']) - strtotime((string)$row['start_datetime'])) / 3600;
                    if ($h >= 0) {
                        $m['_obs_h'][] = $h;
                    }
                }
            }

            // ESI distribution
            $esi = (int)($row['acuity_esi'] ?? 0);
            if ($esi >= 1 && $esi <= 5) {
                $m['esi_dist'][$esi] = ($m['esi_dist'][$esi] ?? 0) + 1;
            }
        }

        // Compute averages and rates, clean up accumulators
        foreach ($providers as $uid => &$m) {
            $m['avg_d2r']        = $this->avg($m['_d2r']);
            $m['avg_d2p']        = $this->avg($m['_d2p']);
            $m['avg_d2d']        = $this->avg($m['_d2d']);
            $m['avg_d2dc']       = $this->avg($m['_d2dc']);
            $m['avg_obs_stay_h'] = $this->avg($m['_obs_h']);
            $m['lwbs_rate']      = $m['volume'] > 0 ? round($m['lwbs_count'] / $m['volume'] * 100, 1) : 0;
            $m['obs_rate']       = $m['volume'] > 0 ? round($m['obs_count']  / $m['volume'] * 100, 1) : 0;

            // Trend data: daily volume for sparkline (last 30 days or date range)
            unset($m['_d2r'], $m['_d2p'], $m['_d2d'], $m['_d2dc'], $m['_obs_h']);
        }
        unset($m);

        return $providers;
    }

    /**
     * Fetch provider names from OpenEMR users table.
     * @return array<int,string>  uid => "Last, First"
     */
    public function providerNames(array $uids): array
    {
        if (!function_exists('sqlStatement') || empty($uids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($uids), '?'));
        $res = sqlStatement(
            "SELECT id, fname, lname FROM users WHERE id IN ({$placeholders})",
            array_values($uids)
        );
        $names = [];
        while ($row = sqlFetchArray($res)) {
            $names[(int)$row['id']] = trim((string)$row['lname'] . ', ' . (string)$row['fname']);
        }
        return $names;
    }

    /**
     * Daily volume per provider for sparkline — returns
     * array<int, array<string,int>>  uid => ['YYYY-MM-DD' => count]
     */
    public function dailyVolume(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $dateToEnd = rtrim($dateTo, ' 0:') . ' 23:59:59';
        $res = sqlStatement(
            "SELECT provider_user_id, DATE(start_datetime) AS day, COUNT(*) AS c
             FROM oei_episode
             WHERE facility_id = ?
               AND start_datetime >= ? AND start_datetime <= ?
               AND provider_user_id IS NOT NULL
             GROUP BY provider_user_id, day
             ORDER BY provider_user_id, day",
            [$facilityId, $dateFrom, $dateToEnd]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $uid = (int)$row['provider_user_id'];
            $out[$uid][(string)$row['day']] = (int)$row['c'];
        }
        return $out;
    }

    // -----------------------------------------------------------------------

    private function emptyMetrics(): array
    {
        return [
            'volume'        => 0,
            'lwbs_count'    => 0,
            'obs_count'     => 0,
            'esi_dist'      => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            '_d2r'          => [],
            '_d2p'          => [],
            '_d2d'          => [],
            '_d2dc'         => [],
            '_obs_h'        => [],
        ];
    }

    private function avg(array $vals): ?float
    {
        $vals = array_filter($vals, fn($v) => is_numeric($v));
        return count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : null;
    }
}



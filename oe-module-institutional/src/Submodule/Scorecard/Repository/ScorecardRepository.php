<?php

namespace OpenEMR\Modules\Institutional\Submodule\Scorecard\Repository;

use OpenEMR\Modules\Institutional\Core\Repository\UserRepository;

/**
 * ScorecardRepository
 *
 * Pulls per-provider throughput metrics from existing episode data.
 */
final class ScorecardRepository
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /**
     * Returns array keyed by provider_user_id with computed metrics.
     *
     * @return array<int,array<string,mixed>>
     */
    public function byProvider(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $dateToEnd = rtrim($dateTo, ' 0:') . ' 23:59:59';

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

            if ($arrTs && $row['ts_roomed']) {
                $diff = (strtotime((string)$row['ts_roomed']) - $arrTs) / 60;
                if ($diff >= 0) $m['_d2r'][] = $diff;
            }
            if ($arrTs && $row['ts_provider']) {
                $diff = (strtotime((string)$row['ts_provider']) - $arrTs) / 60;
                if ($diff >= 0) $m['_d2p'][] = $diff;
            }
            if ($arrTs && $row['ts_decision']) {
                $diff = (strtotime((string)$row['ts_decision']) - $arrTs) / 60;
                if ($diff >= 0) $m['_d2d'][] = $diff;
            }
            if ($arrTs && $row['ts_depart']) {
                $diff = (strtotime((string)$row['ts_depart']) - $arrTs) / 60;
                if ($diff >= 0) $m['_d2dc'][] = $diff;
            }

            $dispo = strtoupper((string)($row['disposition'] ?? ''));
            if ($dispo === 'LWBS' || $dispo === 'ELOPE') {
                $m['lwbs_count']++;
            }

            if (strtoupper((string)($row['type'] ?? '')) === 'OBS') {
                $m['obs_count']++;
                if ($row['end_datetime'] && $row['start_datetime']) {
                    $h = (strtotime((string)$row['end_datetime']) - strtotime((string)$row['start_datetime'])) / 3600;
                    if ($h >= 0) $m['_obs_h'][] = $h;
                }
            }

            $esi = (int)($row['acuity_esi'] ?? 0);
            if ($esi >= 1 && $esi <= 5) {
                $m['esi_dist'][$esi] = ($m['esi_dist'][$esi] ?? 0) + 1;
            }
        }

        foreach ($providers as &$m) {
            $m['avg_d2r']        = $this->avg($m['_d2r']);
            $m['avg_d2p']        = $this->avg($m['_d2p']);
            $m['avg_d2d']        = $this->avg($m['_d2d']);
            $m['avg_d2dc']       = $this->avg($m['_d2dc']);
            $m['avg_obs_stay_h'] = $this->avg($m['_obs_h']);
            $m['lwbs_rate']      = $m['volume'] > 0 ? round($m['lwbs_count'] / $m['volume'] * 100, 1) : 0;
            $m['obs_rate']       = $m['volume'] > 0 ? round($m['obs_count']  / $m['volume'] * 100, 1) : 0;
            unset($m['_d2r'], $m['_d2p'], $m['_d2d'], $m['_d2dc'], $m['_obs_h']);
        }
        unset($m);

        return $providers;
    }

    /**
     * Resolve provider display names for a set of user IDs.
     * Delegates to UserRepository which applies the standard active/username/fname filter.
     *
     * @param  int[]  $uids
     * @return array<int,string>  uid => "Last, First"
     */
    public function providerNames(array $uids): array
    {
        if (empty($uids)) {
            return [];
        }

        $names  = $this->users->namesByIds($uids);
        $result = [];
        foreach ($names as $id => $fullName) {
            // Reformat as "Last, First" for scorecard display
            $parts = explode(' ', $fullName, 2);
            $result[$id] = count($parts) === 2
                ? trim($parts[1]) . ', ' . trim($parts[0])
                : $fullName;
        }
        return $result;
    }

    /**
     * Daily volume per provider for sparkline charts.
     *
     * @return array<int,array<string,int>>  uid => ['YYYY-MM-DD' => count]
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
            'volume'     => 0,
            'lwbs_count' => 0,
            'obs_count'  => 0,
            'esi_dist'   => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            '_d2r'       => [],
            '_d2p'       => [],
            '_d2d'       => [],
            '_d2dc'      => [],
            '_obs_h'     => [],
        ];
    }

    private function avg(array $vals): ?float
    {
        $vals = array_filter($vals, fn($v) => is_numeric($v));
        return count($vals) > 0 ? round(array_sum($vals) / count($vals), 1) : null;
    }
}

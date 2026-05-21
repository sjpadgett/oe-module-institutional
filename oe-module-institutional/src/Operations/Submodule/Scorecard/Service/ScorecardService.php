<?php

/**
 * src/Operations/Submodule/Scorecard/Service/ScorecardService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Operations\Submodule\Scorecard\Service;

use OpenEMR\Modules\Institutional\Operations\Submodule\Scorecard\Repository\ScorecardRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

final class ScorecardService
{
    public function __construct(
        private readonly ScorecardRepository $repo,
        private readonly SettingsRepository  $settings
    ) {}

    /**
     * Build the full scorecard payload for the view.
     *
     * @return array{
     *   providers: array<int,array<string,mixed>>,
     *   names: array<int,string>,
     *   benchmarks: array<string,mixed>,
     *   targets: array<string,mixed>,
     *   sort: string,
     *   daily: array<int,array<string,int>>
     * }
     */
    public function build(int $facilityId, string $dateFrom, string $dateTo, string $sortBy = 'volume'): array
    {
        $providers = $this->repo->byProvider($facilityId, $dateFrom, $dateTo);
        $names     = $this->repo->providerNames(array_keys($providers));
        $daily     = $this->repo->dailyVolume($facilityId, $dateFrom, $dateTo);

        // Facility-wide benchmarks (averages across all providers)
        $benchmarks = $this->computeBenchmarks($providers);

        // Targets from settings
        $all     = $this->settings->all($facilityId);
        $targets = [
            'd2r' => (int)($all['door_to_room_target_min']     ?? 30),
            'd2p' => (int)($all['door_to_provider_target_min'] ?? 60),
        ];

        // Sort
        $sorted = $this->sort($providers, $names, $sortBy);

        return [
            'providers'  => $sorted,
            'names'      => $names,
            'benchmarks' => $benchmarks,
            'targets'    => $targets,
            'sort'       => $sortBy,
            'daily'      => $daily,
        ];
    }

    // -----------------------------------------------------------------------

    /** @param array<int,array<string,mixed>> $providers */
    private function computeBenchmarks(array $providers): array
    {
        if (empty($providers)) {
            return ['avg_d2r' => null, 'avg_d2p' => null, 'avg_d2d' => null, 'avg_d2dc' => null,
                    'total_volume' => 0, 'total_lwbs' => 0, 'lwbs_rate' => 0.0,
                    'total_obs' => 0, 'obs_rate' => 0.0];
        }

        $d2r = $d2p = $d2d = $d2dc = [];
        $vol = $lwbs = $obs = 0;

        foreach ($providers as $m) {
            $vol  += $m['volume'];
            $lwbs += $m['lwbs_count'];
            $obs  += $m['obs_count'];
            if ($m['avg_d2r']  !== null) $d2r[]  = $m['avg_d2r'];
            if ($m['avg_d2p']  !== null) $d2p[]  = $m['avg_d2p'];
            if ($m['avg_d2d']  !== null) $d2d[]  = $m['avg_d2d'];
            if ($m['avg_d2dc'] !== null) $d2dc[] = $m['avg_d2dc'];
        }

        $avg = fn(array $a): ?float => count($a) ? round(array_sum($a) / count($a), 1) : null;

        return [
            'avg_d2r'      => $avg($d2r),
            'avg_d2p'      => $avg($d2p),
            'avg_d2d'      => $avg($d2d),
            'avg_d2dc'     => $avg($d2dc),
            'total_volume' => $vol,
            'total_lwbs'   => $lwbs,
            'lwbs_rate'    => $vol > 0 ? round($lwbs / $vol * 100, 1) : 0.0,
            'total_obs'    => $obs,
            'obs_rate'     => $vol > 0 ? round($obs  / $vol * 100, 1) : 0.0,
        ];
    }

    /**
     * Sort providers array by the given metric key.
     * @param  array<int,array<string,mixed>> $providers
     * @param  array<int,string>              $names
     * @return array<int,array<string,mixed>>
     */
    private function sort(array $providers, array $names, string $sortBy): array
    {
        uasort($providers, function (array $a, array $b) use ($sortBy, $names): int {
            return match ($sortBy) {
                'name'      => strcmp((string)($names[array_key_first([$a])] ?? ''), (string)($names[array_key_first([$b])] ?? '')),
                'd2p'       => $this->cmpNullable($a['avg_d2p'],  $b['avg_d2p']),
                'd2r'       => $this->cmpNullable($a['avg_d2r'],  $b['avg_d2r']),
                'd2d'       => $this->cmpNullable($a['avg_d2d'],  $b['avg_d2d']),
                'lwbs_rate' => $b['lwbs_rate'] <=> $a['lwbs_rate'],  // highest rate first (worst)
                'obs_rate'  => $b['obs_rate']  <=> $a['obs_rate'],
                default     => $b['volume'] <=> $a['volume'],  // volume DESC
            };
        });

        return $providers;
    }

    private function cmpNullable(?float $a, ?float $b): int
    {
        if ($a === null && $b === null) return 0;
        if ($a === null) return 1;
        if ($b === null) return -1;
        return $a <=> $b;
    }
}




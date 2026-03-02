<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Trends\Service;

use OpenEMR\Modules\Institutional\Submodule\Trends\Repository\TrendRepository;

/**
 * TrendsService
 *
 * Prepares all view-model data for the Operational Trends dashboard:
 * - Chart.js JSON series (volume, LWBS rate, D2R, D2P, sepsis, OBS rate)
 * - 7×24 heatmap matrix indexed by [dow][hour]
 * - Period-over-period summary comparison (last vs previous)
 */
final class TrendsService
{
    public function __construct(
        private readonly TrendRepository $repo
    ) {}

    /**
     * Build the complete view model for trends.php.
     *
     * @return array<string,mixed>
     */
    public function buildViewModel(int $facilityId, string $granularity, int $periods): array
    {
        $series  = $this->repo->computeTrends($facilityId, $granularity, $periods);
        $heatmap = $this->repo->computeHeatmap($facilityId, 90);

        return [
            'series'      => $series,
            'chartJson'   => $this->buildChartJson($series),
            'heatmap'     => $this->buildHeatmapMatrix($heatmap),
            'heatmapMax'  => $this->heatmapMax($heatmap),
            'dayNames'    => [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed',
                              5 => 'Thu', 6 => 'Fri', 7 => 'Sat'],
            'summary'     => $this->buildPeriodSummary($series),
            'granularity' => $granularity,
            'periods'     => $periods,
        ];
    }

    /**
     * Build Chart.js-ready JSON strings from trend series.
     *
     * @param  array<int,array<string,mixed>> $series
     * @return array<string,string>  Keys: labels, volume, lwbsRate, d2r, d2p, sepsis, obsRate
     */
    public function buildChartJson(array $series): array
    {
        $nullSafe = fn(?int $v): mixed => $v === null ? null : $v;

        return [
            'labels'   => json_encode(array_column($series, 'period_label')),
            'volume'   => json_encode(array_column($series, 'volume')),
            'lwbsRate' => json_encode(array_column($series, 'lwbs_rate')),
            'd2r'      => json_encode(array_map(fn($r) => $nullSafe($r['avg_d2r'] ?? null), $series)),
            'd2p'      => json_encode(array_map(fn($r) => $nullSafe($r['avg_d2p'] ?? null), $series)),
            'sepsis'   => json_encode(array_column($series, 'sepsis_count')),
            'obsRate'  => json_encode(array_column($series, 'obs_rate')),
        ];
    }

    /**
     * Build the [dow 1-7][hour 0-23] heatmap matrix.
     *
     * @param  array<int,array{dow:int,hour:int,count:int}> $heatmap
     * @return array<int,array<int,int>>
     */
    public function buildHeatmapMatrix(array $heatmap): array
    {
        $matrix = [];
        for ($d = 1; $d <= 7; $d++) {
            for ($h = 0; $h <= 23; $h++) {
                $matrix[$d][$h] = 0;
            }
        }
        foreach ($heatmap as $cell) {
            $matrix[$cell['dow']][$cell['hour']] = $cell['count'];
        }
        return $matrix;
    }

    /**
     * Return the maximum cell value across the heatmap (for colour scaling).
     *
     * @param array<int,array{dow:int,hour:int,count:int}> $heatmap
     */
    public function heatmapMax(array $heatmap): int
    {
        if (empty($heatmap)) {
            return 1;
        }
        return (int)max(array_column($heatmap, 'count'));
    }

    /**
     * Compare the last complete period against the previous one.
     *
     * Returns an array of metrics with 'current', 'previous', and 'delta' keys,
     * suitable for rendering trend arrows on the dashboard summary row.
     *
     * @param  array<int,array<string,mixed>> $series  Oldest first
     * @return array<string,array{current:mixed,previous:mixed,delta:mixed}>
     */
    public function buildPeriodSummary(array $series): array
    {
        $count = count($series);
        if ($count < 2) {
            return [];
        }

        $last = $series[$count - 1];
        $prev = $series[$count - 2];

        $diff = fn(string $key): mixed =>
            ($last[$key] !== null && $prev[$key] !== null)
                ? round((float)$last[$key] - (float)$prev[$key], 1)
                : null;

        return [
            'volume'    => ['current' => $last['volume'],    'previous' => $prev['volume'],    'delta' => $diff('volume')],
            'lwbs_rate' => ['current' => $last['lwbs_rate'], 'previous' => $prev['lwbs_rate'], 'delta' => $diff('lwbs_rate')],
            'avg_d2r'   => ['current' => $last['avg_d2r'],   'previous' => $prev['avg_d2r'],   'delta' => $diff('avg_d2r')],
            'avg_d2p'   => ['current' => $last['avg_d2p'],   'previous' => $prev['avg_d2p'],   'delta' => $diff('avg_d2p')],
            'sepsis'    => ['current' => $last['sepsis_count'], 'previous' => $prev['sepsis_count'], 'delta' => $diff('sepsis_count')],
            'obs_rate'  => ['current' => $last['obs_rate'],  'previous' => $prev['obs_rate'],  'delta' => $diff('obs_rate')],
        ];
    }
}

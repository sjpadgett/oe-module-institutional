<?php

/**
 * src/Inpatient/Submodule/FloorBoard/Controller/FloorBoardController.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\FloorBoard\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\FloorBoard\Repository\FloorBoardRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

/**
 * FloorBoardController
 *
 * Supplies the view-model for the IP floor board page.
 * No POST handling — all row-level actions (MAR, disposition, discharge)
 * are links to their respective pages.
 */
final class FloorBoardController
{
    public function __construct(
        private readonly FloorBoardRepository $repo = new FloorBoardRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $facilityId): array
    {
        $settings        = (new SettingsRepository())->all($facilityId);
        $rows    = $this->repo->fetchCensus($facilityId, 200, $settings);

        // Batch-fetch flagged observation counts — one query for all episodes
        $obsRepo   = new SharedObservationRepository();
        $eIds      = array_map(fn($r) => (int)$r['episode_id'], $rows);
        $obsCounts = $obsRepo->countFlaggedByEpisodes($eIds);
        foreach ($rows as &$row) {
            $row['obs_flagged_count'] = (int)($obsCounts[(int)$row['episode_id']] ?? 0);
        }
        unset($row);
        $units   = $this->repo->fetchUnitSummary($facilityId);

        $counts = [
            'total'   => count($rows),
            'over_los' => array_sum(array_column(
                array_filter($rows, fn($r) => $r['los_status'] === 'over'),
                'los_days'
            )) > 0 ? count(array_filter($rows, fn($r) => $r['los_status'] === 'over')) : 0,
        ];

        return [
            'rows'   => $rows,
            'units'  => $units,
            'counts' => $counts,
            'csrf'   => CsrfUtils::collectCsrfToken(),
        ];
    }
}










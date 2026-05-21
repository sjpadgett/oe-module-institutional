<?php

/**
 * src/Shared/Submodule/Timeline/Controller/TimelineController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Timeline\Controller;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Timeline\Repository\TimelineRepository;

final class TimelineController
{
    public function __construct(
        private readonly EpisodeRepository  $episodes,
        private readonly TimelineRepository $timeline
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $episodeId): array
    {
        $boardRows = $this->episodes->fetchBoard($facilityId);

        // Default to first active episode
        if ($episodeId === null && !empty($boardRows)) {
            $episodeId = (int)($boardRows[0]['id'] ?? 0);
        }

        $selected = null;
        foreach ($boardRows as $r) {
            if ((int)$r['id'] === $episodeId) {
                $selected = $r;
                break;
            }
        }

        $entries   = [];
        $userNames = [];

        if ($episodeId !== null && $episodeId > 0) {
            $entries = $this->timeline->forEpisode($episodeId);
            // Batch-load user display names for user_ids in entries
            $userIds = array_values(array_unique(array_filter(
                array_column($entries, 'user_id'),
                fn($v) => $v !== null
            )));
            if ($userIds && function_exists('sqlStatement')) {
                $placeholders = implode(',', array_fill(0, count($userIds), '?'));
                $res = sqlStatement(
                    "SELECT id, CONCAT(fname, ' ', lname) AS display_name FROM users WHERE id IN ({$placeholders})",
                    $userIds
                );
                while ($row = sqlFetchArray($res)) {
                    $userNames[(int)$row['id']] = trim((string)$row['display_name']);
                }
            }
        }

        return [
            'boardRows'  => $boardRows,
            'selected'   => $selected,
            'episodeId'  => $episodeId,
            'entries'    => $entries,
            'userNames'  => $userNames,
        ];
    }
}




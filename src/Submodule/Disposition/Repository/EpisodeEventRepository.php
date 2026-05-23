<?php

/**
 * src/Submodule/Disposition/Repository/EpisodeEventRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Disposition\Repository;

final class EpisodeEventRepository
{
    public function addEvent(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $eventType, string $eventDatetime, ?int $userId, ?string $note = null
    ): void {
        if (!function_exists('sqlStatement')) return;
        sqlStatement(
            "INSERT INTO oei_episode_event (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$episodeId, $pid, $eid, $facilityId, $eventType, $eventDatetime, $userId, $note]
        );
    }

    /** @return array<int,array<string,string>> [episode_id => [event_type => first_datetime]] */
    public function firstEventMap(array $episodeIds): array
    {
        if (!function_exists('sqlStatement') || empty($episodeIds)) return [];
        $in = implode(',', array_fill(0, count($episodeIds), '?'));
        $res = sqlStatement(
            "SELECT episode_id, event_type, MIN(event_datetime) AS first_dt
             FROM oei_episode_event WHERE episode_id IN ($in)
             GROUP BY episode_id, event_type",
            array_values($episodeIds)
        );
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $map[(int)$row['episode_id']][(string)$row['event_type']] = (string)$row['first_dt'];
        }
        return $map;
    }

    /** @return array<int,array<string,mixed>> */
    public function forEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT id, event_type, event_datetime, user_id, note FROM oei_episode_event
             WHERE episode_id = ? ORDER BY event_datetime ASC, id ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}






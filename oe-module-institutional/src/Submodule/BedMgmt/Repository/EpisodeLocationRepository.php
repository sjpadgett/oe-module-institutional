<?php

namespace OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository;

final class EpisodeLocationRepository
{
    /** @return array<string,mixed>|null */
    public function getCurrentForEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT id, episode_id, facility_id, location_id, location_code, start_datetime, end_datetime
             FROM oei_episode_location
             WHERE episode_id = ? AND end_datetime IS NULL
             ORDER BY start_datetime DESC, id DESC
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    public function moveEpisode(int $episodeId, int $pid, ?int $eid, int $facilityId, ?int $locationId, ?string $locationCode, ?int $userId, ?string $note): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement("UPDATE oei_episode_location SET end_datetime = ? WHERE episode_id = ? AND end_datetime IS NULL", [$now, $episodeId]);
        sqlStatement(
            "INSERT INTO oei_episode_location (episode_id, pid, eid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)",
            [$episodeId, $pid, $eid, $facilityId, $locationId, $locationCode, $now, $userId, $note]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listCurrentByFacility(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT episode_id, location_id, location_code, start_datetime
             FROM oei_episode_location
             WHERE facility_id = ? AND end_datetime IS NULL",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

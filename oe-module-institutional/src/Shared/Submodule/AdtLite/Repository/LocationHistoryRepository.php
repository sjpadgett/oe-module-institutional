<?php

namespace OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Repository;

final class LocationHistoryRepository
{
    public function closeOpenHistory(int $episodeId, string $now): void
    {
        if (!function_exists('sqlStatement')) return;
        sqlStatement(
            "UPDATE oei_patient_location_history SET end_datetime = ? WHERE episode_id = ? AND end_datetime IS NULL",
            [$now, $episodeId]
        );
    }

    public function openHistory(int $pid, ?int $eid, int $facilityId, int $episodeId, ?int $locationId, string $now, string $reason): void
    {
        if (!function_exists('sqlStatement')) return;
        sqlStatement(
            "INSERT INTO oei_patient_location_history (pid, eid, facility_id, episode_id, location_id, start_datetime, end_datetime, reason)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?)",
            [$pid, $eid, $facilityId, $episodeId, $locationId, $now, $reason]
        );
    }
}

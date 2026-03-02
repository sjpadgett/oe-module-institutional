<?php
namespace OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository;

final class DispositionRepository
{
    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery(
            "SELECT * FROM oei_episode_disposition WHERE episode_id = ? LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    public function upsert(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $code, ?string $destination, ?string $decision, ?string $depart,
        int $admitFlag, ?string $notes, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_episode_disposition
               (episode_id, pid, eid, facility_id, disposition_code, destination,
                decision_datetime, depart_datetime, admit_flag, notes, updated_by_user_id, updated_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               disposition_code=VALUES(disposition_code), destination=VALUES(destination),
               decision_datetime=VALUES(decision_datetime), depart_datetime=VALUES(depart_datetime),
               admit_flag=VALUES(admit_flag), notes=VALUES(notes),
               updated_by_user_id=VALUES(updated_by_user_id), updated_datetime=VALUES(updated_datetime)",
            [$episodeId, $pid, $eid, $facilityId, $code, $destination,
             $decision, $depart, $admitFlag, $notes, $userId, $now]
        );
    }

    /**
     * @param int[] $episodeIds
     * @return array<int,array<string,mixed>>
     */
    public function fetchForEpisodes(array $episodeIds): array
    {
        if (!function_exists('sqlStatement') || empty($episodeIds)) return [];
        $in = implode(',', array_fill(0, count($episodeIds), '?'));
        $res = sqlStatement(
            "SELECT * FROM oei_episode_disposition WHERE episode_id IN ($in)",
            array_values($episodeIds)
        );
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $map[(int)$row['episode_id']] = $row;
        }
        return $map;
    }
}

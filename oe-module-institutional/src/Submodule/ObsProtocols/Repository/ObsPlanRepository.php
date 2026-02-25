<?php
namespace OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository;

final class ObsPlanRepository
{
    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery("SELECT * FROM oei_obs_plan WHERE episode_id=? LIMIT 1", [$episodeId]);
        return $row ?: null;
    }

    public function upsert(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $protocolKey, int $targetHours, int $runwayHours,
        string $protocolJson, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_obs_plan
               (episode_id,pid,eid,facility_id,protocol_key,status,start_datetime,target_hours,runway_hours,protocol_json,updated_by_user_id,updated_datetime)
             VALUES (?,?,?,?,?,'ACTIVE',?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               protocol_key=VALUES(protocol_key),status='ACTIVE',
               target_hours=VALUES(target_hours),runway_hours=VALUES(runway_hours),
               protocol_json=VALUES(protocol_json),
               updated_by_user_id=VALUES(updated_by_user_id),updated_datetime=VALUES(updated_datetime)",
            [$episodeId,$pid,$eid,$facilityId,$protocolKey,$now,$targetHours,$runwayHours,$protocolJson,$userId,$now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listActive(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT op.episode_id, op.pid, op.protocol_key, op.start_datetime, op.target_hours, op.runway_hours, op.status,
                    e.type AS episode_type,
                    (SELECT task_type FROM oei_task t WHERE t.episode_id=op.episode_id AND t.status='OPEN'
                     ORDER BY t.due_datetime ASC LIMIT 1) AS next_task_type,
                    (SELECT due_datetime FROM oei_task t WHERE t.episode_id=op.episode_id AND t.status='OPEN'
                     ORDER BY t.due_datetime ASC LIMIT 1) AS next_task_due
             FROM oei_obs_plan op
             JOIN oei_episode e ON e.id = op.episode_id
             WHERE op.facility_id=? AND op.status='ACTIVE'
             ORDER BY op.start_datetime DESC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}

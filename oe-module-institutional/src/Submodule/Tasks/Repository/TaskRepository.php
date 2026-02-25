<?php
namespace OpenEMR\Modules\Institutional\Submodule\Tasks\Repository;

final class TaskRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listOpenByFacility(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT id, episode_id, pid, task_type, due_datetime, assigned_to_user_id
             FROM oei_task
             WHERE facility_id=? AND status='OPEN'
             ORDER BY due_datetime ASC LIMIT 200",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }

    public function complete(int $taskId, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_task SET status='COMPLETE', completed_datetime=?, assigned_to_user_id=? WHERE id=?",
            [$now, $userId, $taskId]
        );
    }

    public function create(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $type, string $due, ?int $userId, ?string $payloadJson = null
    ): void {
        if (!function_exists('sqlStatement')) return;
        // De-duplicate: skip if same episode+type+due already exists (open or complete)
        $existing = sqlQuery(
            "SELECT id FROM oei_task WHERE episode_id=? AND task_type=? AND due_datetime=? LIMIT 1",
            [$episodeId, $type, $due]
        );
        if ($existing) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_task (episode_id,pid,eid,facility_id,task_type,due_datetime,status,payload_json,created_by_user_id,created_datetime)
             VALUES (?,?,?,?,?,?,'OPEN',?,?,?)",
            [$episodeId,$pid,$eid,$facilityId,$type,$due,$payloadJson,$userId,$now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listByEpisode(int $episodeId, string $status = 'OPEN'): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT * FROM oei_task WHERE episode_id=? AND status=? ORDER BY due_datetime ASC",
            [$episodeId, $status]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}

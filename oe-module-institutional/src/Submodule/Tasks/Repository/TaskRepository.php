<?php

/**
 * src/Submodule/Tasks/Repository/TaskRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Tasks\Repository;

final class TaskRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listOpenByFacility(int $facilityId, int $episodeId = 0): array
    {
        if (!function_exists('sqlStatement')) return [];
        $where = "facility_id=? AND status='OPEN'";
        $params = [$facilityId];
        if ($episodeId > 0) {
            $where .= ' AND episode_id=?';
            $params[] = $episodeId;
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, task_type, due_datetime, assigned_to_user_id
             FROM oei_task
             WHERE {$where}
             ORDER BY due_datetime ASC LIMIT 200",
            $params
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

    /** @return array<int,array<string,mixed>> */
    public function listOpenMarFollowUpByEpisode(int $episodeId): array
    {
        return $this->listOpenMarFollowUps('t.episode_id = ?', [$episodeId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listOpenMarFollowUpByFacility(int $facilityId): array
    {
        return $this->listOpenMarFollowUps('t.facility_id = ?', [$facilityId]);
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function listOpenMarFollowUps(string $where, array $params): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT t.id, t.episode_id, t.pid, t.task_type, t.due_datetime, t.payload_json,
                    t.assigned_to_user_id,
                    CONCAT_WS(' ', pd.fname, pd.lname) AS patient_name
             FROM oei_task t
             LEFT JOIN patient_data pd ON pd.pid = t.pid
             WHERE {$where}
               AND t.status = 'OPEN'
               AND t.task_type IN ('MAR_RETRY_DOSE','MAR_PHARMACY_FOLLOWUP','MAR_EXCEPTION_REVIEW')
             ORDER BY t.due_datetime ASC",
            $params
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $tmp = json_decode((string)$row['payload_json'], true);
                if (is_array($tmp)) {
                    $payload = $tmp;
                }
            }
            $rows[] = [
                'task_id'           => (int)($row['id'] ?? 0),
                'episode_id'        => (int)($row['episode_id'] ?? 0),
                'pid'               => (int)($row['pid'] ?? 0),
                'task_type'         => (string)($row['task_type'] ?? ''),
                'due_datetime'      => (string)($row['due_datetime'] ?? ''),
                'patient_name'      => trim((string)($row['patient_name'] ?? '')),
                'mar_order_id'      => (int)($payload['mar_order_id'] ?? 0),
                'drug_name'         => (string)($payload['drug_name'] ?? ''),
                'ordered_dose'      => (string)($payload['dose'] ?? ''),
                'ordered_unit'      => (string)($payload['unit'] ?? ''),
                'ordered_route'     => (string)($payload['route'] ?? ''),
                'scheduled_datetime'=> (string)($row['due_datetime'] ?? ''),
                'task_label'        => (string)($payload['task_label'] ?? ''),
                'detail'            => (string)($payload['detail'] ?? ''),
            ];
        }
        return $rows;
    }
}







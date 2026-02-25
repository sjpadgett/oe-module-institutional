<?php
namespace OpenEMR\Modules\Institutional\Submodule\BhSafety\Repository;

final class BhSafetyRepository
{
    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery("SELECT * FROM oei_bh_safety WHERE episode_id=? LIMIT 1", [$episodeId]);
        return $row ?: null;
    }

    public function upsert(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $level, int $involuntary, int $violence, int $suicide, int $elopement,
        ?string $precautionsJson, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_bh_safety
               (episode_id,pid,eid,facility_id,observation_level,is_involuntary,risk_violence,risk_suicide,elopement_risk,precautions_json,updated_by_user_id,updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               observation_level=VALUES(observation_level), is_involuntary=VALUES(is_involuntary),
               risk_violence=VALUES(risk_violence), risk_suicide=VALUES(risk_suicide),
               elopement_risk=VALUES(elopement_risk), precautions_json=VALUES(precautions_json),
               updated_by_user_id=VALUES(updated_by_user_id), updated_datetime=VALUES(updated_datetime)",
            [$episodeId,$pid,$eid,$facilityId,$level,$involuntary,$violence,$suicide,$elopement,$precautionsJson,$userId,$now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecentByFacility(int $facilityId, int $limit = 50): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT bhs.*, e.pid FROM oei_bh_safety bhs
             JOIN oei_episode e ON e.id = bhs.episode_id
             WHERE bhs.facility_id=?
             ORDER BY bhs.updated_datetime DESC LIMIT " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}

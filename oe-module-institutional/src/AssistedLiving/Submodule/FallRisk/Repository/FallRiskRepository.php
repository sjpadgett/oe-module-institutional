<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Repository;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

/**
 * FallRiskRepository
 *
 * Manages Morse Fall Scale assessment history in oei_fall_risk_assessment.
 *
 * On every new assessment:
 *   1. Writes the scored record.
 *   2. Updates oei_al_episode.fall_risk_level and fall_risk_score so
 *      the board and profile reflect the current classification immediately.
 */
final class FallRiskRepository
{
    /**
     * All assessments for an episode, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByEpisode(int $episodeId, int $limit = 10): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT fra.*,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS assessed_by
             FROM   oei_fall_risk_assessment fra
             LEFT   JOIN users u ON u.id = fra.assessed_by_user_id
                                AND u.active=1 AND u.fname IS NOT NULL
             WHERE  fra.episode_id = ?
             ORDER  BY fra.assessed_datetime DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = $this->castRow($r);
        }

        return $rows;
    }

    /**
     * Most recent assessment for an episode.
     *
     * @return array<string,mixed>|null
     */
    public function getLatest(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }

        $row = sqlQuery(
            "SELECT fra.*,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS assessed_by
             FROM   oei_fall_risk_assessment fra
             LEFT   JOIN users u ON u.id = fra.assessed_by_user_id
                                AND u.active=1 AND u.fname IS NOT NULL
             WHERE  fra.episode_id = ?
             ORDER  BY fra.assessed_datetime DESC, fra.id DESC
             LIMIT  1",
            [$episodeId]
        );

        return $row ? $this->castRow($row) : null;
    }

    /**
     * Record a new Morse Fall Scale assessment.
     * Automatically updates oei_al_episode risk level.
     *
     * MFS items and scores:
     *   fall_history      0 = No,        25 = Yes
     *   secondary_dx      0 = No,        15 = Yes
     *   ambulatory_aid    0 = None/nurse, 15 = Crutch/cane/walker, 30 = Furniture
     *   iv_heparin_lock   0 = No,         20 = Yes
     *   gait              0 = Normal/bedrest, 10 = Weak, 20 = Impaired
     *   mental_status     0 = Knows limits,   15 = Forgets limitations
     *
     * Returns new row id (0 on failure).
     */
    public function record(
        int    $episodeId,
        int    $facilityId,
        ?int   $userId,
        int    $fallHistory,       // 0 or 25
        int    $secondaryDx,       // 0 or 15
        int    $ambulatoryAid,     // 0, 15, or 30
        int    $ivHeparinLock,     // 0 or 20
        int    $gait,              // 0, 10, or 20
        int    $mentalStatus,      // 0 or 15
        ?string $notes
    ): int {
        if (!function_exists('sqlInsert') || !function_exists('sqlStatement')) {
            return 0;
        }

        $total     = $fallHistory + $secondaryDx + $ambulatoryAid + $ivHeparinLock + $gait + $mentalStatus;
        $riskLevel = FallRiskLevel::fromMorseScore($total);

        $id = sqlInsert(
            "INSERT INTO oei_fall_risk_assessment
                (episode_id, facility_id, assessed_by_user_id, assessed_datetime,
                 mfs_fall_history, mfs_secondary_dx, mfs_ambulatory_aid,
                 mfs_iv_heparin_lock, mfs_gait, mfs_mental_status,
                 total_score, risk_level, notes)
             VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,?)",
            [
                $episodeId, $facilityId, $userId,
                $fallHistory, $secondaryDx, $ambulatoryAid,
                $ivHeparinLock, $gait, $mentalStatus,
                $total, $riskLevel, $notes,
            ]
        );

        if ($id) {
            // Propagate to board overlay immediately
            sqlStatement(
                "UPDATE oei_al_episode
                 SET fall_risk_level = ?, fall_risk_score = ?
                 WHERE episode_id = ?",
                [$riskLevel, $total, $episodeId]
            );
        }

        return (int)$id;
    }

    /**
     * Days since the last assessment (null if never assessed).
     */
    public function daysSinceLastAssessment(int $episodeId): ?int
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }

        $row = sqlQuery(
            "SELECT DATEDIFF(NOW(), MAX(assessed_datetime)) AS days
             FROM   oei_fall_risk_assessment
             WHERE  episode_id = ?",
            [$episodeId]
        );

        return ($row && $row['days'] !== null) ? (int)$row['days'] : null;
    }

    /**
     * @param array<string,mixed> $r
     * @return array<string,mixed>
     */
    private function castRow(array $r): array
    {
        return [
            'id'                => (int)$r['id'],
            'episode_id'        => (int)$r['episode_id'],
            'assessed_datetime' => (string)$r['assessed_datetime'],
            'assessed_by'       => trim((string)($r['assessed_by'] ?? '')),
            'mfs_fall_history'  => (int)$r['mfs_fall_history'],
            'mfs_secondary_dx'  => (int)$r['mfs_secondary_dx'],
            'mfs_ambulatory_aid'=> (int)$r['mfs_ambulatory_aid'],
            'mfs_iv_heparin_lock'=> (int)$r['mfs_iv_heparin_lock'],
            'mfs_gait'          => (int)$r['mfs_gait'],
            'mfs_mental_status' => (int)$r['mfs_mental_status'],
            'total_score'       => (int)$r['total_score'],
            'risk_level'        => (string)$r['risk_level'],
            'notes'             => (string)($r['notes'] ?? ''),
        ];
    }
}

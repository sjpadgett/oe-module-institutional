<?php

/**
 * src/AssistedLiving/Submodule/AlDischarge/Repository/AlDischargeRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Repository;

/**
 * AlDischargeRepository
 *
 * Thin data layer for the AL Discharge / Transfer Planning submodule.
 * Wraps oei_episode_disposition (shared with ED dispositions) plus
 * AL-specific header queries on oei_episode + oei_al_episode.
 *
 * The shared DispositionRepository::upsert() is reused for writes.
 * This class provides AL-oriented reads that the ED repo doesn't need.
 */
final class AlDischargeRepository
{
    // ── Disposition plan ─────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    public function getPlan(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            'SELECT d.*, u.fname AS updated_by_fname, u.lname AS updated_by_lname
             FROM   oei_episode_disposition d
             LEFT   JOIN users u ON u.id = d.updated_by_user_id
             WHERE  d.episode_id = ?
             LIMIT  1',
            [$episodeId]
        );
        return $row ?: null;
    }

    /** Insert or update the discharge plan (does NOT close the episode). */
    public function savePlan(
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $code,
        ?string $destination,
        ?string $decisionDatetime,
        ?string $notes,
        ?int    $userId
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            'INSERT INTO oei_episode_disposition
               (episode_id, pid, eid, facility_id, disposition_code, destination,
                decision_datetime, depart_datetime, admit_flag, notes,
                updated_by_user_id, updated_datetime)
             VALUES (?, ?, NULL, ?, ?, ?, ?, NULL, 0, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               disposition_code     = VALUES(disposition_code),
               destination          = VALUES(destination),
               decision_datetime    = VALUES(decision_datetime),
               notes                = VALUES(notes),
               updated_by_user_id   = VALUES(updated_by_user_id),
               updated_datetime     = VALUES(updated_datetime)',
            [$episodeId, $pid, $facilityId, $code, $destination,
             $decisionDatetime, $notes, $userId, $now]
        );
    }

    /**
     * Stamp depart_datetime on the existing plan row.
     * The controller calls EpisodeRepository::closeWithDisposition() separately.
     */
    public function confirmDeparture(
        int     $episodeId,
        string  $departDatetime,
        ?int    $userId
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            'UPDATE oei_episode_disposition
             SET    depart_datetime    = ?,
                    updated_by_user_id = ?,
                    updated_datetime   = ?
             WHERE  episode_id = ?',
            [$departDatetime, $userId, $now, $episodeId]
        );
    }

    // ── Resident header ───────────────────────────────────────────────────────

    /**
     * Minimal header row for the discharge page banner.
     * @return array<string,mixed>|null
     */
    public function getResidentHeader(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT e.id AS episode_id, e.pid, e.status, e.start_datetime,
                    e.end_datetime, e.disposition AS closed_disposition,
                    pd.fname, pd.lname, pd.DOB, pd.sex,
                    ale.room, ale.unit, ale.care_level, ale.fall_risk_level,
                    ale.admit_reason,
                    TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE()) AS age,
                    DATEDIFF(COALESCE(e.end_datetime, NOW()), e.start_datetime) AS days_resident
             FROM   oei_episode e
             INNER  JOIN patient_data pd  ON pd.pid = e.pid
             LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE  e.id = ? AND e.type = 'AL'
             LIMIT  1",
            [$episodeId]
        );
        return $row ?: null;
    }

    // ── Discharge history (facility-level summary) ────────────────────────────

    /**
     * Recent AL discharges for the facility — used by board to show
     * recent departures without loading the full ED disposition page.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getRecentDischarges(int $facilityId, int $limit = 20): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT e.id AS episode_id, e.pid, e.end_datetime,
                    e.disposition AS closed_disposition,
                    d.disposition_code, d.destination, d.depart_datetime,
                    pd.fname, pd.lname,
                    ale.room, ale.unit,
                    DATEDIFF(e.end_datetime, e.start_datetime) AS los_days
             FROM   oei_episode e
             INNER  JOIN patient_data pd  ON pd.pid = e.pid
             LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
             LEFT   JOIN oei_episode_disposition d ON d.episode_id = e.id
             WHERE  e.facility_id = ?
               AND  e.type        = 'AL'
               AND  e.status      = 'CLOSED'
             ORDER  BY e.end_datetime DESC
             LIMIT  " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}




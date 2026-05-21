<?php

/**
 * src/HomeBased/Submodule/HbcDischarge/Repository/HbcDischargeRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcDischarge\Repository;

/**
 * HbcDischargeRepository
 *
 * Thin data layer for HBC discharge/closure planning.
 * Writes to oei_episode_disposition (same shared table as AL/ED dispositions).
 * Header query reads oei_hbc_episode for address/clinician context
 * instead of oei_al_episode for room/unit.
 *
 * Reuses the same two-stage pattern as AlDischargeRepository:
 *   savePlan()         — Stage 1: record code + destination + notes
 *   confirmDeparture() — Stage 2: stamp depart_datetime (controller closes episode)
 *   getPlan()          — Read current disposition plan row
 *   getPatientHeader() — Header banner data for the discharge page
 */
final class HbcDischargeRepository
{
    // ── Disposition plan ─────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    public function getPlan(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
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
        if (!function_exists('sqlStatement')) { return; }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            'INSERT INTO oei_episode_disposition
               (episode_id, pid, eid, facility_id, disposition_code, destination,
                decision_datetime, depart_datetime, admit_flag, notes,
                updated_by_user_id, updated_datetime)
             VALUES (?, ?, NULL, ?, ?, ?, ?, NULL, 0, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               disposition_code   = VALUES(disposition_code),
               destination        = VALUES(destination),
               decision_datetime  = VALUES(decision_datetime),
               notes              = VALUES(notes),
               updated_by_user_id = VALUES(updated_by_user_id),
               updated_datetime   = VALUES(updated_datetime)',
            [$episodeId, $pid, $facilityId, $code, $destination,
             $decisionDatetime, $notes, $userId, $now]
        );
    }

    /** Stamp depart_datetime on the plan row. */
    public function confirmDeparture(int $episodeId, string $departDatetime, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) { return; }
        sqlStatement(
            'UPDATE oei_episode_disposition
             SET    depart_datetime    = ?,
                    updated_by_user_id = ?,
                    updated_datetime   = NOW()
             WHERE  episode_id = ?',
            [$departDatetime, $userId, $episodeId]
        );
    }

    // ── Patient header ────────────────────────────────────────────────────────

    /**
     * Header row for the discharge page banner.
     * @return array<string,mixed>|null
     */
    public function getPatientHeader(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $row = sqlQuery(
            "SELECT e.id AS episode_id, e.pid, e.status, e.start_datetime,
                    e.end_datetime, e.disposition AS closed_disposition,
                    pd.fname, pd.lname, pd.DOB,
                    hbc.service_address_line1, hbc.service_city,
                    hbc.service_state_province, hbc.primary_diagnosis,
                    hbc.referral_status, hbc.urgency,
                    hbc.primary_clinician_user_id,
                    CONCAT(COALESCE(uc.fname,''),' ',COALESCE(uc.lname,'')) AS clinician_name,
                    TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE()) AS age,
                    DATEDIFF(COALESCE(e.end_datetime, NOW()), e.start_datetime) AS days_on_service
             FROM   oei_episode e
             JOIN   patient_data pd ON pd.pid = e.pid
             LEFT   JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             LEFT   JOIN users uc ON uc.id = hbc.primary_clinician_user_id AND uc.active = 1
             WHERE  e.id = ? AND e.type = 'HBC'
             LIMIT  1",
            [$episodeId]
        );
        return $row ?: null;
    }
}




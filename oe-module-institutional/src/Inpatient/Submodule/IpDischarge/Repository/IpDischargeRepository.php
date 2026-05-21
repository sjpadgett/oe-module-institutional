<?php

/**
 * src/Inpatient/Submodule/IpDischarge/Repository/IpDischargeRepository.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpDischarge\Repository;

/**
 * IpDischargeRepository
 *
 * Data layer for the IP Discharge / Transfer Planning submodule.
 * Mirrors AlDischargeRepository exactly, substituting oei_ip_episode
 * for oei_al_episode and type='IP' for type='AL'.
 *
 * Plan writes go to oei_episode_disposition (shared table).
 * Episode closure is delegated to EpisodeRepository::closeWithDisposition()
 * which fires the HL7 A03 Discharge event automatically.
 */
final class IpDischargeRepository
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
        ?string $dischargeSummary,
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

        // Store discharge summary in oei_ip_episode if provided
        if ($dischargeSummary !== null && $dischargeSummary !== '') {
            sqlStatement(
                'UPDATE oei_ip_episode
                 SET    discharge_summary = ?
                 WHERE  episode_id = ?',
                [$dischargeSummary, $episodeId]
            );
        }
    }

    /** Stamp depart_datetime on the plan row. Controller closes episode separately. */
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

    // ── Patient header ────────────────────────────────────────────────────────

    /**
     * Header row for the discharge page banner.
     * Returns null if the episode doesn't exist or isn't type='IP'.
     *
     * @return array<string,mixed>|null
     */
    public function getPatientHeader(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT e.id AS episode_id, e.pid, e.status, e.start_datetime,
                    e.end_datetime, e.disposition AS closed_disposition,
                    pd.fname, pd.lname, pd.DOB, pd.sex,
                    TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE()) AS age,
                    COALESCE(ip.bed,            '')         AS bed,
                    COALESCE(ip.unit,           '')         AS unit,
                    COALESCE(ip.service,        'MED_SURG') AS service,
                    COALESCE(ip.admission_type, 'ELECTIVE') AS admission_type,
                    COALESCE(ip.admitting_diagnosis, '')    AS admitting_diagnosis,
                    ip.expected_los_days,
                    ip.discharge_summary,
                    DATEDIFF(COALESCE(e.end_datetime, NOW()), e.start_datetime) AS los_days,
                    CONCAT(COALESCE(att.fname,''), ' ', COALESCE(att.lname,'')) AS attending_name
             FROM   oei_episode e
             INNER  JOIN patient_data pd   ON pd.pid = e.pid
             LEFT   JOIN oei_ip_episode ip ON ip.episode_id = e.id
             LEFT   JOIN users att         ON att.id = ip.attending_user_id
             WHERE  e.id = ? AND e.type = 'IP'
             LIMIT  1",
            [$episodeId]
        );
        return $row ?: null;
    }
}




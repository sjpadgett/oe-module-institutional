<?php

/**
 * src/Inpatient/Submodule/FloorBoard/Repository/FloorBoardRepository.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\FloorBoard\Repository;

/**
 * FloorBoardRepository
 *
 * Fetches the inpatient census for the floor board.
 * Returns all ACTIVE IP episodes for a facility, ordered by unit → bed.
 *
 * Each row includes:
 *   - Patient demographics (name, DOB, gender)
 *   - Bed / unit / service / admission type
 *   - Attending physician name
 *   - LOS (actual days) and expected LOS (from overlay)
 *   - Next open task (type + due datetime)
 *   - Latest workflow status
 *   - Nurse assignment (from oei_episode.assigned_nurse_user_id)
 *   - Disposition plan code (from oei_episode_disposition)
 */
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

final class FloorBoardRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchCensus(int $facilityId, int $limit = 200, array $serviceDefaults = []): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                e.id                                              AS episode_id,
                e.pid,
                e.start_datetime,
                e.chief_complaint,
                pd.fname,
                pd.lname,
                pd.DOB                                            AS dob,
                pd.sex                                            AS gender,

                -- IP overlay
                COALESCE(ip.bed, '')                             AS bed,
                COALESCE(ip.unit, '')                            AS unit,
                COALESCE(ip.service, 'MED_SURG')                AS service,
                COALESCE(ip.admission_type, 'ELECTIVE')         AS admission_type,
                COALESCE(ip.admitting_diagnosis, '')             AS admitting_diagnosis,
                ip.expected_los_days,

                -- LOS in days (computed)
                DATEDIFF(NOW(), e.start_datetime)                AS los_days,

                -- Attending physician name
                CONCAT(
                    COALESCE(att.fname, ''), ' ',
                    COALESCE(att.lname, '')
                )                                                AS attending_name,

                -- Nurse assignment (stored on oei_episode.assigned_nurse_user_id)
                CONCAT(COALESCE(nu.fname,''), ' ', COALESCE(nu.lname,''))
                                                                 AS nurse_name,

                -- Provider assignment (stored on oei_episode.assigned_provider_user_id)
                CONCAT(COALESCE(prov.fname,''), ' ', COALESCE(prov.lname,''))
                                                                 AS provider_name,

                -- Next open task
                (
                    SELECT task_type FROM oei_task t
                    WHERE  t.episode_id = e.id AND t.status = 'OPEN'
                    ORDER BY t.due_datetime ASC, t.id ASC
                    LIMIT  1
                )                                                AS next_task_type,
                (
                    SELECT due_datetime FROM oei_task t
                    WHERE  t.episode_id = e.id AND t.status = 'OPEN'
                    ORDER BY t.due_datetime ASC, t.id ASC
                    LIMIT  1
                )                                                AS next_task_due,

                -- Latest workflow status
                (
                    SELECT sh.status_code
                    FROM   oei_episode_status_history sh
                    WHERE  sh.episode_id = e.id
                    ORDER BY sh.set_datetime DESC, sh.id DESC
                    LIMIT  1
                )                                                AS workflow_status,

                -- Disposition plan (from planning table, not close field)
                (
                    SELECT disposition_code
                    FROM   oei_episode_disposition
                    WHERE  episode_id = e.id
                    LIMIT  1
                )                                                AS plan_disposition_code

             FROM   oei_episode e
             INNER  JOIN patient_data pd      ON pd.pid          = e.pid
             LEFT   JOIN oei_ip_episode ip    ON ip.episode_id   = e.id
             LEFT   JOIN users att            ON att.id          = ip.attending_user_id
             LEFT   JOIN users nu             ON nu.id           = e.assigned_nurse_user_id
                                             AND nu.active       = 1
             LEFT   JOIN users prov           ON prov.id         = e.assigned_provider_user_id
                                             AND prov.active     = 1
             WHERE  e.facility_id = ?
               AND  e.status      = 'ACTIVE'
               AND  e.type        = 'IP'
             ORDER BY
                 COALESCE(ip.unit, '') ASC,
                 COALESCE(ip.bed, '')  ASC,
                 e.start_datetime      ASC
             LIMIT " . (int) $limit,
            [$facilityId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $losActual   = (int) ($row['los_days'] ?? 0);
            $losExpected = ($row['expected_los_days'] !== null) ? (int) $row['expected_los_days'] : null;

            // Apply service-line default LOS when per-episode value not set
            if ($losExpected === null && !empty($serviceDefaults)) {
                $svc = strtolower((string)($row['service'] ?? 'med_surg'));
                $losExpected = match ($svc) {
                    'telemetry' => isset($serviceDefaults['ip_expected_los_telemetry'])
                                   ? (int)$serviceDefaults['ip_expected_los_telemetry'] : null,
                    'icu'       => isset($serviceDefaults['ip_expected_los_icu'])
                                   ? (int)$serviceDefaults['ip_expected_los_icu'] : null,
                    'ortho'     => isset($serviceDefaults['ip_expected_los_ortho'])
                                   ? (int)$serviceDefaults['ip_expected_los_ortho'] : null,
                    default     => isset($serviceDefaults['ip_expected_los_medsurg'])
                                   ? (int)$serviceDefaults['ip_expected_los_medsurg'] : null,
                };
            }

            // LOS status for colour coding
            $losStatus = 'ok';
            if ($losExpected !== null) {
                $warningHours = (int)($serviceDefaults['ip_los_warning_hours'] ?? 24);
                if ($losActual > $losExpected) {
                    $losStatus = 'over';
                } elseif (($losExpected - $losActual) * 24 <= $warningHours) {
                    $losStatus = 'approaching';
                }
            }

            $rows[] = [
                'episode_id'           => (int)    $row['episode_id'],
                'pid'                  => (int)    $row['pid'],
                'fname'                => (string) $row['fname'],
                'lname'                => (string) $row['lname'],
                'dob'                  => (string) ($row['dob'] ?? ''),
                'gender'               => (string) ($row['gender'] ?? ''),
                'start_datetime'       => (string) $row['start_datetime'],
                'chief_complaint'      => (string) ($row['chief_complaint'] ?? ''),
                'bed'                  => (string) $row['bed'],
                'unit'                 => (string) $row['unit'],
                'service'              => (string) $row['service'],
                'admission_type'       => (string) $row['admission_type'],
                'admitting_diagnosis'  => (string) $row['admitting_diagnosis'],
                'expected_los_days'    => $losExpected,
                'los_days'             => $losActual,
                'los_status'           => $losStatus,
                'attending_name'       => trim((string) ($row['attending_name'] ?? '')),
                'nurse_name'           => trim((string) ($row['nurse_name']    ?? '')),
                'provider_name'        => trim((string) ($row['provider_name'] ?? '')),
                'next_task_type'       => ($row['next_task_type']  ?? null),
                'next_task_due'        => ($row['next_task_due']   ?? null),
                'workflow_status'      => ($row['workflow_status'] ?? null),
                'plan_disposition_code'=> ($row['plan_disposition_code'] ?? null),
            ];
        }
        return $rows;
    }

    /**
     * Unit summary — counts and over-LOS alerts for the board header.
     * @return array<string,array{unit:string,total:int,over_los:int}>
     */
    public function fetchUnitSummary(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT
                COALESCE(ip.unit, 'Unassigned')     AS unit,
                COUNT(*)                             AS total,
                SUM(
                    ip.expected_los_days IS NOT NULL
                    AND DATEDIFF(NOW(), e.start_datetime) > ip.expected_los_days
                )                                    AS over_los
             FROM oei_episode e
             LEFT JOIN oei_ip_episode ip ON ip.episode_id = e.id
             WHERE e.facility_id = ? AND e.status = 'ACTIVE' AND e.type = 'IP'
             GROUP BY unit
             ORDER BY unit ASC",
            [$facilityId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $u        = (string) $row['unit'];
            $out[$u]  = [
                'unit'     => $u,
                'total'    => (int) $row['total'],
                'over_los' => (int) ($row['over_los'] ?? 0),
            ];
        }
        return $out;
    }
}







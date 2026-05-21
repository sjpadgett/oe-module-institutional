<?php

/**
 * src/AssistedLiving/Submodule/IncidentReport/Repository/IncidentRepository.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Repository;

final class IncidentRepository
{
    /** All incidents for a facility (newest first). */
    public function listByFacility(int $facilityId, int $limit = 50): array
    {
        if (!function_exists('sqlStatement')) { return []; }

        $res = sqlStatement(
            "SELECT i.id, i.episode_id, i.incident_type, i.severity,
                    i.incident_datetime, i.location_description,
                    i.narrative, i.corrective_action, i.reported_state,
                    i.mandatory_report_sent, i.created_datetime,
                    pd.fname, pd.lname,
                    CONCAT(u.fname,' ',u.lname) AS reported_by
             FROM   oei_incident i
             INNER  JOIN oei_episode e ON e.id = i.episode_id
             INNER  JOIN patient_data pd ON pd.pid = e.pid
             LEFT   JOIN users u ON u.id = i.reported_by_user_id
                               AND u.active=1 AND u.username IS NOT NULL AND u.fname IS NOT NULL
             WHERE  i.facility_id = ?
             ORDER  BY i.incident_datetime DESC
             LIMIT  ?",
            [$facilityId, $limit]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                   => (int)$r['id'],
                'episode_id'           => (int)$r['episode_id'],
                'resident_name'        => trim($r['fname'] . ' ' . $r['lname']),
                'incident_type'        => (string)$r['incident_type'],
                'severity'             => (string)$r['severity'],
                'incident_datetime'    => (string)$r['incident_datetime'],
                'location_description' => (string)($r['location_description'] ?? ''),
                'narrative'            => (string)($r['narrative'] ?? ''),
                'corrective_action'    => (string)($r['corrective_action'] ?? ''),
                'reported_state'       => (string)($r['reported_state'] ?? 'PENDING'),
                'mandatory_report_sent' => (bool)$r['mandatory_report_sent'],
                'reported_by'          => trim((string)$r['reported_by']),
                'created_datetime'     => (string)$r['created_datetime'],
            ];
        }
        return $rows;
    }

    public function create(int $episodeId, int $facilityId, int $userId, array $data): int
    {
        if (!function_exists('sqlInsert')) { return 0; }
        $id = sqlInsert(
            "INSERT INTO oei_incident
                (episode_id, facility_id, reported_by_user_id, incident_type, severity,
                 incident_datetime, location_description, narrative, corrective_action,
                 reported_state, mandatory_report_sent, created_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,'PENDING',?,NOW())",
            [
                $episodeId, $facilityId, $userId,
                $data['incident_type'], $data['severity'],
                $data['incident_datetime'],
                $data['location_description'] ?? '',
                $data['narrative'] ?? '',
                $data['corrective_action'] ?? '',
                (int)($data['mandatory_report_sent'] ?? 0),
            ]
        );
        return (int)$id;
    }

    public function markReported(int $incidentId): void
    {
        if (!function_exists('sqlStatement')) { return; }
        sqlStatement(
            "UPDATE oei_incident SET reported_state = 'REPORTED', mandatory_report_sent = 1 WHERE id = ?",
            [$incidentId]
        );
    }

    public function fetchOne(int $id): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $r = sqlQuery("SELECT * FROM oei_incident WHERE id = ? LIMIT 1", [$id]);
        return $r ?: null;
    }
}




<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Repository;

/**
 * ResidentBoardRepository
 *
 * v0.11.1 — replaced care team LEFT JOINs with correlated subqueries to
 *           prevent row fanout when a care team has multiple members.
 */
final class ResidentBoardRepository
{
    public function fetchActiveResidents(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                e.id            AS episode_id,
                e.pid,
                pd.fname,
                pd.lname,
                pd.DOB          AS dob,
                pd.sex          AS gender,
                e.start_datetime,
                COALESCE(ale.room, '')               AS room,
                COALESCE(ale.unit, '')               AS unit,
                COALESCE(ale.care_level,      'TIER_1') AS care_level,
                COALESCE(ale.fall_risk_level, 'LOW')    AS fall_risk_level,
                COALESCE(ale.fall_risk_score, 0)        AS fall_risk_score,

                (
                    SELECT CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,''))
                    FROM   care_teams ct2
                    JOIN   care_team_member ctm2 ON ctm2.care_team_id = ct2.id
                    JOIN   users u
                        ON  u.id         = ctm2.user_id
                        AND u.authorized = 1
                        AND u.active     = 1
                        AND u.username   IS NOT NULL
                        AND u.fname      IS NOT NULL
                    WHERE  ct2.pid    = e.pid
                      AND  ct2.status = 'active'
                      AND  ctm2.status = 'active'
                    ORDER BY ct2.id ASC, ctm2.id ASC
                    LIMIT 1
                ) AS primary_provider,

                (
                    SELECT CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,''))
                    FROM   care_teams ct3
                    JOIN   care_team_member ctm3 ON ctm3.care_team_id = ct3.id
                    JOIN   users u
                        ON  u.id         = ctm3.user_id
                        AND u.authorized = 0
                        AND u.active     = 1
                        AND u.username   IS NOT NULL
                        AND u.fname      IS NOT NULL
                    WHERE  ct3.pid    = e.pid
                      AND  ct3.status = 'active'
                      AND  ctm3.status = 'active'
                    ORDER BY ct3.id ASC, ctm3.id ASC
                    LIMIT 1
                ) AS primary_nurse,

                (
                    SELECT noted_datetime
                    FROM   oei_adl_record
                    WHERE  episode_id = e.id
                      AND  noted_datetime >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                    ORDER BY noted_datetime DESC, id DESC
                    LIMIT 1
                ) AS last_adl_datetime,

                (
                    SELECT adl_score
                    FROM   oei_adl_record
                    WHERE  episode_id = e.id
                      AND  noted_datetime >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                    ORDER BY noted_datetime DESC, id DESC
                    LIMIT 1
                ) AS last_adl_score,

                DATEDIFF(NOW(), e.start_datetime) AS days_resident

             FROM oei_episode e
             INNER JOIN patient_data pd    ON pd.pid = e.pid
             LEFT  JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.status      = 'ACTIVE'
               AND e.type        = 'AL'
             ORDER BY
                 COALESCE(ale.unit, '') ASC,
                 COALESCE(ale.room, '') ASC,
                 e.start_datetime ASC",
            [$facilityId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = [
                'episode_id'        => (int)$row['episode_id'],
                'pid'               => (int)$row['pid'],
                'fname'             => (string)$row['fname'],
                'lname'             => (string)$row['lname'],
                'dob'               => (string)$row['dob'],
                'gender'            => (string)$row['gender'],
                'start_datetime'    => (string)$row['start_datetime'],
                'room'              => (string)$row['room'],
                'unit'              => (string)$row['unit'],
                'care_level'        => (string)$row['care_level'],
                'fall_risk_level'   => (string)$row['fall_risk_level'],
                'fall_risk_score'   => (int)$row['fall_risk_score'],
                'primary_provider'  => trim((string)($row['primary_provider'] ?? '')),
                'primary_nurse'     => trim((string)($row['primary_nurse']    ?? '')),
                'last_adl_datetime' => $row['last_adl_datetime'] ?: null,
                'last_adl_score'    => ($row['last_adl_score'] !== null) ? (int)$row['last_adl_score'] : null,
                'days_resident'     => (int)$row['days_resident'],
            ];
        }
        return $rows;
    }

    public function fetchUnitSummary(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                COALESCE(ale.unit, 'Unassigned') AS unit,
                COUNT(*)                          AS total,
                SUM(ale.fall_risk_level = 'HIGH') AS high_risk
             FROM oei_episode e
             LEFT JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.status = 'ACTIVE'
               AND e.type   = 'AL'
             GROUP BY unit
             ORDER BY unit ASC",
            [$facilityId]
        );

        $out = [];
        while ($row = sqlFetchArray($res)) {
            $u = (string)$row['unit'];
            $out[$u] = [
                'unit'      => $u,
                'total'     => (int)$row['total'],
                'high_risk' => (int)($row['high_risk'] ?? 0),
            ];
        }
        return $out;
    }
}

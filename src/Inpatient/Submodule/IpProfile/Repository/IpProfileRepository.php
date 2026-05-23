<?php

/**
 * src/Inpatient/Submodule/IpProfile/Repository/IpProfileRepository.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpProfile\Repository;

/**
 * IpProfileRepository
 *
 * Aggregates data for the IP Patient Profile hub.
 * Mirrors ResidentProfileRepository but uses IP-specific queries.
 *
 * Each method is independent — a failure in one section never
 * prevents other panels from rendering.
 */
final class IpProfileRepository
{
    /**
     * Fetch the patient + episode header row.
     * Returns null if the episode doesn't exist or isn't type='IP'.
     *
     * @return array<string,mixed>|null
     */
    public function fetchHeader(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }

        $row = sqlQuery(
            "SELECT
                e.id            AS episode_id,
                e.pid,
                e.facility_id,
                e.start_datetime,
                e.status,
                e.chief_complaint,
                pd.fname,
                pd.lname,
                pd.DOB          AS dob,
                pd.sex          AS gender,
                pd.phone_cell,
                COALESCE(ip.bed,            '')          AS bed,
                COALESCE(ip.unit,           '')          AS unit,
                COALESCE(ip.service,        'MED_SURG')  AS service,
                COALESCE(ip.admission_type, 'ELECTIVE')  AS admission_type,
                COALESCE(ip.admitting_diagnosis, '')     AS admitting_diagnosis,
                ip.admitting_icd10,
                ip.expected_los_days,
                ip.encounter_id,
                DATEDIFF(NOW(), e.start_datetime)        AS los_days,
                CONCAT(COALESCE(att.fname,''), ' ', COALESCE(att.lname,'')) AS attending_name,
                CONCAT(COALESCE(nu.fname,''),  ' ', COALESCE(nu.lname,''))  AS nurse_name,
                CONCAT(COALESCE(prov.fname,''),' ', COALESCE(prov.lname,'')) AS provider_name
             FROM   oei_episode e
             INNER  JOIN patient_data pd    ON pd.pid  = e.pid
             LEFT   JOIN oei_ip_episode ip  ON ip.episode_id = e.id
             LEFT   JOIN users att          ON att.id  = ip.attending_user_id
             LEFT   JOIN users nu           ON nu.id   = e.assigned_nurse_user_id  AND nu.active = 1
             LEFT   JOIN users prov         ON prov.id = e.assigned_provider_user_id AND prov.active = 1
             WHERE  e.id = ? AND e.type = 'IP'
             LIMIT  1",
            [$episodeId]
        );

        if (!$row) {
            return null;
        }

        $row['pid']              = (int)$row['pid'];
        $row['facility_id']      = (int)$row['facility_id'];
        $row['los_days']         = (int)($row['los_days'] ?? 0);
        $row['expected_los_days']= ($row['expected_los_days'] !== null) ? (int)$row['expected_los_days'] : null;
        $row['encounter_id']     = (int)($row['encounter_id'] ?? 0);
        $row['attending_name']   = trim((string)($row['attending_name'] ?? ''));
        $row['nurse_name']       = trim((string)($row['nurse_name']     ?? ''));
        $row['provider_name']    = trim((string)($row['provider_name']  ?? ''));

        return $row;
    }

    /**
     * Fetch recent vitals history (uses oei_triage — same table as ED/AL).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchVitalsHistory(int $episodeId, int $limit = 6): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT bp_systolic, bp_diastolic, hr, rr, temp_f, spo2,
                    weight_kg, pain_score, gcs, noted_datetime
             FROM   oei_triage
             WHERE  episode_id = ?
             ORDER  BY set_number DESC, id DESC
             LIMIT  ?",
            [$episodeId, $limit]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            foreach (['bp_systolic','bp_diastolic','hr','rr','spo2','pain_score','gcs'] as $col) {
                $row[$col] = ($row[$col] !== null) ? (int)$row[$col] : null;
            }
            foreach (['temp_f','weight_kg'] as $col) {
                $row[$col] = ($row[$col] !== null) ? (float)$row[$col] : null;
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch today's MAR summary for the episode.
     *
     * @return array{pending:int,given:int,held:int,overdue:int,pending_drugs:array}
     */
    public function fetchMarToday(int $episodeId): array
    {
        $default = ['pending' => 0, 'given' => 0, 'held' => 0, 'overdue' => 0, 'pending_drugs' => []];
        if (!function_exists('sqlStatement')) {
            return $default;
        }

        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $res = sqlStatement(
            "SELECT a.outcome, a.scheduled_datetime, a.is_high_alert,
                    o.drug_name, o.dose, o.unit, o.route
             FROM   oei_mar_administration a
             JOIN   oei_mar_order o ON o.id = a.mar_order_id
             WHERE  a.episode_id = ?
               AND  DATE(a.scheduled_datetime) = ?
             ORDER  BY a.scheduled_datetime ASC",
            [$episodeId, $today]
        );

        $result = $default;
        while ($row = sqlFetchArray($res)) {
            $outcome = (string)($row['outcome'] ?? 'PENDING');
            if ($outcome === 'GIVEN')   { $result['given']++; continue; }
            if ($outcome === 'HELD')    { $result['held']++;  continue; }
            if ($outcome === 'PENDING') {
                $result['pending']++;
                if (!empty($row['scheduled_datetime']) && $row['scheduled_datetime'] < $now) {
                    $result['overdue']++;
                }
                $result['pending_drugs'][] = [
                    'drug'       => (string)($row['drug_name']   ?? ''),
                    'dose'       => trim((string)($row['dose']   ?? '') . ' ' . (string)($row['unit'] ?? '')),
                    'route'      => (string)($row['route']       ?? ''),
                    'sched'      => substr((string)($row['scheduled_datetime'] ?? ''), 11, 5),
                    'high_alert' => (bool)($row['is_high_alert'] ?? false),
                    'overdue'    => (!empty($row['scheduled_datetime']) && $row['scheduled_datetime'] < $now),
                ];
            }
        }
        return $result;
    }

    /**
     * Fetch active care plan goals and activities for the episode's encounter.
     *
     * @return array{goals:array,activities:array,counts:array}
     */
    public function fetchCarePlanSummary(int $pid, int $encounterId): array
    {
        $default = ['goals' => [], 'activities' => [], 'counts' => ['goals' => 0, 'activities' => 0, 'completed' => 0]];
        if (!function_exists('sqlStatement') || $encounterId === 0) {
            return $default;
        }

        $res = sqlStatement(
            "SELECT id, care_plan_type, description, plan_status, proposed_date
             FROM   form_care_plan
             WHERE  pid = ? AND encounter = ? AND activity = 1
             ORDER  BY care_plan_type ASC, id ASC
             LIMIT  20",
            [$pid, $encounterId]
        );

        $goals = $activities = [];
        while ($row = sqlFetchArray($res)) {
            $type = strtoupper((string)($row['care_plan_type'] ?? ''));
            if ($type === 'goal' || $type === '') {
                $goals[] = $row;
            } else {
                $activities[] = $row;
            }
        }

        $completed = count(array_filter(
            array_merge($goals, $activities),
            fn($r) => strtolower((string)($r['plan_status'] ?? '')) === 'completed'
        ));

        return [
            'goals'      => $goals,
            'activities' => $activities,
            'counts'     => [
                'goals'      => count($goals),
                'activities' => count($activities),
                'completed'  => $completed,
            ],
        ];
    }

    /**
     * Fetch care team for a patient (from OpenEMR care_teams / care_team_member).
     *
     * @return array<int,array{member_name:string,role:string}>
     */
    public function fetchCareTeam(int $pid): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $team = sqlQuery(
            "SELECT id FROM care_teams WHERE pid = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$pid]
        );
        if (!$team) {
            return [];
        }
        $res = sqlStatement(
            "SELECT ctm.role,
                    CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,'')) AS member_name
             FROM   care_team_member ctm
             JOIN   users u ON u.id = ctm.user_id AND u.active = 1
             WHERE  ctm.care_team_id = ? AND ctm.status = 'active'
             ORDER  BY ctm.id ASC",
            [(int)$team['id']]
        );
        $members = [];
        while ($row = sqlFetchArray($res)) {
            $members[] = [
                'member_name' => trim((string)$row['member_name']),
                'role'        => (string)($row['role'] ?? ''),
            ];
        }
        return $members;
    }

    /**
     * Fetch open tasks for the episode (up to 5).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchOpenTasks(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT task_type, due_datetime, assigned_to_user_id
             FROM   oei_task
             WHERE  episode_id = ? AND status = 'OPEN'
             ORDER  BY due_datetime ASC, id ASC
             LIMIT  5",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Latest fall risk assessment summary for the IP profile panel.
     * Returns null if no assessment exists.
     *
     * @return array{risk_level:string, total_score:int, assessed_datetime:string, days_since:int}|null
     */
    public function fetchFallRiskSummary(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT fra.risk_level, fra.total_score, fra.assessed_datetime,
                    DATEDIFF(NOW(), fra.assessed_datetime) AS days_since
             FROM   oei_fall_risk_assessment fra
             WHERE  fra.episode_id = ?
             ORDER  BY fra.assessed_datetime DESC, fra.id DESC
             LIMIT  1",
            [$episodeId]
        );
        if (!$row) {
            return null;
        }
        return [
            'risk_level'          => (string)$row['risk_level'],
            'total_score'         => (int)$row['total_score'],
            'assessed_datetime'   => (string)$row['assessed_datetime'],
            'days_since'          => (int)$row['days_since'],
        ];
    }

    /**
     * Latest observation reading per type for this episode.
     * Returns array keyed by obs_type_code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function fetchLatestObservations(int $episodeId): array
    {
        $obsRepo = new \OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository();
        return $obsRepo->latestPerType($episodeId);
    }
}










<?php

/**
 * src/AssistedLiving/Submodule/ResidentProfile/Repository/ResidentProfileRepository.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentProfile\Repository;

/**
 * ResidentProfileRepository
 *
 * Aggregates all data needed for the single-resident profile view.
 * Uses targeted queries rather than one giant JOIN to keep each fetch
 * readable, debuggable, and cacheable independently.
 *
 * All vitals are read from oei_triage (existing infrastructure) —
 * arrival_mode='PERIODIC' identifies AL monitoring vs ED triage records.
 */
final class ResidentProfileRepository
{
    /**
     * Core episode + patient header data.
     * Returns null if episode not found or not of type AL.
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
                COALESCE(ale.room,           '')       AS room,
                COALESCE(ale.unit,           '')       AS unit,
                COALESCE(ale.care_level,     'TIER_1') AS care_level,
                COALESCE(ale.fall_risk_level,'LOW')    AS fall_risk_level,
                COALESCE(ale.fall_risk_score, 0)       AS fall_risk_score,
                ale.admit_reason,
                ale.encounter_id,
                DATEDIFF(NOW(), e.start_datetime)      AS days_resident
             FROM  oei_episode e
             INNER JOIN patient_data pd    ON pd.pid       = e.pid
             LEFT  JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE e.id = ? AND e.type = 'AL'
             LIMIT 1",
            [$episodeId]
        );

        if (!$row) {
            return null;
        }

        return [
            'episode_id'     => (int)$row['episode_id'],
            'pid'            => (int)$row['pid'],
            'facility_id'    => (int)$row['facility_id'],
            'fname'          => (string)$row['fname'],
            'lname'          => (string)$row['lname'],
            'dob'            => (string)$row['dob'],
            'gender'         => (string)$row['gender'],
            'phone_cell'     => (string)($row['phone_cell'] ?? ''),
            'room'           => (string)$row['room'],
            'unit'           => (string)$row['unit'],
            'care_level'     => (string)$row['care_level'],
            'fall_risk_level'=> (string)$row['fall_risk_level'],
            'fall_risk_score'=> (int)$row['fall_risk_score'],
            'admit_reason'   => (string)($row['admit_reason'] ?? ''),
            'encounter_id'   => (int)($row['encounter_id'] ?? 0),
            'start_datetime' => (string)$row['start_datetime'],
            'days_resident'  => (int)$row['days_resident'],
            'status'         => (string)$row['status'],
            'chief_complaint'=> (string)($row['chief_complaint'] ?? ''),
        ];
    }

    /**
     * Last N vitals sets — newest first.
     * Reads from oei_triage (shared with ED triage).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchVitalsHistory(int $episodeId, int $limit = 6): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT id, set_number, bp_systolic, bp_diastolic, hr, rr,
                    temp_f, spo2, weight_kg, pain_score, notes,
                    noted_datetime, noted_by_user_id
             FROM   oei_triage
             WHERE  episode_id = ?
             ORDER  BY noted_datetime DESC, id DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'            => (int)$r['id'],
                'set_number'    => (int)$r['set_number'],
                'bp_systolic'   => $r['bp_systolic']  !== null ? (int)$r['bp_systolic']    : null,
                'bp_diastolic'  => $r['bp_diastolic'] !== null ? (int)$r['bp_diastolic']   : null,
                'hr'            => $r['hr']            !== null ? (int)$r['hr']             : null,
                'rr'            => $r['rr']            !== null ? (int)$r['rr']             : null,
                'temp_f'        => $r['temp_f']        !== null ? (float)$r['temp_f']       : null,
                'spo2'          => $r['spo2']          !== null ? (int)$r['spo2']           : null,
                'weight_kg'     => $r['weight_kg']     !== null ? (float)$r['weight_kg']    : null,
                'pain_score'    => $r['pain_score']    !== null ? (int)$r['pain_score']     : null,
                'notes'         => (string)($r['notes'] ?? ''),
                'noted_datetime'=> (string)$r['noted_datetime'],
            ];
        }

        return $rows;
    }

    /**
     * ADL history — last N sessions, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAdlHistory(int $episodeId, int $limit = 5): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT r.id, r.adl_score, r.adl_json, r.noted_datetime, r.notes,
                    CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,'')) AS noted_by
             FROM   oei_adl_record r
             LEFT   JOIN users u ON u.id = r.noted_by_user_id
                                AND u.active = 1 AND u.fname IS NOT NULL
             WHERE  r.episode_id = ?
             ORDER  BY r.noted_datetime DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'             => (int)$r['id'],
                'adl_score'      => (int)$r['adl_score'],
                'domain_levels'  => json_decode((string)$r['adl_json'], true) ?: [],
                'noted_datetime' => (string)$r['noted_datetime'],
                'notes'          => (string)($r['notes'] ?? ''),
                'noted_by'       => trim((string)$r['noted_by']),
            ];
        }

        return $rows;
    }

    /**
     * Care plan summary counts + active goals list.
     *
     * @return array{goals: array, activities: array, counts: array}
     */
    public function fetchCarePlanSummary(int $episodeId, int $pid, int $encounterId): array
    {
        if (!function_exists('sqlStatement') || $encounterId === 0) {
            return ['goals' => [], 'activities' => [], 'counts' => ['goals' => 0, 'activities' => 0, 'completed' => 0]];
        }

        $res = sqlStatement(
            "SELECT id, care_plan_type, description, plan_status, proposed_date
             FROM   form_care_plan
             WHERE  pid = ? AND encounter = ? AND activity = 1
             ORDER  BY care_plan_type ASC, id ASC",
            [$pid, $encounterId]
        );

        $goals = [];
        $activities = [];
        $completed = 0;

        while ($r = sqlFetchArray($res)) {
            $item = [
                'id'          => (int)$r['id'],
                'type'        => (string)$r['care_plan_type'],
                'description' => (string)$r['description'],
                'status'      => (string)$r['plan_status'],
                'proposed_date'=> $r['proposed_date'] ?: null,
            ];
            if ((string)$r['plan_status'] === 'completed') {
                $completed++;
            }
            if ((string)$r['care_plan_type'] === 'goal') {
                $goals[] = $item;
            } else {
                $activities[] = $item;
            }
        }

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
     * Today's MAR summary: pending count, given count, held count, overdue count.
     *
     * @return array{pending: int, given: int, held: int, overdue: int, pending_drugs: array}
     */
    public function fetchMarToday(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return ['pending' => 0, 'given' => 0, 'held' => 0, 'overdue' => 0, 'pending_drugs' => []];
        }

        $today = date('Y-m-d');

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

        $pending = 0;
        $given   = 0;
        $held    = 0;
        $overdue = 0;
        $pendingDrugs = [];
        $now = time();

        while ($r = sqlFetchArray($res)) {
            $outcome = (string)$r['outcome'];
            if ($outcome === 'GIVEN') {
                $given++;
            } elseif (in_array($outcome, ['HELD', 'REFUSED'], true)) {
                $held++;
            } elseif ($outcome === 'PENDING') {
                $pending++;
                $sched = strtotime((string)$r['scheduled_datetime']);
                if ($sched && $sched < ($now - 900)) {  // 15 min grace
                    $overdue++;
                }
                $pendingDrugs[] = [
                    'drug_name'      => (string)$r['drug_name'],
                    'dose'           => (string)$r['dose'],
                    'unit'           => (string)$r['unit'],
                    'route'          => (string)$r['route'],
                    'scheduled_time' => date('g:i a', strtotime((string)$r['scheduled_datetime'])),
                    'is_high_alert'  => (bool)$r['is_high_alert'],
                    'overdue'        => ($sched && $sched < ($now - 900)),
                ];
            }
        }

        return [
            'pending'      => $pending,
            'given'        => $given,
            'held'         => $held,
            'overdue'      => $overdue,
            'pending_drugs'=> $pendingDrugs,
        ];
    }

    /**
     * Recent incidents for this episode.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchRecentIncidents(int $episodeId, int $limit = 3): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT id, incident_type, severity, incident_datetime,
                    location_description, narrative, reported_state
             FROM   oei_incident
             WHERE  episode_id = ?
             ORDER  BY incident_datetime DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                  => (int)$r['id'],
                'incident_type'       => (string)$r['incident_type'],
                'severity'            => (string)$r['severity'],
                'incident_datetime'   => (string)$r['incident_datetime'],
                'location_description'=> (string)($r['location_description'] ?? ''),
                'narrative'           => (string)($r['narrative'] ?? ''),
                'reported_state'      => (string)$r['reported_state'],
            ];
        }

        return $rows;
    }

    /**
     * Most recent fall risk assessment for the episode.
     *
     * @return array<string,mixed>|null
     */
    public function fetchLatestFallRisk(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }

        $row = sqlQuery(
            "SELECT fra.*, CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS assessed_by
             FROM   oei_fall_risk_assessment fra
             LEFT   JOIN users u ON u.id = fra.assessed_by_user_id
                                AND u.active=1 AND u.fname IS NOT NULL
             WHERE  fra.episode_id = ?
             ORDER  BY fra.assessed_datetime DESC
             LIMIT  1",
            [$episodeId]
        );

        return $row ?: null;
    }

    /**
     * Care team (from OpenEMR care_teams + care_team_member).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchCareTeam(int $pid): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $team = sqlQuery(
            "SELECT id FROM care_teams WHERE pid=? AND status='active' ORDER BY id DESC LIMIT 1",
            [$pid]
        );

        if (!$team) {
            return [];
        }

        $res = sqlStatement(
            "SELECT ctm.role,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS member_name,
                    COALESCE(lo.title, ctm.role) AS role_label
             FROM   care_team_member ctm
             LEFT   JOIN users u ON u.id = ctm.user_id AND u.active=1 AND u.fname IS NOT NULL
             LEFT   JOIN list_options lo ON lo.list_id='care_team_roles' AND lo.option_id=ctm.role
             WHERE  ctm.care_team_id=? AND ctm.status='active'
             ORDER  BY ctm.id ASC",
            [(int)$team['id']]
        );

        $members = [];
        while ($r = sqlFetchArray($res)) {
            $members[] = [
                'role'        => (string)$r['role'],
                'role_label'  => (string)$r['role_label'],
                'member_name' => trim((string)$r['member_name']),
            ];
        }

        return $members;
    }
}




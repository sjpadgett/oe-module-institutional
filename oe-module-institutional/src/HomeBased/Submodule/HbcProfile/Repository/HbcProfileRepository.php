<?php

/**
 * src/HomeBased/Submodule/HbcProfile/Repository/HbcProfileRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcProfile\Repository;

final class HbcProfileRepository
{
    /** @return array<string,mixed>|null */
    public function fetchHeader(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $row = sqlQuery(
            "SELECT
                e.id            AS episode_id,
                e.pid,
                e.facility_id,
                e.status,
                e.start_datetime,
                pd.fname,
                pd.lname,
                pd.DOB,
                pd.phone_cell,
                pd.phone_home,
                hbc.encounter_id,
                hbc.referral_source,
                hbc.referral_reason,
                hbc.referral_status,
                hbc.urgency,
                hbc.service_address_line1,
                hbc.service_address_line2,
                hbc.service_city,
                hbc.service_state_province,
                hbc.service_postal_code,
                hbc.service_country,
                hbc.access_notes,
                hbc.caregiver_name,
                hbc.caregiver_phone,
                hbc.caregiver_relationship,
                hbc.primary_diagnosis,
                hbc.primary_icd10,
                hbc.payer_name,
                hbc.authorization_notes,
                hbc.soc_datetime,
                hbc.cert_period_start,
                hbc.cert_period_end,
                hbc.authorized_visits_per_week,
                CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name,
                DATEDIFF(NOW(), e.start_datetime) AS los_days
             FROM oei_episode e
             JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             JOIN patient_data pd     ON pd.pid = e.pid
             LEFT JOIN users u        ON u.id = hbc.primary_clinician_user_id AND u.active = 1
             WHERE e.id = ? AND e.type = 'HBC'
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchRecentVisits(int $episodeId, int $limit = 10): array
    {
        if (!function_exists('sqlStatement')) { return []; }
        $res = sqlStatement(
            "SELECT v.id AS visit_id,
                    v.visit_type,
                    v.scheduled_datetime,
                    v.window_start_datetime,
                    v.window_end_datetime,
                    v.route_sequence,
                    v.travel_notes,
                    v.actual_start_datetime,
                    v.actual_end_datetime,
                    v.status,
                    v.is_draft,
                    v.patient_signature_obtained,
                    v.outcome_summary,
                    v.mileage_miles,
                    v.next_visit_due_date,
                    v.next_visit_type,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name
             FROM   oei_hbc_visit v
             LEFT   JOIN users u ON u.id = v.clinician_user_id AND u.active = 1
             WHERE  v.episode_id = ?
               AND  v.status NOT IN ('CANCELED')
             ORDER  BY v.scheduled_datetime DESC, v.id DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'visit_id' => (int)$r['visit_id'],
                'visit_type' => (string)$r['visit_type'],
                'scheduled' => (string)($r['scheduled_datetime'] ?? ''),
                'window_start_datetime' => (string)($r['window_start_datetime'] ?? ''),
                'window_end_datetime' => (string)($r['window_end_datetime'] ?? ''),
                'route_sequence' => $r['route_sequence'] !== null ? (int)$r['route_sequence'] : null,
                'travel_notes' => (string)($r['travel_notes'] ?? ''),
                'actual_start' => (string)($r['actual_start_datetime'] ?? ''),
                'actual_end' => (string)($r['actual_end_datetime'] ?? ''),
                'status' => (string)$r['status'],
                'is_draft' => (bool)$r['is_draft'],
                'sig_obtained' => (bool)$r['patient_signature_obtained'],
                'outcome' => (string)($r['outcome_summary'] ?? ''),
                'mileage' => $r['mileage_miles'] !== null ? (float)$r['mileage_miles'] : null,
                'next_visit_due_date' => (string)($r['next_visit_due_date'] ?? ''),
                'next_visit_type' => (string)($r['next_visit_type'] ?? ''),
                'clinician' => trim((string)($r['clinician_name'] ?? '')),
            ];
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function fetchNextVisit(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $row = sqlQuery(
            "SELECT v.id AS visit_id,
                    v.visit_type,
                    v.scheduled_datetime,
                    v.window_start_datetime,
                    v.window_end_datetime,
                    v.route_sequence,
                    v.travel_notes,
                    v.status,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name
             FROM   oei_hbc_visit v
             LEFT   JOIN users u ON u.id = v.clinician_user_id AND u.active = 1
             WHERE  v.episode_id = ?
               AND  v.status IN ('SCHEDULED','EN_ROUTE','ARRIVED')
               AND  v.scheduled_datetime >= NOW()
             ORDER  BY COALESCE(v.route_sequence, 9999) ASC, v.scheduled_datetime ASC
             LIMIT  1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function fetchLatestVitals(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $row = sqlQuery(
            "SELECT bp_systolic, bp_diastolic, hr, rr, temp_f, spo2,
                    weight_kg, pain_score, gcs, noted_datetime
             FROM   oei_triage
             WHERE  episode_id = ?
             ORDER  BY noted_datetime DESC, id DESC
             LIMIT  1",
            [$episodeId]
        );
        if (!$row) { return null; }
        return [
            'bp_systolic' => $row['bp_systolic'] !== null ? (int)$row['bp_systolic'] : null,
            'bp_diastolic' => $row['bp_diastolic'] !== null ? (int)$row['bp_diastolic'] : null,
            'hr' => $row['hr'] !== null ? (int)$row['hr'] : null,
            'rr' => $row['rr'] !== null ? (int)$row['rr'] : null,
            'temp_f' => $row['temp_f'] !== null ? (float)$row['temp_f'] : null,
            'spo2' => $row['spo2'] !== null ? (int)$row['spo2'] : null,
            'weight_kg' => $row['weight_kg'] !== null ? (float)$row['weight_kg'] : null,
            'pain_score' => $row['pain_score'] !== null ? (int)$row['pain_score'] : null,
            'gcs' => $row['gcs'] !== null ? (int)$row['gcs'] : null,
            'noted_datetime' => (string)($row['noted_datetime'] ?? ''),
        ];
    }


    /** @return array<string,mixed> */
    public function fetchServiceSnapshot(int $episodeId): array
    {
        if (!function_exists('sqlQuery')) {
            return [];
        }

        $counts = sqlQuery(
            "SELECT
                COUNT(*) AS total_visits,
                SUM(CASE WHEN status = 'COMPLETE' THEN 1 ELSE 0 END) AS completed_visits,
                SUM(CASE WHEN status IN ('SCHEDULED','EN_ROUTE','ARRIVED') THEN 1 ELSE 0 END) AS active_visits
             FROM oei_hbc_visit
             WHERE episode_id = ?",
            [$episodeId]
        ) ?: [];

        $latestComplete = sqlQuery(
            "SELECT visit_type, actual_end_datetime, outcome_summary, next_visit_due_date, next_visit_type
             FROM oei_hbc_visit
             WHERE episode_id = ?
               AND status = 'COMPLETE'
             ORDER BY COALESCE(actual_end_datetime, scheduled_datetime) DESC, id DESC
             LIMIT 1",
            [$episodeId]
        ) ?: [];

        $taskCounts = sqlQuery(
            "SELECT
                COUNT(*) AS open_tasks,
                SUM(CASE WHEN task_type = 'HBC_COORDINATION_REVIEW' THEN 1 ELSE 0 END) AS coordination_tasks,
                SUM(CASE WHEN task_type = 'HBC_MED_REC_REVIEW' THEN 1 ELSE 0 END) AS medrec_tasks,
                SUM(CASE WHEN task_type = 'HBC_FOLLOW_UP_VISIT' THEN 1 ELSE 0 END) AS followup_tasks
             FROM oei_task
             WHERE episode_id = ?
               AND status = 'OPEN'",
            [$episodeId]
        ) ?: [];

        // ── Cert period compliance ────────────────────────────────────
        $certData = sqlQuery(
            "SELECT cert_period_start, cert_period_end, authorized_visits_per_week
             FROM oei_hbc_episode WHERE episode_id = ? LIMIT 1",
            [$episodeId]
        ) ?: [];
        $certEnd = (string) ($certData['cert_period_end'] ?? '');
        $certDaysRemaining = null;
        if ($certEnd !== '' && strtotime($certEnd) !== false) {
            $certDaysRemaining = (int) ((strtotime($certEnd) - time()) / 86400);
        }
        $authPerWeek = $certData['authorized_visits_per_week'] !== null
            ? (int) $certData['authorized_visits_per_week'] : null;

        // Visits this cert period
        $certStart = (string) ($certData['cert_period_start'] ?? '');
        $certVisitCount = 0;
        if ($certStart !== '' && $certEnd !== '') {
            $certRow = sqlQuery(
                "SELECT COUNT(*) AS c FROM oei_hbc_visit
                 WHERE episode_id = ? AND status = 'COMPLETE'
                   AND COALESCE(actual_end_datetime, scheduled_datetime) BETWEEN ? AND ?",
                [$episodeId, $certStart . ' 00:00:00', $certEnd . ' 23:59:59']
            );
            $certVisitCount = (int) ($certRow['c'] ?? 0);
        }
        // Weeks elapsed in cert period
        $certWeeksElapsed = 0;
        if ($certStart !== '' && strtotime($certStart) !== false) {
            $certWeeksElapsed = max(1, (int) ceil((time() - strtotime($certStart)) / 604800));
        }

        return [
            'total_visits' => (int) ($counts['total_visits'] ?? 0),
            'completed_visits' => (int) ($counts['completed_visits'] ?? 0),
            'active_visits' => (int) ($counts['active_visits'] ?? 0),
            'open_tasks' => (int) ($taskCounts['open_tasks'] ?? 0),
            'coordination_tasks' => (int) ($taskCounts['coordination_tasks'] ?? 0),
            'medrec_tasks' => (int) ($taskCounts['medrec_tasks'] ?? 0),
            'followup_tasks' => (int) ($taskCounts['followup_tasks'] ?? 0),
            'last_visit_type' => (string) ($latestComplete['visit_type'] ?? ''),
            'last_visit_datetime' => (string) ($latestComplete['actual_end_datetime'] ?? ''),
            'last_visit_outcome' => (string) ($latestComplete['outcome_summary'] ?? ''),
            'recommended_due_date' => (string) ($latestComplete['next_visit_due_date'] ?? ''),
            'recommended_visit_type' => (string) ($latestComplete['next_visit_type'] ?? ''),
            'cert_period_start' => $certStart,
            'cert_period_end' => $certEnd,
            'cert_days_remaining' => $certDaysRemaining,
            'authorized_visits_per_week' => $authPerWeek,
            'cert_visit_count' => $certVisitCount,
            'cert_weeks_elapsed' => $certWeeksElapsed,
        ];
    }

    /** @return array<string,mixed> */
    public function fetchClinicalAttention(int $episodeId): array
    {
        if (!function_exists('sqlQuery')) {
            return [];
        }

        $latestVisit = sqlQuery(
            "SELECT med_reconciliation_status, care_coordination_needed, care_coordination_summary,
                    home_safety_summary, wound_summary, procedure_summary,
                    next_visit_due_date, next_visit_type, outcome_summary
             FROM oei_hbc_visit
             WHERE episode_id = ?
             ORDER BY COALESCE(actual_end_datetime, scheduled_datetime) DESC, id DESC
             LIMIT 1",
            [$episodeId]
        ) ?: [];

        $pendingMar = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_mar_administration
             WHERE episode_id = ?
               AND outcome = 'PENDING'
               AND scheduled_datetime <= NOW()",
            [$episodeId]
        )['c'] ?? 0));

        $recentIncident = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_incident
             WHERE episode_id = ?
               AND incident_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$episodeId]
        )['c'] ?? 0));

        $futureVisit = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_hbc_visit
             WHERE episode_id = ?
               AND status IN ('SCHEDULED','EN_ROUTE','ARRIVED')
               AND scheduled_datetime >= NOW()",
            [$episodeId]
        )['c'] ?? 0));

        $openTasks = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
             FROM oei_task
             WHERE episode_id = ?
               AND status = 'OPEN'",
            [$episodeId]
        )['c'] ?? 0));

        $reasons = [];
        $score = 0;
        if (($latestVisit['med_reconciliation_status'] ?? '') === 'ISSUES_FOUND') {
            $reasons[] = 'Medication reconciliation needs review';
            $score += 2;
        }
        if ((int) ($latestVisit['care_coordination_needed'] ?? 0) === 1) {
            $reasons[] = 'Care coordination follow-up is open';
            $score += 2;
        }
        if ($pendingMar > 0) {
            $reasons[] = 'Pending MAR items are overdue';
            $score += 2;
        }
        if ($recentIncident > 0) {
            $reasons[] = 'Incident documented within the last 7 days';
            $score += 2;
        }
        $dueDate = (string) ($latestVisit['next_visit_due_date'] ?? '');
        $dueOverdue = $dueDate !== '' && $dueDate < date('Y-m-d') && $futureVisit === 0;
        if ($dueOverdue) {
            $reasons[] = 'Recommended follow-up is overdue';
            $score += 3;
        }
        if ($futureVisit === 0) {
            $reasons[] = 'No future visit is currently scheduled';
            $score += 1;
        }

        // Cert period expiring soon
        $certInfo = sqlQuery(
            "SELECT cert_period_end FROM oei_hbc_episode WHERE episode_id = ? LIMIT 1",
            [$episodeId]
        ) ?: [];
        $certEndDate = (string) ($certInfo['cert_period_end'] ?? '');
        if ($certEndDate !== '' && strtotime($certEndDate) !== false) {
            $daysLeft = (int) ((strtotime($certEndDate) - time()) / 86400);
            if ($daysLeft <= 7 && $daysLeft >= 0) {
                $reasons[] = 'Cert period expires in ' . $daysLeft . ' days';
                $score += 3;
            } elseif ($daysLeft <= 14 && $daysLeft > 7) {
                $reasons[] = 'Cert period expires in ' . $daysLeft . ' days';
                $score += 1;
            } elseif ($daysLeft < 0) {
                $reasons[] = 'Cert period has expired';
                $score += 4;
            }
        }

        $band = $score >= 6 ? 'high' : ($score >= 3 ? 'medium' : 'low');

        return [
            'priority_band' => $band,
            'priority_score' => $score,
            'reasons' => $reasons,
            'pending_mar_count' => $pendingMar,
            'recent_incident_count' => $recentIncident,
            'open_task_count' => $openTasks,
            'med_reconciliation_status' => (string) ($latestVisit['med_reconciliation_status'] ?? ''),
            'care_coordination_needed' => (bool) ((int) ($latestVisit['care_coordination_needed'] ?? 0)),
            'care_coordination_summary' => (string) ($latestVisit['care_coordination_summary'] ?? ''),
            'home_safety_summary' => (string) ($latestVisit['home_safety_summary'] ?? ''),
            'wound_summary' => (string) ($latestVisit['wound_summary'] ?? ''),
            'procedure_summary' => (string) ($latestVisit['procedure_summary'] ?? ''),
            'next_visit_due_date' => $dueDate,
            'next_visit_type' => (string) ($latestVisit['next_visit_type'] ?? ''),
            'outcome_summary' => (string) ($latestVisit['outcome_summary'] ?? ''),
        ];
    }

    /** @return array{counts:array,goals:array} */
    public function fetchCarePlanSummary(int $pid, int $encounterId): array
    {
        $empty = ['counts' => ['goals' => 0, 'activities' => 0, 'completed' => 0], 'goals' => []];
        if (!function_exists('sqlStatement') || $encounterId === 0) { return $empty; }
        $res = sqlStatement(
            "SELECT id, care_plan_type, description, plan_status, proposed_date
             FROM form_care_plan
             WHERE pid = ? AND encounter = ? AND activity = 1",
            [$pid, $encounterId]
        );
        $goals = $activities = $completed = 0;
        $goalList = [];
        while ($r = sqlFetchArray($res)) {
            if (strtolower((string)($r['care_plan_type'] ?? '')) === 'goal') {
                $goals++;
                $goalList[] = $r;
            } else {
                $activities++;
            }
            if (strtolower((string)($r['plan_status'] ?? '')) === 'completed') {
                $completed++;
            }
        }
        return ['counts' => compact('goals','activities','completed'), 'goals' => $goalList];
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchOpenTasks(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) { return []; }
        $res = sqlStatement(
            "SELECT task_type, due_datetime, assigned_to_user_id, payload_json
             FROM oei_task
             WHERE episode_id = ? AND status = 'OPEN'
             ORDER BY due_datetime ASC, id ASC
             LIMIT 8",
            [$episodeId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) { $rows[] = $r; }
        return $rows;
    }
}









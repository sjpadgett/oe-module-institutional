<?php

/**
 * src/HomeBased/Submodule/HbcVisit/Repository/HbcVisitRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Repository;

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;

/**
 * HbcVisitRepository — reads/writes for oei_hbc_visit plus HBC lifecycle sync.
 */
final class HbcVisitRepository
{
    public function create(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $visitType,
        ?int $clinicianUserId,
        string $scheduledDatetime,
        int $createdByUserId,
        ?string $windowStartDatetime = null,
        ?string $windowEndDatetime = null,
        ?int $routeSequence = null,
        ?string $travelNotes = null,
        bool $isSupervisory = false
    ): int {
        if (!function_exists('sqlInsert')) {
            return 0;
        }

        $visitId = (int) sqlInsert(
            "INSERT INTO oei_hbc_visit
                (episode_id, pid, facility_id, visit_type, clinician_user_id,
                 scheduled_datetime, window_start_datetime, window_end_datetime,
                 route_sequence, travel_notes, is_supervisory, status, is_draft,
                 patient_signature_obtained,
                 created_by_user_id, created_datetime, updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'SCHEDULED',0,0,?,NOW(),NOW())",
            [
                $episodeId,
                $pid,
                $facilityId,
                $visitType,
                $clinicianUserId > 0 ? $clinicianUserId : null,
                $scheduledDatetime,
                $windowStartDatetime,
                $windowEndDatetime,
                $routeSequence,
                $travelNotes,
                $isSupervisory ? 1 : 0,
                $createdByUserId,
            ]
        );

        if ($visitId > 0) {
            $this->syncEpisodeScheduled($episodeId);
            $this->addEpisodeEvent(
                $episodeId,
                $pid,
                $facilityId,
                'VISIT_SCHEDULED',
                $scheduledDatetime,
                $createdByUserId,
                trim(($visitType ? $visitType . ' ' : '') . ($travelNotes ?? ''))
            );
        }

        return $visitId;
    }

    public function cancel(int $visitId): bool
    {
        if (!function_exists('sqlQuery') || !function_exists('sqlStatement')) {
            return false;
        }

        $visit = sqlQuery(
            'SELECT id, episode_id, pid, facility_id, scheduled_datetime
             FROM oei_hbc_visit WHERE id = ? LIMIT 1',
            [$visitId]
        );
        if (!$visit) {
            return false;
        }

        sqlStatement(
            "UPDATE oei_hbc_visit
             SET status='CANCELED', updated_datetime=NOW()
             WHERE id=?",
            [$visitId]
        );

        $episodeId = (int) $visit['episode_id'];
        $openVisit = sqlQuery(
            "SELECT id
             FROM oei_hbc_visit
             WHERE episode_id = ?
               AND status IN ('SCHEDULED','EN_ROUTE','ARRIVED')
             LIMIT 1",
            [$episodeId]
        );
        $hbc = sqlQuery(
            "SELECT referral_status, soc_datetime
             FROM oei_hbc_episode
             WHERE episode_id = ?
             LIMIT 1",
            [$episodeId]
        );

        if (!$openVisit && $hbc && empty($hbc['soc_datetime'])) {
            sqlStatement(
                "UPDATE oei_hbc_episode
                 SET referral_status = ?
                 WHERE episode_id = ?
                   AND referral_status IN (?, ?)",
                [
                    HbcReferralStatus::TRIAGED,
                    $episodeId,
                    HbcReferralStatus::SCHEDULED,
                    HbcReferralStatus::ACTIVE,
                ]
            );
        }

        $this->addEpisodeEvent(
            $episodeId,
            (int) $visit['pid'],
            (int) $visit['facility_id'],
            'VISIT_CANCELED',
            date('Y-m-d H:i:s'),
            null,
            (string) ($visit['scheduled_datetime'] ?? '')
        );

        return true;
    }

    /**
     * Update scheduling fields on a non-finalized visit.
     */
    public function updateScheduled(
        int $visitId,
        ?int $clinicianUserId,
        ?string $scheduledDatetime,
        ?string $windowStartDatetime,
        ?string $windowEndDatetime,
        ?int $routeSequence,
        ?string $travelNotes,
        bool $isSupervisory,
        ?string $visitType
    ): bool {
        if (!function_exists('sqlQuery') || !function_exists('sqlStatement')) {
            return false;
        }

        $visit = sqlQuery(
            'SELECT id, status FROM oei_hbc_visit WHERE id = ? LIMIT 1',
            [$visitId]
        );
        if (!$visit) {
            return false;
        }

        // Only allow edits on non-finalized visits
        $status = (string) $visit['status'];
        if (in_array($status, ['COMPLETE', 'MISSED', 'REFUSED', 'CANCELED'], true)) {
            return false;
        }

        sqlStatement(
            "UPDATE oei_hbc_visit SET
                clinician_user_id     = ?,
                scheduled_datetime    = COALESCE(?, scheduled_datetime),
                window_start_datetime = ?,
                window_end_datetime   = ?,
                route_sequence        = ?,
                travel_notes          = ?,
                is_supervisory        = ?,
                visit_type            = COALESCE(?, visit_type),
                updated_datetime      = NOW()
             WHERE id = ?",
            [
                $clinicianUserId > 0 ? $clinicianUserId : null,
                $scheduledDatetime,
                $windowStartDatetime,
                $windowEndDatetime,
                $routeSequence,
                $travelNotes,
                $isSupervisory ? 1 : 0,
                $visitType,
                $visitId,
            ]
        );

        return true;
    }

    /** @return array<string,mixed>|null */
    public function fetchOne(int $visitId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT v.*,
                    pd.fname AS patient_fname,
                    pd.lname AS patient_lname,
                    pd.DOB,
                    hbc.service_address_line1,
                    hbc.service_address_line2,
                    hbc.service_city,
                    hbc.service_state_province,
                    hbc.service_postal_code,
                    hbc.access_notes,
                    hbc.caregiver_name,
                    hbc.caregiver_phone,
                    hbc.primary_diagnosis,
                    hbc.urgency,
                    CONCAT(COALESCE(uc.fname,''),' ',COALESCE(uc.lname,'')) AS clinician_name
             FROM   oei_hbc_visit v
             JOIN   oei_episode e       ON e.id  = v.episode_id
             JOIN   oei_hbc_episode hbc ON hbc.episode_id = v.episode_id
             JOIN   patient_data pd     ON pd.pid = v.pid
             LEFT   JOIN users uc       ON uc.id = v.clinician_user_id AND uc.active = 1
             WHERE  v.id = ? LIMIT 1",
            [$visitId]
        );
        return $row ?: null;
    }

    public function saveDraft(int $visitId, string $draftJson, ?float $lat, ?float $lng): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        if ($lat !== null && $lng !== null) {
            sqlStatement(
                "UPDATE oei_hbc_visit
                 SET draft_data=?, is_draft=1, actual_lat=?, actual_lng=?,
                     updated_datetime=NOW()
                 WHERE id=?",
                [$draftJson, $lat, $lng, $visitId]
            );
            return;
        }

        sqlStatement(
            "UPDATE oei_hbc_visit
             SET draft_data=?, is_draft=1, updated_datetime=NOW()
             WHERE id=?",
            [$draftJson, $visitId]
        );
    }

    public function finalise(
        int $visitId,
        string $visitNote,
        string $outcomeSummary,
        ?float $mileage,
        string $actualStart,
        string $actualEnd,
        string $completionStatus,
        bool $sigObtained,
        ?string $sigData,
        ?float $lat,
        ?float $lng,
        string $medReconciliationStatus,
        string $medReconciliationSummary,
        string $woundSummary,
        string $procedureSummary,
        string $homeSafetySummary,
        bool $careCoordinationNeeded,
        string $careCoordinationSummary,
        string $followupPlan,
        ?string $nextVisitDueDate,
        ?string $nextVisitType,
        ?int $userId,
        ?array $vitals = null
    ): bool {
        if (!function_exists('sqlQuery') || !function_exists('sqlStatement')) {
            return false;
        }

        $visit = sqlQuery(
            'SELECT id, episode_id, pid, facility_id FROM oei_hbc_visit WHERE id = ? LIMIT 1',
            [$visitId]
        );
        if (!$visit) {
            return false;
        }

        $params = [
            $visitNote,
            $outcomeSummary !== '' ? $outcomeSummary : null,
            $mileage,
            $actualStart ?: null,
            $actualEnd ?: null,
            $completionStatus,
            $sigObtained ? 1 : 0,
            $sigObtained ? $sigData : null,
            $sigObtained ? date('Y-m-d H:i:s') : null,
            $medReconciliationStatus,
            $medReconciliationSummary !== '' ? $medReconciliationSummary : null,
            $woundSummary !== '' ? $woundSummary : null,
            $procedureSummary !== '' ? $procedureSummary : null,
            $homeSafetySummary !== '' ? $homeSafetySummary : null,
            $careCoordinationNeeded ? 1 : 0,
            $careCoordinationSummary !== '' ? $careCoordinationSummary : null,
            $followupPlan !== '' ? $followupPlan : null,
            $nextVisitDueDate ?: null,
            $nextVisitType ?: null,
        ];

        $gpsClause = '';
        if ($lat !== null && $lng !== null) {
            $gpsClause = ', actual_lat = ?, actual_lng = ?';
            $params[] = $lat;
            $params[] = $lng;
        }
        $params[] = $visitId;

        sqlStatement(
            "UPDATE oei_hbc_visit
             SET  visit_note = ?,
                  outcome_summary = ?,
                  mileage_miles = ?,
                  actual_start_datetime = COALESCE(actual_start_datetime, ?),
                  actual_end_datetime = ?,
                  is_draft = 0,
                  draft_data = NULL,
                  status = ?,
                  patient_signature_obtained = ?,
                  patient_signature_data = ?,
                  patient_signature_datetime = ?,
                  med_reconciliation_status = ?,
                  med_reconciliation_summary = ?,
                  wound_summary = ?,
                  procedure_summary = ?,
                  home_safety_summary = ?,
                  care_coordination_needed = ?,
                  care_coordination_summary = ?,
                  followup_plan = ?,
                  next_visit_due_date = ?,
                  next_visit_type = ?
                  {$gpsClause},
                  updated_datetime = NOW()
             WHERE id = ?",
            $params
        );

        $episodeId = (int) $visit['episode_id'];
        $pid = (int) $visit['pid'];
        $facilityId = (int) $visit['facility_id'];

        if ($completionStatus === HbcVisitStatus::COMPLETE) {
            $this->syncEpisodeActive($episodeId, $actualEnd);
        }

        $this->addEpisodeEvent(
            $episodeId,
            $pid,
            $facilityId,
            $completionStatus === HbcVisitStatus::COMPLETE
                ? 'VISIT_COMPLETE'
                : ($completionStatus === HbcVisitStatus::REFUSED ? 'VISIT_REFUSED' : 'VISIT_MISSED'),
            $actualEnd ?: date('Y-m-d H:i:s'),
            $userId,
            $outcomeSummary !== '' ? $outcomeSummary : null
        );

        if ($medReconciliationStatus === 'ISSUES_FOUND') {
            $this->createTaskIfMissing(
                $episodeId,
                $pid,
                $facilityId,
                'HBC_MED_REC_REVIEW',
                date('Y-m-d H:i:s'),
                $userId,
                json_encode([
                    'visit_id' => $visitId,
                    'task_label' => 'Medication reconciliation review',
                    'detail' => $medReconciliationSummary,
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        if ($careCoordinationNeeded || $careCoordinationSummary !== '') {
            $this->createTaskIfMissing(
                $episodeId,
                $pid,
                $facilityId,
                'HBC_COORDINATION_REVIEW',
                date('Y-m-d H:i:s'),
                $userId,
                json_encode([
                    'visit_id' => $visitId,
                    'task_label' => 'Care coordination follow-up',
                    'detail' => $careCoordinationSummary,
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        if (!empty($nextVisitDueDate)) {
            $due = $nextVisitDueDate . ' 09:00:00';
            $this->createTaskIfMissing(
                $episodeId,
                $pid,
                $facilityId,
                'HBC_FOLLOW_UP_VISIT',
                $due,
                $userId,
                json_encode([
                    'visit_id' => $visitId,
                    'task_label' => 'Schedule follow-up visit',
                    'detail' => $followupPlan,
                    'next_visit_type' => $nextVisitType,
                    'next_visit_due_date' => $nextVisitDueDate,
                ], JSON_UNESCAPED_UNICODE)
            );
        }

        // ── Inline vitals → oei_triage ──────────────────────────────────
        if ($vitals !== null && $this->hasAnyVital($vitals)) {
            $this->writeVisitVitals($episodeId, $pid, $facilityId, $vitals, $userId);
        }

        return true;
    }

    public function advanceStatus(int $visitId): ?string
    {
        if (!function_exists('sqlQuery') || !function_exists('sqlStatement')) {
            return null;
        }

        $row = sqlQuery(
            'SELECT id, episode_id, pid, facility_id, status FROM oei_hbc_visit WHERE id = ? LIMIT 1',
            [$visitId]
        );
        if (!$row) {
            return null;
        }

        $next = [
            HbcVisitStatus::SCHEDULED => HbcVisitStatus::EN_ROUTE,
            HbcVisitStatus::EN_ROUTE => HbcVisitStatus::ARRIVED,
            HbcVisitStatus::ARRIVED => HbcVisitStatus::COMPLETE,
        ][(string) $row['status']] ?? null;

        if ($next === null) {
            return null;
        }

        $dateClause = match ($next) {
            HbcVisitStatus::ARRIVED => ', actual_start_datetime = COALESCE(actual_start_datetime, NOW())',
            HbcVisitStatus::COMPLETE => ', actual_end_datetime = NOW()',
            default => '',
        };

        sqlStatement(
            "UPDATE oei_hbc_visit
             SET status = ?, updated_datetime = NOW() {$dateClause}
             WHERE id = ?",
            [$next, $visitId]
        );

        if ($next === HbcVisitStatus::COMPLETE) {
            $this->syncEpisodeActive((int) $row['episode_id'], date('Y-m-d H:i:s'));
            $this->addEpisodeEvent(
                (int) $row['episode_id'],
                (int) $row['pid'],
                (int) $row['facility_id'],
                'VISIT_COMPLETE',
                date('Y-m-d H:i:s'),
                null,
                'Quick advance completion from board'
            );
        } elseif ($next === HbcVisitStatus::ARRIVED) {
            $this->addEpisodeEvent(
                (int) $row['episode_id'],
                (int) $row['pid'],
                (int) $row['facility_id'],
                'VISIT_ARRIVED',
                date('Y-m-d H:i:s'),
                null,
                null
            );
        } elseif ($next === HbcVisitStatus::EN_ROUTE) {
            $this->addEpisodeEvent(
                (int) $row['episode_id'],
                (int) $row['pid'],
                (int) $row['facility_id'],
                'VISIT_EN_ROUTE',
                date('Y-m-d H:i:s'),
                null,
                null
            );
        }

        return $next;
    }

    public function recordGps(int $visitId, float $lat, float $lng): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            'UPDATE oei_hbc_visit SET actual_lat=?, actual_lng=? WHERE id=?',
            [$lat, $lng, $visitId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
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
                    v.is_supervisory,
                    v.patient_signature_obtained,
                    v.outcome_summary,
                    v.mileage_miles,
                    v.next_visit_due_date,
                    v.next_visit_type,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name
             FROM   oei_hbc_visit v
             LEFT   JOIN users u ON u.id=v.clinician_user_id AND u.active=1
             WHERE  v.episode_id=? AND v.status NOT IN ('CANCELED')
             ORDER  BY v.scheduled_datetime DESC, v.id DESC",
            [$episodeId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'visit_id' => (int) $r['visit_id'],
                'visit_type' => (string) $r['visit_type'],
                'scheduled' => (string) ($r['scheduled_datetime'] ?? ''),
                'window_start_datetime' => (string) ($r['window_start_datetime'] ?? ''),
                'window_end_datetime' => (string) ($r['window_end_datetime'] ?? ''),
                'route_sequence' => $r['route_sequence'] !== null ? (int) $r['route_sequence'] : null,
                'travel_notes' => (string) ($r['travel_notes'] ?? ''),
                'actual_start' => (string) ($r['actual_start_datetime'] ?? ''),
                'actual_end' => (string) ($r['actual_end_datetime'] ?? ''),
                'status' => (string) $r['status'],
                                'is_draft' => (bool) $r['is_draft'],
                'is_supervisory' => (bool) ($r['is_supervisory'] ?? false),
                'sig_obtained' => (bool) $r['patient_signature_obtained'],
                'outcome' => (string) ($r['outcome_summary'] ?? ''),
                'mileage' => $r['mileage_miles'] !== null ? (float) $r['mileage_miles'] : null,
                'next_visit_due_date' => (string) ($r['next_visit_due_date'] ?? ''),
                'next_visit_type' => (string) ($r['next_visit_type'] ?? ''),
                'clinician' => trim((string) ($r['clinician_name'] ?? '')),
            ];
        }
        return $rows;
    }


    /** @return array<string,mixed>|null */
    public function fetchSchedulingRecommendation(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT
                v.next_visit_due_date,
                v.next_visit_type,
                v.followup_plan,
                v.care_coordination_needed,
                v.care_coordination_summary,
                v.outcome_summary,
                v.actual_end_datetime
             FROM oei_hbc_visit v
             WHERE v.episode_id = ?
               AND v.status = 'COMPLETE'
             ORDER BY COALESCE(v.actual_end_datetime, v.scheduled_datetime) DESC, v.id DESC
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchClinicianDayLoad(int $facilityId, string $date): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';
        $res = sqlStatement(
            "SELECT
                COALESCE(v.clinician_user_id, 0) AS clinician_user_id,
                CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name,
                COUNT(*) AS visit_count,
                SUM(CASE WHEN v.status IN ('EN_ROUTE','ARRIVED') THEN 1 ELSE 0 END) AS active_count
             FROM oei_hbc_visit v
             LEFT JOIN users u ON u.id = v.clinician_user_id AND u.active = 1
             WHERE v.facility_id = ?
               AND v.scheduled_datetime BETWEEN ? AND ?
               AND v.status NOT IN ('CANCELED')
             GROUP BY COALESCE(v.clinician_user_id, 0), clinician_name
             ORDER BY visit_count DESC, clinician_name ASC",
            [$facilityId, $dayStart, $dayEnd]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'clinician_user_id' => (int) ($r['clinician_user_id'] ?? 0),
                'clinician_name' => trim((string) ($r['clinician_name'] ?? '')) ?: 'Unassigned',
                'visit_count' => (int) ($r['visit_count'] ?? 0),
                'active_count' => (int) ($r['active_count'] ?? 0),
            ];
        }
        return $rows;
    }

    /** @return array<int,array{id:int,name:string}> */
    public function listClinicians(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, CONCAT(fname,' ',lname) AS name
             FROM users WHERE active=1 AND authorized=1
             ORDER BY lname ASC, fname ASC"
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = ['id' => (int) $r['id'], 'name' => trim((string) ($r['name'] ?? ''))];
        }
        return $rows;
    }

    private function syncEpisodeScheduled(int $episodeId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE oei_hbc_episode
             SET referral_status = ?
             WHERE episode_id = ?
               AND referral_status IN (?, ?)",
            [
                HbcReferralStatus::SCHEDULED,
                $episodeId,
                HbcReferralStatus::NEW,
                HbcReferralStatus::TRIAGED,
            ]
        );
    }

    private function syncEpisodeActive(int $episodeId, ?string $socDatetime): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE oei_hbc_episode
             SET referral_status = ?,
                 soc_datetime = COALESCE(soc_datetime, ?)
             WHERE episode_id = ?
               AND referral_status NOT IN (?, ?)",
            [
                HbcReferralStatus::ACTIVE,
                $socDatetime ?: date('Y-m-d H:i:s'),
                $episodeId,
                HbcReferralStatus::CLOSED,
                HbcReferralStatus::DECLINED,
            ]
        );
    }

    private function addEpisodeEvent(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $eventType,
        string $eventDatetime,
        ?int $userId,
        ?string $note
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "INSERT INTO oei_episode_event
                (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?)",
            [$episodeId, $pid, $facilityId, $eventType, $eventDatetime, $userId, $note]
        );
    }

    private function createTaskIfMissing(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $taskType,
        string $dueDatetime,
        ?int $userId,
        ?string $payloadJson
    ): void {
        if (!function_exists('sqlQuery') || !function_exists('sqlStatement')) {
            return;
        }

        $existing = sqlQuery(
            "SELECT id
             FROM oei_task
             WHERE episode_id = ? AND task_type = ? AND status = 'OPEN'
             LIMIT 1",
            [$episodeId, $taskType]
        );
        if ($existing) {
            return;
        }

        sqlStatement(
            "INSERT INTO oei_task
                (episode_id, pid, eid, facility_id, task_type, due_datetime,
                 assigned_to_user_id, status, payload_json, created_by_user_id, created_datetime)
             VALUES (?, ?, NULL, ?, ?, ?, NULL, 'OPEN', ?, ?, NOW())",
            [$episodeId, $pid, $facilityId, $taskType, $dueDatetime, $payloadJson, $userId]
        );
    }

    /** @param array<string,mixed> $v */
    private function hasAnyVital(array $v): bool
    {
        foreach (['bp_systolic','bp_diastolic','hr','rr','spo2','temp_f','weight_kg','pain_score'] as $k) {
            if (isset($v[$k]) && $v[$k] !== '' && $v[$k] !== null) {
                return true;
            }
        }
        return false;
    }

    /** @param array<string,mixed> $v */
    private function writeVisitVitals(int $episodeId, int $pid, int $facilityId, array $v, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }

        $bpS  = isset($v['bp_systolic'])  && $v['bp_systolic']  !== '' ? (int) $v['bp_systolic']  : null;
        $bpD  = isset($v['bp_diastolic']) && $v['bp_diastolic'] !== '' ? (int) $v['bp_diastolic'] : null;
        $hr   = isset($v['hr'])           && $v['hr']           !== '' ? (int) $v['hr']           : null;
        $rr   = isset($v['rr'])           && $v['rr']           !== '' ? (int) $v['rr']           : null;
        $spo  = isset($v['spo2'])         && $v['spo2']         !== '' ? (int) $v['spo2']         : null;
        $temp = isset($v['temp_f'])       && $v['temp_f']       !== '' ? (float) $v['temp_f']     : null;
        $wt   = isset($v['weight_kg'])    && $v['weight_kg']    !== '' ? (float) $v['weight_kg']  : null;
        $pain = isset($v['pain_score'])   && $v['pain_score']   !== '' ? (int) $v['pain_score']   : null;

        sqlStatement(
            "INSERT INTO oei_triage
                (episode_id, pid, facility_id,
                 bp_systolic, bp_diastolic, hr, rr, spo2, temp_f,
                 weight_kg, pain_score,
                 noted_datetime, created_by, created_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())",
            [
                $episodeId, $pid, $facilityId,
                $bpS, $bpD, $hr, $rr, $spo, $temp,
                $wt, $pain,
                $userId,
            ]
        );
    }
}















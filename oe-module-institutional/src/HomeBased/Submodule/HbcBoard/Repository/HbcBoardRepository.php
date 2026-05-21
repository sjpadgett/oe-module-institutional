<?php

/**
 * src/HomeBased/Submodule/HbcBoard/Repository/HbcBoardRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcBoard\Repository;

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;

final class HbcBoardRepository
{
    /** @return array<int,array<string,mixed>> */
    public function fetchReferralQueue(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT
                e.id            AS episode_id,
                e.pid,
                e.start_datetime,
                hbc.referral_status,
                hbc.urgency,
                hbc.referral_source,
                hbc.referral_reason,
                hbc.service_city,
                hbc.service_state_province,
                hbc.primary_diagnosis,
                hbc.referral_datetime,
                pd.fname,
                pd.lname,
                pd.DOB,
                CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,'')) AS clinician_name,
                DATEDIFF(NOW(), COALESCE(hbc.referral_datetime, e.start_datetime)) AS days_waiting
             FROM   oei_episode e
             JOIN   oei_hbc_episode hbc ON hbc.episode_id = e.id
             JOIN   patient_data pd     ON pd.pid = e.pid
             LEFT   JOIN users u        ON u.id = hbc.primary_clinician_user_id AND u.active = 1
             WHERE  e.facility_id = ?
               AND  e.status = 'ACTIVE'
               AND  e.type = 'HBC'
               AND  hbc.referral_status IN ('NEW', 'TRIAGED')
             ORDER  BY FIELD(hbc.urgency,'EMERGENT','URGENT','ROUTINE'),
                       hbc.referral_datetime ASC,
                       e.id ASC",
            [$facilityId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = $this->castEpisodeRow($r);
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchDayVisits(int $facilityId, string $date): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $res = sqlStatement(
            "SELECT
                v.id            AS visit_id,
                v.episode_id,
                v.pid,
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
                hbc.service_address_line1,
                hbc.service_city,
                hbc.service_state_province,
                hbc.access_notes,
                hbc.urgency,
                hbc.referral_status,
                hbc.primary_diagnosis,
                pd.fname,
                pd.lname,
                pd.DOB,
                CONCAT(COALESCE(uc.fname,''), ' ', COALESCE(uc.lname,'')) AS clinician_name
             FROM   oei_hbc_visit v
             JOIN   oei_hbc_episode hbc ON hbc.episode_id = v.episode_id
             JOIN   oei_episode e       ON e.id = v.episode_id
             JOIN   patient_data pd     ON pd.pid = v.pid
             LEFT   JOIN users uc       ON uc.id = v.clinician_user_id AND uc.active = 1
             WHERE  v.facility_id = ?
               AND  v.scheduled_datetime BETWEEN ? AND ?
               AND  v.status NOT IN ('CANCELED')
             ORDER  BY COALESCE(v.route_sequence, 9999) ASC,
                       v.scheduled_datetime ASC,
                       v.id ASC",
            [$facilityId, $dayStart, $dayEnd]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = $this->castVisitRow($r);
        }
        return $rows;
    }



    /** @return array<string,int> */
    public function fetchMetrics(int $facilityId, string $date): array
    {
        if (!function_exists('sqlQuery')) {
            return [
                'active_patients' => 0,
                'pending_referrals' => 0,
                'today_visits' => 0,
                'week_completed' => 0,
                'open_actions' => 0,
            ];
        }

        $dayStart = $date . ' 00:00:00';
        $dayEnd = $date . ' 23:59:59';

        $active = (int) ((sqlQuery(
            "SELECT COUNT(*)
               AS c
             FROM oei_episode e
             JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
            WHERE e.facility_id = ?
              AND e.type = 'HBC'
              AND e.status = 'ACTIVE'
              AND hbc.referral_status NOT IN (?, ?)",
            [$facilityId, HbcReferralStatus::CLOSED, HbcReferralStatus::DECLINED]
        )['c'] ?? 0));

        $pending = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
               FROM oei_episode e
               JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
              WHERE e.facility_id = ?
                AND e.type = 'HBC'
                AND e.status = 'ACTIVE'
                AND hbc.referral_status IN (?, ?)",
            [$facilityId, HbcReferralStatus::NEW, HbcReferralStatus::TRIAGED]
        )['c'] ?? 0));

        $today = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
               FROM oei_hbc_visit
              WHERE facility_id = ?
                AND scheduled_datetime BETWEEN ? AND ?
                AND status NOT IN ('CANCELED')",
            [$facilityId, $dayStart, $dayEnd]
        )['c'] ?? 0));

        $weekCompleted = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
               FROM oei_hbc_visit
              WHERE facility_id = ?
                AND status = 'COMPLETE'
                AND actual_end_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            [$facilityId]
        )['c'] ?? 0));

        $openActions = (int) ((sqlQuery(
            "SELECT COUNT(*) AS c
               FROM oei_task t
               JOIN oei_episode e ON e.id = t.episode_id
              WHERE e.facility_id = ?
                AND e.type = 'HBC'
                AND t.status = 'OPEN'",
            [$facilityId]
        )['c'] ?? 0));

        return [
            'active_patients' => $active,
            'pending_referrals' => $pending,
            'today_visits' => $today,
            'week_completed' => $weekCompleted,
            'open_actions' => $openActions,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function fetchActionQueue(int $facilityId, int $limit = 8): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                hbc.urgency,
                hbc.referral_status,
                hbc.primary_diagnosis,
                hbc.service_city,
                hbc.cert_period_end,
                (
                    SELECT COUNT(*)
                    FROM oei_task t
                    WHERE t.episode_id = e.id
                      AND t.status = 'OPEN'
                ) AS open_task_count,
                (
                    SELECT COUNT(*)
                    FROM oei_task t
                    WHERE t.episode_id = e.id
                      AND t.status = 'OPEN'
                      AND t.task_type = 'HBC_COORDINATION_REVIEW'
                ) AS coordination_task_count,
                (
                    SELECT COUNT(*)
                    FROM oei_task t
                    WHERE t.episode_id = e.id
                      AND t.status = 'OPEN'
                      AND t.task_type = 'HBC_MED_REC_REVIEW'
                ) AS medrec_task_count,
                (
                    SELECT COUNT(*)
                    FROM oei_mar_administration ma
                    WHERE ma.episode_id = e.id
                      AND ma.outcome = 'PENDING'
                      AND ma.scheduled_datetime <= NOW()
                ) AS pending_mar_count,
                (
                    SELECT COUNT(*)
                    FROM oei_incident inc
                    WHERE inc.episode_id = e.id
                      AND inc.incident_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ) AS recent_incident_count,
                (
                    SELECT v.next_visit_due_date
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                      AND v.status = 'COMPLETE'
                      AND v.next_visit_due_date IS NOT NULL
                    ORDER BY COALESCE(v.actual_end_datetime, v.scheduled_datetime) DESC, v.id DESC
                    LIMIT 1
                ) AS next_visit_due_date,
                (
                    SELECT v.next_visit_type
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                      AND v.status = 'COMPLETE'
                      AND v.next_visit_due_date IS NOT NULL
                    ORDER BY COALESCE(v.actual_end_datetime, v.scheduled_datetime) DESC, v.id DESC
                    LIMIT 1
                ) AS next_visit_type,
                (
                    SELECT COUNT(*)
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                      AND v.status IN ('SCHEDULED','EN_ROUTE','ARRIVED')
                      AND v.scheduled_datetime >= NOW()
                ) AS future_visit_count,
                (
                    SELECT CONCAT(COALESCE(v.med_reconciliation_status,''),'|',COALESCE(v.care_coordination_needed,0),'|',COALESCE(v.outcome_summary,''))
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                    ORDER BY COALESCE(v.actual_end_datetime, v.scheduled_datetime) DESC, v.id DESC
                    LIMIT 1
                ) AS latest_visit_signal,
                (
                    SELECT COUNT(*)
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                      AND v.visit_type = 'HHA'
                      AND v.status = 'COMPLETE'
                ) AS hha_visit_count,
                (
                    SELECT DATEDIFF(NOW(), MAX(v.actual_end_datetime))
                    FROM oei_hbc_visit v
                    WHERE v.episode_id = e.id
                      AND v.is_supervisory = 1
                      AND v.status = 'COMPLETE'
                ) AS days_since_supervisory
             FROM oei_episode e
             JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.type = 'HBC'
               AND e.status = 'ACTIVE'
               AND hbc.referral_status NOT IN (?, ?)
             ORDER BY FIELD(hbc.urgency,'EMERGENT','URGENT','ROUTINE'), e.id DESC",
            [$facilityId, HbcReferralStatus::CLOSED, HbcReferralStatus::DECLINED]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $futureVisitCount = (int) ($r['future_visit_count'] ?? 0);
            $dueDate = (string) ($r['next_visit_due_date'] ?? '');
            $dueOverdue = $dueDate !== '' && $dueDate < date('Y-m-d') && $futureVisitCount === 0;
            $priorityScore = 0;
            $reasons = [];

            if (((int) ($r['coordination_task_count'] ?? 0)) > 0) {
                $priorityScore += 2;
                $reasons[] = 'Coordination follow-up';
            }
            if (((int) ($r['medrec_task_count'] ?? 0)) > 0) {
                $priorityScore += 2;
                $reasons[] = 'Medication review';
            }
            if (((int) ($r['pending_mar_count'] ?? 0)) > 0) {
                $priorityScore += 2;
                $reasons[] = 'Pending MAR items';
            }
            if (((int) ($r['recent_incident_count'] ?? 0)) > 0) {
                $priorityScore += 2;
                $reasons[] = 'Recent incident';
            }
            if ($dueOverdue) {
                $priorityScore += 3;
                $reasons[] = 'Follow-up overdue';
            }
            if (strtoupper((string) ($r['urgency'] ?? '')) === 'EMERGENT') {
                $priorityScore += 3;
                $reasons[] = 'Emergent urgency';
            } elseif (strtoupper((string) ($r['urgency'] ?? '')) === 'URGENT') {
                $priorityScore += 1;
            }
            if ($futureVisitCount === 0) {
                $priorityScore += 1;
                $reasons[] = 'No future visit';
            }

            // Cert period expiry
            $certEnd = (string) ($r['cert_period_end'] ?? '');
            if ($certEnd !== '' && strtotime($certEnd) !== false) {
                $certDaysLeft = (int) ((strtotime($certEnd) - time()) / 86400);
                if ($certDaysLeft < 0) {
                    $priorityScore += 4;
                    $reasons[] = 'Cert period expired';
                } elseif ($certDaysLeft <= 7) {
                    $priorityScore += 3;
                    $reasons[] = 'Cert expires in ' . $certDaysLeft . 'd';
                } elseif ($certDaysLeft <= 14) {
                    $priorityScore += 1;
                    $reasons[] = 'Cert expires in ' . $certDaysLeft . 'd';
                }
            }

            // Supervisory visit overdue (HHA compliance)
            $hhaCount = (int) ($r['hha_visit_count'] ?? 0);
            $daysSupervisory = $r['days_since_supervisory'] ?? null;
            if ($hhaCount > 0 && ($daysSupervisory === null || (int) $daysSupervisory >= 14)) {
                $priorityScore += 2;
                $reasons[] = 'Supervisory visit overdue';
            }

            $priorityBand = $priorityScore >= 6 ? 'high' : ($priorityScore >= 3 ? 'medium' : 'low');

            $rows[] = [
                'episode_id' => (int) $r['episode_id'],
                'pid' => (int) $r['pid'],
                'urgency' => (string) ($r['urgency'] ?? 'ROUTINE'),
                'referral_status' => (string) ($r['referral_status'] ?? ''),
                'primary_diagnosis' => (string) ($r['primary_diagnosis'] ?? ''),
                'service_city' => (string) ($r['service_city'] ?? ''),
                'open_task_count' => (int) ($r['open_task_count'] ?? 0),
                'coordination_task_count' => (int) ($r['coordination_task_count'] ?? 0),
                'medrec_task_count' => (int) ($r['medrec_task_count'] ?? 0),
                'pending_mar_count' => (int) ($r['pending_mar_count'] ?? 0),
                'recent_incident_count' => (int) ($r['recent_incident_count'] ?? 0),
                'next_visit_due_date' => $dueDate,
                'next_visit_type' => (string) ($r['next_visit_type'] ?? ''),
                'future_visit_count' => $futureVisitCount,
                'due_overdue' => $dueOverdue,
                'priority_band' => $priorityBand,
                'priority_score' => $priorityScore,
                'reasons' => $reasons,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            return [$b['priority_score'], $a['pid']] <=> [$a['priority_score'], $b['pid']];
        });

        return array_slice($rows, 0, $limit);
    }

    public function advanceVisitStatus(int $visitId, int $userId): ?string
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

        $current = (string) $row['status'];
        $next = HbcVisitStatus::next($current);
        if ($next === null) {
            return null;
        }

        $setDatetime = '';
        if ($next === HbcVisitStatus::ARRIVED) {
            $setDatetime = ', actual_start_datetime = COALESCE(actual_start_datetime, NOW())';
        } elseif ($next === HbcVisitStatus::COMPLETE) {
            $setDatetime = ', actual_end_datetime = NOW()';
        }

        sqlStatement(
            "UPDATE oei_hbc_visit
             SET status = ?, updated_datetime = NOW() {$setDatetime}
             WHERE id = ?",
            [$next, $visitId]
        );

        if ($next === HbcVisitStatus::COMPLETE) {
            $this->syncEpisodeActive((int) $row['episode_id']);
            $this->addEpisodeEvent((int) $row['episode_id'], (int) $row['pid'], (int) $row['facility_id'], 'VISIT_COMPLETE', date('Y-m-d H:i:s'), $userId > 0 ? $userId : null, 'Quick advance completion from board');
        } elseif ($next === HbcVisitStatus::ARRIVED) {
            $this->addEpisodeEvent((int) $row['episode_id'], (int) $row['pid'], (int) $row['facility_id'], 'VISIT_ARRIVED', date('Y-m-d H:i:s'), $userId > 0 ? $userId : null, null);
        } elseif ($next === HbcVisitStatus::EN_ROUTE) {
            $this->addEpisodeEvent((int) $row['episode_id'], (int) $row['pid'], (int) $row['facility_id'], 'VISIT_EN_ROUTE', date('Y-m-d H:i:s'), $userId > 0 ? $userId : null, null);
        }

        return $next;
    }

    public function recordGps(int $visitId, float $lat, float $lng): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement('UPDATE oei_hbc_visit SET actual_lat = ?, actual_lng = ? WHERE id = ?', [$lat, $lng, $visitId]);
    }

    /** @return array<string,mixed> */
    private function castEpisodeRow(array $r): array
    {
        return [
            'episode_id' => (int) $r['episode_id'],
            'pid' => (int) $r['pid'],
            'fname' => (string) ($r['fname'] ?? ''),
            'lname' => (string) ($r['lname'] ?? ''),
            'dob' => (string) ($r['DOB'] ?? ''),
            'clinician_name' => trim((string) ($r['clinician_name'] ?? '')),
            'referral_status' => (string) $r['referral_status'],
            'urgency' => (string) $r['urgency'],
            'referral_source' => (string) ($r['referral_source'] ?? ''),
            'referral_reason' => (string) ($r['referral_reason'] ?? ''),
            'service_city' => (string) ($r['service_city'] ?? ''),
            'service_state' => (string) ($r['service_state_province'] ?? ''),
            'primary_diagnosis' => (string) ($r['primary_diagnosis'] ?? ''),
            'days_waiting' => (int) ($r['days_waiting'] ?? 0),
            'start_datetime' => (string) ($r['start_datetime'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function castVisitRow(array $r): array
    {
        return [
            'visit_id' => (int) $r['visit_id'],
            'episode_id' => (int) $r['episode_id'],
            'pid' => (int) $r['pid'],
            'fname' => (string) ($r['fname'] ?? ''),
            'lname' => (string) ($r['lname'] ?? ''),
            'dob' => (string) ($r['DOB'] ?? ''),
            'visit_type' => (string) $r['visit_type'],
            'scheduled_datetime' => (string) ($r['scheduled_datetime'] ?? ''),
            'window_start_datetime' => (string) ($r['window_start_datetime'] ?? ''),
            'window_end_datetime' => (string) ($r['window_end_datetime'] ?? ''),
            'route_sequence' => $r['route_sequence'] !== null ? (int) $r['route_sequence'] : null,
            'travel_notes' => (string) ($r['travel_notes'] ?? ''),
            'actual_start' => (string) ($r['actual_start_datetime'] ?? ''),
            'actual_end' => (string) ($r['actual_end_datetime'] ?? ''),
            'status' => (string) $r['status'],
            'is_draft' => (bool) $r['is_draft'],
            'sig_obtained' => (bool) $r['patient_signature_obtained'],
            'outcome_summary' => (string) ($r['outcome_summary'] ?? ''),
            'mileage_miles' => $r['mileage_miles'] !== null ? (float) $r['mileage_miles'] : null,
            'address_line1' => (string) ($r['service_address_line1'] ?? ''),
            'service_city' => (string) ($r['service_city'] ?? ''),
            'service_state' => (string) ($r['service_state_province'] ?? ''),
            'access_notes' => (string) ($r['access_notes'] ?? ''),
            'urgency' => (string) ($r['urgency'] ?? 'ROUTINE'),
            'primary_diagnosis' => (string) ($r['primary_diagnosis'] ?? ''),
            'clinician_name' => trim((string) ($r['clinician_name'] ?? '')),
        ];
    }

    private function syncEpisodeActive(int $episodeId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE oei_hbc_episode
             SET referral_status = ?,
                 soc_datetime = COALESCE(soc_datetime, NOW())
             WHERE episode_id = ?
               AND referral_status NOT IN (?, ?)",
            [HbcReferralStatus::ACTIVE, $episodeId, HbcReferralStatus::CLOSED, HbcReferralStatus::DECLINED]
        );
    }

    private function addEpisodeEvent(int $episodeId, int $pid, int $facilityId, string $eventType, string $eventDatetime, ?int $userId, ?string $note): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "INSERT INTO oei_episode_event (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?)",
            [$episodeId, $pid, $facilityId, $eventType, $eventDatetime, $userId, $note]
        );
    }
}












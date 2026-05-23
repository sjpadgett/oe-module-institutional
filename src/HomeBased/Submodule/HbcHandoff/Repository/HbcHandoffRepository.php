<?php

/**
 * src/HomeBased/Submodule/HbcHandoff/Repository/HbcHandoffRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcHandoff\Repository;

/**
 * HbcHandoffRepository
 *
 * Single-query snapshot for the HBC shift/day handoff report.
 *
 * HBC-specific differences from AL handoff:
 *   - Joins oei_hbc_episode for service address + clinician (no room/unit)
 *   - "Days on service" replaces "days resident"
 *   - Next scheduled visit replaces ADL score
 *   - No care_level/fall_risk from overlay — reads oei_fall_risk_assessment
 *   - HBC disposition codes (SERVICE_COMPLETED etc.)
 *
 * All vitals, MAR pending, incident, care plan goal, and fall reassessment
 * correlated subqueries are identical to AlHandoffRepository — same tables.
 */
final class HbcHandoffRepository
{
    /**
     * Fetch one row per active HBC patient, ordered by service days desc
     * (longest-on-service first — most complex cases).
     * @return array<int,array<string,mixed>>
     */
    public function fetchHandoff(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) { return []; }

        $res = sqlStatement(
            "SELECT
                -- ── Identity ─────────────────────────────────────────────────
                e.id                                        AS episode_id,
                e.pid,
                e.start_datetime,
                DATEDIFF(NOW(), e.start_datetime)           AS days_on_service,
                pd.fname,
                pd.lname,
                pd.sex,
                TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE())     AS age,

                -- ── HBC overlay ──────────────────────────────────────────────
                hbc.service_address_line1,
                hbc.service_city,
                hbc.service_state_province,
                hbc.primary_diagnosis,
                hbc.urgency,
                hbc.referral_status,
                hbc.cert_period_end,
                hbc.authorized_visits_per_week,
                CONCAT(COALESCE(uc.fname,''),' ',COALESCE(uc.lname,''))
                                                            AS clinician_name,

                -- ── Next scheduled visit ─────────────────────────────────────
                (
                    SELECT v.scheduled_datetime
                    FROM   oei_hbc_visit v
                    WHERE  v.episode_id = e.id
                      AND  v.status     = 'SCHEDULED'
                      AND  v.scheduled_datetime >= NOW()
                    ORDER  BY v.scheduled_datetime ASC
                    LIMIT  1
                ) AS next_visit_datetime,
                (
                    SELECT v.visit_type
                    FROM   oei_hbc_visit v
                    WHERE  v.episode_id = e.id
                      AND  v.status     = 'SCHEDULED'
                      AND  v.scheduled_datetime >= NOW()
                    ORDER  BY v.scheduled_datetime ASC
                    LIMIT  1
                ) AS next_visit_type,
                (
                    SELECT CONCAT(COALESCE(vc.fname,''),' ',COALESCE(vc.lname,''))
                    FROM   oei_hbc_visit v
                    LEFT   JOIN users vc ON vc.id = v.clinician_user_id
                    WHERE  v.episode_id = e.id
                      AND  v.status     = 'SCHEDULED'
                      AND  v.scheduled_datetime >= NOW()
                    ORDER  BY v.scheduled_datetime ASC
                    LIMIT  1
                ) AS next_visit_clinician,

                -- ── Total visits this cert period ────────────────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_hbc_visit v
                    WHERE  v.episode_id = e.id
                      AND  v.status     = 'COMPLETE'
                ) AS completed_visits,

                -- ── Latest vitals ─────────────────────────────────────────────
                (SELECT t.bp_systolic   FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS bp_sys,
                (SELECT t.bp_diastolic  FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS bp_dia,
                (SELECT t.hr            FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS hr,
                (SELECT t.spo2          FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS spo2,
                (SELECT t.weight_kg     FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS weight_kg,
                (SELECT t.noted_datetime FROM oei_triage t WHERE t.episode_id = e.id ORDER BY t.id DESC LIMIT 1) AS vitals_datetime,

                -- ── MAR: overdue pending administrations ─────────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_mar_administration ma
                    WHERE  ma.episode_id        = e.id
                      AND  ma.outcome           = 'PENDING'
                      AND  ma.scheduled_datetime <= NOW()
                ) AS pending_mar_count,

                -- ── Incidents this week ───────────────────────────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_incident inc
                    WHERE  inc.episode_id       = e.id
                      AND  inc.incident_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ) AS recent_incident_count,

                -- ── Discharge / closure plan ─────────────────────────────────
                (
                    SELECT d.disposition_code
                    FROM   oei_episode_disposition d
                    WHERE  d.episode_id = e.id
                    LIMIT  1
                ) AS pending_disposition,
                (
                    SELECT d.destination
                    FROM   oei_episode_disposition d
                    WHERE  d.episode_id = e.id
                    LIMIT  1
                ) AS pending_destination,

                -- ── Care plan: most recent active goal ───────────────────────
                (
                    SELECT SUBSTR(cp.description, 1, 120)
                    FROM   form_care_plan cp
                    WHERE  cp.pid            = e.pid
                      AND  cp.care_plan_type  = 'goal'
                      AND  cp.plan_status     = 'active'
                    ORDER  BY cp.date DESC, cp.id DESC
                    LIMIT  1
                ) AS care_plan_goal,

                -- ── Fall risk: days since last reassessment ──────────────────
                (
                    SELECT DATEDIFF(NOW(), fra.assessed_datetime)
                    FROM   oei_fall_risk_assessment fra
                    WHERE  fra.episode_id = e.id
                    ORDER  BY fra.assessed_datetime DESC
                    LIMIT  1
                ) AS days_since_fall_reassess,
                (
                    SELECT fra.risk_level
                    FROM   oei_fall_risk_assessment fra
                    WHERE  fra.episode_id = e.id
                    ORDER  BY fra.assessed_datetime DESC
                    LIMIT  1
                ) AS fall_risk_level,

                -- ── Supervisory visit compliance ─────────────────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_hbc_visit v
                    WHERE  v.episode_id = e.id
                      AND  v.visit_type = 'HHA'
                      AND  v.status     = 'COMPLETE'
                ) AS hha_visit_count,
                (
                    SELECT DATEDIFF(NOW(), MAX(v.actual_end_datetime))
                    FROM   oei_hbc_visit v
                    WHERE  v.episode_id = e.id
                      AND  v.is_supervisory = 1
                      AND  v.status = 'COMPLETE'
                ) AS days_since_supervisory

            FROM   oei_episode e
            JOIN   patient_data pd          ON pd.pid        = e.pid
            LEFT   JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
            LEFT   JOIN users uc            ON uc.id = hbc.primary_clinician_user_id AND uc.active = 1

            WHERE  e.facility_id = ?
              AND  e.type        = 'HBC'
              AND  e.status      = 'ACTIVE'

            ORDER  BY e.start_datetime ASC",
            [$facilityId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Facility-level summary counts for the handoff header badges.
     * @return array<string,int>
     */
    public function fetchSummary(int $facilityId): array
    {
        if (!function_exists('sqlQuery')) { return []; }
        $row = sqlQuery(
            "SELECT
                COUNT(*)                                                AS total_active,
                SUM(CASE WHEN d.disposition_code IS NOT NULL THEN 1 ELSE 0 END) AS pending_closure,
                SUM(CASE WHEN hbc.urgency = 'EMERGENT' THEN 1 ELSE 0 END)       AS urgent_active
             FROM   oei_episode e
             LEFT   JOIN oei_hbc_episode hbc       ON hbc.episode_id = e.id
             LEFT   JOIN oei_episode_disposition d ON d.episode_id   = e.id
             WHERE  e.facility_id = ? AND e.type = 'HBC' AND e.status = 'ACTIVE'",
            [$facilityId]
        );
        return [
            'total_active'    => (int)($row['total_active']    ?? 0),
            'pending_closure' => (int)($row['pending_closure'] ?? 0),
            'urgent_active'   => (int)($row['urgent_active']   ?? 0),
        ];
    }
}










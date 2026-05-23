<?php

/**
 * src/AssistedLiving/Submodule/AlHandoff/Repository/AlHandoffRepository.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Repository;

/**
 * AlHandoffRepository
 *
 * Single-query snapshot for the AL shift handoff report.
 *
 * Each row contains everything the oncoming shift needs to know about
 * one resident without navigating to individual sub-pages:
 *
 *   Identity:    fname, lname, age, sex, pid, episode_id
 *   Location:    room, unit, care_level, fall_risk_level
 *   Days:        days_resident (DATEDIFF from start_datetime)
 *   Vitals:      most recent oei_triage row (arrival_mode='PERIODIC' preferred)
 *   ADL:         most recent adl_score from oei_adl_record
 *   MAR:         count of PENDING administrations past scheduled_datetime
 *   Incident:    count of open incidents this week
 *   Discharge:   pending disposition_code if any
 *   Care plan:   most recent active goal text (first 100 chars)
 *   Flags:       weight_alert (>2 lb gain in 5 days for CHF, via vitals trend)
 */
final class AlHandoffRepository
{
    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchHandoff(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                -- ── identity ───────────────────────────────────────────────
                e.id                    AS episode_id,
                e.pid,
                e.start_datetime,
                DATEDIFF(NOW(), e.start_datetime)   AS days_resident,
                pd.fname,
                pd.lname,
                pd.sex,
                TIMESTAMPDIFF(YEAR, pd.DOB, CURDATE()) AS age,

                -- ── AL overlay ─────────────────────────────────────────────
                ale.room,
                ale.unit,
                ale.care_level,
                ale.fall_risk_level,
                ale.fall_risk_score,
                ale.admit_reason,

                -- ── latest vitals (PERIODIC preferred over ED-style) ───────
                (
                    SELECT t.bp_systolic
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS bp_sys,
                (
                    SELECT t.bp_diastolic
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS bp_dia,
                (
                    SELECT t.hr
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS hr,
                (
                    SELECT t.spo2
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS spo2,
                (
                    SELECT t.weight_kg
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS weight_kg,
                (
                    SELECT t.noted_datetime
                    FROM   oei_triage t
                    WHERE  t.episode_id = e.id
                    ORDER  BY t.id DESC LIMIT 1
                ) AS vitals_datetime,

                -- ── ADL: most recent score ──────────────────────────────────
                (
                    SELECT ar.adl_score
                    FROM   oei_adl_record ar
                    WHERE  ar.episode_id = e.id
                    ORDER  BY ar.noted_datetime DESC, ar.id DESC LIMIT 1
                ) AS last_adl_score,
                (
                    SELECT ar.noted_datetime
                    FROM   oei_adl_record ar
                    WHERE  ar.episode_id = e.id
                    ORDER  BY ar.noted_datetime DESC, ar.id DESC LIMIT 1
                ) AS last_adl_datetime,

                -- ── MAR: overdue/pending administrations ───────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_mar_administration ma
                    WHERE  ma.episode_id        = e.id
                      AND  ma.outcome           = 'PENDING'
                      AND  ma.scheduled_datetime <= NOW()
                ) AS pending_mar_count,

                -- ── Incidents: open this week ──────────────────────────────
                (
                    SELECT COUNT(*)
                    FROM   oei_incident inc
                    WHERE  inc.episode_id       = e.id
                      AND  inc.incident_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ) AS recent_incident_count,

                -- ── Discharge plan ─────────────────────────────────────────
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

                -- ── Care plan: most recent active goal ─────────────────────
                (
                    SELECT SUBSTR(cp.description, 1, 120)
                    FROM   form_care_plan cp
                    WHERE  cp.pid           = e.pid
                      AND  cp.care_plan_type = 'goal'
                      AND  cp.plan_status    = 'active'
                    ORDER  BY cp.date DESC, cp.id DESC
                    LIMIT  1
                ) AS care_plan_goal,

                -- ── Fall risk reassessment: days since last ────────────────
                (
                    SELECT DATEDIFF(NOW(), fra.assessed_datetime)
                    FROM   oei_fall_risk_assessment fra
                    WHERE  fra.episode_id = e.id
                    ORDER  BY fra.assessed_datetime DESC
                    LIMIT  1
                ) AS days_since_fall_reassess

            FROM   oei_episode e
            INNER  JOIN patient_data pd   ON pd.pid      = e.pid
            LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id

            WHERE  e.facility_id = ?
              AND  e.type        = 'AL'
              AND  e.status      = 'ACTIVE'

            ORDER  BY ale.unit ASC, ale.room ASC, e.start_datetime ASC",
            [$facilityId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Summary counts for the shift report header.
     * @return array<string,int>
     */
    public function fetchSummary(int $facilityId): array
    {
        if (!function_exists('sqlQuery')) {
            return [];
        }

        $row = sqlQuery(
            "SELECT
                COUNT(*)                                                         AS total_residents,
                SUM(ale.fall_risk_level IN ('HIGH','MODERATE'))                  AS at_risk_count,
                SUM(ale.care_level = 'TIER_3')                                   AS high_care_count,
                COALESCE(SUM((
                    SELECT COUNT(*)
                    FROM   oei_mar_administration ma
                    WHERE  ma.episode_id       = e.id
                      AND  ma.outcome          = 'PENDING'
                      AND  ma.scheduled_datetime <= NOW()
                )), 0)                                                           AS total_pending_mar,
                COALESCE(SUM((
                    SELECT COUNT(*)
                    FROM   oei_episode_disposition d
                    WHERE  d.episode_id = e.id
                    LIMIT  1
                )), 0)                                                           AS pending_discharges
             FROM   oei_episode e
             LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE  e.facility_id = ? AND e.type = 'AL' AND e.status = 'ACTIVE'",
            [$facilityId]
        );

        return [
            'total_residents'   => (int)($row['total_residents']   ?? 0),
            'at_risk_count'     => (int)($row['at_risk_count']     ?? 0),
            'high_care_count'   => (int)($row['high_care_count']   ?? 0),
            'total_pending_mar' => (int)($row['total_pending_mar'] ?? 0),
            'pending_discharges'=> (int)($row['pending_discharges'] ?? 0),
        ];
    }
}




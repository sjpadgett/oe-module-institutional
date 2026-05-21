<?php

/**
 * src/Shared/Submodule/Handoff/Repository/HandoffRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Handoff\Repository;

/**
 * HandoffRepository
 *
 * Assembles the shift handoff snapshot in a single compound query.
 *
 * Each row includes:
 *   Episode:     id, pid, type, start_datetime, chief_complaint, acuity_esi, disposition
 *   Location:    location_name
 *   Status:      workflow_status
 *   Vitals:      bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, noted_datetime
 *   Task:        next_task_type, next_task_due
 *   MAR:         pending_mar_count, awaiting_cosign_count, mar_followup_count,
 *                last_mar_outcome, last_mar_datetime, last_mar_drug
 *   Assignments: nurse_name, provider_name
 */
final class HandoffRepository
{
    /**
     * Return all active episodes for a facility, enriched for handoff display.
     * Ordered by location name ASC (unassigned rooms last), then arrival time.
     *
     * User JOINs apply the standard active/username/fname filter so that
     * deactivated or incomplete accounts never surface as staff names.
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchHandoff(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                e.id,
                e.pid,
                e.type,
                e.start_datetime,
                e.chief_complaint,
                e.acuity_esi,
                e.disposition,
                e.assigned_nurse_user_id,
                e.assigned_provider_user_id,

                l.name  AS location_name,
                l.code  AS location_code,

                -- IP overlay fields (NULL for non-IP episodes)
                COALESCE(ip.bed,  '') AS ip_bed,
                COALESCE(ip.unit, '') AS ip_unit,

                (
                    SELECT sh.status_code
                    FROM oei_episode_status_history sh
                    WHERE sh.episode_id = e.id
                    ORDER BY sh.set_datetime DESC, sh.id DESC
                    LIMIT 1
                ) AS workflow_status,

                tv.bp_systolic,
                tv.bp_diastolic,
                tv.hr,
                tv.rr,
                tv.temp_f,
                tv.spo2,
                tv.gcs,
                tv.pain_score,
                tv.noted_datetime AS vitals_datetime,

                (
                    SELECT t.task_type
                    FROM oei_task t
                    WHERE t.episode_id = e.id AND t.status = 'OPEN'
                    ORDER BY t.due_datetime ASC, t.id ASC
                    LIMIT 1
                ) AS next_task_type,

                (
                    SELECT t.due_datetime
                    FROM oei_task t
                    WHERE t.episode_id = e.id AND t.status = 'OPEN'
                    ORDER BY t.due_datetime ASC, t.id ASC
                    LIMIT 1
                ) AS next_task_due,

                (
                    SELECT COUNT(*)
                    FROM oei_mar_administration ma
                    WHERE ma.episode_id = e.id AND ma.outcome = 'PENDING'
                      AND ma.scheduled_datetime <= NOW()
                ) AS pending_mar_count,

                (
                    SELECT COUNT(*)
                    FROM oei_mar_administration ma
                    WHERE ma.episode_id = e.id
                      AND ma.outcome = 'GIVEN'
                      AND ma.is_high_alert = 1
                      AND (ma.co_sign_user_id IS NULL OR ma.co_sign_user_id = 0)
                ) AS awaiting_cosign_count,

                (
                    SELECT COUNT(*)
                    FROM oei_task t
                    WHERE t.episode_id = e.id
                      AND t.status = 'OPEN'
                      AND t.task_type IN ('MAR_RETRY_DOSE','MAR_PHARMACY_FOLLOWUP','MAR_EXCEPTION_REVIEW')
                ) AS mar_followup_count,

                (
                    SELECT ma2.outcome
                    FROM oei_mar_administration ma2
                    WHERE ma2.episode_id = e.id
                      AND ma2.outcome <> 'PENDING'
                    ORDER BY COALESCE(ma2.administered_datetime, ma2.scheduled_datetime) DESC, ma2.id DESC
                    LIMIT 1
                ) AS last_mar_outcome,

                (
                    SELECT COALESCE(ma2.administered_datetime, ma2.scheduled_datetime)
                    FROM oei_mar_administration ma2
                    WHERE ma2.episode_id = e.id
                      AND ma2.outcome <> 'PENDING'
                    ORDER BY COALESCE(ma2.administered_datetime, ma2.scheduled_datetime) DESC, ma2.id DESC
                    LIMIT 1
                ) AS last_mar_datetime,

                (
                    SELECT mo.drug_name
                    FROM oei_mar_administration ma2
                    JOIN oei_mar_order mo ON mo.id = ma2.mar_order_id
                    WHERE ma2.episode_id = e.id
                      AND ma2.outcome <> 'PENDING'
                    ORDER BY COALESCE(ma2.administered_datetime, ma2.scheduled_datetime) DESC, ma2.id DESC
                    LIMIT 1
                ) AS last_mar_drug,

                CONCAT(COALESCE(nu.fname, ''), ' ', COALESCE(nu.lname, '')) AS nurse_name,
                CONCAT(COALESCE(pu.fname, ''), ' ', COALESCE(pu.lname, '')) AS provider_name

            FROM oei_episode e

            LEFT JOIN oei_episode_location el
                ON el.episode_id = e.id AND el.end_datetime IS NULL
            LEFT JOIN oei_location l
                ON l.id = el.location_id

            LEFT JOIN oei_ip_episode ip
                ON ip.episode_id = e.id

            LEFT JOIN (
                SELECT t2.episode_id, t2.bp_systolic, t2.bp_diastolic,
                       t2.hr, t2.rr, t2.temp_f, t2.spo2, t2.gcs,
                       t2.pain_score, t2.noted_datetime
                FROM oei_triage t2
                INNER JOIN (
                    SELECT episode_id, MAX(id) AS max_id
                    FROM oei_triage
                    GROUP BY episode_id
                ) latest ON latest.episode_id = t2.episode_id
                        AND latest.max_id      = t2.id
            ) tv ON tv.episode_id = e.id

            LEFT JOIN users nu ON nu.id = e.assigned_nurse_user_id
                               AND nu.active = 1
                               AND nu.username IS NOT NULL
                               AND nu.fname IS NOT NULL
            LEFT JOIN users pu ON pu.id = e.assigned_provider_user_id
                               AND pu.active = 1
                               AND pu.username IS NOT NULL
                               AND pu.fname IS NOT NULL

            WHERE e.facility_id = ?
              AND e.status = 'ACTIVE'

            ORDER BY
                l.name IS NULL ASC,
                l.name ASC,
                e.start_datetime ASC",
            [$facilityId]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}










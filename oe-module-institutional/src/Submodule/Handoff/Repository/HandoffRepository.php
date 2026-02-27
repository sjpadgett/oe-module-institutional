<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Handoff\Repository;

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
 *   MAR:         pending_mar_count
 *   Assignments: nurse_name, provider_name
 */
final class HandoffRepository
{
    /**
     * Return all active episodes for a facility, enriched for handoff display.
     * Ordered by location name ASC (unassigned rooms last), then arrival time.
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

                CONCAT(COALESCE(nu.fname, ''), ' ', COALESCE(nu.lname, '')) AS nurse_name,
                CONCAT(COALESCE(pu.fname, ''), ' ', COALESCE(pu.lname, '')) AS provider_name

            FROM oei_episode e

            LEFT JOIN oei_episode_location el
                ON el.episode_id = e.id AND el.end_datetime IS NULL
            LEFT JOIN oei_location l
                ON l.id = el.location_id

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
            LEFT JOIN users pu ON pu.id = e.assigned_provider_user_id

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

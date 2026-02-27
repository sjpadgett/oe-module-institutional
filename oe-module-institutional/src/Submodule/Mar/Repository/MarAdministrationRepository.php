<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Mar\Repository;

/**
 * Medication Administration repository.
 * Operates on oei_mar_administration.
 */
final class MarAdministrationRepository
{
    // ------------------------------------------------------------------ reads

    /**
     * All administration rows for an episode, used to render the MAR grid.
     * Joined with order to get drug_name, and with users to get nurse name.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid,
                    a.scheduled_datetime, a.administered_datetime,
                    a.outcome, a.dose_given, a.unit_given, a.route_given,
                    a.site, a.lot_number, a.administered_by_user_id,
                    a.hold_reason, a.note, a.is_high_alert,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn,
                    CONCAT_WS(' ', u.fname, u.lname) AS nurse_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN users u ON u.id = a.administered_by_user_id
             WHERE a.episode_id = ?
             ORDER BY a.scheduled_datetime ASC, o.drug_name ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * All PENDING administrations for an episode (charge nurse dashboard use).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listPendingByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.scheduled_datetime,
                    a.is_high_alert, o.drug_name, o.dose, o.unit, o.route
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             WHERE a.episode_id = ? AND a.outcome = 'PENDING'
             ORDER BY a.scheduled_datetime ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * All overdue PENDING rows across a facility (for AlertService).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listOverdueByFacility(int $facilityId, int $graceMinutes = 15): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $cutoff = date('Y-m-d H:i:s', time() - ($graceMinutes * 60));
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid,
                    a.scheduled_datetime, a.is_high_alert,
                    o.drug_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             WHERE a.facility_id = ?
               AND a.outcome = 'PENDING'
               AND a.scheduled_datetime <= ?
             ORDER BY a.scheduled_datetime ASC
             LIMIT 200",
            [$facilityId, $cutoff]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Latest scheduled datetime for a given order (used to extend the window).
     */
    public function latestScheduledDatetime(int $marOrderId): ?string
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT MAX(scheduled_datetime) AS latest
             FROM oei_mar_administration
             WHERE mar_order_id = ?",
            [$marOrderId]
        );
        return ($row['latest'] ?? null) ?: null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_mar_administration WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    // ----------------------------------------------------------------- writes

    /**
     * Insert a scheduled administration slot (outcome = PENDING).
     * Used when a new order is created or a protocol generates slots.
     */
    public function createScheduled(
        int $marOrderId,
        int $episodeId,
        int $pid,
        int $facilityId,
        string $scheduledDatetime,
        bool $isHighAlert = false
    ): int {
        if (!function_exists('sqlStatement')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_mar_administration
               (mar_order_id, episode_id, pid, facility_id,
                scheduled_datetime, outcome, is_high_alert,
                created_datetime, updated_datetime)
             VALUES (?,?,?,?,?,'PENDING',?,?,?)",
            [$marOrderId, $episodeId, $pid, $facilityId,
             $scheduledDatetime, (int)$isHighAlert, $now, $now]
        );
        return (int)($GLOBALS['lastidado'] > 0 ? $GLOBALS['lastidado'] : $GLOBALS['adodb']['db']->Insert_ID());
    }

    /**
     * Insert an unscheduled PRN slot (no scheduled_datetime).
     * outcome defaults to PENDING; caller calls record() immediately after.
     */
    public function createPrn(
        int $marOrderId,
        int $episodeId,
        int $pid,
        int $facilityId,
        bool $isHighAlert = false
    ): int {
        if (!function_exists('sqlStatement')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_mar_administration
               (mar_order_id, episode_id, pid, facility_id,
                outcome, is_high_alert, created_datetime, updated_datetime)
             VALUES (?,?,?,'PENDING',?,?,?)",
            [$marOrderId, $episodeId, $pid, $facilityId,
             (int)$isHighAlert, $now, $now]
        );
        return (int)($GLOBALS['lastidado'] > 0 ? $GLOBALS['lastidado'] : $GLOBALS['adodb']['db']->Insert_ID());
    }

    /**
     * Record the outcome of an administration slot (nurse documents the dose).
     *
     * @param string      $outcome              GIVEN | HELD | REFUSED | NOT_AVAILABLE | MISSED
     * @param string|null $administeredDatetime Nurse-supplied or server time; always set for all non-PENDING outcomes
     */
    public function record(
        int $adminId,
        string $outcome,
        ?string $administeredDatetime,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $administeredByUserId,
        ?string $holdReason,
        ?string $note
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $allowed = ['GIVEN', 'HELD', 'REFUSED', 'NOT_AVAILABLE', 'MISSED'];
        if (!in_array($outcome, $allowed, true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        // Always record a timestamp — when the nurse made the clinical decision
        $administeredDatetime = $administeredDatetime ?: $now;
        sqlStatement(
            "UPDATE oei_mar_administration
             SET outcome = ?,
                 administered_datetime = ?,
                 dose_given = ?,
                 unit_given = ?,
                 route_given = ?,
                 site = ?,
                 lot_number = ?,
                 administered_by_user_id = ?,
                 hold_reason = ?,
                 note = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [
                $outcome, $administeredDatetime,
                $doseGiven, $unitGiven, $routeGiven,
                $site, $lotNumber, $administeredByUserId,
                $holdReason, $note, $now, $adminId,
            ]
        );
    }

    /**
     * Amend a completed (non-PENDING) administration row.
     * Records who amended and when via the note field prefix.
     */
    public function amend(
        int $adminId,
        string $outcome,
        ?string $administeredDatetime,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $amendedByUserId,
        ?string $holdReason,
        ?string $note
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $allowed = ['GIVEN', 'HELD', 'REFUSED', 'NOT_AVAILABLE', 'MISSED'];
        if (!in_array($outcome, $allowed, true)) {
            return;
        }
        $now       = date('Y-m-d H:i:s');
        $amendNote = '[Amended ' . $now . ' by user ' . ($amendedByUserId ?? '?') . '] ' . ($note ?? '');
        sqlStatement(
            "UPDATE oei_mar_administration
             SET outcome = ?,
                 administered_datetime = ?,
                 dose_given = ?,
                 unit_given = ?,
                 route_given = ?,
                 site = ?,
                 lot_number = ?,
                 administered_by_user_id = ?,
                 hold_reason = ?,
                 note = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [
                $outcome, $administeredDatetime ?: $now,
                $doseGiven, $unitGiven, $routeGiven,
                $site, $lotNumber, $amendedByUserId,
                $holdReason, $amendNote, $now, $adminId,
            ]
        );
    }

    /**
     * Mark all PENDING slots for an order as MISSED.
     * Called when an order is discontinued.
     */
    public function voidPendingForOrder(int $marOrderId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_mar_administration
             SET outcome = 'MISSED', note = 'Order discontinued', updated_datetime = ?
             WHERE mar_order_id = ? AND outcome = 'PENDING'",
            [$now, $marOrderId]
        );
    }
}

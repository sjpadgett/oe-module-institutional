<?php

/**
 * src/Shared/Submodule/Mar/Repository/MarAdministrationRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository;

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
                    a.witness_user_id, a.waste_amount, a.waste_unit,
                    a.co_sign_user_id, a.co_signed_datetime,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn,
                    CONCAT_WS(' ', u.fname, u.lname) AS nurse_name,
                    CONCAT_WS(' ', w.fname, w.lname) AS witness_name,
                    CONCAT_WS(' ', cs.fname, cs.lname) AS co_sign_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN users u ON u.id = a.administered_by_user_id
             LEFT JOIN users w ON w.id = a.witness_user_id
             LEFT JOIN users cs ON cs.id = a.co_sign_user_id
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
             VALUES (?,?,?,?,'PENDING',?,?,?)",
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
        ?string $note,
        ?int $witnessUserId = null,
        ?string $wasteAmount = null,
        ?string $wasteUnit = null
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
                 witness_user_id = ?,
                 waste_amount = ?,
                 waste_unit = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [
                $outcome, $administeredDatetime,
                $doseGiven, $unitGiven, $routeGiven,
                $site, $lotNumber, $administeredByUserId,
                $holdReason, $note,
                $witnessUserId, $wasteAmount, $wasteUnit,
                $now, $adminId,
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
        ?string $note,
        ?int $witnessUserId = null,
        ?string $wasteAmount = null,
        ?string $wasteUnit = null
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
                 witness_user_id = ?,
                 waste_amount = ?,
                 waste_unit = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [
                $outcome, $administeredDatetime ?: $now,
                $doseGiven, $unitGiven, $routeGiven,
                $site, $lotNumber, $amendedByUserId,
                $holdReason, $amendNote,
                $witnessUserId, $wasteAmount, $wasteUnit,
                $now, $adminId,
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

    /**
     * All administrations for a facility within a shift time window.
     * Used by shift_summary.php.  Includes order + nurse info.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByShift(int $facilityId, string $shiftStart, string $shiftEnd): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid,
                    a.scheduled_datetime, a.administered_datetime,
                    a.outcome, a.dose_given, a.unit_given, a.route_given,
                    a.site, a.lot_number, a.hold_reason, a.note,
                    a.is_high_alert,
                    a.witness_user_id, a.waste_amount, a.waste_unit,
                    a.administered_by_user_id,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn, o.is_stat,
                    CONCAT_WS(' ', u.fname, u.lname)  AS nurse_name,
                    CONCAT_WS(' ', w.fname, w.lname)  AS witness_name,
                    e.type AS episode_type
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN oei_episode e ON e.id = a.episode_id
             LEFT JOIN users u ON u.id = a.administered_by_user_id
             LEFT JOIN users w ON w.id = a.witness_user_id
             WHERE a.facility_id = ?
               AND a.outcome != 'PENDING'
               AND (
                   (a.administered_datetime >= ? AND a.administered_datetime < ?)
                   OR
                   (a.administered_datetime IS NULL AND a.scheduled_datetime >= ? AND a.scheduled_datetime < ?)
               )
             ORDER BY a.episode_id ASC, o.drug_name ASC, a.scheduled_datetime ASC",
            [$facilityId, $shiftStart, $shiftEnd, $shiftStart, $shiftEnd]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) { $rows[] = $row; }
        return $rows;
    }

    /**
     * Record a co-signer on a GIVEN high-alert administration.
     * Idempotent: the WHERE guard prevents overwriting an existing co-signer
     * and only matches rows that are GIVEN + is_high_alert + not yet co-signed.
     */
    public function coSign(int $adminId, int $coSignUserId): bool
    {
        if (!function_exists('sqlStatement')) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_mar_administration
             SET co_sign_user_id = ?,
                 co_signed_datetime = ?,
                 updated_datetime = ?
             WHERE id = ?
               AND outcome = 'GIVEN'
               AND is_high_alert = 1
               AND co_sign_user_id IS NULL",
            [$coSignUserId, $now, $now, $adminId]
        );
        return true;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listPendingWorkspaceByEpisode(int $episodeId): array
    {
        return $this->listPendingWorkspace('a.episode_id = ?', [$episodeId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listPendingWorkspaceByFacility(int $facilityId): array
    {
        return $this->listPendingWorkspace('a.facility_id = ?', [$facilityId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAwaitingCoSignByEpisode(int $episodeId): array
    {
        return $this->listAwaitingCoSign('a.episode_id = ?', [$episodeId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listAwaitingCoSignByFacility(int $facilityId): array
    {
        return $this->listAwaitingCoSign('a.facility_id = ?', [$facilityId]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentPrnByEpisode(int $episodeId, int $hours = 8): array
    {
        return $this->listRecentPrn('a.episode_id = ?', [$episodeId], $hours);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentPrnByFacility(int $facilityId, int $hours = 8): array
    {
        return $this->listRecentPrn('a.facility_id = ?', [$facilityId], $hours);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentHighAlertByEpisode(int $episodeId, int $hours = 12): array
    {
        return $this->listRecentHighAlert('a.episode_id = ?', [$episodeId], $hours);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecentHighAlertByFacility(int $facilityId, int $hours = 12): array
    {
        return $this->listRecentHighAlert('a.facility_id = ?', [$facilityId], $hours);
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function listPendingWorkspace(string $where, array $params): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid, a.facility_id,
                    a.scheduled_datetime, a.is_high_alert,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn,
                    CONCAT_WS(' ', pd.fname, pd.lname) AS patient_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN patient_data pd ON pd.pid = a.pid
             WHERE {$where}
               AND a.outcome = 'PENDING'
             ORDER BY a.scheduled_datetime ASC",
            $params
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function listAwaitingCoSign(string $where, array $params): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid, a.facility_id,
                    a.administered_datetime, a.scheduled_datetime, a.is_high_alert,
                    a.witness_user_id, a.waste_amount, a.waste_unit,
                    a.co_sign_user_id, a.co_signed_datetime,
                    a.administered_by_user_id,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency,
                    CONCAT_WS(' ', pd.fname, pd.lname) AS patient_name,
                    CONCAT_WS(' ', u.fname, u.lname) AS nurse_name,
                    CONCAT_WS(' ', w.fname, w.lname) AS witness_name,
                    CONCAT_WS(' ', cs.fname, cs.lname) AS co_sign_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN patient_data pd ON pd.pid = a.pid
             LEFT JOIN users u ON u.id = a.administered_by_user_id
             LEFT JOIN users w ON w.id = a.witness_user_id
             LEFT JOIN users cs ON cs.id = a.co_sign_user_id
             WHERE {$where}
               AND a.outcome = 'GIVEN'
               AND a.is_high_alert = 1
               AND (a.co_sign_user_id IS NULL OR a.co_sign_user_id = 0)
             ORDER BY COALESCE(a.administered_datetime, a.scheduled_datetime) DESC",
            $params
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function listRecentPrn(string $where, array $params, int $hours): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $since = date('Y-m-d H:i:s', time() - (max(1, $hours) * 3600));
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid, a.facility_id,
                    a.administered_datetime, a.scheduled_datetime, a.note, a.outcome,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn,
                    CONCAT_WS(' ', pd.fname, pd.lname) AS patient_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN patient_data pd ON pd.pid = a.pid
             WHERE {$where}
               AND o.is_prn = 1
               AND a.outcome = 'GIVEN'
               AND COALESCE(a.administered_datetime, a.scheduled_datetime) >= ?
             ORDER BY COALESCE(a.administered_datetime, a.scheduled_datetime) DESC",
            array_merge($params, [$since])
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    private function listRecentHighAlert(string $where, array $params, int $hours): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $since = date('Y-m-d H:i:s', time() - (max(1, $hours) * 3600));
        $res = sqlStatement(
            "SELECT a.id, a.mar_order_id, a.episode_id, a.pid, a.facility_id,
                    a.administered_datetime, a.scheduled_datetime, a.note, a.outcome,
                    a.is_high_alert, a.administered_by_user_id,
                    a.witness_user_id, a.waste_amount, a.waste_unit,
                    a.co_sign_user_id, a.co_signed_datetime,
                    o.drug_name, o.dose AS ordered_dose, o.unit AS ordered_unit,
                    o.route AS ordered_route, o.frequency, o.is_prn,
                    CONCAT_WS(' ', pd.fname, pd.lname) AS patient_name,
                    CONCAT_WS(' ', u.fname, u.lname) AS nurse_name,
                    CONCAT_WS(' ', w.fname, w.lname) AS witness_name,
                    CONCAT_WS(' ', cs.fname, cs.lname) AS co_sign_name
             FROM oei_mar_administration a
             JOIN oei_mar_order o ON o.id = a.mar_order_id
             LEFT JOIN patient_data pd ON pd.pid = a.pid
             LEFT JOIN users u ON u.id = a.administered_by_user_id
             LEFT JOIN users w ON w.id = a.witness_user_id
             LEFT JOIN users cs ON cs.id = a.co_sign_user_id
             WHERE {$where}
               AND a.is_high_alert = 1
               AND a.outcome = 'GIVEN'
               AND COALESCE(a.administered_datetime, a.scheduled_datetime) >= ?
             ORDER BY COALESCE(a.administered_datetime, a.scheduled_datetime) DESC",
            array_merge($params, [$since])
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

}















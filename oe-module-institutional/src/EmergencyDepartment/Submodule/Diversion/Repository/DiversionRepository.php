<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Diversion\Repository;

/**
 * DiversionRepository
 *
 * Manages facility-level diversion status per service line.
 * Operates on oei_diversion (current) and oei_diversion_history (audit log).
 */
final class DiversionRepository
{
    // ── Reads ────────────────────────────────────────────────────────────────

    /** @return array<string,mixed>|null */
    public function getCurrent(int $facilityId, string $serviceLine = 'ED'): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_diversion
             WHERE facility_id = ? AND service_line = ?
             LIMIT 1",
            [$facilityId, $serviceLine]
        );
        return $row ?: null;
    }

    /**
     * All service lines for one facility — used by ED Board and directory badges.
     * @return array<int,array<string,mixed>>
     */
    public function listByFacility(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT * FROM oei_diversion
             WHERE facility_id = ?
             ORDER BY service_line ASC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Multi-facility overview — all entries with a non-OPEN status.
     * @param int[] $facilityIds  empty = all facilities
     * @return array<int,array<string,mixed>>
     */
    public function listDiverted(array $facilityIds = []): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $where  = "d.status != 'OPEN'";
        $params = [];
        if (!empty($facilityIds)) {
            $in     = implode(',', array_fill(0, count($facilityIds), '?'));
            $where .= " AND d.facility_id IN ($in)";
            $params = array_values($facilityIds);
        }
        $res  = sqlStatement(
            "SELECT d.*, f.name AS facility_name
             FROM oei_diversion d
             LEFT JOIN facility f ON f.id = d.facility_id
             WHERE {$where}
             ORDER BY d.updated_datetime DESC",
            $params
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Recent history for one facility / service line.
     * @return array<int,array<string,mixed>>
     */
    public function history(int $facilityId, string $serviceLine = 'ED', int $limit = 50): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT h.*, u.fname, u.lname
             FROM oei_diversion_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.facility_id = ? AND h.service_line = ?
             ORDER BY h.changed_datetime DESC
             LIMIT " . (int)$limit,
            [$facilityId, $serviceLine]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * History across all service lines for one facility.
     * @return array<int,array<string,mixed>>
     */
    public function historyAllLines(int $facilityId, int $limit = 100): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT h.*, u.fname, u.lname
             FROM oei_diversion_history h
             LEFT JOIN users u ON u.id = h.changed_by_user_id
             WHERE h.facility_id = ?
             ORDER BY h.changed_datetime DESC
             LIMIT " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    // ── Writes ───────────────────────────────────────────────────────────────

    /**
     * Upsert the current diversion row and append a history record when status changes.
     * Returns the previous status so the caller can decide whether to fire HL7 A09.
     *
     * @return string|null  Previous status, or null on first insert.
     */
    public function upsert(
        int $facilityId,
        string $serviceLine,
        string $status,
        ?string $reason,
        ?string $diversionStart,
        ?string $diversionEnd,
        ?int $userId
    ): ?string {
        if (!function_exists('sqlStatement')) {
            return null;
        }

        $now        = date('Y-m-d H:i:s');
        $existing   = $this->getCurrent($facilityId, $serviceLine);
        $prevStatus = $existing ? (string)($existing['status'] ?? null) : null;

        sqlStatement(
            "INSERT INTO oei_diversion
               (facility_id, service_line, status, reason,
                diversion_start, diversion_end, updated_by_user_id, updated_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               status             = VALUES(status),
               reason             = VALUES(reason),
               diversion_start    = VALUES(diversion_start),
               diversion_end      = VALUES(diversion_end),
               updated_by_user_id = VALUES(updated_by_user_id),
               updated_datetime   = VALUES(updated_datetime)",
            [
                $facilityId, $serviceLine, $status, $reason,
                $diversionStart, $diversionEnd, $userId, $now,
            ]
        );

        // Only append history when status actually changes
        if ($prevStatus !== $status) {
            sqlStatement(
                "INSERT INTO oei_diversion_history
                   (facility_id, service_line, previous_status, new_status, reason,
                    diversion_start, diversion_end, changed_by_user_id, changed_datetime)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $facilityId, $serviceLine, $prevStatus, $status, $reason,
                    $diversionStart, $diversionEnd, $userId, $now,
                ]
            );
        }

        return $prevStatus;
    }
}

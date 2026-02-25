<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Mar\Repository;

/**
 * Medication Order repository.
 * Operates on oei_mar_order.
 */
final class MarOrderRepository
{
    // ------------------------------------------------------------------ reads

    /**
     * Return all orders for a single episode, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, facility_id,
                    drug_name, dose, unit, route, frequency, is_prn,
                    status, ordered_datetime, discontinued_datetime,
                    ordered_by_user_id, discontinued_by_user_id,
                    rx_id, instructions
             FROM oei_mar_order
             WHERE episode_id = ?
             ORDER BY ordered_datetime DESC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return ACTIVE orders for a single episode (used for the MAR grid).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listActiveByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, facility_id,
                    drug_name, dose, unit, route, frequency, is_prn,
                    status, ordered_datetime, instructions
             FROM oei_mar_order
             WHERE episode_id = ? AND status = 'ACTIVE'
             ORDER BY drug_name ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
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
            "SELECT * FROM oei_mar_order WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    // ----------------------------------------------------------------- writes

    /**
     * Create a new medication order.  Returns the new row id.
     */
    public function create(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        string $dose,
        string $unit,
        string $route,
        string $frequency,
        bool $isPrn,
        string $orderedDatetime,
        ?int $orderedByUserId,
        ?int $rxId = null,
        ?string $instructions = null
    ): int {
        if (!function_exists('sqlStatement')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_mar_order
               (episode_id, pid, facility_id, drug_name, dose, unit, route,
                frequency, is_prn, status, ordered_datetime,
                ordered_by_user_id, rx_id, instructions,
                created_datetime, updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,'ACTIVE',?,?,?,?,?,?)",
            [
                $episodeId, $pid, $facilityId, $drugName, $dose, $unit, $route,
                $frequency, (int)$isPrn, $orderedDatetime,
                $orderedByUserId, $rxId, $instructions, $now, $now,
            ]
        );
        return (int)sqlLastInsertId();
    }

    /**
     * Discontinue (soft-delete) a medication order.
     */
    public function discontinue(int $orderId, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_mar_order
             SET status = 'DISCONTINUED',
                 discontinued_datetime = ?,
                 discontinued_by_user_id = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [$now, $userId, $now, $orderId]
        );
    }
}

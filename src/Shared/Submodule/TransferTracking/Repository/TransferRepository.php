<?php

/**
 * src/Shared/Submodule/TransferTracking/Repository/TransferRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\TransferTracking\Repository;

final class TransferRepository
{
    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery("SELECT * FROM oei_transfer WHERE episode_id = ? LIMIT 1", [$episodeId]);
        return $row ?: null;
    }

    public function upsert(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $type, ?string $reason, ?int $dirId, ?string $recvName,
        ?string $requested, ?string $accepted, ?string $transport,
        string $status, ?string $checklistJson, ?string $notes, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_transfer
               (episode_id, pid, eid, facility_id, transfer_type, reason,
                receiving_directory_id, receiving_name,
                requested_datetime, accepted_datetime, transport_datetime,
                status, checklist_json, notes, updated_by_user_id, updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               transfer_type=VALUES(transfer_type), reason=VALUES(reason),
               receiving_directory_id=VALUES(receiving_directory_id), receiving_name=VALUES(receiving_name),
               requested_datetime=VALUES(requested_datetime), accepted_datetime=VALUES(accepted_datetime),
               transport_datetime=VALUES(transport_datetime), status=VALUES(status),
               checklist_json=VALUES(checklist_json), notes=VALUES(notes),
               updated_by_user_id=VALUES(updated_by_user_id), updated_datetime=VALUES(updated_datetime)",
            [$episodeId,$pid,$eid,$facilityId,$type,$reason,$dirId,$recvName,
             $requested,$accepted,$transport,$status,$checklistJson,$notes,$userId,$now]
        );
    }

    public function updateStatus(int $episodeId, string $status, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) return;
        sqlStatement(
            "UPDATE oei_transfer SET status=?, updated_by_user_id=?, updated_datetime=? WHERE episode_id=?",
            [$status, $userId, date('Y-m-d H:i:s'), $episodeId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecentByFacility(int $facilityId, string $start, string $end, int $limit = 1000): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT t.*, e.pid FROM oei_transfer t
             JOIN oei_episode e ON e.id = t.episode_id
             WHERE t.facility_id = ? AND t.updated_datetime BETWEEN ? AND ?
             ORDER BY t.updated_datetime DESC
             LIMIT " . (int)$limit,
            [$facilityId, $start, $end]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}




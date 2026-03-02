<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Repository;

/**
 * E-Referral repository.
 * Operates on oei_ereferral — one row per episode (upsert on episode_id unique key).
 */
final class EReferralRepository
{
    // ------------------------------------------------------------------ reads

    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT r.*,
                    d.name  AS dir_name,
                    d.fax   AS dir_fax,
                    d.phone AS dir_phone,
                    d.address AS dir_address
             FROM oei_ereferral r
             LEFT JOIN oei_facility_directory d ON d.id = r.destination_directory_id
             WHERE r.episode_id = ?
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /**
     * Referrals by facility — newest first — for the facility dashboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByFacility(int $facilityId, int $limit = 200): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT r.id, r.episode_id, r.pid, r.referral_type, r.status, r.priority,
                    r.destination_name, r.sent_datetime, r.created_datetime, r.updated_datetime,
                    e.chief_complaint, e.acuity_esi, e.disposition AS episode_disposition
             FROM oei_ereferral r
             JOIN oei_episode e ON e.id = r.episode_id
             WHERE r.facility_id = ?
             ORDER BY r.updated_datetime DESC
             LIMIT " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    // ----------------------------------------------------------------- writes

    /**
     * Create or update the referral for an episode.
     * Safe to call repeatedly — ON DUPLICATE KEY on episode_id unique key.
     *
     * @param array<string,mixed> $fields
     */
    public function upsert(int $episodeId, int $pid, ?int $eid, int $facilityId, array $fields, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');

        sqlStatement(
            "INSERT INTO oei_ereferral
               (episode_id, pid, eid, facility_id,
                referral_type, status, priority,
                destination_directory_id, destination_name, destination_fax,
                destination_phone, destination_address,
                reason_for_referral, clinical_summary, services_requested,
                medications_summary, followup_instructions,
                created_by_user_id, created_datetime, updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               referral_type              = VALUES(referral_type),
               status                     = VALUES(status),
               priority                   = VALUES(priority),
               destination_directory_id   = VALUES(destination_directory_id),
               destination_name           = VALUES(destination_name),
               destination_fax            = VALUES(destination_fax),
               destination_phone          = VALUES(destination_phone),
               destination_address        = VALUES(destination_address),
               reason_for_referral        = VALUES(reason_for_referral),
               clinical_summary           = VALUES(clinical_summary),
               services_requested         = VALUES(services_requested),
               medications_summary        = VALUES(medications_summary),
               followup_instructions      = VALUES(followup_instructions),
               updated_datetime           = VALUES(updated_datetime)",
            [
                $episodeId, $pid, $eid, $facilityId,
                $fields['referral_type']             ?? 'DISCHARGE',
                $fields['status']                    ?? 'DRAFT',
                $fields['priority']                  ?? 'ROUTINE',
                $fields['destination_directory_id']  ?? null,
                $fields['destination_name']          ?? null,
                $fields['destination_fax']           ?? null,
                $fields['destination_phone']         ?? null,
                $fields['destination_address']       ?? null,
                $fields['reason_for_referral']       ?? null,
                $fields['clinical_summary']          ?? null,
                $fields['services_requested']        ?? null,
                $fields['medications_summary']       ?? null,
                $fields['followup_instructions']     ?? null,
                $userId,
                $now,
                $now,
            ]
        );
    }

    /**
     * Mark referral as SENT.
     */
    public function markSent(int $episodeId, string $sendMethod, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_ereferral
             SET status = 'SENT', sent_datetime = ?, sent_by_user_id = ?,
                 send_method = ?, updated_datetime = ?
             WHERE episode_id = ?",
            [$now, $userId, strtoupper($sendMethod), $now, $episodeId]
        );
    }

    /**
     * Record incoming response (ACCEPTED / DECLINED).
     */
    public function recordResponse(int $episodeId, string $outcome, ?string $byName, ?string $notes): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $allowed = ['ACCEPTED', 'DECLINED', 'CANCELLED'];
        if (!in_array($outcome, $allowed, true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_ereferral
             SET status = ?, response_datetime = ?, response_by_name = ?,
                 response_notes = ?, updated_datetime = ?
             WHERE episode_id = ?",
            [$outcome, $now, $byName, $notes, $now, $episodeId]
        );
    }
}

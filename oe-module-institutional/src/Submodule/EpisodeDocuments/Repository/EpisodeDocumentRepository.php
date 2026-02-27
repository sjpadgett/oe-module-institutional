<?php

namespace OpenEMR\Modules\Institutional\Submodule\EpisodeDocuments\Repository;

final class EpisodeDocumentRepository
{
    // Document type constants — enforced in UI dropdowns
    public const TYPES = [
        'GENERAL'          => 'General',
        'TRANSFER_PACKET'  => 'Transfer Packet',
        'PHYSICIAN_ORDER'  => 'Physician Order',
        'LAB'              => 'Lab Report',
        'IMAGING'          => 'Imaging Report',
        'CONSENT'          => 'Consent Form',
        'ID'               => 'Patient ID',
        'INSURANCE'        => 'Insurance Card',
        'OTHER'            => 'Other',
    ];

    /**
     * Insert a new document record.
     * Returns the new row ID, or 0 on failure.
     */
    public function create(
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $docType,
        string  $label,
        string  $originalName,
        string  $mimeType,
        int     $fileSize,
        string  $storagePath,
        ?int    $userId,
        ?string $notes
    ): int {
        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_episode_document
               (episode_id, pid, facility_id, doc_type, label, original_name,
                mime_type, file_size, storage_path,
                uploaded_by_user_id, uploaded_datetime, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$episodeId, $pid, $facilityId, $docType, $label, $originalName,
             $mimeType, $fileSize, $storagePath,
             $userId, $now, $notes]
        );
        $row = sqlQuery("SELECT LAST_INSERT_ID() AS id");
        return (int)($row['id'] ?? 0);
    }

    /**
     * List all non-deleted documents for an episode, newest first.
     * @return array<int,array<string,mixed>>
     */
    public function listForEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT d.*, u.fname, u.lname
             FROM oei_episode_document d
             LEFT JOIN users u ON u.id = d.uploaded_by_user_id
             WHERE d.episode_id = ? AND d.is_deleted = 0
             ORDER BY d.uploaded_datetime DESC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * List documents for an episode filtered by type.
     * @return array<int,array<string,mixed>>
     */
    public function listForEpisodeByType(int $episodeId, string $docType): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT d.*, u.fname, u.lname
             FROM oei_episode_document d
             LEFT JOIN users u ON u.id = d.uploaded_by_user_id
             WHERE d.episode_id = ? AND d.doc_type = ? AND d.is_deleted = 0
             ORDER BY d.uploaded_datetime DESC",
            [$episodeId, $docType]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** Fetch a single document row by ID (including deleted). */
    public function findById(int $id): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_episode_document WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    /** Soft-delete a document record. Does NOT delete the physical file. */
    public function softDelete(int $id, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE oei_episode_document SET is_deleted = 1 WHERE id = ?",
            [$id]
        );
    }

    /** Count of documents attached to an episode. */
    public function countForEpisode(int $episodeId): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode_document
             WHERE episode_id = ? AND is_deleted = 0",
            [$episodeId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * Summary counts by doc_type for an episode.
     * @return array<string,int>  type => count
     */
    public function typeSummary(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT doc_type, COUNT(*) AS c
             FROM oei_episode_document
             WHERE episode_id = ? AND is_deleted = 0
             GROUP BY doc_type",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $out[(string)$row['doc_type']] = (int)$row['c'];
        }
        return $out;
    }
}



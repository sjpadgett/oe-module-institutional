<?php

namespace OpenEMR\Modules\Institutional\Submodule\EpisodeDocuments\Service;

use OpenEMR\Modules\Institutional\Submodule\EpisodeDocuments\Repository\EpisodeDocumentRepository;

/**
 * Manages physical file storage for episode documents.
 *
 * Storage layout:
 *   {OE_SITE_DIR}/documents/institutional/ep_{episode_id}/{timestamp}_{safename}
 *
 * This keeps files within OpenEMR's standard document root so
 * existing backup and retention policies apply automatically.
 */
final class EpisodeDocumentService
{
    // Max upload size: 20 MB
    private const MAX_BYTES = 20_971_520;

    // Allowed MIME types → extension
    private const ALLOWED = [
        'application/pdf'          => 'pdf',
        'image/jpeg'               => 'jpg',
        'image/png'                => 'png',
        'image/gif'                => 'gif',
        'image/tiff'               => 'tif',
        'text/plain'               => 'txt',
        'application/msword'       => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'       => 'xlsx',
    ];

    public function __construct(
        private readonly EpisodeDocumentRepository $repo
    ) {}

    /**
     * Process an uploaded file and create the document record.
     *
     * @param  array<string,mixed> $uploadedFile  $_FILES entry
     * @return array{ok:bool, id:int, error:string}
     */
    public function upload(
        array   $uploadedFile,
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $docType,
        string  $label,
        ?int    $userId,
        ?string $notes
    ): array {
        // ── Validate upload ──────────────────────────────────────────────────
        if (($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'id' => 0, 'error' => $this->uploadErrorMsg((int)$uploadedFile['error'])];
        }

        $size = (int)($uploadedFile['size'] ?? 0);
        if ($size === 0) {
            return ['ok' => false, 'id' => 0, 'error' => 'Uploaded file is empty.'];
        }
        if ($size > self::MAX_BYTES) {
            return ['ok' => false, 'id' => 0, 'error' => 'File exceeds 20 MB limit.'];
        }

        // Detect MIME from actual file content, not browser header
        $tmpPath  = (string)($uploadedFile['tmp_name'] ?? '');
        $mimeType = $this->detectMime($tmpPath);

        if (!isset(self::ALLOWED[$mimeType])) {
            return ['ok' => false, 'id' => 0, 'error' => "File type '{$mimeType}' is not allowed."];
        }

        // ── Build storage path ───────────────────────────────────────────────
        $storageDir  = $this->storageDir($episodeId);
        if (!is_dir($storageDir) && !mkdir($storageDir, 0750, true)) {
            return ['ok' => false, 'id' => 0, 'error' => 'Could not create storage directory.'];
        }

        $originalName = $this->sanitizeName((string)($uploadedFile['name'] ?? 'upload'));
        $ext          = self::ALLOWED[$mimeType];
        $storedName   = date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $destPath     = $storageDir . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            return ['ok' => false, 'id' => 0, 'error' => 'Failed to move uploaded file.'];
        }

        // ── Persist record ───────────────────────────────────────────────────
        $storagePath = $this->relPath($episodeId, $storedName);
        $id          = $this->repo->create(
            $episodeId, $pid, $facilityId,
            $docType, $label, $originalName,
            $mimeType, $size,
            $storagePath, $userId, $notes
        );

        if ($id === 0) {
            // Record failed — remove the orphaned file
            @unlink($destPath);
            return ['ok' => false, 'id' => 0, 'error' => 'Database error storing document record.'];
        }

        return ['ok' => true, 'id' => $id, 'error' => ''];
    }

    /**
     * Serve a document file to the browser (inline for PDF/images, attachment otherwise).
     * Caller must have already verified ownership and ACL before calling this.
     *
     * @throws \RuntimeException if file not found or deleted
     */
    public function serve(int $docId): never
    {
        $doc = $this->repo->findById($docId);
        if (!$doc || (int)$doc['is_deleted'] === 1) {
            http_response_code(404);
            exit('Document not found.');
        }

        $fullPath = $this->fullPath((string)$doc['storage_path']);
        if (!is_readable($fullPath)) {
            http_response_code(404);
            exit('File not found on disk.');
        }

        $mime = (string)$doc['mime_type'];
        $name = (string)$doc['original_name'];

        // Inline render for PDF and images — everything else forces download
        $inline = in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif'], true);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($fullPath));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . addslashes($name) . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');

        readfile($fullPath);
        exit;
    }

    /**
     * Delete the physical file for a document row.
     * Called after soft-delete to clean up disk.
     */
    public function deleteFile(array $doc): void
    {
        $path = $this->fullPath((string)$doc['storage_path']);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function storageDir(int $episodeId): string
    {
        $base = $this->siteDocRoot();
        return $base . DIRECTORY_SEPARATOR . 'institutional' . DIRECTORY_SEPARATOR . 'ep_' . $episodeId;
    }

    private function relPath(int $episodeId, string $storedName): string
    {
        return 'institutional/ep_' . $episodeId . '/' . $storedName;
    }

    private function fullPath(string $relPath): string
    {
        return $this->siteDocRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relPath);
    }

    private function siteDocRoot(): string
    {
        // OpenEMR stores documents in sites/{site}/documents
        // $GLOBALS['OE_SITE_DIR'] is set by OpenEMR bootstrap
        $base = ($GLOBALS['OE_SITE_DIR'] ?? (dirname(__DIR__, 6) . '/sites/default')) . '/documents';
        return rtrim($base, '/\\');
    }

    private function detectMime(string $path): string
    {
        if (!is_readable($path)) {
            return 'application/octet-stream';
        }
        if (function_exists('finfo_open')) {
            $fi   = finfo_open(FILEINFO_MIME_TYPE);
            $mime = (string)finfo_file($fi, $path);
            finfo_close($fi);
            return $mime;
        }
        if (function_exists('mime_content_type')) {
            return (string)mime_content_type($path);
        }
        return 'application/octet-stream';
    }

    private function sanitizeName(string $name): string
    {
        // Keep only safe characters — strip path traversal
        $base = basename($name);
        return preg_replace('/[^a-zA-Z0-9._\- ]/', '_', $base) ?: 'upload';
    }

    private function uploadErrorMsg(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds size limit.',
            UPLOAD_ERR_PARTIAL   => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_FILE   => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            default              => "Upload error (code {$code}).",
        };
    }
}

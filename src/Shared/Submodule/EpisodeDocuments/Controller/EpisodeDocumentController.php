<?php

/**
 * src/Shared/Submodule/EpisodeDocuments/Controller/EpisodeDocumentController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Repository\EpisodeDocumentRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Service\EpisodeDocumentService;

final class EpisodeDocumentController
{
    public function __construct(
        private readonly EpisodeDocumentRepository $repo,
        private readonly EpisodeDocumentService    $service
    ) {}

    /**
     * Main handler — routes GET/POST actions.
     * Returns view data array for the page template.
     *
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $action  = $_GET['action'] ?? '';
        $message = '';
        $error   = '';

        // ── Serve file ───────────────────────────────────────────────────────
        if ($action === 'serve' && isset($_GET['doc_id'])) {
            $docId = (int)$_GET['doc_id'];
            $doc   = $this->repo->findById($docId);
            // Verify document belongs to this episode (ownership check)
            if (!$doc || (int)$doc['episode_id'] !== $episodeId) {
                http_response_code(403);
                exit('Access denied.');
            }
            $this->service->serve($docId);
            // serve() exits
        }

        // ── POST: upload or delete ───────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $postAction = (string)($_POST['post_action'] ?? 'upload');

            if ($postAction === 'delete') {
                $docId = (int)($_POST['doc_id'] ?? 0);
                $doc   = $this->repo->findById($docId);
                if ($doc && (int)$doc['episode_id'] === $episodeId) {
                    $this->service->deleteFile($doc);
                    $this->repo->softDelete($docId, $userId);
                    $message = 'Document removed.';
                } else {
                    $error = 'Document not found or access denied.';
                }

            } else {
                // Upload
                $docType = $_POST['doc_type'] ?? 'GENERAL';
                if (!array_key_exists($docType, EpisodeDocumentRepository::TYPES)) {
                    $docType = 'GENERAL';
                }

                $label = trim((string)($_POST['label'] ?? ''));
                if ($label === '') {
                    // Fall back to original filename
                    $label = (string)($_FILES['upload_file']['name'] ?? 'Document');
                }

                $notes  = trim((string)($_POST['notes'] ?? '')) ?: null;
                $file   = $_FILES['upload_file'] ?? [];

                $result = $this->service->upload(
                    $file, $episodeId, $pid, $facilityId,
                    $docType, $label, $userId, $notes
                );

                if ($result['ok']) {
                    $message = 'Document uploaded successfully.';
                } else {
                    $error = $result['error'];
                }
            }

            // PRG redirect
            $qs = http_build_query([
                'facility_id' => $facilityId,
                'episode_id'  => $episodeId,
                'msg'         => $message,
                'err'         => $error,
            ]);
            header("Location: episode_documents.php?{$qs}");
            exit;
        }

        // Flash from redirect
        if (!$message && isset($_GET['msg'])) {
            $message = (string)$_GET['msg'];
        }
        if (!$error && isset($_GET['err'])) {
            $error = (string)$_GET['err'];
        }

        $documents = $this->repo->listForEpisode($episodeId);
        $summary   = $this->repo->typeSummary($episodeId);

        return [
            'documents' => $documents,
            'summary'   => $summary,
            'types'     => EpisodeDocumentRepository::TYPES,
            'message'   => $message,
            'error'     => $error,
        ];
    }
}




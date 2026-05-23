<?php

/**
 * src/Submodule/Assignment/Controller/AssignmentController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Assignment\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Assignment\Repository\AssignmentRepository;
use OpenEMR\Modules\Institutional\Core\Service\AuditService;

final class AssignmentController
{
    public function __construct(
        private readonly AssignmentRepository $repo,
        private readonly ?AuditService        $audit = null
    ) {}

    /**
     * Handles the assignment management page (GET renders, POST assigns).
     * Also handles AJAX JSON POST when ?json=1.
     *
     * @return array<string,mixed>
     */
    public function handle(int $facilityId, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $episodeId = (int)($_POST['episode_id'] ?? 0);
            $pid       = (int)($_POST['pid']        ?? 0);

            if ($episodeId > 0) {
                $fields = [];
                if (isset($_POST['nurse_user_id'])) {
                    $fields['nurse'] = (int)$_POST['nurse_user_id'] ?: null;
                }
                if (isset($_POST['provider_user_id'])) {
                    $fields['provider'] = (int)$_POST['provider_user_id'] ?: null;
                }

                if (!empty($fields)) {
                    $this->repo->assign($episodeId, $fields);

                    // Audit trail
                    if ($this->audit !== null && $pid > 0) {
                        $parts = [];
                        if (array_key_exists('nurse', $fields)) {
                            $parts[] = 'nurse=' . ($fields['nurse'] ?? 'none');
                        }
                        if (array_key_exists('provider', $fields)) {
                            $parts[] = 'provider=' . ($fields['provider'] ?? 'none');
                        }
                        $this->audit->record(
                            $episodeId, $pid, $facilityId,
                            AuditService::EVT_STATUS_CHANGE,
                            $userId,
                            'Assignment: ' . implode(', ', $parts)
                        );
                    }
                    $message = xlt('Assignment updated.');
                }
            }

            // JSON response for inline board widgets
            if (!empty($_POST['json'])) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'message' => $message]);
                exit;
            }

            // PRG
            header("Location: assignments.php?facility_id=" . urlencode((string)$facilityId) . "&saved=1");
            exit;
        }

        $staff = $this->repo->availableStaff();

        return [
            'rows'      => $this->repo->listWithAssignments($facilityId),
            'nurses'    => $staff['nurses'],
            'providers' => $staff['providers'],
            'csrf'      => $csrf,
            'message'   => $message,
            'saved'     => !empty($_GET['saved']),
        ];
    }

    /**
     * Lightweight JSON endpoint — returns current assignment for one episode.
     * Called by the ED Board when it needs to render inline dropdowns.
     */
    public function handleGet(int $episodeId): never
    {
        header('Content-Type: application/json');
        echo json_encode($this->repo->getForEpisode($episodeId));
        exit;
    }
}






<?php

/**
 * src/Submodule/Tasks/Controller/TasksController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Tasks\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

final class TasksController
{
    public function __construct(private readonly TaskRepository $repo) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId, int $episodeId = 0): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'complete') {
                $taskId = (int)($_POST['task_id'] ?? 0);
                if ($taskId > 0) {
                    $this->repo->complete($taskId, $userId);
                }
            }
            $epParam = $episodeId > 0 ? "&episode_id=" . $episodeId : "";
            header("Location: tasks.php?facility_id=" . urlencode((string)$facilityId) . $epParam);
            exit;
        }

        return [
            'rows' => $this->repo->listOpenByFacility($facilityId, $episodeId),
            'csrf' => CsrfUtils::collectCsrfToken(),
        ];
    }
}









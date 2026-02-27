<?php
namespace OpenEMR\Modules\Institutional\Submodule\Tasks\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

final class TasksController
{
    public function __construct(private readonly TaskRepository $repo) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
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
            header("Location: tasks.php?facility_id=" . urlencode((string)$facilityId));
            exit;
        }

        return [
            'rows' => $this->repo->listOpenByFacility($facilityId),
            'csrf' => CsrfUtils::collectCsrfToken(),
        ];
    }
}

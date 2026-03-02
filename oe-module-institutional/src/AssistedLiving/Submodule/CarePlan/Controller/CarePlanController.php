<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Service\CarePlanService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Repository\CarePlanRepository;

final class CarePlanController
{
    private readonly CarePlanService $service;

    public function __construct()
    {
        $this->service = new CarePlanService(new CarePlanRepository());
    }

    public function handle(int $episodeId, int $pid, int $userId): array
    {
        $flash = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action      = (string)($_POST['action'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $proposed    = trim((string)($_POST['proposed_date'] ?? ''));

            if ($action === 'add_goal' && $description !== '') {
                $this->service->addGoal($episodeId, $description, $proposed, $userId);
                $flash = 'Goal added.';
            } elseif ($action === 'add_activity' && $description !== '') {
                $this->service->addActivity($episodeId, $description, $proposed, $userId);
                $flash = 'Intervention added.';
            } elseif ($action === 'update_status') {
                $entryId = (int)($_POST['entry_id'] ?? 0);
                $status  = trim((string)($_POST['status'] ?? ''));
                if ($entryId > 0 && $status !== '') {
                    $this->service->updateStatus($entryId, $status);
                    $flash = 'Status updated.';
                }
            }
        }

        $data = $this->service->pageData($episodeId, $pid);
        $data['flash']     = $flash;
        $data['episodeId'] = $episodeId;
        $data['pid']       = $pid;
        return $data;
    }
}

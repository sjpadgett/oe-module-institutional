<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Service\ResidentIntakeService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Repository\ResidentIntakeRepository;

final class ResidentIntakeController
{
    private readonly ResidentIntakeService $service;

    public function __construct()
    {
        $this->service = new ResidentIntakeService(new ResidentIntakeRepository());
    }

    public function handle(int $facilityId, int $userId): array
    {
        $result = ['success' => false, 'episode_id' => 0, 'error' => '', 'submitted' => false];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $result = $this->service->admit($facilityId, $userId, $_POST);
            $result['submitted'] = true;
        }

        return [
            'result'      => $result,
            'care_levels' => $this->service->careLevels(),
            'fall_levels' => $this->service->fallLevels(),
        ];
    }
}

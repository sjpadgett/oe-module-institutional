<?php

/**
 * src/AssistedLiving/Submodule/ResidentIntake/Controller/ResidentIntakeController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

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
            'locations'   => $this->service->listLocations($facilityId),
        ];
    }
}







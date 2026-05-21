<?php

/**
 * src/HomeBased/Submodule/HbcIntake/Controller/HbcIntakeController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Repository\HbcIntakeRepository;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Service\HbcIntakeService;

final class HbcIntakeController
{
    private readonly HbcIntakeService $service;

    public function __construct()
    {
        $this->service = new HbcIntakeService(new HbcIntakeRepository());
    }

    public function handle(int $facilityId, int $userId): array
    {
        $result = ['success' => false, 'episode_id' => 0, 'error' => '', 'submitted' => false];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $result = $this->service->accept($facilityId, $userId, $_POST);
            $result['submitted'] = true;
        }

        return [
            'result'      => $result,
            'clinicians'  => $this->service->listClinicians(),
            'urgencies'   => $this->service->urgencyOptions(),
            'visit_types' => $this->service->visitTypeOptions(),
        ];
    }
}




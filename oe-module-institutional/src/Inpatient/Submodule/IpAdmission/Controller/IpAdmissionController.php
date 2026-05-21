<?php

/**
 * src/Inpatient/Submodule/IpAdmission/Controller/IpAdmissionController.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Service\IpAdmissionService;
/**
 * IpAdmissionController
 *
 * GET  ip/admission.php                     → render admission form
 * GET  ip/admission.php?search=<q>          → patient search JSON endpoint
 * POST ip/admission.php                     → submit admission
 *
 * On success: PRG redirect to ip/profile.php for the new episode.
 */
final class IpAdmissionController
{
    public function __construct(
        private readonly IpAdmissionService $service
    ) {}

    /**
     * Main entry point.
     * @return array<string,mixed>
     */
    public function handle(int $facilityId, int $userId): array
    {
        $csrf   = CsrfUtils::collectCsrfToken();
        $result = ['submitted' => false, 'success' => false, 'episode_id' => 0, 'error' => ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $admitted = $this->service->admit($facilityId, $userId, $_POST);
            $result   = array_merge($result, ['submitted' => true], $admitted);
        }

        return [
            'result'      => $result,
            'csrf'        => $csrf,
            'services'    => HospitalService::all(),
            'admit_types' => AdmissionType::all(),
            'physicians'  => $this->service->listAttendingPhysicians(),
            'locations'   => $this->service->listLocations($facilityId),
        ];
    }
}







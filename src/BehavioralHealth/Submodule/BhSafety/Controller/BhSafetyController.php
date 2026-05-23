<?php

/**
 * src/BehavioralHealth/Submodule/BhSafety/Controller/BhSafetyController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Controller;

use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Repository\BhSafetyRepository;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Service\BhSafetyService;

final class BhSafetyController
{
    public function __construct(
        private readonly BhSafetyRepository $repo,
        private readonly BhSafetyService    $service
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
    {
        return ['rows' => $this->repo->listRecentByFacility($facilityId)];
    }
}




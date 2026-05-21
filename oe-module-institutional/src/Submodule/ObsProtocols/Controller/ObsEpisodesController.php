<?php

/**
 * src/Submodule/ObsProtocols/Controller/ObsEpisodesController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Controller;

use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

final class ObsEpisodesController
{
    public function __construct(
        private readonly ObsPlanRepository $plans,
        private readonly ?TaskRepository   $tasks
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId): array
    {
        return ['rows' => $this->plans->listActive($facilityId)];
    }
}






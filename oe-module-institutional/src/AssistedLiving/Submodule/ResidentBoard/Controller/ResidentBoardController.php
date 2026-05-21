<?php

/**
 * src/AssistedLiving/Submodule/ResidentBoard/Controller/ResidentBoardController.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Controller;

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Service\ResidentBoardService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Repository\ResidentBoardRepository;

/**
 * ResidentBoardController — thin request handler for public/al/board.php
 */
final class ResidentBoardController
{
    private readonly ResidentBoardService $service;

    public function __construct()
    {
        $this->service = new ResidentBoardService(new ResidentBoardRepository());
    }

    /**
     * @return array{residents: array, units: array, counts: array, facilityId: int}
     */
    public function handle(int $facilityId): array
    {
        $data = $this->service->boardData($facilityId);
        $data['facilityId'] = $facilityId;
        return $data;
    }
}




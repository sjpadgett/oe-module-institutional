<?php

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

<?php
namespace OpenEMR\Modules\Institutional\Submodule\BhSafety\Controller;

use OpenEMR\Modules\Institutional\Submodule\BhSafety\Repository\BhSafetyRepository;
use OpenEMR\Modules\Institutional\Submodule\BhSafety\Service\BhSafetyService;

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



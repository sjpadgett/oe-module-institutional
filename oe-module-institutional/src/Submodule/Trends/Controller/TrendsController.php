<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Trends\Controller;

use OpenEMR\Modules\Institutional\Submodule\Trends\Service\TrendsService;

final class TrendsController
{
    public function __construct(
        private readonly TrendsService $service
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, string $granularity, int $periods): array
    {
        return $this->service->buildViewModel($facilityId, $granularity, $periods);
    }
}

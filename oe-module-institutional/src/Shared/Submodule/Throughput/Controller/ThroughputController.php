<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Throughput\Controller;

use OpenEMR\Modules\Institutional\Shared\Submodule\Throughput\Service\ThroughputService;

final class ThroughputController
{
    public function __construct(
        private readonly ThroughputService $service
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, string $start, string $end, array $episodes): array
    {
        return $this->service->compute($facilityId, $start, $end, $episodes);
    }
}

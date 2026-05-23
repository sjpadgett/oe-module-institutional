<?php

/**
 * src/Shared/Submodule/Throughput/Controller/ThroughputController.php
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




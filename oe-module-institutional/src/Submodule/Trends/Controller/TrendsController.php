<?php

/**
 * src/Submodule/Trends/Controller/TrendsController.php
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




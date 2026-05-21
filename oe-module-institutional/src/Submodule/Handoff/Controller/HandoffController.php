<?php

/**
 * src/Submodule/Handoff/Controller/HandoffController.php
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

namespace OpenEMR\Modules\Institutional\Submodule\Handoff\Controller;

use OpenEMR\Modules\Institutional\Submodule\Handoff\Repository\HandoffRepository;
use OpenEMR\Modules\Institutional\Submodule\Handoff\Service\HandoffService;

final class HandoffController
{
    public function __construct(
        private readonly HandoffRepository $repo,
        private readonly HandoffService    $service
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId): array
    {
        $rows    = $this->repo->fetchHandoff($facilityId);
        $summary = $this->service->computeSummary($rows);
        $printed = (new \DateTimeImmutable())->format('F j, Y  g:i A');

        return [
            'rows'    => $rows,
            'summary' => $summary,
            'printed' => $printed,
            'service' => $this->service,   // passed through for use in template helpers
        ];
    }
}




<?php

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

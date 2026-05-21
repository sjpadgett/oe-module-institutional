<?php

/**
 * src/AssistedLiving/Submodule/IncidentReport/Service/IncidentService.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Service;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\IncidentType;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Repository\IncidentRepository;

final class IncidentService
{
    public function __construct(private readonly IncidentRepository $repo) {}

    public function listForFacility(int $facilityId): array
    {
        $rows = $this->repo->listByFacility($facilityId);
        foreach ($rows as &$r) {
            $r['type_label']         = IncidentType::label($r['incident_type']);
            $r['mandatory_required'] = IncidentType::requiresMandatoryReport($r['incident_type']);
        }
        unset($r);
        return $rows;
    }

    public function create(int $episodeId, int $facilityId, int $userId, array $data): int
    {
        // Auto-set mandatory_report_sent based on type
        if (IncidentType::requiresMandatoryReport($data['incident_type'] ?? '')) {
            $data['mandatory_report_sent'] = 0; // not yet sent, but flag it
        }
        return $this->repo->create($episodeId, $facilityId, $userId, $data);
    }

    public function markReported(int $incidentId): void
    {
        $this->repo->markReported($incidentId);
    }

    public function incidentTypes(): array { return IncidentType::all(); }
    public function typeLabel(string $t): string { return IncidentType::label($t); }
}




<?php
namespace OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Repository\EpisodeIntakeRepository;

final class IntakeService
{
    public function __construct(private readonly EpisodeIntakeRepository $repo) {}

    public function createEpisode(
        int $pid, int $facilityId, string $arrivalMode,
        ?int $esi, ?string $chiefComplaint, ?string $triageNote, ?int $userId
    ): int {
        return $this->repo->create($pid, $facilityId, $arrivalMode, $esi, $chiefComplaint, $triageNote, $userId);
    }
}

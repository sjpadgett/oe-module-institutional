<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Service;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Repository\ResidentIntakeRepository;

final class ResidentIntakeService
{
    public function __construct(private readonly ResidentIntakeRepository $repo) {}

    /**
     * Validate and admit a new resident.
     * @param array $data Form POST data
     * @return array{success: bool, episode_id: int, error: string}
     */
    public function admit(int $facilityId, int $userId, array $data): array
    {
        $pid = (int)($data['pid'] ?? 0);
        if ($pid <= 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Patient not selected.'];
        }

        if ($this->repo->hasActiveEpisode($pid, $facilityId)) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Patient already has an active AL episode at this facility.'];
        }

        $fallScore = max(0, (int)($data['fall_risk_score'] ?? 0));
        $fallLevel = FallRiskLevel::fromMorseScore($fallScore);

        // Allow manual care level override; default to ADL-derived if not set
        $careLevel = in_array($data['care_level'] ?? '', CareLevel::all(), true)
            ? $data['care_level']
            : CareLevel::TIER_1;

        $episodeId = $this->repo->admitResident(
            pid:            $pid,
            facilityId:     $facilityId,
            userId:         $userId,
            room:           trim((string)($data['room'] ?? '')),
            unit:           trim((string)($data['unit'] ?? '')),
            careLevel:      $careLevel,
            fallRiskLevel:  $fallLevel,
            fallRiskScore:  $fallScore,
            admitReason:    trim((string)($data['admit_reason'] ?? '')),
            admitDatetime:  trim((string)($data['admit_datetime'] ?? date('Y-m-d H:i:s'))),
        );

        if ($episodeId === 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Database error during admission.'];
        }

        return ['success' => true, 'episode_id' => $episodeId, 'error' => ''];
    }

    public function careLevels(): array   { return CareLevel::all(); }
    public function fallLevels(): array   { return FallRiskLevel::all(); }
}

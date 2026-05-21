<?php

/**
 * src/AssistedLiving/Submodule/ResidentIntake/Service/ResidentIntakeService.php
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

        // Verify the encounter number was created and linked.
        // If encounter_id is 0 the Care Plan tab will be silently empty.
        // Surface this as a hard failure so it is caught during testing
        // rather than discovered when a clinician tries to add a care goal.
        $encounterNum = $this->repo->getEncounterId($episodeId);
        if ($encounterNum === 0) {
            error_log(
                '[OEI] AL admission succeeded but encounter number is 0 (oei_al_episode.encounter_id)'
                . " — episode={$episodeId} pid={$pid} facility={$facilityId}"
                . ' — Care Plan will not function. Check form_encounter table permissions.'
            );
            return [
                'success'     => false,
                'episode_id'  => $episodeId,
                'error'       => 'Resident admitted but the OpenEMR encounter could not be created.'
                                . ' Care Plan will not function. Contact your administrator.'
                                . ' (episode_id=' . $episodeId . ' was created — do not re-admit).',
            ];
        }

        return ['success' => true, 'episode_id' => $episodeId, 'error' => ''];
    }

    public function careLevels(): array   { return CareLevel::all(); }
    public function fallLevels(): array   { return FallRiskLevel::all(); }

    /**
     * Active locations for the admission form room/unit selector.
     * @return array<int,array{id:int,code:string,name:string,type:string,unit:string}>
     */
    public function listLocations(int $facilityId): array
    {
        return $this->repo->listLocations($facilityId);
    }
}









<?php

/**
 * src/Inpatient/Submodule/IpAdmission/Service/IpAdmissionService.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Service;

use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Repository\IpAdmissionRepository;

/**
 * IpAdmissionService
 *
 * Validates form input and delegates the three-step database write
 * to IpAdmissionRepository. Returns a structured result array so
 * the controller and view can handle success/failure uniformly.
 */
final class IpAdmissionService
{
    public function __construct(
        private readonly IpAdmissionRepository $repo
    ) {}

    /**
     * @param  array<string,mixed> $data  Validated POST data
     * @return array{success:bool, episode_id:int, error:string}
     */
    public function admit(int $facilityId, int $userId, array $data): array
    {
        $pid = (int) ($data['pid'] ?? 0);
        if ($pid <= 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => xlt('Patient not selected.')];
        }

        if ($this->repo->hasActiveEpisode($pid, $facilityId)) {
            return [
                'success'    => false,
                'episode_id' => 0,
                'error'      => xlt('This patient already has an active inpatient episode at this facility.'),
            ];
        }

        $service       = strtoupper(trim((string) ($data['service'] ?? HospitalService::MED_SURG)));
        $admissionType = strtoupper(trim((string) ($data['admission_type'] ?? AdmissionType::ELECTIVE)));

        if (!in_array($service, HospitalService::all(), true)) {
            $service = HospitalService::MED_SURG;
        }
        if (!in_array($admissionType, AdmissionType::all(), true)) {
            $admissionType = AdmissionType::ELECTIVE;
        }

        $attendingUserId = isset($data['attending_user_id']) && (int) $data['attending_user_id'] > 0
            ? (int) $data['attending_user_id']
            : null;

        $expectedLos = isset($data['expected_los_days']) && (int) $data['expected_los_days'] > 0
            ? (int) $data['expected_los_days']
            : null;

        $admitDatetime = trim((string) ($data['admit_datetime'] ?? ''));
        if ($admitDatetime === '' || !strtotime($admitDatetime)) {
            $admitDatetime = date('Y-m-d H:i:s');
        } else {
            $admitDatetime = date('Y-m-d H:i:s', strtotime($admitDatetime));
        }

        $episodeId = $this->repo->admitPatient(
            pid:                $pid,
            facilityId:         $facilityId,
            userId:             $userId,
            bed:                trim((string) ($data['bed'] ?? '')),
            unit:               trim((string) ($data['unit'] ?? '')),
            service:            $service,
            admissionType:      $admissionType,
            attendingUserId:    $attendingUserId,
            admittingDiagnosis: trim((string) ($data['admitting_diagnosis'] ?? '')) ?: null,
            admittingIcd10:     trim((string) ($data['admitting_icd10'] ?? '')) ?: null,
            expectedLosDays:    $expectedLos,
            admitDatetime:      $admitDatetime,
            chiefComplaint:     trim((string) ($data['chief_complaint'] ?? '')) ?: null,
        );

        if ($episodeId === 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => xlt('Database error during admission. Please try again.')];
        }

        // Warn (but don't fail) if the encounter number didn't get created
        $encounterNum = $this->repo->getEncounterId($episodeId);
        if ($encounterNum === 0) {
            error_log(
                '[OEI] IP admission succeeded but encounter number is 0 (oei_ip_episode.encounter_id)'
                . " — episode={$episodeId} pid={$pid} facility={$facilityId}"
                . ' — Care Plan and Clinical Notes will not function.'
                . ' Check form_encounter table permissions.'
            );
        }

        return ['success' => true, 'episode_id' => $episodeId, 'error' => ''];
    }

    /** @return array<int,array{id:int,name:string}> */
    public function listAttendingPhysicians(): array
    {
        return $this->repo->listAttendingPhysicians();
    }

    /**
     * Active locations from oei_location for the admission form bed/unit selector.
     * @return array<int,array{id:int,code:string,name:string,type:string,unit:string}>
     */
    public function listLocations(int $facilityId): array
    {
        return $this->repo->listLocations($facilityId);
    }
}









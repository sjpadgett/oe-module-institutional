<?php

/**
 * src/HomeBased/Submodule/HbcIntake/Service/HbcIntakeService.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Service;

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Repository\HbcIntakeRepository;

final class HbcIntakeService
{
    public function __construct(private readonly HbcIntakeRepository $repo) {}

    /**
     * Validate and create a new HBC episode.
     * @return array{success:bool, episode_id:int, error:string}
     */
    public function accept(int $facilityId, int $userId, array $data): array
    {
        $pid = (int)($data['pid'] ?? 0);
        if ($pid <= 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Patient not selected.'];
        }

        if (empty(trim((string)($data['service_address_line1'] ?? '')))) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Service address is required.'];
        }

        if ($this->repo->hasActiveEpisode($pid, $facilityId)) {
            return ['success' => false, 'episode_id' => 0,
                    'error' => 'Patient already has an active Home-Based Care episode at this facility.'];
        }

        $urgency = in_array($data['urgency'] ?? '', ['ROUTINE','URGENT','EMERGENT'], true)
            ? $data['urgency'] : 'ROUTINE';

        $referralDatetime = trim((string)($data['referral_datetime'] ?? ''));
        if ($referralDatetime === '') {
            $referralDatetime = date('Y-m-d H:i:s');
        }

        $episodeId = $this->repo->createEpisode(
            pid:                       $pid,
            facilityId:                $facilityId,
            userId:                    $userId,
            referralSource:            trim((string)($data['referral_source']            ?? '')),
            referralReason:            trim((string)($data['referral_reason']            ?? '')),
            urgency:                   $urgency,
            addressLine1:              trim((string)($data['service_address_line1']      ?? '')),
            addressLine2:              trim((string)($data['service_address_line2']      ?? '')),
            city:                      trim((string)($data['service_city']               ?? '')),
            stateProvince:             trim((string)($data['service_state_province']     ?? '')),
            postalCode:                trim((string)($data['service_postal_code']        ?? '')),
            country:                   trim((string)($data['service_country']            ?? '')),
            accessNotes:               trim((string)($data['access_notes']               ?? '')),
            caregiverName:             trim((string)($data['caregiver_name']             ?? '')),
            caregiverPhone:            trim((string)($data['caregiver_phone']            ?? '')),
            caregiverRelationship:     trim((string)($data['caregiver_relationship']     ?? '')),
            primaryClinicianUserId:    (int)($data['primary_clinician_user_id']          ?? 0),
            primaryDiagnosis:          trim((string)($data['primary_diagnosis']          ?? '')),
            primaryIcd10:              trim((string)($data['primary_icd10']              ?? '')),
            payerName:                 trim((string)($data['payer_name']                 ?? '')),
            authorizationNotes:        trim((string)($data['authorization_notes']        ?? '')),
            referralDatetime:          $referralDatetime,
            certPeriodStart:           trim((string)($data['cert_period_start']          ?? '')) ?: null,
            certPeriodEnd:             trim((string)($data['cert_period_end']            ?? '')) ?: null,
            authorizedVisitsPerWeek:   trim((string)($data['authorized_visits_per_week'] ?? '')) !== '' ? (int)$data['authorized_visits_per_week'] : null,
        );

        if ($episodeId === 0) {
            return ['success' => false, 'episode_id' => 0, 'error' => 'Database error during intake.'];
        }

        $encounterNum = $this->repo->getEncounterId($episodeId);
        if ($encounterNum === 0) {
            error_log("[OEI] HBC intake: episode={$episodeId} created but encounter_id=0. Care Plan will not function.");
            return [
                'success'    => false,
                'episode_id' => $episodeId,
                'error'      => 'Patient accepted but the OpenEMR encounter could not be created.'
                              . ' Care Plan will not function. Contact your administrator.'
                              . ' (episode_id=' . $episodeId . ' — do not re-admit).',
            ];
        }

        return ['success' => true, 'episode_id' => $episodeId, 'error' => ''];
    }

    public function listClinicians(): array { return $this->repo->listClinicians(); }
    public function urgencyOptions(): array { return ['ROUTINE','URGENT','EMERGENT']; }
    public function visitTypeOptions(): array { return HbcVisitType::all(); }
}







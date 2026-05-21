<?php

/**
 * src/HomeBased/Submodule/HbcIntake/Repository/HbcIntakeRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Repository;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;

/**
 * HbcIntakeRepository
 *
 * Three-step encounter creation (identical pattern to ResidentIntakeRepository):
 *   Step 1 — QueryUtils::generateId() from sequences table
 *   Step 2 — INSERT form_encounter (pos_code=12 = Home, class_code='HH')
 *   Step 3 — FormsRegistrar::register() with formdir='newpatient'
 *   Step 4 — INSERT oei_episode  (type='HBC')
 *   Step 5 — INSERT oei_hbc_episode overlay
 */
final class HbcIntakeRepository
{
    /**
     * Create a new HBC episode. Returns episode_id or 0 on failure.
     */
    public function createEpisode(
        int    $pid,
        int    $facilityId,
        int    $userId,
        string $referralSource,
        string $referralReason,
        string $urgency,
        string $addressLine1,
        string $addressLine2,
        string $city,
        string $stateProvince,
        string $postalCode,
        string $country,
        string $accessNotes,
        string $caregiverName,
        string $caregiverPhone,
        string $caregiverRelationship,
        int    $primaryClinicianUserId,
        string $primaryDiagnosis,
        string $primaryIcd10,
        string $payerName,
        string $authorizationNotes,
        string $referralDatetime,
        ?string $certPeriodStart = null,
        ?string $certPeriodEnd = null,
        ?int $authorizedVisitsPerWeek = null
    ): int {
        if (!function_exists('sqlInsert')) {
            return 0;
        }

        // Step 1 — encounter NUMBER
        $encounterNum = QueryUtils::generateId();

        // Step 2 — form_encounter
        // pos_code 12 = Home  |  class_code 'HH' = Home Health
        $formEncounterId = sqlInsert(
            "INSERT INTO form_encounter
                (date, onset_date, reason, facility, pid, provider_id,
                 facility_id, billing_facility, encounter, pos_code, class_code)
             VALUES (?,?,?,'Home-Based Care',?,?,?,?,?,'12','HH')",
            [
                $referralDatetime,
                $referralDatetime,
                $referralReason ?: 'HBC Referral',
                $pid,
                $userId,
                $facilityId,
                $facilityId,
                $encounterNum,
            ]
        );
        if (!$formEncounterId) {
            error_log("[OEI] HBC form_encounter INSERT failed — pid={$pid}");
            return 0;
        }

        // Step 3 — register newpatient form
        (new FormsRegistrar())->register(
            $pid,
            $encounterNum,
            $formEncounterId,
            'newpatient',
            'New Patient Encounter',
            $userId
        );

        // Step 4 — oei_episode (type = 'HBC')
        $episodeId = sqlInsert(
            "INSERT INTO oei_episode
                (pid, facility_id, status, type, start_datetime,
                 created_by_user_id, created_datetime)
             VALUES (?,?,'ACTIVE','HBC',?,?,NOW())",
            [$pid, $facilityId, $referralDatetime, $userId]
        );
        if (!$episodeId) {
            return 0;
        }

        // Step 5 — oei_hbc_episode overlay
        sqlInsert(
            "INSERT INTO oei_hbc_episode
                (episode_id, pid, facility_id, encounter_id,
                 referral_source, referral_reason, referral_status, urgency,
                 referral_datetime,
                 service_address_line1, service_address_line2,
                 service_city, service_state_province, service_postal_code, service_country,
                 access_notes,
                 caregiver_name, caregiver_phone, caregiver_relationship,
                 primary_clinician_user_id,
                 primary_diagnosis, primary_icd10,
                 payer_name, authorization_notes,
                 cert_period_start, cert_period_end, authorized_visits_per_week,
                 created_datetime)
             VALUES (?,?,?,?,?,?,'NEW',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $episodeId, $pid, $facilityId, $encounterNum,
                $referralSource, $referralReason,
                $urgency, $referralDatetime,
                $addressLine1, $addressLine2,
                $city, $stateProvince, $postalCode, $country,
                $accessNotes,
                $caregiverName, $caregiverPhone, $caregiverRelationship,
                $primaryClinicianUserId > 0 ? $primaryClinicianUserId : null,
                $primaryDiagnosis, $primaryIcd10,
                $payerName, $authorizationNotes,
                $certPeriodStart ?: null,
                $certPeriodEnd ?: null,
                $authorizedVisitsPerWeek,
            ]
        );

        return (int)$episodeId;
    }

    /** Verify encounter was created (same guard as AL). */
    public function getEncounterId(int $episodeId): int
    {
        if (!function_exists('sqlQuery')) { return 0; }
        $row = sqlQuery(
            'SELECT encounter_id FROM oei_hbc_episode WHERE episode_id = ? LIMIT 1',
            [$episodeId]
        );
        return (int)($row['encounter_id'] ?? 0);
    }

    /** True if patient already has an active HBC episode at this facility. */
    public function hasActiveEpisode(int $pid, int $facilityId): bool
    {
        if (!function_exists('sqlQuery')) { return false; }
        $r = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode
             WHERE pid=? AND facility_id=? AND status='ACTIVE' AND type='HBC'",
            [$pid, $facilityId]
        );
        return (int)($r['c'] ?? 0) > 0;
    }

    /**
     * Active clinicians/providers for the intake clinician selector.
     * authorized=1 = providers.
     * @return array<int,array{id:int,name:string}>
     */
    public function listClinicians(): array
    {
        if (!function_exists('sqlStatement')) { return []; }
        $res = sqlStatement(
            "SELECT id, CONCAT(fname,' ',lname) AS name
             FROM users
             WHERE active=1 AND authorized=1
             ORDER BY lname ASC, fname ASC"
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = ['id' => (int)$r['id'], 'name' => trim((string)$r['name'])];
        }
        return $rows;
    }
}







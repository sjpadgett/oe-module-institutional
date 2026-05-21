<?php

/**
 * src/Inpatient/Submodule/IpAdmission/Repository/IpAdmissionRepository.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Repository;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;

/**
 * IpAdmissionRepository
 *
 * Correct OpenEMR encounter creation sequence (mirrors newpatient/save.php):
 *
 *   Step 1 — Generate encounter NUMBER from the sequences table.
 *             This is the system-wide unique identifier that ALL downstream
 *             tables reference: form_care_plan.encounter, form_clinical_notes.encounter,
 *             forms.encounter. It is NOT form_encounter.id (the row PK).
 *
 *   Step 2 — INSERT into form_encounter with encounter = $encounterNum.
 *             Returns the row auto-increment id ($formEncounterId).
 *             This id is used only as forms.form_id for the newpatient registry entry.
 *             class_code = 'IMP'  (Inpatient Encounter, HL7 ActEncounterCode)
 *             pos_code   = 21     (Inpatient Hospital, CMS Place of Service)
 *
 *   Step 3 — Register the encounter in the forms table via addForm() (FormsRegistrar).
 *             formdir = 'newpatient'. Without this the encounter is invisible
 *             in the OpenEMR chart timeline, CCDA export, and FHIR Encounter resource.
 *
 *   Step 4 — INSERT oei_episode (type = 'IP').
 *
 *   Step 5 — INSERT oei_ip_episode, storing $encounterNum as encounter_id.
 */
final class IpAdmissionRepository
{
    /**
     * Admit a patient to an inpatient bed and return the new episode_id.
     * Returns 0 on failure.
     */
    public function admitPatient(
        int     $pid,
        int     $facilityId,
        int     $userId,
        string  $bed,
        string  $unit,
        string  $service,
        string  $admissionType,
        ?int    $attendingUserId,
        ?string $admittingDiagnosis,
        ?string $admittingIcd10,
        ?int    $expectedLosDays,
        string  $admitDatetime,
        ?string $chiefComplaint
    ): int {
        if (!function_exists('sqlInsert')) {
            return 0;
        }

        // Step 1 — encounter NUMBER from sequences table
        $encounterNum = QueryUtils::generateId();

        // Step 2 — form_encounter header
        $formEncounterId = sqlInsert(
            "INSERT INTO form_encounter
                (date, onset_date, reason, facility, pid, provider_id,
                 facility_id, billing_facility, encounter, pos_code, class_code)
             VALUES (?,?,?,'Inpatient',?,?,?,?,?,'21','IMP')",
            [
                $admitDatetime,
                $admitDatetime,
                $admittingDiagnosis ?: 'Inpatient Admission',
                $pid,
                $attendingUserId ?? $userId,
                $facilityId,
                $facilityId,
                $encounterNum,
            ]
        );

        if (!$formEncounterId) {
            error_log("[OEI] IP form_encounter INSERT failed — pid={$pid}");
            return 0;
        }

        // Step 3 — register newpatient form (makes encounter visible in OE chart)
        (new FormsRegistrar())->register(
            $pid,
            $encounterNum,      // encounter NUMBER — not form_encounter.id
            $formEncounterId,   // form_encounter row id → forms.form_id
            'newpatient',
            'New Patient Encounter',
            $userId
        );

        // Step 4 — oei_episode
        $episodeId = sqlInsert(
            "INSERT INTO oei_episode
                (pid, facility_id, status, type, start_datetime, chief_complaint,
                 created_by_user_id, created_datetime)
             VALUES (?,?,'ACTIVE','IP',?,?,?,NOW())",
            [$pid, $facilityId, $admitDatetime, $chiefComplaint, $userId]
        );

        if (!$episodeId) {
            return 0;
        }

        // Step 5 — oei_ip_episode (store encounter NUMBER, not row id)
        sqlInsert(
            "INSERT INTO oei_ip_episode
                (episode_id, pid, facility_id, encounter_id,
                 bed, unit, service, admission_type,
                 attending_user_id, admitting_diagnosis, admitting_icd10,
                 expected_los_days, created_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $episodeId,
                $pid,
                $facilityId,
                $encounterNum,
                $bed ?: null,
                $unit ?: null,
                $service,
                $admissionType,
                $attendingUserId,
                $admittingDiagnosis ?: null,
                $admittingIcd10 ?: null,
                $expectedLosDays,
            ]
        );

        return (int)$episodeId;
    }

    /** Return the encounter NUMBER for an IP episode (form_encounter.encounter). */
    public function getEncounterId(int $episodeId): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery(
            'SELECT encounter_id FROM oei_ip_episode WHERE episode_id = ? LIMIT 1',
            [$episodeId]
        );
        return (int)($row['encounter_id'] ?? 0);
    }

    /**
     * Check if a patient already has an active IP episode at this facility.
     * Prevents double-admission.
     */

    /**
     * Active locations from oei_location for the admission form bed/unit selector.
     *
     * Returns rows shaped as expected by the admission form:
     *   id, code, name, type (=location_type), unit (=unit_name)
     *
     * @return array<int,array{id:int,code:string,name:string,type:string,unit:string}>
     */
    public function listLocations(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id,
                    COALESCE(code,'')      AS code,
                    COALESCE(name,'')      AS name,
                    COALESCE(location_type,'') AS type,
                    COALESCE(unit_name,'') AS unit
             FROM oei_location
             WHERE facility_id = ? AND is_active = 1
             ORDER BY COALESCE(unit_name,''), sort_order ASC, name ASC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = [
                'id'   => (int)$row['id'],
                'code' => (string)$row['code'],
                'name' => (string)$row['name'],
                'type' => (string)$row['type'],
                'unit' => (string)$row['unit'],
            ];
        }
        return $rows;
    }

    public function hasActiveEpisode(int $pid, int $facilityId): bool
    {
        if (!function_exists('sqlQuery')) {
            return false;
        }
        $r = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode
             WHERE pid=? AND facility_id=? AND status='ACTIVE' AND type='IP'",
            [$pid, $facilityId]
        );
        return (int)($r['c'] ?? 0) > 0;
    }

    /**
     * Fetch all active attending physicians (authorized=1, active=1).
     * @return array<int,array{id:int,name:string}>
     */
    public function listAttendingPhysicians(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id,
                    CONCAT(COALESCE(fname,''), ' ', COALESCE(lname,'')) AS name
             FROM users
             WHERE authorized = 1 AND active = 1
               AND fname IS NOT NULL AND username IS NOT NULL
             ORDER BY lname ASC, fname ASC"
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = ['id' => (int) $row['id'], 'name' => trim((string) $row['name'])];
        }
        return $rows;
    }
}







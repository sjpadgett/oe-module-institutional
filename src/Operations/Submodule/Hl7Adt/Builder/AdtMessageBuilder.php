<?php

/**
 * src/Operations/Submodule/Hl7Adt/Builder/AdtMessageBuilder.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Operations\Submodule\Hl7Adt\Builder;

/**
 * Builds HL7 v2.5.1 ADT messages from module episode data.
 *
 * Supported event types:
 *   A01 - Admit / Inpatient Admit
 *   A02 - Transfer a Patient (location change)
 *   A03 - Discharge / End Visit
 *   A04 - Register a Patient (ED arrival, outpatient)
 *   A08 - Update Patient Information (status change)
 *
 * Segment order per HL7 v2.5.1 ADT chapter:
 *   MSH  EVN  PID  [PD1]  PV1  [PV2]
 *
 * Field separator:  |
 * Component sep:    ^
 * Repetition sep:   ~
 * Escape:           \
 * Subcomponent sep: &
 */
final class AdtMessageBuilder
{
    private const HL7_VERSION    = '2.5.1';
    private const FIELD_SEP      = '|';
    private const ENCODING_CHARS = '^~\\&';
    private const SEGMENT_SEP    = "\r";   // HL7 uses CR, not CRLF

    private string $sendingApplication;
    private string $sendingFacility;
    private string $receivingApplication;
    private string $receivingFacility;
    private string $processingId;           // P=production, T=test, D=debug

    public function __construct(
        string $sendingApplication   = 'OE-INSTITUTIONAL',
        string $sendingFacility      = 'OPENEMR',
        string $receivingApplication = '',
        string $receivingFacility    = '',
        string $processingId         = 'P'
    ) {
        $this->sendingApplication   = $sendingApplication;
        $this->sendingFacility      = $sendingFacility;
        $this->receivingApplication = $receivingApplication;
        $this->receivingFacility    = $receivingFacility;
        $this->processingId         = $processingId;
    }

    // -----------------------------------------------------------------------
    // Public event builders
    // -----------------------------------------------------------------------

    /**
     * A01 — Admit / begin inpatient encounter.
     * Triggered by: obs start, explicit admit disposition.
     *
     * @param array<string,mixed> $episode   oei_episode row
     * @param array<string,mixed> $patient   patient_data row (pid, fname, lname, DOB, sex, ss, addr…)
     * @param array<string,mixed>|null $location  oei_location row (current assigned location)
     */
    public function buildA01(array $episode, array $patient, ?array $location = null): string
    {
        return $this->build('A01', $episode, $patient, $location);
    }

    /** A02 — Transfer a patient to a new location. */
    public function buildA02(array $episode, array $patient, ?array $location = null): string
    {
        return $this->build('A02', $episode, $patient, $location);
    }

    /** A03 — Discharge / end visit. */
    public function buildA03(array $episode, array $patient, ?array $location = null): string
    {
        return $this->build('A03', $episode, $patient, $location);
    }

    /** A04 — Register a patient (ED arrival, walk-in). */
    public function buildA04(array $episode, array $patient, ?array $location = null): string
    {
        return $this->build('A04', $episode, $patient, $location);
    }

    /** A08 — Update patient information (status change, acuity update). */
    public function buildA08(array $episode, array $patient, ?array $location = null): string
    {
        return $this->build('A08', $episode, $patient, $location);
    }

    // -----------------------------------------------------------------------
    // Core builder
    // -----------------------------------------------------------------------

    private function build(string $eventCode, array $episode, array $patient, ?array $location): string
    {
        $now        = date('YmdHis');
        $msgCtrlId  = $this->generateMessageControlId();
        $eventTs    = $this->hl7Datetime($episode['start_datetime'] ?? null) ?: $now;
        $pid        = (string)($episode['pid'] ?? '');
        $episodeId  = (string)($episode['id'] ?? '');

        $segments = [
            $this->msh($now, $eventCode, $msgCtrlId),
            $this->evn($eventCode, $now, $eventTs),
            $this->pid($pid, $patient, $episodeId),
            $this->pv1($eventCode, $episode, $location),
        ];

        // PV2 only for A01/A04 — captures chief complaint and expected stay
        if (in_array($eventCode, ['A01', 'A04'], true)) {
            $pv2 = $this->pv2($episode);
            if ($pv2 !== '') {
                $segments[] = $pv2;
            }
        }

        return implode(self::SEGMENT_SEP, $segments) . self::SEGMENT_SEP;
    }

    // -----------------------------------------------------------------------
    // Segment builders
    // -----------------------------------------------------------------------

    /** MSH — Message Header */
    private function msh(string $datetime, string $eventCode, string $controlId): string
    {
        return implode('|', [
            'MSH',
            self::ENCODING_CHARS,                          // MSH.2
            $this->esc($this->sendingApplication),         // MSH.3
            $this->esc($this->sendingFacility),            // MSH.4
            $this->esc($this->receivingApplication),       // MSH.5
            $this->esc($this->receivingFacility),          // MSH.6
            $datetime,                                     // MSH.7 date/time
            '',                                            // MSH.8 security
            'ADT^' . $eventCode . '^ADT_A01',             // MSH.9 message type
            $controlId,                                    // MSH.10 control ID
            $this->processingId,                           // MSH.11
            self::HL7_VERSION,                             // MSH.12
        ]);
    }

    /** EVN — Event Type */
    private function evn(string $eventCode, string $recordedTs, string $plannedTs): string
    {
        return implode('|', [
            'EVN',
            $eventCode,     // EVN.1 event type code
            $recordedTs,    // EVN.2 recorded date/time
            '',             // EVN.3 date/time planned event (optional)
            '',             // EVN.4 event reason code
            '',             // EVN.5 operator ID
            $plannedTs,     // EVN.6 event occurred
        ]);
    }

    /** PID — Patient Identification */
    private function pid(string $pid, array $patient, string $episodeId): string
    {
        $fname = $this->esc($patient['fname'] ?? '');
        $lname = $this->esc($patient['lname'] ?? '');
        $dob   = $this->hl7Date($patient['DOB'] ?? null);
        $sex   = $this->mapSex($patient['sex'] ?? '');
        $ss    = preg_replace('/\D/', '', (string)($patient['ss'] ?? ''));

        // PID.3 — patient identifier list: PID^^^AssigningAuth^MR
        $pidList = $pid . '^^^OPENEMR^MR';

        // PID.5 — patient name: LastName^FirstName^Middle^Suffix^Prefix
        $name = $lname . '^' . $fname;

        // PID.11 — address
        $street = $this->esc($patient['street'] ?? '');
        $city   = $this->esc($patient['city'] ?? '');
        $state  = $this->esc($patient['state'] ?? '');
        $zip    = $this->esc($patient['postal_code'] ?? '');
        $addr   = $street . '^' . $city . '^' . $state . '^' . $zip . '^USA';

        // PID.13 — phone home
        $phone  = preg_replace('/\D/', '', (string)($patient['phone_home'] ?? $patient['phone_cell'] ?? ''));

        return implode('|', [
            'PID',
            '1',            // PID.1 set ID
            '',             // PID.2 patient ID (external) — deprecated in v2.5
            $pidList,       // PID.3 patient identifier list
            '',             // PID.4 alternate patient ID — deprecated
            $name,          // PID.5 patient name
            '',             // PID.6 mother's maiden name
            $dob,           // PID.7 DOB
            $sex,           // PID.8 sex
            '',             // PID.9 patient alias
            '',             // PID.10 race
            $addr,          // PID.11 address
            '',             // PID.12 county code
            $phone,         // PID.13 phone home
            '',             // PID.14 phone business
            '',             // PID.15 language
            '',             // PID.16 marital status
            '',             // PID.17 religion
            $episodeId,     // PID.18 patient account number (our episode ID)
            $ss,            // PID.19 SSN (last 4 or full depending on facility policy)
        ]);
    }

    /** PV1 — Patient Visit */
    private function pv1(string $eventCode, array $episode, ?array $location): string
    {
        // PV1.2 — patient class
        $class = $this->mapEpisodeTypeToClass($episode['type'] ?? 'ED');

        // PV1.3 — assigned patient location: PointOfCare^Room^Bed^Facility^Status^Building^Floor
        $poc   = '';
        $room  = '';
        $bed   = '';
        if ($location) {
            $poc  = $this->esc($location['unit_name'] ?? $location['code'] ?? '');
            $room = $this->esc($location['name'] ?? '');
        }
        $assignedLoc = $poc . '^' . $room . '^' . $bed;

        // PV1.4 — admission type
        $admitType = $this->mapAdmitType($episode['arrival_mode'] ?? '');

        // PV1.7 — attending doctor (if we have provider_user_id)
        $attending = '';
        if (!empty($episode['provider_user_id'])) {
            $attending = $this->lookupProviderNpi((int)$episode['provider_user_id']);
        }

        // PV1.17 — admitting doctor (same as attending for ED)
        // PV1.19 — visit number (our episode ID)
        $visitNumber = (string)($episode['id'] ?? '');

        // PV1.36 — discharge disposition (A03 only)
        $dischDispo = '';
        if ($eventCode === 'A03') {
            $dischDispo = $this->mapDisposition($episode['disposition'] ?? '');
        }

        // PV1.44 — admit date/time
        $admitDt = $this->hl7Datetime($episode['start_datetime'] ?? null);

        // PV1.45 — discharge date/time (A03 only)
        $dischDt = '';
        if ($eventCode === 'A03') {
            $dischDt = $this->hl7Datetime($episode['end_datetime'] ?? null);
        }

        return implode('|', [
            'PV1',
            '1',             // PV1.1 set ID
            $class,          // PV1.2 patient class (E=Emergency, I=Inpatient, O=Outpatient)
            $assignedLoc,    // PV1.3 assigned patient location
            $admitType,      // PV1.4 admission type
            '',              // PV1.5 preadmit number
            '',              // PV1.6 prior patient location
            $attending,      // PV1.7 attending doctor
            '',              // PV1.8 referring doctor
            '',              // PV1.9 consulting doctor
            $this->mapEpisodeTypeToService($episode['type'] ?? ''), // PV1.10 hospital service
            '',              // PV1.11 temporary location
            '',              // PV1.12 preadmit test indicator
            '',              // PV1.13 re-admission indicator
            '',              // PV1.14 admit source
            '',              // PV1.15 ambulatory status
            '',              // PV1.16 VIP indicator
            $attending,      // PV1.17 admitting doctor
            '',              // PV1.18 patient type
            $visitNumber,    // PV1.19 visit number
            '',              // PV1.20-35 financial class etc (blank)
            '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            $dischDispo,     // PV1.36 discharge disposition
            '',              // PV1.37 discharged to location
            '',              // PV1.38 diet type
            '',              // PV1.39 servicing facility
            '',              // PV1.40 bed status
            '',              // PV1.41 account status
            '',              // PV1.42 pending location
            '',              // PV1.43 prior temporary location
            $admitDt,        // PV1.44 admit date/time
            $dischDt,        // PV1.45 discharge date/time
        ]);
    }

    /** PV2 — Patient Visit Additional Info */
    private function pv2(array $episode): string
    {
        $chief     = $this->esc($episode['chief_complaint'] ?? '');
        $acuity    = $this->esc((string)($episode['acuity_esi'] ?? ''));
        $arrMode   = $this->esc($episode['arrival_mode'] ?? '');

        if ($chief === '' && $acuity === '') {
            return '';
        }

        return implode('|', [
            'PV2',
            '',         // PV2.1 prior pending location
            '',         // PV2.2 accommodation code
            '',         // PV2.3 admit reason (CWE)
            '',         // PV2.4 transfer reason
            $chief,     // PV2.5 patient valuables
            $chief,     // PV2.6 patient valuables location (reuse for chief complaint text)
            $arrMode,   // PV2.7 visit user code (arrival mode)
            '',         // PV2.8 expected admit date
            '',         // PV2.9 expected discharge date
            '',         // PV2.10 estimated length of inpatient stay
            '',         // PV2.11 actual length of inpatient stay
            $chief,     // PV2.12 visit description (chief complaint)
        ]);
    }

    // -----------------------------------------------------------------------
    // Mapping helpers
    // -----------------------------------------------------------------------

    private function mapEpisodeTypeToClass(string $type): string
    {
        return match (strtoupper($type)) {
            'ED'    => 'E',   // Emergency
            'OBS'   => 'O',   // Observation (outpatient in HL7 terms)
            'BH'    => 'I',   // Inpatient (behavioral health admit)
            default => 'E',
        };
    }

    private function mapEpisodeTypeToService(string $type): string
    {
        return match (strtoupper($type)) {
            'ED'    => 'EMR',
            'OBS'   => 'OBS',
            'BH'    => 'PSY',
            default => 'EMR',
        };
    }

    private function mapAdmitType(string $mode): string
    {
        return match (strtoupper($mode)) {
            'EMS'       => 'E',   // Emergency
            'TRANSFER'  => 'T',   // Transfer
            default     => 'E',   // Emergency (default for ED)
        };
    }

    private function mapSex(string $sex): string
    {
        return match (strtoupper($sex)) {
            'M', 'MALE'    => 'M',
            'F', 'FEMALE'  => 'F',
            default        => 'U',
        };
    }

    private function mapDisposition(string $code): string
    {
        // HL7 discharge disposition table 0112
        return match (strtoupper($code)) {
            'DISCHARGE'         => '01',  // Discharged to home
            'ADMIT'             => '03',  // Admitted to this institution
            'TRANSFER'          => '02',  // Discharged/transferred to another facility
            'LWBS'              => '07',  // Left against medical advice
            'ELOPE'             => '07',
            'EXPIRE'            => '20',  // Expired
            default             => '01',
        };
    }

    /**
     * Look up provider NPI from OpenEMR users table.
     * Returns NPI^LastName^FirstName^^^^^NPI format for PV1.7.
     */
    private function lookupProviderNpi(int $userId): string
    {
        if (!function_exists('sqlQuery')) {
            return '';
        }
        $row = sqlQuery(
            "SELECT fname, lname, npi FROM users WHERE id = ? LIMIT 1",
            [$userId]
        );
        if (!$row) {
            return '';
        }
        $npi   = $this->esc((string)($row['npi']   ?? ''));
        $lname = $this->esc((string)($row['lname']  ?? ''));
        $fname = $this->esc((string)($row['fname']  ?? ''));
        return $npi . '^' . $lname . '^' . $fname . '^^^^^NPI';
    }

    // -----------------------------------------------------------------------
    // Formatting utilities
    // -----------------------------------------------------------------------

    /** Format SQL datetime to HL7 YYYYMMDDHHmmss */
    private function hl7Datetime(?string $dt): string
    {
        if (!$dt) {
            return '';
        }
        $ts = strtotime($dt);
        return $ts ? date('YmdHis', $ts) : '';
    }

    /** Format SQL date to HL7 YYYYMMDD */
    private function hl7Date(?string $d): string
    {
        if (!$d) {
            return '';
        }
        $ts = strtotime($d);
        return $ts ? date('Ymd', $ts) : '';
    }

    /** Escape HL7 special characters in a field value. */
    private function esc(string $val): string
    {
        return str_replace(
            ['\\',   '|',    '^',    '~',    '&'],
            ['\E\\', '\F\\', '\S\\', '\R\\', '\T\\'],
            $val
        );
    }

    /** Generate a unique message control ID. */
    private function generateMessageControlId(): string
    {
        return 'OEI' . date('YmdHis') . sprintf('%04d', random_int(0, 9999));
    }
}




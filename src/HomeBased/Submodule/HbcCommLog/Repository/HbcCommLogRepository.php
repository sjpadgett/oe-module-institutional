<?php

/**
 * src/HomeBased/Submodule/HbcCommLog/Repository/HbcCommLogRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcCommLog\Repository;

/**
 * HbcCommLogRepository — reads/writes for oei_hbc_comm_log.
 */
final class HbcCommLogRepository
{
    /** @return array<string,string> */
    public static function commTypes(): array
    {
        return [
            'PHONE_OUT'  => 'Phone (outgoing)',
            'PHONE_IN'   => 'Phone (incoming)',
            'FAX'        => 'Fax',
            'SECURE_MSG' => 'Secure message',
            'IN_PERSON'  => 'In person',
            'OTHER'      => 'Other',
        ];
    }

    /** @return array<string,string> */
    public static function contactRoles(): array
    {
        return [
            'PCP'                 => 'PCP / Primary Care',
            'SPECIALIST'          => 'Specialist',
            'PHARMACY'            => 'Pharmacy',
            'FAMILY'              => 'Family member',
            'CAREGIVER'           => 'Caregiver',
            'DME_SUPPLIER'        => 'DME / Medical supply',
            'PAYER'               => 'Payer / Insurance',
            'HOME_HEALTH_AGENCY'  => 'Home health agency',
            'HOSPICE'             => 'Hospice',
            'SOCIAL_SERVICES'     => 'Social services',
            'OTHER'               => 'Other',
        ];
    }

    /** @return int Inserted ID */
    public function create(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $commType,
        string $contactRole,
        ?string $contactName,
        ?string $contactPhone,
        ?string $subject,
        ?string $summary,
        ?string $outcome,
        bool $followupNeeded,
        ?string $followupNote,
        string $commDatetime,
        ?int $userId
    ): int {
        if (!function_exists('sqlInsert')) {
            return 0;
        }

        return (int) sqlInsert(
            "INSERT INTO oei_hbc_comm_log
                (episode_id, pid, facility_id,
                 comm_type, contact_role, contact_name, contact_phone,
                 subject, summary, outcome,
                 followup_needed, followup_note,
                 comm_datetime, user_id, created_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $episodeId, $pid, $facilityId,
                $commType, $contactRole, $contactName, $contactPhone,
                $subject, $summary, $outcome,
                $followupNeeded ? 1 : 0, $followupNote,
                $commDatetime, $userId,
            ]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listByEpisode(int $episodeId, int $limit = 30): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT cl.*,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS logged_by_name
             FROM   oei_hbc_comm_log cl
             LEFT   JOIN users u ON u.id = cl.user_id AND u.active = 1
             WHERE  cl.episode_id = ?
             ORDER  BY cl.comm_datetime DESC, cl.id DESC
             LIMIT  " . (int) $limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'              => (int) $r['id'],
                'episode_id'      => (int) $r['episode_id'],
                'pid'             => (int) $r['pid'],
                'comm_type'       => (string) $r['comm_type'],
                'contact_role'    => (string) $r['contact_role'],
                'contact_name'    => (string) ($r['contact_name'] ?? ''),
                'contact_phone'   => (string) ($r['contact_phone'] ?? ''),
                'subject'         => (string) ($r['subject'] ?? ''),
                'summary'         => (string) ($r['summary'] ?? ''),
                'outcome'         => (string) ($r['outcome'] ?? ''),
                'followup_needed' => (bool) $r['followup_needed'],
                'followup_note'   => (string) ($r['followup_note'] ?? ''),
                'comm_datetime'   => (string) $r['comm_datetime'],
                'logged_by_name'  => trim((string) ($r['logged_by_name'] ?? '')),
                'created_datetime' => (string) $r['created_datetime'],
            ];
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> Facility-wide comm log */
    public function listByFacility(int $facilityId, int $limit = 50): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT cl.*,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS logged_by_name,
                    CONCAT(COALESCE(pd.lname,''),', ',COALESCE(pd.fname,'')) AS patient_name,
                    hbc.referral_status,
                    hbc.primary_diagnosis
             FROM   oei_hbc_comm_log cl
             LEFT   JOIN users u        ON u.id = cl.user_id AND u.active = 1
             LEFT   JOIN patient_data pd ON pd.pid = cl.pid
             LEFT   JOIN oei_hbc_episode hbc ON hbc.episode_id = cl.episode_id
             WHERE  cl.facility_id = ?
             ORDER  BY cl.comm_datetime DESC, cl.id DESC
             LIMIT  " . (int) $limit,
            [$facilityId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'              => (int) $r['id'],
                'episode_id'      => (int) $r['episode_id'],
                'pid'             => (int) $r['pid'],
                'patient_name'    => trim((string) ($r['patient_name'] ?? '')),
                'referral_status' => (string) ($r['referral_status'] ?? ''),
                'primary_diagnosis' => (string) ($r['primary_diagnosis'] ?? ''),
                'comm_type'       => (string) $r['comm_type'],
                'contact_role'    => (string) $r['contact_role'],
                'contact_name'    => (string) ($r['contact_name'] ?? ''),
                'contact_phone'   => (string) ($r['contact_phone'] ?? ''),
                'subject'         => (string) ($r['subject'] ?? ''),
                'summary'         => (string) ($r['summary'] ?? ''),
                'outcome'         => (string) ($r['outcome'] ?? ''),
                'followup_needed' => (bool) $r['followup_needed'],
                'followup_note'   => (string) ($r['followup_note'] ?? ''),
                'comm_datetime'   => (string) $r['comm_datetime'],
                'logged_by_name'  => trim((string) ($r['logged_by_name'] ?? '')),
                'created_datetime' => (string) $r['created_datetime'],
            ];
        }
        return $rows;
    }

    /** @return int Facility-wide pending followup count */
    public function countPendingFollowupsByFacility(int $facilityId): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_hbc_comm_log
             WHERE facility_id = ? AND followup_needed = 1",
            [$facilityId]
        );
        return (int) ($row['c'] ?? 0);
    }

    /** @return int Count of follow-up-needed entries for this episode */
    public function countPendingFollowups(int $episodeId): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_hbc_comm_log
             WHERE episode_id = ? AND followup_needed = 1",
            [$episodeId]
        );
        return (int) ($row['c'] ?? 0);
    }
}







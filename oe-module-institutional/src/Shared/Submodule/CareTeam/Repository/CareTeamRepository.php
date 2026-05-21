<?php

/**
 * src/Shared/Submodule/CareTeam/Repository/CareTeamRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Repository;

/**
 * CareTeamRepository
 *
 * Reads and writes OpenEMR's care_teams + care_team_member tables.
 *
 * Care teams are PATIENT-anchored (pid), not encounter-anchored.
 * One team persists across all episodes for a patient.
 *
 * Role values must match list_options where list_id = 'care_team_roles'
 * (SNOMED-CT coded, e.g. 'physician', 'nurse', 'therapist', etc.)
 *
 * Status values must match list_options where list_id = 'Care_Team_Status'
 * (HL7 FHIR coded: 'active', 'inactive', 'suspended', 'proposed')
 */
final class CareTeamRepository
{
    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * Active care team + members for a patient.
     *
     * @return array{team: array|null, members: list<array{
     *             id:int,role:string,role_label:string,
     *             member_name:string,provider_since:string|null,note:string}>}
     */
    public function fetchByPatient(int $pid): array
    {
        if (!function_exists('sqlStatement')) {
            return ['team' => null, 'members' => []];
        }

        $team = sqlQuery(
            "SELECT id, team_name, status, note, date_updated
             FROM   care_teams
             WHERE  pid = ? AND status = 'active'
             ORDER  BY id DESC LIMIT 1",
            [$pid]
        );

        if (!$team) {
            return ['team' => null, 'members' => []];
        }

        $res = sqlStatement(
            "SELECT ctm.id, ctm.role, ctm.provider_since, ctm.note,
                    CASE
                      WHEN ctm.user_id IS NOT NULL
                           THEN CONCAT(u.fname,' ',u.lname)
                      ELSE COALESCE(CONCAT(pd.fname,' ',pd.lname),'')
                    END AS member_name,
                    lo.title AS role_label
             FROM   care_team_member  ctm
             LEFT   JOIN users        u  ON u.id   = ctm.user_id
             LEFT   JOIN patient_data pd ON pd.pid = ctm.contact_id
             LEFT   JOIN list_options lo
                      ON lo.list_id    = 'care_team_roles'
                     AND lo.option_id  = ctm.role
             WHERE  ctm.care_team_id = ? AND ctm.status = 'active'
             ORDER  BY ctm.id ASC",
            [(int)$team['id']]
        );

        $members = [];
        while ($r = sqlFetchArray($res)) {
            $members[] = [
                'id'             => (int)$r['id'],
                'role'           => (string)$r['role'],
                'role_label'     => (string)($r['role_label'] ?? $r['role']),
                'member_name'    => trim((string)$r['member_name']),
                'provider_since' => $r['provider_since'] ?: null,
                'note'           => (string)($r['note'] ?? ''),
            ];
        }

        return ['team' => $team, 'members' => $members];
    }

    /**
     * Fetch all available roles from list_options care_team_roles.
     * Used to populate the Add Member role selector.
     *
     * @return list<array{option_id:string,title:string,codes:string}>
     */
    public function fetchRoles(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT option_id, title, codes
             FROM   list_options
             WHERE  list_id = 'care_team_roles' AND activity = 1
             ORDER  BY seq ASC"
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'option_id' => (string)$r['option_id'],
                'title'     => (string)$r['title'],
                'codes'     => (string)($r['codes'] ?? ''),
            ];
        }
        return $rows;
    }

    /**
     * Fetch all active staff users for the Add Member user picker.
     *
     * @return list<array{id:int,name:string,username:string}>
     */
    public function fetchStaff(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, CONCAT(fname,' ',lname) AS name, username
             FROM   users
             WHERE  active = 1 AND username != ''
             ORDER  BY lname, fname"
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'       => (int)$r['id'],
                'name'     => trim((string)$r['name']),
                'username' => (string)$r['username'],
            ];
        }
        return $rows;
    }

    // ── Write ────────────────────────────────────────────────────────────────

    /**
     * Ensure an active care team exists for the patient.
     * Creates one if absent; returns existing team id if present.
     * Safe to call multiple times (idempotent).
     *
     * @param string $teamName Suggested name, e.g. "Hartwell Care Team"
     * @return int  care_teams.id
     */
    public function ensureTeam(int $pid, string $teamName, int $userId): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }

        $existing = sqlQuery(
            "SELECT id FROM care_teams WHERE pid = ? AND status = 'active' LIMIT 1",
            [$pid]
        );

        if ($existing && !empty($existing['id'])) {
            return (int)$existing['id'];
        }

        // Generate UUID for the new row
        $uuidRow = sqlQuery("SELECT UUID() AS u");
        $uuid    = $uuidRow['u'] ?? null;

        $id = (int)sqlInsert(
            "INSERT INTO care_teams
                 (uuid, pid, status, team_name, created_by, updated_by)
             VALUES (UUID_TO_BIN(?), ?, 'active', ?, ?, ?)",
            [$uuid, $pid, $teamName, $userId, $userId]
        );

        return $id;
    }

    /**
     * Add a member to a care team.
     *
     * Exactly one of $userId, $contactId, or $facilityId must be provided.
     *
     * @param int    $careTeamId    care_teams.id
     * @param string $role          list_options care_team_roles option_id
     * @param int|null $userId      users.id (staff / provider)
     * @param int|null $contactId   contact.id (external contact)
     * @param int|null $facilityId  facility.id (organisation)
     * @param string|null $providerSince  Date string 'Y-m-d'
     * @param string $note
     * @return bool
     */
    public function addMember(
        int     $careTeamId,
        string  $role,
        ?int    $userId        = null,
        ?int    $contactId     = null,
        ?int    $facilityId    = null,
        ?string $providerSince = null,
        string  $note          = '',
        int     $createdBy     = 0
    ): bool {
        if (!function_exists('sqlInsert')) {
            return false;
        }

        // Gracefully handle the UNIQUE KEY by using INSERT IGNORE
        $id = (int)sqlInsert(
            "INSERT IGNORE INTO care_team_member
                 (care_team_id, user_id, contact_id, facility_id,
                  role, status, provider_since, note, created_by, updated_by)
             VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?)",
            [
                $careTeamId,
                $userId,
                $contactId,
                $facilityId,
                $role,
                $providerSince,
                $note,
                $createdBy,
                $createdBy,
            ]
        );

        return $id > 0;
    }

    /**
     * Soft-deactivate a care_team_member row.
     *
     * @param int $memberId   care_team_member.id
     * @param int $updatedBy  users.id
     */
    public function deactivateMember(int $memberId, int $updatedBy): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE care_team_member
             SET    status = 'inactive', updated_by = ?
             WHERE  id = ?",
            [$updatedBy, $memberId]
        );
    }
}




<?php

/**
 * src/Shared/Submodule/CareTeam/Service/CareTeamService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Repository\CareTeamRepository;

/**
 * CareTeamService
 *
 * Business logic for care team management.
 * Care teams are patient-anchored — pid, not episode/encounter.
 */
final class CareTeamService
{
    public function __construct(private readonly CareTeamRepository $repo) {}

    /**
     * Full page data for the care team panel.
     *
     * @return array{team:array|null,members:list<array>,
     *             roles:list<array>,staff:list<array>,pid:int}
     */
    public function pageData(int $pid): array
    {
        $careTeam = $this->repo->fetchByPatient($pid);
        $roles    = $this->repo->fetchRoles();
        $staff    = $this->repo->fetchStaff();

        return [
            'team'    => $careTeam['team'],
            'members' => $careTeam['members'],
            'roles'   => $roles,
            'staff'   => $staff,
            'pid'     => $pid,
        ];
    }

    /**
     * Ensure team + add a member in one call.
     * Convenience method for intake flows.
     *
     * @param string $teamName  Used only if a new team is created
     */
    public function ensureAndAddMember(
        int     $pid,
        string  $teamName,
        string  $role,
        ?int    $userId,
        ?int    $contactId,
        ?int    $facilityId,
        ?string $providerSince,
        string  $note,
        int     $actingUserId
    ): bool {
        $teamId = $this->repo->ensureTeam($pid, $teamName, $actingUserId);
        if ($teamId <= 0) {
            return false;
        }
        return $this->repo->addMember(
            $teamId, $role, $userId, $contactId, $facilityId,
            $providerSince, $note, $actingUserId
        );
    }

    /**
     * Add a member to an existing team.
     * If no active team exists for the patient, creates one first.
     */
    public function addMember(
        int     $pid,
        string  $role,
        ?int    $userId,
        ?int    $contactId,
        ?int    $facilityId,
        ?string $providerSince,
        string  $note,
        int     $actingUserId
    ): bool {
        // Derive a sensible default team name from the patient record
        $teamName = $this->defaultTeamName($pid);
        $teamId   = $this->repo->ensureTeam($pid, $teamName, $actingUserId);
        if ($teamId <= 0) {
            return false;
        }
        return $this->repo->addMember(
            $teamId, $role, $userId, $contactId, $facilityId,
            $providerSince, $note, $actingUserId
        );
    }

    /** Deactivate a member. */
    public function removeMember(int $memberId, int $actingUserId): void
    {
        $this->repo->deactivateMember($memberId, $actingUserId);
    }

    /**
     * Ensure a team exists for intake flow (creates if absent).
     * Returns the care_teams.id.
     */
    public function ensureTeamForPatient(int $pid, int $actingUserId): int
    {
        $teamName = $this->defaultTeamName($pid);
        return $this->repo->ensureTeam($pid, $teamName, $actingUserId);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function defaultTeamName(int $pid): string
    {
        if (!function_exists('sqlQuery')) {
            return "Patient #{$pid} Care Team";
        }
        $pd = sqlQuery(
            "SELECT fname, lname FROM patient_data WHERE pid = ? LIMIT 1",
            [$pid]
        );
        if ($pd) {
            return trim("{$pd['lname']}, {$pd['fname']} Care Team");
        }
        return "Patient #{$pid} Care Team";
    }
}




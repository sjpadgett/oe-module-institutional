<?php

/**
 * src/Shared/Submodule/CarePlan/Repository/CarePlanRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Repository;

use OpenEMR\Modules\Institutional\Core\Service\EncounterResolver;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;

/**
 * Shared CarePlanRepository
 *
 * Cross-context read/write bridge to OpenEMR's form_care_plan table.
 * Works for AL, IP, ED, OBS, and BH episode types via EncounterResolver.
 *
 * All writes also call FormsRegistrar so entries appear in the
 * OpenEMR encounter form list and CCDA/FHIR export pipeline.
 *
 * Care team reads are also here — pulled from OpenEMR's care_teams
 * and care_team_member tables (USCDI v3, FHIR CareTeam compatible).
 */
final class CarePlanRepository
{
    public function __construct(
        private readonly EncounterResolver $encounterResolver,
        private readonly FormsRegistrar    $formsRegistrar
    ) {}

    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * All active goals + activities for an episode (any type).
     *
     * @param int    $episodeId   oei_episode.id
     * @param string $episodeType 'AL'|'IP'|'ED'|'OBS'|'BH'
     * @return array<int,array{id:int,care_plan_type:string,code:string,
     *             codetext:string,description:string,plan_status:string,
     *             proposed_date:string|null,date_end:string|null,
     *             reason_code:string,reason_description:string}>
     */
    public function fetchByEpisode(int $episodeId, string $episodeType): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $encounterId = $this->encounterResolver->resolve($episodeId, $episodeType);
        if ($encounterId === null) {
            return [];
        }

        $ep = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        if (!$ep) {
            return [];
        }

        $res = sqlStatement(
            "SELECT id, care_plan_type,
                    COALESCE(code,'')               AS code,
                    COALESCE(codetext,'')           AS codetext,
                    COALESCE(description,'')        AS description,
                    COALESCE(plan_status,'active')  AS plan_status,
                    proposed_date, date_end,
                    COALESCE(reason_code,'')        AS reason_code,
                    COALESCE(reason_description,'') AS reason_description
             FROM   form_care_plan
             WHERE  pid = ? AND encounter = ? AND activity = 1
             ORDER  BY care_plan_type ASC, id ASC",
            [(int)$ep['pid'], $encounterId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                 => (int)$r['id'],
                'care_plan_type'     => (string)$r['care_plan_type'],
                'code'               => (string)$r['code'],
                'codetext'           => (string)$r['codetext'],
                'description'        => (string)$r['description'],
                'plan_status'        => (string)$r['plan_status'],
                'proposed_date'      => $r['proposed_date'] ?: null,
                'date_end'           => $r['date_end'] ?: null,
                'reason_code'        => (string)$r['reason_code'],
                'reason_description' => (string)$r['reason_description'],
            ];
        }
        return $rows;
    }


    // ── Write ────────────────────────────────────────────────────────────────

    /**
     * Add a goal or activity entry for any episode type.
     *
     * form_care_plan.id has NO auto_increment.  All rows for the same
     * encounter share one id = forms.form_id for that encounter's care_plan
     * registry entry.
     *
     * Pattern:
     *   1. Check if a care_plan forms row already exists for this encounter.
     *   2. Yes → reuse its form_id as the group id.
     *   3. No  → generate new id via QueryUtils::generateId(), call addForm(),
     *             then INSERT form_care_plan with that id.
     */
    public function addEntry(
        int     $episodeId,
        string  $episodeType,
        string  $type,
        string  $description,
        string  $code,
        string  $codeText,
        string  $status,
        ?string $proposedDate,
        int     $userId
    ): bool {
        if (!function_exists('sqlInsert')) {
            return false;
        }

        $encounterNum = $this->encounterResolver->resolve($episodeId, $episodeType);
        if ($encounterNum === null) {
            return false;
        }

        $ep = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        if (!$ep) {
            return false;
        }

        $pid  = (int)$ep['pid'];
        $user = $_SESSION['authUser'] ?? 'admin';

        $cpGroupId = $this->resolveCpGroupId($pid, $encounterNum, $userId, $user);
        if ($cpGroupId === 0) {
            return false;
        }

        sqlInsert(
            "INSERT INTO form_care_plan
                 (id, date, pid, encounter, user, groupname, authorized, activity,
                  code, codetext, description,
                  care_plan_type, plan_status, proposed_date)
             VALUES (?,NOW(),?,?,?,'Default',1,1,?,?,?,?,?,?)",
            [
                $cpGroupId, $pid, $encounterNum, $user,
                $code, $codeText, $description,
                $type, $status, $proposedDate,
            ]
        );

        return true;
    }

    /** Update plan_status on an existing care plan row. */
    public function updateStatus(int $entryId, string $status): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            'UPDATE form_care_plan SET plan_status = ? WHERE id = ?',
            [$status, $entryId]
        );
    }

    /**
     * Resolve (or create) the care plan form group id for this encounter number.
     * Returns 0 on failure.
     */
    private function resolveCpGroupId(
        int    $pid,
        int    $encounterNum,
        int    $userId,
        string $user
    ): int {
        if (!function_exists('sqlQuery')) { return 0; }

        $existing = sqlQuery(
            "SELECT form_id FROM forms
             WHERE  pid=? AND encounter=? AND formdir='care_plan' AND deleted=0
             LIMIT  1",
            [$pid, $encounterNum]
        );

        if ($existing && !empty($existing['form_id'])) {
            return (int)$existing['form_id'];
        }

        if (!class_exists('OpenEMR\\Common\\Database\\QueryUtils')) { return 0; }
        $cpGroupId = \OpenEMR\Common\Database\QueryUtils::generateId();

        if (!function_exists('addForm')) {
            $inc = ($GLOBALS['srcdir'] ?? '') . '/forms.inc.php';
            if (file_exists($inc)) { require_once $inc; }
        }

        if (function_exists('addForm')) {
            addForm(
                $encounterNum, 'Care Plan', $cpGroupId,
                'care_plan', $pid, 1,
                date('Y-m-d H:i:s'), $user, $userId
            );
        } else {
            sqlInsert(
                "INSERT INTO forms
                    (date, encounter, form_name, form_id, pid, user,
                     groupname, authorized, deleted, formdir)
                 VALUES (NOW(),?,'Care Plan',?,?,'','Default',1,0,'care_plan')",
                [$encounterNum, $cpGroupId, $pid]
            );
        }

        return $cpGroupId;
    }


    // ── Care Team (patient-anchored) ─────────────────────────────────────────

    /**
     * Active care team for a patient from OpenEMR's care_teams tables.
     *
     * @return array{team: array|null, members: list<array{id:int,role:string,
     *             role_label:string,member_name:string,provider_since:string|null,note:string}>}
     */
    public function fetchCareTeam(int $pid): array
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
                    CASE WHEN ctm.user_id IS NOT NULL
                         THEN CONCAT(u.fname,' ',u.lname)
                         ELSE COALESCE(CONCAT(pd.fname,' ',pd.lname),'')
                    END AS member_name,
                    lo.title AS role_label
             FROM   care_team_member  ctm
             LEFT   JOIN users        u  ON u.id   = ctm.user_id
             LEFT   JOIN patient_data pd ON pd.pid = ctm.contact_id
             LEFT   JOIN list_options lo
                      ON lo.list_id = 'care_team_roles'
                     AND lo.option_id = ctm.role
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

    /** Return the resolved encounter id (or null) — useful for building OE form URLs. */
    public function resolveEncounter(int $episodeId, string $episodeType): ?int
    {
        return $this->encounterResolver->resolve($episodeId, $episodeType);
    }

}






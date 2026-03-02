<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Repository;

/**
 * CarePlanRepository — bridges OpenEMR's certified care plan infrastructure.
 *
 * We write to form_care_plan (not a custom table) so that:
 *   - CCDA C-CDA export works unmodified
 *   - FHIR CarePlan resource generation works unmodified
 *   - eCQM quality measures pick up the data automatically
 *
 * oei_al_episode.encounter_id  →  form_encounter.id
 *   This is the encounter created at AL admission that groups all care
 *   plan entries for this episode. One encounter per AL stay.
 */
final class CarePlanRepository
{
    /** All active goals + activities for an AL episode. */
    public function fetchByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) { return []; }

        $ale = sqlQuery("SELECT encounter_id FROM oei_al_episode WHERE episode_id = ? LIMIT 1", [$episodeId]);
        $ep  = sqlQuery("SELECT pid FROM oei_episode WHERE id = ? LIMIT 1", [$episodeId]);
        if (!$ale || empty($ale['encounter_id']) || !$ep) { return []; }

        $res = sqlStatement(
            "SELECT id, care_plan_type,
                    COALESCE(code,'') AS code, COALESCE(codetext,'') AS codetext,
                    COALESCE(description,'') AS description,
                    COALESCE(plan_status,'active') AS plan_status,
                    proposed_date, date_end,
                    COALESCE(reason_code,'') AS reason_code,
                    COALESCE(reason_description,'') AS reason_description
             FROM   form_care_plan
             WHERE  pid = ? AND encounter = ? AND activity = 1
             ORDER  BY care_plan_type ASC, id ASC",
            [(int)$ep['pid'], (int)$ale['encounter_id']]
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

    /** Add a goal or activity entry to the OpenEMR care plan. */
    public function addEntry(int $episodeId, string $type, string $description,
        string $code, string $codeText, string $status, ?string $proposedDate, int $userId): bool
    {
        if (!function_exists('sqlInsert')) { return false; }
        $ale = sqlQuery("SELECT encounter_id FROM oei_al_episode WHERE episode_id = ? LIMIT 1", [$episodeId]);
        $ep  = sqlQuery("SELECT pid FROM oei_episode WHERE id = ? LIMIT 1", [$episodeId]);
        if (!$ale || empty($ale['encounter_id']) || !$ep) { return false; }
        $id = sqlInsert(
            "INSERT INTO form_care_plan
                (date, pid, encounter, user, groupname, authorized, activity,
                 code, codetext, description, care_plan_type, plan_status, proposed_date)
             VALUES (NOW(),?,?,?,'Default',1,1,?,?,?,?,?,?)",
            [(int)$ep['pid'], (int)$ale['encounter_id'], $userId,
             $code, $codeText, $description, $type, $status, $proposedDate]
        );
        return $id > 0;
    }

    public function updateStatus(int $entryId, string $status): void
    {
        if (!function_exists('sqlStatement')) { return; }
        sqlStatement("UPDATE form_care_plan SET plan_status = ? WHERE id = ?", [$status, $entryId]);
    }

    /**
     * Active care team for a patient, reading OpenEMR care_teams + care_team_member.
     * @return array{team: array|null, members: array}
     */
    public function fetchCareTeam(int $pid): array
    {
        if (!function_exists('sqlStatement')) { return ['team' => null, 'members' => []]; }

        $team = sqlQuery(
            "SELECT id, team_name, status, note, date_updated FROM care_teams
             WHERE pid = ? AND status = 'active' ORDER BY id DESC LIMIT 1",
            [$pid]
        );
        if (!$team) { return ['team' => null, 'members' => []]; }

        $res = sqlStatement(
            "SELECT ctm.id, ctm.role, ctm.provider_since, ctm.note,
                    CASE WHEN ctm.user_id IS NOT NULL
                         THEN CONCAT(u.fname,' ',u.lname)
                         ELSE CONCAT(pd.fname,' ',pd.lname)
                    END AS member_name,
                    lo.title AS role_label
             FROM   care_team_member ctm
             LEFT   JOIN users u        ON u.id = ctm.user_id
             LEFT   JOIN patient_data pd ON pd.pid = ctm.contact_id
             LEFT   JOIN list_options lo ON lo.list_id = 'care_team_roles' AND lo.option_id = ctm.role
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
}

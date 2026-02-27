<?php

namespace OpenEMR\Modules\Institutional\Submodule\Assignment\Repository;

/**
 * AssignmentRepository
 *
 * Manages nurse and provider assignments stored on oei_episode.
 * Two new nullable FK columns are added via migration:
 *   assigned_nurse_user_id   INT NULL
 *   assigned_provider_user_id INT NULL  (may alias provider_user_id if that already exists)
 *
 * Reads provider display names from OpenEMR's users table.
 */
final class AssignmentRepository
{
    /**
     * Assign or clear nurse and/or provider for an episode.
     * Pass null to clear; omit (leave out of $fields) to leave unchanged.
     *
     * @param array{nurse?:int|null,provider?:int|null} $fields
     */
    public function assign(int $episodeId, array $fields): void
    {
        if (!function_exists('sqlStatement') || empty($fields)) return;

        $sets   = [];
        $params = [];

        if (array_key_exists('nurse', $fields)) {
            $sets[]   = 'assigned_nurse_user_id = ?';
            $params[] = $fields['nurse'] ? (int)$fields['nurse'] : null;
        }
        if (array_key_exists('provider', $fields)) {
            $sets[]   = 'assigned_provider_user_id = ?';
            $params[] = $fields['provider'] ? (int)$fields['provider'] : null;
        }

        if (empty($sets)) return;

        $params[] = $episodeId;
        sqlStatement(
            "UPDATE oei_episode SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    /**
     * Return current assignments for one episode.
     * @return array{nurse_id:int|null,provider_id:int|null}
     */
    public function getForEpisode(int $episodeId): array
    {
        if (!function_exists('sqlQuery')) return ['nurse_id' => null, 'provider_id' => null];
        $row = sqlQuery(
            "SELECT assigned_nurse_user_id, assigned_provider_user_id
             FROM oei_episode WHERE id = ? LIMIT 1",
            [$episodeId]
        );
        return [
            'nurse_id'    => $row ? ((int)($row['assigned_nurse_user_id']    ?? 0) ?: null) : null,
            'provider_id' => $row ? ((int)($row['assigned_provider_user_id'] ?? 0) ?: null) : null,
        ];
    }

    /**
     * Return all active episodes with their current assignments.
     * Joined with user names for display.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listWithAssignments(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT
                e.id, e.pid, e.chief_complaint, e.acuity_esi, e.start_datetime,
                e.assigned_nurse_user_id   AS nurse_id,
                e.assigned_provider_user_id AS provider_id,
                CONCAT(COALESCE(nu.fname,''), ' ', COALESCE(nu.lname,'')) AS nurse_name,
                CONCAT(COALESCE(pu.fname,''), ' ', COALESCE(pu.lname,'')) AS provider_name,
                l.name AS location_name,
                (SELECT sh.status_code FROM oei_episode_status_history sh
                 WHERE sh.episode_id = e.id ORDER BY sh.id DESC LIMIT 1) AS workflow_status
             FROM oei_episode e
             LEFT JOIN users nu ON nu.id = e.assigned_nurse_user_id
             LEFT JOIN users pu ON pu.id = e.assigned_provider_user_id
             LEFT JOIN oei_episode_location el ON el.episode_id = e.id AND el.end_datetime IS NULL
             LEFT JOIN oei_location l ON l.id = el.location_id
             WHERE e.facility_id = ? AND e.status = 'ACTIVE'
             ORDER BY e.start_datetime DESC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }

    /**
     * Return users eligible to be assigned.
     * Uses only columns confirmed present in this OpenEMR schema:
     *   id, fname, lname, active, authorized, npi
     * Nurses  = authorized=0 (non-providers — RNs, techs, aides)
     * Providers = authorized=1 OR npi not empty (physicians, APPs)
     *
     * @return array{nurses:array<int,array<string,mixed>>,providers:array<int,array<string,mixed>>}
     */
    public function availableStaff(): array
    {
        if (!function_exists('sqlStatement')) return ['nurses' => [], 'providers' => []];

        $nurses = [];
        $res = sqlStatement(
            "SELECT id, CONCAT(fname, ' ', lname) AS name
             FROM users
             WHERE active = 1 AND authorized = 0
             ORDER BY lname ASC, fname ASC
             LIMIT 200"
        );
        while ($row = sqlFetchArray($res)) {
            $nurses[(int)$row['id']] = ['id' => (int)$row['id'], 'name' => trim((string)$row['name'])];
        }

        $providers = [];
        $res = sqlStatement(
            "SELECT id, CONCAT(fname, ' ', lname) AS name
             FROM users
             WHERE active = 1 AND (authorized = 1 OR (npi IS NOT NULL AND npi != ''))
             ORDER BY lname ASC, fname ASC
             LIMIT 200"
        );
        while ($row = sqlFetchArray($res)) {
            $providers[(int)$row['id']] = ['id' => (int)$row['id'], 'name' => trim((string)$row['name'])];
        }

        return ['nurses' => $nurses, 'providers' => $providers];
    }
}



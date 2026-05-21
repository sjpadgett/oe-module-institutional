<?php

/**
 * src/Submodule/Assignment/Repository/AssignmentRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Assignment\Repository;

use OpenEMR\Modules\Institutional\Core\Repository\UserRepository;

/**
 * AssignmentRepository
 *
 * Manages nurse and provider assignments stored on oei_episode.
 */
final class AssignmentRepository
{
    private UserRepository $users;

    public function __construct(?UserRepository $users = null)
    {
        $this->users = $users ?? new UserRepository();
    }

    /**
     * Assign or clear nurse and/or provider for an episode.
     * Pass null to clear; omit key to leave unchanged.
     *
     * @param array{nurse?:int|null,provider?:int|null} $fields
     */
    public function assign(int $episodeId, array $fields): void
    {
        if (!function_exists('sqlStatement') || empty($fields)) {
            return;
        }

        $sets = [];
        $params = [];

        if (array_key_exists('nurse', $fields)) {
            $sets[] = 'assigned_nurse_user_id = ?';
            $params[] = $fields['nurse'] ? (int)$fields['nurse'] : null;
        }
        if (array_key_exists('provider', $fields)) {
            $sets[] = 'assigned_provider_user_id = ?';
            $params[] = $fields['provider'] ? (int)$fields['provider'] : null;
        }

        if (empty($sets)) {
            return;
        }

        $params[] = $episodeId;
        sqlStatement(
            "UPDATE oei_episode SET " . implode(', ', $sets) . " WHERE id = ?",
            $params
        );
    }

    /**
     * Return current assignments for one episode.
     *
     * @return array{nurse_id:int|null,provider_id:int|null}
     */
    public function getForEpisode(int $episodeId): array
    {
        if (!function_exists('sqlQuery')) {
            return ['nurse_id' => null, 'provider_id' => null];
        }
        $row = sqlQuery(
            "SELECT assigned_nurse_user_id, assigned_provider_user_id
             FROM oei_episode WHERE id = ? LIMIT 1",
            [$episodeId]
        );
        return [
            'nurse_id' => $row ? ((int)($row['assigned_nurse_user_id'] ?? 0) ?: null) : null,
            'provider_id' => $row ? ((int)($row['assigned_provider_user_id'] ?? 0) ?: null) : null,
        ];
    }

    /**
     * Return all active episodes with their current assignments and staff names.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listWithAssignments(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT
                e.id, e.pid, e.chief_complaint, e.acuity_esi, e.start_datetime,
                e.assigned_nurse_user_id    AS nurse_id,
                e.assigned_provider_user_id AS provider_id,
                CONCAT(COALESCE(nu.fname,''), ' ', COALESCE(nu.lname,'')) AS nurse_name,
                CONCAT(COALESCE(pu.fname,''), ' ', COALESCE(pu.lname,'')) AS provider_name,
                l.name AS location_name,
                (SELECT sh.status_code FROM oei_episode_status_history sh
                 WHERE sh.episode_id = e.id ORDER BY sh.id DESC LIMIT 1) AS workflow_status
             FROM oei_episode e
             LEFT JOIN users nu ON nu.id = e.assigned_nurse_user_id
                                AND nu.active = 1
                                AND nu.username IS NOT NULL
                                AND nu.fname IS NOT NULL
             LEFT JOIN users pu ON pu.id = e.assigned_provider_user_id
                                AND pu.active = 1
                                AND pu.username IS NOT NULL
                                AND pu.fname IS NOT NULL
             LEFT JOIN oei_episode_location el ON el.episode_id = e.id AND el.end_datetime IS NULL
             LEFT JOIN oei_location l ON l.id = el.location_id
             WHERE e.facility_id = ? AND e.status = 'ACTIVE'
             ORDER BY e.start_datetime DESC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return nurses and providers available for assignment.
     * Delegates to UserRepository which applies the standard active/username/fname filter.
     *
     * @return array{nurses:array<int,array{id:int,name:string}>, providers:array<int,array{id:int,name:string}>}
     */
    public function availableStaff(): array
    {
        return $this->users->fetchStaff();
    }
}




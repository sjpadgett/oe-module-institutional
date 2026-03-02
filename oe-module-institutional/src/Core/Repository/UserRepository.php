<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Core\Repository;

/**
 * UserRepository
 *
 * Single source of truth for fetching OpenEMR users across all submodules.
 *
 * Standard filter applied to every query:
 *   active = 1
 *   AND username IS NOT NULL
 *   AND fname IS NOT NULL
 *
 * This excludes:
 *   - Deactivated accounts
 *   - System / service accounts (no username)
 *   - Incomplete records (no first name — would render as blank in dropdowns)
 *
 * abook_type notes:
 *   Internal login users (nurses, providers, admins) have abook_type = ''.
 *   Non-empty abook_type values (ord_lab, spe, vendor, etc.) are external
 *   address book contacts — never staff. All staff queries therefore
 *   do NOT filter on abook_type; they use the authorized flag instead.
 *
 * Provider  = authorized = 1  (physicians, APPs, mid-levels)
 * Nurse     = authorized = 0  AND username IS NOT NULL AND fname IS NOT NULL
 *             (RNs, techs, aides, care coordinators)
 */
final class UserRepository
{
    /**
     * Standard WHERE fragment and base params used by all queries.
     * Callers append additional conditions as needed.
     */
    private const BASE_WHERE = "active = 1 AND username IS NOT NULL AND fname IS NOT NULL";

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Return all active, named staff users ordered by last name, first name.
     *
     * @return array<int,array{id:int,name:string,authorized:int}>
     */
    public function fetchAll(): array
    {
        return $this->query(
            "SELECT id, fname, lname, authorized
             FROM users
             WHERE " . self::BASE_WHERE . "
             ORDER BY lname ASC, fname ASC
             LIMIT 500"
        );
    }

    /**
     * Return providers only (authorized = 1).
     *
     * @return array<int,array{id:int,name:string,authorized:int}>
     */
    public function fetchProviders(): array
    {
        return $this->query(
            "SELECT id, fname, lname, authorized
             FROM users
             WHERE " . self::BASE_WHERE . "
               AND authorized = 1
             ORDER BY lname ASC, fname ASC
             LIMIT 300"
        );
    }

    /**
     * Return non-provider clinical staff (nurses, techs, aides).
     * authorized = 0 excludes physicians and APPs.
     *
     * @return array<int,array{id:int,name:string,authorized:int}>
     */
    public function fetchNurses(): array
    {
        return $this->query(
            "SELECT id, fname, lname, authorized
             FROM users
             WHERE " . self::BASE_WHERE . "
               AND authorized = 1
             ORDER BY lname ASC, fname ASC
             LIMIT 300"
        );
    }

    /**
     * Resolve display names for a set of known user IDs.
     * Used when episode rows already contain user IDs and you just need names.
     *
     * @param  int[]  $ids
     * @return array<int,string>  id => "First Last"
     */
    public function namesByIds(array $ids): array
    {
        if (empty($ids) || !function_exists('sqlStatement')) {
            return [];
        }

        $ids          = array_values(array_unique(array_map('intval', $ids)));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $res   = sqlStatement(
            "SELECT id, fname, lname
             FROM users
             WHERE id IN ({$placeholders})
               AND " . self::BASE_WHERE,
            $ids
        );

        $names = [];
        while ($row = sqlFetchArray($res)) {
            $names[(int)$row['id']] = trim((string)$row['fname'] . ' ' . (string)$row['lname']);
        }
        return $names;
    }

    /**
     * Return nurses and providers split into two lists.
     * Convenience wrapper used by AssignmentController.
     *
     * @return array{nurses:array<int,array{id:int,name:string}>, providers:array<int,array{id:int,name:string}>}
     */
    public function fetchStaff(): array
    {
        return [
            'nurses'    => $this->fetchNurses(),
            'providers' => $this->fetchProviders(),
        ];
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /**
     * Execute a query and return rows as id-keyed arrays with a computed 'name'.
     *
     * @return array<int,array{id:int,name:string,authorized:int}>
     */
    private function query(string $sql, array $params = []): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res  = sqlStatement($sql, $params);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $id          = (int)$row['id'];
            $rows[$id]   = [
                'id'         => $id,
                'name'       => trim((string)$row['fname'] . ' ' . (string)$row['lname']),
                'authorized' => (int)($row['authorized'] ?? 0),
            ];
        }
        return $rows;
    }
}

<?php

/**
 * src/Submodule/BedMgmt/Repository/LocationRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository;

final class LocationRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listActive(int $facilityId, ?string $unitName = null): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT id, code, name, location_type, unit_name, sort_order, notes
                FROM oei_location
                WHERE facility_id = ? AND is_active = 1";
        $params = [$facilityId];
        if ($unitName !== null && $unitName !== '') {
            $sql .= " AND COALESCE(unit_name, '') = ?";
            $params[] = $unitName;
        }
        $sql .= " ORDER BY COALESCE(unit_name, ''), sort_order ASC, name ASC";
        $res = sqlStatement($sql, $params);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return list<string> */
    public function listUnits(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT DISTINCT TRIM(unit_name) AS unit_name
             FROM oei_location
             WHERE facility_id = ? AND is_active = 1 AND COALESCE(TRIM(unit_name), '') <> ''
             ORDER BY unit_name ASC",
            [$facilityId]
        );
        $units = [];
        while ($row = sqlFetchArray($res)) {
            $units[] = (string)$row['unit_name'];
        }
        return $units;
    }

    public function upsert(int $facilityId, string $code, string $name, string $type, ?string $unit, int $sort, int $active, ?string $notes): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "INSERT INTO oei_location (facility_id, code, name, location_type, unit_name, sort_order, is_active, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               location_type = VALUES(location_type),
               unit_name = VALUES(unit_name),
               sort_order = VALUES(sort_order),
               is_active = VALUES(is_active),
               notes = VALUES(notes)",
            [$facilityId, $code, $name, strtoupper($type), $unit, $sort, $active, $notes]
        );
    }
}




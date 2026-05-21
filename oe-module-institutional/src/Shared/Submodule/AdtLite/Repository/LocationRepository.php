<?php

/**
 * src/Shared/Submodule/AdtLite/Repository/LocationRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Repository;

/**
 * Lightweight location repo for the ADT-lite locations.php UI.
 * Operates on oei_location.  Columns used: id, name, location_type (type),
 * status, is_active (active), facility_id.
 */
final class LocationRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listAll(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, name, location_type AS type, COALESCE(status,'AVAILABLE') AS status, is_active AS active
             FROM oei_location
             WHERE facility_id = ?
             ORDER BY sort_order ASC, name ASC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function listActive(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, code, name, location_type, unit_name, sort_order, notes
             FROM oei_location
             WHERE facility_id = ? AND is_active = 1
             ORDER BY sort_order ASC, name ASC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    public function create(int $facilityId, string $name, string $type, string $status): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $code = $this->generateCode($name);
        sqlStatement(
            "INSERT INTO oei_location (facility_id, code, name, location_type, status, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, 1, 0)
             ON DUPLICATE KEY UPDATE name = VALUES(name), location_type = VALUES(location_type), status = VALUES(status)",
            [$facilityId, $code, $name, strtoupper($type), strtoupper($status)]
        );
    }

    public function update(int $id, int $facilityId, string $name, string $type, string $status, int $active): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement(
            "UPDATE oei_location
             SET name = ?, location_type = ?, status = ?, is_active = ?
             WHERE id = ? AND facility_id = ?",
            [$name, strtoupper($type), strtoupper($status), $active, $id, $facilityId]
        );
    }

    private function generateCode(string $name): string
    {
        $slug = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $name) ?? '');
        $slug = substr($slug ?: 'LOC', 0, 8);
        return $slug . sprintf('%03d', random_int(0, 999));
    }

    /**
     * Find a single location by id.
     * Used by AdtService for HL7 A02 notification payload.
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery(
            "SELECT id, code, name, location_type, unit_name, sort_order, notes
             FROM oei_location WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

}




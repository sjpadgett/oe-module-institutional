<?php

/**
 * src/Operations/Submodule/FacilityDirectory/Repository/FacilityDirectoryRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Operations\Submodule\FacilityDirectory\Repository;

final class FacilityDirectoryRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listActive(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT id, name, service_type, phone, fax, email, address, hours, notes, sort_order
                FROM oei_facility_directory
                WHERE facility_id = ? AND is_active = 1
                ORDER BY sort_order ASC, name ASC";
        $res = sqlStatement($sql, [$facilityId]);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function get(int $facilityId, int $id): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery("SELECT * FROM oei_facility_directory WHERE facility_id = ? AND id = ? LIMIT 1", [$facilityId, $id]);
        return $row ?: null;
    }

    public function upsert(int $facilityId, ?int $id, string $name, string $serviceType, ?string $phone, ?string $fax, ?string $email, ?string $address, ?string $hours, ?string $notes, int $active, int $sort): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        if ($id) {
            sqlStatement(
                "UPDATE oei_facility_directory
                 SET name=?, service_type=?, phone=?, fax=?, email=?, address=?, hours=?, notes=?, is_active=?, sort_order=?
                 WHERE facility_id=? AND id=?",
                [$name, strtoupper($serviceType), $phone, $fax, $email, $address, $hours, $notes, $active, $sort, $facilityId, $id]
            );
            return;
        }
        sqlStatement(
            "INSERT INTO oei_facility_directory (facility_id, name, service_type, phone, fax, email, address, hours, notes, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [$facilityId, $name, strtoupper($serviceType), $phone, $fax, $email, $address, $hours, $notes, $active, $sort]
        );
    }
}




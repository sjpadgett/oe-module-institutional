<?php

namespace OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository;

final class LocationRepository
{
    /** @return array<int,array<string,mixed>> */
    public function listActive(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT id, code, name, location_type, unit_name, sort_order, notes
                FROM oei_location
                WHERE facility_id = ? AND is_active = 1
                ORDER BY sort_order ASC, name ASC";
        $res = sqlStatement($sql, [$facilityId]);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
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

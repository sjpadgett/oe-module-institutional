<?php

/**
 * src/Submodule/Intake/Repository/PatientRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Intake\Repository;

final class PatientRepository
{
    /** @return array<int,array<string,mixed>> */
    public function search(string $q, int $limit = 20): array
    {
        if (!function_exists('sqlStatement') || $q === '') return [];

        if (ctype_digit($q)) {
            $res = sqlStatement(
                "SELECT pid, fname, lname, DOB, phone_cell, phone_home FROM patient_data WHERE pid=? LIMIT ?",
                [(int)$q, $limit]
            );
        } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $q)) {
            $res = sqlStatement(
                "SELECT pid, fname, lname, DOB, phone_cell, phone_home FROM patient_data WHERE DOB=? LIMIT ?",
                [$q, $limit]
            );
        } else {
            $like = '%' . $q . '%';
            $res = sqlStatement(
                "SELECT pid, fname, lname, DOB, phone_cell, phone_home FROM patient_data
                 WHERE CONCAT_WS(' ', fname, lname) LIKE ? OR phone_cell LIKE ? OR phone_home LIKE ?
                 ORDER BY lname ASC LIMIT ?",
                [$like, $like, $like, $limit]
            );
        }

        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }

    /**
     * Fetch a single patient row by pid.
     * @return array<string,mixed>|null
     */
    public function getById(int $pid): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery(
            "SELECT pid, fname, lname, DOB, phone_cell, phone_home
             FROM patient_data WHERE pid = ? LIMIT 1",
            [$pid]
        );
        return $row ?: null;
    }

    /**
     * Batch-fetch display names for a set of pids.
     * Returns [pid => "Last, First"] for each found patient.
     * @param  int[]  $pids
     * @return array<int,string>
     */
    public function namesByIds(array $pids): array
    {
        if (empty($pids) || !function_exists('sqlStatement')) return [];
        $pids = array_values(array_unique(array_filter(array_map('intval', $pids))));
        $ph   = implode(',', array_fill(0, count($pids), '?'));
        $res  = sqlStatement(
            "SELECT pid, fname, lname FROM patient_data WHERE pid IN ({$ph})",
            $pids
        );
        $names = [];
        while ($row = sqlFetchArray($res)) {
            $names[(int)$row['pid']] = trim((string)$row['lname'] . ', ' . (string)$row['fname']);
        }
        return $names;
    }

}




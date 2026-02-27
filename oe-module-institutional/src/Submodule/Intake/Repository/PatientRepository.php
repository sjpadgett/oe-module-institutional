<?php
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
}



<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service;

/**
 * AllergyService
 *
 * Checks active medication orders against the patient's documented allergies
 * stored in OpenEMR's native `lists` table (type = 'allergy').
 *
 * This is a WARNING service only — it surfaces potential matches for the
 * nurse to review. It does NOT block administration. Clinical judgment
 * always prevails.
 *
 * Match strategy: case-insensitive substring match in both directions.
 *   - drug name contains allergy title  (e.g. "Heparin Sodium" matches "Heparin")
 *   - allergy title contains drug name  (e.g. "Penicillin" matches "Penicillin VK")
 *
 * OpenEMR `lists` columns used:
 *   type, pid, title (allergen), reaction, severity_al, enddate (null = active)
 */
final class AllergyService
{
    /**
     * Check a set of drug names against a patient's allergy list.
     *
     * @param  int      $pid       Patient ID
     * @param  string[] $drugNames Active drug order names
     * @return array<int,array{drug:string,allergen:string,reaction:string,severity:string}>
     *         One entry per drug+allergen match found.
     */
    public function checkDrugAllergies(int $pid, array $drugNames): array
    {
        if (!function_exists('sqlStatement') || $pid <= 0 || empty($drugNames)) {
            return [];
        }

        $allergies = $this->loadAllergies($pid);
        if (empty($allergies)) {
            return [];
        }

        $warnings = [];

        foreach ($drugNames as $drugName) {
            $drugLower = strtolower(trim($drugName));
            if ($drugLower === '') {
                continue;
            }

            foreach ($allergies as $allergy) {
                $allergenLower = strtolower(trim((string)($allergy['title'] ?? '')));
                if ($allergenLower === '') {
                    continue;
                }

                $matched = str_contains($drugLower, $allergenLower)
                    || str_contains($allergenLower, $drugLower);

                if ($matched) {
                    $warnings[] = [
                        'drug'     => $drugName,
                        'allergen' => (string)($allergy['title'] ?? ''),
                        'reaction' => (string)($allergy['reaction'] ?? ''),
                        'severity' => (string)($allergy['severity_al'] ?? ''),
                    ];
                    break; // one warning per drug is enough
                }
            }
        }

        return $warnings;
    }

    /**
     * Load active allergies for a patient from OpenEMR's lists table.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadAllergies(int $pid): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        // enddate IS NULL or in the future = still active
        $res = sqlStatement(
            "SELECT title, reaction, severity_al
             FROM lists
             WHERE pid = ?
               AND type = 'allergy'
               AND (enddate IS NULL OR enddate >= CURDATE())
             ORDER BY title ASC",
            [$pid]
        );

        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

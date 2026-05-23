<?php

/**
 * src/Submodule/Mar/Repository/MarOrderRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Mar\Repository;

/**
 * Medication Order repository.
 * Operates on oei_mar_order.
 */
final class MarOrderRepository
{
    /** @var array<string,string> */
    private const DEFAULT_UNIT_LABELS = [
        'mg' => 'mg',
        'mcg' => 'mcg',
        'g' => 'g',
        'kg' => 'kg',
        'ml' => 'mL',
        'l' => 'L',
        'units' => 'units',
        'mEq' => 'mEq',
        'IU' => 'IU',
        '%' => '%',
        'tablet' => 'tablet',
        'capsule' => 'capsule',
        'drop' => 'drop',
        'patch' => 'patch',
        'spray' => 'spray',
        'puff' => 'puff',
        'suppository' => 'suppository',
    ];

    /** @var array<string,string> */
    private const DEFAULT_ROUTE_LABELS = [
        'PO' => 'PO — Oral',
        'IV' => 'IV — Intravenous',
        'IM' => 'IM — Intramuscular',
        'SC' => 'SC — Subcutaneous',
        'SL' => 'SL — Sublingual',
        'PR' => 'PR — Rectal',
        'TOP' => 'TOP — Topical / Transdermal',
        'INH' => 'INH — Inhalation',
        'IN' => 'IN — Intranasal',
        'Otic' => 'Otic — Ear',
        'Optic' => 'Ophthalmic — Eye',
        'IP' => 'IP — Intraperitoneal',
    ];

    /** @var array<string,string> */
    private const DEFAULT_FREQUENCY_LABELS = [
        'ONCE' => 'Once',
        'QD' => 'QD — Daily',
        'BID' => 'BID — Twice daily',
        'TID' => 'TID — Three times daily',
        'QID' => 'QID — Four times daily',
        'Q4H' => 'Q4H — Every 4 hours',
        'Q6H' => 'Q6H — Every 6 hours',
        'Q8H' => 'Q8H — Every 8 hours',
        'Q12H' => 'Q12H — Every 12 hours',
        'HS' => 'HS — At bedtime',
        'PRN' => 'PRN — As needed',
    ];
    // ------------------------------------------------------------------ reads

    /**
     * Return all orders for a single episode, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, facility_id,
                    drug_name, dose, unit, route, frequency, is_prn, is_stat, is_high_alert,
                    status, ordered_datetime, discontinued_datetime,
                    ordered_by_user_id, discontinued_by_user_id,
                    rx_id, instructions
             FROM oei_mar_order
             WHERE episode_id = ?
             ORDER BY ordered_datetime DESC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Return ACTIVE orders for a single episode (used for the MAR grid).
     *
     * @return array<int,array<string,mixed>>
     */
    public function listActiveByEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, facility_id,
                    drug_name, dose, unit, route, frequency, is_prn, is_stat, is_high_alert,
                    status, ordered_datetime, instructions
             FROM oei_mar_order
             WHERE episode_id = ? AND status = 'ACTIVE'
             ORDER BY drug_name ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getById(int $id): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_mar_order WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    // ----------------------------------------------------------------- writes

    /**
     * Create a new medication order.  Returns the new row id.
     */
    public function create(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        string $dose,
        string $unit,
        string $route,
        string $frequency,
        bool $isPrn,
        string $orderedDatetime,
        ?int $orderedByUserId,
        ?int $rxId = null,
        ?string $instructions = null,
        bool $isStat = false,
        bool $isHighAlert = false
    ): int {
        if (!function_exists('sqlStatement')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_mar_order
               (episode_id, pid, facility_id, drug_name, dose, unit, route,
                frequency, is_prn, is_stat, is_high_alert, status, ordered_datetime,
                ordered_by_user_id, rx_id, instructions,
                created_datetime, updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,'ACTIVE',?,?,?,?,?,?)",
            [
                $episodeId, $pid, $facilityId, $drugName, $dose, $unit, $route,
                $frequency, (int)$isPrn, (int)$isStat, (int)$isHighAlert, $orderedDatetime,
                $orderedByUserId, $rxId, $instructions, $now, $now,
            ]
        );
        return (int)($GLOBALS['lastidado'] > 0 ? $GLOBALS['lastidado'] : $GLOBALS['adodb']['db']->Insert_ID());
    }

    /**
     * Discontinue (soft-delete) a medication order.
     */
    public function discontinue(int $orderId, ?int $userId): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_mar_order
             SET status = 'DISCONTINUED',
                 discontinued_datetime = ?,
                 discontinued_by_user_id = ?,
                 updated_datetime = ?
             WHERE id = ?",
            [$now, $userId, $now, $orderId]
        );
    }

    // ─────────────────────────────────────── Prescription import (Level 1)

    private function mapInterval(mixed $interval): string
    {
        return $this->normalizeFrequency((string)$interval) ?: 'QD';
    }

    private function mapUnit(mixed $unit): string
    {
        return $this->normalizeUnit((string)$unit) ?: trim((string)$unit);
    }

    private function mapRoute(mixed $route): string
    {
        return $this->normalizeRoute((string)$route) ?: trim((string)$route);
    }

    /**
     * @return array{units:list<array{value:string,label:string}>,routes:list<array{value:string,label:string}>,frequencies:list<array{value:string,label:string}>}
     */
    public function getOrderVocabulary(): array
    {
        return [
            'units' => $this->buildVocabulary(self::DEFAULT_UNIT_LABELS, 'drug_units'),
            'routes' => $this->buildVocabulary(self::DEFAULT_ROUTE_LABELS, 'drug_route'),
            'frequencies' => $this->buildVocabulary(self::DEFAULT_FREQUENCY_LABELS, 'drug_interval'),
        ];
    }

    public function normalizeUnit(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $map = [
            '1' => 'mg', '2' => 'mcg', '3' => 'ml', '4' => 'units',
            '5' => 'mg/ml', '6' => 'mEq', '7' => 'g', '8' => '%',
            '9' => 'IU', '10' => 'mcg/hr',
            'milligram' => 'mg', 'milligrams' => 'mg',
            'microgram' => 'mcg', 'micrograms' => 'mcg',
            'milliliter' => 'ml', 'milliliters' => 'ml', 'millilitre' => 'ml', 'millilitres' => 'ml',
            'unit' => 'units', 'iu' => 'IU',
        ];
        $lower = strtolower($value);
        return $map[$lower] ?? $map[$value] ?? $value;
    }

    public function normalizeRoute(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $map = [
            '1' => 'PO', '2' => 'SL', '3' => 'PR', '4' => 'TOP',
            '5' => 'Otic', '6' => 'Optic', '7' => 'IM', '8' => 'IV',
            '9' => 'INH', '10' => 'SC', '11' => 'IN', '12' => 'IP',
            'oral' => 'PO', 'po' => 'PO', 'bymouth' => 'PO', 'by mouth' => 'PO',
            'sublingual' => 'SL', 'sl' => 'SL', 'under tongue' => 'SL',
            'rectal' => 'PR', 'pr' => 'PR',
            'topical' => 'TOP', 'top' => 'TOP',
            'intramuscular' => 'IM', 'im' => 'IM',
            'intravenous' => 'IV', 'iv' => 'IV', 'i.v.' => 'IV',
            'subcutaneous' => 'SC', 'sq' => 'SC', 'sc' => 'SC', 's.c.' => 'SC', 'subq' => 'SC',
            'inhalation' => 'INH', 'inh' => 'INH',
            'intranasal' => 'IN', 'nasal' => 'IN', 'in' => 'IN',
            'inhaled'     => 'INH', 'inhaler' => 'INH', 'nebulizer' => 'INH',
            'transdermal' => 'TOP', 'patch' => 'TOP', 'td' => 'TOP',
            'ear' => 'Otic', 'otic' => 'Otic',
            'eye' => 'Optic', 'ophthalmic' => 'Optic', 'optic' => 'Optic',
            'intraperitoneal' => 'IP', 'ip' => 'IP',
        ];
        $lower = strtolower($value);
        return $map[$lower] ?? $map[$value] ?? strtoupper($value);
    }

    public function normalizeFrequency(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $numericMap = [
            '0' => 'QD', '1' => 'BID', '2' => 'TID', '3' => 'QID',
            '4' => 'Q4H', '5' => 'Q6H', '6' => 'Q8H', '7' => 'Q12H',
            '8' => 'PRN', '9' => 'QD',
        ];
        $direct = strtoupper($value);
        if (isset(self::DEFAULT_FREQUENCY_LABELS[$direct])) {
            return $direct;
        }
        $lower = strtolower($value);
        if (isset($numericMap[$lower])) {
            return $numericMap[$lower];
        }
        $patterns = [
            'once' => 'ONCE', 'daily' => 'QD', 'every day' => 'QD', 'qd' => 'QD',
            'twice daily' => 'BID', 'two times daily' => 'BID', 'bid' => 'BID',
            'three times' => 'TID', 'tid' => 'TID',
            'four times' => 'QID', 'qid' => 'QID',
            'every 4 hours' => 'Q4H', 'q4h' => 'Q4H',
            'every 6 hours' => 'Q6H', 'q6h' => 'Q6H',
            'every 8 hours' => 'Q8H', 'q8h' => 'Q8H',
            'every 12 hours' => 'Q12H', 'q12h' => 'Q12H',
            'bedtime' => 'HS', 'hs' => 'HS',
            'as needed' => 'PRN', 'prn' => 'PRN',
        ];
        foreach ($patterns as $pattern => $freq) {
            if (str_contains($lower, $pattern)) {
                return $freq;
            }
        }
        return $direct;
    }

    /** @return list<array{value:string,label:string}> */
    public function listDrugLookupOptions(string $search = '', int $limit = 50): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $search = trim($search);
        $limit = max(1, min(200, $limit));
        $res = sqlStatement(
            "SELECT drug_id, name, size, unit, route, drug_code
               FROM drugs
              WHERE active = 1
              ORDER BY name ASC"
        );

        $scored = [];
        $seen = [];
        $add = function (string $value, string $label, int $score) use (&$scored, &$seen): void {
            $value = trim($value);
            $label = trim($label);
            if ($value === '') {
                return;
            }
            $key = strtolower($value);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $scored[] = ['value' => $value, 'label' => ($label !== '' ? $label : $value), '_score' => $score];
        };

        while ($row = sqlFetchArray($res)) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $fullDisplay = $name;
            $size = trim((string)($row['size'] ?? ''));
            $unit = $this->normalizeUnit((string)($row['unit'] ?? ''));
            $route = $this->normalizeRoute((string)($row['route'] ?? ''));
            if ($size !== '') {
                $fullDisplay .= ' ' . $size;
            }
            if ($unit !== '') {
                $fullDisplay .= ' ' . $unit;
            }
            if ($route !== '') {
                $fullDisplay .= ' ' . $route;
            }
            $fullDisplay = trim(preg_replace('/\s+/', ' ', $fullDisplay) ?? $fullDisplay);
            $common = $this->normalizeImportedDrugName($name);

            if ($search !== '') {
                $needle = strtolower($search);
                $haystacks = [strtolower($name), strtolower($fullDisplay), strtolower($common)];
                $matched = false;
                foreach ($haystacks as $hay) {
                    if ($hay !== '' && str_contains($hay, $needle)) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    continue;
                }
                $scoreBase = str_starts_with(strtolower($common), $needle) ? 0 : (str_starts_with(strtolower($name), $needle) ? 1 : 2);
            } else {
                $scoreBase = 1;
            }

            $commonLabel = $common;
            if ($common !== '' && strcasecmp($common, $fullDisplay) !== 0) {
                $commonLabel .= ' — ' . $fullDisplay;
            }
            if ($common !== '') {
                $add($common, $commonLabel, $scoreBase);
            }
            $add($fullDisplay, $fullDisplay, $scoreBase + 10);
        }

        usort($scored, static function (array $a, array $b): int {
            $scoreCmp = ($a['_score'] ?? 0) <=> ($b['_score'] ?? 0);
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            return strcasecmp((string)$a['value'], (string)$b['value']);
        });

        $out = [];
        foreach ($scored as $row) {
            $out[] = ['value' => (string)$row['value'], 'label' => (string)$row['label']];
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }
    public function normalizeImportedDrugName(string $drugName): string
    {
        $drugName = trim($drugName);
        if ($drugName === '') {
            return '';
        }
        $clean = preg_replace('/\s+/', ' ', $drugName) ?? $drugName;
        $clean = trim($clean, " \t\n\r\0\x0B,;:/-()[]");

        $patterns = [
            '/\b\d+(?:\.\d+)?\s*(mg|mcg|g|kg|ml|mL|units?|iu|meq|%)\b/i',
            '/\b(oral|intravenous|intramuscular|subcutaneous|topical|ophthalmic|otic|nasal|inhalation|sublingual|rectal)\b/i',
            '/\b(tablet|tab|capsule|cap|solution|suspension|injectable|injection|cream|ointment|patch|spray|syrup|elixir|gel|powder|packet|suppository|chewable|liquid)\b/i',
            '/\b(extended release|delayed release|sustained release|immediate release|er|xr|sr|dr)\b/i',
        ];
        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, '', $clean);
            if ($candidate !== null) {
                $clean = trim(preg_replace('/\s+/', ' ', $candidate) ?? $candidate, " \t\n\r\0\x0B,;:/-()[]");
            }
        }

        return $clean !== '' ? $clean : $drugName;
    }
    /**
     * Return active OpenEMR prescriptions for a patient.
     * @return array<int,array<string,mixed>>
     */
    public function listActivePrescriptions(int $pid): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT p.id, p.drug, p.size, p.unit, p.route, p.`interval`,
                    p.note, p.provider_id, p.date_added,
                    CONCAT(u.fname, ' ', u.lname) AS provider_name
             FROM prescriptions p
             LEFT JOIN users u ON u.id = p.provider_id
             WHERE p.patient_id = ? AND p.active = 1
             ORDER BY p.drug ASC",
            [$pid]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $row['_freq']   = $this->normalizeFrequency((string)($row['interval'] ?? ''));
            $row['_unit']   = $this->normalizeUnit((string)($row['unit'] ?? ''));
            $row['_route']  = $this->normalizeRoute((string)($row['route'] ?? ''));
            $row['_is_prn'] = ($row['_freq'] === 'PRN');
            $row['_display_drug'] = $this->normalizeImportedDrugName((string)($row['drug'] ?? ''));
            $row['_sig'] = (string)($row['note'] ?? '');
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,true> */
    public function listImportedRxIds(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT rx_id FROM oei_mar_order WHERE episode_id = ? AND rx_id IS NOT NULL",
            [$episodeId]
        );
        $ids = [];
        while ($row = sqlFetchArray($res)) { $ids[(int)$row['rx_id']] = true; }
        return $ids;
    }

    public function updateOrder(
        int $orderId,
        string $drugName,
        string $dose,
        string $unit,
        string $route,
        string $frequency,
        bool $isPrn,
        ?string $instructions,
        bool $isHighAlert,
        bool $isStat = false
    ): bool {
        if (!function_exists('sqlStatement')) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "UPDATE oei_mar_order
             SET drug_name = ?, dose = ?, unit = ?, route = ?, frequency = ?,
                 is_prn = ?, is_high_alert = ?, is_stat = ?, instructions = ?, updated_datetime = ?
             WHERE id = ?",
            [
                trim($drugName), trim($dose), $this->normalizeUnit($unit), $this->normalizeRoute($route),
                $this->normalizeFrequency($frequency), (int)$isPrn, (int)$isHighAlert, (int)$isStat,
                $instructions !== null ? trim($instructions) : null, $now, $orderId,
            ]
        );
        return true;
    }

    /** @param array<string,mixed> $rx Row from listActivePrescriptions() */
    public function importFromPrescription(int $episodeId, int $pid, int $facilityId, array $rx, ?int $userId, bool $isHighAlert = false): int
    {
        $rxId = (int)($rx['id'] ?? 0);
        if ($rxId <= 0) return 0;
        if (function_exists('sqlQuery')) {
            $existing = sqlQuery(
                "SELECT id FROM oei_mar_order WHERE episode_id = ? AND rx_id = ? AND status = 'ACTIVE' LIMIT 1",
                [$episodeId, $rxId]
            );
            if (!empty($existing['id'])) return (int)$existing['id'];
        }
        $drugName  = trim((string)($rx['_display_drug'] ?? $rx['drug'] ?? ''));
        $dose      = trim((string)($rx['_dose'] ?? $rx['size'] ?? ''));
        $frequency = $this->normalizeFrequency((string)($rx['_freq'] ?? $rx['interval'] ?? ''));
        $unit      = $this->normalizeUnit((string)($rx['_unit'] ?? $rx['unit'] ?? ''));
        $route     = $this->normalizeRoute((string)($rx['_route'] ?? $rx['route'] ?? ''));
        $sig       = trim((string)($rx['_sig'] ?? $rx['note'] ?? ''));
        return $this->create(
            $episodeId,
            $pid,
            $facilityId,
            $drugName !== '' ? $drugName : (string)($rx['drug'] ?? ''),
            $dose,
            $unit,
            $route,
            $frequency !== '' ? $frequency : 'QD',
            ($frequency === 'PRN'),
            date('Y-m-d H:i:s'),
            $userId,
            $rxId,
            $sig !== '' ? $sig : null,
            false,
            $isHighAlert
        );
    }

    /**
     * @param array<string,string> $defaults
     * @return list<array{value:string,label:string}>
     */
    private function buildVocabulary(array $defaults, string $listId): array
    {
        $options = [];
        $seen = [];
        $add = static function (string $value, string $label) use (&$options, &$seen): void {
            $value = trim($value);
            $label = trim($label);
            if ($value === '') {
                return;
            }
            $key = strtolower($value);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $options[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
        };
        foreach ($defaults as $value => $label) {
            $add($value, $label);
        }
        if (function_exists('sqlStatement')) {
            $res = sqlStatement(
                "SELECT option_id, title FROM list_options
                 WHERE list_id = ? AND activity = 1
                 ORDER BY seq, title",
                [$listId]
            );
            while ($row = sqlFetchArray($res)) {
                $value = (string)($row['option_id'] ?? '');
                $label = (string)($row['title'] ?? '');
                $add($value, $label !== '' ? $label : $value);
            }
        }
        return $options;
    }
}



















<?php

/**
 * src/Shared/Submodule/Observations/Repository/SharedObservationRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository;

/**
 * SharedObservationRepository
 *
 * Canonical read/write path for oei_observation across all tracks.
 *
 * Design principles:
 *   - One row per measurement per point in time.
 *   - oei_triage remains untouched — this is additive, not a replacement.
 *   - Flagging is computed at write time against oei_obs_type bounds so
 *     reads never need to re-evaluate alert state.
 *   - FHIR dedup uses fhir_id unique key — re-importing the same FHIR
 *     Observation silently skips (INSERT IGNORE).
 *   - All reads gracefully return [] / null when the table doesn't exist.
 */
final class SharedObservationRepository
{
    private static ?bool $tableExists    = null;
    private static ?bool $typeTableExists = null;

    // ── Table guards ──────────────────────────────────────────────────────

    private static function ready(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }
        if (!function_exists('sqlQuery')) {
            return self::$tableExists = false;
        }
        try {
            $r = sqlQuery(
                "SELECT 1 FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'oei_observation' LIMIT 1"
            );
            return self::$tableExists = !empty($r);
        } catch (\Throwable) {
            return self::$tableExists = false;
        }
    }

    private static function typeReady(): bool
    {
        if (self::$typeTableExists !== null) {
            return self::$typeTableExists;
        }
        if (!function_exists('sqlQuery')) {
            return self::$typeTableExists = false;
        }
        try {
            $r = sqlQuery(
                "SELECT 1 FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = 'oei_obs_type' LIMIT 1"
            );
            return self::$typeTableExists = !empty($r);
        } catch (\Throwable) {
            return self::$typeTableExists = false;
        }
    }

    // ── Type catalogue ────────────────────────────────────────────────────

    /**
     * All active observation types, sorted by sort_order.
     *
     * @return array<string, array<string, mixed>>  keyed by code
     */
    public function listTypes(bool $activeOnly = true): array
    {
        if (!self::typeReady()) {
            return [];
        }
        $sql = "SELECT code, display_name, loinc_code, category,
                       default_unit, value_type, alert_low, alert_high,
                       is_active, sort_order
                  FROM oei_obs_type";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY sort_order ASC, code ASC";

        try {
            $res  = sqlStatement($sql);
            $rows = [];
            while ($r = sqlFetchArray($res)) {
                $rows[(string)$r['code']] = $this->hydrateType($r);
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Single type by code.
     *
     * @return array<string, mixed>|null
     */
    public function getType(string $code): ?array
    {
        if (!self::typeReady() || $code === '') {
            return null;
        }
        try {
            $r = sqlQuery(
                "SELECT code, display_name, loinc_code, category,
                        default_unit, value_type, alert_low, alert_high,
                        is_active, sort_order
                   FROM oei_obs_type WHERE code = ? LIMIT 1",
                [$code]
            );
            return $r ? $this->hydrateType($r) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Writes ────────────────────────────────────────────────────────────

    /**
     * Insert a single observation. Returns new row id (0 on failure).
     *
     * is_flagged is computed automatically against oei_obs_type bounds.
     * Duplicate fhir_id rows are silently skipped (INSERT IGNORE).
     *
     * @param string      $obsTypeCode   Must exist in oei_obs_type
     * @param string      $observedAt    'Y-m-d H:i:s'
     * @param string      $sourceType    MANUAL | DEVICE | IMPORT | FHIR
     */
    public function record(
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $obsTypeCode,
        string  $observedAt,
        ?float  $valueNumeric,
        ?string $valueText,
        ?string $unit,
        string  $sourceType      = 'MANUAL',
        ?string $deviceId        = null,
        ?string $fhirId          = null,
        ?int    $userId          = null
    ): int {
        if (!self::ready() || !function_exists('sqlStatement')) {
            return 0;
        }

        $isFlagged = 0;
        if ($valueNumeric !== null) {
            $type = $this->getType($obsTypeCode);
            if ($type) {
                $lo = $type['alert_low'];
                $hi = $type['alert_high'];
                if (($lo !== null && $valueNumeric < $lo) ||
                    ($hi !== null && $valueNumeric > $hi)) {
                    $isFlagged = 1;
                }
            }
        }

        // Application-level FHIR dedup (UNIQUE KEY not valid on partitioned table in MySQL)
        if ($fhirId !== null && $fhirId !== '' && self::ready()) {
            try {
                $dup = sqlQuery("SELECT 1 FROM oei_observation WHERE fhir_id = ? LIMIT 1", [$fhirId]);
                if (!empty($dup)) { return 0; }
            } catch (\Throwable) { /* proceed */ }
        }

        try {
            sqlStatement(
                "INSERT INTO oei_observation
                    (episode_id, pid, facility_id, obs_type_code,
                     observed_datetime, value_numeric, value_text, unit,
                     source_type, device_id, fhir_id,
                     is_flagged, noted_by_user_id, created_datetime)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())",
                [
                    $episodeId, $pid, $facilityId, $obsTypeCode,
                    $observedAt, $valueNumeric, $valueText, $unit,
                    $sourceType, $deviceId, $fhirId,
                    $isFlagged, $userId,
                ]
            );
            $r = sqlQuery("SELECT LAST_INSERT_ID() AS id");
            return (int)($r['id'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Batch insert observations. Returns [processed, failed] counts.
     *
     * @param array<int, array<string, mixed>> $rows  Each row matches record() params as named keys
     * @return array{processed: int, failed: int}
     */
    public function recordBatch(array $rows, string $sourceType = 'IMPORT'): array
    {
        $processed = 0;
        $failed    = 0;
        foreach ($rows as $row) {
            $id = $this->record(
                episodeId:    (int)($row['episode_id']   ?? 0),
                pid:          (int)($row['pid']          ?? 0),
                facilityId:   (int)($row['facility_id']  ?? 0),
                obsTypeCode:  (string)($row['obs_type_code'] ?? ''),
                observedAt:   (string)($row['observed_at']   ?? date('Y-m-d H:i:s')),
                valueNumeric: isset($row['value_numeric']) ? (float)$row['value_numeric'] : null,
                valueText:    isset($row['value_text'])    ? (string)$row['value_text']   : null,
                unit:         isset($row['unit'])          ? (string)$row['unit']         : null,
                sourceType:   (string)($row['source_type'] ?? $sourceType),
                deviceId:     isset($row['device_id'])     ? (string)$row['device_id']   : null,
                fhirId:       isset($row['fhir_id'])        ? (string)$row['fhir_id']     : null,
                userId:       isset($row['user_id'])        ? (int)$row['user_id']        : null,
            );
            $id > 0 ? $processed++ : $failed++;
        }
        return ['processed' => $processed, 'failed' => $failed];
    }

    // ── Reads ─────────────────────────────────────────────────────────────

    /**
     * Recent observations for an episode — newest first.
     * Optionally filtered to specific type codes.
     *
     * @param  string[]   $typeCodes  Empty = all types
     * @return array<int, array<string, mixed>>
     */
    public function listForEpisode(
        int   $episodeId,
        int   $limit     = 20,
        array $typeCodes = []
    ): array {
        if (!self::ready()) {
            return [];
        }

        $where = "WHERE o.episode_id = ?";
        $params = [$episodeId];

        if ($typeCodes) {
            $ph    = implode(',', array_fill(0, count($typeCodes), '?'));
            $where .= " AND o.obs_type_code IN ({$ph})";
            $params = array_merge($params, $typeCodes);
        }

        try {
            $res = sqlStatement(
                "SELECT o.id, o.episode_id, o.pid, o.obs_type_code,
                        o.observed_datetime, o.value_numeric, o.value_text,
                        o.unit, o.source_type, o.device_id,
                        o.is_flagged, o.noted_by_user_id, o.created_datetime,
                        t.display_name, t.default_unit, t.loinc_code, t.category,
                        t.alert_low, t.alert_high
                   FROM oei_observation o
                   LEFT JOIN oei_obs_type t ON t.code = o.obs_type_code
                  {$where}
                  ORDER BY o.observed_datetime DESC, o.id DESC
                  LIMIT " . (int)$limit,
                $params
            );
            $rows = [];
            while ($r = sqlFetchArray($res)) {
                $rows[] = $this->hydrateRow($r);
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Latest reading per type for an episode — for profile page snapshots.
     * Returns array keyed by obs_type_code.
     *
     * @return array<string, array<string, mixed>>
     */
    public function latestPerType(int $episodeId): array
    {
        if (!self::ready()) {
            return [];
        }
        try {
            $res = sqlStatement(
                "SELECT o.obs_type_code,
                        o.value_numeric, o.value_text, o.unit,
                        o.observed_datetime, o.is_flagged,
                        t.display_name, t.default_unit, t.alert_low, t.alert_high
                   FROM oei_observation o
                   INNER JOIN (
                       SELECT obs_type_code, MAX(observed_datetime) AS latest_dt
                         FROM oei_observation
                        WHERE episode_id = ?
                        GROUP BY obs_type_code
                   ) latest ON latest.obs_type_code = o.obs_type_code
                            AND latest.latest_dt    = o.observed_datetime
                   LEFT JOIN oei_obs_type t ON t.code = o.obs_type_code
                  WHERE o.episode_id = ?",
                [$episodeId, $episodeId]
            );
            $map = [];
            while ($r = sqlFetchArray($res)) {
                $code      = (string)$r['obs_type_code'];
                $map[$code] = $this->hydrateRow($r);
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Trend data for a single type — oldest first for chart rendering.
     *
     * @return array<int, array{datetime: string, value: float|null}>
     */
    public function trend(int $episodeId, string $obsTypeCode, int $limit = 30): array
    {
        if (!self::ready()) {
            return [];
        }
        try {
            $res = sqlStatement(
                "SELECT observed_datetime, value_numeric
                   FROM oei_observation
                  WHERE episode_id = ? AND obs_type_code = ?
                    AND value_numeric IS NOT NULL
                  ORDER BY observed_datetime DESC
                  LIMIT " . (int)$limit,
                [$episodeId, $obsTypeCode]
            );
            $rows = [];
            while ($r = sqlFetchArray($res)) {
                $rows[] = [
                    'datetime' => (string)$r['observed_datetime'],
                    'value'    => (float)$r['value_numeric'],
                ];
            }
            return array_reverse($rows);  // oldest first
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * All flagged observations for a facility in a time window.
     * Used by the alerts system.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listFlagged(int $facilityId, int $hoursBack = 24, int $limit = 50): array
    {
        if (!self::ready()) {
            return [];
        }
        $since = date('Y-m-d H:i:s', strtotime("-{$hoursBack} hours"));
        try {
            $res = sqlStatement(
                "SELECT o.*, t.display_name, t.alert_low, t.alert_high
                   FROM oei_observation o
                   LEFT JOIN oei_obs_type t ON t.code = o.obs_type_code
                  WHERE o.facility_id = ?
                    AND o.is_flagged  = 1
                    AND o.observed_datetime >= ?
                  ORDER BY o.observed_datetime DESC
                  LIMIT " . (int)$limit,
                [$facilityId, $since]
            );
            $rows = [];
            while ($r = sqlFetchArray($res)) {
                $rows[] = $this->hydrateRow($r);
            }
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Batch count of flagged observations per episode for the past N hours.
     * Used by board pages to show a single indicator badge per patient
     * without issuing a per-row query.
     *
     * @param  int[]  $episodeIds
     * @return array<int, int>  episode_id => count of flagged rows
     */
    public function countFlaggedByEpisodes(array $episodeIds, int $hoursBack = 24): array
    {
        if (!self::ready() || empty($episodeIds)) {
            return [];
        }
        $ids   = array_map('intval', $episodeIds);
        $ph    = implode(',', array_fill(0, count($ids), '?'));
        $since = date('Y-m-d H:i:s', strtotime("-{$hoursBack} hours"));
        try {
            $res = sqlStatement(
                "SELECT episode_id, COUNT(*) AS cnt
                   FROM oei_observation
                  WHERE episode_id IN ({$ph})
                    AND is_flagged = 1
                    AND observed_datetime >= ?
                  GROUP BY episode_id",
                array_merge($ids, [$since])
            );
            $map = [];
            while ($r = sqlFetchArray($res)) {
                $map[(int)$r['episode_id']] = (int)$r['cnt'];
            }
            return $map;
        } catch (\Throwable) {
            return [];
        }
    }


    // ── Private hydration ─────────────────────────────────────────────────

    /** @param array<string, mixed> $r */
    private function hydrateRow(array $r): array
    {
        return [
            'id'               => isset($r['id'])              ? (int)$r['id']               : null,
            'episode_id'       => isset($r['episode_id'])      ? (int)$r['episode_id']        : null,
            'pid'              => isset($r['pid'])              ? (int)$r['pid']               : null,
            'obs_type_code'    => (string)($r['obs_type_code'] ?? ''),
            'display_name'     => (string)($r['display_name']  ?? $r['obs_type_code'] ?? ''),
            'loinc_code'       => isset($r['loinc_code'])      ? (string)$r['loinc_code']    : null,
            'category'         => (string)($r['category']      ?? 'vital-signs'),
            'observed_datetime'=> (string)($r['observed_datetime'] ?? ''),
            'value_numeric'    => isset($r['value_numeric'])   ? (float)$r['value_numeric']   : null,
            'value_text'       => isset($r['value_text'])      ? (string)$r['value_text']     : null,
            'unit'             => (string)($r['unit'] ?? $r['default_unit'] ?? ''),
            'source_type'      => (string)($r['source_type']   ?? 'MANUAL'),
            'device_id'        => isset($r['device_id'])       ? (string)$r['device_id']     : null,
            'is_flagged'       => (bool)($r['is_flagged']      ?? false),
            'alert_low'        => isset($r['alert_low'])       ? (float)$r['alert_low']       : null,
            'alert_high'       => isset($r['alert_high'])      ? (float)$r['alert_high']      : null,
        ];
    }

    /** @param array<string, mixed> $r */
    private function hydrateType(array $r): array
    {
        return [
            'code'         => (string)$r['code'],
            'display_name' => (string)$r['display_name'],
            'loinc_code'   => isset($r['loinc_code'])  ? (string)$r['loinc_code'] : null,
            'category'     => (string)($r['category']  ?? 'vital-signs'),
            'default_unit' => isset($r['default_unit']) ? (string)$r['default_unit'] : null,
            'value_type'   => (string)($r['value_type'] ?? 'numeric'),
            'alert_low'    => isset($r['alert_low'])   ? (float)$r['alert_low']   : null,
            'alert_high'   => isset($r['alert_high'])  ? (float)$r['alert_high']  : null,
            'is_active'    => (bool)($r['is_active']   ?? true),
            'sort_order'   => (int)($r['sort_order']   ?? 100),
        ];
    }
}










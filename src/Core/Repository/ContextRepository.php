<?php

/**
 * src/Core/Repository/ContextRepository.php
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

namespace OpenEMR\Modules\Institutional\Core\Repository;

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;

/**
 * ContextRepository
 *
 * Reads and writes the active care context for a user+facility pair.
 * Table: oei_user_context (see sql/context.sql)
 *
 * One row per (user_id, facility_id). Upsert on change.
 * Falls back gracefully if the table does not yet exist.
 */
final class ContextRepository
{
    /** Cached result of the table-existence probe (null = not yet checked). */
    private static ?bool $tableExists = null;

    /**
     * Probe once per request whether oei_user_context exists.
     * Uses INFORMATION_SCHEMA so it never touches the missing table itself,
     * which prevents OpenEMR's sqlQuery() from writing to the error log.
     */
    private static function tableReady(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }
        if (!function_exists('sqlQuery')) {
            return self::$tableExists = false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1 AS tbl
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'oei_user_context'
                 LIMIT 1"
            );
            return self::$tableExists = !empty($row);
        } catch (\Throwable $e) {
            return self::$tableExists = false;
        }
    }

    /**
     * Returns the stored context key for this user+facility, or null if not set.
     */
    public function get(int $userId, int $facilityId): ?string
    {
        if (!self::tableReady()) {
            return null;
        }
        try {
            $row = sqlQuery(
                "SELECT context_key FROM oei_user_context
                 WHERE user_id = ? AND facility_id = ?
                 LIMIT 1",
                [$userId, $facilityId]
            );
            if (!$row || !isset($row['context_key'])) {
                return null;
            }
            $key = (string)$row['context_key'];
            return CareContext::isValid($key) ? $key : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Upserts the context for a user+facility pair.
     */
    public function set(int $userId, int $facilityId, string $contextKey): void
    {
        if (!self::tableReady() || !CareContext::isValid($contextKey)) {
            return;
        }
        try {
            $now = date('Y-m-d H:i:s');
            sqlStatement(
                "INSERT INTO oei_user_context
                     (user_id, facility_id, context_key, updated_datetime)
                 VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                     context_key      = VALUES(context_key),
                     updated_datetime = VALUES(updated_datetime)",
                [$userId, $facilityId, $contextKey, $now]
            );
        } catch (\Throwable $e) {
            // Silently degrade — context is a convenience, not critical path
        }
    }

    /**
     * Returns all users' context records for a facility (admin view).
     * @return array<int, array{user_id:int, context_key:string, updated_datetime:string}>
     */
    public function listByFacility(int $facilityId): array
    {
        if (!self::tableReady()) {
            return [];
        }
        try {
            $res  = sqlStatement(
                "SELECT user_id, context_key, updated_datetime
                 FROM oei_user_context
                 WHERE facility_id = ?
                 ORDER BY updated_datetime DESC",
                [$facilityId]
            );
            $rows = [];
            while ($row = sqlFetchArray($res)) {
                $rows[] = [
                    'user_id'          => (int)$row['user_id'],
                    'context_key'      => (string)$row['context_key'],
                    'updated_datetime' => (string)$row['updated_datetime'],
                ];
            }
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }
}




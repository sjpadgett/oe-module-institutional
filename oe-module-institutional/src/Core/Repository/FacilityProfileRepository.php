<?php

/**
 * src/Core/Repository/FacilityProfileRepository.php
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
 * FacilityProfileRepository
 *
 * Reads and writes oei_facility_profile.
 *
 * Table PK is (facility_id, user_id):
 *   user_id = 0  → facility-wide default, written by setup/admin flow
 *   user_id > 0  → personal profile override for that user at that facility
 */
final class FacilityProfileRepository
{
    public const PURPOSE_AL_ONLY         = 'AL_ONLY';
    public const PURPOSE_ED_OBS_BH       = 'ED_OBS_BH';
    public const PURPOSE_INPATIENT       = 'INPATIENT';
    public const PURPOSE_AL_INPATIENT    = 'AL_INPATIENT';
    public const PURPOSE_HOME_BASED_CARE = 'HOME_BASED_CARE';
    public const PURPOSE_FULL            = 'FULL';

    public const VALID_PURPOSES = [
        self::PURPOSE_AL_ONLY,
        self::PURPOSE_ED_OBS_BH,
        self::PURPOSE_INPATIENT,
        self::PURPOSE_AL_INPATIENT,
        self::PURPOSE_HOME_BASED_CARE,
        self::PURPOSE_FULL,
    ];

    /** @var array<string,array<string,mixed>> */
    private static array $cache = [];
    private static ?bool $tableExists = null;

    /** @return array<string,mixed> */
    private static function defaults(int $facilityId, int $userId = 0): array
    {
        return [
            'facility_id'            => $facilityId,
            'user_id'                => $userId,
            'installed_purpose'      => '',
            'facility_name'          => '',
            'institutional_enabled'  => false,
            'default_context'        => CareContext::FULL,
            'home_page'              => '',
            'enabled_contexts_json'  => null,
            'feature_overrides_json' => null,
            'setup_completed'        => false,
            'setup_step'             => 0,
            'updated_by_user_id'     => null,
            'updated_datetime'       => null,
        ];
    }

    public static function tableReady(): bool
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
                    AND TABLE_NAME   = 'oei_facility_profile'
                  LIMIT 1"
            );
            return self::$tableExists = !empty($row);
        } catch (\Throwable) {
            return self::$tableExists = false;
        }
    }

    public static function isValidPurpose(string $purpose): bool
    {
        return in_array(strtoupper(trim($purpose)), self::VALID_PURPOSES, true);
    }

    /** @return array<string,mixed> */
    public function getFacilityDefault(int $facilityId): array
    {
        return $this->get($facilityId, 0, false);
    }

    /**
     * @return array<string,mixed>
     */
    public function get(int $facilityId, ?int $userId = 0, bool $fallback = true): array
    {
        $facilityId = max(0, $facilityId);
        $userId = max(0, (int)($userId ?? 0));
        $cacheKey = $facilityId . '_' . $userId . '_' . ($fallback ? '1' : '0');
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $default = self::defaults($facilityId, $userId);
        if ($facilityId <= 0 || !self::tableReady() || !function_exists('sqlQuery')) {
            return self::$cache[$cacheKey] = $default;
        }

        $row = null;
        if ($userId > 0) {
            $row = sqlQuery(
                'SELECT * FROM oei_facility_profile WHERE facility_id = ? AND user_id = ? LIMIT 1',
                [$facilityId, $userId]
            );
        }

        if (!$row && ($userId === 0 || $fallback)) {
            $row = sqlQuery(
                'SELECT * FROM oei_facility_profile WHERE facility_id = ? AND user_id = 0 LIMIT 1',
                [$facilityId]
            );
        }

        if (!$row) {
            return self::$cache[$cacheKey] = $default;
        }

        $out = [
            'facility_id'            => (int)($row['facility_id'] ?? $facilityId),
            'user_id'                => (int)($row['user_id'] ?? ($row ? 0 : $userId)),
            'installed_purpose'      => (string)($row['installed_purpose'] ?? ''),
            'facility_name'          => (string)($row['facility_name'] ?? ''),
            'institutional_enabled'  => !empty($row['institutional_enabled']),
            'default_context'        => (string)($row['default_context'] ?? CareContext::FULL),
            'home_page'              => (string)($row['home_page'] ?? ''),
            'enabled_contexts_json'  => $row['enabled_contexts_json'] ?? null,
            'feature_overrides_json' => $row['feature_overrides_json'] ?? null,
            'setup_completed'        => !empty($row['setup_completed']),
            'setup_step'             => (int)($row['setup_step'] ?? 0),
            'updated_by_user_id'     => isset($row['updated_by_user_id']) ? (int)$row['updated_by_user_id'] : null,
            'updated_datetime'       => $row['updated_datetime'] ?? null,
        ];

        return self::$cache[$cacheKey] = $out;
    }

    /** @param array<string,mixed> $data */
    public function save(int $facilityId, array $data, ?int $updatedByUserId = null, ?int $rowUserId = null): void
    {
        if ($facilityId <= 0 || !self::tableReady() || !function_exists('sqlStatement')) {
            return;
        }

        $rowUserId = array_key_exists('row_user_id', $data)
            ? max(0, (int)$data['row_user_id'])
            : max(0, (int)($rowUserId ?? 0));

        $current = $this->get($facilityId, $rowUserId, false);

        $purpose = strtoupper(trim((string)($data['installed_purpose'] ?? $current['installed_purpose'] ?? '')));
        if (!self::isValidPurpose($purpose)) {
            $purpose = self::PURPOSE_FULL;
        }

        $facilityName = array_key_exists('facility_name', $data)
            ? trim((string)($data['facility_name'] ?? ''))
            : trim((string)($current['facility_name'] ?? ''));
        if ($facilityName === '') {
            $facilityName = $this->resolveOpenEmrFacilityName($facilityId);
        }
        if ($facilityName === '') {
            $facilityName = 'Facility ' . $facilityId;
        }

        $defaultContext = trim((string)($data['default_context'] ?? $current['default_context'] ?? CareContext::FULL));
        if (!CareContext::isValid($defaultContext)) {
            $defaultContext = CareContext::FULL;
        }

        $homePage = trim((string)($data['home_page'] ?? $current['home_page'] ?? ''));

        $enabledContextsJson = $this->normalizeJsonColumn($data, 'enabled_contexts_json', $current['enabled_contexts_json'] ?? null);
        $featureOverridesJson = $this->normalizeJsonColumn($data, 'feature_overrides_json', $current['feature_overrides_json'] ?? null);

        $institutionalEnabled = array_key_exists('institutional_enabled', $data)
            ? (!empty($data['institutional_enabled']) ? 1 : 0)
            : (!empty($current['institutional_enabled']) ? 1 : 0);

        $setupCompleted = array_key_exists('setup_completed', $data)
            ? (!empty($data['setup_completed']) ? 1 : 0)
            : (!empty($current['setup_completed']) ? 1 : 0);

        $setupStep = array_key_exists('setup_step', $data)
            ? max(0, (int)$data['setup_step'])
            : (int)($current['setup_step'] ?? 0);

        $now = date('Y-m-d H:i:s');

        sqlStatement(
            'INSERT INTO oei_facility_profile (
                facility_id, user_id, updated_by_user_id, updated_datetime,
                installed_purpose, facility_name, institutional_enabled, default_context,
                home_page, enabled_contexts_json, feature_overrides_json,
                setup_completed, setup_step
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                updated_by_user_id     = VALUES(updated_by_user_id),
                updated_datetime       = VALUES(updated_datetime),
                installed_purpose      = VALUES(installed_purpose),
                facility_name          = VALUES(facility_name),
                institutional_enabled  = VALUES(institutional_enabled),
                default_context        = VALUES(default_context),
                home_page              = VALUES(home_page),
                enabled_contexts_json  = VALUES(enabled_contexts_json),
                feature_overrides_json = VALUES(feature_overrides_json),
                setup_completed        = VALUES(setup_completed),
                setup_step             = VALUES(setup_step)',
            [
                $facilityId,
                $rowUserId,
                $updatedByUserId,
                $now,
                $purpose,
                $facilityName,
                $institutionalEnabled,
                $defaultContext,
                $homePage,
                $enabledContextsJson,
                $featureOverridesJson,
                $setupCompleted,
                $setupStep,
            ]
        );

        unset(self::$cache[$facilityId . '_' . $rowUserId . '_0'], self::$cache[$facilityId . '_' . $rowUserId . '_1']);
        unset(self::$cache[$facilityId . '_0_0'], self::$cache[$facilityId . '_0_1']);
    }

    /** @param array<string,mixed> $data */
    private function normalizeJsonColumn(array $data, string $key, mixed $fallback): ?string
    {
        if (!array_key_exists($key, $data)) {
            return is_string($fallback) ? $fallback : null;
        }
        $value = $data[$key];
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES);
        }
        return (string)$value;
    }

    private function resolveOpenEmrFacilityName(int $facilityId): string
    {
        if ($facilityId <= 0 || !function_exists('sqlQuery')) {
            return '';
        }
        try {
            $row = sqlQuery('SELECT name FROM facility WHERE id = ? LIMIT 1', [$facilityId]);
            return trim((string)($row['name'] ?? ''));
        } catch (\Throwable) {
            return '';
        }
    }
}




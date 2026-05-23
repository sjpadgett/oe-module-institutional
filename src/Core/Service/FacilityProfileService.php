<?php

/**
 * src/Core/Service/FacilityProfileService.php
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

namespace OpenEMR\Modules\Institutional\Core\Service;

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\FacilityProfileRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

final class FacilityProfileService
{
    private const ACTIVE_FACILITY_SESSION_KEY = 'oei_active_facility_id';

    private SettingsRepository $settingsRepo;
    private FacilityProfileRepository $profileRepo;
    private int $activeUserId = 0;

    public function __construct(
        ?SettingsRepository $settingsRepo = null,
        ?FacilityProfileRepository $profileRepo = null,
        int $activeUserId = 0
    ) {
        $this->settingsRepo = $settingsRepo ?? new SettingsRepository();
        $this->profileRepo  = $profileRepo ?? new FacilityProfileRepository();
        $this->activeUserId = $activeUserId;
    }

    /** @return array<int,array<string,mixed>> */
    public function listOpenEmrFacilities(bool $activeOnly = true): array
    {
        if (!function_exists('sqlStatement') || !$this->facilityTableReady()) {
            return [];
        }

        $sql = "SELECT id, name, facility_code, city, state, inactive
                  FROM facility";
        if ($activeOnly) {
            $sql .= " WHERE inactive = 0";
        }
        $sql .= " ORDER BY COALESCE(NULLIF(TRIM(name), ''), CONCAT('Facility ', id)) ASC, id ASC";

        $res = sqlStatement($sql);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $row['id'] = (int)($row['id'] ?? 0);
            $row['inactive'] = (int)($row['inactive'] ?? 0);
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    public function getOpenEmrFacility(int $facilityId): ?array
    {
        if ($facilityId <= 0 || !function_exists('sqlQuery') || !$this->facilityTableReady()) {
            return null;
        }
        $row = sqlQuery(
            "SELECT id, name, facility_code, city, state, inactive
               FROM facility
              WHERE id = ?
              LIMIT 1",
            [$facilityId]
        );
        if (!$row) {
            return null;
        }
        $row['id'] = (int)($row['id'] ?? 0);
        $row['inactive'] = (int)($row['inactive'] ?? 0);
        return $row;
    }

    public function facilityExists(int $facilityId, bool $activeOnly = false): bool
    {
        $row = $this->getOpenEmrFacility($facilityId);
        if (!$row) {
            return false;
        }
        return !$activeOnly || (int)($row['inactive'] ?? 0) === 0;
    }

    public function getUserDefaultFacilityId(int $userId): int
    {
        if ($userId <= 0 || !function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery("SELECT facility_id FROM users WHERE id = ? LIMIT 1", [$userId]);
        return (int)($row['facility_id'] ?? 0);
    }

    public function writeActiveFacilitySession(int $facilityId): void
    {
        if ($facilityId > 0) {
            $_SESSION[self::ACTIVE_FACILITY_SESSION_KEY] = $facilityId;
        }
    }

    public function readActiveFacilitySession(): int
    {
        return (int)($_SESSION[self::ACTIVE_FACILITY_SESSION_KEY] ?? 0);
    }

    public function resolveFacilityId(int $requestedFacilityId = 0, int $userId = 0): int
    {
        if ($requestedFacilityId > 0 && $this->facilityExists($requestedFacilityId)) {
            return $requestedFacilityId;
        }

        $sessionFacilityId = $this->readActiveFacilitySession();
        if ($sessionFacilityId > 0 && $this->facilityExists($sessionFacilityId)) {
            return $sessionFacilityId;
        }

        $effectiveUserId = $userId > 0 ? $userId : $this->activeUserId;
        $userFacilityId = $this->getUserDefaultFacilityId($effectiveUserId);
        if ($userFacilityId > 0 && $this->facilityExists($userFacilityId)) {
            return $userFacilityId;
        }

        $configured = $this->getConfiguredFacilityIds();
        if (!empty($configured)) {
            return (int)$configured[0];
        }

        $globalDefault = (int)($GLOBALS['facility_default_id'] ?? 0);
        if ($globalDefault > 0 && $this->facilityExists($globalDefault)) {
            return $globalDefault;
        }

        $facilities = $this->listOpenEmrFacilities(true);
        return (int)($facilities[0]['id'] ?? 0);
    }

    /** @return array<string,mixed> */
    public function getProfile(int $facilityId, ?int $userId = null): array
    {
        if ($facilityId <= 0) {
            return [];
        }

        $resolvedUserId = $userId;
        if ($resolvedUserId === null) {
            $resolvedUserId = $this->activeUserId > 0 ? $this->activeUserId : 0;
        }

        $profile = [];
        if ($resolvedUserId > 0) {
            $profile = $this->profileRepo->get($facilityId, $resolvedUserId);
        }
        if (empty($profile)) {
            if (method_exists($this->profileRepo, 'getFacilityDefault')) {
                $profile = $this->profileRepo->getFacilityDefault($facilityId);
            } else {
                $profile = $this->profileRepo->get($facilityId, 0);
            }
        }
        return is_array($profile) ? $profile : [];
    }

    /** @param array<string,mixed> $data */
    public function saveProfile(int $facilityId, array $data, ?int $updatedByUserId = null): void
    {
        $current = $this->getProfile($facilityId, 0);
        $purpose = (string)($data['installed_purpose'] ?? $current['installed_purpose'] ?? '');
        if (method_exists(FacilityProfileRepository::class, 'isValidPurpose') && !FacilityProfileRepository::isValidPurpose($purpose)) {
            $purpose = FacilityProfileRepository::PURPOSE_FULL;
        }

        $defaultContext = (string)($data['default_context'] ?? '');
        if (!CareContext::isValid($defaultContext)) {
            $defaultContext = $this->recommendedDefaultContext($purpose);
        }

        $enabledJson = $data['enabled_contexts_json'] ?? null;
        if ($enabledJson === null || $enabledJson === '') {
            $enabledJson = json_encode($this->recommendedContexts($purpose, $defaultContext), JSON_UNESCAPED_SLASHES);
        }

        $homePage = trim((string)($data['home_page'] ?? ''));
        if ($homePage === '') {
            $homePage = $this->recommendedHomePage($purpose, $defaultContext);
        }

        $facilityName = trim((string)($data['facility_name'] ?? $current['facility_name'] ?? ''));
        if ($facilityName === '') {
            $row = $this->getOpenEmrFacility($facilityId);
            $facilityName = trim((string)($row['name'] ?? ''));
        }
        if ($facilityName === '') {
            $facilityName = 'Facility ' . $facilityId;
        }

        $payload = [
            'installed_purpose' => $purpose,
            'facility_name' => $facilityName,
            'institutional_enabled' => !empty($data['institutional_enabled']),
            'default_context' => $defaultContext,
            'home_page' => $homePage,
            'enabled_contexts_json' => (string)$enabledJson,
            'feature_overrides_json' => array_key_exists('feature_overrides_json', $data)
                ? $data['feature_overrides_json']
                : ($current['feature_overrides_json'] ?? null),
            'setup_completed' => !empty($data['setup_completed']) || !empty($current['setup_completed']),
            'setup_step' => (int)($data['setup_step'] ?? $current['setup_step'] ?? 0),
        ];
        $this->profileRepo->save($facilityId, $payload, $updatedByUserId, 0);

        $this->settingsRepo->setMany($facilityId, [
            'facility_name' => (string)$payload['facility_name'],
            'institutional_enabled' => $payload['institutional_enabled'] ? '1' : '0',
            'facility_operational_mode' => $this->purposeToLegacyMode($purpose),
            'facility_default_context' => (string)$payload['default_context'],
            'facility_home_page' => (string)$payload['home_page'],
            'facility_enabled_contexts_json' => (string)$payload['enabled_contexts_json'],
        ], $updatedByUserId);
    }

    public function getDisplayName(int $facilityId): string
    {
        $profile = $this->getProfile($facilityId, 0);
        $custom = trim((string)($profile['facility_name'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        $row = $this->getOpenEmrFacility($facilityId);
        $name = trim((string)($row['name'] ?? ''));
        return $name !== '' ? $name : 'Facility ' . $facilityId;
    }

    public function isInstitutionalConfigured(int $facilityId): bool
    {
        $profile = $this->getProfile($facilityId, 0);
        if (!empty($profile['institutional_enabled'])) {
            return true;
        }
        return $this->settingsRepo->get($facilityId, 'institutional_enabled') === '1';
    }

    public function hasInstitutionalData(int $facilityId): bool
    {
        if ($facilityId <= 0 || !function_exists('sqlQuery')) {
            return false;
        }

        $tables = [
            'oei_episode' => 'facility_id',
            'oei_location' => 'facility_id',
            'oei_settings' => 'facility_id',
        ];

        foreach ($tables as $table => $column) {
            if (!$this->tableReady($table)) {
                continue;
            }
            $row = sqlQuery("SELECT 1 AS hit FROM {$table} WHERE {$column} = ? LIMIT 1", [$facilityId]);
            if (!empty($row)) {
                return true;
            }
        }

        return false;
    }

    public function isInstitutionalFacility(int $facilityId): bool
    {
        return $this->isInstitutionalConfigured($facilityId) || $this->hasInstitutionalData($facilityId);
    }

    /** @return array<int,array<string,mixed>> */
    public function listInstitutionalFacilities(): array
    {
        $facilities = $this->listOpenEmrFacilities(true);
        if (empty($facilities)) {
            return [];
        }

        $rows = [];
        foreach ($facilities as $facility) {
            $fid = (int)($facility['id'] ?? 0);
            if ($fid <= 0 || !$this->isInstitutionalFacility($fid)) {
                continue;
            }

            $facility['display_name'] = $this->getDisplayName($fid);
            $facility['institutional_enabled'] = $this->isInstitutionalConfigured($fid) ? 1 : 0;
            $rows[] = $facility;
        }

        return $rows;
    }

    public function getDefaultContext(int $facilityId): string
    {
        $profile = $this->getProfile($facilityId, 0);
        $key = (string)($profile['default_context'] ?? '');
        return CareContext::isValid($key) ? $key : CareContext::FULL;
    }

    public function getHomePage(int $facilityId): string
    {
        $profile = $this->getProfile($facilityId, 0);
        $page = trim((string)($profile['home_page'] ?? ''));
        return $page !== '' ? $page : 'ed_board.php';
    }

    /** @return string[] */
    public function getEnabledContexts(int $facilityId): array
    {
        $profile = $this->getProfile($facilityId, 0);
        $raw = $profile['enabled_contexts_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $key) {
                    $key = (string)$key;
                    if (CareContext::isValid($key)) {
                        $out[] = $key;
                    }
                }
                if (!empty($out)) {
                    return array_values(array_unique($out));
                }
            }
        }
        return [$this->getDefaultContext($facilityId)];
    }

    public function isContextEnabled(int $facilityId, string $contextKey): bool
    {
        if (!CareContext::isValid($contextKey)) {
            return false;
        }
        return in_array($contextKey, $this->getEnabledContexts($facilityId), true);
    }

    public function recommendedDefaultContext(string $purpose): string
    {
        return match ($purpose) {
            FacilityProfileRepository::PURPOSE_INPATIENT => CareContext::INPATIENT_STAY,
            FacilityProfileRepository::PURPOSE_AL_ONLY,
            FacilityProfileRepository::PURPOSE_AL_INPATIENT => CareContext::ASSISTED_LIVING,
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => CareContext::HOME_BASED_CARE,
            FacilityProfileRepository::PURPOSE_ED_OBS_BH => CareContext::ED_ACUTE,
            default => CareContext::FULL,
        };
    }

    public function recommendedHomePage(string $purpose, ?string $defaultContext = null): string
    {
        $context = $defaultContext ?: $this->recommendedDefaultContext($purpose);
        return match ($context) {
            CareContext::INPATIENT_STAY => 'ip/board.php',
            CareContext::ASSISTED_LIVING => 'al/board.php',
            CareContext::HOME_BASED_CARE => 'hbc/board.php',
            CareContext::BH => 'bh_boarding.php',
            CareContext::OPERATIONS => 'command_center.php',
            default => 'ed_board.php',
        };
    }

    /** @return string[] */
    public function recommendedContexts(string $purpose, ?string $defaultContext = null): array
    {
        $defaultContext = $defaultContext ?: $this->recommendedDefaultContext($purpose);
        return match ($purpose) {
            FacilityProfileRepository::PURPOSE_INPATIENT => [CareContext::INPATIENT_STAY],
            FacilityProfileRepository::PURPOSE_AL_ONLY => [CareContext::ASSISTED_LIVING],
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => [CareContext::HOME_BASED_CARE],
            FacilityProfileRepository::PURPOSE_ED_OBS_BH => [CareContext::ED_ACUTE, CareContext::OBSERVATION, CareContext::BH],
            FacilityProfileRepository::PURPOSE_AL_INPATIENT => [CareContext::ASSISTED_LIVING, CareContext::INPATIENT_STAY],
            default => [$defaultContext],
        };
    }

    /** @return int[] */
    private function getConfiguredFacilityIds(): array
    {
        $ids = [];
        $facilities = $this->listOpenEmrFacilities(true);
        foreach ($facilities as $facility) {
            $fid = (int)($facility['id'] ?? 0);
            if ($fid > 0 && $this->isInstitutionalConfigured($fid)) {
                $ids[] = $fid;
            }
        }
        return $ids;
    }

    private function purposeToLegacyMode(string $purpose): string
    {
        return match ($purpose) {
            FacilityProfileRepository::PURPOSE_AL_ONLY => 'AL',
            FacilityProfileRepository::PURPOSE_INPATIENT => 'IP',
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => 'HBC',
            FacilityProfileRepository::PURPOSE_ED_OBS_BH => 'ED',
            FacilityProfileRepository::PURPOSE_AL_INPATIENT => 'AL',
            default => 'FULL',
        };
    }

    private function tableReady(string $table): bool
    {
        if (!function_exists('sqlQuery')) {
            return false;
        }
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?? '';
        if ($table === '') {
            return false;
        }
        try {
            $row = sqlQuery("SHOW TABLES LIKE ?", [$table]);
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function facilityTableReady(): bool
    {
        if (!function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery("SHOW TABLES LIKE 'facility'");
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }
}






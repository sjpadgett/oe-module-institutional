<?php

/**
 * src/Core/Service/FacilityContextResolver.php
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
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

/**
 * FacilityContextResolver
 *
 * Canonical runtime resolver for the Institutional module's active facility.
 *
 * Design rules:
 *   - OpenEMR facility.id is the preferred source of truth for internal facilities.
 *   - Module settings remain scoped by facility_id in oei_settings.
 *   - Context is resolved inside a facility, not globally.
 *   - The module may still coordinate with outside facilities via
 *     oei_facility_directory — that directory is not the runtime facility selector.
 */
final class FacilityContextResolver
{
    private const SESSION_KEY = 'oei_active_facility_id';

    private array $profileCache = [];
    private array $nameCache = [];
    private ?bool $facilityTableReady = null;
    private ?bool $episodeTableReady = null;
    private ?bool $settingsTableReady = null;

    public function __construct(
        private readonly ?SettingsRepository $settingsRepo = null
    ) {
    }

    public function resolveFacilityId(?int $requestedFacilityId, int $userId = 0): int
    {
        $requested = (int)($requestedFacilityId ?? 0);
        if ($requested > 0 && $this->isKnownFacility($requested)) {
            return $requested;
        }

        $sessionFacilityId = (int)($this->sessionFacilityId() ?? 0);
        if ($sessionFacilityId > 0 && $this->isKnownFacility($sessionFacilityId)) {
            return $sessionFacilityId;
        }

        $userFacilityId = (int)($this->getUserDefaultFacilityId($userId) ?? 0);
        if ($userFacilityId > 0 && $this->isInstitutionalFacility($userFacilityId)) {
            return $userFacilityId;
        }

        $configuredFacilityId = (int)($this->firstConfiguredFacilityId() ?? 0);
        if ($configuredFacilityId > 0 && $this->isKnownFacility($configuredFacilityId)) {
            return $configuredFacilityId;
        }

        $globalDefaultId = (int)($this->globalDefaultFacilityId() ?? 0);
        if ($globalDefaultId > 0 && $this->isKnownFacility($globalDefaultId)) {
            return $globalDefaultId;
        }

        $openEmrFacilityId = (int)($this->firstOpenEmrFacilityId() ?? 0);
        if ($openEmrFacilityId > 0) {
            return $openEmrFacilityId;
        }

        return 1;
    }

    public function persistActiveFacilityId(int $facilityId): void
    {
        if ($facilityId <= 0 || !isset($_SESSION)) {
            return;
        }
        $_SESSION[self::SESSION_KEY] = $facilityId;
    }

    /**
     * @return array<string,mixed>
     */
    public function getFacilityProfile(int $facilityId): array
    {
        if ($facilityId <= 0) {
            $facilityId = 1;
        }
        if (isset($this->profileCache[$facilityId])) {
            return $this->profileCache[$facilityId];
        }

        $settings = $this->settingsRepository()->all($facilityId);
        $mode = $this->normalizeMode((string)($settings['facility_operational_mode'] ?? ''));
        $enabledContexts = $this->decodeEnabledContexts((string)($settings['facility_enabled_contexts_json'] ?? ''));
        if (empty($enabledContexts)) {
            $enabledContexts = $this->defaultContextsForMode($mode);
        }

        $defaultContext = (string)($settings['facility_default_context'] ?? '');
        if (!CareContext::isValid($defaultContext) || !in_array($defaultContext, $enabledContexts, true)) {
            $defaultContext = $this->defaultContextForMode($mode);
        }
        if (!in_array($defaultContext, $enabledContexts, true)) {
            array_unshift($enabledContexts, $defaultContext);
        }
        $enabledContexts = array_values(array_unique(array_filter($enabledContexts, [CareContext::class, 'isValid'])));

        $homePage = trim((string)($settings['facility_home_page'] ?? ''));
        if ($homePage === '') {
            $homePage = $this->defaultHomePageForContext($defaultContext);
        }

        $displayName = trim((string)($settings['facility_name'] ?? ''));
        $oeName = $this->getOpenEmrFacilityName($facilityId);
        if ($displayName === '') {
            $displayName = $oeName !== '' ? $oeName : ('Facility ' . $facilityId);
        }

        $profile = [
            'facility_id'           => $facilityId,
            'display_name'          => $displayName,
            'openemr_name'          => $oeName,
            'institutional_enabled' => $this->isInstitutionalFacility($facilityId),
            'has_data'              => $this->hasInstitutionalData($facilityId),
            'operational_mode'      => $mode,
            'operational_mode_label'=> $this->modeLabel($mode),
            'default_context'       => $defaultContext,
            'enabled_contexts'      => $enabledContexts,
            'home_page'             => $homePage,
            'home_url'              => $this->buildModuleUrl($homePage, $facilityId),
            'home_label'            => $this->homeLabelForPage($homePage, $defaultContext),
            'settings'              => $settings,
        ];

        return $this->profileCache[$facilityId] = $profile;
    }

    public function normalizeContextForFacility(int $facilityId, string $contextKey): string
    {
        $profile = $this->getFacilityProfile($facilityId);
        return in_array($contextKey, $profile['enabled_contexts'], true)
            ? $contextKey
            : (string)$profile['default_context'];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listInternalFacilities(): array
    {
        $ids = array_unique(array_merge(
            $this->configuredFacilityIds(),
            $this->episodeFacilityIds()
        ));

        sort($ids);
        $rows = [];
        foreach ($ids as $facilityId) {
            $rows[] = $this->getFacilityProfile($facilityId);
        }

        usort($rows, static function (array $a, array $b): int {
            return strcasecmp((string)$a['display_name'], (string)$b['display_name']);
        });

        return $rows;
    }

    public function buildModuleUrl(string $page, int $facilityId): string
    {
        $page = ltrim(trim($page), '/');
        if (str_starts_with($page, 'public/')) {
            $page = substr($page, 7);
        }
        if ($page === '') {
            $page = 'ed_board.php';
        }
        $url = '/interface/modules/custom_modules/oe-module-institutional/public/' . $page;
        $sep = str_contains($url, '?') ? '&' : '?';
        return $url . $sep . 'facility_id=' . urlencode((string)$facilityId);
    }

    public function getUserDefaultFacilityId(int $userId): ?int
    {
        if ($userId <= 0 || !function_exists('sqlQuery')) {
            return null;
        }
        try {
            $row = sqlQuery(
                "SELECT facility_id FROM users WHERE id = ? LIMIT 1",
                [$userId]
            );
            $facilityId = (int)($row['facility_id'] ?? 0);
            return $facilityId > 0 ? $facilityId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getOpenEmrFacilityName(int $facilityId): string
    {
        if ($facilityId <= 0) {
            return '';
        }
        if (array_key_exists($facilityId, $this->nameCache)) {
            return $this->nameCache[$facilityId];
        }
        if (!$this->facilityTableReady() || !function_exists('sqlQuery')) {
            return $this->nameCache[$facilityId] = '';
        }
        try {
            $row = sqlQuery(
                "SELECT name FROM facility WHERE id = ? AND inactive = 0 LIMIT 1",
                [$facilityId]
            );
            return $this->nameCache[$facilityId] = trim((string)($row['name'] ?? ''));
        } catch (\Throwable) {
            return $this->nameCache[$facilityId] = '';
        }
    }

    // ── Internals ───────────────────────────────────────────────────────

    private function settingsRepository(): SettingsRepository
    {
        return $this->settingsRepo ?? new SettingsRepository();
    }

    private function sessionFacilityId(): ?int
    {
        if (!isset($_SESSION)) {
            return null;
        }
        $facilityId = (int)($_SESSION[self::SESSION_KEY] ?? 0);
        return $facilityId > 0 ? $facilityId : null;
    }

    private function globalDefaultFacilityId(): ?int
    {
        $facilityId = (int)($GLOBALS['facility_default_id'] ?? 0);
        return $facilityId > 0 ? $facilityId : null;
    }

    private function isKnownFacility(int $facilityId): bool
    {
        return $this->openEmrFacilityExists($facilityId)
            || $this->hasInstitutionalData($facilityId)
            || $this->hasSettingsRow($facilityId);
    }

    private function isInstitutionalFacility(int $facilityId): bool
    {
        if ($facilityId <= 0) {
            return false;
        }
        if ($this->hasInstitutionalData($facilityId)) {
            return true;
        }
        if (!$this->settingsTableReady() || !function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1
                   FROM oei_settings
                  WHERE facility_id = ?
                    AND (
                         (setting_key = 'institutional_enabled' AND setting_value = '1')
                      OR (setting_key IN ('facility_operational_mode', 'facility_default_context', 'facility_home_page') AND setting_value <> '')
                    )
                  LIMIT 1",
                [$facilityId]
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function openEmrFacilityExists(int $facilityId): bool
    {
        if ($facilityId <= 0 || !$this->facilityTableReady() || !function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT id FROM facility WHERE id = ? AND inactive = 0 LIMIT 1",
                [$facilityId]
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function firstOpenEmrFacilityId(): ?int
    {
        if (!$this->facilityTableReady() || !function_exists('sqlQuery')) {
            return null;
        }
        try {
            $row = sqlQuery(
                "SELECT id FROM facility WHERE inactive = 0 ORDER BY id ASC LIMIT 1"
            );
            $facilityId = (int)($row['id'] ?? 0);
            return $facilityId > 0 ? $facilityId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function firstConfiguredFacilityId(): ?int
    {
        if (!$this->settingsTableReady() || !function_exists('sqlQuery')) {
            return null;
        }
        try {
            $row = sqlQuery(
                "SELECT facility_id
                   FROM oei_settings
                  WHERE setting_key = 'institutional_enabled'
                    AND setting_value = '1'
                  ORDER BY facility_id ASC
                  LIMIT 1"
            );
            $facilityId = (int)($row['facility_id'] ?? 0);
            return $facilityId > 0 ? $facilityId : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return int[] */
    private function configuredFacilityIds(): array
    {
        if (!$this->settingsTableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        try {
            $res = sqlStatement(
                "SELECT DISTINCT facility_id
                   FROM oei_settings
                  WHERE (
                        setting_key = 'institutional_enabled' AND setting_value = '1'
                     ) OR (
                        setting_key IN ('facility_operational_mode', 'facility_default_context', 'facility_home_page')
                        AND setting_value <> ''
                     )
                  ORDER BY facility_id ASC"
            );
            $ids = [];
            while ($row = sqlFetchArray($res)) {
                $facilityId = (int)($row['facility_id'] ?? 0);
                if ($facilityId > 0) {
                    $ids[] = $facilityId;
                }
            }
            return array_values(array_unique($ids));
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return int[] */
    private function episodeFacilityIds(): array
    {
        if (!$this->episodeTableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        try {
            $res = sqlStatement(
                "SELECT DISTINCT facility_id FROM oei_episode ORDER BY facility_id ASC"
            );
            $ids = [];
            while ($row = sqlFetchArray($res)) {
                $facilityId = (int)($row['facility_id'] ?? 0);
                if ($facilityId > 0) {
                    $ids[] = $facilityId;
                }
            }
            return array_values(array_unique($ids));
        } catch (\Throwable) {
            return [];
        }
    }

    private function hasInstitutionalData(int $facilityId): bool
    {
        if ($facilityId <= 0 || !$this->episodeTableReady() || !function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1 FROM oei_episode WHERE facility_id = ? LIMIT 1",
                [$facilityId]
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasSettingsRow(int $facilityId): bool
    {
        if ($facilityId <= 0 || !$this->settingsTableReady() || !function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1 FROM oei_settings WHERE facility_id = ? LIMIT 1",
                [$facilityId]
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function facilityTableReady(): bool
    {
        if ($this->facilityTableReady !== null) {
            return $this->facilityTableReady;
        }
        return $this->facilityTableReady = $this->tableExists('facility');
    }

    private function episodeTableReady(): bool
    {
        if ($this->episodeTableReady !== null) {
            return $this->episodeTableReady;
        }
        return $this->episodeTableReady = $this->tableExists('oei_episode');
    }

    private function settingsTableReady(): bool
    {
        if ($this->settingsTableReady !== null) {
            return $this->settingsTableReady;
        }
        return $this->settingsTableReady = $this->tableExists('oei_settings');
    }

    private function tableExists(string $table): bool
    {
        if (!function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1
                   FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                  LIMIT 1",
                [$table]
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeMode(string $mode): string
    {
        $mode = strtoupper(trim($mode));
        return match ($mode) {
            'ED', 'IP', 'AL', 'HBC', 'BH', 'OPERATIONS', 'FULL' => $mode,
            default => 'FULL',
        };
    }

    /** @return string[] */
    private function decodeEnabledContexts(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            $decoded = array_map('trim', explode(',', $json));
        }
        $contexts = [];
        foreach ($decoded as $value) {
            $key = strtoupper(trim((string)$value));
            if (CareContext::isValid($key)) {
                $contexts[] = $key;
            }
        }
        return array_values(array_unique($contexts));
    }

    /** @return string[] */
    private function defaultContextsForMode(string $mode): array
    {
        return match ($mode) {
            'ED' => [CareContext::ED_ACUTE, CareContext::OBS_STAY, CareContext::BH, CareContext::OPERATIONS, CareContext::FULL],
            'IP' => [CareContext::INPATIENT_STAY, CareContext::OPERATIONS, CareContext::FULL],
            'AL' => [CareContext::ASSISTED_LIVING, CareContext::FULL],
            'HBC' => [CareContext::HOME_BASED_CARE, CareContext::FULL],
            'BH' => [CareContext::BH, CareContext::OPERATIONS, CareContext::FULL],
            'OPERATIONS' => [CareContext::OPERATIONS, CareContext::FULL],
            default => array_keys(CareContext::all()),
        };
    }

    private function defaultContextForMode(string $mode): string
    {
        return match ($mode) {
            'ED' => CareContext::ED_ACUTE,
            'IP' => CareContext::INPATIENT_STAY,
            'AL' => CareContext::ASSISTED_LIVING,
            'HBC' => CareContext::HOME_BASED_CARE,
            'BH' => CareContext::BH,
            'OPERATIONS' => CareContext::OPERATIONS,
            default => CareContext::FULL,
        };
    }

    private function defaultHomePageForContext(string $contextKey): string
    {
        return match ($contextKey) {
            CareContext::INPATIENT_STAY => 'ip/board.php',
            CareContext::ASSISTED_LIVING => 'al/board.php',
            CareContext::HOME_BASED_CARE => 'hbc/board.php',
            CareContext::BH => 'bh_boarding.php',
            CareContext::OPERATIONS => 'command_center.php',
            default => 'ed_board.php',
        };
    }

    private function homeLabelForPage(string $page, string $contextKey): string
    {
        $page = ltrim($page, '/');
        if (str_starts_with($page, 'public/')) {
            $page = substr($page, 7);
        }
        return match ($page) {
            'ip/board.php' => 'Floor Board',
            'al/board.php' => 'Resident Board',
            'hbc/board.php' => 'Visit Board',
            'bh_boarding.php' => 'BH Boarding',
            'command_center.php' => 'Command Center',
            'multi_facility.php' => 'System Dashboard',
            default => match ($contextKey) {
                CareContext::INPATIENT_STAY => 'Floor Board',
                CareContext::ASSISTED_LIVING => 'Resident Board',
                CareContext::HOME_BASED_CARE => 'Visit Board',
                CareContext::BH => 'BH Boarding',
                CareContext::OPERATIONS => 'Command Center',
                default => 'ED Board',
            },
        };
    }

    private function modeLabel(string $mode): string
    {
        return match ($mode) {
            'ED' => 'Emergency Department',
            'IP' => 'Inpatient',
            'AL' => 'Assisted Living',
            'HBC' => 'Home-Based Care',
            'BH' => 'Behavioral Health',
            'OPERATIONS' => 'Operations',
            default => 'Full Institutional',
        };
    }
}




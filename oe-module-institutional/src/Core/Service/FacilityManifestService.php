<?php

/**
 * src/Core/Service/FacilityManifestService.php
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

use OpenEMR\Modules\Institutional\Core\Repository\FacilityProfileRepository;
use OpenEMR\Modules\Institutional\Manifest\Manifest;
use OpenEMR\Modules\Institutional\Manifest\ManifestWriter;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

final class FacilityManifestService
{
    private const LEGACY_PROFILE_KEY = 'facility_manifest_profile';
    private const LEGACY_OVERRIDES_KEY = 'facility_feature_overrides_json';
    private const FORCED_ON = ['settings', 'smoke_test', 'context_manager'];

    private SettingsRepository $settingsRepo;
    private FacilityProfileRepository $profileRepo;
    private string $moduleRoot;

    public function __construct(
        ?SettingsRepository $settingsRepo = null,
        ?string $moduleRoot = null,
        ?FacilityProfileRepository $profileRepo = null
    ) {
        $this->settingsRepo = $settingsRepo ?? new SettingsRepository();
        $this->profileRepo = $profileRepo ?? new FacilityProfileRepository();
        $this->moduleRoot = $moduleRoot ?? dirname(__DIR__, 3);
    }

    public function getProfileKey(int $facilityId): string
    {
        $profile = $this->profileRepo->get($facilityId, 0, false);
        $purpose = trim((string)($profile['installed_purpose'] ?? ''));
        if ($purpose !== '') {
            return $purpose;
        }
        return trim($this->settingsRepo->get($facilityId, self::LEGACY_PROFILE_KEY));
    }

    /** @return array<string,bool>|null */
    public function getStoredFeatureMap(int $facilityId, array $allKeys, array $fallback = []): ?array
    {
        $profile = $this->profileRepo->get($facilityId, 0, false);
        $raw = trim((string)($profile['feature_overrides_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $this->normalizeFeatureMap($decoded, $allKeys, $fallback);
            }
        }

        $legacyRaw = trim($this->settingsRepo->get($facilityId, self::LEGACY_OVERRIDES_KEY));
        if ($legacyRaw !== '') {
            $decoded = json_decode($legacyRaw, true);
            if (is_array($decoded)) {
                return $this->normalizeFeatureMap($decoded, $allKeys, $fallback);
            }
        }

        $profileKey = $this->getProfileKey($facilityId);
        if ($profileKey !== '') {
            return $this->profileFeatureMap($profileKey, $allKeys, $fallback);
        }
        return null;
    }

    public function applyToManifest(int $facilityId, Manifest $baseManifest): Manifest
    {
        $featureMap = $this->getStoredFeatureMap($facilityId, array_keys($baseManifest->features), $baseManifest->features);
        if ($featureMap === null) {
            return $baseManifest;
        }
        return new Manifest(
            $baseManifest->moduleId,
            $featureMap,
            $baseManifest->ui,
            $baseManifest->migrations,
            $baseManifest->menus
        );
    }

    /** @return array<string,bool> */
    public function profileFeatureMap(string $profileKey, array $allKeys, array $fallback = []): array
    {
        $writer = new ManifestWriter(rtrim($this->moduleRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manifest.json');
        return $this->normalizeFeatureMap($writer->profile($profileKey, $allKeys), $allKeys, $fallback);
    }

    /** @return array<string,array<string,mixed>> */
    public function profileCatalog(): array
    {
        return [
            FacilityProfileRepository::PURPOSE_AL_ONLY => [
                'label' => 'Assisted Living',
                'icon' => '🏡',
                'desc' => 'Resident board, intake, ADL, falls, MAR, incidents, discharge, activity and handoff.',
            ],
            FacilityProfileRepository::PURPOSE_ED_OBS_BH => [
                'label' => 'ED / OBS / BH',
                'icon' => '🚑',
                'desc' => 'Emergency department workflows with observation stay and behavioral health boarding.',
            ],
            FacilityProfileRepository::PURPOSE_INPATIENT => [
                'label' => 'Inpatient',
                'icon' => '🏥',
                'desc' => 'Floor board, admissions, patient profile, vitals, fall risk and discharge planning.',
            ],
            FacilityProfileRepository::PURPOSE_AL_INPATIENT => [
                'label' => 'AL + Inpatient',
                'icon' => '⚕️',
                'desc' => 'Continuing-care campus with both assisted living and inpatient workflows.',
            ],
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => [
                'label' => 'Home-Based Care',
                'icon' => '🏠',
                'desc' => 'Referral queue, scheduling, visit board, mobile encounters, discharge and handoff.',
            ],
            FacilityProfileRepository::PURPOSE_FULL => [
                'label' => 'Full Institutional',
                'icon' => '🧭',
                'desc' => 'Broad capability set for mixed facilities that need more than one care program.',
            ],
        ];
    }

    public function saveFeatureMap(int $facilityId, array $featureMap, ?int $userId = null, string $profileKey = ''): void
    {
        $writer = new ManifestWriter(rtrim($this->moduleRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manifest.json');
        $manifestData = $writer->read();
        $allKeys = array_keys((array)($manifestData['features'] ?? []));
        $baseFeatures = (array)($manifestData['features'] ?? []);
        $normalized = $this->normalizeFeatureMap($featureMap, $allKeys, $baseFeatures);

        $current = $this->profileRepo->get($facilityId, 0, false);
        $profileKey = FacilityProfileRepository::isValidPurpose($profileKey) ? $profileKey : (string)($current['installed_purpose'] ?? '');
        if ($profileKey === '') {
            $profileKey = FacilityProfileRepository::PURPOSE_FULL;
        }

        $this->profileRepo->save($facilityId, [
            'installed_purpose' => $profileKey,
            'feature_overrides_json' => json_encode($normalized, JSON_UNESCAPED_SLASHES),
            'institutional_enabled' => !empty($current['institutional_enabled']) || $this->settingsRepo->get($facilityId, 'institutional_enabled') === '1',
            'default_context' => $current['default_context'] ?? null,
            'home_page' => $current['home_page'] ?? null,
            'enabled_contexts_json' => $current['enabled_contexts_json'] ?? null,
            'setup_completed' => $current['setup_completed'] ?? false,
            'setup_step' => $current['setup_step'] ?? 0,
        ], $userId ?? null, 0);

        // compatibility mirrors
        $this->settingsRepo->setMany($facilityId, [
            self::LEGACY_PROFILE_KEY => $profileKey,
            self::LEGACY_OVERRIDES_KEY => json_encode($normalized, JSON_UNESCAPED_SLASHES),
        ], $userId);
    }

    /** @param array<string,mixed> $map @param string[] $allKeys @param array<string,mixed> $fallback @return array<string,bool> */
    private function normalizeFeatureMap(array $map, array $allKeys, array $fallback = []): array
    {
        $out = [];
        foreach ($allKeys as $key) {
            $out[$key] = array_key_exists($key, $map)
                ? (bool)$map[$key]
                : (bool)($fallback[$key] ?? false);
        }
        foreach (self::FORCED_ON as $forcedKey) {
            if (array_key_exists($forcedKey, $out)) {
                $out[$forcedKey] = true;
            }
        }
        return $out;
    }
}




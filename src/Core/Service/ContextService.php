<?php

/**
 * src/Core/Service/ContextService.php
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
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;

/**
 * ContextService
 *
 * Single point of truth for the active care context in a request.
 *
 * Resolution order:
 *   1. oei_user_context table                   — persisted preference (authoritative)
 *   2. $_SESSION['oei_context'][user_facility] — hot cache / fallback
 *   3. facility default context                — profile fallback
 *
 * The persisted DB row is treated as authoritative so the next request after a
 * context switch reflects the newly selected context consistently across
 * environments, even when session timing/locking differs.
 */
final class ContextService
{
    private const SESSION_KEY = 'oei_context';

    public function __construct(
        private readonly ContextRepository $repo
    ) {}

    /**
     * Resolve the active context key for this user+facility.
     */
    public function resolve(int $userId, int $facilityId): string
    {
        $facilityProfiles = new FacilityProfileService();
        $cacheKey = $userId . '_' . $facilityId;
        $cache    = $_SESSION[self::SESSION_KEY] ?? [];

        // ── DB lookup (authoritative) ───────────────────────────────────
        $stored = $this->repo->get($userId, $facilityId);
        if (is_string($stored) && CareContext::isValid($stored) && $facilityProfiles->isContextEnabled($facilityId, $stored)) {
            if (($cache[$cacheKey] ?? null) !== $stored) {
                $this->writeSession($cacheKey, $stored);
            }
            return $stored;
        }

        // ── Session cache fallback ──────────────────────────────────────
        if (isset($cache[$cacheKey]) && CareContext::isValid((string)$cache[$cacheKey])) {
            $cached = (string)$cache[$cacheKey];
            if ($facilityProfiles->isContextEnabled($facilityId, $cached)) {
                return $cached;
            }
        }

        // ── Facility default fallback ────────────────────────────────────
        $active = $facilityProfiles->getDefaultContext($facilityId);
        if (!CareContext::isValid($active) || !$facilityProfiles->isContextEnabled($facilityId, $active)) {
            $active = CareContext::DEFAULT_CONTEXT;
        }

        $this->writeSession($cacheKey, $active);
        return $active;
    }

    /**
     * Switch context for the user+facility — writes DB and session.
     */
    public function switch(int $userId, int $facilityId, string $contextKey): string
    {
        $facilityProfiles = new FacilityProfileService();
        if (!CareContext::isValid($contextKey) || !$facilityProfiles->isContextEnabled($facilityId, $contextKey)) {
            $contextKey = $facilityProfiles->getDefaultContext($facilityId);
        }

        $this->repo->set($userId, $facilityId, $contextKey);
        $this->writeSession($userId . '_' . $facilityId, $contextKey);

        return $contextKey;
    }

    /**
     * Returns context metadata for the active context.
     * @return array<string,mixed>
     */
    public function meta(string $contextKey): array
    {
        return CareContext::meta($contextKey);
    }

    /**
     * Whether a feature should be surfaced given the active context.
     * Always returns true when context == FULL.
     * Returns true for features not assigned to any context (unowned features
     * are always visible — conservative default preserves existing behaviour).
     */
    public function surfaces(string $contextKey, string $feature): bool
    {
        return CareContext::featureSurfaced($contextKey, $feature);
    }

    /**
     * Provides a filtered view of manifest features based on context.
     * Passes through the original features array, marking each as surfaced/hidden.
     *
     * @param  array<string,bool> $manifestFeatures
     * @return array<string,bool>
     */
    public function applyToFeatures(string $contextKey, array $manifestFeatures): array
    {
        if ($contextKey === CareContext::FULL) {
            return $manifestFeatures;
        }
        $result = [];
        foreach ($manifestFeatures as $key => $enabled) {
            $result[$key] = $enabled && $this->surfaces($contextKey, $key);
        }
        return $result;
    }

    private function writeSession(string $cacheKey, string $contextKey): void
    {
        if (!isset($_SESSION)) {
            return;
        }
        if (!is_array($_SESSION[self::SESSION_KEY] ?? null)) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        $_SESSION[self::SESSION_KEY][$cacheKey] = $contextKey;
    }
}




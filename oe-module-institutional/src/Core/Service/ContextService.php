<?php

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
 *   1. $_SESSION['oei_context'][facility_id]   — hot cache, set on switch
 *   2. oei_user_context table                  — persisted preference
 *   3. CareContext::DEFAULT_CONTEXT             — fallback (ED_ACUTE)
 *
 * The session cache means only the first page load per login hits the DB.
 * Switching context writes both DB and session atomically.
 *
 * Usage in _bootstrap.php:
 *   $contextSvc    = new ContextService(new ContextRepository());
 *   $activeContext = $contextSvc->resolve($userId, $facilityId);
 *
 * Usage anywhere:
 *   CareContext::featureSurfaced($activeContext, 'bh_safety')
 */
final class ContextService
{
    private const SESSION_KEY = 'oei_context';

    public function __construct(
        private readonly ContextRepository $repo
    ) {}

    /**
     * Resolve the active context key for this user+facility.
     * Uses session cache; falls back to DB; falls back to default.
     */
    public function resolve(int $userId, int $facilityId): string
    {
        // ── Session cache ────────────────────────────────────────────────
        $cacheKey = $userId . '_' . $facilityId;
        $cache    = $_SESSION[self::SESSION_KEY] ?? [];
        if (isset($cache[$cacheKey]) && CareContext::isValid((string)$cache[$cacheKey])) {
            return (string)$cache[$cacheKey];
        }

        // ── DB lookup ────────────────────────────────────────────────────
        $stored = $this->repo->get($userId, $facilityId);
        $active = $stored ?? CareContext::DEFAULT_CONTEXT;

        // Write back to session
        $this->writeSession($cacheKey, $active);

        return $active;
    }

    /**
     * Switch context for the user+facility — writes DB and session.
     */
    public function switch(int $userId, int $facilityId, string $contextKey): string
    {
        if (!CareContext::isValid($contextKey)) {
            $contextKey = CareContext::DEFAULT_CONTEXT;
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
     * @return array<string,bool>  Same keys, values AND-ed with context surface rule
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

    // ── Private ──────────────────────────────────────────────────────────

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



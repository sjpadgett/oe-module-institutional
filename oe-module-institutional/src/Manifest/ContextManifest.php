<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Manifest;

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;

/**
 * ContextManifest
 *
 * A proxy around Manifest that gates featureEnabled() and menuItemsEnabled()
 * through the active care context. All other manifest data is delegated
 * transparently to the wrapped Manifest instance.
 *
 * Usage (in _bootstrap.php, after resolving $activeContext):
 *   if ($activeContext !== CareContext::FULL) {
 *       $manifest = new ContextManifest($manifest, $activeContext);
 *   }
 *
 * Any page that calls $manifest->featureEnabled('bh_safety') will now
 * receive false if bh_safety is not surfaced in the active context,
 * triggering the normal "disabled by manifest" guard on that page.
 *
 * Context is a display lens, not access control:
 *   - FULL context → no filtering, all features pass through
 *   - 'context_manager' feature always returns true so users can always switch
 *   - Features not listed in any context are treated as always visible
 *     (conservative default — unowned features don't silently disappear)
 */
final class ContextManifest
{
    // Expose same public properties as Manifest so code that reads
    // $manifest->ui['bootstrap5_mode'] etc. continues to work.
    public readonly string $moduleId;
    public readonly array $features;
    public readonly array $ui;
    public readonly array $migrations;
    public readonly array $menus;

    public function __construct(
        private readonly Manifest $inner,
        private readonly string $contextKey
    )
    {
        $this->moduleId = $inner->moduleId;
        $this->features = $inner->features;
        $this->ui = $inner->ui;
        $this->migrations = $inner->migrations;
        $this->menus = $inner->menus;
    }

    // ── Core filtering API ────────────────────────────────────────────────

    /**
     * Returns true only if:
     *   (a) the inner manifest has the feature enabled, AND
     *   (b) the feature is surfaced in the active context.
     *
     * 'context_manager' is always true so users can always switch context.
     */
    public function featureEnabled(string $name): bool
    {
        // Context manager is always accessible — users must be able to switch
        if ($name === 'context_manager') {
            return $this->inner->featureEnabled($name);
        }

        // Not enabled in manifest at all → false regardless of context
        if (!$this->inner->featureEnabled($name)) {
            return false;
        }

        // FULL context → no filtering
        if ($this->contextKey === CareContext::FULL) {
            return true;
        }

        return CareContext::featureSurfaced($this->contextKey, $name);
    }

    /**
     * Returns only menu items whose feature AND group are surfaced in
     * the active context. Context manager item always passes through.
     *
     * @return array<int,array{label:string,url:string,menu_id:string,group:string,sort:int}>
     */
    public function menuItemsEnabled(): array
    {
        $items = $this->inner->menuItemsEnabled();

        if ($this->contextKey === CareContext::FULL) {
            return $items;
        }

        return array_values(array_filter($items, function (array $item): bool {
            $feature = (string)($item['feature'] ?? '');
            $group = (string)($item['group'] ?? '');

            // Context manager always shown
            if ($feature === 'context_manager') {
                return true;
            }

            // Feature not surfaced in this context → hide
            if ($feature !== '' && !CareContext::featureSurfaced($this->contextKey, $feature)) {
                return false;
            }

            // Group not surfaced in this context → hide
            if ($group !== '' && !CareContext::groupSurfaced($this->contextKey, $group)) {
                return false;
            }

            return true;
        }));
    }

    // ── Delegated API ─────────────────────────────────────────────────────

    public function menusTopLabel(): string
    {
        return $this->inner->menusTopLabel();
    }
}

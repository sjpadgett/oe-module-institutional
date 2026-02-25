<?php

namespace OpenEMR\Modules\Institutional;

use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Manifest\ContextManifest;
use OpenEMR\Modules\Institutional\Manifest\ManifestLoader;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class Bootstrap
{
    public const MODULE_INSTALLATION_PATH = "/interface/modules/custom_modules/oe-module-institutional";
    public const MODULE_NAME = "oe-module-institutional";

    private SystemLogger $logger;
    private string $moduleRoot;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    )
    {
        $this->logger = new SystemLogger();
        $this->moduleRoot = dirname(__DIR__);
    }

    public function subscribeToEvents(): void
    {
        $this->eventDispatcher->addListener(MenuEvent::MENU_UPDATE, $this->addInstitutionalMenu(...));
    }

    public function addInstitutionalMenu(MenuEvent $event): MenuEvent
    {
        try {
            $manifest = ManifestLoader::load($this->moduleRoot);
        } catch (\Throwable $e) {
            $this->logger->error("Institutional: failed to load manifest", ['error' => $e->getMessage()]);
            return $event;
        }

        // ── Context-aware menu filtering ──────────────────────────────────
        // MenuEvent fires per-request after session is started, so we can
        // safely read the user's active context from the session cache.
        // The ContextManifest proxy then filters menuItemsEnabled() so only
        // items surfaced in the user's current context appear in the menu.
        if ($manifest->featureEnabled('context_manager')) {
            $ctxKey = $this->resolveContextFromSession();
            if ($ctxKey !== CareContext::FULL && CareContext::isValid($ctxKey)) {
                $manifest = new ContextManifest($manifest, $ctxKey);
            }
        }

        $items = $manifest->menuItemsEnabled();
        if (empty($items)) {
            return $event;
        }

        $menu = $event->getMenu();

        $top = new stdClass();
        $top->requirement = 0;
        $top->target = 'mod0';
        $top->menu_id = 'inst0';
        $top->label = xlt($manifest->menusTopLabel());
        $top->children = [];

        // Group items by manifest 'group'. Items without group → 'Other'.
        $groups = [];
        foreach ($items as $idx => $child) {
            $g = (string)($child['group'] ?? 'Other');
            if ($g === '') {
                $g = 'Other';
            }
            $groups[$g][] = ['idx' => $idx, 'child' => $child];
        }

        $groupOrder = ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin', 'Other'];
        uksort($groups, function ($a, $b) use ($groupOrder) {
            $ia = array_search($a, $groupOrder, true);
            $ib = array_search($b, $groupOrder, true);
            $ia = ($ia === false) ? 999 : $ia;
            $ib = ($ib === false) ? 999 : $ib;
            if ($ia === $ib) {
                return strcmp($a, $b);
            }
            return $ia <=> $ib;
        });

        foreach ($groups as $groupLabel => $entries) {
            // Header node (non-clickable group separator)
            $h = new stdClass();
            $h->requirement = 0;
            $h->target = preg_replace('/[^a-z0-9_]/i', '_', strtolower($groupLabel));
            $h->menu_id = 'inst_grp_' . preg_replace('/[^a-z0-9_]/i', '_', strtolower($groupLabel));
            $h->label = xlt($groupLabel);
            $h->children = [];
            $h->acl_req = ["admin", "users"];
            $h->global_req = [];
            $top->children[] = $h;

            // Sort entries by optional 'sort' field
            usort($entries, function ($a, $b) {
                $sa = (int)($a['child']['sort'] ?? 0);
                $sb = (int)($b['child']['sort'] ?? 0);
                if ($sa === $sb) {
                    return $a['idx'] <=> $b['idx'];
                }
                return $sa <=> $sb;
            });

            foreach ($entries as $e) {
                $idx = $e['idx'];
                $child = $e['child'];
                $c = new stdClass();
                $c->requirement = 0;
                $c->target = preg_replace('/[^a-z0-9_]/i', '_', strtolower($groupLabel));
                $c->menu_id = 'inst_' . (string)($child['menu_id'] ?? ('item' . $idx));
                $c->label = xlt((string)$child['label']);
                $c->url = self::MODULE_INSTALLATION_PATH . (string)$child['url'];
                $c->children = [];
                $c->acl_req = ["admin", "users"];
                $c->global_req = [];
                $h->children[] = $c;
            }

            // Omit empty group headers (all items filtered out)
            if (empty($h->children)) {
                array_pop($top->children);
            }
        }

        if (!empty($top->children)) {
            $menu[] = $top;
            $event->setMenu($menu);
        }

        return $event;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Read the active context key from the session cache written by ContextService.
     * Falls back to FULL (no filtering) if session data is absent or invalid.
     *
     * Session structure (written by ContextService::writeSession()):
     *   $_SESSION['oei_context']['{userId}_{facilityId}'] = 'ED_ACUTE'
     */
    private function resolveContextFromSession(): string
    {
        if (!isset($_SESSION)) {
            return CareContext::FULL;
        }

        $userId = (int)($_SESSION['authUserID'] ?? 0);
        $facilityId = (int)($GLOBALS['facility_default_id'] ?? 1);

        if ($userId === 0) {
            return CareContext::FULL;
        }

        $cacheKey = $userId . '_' . $facilityId;
        $key = (string)($_SESSION['oei_context'][$cacheKey] ?? '');

        if ($key === '' || !CareContext::isValid($key)) {
            return CareContext::DEFAULT_CONTEXT;
        }

        return $key;
    }
}

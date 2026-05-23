<?php

/**
 * src/Bootstrap.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional;

use OpenEMR\Common\Logging\SystemLogger;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Migration\MigrationRunner;
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;
use OpenEMR\Modules\Institutional\Core\Service\ContextService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityManifestService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;
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
        // ── Schema migrations ─────────────────────────────────────────────
        // Runs pending sql/migrations/NNNN_*.sql files in order.
        // Fast no-op when the install is already up-to-date (one SELECT).
        try {
            $applied = (new MigrationRunner($this->moduleRoot))->runPending();
            if ($applied > 0) {
                $this->logger->info(
                    "Institutional: {$applied} migration(s) applied",
                    ['module' => self::MODULE_NAME]
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error("Institutional: migration runner failed", [
                'error' => $e->getMessage(),
            ]);
            // Don't abort — menus etc. should still load even if a migration fails
        }

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

        $menuUserId     = (int)($_SESSION['authUserID'] ?? 0);
        $facilityProfiles = new FacilityProfileService(null, null, $menuUserId);
        $facilityId = $facilityProfiles->resolveFacilityId(0, $menuUserId);
        $facilityProfiles->writeActiveFacilitySession($facilityId);
        $manifest = (new FacilityManifestService(null, $this->moduleRoot, null, $menuUserId))->applyToManifest($facilityId, $manifest);

        // ── Context-aware menu filtering ──────────────────────────────────
        // Resolve the active context inside the resolved facility so the first
        // request after login already lands in the correct facility lens.
        if ($manifest->featureEnabled('context_manager')) {
            $ctxKey = $this->resolveContextForFacility($facilityId);
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

        $groupOrder = ['Inpatient', 'Assisted Living', 'Home-Based Care', 'Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin', 'Other'];
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
     * Resolve the active context inside a resolved facility.
     * Uses the same ContextService + FacilityProfileService path as public pages
     * so menu filtering matches the facility-owned install state on first load.
     */
    private function resolveContextForFacility(int $facilityId): string
    {
        if (!isset($_SESSION)) {
            return CareContext::FULL;
        }

        $userId = (int)($_SESSION['authUserID'] ?? 0);
        if ($userId <= 0) {
            return CareContext::FULL;
        }

        return (new ContextService(new ContextRepository()))->resolve($userId, $facilityId);
    }
}













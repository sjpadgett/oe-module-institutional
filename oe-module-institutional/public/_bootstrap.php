<?php

/**
 * _bootstrap.php — shared include for all module public pages.
 *
 * ob_start() is called as the very first statement so that the context bar
 * HTML injected at the bottom of this file goes into the output buffer,
 * not directly to the browser. This means POST-redirect pages can still
 * call header('Location:...) safely — the buffer hasn't been sent yet.
 * PHP auto-flushes at request end for normal GET pages.
 */
ob_start();

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;
use OpenEMR\Modules\Institutional\Core\Service\ContextService;
use OpenEMR\Modules\Institutional\Manifest\ContextManifest;
use OpenEMR\Modules\Institutional\Manifest\ManifestLoader;

require_once dirname(__DIR__, 4) . "/globals.php";

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$moduleRoot = dirname(__DIR__);
$manifest   = ManifestLoader::load($moduleRoot);

// ── Care Context resolution ────────────────────────────────────────────────
// Resolve early so $activeContext and $ctxMeta are available to every page.
// The context_bar partial reads these globals and self-injects.

$_oei_userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$_oei_facilityId = (int)($_GET['facility_id'] ?? $_POST['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));

if ($_oei_userId > 0 && $manifest->featureEnabled('context_manager')) {
    $activeContext = (new ContextService(new ContextRepository()))
        ->resolve($_oei_userId, $_oei_facilityId);
} else {
    $activeContext = CareContext::FULL;   // unauthenticated or feature off → no filtering
}

$ctxMeta = CareContext::meta($activeContext);

// ── Apply context to manifest ──────────────────────────────────────────────
// Replace $manifest with a ContextManifest proxy so that every page's
// featureEnabled() call is automatically gated through the active context.
// FULL context → transparent proxy with no filtering.
if ($activeContext !== CareContext::FULL && $manifest->featureEnabled('context_manager')) {
    $manifest = new ContextManifest($manifest, $activeContext);
}

// ── Triage standard resolution ─────────────────────────────────────────────
// Exposes $triageStandard to every page. When mts_triage is disabled
// (the default), always returns ESI — identical to existing hardcoded behavior.
if ($manifest->featureEnabled('mts_triage')) {
    $_oei_tsRepo = new \OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository();
    $_oei_tsCode = $_oei_tsRepo->get($_oei_facilityId, 'triage_standard');
    $triageStandard = \OpenEMR\Modules\Institutional\Core\Domain\TriageStandard::fromCode(
        $_oei_tsCode ?: \OpenEMR\Modules\Institutional\Core\Domain\TriageStandard::ESI
    );
    unset($_oei_tsRepo, $_oei_tsCode);
} else {
    $triageStandard = \OpenEMR\Modules\Institutional\Core\Domain\TriageStandard::fromCode(
        \OpenEMR\Modules\Institutional\Core\Domain\TriageStandard::ESI
    );
}

// ── Helper functions ────────────────────────────────────────────────────────

function institutional_bootstrap5_href($manifest): string
{
    $mode = (string)($manifest->ui['bootstrap5_mode'] ?? 'cdn');
    if ($mode !== 'cdn') {
        return '';
    }
    return "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css";
}

function institutional_human_elapsed(string $start): string
{
    $startTs = strtotime($start);
    if (!$startTs) {
        return '';
    }
    $delta = time() - $startTs;
    if ($delta < 60) {
        return $delta . "s";
    }
    $mins = (int)floor($delta / 60);
    if ($mins < 60) {
        return $mins . "m";
    }
    $hours = (int)floor($mins / 60);
    $mins2 = $mins % 60;
    if ($hours < 24) {
        return $hours . "h " . $mins2 . "m";
    }
    $days   = (int)floor($hours / 24);
    $hours2 = $hours % 24;
    return $days . "d " . $hours2 . "h";
}

// ── Context bar auto-injection ─────────────────────────────────────────────
// The bar is fixed-position (36px) so it doesn't break existing page layouts.
// It is only injected when the page sends HTML output (i.e. not JSON endpoints).
// JSON endpoints typically exit before reaching the bar — safe either way because
// the bar is printed to stdout, not buffered, so it only matters for HTML pages.
//
// The include is intentionally placed at the end of bootstrap so all globals
// ($manifest, $activeContext, $ctxMeta, $facilityId) are already set by pages
// that redefine $facilityId before their own HTML head section.
//
// Pages call `require_once _bootstrap.php` at the very top, then define
// $facilityId, then emit HTML. Since context_bar is fixed-position CSS + JS,
// it doesn't matter where in the HTML stream it appears — it will always
// render at the top of the viewport.

if ($manifest->featureEnabled('context_manager')) {
    $facilityId = $_oei_facilityId;   // use the early-resolved value for the bar
    require_once __DIR__ . '/../src/Core/Ui/partials/context_bar.php';
}

// Clean up temporaries
unset($_oei_userId, $_oei_facilityId);



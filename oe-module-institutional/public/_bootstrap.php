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


// ── Theme resolution ────────────────────────────────────────────────────────
// Read the facility's ui_theme setting (light|dark) and expose it to every
// page and to context_bar.php so the Bootstrap data-bs-theme is applied
// before the page CSS loads.
$_oei_settingsForTheme = new \OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository();
$_oei_theme = $_oei_settingsForTheme->get($_oei_facilityId, 'ui_theme') ?: 'light';
if (!in_array($_oei_theme, ['light', 'dark'], true)) {
    $_oei_theme = 'light';
}
unset($_oei_settingsForTheme);

// ── Helper: themed exit page ───────────────────────────────────────────────
/**
 * Emit a properly themed Bootstrap alert page and exit.
 *
 * Replaces bare `echo '<div class="alert...">' . exit` patterns so Bootstrap
 * CSS and data-bs-theme are always present. Discards the ob buffer (which
 * holds the context_bar partial HTML at this point) and writes a full page.
 *
 * Compatible with PHP 8.0+.
 *
 * @param string $message  Caller-escaped text (use htmlspecialchars() or xlt()).
 * @param string $type     Bootstrap alert variant: warning|danger|info|success
 */
function oei_exit_with_alert(string $message, string $type = 'warning'): void
{
    global $_oei_theme, $manifest;

    $theme   = (isset($_oei_theme) && $_oei_theme === 'dark') ? 'dark' : 'light';
    $bgClass = $theme === 'dark' ? 'bg-dark text-light' : 'bg-light';
    $href    = (isset($manifest) && function_exists('institutional_bootstrap5_href'))
               ? institutional_bootstrap5_href($manifest)
               : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

    // Clear every active ob level so we get a clean output stream.
    // Our own ob_start() from the top of this file is at level 1;
    // globals.php may have added more. We discard all of them.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $safeType = preg_replace('/[^a-z]/', '', $type);

    // Inline minimal alert CSS so the message is styled even if the CDN
    // request is still in-flight or blocked (common on dev environments).
    $fallbackCss = $theme === 'dark'
        ? '.alert-danger{background:#2c1518;color:#ea868f;border-color:#842029}'
        . '.alert-warning{background:#2c2503;color:#ffda6a;border-color:#664d03}'
        . '.alert-info{background:#031f2d;color:#6edff6;border-color:#084298}'
        . '.alert-success{background:#051b11;color:#75b798;border-color:#0a3622}'
        . 'body{background:#1a1d21;color:#dee2e6}'
        : '.alert-danger{background:#f8d7da;color:#842029;border-color:#f5c2c7}'
        . '.alert-warning{background:#fff3cd;color:#664d03;border-color:#ffecb5}'
        . '.alert-info{background:#cff4fc;color:#055160;border-color:#b6effb}'
        . '.alert-success{background:#d1e7dd;color:#0a3622;border-color:#badbcc}';

    echo '<!DOCTYPE html>';
    echo '<html lang="en" data-bs-theme="' . $theme . '">';
    echo '<head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>.alert{padding:.75rem 1rem;border:1px solid transparent;border-radius:.375rem;margin:1rem}'
        . '.container-fluid{padding:1.5rem}' . $fallbackCss . '</style>';
    if ($href !== '') {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($href) . '">';
    }
    echo '</head>';
    echo '<body class="' . $bgClass . '">';
    echo '<div class="container-fluid">';
    echo '<div class="alert alert-' . $safeType . '" role="alert">' . $message . '</div>';
    echo '</div>';
    echo '</body></html>';
    exit;
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

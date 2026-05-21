<?php

/**
 * public/_bootstrap.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

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
use OpenEMR\Modules\Institutional\Core\Repository\FacilityProfileRepository;
use OpenEMR\Modules\Institutional\Core\Service\FacilityManifestService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;
use OpenEMR\Modules\Institutional\Manifest\ContextManifest;
use OpenEMR\Modules\Institutional\Manifest\ManifestLoader;

require_once dirname(__DIR__, 4) . "/globals.php";

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$moduleRoot = dirname(__DIR__);
$manifest   = ManifestLoader::load($moduleRoot);
// Resolve logged-in user id first — needed by services that apply per-user
// profile overrides (preference reads check personal row before facility default).
$_oei_userId           = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$_oei_profileRepo      = new FacilityProfileRepository();
$_oei_facilityProfiles = new FacilityProfileService(null, $_oei_profileRepo, $_oei_userId);
$_oei_facilityManifest = new FacilityManifestService(null, $moduleRoot, $_oei_profileRepo, $_oei_userId);

// ── Care Context + facility resolution ─────────────────────────────────────
// Facility is the runtime anchor. Context lives inside the selected facility.
// $_oei_userId is already set above (needed earlier for service construction).

$_oei_requestedFacilityId = isset($_GET['facility_id'])
    ? (int)$_GET['facility_id']
    : (isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0);
$_oei_facilityId = $_oei_facilityProfiles->resolveFacilityId($_oei_requestedFacilityId, $_oei_userId);
$_oei_facilityProfiles->writeActiveFacilitySession($_oei_facilityId);
$_oei_facilityName = $_oei_facilityProfiles->getDisplayName($_oei_facilityId);
$manifest = $_oei_facilityManifest->applyToManifest($_oei_facilityId, $manifest);

if ($_oei_userId > 0 && $manifest->featureEnabled('context_manager')) {
    $activeContext = (new ContextService(new ContextRepository()))
        ->resolve($_oei_userId, $_oei_facilityId);
} else {
    $activeContext = $_oei_facilityProfiles->getDefaultContext($_oei_facilityId);
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
    $_oei_tsRepo = new \OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository();
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
    $base = rtrim($GLOBALS['webroot'] ?? '', '/')
        . '/interface/modules/custom_modules/oe-module-institutional/';

    if ($mode === 'local') {
        return $base . 'vendor/twbs/bootstrap/dist/css/bootstrap.min.css';
    }
    return "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css";
}

/**
 * Returns a <script> tag for Bootstrap 5 JS bundle (local or CDN).
 */
function institutional_bootstrap5_js_tag(): string
{
    global $manifest;
    $mode = 'cdn';
    if (isset($manifest) && is_object($manifest) && isset($manifest->ui)) {
        $mode = (string)($manifest->ui['bootstrap5_mode'] ?? 'cdn');
    }
    $base = rtrim($GLOBALS['webroot'] ?? '', '/')
        . '/interface/modules/custom_modules/oe-module-institutional/';

    if ($mode === 'local') {
        $src = $base . 'vendor/twbs/bootstrap/dist/js/bootstrap.bundle.min.js';
    } else {
        $src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
    }
    return '<script src="' . htmlspecialchars($src) . '"></script>';
}

/**
 * Returns the module-relative href for the oei-theme.css file.
 */
function institutional_theme_css_href(): string
{
    return rtrim($GLOBALS['webroot'] ?? '', '/')
        . '/interface/modules/custom_modules/oe-module-institutional/public/assets/oei-theme.css';
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
$_oei_settingsForTheme = new \OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository();
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
    $bgClass = $theme === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';
    $href    = (isset($manifest) && function_exists('institutional_bootstrap5_href'))
               ? institutional_bootstrap5_href($manifest)
               : (file_exists(__DIR__ . '/../vendor/twbs/bootstrap/dist/css/bootstrap.min.css')
                  ? (rtrim($GLOBALS['webroot'] ?? '', '/') . '/interface/modules/custom_modules/oe-module-institutional/vendor/twbs/bootstrap/dist/css/bootstrap.min.css')
                  : 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css');

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

// ── Setup wizard nudge ────────────────────────────────────────────────────
// Admin-only banner when the facility has not yet completed Setup Wizard.
// Session-cached so the DB is only hit once per login after setup is done.
if ($manifest->featureEnabled('settings') && !empty($_SESSION['authSuperUser'])) {
    $__ck = 'oei_setup_done_' . $_oei_facilityId;
    $__done = $_SESSION[$__ck] ?? null;
    if ($__done === null) {
        $__done = $_oei_facilityProfiles->isSetupCompleted($_oei_facilityId) ? 1 : 0;
        if ($__done) { $_SESSION[$__ck] = 1; }
    }
    if (!$__done) {
        $__wu = htmlspecialchars(
            ($GLOBALS['webroot'] ?? '')
            . '/interface/modules/custom_modules/oe-module-institutional/public/setup_wizard.php'
            . '?facility_id=' . urlencode((string)$_oei_facilityId));
        echo '<div id="oei-setup-nudge" role="alert" '
           . 'style="position:fixed;top:36px;left:0;right:0;z-index:9998;'
           . 'background:#1e3a5f;border-bottom:2px solid #3b82f6;'
           . 'color:#bfdbfe;font-size:12px;padding:5px 14px;'
           . 'display:flex;align-items:center;gap:10px;'
           . 'font-family:\x27Segoe UI\x27,system-ui,sans-serif;">'
           . '<span>&#9432;&nbsp;Facility setup incomplete for <strong>'
           . htmlspecialchars($_oei_facilityName)
           . '</strong></span>'
           . '<a href="' . $__wu . '" '
           . 'style="color:#93c5fd;font-weight:600;text-decoration:underline;white-space:nowrap;">'
           . 'Run Setup Wizard &rarr;</a>'
           . '<button type="button" '
           . 'onclick="document.getElementById(\x27oei-setup-nudge\x27).remove()" '
           . 'style="margin-left:auto;background:none;border:none;'
           . 'color:#93c5fd;cursor:pointer;font-size:16px;line-height:1;">&#x2715;</button>'
           . '</div>';
    }
    unset($__ck, $__done, $__wu);
}

// Clean up temporaries
unset($_oei_userId, $_oei_requestedFacilityId, $_oei_facilityId);


// ── Patient / user display helpers ──────────────────────────────────────────
// Cached batch lookups — single DB hit per unique id set per request.

/**
 * Batch-fetch patient display names ("Last, First") for a set of pids.
 * @param  int[]  $pids
 * @return array<int,string>  pid => "Last, First"
 */
function oei_patient_names(array $pids): array
{
    static $cache = [];
    $pids    = array_values(array_unique(array_filter(array_map('intval', $pids))));
    $missing = array_values(array_diff($pids, array_keys($cache)));
    if (!empty($missing) && function_exists('sqlStatement')) {
        $ph  = implode(',', array_fill(0, count($missing), '?'));
        $res = sqlStatement(
            "SELECT pid, fname, lname FROM patient_data WHERE pid IN ({$ph})",
            $missing
        );
        while ($row = sqlFetchArray($res)) {
            $p         = (int)$row['pid'];
            $cache[$p] = trim((string)$row['lname'] . ', ' . (string)$row['fname']);
        }
        foreach ($missing as $p) {
            if (!isset($cache[$p])) $cache[$p] = '';
        }
    }
    $result = [];
    foreach ($pids as $p) { $result[$p] = $cache[$p] ?? ''; }
    return $result;
}

/**
 * Batch-fetch user display names ("First Last") for a set of user ids.
 * @param  int[]  $ids
 * @return array<int,string>  id => "First Last"
 */
function oei_user_names(array $ids): array
{
    static $cache = [];
    $ids     = array_values(array_unique(array_filter(array_map('intval', $ids))));
    $missing = array_values(array_diff($ids, array_keys($cache)));
    if (!empty($missing) && function_exists('sqlStatement')) {
        $ph  = implode(',', array_fill(0, count($missing), '?'));
        $res = sqlStatement(
            "SELECT id, fname, lname FROM users WHERE id IN ({$ph})",
            $missing
        );
        while ($row = sqlFetchArray($res)) {
            $id         = (int)$row['id'];
            $cache[$id] = trim((string)$row['fname'] . ' ' . (string)$row['lname']);
        }
        foreach ($missing as $i) {
            if (!isset($cache[$i])) $cache[$i] = '';
        }
    }
    $result = [];
    foreach ($ids as $i) { $result[$i] = $cache[$i] ?? ''; }
    return $result;
}

/**
 * Format a patient for display: "Last, First (PID N)".
 * Falls back to "PID N" when name unavailable.
 * Returns HTML-escaped string ready for echo.
 * @param int               $pid
 * @param array<int,string> $patientNames  Result of oei_patient_names()
 */
function oei_fmt_patient(int $pid, array $patientNames): string
{
    $name = $patientNames[$pid] ?? '';
    if ($name !== '') {
        return htmlspecialchars($name)
             . ' <span class="text-muted small">(PID&nbsp;' . $pid . ')</span>';
    }
    return '<span class="text-muted">PID&nbsp;' . $pid . '</span>';
}




















<?php

/**
 * public/onboarding.php
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

/**
 * public/onboarding.php — Installation Readiness Checklist
 *
 * Runs a set of live database checks against the actual installed state
 * and reports PASS / WARN / FAIL per item. Designed for a Level 2
 * implementer to validate a new facility before going live, and to
 * troubleshoot issues without requiring database access.
 *
 * Check groups:
 *   1. SCHEMA       — core oei_* tables present and accessible
 *   2. CONFIGURATION — facility identity, locations, HL7 state
 *   3. USERS & ACCESS — providers, staff, care context assignments
 *   4. CLINICAL DATA — encounter linkage rate, fall risk coverage
 *   5. INTEGRATION   — HL7 log health, encounter registration
 *   6. MANIFEST      — manifest.json writable, profile sanity
 *
 * Read-only. Safe to run on production at any time.
 * No MAR code involved.
 */

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Manifest\ManifestWriter;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

AclGuard::requireAdmin();

$facilityId  = $_oei_facilityId ?? 1;
$moduleRoot  = dirname(__DIR__);
$manifestPath= $moduleRoot . '/manifest.json';
$href        = institutional_bootstrap5_href($manifest);
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

$settings    = (new SettingsRepository())->all($facilityId);
$writer      = new ManifestWriter($manifestPath);

// ── Check runner ──────────────────────────────────────────────────────────────

/** @var array<int,array{group:string,label:string,status:string,detail:string,action:string}> */
$checks = [];

/** @param 'PASS'|'WARN'|'FAIL' $status */
function obc(string $group, string $label, string $status, string $detail, string $action = ''): void {
    global $checks;
    $checks[] = compact('group', 'label', 'status', 'detail', 'action');
}

function ob_count(string $sql, array $params = []): int {
    if (!function_exists('sqlQuery')) return -1;
    try {
        $row = sqlQuery($sql, $params);
        return (int)($row ? array_values($row)[0] : 0);
    } catch (\Throwable) { return -1; }
}

function ob_table_exists(string $table): bool {
    if (!function_exists('sqlQuery')) return false;
    try {
        $r = sqlQuery("SELECT COUNT(*) c FROM information_schema.tables
                        WHERE table_schema = DATABASE() AND table_name = ?", [$table]);
        return (int)($r['c'] ?? 0) > 0;
    } catch (\Throwable) { return false; }
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. SCHEMA
// ─────────────────────────────────────────────────────────────────────────────

$coreTables = [
    'oei_episode', 'oei_triage', 'oei_episode_location',
    'oei_episode_status_history', 'oei_settings', 'oei_location',
];
$alTables   = ['oei_al_episode', 'oei_adl_record', 'oei_incident',
                'oei_fall_risk_assessment', 'oei_activity_log'];
$ipTables   = ['oei_ip_episode'];
$marTables  = ['oei_mar_order', 'oei_mar_administration'];
$auxTables  = ['oei_schema_version', 'oei_task', 'oei_episode_event',
                'oei_episode_disposition', 'oei_ereferral', 'oei_bh_safety',
                'oei_bh_boarding', 'oei_diversion', 'oei_hl7_outbound_log',
                'oei_facility_directory', 'oei_user_context'];

$allRequired = array_merge($coreTables, $alTables, $ipTables, $marTables, $auxTables);
$missing = [];
foreach ($allRequired as $t) {
    if (!ob_table_exists($t)) $missing[] = $t;
}

if (empty($missing)) {
    obc('Schema', 'All oei_* tables present', 'PASS',
        count($allRequired) . ' tables verified.');
} else {
    obc('Schema', 'Missing tables', 'FAIL',
        'Missing: ' . implode(', ', $missing),
        'Enable the module in Module Manager so table.sql installs the schema, or run table.sql manually.');
}

// Schema version
$sv = function_exists('sqlQuery')
    ? (sqlQuery("SELECT version FROM oei_schema_version ORDER BY applied_datetime DESC LIMIT 1") ?: [])
    : [];
$svVersion = (string)($sv['version'] ?? '—');
obc('Schema', 'Schema version', $svVersion !== '—' ? 'PASS' : 'WARN',
    "Applied version: {$svVersion}",
    $svVersion === '—' ? 'Enable the module in Module Manager (installs table.sql), or run table.sql manually.' : '');

// ─────────────────────────────────────────────────────────────────────────────
// 2. CONFIGURATION
// ─────────────────────────────────────────────────────────────────────────────

// Facility name
$fname = trim((string)($settings['facility_name'] ?? ''));
obc('Configuration', 'Facility display name', $fname !== '' ? 'PASS' : 'WARN',
    $fname !== '' ? "Set to: \"{$fname}\"" : 'Not set — will fall back to OpenEMR facility table name.',
    $fname !== '' ? '' : 'Set via Settings → Facility Identity.');

// Locations
$locCount = ob_count("SELECT COUNT(*) FROM oei_location WHERE facility_id=? AND is_active=1", [$facilityId]);
if ($locCount > 0) {
    obc('Configuration', 'Locations configured', 'PASS', "{$locCount} active location(s) defined.");
} elseif ($locCount === 0) {
    obc('Configuration', 'Locations configured', 'WARN',
        'No active locations in oei_location.',
        'Add rooms/beds via Bed Management before go-live.');
} else {
    obc('Configuration', 'Locations configured', 'WARN', 'Could not query oei_location.');
}

// Facility directory
$dirCount = ob_count("SELECT COUNT(*) FROM oei_facility_directory WHERE facility_id=? AND is_active=1", [$facilityId]);
obc('Configuration', 'Facility directory entries', $dirCount > 0 ? 'PASS' : 'WARN',
    $dirCount > 0 ? "{$dirCount} active directory entr" . ($dirCount === 1 ? 'y' : 'ies') . '.'
                  : 'No entries — e-referral destination lookup will be empty.',
    $dirCount > 0 ? '' : 'Populate via Admin → Facility Directory.');

// OBS runway warning
$runway = (int)($settings['obs_runway_warning_hours'] ?? 6);
obc('Configuration', 'OBS runway warning threshold', 'PASS',
    "Set to {$runway} hours. Default is 6.");

// AL-specific: fall risk thresholds
if ($manifest->featureEnabled('al_fall_risk')) {
    // No configurable threshold yet — check that the feature is on
    obc('Configuration', 'AL fall risk assessment enabled', 'PASS',
        'al_fall_risk feature is enabled in manifest.');
}

// HL7
$hl7Enabled = ($settings['hl7_enabled'] ?? '0') === '1';
if (!$hl7Enabled) {
    obc('Configuration', 'HL7 ADT outbound', 'PASS',
        'Disabled — safe for standalone or non-HL7 deployments.');
} else {
    $transport = (string)($settings['hl7_transport'] ?? 'MLLP');
    $endpoint  = $transport === 'MLLP'
        ? ($settings['hl7_mllp_host'] ?? '') . ':' . ($settings['hl7_mllp_port'] ?? '2575')
        : ($settings['hl7_http_url'] ?? '');
    $procId    = (string)($settings['hl7_processing_id'] ?? 'T');

    if ($endpoint === ':2575' || $endpoint === '') {
        obc('Configuration', 'HL7 ADT outbound', 'WARN',
            "Enabled ({$transport}) but endpoint appears unconfigured.",
            'Set the MLLP host/port or HTTP URL in Settings → HL7.');
    } else {
        $procLabel = $procId === 'P' ? 'PRODUCTION' : 'TEST';
        obc('Configuration', 'HL7 ADT outbound', $procId === 'T' ? 'WARN' : 'PASS',
            "Enabled. Transport: {$transport}. Endpoint: {$endpoint}. Processing ID: {$procLabel}.",
            $procId === 'T' ? 'Switch Processing ID to P (Production) after end-to-end validation.' : '');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 2b. SETTINGS VALIDATION
// ─────────────────────────────────────────────────────────────────────────────

// IP clinical defaults — only when ip_board is enabled
if ($manifest->featureEnabled('ip_board')) {
    $ipLosMs   = (int)($settings['ip_expected_los_medsurg']  ?? 0);
    $ipDisc    = (int)($settings['ip_discharge_target_hour'] ?? -1);
    $ipWarn    = (int)($settings['ip_los_warning_hours']     ?? 0);

    if ($ipLosMs > 0 && $ipDisc >= 0 && $ipDisc <= 23 && $ipWarn > 0) {
        obc('Settings', 'IP clinical defaults configured', 'PASS',
            "LOS Med/Surg={$ipLosMs}d, Discharge target={$ipDisc}h, Warning window={$ipWarn}h.");
    } else {
        obc('Settings', 'IP clinical defaults configured', 'WARN',
            'IP defaults not fully set — Floor Board LOS fallback and discharge target badge may not work.',
            'Set LOS targets per service and discharge target hour in Settings → Inpatient Clinical Defaults.');
    }
}

// AL vitals thresholds — only when al_board is enabled
if ($manifest->featureEnabled('al_board')) {
    $alBpH  = (int)  ($settings['al_bp_systolic_high']     ?? 0);
    $alBpL  = (int)  ($settings['al_bp_systolic_low']      ?? 0);
    $alSpo2 = (int)  ($settings['al_spo2_critical']        ?? 0);
    $alWt   = (float)($settings['al_weight_gain_alert_kg'] ?? 0);

    if ($alBpH > 0 && $alBpL > 0 && $alSpo2 > 0 && $alWt > 0) {
        obc('Settings', 'AL vitals alert thresholds', 'PASS',
            "BP {$alBpL}–{$alBpH} mmHg, SpO2 critical <{$alSpo2}%, weight gain >{$alWt} kg.");
    } else {
        obc('Settings', 'AL vitals alert thresholds', 'WARN',
            'AL vitals thresholds have not been explicitly set — factory defaults apply.',
            'Review Settings → AL Vitals Alert Thresholds with your medical director.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. USERS & ACCESS
// ─────────────────────────────────────────────────────────────────────────────

// Authorized providers
$providerCount = ob_count(
    "SELECT COUNT(*) FROM users WHERE authorized=1 AND active=1 AND username != 'admin'"
);
obc('Users & Access', 'Provider accounts (authorized=1)', $providerCount > 0 ? 'PASS' : 'WARN',
    $providerCount > 0 ? "{$providerCount} active provider account(s)."
                       : 'No active provider accounts besides admin.',
    $providerCount === 0 ? 'Create at least one authorized user for provider assignment in care plan/notes.' : '');

// Nurses / staff
$staffCount = ob_count(
    "SELECT COUNT(*) FROM users WHERE authorized=0 AND active=1"
);
obc('Users & Access', 'Staff / nurse accounts (authorized=0)', $staffCount > 0 ? 'PASS' : 'WARN',
    $staffCount > 0 ? "{$staffCount} active staff account(s)."
                    : 'No staff accounts found.',
    $staffCount === 0 ? 'Create nurse/aide accounts (authorized=0) for MAR and ADL charting.' : '');

// Care context assignments
if ($manifest->featureEnabled('context_manager')) {
    $ctxCount = ob_count("SELECT COUNT(*) FROM oei_user_context WHERE facility_id=?", [$facilityId]);
    obc('Users & Access', 'Care context assignments', $ctxCount > 0 ? 'PASS' : 'WARN',
        $ctxCount > 0 ? "{$ctxCount} user(s) have a saved care context for this facility."
                      : 'No care context records — all users will default to Full Access.',
        $ctxCount === 0 ? 'Assign contexts via Context Manager so nurses see only their relevant submodules.' : '');
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. CLINICAL DATA READINESS
// ─────────────────────────────────────────────────────────────────────────────

// Episodes
$epTotal = ob_count("SELECT COUNT(*) FROM oei_episode WHERE facility_id=?", [$facilityId]);
if ($epTotal === 0) {
    obc('Clinical Data', 'Episode records', 'WARN',
        'No episodes found for this facility.',
        'Normal if this is a fresh install before go-live. Run the demo seed for a populated training instance.');
} else {
    obc('Clinical Data', 'Episode records', 'PASS', "{$epTotal} total episode(s).");
}

// AL encounter linkage
if ($manifest->featureEnabled('al_board') && ob_table_exists('oei_al_episode')) {
    $alTotal  = ob_count("SELECT COUNT(*) FROM oei_al_episode ae
                          JOIN oei_episode e ON e.id=ae.episode_id
                          WHERE e.facility_id=?", [$facilityId]);
    $alLinked = ob_count("SELECT COUNT(*) FROM oei_al_episode ae
                          JOIN oei_episode e ON e.id=ae.episode_id
                          WHERE e.facility_id=? AND ae.encounter_id IS NOT NULL AND ae.encounter_id > 0",
                         [$facilityId]);
    if ($alTotal === 0) {
        obc('Clinical Data', 'AL encounter linkage', 'WARN',
            'No AL episodes yet.', 'Normal on a fresh install.');
    } elseif ($alLinked === $alTotal) {
        obc('Clinical Data', 'AL encounter linkage', 'PASS',
            "100% — all {$alTotal} AL episode(s) linked to OpenEMR encounter numbers.");
    } else {
        $pct = $alTotal > 0 ? round($alLinked / $alTotal * 100) : 0;
        obc('Clinical Data', 'AL encounter linkage', 'FAIL',
            "{$alLinked}/{$alTotal} ({$pct}%) AL episodes have a valid encounter number.",
            'Legacy rows missing encounter numbers — re-link via the episode edit page.');
    }
}

// IP encounter linkage
if ($manifest->featureEnabled('ip_board') && ob_table_exists('oei_ip_episode')) {
    $ipTotal  = ob_count("SELECT COUNT(*) FROM oei_ip_episode ip
                          JOIN oei_episode e ON e.id=ip.episode_id
                          WHERE e.facility_id=?", [$facilityId]);
    $ipLinked = ob_count("SELECT COUNT(*) FROM oei_ip_episode ip
                          JOIN oei_episode e ON e.id=ip.episode_id
                          WHERE e.facility_id=? AND ip.encounter_id IS NOT NULL AND ip.encounter_id > 0",
                         [$facilityId]);
    if ($ipTotal === 0) {
        obc('Clinical Data', 'IP encounter linkage', 'WARN', 'No inpatient episodes yet.', '');
    } elseif ($ipLinked === $ipTotal) {
        obc('Clinical Data', 'IP encounter linkage', 'PASS',
            "100% — all {$ipTotal} IP episode(s) linked to OpenEMR encounter numbers.");
    } else {
        $pct = $ipTotal > 0 ? round($ipLinked / $ipTotal * 100) : 0;
        obc('Clinical Data', 'IP encounter linkage', 'FAIL',
            "{$ipLinked}/{$ipTotal} ({$pct}%) IP episodes have a valid encounter number.",
            'Legacy rows missing encounter numbers — re-link via the episode edit page.');
    }
}

// AL fall risk coverage
if ($manifest->featureEnabled('al_fall_risk') && ob_table_exists('oei_fall_risk_assessment')) {
    $alResidents = ob_count("SELECT COUNT(*) FROM oei_al_episode ae
                              JOIN oei_episode e ON e.id=ae.episode_id
                              WHERE e.facility_id=? AND e.status='ACTIVE'", [$facilityId]);
    $alWithFra   = ob_count("SELECT COUNT(DISTINCT fra.episode_id)
                              FROM oei_fall_risk_assessment fra
                              JOIN oei_episode e ON e.id=fra.episode_id
                              WHERE e.facility_id=? AND e.status='ACTIVE'", [$facilityId]);
    if ($alResidents === 0) {
        obc('Clinical Data', 'AL fall risk coverage', 'WARN', 'No active AL residents.', '');
    } elseif ($alWithFra === $alResidents) {
        obc('Clinical Data', 'AL fall risk coverage', 'PASS',
            "All {$alResidents} active resident(s) have a fall risk assessment.");
    } else {
        $missing = $alResidents - $alWithFra;
        obc('Clinical Data', 'AL fall risk coverage', 'WARN',
            "{$missing} active resident(s) have no fall risk assessment on file.",
            'Complete Morse Fall Scale for each resident via their profile.');
    }
}

// Care plans vs episodes
$cpEpisodes = ob_count("SELECT COUNT(DISTINCT e.id) FROM oei_episode e
                         JOIN form_encounter fe ON fe.encounter = e.eid
                         WHERE e.facility_id=?", [$facilityId]);
// simplified: check form_care_plan has rows at all
$cpRows = ob_count("SELECT COUNT(*) FROM form_care_plan fcp
                    JOIN oei_episode e ON e.pid=fcp.pid
                    WHERE e.facility_id=? AND fcp.activity=1", [$facilityId]);
obc('Clinical Data', 'Active care plan entries', $cpRows > 0 ? 'PASS' : 'WARN',
    $cpRows > 0 ? "{$cpRows} active care plan row(s) across this facility's patients."
                : 'No active care plan entries found.',
    $cpRows === 0 ? 'Expected if this is a fresh install. Create care plans via resident/patient profiles.' : '');

// ─────────────────────────────────────────────────────────────────────────────
// 5. INTEGRATION
// ─────────────────────────────────────────────────────────────────────────────

// OpenEMR forms registration
$formsLinked = ob_count("SELECT COUNT(DISTINCT f.encounter) FROM forms f
                          JOIN oei_episode e ON e.pid=f.pid
                          WHERE e.facility_id=? AND f.formdir='newpatient' AND f.deleted=0",
                         [$facilityId]);
obc('Integration', 'OpenEMR encounter registration (forms table)', $formsLinked > 0 ? 'PASS' : 'WARN',
    $formsLinked > 0 ? "{$formsLinked} encounter(s) registered in OpenEMR forms table (formdir=newpatient)."
                     : 'No module encounters appear in OpenEMR forms table.',
    $formsLinked === 0 ? 'Encounters created via admission or intake should auto-register. Check EncounterResolver logs.' : '');

// HL7 outbound log health (last 24h if HL7 enabled)
if ($hl7Enabled && ob_table_exists('oei_hl7_outbound_log')) {
    $recentSent  = ob_count("SELECT COUNT(*) FROM oei_hl7_outbound_log
                              WHERE facility_id=? AND status='SENT'
                              AND sent_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)", [$facilityId]);
    $recentError = ob_count("SELECT COUNT(*) FROM oei_hl7_outbound_log
                              WHERE facility_id=? AND status IN ('ERROR','NACK')
                              AND sent_datetime >= DATE_SUB(NOW(), INTERVAL 24 HOUR)", [$facilityId]);
    if ($recentError > 0) {
        obc('Integration', 'HL7 outbound log (last 24h)', 'FAIL',
            "{$recentSent} sent, {$recentError} error/NACK in last 24 hours.",
            'Review HL7 Log for error details. Check endpoint reachability.');
    } elseif ($recentSent > 0) {
        obc('Integration', 'HL7 outbound log (last 24h)', 'PASS',
            "{$recentSent} message(s) sent, 0 errors in last 24 hours.");
    } else {
        obc('Integration', 'HL7 outbound log (last 24h)', 'WARN',
            'HL7 is enabled but no messages sent in the last 24 hours.',
            'Expected if no clinical activity today. Verify the endpoint is reachable.');
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// 6. MANIFEST
// ─────────────────────────────────────────────────────────────────────────────

obc('Manifest', 'manifest.json writable', $writer->canWrite() ? 'PASS' : 'WARN',
    $writer->canWrite()
        ? "File is writable — Manifest Editor can save changes."
        : "File is read-only. chmod 664 " . basename($manifestPath),
    $writer->canWrite() ? '' : 'Run: chmod 664 ' . $manifestPath);

// Smoke test feature
obc('Manifest', 'Smoke test feature enabled', $manifest->featureEnabled('smoke_test') ? 'PASS' : 'WARN',
    $manifest->featureEnabled('smoke_test')
        ? 'smoke_test is enabled — full schema validation available.'
        : 'smoke_test is disabled. Enable it in Manifest Editor for post-upgrade verification.',
    '');

// Count enabled features
try {
    $mdata    = $writer->read();
    $allFeats = (array)($mdata['features'] ?? []);
    $enCount  = count(array_filter($allFeats));
    $totCount = count($allFeats);
    obc('Manifest', 'Feature count', 'PASS',
        "{$enCount} of {$totCount} feature(s) enabled.");
} catch (\Throwable) {
    obc('Manifest', 'Feature count', 'WARN', 'Could not read manifest.json.');
}

// ─────────────────────────────────────────────────────────────────────────────
// Summarise
// ─────────────────────────────────────────────────────────────────────────────

$passCount = count(array_filter($checks, fn($c) => $c['status'] === 'PASS'));
$warnCount = count(array_filter($checks, fn($c) => $c['status'] === 'WARN'));
$failCount = count(array_filter($checks, fn($c) => $c['status'] === 'FAIL'));
$total     = count($checks);

$overallStatus  = $failCount > 0 ? 'FAIL' : ($warnCount > 0 ? 'WARN' : 'PASS');
$overallBadge   = match ($overallStatus) {
    'PASS' => 'success', 'WARN' => 'warning', default => 'danger'
};
$overallIcon    = match ($overallStatus) {
    'PASS' => '✅', 'WARN' => '⚠️', default => '❌'
};

// Group checks for display
$byGroup = [];
foreach ($checks as $c) {
    $byGroup[$c['group']][] = $c;
}

// Badge helper
function ob_badge(string $status): string {
    return match ($status) {
        'PASS' => 'text-bg-success',
        'WARN' => 'text-bg-warning text-dark',
        'FAIL' => 'text-bg-danger',
        default => 'text-bg-secondary',
    };
}
function ob_icon(string $status): string {
    return match ($status) { 'PASS' => '✅', 'WARN' => '⚠️', 'FAIL' => '❌', default => '•' };
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Onboarding Checklist') ?> — <?= xlt('Institutional') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php if ($href): ?><link rel="stylesheet" href="<?= htmlspecialchars($href) ?>"><?php endif; ?>
  <style>
    .check-row    { display:flex; align-items:flex-start; gap:.6rem; padding:.4rem 0;
                    border-bottom:1px solid var(--bs-border-color-translucent); }
    .check-row:last-child { border-bottom:0; }
    .check-label  { font-size:.875rem; font-weight:500; min-width:0; flex:1; }
    .check-detail { font-size:.8rem; color:var(--bs-secondary-color); }
    .check-action { font-size:.78rem; color:#0d6efd; }
    .group-card   { margin-bottom:1rem; }
    .group-header-bar { font-size:.8rem; text-transform:uppercase;
                        letter-spacing:.06em; font-weight:600; }
    .kpi-val      { font-size:1.6rem; font-weight:700; line-height:1; }
    .kpi-label    { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:var(--bs-secondary-color); }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3" style="max-width:860px;">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0">✅ <?= xlt('Onboarding Checklist') ?></h4>
      <div class="text-muted small"><?= xlt('Live readiness check for') ?>
        <?= htmlspecialchars((string)($settings['facility_name'] ?: "Facility {$facilityId}")) ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="onboarding.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
        🔄 <?= xlt('Re-run') ?>
      </a>
      <?php if ($manifest->featureEnabled('smoke_test')): ?>
      <a href="smoke_test.php?verbose=1" class="btn btn-sm btn-outline-secondary" target="_blank">
        🔬 <?= xlt('Full Smoke Test') ?>
      </a>
      <?php endif; ?>
      <a href="manifest_editor.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-primary">
        ⚙️ <?= xlt('Manifest Editor') ?>
      </a>
    </div>
  </div>

  <!-- Summary KPIs -->
  <div class="row g-3 mb-4">
    <div class="col-4">
      <div class="card text-center shadow-sm border-0 bg-success bg-opacity-10">
        <div class="card-body py-3">
          <div class="kpi-val text-success"><?= $passCount ?></div>
          <div class="kpi-label">Pass</div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card text-center shadow-sm border-0 bg-warning bg-opacity-10">
        <div class="card-body py-3">
          <div class="kpi-val text-warning"><?= $warnCount ?></div>
          <div class="kpi-label">Warning</div>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card text-center shadow-sm border-0 bg-danger bg-opacity-10">
        <div class="card-body py-3">
          <div class="kpi-val text-danger"><?= $failCount ?></div>
          <div class="kpi-label">Fail</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Overall status banner -->
  <div class="alert alert-<?= $overallBadge === 'warning' ? 'warning' : ($overallBadge === 'success' ? 'success' : 'danger') ?> d-flex align-items-center gap-2 mb-4">
    <span style="font-size:1.5rem;"><?= $overallIcon ?></span>
    <div>
      <?php if ($overallStatus === 'PASS'): ?>
        <strong><?= xlt('Ready for go-live.') ?></strong>
        <?= xlt('All checks passed. Review any warnings before opening to clinical staff.') ?>
      <?php elseif ($overallStatus === 'WARN'): ?>
        <strong><?= xlt('Ready with warnings.') ?></strong>
        <?= xlt('No blocking failures — review warnings before go-live. Some will self-resolve once clinical activity begins.') ?>
      <?php else: ?>
        <strong><?= xlt('Not ready — action required.') ?></strong>
        <?= xlt('One or more failing checks must be resolved before go-live.') ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Check groups -->
  <?php foreach ($byGroup as $groupName => $groupChecks): ?>
  <?php
  $gFail = count(array_filter($groupChecks, fn($c) => $c['status'] === 'FAIL'));
  $gWarn = count(array_filter($groupChecks, fn($c) => $c['status'] === 'WARN'));
  $gBadge= $gFail > 0 ? 'danger' : ($gWarn > 0 ? 'warning' : 'success');
  $gIcon = $gFail > 0 ? '❌' : ($gWarn > 0 ? '⚠️' : '✅');
  ?>
  <div class="card group-card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="group-header-bar"><?= $gIcon ?> <?= htmlspecialchars($groupName) ?></span>
      <span class="badge text-bg-<?= $gBadge ?>">
        <?= count($groupChecks) ?> <?= xlt('checks') ?>
      </span>
    </div>
    <div class="card-body py-2">
      <?php foreach ($groupChecks as $chk): ?>
      <div class="check-row">
        <span class="badge <?= ob_badge($chk['status']) ?> flex-shrink-0" style="min-width:52px;text-align:center;">
          <?= ob_icon($chk['status']) ?> <?= htmlspecialchars($chk['status']) ?>
        </span>
        <div class="min-width-0 flex-fill">
          <div class="check-label"><?= htmlspecialchars($chk['label']) ?></div>
          <?php if ($chk['detail'] !== ''): ?>
          <div class="check-detail"><?= htmlspecialchars($chk['detail']) ?></div>
          <?php endif; ?>
          <?php if ($chk['action'] !== ''): ?>
          <div class="check-action mt-1">→ <?= htmlspecialchars($chk['action']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <!-- Footer note -->
  <div class="text-muted small text-center mt-3">
    <?= xlt('This page runs read-only queries — safe on production. Re-run after making any configuration changes.') ?>
    · <?= xlt('Checked at') ?> <?= date('Y-m-d H:i:s') ?>
    · Facility ID: <?= (int)$facilityId ?>
  </div>

</div><!-- /container -->
<?php if ($href): ?>
<?= institutional_bootstrap5_js_tag() ?>
<?php endif; ?>
</body>
</html>













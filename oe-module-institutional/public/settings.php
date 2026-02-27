<?php

ob_start();

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Domain\TriageStandard;
use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('settings')) {
    die(xlt('Settings is disabled by manifest'));
}

AclGuard::requireAdmin();

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$repo = new SettingsRepository();

function oei_json_response(array $payload, int $code = 200): void
{
    // Ensure nothing else (like bootstrap chrome) contaminates JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload);
    exit;
}


// ── AJAX: save triage badge color ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['ajax_action'] ?? '') === 'triage_color') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        oei_json_response(['ok' => false, 'error' => 'CSRF validation failed'], 400);
    }

    $userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

    $std = strtoupper(trim((string)($_POST['standard'] ?? '')));
    $level = (int)($_POST['level'] ?? 0);
    $bg = strtoupper(trim((string)($_POST['color'] ?? '')));

    if (!in_array($std, ['ESI', 'MTS', 'CTAS'], true) || $level < 1 || $level > 5 || !preg_match('/^#[0-9A-F]{6}$/', $bg)) {
        oei_json_response(['ok' => false, 'error' => 'Invalid input'], 400);
    }

    $key = "triage_color_{$std}_{$level}";
    $repo->setMany($facilityId, [$key => $bg], $userId);

    $fg = TriageStandard::idealTextColor($bg);

    oei_json_response(['ok' => true, 'key' => $key, 'standard' => $std, 'level' => $level, 'bg' => $bg, 'fg' => $fg]);
}


$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }
    $userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;
    $keys = array_keys(SettingsRepository::defaults());
    $values = [];
    foreach ($keys as $k) {
        if ($k === 'hl7_enabled') {
            $values[$k] = isset($_POST[$k]) ? '1' : '0';
        } elseif (array_key_exists($k, $_POST)) {
            $values[$k] = trim((string)$_POST[$k]);
        }
    }
    $repo->setMany($facilityId, $values, $userId);
    $saved = true;
}

$settings = $repo->all($facilityId);

$activeStd = (string)($settings['triage_standard'] ?? 'ESI');
$triageStandard = new TriageStandard($activeStd);
$triageStandard->applyColorOverridesFromSettings($settings);

$csrf = CsrfUtils::collectCsrfToken();
$href = institutional_bootstrap5_href($manifest);

if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= xlt('Institutional Settings') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href) : ?>
        <link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php
    endif; ?>
    <?php if ($manifest->featureEnabled('mts_triage')) : ?>
        <style><?= $triageStandard->cssRules() ?></style>
    <?php endif; ?>
</head>
<body class="bg-light" data-active-standard="<?= htmlspecialchars($activeStd) ?>">
    <div class="container py-4" style="max-width: 860px;">

        <div class="d-flex align-items-center justify-content-between mb-3">
            <h1 class="h4 mb-0"><?= xlt('Institutional Settings') ?></h1>
            <div class="d-flex gap-2">
                <?php if ($manifest->featureEnabled('hl7_adt')) : ?>
                    <a class="btn btn-sm btn-outline-secondary"
                        href="hl7_log.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('HL7 Log') ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary"
                    href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
            </div>
        </div>

        <?php if ($saved) : ?>
            <div class="alert alert-success py-2"><?= xlt('Settings saved.') ?></div>
        <?php endif; ?>

        <form method="post" class="row g-0">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">

            <!-- ── Facility Identity ──────────────────────────────────────────── -->
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <span class="fw-semibold"><?= xlt('Facility Identity') ?></span>
                        <span class="badge text-bg-secondary"><?= xlt('Module-Managed') ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-8">
                                <label class="form-label" for="facility_name">
                                    <?= xlt('Facility Display Name') ?>
                                </label>
                                <input type="text"
                                    id="facility_name"
                                    name="facility_name"
                                    class="form-control"
                                    value="<?= htmlspecialchars((string)($settings['facility_name'] ?? '')) ?>"
                                    placeholder="<?= xla('e.g. Memorial Hospital ED') ?>">
                                <div class="form-text">
                                    <?= xlt('This name is displayed in the multi-facility dashboard and command center. Leave blank to fall back to the OpenEMR facility table name.') ?>
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label"><?= xlt('Facility ID') ?></label>
                                <div class="form-control-plaintext fw-semibold text-muted">
                                    <?= htmlspecialchars((string)$facilityId) ?>
                                </div>
                                <div class="form-text"><?= xlt('Read-only. Set via URL parameter.') ?></div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <?php if ($manifest->featureEnabled('mts_triage')) : ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold"><?= xlt('Triage Standard') ?></div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= xlt('Select the acuity system used at this facility. ESI is standard for North America. MTS (Manchester Triage System) is standard in the UK, Europe, Australasia, Portugal, and Brazil. CTAS (Canadian Triage and Acuity Scale) is used in Canada. All standards use a 5 severity scale stored identically in the database only labels and badge colors change.') ?>
                        </p>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold"><?= xlt('Acuity Standard') ?></label>
                                <select name="triage_standard" class="form-select">
                                    <?php foreach (TriageStandard::allDefinitions() as $__tsCode => $__tsDef) : ?>
                                        <option value="<?= htmlspecialchars($__tsCode) ?>"
                                            <?= ($settings['triage_standard'] ?? 'ESI') === $__tsCode ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($__tsDef['short_name'] .  $__tsDef['name']) ?>
                                            (<?= htmlspecialchars($__tsDef['region']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <?= xlt('Change takes effect immediately for all users at this facility. Existing acuity values are not modified.') ?>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label fw-semibold"><?= xlt('Active Standard Preview') ?></label>
                                <div class="border rounded p-3 bg-light">
                                    <div class="fw-bold" id="oei-ts-name"><?= htmlspecialchars($triageStandard->getName()) ?></div>
                                    <div class="text-muted small mb-2" id="oei-ts-region"><?= htmlspecialchars($triageStandard->getRegion()) ?></div>
                                    <div class="d-flex gap-2 flex-wrap" id="oei-ts-badges">
                                        <?php for ($__l = 1; $__l <= 5; $__l++) : ?>
                                            <span class="badge <?= htmlspecialchars($triageStandard->badgeClass($__l)) ?>" style="font-size:.8rem; padding:.35em .6em;">
                                              <?= htmlspecialchars($triageStandard->shortLabel($__l)) ?>
                                            </span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($manifest->featureEnabled('mts_triage')) : ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                            <span><?= xlt('Acuity Badge Colors') ?></span>
                            <span class="small text-muted"><?= xlt('Click a badge to set its color') ?></span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach (['ESI', 'MTS', 'CTAS'] as $__std) :
                                    $__ts = new TriageStandard($__std);
                                    if (method_exists($__ts, 'applyColorOverridesFromSettings')) {
                                        $__ts->applyColorOverridesFromSettings($settings);
                                    }
                                    $__def = TriageStandard::allDefinitions()[$__std] ?? ['name' => $__std, 'region' => ''];
                                    ?>
                                    <div class="col-12 col-lg-4">
                                        <div class="border rounded p-3 bg-light h-100">
                                            <div class="fw-bold"><?= htmlspecialchars((string)$__def['short_name']) ?> — <?= htmlspecialchars((string)$__def['name']) ?></div>
                                            <div class="text-muted small mb-2"><?= htmlspecialchars((string)$__def['region']) ?></div>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <?php for ($__l = 1; $__l <= 5; $__l++) :
                                                    $__colors = method_exists($__ts, 'levelColors') ? $__ts->levelColors($__std, $__l) : ['bg' => '#999999', 'fg' => '#ffffff'];
                                                    ?>
                                                    <span
                                                        class="badge oei-triage-color-badge"
                                                        role="button"
                                                        tabindex="0"
                                                        data-standard="<?= htmlspecialchars($__std) ?>"
                                                        data-level="<?= (int)$__l ?>"
                                                        title="<?= htmlspecialchars($__ts->levelLabel($__l)) ?>"
                                                        style="cursor:pointer; font-size:.8rem; padding:.35em .6em; background: <?= htmlspecialchars($__colors['bg']) ?>; color: <?= htmlspecialchars($__colors['fg']) ?>;">
                                                    <?= htmlspecialchars($__ts->shortLabel($__l)) ?>
                                </span>
                                                <?php endfor; ?>
                                            </div>
                                            <div class="small text-muted mt-2">
                                                <?= xlt('Tip: click a badge, pick a color, and it saves instantly for this facility.') ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- ── Vitals Monitoring ──────────────────────────────────────────── -->
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <span class="fw-semibold"><?= xlt('Vitals Monitoring') ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('ED Vitals Interval') ?></label>
                                <div class="input-group">
                                    <input type="number" name="vitals_interval_ed_min" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['vitals_interval_ed_min'] ?? '120')) ?>" min="1">
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('OBS Vitals Interval') ?></label>
                                <div class="input-group">
                                    <input type="number" name="vitals_interval_obs_min" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['vitals_interval_obs_min'] ?? '240')) ?>" min="1">
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('Vitals History Window') ?></label>
                                <div class="input-group">
                                    <input type="number" name="vitals_window_hours" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['vitals_window_hours'] ?? '12')) ?>" min="1">
                                    <span class="input-group-text">hours</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Alert Thresholds ───────────────────────────────────────────── -->
            <div class="col-12">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <span class="fw-semibold"><?= xlt('Alert Thresholds') ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('LWBS Alert Threshold') ?>
                                    <span class="text-muted fw-normal small">(min waiting without room)</span></label>
                                <div class="input-group">
                                    <input type="number" name="lwbs_threshold_min" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['lwbs_threshold_min'] ?? '120')) ?>" min="1">
                                    <span class="input-group-text">min</span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('BH Boarding Alert Threshold') ?></label>
                                <div class="input-group">
                                    <input type="number" name="boarding_alert_hours" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['boarding_alert_hours'] ?? '4')) ?>" min="1">
                                    <span class="input-group-text">hours</span>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label"><?= xlt('Obs Runway Warning') ?></label>
                                <div class="input-group">
                                    <input type="number" name="obs_runway_warning_hours" class="form-control"
                                        value="<?= htmlspecialchars((string)($settings['obs_runway_warning_hours'] ?? '6')) ?>" min="1">
                                    <span class="input-group-text">hours</span>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <!-- ── HL7 ADT Outbound ───────────────────────────────────────────── -->
            <?php if ($manifest->featureEnabled('hl7_adt')) : ?>
                <div class="col-12">
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <span class="fw-semibold"><?= xlt('HL7 v2 ADT Outbound') ?></span>
                            <span class="badge <?= ($settings['hl7_enabled'] ?? '0') === '1' ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= ($settings['hl7_enabled'] ?? '0') === '1' ? xlt('Enabled') : xlt('Disabled') ?>
          </span>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="hl7_enabled" id="hl7_enabled"
                                            value="1" <?= ($settings['hl7_enabled'] ?? '0') === '1' ? 'checked' : '' ?>
                                            onchange="toggleHl7Fields()">
                                        <label class="form-check-label fw-semibold" for="hl7_enabled">
                                            <?= xlt('Enable HL7 ADT outbound messaging') ?>
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        <?= xlt('When enabled, ADT events fire automatically on arrival, location change, obs start, and discharge.') ?>
                                    </div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold"><?= xlt('Processing ID') ?></label>
                                    <select name="hl7_processing_id" class="form-select">
                                        <option value="T" <?= ($settings['hl7_processing_id'] ?? 'T') === 'T' ? 'selected' : '' ?>>T — Test</option>
                                        <option value="P" <?= ($settings['hl7_processing_id'] ?? 'T') === 'P' ? 'selected' : '' ?>>P — Production</option>
                                    </select>
                                    <div class="form-text"><?= xlt('Use T until the integration is validated end-to-end.') ?></div>
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold"><?= xlt('Transport') ?></label>
                                    <select name="hl7_transport" class="form-select" onchange="toggleTransport(this.value)">
                                        <option value="MLLP" <?= ($settings['hl7_transport'] ?? 'MLLP') === 'MLLP' ? 'selected' : '' ?>>MLLP (TCP)</option>
                                        <option value="HTTP" <?= ($settings['hl7_transport'] ?? 'MLLP') === 'HTTP' ? 'selected' : '' ?>>HTTP / HTTPS</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-4"><!-- spacer --></div>

                                <div id="mllpFields" class="col-12">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-8">
                                            <label class="form-label"><?= xlt('MLLP Host') ?></label>
                                            <input type="text" name="hl7_mllp_host" class="form-control font-monospace"
                                                value="<?= htmlspecialchars((string)($settings['hl7_mllp_host'] ?? '127.0.0.1')) ?>"
                                                placeholder="127.0.0.1">
                                            <div class="form-text">
                                                <?= xlt('Hostname or IP of your integration engine (Mirth Connect, Rhapsody, etc.)') ?>
                                            </div>
                                        </div>
                                        <div class="col-12 col-md-4">
                                            <label class="form-label"><?= xlt('MLLP Port') ?></label>
                                            <input type="number" name="hl7_mllp_port" class="form-control font-monospace"
                                                value="<?= htmlspecialchars((string)($settings['hl7_mllp_port'] ?? '2575')) ?>"
                                                placeholder="2575">
                                        </div>
                                    </div>
                                </div>

                                <div id="httpFields" class="col-12" style="display:none;">
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label"><?= xlt('HTTP Endpoint URL') ?></label>
                                            <input type="url" name="hl7_http_url" class="form-control font-monospace"
                                                value="<?= htmlspecialchars((string)($settings['hl7_http_url'] ?? '')) ?>"
                                                placeholder="https://mirth.example.com/hl7">
                                        </div>
                                        <div class="col-12 col-md-6">
                                            <label class="form-label"><?= xlt('HTTP Bearer Token') ?>
                                                <span class="text-muted fw-normal small">(optional)</span></label>
                                            <input type="password" name="hl7_http_bearer" class="form-control font-monospace"
                                                value="<?= htmlspecialchars((string)($settings['hl7_http_bearer'] ?? '')) ?>"
                                                autocomplete="off">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"><?= xlt('Sending Application') ?>
                                        <span class="text-muted fw-normal small">(MSH.3)</span></label>
                                    <input type="text" name="hl7_sending_app" class="form-control font-monospace"
                                        value="<?= htmlspecialchars((string)($settings['hl7_sending_app'] ?? 'OE-INSTITUTIONAL')) ?>"
                                        placeholder="OE-INSTITUTIONAL">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"><?= xlt('Sending Facility') ?>
                                        <span class="text-muted fw-normal small">(MSH.4)</span></label>
                                    <input type="text" name="hl7_sending_facility" class="form-control font-monospace"
                                        value="<?= htmlspecialchars((string)($settings['hl7_sending_facility'] ?? 'OPENEMR')) ?>"
                                        placeholder="OPENEMR">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"><?= xlt('Receiving Application') ?>
                                        <span class="text-muted fw-normal small">(MSH.5)</span></label>
                                    <input type="text" name="hl7_receiving_app" class="form-control font-monospace"
                                        value="<?= htmlspecialchars((string)($settings['hl7_receiving_app'] ?? '')) ?>"
                                        placeholder="MIRTH">
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"><?= xlt('Receiving Facility') ?>
                                        <span class="text-muted fw-normal small">(MSH.6)</span></label>
                                    <input type="text" name="hl7_receiving_facility" class="form-control font-monospace"
                                        value="<?= htmlspecialchars((string)($settings['hl7_receiving_facility'] ?? '')) ?>"
                                        placeholder="HOSPITAL">
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; // hl7_adt ?>

            <div class="col-12">
                <button class="btn btn-primary px-4"><?= xlt('Save Settings') ?></button>
            </div>

        </form>
    </div>

    <script>
        function toggleTransport(val) {
            document.getElementById('mllpFields').style.display = (val === 'MLLP') ? '' : 'none';
            document.getElementById('httpFields').style.display = (val === 'HTTP') ? '' : 'none';
        }

        (function () {
            var sel = document.querySelector('[name="hl7_transport"]');
            if (sel) {
                toggleTransport(sel.value);
            }
        }());

        <?php if ($manifest->featureEnabled('mts_triage')) : ?>
        // Dynamic triage standard preview — updates badges without a page reload
        var OEI_TRIAGE_DEFS = <?= TriageStandard::definitionsJson() ?>;

        function updateTriagePreview(code) {
            var def = OEI_TRIAGE_DEFS[code];
            if (!def) return;

            // Update name and region labels
            var nameEl = document.getElementById('oei-ts-name');
            var regionEl = document.getElementById('oei-ts-region');
            if (nameEl) nameEl.textContent = def.name;
            if (regionEl) regionEl.textContent = def.region;

            // Rebuild badge list
            var container = document.getElementById('oei-ts-badges');
            if (!container) return;
            container.innerHTML = '';

            // Inject fresh CSS for the selected standard
            var styleEl = document.getElementById('oei-ts-style');
            if (!styleEl) {
                styleEl = document.createElement('style');
                styleEl.id = 'oei-ts-style';
                document.head.appendChild(styleEl);
            }
            var css = '';
            for (var lvl in def.levels) {
                var l = def.levels[lvl];
                css += '.' + def.css_prefix + '-' + lvl +
                    ' { background: ' + l.color_bg +
                    ' !important; color: ' + l.color_fg + ' !important; } ';
                var span = document.createElement('span');
                span.className = 'badge ' + def.css_prefix + '-' + lvl;
                span.style.fontSize = '.8rem';
                span.style.padding = '.35em .6em';
                span.textContent = l.short;
                container.appendChild(span);
            }
            styleEl.textContent = css;
        }

        (function () {
            var sel = document.querySelector('[name="triage_standard"]');
            if (sel) {
                sel.addEventListener('change', function () {
                    updateTriagePreview(this.value);
                });
            }
        }());
        <?php endif; ?>
    </script>

    <script>
        (function () {
            const csrf = document.querySelector('input[name="csrf_token_form"]')?.value || '';
            const facilityId = <?= (int)$facilityId ?>;
            const PALETTE = ["#B71C1C", "#D32F2F", "#E65100", "#EF6C00", "#F9A825", "#FDD835", "#2E7D32", "#388E3C", "#1565C0", "#1E88E5", "#0277BD", "#00838F", "#00695C", "#5E35B1", "#6A1B9A", "#8E24AA", "#4E342E", "#455A64", "#263238", "#000000"];

            // floating picker
            const picker = document.createElement('div');
            picker.id = 'oei-triage-color-picker';
            picker.style.position = 'absolute';
            picker.style.zIndex = '9999';
            picker.style.display = 'none';
            picker.className = 'card shadow';
            picker.innerHTML = `
    <div class="card-body p-2">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="small fw-semibold" id="oei-picker-title">Badge color</div>
        <button type="button" class="btn-close" aria-label="Close"></button>
      </div>
      <div class="list-group mb-2" id="oei-picker-list" style="max-height:240px;overflow:auto;"></div>
      <div class="small text-muted" id="oei-picker-status"></div>
    </div>
  `;
            document.body.appendChild(picker);

            const btnClose = picker.querySelector('.btn-close');
            const list = picker.querySelector('#oei-picker-list');
            const statusEl = picker.querySelector('#oei-picker-status');
            const titleEl = picker.querySelector('#oei-picker-title');

            function hidePicker() {
                picker.style.display = 'none';
                picker.dataset.standard = '';
                picker.dataset.level = '';
                picker.dataset.targetId = '';
                statusEl.textContent = '';
            }

            function populateList(current) {
                list.innerHTML = '';
                const cur = (current || '').toUpperCase();
                for (const color of PALETTE) {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action py-1 px-2 d-flex align-items-center gap-2';
                    btn.dataset.color = color;
                    if (color.toUpperCase() === cur) {
                        btn.classList.add('active');
                    }
                    const sw = document.createElement('span');
                    sw.className = 'rounded border';
                    sw.style.width = '16px';
                    sw.style.height = '16px';
                    sw.style.display = 'inline-block';
                    sw.style.background = color;
                    const txt = document.createElement('span');
                    txt.className = 'small';
                    txt.textContent = color;
                    btn.appendChild(sw);
                    btn.appendChild(txt);
                    list.appendChild(btn);
                }
            }

            function positionNear(el) {
                const r = el.getBoundingClientRect();
                const top = window.scrollY + r.bottom + 6;
                const left = window.scrollX + Math.min(r.left, window.innerWidth - 260);
                picker.style.top = top + 'px';
                picker.style.left = left + 'px';
            }

            async function saveColor(std, level, color) {
                statusEl.textContent = 'Saving…';
                const fd = new FormData();
                fd.append('csrf_token_form', csrf);
                fd.append('ajax_action', 'triage_color');
                fd.append('standard', std);
                fd.append('level', String(level));
                fd.append('color', color);

                const res = await fetch(window.location.href, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                });
                const ct = res.headers.get('content-type') || '';
                const text = await res.text();
                let json = null;
                try {
                    json = JSON.parse(text);
                } catch (err) {
                    // Most common cause: session/login redirect returned HTML
                    statusEl.textContent = 'Save failed (non-JSON response). Are you still logged in as admin?';
                    console.error('Non-JSON response:', text.slice(0, 200));
                    return null;
                }
                if (!json.ok) {
                    statusEl.textContent = json.error || 'Save failed';
                    return null;
                }
                statusEl.textContent = 'Saved';
                return json;
            }

            function updateBadges(std, level, bg, fg) {
                document.querySelectorAll(`.oei-triage-color-badge[data-standard="${std}"][data-level="${level}"]`).forEach(b => {
                    b.style.background = bg;
                    b.style.color = fg;
                });
            }

            function updateActivePreview(std, level, bg, fg) {
                const activeStd = document.body.getAttribute('data-active-standard') || '';
                if (activeStd.toUpperCase() !== String(std).toUpperCase()) return;
                const wrap = document.getElementById('oei-ts-badges');
                if (!wrap) return;
                const badges = wrap.querySelectorAll('.badge');
                const idx = (parseInt(level, 10) - 1);
                if (idx >= 0 && idx < badges.length) {
                    badges[idx].style.background = bg;
                    badges[idx].style.color = fg;
                }
            }


            // open picker when clicking any badge
            document.addEventListener('click', (e) => {
                const badge = e.target.closest('.oei-triage-color-badge');
                if (badge) {
                    const std = badge.dataset.standard;
                    const level = badge.dataset.level;
                    const current = (badge.style.background || '#999999').toUpperCase();
                    picker.dataset.standard = std;
                    picker.dataset.level = level;
                    picker.dataset.targetId = badge.dataset.badgeId || '';
                    titleEl.textContent = `${std} level ${level}`;
                    populateList(current);
                    positionNear(badge);
                    picker.style.display = 'block';
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }

                // close if clicking outside
                if (!picker.contains(e.target)) {
                    hidePicker();
                }
            });

            // keyboard support: Enter/Space on badge
            document.addEventListener('keydown', (e) => {
                const badge = e.target.closest?.('.oei-triage-color-badge');
                if (!badge) return;
                if (e.key === 'Enter' || e.key === ' ') {
                    badge.click();
                    e.preventDefault();
                }
            });

            btnClose.addEventListener('click', hidePicker);

            list.addEventListener('click', async (e) => {
                const btn = e.target.closest('button[data-color]');
                if (!btn) return;
                const color = (btn.dataset.color || '').toUpperCase();
                // UI selection highlight
                list.querySelectorAll('button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const std = picker.dataset.standard;
                const level = parseInt(picker.dataset.level || '0', 10);
                if (!std || !level) return;

                const saved = await saveColor(std, level, color);
                if (saved) {
                    updateBadges(saved.standard, saved.level, saved.bg, saved.fg);
                    updateActivePreview(saved.standard, saved.level, saved.bg, saved.fg);
                }
            });

            // keep picker positioned on scroll/resize
            window.addEventListener('scroll', () => {
                if (picker.style.display === 'none') return;
                const std = picker.dataset.standard;
                const level = picker.dataset.level;
                const el = document.querySelector(`.oei-triage-color-badge[data-standard="${std}"][data-level="${level}"]`);
                if (el) positionNear(el);
            }, {passive: true});
            window.addEventListener('resize', () => {
                if (picker.style.display === 'none') return;
                const std = picker.dataset.standard;
                const level = picker.dataset.level;
                const el = document.querySelector(`.oei-triage-color-badge[data-standard="${std}"][data-level="${level}"]`);
                if (el) positionNear(el);
            });
        })();
    </script>

</body>
</html>



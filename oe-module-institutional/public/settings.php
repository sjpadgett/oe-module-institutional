<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('settings')) {
    die(xlt('Settings is disabled by manifest'));
}

AclGuard::requireAdmin();

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$repo       = new SettingsRepository();

$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }
    $userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;
    $keys   = array_keys(SettingsRepository::defaults());
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
$csrf     = CsrfUtils::collectCsrfToken();
$href     = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Institutional Settings') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 860px;">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Institutional Settings') ?></h1>
    <div class="d-flex gap-2">
      <?php if ($manifest->featureEnabled('hl7_adt')): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="hl7_log.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('HL7 Log') ?></a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
    </div>
  </div>

  <?php if ($saved): ?>
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
    <?php if ($manifest->featureEnabled('hl7_adt')): ?>
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
                <option value="HTTP" <?= ($settings['hl7_transport'] ?? 'MLLP') === 'HTTP'  ? 'selected' : '' ?>>HTTP / HTTPS</option>
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
    document.getElementById('httpFields').style.display = (val === 'HTTP')  ? '' : 'none';
}
(function () {
    var sel = document.querySelector('[name="hl7_transport"]');
    if (sel) { toggleTransport(sel.value); }
}());
</script>
</body>
</html>

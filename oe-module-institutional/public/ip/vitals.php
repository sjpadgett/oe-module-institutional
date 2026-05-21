<?php

/**
 * public/ip/vitals.php
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
 * public/ip/vitals.php — Periodic Vitals Monitoring (Inpatient)
 *
 * Thin IP wrapper around IpVitalsController / AlVitalsRepository.
 * The backend reads/writes oei_triage (same table as AL and ED).
 * This wrapper provides the IP nav strip and absolute URL routing.
 *
 * Inpatient alert thresholds are tighter than AL:
 *   BP: >180 or <80 systolic   (AL: >160/<90)
 *   HR: >120 or <45            (AL: >100/<50)
 *   SpO₂: <90                  (AL: <93)
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpVitals\Controller\IpVitalsController;

if (!$manifest->featureEnabled('ip_vitals')) {
    oei_exit_with_alert(xlt('Vitals monitoring is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_oei_ip_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

if ($episodeId === 0) {
    header('Location: ' . $_oei_ip_base . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new IpVitalsController();
$data       = $controller->handle($episodeId, $pid, $facilityId, $userId);
$thr        = $data['thresholds'];

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Vitals Monitoring');
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$activePage = 'vitals';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .vital-card  { border-left: 3px solid #457b9d; }
    .history-table td { font-size: .83rem; }
    .trend-canvas { width: 100%; height: 80px; display: block; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">💓 <?= xlt('Vitals Monitoring') ?></h5>
  <a href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
     class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Profile') ?>
  </a>
</div>

<?php if ($data['flash']): ?>
<div class="alert alert-<?= str_contains($data['flash'], xlt('Error')) ? 'danger' : 'success' ?> py-2">
    <?= htmlspecialchars($data['flash']) ?>
</div>
<?php endif; ?>

<?php if ($data['alerts']): ?>
<div class="alert alert-danger py-2">
  <strong><?= xlt('Clinical Alerts') ?></strong>
  <ul class="mb-0 mt-1">
    <?php foreach ($data['alerts'] as $alert): ?>
    <li><?= htmlspecialchars($alert) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Entry Form -->
  <div class="col-lg-4">
    <div class="card vital-card">
      <div class="card-header bg-primary text-white fw-semibold">
        ➕ <?= xlt('Record Vitals') ?>
      </div>
      <div class="card-body">
        <form method="POST"
              action="<?= htmlspecialchars($_oei_ip_base) ?>vitals.php?facility_id=<?= urlencode((string)$facilityId) ?>">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid"         value="<?= $pid ?>">

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('BP Systolic') ?></label>
              <input type="number" class="form-control form-control-sm" name="bp_systolic"
                     min="50" max="300" placeholder="mmHg">
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('BP Diastolic') ?></label>
              <input type="number" class="form-control form-control-sm" name="bp_diastolic"
                     min="30" max="200" placeholder="mmHg">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Heart Rate') ?></label>
              <input type="number" class="form-control form-control-sm" name="hr"
                     min="20" max="250" placeholder="bpm">
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Resp Rate') ?></label>
              <input type="number" class="form-control form-control-sm" name="rr"
                     min="4" max="60" placeholder="/min">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('SpO₂') ?></label>
              <input type="number" class="form-control form-control-sm" name="spo2"
                     min="50" max="100" placeholder="%">
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Temp (°F)') ?></label>
              <input type="number" class="form-control form-control-sm" name="temp_f"
                     min="90" max="110" step="0.1" placeholder="°F">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Weight (kg)') ?></label>
              <input type="number" class="form-control form-control-sm" name="weight_kg"
                     min="20" max="300" step="0.1" placeholder="kg">
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Pain (0–10)') ?></label>
              <input type="number" class="form-control form-control-sm" name="pain_score"
                     min="0" max="10">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('GCS (3–15)') ?></label>
              <input type="number" class="form-control form-control-sm" name="gcs"
                     min="3" max="15">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-sm"><?= xlt('Notes') ?></label>
            <textarea class="form-control form-control-sm" name="notes" rows="2"
                      placeholder="<?= xlt('Observations, clinical notes…') ?>"></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-sm w-100">
            💾 <?= xlt('Save Vitals') ?>
          </button>
        </form>

        <!-- Latest vitals reference -->
        <?php if ($data['latest']): $lv = $data['latest']; ?>
        <div class="mt-3 p-2 bg-body-secondary rounded small">
          <div class="fw-semibold text-muted mb-1"><?= xlt('Previous reading') ?>
            (<?= htmlspecialchars(substr($lv['noted_datetime'], 5, 11)) ?>)
          </div>
            <?php if ($lv['bp_systolic']): ?>
          <div>BP <?= $lv['bp_systolic'] ?>/<?= $lv['bp_diastolic'] ?>
               · HR <?= $lv['hr'] ?>
          </div>
          <?php endif; ?>
            <?php if ($lv['spo2']): ?>
          <div>SpO₂ <?= $lv['spo2'] ?>%
                <?php if ($lv['weight_kg']): ?> · Wt <?= number_format($lv['weight_kg'], 1) ?> kg<?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Trend chart + History -->
  <div class="col-lg-8">

    <?php if (count($data['weight_trend']) > 1): ?>
    <div class="card mb-3">
      <div class="card-header small fw-semibold">⚖ <?= xlt('Weight Trend (kg)') ?></div>
      <div class="card-body py-2">
        <canvas id="weightChart" class="trend-canvas"></canvas>
      </div>
    </div>
    <?php endif; ?>

    <!-- History Table — inpatient thresholds -->
    <div class="card">
      <div class="card-header small fw-semibold">📋 <?= xlt('Vitals History') ?></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 history-table">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Date/Time') ?></th>
              <th><?= xlt('BP') ?></th>
              <th><?= xlt('HR') ?></th>
              <th><?= xlt('SpO₂') ?></th>
              <th><?= xlt('RR') ?></th>
              <th><?= xlt('Temp °F') ?></th>
              <th><?= xlt('Wt kg') ?></th>
              <th><?= xlt('GCS') ?></th>
              <th><?= xlt('Pain') ?></th>
              <th><?= xlt('Notes') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($data['history'] as $v): ?>
          <tr>
            <td class="text-nowrap"><?= htmlspecialchars(substr($v['noted_datetime'], 0, 16)) ?></td>
            <td class="<?= ($v['bp_systolic'] !== null && ($v['bp_systolic'] > $thr['bp_high'] || $v['bp_systolic'] < $thr['bp_low'])) ? 'text-danger fw-semibold' : '' ?>">
                <?= $v['bp_systolic'] !== null ? $v['bp_systolic'] . '/' . $v['bp_diastolic'] : '—' ?>
            </td>
            <td class="<?= ($v['hr'] !== null && ($v['hr'] > $thr['hr_high'] || $v['hr'] < $thr['hr_low'])) ? 'text-danger' : '' ?>">
                <?= $v['hr'] ?? '—' ?>
            </td>
            <td class="<?= ($v['spo2'] !== null && $v['spo2'] < $thr['spo2_critical']) ? 'text-danger fw-semibold' : '' ?>">
                <?= $v['spo2'] !== null ? $v['spo2'] . '%' : '—' ?>
            </td>
            <td class="<?= ($v['rr'] !== null && ($v['rr'] > 24 || $v['rr'] < 8)) ? 'text-danger' : '' ?>">
                <?= $v['rr'] ?? '—' ?>
            </td>
            <td class="<?= ($v['temp_f'] !== null && ($v['temp_f'] > 100.4 || $v['temp_f'] < 96.8)) ? 'text-danger' : '' ?>">
                <?= $v['temp_f'] !== null ? number_format($v['temp_f'], 1) . '°' : '—' ?>
            </td>
            <td><?= $v['weight_kg'] !== null ? number_format($v['weight_kg'], 1) : '—' ?></td>
            <td class="<?= ($v['gcs'] !== null && $v['gcs'] < 13) ? 'text-danger fw-semibold' : '' ?>">
                <?= $v['gcs'] ?? '—' ?>
            </td>
            <td class="<?= ($v['pain_score'] !== null && $v['pain_score'] >= 7) ? 'text-danger' : '' ?>">
                <?= $v['pain_score'] ?? '—' ?>
            </td>
            <td class="text-muted" style="max-width:160px;white-space:normal;">
                <?= htmlspecialchars(mb_strimwidth((string)($v['notes'] ?? ''), 0, 60, '…')) ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$data['history']): ?>
          <tr>
            <td colspan="10" class="text-muted text-center py-3">
                <?= xlt('No vitals recorded yet.') ?>
            </td>
          </tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<script>
(function() {
    const weights = <?= json_encode($data['weight_trend']) ?>;
    if (weights.length < 2) return;
    const canvas = document.getElementById('weightChart');
    if (!canvas) return;
    const W = canvas.parentElement.offsetWidth - 16;
    canvas.width = W; canvas.height = 80;
    const ctx = canvas.getContext('2d');
    const min = Math.min(...weights) - 0.5;
    const max = Math.max(...weights) + 0.5;
    const range = max - min;
    const step  = W / (weights.length - 1);
    const pad   = 4;
    ctx.strokeStyle = 'rgba(128,128,128,0.2)';
    ctx.setLineDash([4, 4]);
    [min + range * 0.25, min + range * 0.75].forEach(v => {
        const y = 80 - pad - ((v - min) / range) * (80 - pad * 2);
        ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(W, y); ctx.stroke();
    });
    ctx.setLineDash([]);
    ctx.beginPath();
    weights.forEach((w, i) => {
        const x = i * step;
        const y = 80 - pad - ((w - min) / range) * (80 - pad * 2);
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.strokeStyle = '#457b9d'; ctx.lineWidth = 2; ctx.stroke();
    ctx.font = '10px system-ui'; ctx.textAlign = 'center';
    weights.forEach((w, i) => {
        const x = i * step;
        const y = 80 - pad - ((w - min) / range) * (80 - pad * 2);
        ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2);
        ctx.fillStyle = '#1d3557'; ctx.fill();
        if (i === 0 || i === weights.length - 1 || Math.abs(w - weights[i-1]) >= 0.5) {
            ctx.fillStyle = '#555';
            ctx.fillText(w.toFixed(1), x, y - 6);
        }
    });
})();
</script>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>
















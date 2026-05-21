<?php

/**
 * public/hbc/vitals.php
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
 * public/hbc/vitals.php — Home-Based Care Vitals Monitoring
 *
 * Uses HbcVitalsController which delegates to SharedVitalsController.
 * HBC nav strip replaces AL resident nav.
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVitals\Controller\HbcVitalsController;

if (!$manifest->featureEnabled('hbc_vitals')) {
    oei_exit_with_alert(xlt('Vitals monitoring is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId); exit;
}

$controller    = new HbcVitalsController();
$data          = $controller->handle($episodeId, $pid, $facilityId, $userId);

// Patient context resolved by HbcVitalsController.
$hbcNavPatient = $data['patient'] ?? null;

$_oei_csrf  = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Vitals Monitoring');
$activePage = 'vitals';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$q = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= ($_oei_theme ?? 'light') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .vital-card    { border-left:3px solid #4a7c59; }
    .history-table td { font-size:.83rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>

<?php foreach ($data['alerts'] ?? [] as $alert): ?>
<div class="alert alert-warning py-2 mb-2">⚠ <?= htmlspecialchars($alert) ?></div>
<?php endforeach; ?>
<?php if (!empty($data['flash'])): ?>
<div class="alert alert-success py-2 mb-3"><?= htmlspecialchars($data['flash']) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <!-- Record form -->
  <div class="col-lg-5">
    <div class="card vital-card">
      <div class="card-header fw-semibold small">+ <?= xlt('Record Vitals') ?></div>
      <div class="card-body">
        <form method="POST"
              action="<?= htmlspecialchars($_hbcBase . 'vitals.php?facility_id=' . $facilityId
                      . '&episode_id=' . $episodeId . '&pid=' . $pid) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_oei_csrf) ?>">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid"        value="<?= $pid ?>">
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('BP Sys / Dia') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="bp_systolic"  class="form-control" min="60"  max="260" placeholder="120">
                <span class="input-group-text">/</span>
                <input type="number" name="bp_diastolic" class="form-control" min="30"  max="160" placeholder="80">
              </div>
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('HR') ?></label>
              <input type="number" name="hr" class="form-control form-control-sm" min="20" max="250">
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('SpO₂%') ?></label>
              <input type="number" name="spo2" class="form-control form-control-sm" min="50" max="100">
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('RR') ?></label>
              <input type="number" name="rr" class="form-control form-control-sm" min="4" max="60">
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('Temp °F') ?></label>
              <input type="number" step="0.1" name="temp_f" class="form-control form-control-sm" min="90" max="110">
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('Wt kg') ?></label>
              <input type="number" step="0.1" name="weight_kg" class="form-control form-control-sm" min="10" max="300">
            </div>
            <div class="col-3">
              <label class="form-label form-label-sm"><?= xlt('Pain 0–10') ?></label>
              <input type="number" name="pain_score" class="form-control form-control-sm" min="0" max="10">
            </div>
            <div class="col-12">
              <textarea name="notes" class="form-control form-control-sm" rows="2"
                        placeholder="<?= xla('Observations…') ?>"></textarea>
            </div>
          </div>
          <button type="submit" class="btn btn-success btn-sm mt-2 w-100">
            💾 <?= xlt('Save Vitals') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- History -->
  <div class="col-lg-7">
    <div class="card vital-card">
      <div class="card-header fw-semibold small">📋 <?= xlt('Vitals History') ?></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 history-table">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Date/Time') ?></th>
              <th><?= xlt('BP') ?></th>
              <th><?= xlt('HR') ?></th>
              <th><?= xlt('SpO₂') ?></th>
              <th><?= xlt('Temp') ?></th>
              <th><?= xlt('Wt kg') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($data['history'] ?? [] as $v): ?>
          <tr>
            <td class="text-nowrap small"><?= htmlspecialchars(substr((string)($v['noted_datetime']??''),0,16)) ?></td>
            <td class="<?= ($v['bp_systolic']!==null && ($v['bp_systolic']>160||$v['bp_systolic']<90)) ? 'text-danger fw-semibold':'' ?>">
              <?= $v['bp_systolic']!==null ? $v['bp_systolic'].'/'.$v['bp_diastolic'] : '—' ?>
            </td>
            <td class="<?= ($v['hr']!==null&&($v['hr']>110||$v['hr']<50))?'text-danger':'' ?>">
              <?= $v['hr'] ?? '—' ?>
            </td>
            <td class="<?= ($v['spo2']!==null&&$v['spo2']<93)?'text-danger fw-semibold':'' ?>">
              <?= $v['spo2']!==null ? $v['spo2'].'%' : '—' ?>
            </td>
            <td class="<?= ($v['temp_f']!==null&&($v['temp_f']>100.4||$v['temp_f']<96.8))?'text-danger':'' ?>">
              <?= $v['temp_f']!==null ? number_format((float)$v['temp_f'],1).'°' : '—' ?>
            </td>
            <td><?= $v['weight_kg']!==null ? number_format((float)$v['weight_kg'],1) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($data['history'])): ?>
          <tr><td colspan="6" class="text-muted small py-3 text-center">
            <?= xlt('No vitals recorded yet.') ?>
          </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>













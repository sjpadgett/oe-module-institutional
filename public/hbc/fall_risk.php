<?php

/**
 * public/hbc/fall_risk.php
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
 * public/hbc/fall_risk.php — Morse Fall Scale Assessment (Home-Based Care)
 *
 * Uses HbcFallRiskController which wraps shared FallRiskController.
 * Writes to oei_fall_risk_assessment (episode-agnostic table).
 * HBC nav strip replaces AL resident nav.
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcFallRisk\Controller\HbcFallRiskController;

if (!$manifest->featureEnabled('hbc_fall_risk')) {
    oei_exit_with_alert(xlt('Fall Risk Assessment is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId); exit;
}

$controller = new HbcFallRiskController();
$data       = $controller->handle($episodeId, $facilityId, $userId);
$patient    = $data['patient'];
$mfsItems   = $data['mfs_items'];
$prefill    = $data['prefill'];

$_oei_csrf  = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Fall Risk Assessment (Morse)');
$activePage = 'fall_risk';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$pid        = (int)($patient['pid'] ?? 0);
$q          = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;

// Nav pre-population
$hbcNavPatient = null;
if (function_exists('sqlQuery') && $episodeId > 0) {
    $nr = sqlQuery(
        "SELECT pd.fname, pd.lname, pd.pid, hbc.referral_status,
                hbc.urgency, hbc.service_city, hbc.service_state_province,
                hbc.primary_diagnosis
         FROM oei_episode e
         JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
         JOIN patient_data pd ON pd.pid = e.pid
         WHERE e.id = ? LIMIT 1", [$episodeId]
    );
    $hbcNavPatient = $nr ?: null;
}
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
    .mfs-item-card  { border-left:3px solid #e76f51; }
    .score-preview  { font-size:2.5rem; font-weight:700; line-height:1; }
    .history-row td { font-size:.82rem; }
    .risk-LOW       { color:#198754; } .risk-MODERATE { color:#fd7e14; } .risk-HIGH { color:#dc3545; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>

<?php if (!empty($data['flash'])): ?>
<div class="alert alert-<?= str_contains($data['flash'], xlt('Error')) ? 'danger' : 'success' ?> py-2 mb-3">
  <?= htmlspecialchars($data['flash']) ?>
</div>
<?php endif; ?>

<!-- Reassessment alert -->
<?php if (!empty($data['due_alert'])): ?>
<div class="alert alert-warning py-2 mb-3">⚠ <?= htmlspecialchars($data['due_alert']) ?></div>
<?php endif; ?>

<div class="row g-3">
  <!-- Assessment form -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold small">Morse Fall Scale Assessment</div>
      <div class="card-body">
        <form method="POST"
              action="<?= htmlspecialchars($_hbcBase . 'fall_risk.php?facility_id=' . $facilityId
                       . '&episode_id=' . $episodeId) ?>">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid"        value="<?= $pid ?>">

          <?php foreach ($mfsItems as $item): ?>
          <div class="card mfs-item-card mb-2">
            <div class="card-body py-2">
              <div class="fw-semibold small mb-1"><?= htmlspecialchars(xlt($item['label'])) ?></div>
              <?php foreach ($item['options'] as $val => $optLabel): ?>
              <div class="form-check form-check-inline">
                <input class="form-check-input mfs-radio" type="radio"
                       name="mfs_<?= htmlspecialchars($item['key']) ?>"
                       id="mfs_<?= htmlspecialchars($item['key']) ?>_<?= $val ?>"
                       value="<?= $val ?>"
                       data-score="<?= $val ?>"
                       <?= ($prefill[$item['key']] ?? null) == $val ? 'checked' : '' ?>
                       onchange="updateScore()">
                <label class="form-check-label small"
                       for="mfs_<?= htmlspecialchars($item['key']) ?>_<?= $val ?>">
                  <?= htmlspecialchars(xlt($optLabel)) ?> (<?= $val ?>)
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>

          <div class="text-center my-3">
            <div class="score-preview" id="scoreDisplay">0</div>
            <div class="small text-muted"><?= xlt('Total Score') ?></div>
            <div id="riskLabel" class="fw-semibold mt-1">—</div>
          </div>

          <div class="mb-3">
            <label class="form-label form-label-sm"><?= xlt('Notes') ?></label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
          </div>
          <input type="hidden" name="total_score" id="totalScore" value="0">
          <input type="hidden" name="risk_level"  id="riskLevel"  value="LOW">

          <button type="submit" class="btn btn-warning w-100 fw-semibold">
            <?= xlt('Save Assessment') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- History -->
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header fw-semibold small">📋 <?= xlt('Assessment History') ?></div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Date') ?></th>
              <th><?= xlt('Score') ?></th>
              <th><?= xlt('Risk') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($data['history'] ?? [] as $h): ?>
          <tr class="history-row">
            <td><?= htmlspecialchars(substr((string)($h['assessed_datetime']??''),0,10)) ?></td>
            <td><?= (int)($h['total_score']??0) ?></td>
            <td><span class="risk-<?= htmlspecialchars($h['risk_level']??'LOW') ?>">
              <?= htmlspecialchars(FallRiskLevel::label($h['risk_level']??'LOW')) ?>
            </span></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($data['history'])): ?>
          <tr><td colspan="3" class="text-muted small py-2"><?= xlt('No assessments yet.') ?></td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

</div>
<script>
const THRESHOLDS = { LOW: [0,24], MODERATE: [25,44], HIGH: [45,125] };
const LABELS     = { LOW:'<?= xlt('Low Risk') ?>', MODERATE:'<?= xlt('Moderate Risk') ?>', HIGH:'<?= xlt('High Risk') ?>' };
function updateScore() {
    let total = 0;
    document.querySelectorAll('.mfs-radio:checked').forEach(r => { total += parseInt(r.dataset.score)||0; });
    document.getElementById('scoreDisplay').textContent = total;
    document.getElementById('totalScore').value = total;
    let level = 'LOW';
    if (total >= 45) level = 'HIGH'; else if (total >= 25) level = 'MODERATE';
    document.getElementById('riskLevel').value = level;
    const lbl = document.getElementById('riskLabel');
    lbl.textContent = LABELS[level];
    lbl.className = 'fw-semibold mt-1 risk-' + level;
}
window.addEventListener('load', updateScore);
</script>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>










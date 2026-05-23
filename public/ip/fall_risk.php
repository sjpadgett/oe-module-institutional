<?php

/**
 * public/ip/fall_risk.php
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
 * public/ip/fall_risk.php — Morse Fall Scale Assessment (Inpatient)
 *
 * Reuses the shared FallRisk infrastructure (FallRiskController /
 * FallRiskRepository / oei_fall_risk_assessment) with an IP-specific
 * patient context query and the inpatient nav strip.
 *
 * Regulatory note: CMS Conditions of Participation require fall risk
 * assessment on admission and reassessment at clinically significant
 * change. 28-day reassessment alert matches AL standard.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpFallRisk\Controller\IpFallRiskController;

if (!$manifest->featureEnabled('ip_fall_risk')) {
    oei_exit_with_alert(xlt('Inpatient Fall Risk Assessment is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_oei_ip_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';

if ($episodeId === 0) {
    header('Location: ' . $_oei_ip_base . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new IpFallRiskController();
$data       = $controller->handle($episodeId, $facilityId, $userId);
$patient    = $data['patient'];
$mfsItems   = $data['mfs_items'];
$prefill    = $data['prefill'];

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Fall Risk Assessment (Morse)');
$activePage = 'fall_risk';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

// For the nav partial
$ipNavPatient = $patient
    ? ['fname' => $patient['fname'], 'lname' => $patient['lname'],
       'bed' => $patient['room'], 'unit' => $patient['unit'],
       'pid' => $patient['pid']]
    : null;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .mfs-item-card  { border-left: 3px solid #e76f51; }
    .score-preview  { font-size:2.5rem; font-weight:700; line-height:1; }
    .history-row td { font-size:.82rem; }
    .risk-LOW       { color:#198754; }
    .risk-MODERATE  { color:#fd7e14; }
    .risk-HIGH      { color:#dc3545; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">
    ⚠️ <?= xlt('Morse Fall Scale Assessment') ?>
    <?php if ($patient): ?>
      &mdash; <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?>
      <span class="badge bg-<?= htmlspecialchars(FallRiskLevel::badge($patient['current_risk_level'])) ?> ms-1">
        <?= htmlspecialchars(FallRiskLevel::label($patient['current_risk_level'])) ?>
      </span>
    <?php endif; ?>
  </h5>
  <a href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?episode_id=<?= $episodeId ?>&facility_id=<?= $facilityId ?>"
     class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Profile') ?>
  </a>
</div>

<?php if ($data['flash']): ?>
<div class="alert alert-<?= str_contains($data['flash'], xlt('Error')) ? 'danger' : 'success' ?> py-2">
  <?= htmlspecialchars($data['flash']) ?>
</div>
<?php endif; ?>

<?php if ($data['reassess_alert']): ?>
<div class="alert alert-warning py-2">
  ⚠ <?= $data['days_since'] === null
      ? xlt('No Morse Fall Scale assessment on file. Complete one now — required on all admissions.')
      : sprintf(xlt('Reassessment overdue: last assessed %d days ago.'), $data['days_since']) ?>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Assessment Form -->
  <div class="col-lg-6">
    <div class="card mfs-item-card">
      <div class="card-header fw-semibold bg-danger text-white">
        📋 <?= xlt('New Assessment') ?>
      </div>
      <div class="card-body">
        <form method="POST" id="mfsForm"
              action="<?= htmlspecialchars($_oei_ip_base . 'fall_risk.php?facility_id=' . $facilityId) ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_oei_csrf) ?>">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">

          <?php foreach ($mfsItems as $key => $item): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold small">
              <?= htmlspecialchars($item['label']) ?>
            </label>
            <?php foreach ($item['options'] as $score => $label): ?>
            <div class="form-check">
              <input class="form-check-input mfs-radio" type="radio"
                     name="<?= htmlspecialchars($key) ?>"
                     id="<?= htmlspecialchars($key) ?>_<?= $score ?>"
                     value="<?= $score ?>"
                     data-score="<?= $score ?>"
                     <?= ((int)($prefill['mfs_' . $key] ?? 0)) === $score ? 'checked' : '' ?>>
              <label class="form-check-label small"
                     for="<?= htmlspecialchars($key) ?>_<?= $score ?>">
                <?= htmlspecialchars($label) ?>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>

          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= xlt('Clinical Notes') ?></label>
            <textarea class="form-control form-control-sm" name="notes" rows="2"
                      placeholder="<?= xla('Precautions ordered, interventions, observations…') ?>"></textarea>
          </div>

          <button type="submit" class="btn btn-danger w-100">
            💾 <?= xlt('Save Assessment') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Score Preview + History -->
  <div class="col-lg-6">

    <div class="card mb-3">
      <div class="card-header fw-semibold small"><?= xlt('Live Score Preview') ?></div>
      <div class="card-body text-center py-4">
        <div class="score-preview mb-1" id="scoreDisplay">
          <?= $data['latest'] ? $data['latest']['total_score'] : '0' ?>
        </div>
        <div class="text-muted small mb-2"><?= xlt('Morse Fall Scale Total') ?></div>
        <div id="riskBadge" class="fs-5 fw-semibold risk-<?= $data['latest'] ? $data['latest']['risk_level'] : 'LOW' ?>">
          <?= htmlspecialchars(FallRiskLevel::label($data['latest']['risk_level'] ?? 'LOW')) ?>
        </div>
        <div class="mt-3 small text-muted">
          <span class="me-3 risk-LOW">0–24 = <?= xlt('Low') ?></span>
          <span class="me-3 risk-MODERATE">25–44 = <?= xlt('Moderate') ?></span>
          <span class="risk-HIGH">45+ = <?= xlt('High') ?></span>
        </div>
      </div>
    </div>

    <?php if ($data['history']): ?>
    <div class="card">
      <div class="card-header small fw-semibold">📅 <?= xlt('Assessment History') ?></div>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 history-row">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Date') ?></th>
              <th><?= xlt('Score') ?></th>
              <th><?= xlt('Risk') ?></th>
              <th><?= xlt('By') ?></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($data['history'] as $a): ?>
          <tr>
            <td class="text-nowrap"><?= htmlspecialchars(substr($a['assessed_datetime'], 0, 10)) ?></td>
            <td class="fw-bold"><?= $a['total_score'] ?></td>
            <td>
              <span class="badge bg-<?= htmlspecialchars(FallRiskLevel::badge($a['risk_level'])) ?>">
                <?= htmlspecialchars(FallRiskLevel::label($a['risk_level'])) ?>
              </span>
            </td>
            <td class="text-muted"><?= htmlspecialchars($a['assessed_by']) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
</div>

<script>
(function() {
    const scoreEl = document.getElementById('scoreDisplay');
    const riskEl  = document.getElementById('riskBadge');
    const THRESHOLDS = [
        [0,  24, 'LOW',      'Low Risk'],
        [25, 44, 'MODERATE', 'Moderate Risk'],
        [45, 999,'HIGH',     'High Risk'],
    ];
    function levelFromScore(score) {
        for (const [min, max, code, label] of THRESHOLDS) {
            if (score >= min && score <= max) return { code, label };
        }
        return { code: 'LOW', label: 'Low Risk' };
    }
    function updateScore() {
        let total = 0;
        document.querySelectorAll('.mfs-radio:checked').forEach(r => {
            total += parseInt(r.dataset.score, 10);
        });
        scoreEl.textContent = total;
        const { code, label } = levelFromScore(total);
        riskEl.textContent = label;
        riskEl.className = 'fs-5 fw-semibold risk-' + code;
    }
    document.querySelectorAll('.mfs-radio').forEach(r => r.addEventListener('change', updateScore));
    updateScore();
})();
</script>
</body>
</html>







<?php

/**
 * public/al/fall_risk.php
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
 * public/al/fall_risk.php — Morse Fall Scale Reassessment
 *
 * Regulatory requirement: reassess every 30 days minimum.
 * Saving a new assessment updates oei_al_episode.fall_risk_level instantly
 * so the Resident Board reflects the change without any additional steps.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Controller\FallRiskController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

if (!$manifest->featureEnabled('al_fall_risk')) {
    oei_exit_with_alert(xlt('Fall Risk Assessment is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

if ($episodeId === 0) {
    // No episode context — send to Board to select a resident
    header('Location: board.php?facility_id=' . $facilityId
         . '&notice=select_resident');
    exit;
}

$controller = new FallRiskController();
$data       = $controller->handle($episodeId, $facilityId, $userId);
$patient    = $data['patient'];
$mfsItems   = $data['mfs_items'];
$prefill    = $data['prefill'];

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle = xlt('Fall Risk Assessment (Morse)');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

$activePage  = 'fall_risk';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= ($_oei_theme ?? 'light') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .mfs-item-card  { border-left: 3px solid #e76f51; }
    .score-preview  { font-size:2.5rem; font-weight:700; line-height:1; }
    .history-row td { font-size:.82rem; }
    .risk-LOW      { color:#198754; }
    .risk-MODERATE { color:#fd7e14; }
    .risk-HIGH     { color:#dc3545; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php';
?>
<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">
      ⚠ <?= xlt('Morse Fall Scale Assessment') ?>
      <?php if ($patient): ?>
        — <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?>
        <span class="badge bg-<?= htmlspecialchars(FallRiskLevel::badge($patient['current_risk_level'])) ?> ms-1">
            <?= htmlspecialchars(FallRiskLevel::label($patient['current_risk_level'])) ?>
        </span>
      <?php endif; ?>
    </h5>
  </div>
  <a href="profile.php?episode_id=<?= $episodeId ?>" class="btn btn-sm btn-outline-secondary">
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
      ? xlt('No Morse Fall Scale assessment on file. Please complete one now.')
      : sprintf(xlt('Reassessment overdue: last assessed %d days ago (30-day schedule).'), $data['days_since']) ?>
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
        <form method="POST" id="mfsForm">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">

          <?php foreach ($mfsItems as $key => $item): ?>
                <?php $fieldKey = str_replace('_', '', $key); ?>
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
                      placeholder="<?= xlt('Observations, precautions, interventions ordered…') ?>"></textarea>
          </div>

          <button type="submit" class="btn btn-danger w-100">
            💾 <?= xlt('Save Assessment') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Live Score Preview + History -->
  <div class="col-lg-6">

    <!-- Score Preview Card -->
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

    <!-- Assessment History -->
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
              <th><?= xlt('Notes') ?></th>
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
            <td class="text-muted" style="max-width:160px;white-space:normal;">
                <?= htmlspecialchars(mb_strimwidth($a['notes'], 0, 60, '…')) ?>
            </td>
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
        [0,  24, 'LOW',      'Low Risk',      'risk-LOW'],
        [25, 44, 'MODERATE', 'Moderate Risk', 'risk-MODERATE'],
        [45, 999,'HIGH',     'High Risk',     'risk-HIGH'],
    ];

    function levelFromScore(score) {
        for (const [min, max, code, label, cls] of THRESHOLDS) {
            if (score >= min && score <= max) return { code, label, cls };
        }
        return { code: 'LOW', label: 'Low Risk', cls: 'risk-LOW' };
    }

    function updateScore() {
        let total = 0;
        document.querySelectorAll('.mfs-radio:checked').forEach(r => {
            total += parseInt(r.dataset.score, 10);
        });
        scoreEl.textContent = total;
        const { label, cls } = levelFromScore(total);
        riskEl.textContent = label;
        riskEl.className = 'fs-5 fw-semibold ' + cls;
    }

    document.querySelectorAll('.mfs-radio').forEach(r => {
        r.addEventListener('change', updateScore);
    });

    updateScore();
})();
</script>
</body>
</html>










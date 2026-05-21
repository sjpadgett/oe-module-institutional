<?php

/**
 * public/hbc/discharge.php
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
 * public/hbc/discharge.php — HBC Service Closure / Discharge Planning
 *
 * Two-stage lifecycle (identical pattern to AL and IP discharge):
 *   Stage 1 — Plan:    Record disposition code, reason, decision date.
 *                      Episode stays ACTIVE.
 *   Stage 2 — Confirm: Record actual end-of-service datetime.
 *                      Episode closes (status → CLOSED), HL7 A03 fires.
 *
 * Reachable from: Profile page Clinical Workflows row + nav Discharge tab.
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcDischarge\Controller\HbcDischargeController;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcDischarge\Repository\HbcDischargeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

if (!$manifest->featureEnabled('hbc_discharge')) {
    oei_exit_with_alert(xlt('HBC Discharge is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId);
    exit;
}

// Resolve pid from episode if missing
if ($pid === 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    $pid   = (int)($epRow['pid'] ?? 0);
}

$controller = new HbcDischargeController(
    new HbcDischargeRepository(),
    new EpisodeRepository(),
    new EpisodeEventRepository()
);
$data = $controller->handle($episodeId, $pid, $facilityId, $userId);

$h      = $data['header'];
$plan   = $data['plan'];
$closed = $data['closed'];
$codes  = $data['codes'];

if (!$h) {
    oei_exit_with_alert(xlt('HBC episode not found.'), 'danger');
}

$activePage  = 'discharge';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$pageTitle   = xlt('Service Closure / Discharge');

// Pre-populate nav from header to avoid second DB round-trip
$hbcNavPatient = [
    'fname'            => $h['fname'],
    'lname'            => $h['lname'],
    'pid'              => $h['pid'],
    'referral_status'  => $h['referral_status'] ?? '',
    'urgency'          => $h['urgency'] ?? '',
    'service_city'     => $h['service_city'] ?? '',
    'service_state_province' => $h['service_state_province'] ?? '',
    'primary_diagnosis'=> $h['primary_diagnosis'] ?? '',
];

$currentCode = (string)($plan['disposition_code'] ?? '');
$codeInfo    = $codes[$currentCode] ?? null;
$isPending   = in_array($currentCode, $data['pending_codes'], true);
$losDays     = (int)($h['days_on_service'] ?? 0);
$q           = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
$formAction  = htmlspecialchars($_hbcBase . 'discharge.php' . $q);
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
    .stage-card     { border-left: 4px solid #e76f51; }
    .stage-complete { border-left-color: #198754; }
    .code-btn       { transition: box-shadow .15s; cursor:pointer; }
    .code-btn.selected { box-shadow: 0 0 0 3px #e76f51; }
    .code-btn:hover { box-shadow: 0 0 0 2px #e76f51; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mt-2 mb-3 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">
      🏠→ <?= xlt('Service Closure') ?>:
      <strong><?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?></strong>
    </h5>
    <div class="text-muted small">
      <?= xlt('Days on service') ?>: <strong><?= $losDays ?></strong>
      <?php if ($h['clinician_name'] ?? ''): ?>
        · <?= xlt('Clinician') ?>: <?= htmlspecialchars(trim($h['clinician_name'])) ?>
      <?php endif; ?>
      <?php if ($h['primary_diagnosis'] ?? ''): ?>
        · <?= htmlspecialchars($h['primary_diagnosis']) ?>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($closed): ?>
  <span class="badge bg-secondary fs-6"><?= xlt('Episode Closed') ?></span>
  <?php endif; ?>
</div>

<?php if ($data['flash']): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($data['flash']) ?></div>
<?php endif; ?>
<?php if ($data['error']): ?>
<div class="alert alert-danger py-2"><?= htmlspecialchars($data['error']) ?></div>
<?php endif; ?>

<?php if ($closed): ?>
<!-- ── Closed episode summary ─────────────────────────────────────────── -->
<div class="card stage-complete">
  <div class="card-header fw-semibold text-success">
    ✅ <?= xlt('Episode Closed') ?>
  </div>
  <div class="card-body small">
    <dl class="row mb-0">
      <dt class="col-sm-3"><?= xlt('Disposition') ?></dt>
      <dd class="col-sm-9">
        <?= htmlspecialchars($codes[$currentCode]['icon'] ?? '') ?>
        <?= htmlspecialchars($codes[$currentCode]['label'] ?? $currentCode) ?>
      </dd>
      <?php if ($plan['destination'] ?? ''): ?>
      <dt class="col-sm-3"><?= xlt('Destination') ?></dt>
      <dd class="col-sm-9"><?= htmlspecialchars($plan['destination']) ?></dd>
      <?php endif; ?>
      <dt class="col-sm-3"><?= xlt('End of Service') ?></dt>
      <dd class="col-sm-9"><?= htmlspecialchars(substr($plan['depart_datetime'] ?? '', 0, 16)) ?></dd>
      <?php if ($plan['notes'] ?? ''): ?>
      <dt class="col-sm-3"><?= xlt('Notes') ?></dt>
      <dd class="col-sm-9"><?= nl2br(htmlspecialchars($plan['notes'])) ?></dd>
      <?php endif; ?>
    </dl>
  </div>
</div>

<?php else: ?>
<div class="row g-3">

  <!-- ── Stage 1: Plan ──────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card stage-card <?= $currentCode ? 'stage-complete' : '' ?> h-100">
      <div class="card-header fw-semibold">
        <?= $currentCode ? '✅' : '1️⃣' ?> <?= xlt('Stage 1 — Discharge Plan') ?>
      </div>
      <div class="card-body">

        <?php if ($currentCode): ?>
        <div class="alert alert-info py-2 mb-3 small">
          <?= htmlspecialchars($codeInfo['icon'] ?? '') ?>
          <strong><?= htmlspecialchars($codeInfo['label'] ?? $currentCode) ?></strong>
          <?php if ($plan['destination'] ?? ''): ?>
            → <?= htmlspecialchars($plan['destination']) ?>
          <?php endif; ?>
          <?php if ($plan['decision_datetime'] ?? ''): ?>
            · <?= htmlspecialchars(substr($plan['decision_datetime'], 0, 10)) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $formAction ?>">
          <input type="hidden" name="csrf_token_form"
                 value="<?= htmlspecialchars($data['csrf']) ?>">
          <input type="hidden" name="action"     value="save_plan">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid"        value="<?= $pid ?>">

          <!-- Disposition code tiles -->
          <div class="row g-2 mb-3" id="codeGrid">
            <?php foreach ($codes as $codeKey => $info): ?>
            <div class="col-6">
              <div class="code-btn border rounded p-2 text-center small
                          <?= $currentCode === $codeKey ? 'selected border-danger' : '' ?>"
                   onclick="selectCode('<?= htmlspecialchars($codeKey) ?>')">
                <div class="fs-5"><?= $info['icon'] ?></div>
                <div class="fw-semibold lh-sm mt-1"><?= htmlspecialchars($info['label']) ?></div>
                <?php if ($info['urgent']): ?>
                  <span class="badge bg-danger mt-1"><?= xlt('Urgent') ?></span>
                <?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="disposition_code" id="dispositionCode"
                 value="<?= htmlspecialchars($currentCode) ?>" required>

          <div class="mb-2">
            <label class="form-label small fw-semibold">
              <?= xlt('Receiving Facility / Agency') ?>
              <span class="fw-normal text-muted">(<?= xlt('if applicable') ?>)</span>
            </label>
            <input type="text" name="destination" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($plan['destination'] ?? '') ?>"
                   placeholder="<?= xla('Hospital name, SNF name, home health agency…') ?>">
          </div>

          <div class="mb-2">
            <label class="form-label small fw-semibold"><?= xlt('Decision Date') ?></label>
            <input type="datetime-local" name="decision_datetime" class="form-control form-control-sm"
                   value="<?= htmlspecialchars(
                       $plan['decision_datetime']
                           ? str_replace(' ', 'T', substr($plan['decision_datetime'], 0, 16))
                           : date('Y-m-d\TH:i')
                   ) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold"><?= xlt('Notes') ?></label>
            <textarea name="notes" class="form-control form-control-sm" rows="3"
                      id="notesField"
                      placeholder="<?= xla('Reason for closure, patient status, follow-up arrangements…') ?>"><?= htmlspecialchars($plan['notes'] ?? '') ?></textarea>
            <div id="deceasedHint" class="form-text text-danger" style="display:none;">
              <?= xlt('Required for Deceased: circumstances, time of death, attending clinician.') ?>
            </div>
          </div>

          <button type="submit" class="btn btn-warning btn-sm w-100"
                  id="savePlanBtn" disabled>
            💾 <?= xlt('Save Discharge Plan') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Stage 2: Confirm ────────────────────────────────────────────── -->
  <div class="col-lg-6">
    <div class="card stage-card h-100 <?= (!$currentCode || $isPending) ? 'opacity-50' : '' ?>"
         id="stage2Card">
      <div class="card-header fw-semibold">
        2️⃣ <?= xlt('Stage 2 — Confirm End of Service') ?>
      </div>
      <div class="card-body">
        <?php if (!$currentCode): ?>
        <p class="text-muted small"><?= xlt('Complete Stage 1 before confirming.') ?></p>
        <?php elseif ($isPending): ?>
        <div class="alert alert-warning py-2 small">
          <?= xlt('Hospital transfer is pending — confirm closure only if patient will not return to home-based care.') ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $formAction ?>">
          <input type="hidden" name="csrf_token_form"
                 value="<?= htmlspecialchars($data['csrf']) ?>">
          <input type="hidden" name="action"     value="confirm_departure">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid"        value="<?= $pid ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">
              <?= xlt('End-of-Service Date / Time') ?>
            </label>
            <input type="datetime-local" name="depart_datetime"
                   class="form-control"
                   value="<?= date('Y-m-d\TH:i') ?>"
                   <?= !$currentCode ? 'disabled' : '' ?> required>
          </div>

          <div class="alert alert-danger py-2 small">
            ⚠ <?= xlt('This action closes the episode permanently. Ensure all visit notes are complete before confirming.') ?>
          </div>

          <button type="submit" class="btn btn-danger w-100"
                  <?= !$currentCode ? 'disabled' : '' ?>
                  onclick="return confirm('<?= xlt('Confirm end of service and close this episode?') ?>')">
            🔒 <?= xlt('Confirm Closure') ?>
          </button>
        </form>
      </div>
    </div>
  </div>

</div><!-- /row -->
<?php endif; ?>

</div>
<script>
(function () {
    function selectCode(code) {
        document.getElementById('dispositionCode').value = code;
        document.getElementById('savePlanBtn').disabled = false;
        document.querySelectorAll('.code-btn').forEach(el => el.classList.remove('selected','border-danger'));
        event.currentTarget.classList.add('selected','border-danger');
        const hint = document.getElementById('deceasedHint');
        if (hint) hint.style.display = (code === 'DECEASED') ? 'block' : 'none';
    }
    window.selectCode = selectCode;

    // Enable save button if code already set from plan
    const existing = document.getElementById('dispositionCode')?.value;
    if (existing) {
        const btn = document.getElementById('savePlanBtn');
        if (btn) btn.disabled = false;
    }
})();
</script>
</body>
</html>







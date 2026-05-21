<?php

/**
 * public/ip/profile.php
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
 * public/ip/profile.php — Inpatient Patient Profile Hub
 *
 * Aggregates vitals snapshot, MAR today, care plan goals, open tasks,
 * care team, and quick navigation to all IP sub-pages.
 * This is the page floor nurses open dozens of times per shift.
 *
 * Requires: ?episode_id=<n>  (pid auto-resolved)
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpProfile\Repository\IpProfileRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('ip_profile')) {
    oei_exit_with_alert(xlt('Inpatient Profile is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$_oei_ip_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

if ($episodeId === 0) {
    header('Location: ' . $_oei_ip_base . 'board.php?facility_id=' . $facilityId);
    exit;
}

$repo = new IpProfileRepository();

$header = $repo->fetchHeader($episodeId);
if (!$header) {
    oei_exit_with_alert(xlt('Episode not found or not an inpatient episode.'), 'danger');
}

$pid         = (int)$header['pid'];
$encounterId = (int)($header['encounter_id'] ?? 0);

// Fetch all panels independently — failures in one don't break others
$vitals    = $repo->fetchVitalsHistory($episodeId, 6);
$marToday  = $repo->fetchMarToday($episodeId);
$carePlan  = $repo->fetchCarePlanSummary($pid, $encounterId);
$careTeam  = $repo->fetchCareTeam($pid);
$tasks        = $repo->fetchOpenTasks($episodeId);
$observations = $repo->fetchLatestObservations($episodeId);

$latestVitals = $vitals[0] ?? null;

// Sparkline data (oldest → newest)
$sparkSbp  = array_reverse(array_filter(array_column($vitals, 'bp_systolic'),  fn($v) => $v !== null));
$sparkSpo2 = array_reverse(array_filter(array_column($vitals, 'spo2'),         fn($v) => $v !== null));
$sparkHr   = array_reverse(array_filter(array_column($vitals, 'hr'),           fn($v) => $v !== null));

// LOS colour — warning window from ip_los_warning_hours setting
$_ipSettings = (new SettingsRepository())->all($facilityId);
$_warnHours  = (int)($_ipSettings['ip_los_warning_hours'] ?? 24);
$losActual   = (int)$header['los_days'];
$losExpected = $header['expected_los_days'];
$losStatus   = 'success';
if ($losExpected !== null) {
    if ($losActual > $losExpected) {
        $losStatus = 'danger';
    } elseif (($losExpected - $losActual) * 24 <= $_warnHours) {
        $losStatus = 'warning';
    }
}

// Pre-populate nav so it doesn't re-query
$ipNavPatient = $header;
$activePage   = 'profile';
$__bgClass    = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$pageTitle    = xlt('Patient Profile') . ' — '
              . htmlspecialchars($header['fname'] . ' ' . $header['lname']);
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
    .oei-ip-profile-header { background: linear-gradient(135deg,#1d3557,#457b9d); color:#fff; border-radius:.5rem; }
    .panel-card             { border-left: 3px solid #457b9d; }
    .panel-card .card-header{ font-size:.85rem; font-weight:600; letter-spacing:.03em; }
    .vitals-grid span       { display:inline-block; min-width:6rem; }
    .spark-bar              { display:inline-block; width:3px; background:#457b9d; border-radius:1px; vertical-align:bottom; margin-right:1px; }
    .spark-bar.alert-val    { background:#dc3545; }
    .mar-badge-ok           { background:#198754; }
    .mar-badge-warn         { background:#fd7e14; }
    .mar-badge-danger       { background:#dc3545; }
    .care-plan-bar          { height:6px; border-radius:3px; background:#dee2e6; overflow:hidden; }
    .care-plan-bar-fill     { height:100%; background:#457b9d; border-radius:3px; }
    .task-row td            { font-size:.82rem; }
    .team-pill              { font-size:.78rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>

<?php if (($_GET['flash'] ?? '') === 'admitted'): ?>
<div class="alert alert-success alert-dismissible py-2 mb-3" role="alert">
  ✔ <?= xlt('Patient admitted successfully. Complete the care plan, record baseline vitals, and place medication orders below.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Profile header ─────────────────────────────────────────────── -->
<div class="oei-ip-profile-header p-3 mb-3">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <div class="fs-4 fw-bold">
        🏥 <?= htmlspecialchars($header['fname'] . ' ' . $header['lname']) ?>
        <span class="badge <?= HospitalService::badgeClass($header['service']) ?> ms-2 fs-6">
          <?= htmlspecialchars(HospitalService::label($header['service'])) ?>
        </span>
        <span class="badge <?= AdmissionType::badgeClass($header['admission_type']) ?> ms-1 fs-6">
          <?= htmlspecialchars(AdmissionType::label($header['admission_type'])) ?>
        </span>
      </div>
      <div class="text-white-50 mt-1" style="font-size:.9rem;">
        <?= xlt('Bed') ?>: <strong class="text-white"><?= htmlspecialchars($header['bed']) ?></strong>
        · <?= xlt('Unit') ?>: <strong class="text-white"><?= htmlspecialchars($header['unit']) ?></strong>
        · <?= xlt('DOB') ?>: <?= htmlspecialchars((string)($header['dob'] ?? '')) ?>
        · <?= xlt('Day') ?>
          <span class="badge bg-<?= $losStatus ?> ms-1"><?= $losActual ?><?= $losExpected !== null ? " / {$losExpected}" : '' ?></span>
      </div>
      <?php if ($header['admitting_diagnosis']): ?>
      <div class="mt-1 text-white-50" style="font-size:.85rem;">
            <?= xlt('Dx') ?>:
        <strong class="text-white"><?= htmlspecialchars($header['admitting_diagnosis']) ?></strong>
            <?php if (!empty($header['admitting_icd10'])): ?>
          <span class="ms-1 opacity-75">(<?= htmlspecialchars($header['admitting_icd10']) ?>)</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($header['attending_name']): ?>
      <div class="mt-1 text-white-50" style="font-size:.85rem;">
            <?= xlt('Attending') ?>:
        <strong class="text-white"><?= htmlspecialchars($header['attending_name']) ?></strong>
            <?php if ($header['nurse_name']): ?>
          · <?= xlt('Nurse') ?>:
          <strong class="text-white"><?= htmlspecialchars($header['nurse_name']) ?></strong>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-column gap-2">
      <?php if ($manifest->featureEnabled('ip_vitals')): ?>
      <a href="<?= htmlspecialchars($_oei_ip_base) ?>vitals.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
         class="btn btn-sm btn-outline-light">+ <?= xlt('Vitals') ?></a>
      <?php endif; ?>
      <?php if ($manifest->featureEnabled('mar')): ?>
      <a href="<?= htmlspecialchars($_oei_pub_base) ?>mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= $episodeId ?>"
         class="btn btn-sm btn-outline-light">💊 <?= xlt('MAR') ?></a>
      <?php endif; ?>
      <?php if ($manifest->featureEnabled('ip_discharge')): ?>
      <a href="<?= htmlspecialchars($_oei_ip_base) ?>discharge.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
         class="btn btn-sm btn-outline-light">🚪 <?= xlt('Discharge') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Row 1: Vitals | MAR Today | Tasks ──────────────────────────── -->
<div class="row g-3 mb-3">

  <!-- VITALS PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>💓 <?= xlt('Latest Vitals') ?></span>
        <?php if ($manifest->featureEnabled('ip_vitals')): ?>
        <a href="<?= htmlspecialchars($_oei_ip_base) ?>vitals.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
            <?= xlt('History') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($latestVitals): $lv = $latestVitals; ?>
          <div class="vitals-grid small">
            <?php if ($lv['bp_systolic'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('BP') ?>:</span>
              <strong class="<?= ($lv['bp_systolic'] > 180 || $lv['bp_systolic'] < 80) ? 'text-danger' : '' ?>">
                <?= $lv['bp_systolic'] ?>/<?= $lv['bp_diastolic'] ?> mmHg
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['hr'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('HR') ?>:</span>
              <strong class="<?= ($lv['hr'] > 120 || $lv['hr'] < 45) ? 'text-danger' : '' ?>">
                <?= $lv['hr'] ?> bpm
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['spo2'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('SpO₂') ?>:</span>
              <strong class="<?= $lv['spo2'] < 90 ? 'text-danger' : ($lv['spo2'] < 94 ? 'text-warning' : '') ?>">
                <?= $lv['spo2'] ?>%
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['rr'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('RR') ?>:</span>
              <strong class="<?= ($lv['rr'] > 24 || $lv['rr'] < 8) ? 'text-danger' : '' ?>">
                <?= $lv['rr'] ?>/min
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['temp_f'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('Temp') ?>:</span>
              <strong class="<?= ($lv['temp_f'] > 100.4 || $lv['temp_f'] < 96.8) ? 'text-danger' : '' ?>">
                <?= number_format($lv['temp_f'], 1) ?>°F
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['gcs'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('GCS') ?>:</span>
              <strong class="<?= $lv['gcs'] < 13 ? 'text-danger' : '' ?>">
                <?= $lv['gcs'] ?>/15
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['pain_score'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('Pain') ?>:</span>
              <strong class="<?= $lv['pain_score'] >= 7 ? 'text-danger' : ($lv['pain_score'] >= 4 ? 'text-warning' : '') ?>">
                <?= $lv['pain_score'] ?>/10
              </strong>
            </div>
            <?php endif; ?>
          </div>

            <?php if (count($sparkSbp) > 1): ?>
          <div class="mt-2">
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('SBP trend') ?></div>
            <canvas id="sparkSbp" height="30" style="width:100%;"></canvas>
          </div>
          <?php endif; ?>

          <div class="text-muted mt-2" style="font-size:.72rem;">
            <?= xlt('Recorded') ?>: <?= htmlspecialchars(substr($lv['noted_datetime'], 0, 16)) ?>
          </div>
        <?php else: ?>
          <div class="text-muted small py-2"><?= xlt('No vitals recorded yet.') ?>
            <?php if ($manifest->featureEnabled('ip_vitals')): ?>
              <a href="<?= htmlspecialchars($_oei_ip_base) ?>vitals.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>">
                <?= xlt('Record vitals') ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- MAR TODAY PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>💊 <?= xlt('Medications Today') ?></span>
        <?php if ($manifest->featureEnabled('mar')): ?>
        <a href="<?= htmlspecialchars($_oei_pub_base) ?>mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= $episodeId ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
            <?= xlt('Full MAR') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php $mt = $marToday; ?>
        <div class="d-flex gap-2 flex-wrap mb-2">
          <span class="badge mar-badge-ok text-white"><?= $mt['given'] ?> <?= xlt('Given') ?></span>
          <?php if ($mt['held']): ?>
            <span class="badge mar-badge-warn text-white"><?= $mt['held'] ?> <?= xlt('Held') ?></span>
          <?php endif; ?>
          <?php if ($mt['overdue']): ?>
            <span class="badge mar-badge-danger text-white">⚠ <?= $mt['overdue'] ?> <?= xlt('Overdue') ?></span>
          <?php elseif ($mt['pending']): ?>
            <span class="badge text-bg-secondary"><?= $mt['pending'] ?> <?= xlt('Pending') ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($mt['pending_drugs'])): ?>
          <div class="small">
            <div class="text-muted mb-1 fw-semibold"><?= xlt('Upcoming / overdue') ?>:</div>
            <?php foreach (array_slice($mt['pending_drugs'], 0, 5) as $drug): ?>
            <div class="d-flex align-items-center gap-1 mb-1
                 <?= $drug['overdue'] ? 'text-danger' : '' ?>">
                <?php if ($drug['high_alert']): ?>
                <span class="badge text-bg-warning" style="font-size:.65rem;">HA</span>
              <?php endif; ?>
              <span><?= htmlspecialchars($drug['sched']) ?></span>
              <span class="fw-semibold"><?= htmlspecialchars($drug['drug']) ?></span>
              <span class="text-muted"><?= htmlspecialchars($drug['dose']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        <?php elseif ($mt['given'] === 0 && $mt['pending'] === 0): ?>
          <div class="text-muted small"><?= xlt('No medication orders for today.') ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- OPEN TASKS PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header">
        📋 <?= xlt('Open Tasks') ?>
        <?php if (!empty($tasks)): ?>
          <span class="badge text-bg-secondary ms-1"><?= count($tasks) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if (!empty($tasks)): ?>
          <table class="table table-sm mb-0 task-row">
            <tbody>
            <?php foreach ($tasks as $t):
                $overdue = !empty($t['due_datetime']) && $t['due_datetime'] < date('Y-m-d H:i:s');
                ?>
              <tr class="<?= $overdue ? 'table-danger' : '' ?>">
                <td>
                  <span class="badge text-bg-light border"><?= htmlspecialchars((string)($t['task_type'] ?? '')) ?></span>
                </td>
                <td class="text-muted small">
                  <?= htmlspecialchars(substr((string)($t['due_datetime'] ?? ''), 0, 16)) ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="text-muted small p-3"><?= xlt('No open tasks.') ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Row 2: Care Plan | Care Team ───────────────────────────────── -->
<div class="row g-3 mb-3">

  <!-- CARE PLAN PANEL -->
  <div class="col-md-8">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🎯 <?= xlt('Care Plan') ?></span>
        <?php if ($manifest->featureEnabled('care_plan')): ?>
        <a href="<?= htmlspecialchars($_oei_pub_base) ?>shared/care_plan.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
            <?= xlt('Edit') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php $cp = $carePlan; ?>
        <?php if ($cp['counts']['goals'] + $cp['counts']['activities'] > 0): ?>
          <!-- Progress bar -->
            <?php $total = $cp['counts']['goals'] + $cp['counts']['activities']; ?>
            <?php $pct = $total > 0 ? round($cp['counts']['completed'] / $total * 100) : 0; ?>
          <div class="care-plan-bar mb-2">
            <div class="care-plan-bar-fill" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="text-muted small mb-2">
            <?= $cp['counts']['completed'] ?>/<?= $total ?> <?= xlt('items completed') ?>
            (<?= $pct ?>%)
          </div>
            <?php foreach (array_slice($cp['goals'], 0, 4) as $goal): ?>
            <div class="d-flex align-items-start gap-2 mb-1 small">
              <span class="badge text-bg-light border text-muted mt-1"><?= xlt('Goal') ?></span>
              <span class="<?= strtolower($goal['plan_status'] ?? '') === 'completed' ? 'text-decoration-line-through text-muted' : '' ?>">
                <?= htmlspecialchars((string)($goal['description'] ?? '')) ?>
              </span>
            </div>
          <?php endforeach; ?>
            <?php if ($cp['counts']['goals'] > 4): ?>
            <div class="text-muted small mt-1">
              + <?= $cp['counts']['goals'] - 4 ?> <?= xlt('more goals…') ?>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-muted small">
            <?= xlt('No care plan entries yet.') ?>
            <?php if ($manifest->featureEnabled('care_plan')): ?>
              <a href="<?= htmlspecialchars($_oei_pub_base) ?>shared/care_plan.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>">
                <?= xlt('Create care plan') ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- CARE TEAM PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>👥 <?= xlt('Care Team') ?></span>
        <?php if ($manifest->featureEnabled('care_team')): ?>
        <a href="<?= htmlspecialchars($_oei_pub_base) ?>shared/care_team.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
            <?= xlt('Manage') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!empty($careTeam)): ?>
          <div class="d-flex flex-wrap gap-1">
            <?php foreach ($careTeam as $member): ?>
              <span class="badge text-bg-light border team-pill">
                <?= htmlspecialchars($member['role'] ?: 'Member') ?>:
                <?= htmlspecialchars($member['member_name']) ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small">
            <?= xlt('No care team members assigned.') ?>
            <?php if ($manifest->featureEnabled('care_team')): ?>
              <a href="<?= htmlspecialchars($_oei_pub_base) ?>shared/care_team.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>">
                <?= xlt('Assign team') ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Row 3: Fall Risk -->
<?php if ($manifest->featureEnabled('ip_fall_risk')): ?>
<?php
    $fallRisk = $repo->fetchFallRiskSummary($episodeId);
    $frLevel  = $fallRisk ? (string)$fallRisk['risk_level']       : null;
    $frScore  = $fallRisk ? (int)$fallRisk['total_score']         : null;
    $frDate   = $fallRisk ? substr((string)$fallRisk['assessed_datetime'], 0, 10) : null;
    $frDays   = $fallRisk ? (int)$fallRisk['days_since']          : null;
    $frAlert  = ($frDays === null || $frDays >= 28);
    $frBadge  = match ($frLevel) { 'HIGH' => 'danger', 'MODERATE' => 'warning', default => 'success' };
?>
<div class="row g-3 mb-3">
  <div class="col-12">
    <div class="card panel-card <?= $frAlert ? 'border-warning' : '' ?>">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>⚠️ <?= xlt('Fall Risk (Morse)') ?></span>
        <a href="<?= htmlspecialchars($_oei_ip_base . 'fall_risk.php?' . $q) ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
          <?= $fallRisk ? xlt('Reassess') : xlt('Assess') ?>
        </a>
      </div>
      <div class="card-body py-2">
        <?php if ($fallRisk): ?>
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <span class="badge bg-<?= $frBadge ?> fs-6"><?= $frScore ?></span>
            <span class="fw-semibold text-<?= $frBadge ?>"><?= htmlspecialchars(
                $frLevel === 'HIGH'      ? xlt('High Risk')
              : ($frLevel === 'MODERATE' ? xlt('Moderate Risk') : xlt('Low Risk'))
            ) ?></span>
            <span class="text-muted small"><?= xlt('Assessed') ?>: <?= htmlspecialchars($frDate ?? '') ?></span>
            <?php if ($frAlert): ?>
              <span class="badge bg-warning text-dark">⏰ <?= xlt('Reassessment due') ?></span>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="text-muted small">
            <?= xlt('No fall risk assessment on file.') ?>
            <a href="<?= htmlspecialchars($_oei_ip_base . 'fall_risk.php?' . $q) ?>" class="ms-2">
              <?= xlt('Complete now') ?>
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; // ip_fall_risk ?>

<!-- ── Quick Navigation (shared sub-pages) ────────────────────────── -->
<div class="card shadow-sm mb-3">
  <div class="card-header small fw-semibold">
    🔗 <?= xlt('Clinical Workflows') ?>
  </div>
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2">
      <?php
        $q = "episode_id={$episodeId}&pid={$pid}&facility_id={$facilityId}";
        $links = [
          ['clinical_notes',    xlt('Clinical Notes'),   '📝', $_oei_pub_base . 'shared/clinical_notes.php?' . $q],
          ['episode_documents', xlt('Documents'),        '📎', $_oei_pub_base . 'episode_documents.php?' . $q],
          ['disposition',       xlt('Dispo Plan'),       '🗂', $_oei_pub_base . 'disposition.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
          ['ereferral',         xlt('E-Referral'),       '📤', $_oei_pub_base . 'ereferral.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
          ['handoff',           xlt('Shift Handoff'),    '🔄', $_oei_pub_base . 'handoff.php?facility_id=' . $facilityId],
          ['timeline',          xlt('Timeline'),         '📅', $_oei_pub_base . 'timeline.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
          ['ip_discharge',      xlt('Discharge'),        '🚪', $_oei_ip_base  . 'discharge.php?' . $q],
          ['ip_fall_risk',      xlt('Fall Risk'),        '⚠',     $_oei_ip_base  . 'fall_risk.php?' . $q],
        ];
        foreach ($links as [$feature, $label, $icon, $url]):
            if (!$manifest->featureEnabled($feature)) continue;
            ?>
      <a href="<?= htmlspecialchars($url) ?>" class="btn btn-sm btn-outline-secondary">
            <?= $icon ?> <?= $label ?>
      </a>
        <?php endforeach; ?>
    </div>
  </div>
</div>


<?php if ($manifest->featureEnabled('observations') && !empty($observations)): ?>
<div class="card panel-card mb-3">
  <div class="card-header">
    &#128225; <?= xlt('Extended Observations') ?>
    <span class="badge bg-secondary ms-2 float-end" style="font-size:.7rem;font-weight:500">
      <?= count($observations) ?> <?= xlt('types') ?>
    </span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th><?= xlt('Type') ?></th>
          <th><?= xlt('Value') ?></th>
          <th><?= xlt('When') ?></th>
          <th><?= xlt('Source') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($observations as $_obsRow): ?>
      <tr class="<?= !empty($_obsRow['is_flagged']) ? 'table-danger' : '' ?>">
        <td class="fw-semibold">
          <?= htmlspecialchars((string)($_obsRow['display_name'] ?? $_obsRow['obs_type_code'] ?? '')) ?>
          <?php if (!empty($_obsRow['is_flagged'])): ?>
            <span class="badge bg-danger ms-1" style="font-size:.65rem">&#9888; <?= xlt('Alert') ?></span>
          <?php endif; ?>
        </td>
        <td class="<?= !empty($_obsRow['is_flagged']) ? 'text-danger fw-bold' : '' ?>">
          <?php if ($_obsRow['value_numeric'] !== null): ?>
            <?= htmlspecialchars(rtrim(rtrim(number_format((float)$_obsRow['value_numeric'], 3, '.', ''), '0'), '.')) ?>
            <?php if (!empty($_obsRow['unit'])): ?>
              <span class="text-muted"><?= htmlspecialchars((string)$_obsRow['unit']) ?></span>
            <?php endif; ?>
          <?php elseif (!empty($_obsRow['value_text'])): ?>
            <?= htmlspecialchars((string)$_obsRow['value_text']) ?>
          <?php else: ?>
            <span class="text-muted">&#8212;</span>
          <?php endif; ?>
        </td>
        <td class="text-muted text-nowrap">
          <?= htmlspecialchars(substr((string)($_obsRow['observed_datetime'] ?? ''), 0, 16)) ?>
        </td>
        <td>
          <?php
          $_srcType  = (string)($_obsRow['source_type'] ?? 'MANUAL');
          $_srcBadge = ($_srcType === 'FHIR')   ? 'bg-primary'
                     : (($_srcType === 'DEVICE') ? 'bg-success'
                     : (($_srcType === 'IMPORT') ? 'bg-info text-dark' : 'bg-secondary'));
          ?>
          <span class="badge <?= $_srcBadge ?>" style="font-size:.65rem">
            <?= htmlspecialchars($_srcType) ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

</div>

<?php if (count($sparkSbp) > 1): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function() {
  var sbpData  = <?= json_encode(array_values($sparkSbp)) ?>;
  var spo2Data = <?= json_encode(array_values($sparkSpo2)) ?>;

  function spark(id, data, color, alertThreshold) {
    var el = document.getElementById(id);
    if (!el || !data.length) return;
    new Chart(el, {
      type: 'bar',
      data: {
        labels: data.map(function(_, i) { return ''; }),
        datasets: [{
          data: data,
          backgroundColor: data.map(function(v) {
            return (alertThreshold && v < alertThreshold) ? '#dc3545' : color;
          }),
          borderRadius: 1,
        }]
      },
      options: {
        animation: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: {
          x: { display: false },
          y: { display: false }
        }
      }
    });
  }

  spark('sparkSbp',  sbpData,  '#457b9d', 90);
  spark('sparkSpo2', spo2Data, '#457b9d', 94);
})();
</script>
<?php endif; ?>

<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>
















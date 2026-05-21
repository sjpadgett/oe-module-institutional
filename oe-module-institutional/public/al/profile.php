<?php

/**
 * public/al/profile.php
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
 * public/al/profile.php — Resident Profile Hub
 *
 * Aggregates vitals, ADL trend, care plan, MAR compliance,
 * incidents, fall risk, and care team for a single resident.
 * This is the page staff open dozens of times per shift.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentProfile\Controller\ResidentProfileController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\AdlLevel;

if (!$manifest->featureEnabled('al_board')) {
    oei_exit_with_alert(xlt('Resident Profile is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;

if ($episodeId === 0) {
    // No episode context — send to Board to select a resident
    header('Location: board.php?facility_id=' . $facilityId
         . '&notice=select_resident');
    exit;
}

$controller = new ResidentProfileController();
$data       = $controller->handle($episodeId);

if ($data['error'] || !$data['header']) {
    oei_exit_with_alert(htmlspecialchars((string)($data['error'] ?? 'Episode not found.')), 'danger');
}

$h   = $data['header'];
$pid = $h['pid'];

// Quick URL builder for sub-pages
$base    = '/interface/modules/custom_modules/oe-module-institutional/public/al/';
$qEp     = '?episode_id=' . $episodeId;
$qEpPid  = $qEp . '&pid=' . $pid;

$pageTitle = xlt('Resident Profile') . ' — ' . htmlspecialchars($h['fname'] . ' ' . $h['lname']);

$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

$activePage  = 'profile';
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
    .oei-profile-header { background: linear-gradient(135deg,#2d6a4f,#40916c); color:#fff; border-radius:.5rem; }
    .panel-card         { border-left:3px solid #40916c; }
    .panel-card .card-header { font-size:.85rem; font-weight:600; letter-spacing:.03em; }
    .vitals-grid span   { display:inline-block; min-width:6rem; }
    .spark-bar          { display:inline-block; width:3px; background:#40916c; border-radius:1px; vertical-align:bottom; margin-right:1px; }
    .spark-bar.alert-val{ background:#dc3545; }
    .adl-dot            { display:inline-block; width:10px; height:10px; border-radius:50%; margin-right:2px; }
    .mar-badge-ok       { background:#198754; }
    .mar-badge-warn     { background:#fd7e14; }
    .mar-badge-danger   { background:#dc3545; }
    .care-plan-bar      { height:6px; border-radius:3px; background:#dee2e6; overflow:hidden; }
    .care-plan-bar-fill { height:100%; background:#40916c; border-radius:3px; }
    .incident-row td    { font-size:.82rem; }
    .team-pill          { font-size:.78rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php';
?>
<?php if (($_GET['flash'] ?? '') === 'admitted'): ?>
<div class="alert alert-success alert-dismissible py-2 mx-2 mt-2" role="alert">
  ✔ <?= xlt('Resident admitted successfully. Complete the care plan and record baseline vitals below.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<!-- ── Profile Header ──────────────────────────────────────── -->
<div class="oei-profile-header p-3 mb-3">
  <div class="d-flex align-items-start justify-content-between flex-wrap gap-2">
    <div>
      <div class="fs-4 fw-bold">
        🏡 <?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?>
        <span class="badge bg-<?= htmlspecialchars($data['care_level_badge']) ?> ms-2 fs-6">
          <?= htmlspecialchars($data['care_level_label']) ?>
        </span>
        <span class="badge bg-<?= htmlspecialchars($data['fall_risk_badge']) ?> ms-1 fs-6">
          ⚠ <?= htmlspecialchars($data['fall_risk_label']) ?>
        </span>
      </div>
      <div class="text-white-50 mt-1" style="font-size:.9rem;">
        <?= xlt('Room') ?>: <strong class="text-white"><?= htmlspecialchars($h['room']) ?></strong>
        · <?= xlt('Unit') ?>: <strong class="text-white"><?= htmlspecialchars($h['unit']) ?></strong>
        · <?= xlt('DOB') ?>: <?= htmlspecialchars($h['dob']) ?>
        · <?= xlt('Day') ?> <?= $h['days_resident'] ?> <?= xlt('of stay') ?>
      </div>
      <?php if ($h['chief_complaint']): ?>
      <div class="mt-1 text-white-50" style="font-size:.85rem;">
            <?= xlt('Admission reason') ?>: <?= htmlspecialchars($h['chief_complaint']) ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="d-flex flex-column gap-2">
      <a href="<?= $base ?>board.php" class="btn btn-sm btn-light">← <?= xlt('Board') ?></a>
      <a href="<?= $base ?>vitals.php<?= $qEp ?>&pid=<?= $pid ?>" class="btn btn-sm btn-outline-light">+ <?= xlt('Vitals') ?></a>
      <a href="<?= $base ?>fall_risk.php<?= $qEp ?>" class="btn btn-sm btn-outline-light">⚠ <?= xlt('Fall Risk') ?></a>
    </div>
  </div>
</div>

<!-- ── Row 1: Vitals | ADL Trend | MAR Today ──────────────── -->
<div class="row g-3 mb-3">

  <!-- VITALS PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>💓 <?= xlt('Latest Vitals') ?></span>
        <a href="<?= $base ?>vitals.php<?= $qEp ?>&pid=<?= $pid ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('History') ?>
        </a>
      </div>
      <div class="card-body">
        <?php if ($data['latest_vitals']): $lv = $data['latest_vitals']; ?>
          <div class="vitals-grid small">
            <?php if ($lv['bp_systolic'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('BP') ?>:</span>
              <strong class="<?= ($lv['bp_systolic'] > 160 || $lv['bp_systolic'] < 90) ? 'text-danger' : '' ?>">
                <?= $lv['bp_systolic'] ?>/<?= $lv['bp_diastolic'] ?> mmHg
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['hr'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('HR') ?>:</span>
              <strong class="<?= ($lv['hr'] > 100 || $lv['hr'] < 50) ? 'text-danger' : '' ?>">
                <?= $lv['hr'] ?> bpm
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['spo2'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('SpO₂') ?>:</span>
              <strong class="<?= $lv['spo2'] < 93 ? 'text-danger' : ($lv['spo2'] < 96 ? 'text-warning' : '') ?>">
                <?= $lv['spo2'] ?>%
              </strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['weight_kg'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('Weight') ?>:</span>
              <strong><?= number_format($lv['weight_kg'], 1) ?> kg</strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['rr'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('RR') ?>:</span>
              <strong><?= $lv['rr'] ?>/min</strong>
            </div>
            <?php endif; ?>
            <?php if ($lv['temp_f'] !== null): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('Temp') ?>:</span>
              <strong><?= number_format($lv['temp_f'], 1) ?>°F</strong>
            </div>
            <?php endif; ?>
          </div>

          <!-- Weight sparkline -->
            <?php if (count($data['spark_weights']) > 1): ?>
          <div class="mt-2">
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('Weight trend') ?></div>
            <canvas id="sparkWeight" height="30" style="width:100%"></canvas>
          </div>
          <?php endif; ?>

          <!-- SpO2 sparkline -->
            <?php if (count($data['spark_spo2']) > 1): ?>
          <div class="mt-2">
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('SpO₂ trend') ?></div>
            <canvas id="sparkSpo2" height="30" style="width:100%"></canvas>
          </div>
          <?php endif; ?>

          <div class="text-muted mt-2" style="font-size:.72rem;">
            <?= xlt('Recorded') ?>: <?= htmlspecialchars(substr($lv['noted_datetime'], 0, 16)) ?>
          </div>
        <?php else: ?>
          <p class="text-muted small"><?= xlt('No vitals recorded yet.') ?>
            <a href="<?= $base ?>vitals.php<?= $qEp ?>&pid=<?= $pid ?>">
              <?= xlt('Add first entry') ?>
            </a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ADL PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🧼 <?= xlt('ADL Status') ?></span>
        <a href="<?= $base ?>adl.php<?= $qEp ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('Chart') ?>
        </a>
      </div>
      <div class="card-body">
        <?php if ($data['latest_adl']): $la = $data['latest_adl']; ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fs-4 fw-bold"><?= $la['adl_score'] ?></span>
            <span class="text-muted small">/ 28 <?= xlt('points') ?></span>
            <span class="badge bg-<?= htmlspecialchars(CareLevel::badge(CareLevel::fromAdlScore($la['adl_score']))) ?>">
              <?= htmlspecialchars(CareLevel::label(CareLevel::fromAdlScore($la['adl_score']))) ?>
            </span>
          </div>

          <!-- Domain breakdown dots -->
          <div class="mb-2">
            <?php foreach (AdlLevel::DOMAINS as $domain => $label):
                $level = (int)($la['domain_levels'][$domain] ?? 8);
                ?>
            <span title="<?= htmlspecialchars($label) ?>: <?= htmlspecialchars(AdlLevel::label($level)) ?>">
              <span class="adl-dot bg-<?= htmlspecialchars(AdlLevel::badge($level)) ?>"></span>
            </span>
            <?php endforeach; ?>
            <span class="text-muted" style="font-size:.72rem;">
              <?= implode(' · ', array_values(AdlLevel::DOMAINS)) ?>
            </span>
          </div>

          <!-- ADL score sparkline -->
            <?php if (count($data['adl_trend']) > 1): ?>
          <canvas id="sparkAdl" height="30" style="width:100%"></canvas>
          <?php endif; ?>

          <div class="text-muted mt-2" style="font-size:.72rem;">
            <?= xlt('Last charted') ?>: <?= htmlspecialchars(substr($la['noted_datetime'], 0, 16)) ?>
            <?php if ($la['noted_by']): ?>· <?= htmlspecialchars($la['noted_by']) ?><?php endif; ?>
          </div>
        <?php else: ?>
          <p class="text-muted small"><?= xlt('No ADL sessions charted.') ?>
            <a href="<?= $base ?>adl.php<?= $qEp ?>"><?= xlt('Add first entry') ?></a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- MAR TODAY PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>💊 <?= xlt("Today's Medications") ?></span>
        <a href="<?= $base ?>al_mar.php<?= $qEp ?>&pid=<?= $pid ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('Full MAR') ?>
        </a>
      </div>
      <div class="card-body">
        <?php $m = $data['mar_today']; ?>
        <div class="d-flex gap-3 mb-3 flex-wrap">
          <div class="text-center">
            <div class="fw-bold fs-5 text-success"><?= $m['given'] ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('Given') ?></div>
          </div>
          <div class="text-center">
            <div class="fw-bold fs-5 <?= $m['pending'] > 0 ? 'text-warning' : 'text-muted' ?>"><?= $m['pending'] ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('Pending') ?></div>
          </div>
          <?php if ($m['overdue'] > 0): ?>
          <div class="text-center">
            <div class="fw-bold fs-5 text-danger"><?= $m['overdue'] ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('Overdue') ?></div>
          </div>
          <?php endif; ?>
          <?php if ($m['held'] > 0): ?>
          <div class="text-center">
            <div class="fw-bold fs-5 text-secondary"><?= $m['held'] ?></div>
            <div class="text-muted" style="font-size:.72rem;"><?= xlt('Held') ?></div>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($m['pending_drugs']): ?>
        <div class="small">
          <div class="text-muted mb-1 fw-semibold"><?= xlt('Upcoming / Overdue') ?></div>
            <?php foreach ($m['pending_drugs'] as $drug): ?>
          <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
            <span class="<?= $drug['is_high_alert'] ? 'text-danger fw-semibold' : '' ?>">
                <?= $drug['is_high_alert'] ? '⚠ ' : '' ?>
                <?= htmlspecialchars($drug['drug_name']) ?>
              <span class="text-muted"><?= htmlspecialchars($drug['dose'] . ' ' . $drug['unit']) ?></span>
            </span>
            <span class="<?= $drug['overdue'] ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= htmlspecialchars($drug['scheduled_time']) ?>
                <?= $drug['overdue'] ? ' ⚠' : '' ?>
            </span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif ($m['given'] > 0 && $m['pending'] === 0): ?>
        <div class="alert alert-success py-2 small mb-0">
          ✓ <?= xlt('All scheduled medications given for today.') ?>
        </div>
        <?php else: ?>
        <p class="text-muted small"><?= xlt('No medications scheduled today.') ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Row 2: Care Plan | Fall Risk | Incidents ────────────── -->
<div class="row g-3 mb-3">

  <!-- CARE PLAN PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>📋 <?= xlt('Care Plan') ?></span>
        <a href="<?= $base ?>care_plan.php<?= $qEpPid ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('Edit') ?>
        </a>
      </div>
      <div class="card-body">
        <?php $cp = $data['care_plan']; $total = $cp['counts']['goals'] + $cp['counts']['activities']; ?>
        <?php if ($total > 0): ?>
          <div class="mb-2 small text-muted">
            <?= $cp['counts']['goals'] ?> <?= xlt('goals') ?>
            · <?= $cp['counts']['activities'] ?> <?= xlt('activities') ?>
            · <?= $cp['counts']['completed'] ?> <?= xlt('completed') ?>
          </div>

            <?php if ($total > 0): ?>
          <div class="care-plan-bar mb-3">
            <div class="care-plan-bar-fill"
                 style="width:<?= $total ? round($cp['counts']['completed'] / $total * 100) : 0 ?>%"></div>
          </div>
          <?php endif; ?>

            <?php foreach (array_slice($cp['goals'], 0, 3) as $goal): ?>
          <div class="d-flex align-items-start gap-1 mb-1 small">
            <span class="badge bg-<?= $goal['status'] === 'completed' ? 'success' : 'primary' ?> mt-1" style="font-size:.6rem;">
                <?= xlt('Goal') ?>
            </span>
            <span class="<?= $goal['status'] === 'completed' ? 'text-decoration-line-through text-muted' : '' ?>">
                <?= htmlspecialchars(mb_strimwidth($goal['description'], 0, 80, '…')) ?>
            </span>
          </div>
          <?php endforeach; ?>
            <?php if (count($cp['goals']) > 3): ?>
          <div class="text-muted small mt-1">
            +<?= count($cp['goals']) - 3 ?> <?= xlt('more goals') ?> …
            <a href="<?= $base ?>care_plan.php<?= $qEpPid ?>"><?= xlt('view all') ?></a>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-muted small"><?= xlt('No care plan entries.') ?>
            <a href="<?= $base ?>care_plan.php<?= $qEpPid ?>"><?= xlt('Add goals') ?></a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- FALL RISK PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>⚠ <?= xlt('Fall Risk') ?></span>
        <a href="<?= $base ?>fall_risk.php<?= $qEp ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('Reassess') ?>
        </a>
      </div>
      <div class="card-body">
        <?php $fr = $data['latest_fall_risk']; ?>
        <?php if ($fr): ?>
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="fs-4 fw-bold"><?= $fr['total_score'] ?></span>
            <span class="badge fs-6 bg-<?= htmlspecialchars(FallRiskLevel::badge($fr['risk_level'])) ?>">
              <?= htmlspecialchars(FallRiskLevel::label($fr['risk_level'])) ?>
            </span>
          </div>
          <div class="small text-muted mb-2">
            <?= xlt('Assessed') ?>: <?= htmlspecialchars(substr($fr['assessed_datetime'], 0, 10)) ?>
          </div>

            <?php if ($data['fall_risk_next_due']): ?>
                <?php $overdue = $data['fall_risk_next_due'] < date('Y-m-d'); ?>
            <div class="alert alert-<?= $overdue ? 'danger' : 'info' ?> py-1 small mb-0">
                <?= $overdue ? '⚠ ' . xlt('Reassessment overdue') : xlt('Next due') ?>:
                <?= htmlspecialchars($data['fall_risk_next_due']) ?>
            </div>
          <?php endif; ?>

          <!-- MFS item breakdown mini-table -->
          <table class="table table-sm mt-2 mb-0" style="font-size:.75rem;">
            <?php foreach ([
              xlt('Fall history') => $fr['mfs_fall_history'],
              xlt('Secondary Dx') => $fr['mfs_secondary_dx'],
              xlt('Aid')          => $fr['mfs_ambulatory_aid'],
              xlt('IV lock')      => $fr['mfs_iv_heparin_lock'],
              xlt('Gait')         => $fr['mfs_gait'],
              xlt('Mental status')=> $fr['mfs_mental_status'],
            ] as $label => $score): ?>
            <tr>
              <td class="text-muted"><?= $label ?></td>
              <td class="fw-semibold <?= $score > 0 ? 'text-danger' : 'text-success' ?>">
                <?= $score ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        <?php else: ?>
          <p class="text-muted small"><?= xlt('No fall risk assessment on file.') ?>
            <a href="<?= $base ?>fall_risk.php<?= $qEp ?>"><?= xlt('Complete Morse assessment') ?></a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- RECENT INCIDENTS PANEL -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🚨 <?= xlt('Recent Incidents') ?></span>
        <a href="<?= $base ?>incident.php<?= $qEp ?>" class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem">
          <?= xlt('Report') ?>
        </a>
      </div>
      <div class="card-body">
        <?php if ($data['incidents']): ?>
        <table class="table table-sm table-hover mb-0 incident-row">
          <thead>
            <tr>
              <th><?= xlt('Date') ?></th>
              <th><?= xlt('Type') ?></th>
              <th><?= xlt('Severity') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data['incidents'] as $inc): ?>
          <tr>
            <td><?= htmlspecialchars(substr($inc['incident_datetime'], 0, 10)) ?></td>
            <td><?= htmlspecialchars(str_replace('_', ' ', $inc['incident_type'])) ?></td>
            <td>
              <span class="badge bg-<?= $inc['severity'] === 'CRITICAL' ? 'danger' : ($inc['severity'] === 'MODERATE' ? 'warning text-dark' : 'secondary') ?>">
                <?= htmlspecialchars($inc['severity']) ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php else: ?>
          <p class="text-muted small">✓ <?= xlt('No incidents recorded.') ?></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ── Row 3: Care Team ────────────────────────────────────── -->
<?php if ($data['care_team']): ?>
<div class="card panel-card mb-3">
  <div class="card-header">👥 <?= xlt('Care Team') ?></div>
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($data['care_team'] as $member): ?>
      <span class="badge bg-secondary team-pill">
            <?= htmlspecialchars($member['role_label']) ?>:
            <?= htmlspecialchars($member['member_name']) ?>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>


<?php if ($manifest->featureEnabled('observations') && !empty($data['observations'])): ?>
<div class="card panel-card mb-3">
  <div class="card-header">
    &#128225; <?= xlt('Extended Observations') ?>
    <span class="badge bg-secondary ms-2 float-end" style="font-size:.7rem;font-weight:500">
      <?= count($data['observations']) ?> <?= xlt('types') ?>
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
      <?php foreach ($data['observations'] as $_obsRow): ?>
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

</div><!-- /container -->

<!-- ── Sparklines via Canvas ──────────────────────────────── -->
<script>
(function() {
    function drawSparkline(canvasId, data, alertFn) {
        const canvas = document.getElementById(canvasId);
        if (!canvas || !data.length) return;
        const ctx = canvas.getContext('2d');
        canvas.width = canvas.offsetWidth || canvas.parentElement.offsetWidth;
        const W = canvas.width, H = canvas.height;
        const min = Math.min(...data), max = Math.max(...data);
        const range = max - min || 1;
        const step = W / (data.length - 1 || 1);
        ctx.clearRect(0, 0, W, H);
        ctx.beginPath();
        data.forEach((v, i) => {
            const x = i * step;
            const y = H - ((v - min) / range) * (H - 4) - 2;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = '#40916c';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        // Dots with alert colouring
        data.forEach((v, i) => {
            const x = i * step;
            const y = H - ((v - min) / range) * (H - 4) - 2;
            ctx.beginPath();
            ctx.arc(x, y, 2.5, 0, Math.PI * 2);
            ctx.fillStyle = alertFn && alertFn(v) ? '#dc3545' : '#40916c';
            ctx.fill();
        });
    }

    const weights = <?= json_encode($data['spark_weights']) ?>;
    const spo2    = <?= json_encode($data['spark_spo2']) ?>;
    const adl     = <?= json_encode($data['adl_trend']) ?>;

    drawSparkline('sparkWeight', weights, null);
    drawSparkline('sparkSpo2',   spo2,   v => v < 93);
    drawSparkline('sparkAdl',    adl,    null);
})();
</script>
</body>
</html>










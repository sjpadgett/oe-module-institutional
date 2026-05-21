<?php

/**
 * public/hbc/profile.php
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
 * public/hbc/profile.php — Home-Based Care Patient Hub
 *
 * Panels: address/caregiver card, next visit, recent visits,
 *         vitals snapshot, care plan progress, open tasks.
 * Nav: hbc_patient_nav.php tab strip for all sub-pages.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcProfile\Controller\HbcProfileController;

if (!$manifest->featureEnabled('hbc_profile')) {
    oei_exit_with_alert(xlt('Home-Based Care Profile is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$_hbcBase   = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';
$_pubBase   = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new HbcProfileController();
$data       = $controller->handle($episodeId);

if (!$data['header']) {
    oei_exit_with_alert(xlt('Episode not found or not a Home-Based Care episode.'), 'danger');
}

$h    = $data['header'];
$pid  = (int)$h['pid'];
$q    = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;

// Pre-populate nav (avoids second DB hit)
$hbcNavPatient = [
    'fname'             => (string)$h['fname'],
    'lname'             => (string)$h['lname'],
    'pid'               => $pid,
    'referral_status'   => (string)$h['referral_status'],
    'urgency'           => (string)$h['urgency'],
    'service_city'      => (string)$h['service_city'],
    'service_state_province' => (string)$h['service_state_province'],
    'primary_diagnosis' => (string)$h['primary_diagnosis'],
];

$activePage = 'profile';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$pageTitle  = xlt('Patient Profile') . ' — ' . htmlspecialchars($h['fname'] . ' ' . $h['lname']);

$nextVisit  = $data['next_visit'];
$visits     = $data['visits'];
$vitals     = $data['vitals'];
$serviceSnapshot = $data['service_snapshot'] ?? [];
$clinicalAttention = $data['clinical_attention'] ?? [];
$carePlan   = $data['care_plan'];
$tasks      = $data['tasks'];

$addressParts = array_filter([
    (string)$h['service_address_line1'],
    (string)$h['service_address_line2'],
    implode(', ', array_filter([(string)$h['service_city'], (string)$h['service_state_province'], (string)$h['service_postal_code']])),
    (string)$h['service_country'],
]);
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
    .panel-card { border-left:3px solid #4a7c59; }
    .panel-card .card-header { font-size:.85rem; font-weight:600; }
    .visit-dot  { width:10px; height:10px; border-radius:50%; display:inline-block; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>

<?php $flash = (string)($_GET['flash'] ?? ''); ?>
<?php if ($flash === 'accepted'): ?>
<div class="alert alert-success alert-dismissible py-2 mb-3" role="alert">
  ✔ <?= xlt('Referral accepted. Please review patient details, assign clinician, and schedule first visit.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($flash === 'visit_scheduled'): ?>
<div class="alert alert-success alert-dismissible py-2 mb-3" role="alert">
  ✔ <?= xlt('Visit scheduled.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($flash === 'visits_scheduled'): ?>
<div class="alert alert-success alert-dismissible py-2 mb-3" role="alert">
  ✔ <?= htmlspecialchars((string)($_GET['count'] ?? '')) ?> <?= xlt('recurring visits scheduled.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php elseif ($flash === 'visit_complete'): ?>
<div class="alert alert-success alert-dismissible py-2 mb-3" role="alert">
  ✔ <?= xlt('Visit finalized and follow-up workflow updated.') ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Profile header ─────────────────────────────────────────────────── -->
<div class="p-3 mb-3 rounded-2"
     style="background:linear-gradient(135deg,#2c5f4a,#4a7c59);color:#fff;">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div>
      <div class="fs-4 fw-bold">
        🏡 <?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?>
        <span class="badge <?= HbcReferralStatus::badge($h['referral_status']) ?> ms-2 fs-6">
          <?= htmlspecialchars(HbcReferralStatus::label($h['referral_status'])) ?>
        </span>
        <?php if ($h['urgency'] !== 'ROUTINE'): ?>
          <span class="badge <?= $h['urgency'] === 'EMERGENT' ? 'bg-danger' : 'bg-warning text-dark' ?> ms-1 fs-6">
            <?= htmlspecialchars($h['urgency']) ?>
          </span>
        <?php endif; ?>
      </div>
      <?php if ($h['primary_diagnosis']): ?>
      <div class="text-white-50 mt-1" style="font-size:.9rem;">
        <?= xlt('Dx') ?>: <strong class="text-white"><?= htmlspecialchars($h['primary_diagnosis']) ?></strong>
        <?php if ($h['primary_icd10']): ?>
          <span class="opacity-75 ms-1">(<?= htmlspecialchars($h['primary_icd10']) ?>)</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($h['clinician_name']): ?>
      <div class="text-white-50 mt-1" style="font-size:.85rem;">
        <?= xlt('Clinician') ?>: <strong class="text-white"><?= htmlspecialchars($h['clinician_name']) ?></strong>
      </div>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?= htmlspecialchars($_hbcBase . 'edit_episode.php' . $q) ?>"
           class="btn btn-sm btn-outline-light">✏️ <?= xlt('Edit Episode') ?></a>
      </div>
    </div>
    <div class="d-flex flex-column gap-1 align-items-end small text-white-50">
      <?php if ($addressParts): ?>
        <span>📍 <?= htmlspecialchars(implode(' · ', array_slice($addressParts, 0, 2))) ?></span>
      <?php endif; ?>
      <?php if ($h['caregiver_name']): ?>
        <span>👤 <?= htmlspecialchars($h['caregiver_name']) ?>
          <?php if ($h['caregiver_phone']): ?>
            · <?= htmlspecialchars($h['caregiver_phone']) ?>
          <?php endif; ?>
          <?php if ($h['caregiver_relationship']): ?>
            (<?= htmlspecialchars($h['caregiver_relationship']) ?>)
          <?php endif; ?>
        </span>
      <?php endif; ?>
      <?php if ($h['referral_source']): ?>
        <span>📨 <?= htmlspecialchars($h['referral_source']) ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>


<!-- ── Service snapshot / attention ────────────────────────────────────── -->
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="card panel-card h-100">
      <div class="card-header">📈 <?= xlt('Service Snapshot') ?></div>
      <div class="card-body">
        <div class="row g-3 small">
          <div class="col-6 col-md-3">
            <div class="text-muted"><?= xlt('Visits Completed') ?></div>
            <div class="fs-5 fw-semibold"><?= (int)($serviceSnapshot['completed_visits'] ?? 0) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted"><?= xlt('Open Tasks') ?></div>
            <div class="fs-5 fw-semibold"><?= (int)($serviceSnapshot['open_tasks'] ?? 0) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted"><?= xlt('Coordination') ?></div>
            <div class="fs-5 fw-semibold"><?= (int)($serviceSnapshot['coordination_tasks'] ?? 0) ?></div>
          </div>
          <div class="col-6 col-md-3">
            <div class="text-muted"><?= xlt('Med Review') ?></div>
            <div class="fs-5 fw-semibold"><?= (int)($serviceSnapshot['medrec_tasks'] ?? 0) ?></div>
          </div>
        </div>
        <?php if (!empty($serviceSnapshot['last_visit_datetime'])): ?>
        <div class="mt-3 small">
          <div class="text-muted"><?= xlt('Last completed visit') ?></div>
          <div class="fw-semibold">
            <?= htmlspecialchars((new DateTime((string)$serviceSnapshot['last_visit_datetime']))->format('D d M Y H:i')) ?>
            <?php if (!empty($serviceSnapshot['last_visit_type'])): ?>
              · <span class="badge <?= HbcVisitType::badge((string)$serviceSnapshot['last_visit_type']) ?>"><?= htmlspecialchars(HbcVisitType::short((string)$serviceSnapshot['last_visit_type'])) ?></span>
            <?php endif; ?>
          </div>
          <?php if (!empty($serviceSnapshot['last_visit_outcome'])): ?>
          <div class="text-muted mt-1"><?= htmlspecialchars(mb_strimwidth((string)$serviceSnapshot['last_visit_outcome'], 0, 120, '…')) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($serviceSnapshot['recommended_due_date'])): ?>
        <div class="mt-3 small">
          <div class="text-muted"><?= xlt('Recommended next visit') ?></div>
          <div class="fw-semibold">
            <?= htmlspecialchars((string)$serviceSnapshot['recommended_due_date']) ?>
            <?php if (!empty($serviceSnapshot['recommended_visit_type'])): ?>
              · <?= htmlspecialchars((string)$serviceSnapshot['recommended_visit_type']) ?>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($serviceSnapshot['cert_period_end'])): ?>
        <div class="mt-3 small border-top pt-2">
          <div class="text-muted"><?= xlt('Cert Period') ?></div>
          <div class="fw-semibold">
            <?= htmlspecialchars((string)$serviceSnapshot['cert_period_start']) ?>
            &rarr;
            <?= htmlspecialchars((string)$serviceSnapshot['cert_period_end']) ?>
            <?php
              $certDays = $serviceSnapshot['cert_days_remaining'];
              if ($certDays !== null):
                if ($certDays < 0): ?>
                  <span class="badge bg-danger ms-1"><?= xlt('Expired') ?></span>
                <?php elseif ($certDays <= 7): ?>
                  <span class="badge bg-danger ms-1"><?= $certDays ?>d <?= xlt('left') ?></span>
                <?php elseif ($certDays <= 14): ?>
                  <span class="badge bg-warning text-dark ms-1"><?= $certDays ?>d <?= xlt('left') ?></span>
                <?php else: ?>
                  <span class="badge bg-secondary ms-1"><?= $certDays ?>d <?= xlt('left') ?></span>
                <?php endif;
              endif; ?>
          </div>
          <?php if ($serviceSnapshot['authorized_visits_per_week'] !== null): ?>
          <div class="mt-1">
            <span class="text-muted"><?= xlt('Auth') ?>:</span>
            <strong><?= (int)$serviceSnapshot['authorized_visits_per_week'] ?></strong>/<?= xlt('wk') ?>
            &middot;
            <span class="text-muted"><?= xlt('Used') ?>:</span>
            <strong><?= (int)$serviceSnapshot['cert_visit_count'] ?></strong>
            <?= xlt('in') ?> <?= (int)$serviceSnapshot['cert_weeks_elapsed'] ?> <?= xlt('wks') ?>
            <?php
              $authPW = (int)$serviceSnapshot['authorized_visits_per_week'];
              $weeks  = max(1, (int)$serviceSnapshot['cert_weeks_elapsed']);
              $expected = $authPW * $weeks;
              $actual   = (int)$serviceSnapshot['cert_visit_count'];
              if ($expected > 0):
                $pct = round($actual / $expected * 100);
            ?>
            <div class="progress mt-1" style="height:5px;">
              <div class="progress-bar <?= $pct < 70 ? 'bg-warning' : 'bg-success' ?>"
                   style="width:<?= min(100, $pct) ?>%"></div>
            </div>
            <div class="text-muted" style="font-size:.7rem;"><?= $pct ?>% <?= xlt('of expected') ?></div>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>⚠ <?= xlt('Clinical Attention') ?></span>
        <?php $attnClass = ($clinicalAttention['priority_band'] ?? 'low') === 'high' ? 'text-bg-danger' : (($clinicalAttention['priority_band'] ?? 'low') === 'medium' ? 'text-bg-warning' : 'text-bg-secondary'); ?>
        <span class="badge <?= $attnClass ?>"><?= htmlspecialchars(strtoupper((string)($clinicalAttention['priority_band'] ?? 'low'))) ?></span>
      </div>
      <div class="card-body small">
        <?php if (!empty($clinicalAttention['reasons'])): ?>
          <ul class="mb-2 ps-3">
            <?php foreach (array_slice($clinicalAttention['reasons'], 0, 5) as $reason): ?>
              <li><?= htmlspecialchars((string)$reason) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-muted"><?= xlt('No major attention flags right now.') ?></div>
        <?php endif; ?>
        <div class="row g-2 mt-2">
          <div class="col-6"><div class="border rounded p-2"><div class="text-muted"><?= xlt('Pending MAR') ?></div><div class="fw-semibold"><?= (int)($clinicalAttention['pending_mar_count'] ?? 0) ?></div></div></div>
          <div class="col-6"><div class="border rounded p-2"><div class="text-muted"><?= xlt('Recent Incidents') ?></div><div class="fw-semibold"><?= (int)($clinicalAttention['recent_incident_count'] ?? 0) ?></div></div></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── Row 1: Next visit | Vitals | Tasks ─────────────────────────────── -->
<div class="row g-3 mb-3">

  <!-- NEXT VISIT -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>📅 <?= xlt('Next Visit') ?></span>
        <?php if ($manifest->featureEnabled('hbc_schedule')): ?>
        <a href="<?= htmlspecialchars($_hbcBase . 'schedule.php' . $q) ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
          <?= xlt('Schedule') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($nextVisit): ?>
          <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge <?= HbcVisitType::badge($nextVisit['visit_type']) ?>">
              <?= htmlspecialchars(HbcVisitType::label($nextVisit['visit_type'])) ?>
            </span>
            <span class="badge <?= HbcVisitStatus::badge($nextVisit['status']) ?>">
              <?= htmlspecialchars(HbcVisitStatus::label($nextVisit['status'])) ?>
            </span>
          </div>
          <div class="fw-semibold">
            <?= htmlspecialchars((new \DateTime($nextVisit['scheduled_datetime']))->format('D d M Y H:i')) ?>
          </div>
          <?php if ($nextVisit['clinician_name']): ?>
          <div class="text-muted small mt-1">
            <?= htmlspecialchars($nextVisit['clinician_name']) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($nextVisit['route_sequence']) || !empty($nextVisit['window_start_datetime']) || !empty($nextVisit['window_end_datetime'])): ?>
          <div class="text-muted small mt-1">
            <?php if (!empty($nextVisit['route_sequence'])): ?>#<?= (int)$nextVisit['route_sequence'] ?><?php endif; ?>
            <?php if (!empty($nextVisit['window_start_datetime']) || !empty($nextVisit['window_end_datetime'])): ?>
              · <?= xlt('Window') ?>
              <?= htmlspecialchars(trim((!empty($nextVisit['window_start_datetime']) ? (new DateTime($nextVisit['window_start_datetime']))->format('H:i') : '') . ' – ' . (!empty($nextVisit['window_end_datetime']) ? (new DateTime($nextVisit['window_end_datetime']))->format('H:i') : ''), ' –')) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($nextVisit['travel_notes'])): ?>
          <div class="text-muted small mt-1">🚗 <?= htmlspecialchars($nextVisit['travel_notes']) ?></div>
          <?php endif; ?>
          <?php if ($manifest->featureEnabled('hbc_visit') && !HbcVisitStatus::isFinal((string)$nextVisit['status'])): ?>
          <div class="mt-2">
            <a href="<?= htmlspecialchars($_hbcBase . 'visit.php?visit_id=' . $nextVisit['visit_id'] . '&episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId) ?>" class="btn btn-sm btn-outline-primary py-0">
              <?= xlt('Open Visit') ?>
            </a>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <div class="text-muted small">
            <?= xlt('No visits scheduled.') ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- VITALS SNAPSHOT -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>💓 <?= xlt('Latest Vitals') ?></span>
        <?php if ($manifest->featureEnabled('hbc_vitals')): ?>
        <a href="<?= htmlspecialchars($_hbcBase . 'vitals.php' . $q) ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
          + <?= xlt('Record') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body small">
        <?php if ($vitals): $v = $vitals; ?>
          <?php if ($v['bp_systolic'] !== null): ?>
          <div class="mb-1">
            <span class="text-muted"><?= xlt('BP') ?>:</span>
            <strong class="<?= ($v['bp_systolic'] > 180 || $v['bp_systolic'] < 80) ? 'text-danger' : '' ?>">
              <?= $v['bp_systolic'] ?>/<?= $v['bp_diastolic'] ?> mmHg
            </strong>
          </div>
          <?php endif; ?>
          <?php if ($v['hr'] !== null): ?>
          <div class="mb-1">
            <span class="text-muted"><?= xlt('HR') ?>:</span>
            <strong class="<?= ($v['hr'] > 120 || $v['hr'] < 45) ? 'text-danger' : '' ?>">
              <?= $v['hr'] ?> bpm
            </strong>
          </div>
          <?php endif; ?>
          <?php if ($v['spo2'] !== null): ?>
          <div class="mb-1">
            <span class="text-muted"><?= xlt('SpO₂') ?>:</span>
            <strong class="<?= $v['spo2'] < 90 ? 'text-danger' : ($v['spo2'] < 94 ? 'text-warning' : '') ?>">
              <?= $v['spo2'] ?>%
            </strong>
          </div>
          <?php endif; ?>
          <?php if ($v['weight_kg'] !== null): ?>
          <div class="mb-1">
            <span class="text-muted"><?= xlt('Wt') ?>:</span>
            <strong><?= number_format($v['weight_kg'], 1) ?> kg</strong>
          </div>
          <?php endif; ?>
          <div class="text-muted mt-2" style="font-size:.72rem;">
            <?= htmlspecialchars(substr($v['noted_datetime'], 0, 16)) ?>
          </div>
        <?php else: ?>
          <div class="text-muted"><?= xlt('No vitals recorded.') ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- OPEN TASKS -->
  <div class="col-md-4">
    <div class="card panel-card h-100">
      <div class="card-header">
        📋 <?= xlt('Open Tasks') ?>
        <?php if ($tasks): ?>
          <span class="badge text-bg-secondary ms-1"><?= count($tasks) ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if ($tasks): ?>
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($tasks as $t):
              $overdue = !empty($t['due_datetime']) && $t['due_datetime'] < date('Y-m-d H:i:s');
          ?>
          <tr class="<?= $overdue ? 'table-danger' : '' ?>">
            <td style="font-size:.82rem;">
              <span class="badge text-bg-light border"><?= htmlspecialchars($t['task_type'] ?? '') ?></span>
            </td>
            <td class="text-muted small">
              <?= htmlspecialchars(substr((string)($t['due_datetime'] ?? ''), 0, 10)) ?>
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

<!-- ── Row 2: Care plan | Visit history ───────────────────────────────── -->
<div class="row g-3 mb-3">

  <!-- CARE PLAN -->
  <div class="col-md-5">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>🎯 <?= xlt('Care Plan') ?></span>
        <?php if ($manifest->featureEnabled('care_plan')): ?>
        <a href="<?= htmlspecialchars($_pubBase . 'shared/care_plan.php' . $q) ?>"
           class="btn btn-xs btn-outline-secondary" style="font-size:.72rem;padding:.15rem .4rem;">
          <?= xlt('Edit') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body small">
        <?php $cp = $carePlan; $total = $cp['counts']['goals'] + $cp['counts']['activities']; ?>
        <?php if ($total > 0): ?>
          <?php $pct = $total > 0 ? round($cp['counts']['completed'] / $total * 100) : 0; ?>
          <div class="progress mb-2" style="height:6px;">
            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="text-muted mb-2">
            <?= $cp['counts']['completed'] ?>/<?= $total ?> <?= xlt('items completed') ?> (<?= $pct ?>%)
          </div>
          <?php foreach (array_slice($cp['goals'], 0, 4) as $g): ?>
          <div class="d-flex gap-2 mb-1 align-items-start">
            <span class="badge text-bg-light border text-muted"><?= xlt('Goal') ?></span>
            <span class="<?= strtolower($g['plan_status'] ?? '') === 'completed' ? 'text-decoration-line-through text-muted' : '' ?>">
              <?= htmlspecialchars((string)($g['description'] ?? '')) ?>
            </span>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted">
            <?= xlt('No care plan entries yet.') ?>
            <?php if ($manifest->featureEnabled('care_plan')): ?>
              <a href="<?= htmlspecialchars($_pubBase . 'shared/care_plan.php' . $q) ?>" class="ms-1">
                <?= xlt('Create') ?>
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- VISIT HISTORY -->
  <div class="col-md-7">
    <div class="card panel-card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>📅 <?= xlt('Recent Visits') ?></span>
        <?php if ($manifest->featureEnabled('hbc_schedule')): ?>
        <a href="<?= htmlspecialchars($_hbcBase . 'schedule.php' . $q) ?>"
           class="btn btn-sm btn-outline-primary py-0">
          + <?= xlt('Schedule') ?>
        </a>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <?php if ($visits): ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0" style="font-size:.82rem;">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Date') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Status') ?></th>
                <th><?= xlt('Clinician') ?></th>
                <th><?= xlt('Route / Window') ?></th>
                <th><?= xlt('Sig') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($visits as $v): ?>
            <tr>
              <td class="text-nowrap">
                <?= htmlspecialchars(substr($v['scheduled'], 0, 16)) ?>
              </td>
              <td>
                <span class="badge <?= HbcVisitType::badge($v['visit_type']) ?>">
                  <?= htmlspecialchars(HbcVisitType::short($v['visit_type'])) ?>
                </span>
              </td>
              <td>
                <span class="badge <?= HbcVisitStatus::badge($v['status']) ?>">
                  <?= htmlspecialchars(HbcVisitStatus::label($v['status'])) ?>
                </span>
                <?php if ($v['is_draft']): ?>
                  <span class="badge bg-warning text-dark ms-1" style="font-size:.6rem;">DRAFT</span>
                <?php endif; ?>
              </td>
              <td class="text-muted">
                <?= htmlspecialchars($v['clinician']) ?>
                <?php if ($manifest->featureEnabled('hbc_visit') && !HbcVisitStatus::isFinal((string)$v['status'])): ?>
                  <div><a class="small" href="<?= htmlspecialchars($_hbcBase . 'visit.php?visit_id=' . $v['visit_id'] . '&episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId) ?>"><?= xlt('Open') ?></a></div>
                <?php endif; ?>
              </td>
              <td class="text-muted small">
                <?php if (!empty($v['route_sequence'])): ?>#<?= (int)$v['route_sequence'] ?><?php endif; ?>
                <?php if (!empty($v['window_start_datetime']) || !empty($v['window_end_datetime'])): ?>
                  <div>
                    <?= htmlspecialchars(trim((!empty($v['window_start_datetime']) ? (new DateTime($v['window_start_datetime']))->format('H:i') : '') . ' – ' . (!empty($v['window_end_datetime']) ? (new DateTime($v['window_end_datetime']))->format('H:i') : ''), ' –')) ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($v['travel_notes'])): ?>
                  <div><?= htmlspecialchars(mb_strimwidth((string)$v['travel_notes'], 0, 28, '…')) ?></div>
                <?php endif; ?>
              </td>
              <td><?= $v['sig_obtained'] ? '<span class="text-success">✓</span>' : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="text-muted small p-3"><?= xlt('No visits recorded yet.') ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- ── Clinical workflow links ─────────────────────────────────────────── -->
<div class="card shadow-sm mb-3">
  <div class="card-header small fw-semibold">🔗 <?= xlt('Clinical Workflows') ?></div>
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2">
      <?php
      $links = [
        ['hbc_schedule',    xlt('Schedule Visit'), '📅', $_hbcBase . 'schedule.php' . $q],
        ['hbc_visit',       xlt('Visit Workspace'), '🩺', $nextVisit && !empty($nextVisit['visit_id']) ? $_hbcBase . 'visit.php?visit_id=' . $nextVisit['visit_id'] . '&episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId : ''],
        ['care_plan',       xlt('Care Plan'),      '🎯', $_pubBase . 'shared/care_plan.php' . $q],
        ['clinical_notes',  xlt('Clinical Notes'), '📝', $_pubBase . 'shared/clinical_notes.php' . $q],
        ['care_team',       xlt('Care Team'),      '👥', $_pubBase . 'shared/care_team.php' . $q],
        ['episode_documents', xlt('Documents'),    '📎', $_pubBase . 'episode_documents.php' . $q],
        ['ereferral',       xlt('eReferral'),      '📤', $_pubBase . 'ereferral.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
        ['hbc_comm_log',    xlt('Comm Log'),       '📞', $_hbcBase . 'comm_log.php' . $q],
        ['hbc_fall_risk',   xlt('Fall Risk'),      '⚠️', $_hbcBase . 'fall_risk.php' . $q],
        ['al_incident',     xlt('Incidents'),      '🚨', $_hbcBase . 'incident.php' . $q],
        ['mar',             xlt('MAR'),            '💊', $_pubBase . 'mar.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
        ['hbc_discharge',   xlt('Discharge'),      '🚪', $_hbcBase . 'discharge.php' . $q],
      ];
      foreach ($links as [$feature, $label, $icon, $url]):
          if (!$manifest->featureEnabled($feature) || $url === '') continue;
      ?>
      <a href="<?= htmlspecialchars($url) ?>" class="btn btn-sm btn-outline-secondary">
        <?= $icon ?> <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>


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
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>

































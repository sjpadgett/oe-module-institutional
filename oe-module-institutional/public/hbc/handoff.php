<?php

/**
 * public/hbc/handoff.php
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
 * public/hbc/handoff.php — HBC Clinician Handoff Report
 *
 * Facility-wide snapshot for outgoing → incoming home-care staff.
 * One row per active HBC patient. Print-optimised.
 *
 * Columns:
 *   Patient · Days on service · Address/City · Clinician
 *   Vitals · Pending MAR · Next visit · Fall risk · Care plan goal
 *   Clinical flags · Closure plan status
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcHandoff\Controller\HbcHandoffController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

if (!$manifest->featureEnabled('hbc_handoff')) {
    oei_exit_with_alert(xlt('HBC Handoff is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$isPrint    = isset($_GET['print']);

$controller = new HbcHandoffController();
$vm         = $controller->handle($facilityId);
$rows       = $vm['rows'];
$summary    = $vm['summary'];

// Batch-fetch patient names
$_pids         = array_values(array_unique(array_filter(array_column($rows, 'pid'))));
$_patientNames = oei_patient_names(array_map('intval', $_pids));

$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

$_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';
$_hbc_base = $_pub_base . 'hbc/';

$flagIcons = [
    'flag_supervisory_due' => ['ð', xlt('Supervisory due')],
    'flag_cert_expiring' => ['📅', xlt('Cert expiring')],
    'flag_mar_overdue'   => ['🔴', xlt('Meds overdue')],
    'flag_fall_reassess' => ['📋', xlt('Fall reassess due')],
    'flag_discharge'     => ['🚪', xlt('Closure pending')],
    'flag_incident'      => ['🚨', xlt('Incident this week')],
    'flag_urgent'        => ['⚠️', xlt('Emergent urgency')],
    'flag_no_visit_soon' => ['📅', xlt('No visit <72h')],
];

$urgBadge = ['ROUTINE'=>'secondary','URGENT'=>'warning','EMERGENT'=>'danger'];

$dispLabel = [
    'SERVICE_COMPLETED' => 'Svc complete',
    'HOSPITAL_TRANSFER' => 'Hospital xfer',
    'SNF_TRANSFER'      => 'SNF/AL',
    'SELF_DISCHARGE'    => 'Self d/c',
    'NON_COMPLIANT'     => 'Non-comply',
    'PAYER_CLOSED'      => 'Payer closed',
    'DECEASED'          => 'Deceased',
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= xlt('HBC Clinician Handoff') ?> — <?= htmlspecialchars(date('F j, Y')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .row-normal  { border-left: 3px solid transparent; }
    .row-caution { border-left: 3px solid #ffc107; background: var(--bs-warning-bg-subtle) !important; }
    .row-alert   { border-left: 3px solid #dc3545; background: var(--bs-danger-bg-subtle) !important; }
    .flag-chip   {
      display:inline-flex; align-items:center; gap:.2rem;
      font-size:.65rem; padding:.1rem .4rem; border-radius:999px;
      background:var(--bs-danger-bg-subtle); color:var(--bs-danger-text-emphasis);
      white-space:nowrap; margin:.1rem;
    }
    .vitals-str  { font-size:.75rem; font-family:monospace; white-space:nowrap; }
    .goal-text   { font-size:.72rem; max-width:220px; line-height:1.3; }
    @media print {
      .no-print  { display:none !important; }
      body       { background:#fff !important; color:#000 !important; font-size:9pt; }
      .container-fluid { padding:0; }
      table      { font-size:8pt; }
      th, td     { padding:3px 5px !important; }
      .row-caution { background:#fffde7 !important; }
      .row-alert   { background:#ffebee !important; }
      .flag-chip   { border:1px solid #ccc; background:none; color:#333; font-size:7pt; }
      .card        { border:1px solid #ddd; box-shadow:none; }
      a            { text-decoration:none; color:inherit; }
    }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <!-- ── Header ────────────────────────────────────────────────────────── -->
  <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2 no-print">
    <div>
      <h1 class="h4 mb-0">🏠 <?= xlt('HBC Clinician Handoff') ?></h1>
      <div class="text-muted small mt-1">
        <?= htmlspecialchars(date('l, F j, Y  g:i A')) ?>
        &mdash; <?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center no-print">
      <a href="<?= htmlspecialchars($_hbc_base . 'board.php?facility_id=' . $facilityId) ?>"
         class="btn btn-sm btn-outline-secondary">← <?= xlt('Visit Board') ?></a>
      <a href="<?= htmlspecialchars($_hbc_base . 'handoff.php?facility_id=' . $facilityId . '&print=1') ?>"
         target="_blank" class="btn btn-sm btn-outline-secondary">🖨 <?= xlt('Print') ?></a>
    </div>
  </div>

  <!-- ── Summary badges ─────────────────────────────────────────────────── -->
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <span class="badge text-bg-primary fs-6"><?= $summary['total_active'] ?> <?= xlt('Active') ?></span>
    <?php if ($summary['urgent_active'] > 0): ?>
    <span class="badge text-bg-danger fs-6"><?= $summary['urgent_active'] ?> <?= xlt('Emergent') ?></span>
    <?php endif; ?>
    <?php if ($summary['pending_closure'] > 0): ?>
    <span class="badge text-bg-warning text-dark fs-6"><?= $summary['pending_closure'] ?> <?= xlt('Closure Pending') ?></span>
    <?php endif; ?>
  </div>

  <!-- ── Print header ────────────────────────────────────────────────────── -->
  <?php if ($isPrint): ?>
  <div class="mb-3">
    <h2 class="h5"><?= xlt('HBC Clinician Handoff Report') ?></h2>
    <div><?= htmlspecialchars(date('l, F j, Y  g:i A')) ?></div>
    <div><?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?></div>
  </div>
  <?php endif; ?>

  <?php if (empty($rows)): ?>
  <div class="alert alert-info"><?= xlt('No active Home-Based Care patients.') ?></div>
  <?php else: ?>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" style="font-size:.82rem;">
        <thead class="table-dark">
          <tr>
            <th><?= xlt('Patient') ?></th>
            <th><?= xlt('Days') ?></th>
            <th><?= xlt('Location') ?></th>
            <th><?= xlt('Clinician') ?></th>
            <th><?= xlt('Vitals') ?></th>
            <th><?= xlt('Next Visit') ?></th>
            <th><?= xlt('MAR') ?></th>
            <th><?= xlt('Fall Risk') ?></th>
            <th><?= xlt('Cert') ?></th>
            <th><?= xlt('Goal') ?></th>
            <th><?= xlt('Flags') ?></th>
            <th><?= xlt('Closure') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $flagCount  = (int)($r['flag_count'] ?? 0);
            $rowClass   = $flagCount >= 3 ? 'row-alert' : ($flagCount >= 1 ? 'row-caution' : 'row-normal');
            $frLevel    = strtoupper((string)($r['fall_risk_level'] ?? 'LOW'));
            $frDays     = $r['days_since_fall_reassess'] ?? null;
            $frOverdue  = ($frDays !== null && (int)$frDays >= 30);
            $urg        = strtoupper((string)($r['urgency'] ?? 'ROUTINE'));
            $epId       = (int)$r['episode_id'];
            $epPid      = (int)$r['pid'];
            $profUrl    = $_hbc_base . 'profile.php?episode_id=' . $epId . '&pid=' . $epPid . '&facility_id=' . $facilityId;
        ?>
        <tr class="<?= $rowClass ?>">
          <!-- Patient -->
          <td>
            <a href="<?= htmlspecialchars($profUrl) ?>" class="fw-semibold text-decoration-none no-print">
              <?= oei_fmt_patient($epPid, $_patientNames) ?>
            </a>
            <span class="d-none d-print-inline fw-semibold">
              <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>
            </span>
            <?php if ($urg !== 'ROUTINE'): ?>
            <span class="badge bg-<?= $urgBadge[$urg] ?? 'secondary' ?> ms-1" style="font-size:.6rem;">
              <?= htmlspecialchars($urg) ?>
            </span>
            <?php endif; ?>
            <div class="text-muted" style="font-size:.7rem;">
              <?= htmlspecialchars(($r['age'] ?? '?') . 'y ' . ($r['sex'] ?? '')) ?>
              · <?= htmlspecialchars((string)($r['primary_diagnosis'] ?? '—')) ?>
            </div>
          </td>

          <!-- Days on service -->
          <td class="text-center fw-bold">
            <?= htmlspecialchars((string)($r['days_on_service'] ?? '—')) ?>
            <div class="text-muted fw-normal" style="font-size:.65rem;"><?= xlt('days') ?></div>
          </td>

          <!-- Location / address -->
          <td>
            <div class="text-nowrap">
              <?= htmlspecialchars((string)($r['service_city'] ?? '—')) ?>
              <?php if ($r['service_state_province'] ?? ''): ?>
                <span class="text-muted">, <?= htmlspecialchars((string)$r['service_state_province']) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($r['service_address_line1'] ?? ''): ?>
            <div class="text-muted" style="font-size:.7rem;">
              <?= htmlspecialchars(mb_strimwidth((string)$r['service_address_line1'], 0, 30, '…')) ?>
            </div>
            <?php endif; ?>
          </td>

          <!-- Clinician -->
          <td class="text-muted text-nowrap">
            <?= htmlspecialchars(trim((string)($r['clinician_name'] ?? '—'))) ?>
          </td>

          <!-- Vitals -->
          <td>
            <?php
            $bpS = $r['bp_sys'] ?? null;
            $bpD = $r['bp_dia'] ?? null;
            $hr  = $r['hr']     ?? null;
            $spo = $r['spo2']   ?? null;
            $bpAlert  = $bpS !== null && ($bpS > 160 || $bpS < 90);
            $hrAlert  = $hr  !== null && ($hr  > 110 || $hr  < 50);
            $spoAlert = $spo !== null && $spo < 93;
            ?>
            <?php if ($bpS !== null): ?>
            <div class="vitals-str <?= $bpAlert  ? 'text-danger fw-bold' : '' ?>">
              <?= htmlspecialchars("{$bpS}/{$bpD}") ?>
            </div>
            <?php endif; ?>
            <?php if ($hr !== null): ?>
            <div class="vitals-str <?= $hrAlert  ? 'text-danger fw-bold' : '' ?>">
              HR <?= htmlspecialchars((string)$hr) ?>
            </div>
            <?php endif; ?>
            <?php if ($spo !== null): ?>
            <div class="vitals-str <?= $spoAlert ? 'text-danger fw-bold' : '' ?>">
              SpO₂ <?= htmlspecialchars((string)$spo) ?>%
            </div>
            <?php endif; ?>
            <?php if ($bpS === null && $hr === null && $spo === null): ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
            <?php if ($r['vitals_datetime'] ?? ''): ?>
            <div class="text-muted" style="font-size:.65rem;">
              <?= htmlspecialchars(substr($r['vitals_datetime'], 5, 11)) ?>
            </div>
            <?php endif; ?>
          </td>

          <!-- Next visit -->
          <td>
            <?php if ($r['next_visit_datetime'] ?? ''): ?>
            <div class="text-nowrap">
              <?php
              $nvDt = new \DateTime($r['next_visit_datetime']);
              echo htmlspecialchars($nvDt->format('D d M H:i'));
              ?>
            </div>
            <div class="text-muted" style="font-size:.7rem;">
              <?= htmlspecialchars(HbcVisitType::short((string)($r['next_visit_type'] ?? ''))) ?>
              <?php if (trim((string)($r['next_visit_clinician'] ?? ''))): ?>
                · <?= htmlspecialchars(trim((string)$r['next_visit_clinician'])) ?>
              <?php endif; ?>
            </div>
            <?php else: ?>
            <span class="badge bg-warning text-dark" style="font-size:.65rem;"><?= xlt('None scheduled') ?></span>
            <?php endif; ?>
            <div class="text-muted" style="font-size:.65rem;">
              <?= (int)($r['completed_visits'] ?? 0) ?> <?= xlt('complete') ?>
            </div>
          </td>

          <!-- MAR -->
          <td class="text-center">
            <?php $pendingMar = (int)($r['pending_mar_count'] ?? 0); ?>
            <?php if ($pendingMar > 0): ?>
            <span class="badge bg-danger"><?= $pendingMar ?></span>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Fall risk -->
          <td>
            <span class="badge bg-<?= FallRiskLevel::badge($frLevel) ?>">
              <?= htmlspecialchars(FallRiskLevel::label($frLevel)) ?>
            </span>
            <?php if ($frOverdue): ?>
            <div class="flag-chip mt-1">📋 <?= xlt('Due') ?></div>
            <?php endif; ?>
          </td>

          <!-- Cert period -->
          <td>
            <?php
              $certEndStr = (string)($r['cert_period_end'] ?? '');
              if ($certEndStr !== '' && strtotime($certEndStr) !== false):
                $certDaysLeft = (int)((strtotime($certEndStr) - time()) / 86400);
            ?>
            <div class="text-nowrap" style="font-size:.75rem;">
              <?= htmlspecialchars(substr($certEndStr, 5)) ?>
              <?php if ($certDaysLeft < 0): ?>
                <span class="badge bg-danger" style="font-size:.6rem;"><?= xlt('Exp') ?></span>
              <?php elseif ($certDaysLeft <= 7): ?>
                <span class="badge bg-danger" style="font-size:.6rem;"><?= $certDaysLeft ?>d</span>
              <?php elseif ($certDaysLeft <= 14): ?>
                <span class="badge bg-warning text-dark" style="font-size:.6rem;"><?= $certDaysLeft ?>d</span>
              <?php endif; ?>
            </div>
            <?php if (($r['authorized_visits_per_week'] ?? null) !== null): ?>
            <div class="text-muted" style="font-size:.65rem;">
              <?= (int)$r['authorized_visits_per_week'] ?>/<?= xlt('wk') ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Care plan goal -->
          <td>
            <?php if ($r['care_plan_goal'] ?? ''): ?>
            <div class="goal-text"><?= htmlspecialchars((string)$r['care_plan_goal']) ?></div>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>

          <!-- Flags -->
          <td>
            <?php foreach ($flagIcons as $key => [$icon, $label]): ?>
            <?php if (!empty($r[$key])): ?>
            <div class="flag-chip"><?= $icon ?> <?= htmlspecialchars($label) ?></div>
            <?php endif; ?>
            <?php endforeach; ?>
          </td>

          <!-- Closure plan -->
          <td>
            <?php if ($r['pending_disposition'] ?? ''): ?>
            <span class="badge bg-warning text-dark" style="font-size:.65rem;">
              <?= htmlspecialchars($dispLabel[$r['pending_disposition']] ?? $r['pending_disposition']) ?>
            </span>
            <?php if ($r['pending_destination'] ?? ''): ?>
            <div class="text-muted" style="font-size:.65rem;">
              <?= htmlspecialchars(mb_strimwidth((string)$r['pending_destination'], 0, 25, '…')) ?>
            </div>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php endif; // rows ?>

  <?php if ($isPrint): ?>
  <div class="small text-muted mt-4">
    <?= xlt('Printed') ?>: <?= htmlspecialchars($vm['printed']) ?>
    &mdash; <?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?>
    &mdash; oe-module-institutional
  </div>
  <?php endif; ?>

</div>
</body>
</html>













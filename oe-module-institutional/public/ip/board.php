<?php

/**
 * public/ip/board.php
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
 * public/ip/board.php — Inpatient Floor Board
 *
 * Displays the active IP census for a facility as a dense sortable table.
 * This is the primary entry point for the INPATIENT_STAY context.
 *
 * Columns: Bed/Unit · Patient · Age · Service · Admission Type · Attending
 *          · LOS vs Expected · Diagnosis · Next Task Due · Workflow
 *          · Staff (nurse/provider) · Actions
 *
 * Actions per row (links, no inline forms):
 *   Profile · MAR · Care Plan · Clinical Notes · Dispo Plan · Discharge
 *
 * No POST handling on this page — all writes go to their respective pages.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\FloorBoard\Controller\FloorBoardController;

if (!$manifest->featureEnabled('ip_board')) {
    oei_exit_with_alert(xlt('Inpatient Floor Board is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$_oei_ip_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';

$controller = new FloorBoardController();
$data       = $controller->handle($facilityId);

$patients = $data['rows'];
$units    = $data['units'];
$counts   = $data['counts'];

// Batch-fetch patient names for display (oei_fmt_patient helper)
$_ipPids  = array_values(array_unique(array_filter(
    array_map(fn($r) => (int)($r['pid'] ?? 0), $patients)
)));
$_ipNames = function_exists('oei_patient_names') ? oei_patient_names($_ipPids) : [];

$pageTitle = xlt('IP Floor Board');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

// LOS badge helper
function ip_los_badge(string $status): string
{
    return match ($status) {
        'over'       => 'text-bg-danger',
        'approaching'=> 'text-bg-warning',
        default      => 'text-bg-success',
    };
}
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
    /* Sticky header + first two columns while scrolling */
    .ip-board-wrap {
        overflow: auto;
        max-height: calc(100vh - 210px);
        border: 0;
    }
    .ip-board-table thead th {
        position: sticky; top: 0; z-index: 3;
        background: #f8f9fa;
        box-shadow: 0 1px 0 #dee2e6;
        white-space: nowrap;
        font-size: .8rem;
    }
    .ip-board-table td { font-size: .82rem; vertical-align: middle; }
    .ip-board-table td:nth-child(1),
    .ip-board-table th:nth-child(1) {
        position: sticky; left: 0; z-index: 2;
        background: inherit; min-width: 80px;
        box-shadow: 1px 0 0 #dee2e6;
    }
    .ip-board-table td:nth-child(2),
    .ip-board-table th:nth-child(2) {
        position: sticky; left: 80px; z-index: 2;
        background: inherit; min-width: 160px;
        box-shadow: 1px 0 0 #dee2e6;
    }
    .ip-board-table thead th:nth-child(1),
    .ip-board-table thead th:nth-child(2) { z-index: 4; }
    .ip-board-table tbody tr:nth-child(odd) td { background-color: rgba(0,0,0,.04); }
    .ip-board-table tbody tr:hover td { background-color: rgba(69,123,157,.09); }
    .over-los td { border-left: 1px solid #2d2c2c !important; }
    .oei-ip-header { background: #457b9d; color: #fff; border-radius: .5rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<!-- Board header strip -->
<div class="oei-ip-header p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <span class="fs-5 fw-bold">🏥 <?= xlt('Inpatient Census') ?></span>
    <span class="ms-2 text-white-50 small">
      <?= htmlspecialchars((string)($GLOBALS['facilityName'] ?? "Facility $facilityId")) ?>
    </span>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <span class="badge bg-light text-dark">
      <?= $counts['total'] ?> <?= xlt('Patients') ?>
    </span>
    <?php if ($counts['over_los'] > 0): ?>
    <span class="badge bg-danger">
      ⚠ <?= $counts['over_los'] ?> <?= xlt('Over Expected LOS') ?>
    </span>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($_oei_ip_base) ?>board.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-light"><?= xlt('Refresh') ?></a>
    <?php if ($manifest->featureEnabled('ip_admission')): ?>
    <a href="<?= htmlspecialchars($_oei_ip_base) ?>admission.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-light text-primary fw-semibold">
      + <?= xlt('Admit Patient') ?>
    </a>
    <?php endif; ?>
    <?php if ($manifest->featureEnabled('handoff')): ?>
    <a href="../handoff.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-light">
      📋 <?= xlt('Handoff') ?>
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Unit summary strip -->
<?php if (!empty($units)): ?>
<div class="d-flex flex-wrap gap-2 mb-3">
    <?php foreach ($units as $u): ?>
  <span class="badge text-bg-secondary">
        <?= htmlspecialchars($u['unit']) ?>:
        <?= $u['total'] ?> <?= xlt('pts') ?>
        <?php if ($u['over_los'] > 0): ?>
      <span class="badge text-bg-danger ms-1">⚠ <?= $u['over_los'] ?></span>
    <?php endif; ?>
  </span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Census table -->
<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center justify-content-between">
    <span><?= xlt('Active Inpatient Episodes') ?></span>
  </div>
  <div class="ip-board-wrap">
    <table class="table table-sm align-middle mb-0 ip-board-table">
      <thead class="table-light">
        <tr>
          <th><?= xlt('Bed') ?></th>
          <th><?= xlt('Patient') ?></th>
          <th><?= xlt('Age') ?></th>
          <th><?= xlt('Service') ?></th>
          <th><?= xlt('Type') ?></th>
          <th><?= xlt('Attending') ?></th>
          <th><?= xlt('LOS') ?></th>
          <th><?= xlt('Diagnosis') ?></th>
          <th><?= xlt('Next Due') ?></th>
          <th><?= xlt('Workflow') ?></th>
          <th><?= xlt('Dispo') ?></th>
          <th><?= xlt('Staff') ?></th>
          <th><?= xlt('Actions') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($patients)): ?>
        <tr>
          <td colspan="13" class="text-center text-muted py-5">
            <?= xlt('No active inpatient admissions.') ?>
            <?php if ($manifest->featureEnabled('ip_admission')): ?>
              <a href="<?= htmlspecialchars($_oei_ip_base) ?>admission.php?facility_id=<?= $facilityId ?>" class="ms-2">
                <?= xlt('Admit a patient') ?>
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endif; ?>
      <?php foreach ($patients as $r):
            $eId      = (int)$r['episode_id'];
            $pid      = (int)$r['pid'];
            $q        = 'episode_id=' . $eId . '&pid=' . $pid . '&facility_id=' . $facilityId;
            $losOver  = $r['los_status'] === 'over';

          // Age from DOB
            $age = '';
            if (!empty($r['dob'])) {
                try {
                    $diff = (new DateTime())->diff(new DateTime($r['dob']));
                    $age  = $diff->y . 'y';
                } catch (Exception) {}
            }

          // Disposition plan badge
            $_planDisp = strtoupper((string)($r['plan_disposition_code'] ?? ''));
            $_planBadge = match($_planDisp) {
                'DISCHARGE' => 'text-bg-success',
                'TRANSFER'  => 'text-bg-warning',
                'ADMIT'     => 'text-bg-primary',
                ''          => '',
                default     => 'text-bg-secondary',
            };
    ?>
      <tr class="<?= $losOver ? 'over-los' : '' ?>">
        <!-- Bed -->
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($r['bed']) ?></div>
          <div class="text-muted small text-truncate" style="max-width:75px;">
            <?= htmlspecialchars($r['unit']) ?>
          </div>
        </td>

        <!-- Patient -->
        <td>
            <?php if ($manifest->featureEnabled('ip_profile')): ?>
            <a href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?<?= $q ?>" class="fw-semibold text-decoration-none">
                <?= function_exists('oei_fmt_patient') ? oei_fmt_patient($pid, $_ipNames) : htmlspecialchars($r['lname'] . ', ' . $r['fname']) ?>
            </a>
          <?php else: ?>
            <span class="fw-semibold">
              <?= function_exists('oei_fmt_patient') ? oei_fmt_patient($pid, $_ipNames) : htmlspecialchars($r['lname'] . ', ' . $r['fname']) ?>
            </span>
          <?php endif; ?>
        </td>

        <!-- Age -->
        <td class="text-muted"><?= htmlspecialchars($age) ?></td>

        <!-- Service -->
        <td>
          <span class="badge <?= HospitalService::badgeClass($r['service']) ?>">
            <?= htmlspecialchars(HospitalService::label($r['service'])) ?>
          </span>
        </td>

        <!-- Admission Type -->
        <td>
          <span class="badge <?= AdmissionType::badgeClass($r['admission_type']) ?>">
            <?= htmlspecialchars(AdmissionType::label($r['admission_type'])) ?>
          </span>
        </td>

        <!-- Attending -->
        <td class="text-muted small">
            <?= htmlspecialchars($r['attending_name']) ?>
        </td>

        <!-- LOS vs Expected -->
        <td>
          <span class="badge <?= ip_los_badge($r['los_status']) ?>">
            <?= $r['los_days'] ?>d
          </span>
            <?php if ($r['expected_los_days'] !== null): ?>
            <span class="text-muted small">/ <?= $r['expected_los_days'] ?>d</span>
          <?php endif; ?>
        </td>

        <!-- Diagnosis -->
        <td class="small text-truncate" style="max-width:140px;"
            title="<?= htmlspecialchars($r['admitting_diagnosis']) ?>">
            <?= htmlspecialchars($r['admitting_diagnosis'] ?: '—') ?>
        </td>

        <!-- Next task due -->
        <td>
            <?php if (!empty($r['next_task_due'])): ?>
                <?php $overdue = (strtotime($r['next_task_due']) ?: 0) < time(); ?>
            <span class="<?= $overdue ? 'text-danger' : 'text-muted' ?> small">
                <?= htmlspecialchars(substr((string)$r['next_task_due'], 11, 5)) ?>
            </span>
            <span class="badge <?= $overdue ? 'text-bg-danger' : 'text-bg-light border' ?>">
                <?= htmlspecialchars((string)$r['next_task_type']) ?>
            </span>
          <?php else: ?>
            <span class="text-muted">—</span>
          <?php endif; ?>
        </td>

        <!-- Workflow status -->
        <td>
          <span class="badge text-bg-light border">
            <?= htmlspecialchars((string)($r['workflow_status'] ?? '—')) ?>
          </span>
          <?php if ($manifest->featureEnabled('observations') && !empty($r['obs_flagged_count'])): ?>
          <a href="<?= htmlspecialchars($_oei_ip_base . '../shared/observations.php?episode_id=' . $eId . '&pid=' . $pid . '&facility_id=' . $facilityId) ?>"
             class="badge bg-warning text-dark text-decoration-none ms-1"
             title="<?= xlt('Flagged observations in last 24h') ?>">
            &#128225;&#9888; <?= (int)$r['obs_flagged_count'] ?>
          </a>
          <?php endif; ?>
        </td>

        <!-- Disposition plan -->
        <td>
            <?php if ($_planDisp !== ''): ?>
            <span class="badge <?= $_planBadge ?>"><?= htmlspecialchars($_planDisp) ?></span>
          <?php else: ?>
            <a class="small text-muted" style="font-size:.72rem;"
               href="../disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= $eId ?>">
              + <?= xlt('Plan') ?>
            </a>
          <?php endif; ?>
        </td>

        <!-- Staff -->
        <td>
            <?php if (!empty($r['nurse_name'])): ?>
            <div class="small"><span class="badge text-bg-primary"><?= htmlspecialchars($r['nurse_name']) ?></span></div>
          <?php endif; ?>
            <?php if (!empty($r['provider_name'])): ?>
            <div class="small mt-1"><span class="badge text-bg-success"><?= htmlspecialchars($r['provider_name']) ?></span></div>
          <?php endif; ?>
            <?php if (empty($r['nurse_name']) && empty($r['provider_name'])): ?>
            <span class="text-muted small fst-italic"><?= xlt('No staff') ?></span>
          <?php endif; ?>
        </td>

        <!-- Actions -->
        <td class="text-nowrap" style="min-width:180px;">
          <div class="d-flex flex-column gap-1">

            <?php if ($manifest->featureEnabled('ip_profile')): ?>
            <a class="btn btn-xs btn-outline-primary" style="font-size:.73rem;padding:.15rem .45rem;"
               href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?<?= $q ?>"><?= xlt('Profile') ?></a>
            <?php endif; ?>

            <?php if ($manifest->featureEnabled('mar')): ?>
            <a class="btn btn-xs btn-outline-secondary" style="font-size:.73rem;padding:.15rem .45rem;"
               href="../mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= $eId ?>">
              💊 <?= xlt('MAR') ?>
            </a>
            <?php endif; ?>

            <?php if ($manifest->featureEnabled('care_plan')): ?>
            <a class="btn btn-xs btn-outline-secondary" style="font-size:.73rem;padding:.15rem .45rem;"
               href="../shared/care_plan.php?<?= $q ?>">
              📋 <?= xlt('Care Plan') ?>
            </a>
            <?php endif; ?>

            <?php if ($manifest->featureEnabled('ip_discharge')): ?>
            <a class="btn btn-xs btn-outline-danger" style="font-size:.73rem;padding:.15rem .45rem;"
               href="<?= htmlspecialchars($_oei_ip_base) ?>discharge.php?<?= $q ?>">
              🚪 <?= xlt('Discharge') ?>
            </a>
            <?php endif; ?>

          </div>
        </td>

      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</div>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>










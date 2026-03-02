<?php
/**
 * public/al/board.php — Assisted Living Resident Board
 *
 * Entry point for the AL workflow. Resident name is the primary
 * link to that resident's Profile hub, from which all sub-pages
 * (Vitals, ADL, MAR, Care Plan, Fall Risk, Incident) are reached.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Controller\ResidentBoardController;

if (!$manifest->featureEnabled('al_board')) {
    oei_exit_with_alert(xlt('Resident Board is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$controller = new ResidentBoardController();
$data       = $controller->handle($facilityId);

$residents  = $data['residents'];
$units      = $data['units'];
$counts     = $data['counts'];

$pageTitle = xlt('AL Resident Board');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .al-card { border-left: 4px solid #4a7c59; transition: box-shadow .15s; }
    .al-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,.18); }
    /* Fall risk colouring on left border */
    .risk-HIGH     { border-left-color: #dc3545 !important; }
    .risk-MODERATE { border-left-color: #fd7e14 !important; }
    .risk-LOW      { border-left-color: #198754 !important; }
    /* ADL overdue tint — uses Bootstrap subtle warning in both themes */
    .adl-overdue   { background: var(--bs-warning-bg-subtle) !important; }
    /* Resident name link */
    .resident-link { text-decoration: none; color: inherit; }
    .resident-link:hover { text-decoration: underline; color: #4a7c59; }
    /* Quick-action footer */
    .card-footer .btn { font-size: .72rem; padding: 2px 7px; }
    /* Board header strip */
    .oei-board-header { background: #4a7c59; color: #fff; border-radius: .5rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<!-- Board header -->
<div class="oei-board-header p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <span class="fs-5 fw-bold">🏡 <?= xlt('Resident Census') ?></span>
    <span class="ms-2 text-white-50 small">
      <?= htmlspecialchars((string)($GLOBALS['facilityName'] ?? "Facility $facilityId")) ?>
    </span>
  </div>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <span class="badge bg-light text-dark">
      <?= $counts['total'] ?> <?= xlt('Residents') ?>
    </span>
    <span class="badge bg-danger">⚠ <?= $counts['high_risk'] ?> <?= xlt('High Fall Risk') ?></span>
    <span class="badge bg-warning text-dark">🏥 <?= $counts['high_care'] ?> <?= xlt('Tier 3') ?></span>
    <a href="<?= $_SERVER['PHP_SELF'] ?>?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-light"><?= xlt('Refresh') ?></a>
    <?php if ($manifest->featureEnabled('al_intake')): ?>
    <a href="intake.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-light text-dark fw-semibold">
      + <?= xlt('Admit Resident') ?>
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Unit summary pills -->
<?php if (!empty($units)): ?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ($units as $u): ?>
  <span class="badge bg-secondary">
    <?= htmlspecialchars($u['unit'] ?: xlt('Unassigned')) ?>:
    <?= $u['total'] ?> <?= xlt('res') ?>
    <?php if ($u['high_risk'] > 0): ?>
      &nbsp;<span class="text-warning fw-bold">⚠ <?= $u['high_risk'] ?></span>
    <?php endif; ?>
  </span>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Resident cards -->
<?php if (empty($residents)): ?>
  <div class="alert alert-info"><?= xlt('No active residents found for this facility.') ?></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($residents as $r):
    $eid      = (int)$r['episode_id'];
    $pid      = (int)$r['pid'];
    $adlDue   = $r['adl_due'] ?? false;
    $qEpPid   = "episode_id=$eid&pid=$pid&facility_id=$facilityId";
    $profileUrl = "profile.php?$qEpPid";
  ?>
  <div class="col-12 col-md-6 col-xl-4">
    <div class="card al-card h-100 risk-<?= htmlspecialchars($r['fall_risk_level']) ?><?= $adlDue ? ' adl-overdue' : '' ?>">

      <div class="card-body pb-2">

        <!-- Resident name → Profile (primary action) -->
        <div class="d-flex justify-content-between align-items-start mb-1">
          <div>
            <a href="<?= htmlspecialchars($profileUrl) ?>" class="resident-link fw-bold fs-6">
              <?= htmlspecialchars($r['display_name']) ?>
            </a>
            <span class="text-muted ms-1 small">
              <?= (int)$r['age'] ?>y <?= htmlspecialchars($r['gender']) ?>
            </span>
          </div>
          <span class="badge bg-secondary small">
            <?= htmlspecialchars($r['unit'] ?: '—') ?> / <?= htmlspecialchars($r['room'] ?: '—') ?>
          </span>
        </div>

        <!-- Badges row -->
        <div class="d-flex flex-wrap gap-1 mb-2">
          <span class="badge bg-<?= htmlspecialchars($r['care_level_badge']) ?>">
            <?= htmlspecialchars($r['care_level_label']) ?>
          </span>
          <span class="badge bg-<?= $r['fall_risk_level'] === 'HIGH' ? 'danger' : ($r['fall_risk_level'] === 'MODERATE' ? 'warning text-dark' : 'success') ?>">
            <?= htmlspecialchars($r['fall_risk_label']) ?>
          </span>
          <?php if ($adlDue): ?>
          <span class="badge bg-warning text-dark">⏰ <?= xlt('ADL Due') ?></span>
          <?php endif; ?>
          <?php if (!empty($r['last_adl_score'])): ?>
          <span class="badge bg-secondary" title="<?= xlt('Last ADL score') ?>">
            ADL <?= (int)$r['last_adl_score'] ?>
          </span>
          <?php endif; ?>
        </div>

        <!-- Care team -->
        <div class="small text-muted mb-1">
          <?php if ($r['primary_provider']): ?>
            👨‍⚕️ <?= htmlspecialchars($r['primary_provider']) ?>
          <?php endif; ?>
          <?php if ($r['primary_nurse']): ?>
            <?= $r['primary_provider'] ? ' · ' : '' ?>
            👩‍⚕️ <?= htmlspecialchars($r['primary_nurse']) ?>
          <?php endif; ?>
        </div>

        <!-- Admission info -->
        <div class="small text-muted">
          <?= xlt('Day') ?> <?= (int)$r['days_resident'] ?>
          &nbsp;·&nbsp;
          <?= xlt('Admitted') ?> <?= htmlspecialchars(date('M j, Y', strtotime($r['start_datetime']))) ?>
        </div>
      </div>

      <!-- Quick-action footer — all sub-pages linked with episode context -->
      <div class="card-footer bg-transparent pt-1 pb-2">
        <div class="d-flex gap-1 flex-wrap">

          <?php if ($manifest->featureEnabled('al_profile')): ?>
          <a href="<?= htmlspecialchars($profileUrl) ?>"
             class="btn btn-sm btn-success" title="<?= xlt('Resident Profile Hub') ?>">
            🏠 <?= xlt('Profile') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_vitals')): ?>
          <a href="vitals.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-info" title="<?= xlt('Record vitals') ?>">
            🩺 <?= xlt('Vitals') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_adl')): ?>
          <a href="adl.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-primary" title="<?= xlt('Chart ADL session') ?>">
            📊 <?= xlt('ADL') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_care_plan')): ?>
          <a href="care_plan.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-success" title="<?= xlt('Care plan') ?>">
            📋 <?= xlt('Plan') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_mar')): ?>
          <a href="al_mar.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-warning" title="<?= xlt('Medication Administration') ?>">
            💊 <?= xlt('MAR') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_fall_risk')): ?>
          <a href="fall_risk.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-secondary" title="<?= xlt('Fall risk reassessment') ?>">
            ⚠️ <?= xlt('Falls') ?>
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_incident')): ?>
          <a href="incident.php?episode_id=<?= $eid ?>&facility_id=<?= $facilityId ?>"
             class="btn btn-sm btn-outline-danger" title="<?= xlt('Report incident') ?>">
            🚨
          </a>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('al_discharge')): ?>
          <a href="discharge.php?<?= $qEpPid ?>"
             class="btn btn-sm btn-outline-secondary" title="<?= xlt('Discharge / Transfer planning') ?>">
            🚪 <?= xlt('D/C Plan') ?>
          </a>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

</div><!-- /container -->

<script>
// Auto-refresh every 3 minutes
setTimeout(function () { location.reload(); }, 180000);
</script>
</body>
</html>

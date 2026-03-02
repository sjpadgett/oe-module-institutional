<?php
/**
 * public/al/board.php — Assisted Living Resident Board
 *
 * Census view sorted by unit → room. Shows care level, fall risk,
 * ADL chart status, and care team assignment per resident.
 *
 * Thin public file — delegates entirely to ResidentBoardController.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Controller\ResidentBoardController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

if (!$manifest->featureEnabled('al_board')) {
    echo '<p class="text-muted p-3">' . xlt('Resident Board is not enabled.') . '</p>';
    exit;
}

$facilityId = $_oei_facilityId ?? 1;
$controller = new ResidentBoardController();
$data       = $controller->handle($facilityId);

$residents = $data['residents'];
$units     = $data['units'];
$counts    = $data['counts'];

$pageTitle = xlt('AL Resident Board');
require __DIR__ . '/../../src/Core/Ui/partials/page_title.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .oei-board-header { background:#4a7c59; color:#fff; border-radius:.5rem; }
    .oei-unit-badge   { font-size:.75rem; }
    .al-card          { border-left:4px solid #4a7c59; transition:box-shadow .15s; }
    .al-card:hover    { box-shadow:0 2px 8px rgba(0,0,0,.15); }
    .adl-overdue      { background:#fff3cd; }
    .risk-HIGH  .fall-badge { background:#dc3545!important; }
    .risk-MODERATE .fall-badge { background:#fd7e14!important; }
    .risk-LOW  .fall-badge { background:#198754!important; }
  </style>
</head>
<body>
<div class="container-fluid p-3">

<?php require __DIR__ . '/../../src/Core/Ui/partials/flash.php'; ?>

<!-- Board header -->
<div class="oei-board-header p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <span class="fs-5 fw-bold">🏡 <?= xlt('Resident Census') ?></span>
    <span class="ms-2 text-white-50">
      <?= htmlspecialchars((string)($GLOBALS['facilityName'] ?? "Facility $facilityId")) ?>
    </span>
  </div>
  <div class="d-flex gap-3 flex-wrap">
    <span class="badge bg-light text-dark fs-6"><?= $counts['total'] ?> <?= xlt('Residents') ?></span>
    <span class="badge bg-danger"><?= $counts['high_risk'] ?> <?= xlt('High Fall Risk') ?></span>
    <span class="badge bg-warning text-dark"><?= $counts['high_care'] ?> <?= xlt('Tier 3 Care') ?></span>
    <a href="<?= $_SERVER['PHP_SELF'] ?>" class="btn btn-sm btn-outline-light"><?= xlt('Refresh') ?></a>
    <?php if ($manifest->featureEnabled('al_intake')): ?>
    <a href="intake.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-light text-dark">
      + <?= xlt('Admit Resident') ?>
    </a>
    <?php endif; ?>
  </div>
</div>

<!-- Unit summary bar -->
<?php if (!empty($units)): ?>
<div class="d-flex gap-2 flex-wrap mb-3">
  <?php foreach ($units as $u): ?>
  <span class="badge bg-secondary oei-unit-badge">
    <?= htmlspecialchars($u['unit'] ?: xlt('Unassigned')) ?>:
    <?= $u['total'] ?> <?= xlt('res') ?>
    <?php if ($u['high_risk'] > 0): ?>
      &nbsp;<span class="text-warning">⚠ <?= $u['high_risk'] ?></span>
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
  <?php foreach ($residents as $r): ?>
  <?php $adlDue = $r['adl_due'] ?? false; ?>
  <div class="col-12 col-md-6 col-xl-4">
    <div class="card al-card h-100 risk-<?= htmlspecialchars($r['fall_risk_level']) ?> <?= $adlDue ? 'adl-overdue' : '' ?>">
      <div class="card-body">
        <!-- Resident header row -->
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <strong><?= htmlspecialchars($r['display_name']) ?></strong>
            <span class="text-muted ms-1 small"><?= (int)$r['age'] ?>y
              <?= htmlspecialchars($r['gender']) ?></span>
          </div>
          <div class="text-end">
            <span class="badge bg-secondary small">
              <?= htmlspecialchars($r['unit'] ?: '—') ?> /
              <?= htmlspecialchars($r['room'] ?: '—') ?>
            </span>
          </div>
        </div>

        <!-- Care level + fall risk badges -->
        <div class="mb-2 d-flex flex-wrap gap-1">
          <span class="badge bg-<?= htmlspecialchars($r['care_level_badge']) ?>">
            <?= htmlspecialchars($r['care_level_label']) ?>
          </span>
          <span class="badge fall-badge text-white">
            <?= htmlspecialchars($r['fall_risk_label']) ?>
          </span>
          <?php if ($adlDue): ?>
          <span class="badge bg-warning text-dark">⏰ <?= xlt('ADL Due') ?></span>
          <?php endif; ?>
        </div>

        <!-- Staff line -->
        <div class="small text-muted mb-1">
          <?php if ($r['primary_provider']): ?>
            👨‍⚕️ <?= htmlspecialchars($r['primary_provider']) ?>
          <?php endif; ?>
          <?php if ($r['primary_nurse']): ?>
            &nbsp;·&nbsp; 👩‍⚕️ <?= htmlspecialchars($r['primary_nurse']) ?>
          <?php endif; ?>
        </div>

        <!-- Residency length -->
        <div class="small text-muted">
          <?= xlt('Day') ?> <?= (int)$r['days_resident'] ?>
          &nbsp;·&nbsp;
          <?= xlt('Admitted') ?> <?= htmlspecialchars(date('M j, Y', strtotime($r['start_datetime']))) ?>
        </div>
      </div>

      <!-- Card footer: quick links -->
      <div class="card-footer bg-transparent d-flex gap-2 flex-wrap">
        <?php if ($manifest->featureEnabled('al_care_plan')): ?>
        <a href="care_plan.php?episode_id=<?= (int)$r['episode_id'] ?>&pid=<?= (int)$r['pid'] ?>&facility_id=<?= $facilityId ?>"
           class="btn btn-sm btn-outline-success">📋 <?= xlt('Care Plan') ?></a>
        <?php endif; ?>
        <?php if ($manifest->featureEnabled('al_adl')): ?>
        <a href="adl.php?episode_id=<?= (int)$r['episode_id'] ?>&facility_id=<?= $facilityId ?>"
           class="btn btn-sm btn-outline-primary">📊 <?= xlt('ADL') ?></a>
        <?php endif; ?>
        <?php if ($manifest->featureEnabled('al_incident')): ?>
        <a href="incident.php?facility_id=<?= $facilityId ?>&episode_id=<?= (int)$r['episode_id'] ?>"
           class="btn btn-sm btn-outline-danger">🚨 <?= xlt('Incident') ?></a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
// Auto-refresh every 3 minutes
setTimeout(function(){ location.reload(); }, 180000);
</script>
</div>
</body>
</html>

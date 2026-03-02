<?php
/**
 * public/al/care_plan.php — AL Care Plan viewer / editor
 *
 * Reads from OpenEMR's form_care_plan (goals + activities) and
 * care_teams + care_team_member (USCDI v3 team composition).
 * All writes go back to form_care_plan so CCDA/FHIR export works.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Controller\CarePlanController;

if (!$manifest->featureEnabled('al_care_plan')) {
    oei_exit_with_alert(xlt('Care Plan is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

// If pid is missing but episode_id is known, resolve it from oei_episode.
if ($episodeId > 0 && $pid === 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery("SELECT pid FROM oei_episode WHERE id = ? LIMIT 1", [$episodeId]);
    $pid   = (int)($epRow['pid'] ?? 0);
}

if ($episodeId === 0 || $pid === 0) {
    header('Location: board.php?facility_id=' . $facilityId
         . '&notice=select_resident');
    exit;
}

$controller = new CarePlanController();
$data = $controller->handle($episodeId, $pid, $userId);

$pageTitle = xlt('Care Plan');

$activePage  = 'care_plan';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php';
?>
<?php if ($data['flash']): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($data['flash']) ?></div>
<?php endif; ?>

<div class="row g-3">

  <!-- Goals column -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <strong>🎯 <?= xlt('Goals') ?></strong>
        <span class="badge bg-light text-dark"><?= count($data['goals']) ?></span>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($data['goals'] as $g): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($g['description']) ?></span>
            <span class="badge bg-<?= $g['plan_status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
              <?= htmlspecialchars($g['plan_status']) ?>
            </span>
          </div>
          <?php if ($g['proposed_date']): ?>
          <small class="text-muted">Target: <?= htmlspecialchars($g['proposed_date']) ?></small>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if (empty($data['goals'])): ?>
        <li class="list-group-item text-muted small"><?= xlt('No goals recorded.') ?></li>
        <?php endif; ?>
      </ul>
      <!-- Add goal form -->
      <div class="card-footer">
        <form method="POST">
          <?= CsrfUtils::collectCsrfToken() ?>
          <input type="hidden" name="action" value="add_goal">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid" value="<?= $pid ?>">
          <div class="input-group input-group-sm">
            <input type="text" name="description" class="form-control"
                   placeholder="<?= xlt('New goal…') ?>" required>
            <input type="date" name="proposed_date" class="form-control" style="max-width:140px;">
            <button class="btn btn-success" type="submit">+ <?= xlt('Add') ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Interventions/Activities column -->
  <div class="col-md-6">
    <div class="card">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <strong>🔧 <?= xlt('Interventions') ?></strong>
        <span class="badge bg-light text-dark"><?= count($data['activities']) ?></span>
      </div>
      <ul class="list-group list-group-flush">
        <?php foreach ($data['activities'] as $a): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between">
            <span><?= htmlspecialchars($a['description']) ?></span>
            <span class="badge bg-<?= $a['plan_status'] === 'active' ? 'primary' : 'secondary' ?> ms-2">
              <?= htmlspecialchars($a['plan_status']) ?>
            </span>
          </div>
          <?php if ($a['proposed_date']): ?>
          <small class="text-muted">By: <?= htmlspecialchars($a['proposed_date']) ?></small>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if (empty($data['activities'])): ?>
        <li class="list-group-item text-muted small"><?= xlt('No interventions recorded.') ?></li>
        <?php endif; ?>
      </ul>
      <div class="card-footer">
        <form method="POST">
          <?= CsrfUtils::collectCsrfToken() ?>
          <input type="hidden" name="action" value="add_activity">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <input type="hidden" name="pid" value="<?= $pid ?>">
          <div class="input-group input-group-sm">
            <input type="text" name="description" class="form-control"
                   placeholder="<?= xlt('New intervention…') ?>" required>
            <input type="date" name="proposed_date" class="form-control" style="max-width:140px;">
            <button class="btn btn-primary" type="submit">+ <?= xlt('Add') ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Care Team (read from OpenEMR care_teams) -->
  <div class="col-12">
    <div class="card">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>👥 <?= xlt('Care Team') ?></strong>
        <?php if ($data['care_team']['team']): ?>
        <span class="small text-muted">
          <?= htmlspecialchars($data['care_team']['team']['team_name'] ?? '') ?>
          &nbsp;·&nbsp;
          <?= xlt('Updated') ?>
          <?= htmlspecialchars(date('M j', strtotime($data['care_team']['team']['date_updated']))) ?>
        </span>
        <?php endif; ?>
      </div>
      <?php if (empty($data['care_team']['members'])): ?>
        <div class="card-body text-muted small">
          <?= xlt('No care team on file. Assign via the patient chart Care Teams section.') ?>
        </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr>
            <th><?= xlt('Member') ?></th>
            <th><?= xlt('Role') ?></th>
            <th><?= xlt('Since') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($data['care_team']['members'] as $m): ?>
          <tr>
            <td><?= htmlspecialchars($m['member_name']) ?></td>
            <td><?= htmlspecialchars($m['role_label']) ?></td>
            <td class="text-muted small"><?= htmlspecialchars($m['provider_since'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /row -->

<div class="mt-3">
  <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back to Board') ?>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</div>
</body>
</html>

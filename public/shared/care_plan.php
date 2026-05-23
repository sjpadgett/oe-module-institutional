<?php

/**
 * public/shared/care_plan.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

/**
 * public/shared/care_plan.php — Cross-context Care Plan viewer / editor
 *
 * Works for AL, IP, ED, OBS, and BH episodes.
 *
 * Reads/writes form_care_plan via Shared CarePlanController.
 * Inline add-goal / add-activity forms write through the module's
 * addEntry() which also calls FormsRegistrar so OE forms stay in sync.
 *
 * Requires: ?episode_id=<n>&pid=<n>  (pid auto-resolved from episode if omitted)
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Controller\CarePlanController;

if (!$manifest->featureEnabled('care_plan')) {
    oei_exit_with_alert(xlt('Care Plan is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

// Auto-resolve pid + episode type from oei_episode
$episodeType = 'ED';
if ($episodeId > 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid, type FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    if ($epRow) {
        if ($pid === 0) {
            $pid = (int)$epRow['pid'];
        }
        $episodeType = strtoupper((string)($epRow['type'] ?? 'ED'));
    }
}

if ($episodeId === 0 || $pid === 0) {
    header('Location: ../ed_board.php?facility_id=' . $facilityId . '&notice=select_patient');
    exit;
}

// ── IP context: absolute base URLs + nav variables ─────────────────────────
$_oei_ip_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

$controller = new CarePlanController();
$data       = $controller->handle($episodeId, $episodeType, $pid, $userId);

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Care Plan');
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$backUrl    = match ($episodeType) {
    'IP'  => $_oei_ip_base  . 'profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'AL'  => $_oei_pub_base . 'al/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'HBC' => $_oei_pub_base . 'hbc/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    default => $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId,
};
$activePage     = 'care_plan';
$launchEnabled  = $manifest->featureEnabled('care_plan_launch') && $data['has_encounter'];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .status-badge-active    { background:#198754; }
    .status-badge-completed { background:#6c757d; }
    .status-badge-draft     { background:#ffc107; color:#000; }
    .no-encounter-banner    { border-left:4px solid #ffc107; background:#fff8e1; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php if ($episodeType === 'IP'): ?>
    <?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>
<?php endif; ?>

<?php if ($data['flash']): ?>
<div class="alert alert-success alert-dismissible py-2" role="alert">
    <?= htmlspecialchars($data['flash']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Page header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">📋 <?= xlt('Care Plan') ?>
    <span class="badge bg-secondary ms-2 fs-6">
      <?= xlt('Episode') ?> #<?= $episodeId ?> &bull; <?= htmlspecialchars($episodeType) ?>
    </span>
  </h5>
  <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back') ?>
  </a>
</div>

<?php if (!$data['has_encounter']): ?>
<!-- No encounter linked warning -->
<div class="p-3 mb-3 rounded no-encounter-banner">
  <strong>⚠ <?= xlt('No encounter linked to this episode.') ?></strong>
  <div class="small mt-1 text-muted">
    <?= xlt('A care plan requires an active OpenEMR encounter number. Create an encounter in the patient chart first, then return here to add goals and activities.') ?>
  </div>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Goals -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <strong>🎯 <?= xlt('Goals') ?></strong>
        <span class="badge bg-light text-dark"><?= count($data['goals']) ?></span>
      </div>

      <ul class="list-group list-group-flush">
        <?php foreach ($data['goals'] as $g): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <span class="me-2"><?= htmlspecialchars($g['description']) ?></span>
            <span class="badge <?= $g['plan_status'] === 'completed' ? 'status-badge-completed' : 'status-badge-active' ?> text-white flex-shrink-0">
              <?= htmlspecialchars($g['plan_status']) ?>
            </span>
          </div>
            <?php if ($g['proposed_date']): ?>
          <small class="text-muted">
                <?= xlt('Target') ?>: <?= htmlspecialchars(substr($g['proposed_date'], 0, 10)) ?>
          </small>
          <?php endif; ?>
            <?php if ($g['reason_description']): ?>
          <div class="small text-muted mt-1">
            <em><?= htmlspecialchars($g['reason_description']) ?></em>
          </div>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if (empty($data['goals'])): ?>
        <li class="list-group-item text-muted small"><?= xlt('No goals recorded.') ?></li>
        <?php endif; ?>
      </ul>

      <?php if ($manifest->featureEnabled('care_plan_launch')): ?>
      <div class="card-footer">
            <?php if ($data['has_encounter']): ?>
        <!-- Inline quick-add form (writes via Shared CarePlanRepository + FormsRegistrar) -->
        <form method="POST">
                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
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
        <?php else: ?>
        <span class="text-muted small"><?= xlt('Link an encounter to add goals.') ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Interventions / Activities -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <strong>🔧 <?= xlt('Interventions') ?></strong>
        <span class="badge bg-light text-dark"><?= count($data['activities']) ?></span>
      </div>

      <ul class="list-group list-group-flush">
        <?php foreach ($data['activities'] as $a): ?>
        <li class="list-group-item">
          <div class="d-flex justify-content-between align-items-start">
            <span class="me-2"><?= htmlspecialchars($a['description']) ?></span>
            <span class="badge <?= $a['plan_status'] === 'completed' ? 'status-badge-completed' : 'bg-primary' ?> text-white flex-shrink-0">
              <?= htmlspecialchars($a['plan_status']) ?>
            </span>
          </div>
            <?php if ($a['proposed_date']): ?>
          <small class="text-muted">
                <?= xlt('By') ?>: <?= htmlspecialchars(substr($a['proposed_date'], 0, 10)) ?>
          </small>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if (empty($data['activities'])): ?>
        <li class="list-group-item text-muted small"><?= xlt('No interventions recorded.') ?></li>
        <?php endif; ?>
      </ul>

      <?php if ($manifest->featureEnabled('care_plan_launch')): ?>
      <div class="card-footer">
            <?php if ($data['has_encounter']): ?>
        <form method="POST">
                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
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
        <?php else: ?>
        <span class="text-muted small"><?= xlt('Link an encounter to add interventions.') ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Care Team -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>👥 <?= xlt('Care Team') ?></strong>
        <?php if ($data['care_team']['team']): ?>
        <span class="small text-muted">
            <?= htmlspecialchars($data['care_team']['team']['team_name'] ?? '') ?>
          &nbsp;·&nbsp;
            <?= xlt('Updated') ?>
            <?= htmlspecialchars(date('M j', strtotime((string)$data['care_team']['team']['date_updated']))) ?>
        </span>
        <a href="../shared/care_team.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>"
           class="btn btn-xs btn-outline-secondary btn-sm ms-2">
            <?= xlt('Manage') ?>
        </a>
        <?php endif; ?>
      </div>
      <?php if (empty($data['care_team']['members'])): ?>
      <div class="card-body text-muted small">
            <?= xlt('No care team on file.') ?>
            <?php if ($manifest->featureEnabled('care_team_launch')): ?>
        <a href="../shared/care_team.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>">
                <?= xlt('Set up care team') ?>
        </a>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light"><tr>
            <th><?= xlt('Member') ?></th><th><?= xlt('Role') ?></th><th><?= xlt('Since') ?></th>
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
  <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back') ?>
  </a>
</div>

<?= institutional_bootstrap5_js_tag() ?>
</div>
</body>
</html>


















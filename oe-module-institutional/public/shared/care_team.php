<?php

/**
 * public/shared/care_team.php
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
 * public/shared/care_team.php — Care Team management panel
 *
 * Works for all episode types.
 * Care teams are patient-anchored (pid), not encounter-anchored.
 *
 * Unlike care plans / clinical notes there is no OpenEMR native
 * form for care_team_member, so this page provides the only management UI.
 * Writes go directly to care_teams + care_team_member via CareTeamController.
 *
 * Requires: ?episode_id=<n>  (pid auto-resolved)
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Controller\CareTeamController;

if (!$manifest->featureEnabled('care_team')) {
    oei_exit_with_alert(xlt('Care Team is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

if ($episodeId > 0 && $pid === 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    if ($epRow) { $pid = (int)$epRow['pid']; }
}

if ($pid === 0) {
    header('Location: ../ed_board.php?facility_id=' . $facilityId . '&notice=select_patient');
    exit;
}

// Resolve episode type for context-aware back navigation
$episodeType = '';
if ($episodeId > 0 && function_exists('sqlQuery')) {
    $epTypeRow = sqlQuery('SELECT type FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    $episodeType = strtoupper((string)($epTypeRow['type'] ?? ''));
}

$controller = new CareTeamController();
$data       = $controller->handle($pid, $episodeId, $userId);

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle    = xlt('Care Team');
$__bgClass    = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$_oei_ip_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';
$backUrl = match ($episodeType) {
    'IP'  => $_oei_ip_base  . 'profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'AL'  => $_oei_pub_base . 'al/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'HBC' => $_oei_pub_base . 'hbc/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    default => $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId,
};
$canLaunch    = $manifest->featureEnabled('care_team_launch');

// Role badge colors for visual differentiation
$roleBadge = [
    'physician'           => 'bg-primary',
    'nurse'               => 'bg-success',
    'nurse_practitioner'  => 'bg-success',
    'therapist'           => 'bg-warning text-dark',
    'social_worker'       => 'bg-info text-dark',
    'case_manager'        => 'bg-info text-dark',
    'specialist'          => 'bg-primary',
    'pharmacist'          => 'bg-secondary',
    'dietitian'           => 'bg-success',
    'mental_health'       => 'bg-info text-dark',
    'caregiver'           => 'bg-warning text-dark',
    'primary_care_provider'=> 'bg-primary',
    'physician_assistant' => 'bg-primary',
    'family_medicine_specialist' => 'bg-primary',
];
$rb = static fn(string $r): string => $roleBadge[$r] ?? 'bg-secondary';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .member-card { border-left:3px solid #0d6efd; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php if ($data['flash']): ?>
<div class="alert alert-<?= htmlspecialchars($data['flash_type']) ?> alert-dismissible py-2" role="alert">
    <?= htmlspecialchars($data['flash']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">👥 <?= xlt('Care Team') ?>
    <?php if ($data['team']): ?>
    <span class="ms-2 text-muted fs-6 fw-normal">
        <?= htmlspecialchars($data['team']['team_name'] ?? '') ?>
    </span>
    <?php endif; ?>
  </h5>
  <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back') ?>
  </a>
</div>

<!-- Team members -->
<?php if (empty($data['members'])): ?>
<div class="card mb-3">
  <div class="card-body text-muted text-center py-4">
    <?= xlt('No team members recorded.') ?>
    <?php if ($canLaunch): ?>
    <div class="mt-2 small"><?= xlt('Use the form below to add the first member.') ?></div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="row g-2 mb-3">
    <?php foreach ($data['members'] as $m): ?>
  <div class="col-md-6 col-lg-4">
    <div class="card member-card h-100">
      <div class="card-body py-2 px-3">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($m['member_name'] ?: '—') ?></div>
            <span class="badge <?= $rb($m['role']) ?> mt-1" style="font-size:.72rem;">
              <?= htmlspecialchars($m['role_label']) ?>
            </span>
            <?php if ($m['provider_since']): ?>
            <div class="text-muted small mt-1">
                <?= xlt('Since') ?> <?= htmlspecialchars(substr((string)$m['provider_since'], 0, 10)) ?>
            </div>
            <?php endif; ?>
            <?php if ($m['note']): ?>
            <div class="text-muted small mt-1 fst-italic">
                <?= htmlspecialchars($m['note']) ?>
            </div>
            <?php endif; ?>
          </div>
          <?php if ($canLaunch): ?>
          <form method="POST" class="ms-2 flex-shrink-0"
                onsubmit="return confirm('<?= xlt('Remove this team member?') ?>')">
                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
            <input type="hidden" name="action"     value="remove_member">
            <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
            <input type="hidden" name="pid"        value="<?= $pid ?>">
            <input type="hidden" name="member_id"  value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn btn-xs btn-outline-danger"
                    style="font-size:.72rem;padding:.1rem .35rem;">
              ✕
            </button>
          </form>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Add member form -->
<?php if ($canLaunch && !empty($data['roles'])): ?>
<div class="card">
  <div class="card-header">
    <strong>➕ <?= xlt('Add Team Member') ?></strong>
  </div>
  <div class="card-body">
    <form method="POST" class="row g-2 align-items-end">
      <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
      <input type="hidden" name="action"     value="add_member">
      <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
      <input type="hidden" name="pid"        value="<?= $pid ?>">

      <div class="col-md-4">
        <label class="form-label small"><?= xlt('Staff Member') ?></label>
        <select name="member_user_id" class="form-select form-select-sm">
          <option value=""><?= xlt('— External / TBD —') ?></option>
          <?php foreach ($data['staff'] as $s): ?>
          <option value="<?= (int)$s['id'] ?>">
                <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['username']) ?>)
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small"><?= xlt('Role') ?> <span class="text-danger">*</span></label>
        <select name="role" class="form-select form-select-sm" required>
          <option value=""><?= xlt('— Select role —') ?></option>
          <?php foreach ($data['roles'] as $r): ?>
          <option value="<?= htmlspecialchars($r['option_id']) ?>">
                <?= htmlspecialchars($r['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label small"><?= xlt('Member Since') ?></label>
        <input type="date" name="provider_since" class="form-control form-control-sm"
               value="<?= date('Y-m-d') ?>">
      </div>

      <div class="col-md-2">
        <label class="form-label small"><?= xlt('Note') ?></label>
        <input type="text" name="note" class="form-control form-control-sm"
               placeholder="<?= xlt('Optional…') ?>" maxlength="255">
      </div>

      <div class="col-md-1">
        <button type="submit" class="btn btn-primary btn-sm w-100">
          <?= xlt('Add') ?>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="mt-3">
  <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back') ?>
  </a>
</div>

<?= institutional_bootstrap5_js_tag() ?>
</div>
</body>
</html>
















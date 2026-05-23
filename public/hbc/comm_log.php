<?php

/**
 * public/hbc/comm_log.php
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
 * public/hbc/comm_log.php — HBC Communication Log
 *
 * Log and review calls, faxes, and messages to/from PCP, pharmacy,
 * family, DME, payer, and other external contacts for an HBC episode.
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcCommLog\Controller\HbcCommLogController;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcCommLog\Repository\HbcCommLogRepository;

if (!$manifest->featureEnabled('hbc_comm_log')) {
    oei_exit_with_alert(xlt('Communication Log is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

// episode_id=0 = facility-wide mode (from top menu)

$controller = new HbcCommLogController();
$data       = $controller->handle($episodeId, $pid, $facilityId, $userId);

$_oei_csrf  = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Communication Log');
$activePage = 'comm_log';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$q = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;

$commTypeIcons = [
    'PHONE_OUT'  => '📞',
    'PHONE_IN'   => '📲',
    'FAX'        => '📠',
    'SECURE_MSG' => '💬',
    'IN_PERSON'  => '🤝',
    'OTHER'      => '📋',
];

$roleColors = [
    'PCP'        => 'bg-primary',
    'SPECIALIST' => 'bg-info text-dark',
    'PHARMACY'   => 'bg-success',
    'FAMILY'     => 'bg-warning text-dark',
    'CAREGIVER'  => 'bg-warning text-dark',
    'PAYER'      => 'bg-secondary',
    'DME_SUPPLIER'       => 'bg-secondary',
    'HOME_HEALTH_AGENCY' => 'bg-info text-dark',
    'HOSPICE'            => 'bg-dark',
    'SOCIAL_SERVICES'    => 'bg-info text-dark',
    'OTHER'              => 'bg-secondary',
];
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
    .comm-card { border-left:3px solid #4a7c59; transition:box-shadow .15s; }
    .comm-card:hover { box-shadow:0 2px 6px rgba(0,0,0,.1); }
    .comm-card.followup { border-left-color:#ffc107; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">

<?php if ($episodeId > 0): ?>
<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>
<?php else: ?>
<div class="mb-3">
  <a href="<?= htmlspecialchars($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>"
     class="btn btn-sm btn-outline-secondary">← <?= xlt('Visit Board') ?></a>
</div>
<?php endif; ?>

<?php if ($data['flash']): ?>
<div class="alert alert-success alert-dismissible py-2 mt-2">
  ✔ <?= htmlspecialchars($data['flash']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($data['error']): ?>
<div class="alert alert-danger py-2 mt-2"><?= htmlspecialchars($data['error']) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3 mt-2 flex-wrap gap-2">
  <div>
    <h5 class="mb-0">📞 <?= xlt('Communication Log') ?>
      <?php if ($episodeId === 0): ?>
        <span class="badge bg-secondary fs-6 ms-2"><?= xlt('All Patients') ?></span>
      <?php endif; ?>
    </h5>
    <div class="text-muted small">
      <?= count($data['entries']) ?> <?= xlt('entries') ?>
      <?php if ($data['pending_followups'] > 0): ?>
        · <span class="text-warning fw-semibold"><?= $data['pending_followups'] ?> <?= xlt('follow-up needed') ?></span>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($episodeId > 0): ?>
  <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#newCommModal">
    + <?= xlt('Log Communication') ?>
  </button>
  <?php endif; ?>
</div>

<?php if (empty($data['entries'])): ?>
<div class="alert alert-info"><?= xlt('No communications logged for this episode.') ?></div>
<?php else: ?>

<!-- Filter -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <select id="filterRole" class="form-select form-select-sm" style="max-width:180px;">
    <option value=""><?= xlt('All contacts') ?></option>
    <?php foreach ($data['contact_roles'] as $key => $label): ?>
    <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars(xlt($label)) ?></option>
    <?php endforeach; ?>
  </select>
  <label class="form-check form-check-inline align-self-center small">
    <input type="checkbox" class="form-check-input" id="filterFollowup">
    <?= xlt('Follow-up only') ?>
  </label>
</div>

<div id="commList">
<?php foreach ($data['entries'] as $e):
    $icon = $commTypeIcons[$e['comm_type']] ?? '📋';
    $roleBadge = $roleColors[$e['contact_role']] ?? 'bg-secondary';
    $roleLabel = $data['contact_roles'][$e['contact_role']] ?? $e['contact_role'];
    $typeLabel = $data['comm_types'][$e['comm_type']] ?? $e['comm_type'];
?>
<div class="card comm-card mb-2 <?= $e['followup_needed'] ? 'followup' : '' ?>"
     data-role="<?= htmlspecialchars($e['contact_role']) ?>"
     data-followup="<?= $e['followup_needed'] ? '1' : '0' ?>">
  <div class="card-body py-2 px-3">
    <div class="d-flex justify-content-between align-items-start">
      <div>
        <div class="fw-semibold">
          <?= $icon ?>
          <?php if ($episodeId === 0 && !empty($e['patient_name'])): ?>
          <a href="<?= htmlspecialchars($_hbcBase . 'profile.php?episode_id=' . $e['episode_id'] . '&pid=' . $e['pid'] . '&facility_id=' . $facilityId) ?>"
             class="fw-bold text-decoration-none me-1"><?= htmlspecialchars($e['patient_name']) ?></a>
          <?php endif; ?>
          <?= htmlspecialchars(xlt($typeLabel)) ?>
          <span class="badge <?= $roleBadge ?> ms-1" style="font-size:.7rem;">
            <?= htmlspecialchars(xlt($roleLabel)) ?>
          </span>
          <?php if ($e['followup_needed']): ?>
          <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">⚡ <?= xlt('Follow-up') ?></span>
          <?php endif; ?>
        </div>
        <?php if ($e['contact_name']): ?>
        <div class="small text-muted">
          <?= htmlspecialchars($e['contact_name']) ?>
          <?php if ($e['contact_phone']): ?>
            · <?= htmlspecialchars($e['contact_phone']) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($e['subject']): ?>
        <div class="small fw-semibold mt-1"><?= htmlspecialchars($e['subject']) ?></div>
        <?php endif; ?>
        <?php if ($e['summary']): ?>
        <div class="small mt-1"><?= nl2br(htmlspecialchars(mb_strimwidth($e['summary'], 0, 300, '…'))) ?></div>
        <?php endif; ?>
        <?php if ($e['outcome']): ?>
        <div class="small mt-1 text-muted fst-italic">
          <?= xlt('Outcome') ?>: <?= htmlspecialchars($e['outcome']) ?>
        </div>
        <?php endif; ?>
        <?php if ($e['followup_note']): ?>
        <div class="small mt-1 text-warning">
          ⚡ <?= htmlspecialchars($e['followup_note']) ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="text-end text-muted small text-nowrap" style="min-width:90px;">
        <div><?= htmlspecialchars((new DateTime($e['comm_datetime']))->format('M j H:i')) ?></div>
        <?php if ($e['logged_by_name']): ?>
        <div style="font-size:.7rem;"><?= htmlspecialchars($e['logged_by_name']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- New Communication Modal -->
<div class="modal fade" id="newCommModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#2c5f4a,#4a7c59);color:#fff;">
        <h5 class="modal-title">📞 <?= xlt('Log Communication') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST"
            action="<?= htmlspecialchars($_hbcBase . 'comm_log.php' . $q) ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
        <input type="hidden" name="pid" value="<?= $pid ?>">
        <div class="modal-body row g-3">
          <div class="col-md-4">
            <label class="form-label fw-semibold small"><?= xlt('Type') ?></label>
            <select name="comm_type" class="form-select form-select-sm" required>
              <?php foreach ($data['comm_types'] as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars(xlt($label)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small"><?= xlt('Contact Role') ?></label>
            <select name="contact_role" class="form-select form-select-sm" required>
              <?php foreach ($data['contact_roles'] as $key => $label): ?>
              <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars(xlt($label)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold small"><?= xlt('Date / Time') ?></label>
            <input type="datetime-local" name="comm_datetime" class="form-control form-control-sm"
                   value="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small"><?= xlt('Contact Name') ?></label>
            <input type="text" name="contact_name" class="form-control form-control-sm"
                   placeholder="<?= xla('Dr. Smith, Main St Pharmacy, etc.') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold small"><?= xlt('Contact Phone') ?></label>
            <input type="tel" name="contact_phone" class="form-control form-control-sm"
                   placeholder="<?= xla('Optional') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small"><?= xlt('Subject') ?></label>
            <input type="text" name="subject" class="form-control form-control-sm"
                   placeholder="<?= xla('Med change, order update, care coordination, etc.') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small"><?= xlt('Summary') ?></label>
            <textarea name="summary" class="form-control form-control-sm" rows="3"
                      placeholder="<?= xla('What was discussed, agreed, communicated…') ?>"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold small"><?= xlt('Outcome / Result') ?></label>
            <input type="text" name="outcome" class="form-control form-control-sm"
                   placeholder="<?= xla('Left voicemail, order confirmed, family agrees, etc.') ?>">
          </div>
          <div class="col-12">
            <div class="form-check mb-2">
              <input type="checkbox" class="form-check-input" name="followup_needed" id="commFollowup" value="1">
              <label class="form-check-label fw-semibold small" for="commFollowup"><?= xlt('Follow-up needed') ?></label>
            </div>
            <input type="text" name="followup_note" class="form-control form-control-sm"
                   placeholder="<?= xla('What needs to happen next…') ?>">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success"><?= xlt('Save') ?></button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?= institutional_bootstrap5_js_tag() ?>
<script>
(function() {
  const cards = Array.from(document.querySelectorAll('.comm-card'));
  const roleFilter = document.getElementById('filterRole');
  const fuFilter   = document.getElementById('filterFollowup');
  function applyFilters() {
    const role = (roleFilter?.value || '').trim();
    const fu   = fuFilter?.checked || false;
    cards.forEach(c => {
      const matchRole = !role || c.getAttribute('data-role') === role;
      const matchFu   = !fu || c.getAttribute('data-followup') === '1';
      c.style.display = (matchRole && matchFu) ? '' : 'none';
    });
  }
  roleFilter?.addEventListener('change', applyFilters);
  fuFilter?.addEventListener('change', applyFilters);
})();
</script>
</div>
</body>
</html>










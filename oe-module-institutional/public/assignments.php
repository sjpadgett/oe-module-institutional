<?php

require_once __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Modules\Institutional\Core\Service\AuditService;
use OpenEMR\Modules\Institutional\Submodule\Assignment\Controller\AssignmentController;
use OpenEMR\Modules\Institutional\Submodule\Assignment\Repository\AssignmentRepository;

if (!$manifest->featureEnabled('assignment')) {
    die(xlt("Staff Assignment is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

// JSON get endpoint
if (isset($_GET['json']) && $_GET['json'] === '1' && isset($_GET['episode_id'])) {
    $ctrl = new AssignmentController(new AssignmentRepository());
    $ctrl->handleGet((int)$_GET['episode_id']);
    // exits
}

$audit = new AuditService();
$controller = new AssignmentController(new AssignmentRepository(), $audit);
$data = $controller->handle($facilityId, $userId);

$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Staff Assignments') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    .assign-select { min-width: 140px; }
    .unassigned { color: #adb5bd; font-style: italic; }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="bi bi-people me-2"></i><?= xlt('Staff Assignments') ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
      <i class="bi bi-grid me-1"></i><?= xlt('ED Board') ?>
    </a>
  </div>

  <?php if ($data['saved']): ?>
    <div class="alert alert-success alert-dismissible fade show py-2" role="alert">
      <i class="bi bi-check-circle me-1"></i><?= xlt('Assignments saved.') ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (empty($data['rows'])): ?>
    <div class="alert alert-info"><?= xlt('No active episodes.') ?></div>
  <?php else: ?>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Active Episodes') ?></span>
      <span class="badge text-bg-secondary"><?= count($data['rows']) ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th><?= xlt('Episode') ?></th>
            <th><?= xlt('PID') ?></th>
            <th><?= xlt('Location') ?></th>
            <th><?= xlt('ESI') ?></th>
            <th><?= xlt('Status') ?></th>
            <th><?= xlt('Chief Complaint') ?></th>
            <th><?= xlt('Assigned Nurse') ?></th>
            <th><?= xlt('Assigned Provider') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
        <tr>
          <td><strong>#<?= htmlspecialchars((string)$r['id']) ?></strong></td>
          <td><?= htmlspecialchars((string)$r['pid']) ?></td>
          <td><?= htmlspecialchars((string)($r['location_name'] ?? '—')) ?></td>
          <td>
            <?php if (!empty($r['acuity_esi'])): ?>
              <span class="badge text-bg-info">ESI <?= htmlspecialchars((string)$r['acuity_esi']) ?></span>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><span class="badge text-bg-light border"><?= htmlspecialchars((string)($r['workflow_status'] ?? '')) ?></span></td>
          <td class="text-truncate" style="max-width:160px;"><?= htmlspecialchars((string)($r['chief_complaint'] ?? '—')) ?></td>

          <!-- Nurse assignment -->
          <td>
            <?php if (!empty($r['nurse_id'])): ?>
              <span class="badge text-bg-primary">
                <i class="bi bi-person-fill me-1"></i><?= htmlspecialchars(trim((string)$r['nurse_name'])) ?>
              </span>
            <?php else: ?>
              <span class="unassigned"><?= xlt('Unassigned') ?></span>
            <?php endif; ?>
          </td>

          <!-- Provider assignment -->
          <td>
            <?php if (!empty($r['provider_id'])): ?>
              <span class="badge text-bg-success">
                <i class="bi bi-person-badge-fill me-1"></i><?= htmlspecialchars(trim((string)$r['provider_name'])) ?>
              </span>
            <?php else: ?>
              <span class="unassigned"><?= xlt('Unassigned') ?></span>
            <?php endif; ?>
          </td>

          <!-- Inline edit -->
          <td>
            <button class="btn btn-sm btn-outline-secondary"
                    data-bs-toggle="modal"
                    data-bs-target="#assignModal"
                    data-episode-id="<?= htmlspecialchars((string)$r['id']) ?>"
                    data-pid="<?= htmlspecialchars((string)$r['pid']) ?>"
                    data-nurse-id="<?= htmlspecialchars((string)($r['nurse_id'] ?? '')) ?>"
                    data-provider-id="<?= htmlspecialchars((string)($r['provider_id'] ?? '')) ?>"
                    data-location="<?= htmlspecialchars((string)($r['location_name'] ?? '')) ?>"
                    data-cc="<?= htmlspecialchars((string)($r['chief_complaint'] ?? '')) ?>">
              <i class="bi bi-pencil"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Assignment modal -->
  <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="post">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
          <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
          <input type="hidden" name="episode_id" id="modal-episode-id" value="">
          <input type="hidden" name="pid"         id="modal-pid"        value="">

          <div class="modal-header">
            <h5 class="modal-title" id="assignModalLabel"><?= xlt('Assign Staff') ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <p class="text-muted small mb-3" id="modal-context"></p>

            <div class="mb-3">
              <label class="form-label fw-semibold" for="nurse_user_id">
                <i class="bi bi-person-fill me-1 text-primary"></i><?= xlt('Assigned Nurse') ?>
              </label>
              <select name="nurse_user_id" id="nurse_user_id" class="form-select assign-select">
                <option value=""><?= xlt('— Unassigned —') ?></option>
                <?php foreach ($data['nurses'] as $u): ?>
                  <option value="<?= htmlspecialchars((string)$u['id']) ?>">
                    <?= htmlspecialchars((string)$u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-2">
              <label class="form-label fw-semibold" for="provider_user_id">
                <i class="bi bi-person-badge-fill me-1 text-success"></i><?= xlt('Assigned Provider') ?>
              </label>
              <select name="provider_user_id" id="provider_user_id" class="form-select assign-select">
                <option value=""><?= xlt('— Unassigned —') ?></option>
                <?php foreach ($data['providers'] as $u): ?>
                  <option value="<?= htmlspecialchars((string)$u['id']) ?>">
                    <?= htmlspecialchars((string)$u['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
            <button type="submit" class="btn btn-primary"><?= xlt('Save Assignment') ?></button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
(function () {
  const modal = document.getElementById('assignModal');
  if (!modal) return;
  modal.addEventListener('show.bs.modal', function (ev) {
    const btn = ev.relatedTarget;
    if (!btn) return;
    document.getElementById('modal-episode-id').value = btn.dataset.episodeId || '';
    document.getElementById('modal-pid').value        = btn.dataset.pid        || '';

    const loc = btn.dataset.location || '';
    const cc  = btn.dataset.cc       || '';
    document.getElementById('modal-context').textContent =
      'Episode #' + (btn.dataset.episodeId || '') +
      (loc ? '  ·  ' + loc : '') +
      (cc  ? '  —  '  + cc  : '');

    // Pre-select current assignments
    const nurseId    = btn.dataset.nurseId    || '';
    const providerId = btn.dataset.providerId || '';
    const nurseEl    = document.getElementById('nurse_user_id');
    const provEl     = document.getElementById('provider_user_id');
    if (nurseEl) nurseEl.value    = nurseId;
    if (provEl)  provEl.value     = providerId;
  });
})();
</script>
</body>
</html>

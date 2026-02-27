<?php

require_once __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Modules\Institutional\Submodule\Diversion\Controller\DiversionController;
use OpenEMR\Modules\Institutional\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\Submodule\Diversion\Service\DiversionService;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Repository\Hl7OutboundLogRepository;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service\AdtNotificationService;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('diversion')) {
    die(xlt('Diversion Status is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$divRepo = new DiversionRepository();
$adt     = new AdtNotificationService(
    new SettingsRepository(),
    new Hl7OutboundLogRepository()
);
$controller = new DiversionController(
    new DiversionService($divRepo, $adt),
    $divRepo
);

$data = $controller->handle($facilityId, $userId);

if (!empty($data['error'])) {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError($data['error']);
}
if (!empty($data['message'])) {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addSuccess($data['message']);
}

$href       = institutional_bootstrap5_href($manifest);
$statusMap  = $data['status_map']  ?? [];
$worst      = $data['worst']       ?? 'OPEN';
$history    = $data['history']     ?? [];
$csrf       = $data['csrf']        ?? '';

// Service lines managed
$serviceLines = ['ED', 'ICU', 'OBS', 'PSYCH', 'TRAUMA', 'PEDS', 'BURN'];

// Badge config
$statusBadge = [
    'OPEN'      => ['class' => 'success',  'label' => 'Open',      'icon' => 'bi-check-circle-fill'],
    'DIVERSION' => ['class' => 'danger',   'label' => 'Diversion', 'icon' => 'bi-slash-circle-fill'],
    'LIMITED'   => ['class' => 'warning',  'label' => 'Limited',   'icon' => 'bi-exclamation-circle-fill'],
    'BYPASS'    => ['class' => 'secondary','label' => 'Bypass',    'icon' => 'bi-arrow-left-right'],
];

$worstBadge = $statusBadge[$worst] ?? $statusBadge['OPEN'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Diversion Status') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link rel="stylesheet" href="<?= $href ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-4 px-4">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
      <h1 class="h4 mb-0"><i class="bi bi-signpost-2 me-2"></i><?= xlt('Diversion Status') ?></h1>
      <span class="badge text-bg-<?= htmlspecialchars($worstBadge['class']) ?> fs-6 px-3 py-2">
        <i class="bi <?= htmlspecialchars($worstBadge['icon']) ?> me-1"></i>
        <?= xlt($worstBadge['label']) ?>
      </span>
    </div>
    <a href="facility_directory.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i><?= xlt('Facility Directory') ?>
    </a>
  </div>

  <?php require __DIR__ . '/../src/Core/Ui/partials/flash.php'; ?>

  <!-- Service Line Cards -->
  <div class="row g-3 mb-4">
    <?php foreach ($serviceLines as $line):
        $row    = $statusMap[$line] ?? null;
        $status = $row ? strtoupper((string)($row['status'] ?? 'OPEN')) : 'OPEN';
        $reason = $row ? (string)($row['reason'] ?? '') : '';
        $badge  = $statusBadge[$status] ?? $statusBadge['OPEN'];
        $since  = $row ? (string)($row['updated_datetime'] ?? '') : '';
        $isDiverted = ($status !== 'OPEN');
        ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
      <div class="card shadow-sm h-100 <?= $isDiverted ? 'border-' . $badge['class'] . ' border-2' : '' ?>">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start mb-2">
            <h5 class="card-title mb-0 fw-bold"><?= htmlspecialchars($line) ?></h5>
            <span class="badge text-bg-<?= htmlspecialchars($badge['class']) ?>">
              <i class="bi <?= htmlspecialchars($badge['icon']) ?> me-1"></i>
              <?= xlt($badge['label']) ?>
            </span>
          </div>
          <?php if ($reason): ?>
            <p class="card-text text-muted small mb-2"><?= htmlspecialchars($reason) ?></p>
          <?php endif; ?>
          <?php if ($since): ?>
            <p class="card-text text-muted" style="font-size:11px">
                <?= xlt('Updated') ?>: <?= htmlspecialchars($since) ?>
            </p>
          <?php endif; ?>
          <!-- Set Status Form -->
          <form method="post" action="diversion.php?facility_id=<?= $facilityId ?>">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="set">
            <input type="hidden" name="service_line" value="<?= htmlspecialchars($line) ?>">
            <div class="mb-2">
              <select name="status" class="form-select form-select-sm">
                <?php foreach ($statusBadge as $sv => $sb): ?>
                  <option value="<?= $sv ?>" <?= ($status === $sv) ? 'selected' : '' ?>>
                    <?= xlt($sb['label']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-2">
              <input type="text" name="reason" class="form-control form-control-sm"
                     placeholder="<?= xla('Reason (optional)') ?>"
                     value="<?= htmlspecialchars($reason) ?>">
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-sm btn-primary flex-fill">
                <?= xlt('Update') ?>
              </button>
              <?php if ($isDiverted): ?>
              <button type="submit"
                      onclick="document.querySelector('input[name=action][form]').value='lift'"
                      formaction="diversion.php?facility_id=<?= $facilityId ?>"
                      class="btn btn-sm btn-outline-success">
                    <?= xlt('Lift') ?>
              </button>
              <?php endif; ?>
            </div>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- History Table -->
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong><?= xlt('Change History') ?></strong>
      <span class="text-muted small"><?= xlt('Last 60 events') ?></span>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th><?= xlt('When') ?></th>
              <th><?= xlt('Line') ?></th>
              <th><?= xlt('From') ?></th>
              <th><?= xlt('To') ?></th>
              <th><?= xlt('Reason') ?></th>
              <th><?= xlt('By') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($history)): ?>
            <tr><td colspan="6" class="text-center text-muted py-3"><?= xlt('No history yet') ?></td></tr>
            <?php else: ?>
                <?php foreach ($history as $h):
                    $hBadge = $statusBadge[strtoupper((string)($h['new_status'] ?? 'OPEN'))] ?? $statusBadge['OPEN'];
                    ?>
            <tr>
              <td class="text-nowrap small"><?= htmlspecialchars((string)($h['changed_datetime'] ?? '')) ?></td>
              <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)($h['service_line'] ?? '')) ?></span></td>
              <td><span class="badge text-bg-light text-dark border"><?= htmlspecialchars((string)($h['previous_status'] ?? '—')) ?></span></td>
              <td><span class="badge text-bg-<?= htmlspecialchars($hBadge['class']) ?>"><?= htmlspecialchars((string)($h['new_status'] ?? '')) ?></span></td>
              <td class="small text-muted"><?= htmlspecialchars((string)($h['reason'] ?? '')) ?></td>
              <td class="small"><?= htmlspecialchars(trim((string)($h['fname'] ?? '') . ' ' . (string)($h['lname'] ?? ''))) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>
<?php if ($href): ?><script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script><?php endif; ?>
</body>
</html>

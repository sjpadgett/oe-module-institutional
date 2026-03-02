<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
$pageTitle = xlt('Intake');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Intake\Repository\PatientRepository;
use OpenEMR\Modules\Institutional\Submodule\Intake\Repository\EpisodeIntakeRepository;
use OpenEMR\Modules\Institutional\Submodule\Intake\Service\IntakeService;
use OpenEMR\Modules\Institutional\Submodule\Intake\Controller\IntakeController;

if (!$manifest->featureEnabled('intake')) {
    die(xlt("Institutional Intake is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$episodeRepo = new EpisodeRepository();
$patientRepo = new PatientRepository();
$intakeRepo = new EpisodeIntakeRepository($episodeRepo);
$intakeService = new IntakeService($intakeRepo);
$controller = new IntakeController($patientRepo, $intakeService);

$data = $controller->handle($facilityId, $userId);


// Institutional: capture controller errors (avoid silent failures)
if (is_string($data) && $data !== '') {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data['error']));
    }
    if (!empty($data['errors']) && is_array($data['errors'])) {
        foreach ($data['errors'] as $err) {
            if (is_string($err) && $err !== '') {
                \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($err));
            }
        }
    }
}
$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Episode Intake</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Episode Intake") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-warning"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt("Find Patient") ?></div>
    <div class="card-body">
      <form method="get" class="row g-2">
        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
        <div class="col-12 col-md-8">
          <input name="q" class="form-control" value="<?= htmlspecialchars((string)$data['q']) ?>" placeholder="<?= xla("Search name, DOB (YYYY-MM-DD), phone, or PID") ?>">
        </div>
        <div class="col-12 col-md-4">
          <button class="btn btn-primary w-100"><?= xlt("Search") ?></button>
        </div>
      </form>
    </div>
  </div>

  <?php if ($data['q'] !== ''): ?>
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Results") ?></span>
      <span class="text-muted small"><?= xlt("Select a patient then create ED episode") ?></span>
    </div>
    <div class="table-responsive">
      <form method="post">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">
        <table class="table table-sm table-striped align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th><?= xlt("Select") ?></th>
              <th><?= xlt("PID") ?></th>
              <th><?= xlt("Name") ?></th>
              <th><?= xlt("DOB") ?></th>
              <th><?= xlt("Phone") ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($data['results'] as $r): ?>
              <tr>
                <td>
                  <input type="radio" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>" required>
                </td>
                <td><?= htmlspecialchars((string)$r['pid']) ?></td>
                <td><?= htmlspecialchars((string)($r['lname'] ?? '')) ?>, <?= htmlspecialchars((string)($r['fname'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($r['DOB'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string)($r['phone_cell'] ?? ($r['phone_home'] ?? ''))) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$data['results']): ?>
              <tr><td colspan="5" class="text-center text-muted py-4"><?= xlt("No matching patients") ?></td></tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="p-3 border-top">
          <div class="row g-2">
            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Arrival Mode") ?></label>
              <select name="arrival_mode" class="form-select">
                <option value="WALKIN"><?= xlt("Walk-in") ?></option>
                <option value="EMS"><?= xlt("EMS") ?></option>
                <option value="TRANSFER"><?= xlt("Transfer") ?></option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("ESI") ?></label>
              <select name="acuity_esi" class="form-select">
                <option value=""><?= xlt("—") ?></option>
                <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
              </select>
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Chief Complaint") ?></label>
              <input name="chief_complaint" class="form-control" placeholder="<?= xla("e.g., chest pain") ?>">
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Triage Note") ?></label>
              <input name="triage_note" class="form-control" placeholder="<?= xla("brief triage note (optional)") ?>">
            </div>

            <div class="col-12 col-md-4">
              <button class="btn btn-success w-100" <?= $data['results'] ? '' : 'disabled' ?>>
                <?= xlt("Create ED Episode & Add to Board") ?>
              </button>
            </div>
            <div class="col-12 col-md-8 d-flex align-items-center">
              <span class="text-muted small">
                <?= xlt("This creates an ED episode, sets initial status WAITING, and redirects to the ED Board.") ?>
              </span>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

</div>
</body>
</html>

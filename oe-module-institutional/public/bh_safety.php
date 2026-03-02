<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Repository\BhSafetyRepository;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Service\BhSafetyService;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Controller\BhSafetyController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

if (!$manifest->featureEnabled('bh_safety')) {
    die(xlt("Institutional BH Safety is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$bhRepo = new BhSafetyRepository();
$taskRepo = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;
$bhService = new BhSafetyService($bhRepo, $taskRepo);
$controller = new BhSafetyController($bhRepo, $bhService);

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
  <title>BH Safety</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Behavioral Health Safety") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <div class="alert alert-info">
    <?= xlt("This is an initial BH overlay: set observation level + basic risk flags. If Tasks is enabled, checks are queued for the next 4 hours.") ?>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><?= xlt("Recent BH Safety Updates") ?></div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Updated") ?></th>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt("PID") ?></th>
            <th><?= xlt("Level") ?></th>
            <th><?= xlt("Invol") ?></th>
            <th><?= xlt("Violence") ?></th>
            <th><?= xlt("Suicide") ?></th>
            <th><?= xlt("Elopement") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['updated_datetime']) ?></td>
            <td><?= htmlspecialchars((string)$r['episode_id']) ?></td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td><span class="badge text-bg-warning"><?= htmlspecialchars((string)$r['observation_level']) ?></span></td>
            <td><?= !empty($r['is_involuntary']) ? 'Y' : '' ?></td>
            <td><?= !empty($r['risk_violence']) ? 'Y' : '' ?></td>
            <td><?= !empty($r['risk_suicide']) ? 'Y' : '' ?></td>
            <td><?= !empty($r['elopement_risk']) ? 'Y' : '' ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="8" class="text-center text-muted py-4"><?= xlt("No BH safety updates yet") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>

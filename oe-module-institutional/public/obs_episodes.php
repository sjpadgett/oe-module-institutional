<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Controller\ObsEpisodesController;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

if (!($manifest->featureEnabled('obs_protocols') || $manifest->featureEnabled('obs_episodes'))) {
    die(xlt("Institutional Obs Episodes is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));

$plans = new ObsPlanRepository();
$tasks = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;

$controller = new ObsEpisodesController($plans, $tasks);
$data = $controller->handle($facilityId);


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

function obs_elapsed_hours(string $start): string {
    $ts = strtotime($start);
    if (!$ts) return '';
    $hours = (time() - $ts) / 3600;
    return number_format($hours, 1) . "h";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Obs Episodes</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Observation Episodes") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Active Obs Plans") ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="obs_episodes.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Refresh") ?></a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt("PID") ?></th>
            <th><?= xlt("Protocol") ?></th>
            <th><?= xlt("Start") ?></th>
            <th><?= xlt("Elapsed") ?></th>
            <th><?= xlt("Target") ?></th>
            <th><?= xlt("Next Due") ?></th>
            <th><?= xlt("Overdue") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
          <tr>
            <td><a href="obs_episode.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$r['episode_id']) ?>"><?= htmlspecialchars((string)$r['episode_id']) ?></a></td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td><span class="badge text-bg-info"><?= htmlspecialchars((string)$r['protocol_key']) ?></span></td>
            <td><?= htmlspecialchars((string)$r['start_datetime']) ?></td>
            <td><?= htmlspecialchars(obs_elapsed_hours((string)$r['start_datetime'])) ?></td>
            <td><?= htmlspecialchars((string)$r['target_hours']) ?>h</td>
            <td>
              <?php if (!empty($r['next_task_due'])): ?>
                <span class="text-muted small"><?= htmlspecialchars((string)$r['next_task_due']) ?></span>
                <span class="badge text-bg-light border"><?= htmlspecialchars((string)$r['next_task_type']) ?></span>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['episode_id']) && function_exists('sqlQuery')): ?>
                    <?php $row = sqlQuery("SELECT COUNT(*) AS c FROM oei_task WHERE episode_id = ? AND status = 'OPEN' AND due_datetime < ?", [(int)$r['episode_id'], date('Y-m-d H:i:s')]); ?>
                    <?php $c = (int)($row['c'] ?? 0); ?>
                    <?php if ($c > 0): ?><span class="badge text-bg-danger"><?= htmlspecialchars((string)$c) ?></span><?php endif; ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="8" class="text-center text-muted py-4"><?= xlt("No active obs plans") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>

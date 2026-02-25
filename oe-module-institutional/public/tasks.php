<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
$pageTitle = xlt('Tasks');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Controller\TasksController;

if (!$manifest->featureEnabled('tasks')) {
    die(xlt("Institutional Tasks is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$repo = new TaskRepository();
$controller = new TasksController($repo);
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
  <title>Institutional Tasks</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Tasks") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Open Tasks") ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="tasks.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Refresh") ?></a>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Due") ?></th>
            <th><?= xlt("Task") ?></th>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt("PID") ?></th>
            <th><?= xlt("Assigned") ?></th>
            <th><?= xlt("Action") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['due_datetime']) ?></td>
            <td><span class="badge text-bg-light border"><?= htmlspecialchars((string)$r['task_type']) ?></span></td>
            <td><?= htmlspecialchars((string)$r['episode_id']) ?></td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td><?= htmlspecialchars((string)($r['assigned_to_user_id'] ?? '')) ?></td>
            <td>
              <form method="post" class="d-inline">
                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="task_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                <button class="btn btn-sm btn-success"><?= xlt("Complete") ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="6" class="text-center text-muted py-4"><?= xlt("No open tasks") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>

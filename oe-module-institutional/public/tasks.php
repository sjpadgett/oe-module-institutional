<?php

/**
 * public/tasks.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
$pageTitle = xlt('Tasks');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Controller\TasksController;

if (!$manifest->featureEnabled('tasks')) {
    die(xlt("Institutional Tasks is disabled by manifest"));
}

$facilityId     = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId         = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;
$_tsk_episodeId = (int)($_GET['episode_id'] ?? 0);

// Context-aware back URL
$_oei_pub_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';
$_tsk_backUrl   = $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId;
$_tsk_backLabel = xlt('ED Board');
if ($_tsk_episodeId > 0 && function_exists('sqlQuery')) {
    $_tsk_ep = sqlQuery('SELECT type, pid FROM oei_episode WHERE id = ? LIMIT 1', [$_tsk_episodeId]);
    if ($_tsk_ep) {
        $_tsk_pid  = (int)$_tsk_ep['pid'];
        $_tsk_type = strtoupper((string)($_tsk_ep['type'] ?? 'ED'));
        $_tsk_q    = 'episode_id=' . $_tsk_episodeId . '&pid=' . $_tsk_pid . '&facility_id=' . $facilityId;
        [$_tsk_backUrl, $_tsk_backLabel] = match ($_tsk_type) {
            'AL'  => [$_oei_pub_base . 'al/profile.php?'  . $_tsk_q, xlt('Resident Profile')],
            'IP'  => [$_oei_pub_base . 'ip/profile.php?'  . $_tsk_q, xlt('IP Profile')],
            'HBC' => [$_oei_pub_base . 'hbc/profile.php?' . $_tsk_q, xlt('HBC Profile')],
            default => [$_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId, xlt('ED Board')],
        };
    }
}

$repo = new TaskRepository();
$controller = new TasksController($repo);
$data = $controller->handle($facilityId, $userId, $_tsk_episodeId);


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
$_tskPids = array_values(array_unique(array_filter(array_map(fn($r)=>(int)($r['pid']??0), $data['rows']??[]))));
$_tskPatientNames = oei_patient_names($_tskPids);
$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Institutional Tasks</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Tasks") ?><?php if ($_tsk_episodeId > 0): ?> <span class="badge bg-secondary fs-6"><?= xlt("Episode") ?> #<?= $_tsk_episodeId ?></span><?php endif; ?></h1>
    <div class="d-flex align-items-center gap-2">
      <span class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></span>
      <a class="btn btn-sm btn-outline-secondary"
         href="<?= htmlspecialchars($_tsk_backUrl) ?>">
        ← <?= htmlspecialchars($_tsk_backLabel) ?>
      </a>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Open Tasks") ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="tasks.php?facility_id=<?= urlencode((string)$facilityId) ?><?= $_tsk_episodeId > 0 ? '&episode_id=' . $_tsk_episodeId : '' ?>"><?= xlt("Refresh") ?></a>
    </div>

    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Due") ?></th>
            <th><?= xlt("Task") ?></th>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt('Patient') ?></th>
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
            <td><?= oei_fmt_patient((int)($r['pid']??0), $_tskPatientNames) ?></td>
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
















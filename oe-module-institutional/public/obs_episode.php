<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Service\ObsProtocolEngine;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Controller\ObsEpisodeController;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

if (!$manifest->featureEnabled('obs_protocols')) {
    die(xlt("Institutional Obs Protocols is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId = (int)($_GET['episode_id'] ?? 0);
if ($episodeId <= 0) {
    die(xlt("Missing episode_id"));
}

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$protoRepo = new ProtocolRepository();
$planRepo = new ObsPlanRepository();
$taskRepo = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;
$engine = new ObsProtocolEngine($protoRepo, $planRepo, $taskRepo);

$controller = new ObsEpisodeController($protoRepo, $planRepo, $engine);
$data = $controller->handle($facilityId, $episodeId, $userId);


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

$plan = $data['plan'];
$elapsedHours = '';
$timeLeft = '';
if ($plan && !empty($plan['start_datetime'])) {
    $startTs = strtotime((string)$plan['start_datetime']);
    if ($startTs) {
        $elapsed = (time() - $startTs) / 3600;
        $elapsedHours = number_format($elapsed, 1) . 'h';
        $target = (int)($plan['target_hours'] ?? 24);
        $left = max(0, $target - $elapsed);
        $timeLeft = number_format($left, 1) . 'h';
    }
}
// Build milestone list (derived from protocol JSON):
// - Prefer explicit "milestones" array
// - Otherwise treat tasks with "at_minutes" as milestones
$milestones = [];
$milestoneTasks = []; // keyed by type|due_datetime
if ($plan && !empty($plan['protocol_json'])) {
    $proto = json_decode((string)$plan['protocol_json'], true);
    if (is_array($proto)) {
        $ms = $proto['milestones'] ?? null;
        if (!is_array($ms)) {
            $ms = [];
            $tasksDef = $proto['tasks'] ?? [];
            if (is_array($tasksDef)) {
                foreach ($tasksDef as $tdef) {
                    if (!is_array($tdef)) continue;
                    if (isset($tdef['at_minutes']) && is_array($tdef['at_minutes']) && !empty($tdef['type'])) {
                        foreach ($tdef['at_minutes'] as $m) {
                            if (!is_numeric($m)) continue;
                            $ms[] = [
                                'label' => (string)($tdef['label'] ?? $tdef['type']),
                                'type' => (string)$tdef['type'],
                                'at_minutes' => (int)$m
                            ];
                        }
                    }
                }
            }
        }

        // Compute due timestamps relative to plan start
        $startTs = $plan && !empty($plan['start_datetime']) ? strtotime((string)$plan['start_datetime']) : 0;
        if ($startTs) {
            foreach ($ms as $mdef) {
                if (!is_array($mdef)) continue;
                $mins = isset($mdef['at_minutes']) && is_numeric($mdef['at_minutes']) ? (int)$mdef['at_minutes'] : null;
                if ($mins === null) continue;
                $type = (string)($mdef['type'] ?? '');
                $label = (string)($mdef['label'] ?? $type);
                if ($type === '' || $label === '') continue;

                $dueTs = $startTs + ($mins * 60);
                $milestones[] = [
                    'label' => $label,
                    'type' => $type,
                    'due' => date('Y-m-d H:i:s', $dueTs),
                    'due_ts' => $dueTs
                ];
            }
        }
    }
}

// attach milestone status from tasks table (OPEN/COMPLETE) where possible
if ($milestones && $manifest->featureEnabled('tasks') && function_exists('sqlStatement')) {
    $res = sqlStatement(
        "SELECT task_type, due_datetime, status
         FROM oei_task
         WHERE episode_id = ?",
        [$episodeId]
    );
    while ($row = sqlFetchArray($res)) {
        $k = (string)($row['task_type'] ?? '') . '|' . (string)($row['due_datetime'] ?? '');
        $milestoneTasks[$k] = (string)($row['status'] ?? '');
    }
    foreach ($milestones as &$m) {
        $k = (string)$m['type'] . '|' . (string)$m['due'];
        $m['status'] = $milestoneTasks[$k] ?? '';
    }
    unset($m);
}

// keep only upcoming + recent milestones, show first 10 sorted
if ($milestones) {
    usort($milestones, static fn($a, $b) => ($a['due_ts'] ?? 0) <=> ($b['due_ts'] ?? 0));
    $nowTs = time();
    $milestones = array_values(array_filter($milestones, static function($m) use ($nowTs) {
        $ts = (int)($m['due_ts'] ?? 0);
        return $ts >= ($nowTs - 3600); // keep last hour + future
    }));
    $milestones = array_slice($milestones, 0, 10);
}


// Fetch next due task + overdue count
$nextTask = null;
$overdueCount = 0;
if ($manifest->featureEnabled('tasks') && function_exists('sqlQuery')) {
    $nextTask = sqlQuery(
        "SELECT task_type, due_datetime
         FROM oei_task
         WHERE episode_id = ? AND status = 'OPEN'
         ORDER BY due_datetime ASC
         LIMIT 1",
        [$episodeId]
    ) ?: null;

    $row = sqlQuery(
        "SELECT COUNT(*) AS c
         FROM oei_task
         WHERE episode_id = ? AND status = 'OPEN' AND due_datetime < ?",
        [$episodeId, date('Y-m-d H:i:s')]
    );
    $overdueCount = (int)($row['c'] ?? 0);
}

// Episode basics for applying protocol (pid/eid)
$episodeRow = null;
if (function_exists('sqlQuery')) {
    $episodeRow = sqlQuery("SELECT pid, eid, type FROM oei_episode WHERE id = ? LIMIT 1", [$episodeId]) ?: null;
}
$pid = (int)($episodeRow['pid'] ?? 0);
$eid = $episodeRow['eid'] ?? null;
$type = (string)($episodeRow['type'] ?? '');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Obs Episode</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Obs Episode View") ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="obs_episodes.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Back to Obs Episodes") ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
    </div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Episode Summary") ?></div>
        <div class="card-body">
          <div class="mb-2"><strong><?= xlt("Episode") ?>:</strong> <?= htmlspecialchars((string)$episodeId) ?></div>
          <div class="mb-2"><strong><?= xlt("PID") ?>:</strong> <?= htmlspecialchars((string)$pid) ?></div>
          <div class="mb-2"><strong><?= xlt("Type") ?>:</strong> <span class="badge text-bg-secondary"><?= htmlspecialchars($type) ?></span></div>

          <?php if ($plan): ?>
            <div class="mb-2"><strong><?= xlt("Protocol") ?>:</strong> <span class="badge text-bg-info"><?= htmlspecialchars((string)$plan['protocol_key']) ?></span></div>
            <div class="mb-2"><strong><?= xlt("Start") ?>:</strong> <?= htmlspecialchars((string)$plan['start_datetime']) ?></div>
            <div class="mb-2"><strong><?= xlt("Elapsed") ?>:</strong> <?= htmlspecialchars($elapsedHours) ?></div>
            <div class="mb-2"><strong><?= xlt("Target") ?>:</strong> <?= htmlspecialchars((string)$plan['target_hours']) ?>h</div>
            <div class="mb-2"><strong><?= xlt("Time Left") ?>:</strong> <?= htmlspecialchars($timeLeft) ?></div>
            <div class="mb-2"><strong><?= xlt("Runway") ?>:</strong> <?= htmlspecialchars((string)$plan['runway_hours']) ?>h</div>
          <?php else: ?>
            <div class="text-muted"><?= xlt("No obs plan yet for this episode. Start Obs from ED Board or apply a protocol below.") ?></div>
          <?php endif; ?>

          <?php if ($manifest->featureEnabled('tasks')): ?>
            <hr>
            <div class="mb-2"><strong><?= xlt("Overdue Tasks") ?>:</strong> <?= htmlspecialchars((string)$overdueCount) ?></div>
            <?php if ($nextTask && !empty($nextTask['due_datetime'])): ?>
              <div class="mb-2"><strong><?= xlt("Next Due") ?>:</strong> <?= htmlspecialchars((string)$nextTask['due_datetime']) ?></div>
              <div class="mb-0"><strong><?= xlt("Next Task") ?>:</strong> <span class="badge text-bg-light border"><?= htmlspecialchars((string)$nextTask['task_type']) ?></span></div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><?= xlt("Apply / Change Protocol") ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$pid) ?>">
            <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($eid ?? '')) ?>">

            <div class="col-12 col-md-8">
              <label class="form-label"><?= xlt("Protocol") ?></label>
              <select name="protocol_key" class="form-select">
                <?php foreach ($data['protocolRows'] as $p): ?>
                  <?php $key = (string)($p['protocol_key'] ?? ''); ?>
                  <option value="<?= htmlspecialchars($key) ?>" <?= ($plan && (string)$plan['protocol_key'] === $key) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)($p['label'] ?? $key)) ?> (<?= htmlspecialchars($key) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100"><?= xlt("Apply & Generate Runway") ?></button>
            </div>

            <div class="col-12">
              <div class="form-text">
                <?= xlt("Applying a protocol updates the episode obs plan and generates tasks within the configured runway window. Task generation is de-duped per episode/type/due time.") ?>
              </div>
            </div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt("Protocol JSON (for this episode)") ?></span>
          <a class="btn btn-sm btn-outline-secondary" href="obs_episode.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt("Refresh") ?></a>
        </div>
        <div class="card-body">
          <?php if ($plan && !empty($plan['protocol_json'])): ?>
            <pre class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars((string)$plan['protocol_json']) ?></pre>
          <?php else: ?>
            <div class="text-muted"><?= xlt("No protocol JSON saved yet.") ?></div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

</div>
</body>
</html>

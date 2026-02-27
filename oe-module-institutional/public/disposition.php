<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Controller\DispositionController;

if (!$manifest->featureEnabled('disposition')) {
    die(xlt("Institutional Disposition is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId = (int)($_GET['episode_id'] ?? 0);

$episodeRepo = new EpisodeRepository();
$episodes = $episodeRepo->fetchBoard($facilityId);

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

if ($episodeId <= 0 && !empty($episodes)) {
    $episodeId = (int)($episodes[0]['id'] ?? 0);
}

$selected = null;
foreach ($episodes as $e) {
    if ((int)$e['id'] === $episodeId) {
        $selected = $e;
        break;
    }
}
if (!$selected) {
    die(xlt("No active episode selected"));
}

$pid = (int)($selected['pid'] ?? 0);
$eid = isset($selected['eid']) && is_numeric($selected['eid']) ? (int)$selected['eid'] : null;

$repo = new DispositionRepository();
$events = new EpisodeEventRepository();
$controller = new DispositionController($repo, $events);
$data = $controller->handle($facilityId, $episodeId, $pid, $eid, $userId);


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

$disp = $data['disposition'] ?? [];
$decisionVal = '';
$departVal = '';
if (!empty($disp['decision_datetime'])) {
    $decisionVal = str_replace(' ', 'T', substr((string)$disp['decision_datetime'], 0, 16));
}
if (!empty($disp['depart_datetime'])) {
    $departVal = str_replace(' ', 'T', substr((string)$disp['depart_datetime'], 0, 16));
}

$codes = [
  'DISCHARGE' => xlt('Discharge'),
  'ADMIT' => xlt('Admit'),
  'TRANSFER' => xlt('Transfer'),
  'LWBS' => xlt('Left Without Being Seen'),
  'AMA' => xlt('Left AMA'),
  'ELoped' => xlt('Eloped'),
  'EXPIRE' => xlt('Expired'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Disposition</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Disposition") ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Back to ED Board") ?></a>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Active Episodes") ?></div>
        <div class="list-group list-group-flush">
          <?php foreach ($episodes as $e): ?>
                <?php $active = ((int)$e['id'] === $episodeId); ?>
            <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
               href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$e['id']) ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-semibold">#<?= htmlspecialchars((string)$e['id']) ?> • PID <?= htmlspecialchars((string)$e['pid']) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?></div>
                </div>
                <div class="text-end">
                  <span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span>
                  <?php if (!empty($e['disposition'])): ?><span class="badge text-bg-info"><?= htmlspecialchars((string)$e['disposition']) ?></span><?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt("Set Disposition") ?> • #<?= htmlspecialchars((string)$episodeId) ?></span>
          <span class="text-muted small"><?= xlt("PID") ?> <?= htmlspecialchars((string)$pid) ?></span>
        </div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Disposition") ?></label>
              <select name="disposition_code" class="form-select" required>
                <option value=""><?= xlt("Select...") ?></option>
                <?php foreach ($codes as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= (!empty($disp['disposition_code']) && (string)$disp['disposition_code'] === (string)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$lbl) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Destination") ?></label>
              <input name="destination" class="form-control" value="<?= htmlspecialchars((string)($disp['destination'] ?? '')) ?>" placeholder="<?= xla("Home / ICU / Facility / Psych / SNF") ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Decision Time") ?></label>
              <input type="datetime-local" name="decision_datetime" class="form-control" value="<?= htmlspecialchars($decisionVal) ?>">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Depart Time") ?></label>
              <input type="datetime-local" name="depart_datetime" class="form-control" value="<?= htmlspecialchars($departVal) ?>">
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="admit_flag" name="admit_flag" <?= !empty($disp['admit_flag']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="admit_flag"><?= xlt("Admit flag (for metrics and downstream workflows)") ?></label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Notes") ?></label>
              <input name="notes" class="form-control" value="<?= htmlspecialchars((string)($disp['notes'] ?? '')) ?>" placeholder="<?= xla("Short note...") ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><?= xlt("Save") ?></button>
              <a class="btn btn-outline-secondary" href="throughput.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("View Throughput") ?></a>
            </div>

            <div class="col-12">
              <div class="form-text"><?= xlt("Decision and Depart times also create episode events (DECISION/DEPART) for throughput calculations.") ?></div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

</div>
</body>
</html>



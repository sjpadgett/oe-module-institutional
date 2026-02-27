<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Submodule\Throughput\Controller\ThroughputController;

if (!$manifest->featureEnabled('throughput')) {
    die(xlt("Institutional Throughput is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$date = (string)($_GET['date'] ?? date('Y-m-d'));
$start = $date . ' 00:00:00';
$end = $date . ' 23:59:59';

$episodeRepo = new EpisodeRepository();
$episodes = $episodeRepo->fetchByDateRange($facilityId, $start, $end);

$events = new EpisodeEventRepository();
$dispo = new DispositionRepository();
$controller = new ThroughputController($events, $dispo);
$data = $controller->handle($facilityId, $start, $end, $episodes);


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

function fmt(?int $v): string {
    return $v === null ? '' : ((string)$v . 'm');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Throughput</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Throughput") ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Disposition") ?></a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
    <div class="col-12 col-md-3">
      <label class="form-label"><?= xlt("Date") ?></label>
      <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date) ?>">
    </div>
    <div class="col-12 col-md-3 d-flex align-items-end">
      <button class="btn btn-primary w-100"><?= xlt("Refresh") ?></button>
    </div>
    <div class="col-12 col-md-6 d-flex align-items-end justify-content-end">
      <div class="text-muted small">
        <?= xlt("Episodes") ?>: <?= htmlspecialchars((string)($data['summary']['count'] ?? 0)) ?>
      </div>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→Room") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_room'])) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→Provider") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_provider'])) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→Decision") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_decision'])) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→Depart") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_depart'])) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→BH Accept") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_bh_accepted'])) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small"><?= xlt("Avg Door→BH Transport") ?></div>
          <div class="h4 mb-0"><?= htmlspecialchars(fmt($data['summary']['avg_door_to_bh_transport'])) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><?= xlt("Episode Metrics") ?></div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt("PID") ?></th>
            <th><?= xlt("Type") ?></th>
            <th><?= xlt("Arrival") ?></th>
            <th><?= xlt("Door→Room") ?></th>
            <th><?= xlt("Door→Prov") ?></th>
            <th><?= xlt("Door→Dec") ?></th>
            <th><?= xlt("Door→Dep") ?></th>
            <th><?= xlt("Disposition") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
          <tr>
            <td><a href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$r['episode_id']) ?>"><?= htmlspecialchars((string)$r['episode_id']) ?></a></td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)$r['type']) ?></span></td>
            <td class="text-muted small"><?= htmlspecialchars((string)$r['arrival']) ?></td>
            <td><?= htmlspecialchars(fmt($r['door_to_room_min'])) ?></td>
            <td><?= htmlspecialchars(fmt($r['door_to_provider_min'])) ?></td>
            <td><?= htmlspecialchars(fmt($r['door_to_decision_min'])) ?></td>
            <td><?= htmlspecialchars(fmt($r['door_to_depart_min'])) ?></td>
            <td>
              <?php if (!empty($r['disposition'])): ?>
                <span class="badge text-bg-info"><?= htmlspecialchars((string)$r['disposition']) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="9" class="text-center text-muted py-4"><?= xlt("No episodes in range") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card-body">
      <div class="form-text">
        <?= xlt("Metrics prefer explicit events (ROOM/PROVIDER/DECISION/DEPART). Decision and Depart events are recorded via Disposition. Room/Provider events can be added next as quick actions on ED Board.") ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>



<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Controller\BedMgmtController;
use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;

if (!$manifest->featureEnabled('bed_mgmt')) {
    die(xlt('Bed Management is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$controller = new BedMgmtController(
    new LocationRepository(),
    new EpisodeLocationRepository(),
    new EpisodeRepository()
);

$data = $controller->handle($facilityId, $userId);

// Unpack view model
$csrf         = $data['csrf'];
$locations    = $data['locations'];
$episodes     = $data['episodes'];
$locByEpisode = $data['locByEpisode'];

$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Bed Board') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Bed Board') ?></h1>
    <a class="btn btn-sm btn-outline-secondary"
       href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Back to ED Board') ?></a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt('Locations') ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
            <input type="hidden" name="action" value="save_location">
            <div class="col-6">
              <label class="form-label"><?= xlt('Code') ?></label>
              <input name="code" class="form-control" placeholder="<?= xla('ED01') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Name') ?></label>
              <input name="name" class="form-control" placeholder="<?= xla('ED Room 1') ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Type') ?></label>
              <select name="location_type" class="form-select">
                <option value="ROOM"><?= xlt('Room') ?></option>
                <option value="BED"><?= xlt('Bed') ?></option>
                <option value="AREA"><?= xlt('Area') ?></option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Unit') ?></label>
              <input name="unit_name" class="form-control" placeholder="<?= xla('ED / OBS') ?>">
            </div>
            <div class="col-4">
              <label class="form-label"><?= xlt('Sort') ?></label>
              <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-8">
              <label class="form-label"><?= xlt('Notes') ?></label>
              <input name="notes" class="form-control" placeholder="<?= xla('Optional') ?>">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active"><?= xlt('Active') ?></label>
              </div>
            </div>
            <div class="col-12">
              <button class="btn btn-primary w-100"><?= xlt('Add/Update Location') ?></button>
              <div class="form-text"><?= xlt('Lightweight location list for small hospitals (not full ADT).') ?></div>
            </div>
          </form>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach ($locations as $loc): ?>
            <div class="list-group-item">
              <div class="fw-semibold"><?= htmlspecialchars((string)$loc['name']) ?></div>
              <div class="small text-muted">
                <?= htmlspecialchars((string)$loc['code']) ?>
                • <?= htmlspecialchars((string)$loc['location_type']) ?>
                <?= !empty($loc['unit_name']) ? (' • ' . htmlspecialchars((string)$loc['unit_name'])) : '' ?>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($locations)): ?>
            <div class="list-group-item text-muted"><?= xlt('No locations configured yet') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt('Assign Episodes to Locations') ?></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Episode') ?></th>
                <th><?= xlt('PID') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Chief Complaint') ?></th>
                <th><?= xlt('Current') ?></th>
                <th style="width: 340px;"><?= xlt('Move') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($episodes as $e): ?>
                    <?php
                    $epid    = (int)($e['id'] ?? 0);
                    $cur     = $locByEpisode[$epid] ?? null;
                    $curLabel = $cur ? ((string)($cur['location_code'] ?? '') ?: ('#' . (string)($cur['location_id'] ?? ''))) : '';
                    ?>
                <tr>
                  <td>#<?= htmlspecialchars((string)$epid) ?></td>
                  <td><?= htmlspecialchars((string)($e['pid'] ?? '')) ?></td>
                  <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span></td>
                  <td class="text-truncate" style="max-width: 260px;"><?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?></td>
                  <td>
                    <?php if ($curLabel !== ''): ?>
                      <span class="badge text-bg-info"><?= htmlspecialchars($curLabel) ?></span>
                    <?php else: ?>
                      <span class="text-muted"><?= xlt('Unassigned') ?></span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <form method="post" class="d-flex gap-2">
                      <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                      <input type="hidden" name="action" value="move_episode">
                      <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$epid) ?>">
                      <input type="hidden" name="pid" value="<?= htmlspecialchars((string)($e['pid'] ?? '')) ?>">
                      <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($e['eid'] ?? '')) ?>">
                      <select name="location_id" class="form-select form-select-sm">
                        <option value=""><?= xlt('Select...') ?></option>
                        <?php foreach ($locations as $loc): ?>
                          <option value="<?= htmlspecialchars((string)$loc['id']) ?>">
                            <?= htmlspecialchars((string)$loc['code']) ?> — <?= htmlspecialchars((string)$loc['name']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <input name="location_code" class="form-control form-control-sm"
                             style="max-width: 90px;" placeholder="<?= xla('Code') ?>">
                      <button class="btn btn-sm btn-outline-primary"><?= xlt('Move') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($episodes)): ?>
                <tr><td colspan="6" class="text-muted p-3"><?= xlt('No active episodes') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div class="card-body">
          <div class="form-text"><?= xlt('Next: show move history and add unit filters.') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>

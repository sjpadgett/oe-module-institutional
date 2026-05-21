<?php

/**
 * public/bed_management.php
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

use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Controller\BedMgmtController;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;

if (!$manifest->featureEnabled('bed_mgmt')) {
    die(xlt('Bed Management is disabled by manifest'));
}

$facilityId   = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$selectedUnit = trim((string)($_GET['unit'] ?? ''));
$userId       = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$controller = new BedMgmtController(
    new LocationRepository(),
    new EpisodeLocationRepository(),
    new EpisodeRepository()
);

$data = $controller->handle($facilityId, $userId, $selectedUnit);

$csrf               = $data['csrf'];
$locations          = $data['locations'];
$episodes           = $data['episodes'];
$locByEpisode       = $data['locByEpisode'];
$occupiedLocationIds= $data['occupiedLocationIds'];
$units              = $data['units'];
$history            = $data['history'];
$selectedUnit       = (string)($data['selectedUnit'] ?? '');

$_bmPids = array_values(array_unique(array_filter(array_merge(
    array_map(fn($e) => (int)($e['pid'] ?? 0), $episodes ?? []),
    array_map(fn($h) => (int)($h['pid'] ?? 0), $history ?? [])
))));
$_bmPatientNames = oei_patient_names($_bmPids);
$href = institutional_bootstrap5_href($manifest);
$showEpisode = static function (array $episode, ?array $current, string $unitFilter): bool {
    if ($unitFilter === '') {
        return true;
    }
    $currentUnit = trim((string)($current['unit_name'] ?? ''));
    return $currentUnit === '' || $currentUnit === $unitFilter;
};
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Bed Management') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
<?php require __DIR__ . '/../src/Core/Ui/partials/flash.php'; ?>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0"><?= xlt('Bed Management') ?></h1>
      <div class="text-muted small">
        <?= $selectedUnit !== '' ? htmlspecialchars($selectedUnit) : xlt('All units') ?>
      </div>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <form method="get" class="d-flex align-items-center gap-2 flex-wrap">
        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
        <label class="small text-muted mb-0" for="unit-filter"><?= xlt('Unit') ?></label>
        <select id="unit-filter" name="unit" class="form-select form-select-sm" onchange="this.form.submit()">
          <option value=""><?= xlt('All Units') ?></option>
          <?php foreach ($units as $unit): ?>
            <option value="<?= htmlspecialchars($unit) ?>"<?= $unit === $selectedUnit ? ' selected' : '' ?>>
                <?= htmlspecialchars($unit) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-sm btn-outline-secondary"><?= xlt('Apply') ?></button>
        <?php if ($selectedUnit !== ''): ?>
          <a class="btn btn-sm btn-outline-secondary" href="bed_management.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Clear') ?></a>
        <?php endif; ?>
      </form>
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Back to ED Board') ?></a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span><?= xlt('Locations') ?></span>
          <?php if ($selectedUnit !== ''): ?>
            <span class="badge bg-secondary"><?= htmlspecialchars($selectedUnit) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
            <input type="hidden" name="action" value="save_location">
            <input type="hidden" name="unit" value="<?= htmlspecialchars($selectedUnit) ?>">
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
                <option value="WARD"><?= xlt('Ward') ?></option>
                <option value="ICU"><?= xlt('ICU') ?></option>
                <option value="AREA"><?= xlt('Area / Bay') ?></option>
                <option value="CORRIDOR"><?= xlt('Corridor / Hallway') ?></option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Unit') ?></label>
              <input name="unit_name" class="form-control" value="<?= htmlspecialchars($selectedUnit) ?>" placeholder="<?= xla('ED / OBS') ?>">
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
              <div class="form-text"><?= xlt('Filtered by unit when a unit is selected above.') ?></div>
            </div>
          </form>
        </div>
        <div class="list-group list-group-flush">
          <?php foreach ($locations as $loc): ?>
            <div class="list-group-item d-flex justify-content-between align-items-start gap-2">
              <div>
                <div class="fw-semibold"><?= htmlspecialchars((string)$loc['name']) ?></div>
                <div class="small text-muted">
                  <?= htmlspecialchars((string)$loc['code']) ?>
                  • <?= htmlspecialchars((string)$loc['location_type']) ?>
                  <?= !empty($loc['unit_name']) ? (' • ' . htmlspecialchars((string)$loc['unit_name'])) : '' ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($locations)): ?>
            <div class="list-group-item text-muted">
                <?= $selectedUnit !== '' ? xlt('No active locations in this unit yet') : xlt('No locations configured yet') ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><?= xlt('Assign Episodes to Locations') ?></span>
          <span class="small text-muted"><?= xlt('Unassigned episodes stay visible even when filtering by unit.') ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Episode') ?></th>
                <th><?= xlt('Patient') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Current') ?></th>
                <th><?= xlt('Chief Complaint') ?></th>
                <th style="width: 380px;"><?= xlt('Move') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php $shownRows = 0; ?>
              <?php foreach ($episodes as $e): ?>
                    <?php
                    $epid     = (int)($e['id'] ?? 0);
                    $cur      = $locByEpisode[$epid] ?? null;
                    if (!$showEpisode($e, $cur, $selectedUnit)) {
                        continue;
                    }
                    $shownRows++;
                    $currentCode = trim((string)($cur['current_code'] ?? $cur['location_code'] ?? ''));
                    $currentName = trim((string)($cur['current_name'] ?? ''));
                    $currentUnit = trim((string)($cur['unit_name'] ?? ''));
                    $curLabel = $currentCode !== ''
                      ? $currentCode . ($currentName !== '' ? ' — ' . $currentName : '')
                      : ($currentName !== '' ? $currentName : '');
                    ?>
                <tr>
                  <td>#<?= htmlspecialchars((string)$epid) ?></td>
                  <td><?= oei_fmt_patient((int)($e['pid'] ?? 0), $_bmPatientNames) ?></td>
                  <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span></td>
                  <td>
                    <?php if ($curLabel !== ''): ?>
                      <div><span class="badge text-bg-info"><?= htmlspecialchars($curLabel) ?></span></div>
                        <?php if ($currentUnit !== ''): ?>
                        <div class="small text-muted mt-1"><?= htmlspecialchars($currentUnit) ?></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="text-muted"><?= xlt('Unassigned') ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="text-truncate" style="max-width: 260px;"><?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?></td>
                  <td>
                    <form method="post" class="d-flex gap-2 align-items-start flex-wrap">
                      <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                      <input type="hidden" name="action" value="move_episode">
                      <input type="hidden" name="unit" value="<?= htmlspecialchars($selectedUnit) ?>">
                      <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$epid) ?>">
                      <input type="hidden" name="pid" value="<?= htmlspecialchars((string)($e['pid'] ?? '')) ?>">
                      <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($e['eid'] ?? '')) ?>">
                      <div class="flex-grow-1" style="min-width: 200px;">
                        <select name="location_id" class="form-select form-select-sm">
                          <option value=""><?= xlt('Select...') ?></option>
                          <?php foreach ($locations as $loc): ?>
                                <?php
                                $locId = (int)($loc['id'] ?? 0);
                                $occupiedBy = (int)($occupiedLocationIds[$locId] ?? 0);
                                $isDisabled = $occupiedBy > 0 && $occupiedBy !== $epid;
                                $label = (string)$loc['code'] . ' — ' . (string)$loc['name'];
                                if (!empty($loc['unit_name'])) {
                                    $label .= ' (' . (string)$loc['unit_name'] . ')';
                                }
                                ?>
                            <option value="<?= htmlspecialchars((string)$locId) ?>"<?= $isDisabled ? ' disabled' : '' ?>>
                                <?= htmlspecialchars($label) ?><?= $isDisabled ? ' ' . xlt('[occupied]') : '' ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <input name="location_code" class="form-control form-control-sm"
                             style="max-width: 110px;" placeholder="<?= xla('Code') ?>">
                      <input name="note" class="form-control form-control-sm"
                             style="max-width: 140px;" placeholder="<?= xla('Move note') ?>">
                      <button class="btn btn-sm btn-outline-primary"><?= xlt('Move') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if ($shownRows === 0): ?>
                <tr><td colspan="6" class="text-muted p-3"><?= xlt('No active episodes for this view') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><?= xlt('Recent Move History') ?></span>
          <span class="small text-muted"><?= xlt('Latest 25 assignments / moves') ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('When') ?></th>
                <th><?= xlt('Episode') ?></th>
                <th><?= xlt('Patient') ?></th>
                <th><?= xlt('From') ?></th>
                <th><?= xlt('To') ?></th>
                <th><?= xlt('Note') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $row): ?>
                    <?php
                    $fromLabel = trim((string)($row['from_code'] ?? ''));
                    if (!empty($row['from_name'])) {
                        $fromLabel .= ($fromLabel !== '' ? ' — ' : '') . (string)$row['from_name'];
                    }
                    $toLabel = trim((string)($row['to_code'] ?? ''));
                    if (!empty($row['to_name'])) {
                        $toLabel .= ($toLabel !== '' ? ' — ' : '') . (string)$row['to_name'];
                    }
                    ?>
                <tr>
                  <td class="small text-nowrap"><?= htmlspecialchars((string)($row['start_datetime'] ?? '')) ?></td>
                  <td>#<?= (int)($row['episode_id'] ?? 0) ?></td>
                  <td><?= oei_fmt_patient((int)($row['pid'] ?? 0), $_bmPatientNames) ?></td>
                  <td><?= $fromLabel !== '' ? htmlspecialchars($fromLabel) : '<span class="text-muted">' . xlt('Initial assignment') . '</span>' ?></td>
                  <td>
                    <div><?= $toLabel !== '' ? htmlspecialchars($toLabel) : '<span class="text-muted">' . xlt('Ad-hoc code') . '</span>' ?></div>
                    <?php if (!empty($row['unit_name'])): ?>
                      <div class="small text-muted"><?= htmlspecialchars((string)$row['unit_name']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= htmlspecialchars((string)($row['note'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($history)): ?>
                <tr><td colspan="6" class="text-muted p-3"><?= xlt('No move history found for this view') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>







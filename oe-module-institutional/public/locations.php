<?php

/**
 * public/locations.php
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
$pageTitle = xlt('Locations');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Controller\LocationsController;

if (!$manifest->featureEnabled('adt_lite')) {
    die(xlt("Institutional Locations is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));

$repo = new LocationRepository();
$controller = new LocationsController($repo);
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Institutional Locations</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Locations") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt("Add Location") ?></div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="action" value="create">
        <div class="col-12 col-md-6">
          <label class="form-label"><?= xlt("Name") ?></label>
          <input name="name" class="form-control" required placeholder="<?= xla("ED Room 1") ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label"><?= xlt("Type") ?></label>
          <select name="type" class="form-select">
            <option value="ED_ROOM"><?= xlt("ED Room") ?></option>
            <option value="OBS_BED"><?= xlt("Obs Bed") ?></option>
            <option value="BH_SAFE_ROOM"><?= xlt("BH Safe Room") ?></option>
            <option value="HOLDING"><?= xlt("Holding") ?></option>
          </select>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label"><?= xlt("Status") ?></label>
          <select name="status" class="form-select">
            <option value="AVAILABLE"><?= xlt("Available") ?></option>
            <option value="OCCUPIED"><?= xlt("Occupied") ?></option>
            <option value="DIRTY"><?= xlt("Dirty") ?></option>
            <option value="BLOCKED"><?= xlt("Blocked") ?></option>
          </select>
        </div>
        <div class="col-12">
          <button class="btn btn-primary"><?= xlt("Add") ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Locations") ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="locations.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Refresh") ?></a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("ID") ?></th>
            <th><?= xlt("Name") ?></th>
            <th><?= xlt("Type") ?></th>
            <th><?= xlt("Status") ?></th>
            <th><?= xlt("Active") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r): ?>
          <tr>
            <td><?= htmlspecialchars((string)$r['id']) ?></td>
            <td><?= htmlspecialchars((string)$r['name']) ?></td>
            <td><?= htmlspecialchars((string)$r['type']) ?></td>
            <td><?= htmlspecialchars((string)$r['status']) ?></td>
            <td><?= htmlspecialchars((string)$r['active']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="5" class="text-center text-muted py-4"><?= xlt("No locations") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>







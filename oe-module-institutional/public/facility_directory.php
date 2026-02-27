<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
$pageTitle = xlt('Facility Directory');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;

if (!$manifest->featureEnabled('facility_directory')) {
    die(xlt("Facility Directory is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$repo = new FacilityDirectoryRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }
    $idRaw = (string)($_POST['id'] ?? '');
    $id = is_numeric($idRaw) ? (int)$idRaw : null;
    $name = trim((string)($_POST['name'] ?? ''));
    $service = trim((string)($_POST['service_type'] ?? 'GENERAL'));
    $phone = trim((string)($_POST['phone'] ?? '')) ?: null;
    $fax = trim((string)($_POST['fax'] ?? '')) ?: null;
    $email = trim((string)($_POST['email'] ?? '')) ?: null;
    $address = trim((string)($_POST['address'] ?? '')) ?: null;
    $hours = trim((string)($_POST['hours'] ?? '')) ?: null;
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;
    $active = !empty($_POST['is_active']) ? 1 : 0;
    $sort = (int)($_POST['sort_order'] ?? 0);

    if ($name !== '') {
        $repo->upsert($facilityId, $id, $name, $service, $phone, $fax, $email, $address, $hours, $notes, $active, $sort);
    }
    header("Location: facility_directory.php?facility_id=" . urlencode((string)$facilityId));
    exit;
}

$csrf = CsrfUtils::collectCsrfToken();
$rows = $repo->listActive($facilityId);
$href = institutional_bootstrap5_href($manifest);

$serviceTypes = [
  'GENERAL' => xlt('General'),
  'BH' => xlt('Behavioral Health'),
  'ICU' => xlt('ICU'),
  'OB' => xlt('OB'),
  'PEDS' => xlt('Pediatrics'),
  'SURG' => xlt('Surgery'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Facility Directory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Facility Directory") ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Add / Update") ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
            <input type="hidden" name="id" value="">
            <div class="col-12">
              <label class="form-label"><?= xlt("Name") ?></label>
              <input name="name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label"><?= xlt("Service Type") ?></label>
              <select name="service_type" class="form-select">
                <?php foreach ($serviceTypes as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>"><?= htmlspecialchars((string)$lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt("Phone") ?></label>
              <input name="phone" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt("Fax") ?></label>
              <input name="fax" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label"><?= xlt("Email") ?></label>
              <input name="email" class="form-control">
            </div>
            <div class="col-12">
              <label class="form-label"><?= xlt("Address") ?></label>
              <input name="address" class="form-control">
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt("Hours") ?></label>
              <input name="hours" class="form-control" placeholder="<?= xla("24/7") ?>">
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt("Sort") ?></label>
              <input type="number" name="sort_order" class="form-control" value="0">
            </div>
            <div class="col-12">
              <label class="form-label"><?= xlt("Notes") ?></label>
              <input name="notes" class="form-control">
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" checked>
                <label class="form-check-label" for="is_active"><?= xlt("Active") ?></label>
              </div>
            </div>
            <div class="col-12">
              <button class="btn btn-primary w-100"><?= xlt("Save") ?></button>
              <div class="form-text"><?= xlt("Next: inline edit + inactive list.") ?></div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Directory") ?></div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt("Name") ?></th>
                <th><?= xlt("Service") ?></th>
                <th><?= xlt("Phone") ?></th>
                <th><?= xlt("Fax") ?></th>
                <th><?= xlt("Email") ?></th>
                <th><?= xlt("Notes") ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $r): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars((string)$r['name']) ?></td>
                  <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)$r['service_type']) ?></span></td>
                  <td><?= htmlspecialchars((string)($r['phone'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['fax'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['email'] ?? '')) ?></td>
                  <td><?= htmlspecialchars((string)($r['notes'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($rows)): ?>
                <tr><td colspan="6" class="text-muted p-3"><?= xlt("No directory entries yet") ?></td></tr>
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



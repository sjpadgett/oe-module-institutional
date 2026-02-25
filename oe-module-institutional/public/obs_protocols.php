<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Controller\ObsProtocolsController;

if (!$manifest->featureEnabled('obs_protocols')) {
    die(xlt("Institutional Obs Protocols is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$repo = new ProtocolRepository();
$controller = new ObsProtocolsController($repo);
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

$defaultJson = json_encode([
  "target_hours" => 24,
  "runway_hours" => 6,
  "milestones" => [
    ["label" => "Troponin #2", "type" => "TROPONIN", "at_minutes" => 180],
    ["label" => "Troponin #3", "type" => "TROPONIN", "at_minutes" => 360],
  ],
  "tasks" => [
    ["type" => "VITALS_Q4H", "every_minutes" => 240],
    ["type" => "REASSESS_Q2H", "every_minutes" => 120],
    ["type" => "TROPONIN", "at_minutes" => [0, 180, 360]],
  ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Obs Protocols</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Observation Protocols") ?></h1>
    <div class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-5">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Create / Update Protocol") ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">
            <input type="hidden" name="action" value="save">

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Protocol Key") ?></label>
              <input name="protocol_key" class="form-control" placeholder="CHEST_PAIN" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Version") ?></label>
              <input name="version" class="form-control" value="1">
            </div>
            <div class="col-12">
              <label class="form-label"><?= xlt("Label") ?></label>
              <input name="label" class="form-control" placeholder="<?= xla("Chest Pain Observation") ?>" required>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="enabled" name="enabled" checked>
                <label class="form-check-label" for="enabled"><?= xlt("Enabled") ?></label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Definition JSON") ?></label>
              <textarea name="definition_json" class="form-control" rows="12"><?= htmlspecialchars($defaultJson) ?></textarea>
              <div class="form-text"><?= xlt("Defines target hours, runway hours, and tasks (every_minutes or at_minutes).") ?></div>
            </div>

            <div class="col-12">
              <button class="btn btn-primary w-100"><?= xlt("Save Protocol") ?></button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-7">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt("Enabled Protocols") ?></span>
          <a class="btn btn-sm btn-outline-secondary" href="obs_protocols.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Refresh") ?></a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt("Key") ?></th>
                <th><?= xlt("Label") ?></th>
                <th><?= xlt("Version") ?></th>
                <th><?= xlt("Updated") ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($data['rows'] as $r): ?>
              <tr>
                <td><code><?= htmlspecialchars((string)$r['protocol_key']) ?></code></td>
                <td><?= htmlspecialchars((string)$r['label']) ?></td>
                <td><?= htmlspecialchars((string)$r['version']) ?></td>
                <td class="text-muted"><?= htmlspecialchars((string)$r['updated_datetime']) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (!$data['rows']): ?>
              <tr><td colspan="4" class="text-center text-muted py-4"><?= xlt("No protocols yet") ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="alert alert-secondary mt-3">
        <strong><?= xlt("Tip") ?>:</strong>
        <?= xlt("Start an Obs episode from the ED Board. The default GENERAL_OBS protocol will apply automatically. You can later choose a different protocol from the Obs Episode view (next step).") ?>
      </div>
    </div>
  </div>

</div>
</body>
</html>

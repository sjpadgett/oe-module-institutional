<?php
/**
 * public/al/adl.php — ADL Charting (Activities of Daily Living)
 *
 * Aide-facing charting form. One session covers all 7 MDS 3.0 domains.
 * History shows trend with care level computation per session.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Controller\AdlController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\AdlLevel;

if (!$manifest->featureEnabled('al_adl')) {
    oei_exit_with_alert(xlt('ADL Tracking is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

if ($episodeId === 0) {
    // No episode context — send to Board to select a resident
    header('Location: board.php?facility_id=' . $facilityId
         . '&notice=select_resident');
    exit;
}

$controller = new AdlController();
$data = $controller->handle($episodeId, $facilityId, $userId);

$levelOptions = [
    AdlLevel::INDEPENDENT      => xlt('0 — Independent'),
    AdlLevel::SUPERVISION      => xlt('1 — Supervision'),
    AdlLevel::LIMITED_ASSIST   => xlt('2 — Limited Assist'),
    AdlLevel::EXTENSIVE_ASSIST => xlt('3 — Extensive Assist'),
    AdlLevel::TOTAL_DEPENDENCE => xlt('4 — Total Dependence'),
    AdlLevel::DID_NOT_OCCUR    => xlt('8 — Did Not Occur'),
];

$pageTitle = xlt('ADL Charting');

$activePage  = 'adl';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/Core/Ui/partials/al_resident_nav.php';
?>
<?php if ($data['flash']): ?>
<div class="alert <?= str_contains($data['flash'], 'saved') ? 'alert-success' : 'alert-danger' ?> py-2">
  <?= htmlspecialchars($data['flash']) ?>
</div>
<?php endif; ?>

<div class="row g-3">

  <!-- Charting form -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header bg-primary text-white"><strong>📊 <?= xlt('New ADL Chart') ?></strong></div>
      <div class="card-body">
        <form method="POST">
          <?= CsrfUtils::collectCsrfToken() ?>
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
          <?php foreach ($data['domains'] as $domain => $label): ?>
          <div class="mb-3">
            <label class="form-label fw-semibold">
              <?= htmlspecialchars($label) ?>
            </label>
            <select name="adl_<?= htmlspecialchars($domain) ?>" class="form-select form-select-sm">
              <?php foreach ($levelOptions as $val => $optLabel): ?>
              <option value="<?= $val ?>" <?= ($val === AdlLevel::INDEPENDENT) ? 'selected' : '' ?>>
                <?= htmlspecialchars($optLabel) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endforeach; ?>
          <div class="mb-3">
            <label class="form-label"><?= xlt('Notes') ?></label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"
                      placeholder="<?= xlt('Optional observations…') ?>"></textarea>
          </div>
          <button type="submit" class="btn btn-primary w-100"><?= xlt('Save ADL Chart') ?></button>
        </form>
      </div>
    </div>
  </div>

  <!-- History -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header bg-light"><strong>📋 <?= xlt('Recent Charts') ?></strong></div>
      <?php if (empty($data['records'])): ?>
        <div class="card-body text-muted small"><?= xlt('No ADL records yet.') ?></div>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr>
            <th><?= xlt('Date / Time') ?></th>
            <th><?= xlt('Score') ?></th>
            <th><?= xlt('Care Level') ?></th>
            <th><?= xlt('By') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($data['records'] as $rec): ?>
          <tr data-bs-toggle="collapse"
              data-bs-target="#adl-<?= (int)$rec['id'] ?>"
              style="cursor:pointer;">
            <td><?= htmlspecialchars(date('M j H:i', strtotime($rec['noted_datetime']))) ?></td>
            <td><strong><?= (int)$rec['adl_score'] ?></strong> / 28</td>
            <td>
              <span class="badge bg-<?= htmlspecialchars($rec['care_level_badge']) ?>">
                <?= htmlspecialchars($rec['care_level_label']) ?>
              </span>
            </td>
            <td class="small text-muted"><?= htmlspecialchars($rec['noted_by'] ?: '—') ?></td>
          </tr>
          <!-- Domain detail collapse -->
          <tr class="collapse" id="adl-<?= (int)$rec['id'] ?>">
            <td colspan="4" class="bg-light">
              <div class="d-flex flex-wrap gap-2 p-2">
                <?php foreach ($rec['domain_labels'] as $dom => $lbl): ?>
                <span class="badge bg-secondary">
                  <?= htmlspecialchars(AdlLevel::DOMAINS[$dom] ?? $dom) ?>: <?= htmlspecialchars($lbl) ?>
                </span>
                <?php endforeach; ?>
                <?php if ($rec['notes']): ?>
                <span class="text-muted small ms-2">📝 <?= htmlspecialchars($rec['notes']) ?></span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<div class="mt-3">
  <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back to Board') ?>
  </a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</div>
</body>
</html>

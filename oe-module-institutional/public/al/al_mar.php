<?php
/**
 * public/al/al_mar.php — AL Medication Administration Record
 *
 * 5-day rolling window. Scheduled medications in a grid (drug × date).
 * PRN medications in a separate section.
 * Administration modal for GIVEN / HELD / REFUSED recording.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Controller\AlMarController;

if (!$manifest->featureEnabled('al_mar')) {
    oei_exit_with_alert(xlt('AL Medication Administration Record is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

if ($episodeId === 0) {
    // No episode context — send to Board to select a resident
    header('Location: board.php?facility_id=' . $facilityId
         . '&notice=select_resident');
    exit;
}

$controller = new AlMarController();
$data       = $controller->handle($episodeId, $pid, $facilityId, $userId);
$patient    = $data['patient'];

$pageTitle = xlt('AL Medication Administration Record');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

// Outcome badge classes
$outcomeBadge = [
    'GIVEN'   => 'success',
    'HELD'    => 'warning',
    'REFUSED' => 'danger',
    'OMITTED' => 'secondary',
    'PENDING' => 'light border',
];

$activePage  = 'mar';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= ($_oei_theme ?? 'light') ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .mar-header   { background:linear-gradient(135deg,#023e8a,#0077b6); color:#fff; border-radius:.5rem; }
    .mar-table th { font-size:.78rem; white-space:nowrap; }
    .mar-table td { font-size:.8rem; vertical-align:middle; }
    .cell-slot    { display:inline-block; min-width:52px; }
    .high-alert   { border-left:3px solid #dc3545; }
    .today-col    { background:rgba(0,119,182,.06); }
    .prn-section  { border-left:3px solid #6c757d; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php';
?>
<!-- Header -->
<div class="mar-header p-3 mb-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
  <div>
    <span class="fs-5 fw-bold">💊 <?= xlt('Medication Administration Record') ?></span>
    <?php if ($patient): ?>
      <span class="ms-2 text-white-50">
        <?= htmlspecialchars($patient['fname'] . ' ' . $patient['lname']) ?>
        · <?= xlt('Room') ?> <?= htmlspecialchars($patient['room']) ?>
      </span>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 align-items-center">
    <!-- Window navigation -->
    <a href="?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&offset=<?= $data['offset'] - 5 ?>"
       class="btn btn-sm btn-outline-light">‹ <?= xlt('Prev 5d') ?></a>
    <span class="text-white-50 small">
      <?= htmlspecialchars($data['date_from']) ?> – <?= htmlspecialchars($data['date_to']) ?>
    </span>
    <?php if ($data['offset'] < 0): ?>
    <a href="?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&offset=<?= $data['offset'] + 5 ?>"
       class="btn btn-sm btn-outline-light"><?= xlt('Next 5d') ?> ›</a>
    <?php endif; ?>
    <a href="profile.php?episode_id=<?= $episodeId ?>" class="btn btn-sm btn-light ms-2">
      ← <?= xlt('Profile') ?>
    </a>
  </div>
</div>

<?php if ($data['flash']): ?>
<div class="alert alert-<?= str_contains($data['flash'], 'Error') ? 'danger' : 'success' ?> py-2">
  <?= htmlspecialchars($data['flash']) ?>
</div>
<?php endif; ?>

<!-- Legend -->
<div class="d-flex gap-3 mb-3 small align-items-center flex-wrap">
  <span class="badge bg-success">GIVEN</span>
  <span class="badge bg-warning text-dark">HELD</span>
  <span class="badge bg-danger">REFUSED</span>
  <span class="badge bg-secondary">OMITTED</span>
  <span class="badge bg-light text-dark border">PENDING</span>
  <span class="text-danger fw-semibold ms-2">⚠ = <?= xlt('High-Alert') ?></span>
</div>

<!-- ── Scheduled Medications Grid ─────────────────────────── -->
<?php if ($data['scheduled']): ?>
<div class="card mb-3">
  <div class="card-header fw-semibold small">
    🗓 <?= xlt('Scheduled Medications') ?>
    <span class="text-muted ms-2">(<?= count($data['scheduled']) ?> <?= xlt('orders') ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mar-table mb-0">
      <thead>
        <tr>
          <th style="min-width:160px"><?= xlt('Medication') ?></th>
          <th><?= xlt('Freq') ?></th>
          <?php foreach ($data['dates'] as $d): ?>
          <th class="text-center <?= $d['is_today'] ? 'today-col' : '' ?>">
            <?= htmlspecialchars($d['label']) ?>
            <?php if ($d['is_today']): ?>
            <span class="badge bg-primary ms-1" style="font-size:.6rem;">TODAY</span>
            <?php endif; ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($data['scheduled'] as $order):
          $isHighAlert = (bool)$order['is_high_alert'];
          $orderId = (int)$order['id'];
      ?>
      <tr class="<?= $isHighAlert ? 'high-alert' : '' ?>">
        <td>
          <?php if ($isHighAlert): ?>
          <span class="text-danger me-1" title="<?= xlt('High-alert medication') ?>">⚠</span>
          <?php endif; ?>
          <strong><?= htmlspecialchars($order['drug_name']) ?></strong>
          <br>
          <span class="text-muted" style="font-size:.75rem;">
            <?= htmlspecialchars($order['dose'] . ' ' . $order['unit'] . ' ' . $order['route']) ?>
          </span>
        </td>
        <td class="text-muted"><?= htmlspecialchars($order['frequency']) ?></td>
        <?php foreach ($data['dates'] as $d):
            $slots = $data['grid'][$orderId][$d['date']] ?? [];
        ?>
        <td class="text-center <?= $d['is_today'] ? 'today-col' : '' ?>">
          <?php if ($slots): ?>
            <?php foreach ($slots as $slot): ?>
            <button type="button"
                    class="btn btn-xs cell-slot mb-1 btn-outline-<?= $outcomeBadge[$slot['outcome']] ?? 'secondary' ?>"
                    style="font-size:.68rem;padding:.1rem .3rem;"
                    onclick="openAdminModal(<?= htmlspecialchars(json_encode($slot)) ?>)"
                    title="<?= htmlspecialchars($slot['outcome'] . ' · ' . substr($slot['scheduled_datetime'], 11, 5)) ?>">
              <?= substr($slot['scheduled_datetime'], 11, 5) ?>
              <?php if ($slot['outcome'] === 'GIVEN'): ?>✓
              <?php elseif ($slot['outcome'] === 'HELD'): ?>H
              <?php elseif ($slot['outcome'] === 'REFUSED'): ?>R
              <?php elseif ($slot['outcome'] === 'PENDING'): ?>…
              <?php endif; ?>
            </button>
            <?php endforeach; ?>
          <?php else: ?>
            <span class="text-muted" style="font-size:.7rem;">—</span>
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── PRN Medications ─────────────────────────────────────── -->
<?php if ($data['prn']): ?>
<div class="card prn-section mb-3">
  <div class="card-header fw-semibold small">
    🔔 <?= xlt('PRN Medications') ?>
    <span class="text-muted ms-1">(<?= xlt('as needed') ?>)</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover mar-table mb-0">
      <thead>
        <tr>
          <th><?= xlt('Medication') ?></th>
          <th><?= xlt('Indication') ?></th>
          <?php foreach ($data['dates'] as $d): ?>
          <th class="text-center <?= $d['is_today'] ? 'today-col' : '' ?>">
            <?= htmlspecialchars($d['label']) ?>
          </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($data['prn'] as $order):
          $orderId = (int)$order['id'];
          $isHighAlert = (bool)$order['is_high_alert'];
      ?>
      <tr class="<?= $isHighAlert ? 'high-alert' : '' ?>">
        <td>
          <?php if ($isHighAlert): ?>
          <span class="text-danger me-1">⚠</span>
          <?php endif; ?>
          <strong><?= htmlspecialchars($order['drug_name']) ?></strong>
          <br>
          <span class="text-muted" style="font-size:.75rem;">
            <?= htmlspecialchars($order['dose'] . ' ' . $order['unit'] . ' ' . $order['route']) ?>
          </span>
        </td>
        <td class="text-muted small">
          <?= htmlspecialchars(mb_strimwidth($order['instructions'] ?? '', 0, 50, '…')) ?>
        </td>
        <?php foreach ($data['dates'] as $d):
            $slots = $data['grid'][$orderId][$d['date']] ?? [];
        ?>
        <td class="text-center <?= $d['is_today'] ? 'today-col' : '' ?>">
          <?php foreach ($slots as $slot): ?>
          <button type="button"
                  class="btn btn-xs cell-slot mb-1 btn-outline-<?= $outcomeBadge[$slot['outcome']] ?? 'secondary' ?>"
                  style="font-size:.68rem;padding:.1rem .3rem;"
                  onclick="openAdminModal(<?= htmlspecialchars(json_encode($slot)) ?>)"
                  title="<?= htmlspecialchars($slot['outcome'] . ' ' . substr($slot['scheduled_datetime'], 11, 5)) ?>">
            <?= substr($slot['scheduled_datetime'], 11, 5) ?>
          </button>
          <?php endforeach; ?>
          <?php if ($d['is_today']): ?>
          <button type="button"
                  class="btn btn-xs btn-outline-success"
                  style="font-size:.68rem;padding:.1rem .3rem;"
                  onclick="openPrnModal(<?= $orderId ?>, '<?= htmlspecialchars($order['drug_name']) ?>', '<?= $d['date'] ?>')">
            + <?= xlt('Give') ?>
          </button>
          <?php endif; ?>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ── Administration Modal ───────────────────────────────── -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">💊 <?= xlt('Record Administration') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="adminForm">
        <?= CsrfUtils::collectCsrfToken() ?>
        <input type="hidden" name="action" value="administer">
        <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
        <input type="hidden" name="administration_id" id="adminId">
        <div class="modal-body">
          <div class="mb-2 fw-semibold" id="adminDrugLabel"></div>
          <div class="mb-2 text-muted small" id="adminSchedLabel"></div>

          <div class="mb-3">
            <label class="form-label form-label-sm fw-semibold"><?= xlt('Outcome') ?></label>
            <select class="form-select form-select-sm" name="outcome" id="outcomeSelect" required>
              <option value="GIVEN"><?= xlt('Given') ?></option>
              <option value="HELD"><?= xlt('Held') ?></option>
              <option value="REFUSED"><?= xlt('Patient Refused') ?></option>
              <option value="OMITTED"><?= xlt('Omitted') ?></option>
            </select>
          </div>

          <div id="givenFields">
            <div class="row g-2 mb-2">
              <div class="col-4">
                <label class="form-label form-label-sm"><?= xlt('Dose') ?></label>
                <input type="text" class="form-control form-control-sm" name="dose_given" id="doseGiven">
              </div>
              <div class="col-4">
                <label class="form-label form-label-sm"><?= xlt('Unit') ?></label>
                <input type="text" class="form-control form-control-sm" name="unit_given" id="unitGiven">
              </div>
              <div class="col-4">
                <label class="form-label form-label-sm"><?= xlt('Route') ?></label>
                <input type="text" class="form-control form-control-sm" name="route_given" id="routeGiven">
              </div>
            </div>
            <div class="mb-2">
              <label class="form-label form-label-sm"><?= xlt('Site (if injection)') ?></label>
              <input type="text" class="form-control form-control-sm" name="site" placeholder="LUQ, RLQ…">
            </div>
          </div>

          <div id="holdField" style="display:none">
            <div class="mb-2">
              <label class="form-label form-label-sm"><?= xlt('Hold Reason') ?></label>
              <select class="form-select form-select-sm" name="hold_reason">
                <?php foreach ($data['hold_reasons'] as $val => $label): ?>
                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label form-label-sm"><?= xlt('Note') ?></label>
            <textarea class="form-control form-control-sm" name="note" rows="2"
                      placeholder="<?= xlt('Optional clinical note…') ?>"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <?= xlt('Cancel') ?>
          </button>
          <button type="submit" class="btn btn-primary btn-sm">
            💾 <?= xlt('Save') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const bs5Modal = new bootstrap.Modal(document.getElementById('adminModal'));

function openAdminModal(slot) {
    document.getElementById('adminId').value      = slot.id;
    document.getElementById('adminDrugLabel').textContent =
        (slot.is_high_alert ? '⚠ ' : '') + slot.drug_name + ' · ' + slot.ordered_dose + ' ' + slot.ordered_unit;
    document.getElementById('adminSchedLabel').textContent =
        '<?= xlt('Scheduled') ?>: ' + slot.scheduled_datetime.substring(0,16) + ' · ' + slot.outcome;
    document.getElementById('outcomeSelect').value = slot.outcome === 'PENDING' ? 'GIVEN' : slot.outcome;
    document.getElementById('doseGiven').value  = slot.dose_given  || slot.ordered_dose  || '';
    document.getElementById('unitGiven').value  = slot.unit_given  || slot.ordered_unit  || '';
    document.getElementById('routeGiven').value = slot.route_given || slot.ordered_route || '';
    updateOutcomeFields();
    bs5Modal.show();
}

function openPrnModal(orderId, drugName, date) {
    // For PRN "Give" button — we need a fresh admin row
    // Route through existing modal with outcome pre-set to GIVEN
    // For now open the form with a signal to create via PRN flow
    // This would need a separate server action; show a simplified version
    alert('<?= xlt('PRN administration: please open the Full MAR to record.') ?>');
}

function updateOutcomeFields() {
    const outcome = document.getElementById('outcomeSelect').value;
    document.getElementById('givenFields').style.display = outcome === 'GIVEN' ? 'block' : 'none';
    document.getElementById('holdField').style.display   = outcome === 'HELD'  ? 'block' : 'none';
}

document.getElementById('outcomeSelect').addEventListener('change', updateOutcomeFields);
</script>
</body>
</html>

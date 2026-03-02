<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Controller\MarController;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Service\AllergyService;
use OpenEMR\Modules\Institutional\Submodule\Mar\Service\MarService;

if (!$manifest->featureEnabled('mar')) {
    die(xlt('MAR is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$controller = new MarController(
    new MarOrderRepository(),
    new MarAdministrationRepository(),
    new EpisodeRepository(),
    new AllergyService()
);

$data = $controller->handle($facilityId, $userId);

if (is_string($data) && $data !== '') {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data['error']));
    }
}

$href      = institutional_bootstrap5_href($manifest);
$view      = (string)($data['view'] ?? 'facility');
$episode   = $data['episode'] ?? [];
$isPrint   = (bool)($data['print'] ?? false);
$allergyWarnings = $data['allergy_warnings'] ?? [];
$holdReasons     = $data['hold_reasons'] ?? MarService::HOLD_REASONS;

function mar_badge(string $outcome): string
{
    return match ($outcome) {
        'GIVEN'         => 'text-bg-success',
        'HELD'          => 'text-bg-warning',
        'REFUSED'       => 'text-bg-danger',
        'NOT_AVAILABLE' => 'text-bg-secondary',
        'MISSED'        => 'text-bg-dark',
        default         => 'text-bg-light border',
    };
}

/** Render the shared administration form fields (used for both Record and Amend). */
function mar_admin_fields(array $order, array $a, string $action, array $holdReasons): void
{
    $isGiven = in_array($a['outcome'] ?? 'PENDING', ['GIVEN'], true);
    ?>
    <input type="hidden" name="action"    value="<?= htmlspecialchars($action) ?>">
    <input type="hidden" name="admin_id"  value="<?= (int)($a['id'] ?? 0) ?>">

    <div class="row g-2 align-items-end">

      <div class="col-auto">
        <label class="form-label"><?= xlt('Outcome') ?></label>
        <select name="outcome" class="form-select form-select-sm mar-outcome-sel" style="width:140px"
                onchange="marToggleHoldReason(this)">
          <?php foreach (['GIVEN','HELD','REFUSED','NOT_AVAILABLE','MISSED'] as $oc): ?>
            <option value="<?= $oc ?>" <?= ($a['outcome'] ?? 'GIVEN') === $oc ? 'selected' : '' ?>>
                <?= htmlspecialchars($oc) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-auto mar-hold-reason-wrap" style="display:none;">
        <label class="form-label"><?= xlt('Hold Reason') ?></label>
        <select name="hold_reason" class="form-select form-select-sm" style="width:180px">
          <?php foreach ($holdReasons as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>"
                <?= ($a['hold_reason'] ?? '') === $code ? 'selected' : '' ?>>
                <?= htmlspecialchars(xlt($label)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Given At') ?></label>
        <input type="datetime-local" name="administered_datetime" class="form-control form-control-sm"
               style="width:165px"
               value="<?= htmlspecialchars(
                   !empty($a['administered_datetime'])
                       ? date('Y-m-d\TH:i', strtotime((string)$a['administered_datetime']))
                       : date('Y-m-d\TH:i')
                      ) ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Dose') ?></label>
        <input name="dose_given" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['dose_given'] ?? $order['dose'])) ?>"
               style="width:65px" placeholder="<?= xla('Dose') ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Unit') ?></label>
        <input name="unit_given" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['unit_given'] ?? $order['unit'])) ?>"
               style="width:55px" placeholder="<?= xla('Unit') ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Route') ?></label>
        <input name="route_given" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['route_given'] ?? $order['route'])) ?>"
               style="width:55px" placeholder="<?= xla('Route') ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Site') ?></label>
        <input name="site" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['site'] ?? '')) ?>"
               style="width:70px" placeholder="<?= xla('LDA…') ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Lot #') ?></label>
        <input name="lot_number" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['lot_number'] ?? '')) ?>"
               style="width:90px" placeholder="<?= xla('Lot') ?>">
      </div>

      <div class="col-auto">
        <label class="form-label"><?= xlt('Note') ?></label>
        <input name="note" class="form-control form-control-sm"
               value="<?= htmlspecialchars((string)($a['note'] ?? '')) ?>"
               style="width:140px">
      </div>

      <div class="col-auto d-flex align-items-end">
        <button class="btn btn-success btn-sm"><?= xlt('Save') ?></button>
      </div>

    </div>
    <?php
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('MAR') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .mar-grid td, .mar-grid th { white-space: nowrap; font-size: .82rem; }
    .high-alert { background-color: #fff3cd !important; }
    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
    .mar-hold-reason-wrap { display: none; }

    @media print {
      .no-print { display: none !important; }
      body { background: white !important; font-size: 10pt; }
      .mar-grid td, .mar-grid th { font-size: 8.5pt; }
      h1 { font-size: 14pt; }
    }
  </style>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
    <h1 class="h4 mb-0">
      <?= xlt('Medication Administration Record') ?>
      <?php if ($view === 'episode' && !empty($episode)): ?>
        <small class="text-muted fs-6 ms-2">
            <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
          &mdash; <?= xlt('PID') ?> <?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>
        </small>
      <?php endif; ?>
    </h1>
    <div class="d-flex gap-2">
      <?php if ($view === 'episode'): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)($data['episode_id'] ?? 0) ?>&print=1"
           target="_blank"><?= xlt('Print MAR') ?></a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary"
         href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Facility View') ?></a>
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
    </div>
  </div>

  <?php if ($view === 'facility'): ?>
  <!-- ───────────────────── FACILITY OVERDUE VIEW ───────────────────────── -->
  <div class="card shadow-sm">
    <div class="card-header"><?= xlt('Overdue / Pending Medications — All Episodes') ?></div>
        <?php if (empty($data['overdue'])): ?>
      <div class="card-body text-success"><?= xlt('No overdue pending medications.') ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 mar-grid">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Episode') ?></th>
              <th><?= xlt('Drug') ?></th>
              <th><?= xlt('Scheduled') ?></th>
              <th><?= xlt('High Alert') ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($data['overdue'] as $r):
                $isHA = (bool)($r['is_high_alert'] ?? false); ?>
            <tr class="<?= $isHA ? 'high-alert' : '' ?>">
              <td>
                <a href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">
                  #<?= (int)$r['episode_id'] ?>
                </a>
              </td>
              <td><?= htmlspecialchars((string)$r['drug_name']) ?></td>
              <td><?= htmlspecialchars((string)($r['scheduled_datetime'] ?? '')) ?></td>
              <td>
                <?php if ($isHA): ?>
                  <span class="badge text-bg-warning">⚠ <?= xlt('HIGH-ALERT') ?></span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn-sm btn-outline-primary"
                   href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">
                  <?= xlt('Open MAR') ?>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php else: ?>
  <!-- ───────────────────── EPISODE MAR VIEW ────────────────────────────── -->

      <?php if ($isPrint): ?>
  <!-- Print header -->
  <div class="mb-3 border-bottom pb-2">
    <strong style="font-size:14pt;"><?= xlt('Medication Administration Record') ?></strong>
    <span style="float:right; font-size:9pt;"><?= htmlspecialchars(date('Y-m-d H:i')) ?></span>
    <div class="small text-muted">
            <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
      &mdash; <?= xlt('PID') ?> <?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>
    </div>
  </div>
  <?php endif; ?>

      <?php if (!empty($allergyWarnings)): ?>
  <!-- Allergy warning banners -->
            <?php foreach ($allergyWarnings as $w): ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-2" role="alert">
      <span style="font-size:1.4rem;">⚠️</span>
      <div>
        <strong><?= xlt('Allergy Match:') ?></strong>
                <?= xlt('Order') ?> <strong><?= htmlspecialchars((string)$w['drug']) ?></strong>
                <?= xlt('matches documented allergen') ?>
        <strong><?= htmlspecialchars((string)$w['allergen']) ?></strong>
                <?php if (!empty($w['reaction'])): ?>
          &mdash; <?= xlt('Reaction:') ?> <?= htmlspecialchars((string)$w['reaction']) ?>
        <?php endif; ?>
                <?php if (!empty($w['severity'])): ?>
          <span class="badge text-bg-danger ms-1"><?= htmlspecialchars((string)$w['severity']) ?></span>
        <?php endif; ?>
        <span class="ms-2 fw-semibold text-danger"><?= xlt('Verify before administering.') ?></span>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <!-- Place Order form -->
      <?php if (!$isPrint): ?>
  <div class="card shadow-sm mb-3 no-print">
    <div class="card-body p-0">
      <details>
        <summary class="px-3 py-2">
          <span class="btn btn-sm btn-outline-primary">+ <?= xlt('Place Medication Order') ?></span>
        </summary>
        <div class="p-3 bg-light border-top">
          <form method="post" class="row g-2" autocomplete="off">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
            <input type="hidden" name="action"          value="place_order">
            <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
            <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
            <input type="hidden" name="pid"             value="<?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>">

            <div class="col-md-3">
              <label class="form-label"><?= xlt('Drug Name') ?> *</label>
              <input name="drug_name" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-1">
              <label class="form-label"><?= xlt('Dose') ?></label>
              <input name="dose" class="form-control form-control-sm" placeholder="5">
            </div>
            <div class="col-md-1">
              <label class="form-label"><?= xlt('Unit') ?></label>
              <input name="unit" class="form-control form-control-sm" placeholder="mg">
            </div>
            <div class="col-md-1">
              <label class="form-label"><?= xlt('Route') ?></label>
              <input name="route" class="form-control form-control-sm" placeholder="IV">
            </div>
            <div class="col-md-2">
              <label class="form-label"><?= xlt('Frequency') ?></label>
              <input name="frequency" class="form-control form-control-sm" placeholder="Q6H">
              <div class="form-text"><?= xlt('Use PRN for as-needed.') ?></div>
            </div>
            <div class="col-md-3">
              <label class="form-label"><?= xlt('Instructions') ?></label>
              <input name="instructions" class="form-control form-control-sm"
                     placeholder="<?= xla('Hold if HR < 60, dilute in 100 mL NS') ?>">
            </div>
            <div class="col-12">
              <div class="form-check form-check-inline">
                <input class="form-check-input" type="checkbox" name="is_high_alert" id="pha" value="1">
                <label class="form-check-label text-warning fw-semibold" for="pha">
                  ⚠ <?= xlt('High-Alert Medication') ?>
                </label>
              </div>
              <div class="form-text d-inline ms-3"><?= xlt('Check for drugs not auto-detected (trade names, compounds).') ?></div>
            </div>
            <div class="col-12">
              <button class="btn btn-primary btn-sm"><?= xlt('Place Order') ?></button>
            </div>
          </form>
        </div>
      </details>
    </div>
  </div>
  <?php endif; ?>

      <?php foreach (($data['grid'] ?? []) as $order):
            $isPrn    = (bool)$order['is_prn'];
            $orderId  = (int)$order['id'];
            ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div>
        <strong><?= htmlspecialchars((string)$order['drug_name']) ?></strong>
        <span class="text-muted ms-2">
            <?= htmlspecialchars((string)$order['dose']) ?>
            <?= htmlspecialchars((string)$order['unit']) ?>
          &bull;
            <?= htmlspecialchars((string)$order['route']) ?>
            <?php if (!$isPrn): ?>
            &bull; <?= htmlspecialchars((string)$order['frequency']) ?>
          <?php else: ?>
            <span class="badge text-bg-info ms-1">PRN</span>
          <?php endif; ?>
        </span>
            <?php if (!empty($order['instructions'])): ?>
          <small class="text-muted ms-2 fst-italic"><?= htmlspecialchars((string)$order['instructions']) ?></small>
        <?php endif; ?>
      </div>

            <?php if (!$isPrint): ?>
      <div class="d-flex gap-2 no-print">

                <?php if (!$isPrn): ?>
        <!-- Extend window -->
        <details class="d-inline">
          <summary><span class="btn btn-sm btn-outline-secondary"><?= xlt('Extend') ?></span></summary>
          <div class="position-absolute bg-white border rounded shadow p-2" style="z-index:10; min-width:220px;">
            <form method="post" class="d-flex gap-2 align-items-end">
              <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
              <input type="hidden" name="action"          value="extend_window">
              <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
              <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
              <input type="hidden" name="order_id"        value="<?= $orderId ?>">
              <div>
                <label class="form-label mb-0 small"><?= xlt('Add hours') ?></label>
                <select name="extend_hours" class="form-select form-select-sm" style="width:80px">
                  <option value="8">8h</option>
                  <option value="12">12h</option>
                  <option value="24" selected>24h</option>
                  <option value="48">48h</option>
                  <option value="72">72h</option>
                </select>
              </div>
              <button class="btn btn-sm btn-primary"><?= xlt('Go') ?></button>
            </form>
          </div>
        </details>
        <?php endif; ?>

        <!-- Discontinue -->
        <form method="post" class="d-inline">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
          <input type="hidden" name="action"          value="discontinue_order">
          <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
          <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
          <input type="hidden" name="order_id"        value="<?= $orderId ?>">
          <button class="btn btn-sm btn-outline-danger"
                  onclick="return confirm('<?= xlt('Discontinue this medication?') ?>')">
                <?= xlt('D/C') ?>
          </button>
        </form>

      </div>
      <?php endif; ?>
    </div>

            <?php if ($isPrn && !$isPrint): ?>
    <!-- PRN Give form -->
    <div class="card-body p-0 border-bottom no-print">
      <details>
        <summary class="px-3 py-2">
          <span class="btn btn-sm btn-outline-success"><?= xlt('+ Give PRN Dose') ?></span>
        </summary>
        <div class="p-3 bg-light">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
            <input type="hidden" name="action"          value="give_prn">
            <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
            <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
            <input type="hidden" name="pid"             value="<?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>">
            <input type="hidden" name="order_id"        value="<?= $orderId ?>">
            <input type="hidden" name="drug_name"       value="<?= htmlspecialchars((string)$order['drug_name']) ?>">

            <div class="col-auto">
              <label class="form-label"><?= xlt('Given At') ?></label>
              <input type="datetime-local" name="administered_datetime" class="form-control form-control-sm"
                     style="width:165px" value="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Dose') ?></label>
              <input name="dose_given" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$order['dose']) ?>" style="width:70px">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Unit') ?></label>
              <input name="unit_given" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$order['unit']) ?>" style="width:55px">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Route') ?></label>
              <input name="route_given" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$order['route']) ?>" style="width:55px">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Site') ?></label>
              <input name="site" class="form-control form-control-sm" style="width:70px" placeholder="LDA…">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Lot #') ?></label>
              <input name="lot_number" class="form-control form-control-sm" style="width:90px">
            </div>
            <div class="col-auto">
              <label class="form-label"><?= xlt('Note') ?></label>
              <input name="note" class="form-control form-control-sm" style="width:140px">
            </div>
            <div class="col-auto d-flex align-items-end">
              <button class="btn btn-success btn-sm"><?= xlt('Record PRN') ?></button>
            </div>
          </form>
        </div>
      </details>
    </div>
    <?php endif; ?>

    <!-- Administration rows -->
            <?php if (!empty($order['admins'])): ?>
    <div class="table-responsive">
      <table class="table table-sm mar-grid align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Scheduled') ?></th>
            <th><?= xlt('Given At') ?></th>
            <th><?= xlt('Outcome') ?></th>
            <th><?= xlt('Hold Reason') ?></th>
            <th><?= xlt('Dose') ?></th>
            <th><?= xlt('Site') ?></th>
            <th><?= xlt('Lot #') ?></th>
            <th><?= xlt('Nurse') ?></th>
            <th><?= xlt('Note') ?></th>
                <?php if (!$isPrint): ?><th class="no-print"></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
                <?php foreach ($order['admins'] as $a):
                    $isHA    = (bool)($a['is_high_alert'] ?? false);
                    $outcome = (string)($a['outcome'] ?? 'PENDING');
                    ?>
          <tr class="<?= $isHA ? 'high-alert' : '' ?>">
            <td>
                    <?= htmlspecialchars((string)($a['scheduled_datetime'] ?? xlt('PRN'))) ?>
                    <?php if ($isHA): ?>
                <span class="badge text-bg-warning ms-1" style="font-size:.65rem;"><?= xlt('HA') ?></span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($a['administered_datetime'] ?? '—')) ?></td>
            <td>
              <span class="badge <?= mar_badge($outcome) ?>">
                    <?= htmlspecialchars($outcome) ?>
              </span>
            </td>
            <td>
                    <?php $hr = (string)($a['hold_reason'] ?? '');
                    echo $hr ? htmlspecialchars($holdReasons[$hr] ?? $hr) : '—'; ?>
            </td>
            <td>
                    <?= htmlspecialchars((string)($a['dose_given'] ?? '')) ?>
                    <?= htmlspecialchars((string)($a['unit_given'] ?? '')) ?>
            </td>
            <td><?= htmlspecialchars((string)($a['site'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($a['lot_number'] ?? '')) ?></td>
            <td><?= htmlspecialchars(trim((string)($a['nurse_name'] ?? (string)($a['administered_by_user_id'] ?? '')))) ?></td>
            <td><?= htmlspecialchars((string)($a['note'] ?? '')) ?></td>

                    <?php if (!$isPrint): ?>
            <td class="no-print">
                        <?php if ($outcome === 'PENDING'): ?>
              <!-- Record (PENDING only) -->
              <details>
                <summary><span class="btn btn-sm btn-outline-secondary py-0 px-1"><?= xlt('Record') ?></span></summary>
                <div class="p-2 bg-light border rounded mt-1" style="min-width:600px;">
                  <form method="post">
                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                    <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
                    <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                            <?php mar_admin_fields($order, $a, 'record_admin', $holdReasons); ?>
                  </form>
                </div>
              </details>
              <?php else: ?>
              <!-- Amend (already documented) -->
              <details>
                <summary><span class="btn btn-sm btn-outline-secondary py-0 px-1 text-muted"><?= xlt('Amend') ?></span></summary>
                <div class="p-2 bg-light border rounded mt-1" style="min-width:600px;">
                  <div class="alert alert-warning py-1 small mb-2">
                    ⚠ <?= xlt('Amending a completed record. The original will be preserved in the note field.') ?>
                  </div>
                  <form method="post">
                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                    <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
                    <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                    <?php mar_admin_fields($order, $a, 'amend_admin', $holdReasons); ?>
                  </form>
                </div>
              </details>
              <?php endif; ?>
            </td>
            <?php endif; ?>

          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
    <div class="card-body text-muted small">
        <?= $isPrn ? xlt('No PRN doses recorded yet.') : xlt('No scheduled slots yet — use Extend to generate slots.') ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

      <?php if (empty($data['grid'])): ?>
    <div class="alert alert-info"><?= xlt('No active medication orders for this episode.') ?></div>
  <?php endif; ?>

      <?php if ($isPrint): ?>
  <div class="small text-muted mt-3">
            <?= xlt('Printed') ?>: <?= htmlspecialchars(date('Y-m-d H:i')) ?>
    &mdash; <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
  </div>
  <?php endif; ?>

  <?php endif; // episode view ?>

</div>

<?php if ($href): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script>
/**
 * Show / hide the Hold Reason dropdown when outcome changes.
 * Applies to any outcome select with class mar-outcome-sel.
 */
function marToggleHoldReason(sel) {
    var wrap = sel.closest('form').querySelector('.mar-hold-reason-wrap');
    if (wrap) {
        wrap.style.display = sel.value === 'HELD' ? '' : 'none';
    }
}

// On page load, show hold reason if outcome is already HELD (Amend case)
document.querySelectorAll('.mar-outcome-sel').forEach(function(sel) {
    marToggleHoldReason(sel);
});
</script>
</body>
</html>

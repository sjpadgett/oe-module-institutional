<?php

/**
 * public/al/al_mar.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

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

use OpenEMR\Modules\Institutional\Core\Repository\UserRepository;

$controller   = new AlMarController();
$data         = $controller->handle($episodeId, $pid, $facilityId, $userId);
$patient      = $data['patient'];
$marVocab     = $data['mar_vocab'] ?? ['units' => [], 'routes' => [], 'frequencies' => []];
$workspace    = $data['workspace'] ?? [];
$_alMarStaff  = (new UserRepository())->fetchAll();

/** @param list<array{value:string,label:string}> $options */
function al_mar_datalist(string $id, array $options): void
{
    ?>
    <datalist id="<?= htmlspecialchars($id) ?>">
        <?php foreach ($options as $opt): ?>
            <option value="<?= htmlspecialchars((string)($opt['value'] ?? '')) ?>" label="<?= htmlspecialchars((string)($opt['label'] ?? ($opt['value'] ?? ''))) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <?php
}

/** @param array<string,array<int,array<string,mixed>>> $workspace */
function al_mar_workspace(array $workspace, int $episodeId, int $facilityId): void
{
    $sections = [
        'due_now' => ['title' => xlt('Due Now'), 'badge' => 'bg-primary'],
        'due_soon' => ['title' => xlt('Due Soon'), 'badge' => 'bg-info'],
        'overdue' => ['title' => xlt('Overdue'), 'badge' => 'bg-danger'],
        'awaiting_cosign' => ['title' => xlt('Awaiting Co-Sign'), 'badge' => 'bg-warning text-dark'],
        'recent_prn' => ['title' => xlt('Recent PRN / Recheck'), 'badge' => 'bg-secondary'],
        'exception_followup' => ['title' => xlt('Exception Follow-Up'), 'badge' => 'bg-dark'],
    ];
    ?>
    <div class="card mb-3">
      <div class="card-header fw-semibold small d-flex justify-content-between">
        <span>🩺 <?= xlt('Med-Pass Workspace') ?></span>
        <span class="text-muted"><?= xlt('Prioritized shift queue') ?></span>
      </div>
      <div class="card-body py-2">
        <div class="row g-3">
          <?php foreach ($sections as $key => $meta): $rows = $workspace[$key] ?? []; ?>
            <div class="col-12 col-xl-6">
              <div class="border rounded h-100">
                <div class="px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                  <span class="fw-semibold"><?= htmlspecialchars($meta['title']) ?></span>
                  <span class="badge <?= htmlspecialchars($meta['badge']) ?>"><?= count($rows) ?></span>
                </div>
                <?php if (empty($rows)): ?>
                  <div class="p-3 text-muted small"><?= xlt('Nothing queued.') ?></div>
                <?php else: ?>
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead class="table-light"><tr><th><?= xlt('Medication') ?></th><th><?= xlt('Time') ?></th><th></th></tr></thead>
                      <tbody>
                        <?php foreach ($rows as $row): $ts = (string)($row['scheduled_datetime'] ?? $row['administered_datetime'] ?? ''); ?>
                          <tr>
                            <td>
                              <div class="fw-semibold"><?= htmlspecialchars((string)($row['drug_name'] ?? '')) ?></div>
                              <div class="small text-muted"><?= htmlspecialchars(trim((string)($row['ordered_dose'] ?? '') . ' ' . (string)($row['ordered_unit'] ?? '') . ' ' . (string)($row['ordered_route'] ?? ''))) ?></div>
                            </td>
                            <td class="small"><?= $ts !== '' ? htmlspecialchars(date('n/j g:i a', strtotime($ts) ?: time())) : '—' ?></td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="../mar.php?facility_id=<?= $facilityId ?>&amp;episode_id=<?= $episodeId ?>#mar-order-<?= (int)($row['mar_order_id'] ?? 0) ?>"><?= xlt('Open MAR') ?></a></td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php
}

$pageTitle = xlt('AL Medication Administration Record');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

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
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
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
<?php al_mar_datalist('al-mar-unit-options', (array)($marVocab['units'] ?? [])); ?>
<?php al_mar_datalist('al-mar-route-options', (array)($marVocab['routes'] ?? [])); ?>
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
    <a href="../mar.php?facility_id=<?= $facilityId ?>&episode_id=<?= $episodeId ?>"
       class="btn btn-sm btn-warning ms-2" title="<?= xlt('Place orders, discontinue, extend window') ?>">
      📋 <?= xlt('Full MAR') ?>
    </a>
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

<?php al_mar_workspace((array)$workspace, $episodeId, $facilityId); ?>

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
            <?php
            $_alSlotIsHA  = (bool)($slot['is_high_alert'] ?? false);
            $_alNeedsCosign = $_alSlotIsHA
                              && ($slot['outcome'] ?? '') === 'GIVEN'
                              && empty($slot['co_sign_user_id']);
            $_alHasCosign   = $_alSlotIsHA
                              && ($slot['outcome'] ?? '') === 'GIVEN'
                              && !empty($slot['co_sign_user_id']);
            ?>
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
            <?php if ($_alNeedsCosign): ?>
            <button type="button" class="btn btn-xs mb-1 btn-warning d-block"
                    style="font-size:.6rem;padding:.1rem .25rem;"
                    onclick="openCoSignModal(<?= (int)($slot['id'] ?? 0) ?>)"
                    title="<?= xlt('Co-signature required') ?>">✍ <?= xlt('Co-Sign') ?></button>
            <?php elseif ($_alHasCosign): ?>
            <span class="d-block text-success" style="font-size:.6rem;"
                  title="<?= htmlspecialchars(xlt('Co-signed by') . ': ' . ($slot['co_sign_name'] ?? '')) ?>">
              ✓✓
            </span>
            <?php endif; ?>
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
                <?php foreach ($slots as $slot):
                    $_alPrnIsHA      = (bool)($slot['is_high_alert'] ?? false);
                    $_alPrnNeedsCosign = $_alPrnIsHA
                                        && ($slot['outcome'] ?? '') === 'GIVEN'
                                        && empty($slot['co_sign_user_id']);
                    $_alPrnHasCosign   = $_alPrnIsHA
                                        && ($slot['outcome'] ?? '') === 'GIVEN'
                                        && !empty($slot['co_sign_user_id']);
                ?>
          <button type="button"
                  class="btn btn-xs cell-slot mb-1 btn-outline-<?= $outcomeBadge[$slot['outcome']] ?? 'secondary' ?>"
                  style="font-size:.68rem;padding:.1rem .3rem;"
                  onclick="openAdminModal(<?= htmlspecialchars(json_encode($slot)) ?>)"
                  title="<?= htmlspecialchars($slot['outcome'] . ' ' . substr($slot['scheduled_datetime'], 11, 5)) ?>">
                    <?= substr($slot['scheduled_datetime'], 11, 5) ?>
          </button>
          <?php if ($_alPrnNeedsCosign): ?>
          <button type="button" class="btn btn-xs mb-1 btn-warning d-block"
                  style="font-size:.6rem;padding:.1rem .25rem;"
                  onclick="openCoSignModal(<?= (int)($slot['id'] ?? 0) ?>)"
                  title="<?= xlt('Co-signature required') ?>">✍ <?= xlt('Co-Sign') ?></button>
          <?php elseif ($_alPrnHasCosign): ?>
          <span class="d-block text-success" style="font-size:.6rem;"
                title="<?= htmlspecialchars(xlt('Co-signed by') . ': ' . ($slot['co_sign_name'] ?? '')) ?>">
            ✓✓
          </span>
          <?php endif; ?>
          <?php endforeach; ?>
                <?php if ($d['is_today']): ?>
          <button type="button"
                  class="btn btn-xs btn-outline-success"
                  style="font-size:.68rem;padding:.1rem .3rem;"
                  onclick="openPrnModal(<?= htmlspecialchars(json_encode(['order_id' => $orderId, 'drug_name' => $order['drug_name'], 'dose' => $order['dose'] ?? '', 'unit' => $order['unit'] ?? '', 'route' => $order['route'] ?? '', 'is_high_alert' => (bool)($order['is_high_alert'] ?? false)])) ?>)">
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

<!-- ── Co-Sign Modal ───────────────────────────────────────── -->
<div class="modal fade" id="coSignModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title">✍ <?= xlt('Co-Sign Administration') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="coSignForm"
            action="al_mar.php?episode_id=<?= $episodeId ?>&amp;pid=<?= $pid ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="action" value="co_sign">
        <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
        <input type="hidden" name="administration_id" id="coSignAdminId">
        <div class="modal-body">
          <p class="small text-muted mb-3">
            <?= xlt('Second-nurse co-signature required for this high-alert administration.') ?>
          </p>
          <label class="form-label form-label-sm fw-semibold">
            <?= xlt('Co-Signing Nurse') ?>
          </label>
          <select name="co_sign_user_id" class="form-select form-select-sm" required>
            <option value=""><?= xlt('— Select —') ?></option>
            <?php foreach ($_alMarStaff as $csu): ?>
            <option value="<?= (int)$csu['id'] ?>">
              <?= htmlspecialchars((string)($csu['name'] ?? '')) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <?= xlt('Cancel') ?>
          </button>
          <button type="submit" class="btn btn-warning btn-sm">
            ✓ <?= xlt('Co-Sign') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── PRN Give Modal ──────────────────────────────────────── -->
<div class="modal fade" id="prnModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">🔔 <?= xlt('Record PRN Dose') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="prnForm" action="al_mar.php?episode_id=<?= $episodeId ?>&amp;pid=<?= $pid ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="action"     value="give_prn">
        <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
        <input type="hidden" name="pid"        value="<?= $pid ?>">
        <input type="hidden" name="order_id"   id="prnOrderId">
        <input type="hidden" name="drug_name"  id="prnDrugName">
        <input type="hidden" name="is_high_alert" id="prnIsHighAlert">
        <div class="modal-body">
          <div class="mb-2 fw-semibold text-primary" id="prnDrugLabel"></div>
          <div class="alert alert-info py-1 small mb-3">
            <?= xlt('PRN — as needed. This will create a new GIVEN administration entry.') ?>
          </div>
          <div class="mb-3">
            <label class="form-label form-label-sm fw-semibold"><?= xlt('Given At') ?></label>
            <input type="datetime-local" class="form-control form-control-sm"
                   name="administered_datetime" id="prnAdminDt" style="width:185px">
          </div>
          <div class="row g-2 mb-2">
            <div class="col-4">
              <label class="form-label form-label-sm"><?= xlt('Dose') ?></label>
              <input type="text" class="form-control form-control-sm" name="dose_given" id="prnDoseGiven">
            </div>
            <div class="col-4">
              <label class="form-label form-label-sm"><?= xlt('Unit') ?></label>
              <input type="text" class="form-control form-control-sm" name="unit_given" id="prnUnitGiven" list="al-mar-unit-options">
            </div>
            <div class="col-4">
              <label class="form-label form-label-sm"><?= xlt('Route') ?></label>
              <input type="text" class="form-control form-control-sm" name="route_given" id="prnRouteGiven" list="al-mar-route-options">
            </div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Site (if injection)') ?></label>
              <input type="text" class="form-control form-control-sm" name="site" placeholder="LUQ, RLQ…">
            </div>
            <div class="col-6">
              <label class="form-label form-label-sm"><?= xlt('Lot #') ?></label>
              <input type="text" class="form-control form-control-sm" name="lot_number">
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label form-label-sm"><?= xlt('Note / Indication') ?></label>
            <textarea class="form-control form-control-sm" name="note" rows="2"
                      placeholder="<?= xlt('Reason given, expected response…') ?>"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
            <?= xlt('Cancel') ?>
          </button>
          <button type="submit" class="btn btn-success btn-sm">
            ✓ <?= xlt('Record PRN Dose') ?>
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Administration Modal ───────────────────────────────── -->
<div class="modal fade" id="adminModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title">💊 <?= xlt('Record Administration') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="adminForm" action="al_mar.php?episode_id=<?= $episodeId ?>&amp;pid=<?= $pid ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="action" value="administer" id="adminAction">
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
              <option value="OMITTED"><?= xlt('Omitted / Missed') ?></option>
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
                <input type="text" class="form-control form-control-sm" name="unit_given" id="unitGiven" list="al-mar-unit-options">
              </div>
              <div class="col-4">
                <label class="form-label form-label-sm"><?= xlt('Route') ?></label>
                <input type="text" class="form-control form-control-sm" name="route_given" id="routeGiven" list="al-mar-route-options">
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

          <div id="exceptionFollowupField" style="display:none" class="mb-3 p-2 border rounded bg-light">
            <div class="small fw-semibold mb-1"><?= xlt('Exception follow-up') ?></div>
            <div class="small text-muted mb-2" id="exceptionHint"><?= xlt('Document downstream actions for held, refused, or omitted doses.') ?></div>
            <div class="row g-2 align-items-end">
              <div class="col-12 col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="providerNotified" name="provider_notified">
                  <label class="form-check-label" for="providerNotified"><?= xlt('Provider notified') ?></label>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="pharmacyFollowup" name="pharmacy_follow_up">
                  <label class="form-check-label" for="pharmacyFollowup"><?= xlt('Pharmacy follow-up') ?></label>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" value="1" id="retryLater" name="retry_later">
                  <label class="form-check-label" for="retryLater"><?= xlt('Retry later') ?></label>
                </div>
              </div>
              <div class="col-12 col-md-6">
                <select class="form-select form-select-sm" name="retry_minutes" id="retryMinutes" disabled>
                  <option value="30">30 <?= xlt('min') ?></option>
                  <option value="60">60 <?= xlt('min') ?></option>
                  <option value="120">120 <?= xlt('min') ?></option>
                  <option value="240">240 <?= xlt('min') ?></option>
                </select>
              </div>
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label form-label-sm"><?= xlt('Note') ?></label>
            <textarea class="form-control form-control-sm" name="note" rows="2"
                      placeholder="<?= xlt('Optional clinical note…') ?>"></textarea>
          </div>

          <!-- High-alert waste/witness section — shown by JS when is_high_alert=true -->
          <div id="highAlertAdminSection" style="display:none;" class="mt-3 p-2 border rounded"
               style="border-color:#ffc107!important;">
            <div class="mb-2">
              <span class="badge text-bg-warning">⚠ <?= xlt('Controlled / High-Alert') ?></span>
              <span class="small text-muted ms-1">
                <?= xlt('Complete waste documentation if any drug was not fully administered.') ?>
              </span>
            </div>
            <div class="row g-2">
              <div class="col-12">
                <label class="form-label form-label-sm"><?= xlt('Witness') ?></label>
                <select name="witness_user_id" class="form-select form-select-sm">
                  <option value=""><?= xlt('— None —') ?></option>
                  <?php foreach ($_alMarStaff as $wu): ?>
                  <option value="<?= (int)$wu['id'] ?>">
                    <?= htmlspecialchars((string)($wu['name'] ?? '')) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label form-label-sm"><?= xlt('Waste Amount') ?></label>
                <input type="text" class="form-control form-control-sm" name="waste_amount"
                       placeholder="<?= xla('e.g. 2') ?>">
              </div>
              <div class="col-6">
                <label class="form-label form-label-sm"><?= xlt('Waste Unit') ?></label>
                <input type="text" class="form-control form-control-sm" list="al-mar-unit-options" name="waste_unit"
                       placeholder="<?= xla('mg / ml') ?>">
              </div>
            </div>
          </div>

          <!-- Hidden flag read by JS to show/hide high-alert section -->
          <input type="hidden" id="adminIsHighAlert" name="is_high_alert" value="0">
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

<?= institutional_bootstrap5_js_tag() ?>
<script>
function openAdminModal(slot) {
    const isAmend = slot.outcome !== 'PENDING';

    document.getElementById('adminAction').value = isAmend ? 'amend_admin' : 'administer';
    document.querySelector('#adminModal .modal-title').textContent =
        isAmend ? '<?= xlt('Amend Administration') ?>' : '💊 <?= xlt('Record Administration') ?>';

    document.getElementById('adminId').value = slot.id;
    document.getElementById('adminDrugLabel').textContent =
        (slot.is_high_alert ? '⚠ ' : '') + slot.drug_name + ' · ' + slot.ordered_dose + ' ' + slot.ordered_unit;

    const schedEl = document.getElementById('adminSchedLabel');
    schedEl.textContent = (isAmend ? '<?= xlt('Amending') ?>: ' : '<?= xlt('Scheduled') ?>: ')
                        + slot.scheduled_datetime.substring(0, 16) + ' · ' + slot.outcome;
    if (isAmend) {
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-warning ms-1';
        badge.textContent = '<?= xlt('original preserved in note') ?>';
        schedEl.appendChild(badge);
    }

    document.getElementById('outcomeSelect').value = slot.outcome === 'PENDING' ? 'GIVEN' : slot.outcome;
    document.getElementById('doseGiven').value  = slot.dose_given  || slot.ordered_dose  || '';
    document.getElementById('unitGiven').value  = slot.unit_given  || slot.ordered_unit  || '';
    document.getElementById('routeGiven').value = slot.route_given || slot.ordered_route || '';

    // High-alert: show/hide waste+witness section
    document.getElementById('adminIsHighAlert').value = slot.is_high_alert ? '1' : '0';
    const haSection = document.getElementById('highAlertAdminSection');
    haSection.style.display = slot.is_high_alert ? 'block' : 'none';
    if (slot.is_high_alert) {
        // Pre-populate existing witness/waste if amending
        const witnessSel = haSection.querySelector('[name="witness_user_id"]');
        const wasteAmtEl = haSection.querySelector('[name="waste_amount"]');
        const wasteUnitEl = haSection.querySelector('[list="al-mar-unit-options" name="waste_unit"]');
        if (witnessSel)  witnessSel.value  = slot.witness_user_id || '';
        if (wasteAmtEl)  wasteAmtEl.value  = slot.waste_amount   || '';
        if (wasteUnitEl) wasteUnitEl.value = slot.waste_unit     || '';
    }

    updateOutcomeFields();
    window.currentAdminSlot = slot;
    new bootstrap.Modal(document.getElementById('adminModal')).show();
}

function openPrnModal(order) {
    document.getElementById('prnOrderId').value     = order.order_id;
    document.getElementById('prnDrugName').value    = order.drug_name;
    document.getElementById('prnIsHighAlert').value = order.is_high_alert ? '1' : '0';
    document.getElementById('prnDoseGiven').value   = order.dose  || '';
    document.getElementById('prnUnitGiven').value   = order.unit  || '';
    document.getElementById('prnRouteGiven').value  = order.route || '';
    document.getElementById('prnDrugLabel').textContent =
        (order.is_high_alert ? '⚠ ' : '') + order.drug_name
        + (order.dose ? ' · ' + order.dose + ' ' + (order.unit || '') : '');
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    document.getElementById('prnAdminDt').value =
        now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
        + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    new bootstrap.Modal(document.getElementById('prnModal')).show();
}

function updateOutcomeFields() {
    const outcome = document.getElementById('outcomeSelect').value;
    document.getElementById('givenFields').style.display = outcome === 'GIVEN' ? 'block' : 'none';
    document.getElementById('holdField').style.display   = outcome === 'HELD'  ? 'block' : 'none';

    const excWrap = document.getElementById('exceptionFollowupField');
    const excHint = document.getElementById('exceptionHint');
    const retryLater = document.getElementById('retryLater');
    const retryMinutes = document.getElementById('retryMinutes');
    const isException = ['HELD', 'REFUSED', 'OMITTED', 'NOT_AVAILABLE', 'MISSED'].includes(outcome);
    excWrap.style.display = isException ? 'block' : 'none';
    let msg = '<?= xla('Document downstream actions for held, refused, or omitted doses.') ?>';
    if (outcome === 'HELD') msg = '<?= xla('Consider retry timing once vitals, labs, or NPO status improve.') ?>';
    if (outcome === 'REFUSED') msg = '<?= xla('Document teaching, patient reason, and whether the provider was notified.') ?>';
    if (outcome === 'OMITTED' || outcome === 'MISSED' || outcome === 'NOT_AVAILABLE') msg = '<?= xla('Consider pharmacy follow-up and a retry task when the medication can be given.') ?>';
    if (excHint) excHint.textContent = msg;
    if (!isException) {
        document.getElementById('providerNotified').checked = false;
        document.getElementById('pharmacyFollowup').checked = false;
        retryLater.checked = false;
    }
    retryMinutes.disabled = !(isException && retryLater.checked);
}

document.getElementById('outcomeSelect').addEventListener('change', updateOutcomeFields);
document.getElementById('retryLater').addEventListener('change', updateOutcomeFields);

function openCoSignModal(adminId) {
    document.getElementById('coSignAdminId').value = adminId;
    new bootstrap.Modal(document.getElementById('coSignModal')).show();
}
</script>
</body>
</html>






















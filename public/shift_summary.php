<?php

/**
 * public/shift_summary.php
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
 * shift_summary.php
 *
 * 7a–7p / 7p–7a shift medication administration reconciliation report.
 *
 * Query params:
 *   facility_id  int              (required)
 *   date         YYYY-MM-DD       (optional, defaults to today)
 *   shift        day | night      (optional, defaults to current shift)
 */

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

if (!$manifest->featureEnabled('mar')) {
    die(xlt('MAR is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$_ss_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

// ── Date / shift resolution ───────────────────────────────────────────────
$today = date('Y-m-d');
$date  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'] ?? '') ? $_GET['date'] : $today;

// Auto-detect current shift if not specified
$currentHour = (int)date('G');
$defaultShift = ($currentHour >= 7 && $currentHour < 19) ? 'day' : 'night';
$shift = in_array($_GET['shift'] ?? '', ['day', 'night'], true) ? $_GET['shift'] : $defaultShift;

if ($shift === 'day') {
    $shiftStart  = $date . ' 07:00:00';
    $shiftEnd    = $date . ' 19:00:00';
    $shiftLabel  = xlt('Day Shift') . ' 07:00–19:00';
    $prevShift   = 'night';
    $nextShift   = 'night';
    $prevDate    = date('Y-m-d', strtotime($date . ' -1 day'));
    $nextDate    = $date;
} else {
    // Night shift spans two calendar days: 19:00 today → 07:00 tomorrow
    $shiftStart  = $date . ' 19:00:00';
    $shiftEnd    = date('Y-m-d', strtotime($date . ' +1 day')) . ' 07:00:00';
    $shiftLabel  = xlt('Night Shift') . ' 19:00–07:00';
    $prevShift   = 'day';
    $nextShift   = 'day';
    $prevDate    = $date;
    $nextDate    = date('Y-m-d', strtotime($date . ' +1 day'));
}

// ── Data ──────────────────────────────────────────────────────────────────
$repo = new MarAdministrationRepository();
$rows = $repo->listByShift($facilityId, $shiftStart, $shiftEnd);
$_ssPids         = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'pid')))));
$_ssPatientNames = oei_patient_names($_ssPids);

// Group by episode_id → mar_order_id
$byEpisode = [];
foreach ($rows as $r) {
    $eid = (int)$r['episode_id'];
    $oid = (int)$r['mar_order_id'];
    $byEpisode[$eid][$oid][] = $r;
}

$taskRepo = new TaskRepository();
$followupTasks = array_values(array_filter(
    $taskRepo->listOpenByFacility($facilityId),
    static fn(array $task): bool => str_starts_with((string)($task['task_type'] ?? ''), 'MAR_')
));

$episodeSafety = [];
foreach ($rows as $r) {
    $eid = (int)($r['episode_id'] ?? 0);
    $episodeSafety[$eid] ??= ['cosign' => 0, 'waste' => 0];
    if ((string)($r['outcome'] ?? '') === 'GIVEN' && !empty($r['is_high_alert']) && empty($r['co_sign_user_id'])) {
        $episodeSafety[$eid]['cosign']++;
    }
    if (!empty($r['waste_amount']) || !empty($r['witness_user_id'])) {
        $episodeSafety[$eid]['waste']++;
    }
}
$followupByEpisode = [];
foreach ($followupTasks as $taskRow) {
    $followupByEpisode[(int)($taskRow['episode_id'] ?? 0)][] = $taskRow;
}

// Outcome counts
$totals = ['GIVEN' => 0, 'HELD' => 0, 'REFUSED' => 0, 'NOT_AVAILABLE' => 0, 'MISSED' => 0];
$awaitingCosignTotal = 0;
$wasteDocTotal = 0;
foreach ($rows as $r) {
    $oc = $r['outcome'] ?? '';
    if (isset($totals[$oc])) $totals[$oc]++;
    if ((string)($r['outcome'] ?? '') === 'GIVEN' && !empty($r['is_high_alert']) && empty($r['co_sign_user_id'])) {
        $awaitingCosignTotal++;
    }
    if (!empty($r['waste_amount']) || !empty($r['witness_user_id'])) {
        $wasteDocTotal++;
    }
}
$followupTaskTotal = count($followupTasks);

$href    = institutional_bootstrap5_href($manifest);
$isPrint = isset($_GET['print']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Shift Summary') ?> — <?= htmlspecialchars($shiftLabel) ?> <?= htmlspecialchars($date) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .shift-table td, .shift-table th { font-size: .82rem; white-space: nowrap; }
    .waste-row td { background: #fff8e1 !important; font-size: .8rem; }
    @media print {
      .no-print { display: none !important; }
      body { background: white !important; font-size: 10pt; }
      .shift-table td, .shift-table th { font-size: 8.5pt; }
    }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <!-- ── Header ─────────────────────────────────────────────────────────── -->
  <div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-2 no-print">
    <div>
      <h1 class="h4 mb-0"><?= xlt('Shift Medication Summary') ?></h1>
      <div class="text-muted small mt-1">
        <?= htmlspecialchars($shiftLabel) ?>
        &mdash; <?= htmlspecialchars($date) ?>
        &mdash; <?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap align-items-center">
      <!-- Back nav -->
      <a href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>"
         class="btn btn-sm btn-outline-secondary no-print">💊 <?= xlt('MAR') ?></a>
      <a href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"
         class="btn btn-sm btn-outline-secondary no-print">🚑 <?= xlt('ED Board') ?></a>
      <!-- Date picker -->
      <form class="d-flex gap-1 align-items-center" method="GET">
        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
        <input type="hidden" name="shift"        value="<?= htmlspecialchars($shift) ?>">
        <input type="date" name="date" class="form-control form-control-sm" style="width:145px"
               value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-sm btn-outline-secondary"><?= xlt('Go') ?></button>
      </form>
      <!-- Shift toggle -->
      <a class="btn btn-sm <?= $shift === 'day' ? 'btn-primary' : 'btn-outline-primary' ?>"
         href="shift_summary.php?facility_id=<?= urlencode((string)$facilityId) ?>&date=<?= urlencode($date) ?>&shift=day">
        ☀ <?= xlt('Day') ?>
      </a>
      <a class="btn btn-sm <?= $shift === 'night' ? 'btn-secondary' : 'btn-outline-secondary' ?>"
         href="shift_summary.php?facility_id=<?= urlencode((string)$facilityId) ?>&date=<?= urlencode($date) ?>&shift=night">
        🌙 <?= xlt('Night') ?>
      </a>
      <a class="btn btn-sm btn-outline-secondary"
         href="shift_summary.php?facility_id=<?= urlencode((string)$facilityId) ?>&date=<?= urlencode($date) ?>&shift=<?= urlencode($shift) ?>&print=1"
         target="_blank">🖨 <?= xlt('Print') ?></a>
      <a class="btn btn-sm btn-outline-secondary"
         href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('MAR') ?></a>
    </div>
  </div>

  <!-- Print header (visible only when printing) -->
  <?php if ($isPrint): ?>
  <div class="mb-3">
    <h2 class="h5"><?= xlt('Shift Medication Administration Summary') ?></h2>
    <div><?= htmlspecialchars($shiftLabel) ?> &mdash; <?= htmlspecialchars($date) ?></div>
    <div><?= xlt('Printed') ?>: <?= htmlspecialchars(date('Y-m-d H:i')) ?></div>
  </div>
  <?php endif; ?>

  <!-- ── Summary badges ─────────────────────────────────────────────────── -->
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <span class="badge text-bg-success fs-6"><?= $totals['GIVEN'] ?> <?= xlt('Given') ?></span>
    <span class="badge text-bg-warning fs-6"><?= $totals['HELD'] ?> <?= xlt('Held') ?></span>
    <span class="badge text-bg-danger fs-6"><?= $totals['REFUSED'] ?> <?= xlt('Refused') ?></span>
    <span class="badge text-bg-secondary fs-6"><?= $totals['NOT_AVAILABLE'] ?> <?= xlt('N/A') ?></span>
    <span class="badge text-bg-dark fs-6"><?= $totals['MISSED'] ?> <?= xlt('Missed') ?></span>
    <span class="badge <?= $awaitingCosignTotal > 0 ? 'text-bg-danger' : 'text-bg-light border text-muted' ?> fs-6"><?= $awaitingCosignTotal ?> <?= xlt('Co-Sign Needed') ?></span>
    <span class="badge <?= $followupTaskTotal > 0 ? 'text-bg-dark' : 'text-bg-light border text-muted' ?> fs-6"><?= $followupTaskTotal ?> <?= xlt('MAR Follow-Up') ?></span>
    <span class="badge <?= $wasteDocTotal > 0 ? 'text-bg-warning text-dark' : 'text-bg-light border text-muted' ?> fs-6"><?= $wasteDocTotal ?> <?= xlt('Waste Docs') ?></span>
    <span class="badge text-bg-light border text-dark fs-6"><?= count($rows) ?> <?= xlt('total') ?></span>
  </div>

  <?php if (empty($byEpisode)): ?>
    <div class="alert alert-info"><?= xlt('No medication administrations recorded during this shift.') ?></div>
  <?php else: ?>

      <?php foreach ($byEpisode as $episodeId => $orderGroups): ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header py-2 d-flex align-items-center gap-2 flex-wrap">
      <?php
      $firstRow  = reset(reset($orderGroups));
      $epSafety  = $episodeSafety[(int)$episodeId] ?? ['cosign' => 0, 'waste' => 0];
      $epFollow  = $followupByEpisode[(int)$episodeId] ?? [];
      $epType    = strtoupper((string)($firstRow['episode_type'] ?? 'ED'));
      $epPid     = (int)($firstRow['pid'] ?? 0);
      $epQs      = 'episode_id=' . $episodeId . '&pid=' . $epPid . '&facility_id=' . $facilityId;
      $epProfile = match ($epType) {
          'AL'  => $_ss_pub_base . 'al/profile.php?'  . $epQs,
          'IP'  => $_ss_pub_base . 'ip/profile.php?'  . $epQs,
          'HBC' => $_ss_pub_base . 'hbc/profile.php?' . $epQs,
          default => null,
      };
      $epTypeBadge = match ($epType) {
          'AL'  => 'bg-success',
          'IP'  => 'bg-primary',
          'HBC' => 'bg-info text-dark',
          'BH'  => 'bg-warning text-dark',
          'OBS' => 'bg-secondary',
          default => 'bg-dark',
      };
      ?>
      <strong><?= xlt('Episode') ?> #<?= htmlspecialchars((string)$episodeId) ?></strong>
      <span class="badge <?= $epTypeBadge ?> ms-1" style="font-size:.65rem;"><?= htmlspecialchars($epType) ?></span>
      <span class="text-muted small"><?= oei_fmt_patient($epPid, $_ssPatientNames) ?></span>
      <span class="badge <?= (($epSafety['cosign'] ?? 0) > 0) ? 'text-bg-danger' : 'text-bg-light border text-muted' ?> ms-2"><?= (int)($epSafety['cosign'] ?? 0) ?> <?= xlt('Co-Sign Needed') ?></span>
      <span class="badge <?= (($epSafety['waste'] ?? 0) > 0) ? 'text-bg-warning text-dark' : 'text-bg-light border text-muted' ?> ms-1"><?= (int)($epSafety['waste'] ?? 0) ?> <?= xlt('Waste') ?></span>
      <span class="badge <?= !empty($epFollow) ? 'text-bg-dark' : 'text-bg-light border text-muted' ?> ms-1"><?= count($epFollow) ?> <?= xlt('Follow-Up') ?></span>
      <div class="ms-auto d-flex gap-1 no-print">
        <?php if ($epProfile): ?>
        <a class="btn btn-sm btn-outline-primary py-0 px-1"
           href="<?= htmlspecialchars($epProfile) ?>">← <?= xlt('Profile') ?></a>
        <?php endif; ?>
        <a class="btn btn-sm btn-outline-secondary py-0 px-1"
           href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">
          <?= xlt('Full MAR') ?>
        </a>
      </div>
    </div>
    <div class="table-responsive">
      <table class="table table-sm shift-table mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Drug') ?></th>
            <th><?= xlt('Ordered') ?></th>
            <th><?= xlt('Freq') ?></th>
            <th><?= xlt('Scheduled') ?></th>
            <th><?= xlt('Administered') ?></th>
            <th><?= xlt('Outcome') ?></th>
            <th><?= xlt('Dose Given') ?></th>
            <th><?= xlt('Route') ?></th>
            <th><?= xlt('Nurse') ?></th>
            <th><?= xlt('Waste / Witness') ?></th>
            <th><?= xlt('Note') ?></th>
          </tr>
        </thead>
        <tbody>
            <?php foreach ($orderGroups as $orderId => $admins): ?>
                <?php foreach ($admins as $idx => $a):
                    $isHA    = (bool)($a['is_high_alert'] ?? false);
                    $isStat  = (bool)($a['is_stat'] ?? false);
                    $outcome = (string)($a['outcome'] ?? '');
                    $hasWaste = !empty($a['waste_amount']) || !empty($a['witness_user_id']);
                    $outcomeClass = match($outcome) {
                        'GIVEN'         => 'text-bg-success',
                        'HELD'          => 'text-bg-warning',
                        'REFUSED'       => 'text-bg-danger',
                        'NOT_AVAILABLE' => 'text-bg-secondary',
                        'MISSED'        => 'text-bg-dark',
                        default         => 'text-bg-light border',
                    };
    ?>
          <tr class="<?= $isHA ? 'table-warning' : '' ?>">
            <td>
              <strong><?= htmlspecialchars((string)($a['drug_name'] ?? '')) ?></strong>
                    <?php if ($isHA): ?>
                <span class="badge text-bg-warning ms-1">⚠</span>
              <?php endif; ?>
                    <?php if ($isStat): ?>
                <span class="badge text-bg-danger ms-1">🔴 STAT</span>
              <?php endif; ?>
            </td>
            <td class="text-muted">
                    <?= htmlspecialchars((string)($a['ordered_dose'] ?? '')) ?>
                    <?= htmlspecialchars((string)($a['ordered_unit'] ?? '')) ?>
            </td>
            <td><?= htmlspecialchars((string)($a['frequency'] ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($a['scheduled_datetime'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)($a['administered_datetime'] ?? '—')) ?></td>
            <td><span class="badge <?= $outcomeClass ?>"><?= htmlspecialchars($outcome) ?></span></td>
            <td>
                    <?php if (!empty($a['dose_given'])): ?>
                        <?= htmlspecialchars((string)$a['dose_given']) ?>
                        <?= htmlspecialchars((string)($a['unit_given'] ?? '')) ?>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($a['route_given'] ?? $a['ordered_route'] ?? '')) ?></td>
            <td class="text-muted small"><?= htmlspecialchars((string)($a['nurse_name'] ?? '')) ?></td>
            <td>
                    <?php $needsCosign = ((string)($a['outcome'] ?? '') === 'GIVEN') && !empty($a['is_high_alert']) && empty($a['co_sign_user_id']); ?>
              <div class="d-flex flex-column gap-1 small">
                <?php if ($hasWaste): ?>
                  <span class="badge text-bg-warning align-self-start">⚠ <?= xlt('Waste') ?></span>
                        <?php if (!empty($a['waste_amount'])): ?>
                  <span>
                            <?= htmlspecialchars((string)$a['waste_amount']) ?>
                            <?= htmlspecialchars((string)($a['waste_unit'] ?? '')) ?>
                  </span>
                        <?php endif; ?>
                        <?php if (!empty($a['witness_name'])): ?>
                  <span class="text-muted">
                            <?= xlt('Witness') ?>: <?= htmlspecialchars((string)$a['witness_name']) ?>
                  </span>
                        <?php else: ?>
                  <span class="text-muted"><?= xlt('Witness not documented') ?></span>
                        <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted"><?= xlt('No waste') ?></span>
                <?php endif; ?>

                <?php if ($needsCosign): ?>
                  <span class="badge text-bg-danger align-self-start"><?= xlt('Co-Sign Needed') ?></span>
                <?php elseif (!empty($a['co_sign_name'])): ?>
                  <span class="text-muted"><?= xlt('Co-Signed') ?>: <?= htmlspecialchars((string)$a['co_sign_name']) ?></span>
                <?php elseif (!empty($a['is_high_alert'])): ?>
                  <span class="text-muted"><?= xlt('No co-sign required') ?></span>
                <?php else: ?>
                  <span class="text-muted"><?= xlt('Not applicable') ?></span>
                <?php endif; ?>
              </div>
            </td>
            <td class="text-muted small" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                title="<?= htmlspecialchars((string)($a['note'] ?? '')) ?>">
                    <?= htmlspecialchars((string)($a['note'] ?? '')) ?>
            </td>
          </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>

  <?php endif; // byEpisode ?>

  <?php if ($isPrint): ?>
  <div class="small text-muted mt-4">
        <?= xlt('Printed') ?>: <?= htmlspecialchars(date('Y-m-d H:i')) ?>
    &mdash; <?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?>
    &mdash; <?= htmlspecialchars($shiftLabel) ?> <?= htmlspecialchars($date) ?>
  </div>
  <?php endif; ?>

</div>

<?php if ($href && !$isPrint): ?>
<?= institutional_bootstrap5_js_tag() ?>
<?php endif; ?>
</body>
</html>



















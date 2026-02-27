<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService;
use OpenEMR\Modules\Institutional\Submodule\Triage\Controller\TriageController;

if (!$manifest->featureEnabled('triage')) {
    die(xlt('Triage is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId  = isset($_GET['episode_id']) && is_numeric($_GET['episode_id'])
    ? (int)$_GET['episode_id']
    : null;
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$triageRepo  = new TriageRepository();
$triageService = new TriageService($triageRepo);
$episodeRepo = new EpisodeRepository();
$controller  = new TriageController($triageRepo, $triageService, $episodeRepo);

$data = $controller->handle($facilityId, $episodeId, $userId);

// After a POST the controller keeps $episodeId from POST body, keep URL in sync
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (int)($data['episodeId'] ?? 0) > 0) {
    header("Location: triage.php?facility_id=" . urlencode((string)$facilityId)
        . "&episode_id=" . urlencode((string)$data['episodeId']));
    exit;
}

$href = institutional_bootstrap5_href($manifest);

/** Format a nullable numeric value for display, with optional unit */
function tv(mixed $v, string $unit = ''): string
{
    if ($v === null || $v === '' || $v === '0') {
        return '<span class="text-muted">—</span>';
    }
    return htmlspecialchars((string)$v) . ($unit !== '' ? '<span class="text-muted small"> ' . $unit . '</span>' : '');
}

/** Map severity to Bootstrap badge class */
function vitalsBadge(array $row): string
{
    $sev = \OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService::boardSeverity($row);
    return match ($sev) {
        'danger'  => 'text-bg-danger',
        'warning' => 'text-bg-warning',
        default   => 'text-bg-light border',
    };
}

$selected  = $data['selected'];
$episodeId = (int)($data['episodeId'] ?? 0);
$history   = $data['history'];
$latest    = $data['latest'];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Triage / Vitals') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .vital-cell { min-width: 72px; }
    .set-row-critical { background: #fff0f0 !important; }
    .set-row-warning  { background: #fffbe6 !important; }
    .trend-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin: 0 1px; }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Triage / Vitals') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
      <?php if ($episodeId > 0): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt('Disposition') ?></a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <?php foreach ($data['alerts'] as $alert): ?>
    <div class="alert alert-danger py-2 fw-semibold">⚠ <?= htmlspecialchars((string)$alert) ?></div>
  <?php endforeach; ?>

  <div class="row g-3">

    <!-- Left sidebar: episode list -->
    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt('Active Episodes') ?></div>
        <div class="list-group list-group-flush" style="max-height: 75vh; overflow-y: auto;">
          <?php foreach ($data['boardRows'] as $e):
            $eId   = (int)$e['id'];
            $isAct = ($eId === $episodeId);
            // Grab latest vitals badge for sidebar indicator
            $evRow = null; // loaded below per-episode would be N+1; skip for now
          ?>
            <a class="list-group-item list-group-item-action py-2 <?= $isAct ? 'active' : '' ?>"
               href="triage.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$eId) ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold small">#<?= htmlspecialchars((string)$eId) ?> &middot; PID <?= htmlspecialchars((string)$e['pid']) ?></div>
                  <div class="small opacity-75 text-truncate" style="max-width: 160px;">
                    <?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?>
                  </div>
                </div>
                <div class="text-end small">
                  <span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span>
                  <?php if (!empty($e['acuity_esi'])): ?>
                    <span class="badge text-bg-info">ESI <?= htmlspecialchars((string)$e['acuity_esi']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
          <?php if (empty($data['boardRows'])): ?>
            <div class="list-group-item text-muted small"><?= xlt('No active episodes') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div><!-- /sidebar -->

    <!-- Main content -->
    <div class="col-12 col-lg-9">

      <?php if ($episodeId <= 0): ?>
        <div class="alert alert-info"><?= xlt('Select an episode from the list to record vitals.') ?></div>
      <?php else: ?>

      <!-- Latest vitals summary banner -->
      <?php if ($latest): ?>
        <?php $sev = \OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService::boardSeverity($latest); ?>
        <div class="alert <?= $sev === 'danger' ? 'alert-danger' : ($sev === 'warning' ? 'alert-warning' : 'alert-secondary') ?> py-2 mb-3 d-flex align-items-center gap-3 flex-wrap">
          <strong><?= xlt('Latest') ?>:</strong>
          <span><?= \OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService::formatForBoard($latest) ?></span>
          <?php if (!empty($latest['pain_score'])): ?>
            <span class="badge text-bg-secondary"><?= xlt('Pain') ?> <?= htmlspecialchars((string)$latest['pain_score']) ?>/10</span>
          <?php endif; ?>
          <?php if (!empty($latest['esi_suggested'])): ?>
            <span class="badge text-bg-warning"><?= xlt('ESI suggest') ?> <?= htmlspecialchars((string)$latest['esi_suggested']) ?></span>
          <?php endif; ?>
          <span class="text-muted small ms-auto"><?= htmlspecialchars(institutional_human_elapsed((string)$latest['noted_datetime'])) ?> <?= xlt('ago') ?></span>
        </div>
      <?php endif; ?>

      <!-- Vitals entry form -->
      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Record Vitals') ?> &mdash; <?= xlt('Episode') ?> #<?= htmlspecialchars((string)$episodeId) ?></span>
          <?php if ($selected): ?>
            <span class="text-muted small"><?= xlt('PID') ?> <?= htmlspecialchars((string)$selected['pid']) ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <form method="post" class="row g-2" autocomplete="off">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">
            <input type="hidden" name="episode_id"     value="<?= htmlspecialchars((string)$episodeId) ?>">
            <input type="hidden" name="pid"            value="<?= htmlspecialchars((string)($selected['pid'] ?? '')) ?>">
            <input type="hidden" name="eid"            value="<?= htmlspecialchars((string)($selected['eid'] ?? '')) ?>">

            <!-- Row 1: BP, HR, RR, SpO2 -->
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('BP Systolic') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="bp_systolic" class="form-control" min="40" max="300"
                       placeholder="120" inputmode="numeric">
                <span class="input-group-text">mmHg</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('BP Diastolic') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="bp_diastolic" class="form-control" min="20" max="200"
                       placeholder="80" inputmode="numeric">
                <span class="input-group-text">mmHg</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('HR') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="hr" class="form-control" min="20" max="300"
                       placeholder="72" inputmode="numeric">
                <span class="input-group-text">bpm</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('RR') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="rr" class="form-control" min="4" max="60"
                       placeholder="16" inputmode="numeric">
                <span class="input-group-text">/min</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('SpO₂') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="spo2" class="form-control" min="50" max="100"
                       placeholder="98" inputmode="numeric">
                <span class="input-group-text">%</span>
              </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('Temp') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="temp_f" class="form-control" min="85" max="115"
                       step="0.1" placeholder="98.6" inputmode="decimal">
                <span class="input-group-text">°F</span>
              </div>
            </div>

            <!-- Row 2: GCS, Pain, Weight, Arrival -->
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('GCS') ?></label>
              <select name="gcs" class="form-select form-select-sm">
                <option value=""><?= xlt('—') ?></option>
                <?php for ($g = 15; $g >= 3; $g--): ?>
                  <option value="<?= $g ?>"><?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('Pain') ?> <span class="text-muted fw-normal">(0–10)</span></label>
              <select name="pain_score" class="form-select form-select-sm">
                <option value=""><?= xlt('—') ?></option>
                <?php for ($p = 0; $p <= 10; $p++): ?>
                  <option value="<?= $p ?>"><?= $p ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
              <label class="form-label fw-semibold"><?= xlt('Weight') ?></label>
              <div class="input-group input-group-sm">
                <input type="number" name="weight_kg" class="form-control" min="1" max="500"
                       step="0.1" placeholder="70" inputmode="decimal">
                <span class="input-group-text">kg</span>
              </div>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
              <label class="form-label fw-semibold"><?= xlt('Arrival Mode') ?></label>
              <select name="arrival_mode" class="form-select form-select-sm">
                <option value=""><?= xlt('—') ?></option>
                <option value="WALKIN"><?= xlt('Walk-in') ?></option>
                <option value="EMS"><?= xlt('EMS') ?></option>
                <option value="TRANSFER"><?= xlt('Transfer') ?></option>
                <option value="POLICE"><?= xlt('Police / Custody') ?></option>
                <option value="WHEELCHAIR"><?= xlt('Wheelchair') ?></option>
                <option value="STRETCHER"><?= xlt('Stretcher') ?></option>
              </select>
            </div>

            <!-- Notes and submit -->
            <div class="col-12 col-md-8">
              <label class="form-label"><?= xlt('Notes') ?></label>
              <input name="notes" class="form-control form-control-sm"
                     placeholder="<?= xla('Optional triage note') ?>">
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
              <button class="btn btn-primary w-100"><?= xlt('Save Vitals') ?></button>
            </div>

            <div class="col-12">
              <div class="form-text">
                <?= xlt('ESI will be suggested from vitals automatically. Leave fields blank if not measured.') ?>
              </div>
            </div>
          </form>
        </div>
      </div><!-- /entry form -->

      <!-- Vitals history table -->
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Vitals History') ?> &mdash; <?= count($history) ?> <?= xlt('set(s)') ?></span>
          <a class="btn btn-sm btn-outline-secondary"
             href="triage.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt('Refresh') ?></a>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('#') ?></th>
                <th><?= xlt('Time') ?></th>
                <th class="vital-cell"><?= xlt('BP') ?></th>
                <th class="vital-cell"><?= xlt('HR') ?></th>
                <th class="vital-cell"><?= xlt('RR') ?></th>
                <th class="vital-cell"><?= xlt('SpO₂') ?></th>
                <th class="vital-cell"><?= xlt('Temp°F') ?></th>
                <th class="vital-cell"><?= xlt('GCS') ?></th>
                <th class="vital-cell"><?= xlt('Pain') ?></th>
                <th class="vital-cell"><?= xlt('Wt kg') ?></th>
                <th><?= xlt('ESI?') ?></th>
                <th><?= xlt('Notes') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach (array_reverse($history) as $row):
              $sev = \OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService::boardSeverity($row);
              $trClass = $sev === 'danger' ? 'set-row-critical' : ($sev === 'warning' ? 'set-row-warning' : '');
            ?>
              <tr class="<?= $trClass ?>">
                <td><span class="badge <?= vitalsBadge($row) ?>"><?= htmlspecialchars((string)$row['set_number']) ?></span></td>
                <td class="text-nowrap small"><?= htmlspecialchars((string)$row['noted_datetime']) ?></td>
                <td class="vital-cell">
                  <?php if (!empty($row['bp_systolic']) && !empty($row['bp_diastolic'])): ?>
                    <?= htmlspecialchars((string)$row['bp_systolic']) ?>/<?= htmlspecialchars((string)$row['bp_diastolic']) ?>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="vital-cell"><?= tv($row['hr'] ?? null) ?></td>
                <td class="vital-cell"><?= tv($row['rr'] ?? null) ?></td>
                <td class="vital-cell"><?php
                  $s = $row['spo2'] ?? null;
                  if ($s !== null && $s !== '') {
                      $cls = (int)$s < 90 ? 'text-danger fw-bold' : ((int)$s < 94 ? 'text-warning fw-semibold' : '');
                      echo '<span class="' . $cls . '">' . htmlspecialchars((string)$s) . '%</span>';
                  } else { echo '<span class="text-muted">—</span>'; }
                ?></td>
                <td class="vital-cell"><?= tv($row['temp_f'] ?? null) ?></td>
                <td class="vital-cell"><?php
                  $g = $row['gcs'] ?? null;
                  if ($g !== null && $g !== '') {
                      $gcsCls = (int)$g <= 8 ? 'text-danger fw-bold' : ((int)$g < 13 ? 'text-warning' : '');
                      echo '<span class="' . $gcsCls . '">' . htmlspecialchars((string)$g) . '</span>';
                  } else { echo '<span class="text-muted">—</span>'; }
                ?></td>
                <td class="vital-cell"><?= tv($row['pain_score'] ?? null) ?></td>
                <td class="vital-cell"><?= tv($row['weight_kg'] ?? null) ?></td>
                <td><?php if (!empty($row['esi_suggested'])): ?>
                    <span class="badge text-bg-warning"><?= htmlspecialchars((string)$row['esi_suggested']) ?></span>
                <?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
                <td class="small text-muted"><?= htmlspecialchars((string)($row['notes'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($history)): ?>
              <tr><td colspan="12" class="text-center text-muted py-4"><?= xlt('No vitals recorded yet for this episode') ?></td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Simple trending mini-chart using CSS dots for SpO2/HR -->
        <?php if (count($history) >= 2):
          $hrSeries  = array_map(fn($r) => (int)($r['hr']   ?? 0), $history);
          $spo2Series = array_map(fn($r) => (int)($r['spo2'] ?? 0), $history);
          $maxHr = max(array_filter($hrSeries)) ?: 1;
          $minSpo2 = min(array_filter($spo2Series)) ?: 100;
        ?>
        <div class="card-body border-top py-2">
          <div class="d-flex flex-wrap gap-4 align-items-center">
            <div class="small text-muted">
              <strong><?= xlt('HR trend') ?>:</strong>
              <?php foreach ($hrSeries as $v): ?>
                <?php $cls = ($v > 130 || ($v > 0 && $v < 50)) ? 'bg-danger' : ($v > 110 ? 'bg-warning' : 'bg-success'); ?>
                <?php if ($v > 0): ?><span class="trend-dot <?= $cls ?>" title="<?= $v ?>"></span><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <div class="small text-muted">
              <strong><?= xlt('SpO₂ trend') ?>:</strong>
              <?php foreach ($spo2Series as $v): ?>
                <?php $cls = ($v > 0 && $v < 90) ? 'bg-danger' : ($v < 94 && $v > 0 ? 'bg-warning' : 'bg-success'); ?>
                <?php if ($v > 0): ?><span class="trend-dot <?= $cls ?>" title="<?= $v ?>%"></span><?php endif; ?>
              <?php endforeach; ?>
            </div>
            <div class="text-muted small ms-auto"><?= xlt('● Critical &nbsp; ● Warning &nbsp; ● Normal') ?></div>
          </div>
        </div>
        <?php endif; ?>

      </div><!-- /history -->

      <?php endif; // episodeId > 0 ?>

    </div><!-- /main -->
  </div><!-- /row -->
</div>
</body>
</html>

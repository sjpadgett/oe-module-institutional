<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Submodule\ObsBilling\Service\ObsBillingService;

if (!$manifest->featureEnabled('obs_billing')) {
    die(xlt('Observation Billing Flags is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$href = institutional_bootstrap5_href($manifest);

$service = new ObsBillingService();
$rows    = $service->fetchObsBillingStatus($facilityId);

// Summary counts by status
$counts = ['NORMAL' => 0, 'APPROACHING_1' => 0, 'APPROACHING_2' => 0, 'CONVERSION_DUE' => 0, 'OVERRUN' => 0];
foreach ($rows as $r) {
    $s = $r['status'];
    $counts[$s] = ($counts[$s] ?? 0) + 1;
}

function billing_row_class(string $status): string
{
    return match ($status) {
        'OVERRUN'       => 'table-danger',
        'CONVERSION_DUE'=> 'table-danger',
        'APPROACHING_2' => 'table-warning',
        'APPROACHING_1' => 'table-warning',
        default         => '',
    };
}

function billing_badge(string $status): string
{
    return match ($status) {
        'OVERRUN'       => '<span class="badge text-bg-danger">OVERRUN</span>',
        'CONVERSION_DUE'=> '<span class="badge text-bg-danger">CONVERT NOW</span>',
        'APPROACHING_2' => '<span class="badge text-bg-warning text-dark">2nd MIDNIGHT</span>',
        'APPROACHING_1' => '<span class="badge text-bg-warning text-dark">1st MIDNIGHT</span>',
        default         => '<span class="badge text-bg-secondary">Normal</span>',
    };
}

function midnight_pips(int $count): string
{
    $out = '';
    for ($i = 0; $i < min($count, 4); $i++) {
        $out .= '<span class="badge text-bg-info me-1">🌙</span>';
    }
    return $out ?: '<span class="text-muted">—</span>';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('OBS Billing Flags') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .kpi-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .06em; color: #6c757d; }
    .kpi-val   { font-size: 1.6rem; font-weight: 700; line-height: 1; }
    .rule-note { border-left: 4px solid #0d6efd; background: #f0f6ff; }
    .progress-midnight { height: 8px; border-radius: 4px; }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0"><?= xlt('Observation Billing Flags') ?></h1>
      <div class="text-muted small"><?= xlt('CMS 2-Midnight Rule compliance · Revenue integrity') ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="obs_episodes.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Obs Episodes') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="cms_quality.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('CMS Measures') ?></a>
    </div>
  </div>

  <!-- 2-midnight rule explainer -->
  <div class="card shadow-sm mb-4 rule-note">
    <div class="card-body py-2">
      <span class="fw-semibold"><?= xlt('CMS 2-Midnight Rule') ?>:</span>
      <?= xlt('OBS stays crossing 2 midnights should be converted to inpatient admission (DRG reimbursement) to avoid revenue loss. Community hospitals lose significant reimbursement on miscoded observation stays.') ?>
      <span class="ms-2">
        <span class="badge text-bg-warning text-dark"><?= xlt('20–48h: Watch') ?></span>
        <span class="badge text-bg-danger ms-1"><?= xlt('48h+: Convert') ?></span>
      </span>
    </div>
  </div>

  <!-- KPI summary row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <div class="kpi-label"><?= xlt('Total OBS') ?></div>
          <div class="kpi-val"><?= count($rows) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card shadow-sm text-center h-100 border-danger">
        <div class="card-body">
          <div class="kpi-label text-danger"><?= xlt('Convert Now') ?></div>
          <div class="kpi-val text-danger"><?= ($counts['CONVERSION_DUE'] + $counts['OVERRUN']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card shadow-sm text-center h-100 border-warning">
        <div class="card-body">
          <div class="kpi-label" style="color:#856404;"><?= xlt('Watch') ?></div>
          <div class="kpi-val" style="color:#856404;"><?= ($counts['APPROACHING_1'] + $counts['APPROACHING_2']) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <div class="kpi-label"><?= xlt('Normal') ?></div>
          <div class="kpi-val text-success"><?= $counts['NORMAL'] ?></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Episode table -->
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Active Observation Episodes') ?></span>
      <span class="text-muted small"><?= xlt('Sorted by elapsed time descending') ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Episode') ?></th>
            <th><?= xlt('Room') ?></th>
            <th><?= xlt('Chief Complaint') ?></th>
            <th><?= xlt('Protocol') ?></th>
            <th><?= xlt('OBS Start') ?></th>
            <th><?= xlt('Elapsed') ?></th>
            <th><?= xlt('Progress') ?></th>
            <th><?= xlt('Midnights') ?></th>
            <th><?= xlt('Status') ?></th>
            <th><?= xlt('Action Required') ?></th>
            <th><?= xlt('Provider') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Sort: OVERRUN first, then CONVERSION_DUE, then by elapsed desc
        $statusOrder = ['OVERRUN' => 0, 'CONVERSION_DUE' => 1, 'APPROACHING_2' => 2, 'APPROACHING_1' => 3, 'NORMAL' => 4];
        usort($rows, function ($a, $b) use ($statusOrder) {
            $so = ($statusOrder[$a['status']] ?? 9) <=> ($statusOrder[$b['status']] ?? 9);
            if ($so !== 0) return $so;
            return $b['elapsed_hours'] <=> $a['elapsed_hours'];
        });
        foreach ($rows as $r):
            $pct    = min(100, round($r['elapsed_hours'] / 72 * 100));
            $barCls = match ($r['status']) {
                'OVERRUN', 'CONVERSION_DUE' => 'bg-danger',
                'APPROACHING_2'             => 'bg-warning',
                'APPROACHING_1'             => 'bg-warning',
                default                     => 'bg-success',
            };
            ?>
          <tr class="<?= billing_row_class($r['status']) ?>">
            <td>
              <a href="obs_episode.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">
                #<?= (int)$r['episode_id'] ?>
              </a>
            </td>
            <td><?= htmlspecialchars((string)$r['location_name']) ?></td>
            <td class="small"><?= htmlspecialchars(mb_strimwidth((string)$r['chief_complaint'], 0, 40, '…')) ?></td>
            <td><span class="badge text-bg-info"><?= htmlspecialchars((string)$r['protocol_key']) ?></span></td>
            <td class="text-muted small"><?= htmlspecialchars(substr((string)$r['obs_start'], 0, 16)) ?></td>
            <td class="fw-semibold">
              <?= htmlspecialchars(ObsBillingService::formatElapsed($r['elapsed_hours'])) ?>
            </td>
            <td style="min-width:90px;">
              <!-- Progress bar: 0–72h window, markers at 24h and 48h -->
              <div class="position-relative">
                <div class="progress progress-midnight">
                  <div class="progress-bar <?= $barCls ?>"
                       role="progressbar"
                       style="width:<?= $pct ?>%">
                  </div>
                </div>
                <!-- Midnight markers -->
                <div class="position-absolute" style="left:33.3%;top:-2px;width:2px;height:12px;background:#6c757d;opacity:.4;"></div>
                <div class="position-absolute" style="left:66.6%;top:-2px;width:2px;height:12px;background:#dc3545;opacity:.7;"></div>
              </div>
              <div class="d-flex justify-content-between" style="font-size:.62rem;color:#adb5bd;margin-top:1px;">
                <span>0</span><span>24h</span><span>48h</span><span>72h</span>
              </div>
            </td>
            <td><?= midnight_pips($r['midnights']) ?></td>
            <td><?= billing_badge($r['status']) ?></td>
            <td class="small"><?= htmlspecialchars($r['action_label']) ?></td>
            <td class="small text-muted"><?= htmlspecialchars($r['provider_name']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="11" class="text-center text-muted py-4"><?= xlt('No active observation episodes') ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3 text-muted small">
    <?= xlt('Progress bar spans 0–72h. Red marker at 48h = 2-midnight threshold. Grey marker at 24h = first midnight.') ?>
    <?= xlt('Midnight count reflects calendar midnights crossed since OBS plan start.') ?>
  </div>

</div>
</body>
</html>



<?php

/**
 * public/cms_quality.php
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

use OpenEMR\Modules\Institutional\ObservationStay\Submodule\CmsQuality\Repository\CmsMeasureRepository;

if (!$manifest->featureEnabled('cms_quality')) {
    die(xlt('Institutional Quality Dashboard is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$dateFrom   = (string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo     = (string)($_GET['date_to']   ?? date('Y-m-d'));

$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : date('Y-m-d', strtotime('-30 days'));
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   ? $dateTo   : date('Y-m-d');

$repo      = new CmsMeasureRepository();
$dashboard = $repo->computeDashboard($facilityId, $dateFrom, $dateTo);
$summary   = $dashboard['summary'] ?? [];
$readiness = $dashboard['readiness'] ?? [];
$signals   = $dashboard['signals'] ?? [];
$catalog   = $dashboard['catalog'] ?? [];
$measures  = $dashboard['operational'] ?? [];

$href = institutional_bootstrap5_href($manifest);

function iq_fmt_min(?int $min): string
{
    if ($min === null) {
        return '—';
    }
    if ($min < 60) {
        return $min . 'm';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
}

function iq_pct($pct): string
{
    return $pct === null ? '—' : round((float)$pct, 1) . '%';
}

function iq_status_badge(string $status): string
{
    return match ($status) {
        'READY', 'GOOD'   => 'text-bg-success',
        'PARTIAL', 'WATCH'=> 'text-bg-warning text-dark',
        'ACTION'          => 'text-bg-danger',
        default           => 'text-bg-secondary',
    };
}

function iq_status_bar(string $status): string
{
    return match ($status) {
        'READY', 'GOOD'   => 'bg-success',
        'PARTIAL', 'WATCH'=> 'bg-warning',
        'ACTION'          => 'bg-danger',
        default           => 'bg-secondary',
    };
}

function iq_rate_tier(?float $rate): string
{
    if ($rate === null) return 'NO DATA';
    if ($rate >= 90) return 'EXCELLENT';
    if ($rate >= 75) return 'GOOD';
    if ($rate >= 50) return 'FAIR';
    return 'POOR';
}

function iq_tier_badge(string $tier): string
{
    return match ($tier) {
        'EXCELLENT' => 'text-bg-success',
        'GOOD'      => 'text-bg-primary',
        'FAIR'      => 'text-bg-warning text-dark',
        'POOR'      => 'text-bg-danger',
        default     => 'text-bg-secondary',
    };
}

function iq_rate_gauge(?float $rate): string
{
    $pct = $rate ?? 0;
    $pct = max(0, min(100, $pct));
    $angle   = ($pct / 100) * 180 - 180;
    $rad     = $angle * M_PI / 180;
    $cx = 60; $cy = 60; $r = 48;
    $x = $cx + $r * cos($rad);
    $y = $cy + $r * sin($rad);

    $fillColor = $pct >= 90 ? '#198754' : ($pct >= 75 ? '#0d6efd' : ($pct >= 50 ? '#ffc107' : '#dc3545'));
    $trackColor = '#e9ecef';
    $largeArc = $pct > 50 ? 1 : 0;

    ob_start(); ?>
    <svg viewBox="0 0 120 65" width="120" height="65" xmlns="http://www.w3.org/2000/svg">
      <path d="M12,60 A48,48 0 0,1 108,60" fill="none" stroke="<?= $trackColor ?>" stroke-width="10" stroke-linecap="round"/>
      <?php if ($pct > 0): ?>
      <path d="M12,60 A48,48 0 <?= $largeArc ?>,1 <?= round($x, 2) ?>,<?= round($y, 2) ?>"
            fill="none" stroke="<?= $fillColor ?>" stroke-width="10" stroke-linecap="round"/>
      <?php endif; ?>
      <text x="60" y="58" text-anchor="middle" font-size="16" font-weight="bold"
            fill="<?= $rate === null ? '#6c757d' : $fillColor ?>">
        <?= $rate !== null ? round($pct, 0) . '%' : '—' ?>
      </text>
    </svg>
    <?php
    return ob_get_clean();
}

$catalogGroups = [];
foreach ($catalog as $item) {
    $catalogGroups[(string)($item['group'] ?? 'Institutional Quality')][] = $item;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Institutional Quality Dashboard') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .iq-card { transition: box-shadow .15s; }
    .iq-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12) !important; }
    .iq-muted { color:#6c757d; }
    .iq-kpi-label { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:#6c757d; }
    .iq-kpi-val { font-size:1.5rem; font-weight:700; line-height:1.1; }
    .iq-section-title { letter-spacing:.04em; text-transform:uppercase; font-size:.75rem; color:#6c757d; }
    .iq-signal-value { font-size:2rem; font-weight:700; line-height:1; }
    .iq-note { font-size:.85rem; color:#6c757d; }
    .iq-mini { font-size:.82rem; }
    .iq-readiness-bar { height:.55rem; }
    .iq-table th { font-size:.73rem; text-transform:uppercase; letter-spacing:.04em; }
    .iq-table td { font-size:.84rem; }
    details > summary { cursor:pointer; list-style:none; }
    details > summary::-webkit-details-marker { display:none; }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0"><?= xlt('Institutional Quality Dashboard') ?></h1>
      <div class="text-muted small"><?= xlt('Operational throughput, institutional harm signals, and native eCQM readiness scaffolding') ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="throughput.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Throughput') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="scorecard.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Scorecard') ?></a>
    </div>
  </div>

  <form method="get" class="row g-2 mb-4 align-items-end">
    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
    <div class="col-auto">
      <label class="form-label small"><?= xlt('From') ?></label>
      <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small"><?= xlt('To') ?></label>
      <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><?= xlt('Apply') ?></button>
      <?php
        $quick = [
          '7d'  => [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')],
          '30d' => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
          '90d' => [date('Y-m-d', strtotime('-90 days')), date('Y-m-d')],
          'YTD' => [date('Y-01-01'), date('Y-m-d')],
        ];
        foreach ($quick as $lbl => [$f, $t]): ?>
        <a class="btn btn-outline-secondary btn-sm"
           href="cms_quality.php?facility_id=<?= urlencode((string)$facilityId) ?>&date_from=<?= urlencode($f) ?>&date_to=<?= urlencode($t) ?>">
            <?= htmlspecialchars($lbl) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="col-auto ms-auto text-muted small">
      <?= htmlspecialchars($dateFrom) ?> → <?= htmlspecialchars($dateTo) ?>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card shadow-sm iq-card h-100"><div class="card-body">
        <div class="iq-kpi-label"><?= xlt('Overall readiness') ?></div>
        <div class="iq-kpi-val"><?= iq_pct($summary['overall_readiness_pct'] ?? null) ?></div>
        <div class="iq-note mt-2"><?= xlt('Average of encounter linkage, vitals, MAR, fall-risk, and discharge-summary readiness checks.') ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card shadow-sm iq-card h-100"><div class="card-body">
        <div class="iq-kpi-label"><?= xlt('Institutional targets') ?></div>
        <div class="iq-kpi-val"><?= (int)($summary['catalog_count'] ?? 0) ?></div>
        <div class="iq-note mt-2"><?= xlt('Native OpenEMR eCQM / hospital-harm targets surfaced as ready, partial, or planned.') ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card shadow-sm iq-card h-100"><div class="card-body">
        <div class="iq-kpi-label"><?= xlt('Implemented signals') ?></div>
        <div class="iq-kpi-val"><?= (int)($summary['signal_count'] ?? 0) ?></div>
        <div class="iq-note mt-2"><?= xlt('Current institutional signals already derived from your module data without pretending to be a full CMS engine.') ?></div>
      </div></div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card shadow-sm iq-card h-100"><div class="card-body">
        <div class="iq-kpi-label"><?= xlt('Signals needing review') ?></div>
        <div class="iq-kpi-val"><?= (int)($summary['signal_flags'] ?? 0) ?></div>
        <div class="iq-note mt-2"><?= xlt('Signals currently in watch or action state during the selected period.') ?></div>
      </div></div>
    </div>
  </div>

  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="iq-section-title"><?= xlt('Institutional readiness') ?></div>
        <h2 class="h5 mb-0"><?= xlt('Source data scaffolding') ?></h2>
      </div>
    </div>
    <div class="row g-3">
      <?php foreach ($readiness as $item): ?>
        <div class="col-12 col-md-6 col-xl-4">
          <div class="card shadow-sm iq-card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                <div class="fw-semibold"><?= htmlspecialchars((string)$item['label']) ?></div>
                <span class="badge <?= iq_status_badge((string)($item['status'] ?? 'PLANNED')) ?>"><?= htmlspecialchars((string)($item['status'] ?? 'PLANNED')) ?></span>
              </div>
              <div class="d-flex justify-content-between align-items-baseline mb-2">
                <div class="iq-kpi-val" style="font-size:1.3rem;"><?= iq_pct($item['pct'] ?? null) ?></div>
                <div class="small text-muted"><?= (int)($item['numerator'] ?? 0) ?> / <?= (int)($item['denominator'] ?? 0) ?></div>
              </div>
              <div class="progress iq-readiness-bar mb-2">
                <div class="progress-bar <?= iq_status_bar((string)($item['status'] ?? 'PLANNED')) ?>" role="progressbar"
                     style="width:<?= max(0, min(100, (float)($item['pct'] ?? 0))) ?>%"></div>
              </div>
              <div class="iq-note"><?= htmlspecialchars((string)($item['note'] ?? '')) ?></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="iq-section-title"><?= xlt('Institutional target catalog') ?></div>
        <h2 class="h5 mb-0"><?= xlt('Native OpenEMR eCQM / hospital-harm anchors') ?></h2>
      </div>
    </div>
    <?php foreach ($catalogGroups as $group => $items): ?>
      <h3 class="h6 mt-3 mb-2 text-muted"><?= htmlspecialchars((string)$group) ?></h3>
      <div class="row g-3 mb-1">
        <?php foreach ($items as $item): ?>
          <div class="col-12 col-md-6 col-xl-4">
            <div class="card shadow-sm iq-card h-100">
              <div class="card-body">
                <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                  <div>
                    <div class="fw-semibold"><?= htmlspecialchars((string)$item['title']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars((string)($item['code'] ?? '')) ?></div>
                  </div>
                  <span class="badge <?= iq_status_badge((string)($item['status'] ?? 'PLANNED')) ?>"><?= htmlspecialchars((string)($item['status'] ?? 'PLANNED')) ?></span>
                </div>
                <div class="iq-note"><?= htmlspecialchars((string)($item['note'] ?? '')) ?></div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="iq-section-title"><?= xlt('Implemented institutional signals') ?></div>
        <h2 class="h5 mb-0"><?= xlt('Current period quality signals') ?></h2>
      </div>
    </div>
    <div class="row g-3">
      <?php foreach ($signals as $key => $signal): ?>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card shadow-sm iq-card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars((string)$signal['label']) ?></div>
                  <div class="small text-muted"><?= htmlspecialchars((string)($signal['code'] ?? '')) ?></div>
                </div>
                <span class="badge <?= iq_status_badge((string)($signal['status'] ?? 'WATCH')) ?>"><?= htmlspecialchars((string)($signal['status'] ?? 'WATCH')) ?></span>
              </div>
              <div class="iq-signal-value mb-1"><?= htmlspecialchars((string)($signal['value'] ?? '—')) ?></div>
              <div class="small text-muted mb-2"><?= htmlspecialchars((string)($signal['subtext'] ?? '')) ?></div>
              <div class="iq-note"><?= htmlspecialchars((string)($signal['note'] ?? '')) ?></div>
            </div>
            <?php if (!empty($signal['rows'])): ?>
              <div class="card-footer p-0 bg-white">
                <details>
                  <summary class="px-3 py-2 text-muted small">▶ <?= xlt('Drill-down') ?> (<?= count($signal['rows']) ?>)</summary>
                  <div class="table-responsive">
                    <table class="table table-sm iq-table mb-0">
                      <thead class="table-light">
                      <tr>
                        <th><?= xlt('Episode') ?></th>
                        <th><?= xlt('When') ?></th>
                        <th><?= xlt('Detail') ?></th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php foreach ($signal['rows'] as $row): ?>
                        <tr>
                          <td>
                            <a href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)($row['episode_id'] ?? 0) ?>">
                              #<?= (int)($row['episode_id'] ?? 0) ?>
                            </a>
                          </td>
                          <td class="text-muted small">
                            <?php
                              $dt = $row['incident_datetime'] ?? $row['antagonist_dt'] ?? $row['opioid_dt'] ?? '';
                              echo htmlspecialchars(substr((string)$dt, 0, 16));
                            ?>
                          </td>
                          <td>
                            <?php if ($key === 'falls_with_injury'): ?>
                              <span class="badge text-bg-danger me-1"><?= htmlspecialchars((string)($row['severity'] ?? '')) ?></span>
                                <?= htmlspecialchars((string)($row['incident_type'] ?? '')) ?>
                            <?php else: ?>
                                <?= htmlspecialchars((string)($row['opioid_name'] ?? '')) ?>
                              →
                                <?= htmlspecialchars((string)($row['antagonist_name'] ?? '')) ?>
                                <?php if (isset($row['minutes_to_rescue'])): ?>
                                <span class="text-muted small">(<?= iq_fmt_min((int)$row['minutes_to_rescue']) ?>)</span>
                              <?php endif; ?>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </details>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="mb-2">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="iq-section-title"><?= xlt('Operational measures') ?></div>
        <h2 class="h5 mb-0"><?= xlt('Existing ED / OBS timing measures kept intact') ?></h2>
      </div>
    </div>
    <div class="row g-3">
      <?php foreach ($measures as $key => $m):
            $tier = iq_rate_tier($m['rate_pct']);
            $badge = iq_tier_badge($tier);
            ?>
        <div class="col-12 col-md-6 col-xl-3">
          <div class="card shadow-sm iq-card h-100">
            <div class="card-body">
              <div class="d-flex align-items-start justify-content-between mb-2">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars((string)$m['label']) ?></div>
                  <div class="text-muted small"><?= htmlspecialchars((string)$m['cms_id']) ?></div>
                </div>
                <span class="badge <?= $badge ?>"><?= htmlspecialchars($tier) ?></span>
              </div>
              <div class="text-center mb-2"><?= iq_rate_gauge($m['rate_pct']) ?></div>
              <div class="row g-2 text-center">
                <div class="col-4">
                  <div class="iq-kpi-label"><?= xlt('N') ?></div>
                  <div class="iq-kpi-val"><?= (int)$m['n'] ?></div>
                </div>
                <div class="col-4">
                  <div class="iq-kpi-label"><?= xlt('Met') ?></div>
                  <div class="iq-kpi-val text-success"><?= (int)$m['n_met'] ?></div>
                </div>
                <div class="col-4">
                  <div class="iq-kpi-label"><?= xlt('Median') ?></div>
                  <div class="iq-kpi-val"><?= iq_fmt_min($m['median_min']) ?></div>
                </div>
              </div>
              <div class="mt-2 d-flex justify-content-between text-muted iq-mini">
                <span><?= xlt('Target:') ?> ≤<?= (int)$m['target_min'] ?>m</span>
                <span><?= xlt('P90:') ?> <?= iq_fmt_min($m['p90_min']) ?></span>
                <span><?= xlt('Avg:') ?> <?= iq_fmt_min($m['avg_min']) ?></span>
              </div>
            </div>
            <?php if (!empty($m['rows'])): ?>
              <div class="card-footer p-0 bg-white">
                <details>
                  <summary class="px-3 py-2 text-muted small">▶ <?= xlt('Episode drill-down') ?> (<?= count($m['rows']) ?>)</summary>
                  <div class="table-responsive">
                    <table class="table table-sm iq-table mb-0">
                      <thead class="table-light">
                      <tr>
                        <th><?= xlt('Ep') ?></th>
                        <th><?= xlt('Arrive') ?></th>
                        <th><?= xlt('Time') ?></th>
                        <?php if ($key === 'sepsis_bundle'): ?><th><?= xlt('Drug') ?></th><?php endif; ?>
                        <th><?= xlt('Met?') ?></th>
                      </tr>
                      </thead>
                      <tbody>
                      <?php foreach ($m['rows'] as $r): ?>
                        <tr>
                          <td>
                            <a href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">#<?= (int)$r['episode_id'] ?></a>
                          </td>
                          <td class="text-muted small"><?= htmlspecialchars(substr((string)$r['arrive_dt'], 0, 16)) ?></td>
                          <td class="<?= !empty($r['met']) ? 'text-success fw-semibold' : 'text-danger fw-semibold' ?>"><?= iq_fmt_min((int)$r['minutes']) ?></td>
                            <?php if ($key === 'sepsis_bundle'): ?><td><?= htmlspecialchars((string)($r['drug_name'] ?? '')) ?></td><?php endif; ?>
                          <td><?= !empty($r['met']) ? '✔' : '✖' ?></td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </details>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
</body>
</html>







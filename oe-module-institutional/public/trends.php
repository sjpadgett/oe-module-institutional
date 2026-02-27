<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Submodule\Trends\Repository\TrendRepository;

if (!$manifest->featureEnabled('trends')) {
    die(xlt('Operational Trends is disabled by manifest'));
}

$facilityId  = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$granularity = in_array($_GET['gran'] ?? '', ['week','month']) ? $_GET['gran'] : 'week';
$periods     = $granularity === 'month' ? 12 : 13;

$repo   = new TrendRepository();
$series = $repo->computeTrends($facilityId, $granularity, $periods);
$heatmap = $repo->computeHeatmap($facilityId, 90);

$href = institutional_bootstrap5_href($manifest);

// ── Build heatmap matrix [dow 1-7][hour 0-23] ────────────────────────────────
$matrix = [];
for ($d = 1; $d <= 7; $d++) {
    for ($h = 0; $h <= 23; $h++) {
        $matrix[$d][$h] = 0;
    }
}
$maxCell = 0;
foreach ($heatmap as $cell) {
    $matrix[$cell['dow']][$cell['hour']] = $cell['count'];
    $maxCell = max($maxCell, $cell['count']);
}

$dayNames = [1=>'Sun',2=>'Mon',3=>'Tue',4=>'Wed',5=>'Thu',6=>'Fri',7=>'Sat'];

// ── JSON for Chart.js ────────────────────────────────────────────────────────
$labels        = array_column($series, 'period_label');
$volumeData    = array_column($series, 'volume');
$lwbsRateData  = array_column($series, 'lwbs_rate');
$d2rData       = array_column($series, 'avg_d2r');
$d2pData       = array_column($series, 'avg_d2p');
$sepsisData    = array_column($series, 'sepsis_count');
$obsRateData   = array_column($series, 'obs_rate');

// Replace null with JS null-safe sentinel for Chart.js spanGaps
$d2rJson  = json_encode(array_map(fn($v) => $v === null ? null : (int)$v, $d2rData));
$d2pJson  = json_encode(array_map(fn($v) => $v === null ? null : (int)$v, $d2pData));

// Trend summary: compare last period to previous
$last  = !empty($series) ? end($series) : null;
$prev  = count($series) >= 2 ? $series[count($series)-2] : null;

function trend_arrow(float|int|null $curr, float|int|null $prev, bool $lowerBetter = false): string {
    if ($curr === null || $prev === null || $prev == 0) return '';
    $delta = $curr - $prev;
    $pct   = round(abs($delta / $prev * 100), 1);
    $up    = $delta > 0;
    $good  = $lowerBetter ? !$up : $up;
    $color = $good ? '#00e676' : '#ff3d57';
    $arrow = $up ? '▲' : '▼';
    return " <span style='color:{$color};font-size:.75em;'>{$arrow} {$pct}%</span>";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= xlt('Operational Trends') ?></title>
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    :root {
      --navy: #1B3A6B; --teal: #2E8B8B; --cyan: #0dcaf0;
      --card-bg: #f8fafc;
    }
    body { background: #f0f4f8; }

    .trend-kpi-strip {
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 12px;
      margin-bottom: 20px;
    }
    .trend-kpi {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 6px rgba(0,0,0,.07);
      padding: 14px 16px;
    }
    .trend-kpi-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .08em; color: #6b8899; font-weight: 600; }
    .trend-kpi-val   { font-size: 1.8rem; font-weight: 700; line-height: 1.1; color: var(--navy); }
    .trend-kpi-sub   { font-size: .78rem; color: #8899aa; margin-top: 2px; }

    .chart-card {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 6px rgba(0,0,0,.07);
      padding: 18px 20px;
      margin-bottom: 16px;
    }
    .chart-title {
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--teal);
      font-weight: 700;
      margin-bottom: 14px;
    }
    .chart-container { position: relative; }

    /* Heatmap */
    .heatmap-wrap {
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 1px 6px rgba(0,0,0,.07);
      padding: 18px 20px;
      overflow-x: auto;
    }
    .heatmap-title {
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: var(--teal);
      font-weight: 700;
      margin-bottom: 12px;
    }
    .heatmap-table {
      border-collapse: collapse;
      width: 100%;
    }
    .heatmap-table th {
      font-size: .62rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: .06em;
      color: #8899aa;
      padding: 2px 4px;
      text-align: center;
      white-space: nowrap;
    }
    .heatmap-table td {
      width: 28px;
      height: 24px;
      border-radius: 3px;
      text-align: center;
      font-size: .6rem;
      font-weight: 600;
      color: rgba(255,255,255,.85);
      transition: transform .1s;
      cursor: default;
    }
    .heatmap-table td:hover { transform: scale(1.25); z-index: 2; position: relative; }
    .heatmap-row-label {
      font-size: .7rem;
      font-weight: 700;
      color: #556677;
      padding-right: 8px;
      text-align: right;
      white-space: nowrap;
    }
    .heatmap-legend {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-top: 10px;
      font-size: .68rem;
      color: #8899aa;
    }
    .heatmap-legend-swatch {
      display: inline-block;
      width: 14px;
      height: 14px;
      border-radius: 2px;
    }

    /* Gran switcher */
    .gran-switcher { display: flex; gap: 8px; }
    .gran-btn {
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      padding: 4px 14px;
      border-radius: 4px;
      text-decoration: none;
      border: 1px solid #dee2e6;
      color: #556677;
    }
    .gran-btn.active { background: var(--navy); color: #fff; border-color: var(--navy); }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0" style="color:var(--navy);"><?= xlt('Operational Trends') ?></h1>
      <div class="text-muted small"><?= xlt('Week-over-week intelligence') ?> &bull; <?= xlt('Facility') ?> <?= $facilityId ?></div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <div class="gran-switcher">
        <a class="gran-btn <?= $granularity === 'week' ? 'active' : '' ?>"
           href="trends.php?facility_id=<?= $facilityId ?>&gran=week">Weekly</a>
        <a class="gran-btn <?= $granularity === 'month' ? 'active' : '' ?>"
           href="trends.php?facility_id=<?= $facilityId ?>&gran=month">Monthly</a>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="command_center.php?facility_id=<?= $facilityId ?>">Command Center</a>
      <a class="btn btn-sm btn-outline-secondary" href="throughput.php?facility_id=<?= $facilityId ?>">Throughput</a>
    </div>
  </div>

  <!-- KPI summary strip: compare last period to previous -->
  <div class="trend-kpi-strip">
    <div class="trend-kpi">
      <div class="trend-kpi-label">Volume (<?= $last ? htmlspecialchars($last['period_label']) : '—' ?>)</div>
      <div class="trend-kpi-val"><?= $last ? (int)$last['volume'] : '—' ?><?= trend_arrow($last['volume'] ?? null, $prev['volume'] ?? null) ?></div>
      <div class="trend-kpi-sub">prev: <?= $prev ? (int)$prev['volume'] : '—' ?></div>
    </div>
    <div class="trend-kpi">
      <div class="trend-kpi-label">LWBS Rate</div>
      <div class="trend-kpi-val"><?= $last ? $last['lwbs_rate'] . '%' : '—' ?><?= trend_arrow($last['lwbs_rate'] ?? null, $prev['lwbs_rate'] ?? null, true) ?></div>
      <div class="trend-kpi-sub">target: &lt;2%</div>
    </div>
    <div class="trend-kpi">
      <div class="trend-kpi-label">Avg D→Room</div>
      <div class="trend-kpi-val"><?= $last && $last['avg_d2r'] !== null ? $last['avg_d2r'] . 'm' : '—' ?><?= trend_arrow($last['avg_d2r'] ?? null, $prev['avg_d2r'] ?? null, true) ?></div>
      <div class="trend-kpi-sub">target: &le;30m</div>
    </div>
    <div class="trend-kpi">
      <div class="trend-kpi-label">Avg D→Provider</div>
      <div class="trend-kpi-val"><?= $last && $last['avg_d2p'] !== null ? $last['avg_d2p'] . 'm' : '—' ?><?= trend_arrow($last['avg_d2p'] ?? null, $prev['avg_d2p'] ?? null, true) ?></div>
      <div class="trend-kpi-sub">target: &le;60m</div>
    </div>
    <div class="trend-kpi">
      <div class="trend-kpi-label">OBS Rate</div>
      <div class="trend-kpi-val"><?= $last ? $last['obs_rate'] . '%' : '—' ?><?= trend_arrow($last['obs_rate'] ?? null, $prev['obs_rate'] ?? null) ?></div>
      <div class="trend-kpi-sub">prev: <?= $prev ? $prev['obs_rate'] . '%' : '—' ?></div>
    </div>
    <div class="trend-kpi">
      <div class="trend-kpi-label">Sepsis Cases</div>
      <div class="trend-kpi-val"><?= $last ? (int)$last['sepsis_count'] : '—' ?><?= trend_arrow($last['sepsis_count'] ?? null, $prev['sepsis_count'] ?? null, true) ?></div>
      <div class="trend-kpi-sub">qSOFA &ge;2</div>
    </div>
  </div>

  <?php if (empty($series)): ?>
    <div class="chart-card text-center text-muted py-5">
        <?= xlt('No episode data found in this date range. Run demo_seed.sql or create episodes to populate trends.') ?>
    </div>
  <?php else: ?>

  <!-- Row 1: Volume + LWBS Rate -->
  <div class="row g-3 mb-0">
    <div class="col-12 col-xl-7">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('Patient Volume') ?></div>
        <div class="chart-container" style="height:200px;">
          <canvas id="chartVolume"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-5">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('LWBS Rate (%)') ?></div>
        <div class="chart-container" style="height:200px;">
          <canvas id="chartLwbs"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Row 2: D2R + D2P -->
  <div class="row g-3 mb-0 mt-0">
    <div class="col-12 col-xl-6">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('Door-to-Room (avg min)') ?></div>
        <div class="chart-container" style="height:180px;">
          <canvas id="chartD2r"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('Door-to-Provider (avg min)') ?></div>
        <div class="chart-container" style="height:180px;">
          <canvas id="chartD2p"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Row 3: Sepsis + OBS Rate -->
  <div class="row g-3 mb-3 mt-0">
    <div class="col-12 col-xl-5">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('Sepsis Risk Cases (qSOFA ≥2)') ?></div>
        <div class="chart-container" style="height:180px;">
          <canvas id="chartSepsis"></canvas>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="chart-card">
        <div class="chart-title"><?= xlt('OBS Conversion Rate (%)') ?></div>
        <div class="chart-container" style="height:180px;">
          <canvas id="chartObs"></canvas>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>

  <!-- Heatmap: always show even with sparse data -->
  <div class="heatmap-wrap mb-3">
    <div class="heatmap-title"><?= xlt('Patient Volume Heatmap') ?> &mdash; <?= xlt('Last 90 Days by Day &amp; Hour') ?></div>
    <?php if ($maxCell === 0): ?>
      <p class="text-muted small"><?= xlt('No episode data in the last 90 days. Run demo_seed.sql or add episodes to populate the heatmap.') ?></p>
    <?php else: ?>
    <table class="heatmap-table">
      <thead>
        <tr>
          <th></th>
          <?php for ($h = 0; $h <= 23; $h++): ?>
            <th><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></th>
          <?php endfor; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($dayNames as $dow => $dayName): ?>
        <tr>
          <td class="heatmap-row-label"><?= $dayName ?></td>
            <?php for ($h = 0; $h <= 23; $h++):
                $count = $matrix[$dow][$h];
                $intensity = $maxCell > 0 ? $count / $maxCell : 0;
            // Navy → teal → cyan gradient
                if ($intensity === 0) {
                    $bg = '#f0f4f8';
                    $color = '#ccc';
                } else {
                    // Lerp: low = navy #1B3A6B, mid = teal #2E8B8B, high = cyan #00d4ff
                    if ($intensity < 0.5) {
                        $t = $intensity * 2;
                        $r = (int)(0x1B + ($t) * (0x2E - 0x1B));
                        $g = (int)(0x3A + ($t) * (0x8B - 0x3A));
                        $b = (int)(0x6B + ($t) * (0x8B - 0x6B));
                    } else {
                        $t = ($intensity - 0.5) * 2;
                        $r = (int)(0x2E + ($t) * (0x00 - 0x2E));
                        $g = (int)(0x8B + ($t) * (0xD4 - 0x8B));
                        $b = (int)(0x8B + ($t) * (0xFF - 0x8B));
                    }
                    $bg    = sprintf('rgb(%d,%d,%d)', $r, $g, $b);
                    $color = $intensity > 0.5 ? '#fff' : 'rgba(255,255,255,.8)';
                }
                ?>
            <td style="background:<?= $bg ?>;color:<?= $color ?>;"
                title="<?= $dayName ?> <?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?>:00 — <?= $count ?> arrivals">
                <?= $count > 0 ? $count : '' ?>
            </td>
          <?php endfor; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="heatmap-legend">
      <span>Low</span>
        <?php
        $swatches = [0.1, 0.25, 0.5, 0.75, 1.0];
        foreach ($swatches as $s):
            if ($s < 0.5) {
                $t=$s*2; $r=intval(0x1B+$t*(0x2E-0x1B)); $g=intval(0x3A+$t*(0x8B-0x3A)); $b=intval(0x6B+$t*(0x8B-0x6B));
            } else {
                $t=($s-.5)*2; $r=intval(0x2E+$t*(0x00-0x2E)); $g=intval(0x8B+$t*(0xD4-0x8B)); $b=intval(0x8B+$t*(0xFF-0x8B));
            }
            $sc = sprintf('rgb(%d,%d,%d)',$r,$g,$b);
            ?>
        <span class="heatmap-legend-swatch" style="background:<?= $sc ?>;"></span>
      <?php endforeach; ?>
      <span>High (<?= $maxCell ?> arrivals)</span>
      <span class="ms-3 text-muted" style="font-size:.65rem;"><?= xlt('Hover cell for detail. Day 1 = Sunday. Hour = arrival hour.') ?></span>
    </div>
    <?php endif; ?>
  </div>

</div><!-- /container -->

<script>
const labels = <?= json_encode($labels) ?>;
const volumeData  = <?= json_encode($volumeData) ?>;
const lwbsData    = <?= json_encode($lwbsRateData) ?>;
const d2rData     = <?= $d2rJson ?>;
const d2pData     = <?= $d2pJson ?>;
const sepsisData  = <?= json_encode($sepsisData) ?>;
const obsRateData = <?= json_encode($obsRateData) ?>;

const NAVY  = '#1B3A6B';
const TEAL  = '#2E8B8B';
const CYAN  = '#0dcaf0';
const RED   = '#dc3545';
const AMBER = '#fd7e14';
const GREEN = '#198754';

const baseOpts = (color, fill) => ({
    responsive: true,
    maintainAspectRatio: false,
    spanGaps: true,
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
    scales: {
        x: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 10 }, maxRotation: 45 } },
        y: { grid: { color: 'rgba(0,0,0,.05)' }, ticks: { font: { size: 11 } }, beginAtZero: true }
    }
});

function lineChart(id, data, color, fill, yLabel) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data,
                borderColor: color,
                backgroundColor: fill || color + '22',
                borderWidth: 2,
                pointRadius: 3,
                pointBackgroundColor: color,
                fill: !!fill,
                tension: 0.35
            }]
        },
        options: baseOpts(color, fill)
    });
}

function barChart(id, data, color) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data,
                backgroundColor: color + 'cc',
                borderColor: color,
                borderWidth: 1,
                borderRadius: 3
            }]
        },
        options: baseOpts(color, false)
    });
}

// Annotated line: draw target reference line
function lineWithTarget(id, data, color, target, targetLabel) {
    const ctx = document.getElementById(id);
    if (!ctx) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels,
            datasets: [
                {
                    data,
                    borderColor: color,
                    backgroundColor: color + '22',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: color,
                    fill: true,
                    tension: 0.35,
                    spanGaps: true
                },
                {
                    data: labels.map(() => target),
                    borderColor: '#dc354566',
                    borderDash: [6, 4],
                    borderWidth: 1.5,
                    pointRadius: 0,
                    fill: false,
                    label: targetLabel
                }
            ]
        },
        options: {
            ...baseOpts(color, true),
            plugins: {
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            }
        }
    });
}

barChart('chartVolume', volumeData, NAVY);
lineChart('chartLwbs', lwbsData, RED, true);
lineWithTarget('chartD2r', d2rData, TEAL, 30, 'Target 30m');
lineWithTarget('chartD2p', d2pData, '#6f42c1', 60, 'Target 60m');
barChart('chartSepsis', sepsisData, '#dc3545');
lineChart('chartObs', obsRateData, AMBER, true);
</script>
</body>
</html>



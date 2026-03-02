<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Shared\Submodule\Trends\Controller\TrendsController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Trends\Repository\TrendRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Trends\Service\TrendsService;

if (!$manifest->featureEnabled('trends')) {
    die(xlt('Operational Trends is disabled by manifest'));
}

$facilityId  = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$granularity = in_array($_GET['gran'] ?? '', ['week', 'month']) ? $_GET['gran'] : 'week';
$periods     = $granularity === 'month' ? 12 : 13;

$controller = new TrendsController(
    new TrendsService(new TrendRepository())
);
$vm = $controller->handle($facilityId, $granularity, $periods);

// Unpack view model
$series    = $vm['series'];
$chartJson = $vm['chartJson'];
$matrix    = $vm['heatmap'];
$maxCell   = $vm['heatmapMax'];
$dayNames  = $vm['dayNames'];
$summary   = $vm['summary'];

$href = institutional_bootstrap5_href($manifest);

// ── Trend summary helpers ────────────────────────────────────────────────────
function trend_arrow(mixed $delta, bool $lowerIsBetter = false): string
{
    if ($delta === null) {
        return '<span class="text-muted">—</span>';
    }
    if ($delta == 0) {
        return '<span class="text-muted">→</span>';
    }
    $up = $delta > 0;
    if ($lowerIsBetter) {
        $cls  = $up ? 'text-danger' : 'text-success';
        $icon = $up ? '↑' : '↓';
    } else {
        $cls  = $up ? 'text-success' : 'text-danger';
        $icon = $up ? '↑' : '↓';
    }
    return "<span class='{$cls}'>{$icon} " . htmlspecialchars((string)abs($delta)) . "</span>";
}

function trend_val(mixed $v, string $suffix = ''): string
{
    return $v === null ? '<span class="text-muted">—</span>' : htmlspecialchars((string)$v) . $suffix;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Operational Trends') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Operational Trends') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm <?= $granularity === 'week' ? 'btn-primary' : 'btn-outline-secondary' ?>"
         href="?facility_id=<?= urlencode((string)$facilityId) ?>&gran=week"><?= xlt('Weekly') ?></a>
      <a class="btn btn-sm <?= $granularity === 'month' ? 'btn-primary' : 'btn-outline-secondary' ?>"
         href="?facility_id=<?= urlencode((string)$facilityId) ?>&gran=month"><?= xlt('Monthly') ?></a>
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
    </div>
  </div>

  <!-- Period-over-period summary -->
  <?php if (!empty($summary)): ?>
  <div class="row g-2 mb-3">
        <?php
        $summaryCards = [
        ['key' => 'volume',    'label' => xlt('Volume'),      'suffix' => '',   'lower' => false],
        ['key' => 'lwbs_rate', 'label' => xlt('LWBS Rate'),   'suffix' => '%',  'lower' => true],
        ['key' => 'avg_d2r',   'label' => xlt('Avg D2R'),     'suffix' => 'm',  'lower' => true],
        ['key' => 'avg_d2p',   'label' => xlt('Avg D2P'),     'suffix' => 'm',  'lower' => true],
        ['key' => 'sepsis',    'label' => xlt('Sepsis Risk'),  'suffix' => '',   'lower' => true],
        ['key' => 'obs_rate',  'label' => xlt('OBS Rate'),    'suffix' => '%',  'lower' => false],
        ];
        foreach ($summaryCards as $card):
            $s = $summary[$card['key']] ?? null;
            if (!$s) continue;
            ?>
      <div class="col-6 col-md-2">
        <div class="card shadow-sm">
          <div class="card-body p-2">
            <div class="text-muted small"><?= $card['label'] ?></div>
            <div class="h5 mb-0"><?= trend_val($s['current'], $card['suffix']) ?></div>
            <div class="small"><?= trend_arrow($s['delta'], $card['lower']) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Volume + LWBS chart -->
  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt('ED Volume & LWBS Rate') ?></div>
    <div class="card-body">
      <canvas id="volumeChart" height="80"></canvas>
    </div>
  </div>

  <!-- D2R / D2P chart -->
  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt('Door-to-Room / Door-to-Provider (minutes)') ?></div>
    <div class="card-body">
      <canvas id="intervalChart" height="80"></canvas>
    </div>
  </div>

  <!-- Sepsis + OBS rate chart -->
  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt('Sepsis Risk Count & OBS Rate') ?></div>
    <div class="card-body">
      <canvas id="clinicalChart" height="80"></canvas>
    </div>
  </div>

  <!-- Heatmap -->
  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt('Arrival Volume Heatmap (90 days)') ?></div>
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0" style="font-size:.7rem;">
          <thead>
            <tr>
              <th style="width:40px;"></th>
              <?php for ($h = 0; $h <= 23; $h++): ?>
                <th class="text-center p-0" style="min-width:28px;"><?= str_pad((string)$h, 2, '0', STR_PAD_LEFT) ?></th>
              <?php endfor; ?>
            </tr>
          </thead>
          <tbody>
            <?php for ($d = 1; $d <= 7; $d++): ?>
              <tr>
                <td class="fw-semibold text-nowrap p-1"><?= htmlspecialchars($dayNames[$d]) ?></td>
                <?php for ($h = 0; $h <= 23; $h++): ?>
                    <?php
                    $cnt   = $matrix[$d][$h] ?? 0;
                    $alpha = $maxCell > 0 ? round($cnt / $maxCell, 2) : 0;
                    $bg    = $cnt > 0 ? "rgba(13,110,253,{$alpha})" : '';
                    $color = $alpha > 0.5 ? '#fff' : '#212529';
                    ?>
                  <td class="text-center p-0"
                      style="background:<?= $bg ?>; color:<?= $color ?>; font-size:.65rem;">
                    <?= $cnt > 0 ? $cnt : '' ?>
                  </td>
                <?php endfor; ?>
              </tr>
            <?php endfor; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<script>
const labels    = <?= $chartJson['labels'] ?>;
const volume    = <?= $chartJson['volume'] ?>;
const lwbsRate  = <?= $chartJson['lwbsRate'] ?>;
const d2r       = <?= $chartJson['d2r'] ?>;
const d2p       = <?= $chartJson['d2p'] ?>;
const sepsis    = <?= $chartJson['sepsis'] ?>;
const obsRate   = <?= $chartJson['obsRate'] ?>;

const sharedOpts = {
    responsive: true,
    plugins: { legend: { position: 'top' } },
    scales: { x: { ticks: { maxRotation: 45 } } }
};

new Chart(document.getElementById('volumeChart'), {
    data: {
        labels,
        datasets: [
            { type: 'bar',  label: '<?= xlt('Volume') ?>',    data: volume,   backgroundColor: 'rgba(13,110,253,.25)', borderColor: 'rgba(13,110,253,.8)', borderWidth: 1, yAxisID: 'y' },
            { type: 'line', label: '<?= xlt('LWBS %') ?>',    data: lwbsRate, borderColor: '#dc3545', tension: .3, yAxisID: 'y2', spanGaps: true }
        ]
    },
    options: { ...sharedOpts, scales: { x: sharedOpts.scales.x, y: { beginAtZero: true }, y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } }
});

new Chart(document.getElementById('intervalChart'), {
    data: {
        labels,
        datasets: [
            { type: 'line', label: '<?= xlt('D2R (min)') ?>',  data: d2r, borderColor: '#0dcaf0', tension: .3, spanGaps: true },
            { type: 'line', label: '<?= xlt('D2P (min)') ?>',  data: d2p, borderColor: '#fd7e14', tension: .3, spanGaps: true }
        ]
    },
    options: { ...sharedOpts, scales: { x: sharedOpts.scales.x, y: { beginAtZero: true } } }
});

new Chart(document.getElementById('clinicalChart'), {
    data: {
        labels,
        datasets: [
            { type: 'bar',  label: '<?= xlt('Sepsis Risk') ?>', data: sepsis,  backgroundColor: 'rgba(220,53,69,.3)', borderColor: '#dc3545', borderWidth: 1, yAxisID: 'y' },
            { type: 'line', label: '<?= xlt('OBS Rate %') ?>', data: obsRate, borderColor: '#6f42c1', tension: .3, yAxisID: 'y2', spanGaps: true }
        ]
    },
    options: { ...sharedOpts, scales: { x: sharedOpts.scales.x, y: { beginAtZero: true }, y2: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false } } } }
});
</script>
</body>
</html>

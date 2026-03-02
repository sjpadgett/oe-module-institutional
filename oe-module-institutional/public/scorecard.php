<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Submodule\Scorecard\Repository\ScorecardRepository;
use OpenEMR\Modules\Institutional\Submodule\Scorecard\Service\ScorecardService;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('scorecard')) {
    die(xlt('Provider Scorecard is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$sortBy     = in_array($_GET['sort'] ?? '', ['volume','d2r','d2p','d2d','lwbs_rate','obs_rate'], true)
    ? $_GET['sort']
    : 'volume';

// Date range — default last 30 days
$dateTo   = $_GET['date_to']   ?? date('Y-m-d');
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));

$repo    = new ScorecardRepository();
$service = new ScorecardService($repo, new SettingsRepository());
$data    = $service->build($facilityId, $dateFrom, $dateTo, $sortBy);

$href = institutional_bootstrap5_href($manifest);

// ── Helpers ──────────────────────────────────────────────────────────────────

/** Format minutes — returns "—" if null, "Xm" if < 60, "Xh Ym" if >= 60 */
function fmtMin(?float $v): string
{
    if ($v === null) return '<span class="text-muted">—</span>';
    $m = (int)round($v);
    if ($m < 60) return $m . 'm';
    return floor($m / 60) . 'h ' . ($m % 60) . 'm';
}

/** Format hours */
function fmtHour(?float $v): string
{
    if ($v === null) return '<span class="text-muted">—</span>';
    return number_format($v, 1) . 'h';
}

/** CSS class when a metric exceeds its target */
function metricClass(?float $val, ?float $target, bool $lowerIsBetter = true): string
{
    if ($val === null || $target === null) return '';
    if ($lowerIsBetter) {
        return $val > $target * 1.25 ? 'text-danger fw-bold'
             : ($val > $target       ? 'text-warning fw-semibold' : 'text-success');
    }
    return '';
}

/** Build mini sparkline data-attribute from daily volume map */
function sparkline(array $daily, int $uid, string $dateFrom, string $dateTo): string
{
    $period = new DatePeriod(
        new DateTime($dateFrom),
        new DateInterval('P1D'),
        (new DateTime($dateTo))->modify('+1 day')
    );
    $vals = [];
    foreach ($period as $d) {
        $key    = $d->format('Y-m-d');
        $vals[] = $daily[$uid][$key] ?? 0;
    }
    return htmlspecialchars(implode(',', $vals));
}

$providers  = $data['providers'];
$names      = $data['names'];
$benchmarks = $data['benchmarks'];
$targets    = $data['targets'];
$daily      = $data['daily'];

$sortLink = fn(string $col, string $label): string =>
    '<a href="scorecard.php?facility_id=' . urlencode((string)$facilityId)
    . '&date_from=' . urlencode($dateFrom)
    . '&date_to='   . urlencode($dateTo)
    . '&sort='      . urlencode($col)
    . '" class="text-decoration-none ' . ($sortBy === $col ? 'fw-bold text-primary' : 'text-secondary') . '">'
    . htmlspecialchars($label)
    . ($sortBy === $col ? ' ▾' : '')
    . '</a>';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Provider Scorecard') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .sparkline { display: inline-block; width: 80px; height: 28px; vertical-align: middle; }
    .esi-bar   { display: inline-flex; gap: 2px; align-items: flex-end; height: 20px; }
    .esi-bar span { display: inline-block; width: 8px; background: #6c757d; border-radius: 2px; }
    .esi-1 { background: #dc3545 !important; }
    .esi-2 { background: #fd7e14 !important; }
    .esi-3 { background: #ffc107 !important; }
    .esi-4 { background: #20c997 !important; }
    .esi-5 { background: #0dcaf0 !important; }
    .bench-card { border-top: 3px solid #0d6efd; }
    th a { white-space: nowrap; }
    .provider-row:hover { background: #f8f9ff !important; }
  </style>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><?= xlt('Provider Scorecard') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="throughput.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Throughput') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
    </div>
  </div>

  <!-- Date range filter -->
  <form method="get" class="card shadow-sm mb-3 p-3">
    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
    <input type="hidden" name="sort"        value="<?= htmlspecialchars($sortBy) ?>">
    <div class="row g-2 align-items-end">
      <div class="col-auto">
        <label class="form-label small mb-1"><?= xlt('From') ?></label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($dateFrom) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label small mb-1"><?= xlt('To') ?></label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($dateTo) ?>">
      </div>
      <div class="col-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm"><?= xlt('Apply') ?></button>
        <?php
        $quickLinks = [
          xlt('7d')  => [date('Y-m-d', strtotime('-7 days')),  date('Y-m-d')],
          xlt('30d') => [date('Y-m-d', strtotime('-30 days')), date('Y-m-d')],
          xlt('90d') => [date('Y-m-d', strtotime('-90 days')), date('Y-m-d')],
        ];
        foreach ($quickLinks as $label => [$f, $t]):
            ?>
          <a class="btn btn-outline-secondary btn-sm"
             href="scorecard.php?facility_id=<?= urlencode((string)$facilityId) ?>&date_from=<?= urlencode($f) ?>&date_to=<?= urlencode($t) ?>&sort=<?= urlencode($sortBy) ?>">
            <?= $label ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="col-auto ms-auto text-muted small">
        <?= htmlspecialchars($dateFrom) ?> → <?= htmlspecialchars($dateTo) ?>
        &bull; <?= (int)$benchmarks['total_volume'] ?> <?= xlt('total episodes') ?>
      </div>
    </div>
  </form>

  <!-- Facility benchmarks -->
  <div class="row g-3 mb-4">
    <?php
    $bCards = [
      [xlt('Facility Avg D→Room'),     fmtMin($benchmarks['avg_d2r']),  $targets['d2r'] . 'm ' . xlt('target')],
      [xlt('Facility Avg D→Provider'), fmtMin($benchmarks['avg_d2p']),  $targets['d2p'] . 'm ' . xlt('target')],
      [xlt('Facility Avg D→Decision'), fmtMin($benchmarks['avg_d2d']),  null],
      [xlt('Facility Avg D→Depart'),   fmtMin($benchmarks['avg_d2dc']), null],
      [xlt('LWBS Rate'),  $benchmarks['lwbs_rate'] . '%', $benchmarks['total_lwbs'] . ' ' . xlt('episodes')],
      [xlt('Obs Rate'),   $benchmarks['obs_rate']  . '%', $benchmarks['total_obs']  . ' ' . xlt('episodes')],
    ];
    foreach ($bCards as [$label, $value, $sub]):
        ?>
    <div class="col-6 col-md-4 col-xl-2">
      <div class="card bench-card h-100 py-2 px-3 text-center">
        <div class="fw-bold fs-5"><?= $value ?></div>
        <div class="small text-muted"><?= $label ?></div>
        <?php if ($sub !== null): ?>
          <div class="text-muted" style="font-size:.72rem"><?= $sub ?></div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Provider table -->
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Provider Metrics') ?> — <?= count($providers) ?> <?= xlt('providers') ?></span>
      <span class="text-muted small"><?= xlt('Click column headers to sort') ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light" style="font-size:.8rem;">
          <tr>
            <th><?= $sortLink('name',      xlt('Provider')) ?></th>
            <th class="text-center"><?= $sortLink('volume',    xlt('Visits')) ?></th>
            <th class="text-center"><?= $sortLink('d2r',       xlt('D→Room')) ?></th>
            <th class="text-center"><?= $sortLink('d2p',       xlt('D→Provider')) ?></th>
            <th class="text-center"><?= $sortLink('d2d',       xlt('D→Decision')) ?></th>
            <th class="text-center"><?= xlt('D→Depart') ?></th>
            <th class="text-center"><?= $sortLink('lwbs_rate', xlt('LWBS')) ?></th>
            <th class="text-center"><?= $sortLink('obs_rate',  xlt('Obs Rate')) ?></th>
            <th class="text-center"><?= xlt('Avg Obs Stay') ?></th>
            <th class="text-center"><?= xlt('ESI Mix') ?></th>
            <th class="text-center"><?= xlt('Volume Trend') ?></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($providers)): ?>
          <tr><td colspan="11" class="text-center text-muted py-4">
            <?= xlt('No episodes with assigned providers in the selected date range.') ?>
          </td></tr>
        <?php endif; ?>

        <?php foreach ($providers as $uid => $m):
            $name = $names[$uid] ?? 'Provider #' . $uid;
            $esi  = $m['esi_dist'];
            $maxEsi = max(array_filter($esi)) ?: 1;
            ?>
          <tr class="provider-row">
            <td class="fw-semibold"><?= htmlspecialchars($name) ?></td>
            <td class="text-center"><?= (int)$m['volume'] ?></td>

            <!-- D→Room -->
            <td class="text-center <?= metricClass($m['avg_d2r'], $targets['d2r']) ?>">
              <?= fmtMin($m['avg_d2r']) ?>
              <?php if ($m['avg_d2r'] !== null && $benchmarks['avg_d2r'] !== null): ?>
                    <?php $diff = $m['avg_d2r'] - $benchmarks['avg_d2r']; ?>
                <span class="d-block" style="font-size:.68rem; color:<?= $diff > 0 ? '#dc3545' : '#198754' ?>">
                    <?= $diff > 0 ? '+' : '' ?><?= round($diff) ?>m vs avg
                </span>
              <?php endif; ?>
            </td>

            <!-- D→Provider -->
            <td class="text-center <?= metricClass($m['avg_d2p'], $targets['d2p']) ?>">
              <?= fmtMin($m['avg_d2p']) ?>
              <?php if ($m['avg_d2p'] !== null && $benchmarks['avg_d2p'] !== null): ?>
                    <?php $diff = $m['avg_d2p'] - $benchmarks['avg_d2p']; ?>
                <span class="d-block" style="font-size:.68rem; color:<?= $diff > 0 ? '#dc3545' : '#198754' ?>">
                    <?= $diff > 0 ? '+' : '' ?><?= round($diff) ?>m vs avg
                </span>
              <?php endif; ?>
            </td>

            <!-- D→Decision -->
            <td class="text-center"><?= fmtMin($m['avg_d2d']) ?></td>

            <!-- D→Depart -->
            <td class="text-center"><?= fmtMin($m['avg_d2dc']) ?></td>

            <!-- LWBS -->
            <td class="text-center <?= $m['lwbs_rate'] > 3 ? 'text-danger fw-bold' : ($m['lwbs_rate'] > 1 ? 'text-warning fw-semibold' : '') ?>">
              <?= number_format($m['lwbs_rate'], 1) ?>%
              <span class="d-block text-muted" style="font-size:.68rem"><?= (int)$m['lwbs_count'] ?> ep</span>
            </td>

            <!-- Obs Rate -->
            <td class="text-center"><?= number_format($m['obs_rate'], 1) ?>%</td>

            <!-- Avg Obs Stay -->
            <td class="text-center"><?= fmtHour($m['avg_obs_stay_h'] ?? null) ?></td>

            <!-- ESI Mix sparkbar -->
            <td class="text-center">
              <div class="esi-bar" title="ESI 1:<?= $esi[1] ?> 2:<?= $esi[2] ?> 3:<?= $esi[3] ?> 4:<?= $esi[4] ?> 5:<?= $esi[5] ?>">
                <?php for ($e = 1; $e <= 5; $e++):
                    $h = $esi[$e] > 0 ? max(4, (int)round($esi[$e] / $maxEsi * 18)) : 2;
                    ?>
                  <span class="esi-<?= $e ?>" style="height:<?= $h ?>px;"
                        title="ESI <?= $e ?>: <?= $esi[$e] ?>"></span>
                <?php endfor; ?>
              </div>
              <div style="font-size:.66rem; color:#6c757d;">
                <?php for ($e = 1; $e <= 5; $e++): ?>
                    <?php if ($esi[$e] > 0): ?><span><?= $e ?>:<?= $esi[$e] ?></span> <?php endif; ?>
                <?php endfor; ?>
              </div>
            </td>

            <!-- Volume trend sparkline (drawn by JS) -->
            <td class="text-center">
              <canvas class="sparkline" data-vals="<?= sparkline($daily, $uid, $dateFrom, $dateTo) ?>"></canvas>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="card-footer text-muted small">
      <?= xlt('D→Room, D→Provider, D→Decision, D→Depart are averages in minutes. Green = at or below target, amber = up to 25% over, red = more than 25% over. ESI bars show acuity distribution. Trend shows daily volume over the selected period.') ?>
    </div>
  </div>

</div><!-- /container -->

<script>
// Draw sparklines using Canvas API — no library needed
document.querySelectorAll('canvas.sparkline').forEach(function(canvas) {
    const vals = (canvas.dataset.vals || '').split(',').map(Number).filter(v => !isNaN(v));
    if (!vals.length) return;

    const ctx    = canvas.getContext('2d');
    const W      = canvas.offsetWidth  || 80;
    const H      = canvas.offsetHeight || 28;
    canvas.width  = W;
    canvas.height = H;

    const max = Math.max(...vals, 1);
    const min = 0;
    const step = W / Math.max(vals.length - 1, 1);

    ctx.beginPath();
    vals.forEach((v, i) => {
        const x = i * step;
        const y = H - ((v - min) / (max - min)) * (H - 4) - 2;
        i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
    });
    ctx.strokeStyle = '#0d6efd';
    ctx.lineWidth   = 1.5;
    ctx.stroke();

    // Fill area under line
    ctx.lineTo((vals.length - 1) * step, H);
    ctx.lineTo(0, H);
    ctx.closePath();
    ctx.fillStyle = 'rgba(13,110,253,0.10)';
    ctx.fill();
});
</script>
</body>
</html>

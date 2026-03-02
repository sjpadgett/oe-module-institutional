<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\ObservationStay\Submodule\CmsQuality\Repository\CmsMeasureRepository;

if (!$manifest->featureEnabled('cms_quality')) {
    die(xlt('CMS Quality Dashboard is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$dateFrom   = (string)($_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days')));
$dateTo     = (string)($_GET['date_to']   ?? date('Y-m-d'));

// Clamp dates
$dateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) ? $dateFrom : date('Y-m-d', strtotime('-30 days'));
$dateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)   ? $dateTo   : date('Y-m-d');

$repo     = new CmsMeasureRepository();
$measures = $repo->computeAll($facilityId, $dateFrom, $dateTo);

$href = institutional_bootstrap5_href($manifest);

// ── helpers ──────────────────────────────────────────────────────────────────

function fmt_min(?int $min): string
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

/**
 * Performance tier:
 *   EXCELLENT   rate >= 90%
 *   GOOD        rate >= 75%
 *   FAIR        rate >= 50%
 *   POOR        rate <  50%
 *   N/A         no data
 */
function rate_tier(?float $rate): string
{
    if ($rate === null) {
        return 'N/A';
    }
    if ($rate >= 90) {
        return 'EXCELLENT';
    }
    if ($rate >= 75) {
        return 'GOOD';
    }
    if ($rate >= 50) {
        return 'FAIR';
    }
    return 'POOR';
}

function tier_badge(string $tier): string
{
    return match ($tier) {
        'EXCELLENT' => 'text-bg-success',
        'GOOD'      => 'text-bg-primary',
        'FAIR'      => 'text-bg-warning text-dark',
        'POOR'      => 'text-bg-danger',
        default     => 'text-bg-secondary',
    };
}

/**
 * Build SVG gauge arc (semi-circle) for a rate value.
 * Returns inline SVG string.
 */
function rate_gauge(?float $rate): string
{
    $pct = $rate ?? 0;
    // Clamp
    $pct = max(0, min(100, $pct));

    // Arc from -180deg to 0deg represents 0→100%
    $angle   = ($pct / 100) * 180 - 180; // degrees from left
    $rad     = $angle * M_PI / 180;
    $cx = 60; $cy = 60; $r = 48;
    $x = $cx + $r * cos($rad);
    $y = $cy + $r * sin($rad);

    $fillColor = $pct >= 90 ? '#198754' : ($pct >= 75 ? '#0d6efd' : ($pct >= 50 ? '#ffc107' : '#dc3545'));
    $trackColor = '#e9ecef';

    // Large arc flag for SVG arc
    $largeArc = $pct > 50 ? 1 : 0;

    ob_start(); ?>
    <svg viewBox="0 0 120 65" width="120" height="65" xmlns="http://www.w3.org/2000/svg">
      <!-- Track arc (grey) -->
      <path d="M12,60 A48,48 0 0,1 108,60"
            fill="none" stroke="<?= $trackColor ?>" stroke-width="10" stroke-linecap="round"/>
      <?php if ($pct > 0): ?>
      <!-- Fill arc (coloured) -->
      <path d="M12,60 A48,48 0 <?= $largeArc ?>,1 <?= round($x, 2) ?>,<?= round($y, 2) ?>"
            fill="none" stroke="<?= $fillColor ?>" stroke-width="10" stroke-linecap="round"/>
      <?php endif; ?>
      <!-- Rate text -->
      <text x="60" y="58" text-anchor="middle" font-size="16" font-weight="bold"
            fill="<?= $rate === null ? '#6c757d' : $fillColor ?>">
        <?= $rate !== null ? round($pct, 0) . '%' : '—' ?>
      </text>
    </svg>
    <?php
    return ob_get_clean();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('CMS Quality Measures') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .measure-card { transition: box-shadow .15s; }
    .measure-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.12) !important; }
    .kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; color: #6c757d; }
    .kpi-val   { font-size: 1.5rem; font-weight: 700; line-height: 1.1; }
    .target-line { font-size: .8rem; }
    .drill-table th { font-size: .73rem; text-transform: uppercase; letter-spacing: .04em; }
    .drill-table td { font-size: .82rem; }
    .row-met    { background: rgba(25,135,84,.07); }
    .row-missed { background: rgba(220,53,69,.07); }
    details > summary { cursor: pointer; list-style: none; }
    details > summary::-webkit-details-marker { display: none; }
  </style>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0"><?= xlt('CMS Quality Measures') ?></h1>
      <div class="text-muted small"><?= xlt('Pay-for-performance tracking') ?></div>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="throughput.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Throughput') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="scorecard.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Scorecard') ?></a>
    </div>
  </div>

  <!-- Date range filter -->
  <form method="get" class="row g-2 mb-4 align-items-end">
    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
    <div class="col-auto">
      <label class="form-label small"><?= xlt('From') ?></label>
      <input type="date" name="date_from" class="form-control form-control-sm"
             value="<?= htmlspecialchars($dateFrom) ?>">
    </div>
    <div class="col-auto">
      <label class="form-label small"><?= xlt('To') ?></label>
      <input type="date" name="date_to" class="form-control form-control-sm"
             value="<?= htmlspecialchars($dateTo) ?>">
    </div>
    <div class="col-auto d-flex gap-2">
      <button class="btn btn-primary btn-sm"><?= xlt('Apply') ?></button>
      <?php
        $quick = [
          '7d'  => [date('Y-m-d', strtotime('-7 days')),   date('Y-m-d')],
          '30d' => [date('Y-m-d', strtotime('-30 days')),  date('Y-m-d')],
          '90d' => [date('Y-m-d', strtotime('-90 days')),  date('Y-m-d')],
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

  <!-- CMS measure cards -->
  <div class="row g-3 mb-4">
    <?php foreach ($measures as $key => $m):
        $tier  = rate_tier($m['rate_pct']);
        $badge = tier_badge($tier);
        ?>
    <div class="col-12 col-md-6 col-xl-3">
      <div class="card shadow-sm measure-card h-100">
        <div class="card-body">
          <div class="d-flex align-items-start justify-content-between mb-2">
            <div>
              <div class="fw-semibold"><?= htmlspecialchars($m['label']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($m['cms_id']) ?></div>
            </div>
            <span class="badge <?= $badge ?>"><?= htmlspecialchars($tier) ?></span>
          </div>

          <div class="text-center mb-2">
            <?= rate_gauge($m['rate_pct']) ?>
          </div>

          <div class="row g-2 text-center">
            <div class="col-4">
              <div class="kpi-label"><?= xlt('N') ?></div>
              <div class="kpi-val"><?= (int)$m['n'] ?></div>
            </div>
            <div class="col-4">
              <div class="kpi-label"><?= xlt('Met') ?></div>
              <div class="kpi-val text-success"><?= (int)$m['n_met'] ?></div>
            </div>
            <div class="col-4">
              <div class="kpi-label"><?= xlt('Median') ?></div>
              <div class="kpi-val"><?= fmt_min($m['median_min']) ?></div>
            </div>
          </div>

          <div class="mt-2 target-line d-flex justify-content-between text-muted">
            <span><?= xlt('Target:') ?> ≤<?= (int)$m['target_min'] ?>m</span>
            <span><?= xlt('P90:') ?> <?= fmt_min($m['p90_min']) ?></span>
            <span><?= xlt('Avg:') ?> <?= fmt_min($m['avg_min']) ?></span>
          </div>
        </div>

        <?php if (!empty($m['rows'])): ?>
        <div class="card-footer p-0">
          <details>
            <summary class="px-3 py-2 text-muted small" style="cursor:pointer;">
              ▶ <?= xlt('Episode drill-down') ?> (<?= count($m['rows']) ?>)
            </summary>
            <div class="table-responsive">
              <table class="table table-sm drill-table mb-0">
                <thead class="table-light">
                  <tr>
                    <th><?= xlt('Ep') ?></th>
                    <th><?= xlt('Arrive') ?></th>
                    <th><?= xlt('Time') ?></th>
                    <?php if ($key === 'sepsis_bundle'): ?>
                      <th><?= xlt('Drug') ?></th>
                    <?php endif; ?>
                    <th><?= xlt('Met?') ?></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($m['rows'] as $r): ?>
                  <tr class="<?= $r['met'] ? 'row-met' : 'row-missed' ?>">
                    <td>
                      <a href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">
                        #<?= (int)$r['episode_id'] ?>
                      </a>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars(substr((string)$r['arrive_dt'], 0, 16)) ?></td>
                    <td class="<?= $r['met'] ? 'text-success fw-semibold' : 'text-danger fw-semibold' ?>">
                      <?= fmt_min($r['minutes']) ?>
                    </td>
                    <?php if ($key === 'sepsis_bundle'): ?>
                      <td class="small"><?= htmlspecialchars((string)($r['drug_name'] ?? '')) ?></td>
                    <?php endif; ?>
                    <td>
                      <?php if ($r['met']): ?>
                        <span class="badge text-bg-success">✓</span>
                      <?php else: ?>
                        <span class="badge text-bg-danger">✗</span>
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

  <!-- Data note -->
  <div class="card shadow-sm">
    <div class="card-body text-muted small">
      <strong><?= xlt('Data sources') ?>:</strong>
      <?= xlt('Door-to-Room and Door-to-Provider require ARRIVE and ROOM/PROVIDER events recorded in oei_episode_event.') ?>
      <?= xlt('Door-to-ECG requires tasks with type EKG or ECG marked COMPLETE with a completed_datetime.') ?>
      <?= xlt('Sepsis Bundle requires antibiotic medications recorded GIVEN in the MAR within 3 hours of arrival.') ?>
      <br>
      <strong><?= xlt('Denominator note') ?>:</strong>
      <?= xlt('Sepsis Bundle counts episodes where any antibiotic was administered (not all ED visits).') ?>
      <?= xlt('ECG measure counts episodes where an ECG task exists and was completed.') ?>
      <?= xlt('Results may differ from official CMS specifications which include additional exclusions.') ?>
    </div>
  </div>

</div>
</body>
</html>

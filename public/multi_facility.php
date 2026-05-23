<?php

/**
 * public/multi_facility.php
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

use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;
use OpenEMR\Modules\Institutional\Operations\Submodule\MultiFacility\Repository\MultiFacilityRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('multi_facility')) {
    die(xlt('Multi-Facility Dashboard is disabled by manifest'));
}

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityProfiles = new FacilityProfileService();
$facilityId = $facilityProfiles->resolveFacilityId(isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : 0, $userId);
$href       = institutional_bootstrap5_href($manifest);

$settings = new SettingsRepository();
$all      = $settings->all($facilityId);
$lwbsMin  = (int)($all['lwbs_threshold_min'] ?? 120);

$repo  = new MultiFacilityRepository($lwbsMin);
$rows  = $repo->fetchAll();

// System-wide totals
$totalCensus  = array_sum(array_column($rows, 'census'));
$totalSepsis  = array_sum(array_column($rows, 'sepsis_risk_count'));
$totalLwbs    = array_sum(array_column($rows, 'lwbs_count'));
$totalMar     = array_sum(array_column($rows, 'pending_mar_count'));
$totalBoarding= array_sum(array_column($rows, 'bh_boarding_count'));
$totalObs     = array_sum(array_column($rows, 'obs_count'));

function occ_pct(int $occupied, int $total): ?float
{
    return $total > 0 ? round($occupied / $total * 100, 0) : null;
}

function occ_bar_cls(?float $pct): string
{
    if ($pct === null)  return 'bg-secondary';
    if ($pct >= 90)     return 'bg-danger';
    if ($pct >= 75)     return 'bg-warning';
    return 'bg-success';
}

function fmt_d2r(?int $min): string
{
    if ($min === null) return '—';
    return $min . 'm';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Multi-Facility Dashboard') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .kpi-label  { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; color: #6c757d; }
    .kpi-val    { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .fac-card   { transition: box-shadow .15s, border-color .15s; }
    .fac-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.13) !important; }
    .fac-card.has-critical { border-color: #dc3545 !important; }
    .fac-card.has-warning  { border-color: #ffc107 !important; }
    .metric-row { font-size: .82rem; }
    .metric-row .label { color: #6c757d; }
    .occ-bar { height: 6px; border-radius: 3px; }
    .badge-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; }
    .auto-refresh-badge { font-size: .7rem; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }
    .live-dot { animation: pulse 2s infinite; }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0">
        <?= xlt('Health System Dashboard') ?>
        <span class="badge text-bg-secondary ms-2" style="font-size:.65rem;"><?= count($rows) ?> <?= xlt('facilities') ?></span>
      </h1>
      <div class="text-muted small d-flex align-items-center gap-2">
        <span class="badge-dot bg-success live-dot"></span>
        <?= xlt('Live census') ?> &bull; <?= xlt('Updated') ?>: <?= date('H:i:s') ?>
      </div>
    </div>
    <div class="d-flex gap-2 align-items-center">
      <span class="auto-refresh-badge text-muted" id="refresh-counter"></span>
      <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()">&#x21bb; <?= xlt('Refresh') ?></button>
    </div>
  </div>

  <!-- System-wide KPI strip -->
  <div class="row g-2 mb-4">
    <?php
    $kpis = [
        [xlt('System Census'),      $totalCensus,   'text-primary',  null],
        [xlt('OBS'),                $totalObs,       '',              null],
        [xlt('Sepsis Risk'),        $totalSepsis,    'text-danger',   $totalSepsis > 0 ? 'border-danger' : null],
        [xlt('LWBS Risk'),          $totalLwbs,      'text-warning',  $totalLwbs  > 0 ? 'border-warning' : null],
        [xlt('BH Boarding'),        $totalBoarding,  'text-danger',   $totalBoarding > 0 ? 'border-danger' : null],
        [xlt('MAR Overdue'),        $totalMar,       'text-danger',   $totalMar   > 0 ? 'border-danger' : null],
    ];
    foreach ($kpis as [$lbl, $val, $valCls, $borderCls]):
        ?>
    <div class="col-6 col-sm-4 col-md-2">
      <div class="card shadow-sm text-center h-100 <?= $borderCls ?? '' ?>">
        <div class="card-body py-2">
          <div class="kpi-label"><?= $lbl ?></div>
          <div class="kpi-val <?= $valCls ?>"><?= (int)$val ?></div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Facility cards grid -->
  <?php if (empty($rows)): ?>
    <div class="card shadow-sm">
      <div class="card-body text-center text-muted py-5">
        <?= xlt('No active facilities with institutional data found.') ?>
      </div>
    </div>
  <?php else: ?>
  <div class="row g-3">
      <?php foreach ($rows as $fac):
            $hasCritical = ($fac['sepsis_risk_count'] > 0 || $fac['bh_boarding_count'] > 0 || $fac['pending_mar_count'] > 0);
            $hasWarning  = ($fac['lwbs_count'] > 0);
            $cardCls     = $hasCritical ? 'has-critical' : ($hasWarning ? 'has-warning' : '');
            $occ         = occ_pct($fac['beds_occupied'], $fac['beds_total']);
            $occBar      = occ_bar_cls($occ);
            $fid         = (int)$fac['facility_id'];
            $homePage    = $facilityProfiles->getHomePage($fid);
            ?>
    <div class="col-12 col-md-6 col-xl-4">
      <div class="card shadow-sm fac-card h-100 border <?= $cardCls ?>">
        <div class="card-header d-flex align-items-center justify-content-between py-2">
          <span class="fw-semibold"><?= htmlspecialchars($fac['facility_name']) ?></span>
          <span>
            <?php if ($fac['sepsis_risk_count'] > 0): ?>
              <span class="badge text-bg-danger"><?= (int)$fac['sepsis_risk_count'] ?> Sepsis</span>
            <?php endif; ?>
            <?php if ($fac['lwbs_count'] > 0): ?>
              <span class="badge text-bg-warning text-dark ms-1"><?= (int)$fac['lwbs_count'] ?> LWBS</span>
            <?php endif; ?>
          </span>
        </div>
        <div class="card-body">

          <!-- Census + bed occupancy -->
          <div class="d-flex align-items-end justify-content-between mb-1">
            <div>
              <div class="kpi-label"><?= xlt('Census') ?></div>
              <div class="kpi-val"><?= (int)$fac['census'] ?></div>
            </div>
            <div class="text-end">
              <div class="kpi-label"><?= xlt('Beds') ?></div>
              <div class="fw-semibold"><?= (int)$fac['beds_occupied'] ?>/<?= (int)$fac['beds_total'] ?>
                <?php if ($occ !== null): ?>
                  <span class="text-muted small">(<?= (int)$occ ?>%)</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Occupancy bar -->
            <?php if ($fac['beds_total'] > 0): ?>
          <div class="progress occ-bar mb-3">
            <div class="progress-bar <?= $occBar ?>"
                 style="width:<?= (int)($occ ?? 0) ?>%"
                 role="progressbar">
            </div>
          </div>
          <?php else: ?>
          <div class="mb-3"></div>
          <?php endif; ?>

          <!-- Metric grid -->
          <div class="row g-1 metric-row">
            <div class="col-6">
              <span class="label"><?= xlt('OBS') ?>:</span>
              <span class="fw-semibold"><?= (int)$fac['obs_count'] ?></span>
            </div>
            <div class="col-6">
              <span class="label"><?= xlt('D→Room today') ?>:</span>
              <span class="fw-semibold <?= ($fac['avg_d2r_today'] !== null && $fac['avg_d2r_today'] > 30) ? 'text-danger' : '' ?>">
                <?= fmt_d2r($fac['avg_d2r_today']) ?>
              </span>
            </div>
            <div class="col-6">
              <span class="label"><?= xlt('BH Boarding') ?>:</span>
              <span class="fw-semibold <?= $fac['bh_boarding_count'] > 0 ? 'text-danger' : '' ?>">
                <?= (int)$fac['bh_boarding_count'] ?>
              </span>
            </div>
            <div class="col-6">
              <span class="label"><?= xlt('MAR Overdue') ?>:</span>
              <span class="fw-semibold <?= $fac['pending_mar_count'] > 0 ? 'text-danger' : '' ?>">
                <?= (int)$fac['pending_mar_count'] ?>
              </span>
            </div>
          </div>

        </div>
        <div class="card-footer d-flex gap-2 py-2">
          <a href="<?= htmlspecialchars($homePage) ?>?facility_id=<?= $fid ?>"
             class="btn btn-sm btn-outline-primary flex-fill text-center">
            <?= xlt('Open Facility') ?>
          </a>
          <a href="alerts.php?facility_id=<?= $fid ?>"
             class="btn btn-sm <?= $hasCritical ? 'btn-danger' : 'btn-outline-secondary' ?> flex-fill text-center">
            <?= xlt('Alerts') ?>
          </a>
          <a href="cms_quality.php?facility_id=<?= $fid ?>"
             class="btn btn-sm btn-outline-secondary flex-fill text-center">
            <?= xlt('Quality') ?>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>

<script>
// Auto-refresh countdown
(function () {
    var seconds = 60;
    var el = document.getElementById('refresh-counter');
    if (!el) return;
    setInterval(function () {
        seconds--;
        if (seconds <= 0) {
            location.reload();
        }
        el.textContent = 'Auto-refresh in ' + seconds + 's';
    }, 1000);
})();
</script>
</body>
</html>













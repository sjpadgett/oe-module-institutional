<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Alerts\Service\AlertService;
use OpenEMR\Modules\Institutional\Submodule\Alerts\Repository\AlertAckRepository;
use OpenEMR\Modules\Institutional\Submodule\Cms\Repository\CmsMeasureRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsBilling\Service\ObsBillingService;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;

if (!$manifest->featureEnabled('command_center')) {
    die(xlt('Command Center is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$settings   = new SettingsRepository();
$cfg        = $settings->all($facilityId);
$lwbsMin    = (int)($cfg['lwbs_threshold_min']      ?? 120);
$boardingH  = (int)($cfg['boarding_alert_hours']    ?? 4);
$obsRunway  = (int)($cfg['obs_runway_warning_hours'] ?? 4);

// Episode board
$episodeRepo  = new EpisodeRepository();
$boardRows    = $episodeRepo->fetchBoard($facilityId);

// Latest vitals for alert engine
$triageRepo   = new TriageRepository();
$latestVitals = $triageRepo->latestByFacility($facilityId);

// Alerts
$alertSvc    = new AlertService($lwbsMin, $boardingH, $obsRunway);
$ackRepo     = new AlertAckRepository();
$allAlerts   = $alertSvc->computeAll($boardRows, $latestVitals, $facilityId);
$snoozed      = $ackRepo->activeSnoozed($facilityId);   // array<string,true>
$activeAlerts = array_values(array_filter($allAlerts, function (array $a) use ($snoozed): bool {
    $key = \OpenEMR\Modules\Institutional\Submodule\Alerts\Repository\AlertAckRepository::key(
        (string)($a['type'] ?? ''),
        (int)($a['episode_id'] ?? 0)
    );
    return !isset($snoozed[$key]);
}));
$summary     = AlertService::summarize($activeAlerts);
$criticals   = array_values(array_filter($activeAlerts, fn($a) => $a['severity'] === 'CRITICAL'));
$warnings    = array_values(array_filter($activeAlerts, fn($a) => $a['severity'] === 'WARNING'));

// CMS measures (last 30 days)
$cmsRepo     = new CmsMeasureRepository();
$dateFrom    = date('Y-m-d', strtotime('-30 days'));
$dateTo      = date('Y-m-d');
$cmsMeasures = [];
if ($manifest->featureEnabled('cms_quality')) {
    $cmsMeasures = $cmsRepo->computeAll($facilityId, $dateFrom, $dateTo);
}

// OBS at-risk
$obsBillingRows = [];
if ($manifest->featureEnabled('obs_billing')) {
    $billing = new ObsBillingService();
    $obsBillingRows = array_filter(
        $billing->fetchObsBillingStatus($facilityId),
        fn($r) => $r['status'] !== 'NORMAL'
    );
    $obsBillingRows = array_values($obsBillingRows);
}

// Board metrics
$census       = count($boardRows);
$obsActive    = count(array_filter($boardRows, fn($r) => strtoupper((string)($r['type'] ?? '')) === 'OBS'));
$bhBoarding   = count(array_filter($boardRows, fn($r) => !empty($r['bh_observation_level'])));
$waitingCount = count(array_filter($boardRows, fn($r) => strtoupper((string)($r['status'] ?? '')) === 'WAITING'));

// LWBS at-risk: WAITING longer than threshold with no location
$lwbsAtRisk = 0;
$lwbsCutoff = time() - $lwbsMin * 60;
foreach ($boardRows as $r) {
    $ts = $r['start_datetime'] ? strtotime((string)$r['start_datetime']) : 0;
    if (strtoupper((string)($r['status'] ?? '')) === 'WAITING' && empty($r['location_name']) && $ts > 0 && $ts <= $lwbsCutoff) {
        $lwbsAtRisk++;
    }
}

// Sepsis risk count
$sepsisCount = count(array_filter($activeAlerts, fn($a) => $a['type'] === 'SEPSIS_RISK'));

// Alert type labels
function cc_alert_label(string $type): string {
    return match ($type) {
        'SEPSIS_RISK'        => 'Sepsis Risk',
        'VITALS_DETERIORATION' => 'Vitals',
        'LWBS_RISK'          => 'LWBS Risk',
        'TASK_OVERDUE'       => 'Task Overdue',
        'MAR_OVERDUE'        => 'MAR Overdue',
        'BH_BOARDING_DWELL'  => 'BH Boarding',
        'OBS_RUNWAY'         => 'OBS Runway',
        'OBS_BILLING_FLAG'   => 'Billing Flag',
        'VITALS_STALE'       => 'Stale Vitals',
        'NO_VITALS'          => 'No Vitals',
        default              => $type,
    };
}

function cc_alert_icon(string $type): string {
    return match ($type) {
        'SEPSIS_RISK'          => '⚡',
        'VITALS_DETERIORATION' => '📉',
        'LWBS_RISK'            => '⏱',
        'TASK_OVERDUE'         => '📋',
        'MAR_OVERDUE'          => '💊',
        'BH_BOARDING_DWELL'    => '🏥',
        'OBS_RUNWAY'           => '🕐',
        'OBS_BILLING_FLAG'     => '💰',
        'VITALS_STALE'         => '⚠',
        'NO_VITALS'            => '❗',
        default                => '•',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= xlt('Command Center') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Barlow+Condensed:wght@300;500;700&family=Barlow:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* ── Reset & base ──────────────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:       #080d14;
      --surface:  #0d1520;
      --border:   #1a2b3c;
      --cyan:     #00e5ff;
      --amber:    #ffab00;
      --red:      #ff3d57;
      --green:    #00e676;
      --muted:    #4a6070;
      --text:     #ccdde8;
      --text-dim: #6b8899;
      --mono:     'Share Tech Mono', monospace;
      --cond:     'Barlow Condensed', sans-serif;
      --body:     'Barlow', sans-serif;
    }

    html, body {
      background: var(--bg);
      color: var(--text);
      font-family: var(--body);
      font-size: 14px;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ── Layout grid ───────────────────────────────────────────────────────── */
    .cc-layout {
      display: grid;
      grid-template-rows: auto auto 1fr auto;
      min-height: 100vh;
      gap: 0;
    }

    /* ── Header ────────────────────────────────────────────────────────────── */
    .cc-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 10px 20px;
      border-bottom: 1px solid var(--border);
      background: var(--surface);
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .cc-header-left {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .cc-wordmark {
      font-family: var(--cond);
      font-weight: 700;
      font-size: 18px;
      letter-spacing: .12em;
      text-transform: uppercase;
      color: var(--cyan);
    }

    .cc-facility {
      font-family: var(--mono);
      font-size: 12px;
      color: var(--text-dim);
      padding: 3px 10px;
      border: 1px solid var(--border);
      border-radius: 2px;
    }

    .cc-time {
      font-family: var(--mono);
      font-size: 22px;
      color: var(--text);
      letter-spacing: .08em;
    }

    .cc-date {
      font-family: var(--mono);
      font-size: 11px;
      color: var(--text-dim);
      text-align: right;
    }

    .cc-header-nav {
      display: flex;
      gap: 8px;
    }

    .cc-nav-link {
      font-family: var(--cond);
      font-size: 13px;
      font-weight: 500;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--text-dim);
      text-decoration: none;
      padding: 4px 12px;
      border: 1px solid transparent;
      border-radius: 2px;
      transition: color .15s, border-color .15s;
    }

    .cc-nav-link:hover { color: var(--cyan); border-color: var(--cyan); }

    /* ── KPI Strip ─────────────────────────────────────────────────────────── */
    .cc-kpi-strip {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      border-bottom: 1px solid var(--border);
    }

    .cc-kpi {
      padding: 10px 16px;
      border-right: 1px solid var(--border);
      display: flex;
      flex-direction: column;
      gap: 2px;
    }
    .cc-kpi:last-child { border-right: none; }

    .cc-kpi-label {
      font-family: var(--cond);
      font-size: 10px;
      font-weight: 500;
      letter-spacing: .14em;
      text-transform: uppercase;
      color: var(--text-dim);
    }

    .cc-kpi-value {
      font-family: var(--mono);
      font-size: 32px;
      line-height: 1;
      color: var(--text);
    }

    .cc-kpi-value.critical { color: var(--red); }
    .cc-kpi-value.warning  { color: var(--amber); }
    .cc-kpi-value.ok       { color: var(--green); }
    .cc-kpi-value.info     { color: var(--cyan); }

    .cc-kpi-sub {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--text-dim);
    }

    /* ── Main 3-column grid ────────────────────────────────────────────────── */
    .cc-main {
      display: grid;
      grid-template-columns: 340px 1fr 320px;
      gap: 0;
      overflow: hidden;
    }

    .cc-panel {
      border-right: 1px solid var(--border);
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }
    .cc-panel:last-child { border-right: none; }

    .cc-panel-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 8px 16px;
      border-bottom: 1px solid var(--border);
      background: var(--surface);
      flex-shrink: 0;
    }

    .cc-panel-title {
      font-family: var(--cond);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--cyan);
    }

    .cc-panel-count {
      font-family: var(--mono);
      font-size: 11px;
      color: var(--text-dim);
    }

    .cc-panel-body {
      overflow-y: auto;
      flex: 1;
      padding: 8px 0;
    }

    /* ── Alert rows ────────────────────────────────────────────────────────── */
    .alert-row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 8px 16px;
      border-bottom: 1px solid var(--border);
      transition: background .1s;
    }
    .alert-row:hover { background: rgba(255,255,255,.03); }
    .alert-row:last-child { border-bottom: none; }

    .alert-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      margin-top: 5px;
      flex-shrink: 0;
    }
    .alert-dot.critical {
      background: var(--red);
      box-shadow: 0 0 6px var(--red);
      animation: pulse-dot 1.8s ease-in-out infinite;
    }
    .alert-dot.warning { background: var(--amber); }

    @keyframes pulse-dot {
      0%, 100% { opacity: 1; box-shadow: 0 0 6px var(--red); }
      50%       { opacity: .5; box-shadow: 0 0 2px var(--red); }
    }

    .alert-body { flex: 1; min-width: 0; }

    .alert-type {
      font-family: var(--cond);
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .1em;
      text-transform: uppercase;
    }
    .alert-type.critical { color: var(--red); }
    .alert-type.warning  { color: var(--amber); }

    .alert-msg {
      font-size: 13px;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .alert-detail {
      font-family: var(--mono);
      font-size: 11px;
      color: var(--text-dim);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .alert-ep {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--text-dim);
      margin-top: 2px;
    }

    /* ── ED Board ──────────────────────────────────────────────────────────── */
    .board-row {
      display: grid;
      grid-template-columns: 64px 56px 40px 1fr 72px;
      gap: 6px;
      align-items: center;
      padding: 7px 14px;
      border-bottom: 1px solid var(--border);
      transition: background .1s;
    }
    .board-row:hover { background: rgba(255,255,255,.03); }
    .board-row.header-row {
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 5px 14px;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .board-row.has-critical { border-left: 3px solid var(--red); padding-left: 11px; }
    .board-row.has-sepsis { border-left: 3px solid var(--red); padding-left: 11px; background: rgba(255,61,87,.04); }
    .board-row.is-waiting { opacity: .75; }

    .board-col {
      font-family: var(--mono);
      font-size: 12px;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .board-col.dim { color: var(--text-dim); font-size: 11px; }
    .board-col.header { font-family: var(--cond); font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--text-dim); }

    .esi-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      border-radius: 4px;
      font-family: var(--mono);
      font-size: 13px;
      font-weight: bold;
    }
    .esi-1 { background: #b71c1c; color: #fff; }
    .esi-2 { background: #e65100; color: #fff; }
    .esi-3 { background: #f9a825; color: #000; }
    .esi-4 { background: #2e7d32; color: #fff; }
    .esi-5 { background: #1565c0; color: #fff; }
    .esi-x { background: var(--border); color: var(--text-dim); }

    .status-chip {
      font-family: var(--cond);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .06em;
      padding: 2px 6px;
      border-radius: 2px;
      text-transform: uppercase;
    }
    .status-WAITING  { color: var(--amber); border: 1px solid var(--amber); }
    .status-ROOMED   { color: var(--cyan); border: 1px solid var(--cyan); }
    .status-PROVIDER_EVALUATION { color: var(--green); border: 1px solid var(--green); }
    .status-OBS_START{ color: #b39ddb; border: 1px solid #b39ddb; }
    .status-default  { color: var(--text-dim); border: 1px solid var(--border); }

    .elapsed-chip {
      font-family: var(--mono);
      font-size: 11px;
      color: var(--text-dim);
    }
    .elapsed-chip.over2h  { color: var(--amber); }
    .elapsed-chip.over4h  { color: var(--red); }

    /* ── CMS mini-gauges ───────────────────────────────────────────────────── */
    .cms-panel-body {
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .cms-gauge-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 4px;
      padding: 12px;
    }

    .cms-gauge-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
    }

    .cms-gauge-title {
      font-family: var(--cond);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
      color: var(--text-dim);
    }

    .cms-gauge-id {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--muted);
    }

    .cms-gauge-bar-bg {
      height: 6px;
      background: var(--border);
      border-radius: 3px;
      margin-bottom: 6px;
      overflow: hidden;
    }

    .cms-gauge-bar-fill {
      height: 100%;
      border-radius: 3px;
      transition: width .4s ease;
    }

    .cms-gauge-stats {
      display: flex;
      justify-content: space-between;
    }

    .cms-stat { text-align: center; }
    .cms-stat-val { font-family: var(--mono); font-size: 16px; color: var(--text); }
    .cms-stat-lbl { font-family: var(--cond); font-size: 9px; letter-spacing: .1em; text-transform: uppercase; color: var(--text-dim); }

    /* ── OBS strip ─────────────────────────────────────────────────────────── */
    .cc-obs-strip {
      border-top: 1px solid var(--border);
      background: var(--surface);
      padding: 8px 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      overflow-x: auto;
      flex-shrink: 0;
    }

    .cc-obs-label {
      font-family: var(--cond);
      font-size: 10px;
      font-weight: 700;
      letter-spacing: .16em;
      text-transform: uppercase;
      color: var(--text-dim);
      white-space: nowrap;
      flex-shrink: 0;
    }

    .obs-pill {
      display: flex;
      align-items: center;
      gap: 6px;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 3px;
      padding: 4px 10px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .obs-pill.convert { border-color: var(--red); }
    .obs-pill.watch   { border-color: var(--amber); }

    .obs-pill-ep { font-family: var(--mono); font-size: 11px; color: var(--text-dim); }
    .obs-pill-status { font-family: var(--cond); font-size: 11px; font-weight: 700; letter-spacing: .06em; }
    .obs-pill.convert .obs-pill-status { color: var(--red); }
    .obs-pill.watch .obs-pill-status   { color: var(--amber); }
    .obs-pill-elapsed { font-family: var(--mono); font-size: 11px; color: var(--text-dim); }

    /* ── Empty states ──────────────────────────────────────────────────────── */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 40px 20px;
      color: var(--text-dim);
      text-align: center;
    }
    .empty-state-icon { font-size: 28px; margin-bottom: 8px; opacity: .5; }
    .empty-state-text { font-family: var(--cond); font-size: 13px; letter-spacing: .1em; text-transform: uppercase; }

    /* ── Scrollbar ─────────────────────────────────────────────────────────── */
    ::-webkit-scrollbar { width: 4px; height: 4px; }
    ::-webkit-scrollbar-track { background: var(--bg); }
    ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 2px; }
    ::-webkit-scrollbar-thumb:hover { background: var(--muted); }

    /* ── Misc ──────────────────────────────────────────────────────────────── */
    .refresh-badge {
      font-family: var(--mono);
      font-size: 10px;
      color: var(--text-dim);
      padding: 2px 8px;
      border: 1px solid var(--border);
      border-radius: 2px;
    }

    .live-dot {
      display: inline-block;
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--green);
      box-shadow: 0 0 6px var(--green);
      animation: pulse-dot 2s ease-in-out infinite;
      margin-right: 4px;
    }
      <?= $triageStandard->cssBlock() ?>
    </style>
</head>
<body>
<div class="cc-layout">

  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <header class="cc-header">
    <div class="cc-header-left">
      <span class="cc-wordmark">Command Center</span>
      <span class="cc-facility">Facility <?= htmlspecialchars((string)$facilityId) ?></span>
      <span class="refresh-badge"><span class="live-dot"></span><span id="refresh-counter">live</span></span>
    </div>
    <nav class="cc-header-nav">
      <a class="cc-nav-link" href="ed_board.php?facility_id=<?= $facilityId ?>">ED Board</a>
      <a class="cc-nav-link" href="alerts.php?facility_id=<?= $facilityId ?>">Alerts</a>
      <a class="cc-nav-link" href="trends.php?facility_id=<?= $facilityId ?>">Trends</a>
      <a class="cc-nav-link" href="handoff.php?facility_id=<?= $facilityId ?>">Handoff</a>
      <a class="cc-nav-link" href="multi_facility.php?facility_id=<?= $facilityId ?>">System</a>
    </nav>
    <div>
      <div class="cc-time" id="cc-clock"><?= date('H:i:s') ?></div>
      <div class="cc-date"><?= date('D, M j Y') ?></div>
    </div>
  </header>

  <!-- ── KPI Strip ───────────────────────────────────────────────────────── -->
  <div class="cc-kpi-strip">
    <div class="cc-kpi">
      <div class="cc-kpi-label">Census</div>
      <div class="cc-kpi-value info"><?= $census ?></div>
      <div class="cc-kpi-sub"><?= $waitingCount ?> waiting</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">Criticals</div>
      <div class="cc-kpi-value <?= $summary['critical'] > 0 ? 'critical' : 'ok' ?>"><?= $summary['critical'] ?></div>
      <div class="cc-kpi-sub"><?= $summary['warning'] ?> warnings</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">Sepsis Risk</div>
      <div class="cc-kpi-value <?= $sepsisCount > 0 ? 'critical' : 'ok' ?>"><?= $sepsisCount ?></div>
      <div class="cc-kpi-sub">qSOFA &ge;2</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">LWBS Risk</div>
      <div class="cc-kpi-value <?= $lwbsAtRisk > 0 ? 'warning' : 'ok' ?>"><?= $lwbsAtRisk ?></div>
      <div class="cc-kpi-sub">&gt;<?= $lwbsMin ?>m waiting</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">OBS Active</div>
      <div class="cc-kpi-value info"><?= $obsActive ?></div>
      <div class="cc-kpi-sub"><?= count($obsBillingRows) ?> at-risk</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">BH Boarding</div>
      <div class="cc-kpi-value <?= $bhBoarding > 0 ? 'warning' : 'ok' ?>"><?= $bhBoarding ?></div>
      <div class="cc-kpi-sub">active episodes</div>
    </div>
    <div class="cc-kpi">
      <div class="cc-kpi-label">CMS 30d Avg</div>
      <?php
        $cmsRates = array_filter(array_column($cmsMeasures, 'rate_pct'), fn($v) => $v !== null);
        $avgRate  = $cmsRates ? round(array_sum($cmsRates) / count($cmsRates), 0) : null;
        $rateCls  = $avgRate === null ? '' : ($avgRate >= 90 ? 'ok' : ($avgRate >= 75 ? 'info' : ($avgRate >= 50 ? 'warning' : 'critical')));
        ?>
      <div class="cc-kpi-value <?= $rateCls ?>"><?= $avgRate !== null ? $avgRate . '%' : '—' ?></div>
      <div class="cc-kpi-sub">4 measures</div>
    </div>
  </div>

  <!-- ── Main 3-column ───────────────────────────────────────────────────── -->
  <div class="cc-main">

    <!-- ── Left: Active Alerts ─────────────────────────────────────────── -->
    <div class="cc-panel">
      <div class="cc-panel-header">
        <span class="cc-panel-title">Active Alerts</span>
        <span class="cc-panel-count"><?= count($activeAlerts) ?> active</span>
      </div>
      <div class="cc-panel-body">
        <?php if (empty($activeAlerts)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">✓</div>
            <div class="empty-state-text">All Clear</div>
          </div>
        <?php endif; ?>
        <?php foreach ($activeAlerts as $a): ?>
            <?php $sev = strtolower($a['severity'] ?? 'warning'); ?>
          <div class="alert-row">
            <div class="alert-dot <?= $sev ?>"></div>
            <div class="alert-body">
              <div class="alert-type <?= $sev ?>">
                <?= cc_alert_icon($a['type'] ?? '') ?>
                <?= htmlspecialchars(cc_alert_label($a['type'] ?? '')) ?>
              </div>
              <div class="alert-msg"><?= htmlspecialchars((string)($a['message'] ?? '')) ?></div>
              <?php if (!empty($a['detail'])): ?>
                <div class="alert-detail"><?= htmlspecialchars((string)$a['detail']) ?></div>
              <?php endif; ?>
              <div class="alert-ep">Ep #<?= (int)($a['episode_id'] ?? 0) ?> · PID <?= (int)($a['pid'] ?? 0) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Centre: ED Board ────────────────────────────────────────────── -->
    <div class="cc-panel">
      <div class="cc-panel-header">
        <span class="cc-panel-title">Live ED Board</span>
        <span class="cc-panel-count"><?= $census ?> patients</span>
      </div>
      <div class="cc-panel-body">
        <div class="board-row header-row">
          <span class="board-col header">Room</span>
          <span class="board-col header">Status</span>
          <span class="board-col header"><?= htmlspecialchars($triageStandard->columnLabel()) ?></span>
          <span class="board-col header">Chief Complaint</span>
          <span class="board-col header">Elapsed</span>
        </div>
        <?php if (empty($boardRows)): ?>
          <div class="empty-state">
            <div class="empty-state-icon">🏥</div>
            <div class="empty-state-text">No Active Episodes</div>
          </div>
        <?php endif; ?>
        <?php foreach ($boardRows as $r):
            $status    = strtoupper((string)($r['status'] ?? 'WAITING'));
            $esi       = (int)($r['acuity_esi'] ?? 0);
            $arrivedTs = $r['start_datetime'] ? strtotime((string)$r['start_datetime']) : 0;
            $elapsedMin = $arrivedTs > 0 ? (int)round((time() - $arrivedTs) / 60) : 0;
            $elapsedH  = intdiv($elapsedMin, 60);
            $elapsedM  = $elapsedMin % 60;
            $elapsedStr = $elapsedH > 0 ? "{$elapsedH}h {$elapsedM}m" : "{$elapsedM}m";
            $elapsedCls = $elapsedMin > 240 ? 'over4h' : ($elapsedMin > 120 ? 'over2h' : '');
          // Check if this episode has a critical alert
            $epId = (int)($r['id'] ?? 0);
            $hasCrit = false;
            $hasSepsis = false;
            foreach ($activeAlerts as $a) {
                if ((int)($a['episode_id'] ?? 0) === $epId) {
                    if ($a['severity'] === 'CRITICAL') $hasCrit = true;
                    if ($a['type'] === 'SEPSIS_RISK') $hasSepsis = true;
                }
            }
            $rowCls = $hasSepsis ? 'has-sepsis' : ($hasCrit ? 'has-critical' : ($status === 'WAITING' ? 'is-waiting' : ''));
            $statusCls = match ($status) {
                'WAITING' => 'status-WAITING',
                'ROOMED'  => 'status-ROOMED',
                'PROVIDER_EVALUATION' => 'status-PROVIDER_EVALUATION',
                'OBS_START' => 'status-OBS_START',
                default   => 'status-default',
            };
          $statusLabel = match ($status) {
            'WAITING'   => 'WAITING',
            'ROOMED'    => 'ROOMED',
            'PROVIDER_EVALUATION' => 'PROVIDER',
            'OBS_START' => 'OBS',
            'READY_DISPO' => 'READY',
            default     => substr($status, 0, 8),
          };
          $esiCls = $triageStandard->badgeClass($esi ?: 0);
    ?>
          <a href="timeline.php?facility_id=<?= $facilityId ?>&episode_id=<?= $epId ?>"
             style="text-decoration:none;display:block;"
             class="board-row <?= $rowCls ?>">
            <span class="board-col"><?= htmlspecialchars((string)($r['location_name'] ?? '—')) ?></span>
            <span class="board-col"><span class="status-chip <?= $statusCls ?>"><?= htmlspecialchars($statusLabel) ?></span></span>
            <span class="board-col"><span class="esi-badge <?= $esiCls ?>"><?= htmlspecialchars($triageStandard->shortLabel($esi ?: 0)) ?></span></span>
            <span class="board-col"><?= htmlspecialchars(mb_strimwidth((string)($r['chief_complaint'] ?? ''), 0, 38, '…')) ?></span>
            <span class="board-col elapsed-chip <?= $elapsedCls ?>"><?= $elapsedStr ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ── Right: CMS Mini-Gauges ──────────────────────────────────────── -->
    <div class="cc-panel">
      <div class="cc-panel-header">
        <span class="cc-panel-title">CMS Quality · 30d</span>
        <a href="cms_quality.php?facility_id=<?= $facilityId ?>" class="cc-nav-link" style="font-size:10px;">Details →</a>
      </div>
      <div class="cc-panel-body">
        <div class="cms-panel-body">
          <?php foreach ($cmsMeasures as $key => $m):
                $rate    = $m['rate_pct'];
                $pct     = min(100, max(0, (float)($rate ?? 0)));
                $barColor = $pct >= 90 ? '#00e676' : ($pct >= 75 ? '#00e5ff' : ($pct >= 50 ? '#ffab00' : '#ff3d57'));
                $tier     = $pct >= 90 ? 'EXCELLENT' : ($pct >= 75 ? 'GOOD' : ($pct >= 50 ? 'FAIR' : ($rate === null ? 'NO DATA' : 'POOR')));
                $tierColor = $pct >= 90 ? '#00e676' : ($pct >= 75 ? '#00e5ff' : ($pct >= 50 ? '#ffab00' : '#ff3d57'));
                ?>
          <div class="cms-gauge-card">
            <div class="cms-gauge-header">
              <span class="cms-gauge-title"><?= htmlspecialchars($m['label']) ?></span>
              <span class="cms-gauge-id"><?= htmlspecialchars($m['cms_id']) ?></span>
            </div>
            <div class="cms-gauge-bar-bg">
              <div class="cms-gauge-bar-fill"
                   style="width:<?= round($pct) ?>%;background:<?= $barColor ?>;"></div>
            </div>
            <div class="cms-gauge-stats">
              <div class="cms-stat">
                <div class="cms-stat-val" style="color:<?= $tierColor ?>"><?= $rate !== null ? round($pct) . '%' : '—' ?></div>
                <div class="cms-stat-lbl">Rate</div>
              </div>
              <div class="cms-stat">
                <div class="cms-stat-val"><?= (int)$m['n'] ?></div>
                <div class="cms-stat-lbl">Cases</div>
              </div>
              <div class="cms-stat">
                <div class="cms-stat-val"><?= $m['median_min'] !== null ? $m['median_min'] . 'm' : '—' ?></div>
                <div class="cms-stat-lbl">Median</div>
              </div>
              <div class="cms-stat">
                <div class="cms-stat-val"><?= $m['p90_min'] !== null ? $m['p90_min'] . 'm' : '—' ?></div>
                <div class="cms-stat-lbl">P90</div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($cmsMeasures)): ?>
            <div class="empty-state">
              <div class="empty-state-icon">📊</div>
              <div class="empty-state-text">CMS measures disabled</div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>

  <!-- ── OBS At-Risk Strip ───────────────────────────────────────────────── -->
  <?php if (!empty($obsBillingRows)): ?>
  <div class="cc-obs-strip">
    <span class="cc-obs-label">OBS at-risk</span>
        <?php foreach ($obsBillingRows as $r):
            $isConvert = in_array($r['status'], ['CONVERSION_DUE', 'OVERRUN']);
            $cls = $isConvert ? 'convert' : 'watch';
            $statusText = match ($r['status']) {
                'OVERRUN'        => 'OVERRUN',
                'CONVERSION_DUE' => 'CONVERT',
                'APPROACHING_2'  => '2ND MIDNIGHT',
                'APPROACHING_1'  => '1ST MIDNIGHT',
                default          => $r['status'],
            };
    ?>
    <div class="obs-pill <?= $cls ?>">
      <span class="obs-pill-ep">#<?= (int)$r['episode_id'] ?></span>
      <span class="obs-pill-status"><?= htmlspecialchars($statusText) ?></span>
      <span class="obs-pill-elapsed"><?= htmlspecialchars(\OpenEMR\Modules\Institutional\Submodule\ObsBilling\Service\ObsBillingService::formatElapsed($r['elapsed_hours'])) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div><!-- /cc-layout -->

<script>
// Live clock
(function () {
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var d = new Date();
        var el = document.getElementById('cc-clock');
        if (el) el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    tick();
    setInterval(tick, 1000);
})();

// Auto-refresh with countdown
(function () {
    var seconds = 60;
    var el = document.getElementById('refresh-counter');
    if (!el) return;
    setInterval(function () {
        seconds--;
        if (seconds <= 0) { location.reload(); return; }
        el.textContent = seconds + 's';
    }, 1000);
})();
</script>
</body>
</html>

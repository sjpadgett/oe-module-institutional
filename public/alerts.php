<?php

/**
 * public/alerts.php
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

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Alerts\Controller\AlertsController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Alerts\Repository\AlertAckRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Alerts\Service\AlertService;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$settings = new SettingsRepository();
$all      = $settings->all($facilityId);

$alertService = new AlertService(
    (int)($all['lwbs_threshold_min']       ?? 120),
    (int)($all['boarding_alert_hours']     ?? 4),
    (int)($all['obs_runway_warning_hours'] ?? 6)
);

$episodeRepo = new EpisodeRepository();
$ackRepo     = new AlertAckRepository();

$triageRepo = null;
if (class_exists('OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository')) {
    $triageRepo = new \OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository();
}

$controller = new AlertsController($alertService, $episodeRepo, $ackRepo, $triageRepo);

if (isset($_GET['json']) && $_GET['json'] === '1') {
    $controller->handleJson($facilityId, $userId);
}

$data = $controller->handle($facilityId, $userId);

$href = institutional_bootstrap5_href($manifest);

function severityBadge(string $sev): string
{
    return $sev === 'CRITICAL' ? 'text-bg-danger' : 'oei-badge-warning';
}

function rowClass(string $sev): string
{
    return $sev === 'CRITICAL' ? 'table-danger' : 'table-warning';
}

function typeLabel(string $type): string
{
    return match ($type) {
        'LWBS_RISK'            => '🚶 LWBS Risk',
        'TASK_OVERDUE'         => '⏰ Overdue Task',
        'BH_BOARDING_DWELL'    => '🏥 BH Dwell',
        'OBS_RUNWAY'           => '🕐 Obs Runway',
        'VITALS_DETERIORATION' => '❤️ Vitals',
        'VITALS_STALE'         => '🩺 Stale Vitals',
        'NO_VITALS'            => '⚠ No Vitals',
        'MAR_OVERDUE'          => '💊 MAR Overdue',
        'SEPSIS_RISK'          => '🔴 Sepsis Risk',
        default                => htmlspecialchars($type),
    };
}

function groupMeta(string $group): array
{
    return match ($group) {
        'lwbs'    => ['label' => 'LWBS',    'cls' => 'oei-badge-warning'],
        'tasks'   => ['label' => 'Tasks',   'cls' => 'oei-badge-warning'],
        'bh'      => ['label' => 'BH',      'cls' => 'text-bg-danger'],
        'obs'     => ['label' => 'Obs',     'cls' => 'text-bg-primary'],
        'vitals'  => ['label' => 'Vitals',  'cls' => 'text-bg-danger'],
        'mar'     => ['label' => 'MAR',     'cls' => 'oei-badge-warning'],
        default   => ['label' => ucfirst($group), 'cls' => 'text-bg-secondary'],
    };
}

$summary = $data['summary'];
$alerts  = $data['alerts'];

$grouped = [];
foreach ($alerts as $a) {
    $grouped[$a['group']][] = $a;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Charge Nurse Dashboard') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    /*
     * alerts.php — theme-adaptive styles
     * All colours use Bootstrap 5.3 CSS variables so they flip automatically
     * when data-bs-theme="dark"|"light" is set on <html>.
     * Hardcoded hex values are only used for non-Bootstrap custom UI elements,
     * and even those use a light/dark variant via the [data-bs-theme] selector.
     */

    /* ── dashboard header bar ───────────────────────────────────────────── */
    .dash-header {
        background: var(--bs-tertiary-bg);
        border-bottom: 2px solid var(--bs-border-color);
    }

    /* ── alert-none (all-clear panel) ──────────────────────────────────── */
    .alert-none {
        background: var(--bs-success-bg-subtle);
        border: 1px solid var(--bs-success-border-subtle);
        color: var(--bs-success-text-emphasis);
        border-radius: .5rem;
    }

    /* ── group header row inside card ──────────────────────────────────── */
    .group-header {
        font-size: .7rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--bs-secondary-color);
        padding: 6px 12px;
        background: var(--bs-tertiary-bg);
    }

    /* ── pulse dot ─────────────────────────────────────────────────────── */
    .pulse-dot {
        display: inline-block;
        width: 10px; height: 10px;
        border-radius: 50%;
        background: var(--bs-danger);
        animation: oei-pulse-dot 1.4s infinite;
    }
    .pulse-dot.green { background: var(--bs-success); }
    @keyframes oei-pulse-dot {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: .5; transform: scale(1.3); }
    }

    /* ── misc ───────────────────────────────────────────────────────────── */
    .countdown { font-variant-numeric: tabular-nums; }
    .snooze-btn { font-size: .75rem; padding: 1px 6px; }
    #soundToggle { cursor: pointer; }

    /* ── dark-theme only: patient sidebar list-group items ─────────────── */
    [data-bs-theme="dark"] .list-group-item-action {
        color: var(--bs-body-color);
    }
    [data-bs-theme="dark"] .list-group-item-action:hover {
        background: var(--bs-tertiary-bg);
    }

    /* ── warning badge: override text-dark which breaks in dark theme ───── */
    /* Bootstrap's text-bg-warning uses black text; in dark mode the badge
       background is dark-yellow and black text is still legible — but the
       legacy `text-dark` force on some elements breaks. We let BS5.3 handle
       it via data-bs-theme instead. */
    .oei-badge-warning {
        background-color: var(--bs-warning);
        color: var(--bs-emphasis-color);  /* black in light, white in dark */
    }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">

<div class="dash-header px-3 py-2 d-flex align-items-center gap-3 flex-wrap sticky-top">
  <div class="d-flex align-items-center gap-2">
    <span id="statusDot" class="pulse-dot green"></span>
    <span class="fw-semibold"><?= xlt('Charge Nurse Dashboard') ?></span>
  </div>

  <div class="d-flex gap-2 flex-wrap" id="summaryBar">
    <?php if ($summary['critical'] > 0): ?>
      <span class="badge text-bg-danger fs-6" id="badgeCritical">
        <?= (int)$summary['critical'] ?> <?= xlt('Critical') ?>
      </span>
    <?php endif; ?>
    <?php if ($summary['warning'] > 0): ?>
      <span class="badge oei-badge-warning fs-6" id="badgeWarning">
        <?= (int)$summary['warning'] ?> <?= xlt('Warning') ?>
      </span>
    <?php endif; ?>
    <?php if (empty($alerts)): ?>
      <span class="badge text-bg-success" id="badgeAllClear"><?= xlt('All Clear') ?></span>
    <?php endif; ?>
    <?php foreach ($summary['by_group'] as $grp => $cnt):
        $gm = groupMeta($grp); ?>
      <span class="badge <?= $gm['cls'] ?>"><?= htmlspecialchars($gm['label']) ?> <?= (int)$cnt ?></span>
    <?php endforeach; ?>
  </div>

  <div class="ms-auto d-flex align-items-center gap-3">
    <span class="text-muted small">
      <?= xlt('Refresh in') ?> <span id="countdown" class="countdown"><?= 60 ?></span>s
    </span>
    <span id="soundToggle" class="text-muted" title="<?= xlt('Toggle alert sound') ?>">🔕</span>
    <a href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"
       class="btn btn-sm btn-outline-secondary"><?= xlt('ED Board') ?></a>
    <a href="handoff.php?facility_id=<?= urlencode((string)$facilityId) ?>"
       class="btn btn-sm btn-outline-secondary" target="_blank"><?= xlt('Handoff Report') ?></a>
  </div>
</div>

<div class="container-fluid py-3">

  <?php if (empty($alerts)): ?>
    <div class="alert-none rounded p-4 text-center">
      <div style="font-size:2.5rem;">✅</div>
      <div class="fw-semibold mt-2"><?= xlt('No active alerts') ?></div>
      <div class="small mt-1 opacity-75"><?= xlt('All patients within thresholds') ?></div>
    </div>
  <?php else: ?>

  <div class="row g-3">
    <div class="col-12 col-xl-8">
      <div id="alertList">
        <?php foreach ($grouped as $grp => $rows):
            $gm = groupMeta($grp);
            if (empty($rows)) continue;
            ?>
          <div class="card mb-3 shadow-sm">
            <div class="group-header d-flex align-items-center gap-2">
              <span class="badge <?= $gm['cls'] ?>"><?= htmlspecialchars($gm['label']) ?></span>
              <?= count($rows) ?> <?= xlt('alert(s)') ?>
            </div>
            <div class="table-responsive">
              <table class="table table-sm align-middle mb-0">
                <thead style="font-size:.75rem;" class="text-secondary">
                  <tr>
                    <th><?= xlt('Severity') ?></th>
                    <th><?= xlt('Episode') ?></th>
                    <th><?= xlt('Alert') ?></th>
                    <th><?= xlt('Detail') ?></th>
                    <th><?= xlt('Actions') ?></th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $a):
                    $eid = (int)$a['episode_id'];
                    $key = AlertAckRepository::key((string)$a['type'], $eid);
                    ?>
                  <tr class="<?= rowClass((string)$a['severity']) ?>">
                    <td>
                      <span class="badge <?= severityBadge((string)$a['severity']) ?>">
                        <?= htmlspecialchars((string)$a['severity']) ?>
                      </span>
                    </td>
                    <td class="text-nowrap">
                      <a class="text-decoration-none link-primary"
                         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>#ep<?= $eid ?>">
                        #<?= $eid ?>
                      </a>
                      <span class="text-muted small">&bull; PID <?= (int)$a['pid'] ?></span>
                    </td>
                    <td>
                      <span class="fw-semibold"><?= typeLabel((string)$a['type']) ?></span><br>
                      <span class="small"><?= htmlspecialchars((string)$a['message']) ?></span>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars((string)$a['detail']) ?></td>
                    <td>
                      <a href="triage.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= $eid ?>"
                         class="btn btn-sm btn-outline-info me-1" style="font-size:.72rem;">
                        <?= xlt('Vitals') ?>
                      </a>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">
                        <input type="hidden" name="action"          value="ack">
                        <input type="hidden" name="alert_key"       value="<?= htmlspecialchars($key) ?>">
                        <select name="snooze_min" class="form-select form-select-sm d-inline-block"
                                style="width:auto; display:inline!important; font-size:.72rem;">
                          <option value="15">15m</option>
                          <option value="30" selected>30m</option>
                          <option value="60">60m</option>
                          <option value="120">2h</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary snooze-btn"
                                title="<?= xlt('Snooze this alert') ?>">
                          <?= xlt('Snooze') ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-12 col-xl-4">
      <div class="card shadow-sm">
        <div class="card-header small"><?= xlt('Active Patients') ?> (<?= count($data['boardRows']) ?>)</div>
        <div class="list-group list-group-flush" style="max-height:80vh; overflow-y:auto; font-size:.82rem;">
          <?php
            $epAlertCount = [];
            foreach ($alerts as $a) {
                $epAlertCount[(int)$a['episode_id']] = ($epAlertCount[(int)$a['episode_id']] ?? 0) + 1;
            }
            $epCritical = [];
            foreach ($alerts as $a) {
                if ($a['severity'] === 'CRITICAL') {
                    $epCritical[(int)$a['episode_id']] = true;
                }
            }
            foreach ($data['boardRows'] as $e):
                $eid    = (int)$e['id'];
                $aCnt   = $epAlertCount[$eid] ?? 0;
                $isCrit = !empty($epCritical[$eid]);
                ?>
          <a class="list-group-item list-group-item-action py-2 <?= $isCrit ? 'list-group-item-danger' : '' ?>"
             href="triage.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= $eid ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small">#<?= $eid ?> &middot; PID <?= (int)$e['pid'] ?></div>
                <?php if (!empty($e['chief_complaint'])): ?>
                  <div class="text-muted text-truncate" style="max-width:200px;">
                    <?= htmlspecialchars((string)$e['chief_complaint']) ?>
                  </div>
                <?php endif; ?>
              </div>
                <?php if ($aCnt > 0): ?>
                <span class="badge <?= $isCrit ? 'text-bg-danger' : 'oei-badge-warning' ?>">
                    <?= $aCnt ?>
                </span>
              <?php endif; ?>
            </div>
                <?php
                $loc     = $e['location_name'] ?? null;
                $elapsed = institutional_human_elapsed((string)($e['start_datetime'] ?? ''));
                ?>
            <div class="d-flex gap-2 mt-1 flex-wrap">
                <?php if ($loc): ?>
                <span class="badge text-bg-info"><?= htmlspecialchars((string)$loc) ?></span>
              <?php else: ?>
                <span class="badge text-bg-secondary"><?= xlt('No room') ?></span>
              <?php endif; ?>
                <?php if ($elapsed): ?>
                <span class="text-muted"><?= htmlspecialchars($elapsed) ?></span>
              <?php endif; ?>
                <?php if (!empty($e['acuity_esi'])): ?>
                <span class="badge text-bg-dark">ESI <?= htmlspecialchars((string)$e['acuity_esi']) ?></span>
              <?php endif; ?>
            </div>
          </a>
            <?php endforeach; ?>
          <?php if (empty($data['boardRows'])): ?>
            <div class="list-group-item text-muted"><?= xlt('No active episodes') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
const FACILITY_ID  = <?= (int)$facilityId ?>;
const REFRESH_SECS = 60;
const BASE_URL     = 'alerts.php?facility_id=' + FACILITY_ID + '&json=1';

let countdown    = REFRESH_SECS;
let soundEnabled = false;
let prevCritical = <?= (int)($summary['critical'] > 0) ?>;

let audioCtx = null;
function getAudioCtx() {
    if (!audioCtx) {
        audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    }
    return audioCtx;
}
function beep(freq, duration, gain = 0.3) {
    try {
        const ctx = getAudioCtx();
        const osc = ctx.createOscillator();
        const vol = ctx.createGain();
        osc.connect(vol);
        vol.connect(ctx.destination);
        osc.frequency.value = freq;
        vol.gain.setValueAtTime(gain, ctx.currentTime);
        vol.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + duration);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + duration);
    } catch(e) {}
}
function alertTone() {
    beep(880, 0.15);
    setTimeout(() => beep(1100, 0.15), 200);
    setTimeout(() => beep(880, 0.25), 400);
}

document.getElementById('soundToggle').addEventListener('click', function() {
    soundEnabled = !soundEnabled;
    this.textContent = soundEnabled ? '🔔' : '🔕';
    this.title = soundEnabled ? 'Sound on' : 'Sound off';
    if (soundEnabled) {
        try { getAudioCtx().resume(); } catch(e) {}
        beep(660, 0.1, 0.1);
    }
});

const countEl = document.getElementById('countdown');
setInterval(() => {
    countdown--;
    if (countEl) countEl.textContent = countdown;
    if (countdown <= 0) refreshNow();
}, 1000);

function refreshNow() {
    countdown = REFRESH_SECS;
    if (countEl) countEl.textContent = countdown;
    const dot = document.getElementById('statusDot');
    if (dot) { dot.classList.remove('green'); dot.style.background = '#faad14'; }

    fetch(BASE_URL)
        .then(r => r.json())
        .then(data => {
            const newCrit = data.summary.critical || 0;
            if (soundEnabled && newCrit > prevCritical) alertTone();
            prevCritical = newCrit;
            if (dot) { dot.classList.add('green'); dot.style.background = ''; }
            window.location.reload();
        })
        .catch(() => {
            if (dot) { dot.style.background = '#ff4d4f'; dot.classList.remove('green'); }
        });
}
</script>
</body>
</html>







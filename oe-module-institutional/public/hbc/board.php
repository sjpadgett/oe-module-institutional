<?php

/**
 * public/hbc/board.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

/**
 * public/hbc/board.php — Home-Based Care Visit Board
 *
 * Two panels:
 *   1. Referral Queue   — new/triaged referrals awaiting scheduling
 *   2. Today's Schedule — visits for the selected date, sortable by time
 *
 * Quick status advance (SCHEDULED→EN_ROUTE→ARRIVED→COMPLETE) via JSON POST.
 * GPS capture on arrival via Geolocation API → JSON POST.
 * Date navigation: ← yesterday | Today | tomorrow →
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcBoard\Controller\HbcBoardController;

if (!$manifest->featureEnabled('hbc_board')) {
    oei_exit_with_alert(xlt('Home-Based Care Board is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$_csrf      = \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken();
$_hbcBase   = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

$controller = new HbcBoardController();
$data       = $controller->handle($facilityId);

$date        = $data['date'];
$metrics     = $data['metrics'] ?? [];
$actionQueue = $data['action_queue'] ?? [];
$referrals   = $data['referrals'];
$visits      = $data['visits'];

$dateLabel  = (new \DateTime($date))->format('D d M Y');
$prevDate   = (new \DateTime($date))->modify('-1 day')->format('Y-m-d');
$nextDate   = (new \DateTime($date))->modify('+1 day')->format('Y-m-d');
$isToday    = ($date === date('Y-m-d'));

// Batch patient names
$_allPids = array_unique(array_merge(
    array_column($actionQueue, 'pid'),
    array_column($referrals,   'pid'),
    array_column($visits,      'pid')
));
$_names = oei_patient_names(array_map('intval', $_allPids));

$pageTitle = xlt('Visit Board — Home-Based Care');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <meta name="oei-csrf" content="<?= htmlspecialchars(CsrfUtils::collectCsrfToken()) ?>">
  <style>
    .hbc-board-header  { background:linear-gradient(135deg,#2c5f4a,#4a7c59); color:#fff; border-radius:.5rem; }
    .visit-card        { border-left:4px solid #4a7c59; transition:box-shadow .15s; }
    .visit-card:hover  { box-shadow:0 2px 8px rgba(0,0,0,.12); }
    .visit-card.status-en-route  { border-left-color:#0dcaf0; }
    .visit-card.status-arrived   { border-left-color:#0d6efd; }
    .visit-card.status-complete  { border-left-color:#198754; opacity:.85; }
    .visit-card.status-missed    { border-left-color:#dc3545; opacity:.8; }
    .referral-row      { border-left:4px solid #f4a261; }
    .urgency-emergent  { border-left-color:#dc3545 !important; }
    .urgency-urgent    { border-left-color:#ffc107 !important; }
    .advance-btn       { min-width:110px; }
    @media (max-width:576px) {
      .visit-card .visit-meta { font-size:.78rem; }
    }
    #oei-offline-banner {
      display:none; background:#856404; color:#fff3cd;
      padding:.5rem 1rem; font-size:.85rem; text-align:center;
      border-bottom:1px solid #997404; border-radius:.375rem; margin-bottom:.5rem;
    }
    #oei-offline-banner.show { display:block; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div id="oei-offline-banner" role="alert">
  📵 <?= xlt('Offline — data saved locally, will sync on reconnect.') ?>
</div>
<div class="container-fluid p-3">

<!-- Header -->
<div class="hbc-board-header p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <h4 class="mb-0 fw-bold">🏡 <?= xlt('Home-Based Care') ?> — <?= xlt('Visit Board') ?></h4>
      <div class="text-white-50 small mt-1">
        <?= xlt('Facility') ?> #<?= htmlspecialchars((string)$facilityId) ?>
        · <?= count($referrals) ?> <?= xlt('referrals pending') ?>
        · <?= count($visits) ?> <?= xlt('visits today') ?>
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <?php if ($manifest->featureEnabled('hbc_intake')): ?>
      <a href="<?= htmlspecialchars($_hbcBase . 'intake.php?facility_id=' . $facilityId) ?>"
         class="btn btn-sm btn-light fw-semibold">+ <?= xlt('New Referral') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Outcome / KPI strip -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm h-100"><div class="card-body py-2">
      <div class="text-muted small"><?= xlt('Active on Service') ?></div>
      <div class="fs-4 fw-bold"><?= (int)($metrics['active_patients'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm h-100"><div class="card-body py-2">
      <div class="text-muted small"><?= xlt('Pending Referrals') ?></div>
      <div class="fs-4 fw-bold text-warning"><?= (int)($metrics['pending_referrals'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm h-100"><div class="card-body py-2">
      <div class="text-muted small"><?= xlt('Visits This Day') ?></div>
      <div class="fs-4 fw-bold"><?= (int)($metrics['today_visits'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm h-100"><div class="card-body py-2">
      <div class="text-muted small"><?= xlt('Completed 7d') ?></div>
      <div class="fs-4 fw-bold text-success"><?= (int)($metrics['week_completed'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-6 col-lg-2">
    <div class="card shadow-sm h-100"><div class="card-body py-2">
      <div class="text-muted small"><?= xlt('Open Actions') ?></div>
      <div class="fs-4 fw-bold text-danger"><?= (int)($metrics['open_actions'] ?? 0) ?></div>
    </div></div>
  </div>
</div>

<div class="card shadow-sm mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <span class="fw-semibold">⚡ <?= xlt('Priority Action Queue') ?></span>
    <span class="text-muted small"><?= xlt('Focus patients needing the next touch') ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (!$actionQueue): ?>
      <div class="text-muted small p-3"><?= xlt('No high-priority actions right now.') ?></div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle" style="font-size:.84rem;">
          <thead class="table-light">
            <tr>
              <th><?= xlt('Patient') ?></th>
              <th><?= xlt('Priority') ?></th>
              <th><?= xlt('Why') ?></th>
              <th><?= xlt('Follow-up') ?></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($actionQueue as $aq): ?>
            <?php $prioClass = $aq['priority_band'] === 'high' ? 'bg-danger' : ($aq['priority_band'] === 'medium' ? 'bg-warning text-dark' : 'bg-secondary'); ?>
            <tr>
              <td class="fw-semibold">
                <?= oei_fmt_patient($aq['pid'], $_names) ?>
                <div class="text-muted small"><?= htmlspecialchars(implode(', ', array_filter([$aq['service_city'], $aq['primary_diagnosis']]))) ?></div>
              </td>
              <td><span class="badge <?= $prioClass ?>"><?= htmlspecialchars(strtoupper((string)$aq['priority_band'])) ?></span></td>
              <td>
                <?php foreach (array_slice($aq['reasons'], 0, 3) as $reason): ?>
                  <div class="small"><?= htmlspecialchars($reason) ?></div>
                <?php endforeach; ?>
              </td>
              <td class="small text-muted">
                <?php if (!empty($aq['next_visit_due_date'])): ?>
                  <?= xlt('Due') ?>: <?= htmlspecialchars((string)$aq['next_visit_due_date']) ?>
                  <?php if (!empty($aq['next_visit_type'])): ?> · <?= htmlspecialchars((string)$aq['next_visit_type']) ?><?php endif; ?>
                <?php else: ?>
                  <?= xlt('No recommendation on file') ?>
                <?php endif; ?>
              </td>
              <td class="text-nowrap">
                <a href="<?= htmlspecialchars($_hbcBase . 'profile.php?episode_id=' . $aq['episode_id'] . '&pid=' . $aq['pid'] . '&facility_id=' . $facilityId) ?>"
                   class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:.15rem .4rem;"><?= xlt('Open') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Panel 1: Referral Queue ─────────────────────────────────────────── -->
<div class="card shadow-sm mb-4">
  <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
    <span>📋 <?= xlt('Referral Queue') ?>
      <?php if ($referrals): ?>
        <span class="badge bg-warning text-dark ms-1"><?= count($referrals) ?></span>
      <?php endif; ?>
    </span>
  </div>
  <div class="card-body p-0">
    <?php if (!$referrals): ?>
      <div class="text-muted small p-3">✓ <?= xlt('No pending referrals.') ?></div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Patient') ?></th>
            <th><?= xlt('Location') ?></th>
            <th><?= xlt('Reason') ?></th>
            <th><?= xlt('Source') ?></th>
            <th><?= xlt('Waiting') ?></th>
            <th><?= xlt('Status') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($referrals as $r):
            $urgClass = strtolower($r['urgency']) !== 'routine' ? 'urgency-' . strtolower($r['urgency']) : '';
        ?>
        <tr class="referral-row <?= $urgClass ?>">
          <td class="fw-semibold">
            <?= oei_fmt_patient($r['pid'], $_names) ?>
          </td>
          <td class="text-muted">
            <?= htmlspecialchars(implode(', ', array_filter([$r['service_city'], $r['service_state']]))) ?>
          </td>
          <td><?= htmlspecialchars(mb_strimwidth($r['referral_reason'], 0, 50, '…')) ?></td>
          <td class="text-muted"><?= htmlspecialchars($r['referral_source']) ?></td>
          <td class="text-muted">
            <?php if ($r['days_waiting'] > 3): ?>
              <span class="text-danger fw-semibold"><?= $r['days_waiting'] ?>d</span>
            <?php else: ?>
              <?= $r['days_waiting'] ?>d
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= HbcReferralStatus::badge($r['referral_status']) ?>">
              <?= htmlspecialchars(HbcReferralStatus::label($r['referral_status'])) ?>
            </span>
            <?php if ($r['urgency'] !== 'ROUTINE'): ?>
              <span class="badge <?= $r['urgency'] === 'EMERGENT' ? 'bg-danger' : 'bg-warning text-dark' ?> ms-1">
                <?= htmlspecialchars($r['urgency']) ?>
              </span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($manifest->featureEnabled('hbc_profile')): ?>
            <a href="<?= htmlspecialchars($_hbcBase . 'profile.php?episode_id=' . $r['episode_id'] . '&pid=' . $r['pid'] . '&facility_id=' . $facilityId) ?>"
               class="btn btn-xs btn-outline-secondary" style="font-size:.75rem;padding:.2rem .5rem;">
              <?= xlt('Open') ?> →
            </a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Panel 2: Day Schedule ───────────────────────────────────────────── -->
<div class="card shadow-sm">
  <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="fw-semibold">📅 <?= xlt('Schedule') ?> — <?= htmlspecialchars($dateLabel) ?>
      <?php if ($isToday): ?>
        <span class="badge bg-primary ms-1"><?= xlt('Today') ?></span>
      <?php endif; ?>
    </span>
    <!-- Date navigation -->
    <div class="d-flex gap-1 align-items-center">
      <a href="?facility_id=<?= $facilityId ?>&date=<?= $prevDate ?>"
         class="btn btn-sm btn-outline-secondary">‹</a>
      <form method="GET" class="d-flex gap-1">
        <input type="hidden" name="facility_id" value="<?= $facilityId ?>">
        <input type="date" name="date" class="form-control form-control-sm" style="width:145px"
               value="<?= htmlspecialchars($date) ?>">
        <button class="btn btn-sm btn-outline-secondary"><?= xlt('Go') ?></button>
      </form>
      <a href="?facility_id=<?= $facilityId ?>&date=<?= date('Y-m-d') ?>"
         class="btn btn-sm btn-outline-secondary <?= $isToday ? 'active' : '' ?>"><?= xlt('Today') ?></a>
      <a href="?facility_id=<?= $facilityId ?>&date=<?= $nextDate ?>"
         class="btn btn-sm btn-outline-secondary">›</a>
    </div>
  </div>
  <div class="card-body">
    <?php if (!$visits): ?>
      <div class="text-muted small py-3 text-center">
        <?= xlt('No visits scheduled for this date.') ?>
      </div>
    <?php else: ?>
    <div class="d-flex gap-2 flex-wrap mb-3">
      <input type="search" id="hbcVisitSearch" class="form-control form-control-sm" style="max-width:220px" placeholder="<?= xla('Search patient, city, diagnosis') ?>">
      <select id="hbcVisitStatusFilter" class="form-select form-select-sm" style="max-width:170px">
        <option value=""><?= xlt('All statuses') ?></option>
        <?php foreach (['SCHEDULED','EN_ROUTE','ARRIVED','COMPLETE','MISSED','REFUSED'] as $_status): ?>
          <option value="<?= $_status ?>"><?= htmlspecialchars(HbcVisitStatus::label($_status)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row g-3" id="hbcVisitGrid">
    <?php foreach ($visits as $v):
        $statusClass = 'status-' . strtolower(str_replace('_', '-', $v['status']));
        $isFinal     = HbcVisitStatus::isFinal($v['status']);
        $nextStatus  = HbcVisitStatus::next($v['status']);
        $timeStr     = $v['scheduled_datetime'] ? (new \DateTime($v['scheduled_datetime']))->format('H:i') : '—';
    ?>
    <?php $_searchBlob = strtolower(trim(implode(' ', array_filter([(string)($v['fname'] ?? ''), (string)($v['lname'] ?? ''), (string)($v['service_city'] ?? ''), (string)($v['primary_diagnosis'] ?? '')])))); ?>
    <div class="col-12 col-md-6 col-xl-4 hbc-visit-col" data-status="<?= htmlspecialchars((string)$v['status']) ?>" data-search="<?= htmlspecialchars($_searchBlob) ?>">
      <div class="card visit-card <?= $statusClass ?>">
        <div class="card-body py-2 px-3">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold">
                <?= oei_fmt_patient($v['pid'], $_names) ?>
              </div>
              <div class="visit-meta text-muted small">
                <span class="badge <?= HbcVisitType::badge($v['visit_type']) ?> me-1">
                  <?= htmlspecialchars(HbcVisitType::short($v['visit_type'])) ?>
                </span>
                🕐 <?= htmlspecialchars($timeStr) ?>
                <?php if ($v['clinician_name']): ?>
                  · <?= htmlspecialchars($v['clinician_name']) ?>
                <?php endif; ?>
              </div>
              <?php
                $durationStr = '';
                if ($v['status'] === 'COMPLETE' && !empty($v['actual_start']) && !empty($v['actual_end'])) {
                    $durMin = (int)((strtotime($v['actual_end']) - strtotime($v['actual_start'])) / 60);
                    if ($durMin > 0) {
                        $durationStr = ($durMin >= 60)
                            ? floor($durMin / 60) . 'h ' . ($durMin % 60) . 'm'
                            : $durMin . 'm';
                    }
                }
              ?>
              <?php if ($durationStr !== ''): ?>
              <div class="visit-meta text-muted small">
                ⏱ <?= htmlspecialchars($durationStr) ?>
                <?php if ($v['mileage_miles'] !== null): ?>
                  Â· <?= number_format((float)$v['mileage_miles'], 1) ?> mi
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if ($v['address_line1'] || $v['service_city']): ?>
              <div class="visit-meta text-muted small mt-1">
                📍 <?= htmlspecialchars(implode(', ', array_filter([$v['address_line1'], $v['service_city']]))) ?>
                <?php if ($v['access_notes']): ?>
                  <span class="text-warning" title="<?= htmlspecialchars($v['access_notes']) ?>">⚠</span>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if (!empty($v['route_sequence']) || !empty($v['window_start_datetime']) || !empty($v['window_end_datetime']) || !empty($v['travel_notes'])): ?>
              <div class="visit-meta text-muted small mt-1">
                <?php if (!empty($v['route_sequence'])): ?>#<?= (int)$v['route_sequence'] ?><?php endif; ?>
                <?php if (!empty($v['window_start_datetime']) || !empty($v['window_end_datetime'])): ?>
                  · <?= htmlspecialchars(trim((!empty($v['window_start_datetime']) ? (new DateTime($v['window_start_datetime']))->format('H:i') : '') . ' – ' . (!empty($v['window_end_datetime']) ? (new DateTime($v['window_end_datetime']))->format('H:i') : ''), ' –')) ?>
                <?php endif; ?>
                <?php if (!empty($v['travel_notes'])): ?>
                  <div>🚗 <?= htmlspecialchars(mb_strimwidth((string)$v['travel_notes'], 0, 42, '…')) ?></div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?php if ($v['outcome_summary']): ?>
              <div class="visit-meta small mt-1 fst-italic text-muted">
                <?= htmlspecialchars(mb_strimwidth($v['outcome_summary'], 0, 60, '…')) ?>
              </div>
              <?php endif; ?>
            </div>
            <div class="d-flex flex-column gap-1 align-items-end">
              <span class="badge <?= HbcVisitStatus::badge($v['status']) ?>">
                <?= htmlspecialchars(HbcVisitStatus::label($v['status'])) ?>
              </span>
              <?php if ($v['is_draft']): ?>
                <span class="badge bg-warning text-dark" style="font-size:.65rem;">DRAFT</span>
              <?php endif; ?>
              <?php if ($v['sig_obtained']): ?>
                <span class="badge bg-success" style="font-size:.65rem;" title="<?= xlt('Patient signed') ?>">✓ <?= xlt('Signed') ?></span>
              <?php endif; ?>
              <?php if ($manifest->featureEnabled('observations') && !empty($v['obs_flagged_count'])): ?>
                <a href="<?= htmlspecialchars('../shared/observations.php?episode_id=' . $v['episode_id'] . '&pid=' . $v['pid'] . '&facility_id=' . $facilityId) ?>"
                   class="badge bg-warning text-dark text-decoration-none" style="font-size:.65rem;"
                   title="<?= xlt('Flagged observations in last 24h') ?>">
                  &#128225;&#9888; <?= (int)$v['obs_flagged_count'] ?>
                </a>
              <?php endif; ?>
            </div>
          </div>
          <!-- Actions -->
          <div class="d-flex gap-1 mt-2 flex-wrap">
            <?php if ($manifest->featureEnabled('hbc_profile')): ?>
            <a href="<?= htmlspecialchars($_hbcBase . 'profile.php?episode_id=' . $v['episode_id'] . '&pid=' . $v['pid'] . '&facility_id=' . $facilityId) ?>"
               class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">📋 <?= xlt('Profile') ?></a>
            <?php endif; ?>
            <?php if ($manifest->featureEnabled('hbc_visit') && !$isFinal): ?>
            <a href="<?= htmlspecialchars($_hbcBase . 'visit.php?visit_id=' . $v['visit_id'] . '&episode_id=' . $v['episode_id'] . '&pid=' . $v['pid'] . '&facility_id=' . $facilityId) ?>"
               class="btn btn-sm btn-outline-success" style="font-size:.75rem;">🩺 <?= xlt('Visit') ?></a>
            <?php endif; ?>
            <?php if (!$isFinal && $nextStatus): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-primary advance-btn"
                    style="font-size:.75rem;"
                    data-visit-id="<?= $v['visit_id'] ?>"
                    data-csrf="<?= htmlspecialchars($_csrf) ?>"
                    onclick="advanceVisit(this)">
              → <?= htmlspecialchars(HbcVisitStatus::label($nextStatus)) ?>
            </button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

</div><!-- /container -->

<script>
// ── Online/offline handling ──────────────────────────────────────────────
(function(){
    const banner = document.getElementById('oei-offline-banner');
    function setOffline(off) { if (banner) banner.classList.toggle('show', off); }
    if (!navigator.onLine) setOffline(true);
    window.addEventListener('offline', () => setOffline(true));
    window.addEventListener('online',  () => {
        setOffline(false);
        // Trigger HBC background sync on reconnect
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function(reg){
                if ('sync' in reg) reg.sync.register('oei-hbc-sync').catch(()=>{});
            });
        }
    });
    window.addEventListener('oei:offline', () => setOffline(true));
    window.addEventListener('oei:online',  () => setOffline(false));
    window.addEventListener('oei:hbc-sync-complete', function(){
        // Brief flash then reload board to show updated visit statuses
        setTimeout(() => location.reload(), 800);
    });
})();

function advanceVisit(btn) {
    const visitId = btn.dataset.visitId;
    const csrf    = btn.dataset.csrf;
    const orig    = btn.innerHTML;
    btn.disabled  = true;
    const origText = btn.textContent;
    btn.textContent = '…';

    const tryGps = origText.indexOf('Arrived') !== -1 || origText.indexOf('Complete') !== -1 || origText.indexOf('→') !== -1;

    const doAdvance = (lat, lng) => {
        const fd = new FormData();
        fd.append('action',           'advance_visit');
        fd.append('csrf_token_form',  csrf);
        fd.append('visit_id',         visitId);

        fetch('board.php?facility_id=<?= $facilityId ?>', { method:'POST', body:fd })
            .then(r => r.json())
            .then(json => {
                if (json.ok) {
                    // If we also have GPS, post it
                    if (lat && lng) {
                        const gd = new FormData();
                        gd.append('action','record_gps');
                        gd.append('csrf_token_form', csrf);
                        gd.append('visit_id', visitId);
                        gd.append('lat', lat);
                        gd.append('lng', lng);
                        fetch('board.php?facility_id=<?= $facilityId ?>', { method:'POST', body:gd });
                    }
                    location.reload();
                } else {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    alert('<?= xlt('Status update failed. Please refresh.') ?>');
                }
            })
            .catch(() => { btn.disabled=false; btn.innerHTML=orig; });
    };

    if (tryGps && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => doAdvance(pos.coords.latitude, pos.coords.longitude),
            ()  => doAdvance(null, null),
            { timeout: 5000 }
        );
    } else {
        doAdvance(null, null);
    }
}
</script>

<!-- Bootstrap CSS loaded in <head> via institutional_bootstrap5_href() -->
<?= institutional_bootstrap5_js_tag() ?>

<script>
(function() {
  const q = document.getElementById('hbcVisitSearch');
  const s = document.getElementById('hbcVisitStatusFilter');
  const cards = Array.from(document.querySelectorAll('.hbc-visit-col'));
  function applyFilters() {
    const qv = (q?.value || '').toLowerCase().trim();
    const sv = (s?.value || '').trim();
    cards.forEach(card => {
      const hay = card.getAttribute('data-search') || '';
      const status = card.getAttribute('data-status') || '';
      const matchesQ = !qv || hay.indexOf(qv) !== -1;
      const matchesS = !sv || status === sv;
      card.style.display = (matchesQ && matchesS) ? '' : 'none';
    });
  }
  q?.addEventListener('input', applyFilters);
  s?.addEventListener('change', applyFilters);
})();
</script>

</body>
</html>



























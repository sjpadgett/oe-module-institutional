<?php

/**
 * public/al/activity.php
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
 * public/al/activity.php — Activity & Engagement Log
 *
 * Two modes:
 *   facility (default)  — Date picker + session list for all residents.
 *                          Log new sessions. Show 7-day type summary.
 *   resident            — Per-episode 30-day history + participation stats.
 *                          Accessed from resident nav tab (episode_id present).
 *
 * Regulatory context:
 *   State AL licensing requires documentation of structured activities.
 *   This page is used by activity coordinators and CNAs to log attendance
 *   and by surveyors / directors to review engagement patterns.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Controller\AlActivityController;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Repository\AlActivityRepository;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Repository\ResidentBoardRepository;

if (!$manifest->featureEnabled('al_activity')) {
    oei_exit_with_alert(xlt('Activity Log is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$episodeId  = (int)($_GET['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? 0);
$mode       = $episodeId > 0 ? 'resident' : 'facility';
$viewDate   = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $viewDate)) {
    $viewDate = date('Y-m-d');
}

$controller = new AlActivityController(new AlActivityRepository());

if ($mode === 'resident') {
    // Resolve pid from episode if needed
    if ($pid === 0 && function_exists('sqlQuery')) {
        $epRow = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        $pid   = (int)($epRow['pid'] ?? 0);
    }
    $data = $controller->handleResident($episodeId, $facilityId);
} else {
    // Fetch active AL residents for the session log form
    $boardRepo = new ResidentBoardRepository();
    $residents = $boardRepo->fetchActiveResidents($facilityId);

    $data = $controller->handleFacility($facilityId, $viewDate, $residents, $userId);
}

$activePage = 'activity';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

$types  = AlActivityController::TYPES;
$levels = AlActivityController::LEVELS;

$levelBadge = ['FULL' => 'success', 'PARTIAL' => 'warning', 'REFUSED' => 'secondary', 'ABSENT' => 'light text-dark'];
$levelIcon  = ['FULL' => '✓', 'PARTIAL' => '½', 'REFUSED' => '✗', 'ABSENT' => '—'];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Activity & Engagement Log') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .activity-type-chip { display:inline-flex; align-items:center; gap:.3rem;
                          padding:.2rem .6rem; border-radius:999px;
                          font-size:.75rem; font-weight:600;
                          background:var(--bs-success-bg-subtle);
                          color:var(--bs-success-text-emphasis); }
    .attend-grid th   { font-size:.72rem; text-align:center; white-space:nowrap; }
    .attend-grid td   { font-size:.78rem; vertical-align:middle; }
    .participate-bar  { height:6px; border-radius:3px; background:var(--bs-border-color); overflow:hidden; }
    .participate-fill { height:100%; background:#4a7c59; border-radius:3px; }
    .session-card     { border-left:3px solid #4a7c59; }
    .session-card.music   { border-left-color:#6f42c1; }
    .session-card.exercise{ border-left-color:#0d6efd; }
    .session-card.cognitive{ border-left-color:#fd7e14; }
    .session-card.dining  { border-left-color:#20c997; }
    .log-form-card    { border:2px dashed var(--bs-border-color); }
    .log-form-card:hover { border-color: #4a7c59; }
    .type-select-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(150px,1fr)); gap:.4rem; }
    .type-opt         { padding:.4rem .6rem; border:1px solid var(--bs-border-color);
                        border-radius:.375rem; cursor:pointer; font-size:.8rem; }
    .type-opt:hover   { border-color:#4a7c59; background:var(--bs-success-bg-subtle); }
    .type-opt.selected{ border-color:#4a7c59; background:var(--bs-success-bg-subtle); font-weight:600; }
    .summary-pill     { padding:.25rem .75rem; border-radius:999px; font-size:.8rem;
                        background:var(--bs-secondary-bg); border:1px solid var(--bs-border-color); }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid px-3 pt-2">

<?php if ($mode === 'resident'): ?>
    <?php require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php'; ?>
<?php endif; ?>

<?php if ($data['flash'] ?? ''): ?>
<div class="alert alert-success alert-dismissible py-2 mx-1" role="alert">
  ✔ <?= htmlspecialchars($data['flash']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($data['error'] ?? ''): ?>
<div class="alert alert-danger py-2 mx-1" role="alert">
  ⚠ <?= htmlspecialchars($data['error']) ?>
</div>
<?php endif; ?>

<?php /* ═══════════════════════════════════════════════════════════════════════
       FACILITY MODE — date view with session list + log form
       ═══════════════════════════════════════════════════════════════════════ */ ?>

<?php if ($mode === 'facility'): ?>

<div class="d-flex align-items-center gap-3 mb-3 mx-1 mt-2">
  <h5 class="mb-0 fw-bold">🎭 <?= xlt('Activity & Engagement Log') ?></h5>

  <!-- Date nav -->
  <form method="GET" class="d-flex align-items-center gap-2 ms-auto">
    <input type="hidden" name="facility_id" value="<?= $facilityId ?>">
    <a href="?facility_id=<?= $facilityId ?>&date=<?= date('Y-m-d', strtotime($viewDate . ' -1 day')) ?>"
       class="btn btn-sm btn-outline-secondary">‹</a>
    <input type="date" name="date" class="form-control form-control-sm"
           value="<?= htmlspecialchars($viewDate) ?>"
           onchange="this.form.submit()" style="width:140px">
    <a href="?facility_id=<?= $facilityId ?>&date=<?= date('Y-m-d', strtotime($viewDate . ' +1 day')) ?>"
       class="btn btn-sm btn-outline-secondary">›</a>
    <?php if ($viewDate !== date('Y-m-d')): ?>
    <a href="?facility_id=<?= $facilityId ?>&date=<?= date('Y-m-d') ?>"
       class="btn btn-sm btn-outline-success">Today</a>
    <?php endif; ?>
  </form>
</div>

<!-- 7-day activity type summary pills -->
    <?php if (!empty($data['typeSummary'])): ?>
<div class="d-flex flex-wrap gap-2 mb-3 mx-1">
  <span class="text-muted small align-self-center">Last 7 days:</span>
        <?php foreach ($data['typeSummary'] as $tKey => $tCnt): ?>
            <?php $tInfo = $types[$tKey] ?? ['label' => $tKey, 'icon' => '📋']; ?>
    <span class="summary-pill">
            <?= htmlspecialchars($tInfo['icon']) ?>
            <?= htmlspecialchars(xlt($tInfo['label'])) ?>
      <strong><?= $tCnt ?></strong>
    </span>
  <?php endforeach; ?>
</div>
    <?php endif; ?>

<div class="row g-3 mx-0">
  <!-- Sessions column -->
  <div class="col-lg-8">
    <?php if (empty($data['sessions'])): ?>
    <div class="text-muted text-center py-5">
      <div style="font-size:2.5rem">🎭</div>
      <div class="mt-2"><?= xlt('No activities logged for this date.') ?></div>
      <div class="small"><?= xlt('Use the form to log the first session.') ?></div>
    </div>
    <?php else: ?>
        <?php foreach ($data['sessions'] as $sess): ?>
            <?php
            $tKey  = strtolower($sess['activity_type'] ?? '');
            $tInfo = $types[strtoupper($tKey)] ?? ['label' => $tKey, 'icon' => '📋'];
            $att   = $sess['attendance'] ?? [];
            $cssClass = in_array($tKey, ['music','exercise','cognitive','dining_social']) ? $tKey : '';
            ?>
      <div class="card mb-3 session-card <?= $cssClass ?>">
        <div class="card-header d-flex align-items-center gap-2 py-2">
          <span class="fs-5"><?= htmlspecialchars($tInfo['icon']) ?></span>
          <div class="flex-grow-1">
            <span class="fw-semibold"><?= htmlspecialchars($sess['activity_name']) ?></span>
            <span class="text-muted small ms-2">
              <?= htmlspecialchars(date('g:i A', strtotime($sess['start_time']))) ?>
              · <?= (int)$sess['duration_minutes'] ?> min
              <?php if ($sess['location'] ?? ''): ?>
                · <?= htmlspecialchars($sess['location']) ?>
              <?php endif; ?>
            </span>
          </div>
          <span class="badge bg-success">
            <?= (int)$sess['attendance_count'] ?> attended
          </span>
            <?php if ($sess['led_by_name'] ?? ($sess['user_fname'] ?? '')): ?>
          <span class="text-muted small">
                <?= htmlspecialchars($sess['led_by_name']
                ?? trim(($sess['user_fname'] ?? '') . ' ' . ($sess['user_lname'] ?? ''))) ?>
          </span>
          <?php endif; ?>
        </div>
        <div class="card-body py-2">

          <!-- Attendance grid -->
          <table class="table table-sm attend-grid mb-2">
            <thead>
              <tr>
                <th class="text-start"><?= xlt('Resident') ?></th>
                <th><?= xlt('Rm') ?></th>
                <th><?= xlt('Participation') ?></th>
                <th class="text-start"><?= xlt('Note') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php
                // Show residents in attendance first, then others
                $inAtt = [];
                $notIn = [];
                foreach ($data['residents'] as $r) {
                    $eid = (string)$r['episode_id'];
                    if (isset($att[$eid])) {
                        $inAtt[$eid] = $r;
                    } else {
                        $notIn[$eid] = $r;
                    }
                }
                ?>
              <?php foreach ($inAtt as $eid => $r): ?>
                    <?php
                    $item  = $att[$eid];
                    $level = strtoupper($item['level'] ?? 'ABSENT');
                    $bg    = $levelBadge[$level] ?? 'secondary';
                    $ico   = $levelIcon[$level] ?? '—';
                    ?>
                <tr>
                  <td><?= htmlspecialchars(trim($r['fname'] . ' ' . $r['lname'])) ?></td>
                  <td class="text-center text-muted"><?= htmlspecialchars($r['room'] ?? '') ?></td>
                  <td class="text-center">
                    <span class="badge bg-<?= $bg ?>"><?= $ico ?> <?= xlt($levels[$level]['label'] ?? $level) ?></span>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars($item['note'] ?? '') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!empty($notIn)): ?>
                <tr class="text-muted" style="font-size:.7rem">
                  <td colspan="4" class="pt-1">
                    <?= xlt('Not recorded') ?>:
                    <?= htmlspecialchars(implode(', ', array_map(
                        fn($r) => trim($r['fname'] . ' ' . $r['lname']),
                        $notIn
                    ))) ?>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>

            <?php if ($sess['notes'] ?? ''): ?>
          <div class="small text-muted p-2 rounded" style="background:var(--bs-tertiary-bg)">
                <?= nl2br(htmlspecialchars($sess['notes'])) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Log new session form -->
  <div class="col-lg-4">
    <div class="card log-form-card">
      <div class="card-header py-2">
        <strong>+ <?= xlt('Log Activity Session') ?></strong>
      </div>
      <div class="card-body">
        <form method="POST" id="logForm">
          <?= \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken() ?>
          <input type="hidden" name="action"      value="log_session">
          <input type="hidden" name="facility_id" value="<?= $facilityId ?>">

          <!-- Type picker -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              <?= xlt('Type') ?> <span class="text-danger">*</span>
            </label>
            <div class="type-select-grid" id="typeGrid">
              <?php foreach ($types as $tKey => $tInfo): ?>
              <label class="type-opt" data-type="<?= htmlspecialchars($tKey) ?>">
                <input type="radio" name="activity_type" value="<?= htmlspecialchars($tKey) ?>"
                       class="d-none" required>
                    <?= htmlspecialchars($tInfo['icon']) ?>
                    <?= htmlspecialchars(xlt($tInfo['label'])) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Activity Name') ?> *</label>
            <input type="text" name="activity_name" class="form-control form-control-sm"
                   placeholder="<?= xlt('e.g. Morning Stretch, Bingo, Guitar Singalong') ?>" required>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Date') ?></label>
              <input type="date" name="activity_date" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($viewDate) ?>">
            </div>
            <div class="col-5">
              <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Start') ?></label>
              <input type="time" name="start_time" class="form-control form-control-sm"
                     value="<?= date('H:i') ?>">
            </div>
          </div>

          <div class="row g-2 mb-2">
            <div class="col-7">
              <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Location') ?></label>
              <input type="text" name="location" class="form-control form-control-sm"
                     placeholder="<?= xlt('Community Room, etc.') ?>">
            </div>
            <div class="col-5">
              <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Mins') ?></label>
              <input type="number" name="duration_minutes" class="form-control form-control-sm"
                     value="45" min="5" max="480">
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Led by') ?></label>
            <input type="text" name="led_by_name" class="form-control form-control-sm"
                   placeholder="<?= xlt('Staff name, role') ?>">
          </div>

          <!-- Attendance quick-set -->
          <div class="mb-2">
            <label class="form-label small fw-semibold text-muted text-uppercase">
              <?= xlt('Attendance') ?>
            </label>
            <?php foreach ($data['residents'] as $r): ?>
                <?php $eid = (string)$r['episode_id']; ?>
              <div class="d-flex align-items-center gap-2 mb-1">
                <div class="text-truncate small fw-semibold" style="width:110px">
                  <?= htmlspecialchars(trim($r['fname'] . ' ' . $r['lname'])) ?>
                </div>
                <select name="level_<?= $eid ?>" class="form-select form-select-sm" style="flex:1">
                  <?php foreach ($levels as $lKey => $lInfo): ?>
                  <option value="<?= $lKey ?>" <?= $lKey === 'FULL' ? 'selected' : '' ?>>
                        <?= $levelIcon[$lKey] ?> <?= xlt($lInfo['label']) ?>
                  </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold text-muted text-uppercase"><?= xlt('Session Notes') ?></label>
            <textarea name="notes" class="form-control form-control-sm" rows="2"
                      placeholder="<?= xlt('Observations, outcomes, notable participation…') ?>"></textarea>
          </div>

          <button type="submit" class="btn btn-success btn-sm w-100 fw-semibold">
            ✔ <?= xlt('Log Session') ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Board link -->
    <div class="mt-2 text-center">
      <a href="board.php?facility_id=<?= $facilityId ?>" class="text-muted small">
        ← <?= xlt('Resident Board') ?>
      </a>
    </div>
  </div>

</div><!-- /row -->

    <?php /* ═══════════════════════════════════════════════════════════════════════
       RESIDENT MODE — participation history from nav tab
       ═══════════════════════════════════════════════════════════════════════ */ ?>

<?php else: ?>

<div class="container-fluid px-2 pt-1">

  <!-- Participation rate banner -->
    <?php if ($data['participation'] !== null): ?>
  <div class="row g-2 mb-3 mx-0">
        <?php
        $s = $data['stats'];
        $pct = $data['participation'];
        $pBar = $pct >= 75 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
        ?>
    <div class="col-6 col-md-3">
      <div class="card text-center py-2">
        <div class="fs-3 fw-bold text-<?= $pBar ?>"><?= $pct ?>%</div>
        <div class="small text-muted"><?= xlt('Participation (30d)') ?></div>
        <div class="participate-bar mt-1 mx-3">
          <div class="participate-fill" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
    </div>
    <div class="col-3 col-md-2">
      <div class="card text-center py-2">
        <div class="fs-4 fw-bold text-success"><?= $s['full'] ?></div>
        <div class="small text-muted"><?= xlt('Full') ?></div>
      </div>
    </div>
    <div class="col-3 col-md-2">
      <div class="card text-center py-2">
        <div class="fs-4 fw-bold text-warning"><?= $s['partial'] ?></div>
        <div class="small text-muted"><?= xlt('Partial') ?></div>
      </div>
    </div>
    <div class="col-3 col-md-2">
      <div class="card text-center py-2">
        <div class="fs-4 fw-bold text-secondary"><?= $s['refused'] ?></div>
        <div class="small text-muted"><?= xlt('Refused') ?></div>
      </div>
    </div>
    <div class="col-3 col-md-2">
      <div class="card text-center py-2">
        <div class="fs-4 fw-bold text-muted"><?= $s['total'] ?></div>
        <div class="small text-muted"><?= xlt('Total') ?></div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Session history -->
    <?php if (empty($data['sessions'])): ?>
  <div class="text-center text-muted py-5">
    <div style="font-size:2.5rem">🎭</div>
    <div class="mt-2"><?= xlt('No activity sessions recorded yet.') ?></div>
  </div>
  <?php else: ?>

      <?php
        $grouped = [];
        foreach ($data['sessions'] as $sess) {
            $grouped[$sess['activity_date']][] = $sess;
        }
        ?>
      <?php foreach ($grouped as $gDate => $daySessions): ?>
    <div class="text-muted small fw-semibold mb-1 mt-3" style="letter-spacing:.05em">
            <?= htmlspecialchars(date('l, F j, Y', strtotime($gDate))) ?>
    </div>
            <?php foreach ($daySessions as $sess):
                $tInfo = $types[$sess['activity_type']] ?? ['label' => $sess['activity_type'], 'icon' => '📋'];
                $eid   = (string)$episodeId;
                $item  = $sess['attendance'][$eid] ?? null;
                $level = $item ? strtoupper($item['level'] ?? 'ABSENT') : null;
                $note  = $item ? ($item['note'] ?? '') : '';
                $bg    = $level ? ($levelBadge[$level] ?? 'secondary') : 'secondary';
                $ico   = $level ? ($levelIcon[$level] ?? '—') : '—';
                ?>
    <div class="card mb-2 session-card" style="border-left-color:<?= $level === 'FULL' ? '#4a7c59' : ($level === 'REFUSED' ? '#6c757d' : '#ffc107') ?>">
      <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
        <span class="fs-5"><?= htmlspecialchars($tInfo['icon']) ?></span>
        <div class="flex-grow-1">
          <div class="fw-semibold small"><?= htmlspecialchars($sess['activity_name']) ?></div>
          <div class="text-muted" style="font-size:.72rem">
                <?= htmlspecialchars(date('g:i A', strtotime($sess['start_time']))) ?>
            · <?= (int)$sess['duration_minutes'] ?> min
                <?php if ($sess['location'] ?? ''): ?>· <?= htmlspecialchars($sess['location']) ?><?php endif; ?>
          </div>
                <?php if ($note): ?>
          <div class="small text-muted fst-italic mt-1">"<?= htmlspecialchars($note) ?>"</div>
          <?php endif; ?>
        </div>
                <?php if ($level): ?>
        <span class="badge bg-<?= $bg ?> align-self-center">
                    <?= $ico ?> <?= xlt($levels[$level]['label'] ?? $level) ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endforeach; ?>

  <div class="text-center text-muted small mt-3">
      <?= xlt('Showing last 30 days') ?>
    · <a href="activity.php?facility_id=<?= $facilityId ?>"><?= xlt('Facility log') ?></a>
  </div>
  <?php endif; ?>

</div>
<?php endif; ?>

</div><!-- /container -->

<script>
// Type picker highlight
document.querySelectorAll('.type-opt').forEach(function(el) {
    el.addEventListener('click', function() {
        document.querySelectorAll('.type-opt').forEach(l => l.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input').checked = true;
    });
});
</script>
</body>
</html>







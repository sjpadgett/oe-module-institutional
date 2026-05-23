<?php

/**
 * public/hbc/schedule.php
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
 * public/hbc/schedule.php — Schedule / Cancel a Home-Based Care Visit
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Controller\HbcVisitController;

if (!$manifest->featureEnabled('hbc_schedule')) {
    oei_exit_with_alert(xlt('Visit scheduling is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid'] ?? $_POST['pid'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new HbcVisitController();
$data       = $controller->handleSchedule($episodeId, $facilityId, $userId);
$result     = $data['result'];
$recommendation = $data['recommendation'] ?? null;
$dayLoad = $data['day_load'] ?? [];
$planningDatetime = (string)($data['planning_datetime'] ?? date('Y-m-d H:i:s', strtotime('+1 day 09:00')));

if ($result['submitted'] && $result['success'] && !in_array(($_POST['action'] ?? ''), ['cancel_visit', 'edit_visit'], true)) {
    $batchCount = (int) ($result['batch_count'] ?? 0);
    $flashParam = $batchCount > 1 ? 'visits_scheduled&count=' . $batchCount : 'visit_scheduled';
    header('Location: ' . $_hbcBase . 'profile.php?episode_id=' . $episodeId
        . '&pid=' . $pid . '&facility_id=' . $facilityId . '&flash=' . $flashParam);
    exit;
}

$_csrf      = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Schedule Visit');
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$q          = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container py-4" style="max-width:760px;">

  <div class="mb-3 d-flex gap-2 flex-wrap">
    <a href="<?= htmlspecialchars($_hbcBase . 'profile.php' . $q) ?>"
       class="btn btn-sm btn-outline-secondary">← <?= xlt('Profile') ?></a>
    <a href="<?= htmlspecialchars($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>"
       class="btn btn-sm btn-outline-secondary"><?= xlt('Visit Board') ?></a>
  </div>

  <h4 class="mb-3">📅 <?= xlt('Schedule Visit') ?></h4>

  <?php if ($result['submitted'] && !$result['success']): ?>
  <div class="alert alert-danger py-2"><?= htmlspecialchars($result['error']) ?></div>
  <?php endif; ?>
  <?php if ($result['submitted'] && $result['success'] && ($_POST['action'] ?? '') === 'cancel_visit'): ?>
  <div class="alert alert-info py-2">✓ <?= xlt('Visit canceled.') ?></div>
  <?php endif; ?>
  <?php if ($result['submitted'] && $result['success'] && ($_POST['action'] ?? '') === 'edit_visit'): ?>
  <div class="alert alert-success py-2">✓ <?= xlt('Visit updated.') ?></div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-header fw-semibold small">🧭 <?= xlt('Next Visit Recommendation') ?></div>
        <div class="card-body small">
          <?php if ($recommendation): ?>
            <div class="mb-1">
              <span class="text-muted"><?= xlt('Recommended due') ?>:</span>
              <strong><?= htmlspecialchars((string)($recommendation['next_visit_due_date'] ?? '')) ?: xlt('None on file') ?></strong>
              <?php if (!empty($recommendation['next_visit_type'])): ?>
                · <span class="badge <?= HbcVisitType::badge((string)$recommendation['next_visit_type']) ?>"><?= htmlspecialchars(HbcVisitType::short((string)$recommendation['next_visit_type'])) ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($recommendation['followup_plan'])): ?>
              <div class="text-muted"><?= htmlspecialchars(mb_strimwidth((string)$recommendation['followup_plan'], 0, 140, '…')) ?></div>
            <?php endif; ?>
            <?php if (!empty($recommendation['care_coordination_summary'])): ?>
              <div class="mt-2 text-muted"><?= xlt('Coordination') ?>: <?= htmlspecialchars(mb_strimwidth((string)$recommendation['care_coordination_summary'], 0, 140, '…')) ?></div>
            <?php endif; ?>
          <?php else: ?>
            <div class="text-muted"><?= xlt('No prior completed visit recommendation is available yet.') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card h-100">
        <div class="card-header fw-semibold small">👥 <?= xlt('Clinician Day Load') ?></div>
        <div class="card-body small">
          <?php if (!$dayLoad): ?>
            <div class="text-muted"><?= xlt('No visits on the planning date yet.') ?></div>
          <?php else: ?>
            <?php foreach ($dayLoad as $load): ?>
              <div class="d-flex justify-content-between border-bottom py-1">
                <span><?= htmlspecialchars((string)$load['clinician_name']) ?></span>
                <span class="text-muted"><?= (int)$load['visit_count'] ?> <?= xlt('visits') ?><?php if ((int)$load['active_count'] > 0): ?> · <?= (int)$load['active_count'] ?> <?= xlt('active') ?><?php endif; ?></span>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="card mb-4">
    <div class="card-header fw-semibold small">+ <?= xlt('New Visit') ?></div>
    <div class="card-body">
      <form method="POST" action="<?= htmlspecialchars($_hbcBase . 'schedule.php' . $q) ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
        <input type="hidden" name="pid" value="<?= $pid ?>">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Visit Type') ?> *</label>
            <select name="visit_type" class="form-select form-select-sm" required>
              <?php $recommendedType = (string)($recommendation['next_visit_type'] ?? ''); ?>
              <?php foreach (HbcVisitType::all() as $vt): ?>
              <option value="<?= $vt ?>" <?= $recommendedType === $vt ? 'selected' : '' ?>><?= htmlspecialchars(HbcVisitType::label($vt)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Anchor Date & Time') ?> *</label>
            <input type="datetime-local" name="scheduled_datetime" class="form-control form-control-sm"
                   value="<?= htmlspecialchars(date('Y-m-d\TH:i', strtotime($planningDatetime) ?: time())) ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Assigned Clinician') ?></label>
            <select name="clinician_user_id" class="form-select form-select-sm">
              <option value="0"><?= xlt('— Unassigned —') ?></option>
              <?php foreach ($data['clinicians'] as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Arrival Window Start') ?></label>
            <input type="datetime-local" name="window_start_datetime" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Arrival Window End') ?></label>
            <input type="datetime-local" name="window_end_datetime" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Route Sequence') ?></label>
            <input type="number" name="route_sequence" class="form-control form-control-sm"
                   min="1" max="99" placeholder="<?= xla('Optional') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Travel / Access Notes') ?></label>
            <textarea name="travel_notes" rows="2" class="form-control form-control-sm"
                      placeholder="<?= xla('Parking, gate, call-on-arrival, pet, building access, best entrance…') ?>"></textarea>
          </div>
        </div>
        <div class="col-12">
            <div class="form-check mt-2">
              <input type="checkbox" class="form-check-input" name="is_supervisory" id="isSupervisory" value="1">
              <label class="form-check-label fw-semibold small" for="isSupervisory">
                <?= xlt('Supervisory visit') ?>
                <span class="text-muted fw-normal">(<?= xlt('RN oversight of HHA — regulatory requirement every 14 days') ?>)</span>
              </label>
            </div>
          </div>

        <!-- ── Repeat / Batch Scheduling ────────────────────────────────── -->
        <div class="card mt-3 border-info">
          <div class="card-header py-2 d-flex align-items-center gap-2"
               style="cursor:pointer; background:#e3f2fd;"
               data-bs-toggle="collapse" data-bs-target="#repeatCollapse">
            <input type="checkbox" class="form-check-input" name="repeat_enabled" id="repeatEnabled" value="1"
                   onclick="event.stopPropagation();">
            <label class="form-check-label fw-semibold small mb-0" for="repeatEnabled" onclick="event.stopPropagation();">
              🔁 <?= xlt('Repeat schedule (create multiple visits)') ?>
            </label>
          </div>
          <div class="collapse" id="repeatCollapse">
            <div class="card-body">
              <div class="row g-3">
                <div class="col-md-4">
                  <label class="form-label small fw-semibold"><?= xlt('Number of weeks') ?></label>
                  <select name="repeat_weeks" class="form-select form-select-sm">
                    <?php for ($w = 1; $w <= 8; $w++): ?>
                    <option value="<?= $w ?>" <?= $w === 4 ? 'selected' : '' ?>><?= $w ?> <?= xlt($w === 1 ? 'week' : 'weeks') ?></option>
                    <?php endfor; ?>
                  </select>
                </div>
                <div class="col-md-8">
                  <label class="form-label small fw-semibold"><?= xlt('Days of week') ?></label>
                  <div class="d-flex gap-2 flex-wrap">
                    <?php foreach (['Mon'=>1,'Tue'=>2,'Wed'=>3,'Thu'=>4,'Fri'=>5,'Sat'=>6,'Sun'=>0] as $dayLabel => $dayNum): ?>
                    <div class="form-check form-check-inline">
                      <input type="checkbox" class="form-check-input" name="repeat_days[]" id="day<?= $dayNum ?>" value="<?= $dayNum ?>"
                             <?= in_array($dayNum, [1,3,5]) ? 'checked' : '' ?>>
                      <label class="form-check-label small" for="day<?= $dayNum ?>"><?= xlt($dayLabel) ?></label>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="text-muted small mt-2">
                <?= xlt('Visits will be created at the same time each selected day, starting from the anchor date above.') ?>
                <?= xlt('Clinician, visit type, window, route, and travel notes apply to all generated visits.') ?>
              </div>
            </div>
          </div>
        </div>

        <button type="submit" class="btn btn-success btn-sm mt-3" id="scheduleSubmitBtn">📅 <?= xlt('Schedule Visit') ?></button>
      </form>
    </div>
  </div>

  <?php if ($data['visits']): ?>
  <div class="card">
    <div class="card-header fw-semibold small">📋 <?= xlt('Scheduled & Recent Visits') ?></div>
    <div class="table-responsive">
      <table class="table table-sm mb-0" style="font-size:.82rem;">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Date') ?></th>
            <th><?= xlt('Type') ?></th>
            <th><?= xlt('Status') ?></th>
            <th><?= xlt('Route') ?></th>
            <th><?= xlt('Clinician') ?></th>
            <th><?= xlt('Duration') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['visits'] as $v): $isFinal = HbcVisitStatus::isFinal($v['status']); ?>
        <tr>
          <td class="text-nowrap">
            <?= htmlspecialchars(substr($v['scheduled'], 0, 16)) ?>
            <?php if (!empty($v['window_start_datetime']) || !empty($v['window_end_datetime'])): ?>
              <div class="text-muted small">
                <?= xlt('Window') ?>:
                <?= htmlspecialchars(substr((string)($v['window_start_datetime'] ?? ''), 11, 5) ?: '—') ?>
                –
                <?= htmlspecialchars(substr((string)($v['window_end_datetime'] ?? ''), 11, 5) ?: '—') ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <span class="badge <?= HbcVisitType::badge($v['visit_type']) ?>">
              <?= htmlspecialchars(HbcVisitType::short($v['visit_type'])) ?>
            </span>
          </td>
          <td>
            <span class="badge <?= HbcVisitStatus::badge($v['status']) ?>">
              <?= htmlspecialchars(HbcVisitStatus::label($v['status'])) ?>
            </span>
            <?php if ($v['is_draft']): ?>
              <span class="badge bg-warning text-dark ms-1" style="font-size:.65rem;">DRAFT</span>
            <?php endif; ?>
            <?php if (!empty($v['is_supervisory'])): ?>
              <span class="badge bg-info text-dark ms-1" style="font-size:.65rem;">👁 SUP</span>
            <?php endif; ?>
          </td>
          <td>
            <?= $v['route_sequence'] ? '#' . (int)$v['route_sequence'] : '—' ?>
            <?php if (!empty($v['travel_notes'])): ?>
              <div class="text-muted small"><?= htmlspecialchars(mb_strimwidth((string)$v['travel_notes'], 0, 36, '…')) ?></div>
            <?php endif; ?>
          </td>
          <td class="text-muted"><?= htmlspecialchars($v['clinician']) ?></td>
          <td class="text-muted small">
            <?php
              if ($v['status'] === 'COMPLETE' && !empty($v['actual_start']) && !empty($v['actual_end'])) {
                  $durMin = (int)((strtotime($v['actual_end']) - strtotime($v['actual_start'])) / 60);
                  echo $durMin >= 60 ? floor($durMin / 60) . 'h ' . ($durMin % 60) . 'm' : ($durMin > 0 ? $durMin . 'm' : "â");
              } else {
                  echo "â";
              }
            ?>
          </td>
          <td class="text-nowrap">
            <?php if ($manifest->featureEnabled('hbc_visit') && !$isFinal): ?>
            <a href="<?= htmlspecialchars($_hbcBase . 'visit.php?visit_id=' . (int)$v['visit_id'] . '&episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId) ?>"
               class="btn btn-xs btn-outline-primary" style="font-size:.72rem;padding:.15rem .4rem;">
              <?= xlt('Open') ?>
            </a>
            <?php endif; ?>
            <?php if (!$isFinal): ?>
            <button type="button" class="btn btn-xs btn-outline-secondary"
                    style="font-size:.72rem;padding:.15rem .4rem;"
                    onclick="openEditVisit(<?= (int)$v['visit_id'] ?>, '<?= htmlspecialchars($v['visit_type']) ?>', '<?= htmlspecialchars(substr($v['scheduled'],0,16)) ?>', '<?= htmlspecialchars(substr((string)($v['window_start_datetime']??''),0,16)) ?>', '<?= htmlspecialchars(substr((string)($v['window_end_datetime']??''),0,16)) ?>', <?= $v['route_sequence'] !== null ? (int)$v['route_sequence'] : 0 ?>, '<?= htmlspecialchars(addslashes((string)($v['travel_notes']??''))) ?>', <?= !empty($v['is_supervisory']) ? 1 : 0 ?>)">✏️</button>
            <form method="POST" action="<?= htmlspecialchars($_hbcBase . 'schedule.php' . $q) ?>" class="d-inline">
              <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_csrf) ?>">
              <input type="hidden" name="action" value="cancel_visit">
              <input type="hidden" name="visit_id" value="<?= $v['visit_id'] ?>">
              <button type="submit" class="btn btn-xs btn-outline-danger"
                      style="font-size:.72rem;padding:.15rem .4rem;"
                      onclick="return confirm('<?= xlt('Cancel this visit?') ?>')">✕</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

<!-- Edit Visit Modal -->
<div class="modal fade" id="editVisitModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-secondary text-white">
        <h5 class="modal-title">✏️ <?= xlt('Edit Visit') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" action="<?= htmlspecialchars($_hbcBase . 'schedule.php' . $q) ?>">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_csrf) ?>">
        <input type="hidden" name="action" value="edit_visit">
        <input type="hidden" name="visit_id" id="editVisitId" value="">
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Visit Type') ?></label>
            <select name="edit_visit_type" id="editVisitType" class="form-select form-select-sm">
              <?php foreach (HbcVisitType::all() as $vt): ?>
              <option value="<?= $vt ?>"><?= htmlspecialchars(HbcVisitType::label($vt)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Date & Time') ?></label>
            <input type="datetime-local" name="edit_scheduled_datetime" id="editScheduledDt" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Assigned Clinician') ?></label>
            <select name="edit_clinician_user_id" id="editClinician" class="form-select form-select-sm">
              <option value="0"><?= xlt('— Unassigned —') ?></option>
              <?php foreach ($data['clinicians'] as $c): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Route Sequence') ?></label>
            <input type="number" name="edit_route_sequence" id="editRoute" class="form-control form-control-sm" min="0" max="99">
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Window Start') ?></label>
            <input type="datetime-local" name="edit_window_start" id="editWinStart" class="form-control form-control-sm">
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Window End') ?></label>
            <input type="datetime-local" name="edit_window_end" id="editWinEnd" class="form-control form-control-sm">
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Travel / Access Notes') ?></label>
            <textarea name="edit_travel_notes" id="editTravel" rows="2" class="form-control form-control-sm"></textarea>
          </div>
          <div class="col-12">
            <div class="form-check">
              <input type="checkbox" class="form-check-input" name="edit_is_supervisory" id="editSupervisory" value="1">
              <label class="form-check-label small" for="editSupervisory"><?= xlt('Supervisory visit') ?></label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success btn-sm"><?= xlt('Save Changes') ?></button>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?= institutional_bootstrap5_js_tag() ?>
<script>
(function() {
  var cb = document.getElementById('repeatEnabled');
  var btn = document.getElementById('scheduleSubmitBtn');
  var col = document.getElementById('repeatCollapse');
  if (cb && btn) {
    cb.addEventListener('change', function() {
      btn.textContent = cb.checked
        ? '📅 ' + <?= json_encode(xlt('Schedule Recurring Visits')) ?>
        : '📅 ' + <?= json_encode(xlt('Schedule Visit')) ?>;
      if (cb.checked && col && !col.classList.contains('show')) {
        new bootstrap.Collapse(col, {toggle:true});
      }
    });
  }
})();
</script>
<script>
function openEditVisit(visitId, type, dt, winStart, winEnd, route, travel, supervisory) {
    document.getElementById('editVisitId').value = visitId;
    document.getElementById('editVisitType').value = type;
    document.getElementById('editScheduledDt').value = dt ? dt.replace(' ', 'T') : '';
    document.getElementById('editWinStart').value = winStart ? winStart.replace(' ', 'T') : '';
    document.getElementById('editWinEnd').value = winEnd ? winEnd.replace(' ', 'T') : '';
    document.getElementById('editRoute').value = route || '';
    document.getElementById('editTravel').value = travel || '';
    document.getElementById('editSupervisory').checked = !!supervisory;
    new bootstrap.Modal(document.getElementById('editVisitModal')).show();
}
</script>
</div>
</body>
</html>


















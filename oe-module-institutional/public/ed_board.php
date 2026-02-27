<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
// Fallback include in case composer autoload is stale (eg. Windows installs)
if (!class_exists('OpenEMR\\Modules\\Institutional\\Submodule\\Disposition\\Repository\\EpisodeEventRepository')) {
    $fallback = dirname(__DIR__) . '/src/Submodule/Disposition/Repository/EpisodeEventRepository.php';
    if (is_file($fallback)) {
        require_once $fallback;
    }
}

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Core\Service\AuditService;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Repository\LocationHistoryRepository;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Service\AdtService;
use OpenEMR\Modules\Institutional\Submodule\Assignment\Controller\AssignmentController;
use OpenEMR\Modules\Institutional\Submodule\Assignment\Repository\AssignmentRepository;
use OpenEMR\Modules\Institutional\Submodule\EdtBoard\Controller\EdBoardController;
use OpenEMR\Modules\Institutional\Submodule\ObsStay\Service\ObsService;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Service\TaskService;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Service\ObsProtocolEngine;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Service\VitalsSchedulerService;

if (!$manifest->featureEnabled('edt_board')) {
    die(xlt("Institutional ED Board is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$protocolOptions = [];
$csrfToken = \OpenEMR\Common\Csrf\CsrfUtils::collectCsrfToken();
if ($manifest->featureEnabled('obs_protocols')) {
    try {
        $protoRepoForUi = new ProtocolRepository();
        $protoRepoForUi->ensureDefaultProtocols($facilityId, $userId);
        $protocolOptions = $protoRepoForUi->listEnabled($facilityId);
    } catch (\Throwable $e) {
        $protocolOptions = [];
    }
}

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$episodeRepo = new EpisodeRepository();
$locationRepo = new LocationRepository();
$historyRepo = new LocationHistoryRepository();

// ── Vitals scheduler (wired into both AdtService and ObsService) ──────────────
$vitalsScheduler = null;
if ($manifest->featureEnabled('tasks') && $manifest->featureEnabled('triage')) {
    $vitalsScheduler = new VitalsSchedulerService(new TaskRepository(), new SettingsRepository());
}

$taskService = null;
$taskRepo    = null;
if ($manifest->featureEnabled('tasks')) {
    $taskRepo    = new TaskRepository();
    $taskService = new TaskService($taskRepo);
}

$protocolEngine = null;
if ($manifest->featureEnabled('obs_protocols')) {
    $protoRepo = new ProtocolRepository();
    $planRepo  = new ObsPlanRepository();
    $protoRepo->ensureDefaultProtocols($facilityId, $userId);
    $protocolEngine = new ObsProtocolEngine($protoRepo, $planRepo, $taskRepo);
}

// AdtService receives vitalsScheduler — schedules VITALS_CHECK tasks on room assignment
$adtService = new AdtService($episodeRepo, $historyRepo, $locationRepo, null, $vitalsScheduler);

// ObsService receives vitalsScheduler — schedules vitals on obs start (skipped if protocol owns them)
$obsService = new ObsService($episodeRepo, $taskService, $protocolEngine, null, $vitalsScheduler);

$eventRepo  = new EpisodeEventRepository();
$controller = new EdBoardController($episodeRepo, $locationRepo, $adtService, $obsService);
$data       = $controller->handle($facilityId, $userId);

// ── Assignment data for board column (if enabled) ─────────────────────────────
$assignmentsByEpisode = [];
if ($manifest->featureEnabled('assignment')) {
    // AssignmentController::handle JSON POST endpoint (inline quick-assign from board)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['json']) && isset($_POST['nurse_user_id'], $_POST['episode_id'])) {
        $assignCtrl = new AssignmentController(new AssignmentRepository(), new AuditService());
        $assignCtrl->handle($facilityId, $userId); // exits via JSON response
    }
    // Load current assignments for all active episodes in one query
    $assignRepo = new AssignmentRepository();
    foreach ($assignRepo->listWithAssignments($facilityId) as $aRow) {
        $assignmentsByEpisode[(int)$aRow['id']] = $aRow;
    }
    $availableStaff = $assignRepo->availableStaff();
}

// Institutional: capture controller errors (avoid silent failures)
if (is_string($data) && $data !== '') {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data['error']));
    }
    if (!empty($data['errors']) && is_array($data['errors'])) {
        foreach ($data['errors'] as $err) {
            if (is_string($err) && $err !== '') {
                \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($err));
            }
        }
    }
}
$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>ED Tracking Board</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<body class="bg-light">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("ED Tracking Board") ?></h1>
    <div class="d-flex gap-2 align-items-center">
      <?php if ($manifest->featureEnabled('timeline')): ?>
        <a class="btn btn-sm btn-outline-secondary" href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Timeline") ?></a>
      <?php endif; ?>
      <?php if ($manifest->featureEnabled('assignment')): ?>
        <a class="btn btn-sm btn-outline-secondary" href="assignments.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Assignments") ?></a>
      <?php endif; ?>
      <span class="text-muted small"><?= xlt("Facility ID") ?>: <?= htmlspecialchars((string)$facilityId) ?></span>
    </div>
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-header"><?= xlt("Quick Arrival") ?></div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="action" value="arrival">

        <div class="col-12 col-md-2">
          <label class="form-label"><?= xlt("PID") ?></label>
          <input name="pid" class="form-control" inputmode="numeric" required>
        </div>

        <div class="col-12 col-md-6">
          <label class="form-label"><?= xlt("Chief complaint") ?></label>
          <input name="chief_complaint" class="form-control" placeholder="<?= xla("e.g., chest pain, SOB, fall") ?>">
        </div>

        <div class="col-12 col-md-2">
          <label class="form-label"><?= xlt("ESI") ?></label>
          <select name="acuity_esi" class="form-select">
            <option value=""><?= xlt("—") ?></option>
            <option>1</option><option>2</option><option>3</option><option>4</option><option>5</option>
          </select>
        </div>

        <div class="col-12 col-md-2 d-flex align-items-end">
          <button class="btn btn-primary w-100"><?= xlt("Add to Board") ?></button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt("Active Episodes") ?></span>
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Refresh") ?></a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt("Episode") ?></th>
            <th><?= xlt("PID") ?></th>
            <th><?= xlt("Type") ?></th>
            <th><?= xlt("Obs Prot") ?></th>
            <th><?= xlt("Next Due") ?></th>
            <th><?= xlt("Dispo") ?></th>
            <th><?= xlt("Throughput") ?></th>
            <th><?= xlt("Elapsed") ?></th>
            <th><?= xlt("Chief Complaint") ?></th>
            <th><?= htmlspecialchars($triageStandard->columnLabel()) ?></th>
            <th><?= xlt("Location") ?></th>
            <th><?= xlt("Workflow") ?></th>
            <th><?= xlt("BH") ?></th>
            <?php if ($manifest->featureEnabled('assignment')): ?>
            <th><?= xlt("Staff") ?></th>
            <?php endif; ?>
            <th><?= xlt("Actions") ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($data['rows'] as $r):
            $eId = (int)$r['id'];
            $assign = $assignmentsByEpisode[$eId] ?? [];
            ?>
          <tr>
            <td>
              <?= htmlspecialchars((string)$r['id']) ?>
              <?php if ($manifest->featureEnabled('timeline')): ?>
                <a class="ms-1 text-muted small" href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$eId) ?>" title="<?= xla('Timeline') ?>">&#x1F4CB;</a>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)$r['type']) ?></span></td>
            <td><?php if (!empty($r['disposition'])): ?><span class="badge text-bg-info"><?= htmlspecialchars((string)$r['disposition']) ?></span><?php endif; ?></td>
            <td class="text-nowrap">
              <form method="post" class="d-inline" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrfToken) ?>">
                <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
                <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
                <button class="btn btn-sm btn-outline-secondary" name="action" value="stamp_room"><?= xlt("Room") ?></button>
                <button class="btn btn-sm btn-outline-secondary" name="action" value="stamp_provider"><?= xlt("Provider") ?></button>
              </form>
              <a class="btn btn-sm btn-outline-primary" href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$r['id']) ?>"><?= xlt("Disposition") ?></a>
            </td>
            <td><?php if (!empty($r['obs_protocol_key'])): ?>
              <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-info"><?= htmlspecialchars((string)$r['obs_protocol_key']) ?></span>
                <?php if ($manifest->featureEnabled('obs_protocols') && !empty($protocolOptions)): ?>
                  <form method="post" action="obs_apply_protocol.php" class="m-0">
                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrfToken) ?>">
                    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                    <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                    <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
                    <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
                    <input type="hidden" name="redirect" value="ed_board">
                    <input type="hidden" name="extend_hours" value="6">
                    <select name="protocol_key" class="form-select form-select-sm d-inline-block" style="width: 170px;">
                      <?php foreach ($protocolOptions as $popt): ?>
                            <?php $k = (string)($popt['protocol_key'] ?? ''); ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= ($k === (string)$r['obs_protocol_key']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($popt['label'] ?? $k)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-outline-primary"><?= xlt("Apply") ?></button>
                    <button formaction="obs_extend_runway.php" class="btn btn-sm btn-outline-secondary" name="extend" value="1"><?= xlt("Extend") ?></button>
                  </form>
                <?php endif; ?>
              </div>
            <?php endif; ?></td>
            <td><?php if (!empty($r['next_task_due'])): ?>
                <?php $isOverdue = (strtotime((string)$r['next_task_due']) ?: 0) < time(); ?>
              <span class="<?= $isOverdue ? 'text-danger' : 'text-muted' ?> small"><?= htmlspecialchars((string)$r['next_task_due']) ?></span>
              <span class="badge <?= $isOverdue ? 'text-bg-danger' : 'text-bg-light border' ?>"><?= htmlspecialchars((string)$r['next_task_type']) ?></span>
            <?php endif; ?></td>
            <td><?= htmlspecialchars(institutional_human_elapsed((string)$r['start_datetime'])) ?></td>
            <td><?= htmlspecialchars((string)($r['chief_complaint'] ?? '')) ?></td>
            <td><?php $__esi = (int)($r['acuity_esi'] ?? 0); ?>
              <?php if ($__esi): ?>
              <span class="badge <?= htmlspecialchars($triageStandard->badgeClass($__esi)) ?>">
                    <?= htmlspecialchars($triageStandard->shortLabel($__esi)) ?>
              </span>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)($r['location_name'] ?? '')) ?></td>
            <td><span class="badge text-bg-light border"><?= htmlspecialchars((string)($r['workflow_status'] ?? '')) ?></span></td>
            <td><?php if (!empty($r['bh_observation_level']) && (string)$r['bh_observation_level'] !== 'NONE'): ?><span class="badge text-bg-warning"><?= htmlspecialchars((string)$r['bh_observation_level']) ?></span><?php endif; ?></td>

            <?php if ($manifest->featureEnabled('assignment')): ?>
            <td style="min-width:160px;">
                <?php if (!empty($assign['nurse_id'])): ?>
                <div class="small"><span class="badge text-bg-primary"><i class="bi bi-person-fill"></i> <?= htmlspecialchars(trim((string)$assign['nurse_name'])) ?></span></div>
              <?php else: ?>
                <div class="small text-muted fst-italic"><?= xlt('No nurse') ?></div>
              <?php endif; ?>
                <?php if (!empty($assign['provider_id'])): ?>
                <div class="small mt-1"><span class="badge text-bg-success"><i class="bi bi-person-badge-fill"></i> <?= htmlspecialchars(trim((string)$assign['provider_name'])) ?></span></div>
              <?php else: ?>
                <div class="small text-muted fst-italic"><?= xlt('No provider') ?></div>
              <?php endif; ?>
              <!-- Quick-assign modal trigger -->
              <button class="btn btn-link btn-sm p-0 mt-1 text-muted"
                      data-bs-toggle="modal"
                      data-bs-target="#quickAssignModal"
                      data-episode-id="<?= htmlspecialchars((string)$eId) ?>"
                      data-pid="<?= htmlspecialchars((string)$r['pid']) ?>"
                      data-nurse-id="<?= htmlspecialchars((string)($assign['nurse_id'] ?? '')) ?>"
                      data-provider-id="<?= htmlspecialchars((string)($assign['provider_id'] ?? '')) ?>">
                <small><?= xlt('Assign') ?></small>
              </button>
            </td>
            <?php endif; ?>

            <td style="min-width: 560px;">
              <div class="d-flex flex-wrap gap-2">
                <?php if ($manifest->featureEnabled('adt_lite')): ?>
                <form method="post" class="d-flex gap-2">
                  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                  <input type="hidden" name="action" value="assign_location">
                  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                  <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
                  <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
                  <select name="location_id" class="form-select form-select-sm">
                    <option value=""><?= xlt("Waiting") ?></option>
                    <?php foreach ($data['locations'] as $loc): ?>
                      <option value="<?= htmlspecialchars((string)$loc['id']) ?>"><?= htmlspecialchars((string)$loc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-primary"><?= xlt("Set Room") ?></button>
                </form>
                <?php endif; ?>

            <?php if ($manifest->featureEnabled('bh_safety') && !$manifest->featureEnabled('bh_boarding')): ?>
<form method="post" action="bh_safety_set.php" class="d-flex gap-2">
  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
  <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
  <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
  <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
  <select name="observation_level" class="form-select form-select-sm">
    <option value="NONE"><?= xlt("Safety: None") ?></option>
    <option value="Q60">Q60</option>
    <option value="Q30">Q30</option>
    <option value="Q15">Q15</option>
    <option value="ONE_TO_ONE"><?= xlt("1:1") ?></option>
  </select>
  <button class="btn btn-sm btn-outline-warning"><?= xlt("BH Safety") ?></button>
</form>
            <?php endif; ?>

                <form method="post" class="d-flex gap-2">
                  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                  <input type="hidden" name="action" value="set_status">
                  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                  <select name="status_code" class="form-select form-select-sm">
                    <?php foreach ($data['allowed_statuses'] as $s): ?>
                      <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-outline-secondary"><?= xlt("Set Status") ?></button>
                </form>

                <?php if ($manifest->featureEnabled('obs_stay') && (string)$r['type'] !== 'OBS'): ?>
                <form method="post">
                  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                  <input type="hidden" name="action" value="start_obs">
                  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                  <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
                  <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">

                    <?php if ($manifest->featureEnabled('obs_start_picker') && !empty($protocolOptions)): ?>
              <select name="protocol_key" class="form-select form-select-sm d-inline-block" style="width: 170px;">
                        <?php foreach ($protocolOptions as $popt): ?>
                            <?php $k = (string)($popt['protocol_key'] ?? ''); ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= ($k === 'GENERAL_OBS') ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)($popt['label'] ?? $k)) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            <?php else: ?>
              <input type="hidden" name="protocol_key" value="GENERAL_OBS">
            <?php endif; ?>

                  <button class="btn btn-sm btn-outline-success"><?= xlt("Start Obs") ?></button>
                </form>
                <?php endif; ?>

            <?php if ($manifest->featureEnabled('bh_safety') && !$manifest->featureEnabled('bh_boarding')): ?>
<form method="post" action="bh_safety_set.php" class="d-flex gap-2">
  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
  <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
  <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
  <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
  <select name="observation_level" class="form-select form-select-sm">
    <option value="NONE"><?= xlt("Safety: None") ?></option>
    <option value="Q60">Q60</option>
    <option value="Q30">Q30</option>
    <option value="Q15">Q15</option>
    <option value="ONE_TO_ONE"><?= xlt("1:1") ?></option>
  </select>
  <button class="btn btn-sm btn-outline-warning"><?= xlt("BH Safety") ?></button>
</form>
            <?php endif; ?>

                <form method="post" class="d-flex gap-2">
                  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                  <input type="hidden" name="action" value="set_disposition">
                  <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                  <select name="disposition" class="form-select form-select-sm">
                    <option value=""><?= xlt("Disposition…") ?></option>
                    <?php foreach ($data['allowed_dispos'] as $d): ?>
                      <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm btn-danger" onclick="return confirm('Close this episode?');"><?= xlt("Close") ?></button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$data['rows']): ?>
          <tr><td colspan="<?= $manifest->featureEnabled('assignment') ? '15' : '14' ?>" class="text-center text-muted py-4"><?= xlt("No active episodes") ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($manifest->featureEnabled('assignment') && !empty($availableStaff)): ?>
<!-- Quick-assign modal (inline from board) -->
<div class="modal fade" id="quickAssignModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="post">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
        <input type="hidden" name="episode_id" id="qa-episode-id" value="">
        <input type="hidden" name="pid"         id="qa-pid"        value="">
        <div class="modal-header py-2">
          <h6 class="modal-title mb-0"><?= xlt('Assign Staff') ?></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body pb-2">
          <label class="form-label small fw-semibold mb-1"><?= xlt('Nurse') ?></label>
          <select name="nurse_user_id" id="qa-nurse" class="form-select form-select-sm mb-2">
            <option value=""><?= xlt('— Unassigned —') ?></option>
            <?php foreach ($availableStaff['nurses'] as $u): ?>
              <option value="<?= htmlspecialchars((string)$u['id']) ?>"><?= htmlspecialchars((string)$u['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <label class="form-label small fw-semibold mb-1"><?= xlt('Provider') ?></label>
          <select name="provider_user_id" id="qa-provider" class="form-select form-select-sm">
            <option value=""><?= xlt('— Unassigned —') ?></option>
            <?php foreach ($availableStaff['providers'] as $u): ?>
              <option value="<?= htmlspecialchars((string)$u['id']) ?>"><?= htmlspecialchars((string)$u['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
          <button type="submit" class="btn btn-primary btn-sm"><?= xlt('Save') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
(function () {
  const m = document.getElementById('quickAssignModal');
  if (!m) return;
  m.addEventListener('show.bs.modal', function (ev) {
    const b = ev.relatedTarget;
    if (!b) return;
    document.getElementById('qa-episode-id').value = b.dataset.episodeId || '';
    document.getElementById('qa-pid').value        = b.dataset.pid        || '';
    document.getElementById('qa-nurse').value      = b.dataset.nurseId    || '';
    document.getElementById('qa-provider').value   = b.dataset.providerId || '';
  });
})();
</script>
<?php endif; ?>

</body>
</html>



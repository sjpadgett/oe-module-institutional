<?php

/**
 * public/ed_board.php
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

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
// Fallback include in case composer autoload is stale (eg. Windows installs)
if (!class_exists('OpenEMR\\Modules\\Institutional\\Submodule\\Disposition\\Repository\\EpisodeEventRepository')) {
    $fallback = dirname(__DIR__) . '/src/Submodule/Disposition/Repository/EpisodeEventRepository.php';
    if (is_file($fallback)) {
        require_once $fallback;
    }
}

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Core\Service\AuditService;
use OpenEMR\Modules\Institutional\Core\Ui\Flash;
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\LocationRepository as BedMgmtLocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Repository\LocationHistoryRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Service\AdtService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Assignment\Controller\AssignmentController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Assignment\Repository\AssignmentRepository;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\EdBoard\Controller\EdBoardController;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsCore\Service\ObsService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Service\TaskService;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Service\ObsProtocolEngine;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Service\VitalsSchedulerService;

if (!$manifest->featureEnabled('edt_board')) {
    die(xlt("Institutional ED Board is disabled by manifest"));
}

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityId = (int)($_oei_facilityId ?? ($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1)));

$protocolOptions = [];
$csrfToken = CsrfUtils::collectCsrfToken();
if ($manifest->featureEnabled('obs_protocols')) {
    try {
        $protoRepoForUi = new ProtocolRepository();
        $protoRepoForUi->ensureDefaultProtocols($facilityId, $userId);
        $protocolOptions = $protoRepoForUi->listEnabled($facilityId);
    } catch (\Throwable $e) {
        $protocolOptions = [];
    }
}

$episodeRepo = new EpisodeRepository();
$locationRepo = new LocationRepository();
$historyRepo = new LocationHistoryRepository();

// ── Vitals scheduler (wired into both AdtService and ObsService) ──────────────
$vitalsScheduler = null;
if ($manifest->featureEnabled('tasks') && $manifest->featureEnabled('triage')) {
    $vitalsScheduler = new VitalsSchedulerService(new TaskRepository(), new SettingsRepository());
}

$taskService = null;
$taskRepo = null;
if ($manifest->featureEnabled('tasks')) {
    $taskRepo = new TaskRepository();
    $taskService = new TaskService($taskRepo);
}

$protocolEngine = null;
if ($manifest->featureEnabled('obs_protocols')) {
    $protoRepo = new ProtocolRepository();
    $planRepo = new ObsPlanRepository();
    $protoRepo->ensureDefaultProtocols($facilityId, $userId);
    $protocolEngine = new ObsProtocolEngine($protoRepo, $planRepo, $taskRepo);
}

// AdtService receives vitalsScheduler — schedules VITALS_CHECK tasks on room assignment
$adtService = new AdtService(
    $episodeRepo, $historyRepo, $locationRepo,
    null,                           // AdtNotificationService
    $vitalsScheduler,
    new EpisodeLocationRepository() // syncs board location display
);

// ObsService receives vitalsScheduler — schedules vitals on obs start (skipped if protocol owns them)
$obsService = new ObsService($episodeRepo, $taskService, $protocolEngine, null, $vitalsScheduler);

$eventRepo = new EpisodeEventRepository();

// ── Assignment POST must run BEFORE EdBoardController::handle() ──────────────
// handle() processes every POST and redirects+exits, so the assignment
// JSON endpoint must intercept its own POST before that happens.
$assignmentsByEpisode = [];
$availableStaff = [];
if ($manifest->featureEnabled('assignment')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST'
        && !empty($_POST['json'])
        && isset($_POST['nurse_user_id'], $_POST['episode_id'])) {
        if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'CSRF']);
            exit;
        }
        $assignCtrl = new AssignmentController(new AssignmentRepository(), new AuditService());
        $assignCtrl->handle($facilityId, $userId); // outputs JSON and exits
    }
}

$controller = new EdBoardController($episodeRepo, new BedMgmtLocationRepository(), $adtService, $obsService);
$data = $controller->handle($facilityId, $userId);

// ── Assignment display data for board render ──────────────────────────────────
if ($manifest->featureEnabled('assignment')) {
    $assignRepo = new AssignmentRepository();
    foreach ($assignRepo->listWithAssignments($facilityId) as $aRow) {
        $assignmentsByEpisode[(int)$aRow['id']] = $aRow;
    }
    $availableStaff = $assignRepo->availableStaff();
}

// Institutional: capture controller errors (avoid silent failures)
if (is_string($data) && $data !== '') {
    Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        Flash::addError(xlt($data['error']));
    }
    if (!empty($data['errors']) && is_array($data['errors'])) {
        foreach ($data['errors'] as $err) {
            if (is_string($err) && $err !== '') {
                Flash::addError(xlt($err));
            }
        }
    }
}
$_edPids = array_values(array_unique(array_filter(array_map(
    fn($r) => (int)($r['pid'] ?? 0), $data['rows'] ?? []))));
$_edPatientNames = oei_patient_names($_edPids);

$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ED Tracking Board</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href): ?>
        <link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>

<style>
/* ── ED Board table UX ──────────────────────────────────────────────────
   Problem: table-responsive puts scrollbar at page bottom, requiring
   users to scroll past all rows to reach it.
   Fix: constrain container to viewport, keep scrollbar always visible,
   sticky thead + first two columns for context while scrolling.
──────────────────────────────────────────────────────────────────────── */

.ed-board-wrap {
    overflow: auto;
    /* Leave room for header, card header, arrival form (~200px) */
    max-height: calc(100vh - 200px);
    border: 0;
}

/* Sticky column headers — always visible while scrolling vertically */
.ed-board-table thead th {
    position: sticky;
    top: 0;
    z-index: 3;
    background: #f8f9fa; /* Bootstrap table-light */
    box-shadow: 0 1px 0 #dee2e6;
}

/* Sticky Episode column (1st) */
.ed-board-table td:nth-child(1),
.ed-board-table th:nth-child(1) {
    position: sticky;
    left: 0;
    z-index: 2;
    background: inherit;
    min-width: 80px;
    box-shadow: 1px 0 0 #dee2e6;
}
/* thead first col needs higher z so it stays above sticky data cells */
.ed-board-table thead th:nth-child(1) {
    z-index: 4;
}

/* Sticky Patient column (2nd) */
.ed-board-table td:nth-child(2),
.ed-board-table th:nth-child(2) {
    position: sticky;
    left: 80px;  /* matches min-width of col 1 */
    z-index: 2;
    background: inherit;
    min-width: 180px;
    box-shadow: 1px 0 0 #dee2e6;
}
.ed-board-table thead th:nth-child(2) {
    z-index: 4;
}

/* Stripe rows — sticky cells inherit the stripe color correctly */
.ed-board-table tbody tr:nth-child(odd) td {
    background-color: rgba(0,0,0,.05);
}
.ed-board-table tbody tr:hover td {
    background-color: rgba(13,110,253,.08);
}

/* Actions column: stack forms vertically to reduce width */
.ed-actions-col {
    min-width: 220px !important;
}
.ed-actions-col > div {
    flex-direction: column !important;
    align-items: flex-start !important;
    gap: 4px !important;
}
.ed-actions-col form {
    width: 100%;
}
.ed-actions-col .form-select {
    width: 100% !important;
    max-width: 200px;
}
</style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
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
                <form method="post" class="row g-2" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
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
                            <option>1</option>
                            <option>2</option>
                            <option>3</option>
                            <option>4</option>
                            <option>5</option>
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
            <div class="ed-board-wrap">
                <table class="table table-sm align-middle mb-0 ed-board-table">
                    <thead class="table-light">
                    <tr>
                        <th><?= xlt("Episode") ?></th>
                        <th><?= xlt("Patient") ?></th>
                        <th><?= xlt("Type") ?></th>
                        <th><?= xlt("Dispo") ?></th>
                        <th><?= xlt("Throughput") ?></th>
                        <th><?= xlt("Obs Prot") ?></th>
                        <th><?= xlt("Next Due") ?></th>
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
                            <td><?= oei_fmt_patient((int)($r['pid'] ?? 0), $_edPatientNames) ?></td>
                            <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)$r['type']) ?></span></td>
                            <td>
                                <?php
                                $_planDisp  = strtoupper((string)($r['plan_disposition_code'] ?? ''));
                                $_planDest  = (string)($r['plan_destination'] ?? '');
                                $_refDisps  = ['DISCHARGE', 'TRANSFER', 'ADMIT'];
                                $_dispBadge = match($_planDisp) {
                                    'DISCHARGE'            => 'text-bg-success',
                                    'TRANSFER'             => 'text-bg-warning',
                                    'ADMIT'                => 'text-bg-primary',
                                    'LWBS', 'AMA', 'ELOPE' => 'text-bg-secondary',
                                    default                => 'text-bg-info',
                                };
    ?>
                                <?php if ($_planDisp !== ''): ?>
                                    <span class="badge <?= $_dispBadge ?>"><?= htmlspecialchars($_planDisp) ?></span>
                                    <?php if ($_planDest !== ''): ?>
                                        <div class="small text-muted text-truncate" style="max-width:120px;"
                                             title="<?= htmlspecialchars($_planDest) ?>">
                                            <?= htmlspecialchars($_planDest) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (in_array($_planDisp, $_refDisps, true) && $manifest->featureEnabled('ereferral')): ?>
                                        <a class="small text-success fw-semibold d-block mt-1"
                                           href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= urlencode((string)$r['id']) ?>"
                                           title="<?= xlt('Open E-Referral') ?>">&#8599; <?= xlt('Referral') ?></a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a class="btn btn-outline-secondary"
                                       style="font-size:.75rem;padding:.15rem .5rem;"
                                       href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= urlencode((string)$r['id']) ?>"
                                       title="<?= xlt('Set Disposition Plan') ?>">+ <?= xlt('Plan') ?></a>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <form method="post" class="d-inline" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
                                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrfToken) ?>">
                                    <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                                    <input type="hidden" name="pid" value="<?= htmlspecialchars((string)$r['pid']) ?>">
                                    <input type="hidden" name="eid" value="<?= htmlspecialchars((string)($r['eid'] ?? '')) ?>">
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="stamp_room"><?= xlt("Room") ?></button>
                                    <button class="btn btn-sm btn-outline-secondary" name="action" value="stamp_provider"><?= xlt("Provider") ?></button>
                                </form>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= urlencode((string)$r['id']) ?>"><?= xlt("Dispo Plan") ?></a>
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
                                    <button type="button" class="btn btn-link btn-sm p-0 mt-1 text-muted"
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

                            <td class="ed-actions-col">
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ($manifest->featureEnabled('adt_lite')): ?>
                                        <form method="post" class="d-flex gap-2" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
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

                                    <form method="post" class="d-flex gap-2" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
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
                                        <form method="post" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
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

                                    <form method="post" class="d-flex gap-2" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
                                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                                        <input type="hidden" name="action" value="set_disposition">
                                        <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)$r['id']) ?>">
                                        <select name="disposition" class="form-select form-select-sm"
                                                title="<?= xlt('Closes episode and removes from board') ?>">
                                            <option value=""><?= xlt("Close as…") ?></option>
                                            <?php foreach ($data['allowed_dispos'] as $d): ?>
                                                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-danger"
                                                title="<?= xlt('Closes episode permanently — use Dispo Plan column for planning') ?>"
                                                onclick="return confirm('<?= xlt('This closes the episode and removes it from the board. Use the Dispo Plan column to plan without closing. Continue?') ?>');"><?= xlt("Close") ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$data['rows']): ?>
                        <tr>
                            <td colspan="<?= $manifest->featureEnabled('assignment') ? '15' : '14' ?>" class="text-center text-muted py-4"><?= xlt("No active episodes") ?></td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($manifest->featureEnabled('assignment') && !empty($availableStaff)): ?>
        <!-- Quick-assign modal (inline from board) -->
        <div class="modal fade mt-5" id="quickAssignModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <form method="post" action="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
                        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                        <input type="hidden" name="json" value="1">
                        <input type="hidden" name="episode_id" id="qa-episode-id" value="">
                        <input type="hidden" name="pid" id="qa-pid" value="">
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
                    document.getElementById('qa-pid').value = b.dataset.pid || '';
                    document.getElementById('qa-nurse').value = b.dataset.nurseId || '';
                    document.getElementById('qa-provider').value = b.dataset.providerId || '';
                });
            })();
        </script>
    <?php endif; ?>

    <?php if ($href): ?>
        <?= institutional_bootstrap5_js_tag() ?>
    <?php endif; ?>
</body>
</html>






















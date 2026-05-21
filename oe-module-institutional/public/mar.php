<?php

/**
 * public/mar.php
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
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Controller\MarController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service\AllergyService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service\MarService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Core\Repository\UserRepository;

if (!$manifest->featureEnabled('mar')) {
    die(xlt('MAR is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$controller = new MarController(
    new MarOrderRepository(),
    new MarAdministrationRepository(),
    new EpisodeRepository(),
    new AllergyService(),
    class_exists(TaskRepository::class) ? new TaskRepository() : null
);

// ── PIN verification endpoint ────────────────────────────────────────────
// Runs before $controller->handle() so the main POST→redirect path never
// consumes this request. Returns JSON and exits; never reaches the view.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_pin') {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    if (!\OpenEMR\Common\Csrf\CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    $pin    = (string)($_POST['pin'] ?? '');
    $pinOk  = false;
    if ($userId !== null && $pin !== '' && function_exists('sqlQuery')) {
        $row = sqlQuery(
            "SELECT `password` FROM `users_secure` WHERE `id` = ? LIMIT 1",
            [$userId]
        );
        if (!empty($row['password'])) {
            $pinOk = password_verify($pin, $row['password']);
        }
    }
    echo json_encode(['ok' => $pinOk]);
    exit;
}

$data = $controller->handle($facilityId, $userId);

if (is_string($data) && $data !== '') {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data['error']));
    }
}

$href = institutional_bootstrap5_href($manifest);
$view = (string)($data['view'] ?? 'facility');
$episode = $data['episode'] ?? [];
$isPrint = (bool)($data['print'] ?? false);
$allergyWarnings = $data['allergy_warnings'] ?? [];
$holdReasons = $data['hold_reasons'] ?? MarService::HOLD_REASONS;
$workspace = is_array($data['workspace'] ?? null) ? $data['workspace'] : [];
$orderVocab = is_array($data['order_vocab'] ?? null) ? $data['order_vocab'] : ['units' => [], 'routes' => [], 'frequencies' => []];
$drugLookup = is_array($data['drug_lookup'] ?? null) ? $data['drug_lookup'] : [];
$rxSourceMap = is_array($data['rx_source_map'] ?? null) ? $data['rx_source_map'] : [];

$_marPid = (int)($episode['pid'] ?? 0);
$_marPatientNames = function_exists('oei_patient_names') && $_marPid > 0
    ? oei_patient_names([$_marPid]) : [];

// Batch-fetch staff list once for co-sign dropdowns (episode view only)
$_marAllStaff = ($view === 'episode' && !$isPrint)
    ? (new UserRepository())->fetchAll() : [];

function mar_badge(string $outcome): string
{
    return match ($outcome) {
        'GIVEN' => 'text-bg-success',
        'HELD' => 'text-bg-warning',
        'REFUSED' => 'text-bg-danger',
        'NOT_AVAILABLE' => 'text-bg-secondary',
        'MISSED' => 'text-bg-dark',
        default => 'text-bg-light border',
    };
}

/** Render the shared administration form fields (used for both Record and Amend). */
function mar_admin_fields(array $order, array $a, string $action, array $holdReasons): void
{
    $_marAllStaff = (new UserRepository())->fetchAll();
    ?>
    <input type="hidden" name="action" value="<?= htmlspecialchars($action) ?>">
    <input type="hidden" name="admin_id" value="<?= (int)($a['id'] ?? 0) ?>">

    <div class="row g-2 align-items-end">

        <div class="col-auto">
            <label class="form-label"><?= xlt('Outcome') ?></label>
            <select name="outcome" class="form-select form-select-sm mar-outcome-sel" style="width:140px"
                onchange="marToggleOutcomeFields(this)">
                <?php foreach (['GIVEN', 'HELD', 'REFUSED', 'NOT_AVAILABLE', 'MISSED'] as $oc): ?>
                    <option value="<?= $oc ?>" <?= ($a['outcome'] ?? 'GIVEN') === $oc ? 'selected' : '' ?>>
                        <?= htmlspecialchars($oc) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-auto mar-hold-reason-wrap" style="display:none;">
            <label class="form-label"><?= xlt('Hold Reason') ?></label>
            <select name="hold_reason" class="form-select form-select-sm" style="width:180px">
                <?php foreach ($holdReasons as $code => $label): ?>
                    <option value="<?= htmlspecialchars($code) ?>"
                        <?= ($a['hold_reason'] ?? '') === $code ? 'selected' : '' ?>>
                        <?= htmlspecialchars(xlt($label)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Given At') ?></label>
            <input type="datetime-local" name="administered_datetime" class="form-control form-control-sm"
                style="width:165px"
                value="<?= htmlspecialchars(
                    !empty($a['administered_datetime'])
                        ? date('Y-m-d\TH:i', strtotime((string)$a['administered_datetime']))
                        : date('Y-m-d\TH:i')
                       ) ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Dose') ?></label>
            <input name="dose_given" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['dose_given'] ?? $order['dose'])) ?>"
                style="width:65px" placeholder="<?= xla('Dose') ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Unit') ?></label>
            <input name="unit_given" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['unit_given'] ?? $order['unit'])) ?>"
                style="width:55px" placeholder="<?= xla('Unit') ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Route') ?></label>
            <input name="route_given" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['route_given'] ?? $order['route'])) ?>"
                style="width:55px" placeholder="<?= xla('Route') ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Site') ?></label>
            <input name="site" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['site'] ?? '')) ?>"
                style="width:70px" placeholder="<?= xla('LDA…') ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Lot #') ?></label>
            <input name="lot_number" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['lot_number'] ?? '')) ?>"
                style="width:90px" placeholder="<?= xla('Lot') ?>">
        </div>

        <div class="col-auto">
            <label class="form-label"><?= xlt('Note') ?></label>
            <input name="note" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['note'] ?? '')) ?>"
                style="width:140px">
        </div>

        <div class="col-auto d-flex align-items-end">
            <button class="btn btn-success btn-sm"><?= xlt('Save') ?></button>
        </div>

    </div>

    <div class="mar-exception-followup-wrap mt-2 p-2 border rounded bg-light" style="display:none;">
        <div class="small fw-semibold mb-1"><?= xlt('Exception follow-up') ?></div>
        <div class="small text-muted mb-2 mar-exception-hint"><?= xlt('Document downstream actions for held, refused, or unavailable doses.') ?></div>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <div class="form-check">
                    <input class="form-check-input mar-provider-notified" type="checkbox" value="1" name="provider_notified" id="provider-notified-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>">
                    <label class="form-check-label" for="provider-notified-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>"><?= xlt('Provider notified') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="form-check">
                    <input class="form-check-input mar-pharmacy-follow-up" type="checkbox" value="1" name="pharmacy_follow_up" id="pharmacy-follow-up-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>">
                    <label class="form-check-label" for="pharmacy-follow-up-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>"><?= xlt('Pharmacy follow-up') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="form-check">
                    <input class="form-check-input mar-retry-later" type="checkbox" value="1" name="retry_later" id="retry-later-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>" onchange="marToggleOutcomeFields(this.closest('form').querySelector('.mar-outcome-sel'))">
                    <label class="form-check-label" for="retry-later-<?=(int)($a['id'] ?? 0)?>-<?= htmlspecialchars($action) ?>"><?= xlt('Retry later') ?></label>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small text-muted mb-1"><?= xlt('Retry in') ?></label>
                <select class="form-select form-select-sm mar-retry-minutes" name="retry_minutes" disabled>
                    <option value="30">30 <?= xlt('min') ?></option>
                    <option value="60">60 <?= xlt('min') ?></option>
                    <option value="120">120 <?= xlt('min') ?></option>
                    <option value="240">240 <?= xlt('min') ?></option>
                </select>
            </div>
        </div>
    </div>

    <?php if (!empty($order['is_high_alert'])): ?>
    <div class="row g-2 mt-1 p-2 border rounded bg-warning bg-opacity-10 mar-high-alert-section">
        <div class="col-12">
            <span class="badge text-bg-warning me-1">⚠ <?= xlt('Controlled / High-Alert') ?></span>
            <span class="small text-muted"><?= xlt('Complete waste documentation if any drug was not fully administered.') ?></span>
        </div>
        <div class="col-auto">
            <label class="form-label"><?= xlt('Witness') ?></label>
            <select name="witness_user_id" class="form-select form-select-sm" style="width:160px">
                <option value=""><?= xlt('— None —') ?></option>
                <?php foreach ($_marAllStaff as $wu): ?>
                    <option value="<?= (int)$wu['id'] ?>"
                        <?= (int)($a['witness_user_id'] ?? 0) === (int)$wu['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($wu['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <label class="form-label"><?= xlt('Waste Amount') ?></label>
            <input name="waste_amount" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['waste_amount'] ?? '')) ?>"
                style="width:80px" placeholder="<?= xla('e.g. 2') ?>">
        </div>
        <div class="col-auto">
            <label class="form-label"><?= xlt('Waste Unit') ?></label>
            <input name="waste_unit" class="form-control form-control-sm"
                value="<?= htmlspecialchars((string)($a['waste_unit'] ?? '')) ?>"
                style="width:70px" placeholder="<?= xla('mg / ml') ?>">
        </div>
    </div>
<?php endif; ?>
    <?php

}

/** @param list<array{value:string,label:string}> $options */
function mar_render_datalist(string $id, array $options): void
{
    ?>
    <datalist id="<?= htmlspecialchars($id) ?>">
        <?php foreach ($options as $opt): ?>
            <option value="<?= htmlspecialchars((string)($opt['value'] ?? '')) ?>" label="<?= htmlspecialchars((string)($opt['label'] ?? ($opt['value'] ?? ''))) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <?php
}

/**
 * @param array<string,array<int,array<string,mixed>>> $workspace
 */
function mar_render_workspace(array $workspace, int $facilityId, ?int $episodeId = null): void
{
    $sections = [
        'due_now' => ['title' => xlt('Due Now'), 'badge' => 'text-bg-primary'],
        'due_soon' => ['title' => xlt('Due Soon'), 'badge' => 'text-bg-info'],
        'overdue' => ['title' => xlt('Overdue'), 'badge' => 'text-bg-danger'],
        'awaiting_cosign' => ['title' => xlt('Awaiting Co-Sign'), 'badge' => 'text-bg-warning'],
        'recent_prn' => ['title' => xlt('Recent PRN / Recheck'), 'badge' => 'text-bg-secondary'],
        'exception_followup' => ['title' => xlt('Exception Follow-Up'), 'badge' => 'text-bg-dark'],
    ];
    ?>
    <div class="card shadow-sm mb-3 no-print">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="fw-semibold">🩺 <?= xlt('Med-Pass Workspace') ?></div>
            <div class="small text-muted"><?= xlt('Prioritized queue for this pass') ?></div>
        </div>
        <div class="card-body py-2">
            <div class="row g-3">
                <?php foreach ($sections as $key => $meta): $rows = $workspace[$key] ?? []; ?>
                    <div class="col-12 col-xl-6">
                        <div class="border rounded h-100">
                            <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-semibold"><?= htmlspecialchars($meta['title']) ?></span>
                                <span class="badge <?= htmlspecialchars($meta['badge']) ?>"><?= count($rows) ?></span>
                            </div>
                            <?php if (empty($rows)): ?>
                                <div class="p-3 text-muted small"><?= xlt('Nothing queued.') ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead class="table-light">
                                        <tr>
                                            <?php if ($episodeId === null): ?><th><?= xlt('Patient') ?></th><?php endif; ?>
                                            <th><?= xlt('Medication') ?></th>
                                            <th><?= xlt('Time') ?></th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($rows as $row):
                                            $targetEpisodeId = (int)($row['episode_id'] ?? $episodeId ?? 0);
                                            $sched = (string)($row['scheduled_datetime'] ?? $row['administered_datetime'] ?? '');
                                            ?>
                                            <tr>
                                                <?php if ($episodeId === null): ?>
                                                    <td class="small fw-semibold"><?= htmlspecialchars((string)($row['patient_name'] ?? $row['pid'] ?? '')) ?></td>
                                                <?php endif; ?>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars((string)($row['drug_name'] ?? '')) ?></div>
                                                    <div class="small text-muted">
                                                        <?= htmlspecialchars(trim((string)($row['ordered_dose'] ?? '') . ' ' . (string)($row['ordered_unit'] ?? '') . ' ' . (string)($row['ordered_route'] ?? ''))) ?>
                                                    </div>
                                                    <?php if (!empty($row['task_label']) || !empty($row['detail'])): ?>
                                                        <div class="small mt-1">
                                                            <?php if (!empty($row['task_label'])): ?>
                                                                <span class="badge text-bg-light border text-dark me-1"><?= htmlspecialchars((string)$row['task_label']) ?></span>
                                                            <?php endif; ?>
                                                            <?php if (!empty($row['detail'])): ?>
                                                                <span class="text-muted"><?= htmlspecialchars((string)$row['detail']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="small"><?= $sched !== '' ? htmlspecialchars(date('n/j g:i a', strtotime($sched) ?: time())) : '—' ?></td>
                                                <td class="text-end">
                                                    <a class="btn btn-sm btn-outline-primary" href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= $targetEpisodeId ?>#mar-order-<?= (int)($row['mar_order_id'] ?? 0) ?>">
                                                        <?= xlt('Open MAR') ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * @param array<string,mixed> $rx
 * @param array<string,array<int,array{value:string,label:string}>> $orderVocab
 * @return array{warnings:list<string>,mapped_cleanly:bool,needs_review:bool,summary:string}
 */
function mar_rx_review_meta(array $rx, array $orderVocab): array
{
    $warnings = [];
    $sourceDrug = trim((string)($rx['drug'] ?? ''));
    $displayDrug = trim((string)($rx['_display_drug'] ?? $sourceDrug));
    $dose = trim((string)($rx['_dose'] ?? $rx['size'] ?? ''));
    $unit = trim((string)($rx['_unit'] ?? ''));
    $route = trim((string)($rx['_route'] ?? ''));
    $freq = trim((string)($rx['_freq'] ?? ''));
    $sig = trim((string)($rx['_sig'] ?? $rx['note'] ?? ''));

    if ($displayDrug === '') {
        $warnings[] = xlt('Medication name is blank.');
    }
    if ($route === '') {
        $warnings[] = xlt('Route needs review.');
    }
    if ($freq === '') {
        $warnings[] = xlt('Frequency needs review.');
    }
    if ($dose === '' && $unit !== '') {
        $warnings[] = xlt('Dose is blank while unit is present.');
    }
    if ($sig === '') {
        $warnings[] = xlt('Signature / instructions are blank.');
    }
    if ($sourceDrug !== '' && strcasecmp($sourceDrug, $displayDrug) === 0 && strlen($sourceDrug) >= 28) {
        $warnings[] = xlt('Long source medication name may need a shorter bedside label.');
    }
    if ($sourceDrug !== '' && $displayDrug !== '' && strcasecmp($sourceDrug, $displayDrug) !== 0) {
        $warnings[] = xlt('Bedside display name differs from source prescription.');
    }

    $routeValues = array_map(static fn(array $row): string => strtoupper(trim((string)($row['value'] ?? ''))), (array)($orderVocab['routes'] ?? []));
    $freqValues = array_map(static fn(array $row): string => strtoupper(trim((string)($row['value'] ?? ''))), (array)($orderVocab['frequencies'] ?? []));
    if ($route !== '' && !in_array(strtoupper($route), $routeValues, true)) {
        $warnings[] = xlt('Route is not in the preferred MAR vocabulary.');
    }
    if ($freq !== '' && !in_array(strtoupper($freq), $freqValues, true)) {
        $warnings[] = xlt('Frequency is not in the preferred MAR vocabulary.');
    }

    $needsReview = !empty($warnings);
    return [
        'warnings' => $warnings,
        'mapped_cleanly' => !$needsReview,
        'needs_review' => $needsReview,
        'summary' => $needsReview ? xlt('Needs review') : xlt('Mapped cleanly'),
    ];
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= xlt('MAR') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href): ?>
        <link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
    <style>
      .mar-grid td, .mar-grid th {
        white-space: nowrap;
        font-size: .82rem;
      }

      .high-alert {
        background-color: #fff3cd !important;
      }

      details > summary {
        cursor: pointer;
        list-style: none;
      }

      details > summary::-webkit-details-marker {
        display: none;
      }

      .mar-hold-reason-wrap,
      .mar-exception-followup-wrap {
        display: none;
      }

      @media print {
        .no-print {
          display: none !important;
        }

        body {
          background: white !important;
          font-size: 10pt;
        }

        .mar-grid td, .mar-grid th {
          font-size: 8.5pt;
        }

        h1 {
          font-size: 14pt;
        }
      }
    </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
    <?php if (!$isPrint): ?>
        <?php mar_render_datalist('mar-drug-options', (array)$drugLookup); ?>
        <?php mar_render_datalist('mar-unit-options', (array)($orderVocab['units'] ?? [])); ?>
        <?php mar_render_datalist('mar-route-options', (array)($orderVocab['routes'] ?? [])); ?>
        <?php mar_render_datalist('mar-frequency-options', (array)($orderVocab['frequencies'] ?? [])); ?>
    <?php endif; ?>
    <div class="container-fluid py-3">

        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2 no-print">
            <h1 class="h4 mb-0">
                <?= xlt('Medication Administration Record') ?>
                <?php if ($view === 'episode' && !empty($episode)): ?>
                    <small class="text-muted fs-6 ms-2">
                        <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
                        &mdash; <?= function_exists('oei_fmt_patient') && $_marPid > 0
                            ? oei_fmt_patient($_marPid, $_marPatientNames)
                            : htmlspecialchars((string)($episode['pid'] ?? '')) ?>
                    </small>
                <?php endif; ?>
            </h1>
            <div class="d-flex gap-2">
                <?php if ($view === 'episode'): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                        href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)($data['episode_id'] ?? 0) ?>&print=1"
                        target="_blank"><?= xlt('Print MAR') ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary"
                    href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Facility View') ?></a>
                <a class="btn btn-sm btn-outline-secondary no-print"
                    href="shift_summary.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Shift Summary') ?></a>
                <?php
                $episodeType = strtoupper((string)($episode['type'] ?? ''));
                $epPid = (int)($episode['pid'] ?? 0);
                $epId  = (int)($data['episode_id'] ?? 0);
                $qs = 'facility_id=' . urlencode((string)$facilityId);
                $profileUrl = null;
                if ($episodeType === 'AL') {
                    $boardUrl   = 'al/board.php?' . $qs;
                    $boardLabel = xlt('Resident Board');
                    $boardIcon  = '🏠';
                    $profileUrl = 'al/profile.php?episode_id=' . $epId . '&pid=' . $epPid . '&' . $qs;
                    $profileLabel = xlt('Resident Profile');
                } elseif ($episodeType === 'IP') {
                    $boardUrl   = 'ip/board.php?' . $qs;
                    $boardLabel = xlt('Floor Board');
                    $boardIcon  = '🏥';
                    $profileUrl = 'ip/profile.php?episode_id=' . $epId . '&pid=' . $epPid . '&' . $qs;
                    $profileLabel = xlt('IP Profile');
                } elseif ($episodeType === 'HBC') {
                    $boardUrl   = 'hbc/board.php?' . $qs;
                    $boardLabel = xlt('Visit Board');
                    $boardIcon  = '🏠';
                    $profileUrl = 'hbc/profile.php?episode_id=' . $epId . '&pid=' . $epPid . '&' . $qs;
                    $profileLabel = xlt('HBC Profile');
                } else {
                    $boardUrl   = 'ed_board.php?' . $qs;
                    $boardLabel = xlt('ED Board');
                    $boardIcon  = '🚑';
                }
                ?>
                <?php if ($profileUrl !== null): ?>
                <a class="btn btn-sm btn-outline-secondary no-print"
                    href="<?= htmlspecialchars($profileUrl) ?>">← <?= $profileLabel ?></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-secondary"
                    href="<?= htmlspecialchars($boardUrl) ?>"><?= $boardIcon . ' ' . $boardLabel ?></a>
            </div>
        </div>

        <?php if (!$isPrint): ?>
            <?php mar_render_workspace($workspace, $facilityId, $view === 'episode' ? (int)($data['episode_id'] ?? 0) : null); ?>
        <?php endif; ?>

        <?php if ($view === 'facility'): ?>
            <!-- ───────────────────── FACILITY OVERDUE VIEW ───────────────────────── -->
            <div class="card shadow-sm">
                <div class="card-header"><?= xlt('Overdue / Pending Medications — All Episodes') ?></div>
                <?php
            // Batch-fetch patient names for overdue list
            $_marOverduePids = array_unique(array_map('intval', array_column($data['overdue'] ?? [], 'pid')));
            $_marOverdueNames = (!empty($_marOverduePids) && function_exists('oei_patient_names'))
                ? oei_patient_names($_marOverduePids) : [];
            ?>
            <?php if (empty($data['overdue'])): ?>
                    <div class="card-body text-success"><?= xlt('No overdue pending medications.') ?></div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 mar-grid">
                            <thead class="table-light">
                            <tr>
                                <th><?= xlt('Episode') ?></th>
                                <th><?= xlt('Patient') ?></th>
                                <th><?= xlt('Drug') ?></th>
                                <th><?= xlt('Scheduled') ?></th>
                                <th><?= xlt('Overdue') ?></th>
                                <th><?= xlt('High Alert') ?></th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['overdue'] as $r):
                                $isHA = (bool)($r['is_high_alert'] ?? false); ?>
                                <?php
                                $_marRPid = (int)($r['pid'] ?? 0);
                                $_marSchedTs = !empty($r['scheduled_datetime']) ? strtotime((string)$r['scheduled_datetime']) : 0;
                                $_marOverdueMin = $_marSchedTs > 0 ? (int)floor((time() - $_marSchedTs) / 60) : 0;
                                ?>
                                <tr class="<?= $isHA ? 'high-alert' : '' ?>">
                                    <td>
                                        <a href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)$r['episode_id'] ?>">
                                            #<?= (int)$r['episode_id'] ?>
                                        </a>
                                    </td>
                                    <td class="fw-semibold">
                                        <?= function_exists('oei_fmt_patient') && $_marRPid > 0
                                            ? oei_fmt_patient($_marRPid, $_marOverdueNames)
                                            : htmlspecialchars((string)$_marRPid) ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)$r['drug_name']) ?></td>
                                    <td><?= htmlspecialchars((string)($r['scheduled_datetime'] ?? '')) ?></td>
                                    <td>
                                        <?php if ($_marOverdueMin > 0): ?>
                                            <span class="badge text-bg-danger">
                                                <?= htmlspecialchars(
                                                    $_marOverdueMin >= 60
                                                        ? floor($_marOverdueMin / 60) . 'h ' . ($_marOverdueMin % 60) . 'm'
                                                        : $_marOverdueMin . 'm'
                                                ) ?> <?= xlt('late') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($isHA): ?>
                                            <span class="badge text-bg-warning">⚠ <?= xlt('HIGH-ALERT') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                            href="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= (int)$r['episode_id'] ?>">
                                            <?= xlt('Open MAR') ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- ───────────────────── EPISODE MAR VIEW ────────────────────────────── -->

            <?php if ($isPrint): ?>
            <!-- Print header -->
            <div class="mb-3 border-bottom pb-2">
                <strong style="font-size:14pt;"><?= xlt('Medication Administration Record') ?></strong>
                <span style="float:right; font-size:9pt;"><?= htmlspecialchars(date('Y-m-d H:i')) ?></span>
                <div class="small text-muted">
                    <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
                    &mdash; <?= function_exists('oei_fmt_patient') && $_marPid > 0
                        ? oei_fmt_patient($_marPid, $_marPatientNames)
                        : htmlspecialchars((string)($episode['pid'] ?? '')) ?>
                </div>
            </div>
        <?php endif; ?>

            <?php if (!empty($allergyWarnings)): ?>
            <!-- Allergy warning banners -->
                <?php foreach ($allergyWarnings as $w): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 mb-2" role="alert">
                <span style="font-size:1.4rem;">⚠️</span>
                <div>
                    <strong><?= xlt('Allergy Match:') ?></strong>
                    <?= xlt('Order') ?> <strong><?= htmlspecialchars((string)$w['drug']) ?></strong>
                    <?= xlt('matches documented allergen') ?>
                    <strong><?= htmlspecialchars((string)$w['allergen']) ?></strong>
                    <?php if (!empty($w['reaction'])): ?>
                        &mdash; <?= xlt('Reaction:') ?> <?= htmlspecialchars((string)$w['reaction']) ?>
                    <?php endif; ?>
                    <?php if (!empty($w['severity'])): ?>
                        <span class="badge text-bg-danger ms-1"><?= htmlspecialchars((string)$w['severity']) ?></span>
                    <?php endif; ?>
                    <span class="ms-2 fw-semibold text-danger"><?= xlt('Verify before administering.') ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        <?php endif; ?>

            <!-- ── Medication Reconciliation panel ────────────────── -->
            <?php if ($view === 'episode' && !$isPrint && !empty($data['rx_prescriptions'])): ?>
            <?php
            $rxPendingReview = 0;
            $rxMappedCleanly = 0;
            $rxNeedReview = 0;
            foreach ($data['rx_prescriptions'] as $__rxCounterRow) {
                $rxIdCounter = (int)($__rxCounterRow['id'] ?? 0);
                if (isset($data['imported_rx_ids'][$rxIdCounter])) {
                    continue;
                }
                $rxPendingReview++;
                $__meta = mar_rx_review_meta($__rxCounterRow, $orderVocab);
                if (!empty($__meta['needs_review'])) {
                    $rxNeedReview++;
                } else {
                    $rxMappedCleanly++;
                }
            }
            ?>
            <div class="card shadow-sm mb-3 no-print border-primary">
                <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between py-2 flex-wrap gap-2">
                    <span class="fw-semibold">💊 <?= xlt('Medication Reconciliation — Active Prescriptions') ?></span>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge text-bg-light text-primary">
                            <?= count($data['rx_prescriptions']) ?> <?= xlt('active') ?>
                        </span>
                        <button type="button"
                            class="btn btn-sm btn-light text-primary fw-semibold"
                            data-bs-toggle="collapse"
                            data-bs-target="#rx-reconciliation-panel"
                            aria-expanded="false"
                            aria-controls="rx-reconciliation-panel">
                            <?= xlt('Open panel') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body py-2 px-3 border-bottom bg-light d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div class="d-flex flex-wrap gap-2 small">
                        <span class="badge text-bg-secondary"><?= htmlspecialchars((string)$rxPendingReview) ?> <?= xlt('pending') ?></span>
                        <span class="badge text-bg-success"><?= htmlspecialchars((string)count($data['imported_rx_ids'])) ?> <?= xlt('on MAR') ?></span>
                    </div>
                    <div class="small text-muted"><?= xlt('Collapsed by default to keep MAR focused. Expand to review and activate imported prescriptions.') ?></div>
                </div>
                <div class="collapse" id="rx-reconciliation-panel">
                    <div class="card-body p-2 bg-light border-bottom small text-muted d-flex justify-content-between flex-wrap gap-2">
                        <div><?= xlt('Review and edit imported medication names, dose, route, frequency, and signature before activation. Common bedside-friendly drug labels are suggested first in lookup so staff can shorten OpenEMR Rx/RxNorm-style names without changing the source prescription.') ?></div>
                        <div><?= xlt('Shared episode review path for IP / ED / OBS / BH.') ?></div>
                    </div>
                    <div class="card-body border-bottom py-2">
                        <div class="d-flex flex-wrap gap-2 small">
                            <span class="badge text-bg-secondary"><?= htmlspecialchars((string)$rxPendingReview) ?> <?= xlt('pending review') ?></span>
                            <span class="badge text-bg-success"><?= htmlspecialchars((string)$rxMappedCleanly) ?> <?= xlt('mapped cleanly') ?></span>
                            <span class="badge text-bg-warning text-dark"><?= htmlspecialchars((string)$rxNeedReview) ?> <?= xlt('need review') ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                    <form method="POST" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                        <input type="hidden" name="action" value="import_rx">
                        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                        <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                        <input type="hidden" name="pid" value="<?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 align-middle" style="font-size:.85rem">
                                <thead class="table-light">
                                <tr>
                                    <th style="width:32px"></th>
                                    <th><?= xlt('Medication') ?></th>
                                    <th><?= xlt('Dose / Unit') ?></th>
                                    <th><?= xlt('Route') ?></th>
                                    <th><?= xlt('Frequency') ?></th>
                                    <th><?= xlt('Sig / Instructions') ?></th>
                                    <th><?= xlt('Status') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($data['rx_prescriptions'] as $rx):
                                    $rxId = (int)($rx['id'] ?? 0);
                                    $onMar = isset($data['imported_rx_ids'][$rxId]);
                                    $displayDrug = (string)($rx['_display_drug'] ?? $rx['drug'] ?? '');
                                    $doseValue = (string)($rx['_dose'] ?? $rx['size'] ?? '');
                                    $unitValue = (string)($rx['_unit'] ?? '');
                                    $routeValue = (string)($rx['_route'] ?? '');
                                    $freqValue = (string)($rx['_freq'] ?? '');
                                    $sigValue = (string)($rx['_sig'] ?? $rx['note'] ?? '');
                                    $reviewMeta = mar_rx_review_meta($rx, $orderVocab);
                                    $reviewId = 'rx-review-' . $rxId;
                                    $reviewActionLabel = $onMar
                                        ? '✓ ' . xlt('Review import')
                                        : (!empty($reviewMeta['needs_review']) ? '⚠ ' . xlt('Review import') : '✓ ' . xlt('Review import'));
                                    $reviewActionClass = $onMar
                                        ? 'text-secondary'
                                        : (!empty($reviewMeta['needs_review']) ? 'text-warning-emphasis' : 'text-primary');
                                    ?>
                                    <tr class="<?= $onMar ? 'table-success text-muted' : '' ?>" data-review-target="<?= htmlspecialchars($reviewId) ?>">
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input rx-check" name="rx_ids[]" value="<?= $rxId ?>" <?= $onMar ? 'disabled' : '' ?>>
                                        </td>
                                        <td>
                                            <div class="fw-semibold mb-1"><?= htmlspecialchars((string)($rx['drug'] ?? '')) ?></div>
                                            <label class="form-label form-label-sm small text-muted mb-1"><?= xlt('Bedside label shown on MAR') ?></label>
                                            <input type="text" class="form-control form-control-sm rx-edit-field" name="rx_display_drug[<?= $rxId ?>]" value="<?= htmlspecialchars($displayDrug) ?>" list="mar-drug-options" <?= $onMar ? 'readonly' : '' ?>>
                                            <div class="small text-muted mt-1"><?= xlt('Source prescription stays visible in review for audit context.') ?></div>
                                            <div class="mt-1 d-flex flex-wrap align-items-center gap-2 small">
                                                <?php if (!empty($rx['provider_name'])): ?>
                                                    <span class="text-muted"><?= htmlspecialchars((string)$rx['provider_name']) ?></span>
                                                <?php endif; ?>
                                                <button type="button"
                                                    class="btn btn-link btn-sm p-0 text-decoration-underline rx-review-toggle <?= $reviewActionClass ?>"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#<?= htmlspecialchars($reviewId) ?>"
                                                    aria-expanded="false"
                                                    aria-controls="<?= htmlspecialchars($reviewId) ?>">
                                                    <?= $reviewActionLabel ?>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="row g-1">
                                                <div class="col-6"><input type="text" class="form-control form-control-sm rx-edit-field" name="rx_dose[<?= $rxId ?>]" value="<?= htmlspecialchars($doseValue) ?>" <?= $onMar ? 'readonly' : '' ?>></div>
                                                <div class="col-6"><input type="text" class="form-control form-control-sm rx-edit-field" name="rx_unit[<?= $rxId ?>]" value="<?= htmlspecialchars($unitValue) ?>" list="mar-unit-options" <?= $onMar ? 'readonly' : '' ?>></div>
                                            </div>
                                        </td>
                                        <td><input type="text" class="form-control form-control-sm rx-edit-field" name="rx_route[<?= $rxId ?>]" value="<?= htmlspecialchars($routeValue) ?>" list="mar-route-options" <?= $onMar ? 'readonly' : '' ?>></td>
                                        <td><input type="text" class="form-control form-control-sm rx-edit-field" name="rx_frequency[<?= $rxId ?>]" value="<?= htmlspecialchars($freqValue) ?>" list="mar-frequency-options" <?= $onMar ? 'readonly' : '' ?>></td>
                                        <td><input type="text" class="form-control form-control-sm rx-edit-field" name="rx_sig[<?= $rxId ?>]" value="<?= htmlspecialchars($sigValue) ?>" <?= $onMar ? 'readonly' : '' ?>></td>
                                        <td>
                                            <?php if ($onMar): ?>
                                                <span class="badge text-bg-success">✓ <?= xlt('On MAR') ?></span>
                                            <?php elseif (!empty($reviewMeta['needs_review'])): ?>
                                                <span class="badge text-bg-warning text-dark"><?= xlt('Needs review') ?></span>
                                            <?php else: ?>
                                                <span class="badge text-bg-light border text-secondary"><?= xlt('Mapped cleanly') ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="<?= htmlspecialchars($reviewId) ?>">
                                        <td colspan="7" class="p-0 border-top-0 bg-light">
                                            <div class="small p-3 border-top bg-light">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                    <div class="fw-semibold"><?= xlt('Import review') ?></div>
                                                    <div class="d-flex flex-wrap gap-1">
                                                        <span class="badge <?= !empty($reviewMeta['needs_review']) ? 'text-bg-warning text-dark' : 'text-bg-success' ?>">
                                                            <?= htmlspecialchars($reviewMeta['summary']) ?>
                                                        </span>
                                                        <?php if ($onMar): ?>
                                                            <span class="badge text-bg-success"><?= xlt('Already on MAR') ?></span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($rx['provider_name'])): ?>
                                                            <span class="badge text-bg-light border"><?= htmlspecialchars((string)$rx['provider_name']) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-lg-6">
                                                        <div class="border rounded p-2 h-100 bg-white">
                                                            <div class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.72rem"><?= xlt('Source prescription') ?></div>
                                                            <div><strong><?= xlt('Medication') ?>:</strong> <?= htmlspecialchars((string)($rx['drug'] ?? '')) ?></div>
                                                            <div><strong><?= xlt('Dose / Unit') ?>:</strong> <?= htmlspecialchars(trim((string)($rx['size'] ?? '') . ' ' . (string)($rx['unit'] ?? ''))) ?></div>
                                                            <div><strong><?= xlt('Route') ?>:</strong> <?= htmlspecialchars((string)($rx['route'] ?? '')) ?></div>
                                                            <div><strong><?= xlt('Frequency') ?>:</strong> <?= htmlspecialchars((string)($rx['interval'] ?? '')) ?></div>
                                                            <div><strong><?= xlt('Sig') ?>:</strong> <?= htmlspecialchars((string)($rx['note'] ?? '')) ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="col-lg-6">
                                                        <div class="border rounded p-2 h-100 bg-white">
                                                            <div class="fw-semibold text-uppercase text-muted mb-2" style="font-size:.72rem"><?= xlt('Normalized MAR values') ?></div>
                                                            <div><strong><?= xlt('Display label') ?>:</strong> <?= htmlspecialchars($displayDrug) ?></div>
                                                            <div><strong><?= xlt('Dose / Unit') ?>:</strong> <?= htmlspecialchars(trim($doseValue . ' ' . $unitValue)) ?></div>
                                                            <div><strong><?= xlt('Route') ?>:</strong> <?= htmlspecialchars($routeValue) ?></div>
                                                            <div><strong><?= xlt('Frequency') ?>:</strong> <?= htmlspecialchars($freqValue) ?></div>
                                                            <div><strong><?= xlt('Sig') ?>:</strong> <?= htmlspecialchars($sigValue) ?></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="mt-3">
                                                    <?php if (!empty($reviewMeta['warnings'])): ?>
                                                        <div class="fw-semibold text-warning-emphasis mb-1"><?= xlt('Review focus') ?></div>
                                                        <ul class="mb-0 ps-3">
                                                            <?php foreach ($reviewMeta['warnings'] as $warning): ?>
                                                                <li><?= htmlspecialchars((string)$warning) ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php else: ?>
                                                        <div class="text-success"><?= xlt('This prescription maps cleanly into the preferred MAR vocabulary.') ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-2 border-top d-flex align-items-center gap-3 flex-wrap">
                            <button type="submit" class="btn btn-primary btn-sm" id="btnActivate" disabled>✓ <?= xlt('Activate Selected on MAR') ?></button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnSelectAll"><?= xlt('Select All Pending') ?></button>
                            <span class="small text-muted"><?= xlt('Edit the bedside-facing label, dose, route, frequency, or sig before activation.') ?></span>
                        </div>
                    </form>
                    </div>
                </div>
            </div>
            <script>
                (function () {
                    const checks = () => document.querySelectorAll('.rx-check:not([disabled])');
                    const activate = document.getElementById('btnActivate');
                    const selectAll = document.getElementById('btnSelectAll');
                    function updateBtn() {
                        if (activate) activate.disabled = ![...checks()].some(c => c.checked);
                    }
                    function autoSelectRow(target) {
                        const row = target.closest('tr');
                        if (!row) return;
                        const check = row.querySelector('.rx-check:not([disabled])');
                        if (check) {
                            check.checked = true;
                            updateBtn();
                        }
                        const reviewId = row.getAttribute('data-review-target');
                        const review = reviewId ? document.getElementById(reviewId) : null;
                        if (review) {
                            if (window.bootstrap && bootstrap.Collapse) {
                                bootstrap.Collapse.getOrCreateInstance(review, {toggle: false}).show();
                            } else {
                                review.classList.add('show');
                            }
                        }
                    }
                    document.addEventListener('change', e => {
                        if (e.target.classList.contains('rx-check')) updateBtn();
                        if (e.target.classList.contains('rx-edit-field')) autoSelectRow(e.target);
                    });
                    document.addEventListener('input', e => {
                        if (e.target.classList.contains('rx-edit-field')) autoSelectRow(e.target);
                    });
                    if (selectAll) {
                        selectAll.addEventListener('click', () => {
                            checks().forEach(c => { c.checked = true; });
                            updateBtn();
                        });
                    }
                    updateBtn();
                })();
            </script>
        <?php elseif ($view === 'episode' && !$isPrint && empty($data['rx_prescriptions'])): ?>
            <div class="alert alert-light border no-print py-2 mb-3" style="font-size:.85rem">
                💊 <?= xlt('No active prescriptions found in OpenEMR for this patient — orders must be entered manually.') ?>
            </div>
        <?php endif; ?>

            <!-- Place Order form -->
            <?php if (!$isPrint): ?>
            <div class="card shadow-sm mb-3 no-print">
                <div class="card-body p-0">
                    <details>
                        <summary class="px-3 py-2">
                            <span class="btn btn-sm btn-outline-primary">+ <?= xlt('Place Medication Order') ?></span>
                        </summary>
                        <div class="p-3 bg-light border-top">
                            <form method="post" class="row g-2" autocomplete="off" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                <input type="hidden" name="action" value="place_order">
                                <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                <input type="hidden" name="pid" value="<?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>">

                                <div class="col-md-3">
                                    <label class="form-label"><?= xlt('Drug Name') ?> *</label>
                                    <input name="drug_name" class="form-control form-control-sm" list="mar-drug-options" required>
                                    <div class="form-text"><?= xlt('Lookup suggestions prefer common bedside labels first, then full catalog names.') ?></div>
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><?= xlt('Dose') ?></label>
                                    <input name="dose" class="form-control form-control-sm" placeholder="5">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><?= xlt('Unit') ?></label>
                                    <input name="unit" class="form-control form-control-sm" list="mar-unit-options" placeholder="mg">
                                </div>
                                <div class="col-md-1">
                                    <label class="form-label"><?= xlt('Route') ?></label>
                                    <input name="route" class="form-control form-control-sm" list="mar-route-options" placeholder="PO">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label"><?= xlt('Frequency') ?></label>
                                    <input name="frequency" class="form-control form-control-sm" list="mar-frequency-options" placeholder="Q6H / BID / PRN">
                                    <div class="form-text"><?= xlt('Use PRN for as-needed.') ?></div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label"><?= xlt('Instructions') ?></label>
                                    <input name="instructions" class="form-control form-control-sm"
                                        placeholder="<?= xla('Hold if HR < 60, dilute in 100 mL NS') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="is_high_alert" id="pha" value="1">
                                        <label class="form-check-label text-warning fw-semibold" for="pha">
                                            ⚠ <?= xlt('High-Alert Medication') ?>
                                        </label>
                                    </div>
                                    <div class="form-text d-inline ms-3"><?= xlt('Check for drugs not auto-detected (trade names, compounds).') ?></div>
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="checkbox" name="is_stat" id="pstat" value="1">
                                        <label class="form-check-label text-danger fw-semibold" for="pstat">
                                            🔴 <?= xlt('STAT — Give Immediately') ?>
                                        </label>
                                    </div>
                                    <div class="form-text d-inline ms-3">
                                        <?= xlt('STAT creates an immediate slot plus the normal schedule. Not available for PRN.') ?>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <button class="btn btn-primary btn-sm"><?= xlt('Place Order') ?></button>
                                </div>
                            </form>
                        </div>
                    </details>
                </div>
            </div>

            <script>
                /* STAT checkbox: disable when frequency is PRN */
                (function () {
                    var freqInput = document.querySelector('input[name="frequency"]');
                    var statBox = document.getElementById('pstat');
                    if (!freqInput || !statBox) return;

                    function syncStat() {
                        var isPrn = freqInput.value.trim().toUpperCase() === 'PRN';
                        statBox.disabled = isPrn;
                        if (isPrn) statBox.checked = false;
                    }

                    freqInput.addEventListener('input', syncStat);
                    freqInput.addEventListener('change', syncStat);
                    syncStat();
                })();

                document.querySelectorAll('.js-edit-frequency').forEach(function (freqInput) {
                    var root = freqInput.closest('form');
                    var statBox = root ? root.querySelector('.js-edit-stat') : null;
                    if (!statBox) return;
                    function syncEditStat() {
                        var isPrn = freqInput.value.trim().toUpperCase() === 'PRN';
                        statBox.disabled = isPrn;
                        if (isPrn) statBox.checked = false;
                    }
                    freqInput.addEventListener('input', syncEditStat);
                    freqInput.addEventListener('change', syncEditStat);
                    syncEditStat();
                });
            </script>
        <?php endif; ?>

            <?php foreach (($data['grid'] ?? []) as $order):
                $isPrn = (bool)$order['is_prn'];
                $orderId = (int)$order['id'];
                $sourceRx = (array)($order['_source_rx'] ?? (($order['rx_id'] ?? null) && isset($rxSourceMap[(int)$order['rx_id']]) ? $rxSourceMap[(int)$order['rx_id']] : []));
                $sourceDrug = trim((string)($sourceRx['drug'] ?? ''));
                $sourceSig = trim((string)($sourceRx['note'] ?? ''));
                $sourceProvider = trim((string)($sourceRx['provider_name'] ?? ''));
                $hasSourceContext = $sourceDrug !== '' || !empty($order['rx_id']);
                ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <strong><?= htmlspecialchars((string)$order['drug_name']) ?></strong>
                            <span class="badge text-bg-light border text-secondary"><?= xlt('Bedside label') ?></span>
                            <?php if (!empty($order['rx_id'])): ?>
                                <span class="badge text-bg-info"><?= xlt('Imported Rx') ?> #<?= (int)$order['rx_id'] ?></span>
                            <?php endif; ?>
                            <?php if (!empty($order['is_stat'])): ?>
                                <span class="badge text-bg-danger">🔴 STAT</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasSourceContext): ?>
                            <div class="small text-muted mt-1">
                                <?= xlt('Source prescription') ?>:
                                <?php if ($sourceDrug !== ''): ?>
                                    <span><?= htmlspecialchars($sourceDrug) ?></span>
                                <?php else: ?>
                                    <span><?= xlt('Linked by Rx ID') ?> #<?= (int)($order['rx_id'] ?? 0) ?></span>
                                <?php endif; ?>
                                <?php if ($sourceProvider !== ''): ?>
                                    <span class="ms-1">• <?= htmlspecialchars($sourceProvider) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="text-muted mt-1">
                            <?= htmlspecialchars((string)$order['dose']) ?>
                            <?= htmlspecialchars((string)$order['unit']) ?>
                            &bull;
                            <?= htmlspecialchars((string)$order['route']) ?>
                            <?php if (!$isPrn): ?>
                                &bull; <?= htmlspecialchars((string)$order['frequency']) ?>
                            <?php else: ?>
                                <span class="badge text-bg-info ms-1">PRN</span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($order['instructions'])): ?>
                            <small class="text-muted fst-italic d-block mt-1"><?= htmlspecialchars((string)$order['instructions']) ?></small>
                        <?php endif; ?>
                    </div>

                    <?php if (!$isPrint): ?>
                        <div class="d-flex gap-2 no-print flex-wrap">
                            <details class="d-inline">
                                <summary><span class="btn btn-sm btn-outline-secondary"><?= xlt('Edit Order') ?></span></summary>
                                <div class="position-absolute bg-white border rounded shadow p-3" style="z-index:10; min-width:360px; max-width:520px; right:16px;">
                                    <form method="post" class="row g-2" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                        <input type="hidden" name="action" value="update_order">
                                        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                        <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                        <input type="hidden" name="order_id" value="<?= $orderId ?>">

                                        <div class="col-12">
                                            <label class="form-label small text-muted mb-1"><?= xlt('Bedside label shown on MAR') ?></label>
                                            <input name="drug_name" class="form-control form-control-sm" list="mar-drug-options" value="<?= htmlspecialchars((string)$order['drug_name']) ?>" required>
                                        </div>
                                        <?php if ($hasSourceContext): ?>
                                            <div class="col-12">
                                                <div class="small text-muted">
                                                    <strong><?= xlt('Source prescription') ?>:</strong>
                                                    <?= htmlspecialchars($sourceDrug !== '' ? $sourceDrug : ('Rx #' . (int)($order['rx_id'] ?? 0))) ?>
                                                    <?php if ($sourceSig !== ''): ?>
                                                        <span class="d-block mt-1"><strong><?= xlt('Original sig') ?>:</strong> <?= htmlspecialchars($sourceSig) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <div class="col-4">
                                            <label class="form-label"><?= xlt('Dose') ?></label>
                                            <input name="dose" class="form-control form-control-sm" value="<?= htmlspecialchars((string)$order['dose']) ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label"><?= xlt('Unit') ?></label>
                                            <input name="unit" class="form-control form-control-sm" list="mar-unit-options" value="<?= htmlspecialchars((string)$order['unit']) ?>">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label"><?= xlt('Route') ?></label>
                                            <input name="route" class="form-control form-control-sm" list="mar-route-options" value="<?= htmlspecialchars((string)$order['route']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label"><?= xlt('Frequency') ?></label>
                                            <input name="frequency" class="form-control form-control-sm js-edit-frequency" list="mar-frequency-options" value="<?= htmlspecialchars((string)$order['frequency']) ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label"><?= xlt('Instructions') ?></label>
                                            <input name="instructions" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($order['instructions'] ?? '')) ?>">
                                        </div>
                                        <div class="col-12 d-flex flex-wrap gap-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="is_high_alert" id="order-ha-<?= $orderId ?>" value="1" <?= !empty($order['is_high_alert']) ? 'checked' : '' ?>>
                                                <label class="form-check-label text-warning fw-semibold" for="order-ha-<?= $orderId ?>">⚠ <?= xlt('High-Alert Medication') ?></label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input js-edit-stat" type="checkbox" name="is_stat" id="order-stat-<?= $orderId ?>" value="1" <?= !empty($order['is_stat']) ? 'checked' : '' ?>>
                                                <label class="form-check-label text-danger fw-semibold" for="order-stat-<?= $orderId ?>">🔴 <?= xlt('STAT') ?></label>
                                            </div>
                                        </div>
                                        <div class="col-12 small text-muted"><?= xlt('Update the bedside-facing label without losing the original prescription context shown above.') ?></div>
                                        <div class="col-12 d-flex justify-content-end gap-2">
                                            <button class="btn btn-sm btn-primary"><?= xlt('Save changes') ?></button>
                                        </div>
                                    </form>
                                </div>
                            </details>

                            <?php if (!$isPrn): ?>
                                <!-- Extend window -->
                                <details class="d-inline">
                                    <summary><span class="btn btn-sm btn-outline-secondary"><?= xlt('Extend') ?></span></summary>
                                    <div class="position-absolute bg-white border rounded shadow p-2" style="z-index:10; min-width:220px;">
                                        <form method="post" class="d-flex gap-2 align-items-end" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                            <input type="hidden" name="action" value="extend_window">
                                            <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                            <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <div>
                                                <label class="form-label mb-0 small"><?= xlt('Add hours') ?></label>
                                                <select name="extend_hours" class="form-select form-select-sm" style="width:80px">
                                                    <option value="8">8h</option>
                                                    <option value="12">12h</option>
                                                    <option value="24" selected>24h</option>
                                                    <option value="48">48h</option>
                                                    <option value="72">72h</option>
                                                </select>
                                            </div>
                                            <button class="btn btn-sm btn-primary"><?= xlt('Go') ?></button>
                                        </form>
                                    </div>
                                </details>
                            <?php endif; ?>

                            <!-- Discontinue -->
                            <form method="post" class="d-inline" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                <input type="hidden" name="action" value="discontinue_order">
                                <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                <button class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('<?= xlt('Discontinue this medication?') ?>')">
                                    <?= xlt('D/C') ?>
                                </button>
                            </form>

                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($isPrn && !$isPrint): ?>
                    <!-- PRN Give form -->
                    <div class="card-body p-0 border-bottom no-print">
                        <details>
                            <summary class="px-3 py-2">
                                <span class="btn btn-sm btn-outline-success"><?= xlt('+ Give PRN Dose') ?></span>
                            </summary>
                            <div class="p-3 bg-light">
                                <form method="post" class="row g-2" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                    <input type="hidden" name="action" value="give_prn">
                                    <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                    <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                    <input type="hidden" name="pid" value="<?= htmlspecialchars((string)($episode['pid'] ?? '')) ?>">
                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                    <input type="hidden" name="drug_name" value="<?= htmlspecialchars((string)$order['drug_name']) ?>">

                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Given At') ?></label>
                                        <input type="datetime-local" name="administered_datetime" class="form-control form-control-sm"
                                            style="width:165px" value="<?= date('Y-m-d\TH:i') ?>">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Dose') ?></label>
                                        <input name="dose_given" class="form-control form-control-sm"
                                            value="<?= htmlspecialchars((string)$order['dose']) ?>" style="width:70px">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Unit') ?></label>
                                        <input name="unit_given" class="form-control form-control-sm"
                                            value="<?= htmlspecialchars((string)$order['unit']) ?>" style="width:55px">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Route') ?></label>
                                        <input name="route_given" class="form-control form-control-sm"
                                            value="<?= htmlspecialchars((string)$order['route']) ?>" style="width:55px">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Site') ?></label>
                                        <input name="site" class="form-control form-control-sm" style="width:70px" placeholder="LDA…">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Lot #') ?></label>
                                        <input name="lot_number" class="form-control form-control-sm" style="width:90px">
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label"><?= xlt('Note') ?></label>
                                        <input name="note" class="form-control form-control-sm" style="width:140px">
                                    </div>
                                    <div class="col-auto d-flex align-items-end">
                                        <button class="btn btn-success btn-sm"><?= xlt('Record PRN') ?></button>
                                    </div>
                                </form>
                            </div>
                        </details>
                    </div>
                <?php endif; ?>

                <!-- Administration rows -->
                <?php if (!empty($order['admins'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm mar-grid align-middle mb-0">
                            <thead class="table-light">
                            <tr>
                                <th><?= xlt('Scheduled') ?></th>
                                <th><?= xlt('Given At') ?></th>
                                <th><?= xlt('Outcome') ?></th>
                                <th><?= xlt('Hold Reason') ?></th>
                                <th><?= xlt('Dose') ?></th>
                                <th><?= xlt('Site') ?></th>
                                <th><?= xlt('Lot #') ?></th>
                                <th><?= xlt('Nurse') ?></th>
                                <th><?= xlt('Note') ?></th>
                                <?php if (!$isPrint): ?>
                                    <th class="no-print"><?= xlt('Witness') ?></th>
                                    <th class="no-print"><?= xlt('Waste') ?></th>
                                    <th class="no-print"><?= xlt('Co-Sign') ?></th>
                                    <th class="no-print"></th><?php endif; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($order['admins'] as $a):
                                $isHA = (bool)($a['is_high_alert'] ?? false);
                                $outcome = (string)($a['outcome'] ?? 'PENDING');
                                ?>
                                <tr class="<?= $isHA ? 'high-alert' : '' ?>">
                                    <td>
                                        <?= htmlspecialchars((string)($a['scheduled_datetime'] ?? xlt('PRN'))) ?>
                                        <?php if ($isHA): ?>
                                            <span class="badge text-bg-warning ms-1" style="font-size:.65rem;"><?= xlt('HA') ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($a['administered_datetime'] ?? '—')) ?></td>
                                    <td>
              <span class="badge <?= mar_badge($outcome) ?>">
                                <?= htmlspecialchars($outcome) ?>
              </span>
                                    </td>
                                    <td>
                                        <?php $hr = (string)($a['hold_reason'] ?? '');
                                        echo $hr ? htmlspecialchars($holdReasons[$hr] ?? $hr) : '—'; ?>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars((string)($a['dose_given'] ?? '')) ?>
                                        <?= htmlspecialchars((string)($a['unit_given'] ?? '')) ?>
                                    </td>
                                    <td><?= htmlspecialchars((string)($a['site'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string)($a['lot_number'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars(trim((string)($a['nurse_name'] ?? (string)($a['administered_by_user_id'] ?? '')))) ?></td>
                                    <td><?= htmlspecialchars((string)($a['note'] ?? '')) ?></td>
                                    <?php if (!$isPrint): ?>
                                    <td class="no-print">
                                        <?php if (!empty($a['witness_user_id'])): ?>
                                            <span class="text-muted small"><?= htmlspecialchars((string)($a['witness_name'] ?? (string)$a['witness_user_id'])) ?></span>
                                        <?php else: ?>
                                            <?php if ($isHA): ?><span class="badge text-bg-warning">⚠ <?= xlt('Req') ?></span><?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <?php if (!empty($a['waste_amount'])): ?>
                                            <?= htmlspecialchars((string)$a['waste_amount']) ?> <?= htmlspecialchars((string)($a['waste_unit'] ?? '')) ?>
                                        <?php elseif ($isHA && in_array($a['outcome'] ?? '', ['GIVEN'], true)): ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <?php if ($isHA && $outcome === 'GIVEN'): ?>
                                            <?php if (!empty($a['co_sign_user_id'])): ?>
                                                <span class="text-success small fw-semibold">✓ <?= htmlspecialchars((string)($a['co_sign_name'] ?? (string)$a['co_sign_user_id'])) ?></span>
                                                <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars(substr((string)($a['co_signed_datetime'] ?? ''), 0, 16)) ?></div>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">⚠ <?= xlt('Needed') ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>

                                    <?php if (!$isPrint): ?>
                                        <td class="no-print">
                                            <?php if ($outcome === 'PENDING'): ?>
                                                <!-- Record (PENDING only) -->
                                                <details>
                                                    <summary><span class="btn btn-sm btn-outline-secondary py-0 px-1"><?= xlt('Record') ?></span></summary>
                                                    <div class="p-2 bg-light border rounded mt-1" style="min-width:600px;">
                                                        <form method="post" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                                            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                                            <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                                            <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                                            <?php mar_admin_fields($order, $a, 'record_admin', $holdReasons); ?>
                                                        </form>
                                                    </div>
                                                </details>
                                            <?php else: ?>
                                                <?php if ($isHA && $outcome === 'GIVEN' && empty($a['co_sign_user_id'])): ?>
                                                <!-- Co-Sign (high-alert, GIVEN, not yet co-signed) -->
                                                <details class="mb-1">
                                                    <summary><span class="btn btn-sm btn-warning py-0 px-1">✍ <?= xlt('Co-Sign') ?></span></summary>
                                                    <div class="p-2 bg-warning bg-opacity-10 border rounded mt-1" style="min-width:300px;">
                                                        <p class="small mb-2 fw-semibold text-dark"><?= xlt('Second-nurse co-signature required for this high-alert administration.') ?></p>
                                                        <form method="post" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                                            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                                            <input type="hidden" name="action" value="co_sign">
                                                            <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                                            <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                                            <input type="hidden" name="admin_id" value="<?= (int)($a['id'] ?? 0) ?>">
                                                            <div class="d-flex gap-2 align-items-end">
                                                                <div>
                                                                    <label class="form-label mb-0 small fw-semibold"><?= xlt('Co-Signing Nurse') ?></label>
                                                                    <select name="co_sign_user_id" class="form-select form-select-sm" style="width:165px" required>
                                                                        <option value=""><?= xlt('— Select —') ?></option>
                                                                        <?php foreach ($_marAllStaff as $csu): ?>
                                                                            <option value="<?= (int)$csu['id'] ?>"><?= htmlspecialchars((string)($csu['name'] ?? '')) ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <button class="btn btn-warning btn-sm">✓ <?= xlt('Co-Sign') ?></button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>
                                                <?php endif; ?>
                                                <!-- Amend (already documented) -->
                                                <details>
                                                    <summary><span class="btn btn-sm btn-outline-secondary py-0 px-1 text-muted"><?= xlt('Amend') ?></span></summary>
                                                    <div class="p-2 bg-light border rounded mt-1" style="min-width:600px;">
                                                        <div class="alert alert-warning py-1 small mb-2">
                                                            ⚠ <?= xlt('Amending a completed record. The original will be preserved in the note field.') ?>
                                                        </div>
                                                        <form method="post" action="mar.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= (int)($data['episode_id'] ?? 0) ?>">
                                                            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
                                                            <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
                                                            <input type="hidden" name="episode_id" value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
                                                            <?php mar_admin_fields($order, $a, 'amend_admin', $holdReasons); ?>
                                                        </form>
                                                    </div>
                                                </details>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>

                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="card-body text-muted small">
                        <?= $isPrn ? xlt('No PRN doses recorded yet.') : xlt('No scheduled slots yet — use Extend to generate slots.') ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

            <?php if (empty($data['grid'])): ?>
            <div class="alert alert-info"><?= xlt('No active medication orders for this episode.') ?></div>
        <?php endif; ?>

            <?php if ($isPrint): ?>
            <div class="small text-muted mt-3">
                <?= xlt('Printed') ?>: <?= htmlspecialchars(date('Y-m-d H:i')) ?>
                &mdash; <?= xlt('Episode') ?> #<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>
            </div>
        <?php endif; ?>

        <?php endif; // episode view ?>

    </div>

    <?php if ($href): ?>
        <?= institutional_bootstrap5_js_tag() ?>
    <?php endif; ?>

    <?php if ($view === 'episode' && !$isPrint): ?>
    <!-- PIN Re-Auth Modal — shown before record_admin / amend_admin / give_prn commits -->
    <input type="hidden" id="marPinCsrf"
           value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
    <input type="hidden" id="marPinFacilityId"
           value="<?= htmlspecialchars((string)$facilityId) ?>">
    <input type="hidden" id="marPinEpisodeId"
           value="<?= htmlspecialchars((string)($data['episode_id'] ?? '')) ?>">
    <div class="modal fade" id="marPinModal" tabindex="-1" aria-labelledby="marPinModalLabel"
         aria-modal="true" role="dialog" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header py-2 bg-dark text-white">
                    <h6 class="modal-title" id="marPinModalLabel">🔒 <?= xlt('Confirm Identity') ?></h6>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        <?= xlt('Enter your password to record this administration.') ?>
                    </p>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">
                            <?= xlt('Password') ?>
                        </label>
                        <input type="password" id="marPinInput"
                               class="form-control form-control-sm"
                               autocomplete="current-password"
                               placeholder="<?= xla('Enter your OpenEMR password') ?>">
                    </div>
                    <div id="marPinError" class="alert alert-danger py-1 small mb-0" style="display:none;">
                        <?= xlt('Password incorrect — please try again.') ?>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" id="marPinCancelBtn">
                        <?= xlt('Cancel') ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-dark" id="marPinVerifyBtn">
                        🔓 <?= xlt('Verify & Submit') ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function marToggleOutcomeFields(sel) {
            if (!sel) {
                return;
            }
            var form = sel.closest('form');
            if (!form) {
                return;
            }

            var outcome = sel.value || 'GIVEN';
            var holdWrap = form.querySelector('.mar-hold-reason-wrap');
            if (holdWrap) {
                holdWrap.style.display = outcome === 'HELD' ? '' : 'none';
            }

            var exceptionWrap = form.querySelector('.mar-exception-followup-wrap');
            var provider = form.querySelector('.mar-provider-notified');
            var pharmacy = form.querySelector('.mar-pharmacy-follow-up');
            var retryLater = form.querySelector('.mar-retry-later');
            var retryMinutes = form.querySelector('.mar-retry-minutes');
            var hint = form.querySelector('.mar-exception-hint');
            var actionInput = form.querySelector('input[name="action"]');
            var isRecording = !!(actionInput && actionInput.value === 'record_admin');
            var isHighAlert = !!form.querySelector('.mar-high-alert-section');
            var isException = ['HELD', 'REFUSED', 'NOT_AVAILABLE', 'MISSED'].indexOf(outcome) !== -1;
            var shouldAutoRetry = isRecording && isHighAlert && ['HELD', 'NOT_AVAILABLE', 'MISSED'].indexOf(outcome) !== -1;

            if (exceptionWrap) {
                exceptionWrap.style.display = isException ? '' : 'none';
            }

            var msg = '<?= xla('Document downstream actions for held, refused, or unavailable doses.') ?>';
            if (outcome === 'HELD') {
                msg = '<?= xla('Consider retry timing once vitals, labs, or NPO status improve.') ?>';
            } else if (outcome === 'REFUSED') {
                msg = '<?= xla('Document teaching, patient reason, and whether the provider was notified.') ?>';
            } else if (outcome === 'NOT_AVAILABLE' || outcome === 'MISSED') {
                msg = '<?= xla('Consider pharmacy follow-up and a retry task when the medication can be given.') ?>';
            }
            if (hint) {
                hint.textContent = msg;
            }

            if (retryLater && shouldAutoRetry && retryLater.dataset.userChanged !== '1') {
                retryLater.checked = true;
            }
            if (retryMinutes && shouldAutoRetry && retryMinutes.dataset.userChanged !== '1' && !retryMinutes.value) {
                retryMinutes.value = '30';
            }

            if (!isException) {
                if (provider) provider.checked = false;
                if (pharmacy) pharmacy.checked = false;
                if (retryLater) retryLater.checked = false;
            }
            if (retryMinutes) {
                retryMinutes.disabled = !(isException && retryLater && retryLater.checked);
            }
        }

        document.querySelectorAll('.mar-outcome-sel').forEach(function (sel) {
            marToggleOutcomeFields(sel);
        });
        document.querySelectorAll('.mar-retry-later').forEach(function (cb) {
            cb.addEventListener('change', function () {
                this.dataset.userChanged = '1';
                marToggleOutcomeFields(this.closest('form').querySelector('.mar-outcome-sel'));
            });
        });
        document.querySelectorAll('.mar-retry-minutes').forEach(function (sel) {
            sel.addEventListener('change', function () {
                this.dataset.userChanged = '1';
            });
        });

        // ── PIN Re-Authentication Interceptor ─────────────────────────────────
        // Intercepts record_admin, amend_admin, and give_prn form submissions.
        // Shows PIN modal, verifies against the server, then re-submits.
        (function () {
            var PIN_ACTIONS = ['record_admin', 'amend_admin', 'give_prn'];
            var pinModal    = null;
            var pendingForm = null;

            var modalEl = document.getElementById('marPinModal');
            if (!modalEl) return; // not episode view or print mode

            // Bootstrap 5 modal instance — wait until BS is loaded
            function getModal() {
                if (!pinModal && typeof bootstrap !== 'undefined') {
                    pinModal = new bootstrap.Modal(modalEl);
                }
                return pinModal;
            }

            // Intercept all form submits at document level
            document.addEventListener('submit', function (e) {
                var form = e.target;
                var actionInput = form.querySelector('input[name="action"]');
                if (!actionInput) return;
                if (PIN_ACTIONS.indexOf(actionInput.value) === -1) return;
                // Already PIN-verified — let the real submit through
                if (form.dataset.marPinVerified === '1') return;

                e.preventDefault();
                pendingForm = form;

                var m = getModal();
                if (!m) {
                    // Bootstrap not yet loaded — allow submit without PIN
                    form.dataset.marPinVerified = '1';
                    form.submit();
                    return;
                }

                document.getElementById('marPinInput').value = '';
                document.getElementById('marPinError').style.display = 'none';
                m.show();
                modalEl.addEventListener('shown.bs.modal', function focusPin() {
                    document.getElementById('marPinInput').focus();
                    modalEl.removeEventListener('shown.bs.modal', focusPin);
                });
            });

            // Verify button click
            document.getElementById('marPinVerifyBtn').addEventListener('click', function () {
                verifyPin();
            });

            // Enter key in password field
            document.getElementById('marPinInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); verifyPin(); }
            });

            // Cancel — just dismiss; form is not submitted
            document.getElementById('marPinCancelBtn').addEventListener('click', function () {
                getModal().hide();
                pendingForm = null;
            });

            function verifyPin() {
                var pin = document.getElementById('marPinInput').value;
                if (!pin) return;

                var btn = document.getElementById('marPinVerifyBtn');
                var origText = btn.innerHTML;
                btn.disabled = true;
                btn.textContent = '...';

                var csrf       = document.getElementById('marPinCsrf').value;
                var facilityId = document.getElementById('marPinFacilityId').value;
                var episodeId  = document.getElementById('marPinEpisodeId').value;

                var fd = new FormData();
                fd.append('action',           'verify_pin');
                fd.append('pin',              pin);
                fd.append('csrf_token_form',  csrf);
                fd.append('facility_id',      facilityId);
                fd.append('episode_id',       episodeId);

                var url = 'mar.php?facility_id=' + encodeURIComponent(facilityId)
                        + '&episode_id=' + encodeURIComponent(episodeId);

                fetch(url, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (json) {
                        if (json.ok) {
                            getModal().hide();
                            pendingForm.dataset.marPinVerified = '1';
                            pendingForm.submit();
                        } else {
                            document.getElementById('marPinError').style.display = '';
                            document.getElementById('marPinInput').value = '';
                            document.getElementById('marPinInput').focus();
                        }
                    })
                    .catch(function () {
                        document.getElementById('marPinError').textContent =
                            'Network error — please retry.';
                        document.getElementById('marPinError').style.display = '';
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.innerHTML = origText;
                    });
            }
        })();
    </script>
</body>
</html>













































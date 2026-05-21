<?php

/**
 * public/hbc/visit.php
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
 * public/hbc/visit.php — Mobile Visit Workspace
 *
 * Phone/tablet-friendly field encounter page.
 * Supports:
 *   - server draft load + autosave draft JSON
 *   - localStorage fallback
 *   - silent GPS capture
 *   - signature canvas
 *   - structured completion for COMPLETE / REFUSED / MISSED
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Controller\HbcVisitController;

if (!$manifest->featureEnabled('hbc_visit')) {
    oei_exit_with_alert(xlt('Visit workspace is not enabled.'), 'info');
}

$visitId    = (int)($_GET['visit_id'] ?? $_POST['visit_id'] ?? 0);
$episodeId  = (int)($_GET['episode_id'] ?? 0);
$pid        = (int)($_GET['pid'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($visitId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new HbcVisitController();
$data = $controller->handleWorkspace($visitId, $userId);
if ($data === null) {
    exit;
}

$visit = $data['visit'];
$draft = $data['draft'];
if (!$visit) {
    oei_exit_with_alert(xlt('Visit not found.'), 'danger');
}

if ($episodeId === 0) {
    $episodeId = (int)($visit['episode_id'] ?? 0);
}
if ($pid === 0) {
    $pid = (int)($visit['pid'] ?? 0);
}

$isFinal = HbcVisitStatus::isFinal((string)($visit['status'] ?? ''));
$_csrf   = CsrfUtils::collectCsrfToken();
$pageTitle = xlt('Visit Workspace');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';
$q = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
$profileUrl = $_hbcBase . 'profile.php' . $q;
$visitUrl   = $_hbcBase . 'visit.php?visit_id=' . $visitId . '&episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;

$noteValue    = (string)($draft['visit_note'] ?? $visit['visit_note'] ?? '');
$outcomeValue = (string)($draft['outcome_summary'] ?? $visit['outcome_summary'] ?? '');
$mileageValue = (string)($draft['mileage_miles'] ?? $visit['mileage_miles'] ?? '');
$completionStatusValue = (string)($draft['completion_status'] ?? ($visit['status'] ?? HbcVisitStatus::COMPLETE));
if (!in_array($completionStatusValue, [HbcVisitStatus::COMPLETE, HbcVisitStatus::REFUSED, HbcVisitStatus::MISSED], true)) {
    $completionStatusValue = HbcVisitStatus::COMPLETE;
}
$medRecValue = (string)($draft['med_reconciliation_status'] ?? $visit['med_reconciliation_status'] ?? 'NOT_DONE');
if (!in_array($medRecValue, ['NOT_DONE','NO_CHANGES','UPDATED','ISSUES_FOUND'], true)) {
    $medRecValue = 'NOT_DONE';
}
$medRecSummaryValue = (string)($draft['med_reconciliation_summary'] ?? $visit['med_reconciliation_summary'] ?? '');
$woundSummaryValue = (string)($draft['wound_summary'] ?? $visit['wound_summary'] ?? '');
$procedureSummaryValue = (string)($draft['procedure_summary'] ?? $visit['procedure_summary'] ?? '');
$homeSafetySummaryValue = (string)($draft['home_safety_summary'] ?? $visit['home_safety_summary'] ?? '');
$careCoordNeededValue = !empty($draft) ? !empty($draft['care_coordination_needed']) : !empty($visit['care_coordination_needed']);
$careCoordSummaryValue = (string)($draft['care_coordination_summary'] ?? $visit['care_coordination_summary'] ?? '');
$followupPlanValue = (string)($draft['followup_plan'] ?? $visit['followup_plan'] ?? '');
$nextVisitDueValue = (string)($draft['next_visit_due_date'] ?? $visit['next_visit_due_date'] ?? '');
$nextVisitTypeValue = (string)($draft['next_visit_type'] ?? $visit['next_visit_type'] ?? '');
if ($nextVisitTypeValue !== '' && !in_array($nextVisitTypeValue, HbcVisitType::all(), true)) {
    $nextVisitTypeValue = '';
}

// Inline vitals — draft-only (not persisted on oei_hbc_visit, written to oei_triage on finalise)
$vitalsBpSysValue  = (string)($draft['vitals_bp_systolic'] ?? '');
$vitalsBpDiaValue  = (string)($draft['vitals_bp_diastolic'] ?? '');
$vitalsHrValue     = (string)($draft['vitals_hr'] ?? '');
$vitalsRrValue     = (string)($draft['vitals_rr'] ?? '');
$vitalsSpo2Value   = (string)($draft['vitals_spo2'] ?? '');
$vitalsTempValue   = (string)($draft['vitals_temp_f'] ?? '');
$vitalsWtValue     = (string)($draft['vitals_weight_kg'] ?? '');
$vitalsPainValue   = (string)($draft['vitals_pain_score'] ?? '');

$windowLabel = '';
if (!empty($visit['window_start_datetime']) || !empty($visit['window_end_datetime'])) {
    $parts = [];
    if (!empty($visit['window_start_datetime'])) {
        $parts[] = (new DateTime((string)$visit['window_start_datetime']))->format('H:i');
    }
    if (!empty($visit['window_end_datetime'])) {
        $parts[] = (new DateTime((string)$visit['window_end_datetime']))->format('H:i');
    }
    $windowLabel = implode(' – ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <meta name="oei-csrf" content="<?= htmlspecialchars($_csrf) ?>">
  <style>
    body { font-size:.9rem; }
    .visit-header { background:linear-gradient(135deg,#2c5f4a,#4a7c59); color:#fff; border-radius:.5rem; }
    .field-section { border-left:3px solid #4a7c59; padding-left:.75rem; }
    .autosave-indicator { font-size:.75rem; color:#6c757d; transition:color .3s; }
    .autosave-indicator.saving { color:#0d6efd; }
    .autosave-indicator.saved { color:#198754; }
    .autosave-indicator.error { color:#dc3545; }
    #signatureCanvas {
      border:2px dashed #adb5bd; border-radius:.375rem; cursor:crosshair; touch-action:none;
      background:#fff; display:block; width:100%; height:160px;
    }
    #signatureCanvas.signed { border-color:#198754; border-style:solid; }
    #oei-offline-banner {
      display:none; position:sticky; top:0; z-index:1030;
      background:#856404; color:#fff3cd;
      padding:.5rem 1rem; font-size:.85rem; text-align:center;
      border-bottom:1px solid #997404;
    }
    #oei-offline-banner.show { display:block; }
    #oei-queued-badge {
      display:none; background:#0d6efd; color:#fff;
      border-radius:999px; font-size:.72rem; padding:.2rem .55rem;
      vertical-align:middle; margin-left:.4rem;
    }
    #oei-queued-badge.show { display:inline; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div id="oei-offline-banner" role="alert">
  📵 <?= xlt('Offline — visit will be saved locally and synced when connectivity returns.') ?>
  <span id="oei-queued-badge"></span>
</div>
<div class="container py-3" style="max-width:760px;">

<div class="visit-header p-3 mb-3">
  <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
    <div>
      <div class="fw-bold fs-5">
        <?= htmlspecialchars(trim((string)($visit['patient_fname'] ?? '') . ' ' . (string)($visit['patient_lname'] ?? ''))) ?>
      </div>
      <div class="text-white-50 small mt-1">
        <span class="badge <?= HbcVisitType::badge((string)$visit['visit_type']) ?> me-1">
          <?= htmlspecialchars(HbcVisitType::label((string)$visit['visit_type'])) ?>
        </span>
        <?php if (!empty($visit['scheduled_datetime'])): ?>
          <?= htmlspecialchars((new DateTime((string)$visit['scheduled_datetime']))->format('D d M Y H:i')) ?>
        <?php endif; ?>
        <?php if (!empty($visit['route_sequence'])): ?>
          · #<?= (int)$visit['route_sequence'] ?>
        <?php endif; ?>
        <?php if ($windowLabel !== ''): ?>
          · <?= xlt('Window') ?> <?= htmlspecialchars($windowLabel) ?>
        <?php endif; ?>
      </div>
      <?php if (!empty($visit['service_address_line1']) || !empty($visit['service_city'])): ?>
      <div class="text-white-50 small mt-1">
        📍 <?= htmlspecialchars(implode(', ', array_filter([(string)($visit['service_address_line1'] ?? ''), (string)($visit['service_city'] ?? ''), (string)($visit['service_state_province'] ?? '')]))) ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($visit['travel_notes']) || !empty($visit['access_notes'])): ?>
      <div class="text-white-50 small mt-1">
        🚗 <?= htmlspecialchars(implode(' · ', array_filter([(string)($visit['travel_notes'] ?? ''), (string)($visit['access_notes'] ?? '')]))) ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="text-end">
      <span class="badge <?= HbcVisitStatus::badge((string)$visit['status']) ?> fs-6">
        <?= htmlspecialchars(HbcVisitStatus::label((string)$visit['status'])) ?>
      </span>
      <?php if (!empty($visit['is_draft'])): ?>
        <div class="badge bg-warning text-dark mt-1">DRAFT</div>
      <?php endif; ?>
      <div class="mt-2">
        <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn-sm btn-outline-light">← <?= xlt('Profile') ?></a>
      </div>
    </div>
  </div>
</div>

<?php if ($isFinal): ?>
<div class="alert alert-success py-2">
  ✓ <?= xlt('This visit is finalized.') ?>
</div>
<div class="card mb-3">
  <div class="card-header small fw-semibold"><?= xlt('Visit Summary') ?></div>
  <div class="card-body small">
    <?php if (!empty($visit['visit_note'])): ?>
      <div class="mb-2"><strong><?= xlt('Visit Note') ?>:</strong><br><?= nl2br(htmlspecialchars((string)$visit['visit_note'])) ?></div>
    <?php endif; ?>
    <?php if (!empty($visit['outcome_summary'])): ?>
      <div class="mb-2"><strong><?= xlt('Outcome') ?>:</strong> <?= htmlspecialchars((string)$visit['outcome_summary']) ?></div>
    <?php endif; ?>
    <?php if (!empty($visit['med_reconciliation_summary']) || !empty($visit['wound_summary']) || !empty($visit['procedure_summary']) || !empty($visit['home_safety_summary']) || !empty($visit['care_coordination_summary']) || !empty($visit['followup_plan'])): ?>
      <div class="row g-2">
        <?php foreach ([
          xlt('Medication Reconciliation') => (string)($visit['med_reconciliation_summary'] ?? ''),
          xlt('Wound Summary') => (string)($visit['wound_summary'] ?? ''),
          xlt('Procedure / Intervention') => (string)($visit['procedure_summary'] ?? ''),
          xlt('Home Safety') => (string)($visit['home_safety_summary'] ?? ''),
          xlt('Care Coordination') => (string)($visit['care_coordination_summary'] ?? ''),
          xlt('Follow-up Plan') => (string)($visit['followup_plan'] ?? ''),
        ] as $label => $value): if ($value === '') continue; ?>
        <div class="col-12">
          <strong><?= htmlspecialchars($label) ?>:</strong><br><?= nl2br(htmlspecialchars($value)) ?>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($visit['next_visit_due_date']) || !empty($visit['next_visit_type'])): ?>
    <div class="mt-2"><strong><?= xlt('Recommended Next Visit') ?>:</strong>
      <?= htmlspecialchars(trim((string)($visit['next_visit_due_date'] ?? '') . ' ' . (string)($visit['next_visit_type'] ?? ''))) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<form id="visitForm" method="POST">
  <input type="hidden" name="csrf_token_form" id="csrfToken" value="<?= htmlspecialchars($_csrf) ?>">
  <input type="hidden" name="visit_id" value="<?= $visitId ?>">
  <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
  <input type="hidden" name="action" id="formAction" value="save_draft">
  <input type="hidden" name="signature_data" id="signatureData" value="">

  <div class="d-flex justify-content-between align-items-center mb-2">
    <small class="autosave-indicator" id="autosaveStatus"><?= !empty($visit['is_draft']) ? xlt('Draft loaded.') : xlt('New visit.') ?></small>
    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="saveDraft()">💾 <?= xlt('Save Draft') ?></button>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Completion Status') ?></label>
    <select name="completion_status" id="completionStatus" class="form-select">
      <?php foreach ([HbcVisitStatus::COMPLETE => xlt('Complete'), HbcVisitStatus::REFUSED => xlt('Refused'), HbcVisitStatus::MISSED => xlt('Missed')] as $value => $label): ?>
      <option value="<?= htmlspecialchars($value) ?>" <?= $completionStatusValue === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Visit Note') ?></label>
    <textarea name="visit_note" id="visitNote" class="form-control" rows="6" placeholder="<?= xla('Assessment findings, interventions, patient response, teaching performed…') ?>"><?= htmlspecialchars($noteValue) ?></textarea>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Outcome Summary') ?></label>
    <input type="text" name="outcome_summary" id="outcomeSummary" class="form-control" value="<?= htmlspecialchars($outcomeValue) ?>" placeholder="<?= xla('Stable, refused med teaching, next visit needed in 2 days…') ?>">
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4 field-section">
      <label class="form-label fw-semibold"><?= xlt('Mileage') ?></label>
      <div class="input-group">
        <input type="number" name="mileage_miles" id="mileageField" class="form-control" step="0.1" min="0" max="500" value="<?= htmlspecialchars($mileageValue) ?>">
        <span class="input-group-text"><?= xlt('mi') ?></span>
      </div>
    </div>
    <div class="col-md-8 field-section">
      <label class="form-label fw-semibold"><?= xlt('Medication Reconciliation') ?></label>
      <select name="med_reconciliation_status" id="medRecStatus" class="form-select mb-2">
        <?php foreach (['NOT_DONE' => xlt('Not done'), 'NO_CHANGES' => xlt('No changes'), 'UPDATED' => xlt('Updated'), 'ISSUES_FOUND' => xlt('Issues found')] as $value => $label): ?>
        <option value="<?= htmlspecialchars($value) ?>" <?= $medRecValue === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
      </select>
      <textarea name="med_reconciliation_summary" id="medRecSummary" class="form-control" rows="2" placeholder="<?= xla('Changes made, discrepancies found, issues to review…') ?>"><?= htmlspecialchars($medRecSummaryValue) ?></textarea>
    </div>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Wound Summary') ?></label>
    <textarea name="wound_summary" id="woundSummary" class="form-control" rows="2" placeholder="<?= xla('Location, size, drainage, dressing change…') ?>"><?= htmlspecialchars($woundSummaryValue) ?></textarea>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Procedure / Intervention Summary') ?></label>
    <textarea name="procedure_summary" id="procedureSummary" class="form-control" rows="2" placeholder="<?= xla('Neb treatment, injection, line care, teaching, etc…') ?>"><?= htmlspecialchars($procedureSummaryValue) ?></textarea>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Home Safety Summary') ?></label>
    <textarea name="home_safety_summary" id="homeSafetySummary" class="form-control" rows="2" placeholder="<?= xla('Trip hazards, equipment issues, caregiver concerns, access issues…') ?>"><?= htmlspecialchars($homeSafetySummaryValue) ?></textarea>
  </div>

  <div class="field-section mb-3">
    <div class="form-check mb-2">
      <input type="checkbox" class="form-check-input" name="care_coordination_needed" id="careCoordNeeded" value="1" <?= $careCoordNeededValue ? 'checked' : '' ?>>
      <label class="form-check-label fw-semibold" for="careCoordNeeded"><?= xlt('Care coordination follow-up needed') ?></label>
    </div>
    <textarea name="care_coordination_summary" id="careCoordSummary" class="form-control" rows="2" placeholder="<?= xla('PCP update, referral, DME, pharmacy, family call, outside coordination…') ?>"><?= htmlspecialchars($careCoordSummaryValue) ?></textarea>
  </div>

  <div class="field-section mb-3">
    <label class="form-label fw-semibold"><?= xlt('Follow-up Plan') ?></label>
    <textarea name="followup_plan" id="followupPlan" class="form-control" rows="2" placeholder="<?= xla('What should happen after this visit?') ?>"><?= htmlspecialchars($followupPlanValue) ?></textarea>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-6 field-section">
      <label class="form-label fw-semibold"><?= xlt('Recommended Next Visit Date') ?></label>
      <input type="date" name="next_visit_due_date" id="nextVisitDueDate" class="form-control" value="<?= htmlspecialchars($nextVisitDueValue) ?>">
    </div>
    <div class="col-md-6 field-section">
      <label class="form-label fw-semibold"><?= xlt('Recommended Next Visit Type') ?></label>
      <select name="next_visit_type" id="nextVisitType" class="form-select">
        <option value=""><?= xlt('Not specified') ?></option>
        <?php foreach (HbcVisitType::all() as $type): ?>
        <option value="<?= htmlspecialchars($type) ?>" <?= $nextVisitTypeValue === $type ? 'selected' : '' ?>><?= htmlspecialchars(HbcVisitType::label($type)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- ── Inline Vitals (collapsible) ──────────────────────────────────── -->
  <div class="card mb-3 border-success">
    <div class="card-header py-2 d-flex justify-content-between align-items-center"
         style="cursor:pointer; background:linear-gradient(135deg,#e8f5e9,#c8e6c9);"
         data-bs-toggle="collapse" data-bs-target="#vitalsCollapse">
      <span class="fw-semibold">ð <?= xlt('Vitals (optional)') ?></span>
      <span class="badge bg-success" id="vitalsFilledBadge" style="display:none;">â</span>
    </div>
    <div class="collapse" id="vitalsCollapse">
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6 col-md-3">
            <label class="form-label form-label-sm"><?= xlt('BP Sys / Dia') ?></label>
            <div class="input-group input-group-sm">
              <input type="number" name="vitals_bp_systolic" id="vitalsBpSys" class="form-control" min="60" max="260" placeholder="120" value="<?= htmlspecialchars($vitalsBpSysValue) ?>">
              <span class="input-group-text">/</span>
              <input type="number" name="vitals_bp_diastolic" id="vitalsBpDia" class="form-control" min="30" max="160" placeholder="80" value="<?= htmlspecialchars($vitalsBpDiaValue) ?>">
            </div>
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('HR') ?></label>
            <input type="number" name="vitals_hr" id="vitalsHr" class="form-control form-control-sm" min="20" max="250" value="<?= htmlspecialchars($vitalsHrValue) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('SpOâ%') ?></label>
            <input type="number" name="vitals_spo2" id="vitalsSpo2" class="form-control form-control-sm" min="50" max="100" value="<?= htmlspecialchars($vitalsSpo2Value) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('RR') ?></label>
            <input type="number" name="vitals_rr" id="vitalsRr" class="form-control form-control-sm" min="4" max="60" value="<?= htmlspecialchars($vitalsRrValue) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('Temp Â°F') ?></label>
            <input type="number" step="0.1" name="vitals_temp_f" id="vitalsTempF" class="form-control form-control-sm" min="90" max="110" value="<?= htmlspecialchars($vitalsTempValue) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('Wt kg') ?></label>
            <input type="number" step="0.1" name="vitals_weight_kg" id="vitalsWtKg" class="form-control form-control-sm" min="10" max="300" value="<?= htmlspecialchars($vitalsWtValue) ?>">
          </div>
          <div class="col-4 col-md-2">
            <label class="form-label form-label-sm"><?= xlt('Pain 0â10') ?></label>
            <input type="number" name="vitals_pain_score" id="vitalsPain" class="form-control form-control-sm" min="0" max="10" value="<?= htmlspecialchars($vitalsPainValue) ?>">
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="field-section mb-4">
    <label class="form-label fw-semibold">âï¸ <?= xlt('Patient / Caregiver Signature') ?> <span class="text-muted fw-normal small">(<?= xlt('optional') ?>)</span></label>
    <canvas id="signatureCanvas"></canvas>
    <div class="d-flex gap-2 mt-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearSignature()">✕ <?= xlt('Clear') ?></button>
      <small class="text-muted align-self-center" id="sigStatus"><?= !empty($visit['patient_signature_obtained']) ? '<span class="text-success">✓ ' . xlt('Previously signed') . '</span>' : xlt('Draw signature above') ?></small>
    </div>
  </div>

  <div class="d-grid gap-2">
    <button type="button" class="btn btn-success" onclick="finaliseVisit()">✅ <?= xlt('Finalize Visit') ?></button>
    <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn-outline-secondary"><?= xlt('Back to Profile (keep draft)') ?></a>
  </div>
</form>
<?php endif; ?>

</div>
<script>
(function () {
    'use strict';
    const VISIT_ID   = <?= $visitId ?>;
    const FACILITY_ID= <?= $facilityId ?>;
    const STORAGE_KEY= 'hbc_visit_draft_' + VISIT_ID;
    const VISIT_URL  = <?= json_encode($visitUrl) ?>;
    const BOARD_URL  = <?= json_encode($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>;
    const PROFILE_URL= <?= json_encode($profileUrl . '&flash=visit_complete') ?>;
    const AUTOSAVE_MS= 30000;

    function byId(id) { return document.getElementById(id); }

    // ── Field accessors ──────────────────────────────────────────────────────
    function getFormFields() {
        return {
            visit_note: byId('visitNote')?.value ?? '',
            outcome_summary: byId('outcomeSummary')?.value ?? '',
            mileage_miles: byId('mileageField')?.value ?? '',
            completion_status: byId('completionStatus')?.value ?? 'COMPLETE',
            med_reconciliation_status: byId('medRecStatus')?.value ?? 'NOT_DONE',
            med_reconciliation_summary: byId('medRecSummary')?.value ?? '',
            wound_summary: byId('woundSummary')?.value ?? '',
            procedure_summary: byId('procedureSummary')?.value ?? '',
            home_safety_summary: byId('homeSafetySummary')?.value ?? '',
            care_coordination_needed: byId('careCoordNeeded')?.checked ? 1 : 0,
            care_coordination_summary: byId('careCoordSummary')?.value ?? '',
            followup_plan: byId('followupPlan')?.value ?? '',
            next_visit_due_date: byId('nextVisitDueDate')?.value ?? '',
            next_visit_type: byId('nextVisitType')?.value ?? '',
            vitals_bp_systolic: byId('vitalsBpSys')?.value ?? '',
            vitals_bp_diastolic: byId('vitalsBpDia')?.value ?? '',
            vitals_hr: byId('vitalsHr')?.value ?? '',
            vitals_rr: byId('vitalsRr')?.value ?? '',
            vitals_spo2: byId('vitalsSpo2')?.value ?? '',
            vitals_temp_f: byId('vitalsTempF')?.value ?? '',
            vitals_weight_kg: byId('vitalsWtKg')?.value ?? '',
            vitals_pain_score: byId('vitalsPain')?.value ?? '',
        };
    }

    // ── localStorage backup ──────────────────────────────────────────────────
    function saveToLocalStorage() {
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(getFormFields())); } catch (e) {}
    }
    function loadFromLocalStorage() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return;
            const d = JSON.parse(stored);
            if ((byId('visitNote')?.value ?? '') !== '') return;
            const map = {
                visit_note: 'visitNote', outcome_summary: 'outcomeSummary', mileage_miles: 'mileageField',
                completion_status: 'completionStatus', med_reconciliation_status: 'medRecStatus',
                med_reconciliation_summary: 'medRecSummary', wound_summary: 'woundSummary',
                procedure_summary: 'procedureSummary', home_safety_summary: 'homeSafetySummary',
                care_coordination_summary: 'careCoordSummary', followup_plan: 'followupPlan',
                next_visit_due_date: 'nextVisitDueDate', next_visit_type: 'nextVisitType',
                vitals_bp_systolic: 'vitalsBpSys', vitals_bp_diastolic: 'vitalsBpDia',
                vitals_hr: 'vitalsHr', vitals_rr: 'vitalsRr', vitals_spo2: 'vitalsSpo2',
                vitals_temp_f: 'vitalsTempF', vitals_weight_kg: 'vitalsWtKg', vitals_pain_score: 'vitalsPain'
            };
            Object.entries(map).forEach(([key, id]) => {
                if (d[key] !== undefined && byId(id)) byId(id).value = d[key];
            });
            if (byId('careCoordNeeded')) byId('careCoordNeeded').checked = !!d.care_coordination_needed;
        } catch (e) {}
    }
    ['visitNote','outcomeSummary','mileageField','completionStatus','medRecStatus','medRecSummary',
     'woundSummary','procedureSummary','homeSafetySummary','careCoordNeeded','careCoordSummary',
     'followupPlan','nextVisitDueDate','nextVisitType',
     'vitalsBpSys','vitalsBpDia','vitalsHr','vitalsRr','vitalsSpo2','vitalsTempF','vitalsWtKg','vitalsPain'].forEach(id => {
        const el = byId(id);
        if (el) { el.addEventListener('input', saveToLocalStorage); el.addEventListener('change', saveToLocalStorage); }
    });

    // ── IndexedDB helpers ────────────────────────────────────────────────────
    let _idb = null;
    function getIdb() {
        if (_idb) return Promise.resolve(_idb);
        return new Promise((res, rej) => {
            const req = indexedDB.open('oei-downtime', 2);
            req.onupgradeneeded = (e) => {
                const db = e.target.result;
                if (!db.objectStoreNames.contains('pendingQueue')) {
                    db.createObjectStore('pendingQueue', {keyPath: 'idb_id', autoIncrement: true});
                }
                if (!db.objectStoreNames.contains('meta')) {
                    db.createObjectStore('meta');
                }
                if (!db.objectStoreNames.contains('hbcVisitQueue')) {
                    db.createObjectStore('hbcVisitQueue', {keyPath: 'idb_id', autoIncrement: true});
                }
            };
            req.onsuccess  = (e) => { _idb = e.target.result; res(_idb); };
            req.onerror    = (e) => rej(e.target.error);
        });
    }
    async function queueHbcVisit(entryType, fields) {
        const db = await getIdb();
        const tx = db.transaction('hbcVisitQueue', 'readwrite');
        tx.objectStore('hbcVisitQueue').add({
            entry_type:      entryType,
            visit_id:        VISIT_ID,
            facility_id:     FACILITY_ID,
            fields:          fields,
            signature_data:  byId('signatureData')?.value || null,
            captured_client: new Date().toISOString(),
        });
        return new Promise((res, rej) => { tx.oncomplete = res; tx.onerror = () => rej(tx.error); });
    }
    async function countHbcQueue() {
        const db = await getIdb();
        return new Promise((res) => {
            const tx = db.transaction('hbcVisitQueue','readonly');
            const req = tx.objectStore('hbcVisitQueue').count();
            req.onsuccess = () => res(req.result ?? 0);
            req.onerror   = () => res(0);
        });
    }
    async function updateQueueBadge() {
        const n = await countHbcQueue();
        const badge = byId('oei-queued-badge');
        if (!badge) return;
        if (n > 0) { badge.textContent = n + ' queued'; badge.classList.add('show'); }
        else        { badge.classList.remove('show'); }
    }
    async function triggerBackgroundSync() {
        if (!('serviceWorker' in navigator)) return;
        const reg = await navigator.serviceWorker.ready;
        if ('sync' in reg) { reg.sync.register('oei-hbc-sync').catch(() => {}); }
    }
    function storeCsrfInIdb() {
        const token = byId('csrfToken')?.value || document.querySelector('meta[name="oei-csrf"]')?.content || '';
        if (!token) return;
        getIdb().then(db => {
            try {
                const tx = db.transaction('meta','readwrite');
                tx.objectStore('meta').put(token, 'csrf_token');
            } catch (_) {}
        });
    }

    // ── GPS capture ──────────────────────────────────────────────────────────
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function (pos) {
            const fd = new FormData();
            fd.append('action', 'record_gps');
            fd.append('csrf_token_form', byId('csrfToken').value);
            fd.append('visit_id', VISIT_ID);
            fd.append('lat', pos.coords.latitude);
            fd.append('lng', pos.coords.longitude);
            fetch(BOARD_URL, {method: 'POST', body: fd}).catch(() => {});
        }, function () {}, {timeout: 8000});
    }

    // ── Signature canvas ─────────────────────────────────────────────────────
    const canvas = byId('signatureCanvas');
    const sigData = byId('signatureData');
    const sigStat = byId('sigStatus');
    let signing = false, hasSig = false;
    if (canvas) {
        const ctx = canvas.getContext('2d');
        ctx.strokeStyle = '#1a1a1a'; ctx.lineWidth = 2; ctx.lineCap = 'round';
        function getPos(e) {
            const r = canvas.getBoundingClientRect();
            const t = e.touches ? e.touches[0] : e;
            return {x: (t.clientX - r.left) * (canvas.width / r.width),
                    y: (t.clientY - r.top)  * (canvas.height / r.height)};
        }
        function onSign() {
            if (!hasSig) return;
            canvas.classList.add('signed');
            sigData.value = canvas.toDataURL('image/png');
            if (sigStat) sigStat.innerHTML = '<span class="text-success">✓ <?= xlt('Signature captured') ?></span>';
        }
        function resizeCanvas() {
            const w = canvas.offsetWidth, h = 160;
            if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
        }
        canvas.addEventListener('mousedown',  e => { signing=true; ctx.beginPath(); const p=getPos(e); ctx.moveTo(p.x,p.y); });
        canvas.addEventListener('mousemove',  e => { if(!signing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); hasSig=true; });
        canvas.addEventListener('mouseup',    () => { signing=false; onSign(); });
        canvas.addEventListener('touchstart', e => { e.preventDefault(); signing=true; ctx.beginPath(); const p=getPos(e); ctx.moveTo(p.x,p.y); }, {passive:false});
        canvas.addEventListener('touchmove',  e => { e.preventDefault(); if(!signing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.stroke(); hasSig=true; }, {passive:false});
        canvas.addEventListener('touchend',   () => { signing=false; onSign(); });
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
    }
    window.clearSignature = function () {
        if (!canvas) return;
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
        canvas.classList.remove('signed');
        sigData.value = ''; hasSig = false;
        if (sigStat) sigStat.textContent = '<?= xlt('Draw signature above') ?>';
    };

    // ── Autosave indicator ───────────────────────────────────────────────────
    const statusEl = byId('autosaveStatus');
    function setStatus(cls, msg) {
        if (!statusEl) return;
        statusEl.className = 'autosave-indicator ' + cls;
        statusEl.textContent = msg;
    }

    // ── Online/offline banner ────────────────────────────────────────────────
    const offlineBanner = byId('oei-offline-banner');
    function setOfflineUi(offline) {
        if (offlineBanner) offlineBanner.classList.toggle('show', offline);
    }
    if (!navigator.onLine) setOfflineUi(true);
    window.addEventListener('offline', () => setOfflineUi(true));
    window.addEventListener('online',  () => {
        setOfflineUi(false);
        triggerBackgroundSync();
        updateQueueBadge();
    });
    // SW message forwarded as DOM event by _bootstrap.php
    window.addEventListener('oei:offline', () => setOfflineUi(true));
    window.addEventListener('oei:online',  () => { setOfflineUi(false); triggerBackgroundSync(); });
    window.addEventListener('oei:hbc-sync-complete', (ev) => {
        updateQueueBadge();
        if (ev.detail?.synced > 0) {
            setStatus('saved', '<?= xlt('Synced') ?> (' + ev.detail.synced + ')');
            // If any FINALISE was synced, redirect to profile
            const finalised = (ev.detail?.results ?? []).some(r => r.type === 'FINALISE' && r.ok);
            if (finalised) {
                try { localStorage.removeItem(STORAGE_KEY); } catch(_) {}
                window.location.href = PROFILE_URL;
            }
        }
    });

    // ── Save draft ───────────────────────────────────────────────────────────
    window.saveDraft = async function () {
        setStatus('saving', '<?= xlt('Saving…') ?>');
        saveToLocalStorage();
        const fd = new FormData(byId('visitForm'));
        fd.set('action', 'save_draft');
        try {
            const r = await fetch(VISIT_URL, {method:'POST', body:fd});
            const j = await r.json();
            setStatus(j.ok ? 'saved' : 'error', j.ok ? '<?= xlt('Draft saved') ?>' : '<?= xlt('Save failed') ?>');
        } catch (_) {
            // Offline — persist to IDB
            await queueHbcVisit('DRAFT', getFormFields()).catch(() => {});
            await triggerBackgroundSync();
            await updateQueueBadge();
            setStatus('error', '<?= xlt('Offline — queued locally') ?>');
        }
    };
    let autoTimer = setInterval(window.saveDraft, AUTOSAVE_MS);

    // ── Finalise ─────────────────────────────────────────────────────────────
    window.finaliseVisit = async function () {
        if (!confirm('<?= xlt('Finalize this visit?') ?>')) return;
        clearInterval(autoTimer);
        const fd = new FormData(byId('visitForm'));
        fd.set('action', 'finalise');
        try {
            const r = await fetch(VISIT_URL, {method:'POST', body:fd});
            const j = await r.json();
            if (j.ok) {
                try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
                window.location.href = PROFILE_URL;
            } else {
                alert('<?= xlt('Error finalizing visit. Please save draft and retry.') ?>');
                autoTimer = setInterval(window.saveDraft, AUTOSAVE_MS);
            }
        } catch (_) {
            // Offline — queue the finalise for background sync
            try {
                await queueHbcVisit('FINALISE', getFormFields());
                await triggerBackgroundSync();
                await updateQueueBadge();
                setStatus('error', '<?= xlt('Offline — finalise queued, will sync automatically') ?>');
                setOfflineUi(true);
                // Replace finalise button with queued state
                const btn = document.querySelector('button[onclick="finaliseVisit()"]');
                if (btn) { btn.disabled=true; btn.textContent='⏳ <?= xlt('Queued for sync…') ?>'; }
            } catch (err) {
                alert('<?= xlt('Network error and offline queue failed. Please save draft manually.') ?>');
                autoTimer = setInterval(window.saveDraft, AUTOSAVE_MS);
            }
        }
    };

    // ── Vitals filled badge ─────────────────────────────────────────────
    function updateVitalsBadge() {
        const ids = ['vitalsBpSys','vitalsBpDia','vitalsHr','vitalsRr','vitalsSpo2','vitalsTempF','vitalsWtKg','vitalsPain'];
        const any = ids.some(id => (byId(id)?.value ?? '') !== '');
        const badge = byId('vitalsFilledBadge');
        if (badge) badge.style.display = any ? 'inline' : 'none';
        // Auto-expand if any value loaded from draft
        if (any) {
            const col = document.getElementById('vitalsCollapse');
            if (col && !col.classList.contains('show')) col.classList.add('show');
        }
    }
    ['vitalsBpSys','vitalsBpDia','vitalsHr','vitalsRr','vitalsSpo2','vitalsTempF','vitalsWtKg','vitalsPain'].forEach(id => {
        const el = byId(id);
        if (el) { el.addEventListener('input', updateVitalsBadge); }
    });

    // ── Init ─────────────────────────────────────────────────────────────────
    loadFromLocalStorage();
    storeCsrfInIdb();
    updateQueueBadge();
    updateVitalsBadge();
})();
</script>
</body>
</html>













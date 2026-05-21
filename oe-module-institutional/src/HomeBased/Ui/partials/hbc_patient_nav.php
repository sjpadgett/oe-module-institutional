<?php

/**
 * src/HomeBased/Ui/partials/hbc_patient_nav.php
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
 * hbc_patient_nav.php — Shared patient context strip for all HBC sub-pages.
 *
 * Requires in scope (set before include):
 *   $episodeId    int
 *   $facilityId   int
 *   $manifest     Manifest|ContextManifest
 *   $activePage   string — board|profile|vitals|care_plan|clinical_notes|care_team|
 *                          fall_risk|incident|mar|tasks|documents|discharge|ereferral
 *
 * Optionally pre-populated (avoids extra DB call):
 *   $hbcNavPatient array — keys: fname, lname, pid, referral_status, urgency,
 *                          service_city, service_state_province, primary_diagnosis
 *
 * Emits a Bootstrap 5 sticky-top nav strip with:
 *   - Patient name / location / diagnosis badge
 *   - Status + urgency badges
 *   - Tab row: Profile · Vitals · Care Plan · Clinical Notes · Care Team ·
 *              Fall Risk · Incidents · MAR · Tasks · Documents · Discharge
 *   - ← Visit Board button
 */

if (!isset($episodeId, $facilityId, $manifest, $activePage)) {
    return;
}

// ── Lightweight header fetch if not pre-loaded ─────────────────────────────
if (!isset($hbcNavPatient) && function_exists('sqlQuery') && $episodeId > 0) {
    $hbcNavPatient = sqlQuery(
        "SELECT pd.fname, pd.lname, pd.pid,
                hbc.referral_status,
                hbc.urgency,
                hbc.service_city,
                hbc.service_state_province,
                hbc.primary_diagnosis
         FROM   oei_episode e
         JOIN   oei_hbc_episode hbc ON hbc.episode_id = e.id
         JOIN   patient_data pd     ON pd.pid = e.pid
         WHERE  e.id = ? AND e.type = 'HBC'
         LIMIT  1",
        [$episodeId]
    ) ?: [];
}

$_np = $hbcNavPatient ?? [];
$_navName   = trim(($_np['fname'] ?? '') . ' ' . ($_np['lname'] ?? '')) ?: '—';
$_navCity   = (string)($_np['service_city'] ?? '');
$_navState  = (string)($_np['service_state_province'] ?? '');
$_navLoc    = implode(', ', array_filter([$_navCity, $_navState]));
$_navDx     = (string)($_np['primary_diagnosis'] ?? '');
$_navStatus = (string)($_np['referral_status'] ?? 'NEW');
$_navUrgency= (string)($_np['urgency'] ?? 'ROUTINE');

use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcReferralStatus;

$_statusBadge  = HbcReferralStatus::badge($_navStatus);
$_statusLabel  = HbcReferralStatus::label($_navStatus);
$_urgencyBadge = match ($_navUrgency) {
    'EMERGENT' => 'bg-danger',
    'URGENT'   => 'bg-warning text-dark',
    default    => 'bg-secondary',
};

// Base URL
$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';
$_pubBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

$_q = '?episode_id=' . $episodeId . '&pid=' . (int)($_np['pid'] ?? 0) . '&facility_id=' . $facilityId;

// Nav tabs definition
$_tabs = [
    ['page' => 'profile',         'label' => xlt('Profile'),        'feature' => 'hbc_profile',
     'url'  => $_hbcBase . 'profile.php'   . $_q],
    ['page' => 'edit_episode',    'label' => xlt('Edit'),            'feature' => 'hbc_profile',
     'url'  => $_hbcBase . 'edit_episode.php'  . $_q],
    ['page' => 'vitals',          'label' => xlt('Vitals'),         'feature' => 'hbc_vitals',
     'url'  => $_hbcBase . 'vitals.php'    . $_q],
    ['page' => 'care_plan',       'label' => xlt('Care Plan'),      'feature' => 'care_plan',
     'url'  => $_pubBase . 'shared/care_plan.php' . $_q],
    ['page' => 'clinical_notes',  'label' => xlt('Notes'),          'feature' => 'clinical_notes',
     'url'  => $_pubBase . 'shared/clinical_notes.php' . $_q],
    ['page' => 'care_team',       'label' => xlt('Care Team'),      'feature' => 'care_team',
     'url'  => $_pubBase . 'shared/care_team.php' . $_q],
    ['page' => 'fall_risk',       'label' => xlt('Fall Risk'),      'feature' => 'hbc_fall_risk',
     'url'  => $_hbcBase . 'fall_risk.php' . $_q],
    ['page' => 'incident',        'label' => xlt('Incidents'),      'feature' => 'al_incident',
     'url'  => $_hbcBase . 'incident.php'  . $_q],
    ['page' => 'mar',             'label' => xlt('MAR'),            'feature' => 'mar',
     'url'  => $_pubBase . 'mar.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
    ['page' => 'tasks',           'label' => xlt('Tasks'),          'feature' => 'tasks',
     'url'  => $_pubBase . 'tasks.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
    ['page' => 'documents',       'label' => xlt('Docs'),           'feature' => 'episode_documents',
     'url'  => $_pubBase . 'episode_documents.php' . $_q],
    ['page' => 'ereferral',       'label' => xlt('eReferral'),      'feature' => 'ereferral',
     'url'  => $_pubBase . 'ereferral.php?facility_id=' . $facilityId . '&episode_id=' . $episodeId],
    ['page' => 'handoff',         'label' => xlt('Handoff'),        'feature' => 'hbc_handoff',
     'url'  => $_hbcBase . 'handoff.php?facility_id=' . $facilityId],
    ['page' => 'comm_log',        'label' => xlt('Comm Log'),       'feature' => 'hbc_comm_log',
     'url'  => $_hbcBase . 'comm_log.php'  . $_q],
    ['page' => 'discharge',       'label' => xlt('Discharge'),      'feature' => 'hbc_discharge',
     'url'  => $_hbcBase . 'discharge.php' . $_q],
];
?>
<div class="sticky-top" style="z-index:100;">
  <!-- Patient identity strip -->
  <div class="d-flex align-items-center gap-2 px-3 py-2 no-print"
       style="background:linear-gradient(135deg,#2c5f4a,#4a7c59);color:#fff;font-size:.85rem;">
    <a href="<?= htmlspecialchars($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>"
       class="text-white text-decoration-none me-2 fw-semibold">← <?= xlt('Visit Board') ?></a>
    <span class="fw-bold fs-6"><?= htmlspecialchars($_navName) ?></span>
    <?php if ($_navLoc): ?>
      <span class="opacity-75">📍 <?= htmlspecialchars($_navLoc) ?></span>
    <?php endif; ?>
    <?php if ($_navDx): ?>
      <span class="opacity-75 d-none d-md-inline">· <?= htmlspecialchars($_navDx) ?></span>
    <?php endif; ?>
    <span class="badge <?= $_statusBadge ?> ms-auto"><?= htmlspecialchars($_statusLabel) ?></span>
    <?php if ($_navUrgency !== 'ROUTINE'): ?>
      <span class="badge <?= $_urgencyBadge ?>"><?= htmlspecialchars($_navUrgency) ?></span>
    <?php endif; ?>
  </div>
  <!-- Tab row -->
  <ul class="nav nav-tabs nav-tabs-sm flex-nowrap overflow-x-auto border-bottom-0 px-2 pt-1 no-print"
      style="background:#f8f9fa;white-space:nowrap;">
    <?php foreach ($_tabs as $_tab):
        if (!$manifest->featureEnabled($_tab['feature'])) { continue; }
        $_active = ($activePage === $_tab['page']) ? 'active' : '';
        ?>
    <li class="nav-item">
      <a class="nav-link py-1 px-2 <?= $_active ?>"
         href="<?= htmlspecialchars($_tab['url']) ?>"
         style="font-size:.8rem;"><?= $_tab['label'] ?></a>
    </li>
    <?php endforeach; ?>
  </ul>
</div>
















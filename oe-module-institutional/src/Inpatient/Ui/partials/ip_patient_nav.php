<?php

/**
 * src/Inpatient/Ui/partials/ip_patient_nav.php
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
 * ip_patient_nav.php — Shared patient context strip for all IP sub-pages.
 *
 * Requires in scope (set before include):
 *   $episodeId   int    — current IP episode
 *   $facilityId  int    — current facility
 *   $manifest    Manifest|ContextManifest
 *   $activePage  string — one of: profile|vitals|mar|care_plan|clinical_notes|documents|discharge
 *
 * Optionally pre-populated (avoids extra DB call):
 *   $ipNavPatient array — keys: fname, lname, pid, bed, unit, service,
 *                                admission_type, los_days, expected_los_days,
 *                                attending_name
 *
 * Emits a Bootstrap 5 sticky-top nav strip with:
 *   - Patient name / bed / unit / service badge / LOS counter
 *   - Tab row: Profile · Vitals · MAR · Care Plan · Clinical Notes · Documents · Discharge
 *   - ← Floor Board button
 */

use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;

if (!isset($episodeId, $facilityId, $manifest, $activePage)) {
    return; // silently skip if required vars absent
}

// ── Lightweight header fetch if not pre-loaded ─────────────────────────────
if (!isset($ipNavPatient) && function_exists('sqlQuery') && $episodeId > 0) {
    $ipNavPatient = sqlQuery(
        "SELECT pd.fname, pd.lname, pd.pid,
                COALESCE(ip.bed,            '')          AS bed,
                COALESCE(ip.unit,           '')          AS unit,
                COALESCE(ip.service,        'MED_SURG')  AS service,
                COALESCE(ip.admission_type, 'ELECTIVE')  AS admission_type,
                ip.expected_los_days,
                DATEDIFF(NOW(), e.start_datetime)        AS los_days,
                CONCAT(COALESCE(att.fname,''), ' ', COALESCE(att.lname,'')) AS attending_name
         FROM   oei_episode e
         INNER  JOIN patient_data pd   ON pd.pid  = e.pid
         LEFT   JOIN oei_ip_episode ip ON ip.episode_id = e.id
         LEFT   JOIN users att         ON att.id  = ip.attending_user_id
         WHERE  e.id = ? AND e.type = 'IP'
         LIMIT  1",
        [$episodeId]
    ) ?: [];
}

$navR          = $ipNavPatient ?? [];
$navFname      = (string)($navR['fname'] ?? '');
$navLname      = (string)($navR['lname'] ?? '');
$navName       = trim("$navFname $navLname") ?: '—';
$navBed        = (string)($navR['bed']        ?? '');
$navUnit       = (string)($navR['unit']       ?? '');
$navService    = (string)($navR['service']    ?? 'MED_SURG');
$navAdmType    = (string)($navR['admission_type'] ?? 'ELECTIVE');
$navPid        = (int)($navR['pid']           ?? 0);
$navLos        = (int)($navR['los_days']      ?? 0);
$navExpLos     = ($navR['expected_los_days'] !== null) ? (int)$navR['expected_los_days'] : null;
$navAttending  = trim((string)($navR['attending_name'] ?? ''));

// LOS colour
$navLosBadge = 'bg-success';
if ($navExpLos !== null) {
    if ($navLos > $navExpLos)           $navLosBadge = 'bg-danger';
    elseif ($navLos >= $navExpLos - 1)  $navLosBadge = 'bg-warning';
}

$q  = 'episode_id=' . $episodeId . '&pid=' . $navPid . '&facility_id=' . $facilityId;
$qf = 'facility_id=' . $facilityId;

// Tab definitions use absolute URLs via base variables inherited from including page.
// $_oei_ip_base  = .../public/ip/
// $_oei_pub_base = .../public/
$_nav_ip  = htmlspecialchars($_oei_ip_base  ?? '');
$_nav_pub = htmlspecialchars($_oei_pub_base ?? '');

// Tab definitions: [feature_key, label, icon, url, page_key]
$navTabs = [
    ['ip_profile',        xlt('Profile'),        '🏥', "{$_nav_ip}profile.php?{$q}",                                           'profile'],
    ['al_vitals',         xlt('Vitals'),          '🩺', "{$_nav_ip}vitals.php?{$q}",                                       'vitals'],
    ['mar',               xlt('Meds (MAR)'),      '💊', "{$_nav_pub}mar.php?facility_id={$facilityId}&episode_id={$episodeId}", 'mar'],
    ['care_plan',         xlt('Care Plan'),       '📋', "{$_nav_pub}shared/care_plan.php?{$q}",                                 'care_plan'],
    ['clinical_notes',    xlt('Clinical Notes'),  '📝', "{$_nav_pub}shared/clinical_notes.php?{$q}",                            'clinical_notes'],
    ['episode_documents', xlt('Documents'),       '📎', "{$_nav_pub}episode_documents.php?{$q}",                                'documents'],
    ['ip_discharge',      xlt('Discharge'),       '🚪', "{$_nav_ip}discharge.php?{$q}",                                         'discharge'],
];
?>
<nav class="oei-ip-nav sticky-top mb-3" aria-label="<?= xlt('Patient navigation') ?>">
  <div class="oei-ip-nav-header d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2">

    <!-- Patient identity -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= $_nav_ip ?>board.php?<?= $qf ?>"
         class="btn btn-sm oei-ip-back-btn"
         title="<?= xlt('Back to Floor Board') ?>">
        ← <?= xlt('Board') ?>
      </a>
      <span class="fw-semibold"><?= htmlspecialchars($navName) ?></span>
      <?php if ($navBed || $navUnit): ?>
        <span class="text-muted small">
            <?= htmlspecialchars($navUnit) ?><?= ($navUnit && $navBed) ? ' / ' : '' ?><?= htmlspecialchars($navBed) ?>
        </span>
      <?php endif; ?>
      <span class="badge <?= HospitalService::badgeClass($navService) ?>">
        <?= htmlspecialchars(HospitalService::label($navService)) ?>
      </span>
      <span class="badge <?= AdmissionType::badgeClass($navAdmType) ?>">
        <?= htmlspecialchars(AdmissionType::label($navAdmType)) ?>
      </span>
      <span class="badge <?= $navLosBadge ?> text-white">
        <?= xlt('Day') ?> <?= $navLos ?>
        <?php if ($navExpLos !== null): ?>
          / <?= $navExpLos ?>
        <?php endif; ?>
      </span>
    </div>

    <!-- Attending + episode marker -->
    <div class="d-flex gap-3 align-items-center">
      <?php if ($navAttending): ?>
        <span class="text-muted small">
            <?= xlt('Attending') ?>: <strong><?= htmlspecialchars($navAttending) ?></strong>
        </span>
      <?php endif; ?>
      <span class="text-muted small"><?= xlt('Episode') ?> #<?= (int)$episodeId ?></span>
    </div>

  </div>

  <!-- Tab row -->
  <div class="oei-ip-nav-tabs d-flex flex-wrap gap-1 px-3 pb-2">
    <?php foreach ($navTabs as [$feature, $label, $icon, $url, $pageKey]): ?>
        <?php if (!$manifest->featureEnabled($feature)): continue; endif; ?>
        <?php $isActive = ($activePage === $pageKey); ?>
      <a href="<?= htmlspecialchars($url) ?>"
         class="oei-ip-tab <?= $isActive ? 'oei-ip-tab-active' : '' ?>">
        <?= $icon ?> <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>

<style>
.oei-ip-nav {
    background: var(--bs-tertiary-bg);
    border-bottom: 2px solid #457b9d;
    z-index: 100;
}
.oei-ip-nav-header {
    border-bottom: 1px solid var(--bs-border-color);
}
.oei-ip-back-btn {
    background: transparent;
    border: 1px solid var(--bs-border-color);
    color: var(--bs-body-color);
    font-size: .78rem;
    padding: 2px 8px;
}
.oei-ip-back-btn:hover {
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}
.oei-ip-tab {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: .8rem;
    text-decoration: none;
    color: var(--bs-body-color);
    background: var(--bs-secondary-bg);
    border: 1px solid transparent;
    transition: background .12s, border-color .12s;
    white-space: nowrap;
}
.oei-ip-tab:hover {
    background: var(--bs-body-bg);
    border-color: #457b9d;
    color: #457b9d;
    text-decoration: none;
}
.oei-ip-tab-active {
    background: #457b9d !important;
    color: #fff !important;
    border-color: #457b9d !important;
    font-weight: 600;
}
</style>




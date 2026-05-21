<?php

/**
 * src/Core/Ui/partials/al_resident_nav.php
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
 * al_resident_nav.php — Shared resident context strip for all AL sub-pages.
 *
 * Requires in scope (set before include):
 *   $episodeId   int    — current AL episode
 *   $facilityId  int    — current facility
 *   $manifest    Manifest|ContextManifest
 *   $activePage  string — one of: profile|vitals|adl|care_plan|mar|fall_risk|incident
 *
 * Optionally pre-populated (avoids extra DB call):
 *   $alNavResident array — keys: fname, lname, room, unit, care_level, fall_risk_level, pid
 *
 * Emits a Bootstrap 5 sticky-top nav strip with:
 *   - Resident name / room / unit / care level badge
 *   - Tab row: Profile · Vitals · ADL · Care Plan · MAR · Fall Risk · Incident
 *   - ← Board button
 *
 * Theme-aware: uses Bootstrap CSS variables only.
 */

if (!isset($episodeId, $facilityId, $manifest, $activePage)) {
    return; // silently skip if required vars absent
}

// ── Lightweight header fetch if not pre-loaded ─────────────────────────────
if (!isset($alNavResident) && function_exists('sqlQuery') && $episodeId > 0) {
    $alNavResident = sqlQuery(
        "SELECT pd.fname, pd.lname, pd.pid,
                COALESCE(ale.room,'')       AS room,
                COALESCE(ale.unit,'')       AS unit,
                COALESCE(ale.care_level, 'TIER_1')    AS care_level,
                COALESCE(ale.fall_risk_level,'LOW')   AS fall_risk_level
         FROM   oei_episode e
         INNER  JOIN patient_data pd ON pd.pid = e.pid
         LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
         WHERE  e.id = ? AND e.type = 'AL'
         LIMIT  1",
        [$episodeId]
    ) ?: [];
}

$navR = $alNavResident ?? [];
$navName    = trim(($navR['fname'] ?? '') . ' ' . ($navR['lname'] ?? '')) ?: '—';
$navRoom    = (string)($navR['room'] ?? '');
$navUnit    = (string)($navR['unit'] ?? '');
$navCL      = (string)($navR['care_level'] ?? 'TIER_1');
$navFR      = (string)($navR['fall_risk_level'] ?? 'LOW');
$navPid     = (int)($navR['pid'] ?? 0);

$clLabels   = ['TIER_1' => 'L1', 'TIER_2' => 'L2', 'TIER_3' => 'L3'];
$clBadge    = ['TIER_1' => 'success', 'TIER_2' => 'warning', 'TIER_3' => 'danger'];
$frBadge    = ['LOW' => 'success', 'MODERATE' => 'warning', 'HIGH' => 'danger'];

$q  = 'episode_id=' . $episodeId . '&pid=' . $navPid . '&facility_id=' . $facilityId;
$qf = 'facility_id=' . $facilityId;

// Tab definitions: [feature_key, label, icon, url]
$navTabs = [
    ['al_profile',   xlt('Profile'),    '🏠', "profile.php?$q"],
    ['al_vitals',    xlt('Vitals'),     '🩺', "vitals.php?$q"],
    ['al_adl',       xlt('ADL'),        '📊', "adl.php?$q"],
    ['al_care_plan', xlt('Care Plan'),  '📋', "care_plan.php?$q"],
    ['al_mar',       xlt('Meds (MAR)'), '💊', "al_mar.php?$q"],
    ['al_fall_risk', xlt('Fall Risk'),  '⚠️',  "fall_risk.php?$q"],
    ['al_incident',  xlt('Incident'),  '🚨',  "incident.php?episode_id=$episodeId&$qf"],
    ['al_activity',  xlt('Activity'),  '🎭',  "activity.php?$q"],
    ['al_discharge', xlt('Discharge'), '🚪',  "discharge.php?$q"],
];

// Page-key → active tab label map
$pageTabMap = [
    'profile'   => xlt('Profile'),
    'vitals'    => xlt('Vitals'),
    'adl'       => xlt('ADL'),
    'care_plan' => xlt('Care Plan'),
    'mar'       => xlt('Meds (MAR)'),
    'fall_risk' => xlt('Fall Risk'),
    'incident'  => xlt('Incident'),
    'activity'  => xlt('Activity'),
    'discharge' => xlt('Discharge'),
];
$activeLabel = $pageTabMap[$activePage] ?? '';
?>
<nav class="oei-al-nav sticky-top mb-3" aria-label="<?= xlt('Resident navigation') ?>">
  <div class="oei-al-nav-header d-flex align-items-center justify-content-between flex-wrap gap-2 px-3 py-2">

    <!-- Resident identity -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="board.php?<?= $qf ?>"
         class="btn btn-sm oei-al-back-btn"
         title="<?= xlt('Back to Resident Board') ?>">
        ← <?= xlt('Board') ?>
      </a>
      <span class="fw-semibold"><?= htmlspecialchars($navName) ?></span>
      <?php if ($navRoom || $navUnit): ?>
        <span class="text-muted small">
            <?= htmlspecialchars($navUnit) ?><?= ($navUnit && $navRoom) ? ' / ' : '' ?><?= htmlspecialchars($navRoom) ?>
        </span>
      <?php endif; ?>
      <span class="badge bg-<?= $clBadge[$navCL] ?? 'secondary' ?>">
        <?= $clLabels[$navCL] ?? $navCL ?>
      </span>
      <span class="badge bg-<?= $frBadge[$navFR] ?? 'secondary' ?> oei-fr-badge">
        <?= htmlspecialchars($navFR) ?> <?= xlt('Fall') ?>
      </span>
    </div>

    <!-- Episode marker -->
    <span class="text-muted small">
      <?= xlt('Episode') ?> #<?= (int)$episodeId ?>
    </span>
  </div>

  <!-- Tab row -->
  <div class="oei-al-nav-tabs d-flex flex-wrap gap-1 px-3 pb-2">
    <?php foreach ($navTabs as [$feature, $label, $icon, $url]): ?>
        <?php if (!$manifest->featureEnabled($feature)): continue; endif; ?>
        <?php $isActive = ($label === $activeLabel); ?>
      <a href="<?= htmlspecialchars($url) ?>"
         class="oei-al-tab <?= $isActive ? 'oei-al-tab-active' : '' ?>">
        <?= $icon ?> <?= $label ?>
      </a>
    <?php endforeach; ?>
  </div>
</nav>

<style>
.oei-al-nav {
    background: var(--bs-tertiary-bg);
    border-bottom: 2px solid #4a7c59;
    z-index: 100;
}
.oei-al-nav-header {
    border-bottom: 1px solid var(--bs-border-color);
}
.oei-al-back-btn {
    background: transparent;
    border: 1px solid var(--bs-border-color);
    color: var(--bs-body-color);
    font-size: .78rem;
    padding: 2px 8px;
}
.oei-al-back-btn:hover {
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}
.oei-al-tab {
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
.oei-al-tab:hover {
    background: var(--bs-body-bg);
    border-color: #4a7c59;
    color: #4a7c59;
    text-decoration: none;
}
.oei-al-tab-active {
    background: #4a7c59 !important;
    color: #fff !important;
    border-color: #4a7c59 !important;
    font-weight: 600;
}
.oei-fr-badge { font-size: .68rem; }
</style>




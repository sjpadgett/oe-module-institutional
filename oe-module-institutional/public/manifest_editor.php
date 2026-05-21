<?php

/**
 * public/manifest_editor.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

/**
 * public/manifest_editor.php — Advanced Facility Feature Overrides
 *
 * Level 2: save a facility-owned feature set for the selected OpenEMR facility.
 * The base manifest.json remains the global capability catalog.
 *
 * Workflow:
 *   1. Select the OpenEMR facility to configure.
 *   2. (Optional) Click a Quick Profile such as AL, IP, or HBC.
 *   3. Review and adjust feature checkboxes.
 *   4. Click Save — persists this facility's feature overrides in the facility profile.
 *
 * Requires admin ACL and a valid selected facility.
 * CSRF-protected. No MAR code touched.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Core/Ui/partials/context_help.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Core\Service\FacilityManifestService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;
use OpenEMR\Modules\Institutional\Manifest\ManifestWriter;

AclGuard::requireAdmin();

if (!$manifest->featureEnabled('settings')) {
    die(xlt('Settings is disabled by manifest'));
}

$moduleRoot    = dirname(__DIR__);
$writer        = new ManifestWriter($moduleRoot . '/manifest.json');
$facilityProfiles = new FacilityProfileService();
$facilityManifest = new FacilityManifestService(null, $moduleRoot);
$userId        = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityId    = $facilityProfiles->resolveFacilityId(isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : (isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : ($_oei_facilityId ?? 0)), $userId);
$facilityName  = $facilityProfiles->getDisplayName($facilityId);
$oeFacilityList = $facilityProfiles->listOpenEmrFacilities(true);
$href          = institutional_bootstrap5_href($manifest);
$__bgClass     = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

$flash        = '';
$flashType    = 'success';

// ── Collect current feature state ─────────────────────────────────────────────
try {
    $manifestData    = $writer->read();
} catch (\RuntimeException $e) {
    die(htmlspecialchars($e->getMessage()));
}
$baseFeatures    = (array)($manifestData['features'] ?? []);
$allKeys         = array_keys($baseFeatures);
$currentFeatures = $facilityManifest->getStoredFeatureMap($facilityId, $allKeys, $baseFeatures) ?? $baseFeatures;
$currentProfile  = $facilityManifest->getProfileKey($facilityId);

// ── POST: save feature flags per selected facility ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }

    $action = (string)($_POST['action'] ?? 'save_features');

    if ($action === 'save_features') {
        $newFeatures = [];
        foreach ($allKeys as $k) {
            $newFeatures[$k] = isset($_POST['feat_' . $k]);
        }
        $selectedProfile = trim((string)($_POST['facility_manifest_profile'] ?? ''));
        try {
            $facilityManifest->saveFeatureMap($facilityId, $newFeatures, $userId > 0 ? $userId : null, $selectedProfile);
            $currentFeatures = $facilityManifest->getStoredFeatureMap($facilityId, $allKeys, $baseFeatures) ?? $newFeatures;
            $currentProfile  = $selectedProfile;
            $flash = xlt('Facility install state saved. This facility now uses its own feature set on next page load.');
        } catch (\Throwable $e) {
            $flash     = htmlspecialchars($e->getMessage());
            $flashType = 'danger';
        }
    }
}

// ── Prepare profile definitions for JS quick-apply ───────────────────────────
$profileMeta = $facilityManifest->profileCatalog();

// Build each profile's feature map for the JS inline object
$profilesJs = [];
foreach ($profileMeta as $pKey => $_) {
    $map = $facilityManifest->profileFeatureMap($pKey, $allKeys, $baseFeatures);
    $profilesJs[$pKey] = $map;
}

// ── Feature groups for display ────────────────────────────────────────────────
$featureGroups = [
    'Emergency Department' => [
        'edt_board'      => 'ED Tracking Board',
        'intake'         => 'Patient Intake',
        'triage'         => 'Triage',
        'mts_triage'     => 'MTS / CTAS Triage Standards',
        'disposition'    => 'Disposition',
        'diversion'      => 'Diversion Status',
        'downtime'       => 'Downtime / Offline Mode',
    ],
    'Observation Stay' => [
        'obs_stay'              => 'OBS Stay Engine',
        'obs_protocols'         => 'OBS Protocols',
        'obs_start_picker'      => 'OBS Protocol Picker',
        'obs_episodes'          => 'OBS Episode List',
        'obs_billing'           => 'OBS Billing (Two-Midnight)',
        'institutional_billing' => 'Billing Workbench',
    ],
    'Behavioral Health' => [
        'bh_safety'         => 'BH Safety',
        'bh_boarding'       => 'BH Boarding Tracker',
        'transfer_tracking' => 'Transfer Tracking',
    ],
    'Assisted Living' => [
        'al_board'       => 'Resident Board',
        'al_intake'      => 'Resident Intake',
        'al_profile'     => 'Resident Profile Hub',
        'al_care_plan'   => 'AL Care Plans',
        'al_adl'         => 'ADL Charting',
        'al_incident'    => 'Incident Reports',
        'al_vitals'      => 'AL Vitals Monitoring',
        'al_fall_risk'   => 'AL Fall Risk (Morse)',
        'al_mar'         => 'AL 5-Day Nursing MAR',
        'al_discharge'   => 'AL Discharge',
        'al_activity'    => 'Activity Log',
        'al_handoff'     => 'AL Shift Handoff',
    ],
    'Inpatient' => [
        'ip_board'       => 'IP Floor Board',
        'ip_admission'   => 'IP Admission',
        'ip_profile'     => 'IP Patient Profile Hub',
        'ip_vitals'      => 'IP Vitals Monitoring',
        'ip_fall_risk'   => 'IP Fall Risk',
        'ip_discharge'   => 'IP Discharge / Transfer Planning',
    ],
    'Home-Based Care' => [
        'hbc_board'      => 'HBC Visit Board',
        'hbc_intake'     => 'HBC New Referral',
        'hbc_profile'    => 'HBC Patient Profile',
        'hbc_visit'      => 'HBC Visit Workspace',
        'hbc_schedule'   => 'HBC Schedule Visit',
        'hbc_vitals'     => 'HBC Vitals Monitoring',
        'hbc_fall_risk'  => 'HBC Fall Risk',
        'hbc_handoff'    => 'HBC Clinician Handoff',
        'hbc_discharge'  => 'HBC Discharge',
        'hbc_comm_log'   => 'HBC Communication Log',
    ],
    'Clinical (Shared)' => [
        'care_plan'                  => 'Care Plan Viewer / Editor',
        'care_plan_launch'           => 'Care Plan Launch Button',
        'clinical_notes'             => 'Clinical Notes',
        'clinical_notes_launch'      => 'Clinical Notes Launch Button',
        'clinical_notes_documents'   => 'Clinical Notes — Documents Tab',
        'clinical_notes_results'     => 'Clinical Notes — Results Tab',
        'care_team'                  => 'Care Team Management',
        'care_team_launch'           => 'Care Team Launch Button',
        'episode_documents'          => 'Episode Document Attachments',
        'ereferral'                  => 'E-Referral',
        'mar'                        => 'Medication Administration Record',
        'observations'               => 'Extended Observations',
    ],
    'Operations & Reporting' => [
        'tasks'          => 'Task Tracker',
        'assignment'     => 'Staff Assignments',
        'handoff'        => 'Shift Handoff (Shared)',
        'alerts'         => 'Clinical Alerts',
        'timeline'       => 'Episode Timeline',
        'throughput'     => 'Throughput Analytics',
        'scorecard'      => 'Provider Scorecard',
        'trends'         => 'Operational Trends',
        'cms_quality'    => 'Institutional Quality Dashboard',
        'multi_facility' => 'Multi-Facility Dashboard',
        'command_center' => 'Command Center',
    ],
    'Admin & Infrastructure' => [
        'context_manager'   => 'Care Context Manager',
        'bed_mgmt'          => 'Bed Management',
        'adt_lite'          => 'ADT Lite',
        'facility_directory'=> 'Facility Directory',
        'hl7_adt'           => 'HL7 ADT Outbound',
        'admin_exports'     => 'Admin Data Exports',
        'settings'          => 'Settings Page',
        'smoke_test'        => 'Smoke Test (Read-only)',
    ],
];

// CSRF token collected before any HTML output
$csrf = CsrfUtils::collectCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Advanced Feature Overrides') ?> — <?= xlt('Institutional') ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <?php if ($href): ?><link rel="stylesheet" href="<?= htmlspecialchars($href) ?>"><?php endif; ?>
  <style>
    .profile-card       { cursor:pointer; border:2px solid transparent; transition:border-color .15s, box-shadow .15s; }
    .profile-card:hover { border-color:#0d6efd; box-shadow:0 0 0 .15rem rgba(13,110,253,.15); }
    .profile-card.active{ border-color:#0d6efd; background:var(--bs-primary-bg-subtle); }
    .group-header       { background:var(--bs-secondary-bg); font-size:.8rem;
                          text-transform:uppercase; letter-spacing:.06em; font-weight:600;
                          padding:.4rem .75rem; border-radius:.25rem; margin-bottom:.5rem; }
    .feature-row        { display:flex; align-items:center; gap:.5rem; padding:.2rem 0; }
    .feature-label      { font-size:.875rem; }
    .badge-new          { font-size:.65rem; }
    .perm-warn          { border-left:4px solid #dc3545; }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3" style="max-width:960px;">

  <!-- Page header -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0">⚙️ <?= xlt('Advanced Feature Overrides') ?></h4>
      <div class="text-muted small"><?= xlt('Tune feature exceptions for the selected facility. Installed purpose stays in the facility profile; the base manifest remains the module capability catalog.') ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="onboarding.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-success">
        ✅ <?= xlt('Onboarding Checklist') ?>
      </a>
      <a href="settings.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
        ← <?= xlt('Settings') ?>
      </a>
    </div>
  </div>

  <?php oei_render_context_help('manifest', ['facility_name' => $facilityName]); ?>

  <!-- Flash -->
  <?php if ($flash): ?>
  <div class="alert alert-<?= $flashType ?> py-2 mb-3"><?= $flash ?></div>
  <?php endif; ?>

  <div class="card mb-4 shadow-sm">
    <div class="card-header fw-semibold">🏥 <?= xlt('Selected Facility') ?></div>
    <div class="card-body">
      <form method="get" class="row g-3 align-items-end">
        <div class="col-12 col-lg-8">
          <label class="form-label" for="facility_id"><?= xlt('OpenEMR Facility') ?></label>
          <select class="form-select" id="facility_id" name="facility_id" onchange="this.form.submit()">
            <?php foreach ($oeFacilityList as $__facility): ?>
              <option value="<?= (int)$__facility['id'] ?>" <?= (int)$__facility['id'] === $facilityId ? 'selected' : '' ?>><?= htmlspecialchars((string)($__facility['name'] ?? ('Facility ' . $__facility['id']))) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text"><?= xlt('The install state you save here belongs only to this OpenEMR facility.') ?></div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="small text-muted"><?= xlt('Current facility') ?></div>
          <div class="fw-semibold">#<?= (int)$facilityId ?> — <?= htmlspecialchars($facilityName) ?></div>
          <div class="text-muted small"><?= $currentProfile !== '' ? xlt('Installed As') . ': ' . htmlspecialchars($currentProfile) : xlt('No installed-purpose profile saved yet') ?></div>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Quick Profiles ────────────────────────────────────────────────────── -->
  <div class="card mb-4 shadow-sm">
    <div class="card-header fw-semibold d-flex align-items-center gap-2">
      🏗️ <?= xlt('Installed-As Presets') ?>
      <span class="text-muted fw-normal small"><?= xlt('Choose the facility installed-as preset, then review any feature exceptions before saving.') ?></span>
    </div>
    <div class="card-body">
      <div class="row g-3" id="profileCards">
        <?php foreach ($profileMeta as $pKey => $pMeta): ?>
        <div class="col-6 col-md-3">
          <div class="profile-card card h-100 p-3 text-center"
               data-profile="<?= htmlspecialchars($pKey) ?>"
               role="button" tabindex="0"
               aria-label="<?= htmlspecialchars($pMeta['label']) ?>">
            <div style="font-size:2rem;"><?= $pMeta['icon'] ?></div>
            <div class="fw-semibold mt-1"><?= htmlspecialchars($pMeta['label']) ?></div>
            <div class="text-muted small mt-1"><?= htmlspecialchars($pMeta['desc']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="mt-2 small text-muted"><?= xlt('Selecting a profile only updates the checkboxes below — nothing is saved until you click Save.') ?></div>
    </div>
  </div>

  <!-- ── Feature Flags Form ────────────────────────────────────────────────── -->
  <form method="post"
        action="manifest_editor.php?facility_id=<?= $facilityId ?>"
        id="featureForm">
    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="action" value="save_features">
    <input type="hidden" name="facility_manifest_profile" id="facility_manifest_profile" value="<?= htmlspecialchars($currentProfile) ?>">

    <?php foreach ($featureGroups as $groupLabel => $groupFeatures): ?>
    <div class="card mb-3 shadow-sm">
      <div class="card-body">
        <div class="group-header mb-3"><?= htmlspecialchars($groupLabel) ?></div>
        <div class="row g-0">
          <?php foreach ($groupFeatures as $featureKey => $featureLabel): ?>
          <?php $enabled = (bool)($currentFeatures[$featureKey] ?? false); ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="feature-row">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input feat-toggle"
                       type="checkbox"
                       id="feat_<?= htmlspecialchars($featureKey) ?>"
                       name="feat_<?= htmlspecialchars($featureKey) ?>"
                       data-key="<?= htmlspecialchars($featureKey) ?>"
                       <?= $enabled ? 'checked' : '' ?>>
              </div>
              <label class="feature-label" for="feat_<?= htmlspecialchars($featureKey) ?>">
                <?= htmlspecialchars($featureLabel) ?>
                <?php if (!array_key_exists($featureKey, $currentFeatures)): ?>
                <span class="badge text-bg-info badge-new">new</span>
                <?php endif; ?>
              </label>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Any features in manifest.json not yet in a display group -->
    <?php
    $groupedKeys = array_merge(...array_values(array_map('array_keys', $featureGroups)));
    $ungrouped   = array_diff($allKeys, $groupedKeys);
    if ($ungrouped):
    ?>
    <div class="card mb-3 shadow-sm">
      <div class="card-body">
        <div class="group-header mb-3">Other</div>
        <div class="row g-0">
          <?php foreach ($ungrouped as $featureKey): ?>
          <?php $enabled = (bool)($currentFeatures[$featureKey] ?? false); ?>
          <div class="col-12 col-md-6 col-lg-4">
            <div class="feature-row">
              <div class="form-check form-switch mb-0">
                <input class="form-check-input feat-toggle"
                       type="checkbox"
                       id="feat_<?= htmlspecialchars($featureKey) ?>"
                       name="feat_<?= htmlspecialchars($featureKey) ?>"
                       data-key="<?= htmlspecialchars($featureKey) ?>"
                       <?= $enabled ? 'checked' : '' ?>>
              </div>
              <label class="feature-label" for="feat_<?= htmlspecialchars($featureKey) ?>">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $featureKey))) ?>
              </label>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Save bar -->
    <div class="d-flex align-items-center gap-3 mt-2 mb-4">
      <button type="submit" class="btn btn-primary px-4">
        💾 <?= xlt('Save Advanced Feature Overrides') ?>
      </button>
      <span class="text-muted small"><?= xlt('These overrides belong only to the selected facility. The global manifest.json file is not modified.') ?></span>
    </div>

  </form><!-- /featureForm -->

</div><!-- /container -->

<script>
// Profile feature maps (generated server-side)
const OEI_PROFILES = <?= json_encode($profilesJs, JSON_UNESCAPED_UNICODE) ?>;

// Profile card click → update checkboxes
document.querySelectorAll('.profile-card').forEach(card => {
    card.addEventListener('click', () => applyProfile(card.dataset.profile));
    card.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); applyProfile(card.dataset.profile); }
    });
});

function applyProfile(name) {
    const map = OEI_PROFILES[name];
    if (!map) return;
    const profileInput = document.getElementById('facility_manifest_profile');
    if (profileInput) profileInput.value = name;

    // Update all checkboxes
    document.querySelectorAll('.feat-toggle').forEach(cb => {
        const key = cb.dataset.key;
        if (key in map) cb.checked = map[key];
    });

    // Highlight active profile card
    document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('active'));
    const activeCard = document.querySelector(`.profile-card[data-profile="${name}"]`);
    if (activeCard) activeCard.classList.add('active');
}

// Deactivate profile highlight when any checkbox is changed manually
document.querySelectorAll('.feat-toggle').forEach(cb => {
    cb.addEventListener('change', () => {
        document.querySelectorAll('.profile-card').forEach(c => c.classList.remove('active'));
        const profileInput = document.getElementById('facility_manifest_profile');
        if (profileInput) profileInput.value = '';
    });
});

const __savedProfile = document.getElementById('facility_manifest_profile') ? document.getElementById('facility_manifest_profile').value : '';
if (__savedProfile) {
    const activeCard = document.querySelector(`.profile-card[data-profile="${__savedProfile}"]`);
    if (activeCard) activeCard.classList.add('active');
}

// Scroll-to-top after save flash
<?php if ($flash && $flashType === 'success'): ?>
window.scrollTo({ top: 0, behavior: 'smooth' });
<?php endif; ?>
</script>

<?php if ($href): ?>
<?= institutional_bootstrap5_js_tag() ?>
<?php endif; ?>
</body>
</html>

























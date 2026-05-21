<?php

/**
 * public/setup_wizard.php
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

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Core/Ui/partials/context_help.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\FacilityProfileRepository;
use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Core\Service\FacilityManifestService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

AclGuard::requireAdmin();
if (!$manifest->featureEnabled('settings')) {
    die(xlt('Settings is disabled by manifest'));
}

$repo = new SettingsRepository();
$facilityProfiles = new FacilityProfileService($repo);
$facilityManifest = new FacilityManifestService($repo, dirname(__DIR__));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityId = $facilityProfiles->resolveFacilityId(
    isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : (isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0),
    $userId
);
$selectedFacility = $facilityProfiles->getOpenEmrFacility($facilityId);
$facilityName = $facilityProfiles->getDisplayName($facilityId);
$profile = $facilityProfiles->getProfile($facilityId);
$href = institutional_bootstrap5_href($manifest);
$csrf = CsrfUtils::collectCsrfToken();
$flash = '';
$flashType = 'success';

$purposes = $facilityManifest->profileCatalog();
$homePageLabels = [
    'ed_board.php' => xlt('ED Board'),
    'ip/board.php' => xlt('Floor Board'),
    'al/board.php' => xlt('Resident Board'),
    'hbc/board.php' => xlt('Visit Board'),
    'bh_boarding.php' => xlt('BH Boarding'),
    'multi_facility.php' => xlt('System Dashboard'),
    'command_center.php' => xlt('Command Center'),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }

    $purpose = trim((string)($_POST['installed_purpose'] ?? FacilityProfileRepository::PURPOSE_FULL));
    if (!FacilityProfileRepository::isValidPurpose($purpose)) {
        $purpose = FacilityProfileRepository::PURPOSE_FULL;
    }

    $enabledContexts = [];
    foreach ((array)($_POST['enabled_contexts'] ?? []) as $ctx) {
        $ctx = trim((string)$ctx);
        if ($ctx !== '' && CareContext::isValid($ctx)) {
            $enabledContexts[] = $ctx;
        }
    }

    $defaultContext = trim((string)($_POST['default_context'] ?? ''));
    if (!CareContext::isValid($defaultContext)) {
        $defaultContext = $facilityProfiles->recommendedDefaultContext($purpose);
    }
    if (empty($enabledContexts)) {
        $enabledContexts = $facilityProfiles->recommendedContexts($purpose, $defaultContext);
    }

    $homePage = trim((string)($_POST['home_page'] ?? ''));
    if ($homePage === '') {
        $homePage = $facilityProfiles->recommendedHomePage($purpose, $defaultContext);
    }

    $facilityProfiles->saveProfile($facilityId, [
        'installed_purpose' => $purpose,
        'facility_name' => trim((string)($_POST['facility_name'] ?? '')),
        'institutional_enabled' => isset($_POST['institutional_enabled']),
        'default_context' => $defaultContext,
        'home_page' => $homePage,
        'enabled_contexts_json' => json_encode(array_values(array_unique($enabledContexts))),
        'setup_completed' => 1,
        'setup_step' => 4,
    ], $userId);

    $profile = $facilityProfiles->getProfile($facilityId);
    $facilityName = $facilityProfiles->getDisplayName($facilityId);
    $flash = xlt('Facility setup saved. This facility now owns its installed purpose, default work mode, and available work modes.');
}

$currentPurpose = (string)($profile['installed_purpose'] ?: FacilityProfileRepository::PURPOSE_FULL);
$currentContext = (string)($profile['default_context'] ?: $facilityProfiles->recommendedDefaultContext($currentPurpose));
$currentHome = (string)($profile['home_page'] ?: $facilityProfiles->recommendedHomePage($currentPurpose, $currentContext));
$currentContexts = $facilityProfiles->getEnabledContexts($facilityId);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= xlt('Setup Wizard') ?> — <?= xlt('Institutional') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
    <style>
        .purpose-card { cursor:pointer; border:2px solid transparent; transition:border-color .12s ease, box-shadow .12s ease; }
        .purpose-card:hover { border-color:#0d6efd; box-shadow:0 0 0 .15rem rgba(13,110,253,.12); }
        .purpose-card.active { border-color:#0d6efd; background:var(--bs-primary-bg-subtle); }
        .step-pill { font-size:.75rem; }
    </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container py-4" style="max-width: 1000px;">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-0"><?= xlt('Setup Wizard') ?></h1>
            <div class="text-muted small"><?= xlt('OpenEMR chooses the facility. The facility profile decides what the app is installed as.') ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-secondary" href="settings.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Settings') ?></a>
            <a class="btn btn-sm btn-outline-secondary" href="manifest_editor.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Advanced Feature Overrides') ?></a>
        </div>
    </div>

    <?php oei_render_context_help('wizard', ['facility_name' => $facilityName]); ?>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flashType ?> py-2 mb-3"><?= $flash ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3"><div class="badge text-bg-primary step-pill">1. <?= xlt('Choose Facility') ?></div></div>
        <div class="col-12 col-md-3"><div class="badge text-bg-primary step-pill">2. <?= xlt('Choose Installed As') ?></div></div>
        <div class="col-12 col-md-3"><div class="badge text-bg-primary step-pill">3. <?= xlt('Review Generated Defaults') ?></div></div>
        <div class="col-12 col-md-3"><div class="badge text-bg-primary step-pill">4. <?= xlt('Save Facility Profile') ?></div></div>
    </div>

    <form method="post" id="setup-form">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
        <input type="hidden" name="facility_id" value="<?= (int)$facilityId ?>">

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><?= xlt('Step 1 — OpenEMR Facility') ?></div>
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-lg-8">
                    <label class="form-label" for="facility_picker"><?= xlt('OpenEMR Facility') ?></label>
                    <select class="form-select" id="facility_picker" onchange="window.location='setup_wizard.php?facility_id='+this.value;">
                        <?php foreach ($facilityProfiles->listOpenEmrFacilities(true) as $f): ?>
                            <option value="<?= (int)$f['id'] ?>" <?= (int)$f['id'] === $facilityId ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)($f['name'] ?? ('Facility ' . $f['id']))) ?><?= !empty($f['facility_code']) ? ' [' . htmlspecialchars((string)$f['facility_code']) . ']' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= xlt('Users whose OpenEMR default facility matches this profile will land here automatically.') ?></div>
                </div>
                <div class="col-12 col-lg-4">
                    <div class="small text-muted"><?= xlt('Selected') ?></div>
                    <div class="fw-semibold">#<?= (int)$facilityId ?> — <?= htmlspecialchars($facilityName) ?></div>
                    <?php if (!empty($selectedFacility['city']) || !empty($selectedFacility['state'])): ?>
                        <div class="text-muted small"><?= htmlspecialchars(trim((string)($selectedFacility['city'] ?? ''))) ?><?= (!empty($selectedFacility['city']) && !empty($selectedFacility['state'])) ? ', ' : '' ?><?= htmlspecialchars(trim((string)($selectedFacility['state'] ?? ''))) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><?= xlt('Step 2 — Facility Installed As') ?></div>
            <div class="card-body">
                <div class="row g-3" id="purposeCards">
                    <?php foreach ($purposes as $key => $meta): ?>
                        <div class="col-12 col-md-6 col-xl-4">
                            <label class="purpose-card card h-100 p-3 <?= $currentPurpose === $key ? 'active' : '' ?>" data-purpose="<?= htmlspecialchars($key) ?>">
                                <input type="radio" class="d-none" name="installed_purpose" value="<?= htmlspecialchars($key) ?>" <?= $currentPurpose === $key ? 'checked' : '' ?>>
                                <div class="d-flex align-items-center gap-3">
                                    <div style="font-size:1.9rem;line-height:1;"><?= htmlspecialchars((string)$meta['icon']) ?></div>
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars((string)$meta['label']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars((string)$meta['desc']) ?></div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label" for="facility_name"><?= xlt('Facility Display Name Override') ?></label>
                        <input type="text" class="form-control" id="facility_name" name="facility_name" value="<?= htmlspecialchars((string)($profile['facility_name'] ?? '')) ?>" placeholder="<?= xla('Leave blank to use the OpenEMR facility name') ?>">
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="institutional_enabled" name="institutional_enabled" value="1" <?= !empty($profile['institutional_enabled']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="institutional_enabled"><?= xlt('Enable Institutional for this facility') ?></label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header fw-semibold"><?= xlt('Step 3 — Review Generated Defaults') ?></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="default_context"><?= xlt('Default Work Mode') ?></label>
                        <select class="form-select" id="default_context" name="default_context">
                            <?php foreach (CareContext::all() as $ctxKey => $ctxMeta): ?>
                                <option value="<?= htmlspecialchars($ctxKey) ?>" <?= $currentContext === $ctxKey ? 'selected' : '' ?>><?= htmlspecialchars((string)($ctxMeta['label'] ?? $ctxKey)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="home_page"><?= xlt('Facility Home Page') ?></label>
                        <select class="form-select" id="home_page" name="home_page">
                            <option value=""><?= xlt('Use recommended home page') ?></option>
                            <?php foreach ($homePageLabels as $page => $label): ?>
                                <option value="<?= htmlspecialchars($page) ?>" <?= $currentHome === $page ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label d-block"><?= xlt('Available Work Modes') ?></label>
                        <div class="border rounded p-2 bg-body-tertiary" id="context-list">
                            <?php foreach (CareContext::all() as $ctxKey => $ctxMeta): ?>
                                <label class="d-flex align-items-start gap-2 small mb-2">
                                    <input class="form-check-input mt-1 ctx-check" type="checkbox" name="enabled_contexts[]" value="<?= htmlspecialchars($ctxKey) ?>" <?= in_array($ctxKey, $currentContexts, true) ? 'checked' : '' ?>>
                                    <span>
                                        <span class="fw-semibold d-block"><?= htmlspecialchars((string)($ctxMeta['label'] ?? $ctxKey)) ?></span>
                                        <span class="text-muted"><?= htmlspecialchars((string)($ctxMeta['subtitle'] ?? '')) ?></span>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info py-2 mt-3 mb-0 small">
                    <?= xlt('For beta, installed purpose is facility-owned. Users may switch work modes inside the facility, but they do not redefine what the facility is installed as.') ?>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
            <div class="text-muted small"><?= xlt('This saves the facility-default row in oei_facility_profile and keeps compatibility settings in sync for older module code.') ?></div>
            <button type="submit" class="btn btn-primary px-4"><?= xlt('Save Facility Profile') ?></button>
        </div>
    </form>
</div>
<script>
const PURPOSE_DEFAULTS = <?= json_encode(array_map(function($meta, $key) use ($facilityProfiles) {
    $ctx = $facilityProfiles->recommendedDefaultContext($key);
    return [
        'default_context' => $ctx,
        'home_page' => $facilityProfiles->recommendedHomePage($key, $ctx),
        'contexts' => $facilityProfiles->recommendedContexts($key, $ctx),
    ];
}, $purposes, array_keys($purposes)), JSON_UNESCAPED_SLASHES) ?>;

document.querySelectorAll('.purpose-card').forEach(card => {
    card.addEventListener('click', () => {
        const purpose = card.dataset.purpose;
        document.querySelectorAll('.purpose-card').forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        const radio = card.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
        const def = PURPOSE_DEFAULTS[purpose];
        if (!def) return;
        const defaultCtx = document.getElementById('default_context');
        if (defaultCtx) defaultCtx.value = def.default_context;
        const home = document.getElementById('home_page');
        if (home && !home.dataset.userChanged) home.value = def.home_page;
        document.querySelectorAll('.ctx-check').forEach(cb => {
            cb.checked = def.contexts.includes(cb.value);
        });
    });
});
const homeSel = document.getElementById('home_page');
if (homeSel) {
    homeSel.addEventListener('change', () => { homeSel.dataset.userChanged = '1'; });
}
</script>
</body>
</html>










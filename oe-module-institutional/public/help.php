<?php

/**
 * public/help.php
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

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Service\FacilityContextResolver;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;

require __DIR__ . '/../src/Core/Ui/partials/flash.php';

$pageTitle = xlt('Institutional Help');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$resolver = class_exists(FacilityContextResolver::class) ? new FacilityContextResolver() : null;
$facilityId = $resolver ? $resolver->resolveFacilityId((int)($_GET['facility_id'] ?? 0), $userId) : (int)($_GET['facility_id'] ?? 0);
$profiles = new FacilityProfileService();
$profile = $facilityId > 0 ? $profiles->getProfile($facilityId) : [];
$facilityName = $facilityId > 0 ? $profiles->getDisplayName($facilityId) : '';
$enabledContexts = $facilityId > 0 ? $profiles->getEnabledContexts($facilityId) : [];

$contextLabels = [];
foreach ($enabledContexts as $ctx) {
    $contextLabels[] = CareContext::label($ctx);
}
?>
<div class="container-fluid px-0">
    <div class="alert alert-info mb-3">
        <strong><?= xlt('How this setup works') ?>:</strong>
        <?= xlt('OpenEMR chooses the facility from the logged-in user. The facility profile decides what the Institutional app is installed as. Context only changes the current work mode inside that facility.') ?>
    </div>

    <?php oei_render_context_help('settings', ['facility_name' => $facilityName]); ?>
    <?php oei_render_context_help('manifest', ['facility_name' => $facilityName]); ?>
    <?php oei_render_context_help('wizard', ['facility_name' => $facilityName]); ?>
    <?php oei_render_context_help('context', ['facility_name' => $facilityName]); ?>

    <?php if ($facilityId > 0): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong><?= xlt('Current Facility Summary') ?></strong>
                <a class="btn btn-sm btn-outline-primary" href="settings.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Open Settings') ?></a>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Facility') ?></div>
                        <div class="fw-semibold"><?= htmlspecialchars($facilityName !== '' ? $facilityName : ('Facility ' . $facilityId)) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Installed As') ?></div>
                        <div class="fw-semibold"><?= htmlspecialchars($profiles->purposeLabel((string)($profile['installed_purpose'] ?? 'FULL'))) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Default Work Mode') ?></div>
                        <div class="fw-semibold"><?= htmlspecialchars(CareContext::label((string)($profile['default_context'] ?? CareContext::FULL))) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Home Page') ?></div>
                        <div class="fw-semibold"><?= htmlspecialchars((string)($profile['home_page'] ?? '')) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Institutional Enabled') ?></div>
                        <div class="fw-semibold"><?= !empty($profile['institutional_enabled']) ? xlt('Yes') : xlt('No') ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small"><?= xlt('Available Work Modes') ?></div>
                        <div class="fw-semibold"><?= htmlspecialchars($contextLabels ? implode(', ', $contextLabels) : xlt('None configured')) ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong><?= xlt('Recommended Setup Flow') ?></strong></div>
                <div class="card-body">
                    <ol class="mb-0 ps-3">
                        <li class="mb-2"><?= xlt('Log in using a user whose OpenEMR default facility matches the facility you want to configure.') ?></li>
                        <li class="mb-2"><?= xlt('Open Settings for that facility.') ?></li>
                        <li class="mb-2"><?= xlt('Choose the Installed Purpose. This is the main decision and it should describe what that facility is operationally.') ?></li>
                        <li class="mb-2"><?= xlt('Review the recommended Default Work Mode, Home Page, and Available Work Modes.') ?></li>
                        <li class="mb-2"><?= xlt('Save the facility profile.') ?></li>
                        <li><?= xlt('After save, users at that facility should land in the correct home page and can switch only among the enabled work modes for that facility.') ?></li>
                    </ol>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header"><strong><?= xlt('What each setting means') ?></strong></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5"><?= xlt('Installed Purpose') ?></dt>
                        <dd class="col-sm-7"><?= xlt('The overall application shape for the facility, such as Assisted Living, Inpatient, or Home-Based Care.') ?></dd>

                        <dt class="col-sm-5"><?= xlt('Facility Display Name') ?></dt>
                        <dd class="col-sm-7"><?= xlt('Optional label shown in module UI. If left blank, the OpenEMR facility name is used automatically.') ?></dd>

                        <dt class="col-sm-5"><?= xlt('Default Work Mode') ?></dt>
                        <dd class="col-sm-7"><?= xlt('The context used when the user has no saved context choice yet for this facility.') ?></dd>

                        <dt class="col-sm-5"><?= xlt('Available Work Modes') ?></dt>
                        <dd class="col-sm-7"><?= xlt('The contexts the user may switch between while working inside this facility.') ?></dd>

                        <dt class="col-sm-5"><?= xlt('Home Page') ?></dt>
                        <dd class="col-sm-7"><?= xlt('The first Institutional page users should land on for this facility.') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header"><strong><?= xlt('Important behavior') ?></strong></div>
        <div class="card-body">
            <ul class="mb-0 ps-3">
                <li class="mb-2"><?= xlt('The facility profile is saved per facility. It should describe the facility, not one person.') ?></li>
                <li class="mb-2"><?= xlt('Context switching is a runtime preference. It should not redefine what the facility is installed as.') ?></li>
                <li class="mb-2"><?= xlt('Advanced Feature Overrides should be used only when a facility needs an exception from its normal Installed Purpose.') ?></li>
                <li><?= xlt('If a facility display name is left blank, the module now falls back to the OpenEMR facility name automatically.') ?></li>
            </ul>
        </div>
    </div>
</div>
</body>
</html>







<?php

/**
 * public/context_manager.php
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
 * context_manager.php — Care Context Manager
 *
 * Allows users to select their active care setting, which governs which
 * submodules are surfaced in menus and the context bar.
 */
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../src/Core/Ui/partials/context_help.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;
use OpenEMR\Modules\Institutional\Core\Service\ContextService;
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityProfiles = new FacilityProfileService();
$facilityId = $facilityProfiles->resolveFacilityId(isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : (isset($_POST['facility_id']) ? (int)$_POST['facility_id'] : 0), $userId);
$facilityName = $facilityProfiles->getDisplayName($facilityId);
$return = trim((string)($_GET['return'] ?? ''));

// Sanitise return URL
$return = filter_var($return, FILTER_SANITIZE_URL);
if ($return === false || !preg_match('#^/[^/]#', $return) || str_contains($return, '//')) {
    $return = '/interface/modules/custom_modules/oe-module-institutional/public/'
        . $facilityProfiles->getHomePage($facilityId)
        . '?facility_id=' . $facilityId;
}

$ctxRepo = new ContextRepository();
$ctxSvc = new ContextService($ctxRepo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die(xlt('CSRF validation failed'));
    }
    $selected = trim((string)($_POST['context_key'] ?? ''));
    if (CareContext::isValid($selected) && $userId > 0) {
        $ctxSvc->switch($userId, $facilityId, $selected);
        $returnUrl = trim((string)($_POST['return'] ?? $return));
        $returnUrl = filter_var($returnUrl, FILTER_SANITIZE_URL);
        if (!$returnUrl || !preg_match('#^/[^/]#', $returnUrl)) {
            $returnUrl = $return;
        }
        header('Location: ' . $returnUrl);
        exit;
    }
}

$activeContext = $ctxSvc->resolve($userId, $facilityId);
$allowedContextKeys = $facilityProfiles->getEnabledContexts($facilityId);
$allContexts = [];
foreach ($allowedContextKeys as $__ctxKey) {
    if (isset(CareContext::all()[$__ctxKey])) {
        $allContexts[$__ctxKey] = CareContext::all()[$__ctxKey];
    }
}
if (empty($allContexts)) {
    $allContexts = CareContext::all();
}
$csrf = CsrfUtils::collectCsrfToken();
$href = institutional_bootstrap5_href($manifest);

// Feature -> short label + Bootstrap badge variant
$featureMeta = [
    'edt_board' => ['label' => 'ED Board', 'color' => 'danger'],
    'intake' => ['label' => 'Intake', 'color' => 'secondary'],
    'triage' => ['label' => 'Triage', 'color' => 'secondary'],
    'tasks' => ['label' => 'Tasks', 'color' => 'secondary'],
    'alerts' => ['label' => 'Alerts', 'color' => 'warning'],
    'assignment' => ['label' => 'Assignments', 'color' => 'secondary'],
    'handoff' => ['label' => 'Handoff', 'color' => 'secondary'],
    'disposition' => ['label' => 'Disposition', 'color' => 'secondary'],
    'transfer_tracking' => ['label' => 'Transfers', 'color' => 'secondary'],
    'bh_safety' => ['label' => 'BH Safety', 'color' => 'warning'],
    'mar' => ['label' => 'MAR', 'color' => 'secondary'],
    'ereferral' => ['label' => 'eReferral', 'color' => 'secondary'],
    'obs_stay' => ['label' => 'OBS Stay', 'color' => 'success'],
    'obs_protocols' => ['label' => 'OBS Protocols', 'color' => 'success'],
    'obs_billing' => ['label' => 'OBS Billing', 'color' => 'success'],
    'institutional_billing' => ['label' => 'Billing Workbench', 'color' => 'warning'],
    'obs_episodes' => ['label' => 'OBS Episodes', 'color' => 'success'],
    'cms_quality' => ['label' => 'Institutional Quality', 'color' => 'primary'],
    'bh_boarding' => ['label' => 'BH Boarding', 'color' => 'warning'],
    'timeline' => ['label' => 'Timeline', 'color' => 'secondary'],
    'episode_documents' => ['label' => 'Documents', 'color' => 'secondary'],
    'multi_facility' => ['label' => 'Multi-Facility', 'color' => 'info'],
    'scorecard' => ['label' => 'Scorecard', 'color' => 'primary'],
    'throughput' => ['label' => 'Throughput', 'color' => 'primary'],
    'admin_exports' => ['label' => 'Exports', 'color' => 'secondary'],
    'settings' => ['label' => 'Settings', 'color' => 'secondary'],
    'facility_directory' => ['label' => 'Directory', 'color' => 'secondary'],
    'hl7_adt' => ['label' => 'HL7 ADT', 'color' => 'secondary'],
    'bed_mgmt' => ['label' => 'Bed Board', 'color' => 'secondary'],
    'command_center' => ['label' => 'Command Center', 'color' => 'primary'],
    'adt_lite' => ['label' => 'ADT', 'color' => 'secondary'],
    'obs_start_picker' => ['label' => 'OBS Start', 'color' => 'success'],
    // Assisted Living
    'al_board' => ['label' => 'Resident Board', 'color' => 'success'],
    'al_care_plan' => ['label' => 'AL Care Plans', 'color' => 'success'],
    'al_adl' => ['label' => 'ADL Tracking', 'color' => 'success'],
    'al_incident' => ['label' => 'Incidents', 'color' => 'warning'],
    'al_intake' => ['label' => 'AL Intake', 'color' => 'success'],
    'al_profile' => ['label' => 'Resident Profile', 'color' => 'success'],
    'al_vitals' => ['label' => 'AL Vitals', 'color' => 'success'],
    'al_fall_risk' => ['label' => 'Fall Risk', 'color' => 'warning'],
    'al_mar' => ['label' => 'AL MAR', 'color' => 'success'],
    'al_discharge' => ['label' => 'AL Discharge', 'color' => 'success'],
    'al_activity' => ['label' => 'Activity Log', 'color' => 'success'],
    'al_handoff' => ['label' => 'AL Handoff', 'color' => 'success'],
    // Inpatient
    'ip_board' => ['label' => 'Floor Board', 'color' => 'info'],
    'ip_admission' => ['label' => 'Admit Patient', 'color' => 'info'],
    'ip_profile' => ['label' => 'IP Profile', 'color' => 'info'],
    'ip_vitals' => ['label' => 'IP Vitals', 'color' => 'info'],
    'ip_discharge' => ['label' => 'IP Discharge', 'color' => 'info'],
    'ip_fall_risk' => ['label' => 'IP Fall Risk', 'color' => 'info'],
    // Home-Based Care
    'hbc_board' => ['label' => 'Visit Board', 'color' => 'success'],
    'hbc_intake' => ['label' => 'New Referral', 'color' => 'success'],
    'hbc_profile' => ['label' => 'Patient Hub', 'color' => 'success'],
    'hbc_visit' => ['label' => 'Visit Workspace', 'color' => 'success'],
    'hbc_schedule' => ['label' => 'Schedule Visit', 'color' => 'success'],
    'hbc_vitals' => ['label' => 'HBC Vitals', 'color' => 'success'],
    'hbc_fall_risk' => ['label' => 'HBC Fall Risk', 'color' => 'warning'],
    'hbc_handoff' => ['label' => 'HBC Handoff', 'color' => 'success'],
    'hbc_discharge' => ['label' => 'HBC Discharge', 'color' => 'success'],
    'hbc_comm_log' => ['label' => 'Comm Log', 'color' => 'success'],
    // Shared clinical
    'care_plan' => ['label' => 'Care Plan', 'color' => 'secondary'],
    'care_plan_launch' => ['label' => 'Care Plan', 'color' => 'secondary'],
    'clinical_notes' => ['label' => 'Clinical Notes', 'color' => 'secondary'],
    'clinical_notes_launch' => ['label' => 'Clinical Notes', 'color' => 'secondary'],
    'care_team' => ['label' => 'Care Team', 'color' => 'secondary'],
    'care_team_launch' => ['label' => 'Care Team', 'color' => 'secondary'],
    'observations' => ['label' => 'Observations', 'color' => 'primary'],
    // Other
    'trends' => ['label' => 'Trends', 'color' => 'primary'],
    'diversion' => ['label' => 'Diversion', 'color' => 'secondary'],
    'downtime' => ['label' => 'Downtime Mode', 'color' => 'secondary'],
    'smoke_test' => ['label' => 'Smoke Test', 'color' => 'secondary'],
    'intake' => ['label' => 'Intake', 'color' => 'secondary'],
    'triage' => ['label' => 'Triage', 'color' => 'secondary'],
    'context_manager' => ['label' => 'Contexts', 'color' => 'secondary'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= xlt('Care Context Manager') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href): ?>
        <link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
      /* ── Selectable context card ─────────────────────────────────── */
      .ctx-card {
        cursor: pointer;
        border: 1.5px solid #dee2e6;
        border-radius: .5rem;
        transition: border-color .12s ease, box-shadow .12s ease;
        height: 100%;
        display: block; /* label element fills the col */
        text-decoration: none;
        color: inherit;
      }

      .ctx-card:hover {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .15rem rgba(13, 110, 253, .12);
        text-decoration: none;
        color: inherit;
      }

      .ctx-card.ctx-selected {
        border-color: #0d6efd;
        box-shadow: 0 0 0 .2rem rgba(13, 110, 253, .18);
        background-color: #f5f8ff;
      }

      .ctx-card.ctx-selected .ctx-card-body {
        border-left: 3px solid #0d6efd;
        padding-left: calc(.75rem + 3px);
      }

      .ctx-card .ctx-card-body {
        padding: .85rem .75rem;
        border-radius: .375rem;
      }

      /* ── Radio indicator ─────────────────────────────────────────── */
      .ctx-radio {
        width: 18px;
        height: 18px;
        border: 2px solid #adb5bd;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: border-color .12s, background .12s;
      }

      .ctx-card.ctx-selected .ctx-radio {
        border-color: #0d6efd;
        background: #0d6efd;
      }

      .ctx-radio-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #fff;
        opacity: 0;
        transform: scale(0);
        transition: opacity .12s, transform .12s;
      }

      .ctx-card.ctx-selected .ctx-radio-dot {
        opacity: 1;
        transform: scale(1);
      }

      /* ── Feature tags ────────────────────────────────────────────── */
      .ctx-tags {
        gap: 3px;
      }

      .ctx-tag {
        font-size: .67rem;
        padding: .15em .45em;
      }

      /* ── Audience text ───────────────────────────────────────────── */
      .ctx-audience {
        font-size: .75rem;
        color: #6c757d;
      }

      /* ── Full Access card (row layout) ───────────────────────────── */
      .ctx-full-card .ctx-card-body {
        display: flex;
        align-items: center;
        gap: .85rem;
      }
    </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
    <div class="container-fluid py-4" style="max-width: 1060px;">

        <!-- Page header — same pattern as every other module page -->
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div>
                <h1 class="h4 mb-0"><?= xlt('Care Context Manager') ?></h1>
                <div class="text-muted small"><?= xlt('Facility') ?>: <?= htmlspecialchars($facilityName) ?> <span class="ms-2">#<?= (int)$facilityId ?></span></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary"
                    href="<?= htmlspecialchars($return) ?>"><?= xlt('Cancel') ?></a>
            </div>
        </div>

        <?php oei_render_context_help('context', ['facility_name' => $facilityName]); ?>

        <div class="alert alert-info py-2 mb-4">
            <small>
                <strong><?= xlt('Context is a display lens, not access control.') ?></strong>
                <?= xlt('Your selection surfaces the relevant submodules and menu items for your role. You can still navigate to any page you have permission for. Preference is saved per facility and restored at each login.') ?>
                <?= xlt('Only contexts enabled for this facility profile are shown.') ?>
            </small>
        </div>

        <form method="post" id="ctx-form">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
            <input type="hidden" name="facility_id" value="<?= (int)$facilityId ?>">
            <input type="hidden" name="context_key" id="selected-key" value="<?= htmlspecialchars($activeContext) ?>">

            <!-- Context cards -->
            <div class="row g-3 mb-3" role="radiogroup" aria-label="<?= xla('Care context') ?>">

                <?php foreach ($allContexts as $key => $meta):
                    $isSelected = ($key === $activeContext);
                    $isFullAccess = ($key === CareContext::FULL);
                    $ctxFeatures = (array)($meta['features'] ?? []);
                    $isWildcard = in_array('*', $ctxFeatures, true);

                    // Build feature tag list
                    $tags = [];
                    if (!$isWildcard) {
                        foreach ($ctxFeatures as $f) {
                            if (isset($featureMeta[$f]) && $manifest->featureEnabled($f)) {
                                $tags[] = $featureMeta[$f];
                            }
                        }
                    }
                    $tagsDisplay = array_slice($tags, 0, 14);
                    $hiddenCount = count($tags) - count($tagsDisplay);

                    $colClass = $isFullAccess ? 'col-12' : 'col-12 col-md-6 col-xl-3';
                    $cardClass = 'ctx-card'
                        . ($isSelected ? ' ctx-selected' : '')
                        . ($isFullAccess ? ' ctx-full-card' : '');
                    ?>

                    <div class="<?= $colClass ?>">
                        <label class="<?= $cardClass ?>"
                            data-key="<?= htmlspecialchars($key) ?>"
                            tabindex="0"
                            role="radio"
                            aria-checked="<?= $isSelected ? 'true' : 'false' ?>">
                            <input type="radio" class="d-none" name="_ctx_radio" value="<?= htmlspecialchars($key) ?>"
                                <?= $isSelected ? 'checked' : '' ?>>

                            <?php if ($isFullAccess): ?>

                                <!-- Full Access — horizontal layout -->
                                <div class="ctx-card-body">
            <span style="font-size:1.4rem;line-height:1;">
                <?= htmlspecialchars((string)($meta['icon'] ?? '')) ?>
            </span>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="fw-semibold"><?= htmlspecialchars((string)($meta['label'] ?? $key)) ?></span>
                                            <span class="badge text-bg-primary ctx-tag">
                  <?= xlt('All submodules') ?>
                </span>
                <span class="badge text-bg-light border ctx-tag">
                  <?= count(array_filter($manifest->features)) ?> <?= xlt('features enabled') ?>
                </span>
                                        </div>
                                        <div class="ctx-audience">
                                            <i class="bi bi-people me-1"></i><?= htmlspecialchars((string)($meta['audience'] ?? '')) ?>
                                        </div>
                                    </div>
                                    <div class="ctx-radio ms-2">
                                        <div class="ctx-radio-dot"></div>
                                    </div>
                                </div>

                            <?php else: ?>

                                <!-- Standard context card -->
                                <div class="ctx-card-body">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
              <span style="font-size:1.5rem;line-height:1;">
                <?= htmlspecialchars((string)($meta['icon'] ?? '')) ?>
              </span>
                                        <div class="ctx-radio">
                                            <div class="ctx-radio-dot"></div>
                                        </div>
                                    </div>

                                    <div class="fw-semibold mb-1">
                                        <?= htmlspecialchars((string)($meta['label'] ?? $key)) ?>
                                    </div>
                                    <div class="text-muted small mb-2">
                                        <?= htmlspecialchars((string)($meta['subtitle'] ?? '')) ?>
                                    </div>
                                    <div class="ctx-audience mb-3">
                                        <i class="bi bi-people me-1"></i><?= htmlspecialchars((string)($meta['audience'] ?? '')) ?>
                                    </div>

                                    <?php if (!empty($tagsDisplay)): ?>
                                        <div class="border-top pt-2 mt-auto">
                                            <div class="text-uppercase text-muted mb-1"
                                                style="font-size:.63rem;letter-spacing:.06em;"><?= xlt('Surfaces') ?></div>
                                            <div class="d-flex flex-wrap ctx-tags">
                                                <?php foreach ($tagsDisplay as $tag): ?>
                                                    <span class="badge text-bg-<?= htmlspecialchars($tag['color']) ?> ctx-tag">
                        <?= htmlspecialchars($tag['label']) ?>
                  </span>
                                                <?php endforeach; ?>
                                                <?php if ($hiddenCount > 0): ?>
                                                    <span class="badge text-bg-light border ctx-tag">+<?= $hiddenCount ?> more</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                </div>
                            <?php endif; ?>

                        </label>
                    </div>

                <?php endforeach; ?>

            </div><!-- /row -->

            <!-- Action bar — matches the pattern of card + d-flex footer used elsewhere -->
            <div class="card shadow-sm">
                <div class="card-body d-flex align-items-center justify-content-between py-3">
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted small"><?= xlt('Selected') ?>:</span>
                        <span class="fw-semibold" id="selected-label">
            <?= htmlspecialchars(
                (string)($allContexts[$activeContext]['icon'] ?? '')
                . ' '
                . (string)($allContexts[$activeContext]['label'] ?? $activeContext)
            ) ?>
          </span>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="<?= htmlspecialchars($return) ?>"
                            class="btn btn-sm btn-outline-secondary"><?= xlt('Cancel') ?></a>
                        <button type="submit" class="btn btn-sm btn-primary" id="submit-btn">
                            <i class="bi bi-check2-circle me-1"></i><?= xlt('Activate Context') ?>
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
        (function () {
            'use strict';

            var cards = document.querySelectorAll('.ctx-card');
            var hiddenInput = document.getElementById('selected-key');
            var label = document.getElementById('selected-label');

            var ctxLabels = {
                <?php foreach ($allContexts as $key => $meta): ?>
                <?= json_encode($key) ?>: <?= json_encode(
                    (string)($meta['icon'] ?? '') . ' ' . (string)($meta['label'] ?? $key)
                ) ?>,
                <?php endforeach; ?>
            };

            function selectCard(card) {
                if (!card) {
                    return;
                }
                var key = card.getAttribute('data-key');

                cards.forEach(function (c) {
                    c.classList.remove('ctx-selected');
                    c.setAttribute('aria-checked', 'false');
                });
                card.classList.add('ctx-selected');
                card.setAttribute('aria-checked', 'true');

                var radio = card.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }

                hiddenInput.value = key;
                if (label && ctxLabels[key]) {
                    label.textContent = ctxLabels[key];
                }
            }

            cards.forEach(function (card) {
                card.addEventListener('click', function () {
                    selectCard(card);
                });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        selectCard(card);
                    }
                    var arr = Array.from(cards);
                    var idx = arr.indexOf(card);
                    if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                        e.preventDefault();
                        var n = arr[(idx + 1) % arr.length];
                        n.focus();
                        selectCard(n);
                    }
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                        e.preventDefault();
                        var p = arr[(idx - 1 + arr.length) % arr.length];
                        p.focus();
                        selectCard(p);
                    }
                });
            });

            document.getElementById('ctx-form').addEventListener('submit', function () {
                var btn = document.getElementById('submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i><?= xlt('Activating...') ?>';
                }
            });
        }());
    </script>
</body>
</html>






















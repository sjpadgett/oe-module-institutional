<?php

/**
 * context_manager.php — Care Context Manager
 *
 * Allows users to select their active care setting, which governs which
 * submodules are surfaced in menus and the context bar.
 */
require_once __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;
use OpenEMR\Modules\Institutional\Core\Service\ContextService;

$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$return     = trim((string)($_GET['return'] ?? ''));

// Sanitise return URL
$return = filter_var($return, FILTER_SANITIZE_URL);
if ($return === false || !preg_match('#^/[^/]#', $return) || str_contains($return, '//')) {
    $return = '/interface/modules/custom_modules/oe-module-institutional/public/ed_board.php'
        . '?facility_id=' . $facilityId;
}

$ctxRepo = new ContextRepository();
$ctxSvc  = new ContextService($ctxRepo);

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
$allContexts   = CareContext::all();
$csrf          = CsrfUtils::collectCsrfToken();
$href          = institutional_bootstrap5_href($manifest);

// Feature -> short label + Bootstrap badge variant
$featureMeta = [
    'edt_board'         => ['label' => 'ED Board',        'color' => 'danger'],
    'intake'            => ['label' => 'Intake',           'color' => 'secondary'],
    'triage'            => ['label' => 'Triage',           'color' => 'secondary'],
    'tasks'             => ['label' => 'Tasks',            'color' => 'secondary'],
    'alerts'            => ['label' => 'Alerts',           'color' => 'warning'],
    'assignment'        => ['label' => 'Assignments',      'color' => 'secondary'],
    'handoff'           => ['label' => 'Handoff',          'color' => 'secondary'],
    'disposition'       => ['label' => 'Disposition',      'color' => 'secondary'],
    'transfer_tracking' => ['label' => 'Transfers',        'color' => 'secondary'],
    'bh_safety'         => ['label' => 'BH Safety',        'color' => 'warning'],
    'mar'               => ['label' => 'MAR',              'color' => 'secondary'],
    'ereferral'         => ['label' => 'eReferral',        'color' => 'secondary'],
    'obs_stay'          => ['label' => 'OBS Stay',         'color' => 'success'],
    'obs_protocols'     => ['label' => 'OBS Protocols',    'color' => 'success'],
    'obs_billing'       => ['label' => 'OBS Billing',      'color' => 'success'],
    'obs_episodes'      => ['label' => 'OBS Episodes',     'color' => 'success'],
    'cms_quality'       => ['label' => 'CMS Quality',      'color' => 'primary'],
    'bh_boarding'       => ['label' => 'BH Boarding',      'color' => 'warning'],
    'timeline'          => ['label' => 'Timeline',         'color' => 'secondary'],
    'episode_documents' => ['label' => 'Documents',        'color' => 'secondary'],
    'multi_facility'    => ['label' => 'Multi-Facility',   'color' => 'info'],
    'scorecard'         => ['label' => 'Scorecard',        'color' => 'primary'],
    'throughput'        => ['label' => 'Throughput',       'color' => 'primary'],
    'admin_exports'     => ['label' => 'Exports',          'color' => 'secondary'],
    'settings'          => ['label' => 'Settings',         'color' => 'secondary'],
    'facility_directory'=> ['label' => 'Directory',        'color' => 'secondary'],
    'hl7_adt'           => ['label' => 'HL7 ADT',          'color' => 'secondary'],
    'bed_mgmt'          => ['label' => 'Bed Board',        'color' => 'secondary'],
    'command_center'    => ['label' => 'Command Center',   'color' => 'primary'],
    'adt_lite'          => ['label' => 'ADT',              'color' => 'secondary'],
    'obs_start_picker'  => ['label' => 'OBS Start',        'color' => 'success'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Care Context Manager') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* ── Selectable context card ─────────────────────────────────── */
    .ctx-card {
        cursor: pointer;
        border: 1.5px solid #dee2e6;
        border-radius: .5rem;
        transition: border-color .12s ease, box-shadow .12s ease;
        height: 100%;
        display: block;        /* label element fills the col */
        text-decoration: none;
        color: inherit;
    }
    .ctx-card:hover {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .15rem rgba(13,110,253,.12);
        text-decoration: none;
        color: inherit;
    }
    .ctx-card.ctx-selected {
        border-color: #0d6efd;
        box-shadow: 0 0 0 .2rem rgba(13,110,253,.18);
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
    .ctx-tags { gap: 3px; }
    .ctx-tag  { font-size: .67rem; padding: .15em .45em; }

    /* ── Audience text ───────────────────────────────────────────── */
    .ctx-audience { font-size: .75rem; color: #6c757d; }

    /* ── Full Access card (row layout) ───────────────────────────── */
    .ctx-full-card .ctx-card-body {
        display: flex;
        align-items: center;
        gap: .85rem;
    }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-4" style="max-width: 1060px;">

  <!-- Page header — same pattern as every other module page -->
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Care Context Manager') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary"
         href="<?= htmlspecialchars($return) ?>"><?= xlt('Cancel') ?></a>
    </div>
  </div>

  <div class="alert alert-info py-2 mb-4">
    <small>
      <strong><?= xlt('Context is a display lens, not access control.') ?></strong>
      <?= xlt('Your selection surfaces the relevant submodules and menu items for your role. You can still navigate to any page you have permission for. Preference is saved per facility and restored at each login.') ?>
    </small>
  </div>

  <form method="post" id="ctx-form">
    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
    <input type="hidden" name="return"          value="<?= htmlspecialchars($return) ?>">
    <input type="hidden" name="context_key"     id="selected-key" value="<?= htmlspecialchars($activeContext) ?>">

    <!-- Context cards -->
    <div class="row g-3 mb-3" role="radiogroup" aria-label="<?= xla('Care context') ?>">

      <?php foreach ($allContexts as $key => $meta):
        $isSelected   = ($key === $activeContext);
        $isFullAccess = ($key === CareContext::FULL);
        $ctxFeatures  = (array)($meta['features'] ?? []);
        $isWildcard   = in_array('*', $ctxFeatures, true);

        // Build feature tag list
        $tags = [];
        if (!$isWildcard) {
            foreach ($ctxFeatures as $f) {
                if (isset($featureMeta[$f]) && $manifest->featureEnabled($f)) {
                    $tags[] = $featureMeta[$f];
                }
            }
        }
        $tagsDisplay = array_slice($tags, 0, 9);
        $hiddenCount = count($tags) - count($tagsDisplay);

        $colClass  = $isFullAccess ? 'col-12' : 'col-12 col-md-6 col-xl-3';
        $cardClass = 'ctx-card'
            . ($isSelected   ? ' ctx-selected' : '')
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
                <span class="badge text-bg-secondary ctx-tag">
                  <?= xlt('All submodules') ?>
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

    var cards       = document.querySelectorAll('.ctx-card');
    var hiddenInput = document.getElementById('selected-key');
    var label       = document.getElementById('selected-label');

    var ctxLabels = {
        <?php foreach ($allContexts as $key => $meta): ?>
        <?= json_encode($key) ?>: <?= json_encode(
            (string)($meta['icon'] ?? '') . ' ' . (string)($meta['label'] ?? $key)
        ) ?>,
        <?php endforeach; ?>
    };

    function selectCard(card) {
        if (!card) { return; }
        var key = card.getAttribute('data-key');

        cards.forEach(function (c) {
            c.classList.remove('ctx-selected');
            c.setAttribute('aria-checked', 'false');
        });
        card.classList.add('ctx-selected');
        card.setAttribute('aria-checked', 'true');

        var radio = card.querySelector('input[type="radio"]');
        if (radio) { radio.checked = true; }

        hiddenInput.value = key;
        if (label && ctxLabels[key]) { label.textContent = ctxLabels[key]; }
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () { selectCard(card); });
        card.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault(); selectCard(card);
            }
            var arr = Array.from(cards);
            var idx = arr.indexOf(card);
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                var n = arr[(idx + 1) % arr.length]; n.focus(); selectCard(n);
            }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                var p = arr[(idx - 1 + arr.length) % arr.length]; p.focus(); selectCard(p);
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

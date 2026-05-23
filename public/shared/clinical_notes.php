<?php

/**
 * public/shared/clinical_notes.php
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
 * public/shared/clinical_notes.php — Clinical Notes panel / list
 *
 * Works for all episode types (AL, IP, ED, OBS, BH).
 * Displays form_clinical_notes entries for the episode's encounter.
 * New notes are created via the native OE form (launch button).
 *
 * Requires: ?episode_id=<n>  (pid + type auto-resolved from oei_episode)
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Controller\ClinicalNotesController;

if (!$manifest->featureEnabled('clinical_notes')) {
    oei_exit_with_alert(xlt('Clinical Notes is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;

$episodeType = 'ED';
if ($episodeId > 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid, type FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    if ($epRow) {
        if ($pid === 0) { $pid = (int)$epRow['pid']; }
        $episodeType = strtoupper((string)($epRow['type'] ?? 'ED'));
    }
}

if ($episodeId === 0 || $pid === 0) {
    header('Location: ../ed_board.php?facility_id=' . $facilityId . '&notice=select_patient');
    exit;
}

// ── IP context: absolute base URLs ────────────────────────────────────────
$_oei_ip_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

$controller = new ClinicalNotesController();
$data       = $controller->handlePage($episodeId, $episodeType, $pid);

$pageTitle  = xlt('Clinical Notes');
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$activePage = 'clinical_notes';
$backUrl    = match ($episodeType) {
    'IP'  => $_oei_ip_base  . 'profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'AL'  => $_oei_pub_base . 'al/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'HBC' => $_oei_pub_base . 'hbc/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    default => $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId,
};

// Badge color helper (inline because we need it in the template)
$badgeColors = [
    'primary' => 'bg-primary', 'info' => 'bg-info text-dark',
    'warning' => 'bg-warning text-dark', 'success' => 'bg-success',
    'danger'  => 'bg-danger',  'dark'  => 'bg-dark',
    'secondary'=> 'bg-secondary',
];
$getBadge = static fn(string $color): string => $badgeColors[$color] ?? 'bg-secondary';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .note-row { cursor:pointer; transition:background .12s; }
    .note-row:hover { background:rgba(0,0,0,.04); }
    .note-body { display:none; white-space:pre-wrap; font-size:.85rem; }
    .note-row.open .note-body { display:block; }
    .no-encounter-banner { border-left:4px solid #ffc107; background:#fff8e1; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php if ($episodeType === 'IP'): ?>
    <?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>
<?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h5 class="mb-0">📝 <?= xlt('Clinical Notes') ?>
    <span class="badge bg-secondary ms-2 fs-6">
      <?= xlt('Episode') ?> #<?= $episodeId ?> &bull; <?= htmlspecialchars($episodeType) ?>
    </span>
  </h5>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($manifest->featureEnabled('clinical_notes_launch') && $data['has_encounter']): ?>
    <a href="<?= htmlspecialchars($data['launch_url']) ?>" target="oe_form"
       class="btn btn-sm btn-primary">
      + <?= xlt('Add Note') ?>
    </a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
      ← <?= xlt('Back') ?>
    </a>
  </div>
</div>

<?php if (!$data['has_encounter']): ?>
<div class="p-3 mb-3 rounded no-encounter-banner">
  <strong>⚠ <?= xlt('No encounter linked to this episode.') ?></strong>
  <div class="small mt-1 text-muted">
    <?= xlt('Clinical notes require an active OpenEMR encounter number. Create one in the patient chart first.') ?>
  </div>
</div>
<?php endif; ?>

<!-- Notes list -->
<?php if (empty($data['notes'])): ?>
<div class="card">
  <div class="card-body text-muted text-center py-5">
    <?php if ($data['has_encounter']): ?>
        <?= xlt('No clinical notes recorded for this episode.') ?>
        <?php if ($manifest->featureEnabled('clinical_notes_launch')): ?>
    <div class="mt-2">
      <a href="<?= htmlspecialchars($data['launch_url']) ?>" target="oe_form" class="btn btn-sm btn-primary">
        + <?= xlt('Add the first note') ?>
      </a>
    </div>
    <?php endif; ?>
    <?php else: ?>
        <?= xlt('Link an encounter to begin recording clinical notes.') ?>
    <?php endif; ?>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="list-group list-group-flush">
    <?php foreach ($data['notes'] as $note): ?>
        <?php $badgeClass = $getBadge($note['type_badge']); ?>
    <div class="list-group-item note-row p-0"
         onclick="this.classList.toggle('open')">
      <div class="d-flex align-items-start gap-2 p-3">
        <!-- Type badge -->
        <span class="badge <?= $badgeClass ?> flex-shrink-0 mt-1" style="min-width:100px;text-align:center;">
          <?= htmlspecialchars($note['type_label']) ?>
        </span>
        <!-- Main content -->
        <div class="flex-grow-1 overflow-hidden">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-1">
            <span class="small text-muted">
              <?= htmlspecialchars(date('M j, Y H:i', strtotime($note['last_updated']))) ?>
              &bull; <?= htmlspecialchars($note['user']) ?>
              <?php if ($note['clinical_notes_category']): ?>
              &bull; <em><?= htmlspecialchars($note['clinical_notes_category']) ?></em>
              <?php endif; ?>
            </span>
            <div class="d-flex gap-1 flex-shrink-0">
              <?php if ($manifest->featureEnabled('clinical_notes_documents') && $note['doc_count'] > 0): ?>
              <span class="badge bg-light text-dark border">
                📎 <?= $note['doc_count'] ?>
              </span>
              <?php endif; ?>
              <?php if ($manifest->featureEnabled('clinical_notes_results') && $note['result_count'] > 0): ?>
              <span class="badge bg-light text-dark border">
                🧪 <?= $note['result_count'] ?>
              </span>
              <?php endif; ?>
              <?php if ($manifest->featureEnabled('clinical_notes_launch') && $data['has_encounter']): ?>
              <a href="<?= htmlspecialchars($data['edit_base_url'] . $note['form_id']) ?>"
                 target="oe_form" class="btn btn-xs btn-outline-secondary"
                 style="font-size:.72rem;padding:.1rem .35rem;"
                 onclick="event.stopPropagation()">
                    <?= xlt('Edit') ?>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="mt-1 small"><?= htmlspecialchars($note['excerpt']) ?></div>
          <!-- Expanded body (shown on click) -->
          <div class="note-body mt-2 text-break">
            <?= htmlspecialchars($note['description'] ?? '') ?>
            <?php if ($note['note_related_to']): ?>
            <div class="text-muted mt-2 small">
              <strong><?= xlt('Related to') ?>:</strong>
                <?= htmlspecialchars($note['note_related_to']) ?>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mt-3">
  <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Back') ?>
  </a>
</div>

<?= institutional_bootstrap5_js_tag() ?>
</div>
</body>
</html>















<?php

/**
 * public/ip/discharge.php
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
 * public/ip/discharge.php — Inpatient Discharge / Transfer Planning
 *
 * Two-stage workflow (mirrors al/discharge.php):
 *   Stage 1 — Plan:    Record disposition code, destination, expected
 *                      discharge date, discharge summary, notes.
 *                      Episode stays ACTIVE.
 *   Stage 2 — Confirm: Record actual discharge datetime.
 *                      Episode → CLOSED, HL7 A03 fires.
 *
 * IP-specific disposition codes:
 *   DISCHARGE_HOME · SNF · REHAB · HOSPICE · TRANSFER · AMA · EXPIRED
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpDischarge\Controller\IpDischargeController;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpDischarge\Repository\IpDischargeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;

if (!$manifest->featureEnabled('ip_discharge')) {
    oei_exit_with_alert(xlt('Inpatient Discharge Planning is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_oei_ip_base  = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';

if ($episodeId === 0) {
    header('Location: ' . $_oei_ip_base . 'board.php?facility_id=' . $facilityId);
    exit;
}

// Resolve pid from episode if missing
if ($pid === 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    $pid   = (int)($epRow['pid'] ?? 0);
}

$controller = new IpDischargeController(
    new IpDischargeRepository(),
    new EpisodeRepository(),
    new EpisodeEventRepository()
);
$data = $controller->handle($episodeId, $pid, $facilityId, $userId);

$h      = $data['header'];
$plan   = $data['plan'];
$closed = $data['closed'];
$codes  = $data['codes'];

if (!$h) {
    oei_exit_with_alert(xlt('Inpatient episode not found.'), 'danger');
}

$activePage  = 'discharge';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

// Pre-populate nav to avoid a second DB round-trip
$ipNavPatient = $h;

$currentCode = (string)($plan['disposition_code'] ?? '');
$codeInfo    = $codes[$currentCode] ?? null;
$isExpired   = ($currentCode === 'EXPIRED');
$losDays     = (int)($h['los_days'] ?? 0);
$losExpected = ($h['expected_los_days'] !== null) ? (int)$h['expected_los_days'] : null;

$pageTitle = xlt('Discharge / Transfer Planning');

// Read facility settings for target-hour indicator
$_discSettings     = (new SettingsRepository())->all($facilityId);
$_targetHour       = (int)($_discSettings['ip_discharge_target_hour'] ?? 11);
$_losWarningHours  = (int)($_discSettings['ip_los_warning_hours'] ?? 24);
// Format as 12h for display
$_targetHourLabel  = date('g:i A', mktime($_targetHour, 0, 0));
// Show countdown only on active (not closed) episodes
$_showTargetBadge  = !$closed && !$isExpired;
$_targetToday      = date('Y-m-d') . ' ' . sprintf('%02d:00:00', $_targetHour);
$_minsToTarget     = (int)((strtotime($_targetToday) - time()) / 60);
// Negative = past target hour today; next target = tomorrow same time
$_targetStatus     = 'ok';
if ($_minsToTarget < 0)                    { $_targetStatus = 'past'; }
elseif ($_minsToTarget <= 60)              { $_targetStatus = 'urgent'; }
elseif ($_minsToTarget <= $_losWarningHours * 60) { $_targetStatus = 'warning'; }
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
  <style>
    .discharge-card         { border-left: 4px solid #6c757d; }
    .discharge-card.planned { border-left-color: #fd7e14; }
    .discharge-card.closed  { border-left-color: #198754; }
    .expired-zone           { border: 2px solid #6c757d; background: var(--bs-secondary-bg); }
    .stage-number  { display:inline-flex; align-items:center; justify-content:center;
                     width:26px; height:26px; border-radius:50%;
                     font-size:.78rem; font-weight:700; flex-shrink:0; }
    .stage-done    { background:#198754; color:#fff; }
    .stage-open    { background:var(--bs-secondary-bg); color:var(--bs-body-color);
                     border:1px solid var(--bs-border-color); }
    .stage-active  { background:#0d6efd; color:#fff; }
    .code-grid     { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:.5rem; }
    .code-option   { padding:.5rem .75rem; border:1px solid var(--bs-border-color);
                     border-radius:.375rem; cursor:pointer; transition:all .12s; }
    .code-option:hover    { border-color:#0d6efd; background:var(--bs-secondary-bg); }
    .code-option.selected { border-color:#fd7e14; background:var(--bs-warning-bg-subtle); }
    .code-option.sel-expired { border-color:#6c757d; background:var(--bs-secondary-bg); }
    .los-banner    { background:var(--bs-info-bg-subtle); border:1px solid var(--bs-info-border-subtle);
                     border-radius:.375rem; }
    .closed-banner { background:var(--bs-secondary-bg); border:1px solid var(--bs-border-color);
                     border-radius:.5rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid px-3 pt-2">

<?php require __DIR__ . '/../../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>

<?php if ($data['flash']): ?>
<div class="alert alert-success alert-dismissible py-2 mx-1" role="alert">
  ✔ <?= htmlspecialchars($data['flash']) ?>
    <?php if ($closed): ?>
    &nbsp;·&nbsp;
    <a href="<?= htmlspecialchars($_oei_ip_base) ?>board.php?facility_id=<?= $facilityId ?>">
      ← <?= xlt('Return to Board') ?>
    </a>
  <?php endif; ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($data['error']): ?>
<div class="alert alert-danger py-2 mx-1" role="alert">
  ⚠ <?= htmlspecialchars($data['error']) ?>
</div>
<?php endif; ?>

<?php if ($closed): ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- CLOSED STATE                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="closed-banner p-4 mx-1 mt-2">
  <div class="d-flex align-items-center gap-3 mb-3">
    <span class="fs-2"><?= htmlspecialchars($codeInfo['icon'] ?? '✓') ?></span>
    <div>
      <h5 class="mb-0 fw-bold">
        <?= $isExpired
            ? xlt('Patient Expired — Episode Closed')
            : xlt('Episode Closed — Patient Discharged') ?>
      </h5>
      <div class="text-muted small">
        <?= xlt('Disposition') ?>:
        <strong><?= htmlspecialchars($codeInfo['label'] ?? $currentCode) ?></strong>
        <?php if ($plan['destination'] ?? ''): ?>
          &nbsp;→&nbsp; <?= htmlspecialchars($plan['destination']) ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="row g-3 small">
    <div class="col-sm-3">
      <span class="text-muted"><?= xlt('Admitted') ?></span><br>
      <strong><?= htmlspecialchars(date('M j, Y', strtotime($h['start_datetime']))) ?></strong>
    </div>
    <div class="col-sm-3">
      <span class="text-muted"><?= $isExpired ? xlt('Date of Death') : xlt('Discharged') ?></span><br>
      <strong>
        <?= $plan['depart_datetime']
            ? htmlspecialchars(date('M j, Y H:i', strtotime($plan['depart_datetime'])))
            : '—' ?>
      </strong>
    </div>
    <div class="col-sm-3">
      <span class="text-muted"><?= xlt('Length of Stay') ?></span><br>
      <strong><?= $losDays ?> <?= xlt('days') ?></strong>
    </div>
    <div class="col-sm-3">
      <span class="text-muted"><?= xlt('Service') ?></span><br>
      <strong><?= htmlspecialchars(HospitalService::label($h['service'])) ?></strong>
    </div>
  </div>

    <?php if ($plan['notes'] ?? ''): ?>
  <div class="mt-3 p-2 rounded" style="background:var(--bs-tertiary-bg)">
    <span class="text-muted small"><?= xlt('Notes') ?></span><br>
    <span class="small"><?= nl2br(htmlspecialchars($plan['notes'])) ?></span>
  </div>
  <?php endif; ?>

    <?php if (!empty($h['discharge_summary'])): ?>
  <div class="mt-3 p-2 rounded border">
    <span class="text-muted small fw-semibold"><?= xlt('Discharge Summary') ?></span><br>
    <span class="small"><?= nl2br(htmlspecialchars($h['discharge_summary'])) ?></span>
  </div>
  <?php endif; ?>

  <div class="mt-3">
    <a href="<?= htmlspecialchars($_oei_ip_base) ?>board.php?facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-secondary">
      ← <?= xlt('Board') ?>
    </a>
    <a href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
       class="btn btn-sm btn-outline-secondary ms-2">
      <?= xlt('Profile') ?>
    </a>
  </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════════════════ -->
<!-- ACTIVE STATE — two-stage planning UI                                  -->
<!-- ══════════════════════════════════════════════════════════════════════ -->

<!-- Patient summary banner -->
<div class="los-banner d-flex align-items-center gap-3 p-3 mx-1 mb-3 flex-wrap">
  <div class="flex-grow-1">
    <span class="fw-semibold"><?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?></span>
    <span class="text-muted ms-1 small"><?= (int)$h['age'] ?>y</span>
    <span class="ms-2 badge text-bg-secondary"><?= htmlspecialchars($h['unit'] . ($h['bed'] ? ' / ' . $h['bed'] : '')) ?></span>
    <span class="ms-1 badge <?= HospitalService::badgeClass($h['service']) ?>">
      <?= htmlspecialchars(HospitalService::label($h['service'])) ?>
    </span>
  </div>
  <div class="text-end small text-muted">
    <?= xlt('Day') ?> <strong><?= $losDays ?></strong>
    <?php if ($losExpected !== null): ?>
      / <?= $losExpected ?> <?= xlt('expected') ?>
    <?php endif; ?>
    &nbsp;·&nbsp; <?= xlt('Admitted') ?>
    <?= htmlspecialchars(date('M j, Y', strtotime($h['start_datetime']))) ?>
  </div>
  <?php if ($_showTargetBadge): ?>
  <div class="text-end">
    <?php
    $badgeCls = match ($_targetStatus) {
        'urgent'  => 'text-bg-danger',
        'warning' => 'text-bg-warning text-dark',
        'past'    => 'text-bg-secondary',
        default   => 'text-bg-light text-dark border',
    };
    $minsAbs = abs($_minsToTarget);
    $label   = $_targetStatus === 'past'
        ? xlt('Target passed') . ' ' . ($minsAbs < 60 ? $minsAbs . xlt('m ago') : intdiv($minsAbs,60) . xlt('h ago'))
        : xlt('Target') . ' ' . $_targetHourLabel . ' (' . ($minsAbs < 60 ? $minsAbs . xlt('m') : intdiv($minsAbs,60) . 'h ' . ($minsAbs%60) . 'm') . ')';
    ?>
    <span class="badge <?= $badgeCls ?> small">
      🕐 <?= htmlspecialchars($label) ?>
    </span>
    <div class="text-muted" style="font-size:.68rem;"><?= xlt('Facility discharge target') ?></div>
  </div>
  <?php endif; ?>
</div>

<div class="row g-3 mx-0">

  <!-- ─── Stage 1: Plan ────────────────────────────────────────────────── -->
  <div class="col-lg-7">
    <div class="card discharge-card <?= $plan ? 'planned' : '' ?>">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="stage-number <?= $plan ? 'stage-done' : 'stage-active' ?>">1</span>
        <strong><?= xlt('Discharge / Transfer Plan') ?></strong>
        <?php if ($plan): ?>
        <span class="badge bg-warning text-dark ms-auto">
            <?= htmlspecialchars($codes[$currentCode]['icon'] ?? '') ?>
            <?= htmlspecialchars($codes[$currentCode]['label'] ?? $currentCode) ?>
        </span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="POST" id="planForm"
              action="<?= htmlspecialchars($_oei_ip_base) ?>discharge.php?facility_id=<?= urlencode((string)$facilityId) ?>">
          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
          <input type="hidden" name="action"     value="save_plan">
          <input type="hidden" name="episode_id" value="<?= $episodeId ?>">

          <!-- Disposition code picker -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              <?= xlt('Disposition Type') ?> <span class="text-danger">*</span>
            </label>
            <div class="code-grid" id="codeGrid">
              <?php foreach ($codes as $codeKey => $cInfo): ?>
                    <?php
                    $isSelected = ($codeKey === $currentCode);
                    $selClass   = $isSelected
                      ? ($codeKey === 'EXPIRED' ? 'sel-expired' : 'selected')
                      : '';
                    ?>
                <label class="code-option <?= $selClass ?>"
                       data-code="<?= htmlspecialchars($codeKey) ?>">
                  <input type="radio" name="disposition_code"
                         value="<?= htmlspecialchars($codeKey) ?>"
                         class="d-none"
                         <?= $isSelected ? 'checked' : '' ?>>
                  <div class="fw-semibold small">
                    <?= htmlspecialchars($cInfo['icon']) ?>
                    <?= htmlspecialchars(xlt($cInfo['label'])) ?>
                  </div>
                </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Destination -->
          <div class="mb-3" id="destinationRow">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              <?= xlt('Receiving Facility / Destination') ?>
            </label>
            <input type="text" name="destination" class="form-control"
                   placeholder="<?= xlt('e.g. Valley SNF, 123 Main St') ?>"
                   value="<?= htmlspecialchars($plan['destination'] ?? '') ?>">
          </div>

          <!-- Decision datetime -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              <?= xlt('Discharge Decision Date/Time') ?>
            </label>
            <?php
              $decVal = !empty($plan['decision_datetime'])
                  ? date('Y-m-d\TH:i', strtotime($plan['decision_datetime']))
                  : date('Y-m-d\TH:i');
            ?>
            <input type="datetime-local" name="decision_datetime" class="form-control"
                   value="<?= htmlspecialchars($decVal) ?>">
          </div>

          <!-- Discharge summary narrative -->
          <div class="mb-3" id="summaryRow">
            <label class="form-label fw-semibold small text-uppercase text-muted">
              <?= xlt('Discharge Summary') ?>
              <span class="text-muted fw-normal">(<?= xlt('clinical narrative of the stay') ?>)</span>
            </label>
            <textarea name="discharge_summary" class="form-control" rows="4"
                      placeholder="<?= xlt('Hospital course, procedures performed, key findings, follow-up instructions…') ?>"><?= htmlspecialchars($h['discharge_summary'] ?? '') ?></textarea>
          </div>

          <!-- Notes / documentation -->
          <div class="mb-3">
            <label class="form-label fw-semibold small text-uppercase text-muted" id="notesLabel">
              <?= xlt('Notes') ?>
            </label>
            <textarea name="notes" class="form-control" rows="2"
                      id="notesField"
                      placeholder="<?= xlt('Pending orders, follow-up appointments, family notification…') ?>"><?= htmlspecialchars($plan['notes'] ?? '') ?></textarea>
            <div class="form-text text-danger d-none" id="expiredNoteHint">
              <?= xlt('Required: circumstances, time of death, attending physician name.') ?>
            </div>
          </div>

          <!-- EXPIRED solemn warning -->
          <div class="expired-zone rounded p-3 mb-3 d-none" id="expiredZone">
            <div class="fw-semibold">✝ <?= xlt('Death During Admission — Documentation') ?></div>
            <div class="small text-muted mt-1">
              <?= xlt('Recording this will permanently close the episode when confirmed. Notes must include time of death, circumstances, and attending physician name.') ?>
            </div>
          </div>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-warning btn-sm fw-semibold">
              💾 <?= xlt('Save Plan') ?>
            </button>
            <a href="<?= htmlspecialchars($_oei_ip_base) ?>profile.php?episode_id=<?= $episodeId ?>&pid=<?= $pid ?>&facility_id=<?= $facilityId ?>"
               class="btn btn-sm btn-outline-secondary">
              ← <?= xlt('Profile') ?>
            </a>
          </div>
        </form>
      </div>
    </div>
  </div><!-- /Stage 1 -->

  <!-- ─── Stage 2: Confirm discharge ──────────────────────────────────── -->
  <div class="col-lg-5">
    <div class="card discharge-card <?= ($plan && $plan['depart_datetime']) ? 'closed' : '' ?>">
      <div class="card-header d-flex align-items-center gap-2">
        <span class="stage-number <?= !$plan ? 'stage-open' : 'stage-active' ?>">2</span>
        <strong>
          <?= $isExpired
              ? xlt('Confirm Death')
              : xlt('Confirm Discharge') ?>
        </strong>
        <?php if (!$plan): ?>
          <span class="badge bg-secondary ms-auto small"><?= xlt('Complete Stage 1 first') ?></span>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (!$plan): ?>
          <p class="text-muted small mb-0">
            <?= xlt('Save a discharge plan above before confirming the actual discharge.') ?>
          </p>
        <?php else: ?>
          <p class="text-muted small mb-3">
            <?= $isExpired
                ? xlt('Record date and time of death. This permanently closes the episode.')
                : xlt('Record the actual discharge date/time. This closes the episode and fires the HL7 A03 Discharge event.') ?>
          </p>

          <form method="POST" id="confirmForm"
                action="<?= htmlspecialchars($_oei_ip_base) ?>discharge.php?facility_id=<?= urlencode((string)$facilityId) ?>"
                onsubmit="return confirmDischarge(this)">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($data['csrf']) ?>">
            <input type="hidden" name="action"     value="confirm_departure">
            <input type="hidden" name="episode_id" value="<?= $episodeId ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold small text-uppercase text-muted">
                <?= $isExpired ? xlt('Date / Time of Death') : xlt('Actual Discharge Date/Time') ?>
                <span class="text-danger">*</span>
              </label>
              <?php
                $departVal = !empty($plan['depart_datetime'])
                    ? date('Y-m-d\TH:i', strtotime($plan['depart_datetime']))
                    : date('Y-m-d\TH:i');
                ?>
              <input type="datetime-local" name="depart_datetime"
                     class="form-control" required
                     value="<?= htmlspecialchars($departVal) ?>">
            </div>

            <div class="d-grid">
              <?php if ($isExpired): ?>
              <button type="submit" class="btn btn-secondary fw-semibold">
                ✝ <?= xlt('Record Death & Close Episode') ?>
              </button>
              <?php else: ?>
              <button type="submit" class="btn btn-danger fw-semibold">
                🚪 <?= xlt('Confirm Discharge & Close Episode') ?>
              </button>
              <?php endif; ?>
            </div>
          </form>

            <?php if ($plan['updated_by_fname'] ?? ''): ?>
          <div class="text-muted small mt-3">
                <?= xlt('Last updated by') ?>
                <?= htmlspecialchars($plan['updated_by_fname'] . ' ' . $plan['updated_by_lname']) ?>
                <?php if ($plan['updated_datetime'] ?? ''): ?>
                    <?= xlt('on') ?>
                    <?= htmlspecialchars(date('M j, Y H:i', strtotime($plan['updated_datetime']))) ?>
            <?php endif; ?>
          </div>
          <?php endif; ?>
        <?php endif; // $plan exists ?>
      </div>
    </div>

    <!-- What happens on close info card -->
    <div class="card mt-3 border-0 small text-muted" style="background:var(--bs-tertiary-bg)">
      <div class="card-body py-2">
        <div class="fw-semibold mb-1"><?= xlt('What happens when the episode closes') ?></div>
        <ul class="mb-0 ps-3">
          <li><?= xlt('Patient removed from Floor Board') ?></li>
          <li><?= xlt('HL7 A03 Discharge event sent to ADT listener') ?></li>
          <li><?= xlt('Episode status set to CLOSED with discharge timestamp') ?></li>
          <li><?= xlt('Clinical record, care plan, and MAR data preserved') ?></li>
        </ul>
      </div>
    </div>

    <!-- E-Referral shortcut if disposition warrants one -->
    <?php if ($plan && in_array($currentCode, ['SNF','REHAB','HOSPICE','TRANSFER'], true)
              && $manifest->featureEnabled('ereferral')): ?>
    <div class="card mt-3 border-info">
      <div class="card-body py-2">
        <div class="small fw-semibold mb-1">📤 <?= xlt('E-Referral') ?></div>
        <div class="small text-muted mb-2">
                  <?= xlt('Generate a referral packet for the receiving facility.') ?>
        </div>
        <a href="<?= htmlspecialchars($_oei_pub_base) ?>ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= $episodeId ?>"
           class="btn btn-sm btn-outline-info">
          ↗ <?= xlt('Open E-Referral') ?>
        </a>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /Stage 2 -->
</div><!-- /row -->
<?php endif; // !closed ?>
</div><!-- /container -->

<script>
document.querySelectorAll('.code-option').forEach(function(label) {
    label.addEventListener('click', function() {
        document.querySelectorAll('.code-option').forEach(function(l) {
            l.classList.remove('selected', 'sel-expired');
        });
        const code = this.dataset.code;
        this.classList.add(code === 'EXPIRED' ? 'sel-expired' : 'selected');
        this.querySelector('input[type=radio]').checked = true;
        updateExpiredUi(code === 'EXPIRED');
    });
});

function updateExpiredUi(isExpired) {
    const zone     = document.getElementById('expiredZone');
    const hint     = document.getElementById('expiredNoteHint');
    const label    = document.getElementById('notesLabel');
    const destRow  = document.getElementById('destinationRow');
    const sumRow   = document.getElementById('summaryRow');
    if (!zone) return;
    if (isExpired) {
        zone.classList.remove('d-none');
        hint.classList.remove('d-none');
        label.textContent = '<?= xlt('Notes (death documentation)') ?> *';
        destRow.classList.add('d-none');
        sumRow.classList.add('d-none');
    } else {
        zone.classList.add('d-none');
        hint.classList.add('d-none');
        label.textContent = '<?= xlt('Notes') ?>';
        destRow.classList.remove('d-none');
        sumRow.classList.remove('d-none');
    }
}

(function() {
    const checked = document.querySelector('input[name="disposition_code"]:checked');
    if (checked) updateExpiredUi(checked.value === 'EXPIRED');
})();

function confirmDischarge(form) {
    const isExpired = document.querySelector('input[name="disposition_code"]:checked')?.value === 'EXPIRED';
    const name = '<?= addslashes(htmlspecialchars($h['fname'] . ' ' . $h['lname'])) ?>';
    const msg = isExpired
        ? '<?= xlt('Record death for') ?> ' + name + '?\n\n<?= xlt('This permanently closes the episode and cannot be undone.') ?>'
        : '<?= xlt('Confirm discharge for') ?> ' + name + '?\n\n<?= xlt('This closes the episode and fires the HL7 A03 Discharge event.') ?>';
    return confirm(msg);
}
</script>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>










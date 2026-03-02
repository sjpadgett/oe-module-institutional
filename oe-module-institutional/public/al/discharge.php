<?php
/**
 * public/al/discharge.php — AL Discharge / Transfer Planning
 *
 * Two-stage workflow:
 *   Stage 1 — Plan:    Record disposition code, destination, decision date, notes.
 *                       Episode stays ACTIVE. Board shows a pending departure badge.
 *   Stage 2 — Confirm: Record actual depart_datetime.
 *                       Episode closes (status → CLOSED) and HL7 A03 fires.
 *
 * Accessible from:
 *   • Board card footer (🚪 button, carries episode_id + pid)
 *   • Resident nav tab bar on any sub-page
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Controller\AlDischargeController;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Repository\AlDischargeRepository;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

if (!$manifest->featureEnabled('al_discharge')) {
    oei_exit_with_alert(xlt('Discharge Planning is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

if ($episodeId === 0) {
    header('Location: board.php?facility_id=' . $facilityId . '&notice=select_resident');
    exit;
}

// Resolve pid from episode if missing
if ($pid === 0 && function_exists('sqlQuery')) {
    $epRow = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
    $pid   = (int)($epRow['pid'] ?? 0);
}

$controller = new AlDischargeController(
    new AlDischargeRepository(),
    new EpisodeRepository(),
    new EpisodeEventRepository()
);
$data = $controller->handle($episodeId, $pid, $facilityId, $userId);

$h      = $data['header'];
$plan   = $data['plan'];
$closed = $data['closed'];
$codes  = $data['codes'];

if (!$h) {
    oei_exit_with_alert(htmlspecialchars(xlt('Resident episode not found.')), 'danger');
}

$activePage  = 'discharge';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

// Pre-populate nav resident data to avoid a second DB round-trip
$alNavResident = [
    'fname'           => $h['fname'],
    'lname'           => $h['lname'],
    'pid'             => $h['pid'],
    'room'            => $h['room'],
    'unit'            => $h['unit'],
    'care_level'      => $h['care_level'],
    'fall_risk_level' => $h['fall_risk_level'],
];

$currentCode = (string)($plan['disposition_code'] ?? '');
$codeInfo    = $codes[$currentCode] ?? null;
$isDeceased  = $currentCode === 'DECEASED';
$isPending   = in_array($currentCode, $data['pending_codes'], true);
$losDays     = (int)($h['days_resident'] ?? 0);

$pageTitle   = xlt('Discharge / Transfer Planning');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .discharge-card         { border-left: 4px solid #6c757d; }
    .discharge-card.planned { border-left-color: #fd7e14; }
    .discharge-card.closed  { border-left-color: #6c757d; }
    .deceased-zone          { border: 2px solid #6c757d; background: var(--bs-secondary-bg); }
    .stage-number { display: inline-flex; align-items: center; justify-content: center;
                    width: 26px; height: 26px; border-radius: 50%;
                    font-size: .78rem; font-weight: 700; flex-shrink: 0; }
    .stage-done  { background: #198754; color: #fff; }
    .stage-open  { background: var(--bs-secondary-bg); color: var(--bs-body-color);
                   border: 1px solid var(--bs-border-color); }
    .stage-active{ background: #0d6efd; color: #fff; }
    .code-grid   { display: grid; grid-template-columns: repeat(auto-fill,minmax(200px,1fr)); gap:.5rem; }
    .code-option { padding: .5rem .75rem; border: 1px solid var(--bs-border-color);
                   border-radius: .375rem; cursor: pointer; transition: all .12s; }
    .code-option:hover       { border-color: #0d6efd; background: var(--bs-secondary-bg); }
    .code-option.selected    { border-color: #fd7e14; background: var(--bs-warning-bg-subtle); }
    .code-option.sel-deceased{ border-color: #6c757d; background: var(--bs-secondary-bg); }
    .los-banner { background: var(--bs-success-bg-subtle); border: 1px solid var(--bs-success-border-subtle);
                  border-radius: .375rem; }
    /* Closed state overlay */
    .closed-banner { background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);
                     border-radius: .5rem; }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid px-3 pt-2">

<?php require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php'; ?>

<?php if ($data['flash']): ?>
<div class="alert alert-success alert-dismissible py-2 mx-1" role="alert">
  ✔ <?= htmlspecialchars($data['flash']) ?>
  <?php if ($closed && !$isDeceased): ?>
  &nbsp;·&nbsp;
  <a href="../board.php?facility_id=<?= $facilityId ?>">← <?= xlt('Return to Board') ?></a>
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
          <?= $isDeceased
              ? xlt('Resident Deceased — Episode Closed')
              : xlt('Episode Closed — Resident Departed') ?>
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
      <div class="col-sm-4">
        <span class="text-muted"><?= xlt('Decision recorded') ?></span><br>
        <strong>
          <?= $plan['decision_datetime']
              ? htmlspecialchars(date('M j, Y H:i', strtotime($plan['decision_datetime'])))
              : '—' ?>
        </strong>
      </div>
      <div class="col-sm-4">
        <span class="text-muted"><?= $isDeceased ? xlt('Date/Time of Death') : xlt('Departed') ?></span><br>
        <strong>
          <?= $plan['depart_datetime']
              ? htmlspecialchars(date('M j, Y H:i', strtotime($plan['depart_datetime'])))
              : '—' ?>
        </strong>
      </div>
      <div class="col-sm-4">
        <span class="text-muted"><?= xlt('Length of stay') ?></span><br>
        <strong><?= $losDays ?> <?= xlt('days') ?></strong>
      </div>
    </div>

    <?php if ($plan['notes'] ?? ''): ?>
    <div class="mt-3 p-2 rounded" style="background:var(--bs-tertiary-bg)">
      <span class="text-muted small"><?= xlt('Notes') ?></span><br>
      <span class="small"><?= nl2br(htmlspecialchars($plan['notes'])) ?></span>
    </div>
    <?php endif; ?>

    <div class="mt-3">
      <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
        ← <?= xlt('Board') ?>
      </a>
    </div>
  </div>

<?php else: ?>
  <!-- ══════════════════════════════════════════════════════════════════════ -->
  <!-- ACTIVE STATE — two-stage planning UI                                  -->
  <!-- ══════════════════════════════════════════════════════════════════════ -->

  <!-- Resident summary banner -->
  <div class="los-banner d-flex align-items-center gap-3 p-3 mx-1 mb-3">
    <div class="flex-grow-1">
      <span class="fw-semibold"><?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?></span>
      <span class="text-muted ms-1 small"><?= (int)$h['age'] ?>y / <?= htmlspecialchars($h['sex'] ?? '') ?></span>
      <span class="ms-2 badge bg-secondary"><?= htmlspecialchars($h['unit'] . ' / ' . $h['room']) ?></span>
    </div>
    <div class="text-end small text-muted">
      <?= xlt('Day') ?> <strong><?= $losDays ?></strong> &nbsp;·&nbsp;
      <?= xlt('Admitted') ?> <?= htmlspecialchars(date('M j, Y', strtotime($h['start_datetime']))) ?>
    </div>
  </div>

  <div class="row g-3 mx-0">

    <!-- ─── Stage 1: Plan ──────────────────────────────────────────────── -->
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
          <form method="POST" id="planForm">
            <?= CsrfUtils::collectCsrfToken() ?>
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
                    $isSelected  = ($codeKey === $currentCode);
                    $selClass    = $isSelected
                        ? ($codeKey === 'DECEASED' ? 'sel-deceased' : 'selected')
                        : '';
                  ?>
                  <label class="code-option <?= $selClass ?>"
                         data-code="<?= htmlspecialchars($codeKey) ?>"
                         id="opt-<?= htmlspecialchars($codeKey) ?>">
                    <input type="radio" name="disposition_code"
                           value="<?= htmlspecialchars($codeKey) ?>"
                           class="d-none"
                           <?= $isSelected ? 'checked' : '' ?>>
                    <div class="fw-semibold small">
                      <?= htmlspecialchars($cInfo['icon']) ?>
                      <?= htmlspecialchars(xlt($cInfo['label'])) ?>
                    </div>
                    <?php if ($cInfo['urgent']): ?>
                    <div class="text-danger" style="font-size:.7rem">
                      <?= xlt('Pending — resident may return') ?>
                    </div>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- Destination -->
            <div class="mb-3" id="destinationRow">
              <label class="form-label fw-semibold small text-uppercase text-muted">
                <?= xlt('Destination / Facility') ?>
              </label>
              <input type="text" name="destination" class="form-control"
                     placeholder="<?= xlt('e.g. Springfield SNF, 123 Main St') ?>"
                     value="<?= htmlspecialchars($plan['destination'] ?? '') ?>">
            </div>

            <!-- Decision date -->
            <div class="mb-3">
              <label class="form-label fw-semibold small text-uppercase text-muted">
                <?= xlt('Decision / Planning Date') ?>
              </label>
              <?php
                $decVal = '';
                if (!empty($plan['decision_datetime'])) {
                    $decVal = date('Y-m-d\TH:i', strtotime($plan['decision_datetime']));
                }
              ?>
              <input type="datetime-local" name="decision_datetime" class="form-control"
                     value="<?= htmlspecialchars($decVal ?: date('Y-m-d\TH:i')) ?>">
            </div>

            <!-- Notes — required for DECEASED -->
            <div class="mb-3">
              <label class="form-label fw-semibold small text-uppercase text-muted" id="notesLabel">
                <?= xlt('Notes') ?>
              </label>
              <textarea name="notes" class="form-control" rows="3"
                        id="notesField"
                        placeholder="<?= xlt('Care summary, receiving facility contacts, family notification…') ?>"><?= htmlspecialchars($plan['notes'] ?? '') ?></textarea>
              <div class="form-text text-danger d-none" id="deceasedNoteHint">
                <?= xlt('Required: circumstances, time of death, attending physician name.') ?>
              </div>
            </div>

            <!-- DECEASED solemn warning zone -->
            <div class="deceased-zone rounded p-3 mb-3 d-none" id="deceasedZone">
              <div class="fw-semibold">✝ <?= xlt('Death in Facility Documentation') ?></div>
              <div class="small text-muted mt-1">
                <?= xlt('Recording this will close the episode permanently when departure is confirmed. Ensure notes include: time of death, circumstances, and attending physician.') ?>
              </div>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-warning btn-sm fw-semibold">
                💾 <?= xlt('Save Plan') ?>
              </button>
              <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
                ← <?= xlt('Board') ?>
              </a>
            </div>
          </form>
        </div>
      </div>
    </div><!-- /Stage 1 -->

    <!-- ─── Stage 2: Confirm departure ────────────────────────────────── -->
    <div class="col-lg-5">
      <div class="card discharge-card <?= ($plan && $plan['depart_datetime']) ? 'closed' : '' ?>">
        <div class="card-header d-flex align-items-center gap-2">
          <span class="stage-number <?= !$plan ? 'stage-open' : 'stage-active' ?>">2</span>
          <strong>
            <?= $isDeceased
                ? xlt('Confirm Death')
                : xlt('Confirm Departure') ?>
          </strong>
          <?php if (!$plan): ?>
          <span class="badge bg-secondary ms-auto small"><?= xlt('Complete Stage 1 first') ?></span>
          <?php endif; ?>
        </div>
        <div class="card-body">
          <?php if (!$plan): ?>
          <p class="text-muted small mb-0">
            <?= xlt('Save a discharge plan above before confirming the actual departure.') ?>
          </p>
          <?php else: ?>

          <?php if ($isPending): ?>
          <div class="alert alert-warning py-2 small" role="alert">
            ⚠ <?= xlt('HOSPITAL_EVAL is a pending transfer. Confirm departure only when resident has actually left the facility. If they return, the episode remains open.') ?>
          </div>
          <?php endif; ?>

          <p class="text-muted small mb-3">
            <?= $isDeceased
                ? xlt('Record date and time of death. This permanently closes the episode.')
                : xlt('Record the actual departure date/time. This closes the episode and fires the HL7 A03 Discharge event.') ?>
          </p>

          <form method="POST" id="confirmForm"
                onsubmit="return confirmDeparture(this)">
            <?= CsrfUtils::collectCsrfToken() ?>
            <input type="hidden" name="action"     value="confirm_departure">
            <input type="hidden" name="episode_id" value="<?= $episodeId ?>">

            <div class="mb-3">
              <label class="form-label fw-semibold small text-uppercase text-muted">
                <?= $isDeceased ? xlt('Date / Time of Death') : xlt('Actual Departure Date/Time') ?>
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
              <?php if ($isDeceased): ?>
              <button type="submit"
                      class="btn btn-secondary fw-semibold"
                      style="background:#6c757d">
                ✝ <?= xlt('Record Death & Close Episode') ?>
              </button>
              <?php else: ?>
              <button type="submit" class="btn btn-danger fw-semibold">
                🚪 <?= xlt('Confirm Departure & Close Episode') ?>
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

      <!-- Info card: what happens on close -->
      <div class="card mt-3 border-0 small text-muted" style="background:var(--bs-tertiary-bg)">
        <div class="card-body py-2">
          <div class="fw-semibold mb-1">
            <?= xlt('What happens when the episode closes') ?>
          </div>
          <ul class="mb-0 ps-3">
            <li><?= xlt('Resident removed from Active Board') ?></li>
            <li><?= xlt('HL7 A03 Discharge event sent to ADT listener') ?></li>
            <li><?= xlt('Episode status set to CLOSED with end timestamp') ?></li>
            <li><?= xlt('Historical record and care plan data preserved') ?></li>
          </ul>
        </div>
      </div>

    </div><!-- /Stage 2 -->
  </div><!-- /row -->

<?php endif; // !closed ?>
</div><!-- /container -->

<script>
// ── Code grid click handler ──────────────────────────────────────────────────
document.querySelectorAll('.code-option').forEach(function(label) {
    label.addEventListener('click', function() {
        document.querySelectorAll('.code-option').forEach(function(l) {
            l.classList.remove('selected', 'sel-deceased');
        });
        const code = this.dataset.code;
        if (code === 'DECEASED') {
            this.classList.add('sel-deceased');
        } else {
            this.classList.add('selected');
        }
        this.querySelector('input[type=radio]').checked = true;
        updateDeceasedUi(code === 'DECEASED');
    });
});

function updateDeceasedUi(isDeceased) {
    const zone      = document.getElementById('deceasedZone');
    const noteHint  = document.getElementById('deceasedNoteHint');
    const noteLabel = document.getElementById('notesLabel');
    const destRow   = document.getElementById('destinationRow');
    if (!zone) return;
    if (isDeceased) {
        zone.classList.remove('d-none');
        noteHint.classList.remove('d-none');
        noteLabel.textContent = '<?= xlt('Notes (death documentation)') ?> *';
        destRow.classList.add('d-none');
    } else {
        zone.classList.add('d-none');
        noteHint.classList.add('d-none');
        noteLabel.textContent = '<?= xlt('Notes') ?>';
        destRow.classList.remove('d-none');
    }
}

// Initialise on load in case form is pre-filled
(function() {
    const checked = document.querySelector('input[name="disposition_code"]:checked');
    if (checked) updateDeceasedUi(checked.value === 'DECEASED');
})();

// ── Departure confirmation guard ─────────────────────────────────────────────
function confirmDeparture(form) {
    const isDeceased = document.querySelector('input[name="disposition_code"]:checked')?.value === 'DECEASED';
    const name = '<?= htmlspecialchars($h['fname'] . ' ' . $h['lname']) ?>';
    const msg = isDeceased
        ? '<?= xlt('Record death for') ?> ' + name + '?\n\n<?= xlt('This permanently closes the episode and cannot be undone.') ?>'
        : '<?= xlt('Confirm departure for') ?> ' + name + '?\n\n<?= xlt('This closes the episode and fires the HL7 A03 Discharge event.') ?>';
    return confirm(msg);
}
</script>
</body>
</html>

<?php

/**
 * public/ip/admission.php
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
 * public/ip/admission.php — Inpatient Admission
 *
 * Creates oei_episode (type='IP') + oei_ip_episode overlay
 * + a form_encounter header plus encounter number to anchor care plan and clinical notes.
 *
 * Workflow:
 *   1. Staff search for a patient via the live-search widget.
 *   2. Fill in bed/unit, service, admission type, attending, diagnosis.
 *   3. Submit → PRG redirect to ip/profile.php for the new episode.
 *
 * GET  ip/admission.php?search=<q>  — patient search JSON endpoint
 * GET  ip/admission.php             — render form
 * POST ip/admission.php             — submit admission
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Inpatient\Domain\AdmissionType;
use OpenEMR\Modules\Institutional\Inpatient\Domain\HospitalService;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Controller\IpAdmissionController;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Repository\IpAdmissionRepository;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpAdmission\Service\IpAdmissionService;

if (!$manifest->featureEnabled('ip_admission')) {
    oei_exit_with_alert(xlt('Inpatient Admission is not enabled.'), 'info');
}

$facilityId  = $_oei_facilityId ?? 1;
$userId      = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$_oei_ip_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';

// ── Patient search JSON endpoint ──────────────────────────────────────────
// Must run before any HTML output. Clears ob buffer set by _bootstrap.php.
if (isset($_GET['search'])) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    $q    = trim((string)($_GET['search'] ?? ''));
    $repo = new \OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Repository\PatientRepository();
    $rows = $q !== '' ? $repo->search($q, 15) : [];
    $out  = [];
    foreach ($rows as $r) {
        $out[] = [
            'pid'   => (int)$r['pid'],
            'label' => trim($r['lname'] . ', ' . $r['fname'])
                     . ' — DOB ' . ($r['DOB'] ?? '')
                     . ' (PID ' . $r['pid'] . ')',
            'name'  => trim($r['lname'] . ', ' . $r['fname']),
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new IpAdmissionController(
    new IpAdmissionService(new IpAdmissionRepository())
);
$data = $controller->handle($facilityId, $userId);

// PRG: redirect to Profile hub immediately after successful admission
if ($data['result']['submitted'] && $data['result']['success']) {
    $newEpisodeId = (int)$data['result']['episode_id'];
    $newPid       = (int)($_POST['pid'] ?? 0);
    header('Location: ' . $_oei_ip_base . 'profile.php?episode_id=' . $newEpisodeId
         . '&pid=' . $newPid
         . '&facility_id=' . $facilityId
         . '&flash=admitted');
    exit;
}

$pageTitle = xlt('Inpatient Admission');
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3" style="max-width:860px;">

<div class="d-flex align-items-center gap-3 mb-3">
  <a href="<?= htmlspecialchars($_oei_ip_base) ?>board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Floor Board') ?>
  </a>
  <h5 class="mb-0">🏥 <?= xlt('Admit Inpatient') ?></h5>
</div>

<?php if ($data['result']['submitted'] && !$data['result']['success']): ?>
<div class="alert alert-danger">
    <?= htmlspecialchars($data['result']['error']) ?>
</div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-header bg-primary text-white fw-semibold">
    🏥 <?= xlt('New Inpatient Admission') ?>
  </div>
  <div class="card-body">
    <form method="POST"
          action="<?= htmlspecialchars($_oei_ip_base) ?>admission.php?facility_id=<?= urlencode((string)$facilityId) ?>"
          class="row g-3">
      <input type="hidden" name="csrf_token_form"
             value="<?= htmlspecialchars($data['csrf']) ?>">

      <!-- ── Patient live-search widget ──────────────────────────────── -->
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Patient') ?> *</label>
        <input type="hidden" name="pid" id="ip-pid" required>
        <div class="input-group">
          <input type="text" id="ip-patient-search" class="form-control"
                 placeholder="<?= xla('Search by name, DOB (YYYY-MM-DD), or PID') ?>"
                 autocomplete="off">
          <button type="button" class="btn btn-outline-secondary" id="ip-search-btn">
            &#x1F50D; <?= xlt('Search') ?>
          </button>
        </div>
        <div id="ip-patient-results" class="list-group mt-1"
             style="display:none;max-height:220px;overflow-y:auto;z-index:1050;position:relative;"></div>
        <div id="ip-patient-selected" class="alert alert-success py-2 mt-2 small" style="display:none;">
          &#x2713; <strong><?= xlt('Selected') ?>:</strong>
          <span id="ip-patient-label"></span>
          <button type="button" class="btn-close btn-sm float-end" id="ip-clear-patient"
                  aria-label="<?= xla('Clear') ?>"></button>
        </div>
        <div class="form-text">
          <?= xlt('Search by last name, first name, date of birth, or PID.') ?>
        </div>
      </div>

      <!-- ── Location selector (from Bed Management) ──────────────────── -->
      <?php if (!empty($data['locations'])): ?>
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Select Location') ?>
          <span class="text-muted fw-normal small">(<?= xlt('auto-fills Bed and Unit below') ?>)</span>
        </label>
        <select id="ip-loc-select" class="form-select">
          <option value=""><?= xlt('— Choose from Bed Management —') ?></option>
            <?php
            $locGroups = [];
            foreach ($data['locations'] as $loc) {
                $g = $loc['unit'] ?: xlt('Unassigned Unit');
                $locGroups[$g][] = $loc;
            }
            foreach ($locGroups as $group => $locs): ?>
            <optgroup label="<?= htmlspecialchars($group) ?>">
                <?php foreach ($locs as $loc): ?>
              <option value="<?= htmlspecialchars($loc['code']) ?>"
                      data-bed="<?= htmlspecialchars($loc['code']) ?>"
                      data-unit="<?= htmlspecialchars($loc['unit']) ?>"
                      data-name="<?= htmlspecialchars($loc['name']) ?>">
                    <?= htmlspecialchars($loc['code'] . ' — ' . $loc['name']) ?>
                    <?php if ($loc['type'] !== 'ROOM'): ?>
                  (<?= htmlspecialchars($loc['type']) ?>)
                <?php endif; ?>
              </option>
              <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
        </select>
        <div class="form-text"><?= xlt('Or enter bed and unit manually below.') ?></div>
      </div>
      <?php endif; ?>

      <!-- ── Bed assignment ──────────────────────────────────────────── -->
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Bed') ?></label>
        <input type="text" name="bed" class="form-control"
               placeholder="<?= xla('e.g. 4B-201') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Unit / Floor') ?></label>
        <input type="text" name="unit" class="form-control"
               placeholder="<?= xla('e.g. Medical/Surgical') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Admit Date / Time') ?></label>
        <input type="datetime-local" name="admit_datetime" class="form-control"
               value="<?= date('Y-m-d\TH:i') ?>" required>
      </div>

      <!-- ── Clinical classification ────────────────────────────────── -->
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Service') ?></label>
        <select name="service" class="form-select">
          <?php foreach ($data['services'] as $svc): ?>
          <option value="<?= htmlspecialchars($svc) ?>">
                <?= htmlspecialchars(HospitalService::label($svc)) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Admission Type') ?></label>
        <select name="admission_type" class="form-select">
          <?php foreach ($data['admit_types'] as $type): ?>
          <option value="<?= htmlspecialchars($type) ?>">
                <?= htmlspecialchars(AdmissionType::label($type)) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Expected LOS (days)') ?></label>
        <input type="number" name="expected_los_days" class="form-control"
               min="1" max="365" placeholder="<?= xla('Optional') ?>">
        <div class="form-text"><?= xlt('Case management target — used for LOS alert on board.') ?></div>
      </div>

      <!-- ── Attending physician ────────────────────────────────────── -->
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?= xlt('Attending Physician') ?></label>
        <select name="attending_user_id" class="form-select">
          <option value=""><?= xlt('— Not assigned —') ?></option>
          <?php foreach ($data['physicians'] as $phys): ?>
          <option value="<?= (int)$phys['id'] ?>">
                <?= htmlspecialchars($phys['name']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- ── Diagnosis ──────────────────────────────────────────────── -->
      <div class="col-md-4">
        <label class="form-label fw-semibold"><?= xlt('Admitting Diagnosis') ?></label>
        <input type="text" name="admitting_diagnosis" class="form-control"
               placeholder="<?= xla('e.g. Acute MI, Hip fracture') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold"><?= xlt('ICD-10') ?></label>
        <input type="text" name="admitting_icd10" class="form-control"
               placeholder="<?= xla('e.g. I21.9') ?>">
      </div>

      <!-- ── Chief complaint / reason ──────────────────────────────── -->
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Chief Complaint / Admission Reason') ?></label>
        <textarea name="chief_complaint" class="form-control" rows="2"
                  placeholder="<?= xla('Brief reason for admission…') ?>"></textarea>
      </div>

      <!-- ── Submit ─────────────────────────────────────────────────── -->
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          🏥 <?= xlt('Admit Patient') ?>
        </button>
        <a href="<?= htmlspecialchars($_oei_ip_base) ?>board.php?facility_id=<?= urlencode((string)$facilityId) ?>"
           class="btn btn-outline-secondary">
          <?= xlt('Cancel') ?>
        </a>
      </div>

    </form>
  </div>
</div>

</div>

<!-- Location selector auto-fill -->
<script>
(function () {
  var locSel  = document.getElementById('ip-loc-select');
  var bedIn   = document.querySelector('input[name="bed"]');
  var unitIn  = document.querySelector('input[name="unit"]');
  if (!locSel || !bedIn || !unitIn) return;
  locSel.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (!opt || opt.value === '') return;
    bedIn.value  = opt.dataset.bed  || '';
    unitIn.value = opt.dataset.unit || '';
  });
})();
</script>

<!-- Patient live-search JS (identical pattern to AL intake) -->
<script>
(function () {
  var searchInput   = document.getElementById('ip-patient-search');
  var searchBtn     = document.getElementById('ip-search-btn');
  var resultsList   = document.getElementById('ip-patient-results');
  var selectedBox   = document.getElementById('ip-patient-selected');
  var selectedLabel = document.getElementById('ip-patient-label');
  var clearBtn      = document.getElementById('ip-clear-patient');
  var pidInput      = document.getElementById('ip-pid');
  var baseUrl       = window.location.pathname;
  var debounce      = null;

  function showResults(patients) {
    resultsList.innerHTML = '';
    if (!patients.length) {
      var d = document.createElement('div');
      d.className   = 'list-group-item text-muted small';
      d.textContent = '<?= xlt('No patients found.') ?>';
      resultsList.appendChild(d);
      resultsList.style.display = 'block';
      return;
    }
    patients.forEach(function (p) {
      var btn = document.createElement('button');
      btn.type      = 'button';
      btn.className = 'list-group-item list-group-item-action py-2 small';
      btn.textContent = p.label;
      btn.addEventListener('click', function () { selectPatient(p); });
      resultsList.appendChild(btn);
    });
    resultsList.style.display = 'block';
  }

  function selectPatient(p) {
    pidInput.value            = p.pid;
    selectedLabel.textContent = p.label;
    selectedBox.style.display = '';
    resultsList.style.display = 'none';
    searchInput.value         = p.name;
    searchInput.disabled      = true;
    searchBtn.disabled        = true;
  }

  function clearSelection() {
    pidInput.value            = '';
    selectedBox.style.display = 'none';
    searchInput.value         = '';
    searchInput.disabled      = false;
    searchBtn.disabled        = false;
    searchInput.focus();
  }

  function doSearch() {
    var q = searchInput.value.trim();
    if (q.length < 2) { resultsList.style.display = 'none'; return; }
    fetch(baseUrl + '?search=' + encodeURIComponent(q)
        + '&facility_id=<?= (int)$facilityId ?>')
      .then(function (r) { return r.json(); })
      .then(showResults)
      .catch(function (e) {
        resultsList.innerHTML = '';
        var d = document.createElement('div');
        d.className   = 'list-group-item text-danger small';
        d.textContent = '<?= xlt('Search error') ?>: ' + e.message;
        resultsList.appendChild(d);
        resultsList.style.display = 'block';
      });
  }

  searchBtn.addEventListener('click', doSearch);
  searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
  });
  searchInput.addEventListener('input', function () {
    clearTimeout(debounce);
    debounce = setTimeout(doSearch, 350);
  });
  clearBtn.addEventListener('click', clearSelection);
  document.addEventListener('click', function (e) {
    if (!resultsList.contains(e.target)
        && e.target !== searchInput
        && e.target !== searchBtn) {
      resultsList.style.display = 'none';
    }
  });
})();
</script>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>












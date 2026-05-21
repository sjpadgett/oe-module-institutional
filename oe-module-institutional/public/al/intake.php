<?php

/**
 * public/al/intake.php
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
 * public/al/intake.php — AL Resident Admission (Intake)
 *
 * Creates oei_episode (type='AL') + oei_al_episode overlay
 * + a form_encounter to anchor care plan entries.
 * Fall risk stratified via Morse Scale score.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Controller\ResidentIntakeController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

if (!$manifest->featureEnabled('al_intake')) {
    oei_exit_with_alert(xlt('Resident Intake is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

// ── Patient search JSON endpoint ──────────────────────────────────────────
// Runs before any HTML. Clears ob buffer (set by _bootstrap.php) before
// outputting JSON so the response is clean JSON, not HTML+JSON.
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
                     . ' â DOB ' . ($r['DOB'] ?? '')
                     . ' (PID ' . $r['pid'] . ')',
            'name'  => trim($r['lname'] . ', ' . $r['fname']),
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new ResidentIntakeController();
$data = $controller->handle($facilityId, $userId);
$result = $data['result'];
$_intakeCsrf = CsrfUtils::collectCsrfToken();

// PRG: redirect to Profile hub immediately after successful admission
if ($result['submitted'] && $result['success']) {
    $newEpisodeId = (int)$result['episode_id'];
    $newPid       = (int)($_POST['pid'] ?? 0);
    header('Location: profile.php?episode_id=' . $newEpisodeId
         . '&pid=' . $newPid
         . '&facility_id=' . $facilityId
         . '&flash=admitted');
    exit;
}

$pageTitle = xlt('Resident Admission');
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
<div class="container-fluid p-3">

<div class="d-flex align-items-center gap-3 mb-3">
  <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
    ← <?= xlt('Board') ?>
  </a>
  <h5 class="mb-0">🏡 <?= xlt('Admit New Resident') ?></h5>
</div>

<?php if ($result['submitted'] && !$result['success']): ?>
<div class="alert alert-danger">
    <?= htmlspecialchars($result['error']) ?>
</div>
<?php endif; ?>

<div class="card" style="max-width:700px;">
  <div class="card-header bg-success text-white"><strong>🏡 <?= xlt('New AL Admission') ?></strong></div>
  <div class="card-body">
    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf_token_form"
             value="<?= htmlspecialchars($_intakeCsrf) ?>">

      <!-- Patient live-search widget -->
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Patient') ?> *</label>
        <input type="hidden" name="pid" id="al-pid" required>
        <div class="input-group">
          <input type="text" id="al-patient-search" class="form-control"
                 placeholder="<?= xla('Search by name, DOB (YYYY-MM-DD), or PID') ?>"
                 autocomplete="off">
          <button type="button" class="btn btn-outline-secondary" id="al-search-btn">
            &#x1F50D; <?= xlt('Search') ?>
          </button>
        </div>
        <div id="al-patient-results" class="list-group mt-1"
             style="display:none;max-height:220px;overflow-y:auto;z-index:1050;position:relative;"></div>
        <div id="al-patient-selected" class="alert alert-success py-2 mt-2 small" style="display:none;">
          &#x2713; <strong><?= xlt('Selected') ?>:</strong>
          <span id="al-patient-label"></span>
          <button type="button" class="btn-close btn-sm float-end" id="al-clear-patient"
                  aria-label="<?= xla('Clear') ?>"></button>
        </div>
        <div class="form-text">
          <?= xlt('Search by last name, first name, date of birth, or PID.') ?>
        </div>
      </div>

      <!-- Location selector from Bed Management -->
      <?php if (!empty($data['locations'])): ?>
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Select Room / Bed') ?>
          <span class="text-muted fw-normal small">(<?= xlt('auto-fills Unit and Room below') ?>)</span>
        </label>
        <select id="al-loc-select" class="form-select">
          <option value=""><?= xlt('— Choose from Bed Management —') ?></option>
            <?php
            $alLocGroups = [];
            foreach ($data['locations'] as $loc) {
                $g = $loc['unit'] ?: xlt('Unassigned Unit');
                $alLocGroups[$g][] = $loc;
            }
            foreach ($alLocGroups as $group => $locs): ?>
            <optgroup label="<?= htmlspecialchars($group) ?>">
                <?php foreach ($locs as $loc): ?>
              <option value="<?= htmlspecialchars($loc['code']) ?>"
                      data-bed="<?= htmlspecialchars($loc['code']) ?>"
                      data-unit="<?= htmlspecialchars($loc['unit']) ?>"
                      data-name="<?= htmlspecialchars($loc['name']) ?>">
                    <?= htmlspecialchars($loc['code'] . ' — ' . $loc['name']) ?>
              </option>
              <?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
        </select>
        <div class="form-text"><?= xlt('Or type a room and unit manually below.') ?></div>
      </div>
      <?php endif; ?>

      <div class="col-md-6">
        <input type="text" name="unit" class="form-control" placeholder="<?= xlt('e.g. Wing A') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label fw-semibold"><?= xlt('Room') ?></label>
        <input type="text" name="room" class="form-control" placeholder="<?= xlt('e.g. 14A') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold"><?= xlt('Admission Date/Time') ?></label>
        <input type="datetime-local" name="admit_datetime" class="form-control"
               value="<?= date('Y-m-d\TH:i') ?>" required>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold"><?= xlt('Care Level') ?></label>
        <select name="care_level" class="form-select">
          <?php foreach ($data['care_levels'] as $lvl): ?>
          <option value="<?= htmlspecialchars($lvl) ?>">
                <?= htmlspecialchars(CareLevel::label($lvl)) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-text"><?= xlt('Can be auto-computed after first ADL chart.') ?></div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold">
          <?= xlt('Morse Fall Scale Score') ?>
          <span class="text-muted small">(0–125)</span>
        </label>
        <input type="number" name="fall_risk_score" class="form-control"
               min="0" max="125" value="0" id="morseScore">
        <div class="form-text" id="morseRiskLabel"><?= xlt('Low Risk (0–24)') ?></div>
      </div>

      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Admission Reason') ?></label>
        <textarea name="admit_reason" class="form-control" rows="2"
                  placeholder="<?= xlt('Brief reason for AL placement…') ?>"></textarea>
      </div>

      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-success"><?= xlt('Admit Resident') ?></button>
        <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-outline-secondary">
          <?= xlt('Cancel') ?>
        </a>
      </div>
    </form>
  </div>
</div>

<script>
// Location selector → auto-fill Unit and Room
(function () {
  var locSel  = document.getElementById('al-loc-select');
  var unitIn  = document.querySelector('input[name="unit"]');
  var roomIn  = document.querySelector('input[name="room"]');
  if (!locSel || !unitIn || !roomIn) return;
  locSel.addEventListener('change', function () {
    var opt = this.options[this.selectedIndex];
    if (!opt || opt.value === '') return;
    roomIn.value = opt.dataset.bed  || '';
    unitIn.value = opt.dataset.unit || '';
  });
})();

// Live Morse score → risk label
const morseScore = document.getElementById('morseScore');
const morseLabel = document.getElementById('morseRiskLabel');
if (morseScore && morseLabel) {
  morseScore.addEventListener('input', function() {
    const s = parseInt(this.value, 10);
    if (s <= 24)       morseLabel.textContent = '<?= xlt('Low Risk') ?> (0–24)';
    else if (s <= 44)  morseLabel.textContent = '<?= xlt('Moderate Risk') ?> (25–44)';
    else               morseLabel.textContent = '⚠ <?= xlt('High Risk') ?> (45+)';
  });
}
</script>
</div>
  <script>
  (function () {
    var searchInput   = document.getElementById('al-patient-search');
    var searchBtn     = document.getElementById('al-search-btn');
    var resultsList   = document.getElementById('al-patient-results');
    var selectedBox   = document.getElementById('al-patient-selected');
    var selectedLabel = document.getElementById('al-patient-label');
    var clearBtn      = document.getElementById('al-clear-patient');
    var pidInput      = document.getElementById('al-pid');
    var baseUrl       = window.location.pathname;
    var debounce      = null;

    function showResults(patients) {
      resultsList.innerHTML = '';
      if (!patients.length) {
        var d = document.createElement('div');
        d.className = 'list-group-item text-muted small';
        d.textContent = '<?= xlt('No patients found.') ?>';
        resultsList.appendChild(d);
        resultsList.style.display = 'block';
        return;
      }
      patients.forEach(function (p) {
        var btn = document.createElement('button');
        btn.type = 'button';
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
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(showResults)
        .catch(function (e) {
          resultsList.innerHTML = '';
          var d = document.createElement('div');
          d.className = 'list-group-item text-danger small';
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
          && e.target !== searchInput && e.target !== searchBtn) {
        resultsList.style.display = 'none';
      }
    });
  })();
  </script>
  <?= institutional_bootstrap5_js_tag() ?>
</body>
</html>













<?php
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

$controller = new ResidentIntakeController();
$data = $controller->handle($facilityId, $userId);
$result = $data['result'];

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
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
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
      <?= CsrfUtils::collectCsrfToken() ?>

      <!-- Patient lookup — uses OpenEMR patient search -->
      <div class="col-12">
        <label class="form-label fw-semibold"><?= xlt('Patient (PID)') ?></label>
        <input type="number" name="pid" class="form-control" required min="1"
               placeholder="<?= xlt('Enter patient PID') ?>">
        <div class="form-text">
          <?= xlt('Find PID via Patient Search. Resident must have an existing patient record.') ?>
        </div>
      </div>

      <div class="col-md-6">
        <label class="form-label fw-semibold"><?= xlt('Unit') ?></label>
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
</body>
</html>

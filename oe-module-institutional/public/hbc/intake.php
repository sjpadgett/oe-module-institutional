<?php

/**
 * public/hbc/intake.php
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
 * public/hbc/intake.php — Home-Based Care Referral Intake
 *
 * Creates oei_episode (type='HBC') + oei_hbc_episode overlay
 * + form_encounter to anchor care plan entries.
 *
 * Fields: patient search, service address (international), caregiver contact,
 *         referral source/reason, urgency, assigned clinician, payer/auth notes.
 * GPS and patient signature fields are visit-level, not intake-level.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcIntake\Controller\HbcIntakeController;

if (!$manifest->featureEnabled('hbc_intake')) {
    oei_exit_with_alert(xlt('Home-Based Care Intake is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$_hbcBase   = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

// ── Patient search JSON endpoint ───────────────────────────────────────────
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
                     . ' · ' . ($r['DOB'] ?? '')
                     . ' (PID ' . $r['pid'] . ')',
            'name'  => trim($r['lname'] . ', ' . $r['fname']),
        ];
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$controller = new HbcIntakeController();
$data       = $controller->handle($facilityId, $userId);
$result     = $data['result'];
$clinicians = $data['clinicians'];

$_csrf = CsrfUtils::collectCsrfToken();

// PRG after successful intake
if ($result['submitted'] && $result['success']) {
    header('Location: ' . $_hbcBase . 'profile.php?episode_id=' . $result['episode_id']
         . '&pid=' . (int)($_POST['pid'] ?? 0)
         . '&facility_id=' . $facilityId . '&flash=accepted');
    exit;
}

$pageTitle = xlt('New Referral — Home-Based Care');
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
<div class="container py-4" style="max-width:800px;">

  <!-- Back -->
  <div class="mb-3">
    <a href="<?= htmlspecialchars($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>"
       class="btn btn-sm btn-outline-secondary">← <?= xlt('Visit Board') ?></a>
  </div>

  <h4 class="mb-1">🏡 <?= xlt('New Home-Based Care Referral') ?></h4>
  <p class="text-muted small mb-4">
    <?= xlt('Complete the referral details below. Service address and patient are required.') ?>
  </p>

  <!-- Error / validation -->
  <?php if ($result['submitted'] && !$result['success']): ?>
  <div class="alert alert-danger py-2">
    <?= htmlspecialchars($result['error']) ?>
  </div>
  <?php endif; ?>

  <form method="POST"
        action="<?= htmlspecialchars($_hbcBase . 'intake.php?facility_id=' . $facilityId) ?>">
    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_csrf) ?>">

    <!-- ── Patient ── -->
    <div class="card mb-3">
      <div class="card-header fw-semibold small">1. <?= xlt('Patient') ?></div>
      <div class="card-body">
        <input type="hidden" name="pid" id="selectedPid" value="<?= (int)($_POST['pid'] ?? 0) ?>">
        <label class="form-label small"><?= xlt('Search patient') ?></label>
        <input type="text" id="patientSearch" class="form-control"
               placeholder="<?= xla('Last name, first name, or PID…') ?>"
               autocomplete="off">
        <div id="patientSuggestions" class="list-group mt-1" style="position:absolute;z-index:200;width:90%;"></div>
        <div id="patientSelected" class="mt-2 small text-success fw-semibold"></div>
      </div>
    </div>

    <!-- ── Referral details ── -->
    <div class="card mb-3">
      <div class="card-header fw-semibold small">2. <?= xlt('Referral') ?></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Referral Source') ?></label>
            <input type="text" name="referral_source" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['referral_source'] ?? '')) ?>"
                   placeholder="<?= xla('GP, Hospital, Family, Self…') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Urgency') ?> <span class="text-danger">*</span></label>
            <select name="urgency" class="form-select" required>
              <?php foreach (['ROUTINE','URGENT','EMERGENT'] as $u): ?>
              <option value="<?= $u ?>" <?= (($_POST['urgency'] ?? 'ROUTINE') === $u) ? 'selected' : '' ?>>
                <?= htmlspecialchars(ucfirst(strtolower($u))) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Referral Reason / Chief Concern') ?></label>
            <textarea name="referral_reason" class="form-control" rows="2"
                      placeholder="<?= xla('Primary reason for referral…') ?>"><?= htmlspecialchars((string)($_POST['referral_reason'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Primary Diagnosis') ?></label>
            <input type="text" name="primary_diagnosis" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['primary_diagnosis'] ?? '')) ?>"
                   placeholder="<?= xla('Free text or ICD-10 description') ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small"><?= xlt('ICD-10') ?></label>
            <input type="text" name="primary_icd10" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['primary_icd10'] ?? '')) ?>"
                   placeholder="J18.9" maxlength="20">
          </div>
          <div class="col-md-3">
            <label class="form-label small"><?= xlt('Referral Date') ?></label>
            <input type="datetime-local" name="referral_datetime" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['referral_datetime'] ?? date('Y-m-d\TH:i'))) ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Service address ── -->
    <div class="card mb-3">
      <div class="card-header fw-semibold small">3. <?= xlt('Service Address') ?> <span class="text-danger">*</span></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label small"><?= xlt('Address Line 1') ?> <span class="text-danger">*</span></label>
            <input type="text" name="service_address_line1" class="form-control" required
                   value="<?= htmlspecialchars((string)($_POST['service_address_line1'] ?? '')) ?>"
                   placeholder="<?= xla('Street address, apt, unit…') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Address Line 2') ?></label>
            <input type="text" name="service_address_line2" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['service_address_line2'] ?? '')) ?>"
                   placeholder="<?= xla('Building, floor, gate…') ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label small"><?= xlt('City / Town') ?></label>
            <input type="text" name="service_city" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['service_city'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small"><?= xlt('State / Province') ?></label>
            <input type="text" name="service_state_province" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['service_state_province'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small"><?= xlt('Postal Code') ?></label>
            <input type="text" name="service_postal_code" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['service_postal_code'] ?? '')) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small"><?= xlt('Country') ?></label>
            <input type="text" name="service_country" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['service_country'] ?? 'US')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Access Notes') ?>
              <span class="text-muted">(<?= xlt('gate code, parking, dog, key location') ?>)</span>
            </label>
            <input type="text" name="access_notes" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['access_notes'] ?? '')) ?>"
                   placeholder="<?= xla('e.g. Gate code 1234. Park in driveway. Ring twice.') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Caregiver contact ── -->
    <div class="card mb-3">
      <div class="card-header fw-semibold small">4. <?= xlt('Caregiver / Contact') ?></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label small"><?= xlt('Name') ?></label>
            <input type="text" name="caregiver_name" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['caregiver_name'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Phone') ?></label>
            <input type="tel" name="caregiver_phone" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['caregiver_phone'] ?? '')) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label small"><?= xlt('Relationship') ?></label>
            <input type="text" name="caregiver_relationship" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['caregiver_relationship'] ?? '')) ?>"
                   placeholder="<?= xla('Spouse, Child, Carer…') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Clinical assignment + payer ── -->
    <div class="card mb-3">
      <div class="card-header fw-semibold small">5. <?= xlt('Assignment & Authorisation') ?></div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Assigned Clinician') ?></label>
            <select name="primary_clinician_user_id" class="form-select">
              <option value="0"><?= xlt('— Unassigned —') ?></option>
              <?php foreach ($clinicians as $c): ?>
              <option value="<?= $c['id'] ?>"
                <?= ((int)($_POST['primary_clinician_user_id'] ?? 0) === $c['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['name']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small"><?= xlt('Payer / Funder') ?></label>
            <input type="text" name="payer_name" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['payer_name'] ?? '')) ?>"
                   placeholder="<?= xla('Medicare, Medicaid, Private, NHS, etc.') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small"><?= xlt('Authorisation Notes') ?></label>
            <textarea name="authorization_notes" class="form-control" rows="2"
                      placeholder="<?= xla('Auth number, visit limit, approved services…') ?>"><?= htmlspecialchars((string)($_POST['authorization_notes'] ?? '')) ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Cert Period Start') ?></label>
            <input type="date" name="cert_period_start" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['cert_period_start'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Cert Period End') ?></label>
            <input type="date" name="cert_period_end" class="form-control"
                   value="<?= htmlspecialchars((string)($_POST['cert_period_end'] ?? '')) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label small"><?= xlt('Authorized Visits / Week') ?></label>
            <input type="number" name="authorized_visits_per_week" class="form-control"
                   min="1" max="21" placeholder="<?= xla('e.g. 3') ?>"
                   value="<?= htmlspecialchars((string)($_POST['authorized_visits_per_week'] ?? '')) ?>">
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-success px-4">
        🏡 <?= xlt('Accept Referral') ?>
      </button>
      <a href="<?= htmlspecialchars($_hbcBase . 'board.php?facility_id=' . $facilityId) ?>"
         class="btn btn-outline-secondary"><?= xlt('Cancel') ?></a>
    </div>
  </form>
</div>

<script>
(function () {
    const search  = document.getElementById('patientSearch');
    const sugg    = document.getElementById('patientSuggestions');
    const pidInput = document.getElementById('selectedPid');
    const selLabel = document.getElementById('patientSelected');
    let timer;

    search.addEventListener('input', () => {
        clearTimeout(timer);
        const q = search.value.trim();
        if (q.length < 2) { sugg.innerHTML = ''; return; }
        timer = setTimeout(() => {
            fetch('intake.php?facility_id=<?= $facilityId ?>&search=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(rows => {
                    sugg.innerHTML = rows.map(r =>
                        `<button type="button" class="list-group-item list-group-item-action small"
                                 data-pid="${r.pid}" data-name="${r.name}">${r.label}</button>`
                    ).join('');
                    sugg.querySelectorAll('button').forEach(btn => {
                        btn.addEventListener('click', () => {
                            pidInput.value  = btn.dataset.pid;
                            search.value    = btn.dataset.name;
                            selLabel.textContent = '✓ PID ' + btn.dataset.pid + ' selected';
                            sugg.innerHTML  = '';
                        });
                    });
                });
        }, 250);
    });

    document.addEventListener('click', e => {
        if (!sugg.contains(e.target) && e.target !== search) { sugg.innerHTML = ''; }
    });
})();
</script>
</body>
</html>










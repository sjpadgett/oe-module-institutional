<?php

/**
 * public/hbc/edit_episode.php
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
 * public/hbc/edit_episode.php — Edit HBC Episode Data
 *
 * Allows supervisors and clinicians to update service address, caregiver,
 * clinician assignment, diagnosis, payer, cert period, and access notes
 * after initial intake.
 */
require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcEpisodeEdit\Controller\HbcEpisodeEditController;

if (!$manifest->featureEnabled('hbc_profile')) {
    oei_exit_with_alert(xlt('Home-Based Care is not enabled.'), 'info');
}

$episodeId  = (int)($_GET['episode_id'] ?? $_POST['episode_id'] ?? 0);
$pid        = (int)($_GET['pid']        ?? $_POST['pid']        ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;

$_hbcBase = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/hbc/';

if ($episodeId === 0) {
    header('Location: ' . $_hbcBase . 'board.php?facility_id=' . $facilityId);
    exit;
}

$controller = new HbcEpisodeEditController();
$data       = $controller->handle($episodeId, $facilityId, $userId);
$ep         = $data['episode'];

if (!$ep) {
    oei_exit_with_alert(xlt('Episode not found or not a Home-Based Care episode.'), 'danger');
}

if ($pid === 0) {
    $pid = (int)$ep['pid'];
}

$_oei_csrf  = CsrfUtils::collectCsrfToken();
$pageTitle  = xlt('Edit Episode');
$activePage = 'edit_episode';
$__bgClass  = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
$q = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
$profileUrl = $_hbcBase . 'profile.php' . $q;

// For the nav partial
$hbcNavPatient = [
    'fname'             => (string)$ep['fname'],
    'lname'             => (string)$ep['lname'],
    'pid'               => $pid,
    'referral_status'   => (string)$ep['referral_status'],
    'urgency'           => (string)$ep['urgency'],
    'service_city'      => (string)$ep['service_city'],
    'service_state_province' => (string)$ep['service_state_province'],
    'primary_diagnosis' => (string)$ep['primary_diagnosis'],
];
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

<?php require __DIR__ . '/../../src/HomeBased/Ui/partials/hbc_patient_nav.php'; ?>

<?php if ($data['flash']): ?>
<div class="alert alert-success alert-dismissible py-2 mt-2">
  ✔ <?= htmlspecialchars($data['flash']) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($data['error']): ?>
<div class="alert alert-danger py-2 mt-2"><?= htmlspecialchars($data['error']) ?></div>
<?php endif; ?>

<div class="mb-3 mt-2 d-flex justify-content-between align-items-center">
  <h5 class="mb-0">✏️ <?= xlt('Edit Episode') ?> — <?= htmlspecialchars($ep['fname'] . ' ' . $ep['lname']) ?></h5>
  <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn-sm btn-outline-secondary">← <?= xlt('Back to Profile') ?></a>
</div>

<form method="POST" action="<?= htmlspecialchars($_hbcBase . 'edit_episode.php' . $q) ?>">
  <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
  <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
  <input type="hidden" name="pid" value="<?= $pid ?>">

  <div class="row g-3">

    <!-- ── Clinical ──────────────────────────────────────────────────── -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header fw-semibold small">🩺 <?= xlt('Clinical') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Primary Diagnosis') ?></label>
              <input type="text" name="primary_diagnosis" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['primary_diagnosis']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small"><?= xlt('ICD-10') ?></label>
              <input type="text" name="primary_icd10" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['primary_icd10']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small"><?= xlt('Urgency') ?></label>
              <select name="urgency" class="form-select form-select-sm">
                <?php foreach (['ROUTINE','URGENT','EMERGENT'] as $u): ?>
                <option value="<?= $u ?>" <?= $ep['urgency'] === $u ? 'selected' : '' ?>><?= htmlspecialchars(xlt($u)) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Assigned Clinician') ?></label>
              <select name="primary_clinician_user_id" class="form-select form-select-sm">
                <option value="0"><?= xlt('— Unassigned —') ?></option>
                <?php foreach ($data['clinicians'] as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)$ep['primary_clinician_user_id'] === $c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Referral Source') ?></label>
              <input type="text" name="referral_source" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['referral_source']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small"><?= xlt('Referral Reason') ?></label>
              <textarea name="referral_reason" class="form-control form-control-sm" rows="2"><?= htmlspecialchars((string)$ep['referral_reason']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Service Address ────────────────────────────────────────────── -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header fw-semibold small">📍 <?= xlt('Service Address') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small"><?= xlt('Address Line 1') ?> *</label>
              <input type="text" name="service_address_line1" class="form-control form-control-sm" required
                     value="<?= htmlspecialchars((string)$ep['service_address_line1']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small"><?= xlt('Address Line 2') ?></label>
              <input type="text" name="service_address_line2" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['service_address_line2']) ?>">
            </div>
            <div class="col-md-5">
              <label class="form-label small"><?= xlt('City') ?></label>
              <input type="text" name="service_city" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['service_city']) ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label small"><?= xlt('State / Province') ?></label>
              <input type="text" name="service_state_province" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['service_state_province']) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small"><?= xlt('Postal Code') ?></label>
              <input type="text" name="service_postal_code" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['service_postal_code']) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label small"><?= xlt('Country') ?></label>
              <input type="text" name="service_country" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['service_country']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small"><?= xlt('Access Notes') ?></label>
              <textarea name="access_notes" class="form-control form-control-sm" rows="2"
                        placeholder="<?= xla('Gate code, parking, dog, key location…') ?>"><?= htmlspecialchars((string)$ep['access_notes']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Caregiver ──────────────────────────────────────────────────── -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header fw-semibold small">👤 <?= xlt('Caregiver / Contact') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small"><?= xlt('Name') ?></label>
              <input type="text" name="caregiver_name" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['caregiver_name']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Phone') ?></label>
              <input type="tel" name="caregiver_phone" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['caregiver_phone']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Relationship') ?></label>
              <input type="text" name="caregiver_relationship" class="form-control form-control-sm"
                     placeholder="<?= xla('Spouse, Child, Friend…') ?>"
                     value="<?= htmlspecialchars((string)$ep['caregiver_relationship']) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Payer / Authorization ──────────────────────────────────────── -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header fw-semibold small">💳 <?= xlt('Payer / Authorization') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small"><?= xlt('Payer Name') ?></label>
              <input type="text" name="payer_name" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)$ep['payer_name']) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small"><?= xlt('Authorization Notes') ?></label>
              <textarea name="authorization_notes" class="form-control form-control-sm" rows="2"><?= htmlspecialchars((string)$ep['authorization_notes']) ?></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Cert Period ────────────────────────────────────────────────── -->
    <div class="col-lg-4">
      <div class="card h-100">
        <div class="card-header fw-semibold small">📅 <?= xlt('Certification Period') ?></div>
        <div class="card-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('Start Date') ?></label>
              <input type="date" name="cert_period_start" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)($ep['cert_period_start'] ?? '')) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small"><?= xlt('End Date') ?></label>
              <input type="date" name="cert_period_end" class="form-control form-control-sm"
                     value="<?= htmlspecialchars((string)($ep['cert_period_end'] ?? '')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small"><?= xlt('Authorized Visits / Week') ?></label>
              <input type="number" name="authorized_visits_per_week" class="form-control form-control-sm"
                     min="1" max="21" placeholder="<?= xla('e.g. 3') ?>"
                     value="<?= htmlspecialchars((string)($ep['authorized_visits_per_week'] ?? '')) ?>">
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>

  <div class="d-flex gap-2 mt-4">
    <button type="submit" class="btn btn-success px-4">💾 <?= xlt('Save Changes') ?></button>
    <a href="<?= htmlspecialchars($profileUrl) ?>" class="btn btn-outline-secondary"><?= xlt('Cancel') ?></a>
  </div>
</form>

</div>
<?= institutional_bootstrap5_js_tag() ?>
</body>
</html>







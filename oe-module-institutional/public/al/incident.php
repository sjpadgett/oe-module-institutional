<?php

/**
 * public/al/incident.php
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
 * public/al/incident.php — AL Incident Reports
 *
 * Facility-wide incident log with mandatory-report tracking.
 * State AL licensing requires 24-72h notification for falls with injury,
 * elopements, abuse/neglect, and deaths.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Controller\IncidentController;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\IncidentType;

if (!$manifest->featureEnabled('al_incident')) {
    oei_exit_with_alert(xlt('Incident Reports is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$episodeId  = (int)($_GET['episode_id'] ?? 0);

$controller = new IncidentController();
$data = $controller->handle($facilityId, $userId);

$_oei_csrf = CsrfUtils::collectCsrfToken();
$pageTitle = xlt('Incident Reports');

$activePage  = 'incident';
$__bgClass   = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid p-3">
<?php
// AL resident nav — tabs + context strip
require __DIR__ . '/../../src/AssistedLiving/Ui/partials/al_resident_nav.php';
?>
<?php if ($data['flash']): ?>
<div class="alert alert-success py-2"><?= htmlspecialchars($data['flash']) ?></div>
<?php endif; ?>

<!-- New Incident button -->
<div class="mb-3">
  <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#newIncidentModal">
    🚨 <?= xlt('Report New Incident') ?>
  </button>
  <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary ms-2">
    ← <?= xlt('Board') ?>
  </a>
</div>

<!-- Incidents table -->
<?php if (empty($data['incidents'])): ?>
<div class="alert alert-info"><?= xlt('No incidents on file for this facility.') ?></div>
<?php else: ?>
<div class="table-responsive">
  <table class="table table-sm table-hover">
    <thead class="table-dark"><tr>
      <th><?= xlt('Date/Time') ?></th>
      <th><?= xlt('Resident') ?></th>
      <th><?= xlt('Type') ?></th>
      <th><?= xlt('Severity') ?></th>
      <th><?= xlt('State Report') ?></th>
      <th><?= xlt('Reported By') ?></th>
    </tr></thead>
    <tbody>
    <?php foreach ($data['incidents'] as $inc): ?>
    <tr class="<?= $inc['mandatory_required'] && !$inc['mandatory_report_sent'] ? 'table-warning' : '' ?>">
      <td><?= htmlspecialchars(date('M j H:i', strtotime($inc['incident_datetime']))) ?></td>
      <td><?= htmlspecialchars($inc['resident_name']) ?></td>
      <td>
        <span class="badge bg-secondary"><?= htmlspecialchars($inc['type_label']) ?></span>
        <?php if ($inc['mandatory_required']): ?>
        <span class="badge bg-danger ms-1" title="<?= xlt('Mandatory state report required') ?>">!</span>
        <?php endif; ?>
      </td>
      <td>
        <span class="badge bg-<?= match($inc['severity']){
            'CRITICAL'=>'danger','HIGH'=>'warning','MODERATE'=>'info',default=>'secondary'} ?>">
          <?= htmlspecialchars($inc['severity']) ?>
        </span>
      </td>
      <td>
        <?php if ($inc['mandatory_report_sent']): ?>
          <span class="text-success">✔ <?= xlt('Sent') ?></span>
        <?php elseif ($inc['mandatory_required']): ?>
          <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
            <input type="hidden" name="action" value="mark_reported">
            <input type="hidden" name="incident_id" value="<?= (int)$inc['id'] ?>">
            <button class="btn btn-xs btn-warning btn-sm py-0"><?= xlt('Mark Sent') ?></button>
          </form>
        <?php else: ?>
          <span class="text-muted">—</span>
        <?php endif; ?>
      </td>
      <td class="small text-muted"><?= htmlspecialchars($inc['reported_by'] ?: '—') ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- New Incident Modal -->
<div class="modal fade" id="newIncidentModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">🚨 <?= xlt('New Incident Report') ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($_oei_csrf) ?>">
        <input type="hidden" name="action" value="create">
        <div class="modal-body row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold"><?= xlt('Episode ID') ?></label>
            <input type="number" name="episode_id" class="form-control"
                   value="<?= $episodeId ?: '' ?>" placeholder="<?= xlt('Episode #') ?>" required>
            <div class="form-text"><?= xlt('From the resident board.') ?></div>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold"><?= xlt('Incident Type') ?></label>
            <select name="incident_type" class="form-select" required>
              <option value=""><?= xlt('— Select —') ?></option>
              <?php foreach ($data['incident_types'] as $type): ?>
              <option value="<?= htmlspecialchars($type) ?>">
                    <?= htmlspecialchars(IncidentType::label($type)) ?>
                    <?= IncidentType::requiresMandatoryReport($type) ? ' ⚠' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold"><?= xlt('Severity') ?></label>
            <select name="severity" class="form-select">
              <option value="LOW"><?= xlt('Low') ?></option>
              <option value="MODERATE" selected><?= xlt('Moderate') ?></option>
              <option value="HIGH"><?= xlt('High') ?></option>
              <option value="CRITICAL"><?= xlt('Critical') ?></option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold"><?= xlt('Date / Time of Incident') ?></label>
            <input type="datetime-local" name="incident_datetime" class="form-control"
                   value="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold"><?= xlt('Location') ?></label>
            <input type="text" name="location_description" class="form-control"
                   placeholder="<?= xlt('e.g. Room 14A, dining room…') ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold"><?= xlt('Narrative') ?></label>
            <textarea name="narrative" class="form-control" rows="3"
                      placeholder="<?= xlt('Factual description of what occurred…') ?>"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold"><?= xlt('Corrective Action Taken') ?></label>
            <textarea name="corrective_action" class="form-control" rows="2"
                      placeholder="<?= xlt('Immediate actions taken…') ?>"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-danger"><?= xlt('Submit Report') ?></button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
        </div>
      </form>
    </div>
  </div>
</div>

<?= institutional_bootstrap5_js_tag() ?>
</div>
</body>
</html>










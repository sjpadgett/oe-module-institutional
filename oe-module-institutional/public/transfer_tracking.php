<?php

/**
 * public/transfer_tracking.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
$pageTitle = xlt('Transfers');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\TransferTracking\Repository\TransferRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

if (!$manifest->featureEnabled('transfer_tracking')) {
    die(xlt("Transfers is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId = (int)($_GET['episode_id'] ?? 0);
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$episodeRepo = new EpisodeRepository();
$episodes = $episodeRepo->fetchBoard($facilityId);

if ($episodeId <= 0 && !empty($episodes)) {
    $episodeId = (int)($episodes[0]['id'] ?? 0);
}

$selected = null;
foreach ($episodes as $e) {
    if ((int)$e['id'] === $episodeId) { $selected = $e; break; }
}
if (!$selected) {
    die(xlt("No active episode selected"));
}

$pid = (int)($selected['pid'] ?? 0);
$eid = isset($selected['eid']) && is_numeric((string)$selected['eid']) ? (int)$selected['eid'] : null;

$repo = new TransferRepository();
$dirRepo = new FacilityDirectoryRepository();
$events = new EpisodeEventRepository();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }
    $type = trim((string)($_POST['transfer_type'] ?? 'TRANSFER')) ?: 'TRANSFER';
    $reason = trim((string)($_POST['reason'] ?? '')) ?: null;
    $dirIdRaw = (string)($_POST['receiving_directory_id'] ?? '');
    $dirId = is_numeric($dirIdRaw) ? (int)$dirIdRaw : null;
    $recvName = trim((string)($_POST['receiving_name'] ?? '')) ?: null;

    $requested = trim((string)($_POST['requested_datetime'] ?? '')) ?: null;
    $accepted = trim((string)($_POST['accepted_datetime'] ?? '')) ?: null;
    $transport = trim((string)($_POST['transport_datetime'] ?? '')) ?: null;
    $requested = $requested ? str_replace('T',' ', $requested) . ':00' : null;
    $accepted = $accepted ? str_replace('T',' ', $accepted) . ':00' : null;
    $transport = $transport ? str_replace('T',' ', $transport) . ':00' : null;

    $status = strtoupper(trim((string)($_POST['status'] ?? 'PENDING'))) ?: 'PENDING';
    $notes = trim((string)($_POST['notes'] ?? '')) ?: null;

    $check = [
      'summary_sent' => !empty($_POST['chk_summary_sent']),
      'labs_sent' => !empty($_POST['chk_labs_sent']),
      'imaging_sent' => !empty($_POST['chk_imaging_sent']),
      'meds_reconciled' => !empty($_POST['chk_meds_reconciled']),
      'acceptance_doc' => !empty($_POST['chk_acceptance_doc']),
      'handoff_complete' => !empty($_POST['chk_handoff_complete']),
    ];
    $checkJson = json_encode($check);

    // Auto-fill receiving name if directory chosen
    if ($dirId && !$recvName) {
        $d = $dirRepo->get($facilityId, $dirId);
        if (!empty($d['name'])) $recvName = (string)$d['name'];
    }

    $repo->upsert($episodeId, $pid, $eid, $facilityId, $type, $reason, $dirId, $recvName, $requested, $accepted, $transport, $status, $checkJson, $notes, $userId);

    // Stamp general events for throughput (optional)
    if ($requested) $events->addEvent($episodeId, $pid, $eid, $facilityId, 'XFER_REQUESTED', $requested, $userId, $recvName);
    if ($accepted) $events->addEvent($episodeId, $pid, $eid, $facilityId, 'XFER_ACCEPTED', $accepted, $userId, $recvName);
    if ($transport) $events->addEvent($episodeId, $pid, $eid, $facilityId, 'XFER_TRANSPORT', $transport, $userId, null);

    header("Location: transfers.php?facility_id=" . urlencode((string)$facilityId) . "&episode_id=" . urlencode((string)$episodeId));
    exit;
}

$csrf = CsrfUtils::collectCsrfToken();
$transfer = $repo->getByEpisode($episodeId) ?: [];
$directory = $dirRepo->listActive($facilityId);

$check = [];
if (!empty($transfer['checklist_json'])) {
    $tmp = json_decode((string)$transfer['checklist_json'], true);
    if (is_array($tmp)) $check = $tmp;
}
$requestedVal = !empty($transfer['requested_datetime']) ? str_replace(' ', 'T', substr((string)$transfer['requested_datetime'], 0, 16)) : '';
$acceptedVal = !empty($transfer['accepted_datetime']) ? str_replace(' ', 'T', substr((string)$transfer['accepted_datetime'], 0, 16)) : '';
$transportVal = !empty($transfer['transport_datetime']) ? str_replace(' ', 'T', substr((string)$transfer['transport_datetime'], 0, 16)) : '';

$_ttPids = array_values(array_unique(array_filter(array_map(fn($e)=>(int)($e['pid']??0), $episodes??[]))));
$_ttPatientNames = oei_patient_names($_ttPids);
$href = institutional_bootstrap5_href($manifest);

$statuses = [
  'PENDING' => xlt('Pending'),
  'ACCEPTED' => xlt('Accepted'),
  'DECLINED' => xlt('Declined'),
  'CANCELLED' => xlt('Cancelled'),
  'COMPLETED' => xlt('Completed'),
];

$types = [
  'TRANSFER' => xlt('Transfer'),
  'ADMIT_TO_HOSPITAL' => xlt('Admit to Hospital'),
  'OBS_TO_INPATIENT' => xlt('Obs to Inpatient'),
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Transfers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Transfers") ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
  </div>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Active Episodes") ?></div>
        <div class="list-group list-group-flush">
          <?php foreach ($episodes as $e): ?>
                <?php $active = ((int)$e['id'] === $episodeId); ?>
            <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
               href="transfers.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$e['id']) ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-semibold">#<?= htmlspecialchars((string)$e['id']) ?> <?= oei_fmt_patient((int)($e['pid'] ?? 0), $_ttPatientNames) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?></div>
                </div>
                <div class="text-end">
                  <span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Transfer Tracking") ?> • #<?= htmlspecialchars((string)$episodeId) ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Type") ?></label>
              <select name="transfer_type" class="form-select">
                <?php foreach ($types as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= ((string)($transfer['transfer_type'] ?? 'TRANSFER') === (string)$k) ? 'selected' : '' ?>><?= htmlspecialchars((string)$lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Status") ?></label>
              <select name="status" class="form-select">
                <?php foreach ($statuses as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= ((string)($transfer['status'] ?? 'PENDING') === (string)$k) ? 'selected' : '' ?>><?= htmlspecialchars((string)$lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Receiving Facility") ?></label>
              <select name="receiving_directory_id" class="form-select">
                <option value=""><?= xlt("Select from directory...") ?></option>
                <?php foreach ($directory as $d): ?>
                  <option value="<?= htmlspecialchars((string)$d['id']) ?>" <?= ((string)($transfer['receiving_directory_id'] ?? '') === (string)$d['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$d['name']) ?> (<?= htmlspecialchars((string)$d['service_type']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text"><?= xlt("Or type a name below.") ?></div>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Receiving Name (manual)") ?></label>
              <input name="receiving_name" class="form-control" value="<?= htmlspecialchars((string)($transfer['receiving_name'] ?? '')) ?>">
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Reason") ?></label>
              <input name="reason" class="form-control" value="<?= htmlspecialchars((string)($transfer['reason'] ?? '')) ?>">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Requested") ?></label>
              <input type="datetime-local" name="requested_datetime" class="form-control" value="<?= htmlspecialchars($requestedVal) ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Accepted") ?></label>
              <input type="datetime-local" name="accepted_datetime" class="form-control" value="<?= htmlspecialchars($acceptedVal) ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Transport") ?></label>
              <input type="datetime-local" name="transport_datetime" class="form-control" value="<?= htmlspecialchars($transportVal) ?>">
            </div>

            <div class="col-12">
              <div class="card border-0 bg-body-tertiary">
                <div class="card-body">
                  <div class="fw-semibold mb-2"><?= xlt("Checklist") ?></div>
                  <div class="row g-2">
                    <div class="col-12 col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_summary_sent" id="chk_summary_sent" value="1" <?= !empty($check['summary_sent']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_summary_sent"><?= xlt("Clinical summary sent") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_labs_sent" id="chk_labs_sent" value="1" <?= !empty($check['labs_sent']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_labs_sent"><?= xlt("Labs sent") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_imaging_sent" id="chk_imaging_sent" value="1" <?= !empty($check['imaging_sent']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_imaging_sent"><?= xlt("Imaging sent") ?></label>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_meds_reconciled" id="chk_meds_reconciled" value="1" <?= !empty($check['meds_reconciled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_meds_reconciled"><?= xlt("Med reconciliation complete") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_acceptance_doc" id="chk_acceptance_doc" value="1" <?= !empty($check['acceptance_doc']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_acceptance_doc"><?= xlt("Acceptance documented") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_handoff_complete" id="chk_handoff_complete" value="1" <?= !empty($check['handoff_complete']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_handoff_complete"><?= xlt("Handoff complete") ?></label>
                      </div>
                    </div>
                  </div>
                  <div class="form-text mt-2"><?= xlt("Checklist is stored as JSON for now; later we can bind to documents/forms.") ?></div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Notes") ?></label>
              <input name="notes" class="form-control" value="<?= htmlspecialchars((string)($transfer['notes'] ?? '')) ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><?= xlt("Save") ?></button>
              <a class="btn btn-outline-secondary" href="facility_directory.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Directory") ?></a>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>










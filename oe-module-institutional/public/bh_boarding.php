<?php

/**
 * public/bh_boarding.php
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
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhBoarding\Repository\BhBoardingRepository;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhBoarding\Controller\BhBoardingController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

if (!$manifest->featureEnabled('bh_boarding')) {
    die(xlt("Institutional BH Boarding is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId = (int)($_GET['episode_id'] ?? 0);

$episodeRepo = new EpisodeRepository();
$episodes = $episodeRepo->fetchBoard($facilityId);

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

if ($episodeId <= 0 && !empty($episodes)) {
    $episodeId = (int)($episodes[0]['id'] ?? 0);
}

$selected = null;
foreach ($episodes as $e) {
    if ((int)$e['id'] === $episodeId) {
        $selected = $e;
        break;
    }
}
if (!$selected) {
    die(xlt("No active episode selected"));
}

$pid = (int)($selected['pid'] ?? 0);
$eid = isset($selected['eid']) && is_numeric($selected['eid']) ? (int)$selected['eid'] : null;

$repo = new BhBoardingRepository();
$events = new EpisodeEventRepository();
$controller = new BhBoardingController($repo, $events);
$data = $controller->handle($facilityId, $episodeId, $pid, $eid, $userId);


// Institutional: capture controller errors (avoid silent failures)
if (is_string($data) && $data !== '') {
    \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data));
    $data = [];
} elseif (is_array($data)) {
    if (!empty($data['error']) && is_string($data['error'])) {
        \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($data['error']));
    }
    if (!empty($data['errors']) && is_array($data['errors'])) {
        foreach ($data['errors'] as $err) {
            if (is_string($err) && $err !== '') {
                \OpenEMR\Modules\Institutional\Core\Ui\Flash::addError(xlt($err));
            }
        }
    }
}
$_bhbPids = array_values(array_unique(array_filter(array_map(fn($e)=>(int)($e['pid']??0), $episodes??[]))));
$_bhbPatientNames = oei_patient_names($_bhbPids);
$href = institutional_bootstrap5_href($manifest);

$bh = $data['bh'] ?? [];
$acceptedVal = '';
$transportVal = '';
if (!empty($bh['accepted_datetime'])) {
    $acceptedVal = str_replace(' ', 'T', substr((string)$bh['accepted_datetime'], 0, 16));
}
if (!empty($bh['transport_datetime'])) {
    $transportVal = str_replace(' ', 'T', substr((string)$bh['transport_datetime'], 0, 16));
}

$check = [];
if (!empty($bh['checklist_json'])) {
    $tmp = json_decode((string)$bh['checklist_json'], true);
    if (is_array($tmp)) $check = $tmp;
}

$placementStatuses = [
  'SEARCHING' => xlt('Searching'),
  'PENDING' => xlt('Pending Acceptance'),
  'ACCEPTED' => xlt('Accepted'),
  'TRANSPORT_SCHEDULED' => xlt('Transport Scheduled'),
  'TRANSFERRED' => xlt('Transferred'),
  'ADMITTED' => xlt('Admitted'),
  'DECLINED' => xlt('Declined'),
];

$risks = [
  '' => xlt('—'),
  'LOW' => xlt('Low'),
  'MODERATE' => xlt('Moderate'),
  'HIGH' => xlt('High'),
];

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BH Boarding</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("BH Boarding / Transfer") ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="throughput.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("Throughput") ?></a>
    </div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-info"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>

  <div class="row g-3">
    <div class="col-12 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Active Episodes") ?></div>
        <div class="list-group list-group-flush">
          <?php foreach ($episodes as $e): ?>
                <?php $active = ((int)$e['id'] === $episodeId); ?>
            <a class="list-group-item list-group-item-action <?= $active ? 'active' : '' ?>"
               href="bh_boarding.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$e['id']) ?>">
              <div class="d-flex justify-content-between">
                <div>
                  <div class="fw-semibold">#<?= htmlspecialchars((string)$e['id']) ?> <?= oei_fmt_patient((int)($e['pid'] ?? 0), $_bhbPatientNames) ?></div>
                  <div class="small opacity-75"><?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?></div>
                </div>
                <div class="text-end">
                  <span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span>
                  <?php if (!empty($e['disposition'])): ?><span class="badge text-bg-info"><?= htmlspecialchars((string)$e['disposition']) ?></span><?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-8">
      <div class="card shadow-sm mb-3">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt("BH Boarding Details") ?> • #<?= htmlspecialchars((string)$episodeId) ?></span>
          <a class="btn btn-sm btn-outline-secondary" href="bh_packet.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt("Print packet") ?></a>
        </div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$data['csrf']) ?>">

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Placement Status") ?></label>
              <select name="placement_status" class="form-select">
                <?php foreach ($placementStatuses as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= (!empty($bh['placement_status']) && (string)$bh['placement_status'] === (string)$k) ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string)$lbl) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Accepting Facility") ?></label>
              <input name="accepting_facility" class="form-control" value="<?= htmlspecialchars((string)($bh['accepting_facility'] ?? '')) ?>" placeholder="<?= xla("Facility name") ?>">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label"><?= xlt("Accepted Time") ?></label>
              <input type="datetime-local" name="accepted_datetime" class="form-control" value="<?= htmlspecialchars($acceptedVal) ?>">
            </div>

            <div class="col-12 col-md-3">
              <label class="form-label"><?= xlt("Transport Method") ?></label>
              <input name="transport_method" class="form-control" value="<?= htmlspecialchars((string)($bh['transport_method'] ?? '')) ?>" placeholder="<?= xla("Ambulance/LE/Family") ?>">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label"><?= xlt("Transport Time") ?></label>
              <input type="datetime-local" name="transport_datetime" class="form-control" value="<?= htmlspecialchars($transportVal) ?>">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Legal Status") ?></label>
              <input name="legal_status" class="form-control" value="<?= htmlspecialchars((string)($bh['legal_status'] ?? '')) ?>" placeholder="<?= xla("Voluntary / IVC / Hold") ?>">
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Suicide Risk") ?></label>
              <select name="suicide_risk" class="form-select">
                <?php foreach ($risks as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= ((string)($bh['suicide_risk'] ?? '') === (string)$k) ? 'selected' : '' ?>><?= htmlspecialchars((string)$lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label"><?= xlt("Violence Risk") ?></label>
              <select name="violence_risk" class="form-select">
                <?php foreach ($risks as $k => $lbl): ?>
                  <option value="<?= htmlspecialchars($k) ?>" <?= ((string)($bh['violence_risk'] ?? '') === (string)$k) ? 'selected' : '' ?>><?= htmlspecialchars((string)$lbl) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" value="1" id="emtala_complete" name="emtala_complete" <?= !empty($bh['emtala_complete']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="emtala_complete"><?= xlt("EMTALA transfer requirements complete") ?></label>
              </div>
            </div>

            <div class="col-12">
              <div class="card border-0 bg-body-tertiary">
                <div class="card-body">
                  <div class="fw-semibold mb-2"><?= xlt("Transfer Packet Checklist") ?></div>
                  <div class="row g-2">
                    <div class="col-12 col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_mdm_complete" id="chk_mdm_complete" value="1" <?= !empty($check['mdm_complete']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_mdm_complete"><?= xlt("Provider note / MDM complete") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_labs_printed" id="chk_labs_printed" value="1" <?= !empty($check['labs_printed']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_labs_printed"><?= xlt("Labs printed/sent") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_imaging_sent" id="chk_imaging_sent" value="1" <?= !empty($check['imaging_sent']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_imaging_sent"><?= xlt("Imaging sent") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_meds_reconciled" id="chk_meds_reconciled" value="1" <?= !empty($check['meds_reconciled']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_meds_reconciled"><?= xlt("Med reconciliation complete") ?></label>
                      </div>
                    </div>
                    <div class="col-12 col-md-6">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_consent_signed" id="chk_consent_signed" value="1" <?= !empty($check['consent_signed']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_consent_signed"><?= xlt("Consent/transfer acceptance documented") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_nursing_report" id="chk_nursing_report" value="1" <?= !empty($check['nursing_report']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_nursing_report"><?= xlt("Nursing report given") ?></label>
                      </div>
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="chk_transfer_form" id="chk_transfer_form" value="1" <?= !empty($check['transfer_form']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chk_transfer_form"><?= xlt("Transfer form completed") ?></label>
                      </div>
                    </div>
                  </div>
                  <div class="form-text mt-2"><?= xlt("Checklist is stored as JSON for now; later we can bind to OpenEMR forms/documents.") ?></div>
                </div>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label"><?= xlt("Notes") ?></label>
              <input name="notes" class="form-control" value="<?= htmlspecialchars((string)($bh['notes'] ?? '')) ?>" placeholder="<?= xla("Short note...") ?>">
            </div>

            <div class="col-12 d-flex gap-2">
              <button class="btn btn-primary"><?= xlt("Save") ?></button>
              <a class="btn btn-outline-secondary" href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt("Disposition") ?></a>
            </div>

            <div class="col-12">
              <div class="form-text">
                <?= xlt("Saving Accepted/Transport times records throughput events BH_ACCEPTED and BH_TRANSPORT. EMTALA complete stamps EMTALA_COMPLETE.") ?>
              </div>
            </div>

          </form>
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Quick status") ?></div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2">
            <span class="badge text-bg-secondary"><?= xlt("Status") ?>: <?= htmlspecialchars((string)($bh['placement_status'] ?? 'SEARCHING')) ?></span>
            <?php if (!empty($bh['accepting_facility'])): ?><span class="badge text-bg-info"><?= htmlspecialchars((string)$bh['accepting_facility']) ?></span><?php endif; ?>
            <?php if (!empty($bh['suicide_risk'])): ?><span class="badge text-bg-warning"><?= xlt("Suicide") ?>: <?= htmlspecialchars((string)$bh['suicide_risk']) ?></span><?php endif; ?>
            <?php if (!empty($bh['violence_risk'])): ?><span class="badge text-bg-warning"><?= xlt("Violence") ?>: <?= htmlspecialchars((string)$bh['violence_risk']) ?></span><?php endif; ?>
            <?php if (!empty($bh['emtala_complete'])): ?><span class="badge text-bg-success"><?= xlt("EMTALA complete") ?></span><?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>
</body>
</html>










<?php

/**
 * public/ereferral.php
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
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Controller\EReferralController;
use OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Repository\EReferralRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Service\EReferralService;
use OpenEMR\Modules\Institutional\Operations\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarOrderRepository;

if (!$manifest->featureEnabled('ereferral')) {
    die(xlt("Institutional E-Referral is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId  = (int)($_GET['episode_id'] ?? 0);
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

// Sidebar: active episodes for navigation
$episodeRepo = new EpisodeRepository();
$episodes    = $episodeRepo->fetchBoard($facilityId);

if ($episodeId <= 0 && !empty($episodes)) {
    $episodeId = (int)($episodes[0]['id'] ?? 0);
}

// Resolve selected episode for sidebar highlight
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

// Context-aware profile back URL
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';
$_eref_episodeType = strtoupper((string)($selected['type'] ?? 'ED'));
$_eref_pid         = (int)($selected['pid'] ?? 0);
$_eref_backUrl = match ($_eref_episodeType) {
    'AL'  => $_oei_pub_base . 'al/profile.php?episode_id='  . $episodeId . '&pid=' . $_eref_pid . '&facility_id=' . $facilityId,
    'IP'  => $_oei_pub_base . 'ip/profile.php?episode_id='  . $episodeId . '&pid=' . $_eref_pid . '&facility_id=' . $facilityId,
    'HBC' => $_oei_pub_base . 'hbc/profile.php?episode_id=' . $episodeId . '&pid=' . $_eref_pid . '&facility_id=' . $facilityId,
    default => $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId,
};
$_eref_backLabel = match ($_eref_episodeType) {
    'AL'  => xlt('Resident Profile'),
    'IP'  => xlt('IP Profile'),
    'HBC' => xlt('HBC Profile'),
    default => xlt('ED Board'),
};

$controller = new EReferralController(
    new EReferralRepository(),
    new EReferralService(new EReferralRepository(), new FacilityDirectoryRepository(), new MarOrderRepository()),
    $episodeRepo,
    new DispositionRepository(),
    new FacilityDirectoryRepository()
);

$data = $controller->handle($facilityId, $episodeId, $userId);

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

// ── Facility referral dashboard data (Drop C) ──────────────────────────
$_erefAllReferrals    = (new EReferralRepository())->listByFacility($facilityId, 100);
$_erefDashPids        = array_values(array_unique(array_filter(
    array_map(fn($r) => (int)($r['pid'] ?? 0), $_erefAllReferrals)
)));

$_erefPids = array_values(array_unique(array_filter([
    (int)($episode['pid'] ?? 0),
    ...array_map(fn($e)=>(int)($e['pid']??0), $episodes??[]),
    ...$_erefDashPids,
])));
$_erefPatientNames = oei_patient_names($_erefPids);
$href      = institutional_bootstrap5_href($manifest);
$referral   = $data['referral']   ?? [];
$episode    = $data['episode']    ?? $selected;
$directory  = $data['directory']  ?? [];
$allergies  = $data['allergies']  ?? '';
$isPrint    = isset($_GET['action']) && $_GET['action'] === 'print';

// ── Print view ────────────────────────────────────────────────────────────────
if ($isPrint) {
    // Render a clean printable referral and exit
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Discharge Referral') ?> #<?= htmlspecialchars((string)$episodeId) ?></title>
  <style>
    body { font-family: Arial, sans-serif; font-size: 11pt; margin: 2cm; color: #000; }
    h1   { font-size: 15pt; border-bottom: 2px solid #000; padding-bottom: 4px; }
    h2   { font-size: 12pt; margin-top: 16px; margin-bottom: 4px; border-bottom: 1px solid #999; }
    .row { display: flex; gap: 40px; margin-bottom: 8px; }
    .label { font-weight: bold; min-width: 180px; }
    pre  { white-space: pre-wrap; font-family: inherit; margin: 0; }
    .sig { margin-top: 40px; border-top: 1px solid #000; padding-top: 8px; }
    @media print { .no-print { display: none; } }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<body>
  <p class="no-print"><a href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">&larr; <?= xlt('Back') ?></a> &nbsp;
    <button onclick="window.print()"><?= xlt('Print') ?></button></p>

  <h1><?= xlt('Discharge / Transfer Referral') ?></h1>

  <div class="row">
    <div><span class="label"><?= xlt('Date') ?>:</span> <?= htmlspecialchars(date('F j, Y')) ?></div>
    <div><span class="label"><?= xlt('Episode') ?>:</span> #<?= htmlspecialchars((string)$episodeId) ?></div>
    <div><span class="label"><?= xlt('Patient') ?>:</span> <?= oei_fmt_patient((int)($episode['pid']??0), $_erefPatientNames) ?></div>
  </div>
  <div class="row">
    <div><span class="label"><?= xlt('Referral Type') ?>:</span> <?= htmlspecialchars((string)($referral['referral_type'] ?? '')) ?></div>
    <div><span class="label"><?= xlt('Priority') ?>:</span> <?= htmlspecialchars((string)($referral['priority'] ?? '')) ?></div>
  </div>

  <h2><?= xlt('Receiving Facility') ?></h2>
  <div><span class="label"><?= xlt('Name') ?>:</span> <?= htmlspecialchars((string)($referral['destination_name'] ?? $referral['dir_name'] ?? '')) ?></div>
  <div><span class="label"><?= xlt('Phone') ?>:</span> <?= htmlspecialchars((string)($referral['destination_phone'] ?? $referral['dir_phone'] ?? '')) ?></div>
  <div><span class="label"><?= xlt('Fax') ?>:</span> <?= htmlspecialchars((string)($referral['destination_fax'] ?? $referral['dir_fax'] ?? '')) ?></div>
  <div><span class="label"><?= xlt('Address') ?>:</span> <?= htmlspecialchars((string)($referral['destination_address'] ?? $referral['dir_address'] ?? '')) ?></div>

  <h2><?= xlt('Reason for Referral') ?></h2>
  <pre><?= htmlspecialchars((string)($referral['reason_for_referral'] ?? '')) ?></pre>

  <h2><?= xlt('Clinical Summary') ?></h2>
  <pre><?= htmlspecialchars((string)($referral['clinical_summary'] ?? '')) ?></pre>

    <?php if (!empty($referral['medications_summary'])): ?>
  <h2><?= xlt('Current Medications') ?></h2>
  <pre><?= htmlspecialchars((string)$referral['medications_summary']) ?></pre>
  <?php endif; ?>

  <h2><?= xlt('Allergies') ?></h2>
  <pre><?= htmlspecialchars($allergies ?: xlt('None documented / not checked')) ?></pre>

  <h2><?= xlt('Services Requested') ?></h2>
  <pre><?= htmlspecialchars((string)($referral['services_requested'] ?? '')) ?></pre>

    <?php if (!empty($referral['followup_instructions'])): ?>
  <h2><?= xlt('Follow-up Instructions') ?></h2>
  <pre><?= htmlspecialchars((string)$referral['followup_instructions']) ?></pre>
  <?php endif; ?>

  <div class="sig">
    <div class="row">
      <div><?= xlt('Referring Clinician') ?>: ___________________________ <?= xlt('Date') ?>: ________________</div>
    </div>
    <div class="row" style="margin-top:16px;">
      <div><?= xlt('Receiving Signature') ?>: ___________________________ <?= xlt('Date') ?>: ________________</div>
    </div>
  </div>
  <p style="margin-top:30px; font-size:9pt; color:#666;">
    <?= xlt('Generated by') ?> oe-module-institutional &bull;
    <?= htmlspecialchars(date('Y-m-d H:i')) ?>
  </p>
</body>
</html>
    <?php
    exit;
}
// ── End print view ────────────────────────────────────────────────────────────

// Status badge helper
function eref_status_badge(string $status): string
{
    return match ($status) {
        'DRAFT'     => 'text-bg-secondary',
        'SENT'      => 'text-bg-primary',
        'ACCEPTED'  => 'text-bg-success',
        'DECLINED'  => 'text-bg-danger',
        'CANCELLED' => 'text-bg-dark',
        default     => 'text-bg-light border',
    };
}

function eref_priority_badge(string $priority): string
{
    return match ($priority) {
        'EMERGENT' => 'text-bg-danger',
        'URGENT'   => 'text-bg-warning',
        default    => 'text-bg-light border',
    };
}

$referralStatus   = (string)($referral['status']   ?? 'DRAFT');
$referralPriority = (string)($referral['priority'] ?? 'ROUTINE');
$isDraft          = ($referralStatus === 'DRAFT');
$isSent           = in_array($referralStatus, ['SENT', 'ACCEPTED', 'DECLINED', 'CANCELLED'], true);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('E-Referral') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Discharge E-Referral") ?></h1>
    <div class="d-flex gap-2">
      <?php if ($referral): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>&action=print"
           target="_blank"><?= xlt("Print / Fax Sheet") ?></a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary"
         href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">
        <?= xlt("Disposition") ?>
      </a>
      <a class="btn btn-sm btn-outline-secondary"
         href="<?= htmlspecialchars($_eref_backUrl) ?>">
        ← <?= htmlspecialchars($_eref_backLabel) ?>
      </a>
    </div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>
  <?php if (!empty($data['error'])): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$data['error']) ?></div>
  <?php endif; ?>

  <?php if (!empty($_erefAllReferrals)): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="fw-semibold"><?= xlt('Facility Referral Dashboard') ?></span>
      <span class="badge text-bg-secondary"><?= count($_erefAllReferrals) ?> <?= xlt('referral(s)') ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0" style="font-size:.85rem">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Episode') ?></th>
            <th><?= xlt('Patient') ?></th>
            <th><?= xlt('Type') ?></th>
            <th><?= xlt('Priority') ?></th>
            <th><?= xlt('Status') ?></th>
            <th><?= xlt('Destination') ?></th>
            <th><?= xlt('Sent') ?></th>
            <th><?= xlt('Age') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($_erefAllReferrals as $_dr):
            $_drStatus   = (string)($_dr['status']   ?? 'DRAFT');
            $_drPriority = (string)($_dr['priority'] ?? 'ROUTINE');
            $_drUpdated  = (string)($_dr['updated_datetime'] ?? '');
            $_drAge      = $_drUpdated ? (int)floor((time() - strtotime($_drUpdated)) / 3600) : 0;
            ?>
          <tr>
            <td><a href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= urlencode((string)$_dr['episode_id']) ?>">
              #<?= htmlspecialchars((string)$_dr['episode_id']) ?></a>
            </td>
            <td><?= function_exists('oei_fmt_patient')
                ? oei_fmt_patient((int)($_dr['pid'] ?? 0), $_erefPatientNames)
                : htmlspecialchars((string)$_dr['pid']) ?>
            </td>
            <td><span class="badge text-bg-secondary"><?= htmlspecialchars($_dr['referral_type'] ?? '') ?></span></td>
            <td><span class="badge <?= eref_priority_badge($_drPriority) ?>"><?= htmlspecialchars($_drPriority) ?></span></td>
            <td><span class="badge <?= eref_status_badge($_drStatus) ?>"><?= htmlspecialchars($_drStatus) ?></span></td>
            <td class="text-truncate" style="max-width:140px"><?= htmlspecialchars((string)($_dr['destination_name'] ?? '—')) ?></td>
            <td class="text-muted small"><?= htmlspecialchars(substr((string)($_dr['sent_datetime'] ?? '—'), 0, 16)) ?></td>
            <td class="text-muted small"><?= $_drAge > 0 ? htmlspecialchars((string)$_drAge) . 'h' : '&lt;1h' ?></td>
            <td>
              <a class="btn btn-xs btn-outline-primary" style="font-size:.75rem;padding:.15rem .4rem;"
                 href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&amp;episode_id=<?= urlencode((string)$_dr['episode_id']) ?>"><?= xlt('Open') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="row g-3">

    <!-- ── Episode sidebar ─────────────────────────────────────── -->
    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header small fw-semibold"><?= xlt("Active Episodes") ?></div>
        <div class="list-group list-group-flush" style="max-height:420px;overflow-y:auto;">
          <?php foreach ($episodes as $e):
                $active = ((int)$e['id'] === $episodeId);
                ?>
            <a class="list-group-item list-group-item-action py-2 <?= $active ? 'active' : '' ?>"
               href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$e['id']) ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold small">#<?= htmlspecialchars((string)$e['id']) ?> <?= oei_fmt_patient((int)($e['pid']??0), $_erefPatientNames) ?></div>
                  <div class="small opacity-75 text-truncate" style="max-width:140px;">
                    <?= htmlspecialchars((string)($e['chief_complaint'] ?? '—')) ?>
                  </div>
                </div>
                <div class="text-end">
                  <?php if (!empty($e['disposition'])): ?>
                    <span class="badge text-bg-info" style="font-size:.65rem;"><?= htmlspecialchars((string)$e['disposition']) ?></span>
                  <?php endif; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ── Main referral panel ─────────────────────────────────── -->
    <div class="col-12 col-lg-9">

      <?php if (!$referral): ?>
      <!-- No disposition set yet -->
      <div class="card shadow-sm">
        <div class="card-body text-muted text-center py-5">
          <p class="mb-2"><?= xlt("No referral yet. Set a disposition first — a draft will be generated automatically.") ?></p>
          <a href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"
             class="btn btn-primary"><?= xlt("Go to Disposition") ?></a>
        </div>
      </div>

      <?php else: ?>

      <!-- ── Status banner ── -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2 d-flex align-items-center gap-3">
          <span class="fw-semibold"><?= xlt("Status") ?>:</span>
          <span class="badge <?= eref_status_badge($referralStatus) ?> fs-6">
            <?= htmlspecialchars($referralStatus) ?>
          </span>
          <span class="badge <?= eref_priority_badge($referralPriority) ?>">
            <?= htmlspecialchars($referralPriority) ?>
          </span>
          <?php if (!empty($referral['sent_datetime'])): ?>
            <span class="text-muted small">
                <?= xlt("Sent") ?>: <?= htmlspecialchars((string)$referral['sent_datetime']) ?>
            </span>
          <?php endif; ?>
          <?php if (!empty($referral['response_datetime'])): ?>
            <span class="text-muted small">
                <?= xlt("Response") ?>: <?= htmlspecialchars((string)$referral['response_datetime']) ?>
                <?= !empty($referral['response_by_name']) ? '— ' . htmlspecialchars((string)$referral['response_by_name']) : '' ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Edit / review form ── -->
      <div class="card shadow-sm mb-3">
        <div class="card-header"><?= $isDraft ? xlt("Review & Edit Referral") : xlt("Referral Details") ?></div>
        <div class="card-body">
          <form method="post" id="referralForm">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
            <input type="hidden" name="facility_id"     value="<?= htmlspecialchars((string)$facilityId) ?>">
            <input type="hidden" name="episode_id"      value="<?= htmlspecialchars((string)$episodeId) ?>">

            <div class="row g-3">

              <!-- Referral type + priority -->
              <div class="col-md-4">
                <label class="form-label"><?= xlt("Referral Type") ?></label>
                <select name="referral_type" class="form-select form-select-sm" <?= $isSent ? 'disabled' : '' ?>>
                  <?php foreach (['DISCHARGE' => xlt('Discharge'), 'TRANSFER' => xlt('Transfer'), 'BH_PLACEMENT' => xlt('BH Placement')] as $v => $lbl): ?>
                    <option value="<?= htmlspecialchars($v) ?>"
                        <?= ((string)($referral['referral_type'] ?? 'DISCHARGE') === $v) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$lbl) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-3">
                <label class="form-label"><?= xlt("Priority") ?></label>
                <select name="priority" class="form-select form-select-sm" <?= $isSent ? 'disabled' : '' ?>>
                  <?php foreach (['ROUTINE' => xlt('Routine'), 'URGENT' => xlt('Urgent'), 'EMERGENT' => xlt('Emergent')] as $v => $lbl): ?>
                    <option value="<?= htmlspecialchars($v) ?>"
                        <?= ((string)($referral['priority'] ?? 'ROUTINE') === $v) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$lbl) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <!-- Destination: directory lookup -->
              <?php if (!empty($directory)): ?>
              <div class="col-md-5">
                <label class="form-label"><?= xlt("Destination (Facility Directory)") ?></label>
                <select name="destination_directory_id" class="form-select form-select-sm" <?= $isSent ? 'disabled' : '' ?>>
                  <option value=""><?= xlt("— Manual entry below —") ?></option>
                    <?php foreach ($directory as $dir): ?>
                    <option value="<?= htmlspecialchars((string)$dir['id']) ?>"
                        <?= ((int)($referral['destination_directory_id'] ?? 0) === (int)$dir['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string)$dir['name']) ?>
                        <?php if (!empty($dir['service_type'])): ?>
                        (<?= htmlspecialchars((string)$dir['service_type']) ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php endif; ?>

              <!-- Destination free-text fields -->
              <div class="col-md-6">
                <label class="form-label"><?= xlt("Receiving Facility Name") ?></label>
                <input name="destination_name" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string)($referral['destination_name'] ?? '')) ?>"
                       <?= $isSent ? 'disabled' : '' ?>>
              </div>
              <div class="col-md-3">
                <label class="form-label"><?= xlt("Fax") ?></label>
                <input name="destination_fax" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string)($referral['destination_fax'] ?? $referral['dir_fax'] ?? '')) ?>"
                       <?= $isSent ? 'disabled' : '' ?>>
              </div>
              <div class="col-md-3">
                <label class="form-label"><?= xlt("Phone") ?></label>
                <input name="destination_phone" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string)($referral['destination_phone'] ?? $referral['dir_phone'] ?? '')) ?>"
                       <?= $isSent ? 'disabled' : '' ?>>
              </div>
              <div class="col-12">
                <label class="form-label"><?= xlt("Address") ?></label>
                <input name="destination_address" class="form-control form-control-sm"
                       value="<?= htmlspecialchars((string)($referral['destination_address'] ?? $referral['dir_address'] ?? '')) ?>"
                       <?= $isSent ? 'disabled' : '' ?>>
              </div>

              <!-- Clinical content -->
              <div class="col-12">
                <label class="form-label"><?= xlt("Reason for Referral") ?></label>
                <textarea name="reason_for_referral" rows="2"
                          class="form-control form-control-sm"
                          <?= $isSent ? 'disabled' : '' ?>><?= htmlspecialchars((string)($referral['reason_for_referral'] ?? '')) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label"><?= xlt("Clinical Summary") ?> <small class="text-muted"><?= xlt("(auto-filled from episode and vitals)") ?></small></label>
                <textarea name="clinical_summary" rows="4"
                          class="form-control form-control-sm"
                          <?= $isSent ? 'disabled' : '' ?>><?= htmlspecialchars((string)($referral['clinical_summary'] ?? '')) ?></textarea>
              </div>
              <?php if ($allergies !== ''): ?>
              <div class="col-12">
                <label class="form-label"><?= xlt("Documented Allergies") ?> <small class="text-muted"><?= xlt("(from OpenEMR patient record — read only)") ?></small></label>
                <div class="form-control form-control-sm bg-warning bg-opacity-10" style="height:auto;min-height:60px;white-space:pre-wrap;font-size:.85rem;"><?= htmlspecialchars($allergies) ?></div>
              </div>
              <?php endif; ?>
              <div class="col-md-6">
                <label class="form-label"><?= xlt("Current Medications") ?> <small class="text-muted"><?= xlt("(auto-filled from active MAR orders)") ?></small></label>
                <textarea name="medications_summary" rows="3"
                          class="form-control form-control-sm"
                          placeholder="<?= xla("List key discharge medications") ?>"
                          <?= $isSent ? 'disabled' : '' ?>><?= htmlspecialchars((string)($referral['medications_summary'] ?? '')) ?></textarea>
              </div>
              <div class="col-md-6">
                <label class="form-label"><?= xlt("Services Requested") ?></label>
                <textarea name="services_requested" rows="3"
                          class="form-control form-control-sm"
                          <?= $isSent ? 'disabled' : '' ?>><?= htmlspecialchars((string)($referral['services_requested'] ?? '')) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label"><?= xlt("Follow-up Instructions") ?></label>
                <textarea name="followup_instructions" rows="2"
                          class="form-control form-control-sm"
                          placeholder="<?= xla("e.g. PCP follow-up within 7 days, wound check in 3 days") ?>"
                          <?= $isSent ? 'disabled' : '' ?>><?= htmlspecialchars((string)($referral['followup_instructions'] ?? '')) ?></textarea>
              </div>

              <!-- Action buttons -->
              <?php if ($isDraft): ?>
              <div class="col-12 d-flex gap-2 flex-wrap">
                <button type="submit" name="action" value="save" class="btn btn-outline-secondary">
                    <?= xlt("Save Draft") ?>
                </button>
                <details class="d-inline">
                  <summary><span class="btn btn-primary"><?= xlt("Mark as Sent") ?></span></summary>
                  <div class="mt-2 p-3 border rounded bg-white" style="min-width:300px;">
                    <label class="form-label"><?= xlt("Send Method") ?></label>
                    <select name="send_method" class="form-select form-select-sm mb-2">
                      <option value="MANUAL"><?= xlt("Manual / Phone") ?></option>
                      <option value="FAX"><?= xlt("Fax") ?></option>
                      <option value="DIRECT"><?= xlt("Direct Secure Message") ?></option>
                      <option value="PRINT"><?= xlt("Print & Hand Deliver") ?></option>
                    </select>
                    <button type="submit" name="action" value="send" class="btn btn-primary btn-sm">
                      <?= xlt("Confirm Send") ?>
                    </button>
                  </div>
                </details>
              </div>
              <?php endif; ?>

            </div><!-- /row -->
          </form>
        </div>
      </div>

      <!-- ── Response tracking (visible once sent) ── -->
          <?php if ($referralStatus === 'SENT'): ?>
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt("Record Response") ?></div>
        <div class="card-body">
          <form method="post" class="row g-2">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)($data['csrf'] ?? '')) ?>">
            <input type="hidden" name="action"      value="respond">
            <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
            <input type="hidden" name="episode_id"  value="<?= htmlspecialchars((string)$episodeId) ?>">
            <div class="col-md-3">
              <label class="form-label"><?= xlt("Outcome") ?></label>
              <select name="response_outcome" class="form-select form-select-sm">
                <option value="ACCEPTED"><?= xlt("Accepted") ?></option>
                <option value="DECLINED"><?= xlt("Declined") ?></option>
                <option value="CANCELLED"><?= xlt("Cancelled") ?></option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label"><?= xlt("Received By") ?></label>
              <input name="response_by_name" class="form-control form-control-sm"
                     placeholder="<?= xla("Name or dept") ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label"><?= xlt("Notes") ?></label>
              <input name="response_notes" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex align-items-end">
              <button class="btn btn-primary btn-sm w-100"><?= xlt("Save Response") ?></button>
            </div>
          </form>
                <?php if (!empty($referral['response_notes'])): ?>
            <div class="mt-2 small text-muted">
                    <?= xlt("Last response notes") ?>: <?= htmlspecialchars((string)$referral['response_notes']) ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php endif; /* end $referral exists */ ?>

    </div><!-- /col-lg-9 -->
  </div><!-- /row -->
</div>
</body>
</html>
















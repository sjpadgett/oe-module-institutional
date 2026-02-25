<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Submodule\EReferral\Controller\EReferralController;
use OpenEMR\Modules\Institutional\Submodule\EReferral\Repository\EReferralRepository;
use OpenEMR\Modules\Institutional\Submodule\EReferral\Service\EReferralService;
use OpenEMR\Modules\Institutional\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarOrderRepository;

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

$controller = new EReferralController(
    new EReferralRepository(),
    new EReferralService(new EReferralRepository(), new FacilityDirectoryRepository()),
    $episodeRepo,
    new DispositionRepository(),
    new FacilityDirectoryRepository(),
    new MarOrderRepository()
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

$href      = institutional_bootstrap5_href($manifest);
$referral  = $data['referral']  ?? [];
$episode   = $data['episode']   ?? $selected;
$directory = $data['directory'] ?? [];
$isPrint   = isset($_GET['action']) && $_GET['action'] === 'print';

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
</head>
<body>
  <p class="no-print"><a href="ereferral.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">&larr; <?= xlt('Back') ?></a> &nbsp;
    <button onclick="window.print()"><?= xlt('Print') ?></button></p>

  <h1><?= xlt('Discharge / Transfer Referral') ?></h1>

  <div class="row">
    <div><span class="label"><?= xlt('Date') ?>:</span> <?= htmlspecialchars(date('F j, Y')) ?></div>
    <div><span class="label"><?= xlt('Episode') ?>:</span> #<?= htmlspecialchars((string)$episodeId) ?></div>
    <div><span class="label"><?= xlt('PID') ?>:</span> <?= htmlspecialchars((string)($episode['pid'] ?? '')) ?></div>
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
<body class="bg-light">
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
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>">
        <?= xlt("ED Board") ?>
      </a>
    </div>
  </div>

  <?php if (!empty($data['message'])): ?>
    <div class="alert alert-success py-2"><?= htmlspecialchars((string)$data['message']) ?></div>
  <?php endif; ?>
  <?php if (!empty($data['error'])): ?>
    <div class="alert alert-danger py-2"><?= htmlspecialchars((string)$data['error']) ?></div>
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
                  <div class="fw-semibold small">#<?= htmlspecialchars((string)$e['id']) ?> &middot; PID <?= htmlspecialchars((string)$e['pid']) ?></div>
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
              <div class="col-md-6">
                <label class="form-label"><?= xlt("Current Medications") ?></label>
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

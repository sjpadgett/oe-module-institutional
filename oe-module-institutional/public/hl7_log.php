<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Service\AclGuard;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Repository\Hl7OutboundLogRepository;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

AclGuard::requireAdmin();

if (!$manifest->featureEnabled('hl7_adt')) {
    die(xlt('HL7 ADT is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$logRepo    = new Hl7OutboundLogRepository();
$settings   = new SettingsRepository();

// Raw message view (AJAX / modal)
if (isset($_GET['raw']) && is_numeric($_GET['raw'])) {
    AclGuard::requireAdmin();
    $detail = $logRepo->getDetail((int)$_GET['raw']);
    header('Content-Type: text/plain; charset=utf-8');
    // Replace HL7 CR segments with visible line breaks for readability
    echo str_replace("\r", "\n", (string)($detail['message_body'] ?? ''));
    exit;
}

$rows    = $logRepo->listRecent($facilityId, 200);
$summary = $logRepo->summary24h($facilityId);
$href    = institutional_bootstrap5_href($manifest);

// Settings summary for header
$hlEnabled   = $settings->get($facilityId, 'hl7_enabled')  === '1';
$hlTransport = $settings->get($facilityId, 'hl7_transport') ?: 'MLLP';
$hlEndpoint  = $hlTransport === 'HTTP'
    ? $settings->get($facilityId, 'hl7_http_url')
    : ($settings->get($facilityId, 'hl7_mllp_host') . ':' . ($settings->get($facilityId, 'hl7_mllp_port') ?: '2575'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('HL7 Outbound Log') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .badge-sent  { background:#198754; color:#fff; }
    .badge-error { background:#dc3545; color:#fff; }
    .badge-nack  { background:#fd7e14; color:#fff; }
    pre { background:#1e1e2e; color:#cdd6f4; font-size:.78rem; border-radius:6px; padding:1rem; overflow-x:auto; white-space:pre-wrap; }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 class="h4 mb-0"><?= xlt('HL7 ADT Outbound Log') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary"
         href="settings.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Settings') ?></a>
      <a class="btn btn-sm btn-outline-secondary"
         href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
    </div>
  </div>

  <!-- Status bar -->
  <div class="row g-2 mb-3">
    <div class="col-auto">
      <div class="card px-3 py-2 text-center <?= $hlEnabled ? 'border-success' : 'border-secondary' ?>">
        <div class="fw-bold <?= $hlEnabled ? 'text-success' : 'text-secondary' ?>">
          <?= $hlEnabled ? '● ENABLED' : '○ DISABLED' ?>
        </div>
        <div class="small text-muted"><?= htmlspecialchars($hlTransport) ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="card px-3 py-2">
        <div class="small text-muted"><?= xlt('Endpoint') ?></div>
        <div class="fw-semibold small"><?= htmlspecialchars($hlEndpoint ?: '—') ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="card px-3 py-2 text-center">
        <div class="fw-bold text-success"><?= (int)$summary['sent'] ?></div>
        <div class="small text-muted"><?= xlt('Sent 24h') ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="card px-3 py-2 text-center">
        <div class="fw-bold text-danger"><?= (int)$summary['error'] ?></div>
        <div class="small text-muted"><?= xlt('Errors 24h') ?></div>
      </div>
    </div>
    <div class="col-auto">
      <div class="card px-3 py-2 text-center">
        <div class="fw-bold text-warning"><?= (int)$summary['nack'] ?></div>
        <div class="small text-muted"><?= xlt('NACKs 24h') ?></div>
      </div>
    </div>
  </div>

  <!-- Log table -->
  <div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Recent Messages') ?> (<?= count($rows) ?>)</span>
      <a class="btn btn-sm btn-outline-secondary"
         href="hl7_log.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Refresh') ?></a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Time') ?></th>
            <th><?= xlt('Event') ?></th>
            <th><?= xlt('Episode') ?></th>
            <th><?= xlt('PID') ?></th>
            <th><?= xlt('Transport') ?></th>
            <th><?= xlt('Endpoint') ?></th>
            <th><?= xlt('Status') ?></th>
            <th><?= xlt('Error') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r):
            $st = strtolower((string)($r['status'] ?? ''));
            $badgeCls = match ($st) { 'sent' => 'badge-sent', 'nack' => 'badge-nack', default => 'badge-error' };
            ?>
          <tr>
            <td class="text-nowrap small"><?= htmlspecialchars((string)$r['sent_datetime']) ?></td>
            <td><code><?= htmlspecialchars((string)$r['event_type']) ?></code></td>
            <td><?= htmlspecialchars((string)$r['episode_id']) ?></td>
            <td><?= htmlspecialchars((string)$r['pid']) ?></td>
            <td class="small"><?= htmlspecialchars((string)$r['transport_type']) ?></td>
            <td class="small text-muted text-truncate" style="max-width:180px;"><?= htmlspecialchars((string)$r['endpoint']) ?></td>
            <td><span class="badge <?= $badgeCls ?>"><?= htmlspecialchars(strtoupper($st)) ?></span></td>
            <td class="small text-danger text-truncate" style="max-width:200px;">
              <?= htmlspecialchars((string)($r['error_message'] ?? '')) ?>
            </td>
            <td>
              <button class="btn btn-sm btn-outline-secondary"
                      onclick="showRaw(<?= (int)$r['id'] ?>, <?= urlencode((string)$facilityId) ?>)"><?= xlt('Raw') ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4"><?= xlt('No messages sent yet') ?></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div><!-- /container -->

<!-- Raw message modal -->
<div class="modal fade" id="rawModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><?= xlt('Raw HL7 Message') ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <pre id="rawContent">Loading…</pre>
      </div>
    </div>
  </div>
</div>

<?php if ($href): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>
<script>
function showRaw(logId, facilityId) {
    const modal = new bootstrap.Modal(document.getElementById('rawModal'));
    document.getElementById('rawContent').textContent = 'Loading…';
    modal.show();
    fetch('hl7_log.php?facility_id=' + facilityId + '&raw=' + logId)
        .then(r => r.text())
        .then(t => { document.getElementById('rawContent').textContent = t; })
        .catch(() => { document.getElementById('rawContent').textContent = 'Error loading message.'; });
}
</script>
</body>
</html>






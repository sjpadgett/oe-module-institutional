<?php
/**
 * downtime.php
 *
 * Online mode  → Downtime configuration, snapshot refresh status, sync queue.
 * Offline mode → Full offline viewer rendered by JavaScript from the IndexedDB
 *                snapshot; also provides write forms (arrival, vitals, note)
 *                that queue entries for sync on reconnect.
 *
 * The page shell is always served from the Service Worker cache so it works
 * even when the PHP server is unreachable.  PHP is only needed for the initial
 * load and for the server-rendered queue table (?view=queue).
 */

require_once __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\Submodule\Downtime\Controller\DowntimeController;
use OpenEMR\Modules\Institutional\Submodule\Downtime\Service\DowntimeSnapshotService;
use OpenEMR\Modules\Institutional\Submodule\Downtime\Service\DowntimeSyncService;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;

if (!$manifest->featureEnabled('downtime')) {
    die(xlt('Downtime Mode is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$controller = new DowntimeController(
    new DowntimeSnapshotService(
        new \OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository(),
        new TaskRepository(),
        new LocationRepository(),
        new EpisodeLocationRepository(),
        new DiversionRepository(),
        new SettingsRepository()
    ),
    new DowntimeSyncService(
        new \OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository(),
        new TriageRepository(),
        new TaskRepository()
    )
);

$data      = $controller->handlePage($facilityId, $userId);
$href      = institutional_bootstrap5_href($manifest);
$csrf      = $data['csrf'];
$pending   = (int)$data['pending'];
$synced    = (int)$data['synced'];
$failed    = (int)$data['failed'];
$queueRows = $data['queue_rows'] ?? [];
$view      = $data['view'];

$snapshotUrl = "downtime_snapshot.php?facility_id={$facilityId}";
$syncUrl     = "downtime_sync.php?facility_id={$facilityId}";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Downtime Mode') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?>
  <link rel="stylesheet" href="<?= $href ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <?php endif; ?>
  <style>
    /* Offline banner — hidden by default, shown by JS when offline */
    #oei-offline-banner {
        display: none;
        position: sticky;
        top: 0;
        z-index: 9999;
        background: #dc3545;
        color: #fff;
        padding: 10px 16px;
        font-weight: 600;
        font-size: 14px;
        text-align: center;
        animation: oei-pulse-bg 2s ease-in-out infinite;
    }
    #oei-offline-banner.show { display: block; }
    @keyframes oei-pulse-bg {
        0%,100% { background: #dc3545; }
        50%      { background: #a71d2a; }
    }
    /* ESI colour dots */
    .oei-esi { display:inline-block;width:22px;height:22px;border-radius:50%;line-height:22px;text-align:center;font-size:12px;font-weight:700;color:#fff; }
    .oei-esi-1{background:#000;} .oei-esi-2{background:#e63946;}
    .oei-esi-3{background:#f4a261;} .oei-esi-4{background:#2a9d8f;}
    .oei-esi-5{background:#6c757d;}
    /* Offline board */
    #oei-offline-board { display: none; }
    #oei-online-content { display: block; }
    body.oei-offline #oei-offline-board   { display: block; }
    body.oei-offline #oei-online-content  { display: none; }
    .oei-elapsed-warn { color: #dc3545; font-weight: 600; }
  </style>
</head>
<body class="bg-light">

<!-- Offline banner — controlled by JS -->
<div id="oei-offline-banner">
  <i class="bi bi-wifi-off me-2"></i>
  <?= xlt('OFFLINE MODE — Displaying cached snapshot. Writes are queued for sync on reconnect.') ?>
  <span id="oei-snapshot-age" class="ms-3 opacity-75" style="font-size:12px"></span>
</div>

<div class="container-fluid py-4 px-4">

  <!-- ── ONLINE CONTENT ─────────────────────────────────────────────────── -->
  <div id="oei-online-content">

    <div class="d-flex align-items-center justify-content-between mb-4">
      <h1 class="h4 mb-0">
        <i class="bi bi-shield-exclamation me-2"></i><?= xlt('Downtime Mode') ?>
      </h1>
      <div class="d-flex gap-2">
        <a href="?facility_id=<?= $facilityId ?>&view=queue"
           class="btn btn-sm btn-outline-secondary <?= $view === 'queue' ? 'active' : '' ?>">
          <i class="bi bi-list-check me-1"></i><?= xlt('Sync Queue') ?>
          <?php if ($pending > 0): ?>
            <span class="badge text-bg-warning ms-1"><?= $pending ?></span>
          <?php endif; ?>
        </a>
        <a href="?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-speedometer2 me-1"></i><?= xlt('Status') ?>
        </a>
      </div>
    </div>

    <?php require __DIR__ . '/../src/Core/Ui/partials/flash.php'; ?>

    <?php if ($view === 'status'): ?>

    <!-- Status cards -->
    <div class="row g-3 mb-4">
      <div class="col-sm-4">
        <div class="card shadow-sm border-0">
          <div class="card-body text-center">
            <div class="h2 mb-0 text-success"><i class="bi bi-check-circle"></i></div>
            <div class="mt-1 fw-semibold"><?= xlt('Synced') ?></div>
            <div class="display-6 fw-bold text-success"><?= $synced ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card shadow-sm border-0">
          <div class="card-body text-center">
            <div class="h2 mb-0 text-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="mt-1 fw-semibold"><?= xlt('Pending') ?></div>
            <div class="display-6 fw-bold text-warning"><?= $pending ?></div>
          </div>
        </div>
      </div>
      <div class="col-sm-4">
        <div class="card shadow-sm border-0">
          <div class="card-body text-center">
            <div class="h2 mb-0 text-danger"><i class="bi bi-x-circle"></i></div>
            <div class="mt-1 fw-semibold"><?= xlt('Failed') ?></div>
            <div class="display-6 fw-bold text-danger"><?= $failed ?></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Snapshot control -->
    <div class="card shadow-sm mb-4">
      <div class="card-header fw-semibold"><?= xlt('Snapshot Cache') ?></div>
      <div class="card-body">
        <p class="mb-3 text-muted">
          <?= xlt('The Service Worker caches a snapshot of the current board every 5 minutes. The snapshot is used to populate the offline viewer when the server is unreachable.') ?>
        </p>
        <div class="d-flex gap-2 align-items-center">
          <button class="btn btn-primary btn-sm" id="oei-refresh-snapshot-btn">
            <i class="bi bi-arrow-clockwise me-1"></i><?= xlt('Refresh Snapshot Now') ?>
          </button>
          <span id="oei-snapshot-status" class="text-muted small"></span>
        </div>
        <div id="oei-snapshot-detail" class="mt-2 small text-muted"></div>
      </div>
    </div>

    <!-- Instructions -->
    <div class="card shadow-sm border-info">
      <div class="card-header bg-info bg-opacity-10 text-info fw-semibold">
        <i class="bi bi-info-circle me-1"></i><?= xlt('How Downtime Mode Works') ?>
      </div>
      <div class="card-body small">
        <p><?= xlt('When network connectivity is lost, this page automatically switches to Offline Mode, displaying the most recently cached snapshot of the ED board.') ?></p>
        <ul class="mb-2">
          <li><?= xlt('New arrivals, vitals, status notes, and task notes can be entered offline — they are queued in browser storage.') ?></li>
          <li><?= xlt('When connectivity is restored, queued entries are automatically submitted and the board refreshes.') ?></li>
          <li><?= xlt('The Service Worker keeps the snapshot current by refreshing it every 5 minutes while online.') ?></li>
          <li><?= xlt('For the offline viewer to work, this page must be loaded at least once while online.') ?></li>
        </ul>
      </div>
    </div>

    <?php elseif ($view === 'queue'): ?>

    <!-- Queue table -->
    <div class="card shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong><?= xlt('Sync Queue') ?></strong>
        <?php if ($pending > 0): ?>
          <form method="post" action="downtime_sync.php?facility_id=<?= $facilityId ?>" class="d-inline">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
            <button class="btn btn-sm btn-warning">
              <i class="bi bi-arrow-repeat me-1"></i><?= xlt('Process Pending Now') ?>
            </button>
          </form>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Queued') ?></th>
                <th><?= xlt('Captured') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Status') ?></th>
                <th><?= xlt('Result') ?></th>
                <th><?= xlt('By') ?></th>
              </tr>
            </thead>
            <tbody>
            <?php if (empty($queueRows)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3"><?= xlt('No queue entries') ?></td></tr>
            <?php else: ?>
              <?php foreach ($queueRows as $qr):
                $qStatus = (string)($qr['status'] ?? 'PENDING');
                $badgeClass = match($qStatus) {
                    'SYNCED'  => 'success',
                    'FAILED'  => 'danger',
                    'SKIPPED' => 'secondary',
                    default   => 'warning',
                };
              ?>
              <tr>
                <td class="small text-nowrap"><?= htmlspecialchars((string)($qr['queued_datetime'] ?? '')) ?></td>
                <td class="small text-nowrap"><?= htmlspecialchars((string)($qr['captured_client']  ?? '')) ?></td>
                <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)($qr['entry_type'] ?? '')) ?></span></td>
                <td><span class="badge text-bg-<?= $badgeClass ?>"><?= htmlspecialchars($qStatus) ?></span></td>
                <td class="small text-muted"><?= htmlspecialchars((string)($qr['result_note'] ?? '')) ?></td>
                <td class="small"><?= htmlspecialchars(trim((string)($qr['fname'] ?? '') . ' ' . (string)($qr['lname'] ?? ''))) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /oei-online-content -->

  <!-- ── OFFLINE BOARD ────────────────────────────────────────────────────── -->
  <div id="oei-offline-board">

    <!-- Board header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h1 class="h4 mb-0">
          <i class="bi bi-clipboard2-pulse me-2"></i><?= xlt('ED Board') ?>
          <span class="badge text-bg-danger ms-2"><?= xlt('OFFLINE') ?></span>
        </h1>
        <div class="text-muted small" id="oei-board-timestamp"><?= xlt('Loading cached snapshot…') ?></div>
      </div>
      <button class="btn btn-sm btn-outline-primary" id="oei-add-arrival-btn">
        <i class="bi bi-person-plus me-1"></i><?= xlt('New Arrival') ?>
      </button>
    </div>

    <!-- Episode table populated by JS -->
    <div class="card shadow-sm mb-4">
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-hover mb-0" id="oei-offline-ep-table">
            <thead class="table-light">
              <tr>
                <th><?= xlt('ESI') ?></th>
                <th><?= xlt('Patient') ?></th>
                <th><?= xlt('Chief Complaint') ?></th>
                <th><?= xlt('Location') ?></th>
                <th><?= xlt('Elapsed') ?></th>
                <th><?= xlt('Status') ?></th>
                <th><?= xlt('Actions') ?></th>
              </tr>
            </thead>
            <tbody id="oei-offline-ep-body">
              <tr><td colspan="7" class="text-center text-muted py-3"><?= xlt('Loading…') ?></td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pending queue badge -->
    <div class="alert alert-info d-flex justify-content-between align-items-center py-2" id="oei-queue-badge" style="display:none!important">
      <span><i class="bi bi-clock-history me-1"></i><strong id="oei-queue-count">0</strong> <?= xlt('entries pending sync') ?></span>
      <span class="small text-muted"><?= xlt('Will sync automatically when connection is restored') ?></span>
    </div>

    <!-- Modals rendered by JS -->
    <div id="oei-modal-container"></div>

  </div><!-- /oei-offline-board -->

</div><!-- /container-fluid -->

<?php if ($href): ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php endif; ?>

<script>
/* jshint esversion:11 */
'use strict';

// ── Configuration injected from PHP ──────────────────────────────────────────
const OEI = {
    facilityId:    <?= $facilityId ?>,
    snapshotUrl:   '<?= $snapshotUrl ?>',
    syncUrl:       '<?= $syncUrl ?>',
    csrfToken:     <?= json_encode($csrf) ?>,
    swUrl:         'sw.js',
    snapshotTtl:   5 * 60 * 1000,   // 5 min
};

// ── Service Worker registration ───────────────────────────────────────────────
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(OEI.swUrl, { scope: './' })
        .then((reg) => {
            console.log('[OEI] SW registered, scope:', reg.scope);
            // Store CSRF token in IDB so SW background sync can use it
            storeMetaInIdb('csrf_token', OEI.csrfToken);
        })
        .catch((err) => console.warn('[OEI] SW registration failed:', err));

    // Listen for online/offline messages from the SW
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'oei:offline') setOfflineMode(true);
        if (event.data?.type === 'oei:online')  setOfflineMode(false);
        if (event.data?.type === 'oei:sync-complete') {
            updateQueueBadge();
            loadSnapshotBoard();
        }
    });
}

// Also listen to native network events
window.addEventListener('offline', () => setOfflineMode(true));
window.addEventListener('online',  () => {
    setOfflineMode(false);
    triggerBackgroundSync();
});

// ── Offline/online mode toggle ────────────────────────────────────────────────
function setOfflineMode(offline) {
    document.body.classList.toggle('oei-offline', offline);
    const banner = document.getElementById('oei-offline-banner');
    if (banner) banner.classList.toggle('show', offline);
    if (offline) loadSnapshotBoard();
}

// Initial state
if (!navigator.onLine) setOfflineMode(true);

// ── Snapshot board rendering ──────────────────────────────────────────────────
let _snapshot = null;

async function loadSnapshotBoard() {
    try {
        // Try live fetch first (may already be offline, SW serves cache)
        const resp = await fetch(OEI.snapshotUrl, { cache: 'no-store' });
        _snapshot = await resp.json();
    } catch (_) {
        // Try IDB cache
        _snapshot = await idbGetMeta('last_snapshot');
    }
    if (_snapshot) renderBoard(_snapshot);
}

function renderBoard(snap) {
    if (snap.error) return;

    // Update timestamp
    const tsEl = document.getElementById('oei-board-timestamp');
    if (tsEl && snap.generated) {
        const d = new Date(snap.generated);
        tsEl.textContent = '<?= xlt('Snapshot taken') ?>: ' + d.toLocaleTimeString();
    }

    // Persist to IDB
    storeMetaInIdb('last_snapshot', snap);

    const settings = snap.settings ?? {};
    const targetMin = settings.door_to_provider_target_min ?? 60;
    const lwbsMin   = settings.lwbs_threshold_min          ?? 120;

    const tbody = document.getElementById('oei-offline-ep-body');
    if (!tbody) return;

    const episodes = snap.episodes ?? [];
    if (episodes.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-3"><?= xlt('No active episodes') ?></td></tr>`;
        return;
    }

    const esiColors = {1:'oei-esi-1',2:'oei-esi-2',3:'oei-esi-3',4:'oei-esi-4',5:'oei-esi-5'};

    tbody.innerHTML = episodes.map((ep) => {
        const esi       = ep.acuity_esi ? `<span class="oei-esi ${esiColors[ep.acuity_esi]??'oei-esi-5'}">${ep.acuity_esi}</span>` : '—';
        const name      = escHtml(ep._patient_name   || `PID ${ep.pid}`);
        const cc        = escHtml(ep.chief_complaint ?? '');
        const loc       = escHtml(ep._location_name  || ep._location_code || '—');
        const elapsed   = ep._elapsed_minutes ?? 0;
        const elClass   = elapsed >= lwbsMin ? 'oei-elapsed-warn' : '';
        const elText    = elapsed >= 60
            ? `${Math.floor(elapsed/60)}h ${elapsed%60}m`
            : `${elapsed}m`;
        const status    = escHtml(ep.status ?? '');
        const epId      = parseInt(ep.id);
        const pid       = parseInt(ep.pid);

        return `<tr>
          <td>${esi}</td>
          <td class="fw-semibold">${name}</td>
          <td class="text-muted">${cc}</td>
          <td>${loc}</td>
          <td class="${elClass}">${elText}</td>
          <td><span class="badge text-bg-secondary">${status}</span></td>
          <td>
            <button class="btn btn-xs btn-outline-primary"
                    style="font-size:11px;padding:2px 7px"
                    onclick="openVitalsModal(${epId},${pid},'${escHtml(ep._patient_name??'')}')">
              <?= xlt('Vitals') ?>
            </button>
            <button class="btn btn-xs btn-outline-secondary ms-1"
                    style="font-size:11px;padding:2px 7px"
                    onclick="openNoteModal(${epId},'${escHtml(ep._patient_name??'')}')">
              <?= xlt('Note') ?>
            </button>
          </td>
        </tr>`;
    }).join('');
}

// ── Modal helpers ─────────────────────────────────────────────────────────────

function openVitalsModal(epId, pid, patientName) {
    showModal(`<?= xlt('Record Vitals') ?> — ${patientName}`, `
      <div class="row g-2">
        <div class="col-6"><label class="form-label small"><?= xlt('BP Systolic') ?></label><input id="m-bpsys" type="number" class="form-control form-control-sm" min="40" max="300"></div>
        <div class="col-6"><label class="form-label small"><?= xlt('BP Diastolic') ?></label><input id="m-bpdia" type="number" class="form-control form-control-sm" min="20" max="200"></div>
        <div class="col-4"><label class="form-label small"><?= xlt('HR') ?></label><input id="m-hr" type="number" class="form-control form-control-sm" min="20" max="300"></div>
        <div class="col-4"><label class="form-label small"><?= xlt('RR') ?></label><input id="m-rr" type="number" class="form-control form-control-sm" min="4" max="60"></div>
        <div class="col-4"><label class="form-label small"><?= xlt('SpO2 %') ?></label><input id="m-spo2" type="number" class="form-control form-control-sm" min="50" max="100"></div>
        <div class="col-6"><label class="form-label small"><?= xlt('Temp °F') ?></label><input id="m-temp" type="number" step="0.1" class="form-control form-control-sm" min="85" max="110"></div>
        <div class="col-6"><label class="form-label small"><?= xlt('GCS') ?></label><input id="m-gcs" type="number" class="form-control form-control-sm" min="3" max="15"></div>
        <div class="col-12"><label class="form-label small"><?= xlt('Pain 0-10') ?></label><input id="m-pain" type="number" class="form-control form-control-sm" min="0" max="10"></div>
        <div class="col-12"><label class="form-label small"><?= xlt('Notes') ?></label><textarea id="m-notes" class="form-control form-control-sm" rows="2"></textarea></div>
      </div>`,
        () => {
            queueEntry('VITALS', {
                episode_id:   epId,
                pid:          pid,
                facility_id:  OEI.facilityId,
                bp_systolic:  val('m-bpsys'),
                bp_diastolic: val('m-bpdia'),
                hr:           val('m-hr'),
                rr:           val('m-rr'),
                spo2:         val('m-spo2'),
                temp_f:       val('m-temp'),
                gcs:          val('m-gcs'),
                pain_score:   val('m-pain'),
                notes:        strVal('m-notes'),
            });
        }
    );
}

function openNoteModal(epId, patientName) {
    showModal(`<?= xlt('Status Note') ?> — ${patientName}`, `
      <div class="mb-2">
        <label class="form-label small"><?= xlt('Status') ?></label>
        <select id="m-status" class="form-select form-select-sm">
          <option value="WAITING"><?= xlt('Waiting') ?></option>
          <option value="WITH_PROVIDER"><?= xlt('With Provider') ?></option>
          <option value="PENDING_RESULTS"><?= xlt('Pending Results') ?></option>
          <option value="READY_DISCHARGE"><?= xlt('Ready Discharge') ?></option>
        </select>
      </div>
      <div>
        <label class="form-label small"><?= xlt('Note') ?></label>
        <textarea id="m-note-text" class="form-control form-control-sm" rows="3"></textarea>
      </div>`,
        () => queueEntry('STATUS_NOTE', {
            episode_id:  epId,
            facility_id: OEI.facilityId,
            status:      strVal('m-status'),
            note:        strVal('m-note-text'),
        })
    );
}

window.openArrivalForm = function () {
    showModal(`<?= xlt('New Arrival') ?>`, `
      <div class="alert alert-warning small py-2">
        <?= xlt("Offline mode: enter the patient\'s OpenEMR PID (required for sync). Name will be matched on reconnect.") ?>
      </div>
      <div class="row g-2">
        <div class="col-6"><label class="form-label small"><?= xlt('Patient PID') ?> *</label><input id="m-pid" type="number" class="form-control form-control-sm"></div>
        <div class="col-6"><label class="form-label small"><?= xlt('ESI Acuity') ?></label>
          <select id="m-esi" class="form-select form-select-sm">
            <option value=""><?= xlt('Unknown') ?></option>
            <option value="1">1</option><option value="2">2</option>
            <option value="3">3</option><option value="4">4</option><option value="5">5</option>
          </select>
        </div>
        <div class="col-12"><label class="form-label small"><?= xlt('Chief Complaint') ?></label><input id="m-cc" type="text" class="form-control form-control-sm"></div>
      </div>`,
        () => {
            const pid = parseInt(document.getElementById('m-pid')?.value ?? '0');
            if (!pid) { alert('<?= xlt('PID is required') ?>'); return false; }
            queueEntry('ARRIVAL', {
                pid:             pid,
                facility_id:     OEI.facilityId,
                acuity_esi:      val('m-esi'),
                chief_complaint: strVal('m-cc'),
            });
        }
    );
};

document.getElementById('oei-add-arrival-btn')?.addEventListener('click', openArrivalForm);

// ── Generic modal ─────────────────────────────────────────────────────────────
function showModal(title, bodyHtml, onSave) {
    const id = 'oei-dyn-modal';
    document.getElementById(id)?.remove();
    const el = document.createElement('div');
    el.id = id;
    el.className = 'modal fade';
    el.innerHTML = `
      <div class="modal-dialog modal-lg">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">${escHtml(title)}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">${bodyHtml}</div>
          <div class="modal-footer">
            <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= xlt('Cancel') ?></button>
            <button class="btn btn-primary btn-sm" id="oei-modal-save"><?= xlt('Queue for Sync') ?></button>
          </div>
        </div>
      </div>`;
            document.getElementById('oei-modal-container').appendChild(el);
            const modal = new bootstrap.Modal(el);
            document.getElementById('oei-modal-save').addEventListener('click', () => {
                const result = onSave();
                if (result !== false) modal.hide();
            });
            modal.show();
        }

        // ── IndexedDB queue ───────────────────────────────────────────────────────────
        let _idb = null;

        async function getIdb() {
            if (_idb) return _idb;
            return new Promise((res, rej) => {
                const req = indexedDB.open('oei-downtime', 1);
                req.onupgradeneeded = (e) => {
                    const db = e.target.result;
                    if (!db.objectStoreNames.contains('pendingQueue')) {
                        db.createObjectStore('pendingQueue', { keyPath: 'idb_id', autoIncrement: true });
                    }
                    if (!db.objectStoreNames.contains('meta')) {
                        db.createObjectStore('meta');
                    }
                };
                req.onsuccess  = (e) => { _idb = e.target.result; res(_idb); };
                req.onerror    = (e) => rej(e.target.error);
            });
        }

        async function queueEntry(entryType, payload) {
            const db  = await getIdb();
            const now = new Date().toISOString();
            const tx  = db.transaction('pendingQueue', 'readwrite');
            tx.objectStore('pendingQueue').add({
                entry_type:      entryType,
                facility_id:     OEI.facilityId,
                payload:         payload,
                captured_client: now,
            });
            await txDone(tx);
            updateQueueBadge();
            // Register background sync if available
            triggerBackgroundSync();
            showToast(`<?= xlt('Queued for sync') ?>: ${entryType}`);
        }

        async function updateQueueBadge() {
            const db    = await getIdb();
            const tx    = db.transaction('pendingQueue', 'readonly');
            const count = await idbCount(tx, 'pendingQueue');
            const badge = document.getElementById('oei-queue-badge');
            const cnt   = document.getElementById('oei-queue-count');
            if (badge) badge.style.display = count > 0 ? '' : 'none';
            if (cnt)   cnt.textContent     = count;
        }

        async function storeMetaInIdb(key, value) {
            const db = await getIdb();
            const tx = db.transaction('meta', 'readwrite');
            tx.objectStore('meta').put(value, key);
            await txDone(tx);
        }

        async function idbGetMeta(key) {
            const db = await getIdb();
            return new Promise((res) => {
                const tx  = db.transaction('meta', 'readonly');
                const req = tx.objectStore('meta').get(key);
                req.onsuccess = () => res(req.result ?? null);
                req.onerror   = () => res(null);
            });
        }

        function idbCount(tx, storeName) {
            return new Promise((res) => {
                const req = tx.objectStore(storeName).count();
                req.onsuccess = () => res(req.result ?? 0);
                req.onerror   = () => res(0);
            });
        }

        function txDone(tx) {
            return new Promise((res, rej) => {
                tx.oncomplete = () => res();
                tx.onerror    = () => rej(tx.error);
            });
        }

        // ── Background sync trigger ───────────────────────────────────────────────────
        async function triggerBackgroundSync() {
            if (!('serviceWorker' in navigator)) return;
            const reg = await navigator.serviceWorker.ready;
            if ('sync' in reg) {
                reg.sync.register('oei-sync-queue').catch(() => {});
            }
        }

        // ── Snapshot refresh button ───────────────────────────────────────────────────
        document.getElementById('oei-refresh-snapshot-btn')?.addEventListener('click', async () => {
            const btn = document.getElementById('oei-refresh-snapshot-btn');
            const status = document.getElementById('oei-snapshot-status');
            if (btn)    btn.disabled = true;
            if (status) status.textContent = '<?= xlt('Refreshing…') ?>';
            try {
                const resp = await fetch(OEI.snapshotUrl, { cache: 'no-store' });
                const data = await resp.json();
                if (status) {
                    const detail = document.getElementById('oei-snapshot-detail');
                    status.textContent = `<?= xlt('Done') ?> — ${data.episodes?.length ?? 0} <?= xlt('episodes') ?>`;
                    if (detail) {
                        detail.textContent = `<?= xlt('Generated') ?>: ${data.generated} | ${Object.keys(data.loc_map??{}).length} <?= xlt('locations assigned') ?>`;
                    }
                }
            } catch (e) {
                if (status) status.textContent = '<?= xlt('Failed — server unreachable') ?>';
            } finally {
                if (btn) btn.disabled = false;
            }
        });

        // ── Toast notification ────────────────────────────────────────────────────────
        function showToast(msg) {
            let container = document.getElementById('oei-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'oei-toast-container';
                container.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:11000;display:flex;flex-direction:column;gap:8px';
                document.body.appendChild(container);
            }
            const t = document.createElement('div');
            t.className = 'toast align-items-center text-bg-success border-0 show';
            t.setAttribute('role', 'alert');
            t.innerHTML = `<div class="d-flex"><div class="toast-body">${escHtml(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            container.appendChild(t);
            setTimeout(() => t.remove(), 3500);
        }

        // ── Utilities ─────────────────────────────────────────────────────────────────
        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function val(id) {
            const v = document.getElementById(id)?.value;
            return (v !== undefined && v !== '') ? Number(v) : null;
        }

        function strVal(id) {
            return document.getElementById(id)?.value?.trim() ?? '';
        }

        // ── Init ──────────────────────────────────────────────────────────────────────
        updateQueueBadge();
        // Pre-load snapshot in background so offline board is always fresh
        loadSnapshotBoard();
    </script>
</body>
</html>

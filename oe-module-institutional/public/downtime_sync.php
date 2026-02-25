<?php
/**
 * downtime_sync.php
 *
 * Receives a batch of offline entries from the browser's IndexedDB pending
 * queue, inserts them into oei_downtime_sync_queue, and immediately
 * processes them against the live database.
 *
 * POST  JSON body:
 * {
 *   "facility_id": 1,
 *   "entries": [
 *     {
 *       "entry_type": "ARRIVAL|VITALS|STATUS_NOTE|TASK_NOTE",
 *       "payload": { ... },
 *       "captured_client": "2026-03-01T14:22:00Z"
 *     }
 *   ]
 * }
 *
 * Response:
 * { "ok": true, "inserted": N, "processed": N, "synced": N, "failed": N }
 */

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Common\Csrf\CsrfUtils;
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
    http_response_code(403);
    echo json_encode(['error' => 'Downtime feature disabled']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'POST required']);
    exit;
}

// CSRF check — token sent as X-CSRF-Token header by the browser client
$token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!CsrfUtils::verifyCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
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

$result = $controller->handleSync($facilityId, $userId);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result);

<?php

/**
 * public/downtime_snapshot.php
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
 * downtime_snapshot.php
 *
 * Serves a JSON snapshot of the current board for offline caching.
 * Called periodically by the Service Worker and directly by the offline viewer.
 *
 * Response headers:
 *   Content-Type: application/json; charset=utf-8
 *   X-OEI-Snapshot-Generated: <UTC ISO-8601>
 *
 * Query params:
 *   facility_id  int  (defaults to facility_default_id from globals)
 */

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Downtime\Controller\DowntimeController;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Downtime\Service\DowntimeSnapshotService;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Downtime\Service\DowntimeSyncService;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository;

if (!$manifest->featureEnabled('downtime')) {
    http_response_code(403);
    echo json_encode(['error' => 'Downtime feature disabled']);
    exit;
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));

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

$controller->serveSnapshot($facilityId);




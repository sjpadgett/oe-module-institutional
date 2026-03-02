<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Repository\BhSafetyRepository;
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhSafety\Service\BhSafetyService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ed_board.php");
    exit;
}

if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    die("CSRF validation failed");
}

if (!$manifest->featureEnabled('bh_safety')) {
    die(xlt("Institutional BH Safety is disabled by manifest"));
}

$facilityId = (int)($_POST['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

$episodeId = (int)($_POST['episode_id'] ?? 0);
$pid = (int)($_POST['pid'] ?? 0);
$eidRaw = trim((string)($_POST['eid'] ?? ''));
$eid = ctype_digit($eidRaw) ? (int)$eidRaw : null;

$level = strtoupper(trim((string)($_POST['observation_level'] ?? 'NONE')));

$bhRepo = new BhSafetyRepository();
$taskRepo = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;
$bhService = new BhSafetyService($bhRepo, $taskRepo);

// quick-set: just level, no flags (future UI can add those)
$bhService->setBhSafety($episodeId, $pid, $eid, $facilityId, $level, 0, 0, 0, 0, [], $userId);

header("Location: ed_board.php?facility_id=" . urlencode((string)$facilityId));
exit;

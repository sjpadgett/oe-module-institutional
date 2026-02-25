<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Service\ObsProtocolEngine;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
    die("CSRF validation failed");
}

if (!$manifest->featureEnabled('obs_protocols')) {
    die(xlt("Obs protocols are disabled"));
}

$facilityId = (int)($_POST['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId = (int)($_POST['episode_id'] ?? 0);
$pid = (int)($_POST['pid'] ?? 0);
$eidRaw = trim((string)($_POST['eid'] ?? ''));
$eid = ctype_digit($eidRaw) ? (int)$eidRaw : null;
$protocolKey = strtoupper(trim((string)($_POST['protocol_key'] ?? 'GENERAL_OBS')));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

if ($episodeId <= 0 || $pid <= 0) {
    die(xlt("Missing episode or patient"));
}

$protoRepo = new ProtocolRepository();
$planRepo = new ObsPlanRepository();
$taskRepo = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;

$protoRepo->ensureDefaultProtocols($facilityId, $userId);

$engine = new ObsProtocolEngine($protoRepo, $planRepo, $taskRepo);
$engine->apply($episodeId, $pid, $eid, $facilityId, $protocolKey, $userId);

$redirect = (string)($_POST['redirect'] ?? 'ed_board.php');
if ($redirect === 'obs_episode') {
    header("Location: obs_episode.php?facility_id=" . urlencode((string)$facilityId) . "&episode_id=" . urlencode((string)$episodeId));
} else {
    header("Location: ed_board.php?facility_id=" . urlencode((string)$facilityId));
}
exit;

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
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

if ($episodeId <= 0) {
    die(xlt("Missing episode"));
}

$planRepo = new ObsPlanRepository();
$plan = $planRepo->getByEpisode($episodeId);
if (!$plan) {
    die(xlt("No obs plan found for this episode"));
}

$pid = (int)($plan['pid'] ?? 0);
$eidVal = $plan['eid'] ?? null;
$eid = is_numeric($eidVal) ? (int)$eidVal : null;
$protocolKey = (string)($plan['protocol_key'] ?? 'GENERAL_OBS');

$extendHours = (int)($_POST['extend_hours'] ?? (int)($plan['runway_hours'] ?? 6));
$extendHours = max(1, min(24, $extendHours));

// Load protocol definition and temporarily override runway_hours for generation window
$protoRepo = new ProtocolRepository();
$protoRepo->ensureDefaultProtocols($facilityId, $userId);
$row = $protoRepo->get($facilityId, $protocolKey);
$definition = json_decode((string)($row['definition_json'] ?? ''), true);
if (!is_array($definition)) {
    $definition = [];
}
$taskRepo = $manifest->featureEnabled('tasks') ? new TaskRepository() : null;
$engine = new ObsProtocolEngine($protoRepo, $planRepo, $taskRepo);

// Generate runway tasks from NOW -> NOW+extendHours using the current protocol definition
$generated = 0;
if (is_array($definition)) {
    $generated = $engine->generateOnlyRunway($episodeId, $pid, $eid, $facilityId, $definition, $extendHours, $userId);
}

// Update runway_hours on plan (without altering start_datetime)
$protocolJson = (string)($plan['protocol_json'] ?? '');
$targetHours = (int)($plan['target_hours'] ?? 24);
$planRepo->upsert($episodeId, $pid, $eid, $facilityId, (string)$protocolKey, $targetHours, $extendHours, $protocolJson, $userId);

header("Location: obs_episode.php?facility_id=" . urlencode((string)$facilityId) . "&episode_id=" . urlencode((string)$episodeId) . "&msg=" . urlencode(xlt("Extended runway (generated " . (string)$generated . " tasks)") . ": " . (string)$extendHours . "h"));
exit;

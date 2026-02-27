<?php

/**
 * context_switch.php
 *
 * Lightweight handler for the quick-switch dropdown in the context bar.
 * Accepts: ?context=ED_ACUTE&facility_id=1&return=/path/to/previous/page
 *
 * Validates, writes context, redirects back.
 * No HTML output — pure redirect.
 */
require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Core\Domain\CareContext;
use OpenEMR\Modules\Institutional\Core\Repository\ContextRepository;
use OpenEMR\Modules\Institutional\Core\Service\ContextService;

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
if ($userId === 0) {
    header('Location: /openemr/interface/login/login.php');
    exit;
}

$requestedKey = trim((string)($_GET['context'] ?? ''));
$facilityId   = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$return       = trim((string)($_GET['return'] ?? ''));

// Validate context key
if (!CareContext::isValid($requestedKey)) {
    $requestedKey = CareContext::DEFAULT_CONTEXT;
}

// Sanitise return URL — must be same-origin path
$return = filter_var($return, FILTER_SANITIZE_URL);
if (
    $return === false
    || $return === ''
    || !preg_match('#^/[^/]#', $return)
    || str_contains($return, '//')
) {
    $return = '/interface/modules/custom_modules/oe-module-institutional/public/ed_board.php'
        . '?facility_id=' . $facilityId;
}

// Switch
$svc = new ContextService(new ContextRepository());
$svc->switch($userId, $facilityId, $requestedKey);

header('Location: ' . $return);
exit;



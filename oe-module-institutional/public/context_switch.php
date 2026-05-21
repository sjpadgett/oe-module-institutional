<?php

/**
 * public/context_switch.php
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
use OpenEMR\Modules\Institutional\Core\Service\FacilityProfileService;

$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
if ($userId === 0) {
    header('Location: /openemr/interface/login/login.php');
    exit;
}

$facilityProfiles = new FacilityProfileService();
$requestedKey = trim((string)($_GET['context'] ?? ''));
$facilityId = $facilityProfiles->resolveFacilityId(isset($_GET['facility_id']) ? (int)$_GET['facility_id'] : 0, $userId);
$return = trim((string)($_GET['return'] ?? ''));

// Validate context key
if (!CareContext::isValid($requestedKey) || !$facilityProfiles->isContextEnabled($facilityId, $requestedKey)) {
    $requestedKey = $facilityProfiles->getDefaultContext($facilityId);
}

// Sanitise return URL — must be same-origin path
$return = filter_var($return, FILTER_SANITIZE_URL);
if (
    $return === false
    || $return === ''
    || !preg_match('#^/[^/]#', $return)
    || str_contains($return, '//')
) {
    $return = '/interface/modules/custom_modules/oe-module-institutional/public/'
        . $facilityProfiles->getHomePage($facilityId)
        . '?facility_id=' . $facilityId;
}

// Switch
$svc = new ContextService(new ContextRepository());
$svc->switch($userId, $facilityId, $requestedKey);
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

header('Location: ' . $return);
exit;







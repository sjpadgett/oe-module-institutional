<?php

/**
 * public/api/observations_ingest.php
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
 * public/api/observations_ingest.php
 *
 * REST endpoint for ingesting clinical observations from external sources:
 *   - RPM wearable device bridges
 *   - FHIR R4 Observation bundles
 *   - Simplified batch JSON (module-native format)
 *
 * Authentication (one of):
 *   1. Active OpenEMR session (authUserID in $_SESSION)
 *   2. X-OEI-API-Key header matching oei_settings key 'api_ingest_key'
 *      for the resolved facility
 *
 * Method: POST only
 * Content-Type: application/fhir+json | application/json
 *
 * Response: JSON { ok, processed, failed, errors[] }
 *
 * Rate: no built-in rate limiting — deploy behind nginx/haproxy
 * if exposing to the internet.
 *
 * CSRF: exempt — this is a machine-to-machine API endpoint.
 * API key or session auth is the authentication boundary.
 */

// ── Bootstrap — minimal, JSON-safe ──────────────────────────────────────────
// Must clear ob_start() buffer from _bootstrap.php before emitting JSON.
ob_start();

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Controller\ObservationIngestController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

// JSON-only response helper
function obs_json(array $payload, int $code = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Method guard ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    obs_json(['ok' => false, 'error' => 'POST required'], 405);
}

// ── Feature guard ─────────────────────────────────────────────────────────────
if (!$manifest->featureEnabled('observations')) {
    obs_json(['ok' => false, 'error' => 'Observations feature is not enabled'], 403);
}

// ── Facility resolution ───────────────────────────────────────────────────────
$facilityId = $_oei_facilityId ?? 0;
if ($facilityId <= 0) {
    obs_json(['ok' => false, 'error' => 'Could not resolve facility'], 400);
}

// ── Authentication ────────────────────────────────────────────────────────────
$userId    = 0;
$authed    = false;

// 1. Active OpenEMR session
if (!empty($_SESSION['authUserID'])) {
    $userId = (int)$_SESSION['authUserID'];
    $authed = true;
}

// 2. API key — X-OEI-API-Key header
if (!$authed) {
    $headerKey  = $_SERVER['HTTP_X_OEI_API_KEY'] ?? '';
    if ($headerKey !== '') {
        $settingsRepo  = new SettingsRepository();
        $storedKey     = trim($settingsRepo->get($facilityId, 'api_ingest_key'));
        if ($storedKey !== '' && hash_equals($storedKey, $headerKey)) {
            $authed = true;
            // userId stays 0 — API key auth is system-level, not user-level
        }
    }
}

if (!$authed) {
    obs_json(['ok' => false, 'error' => 'Unauthorised — provide session or X-OEI-API-Key'], 401);
}

// ── Read body + content-type ──────────────────────────────────────────────────
$body        = (string)file_get_contents('php://input');
$contentType = strtolower(trim(
    explode(';', $_SERVER['CONTENT_TYPE'] ?? 'application/json')[0]
));

// ── Delegate to controller ────────────────────────────────────────────────────
$controller = new ObservationIngestController(new SharedObservationRepository());
$result     = $controller->handle($body, $contentType, $facilityId, $userId);

obs_json($result, $result['ok'] ? 200 : 422);







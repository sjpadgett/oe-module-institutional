<?php

/**
 * src/Submodule/Alerts/Controller/AlertsController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Alerts\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Alerts\Repository\AlertAckRepository;
use OpenEMR\Modules\Institutional\Submodule\Alerts\Service\AlertService;

final class AlertsController
{
    public function __construct(
        private readonly AlertService        $service,
        private readonly EpisodeRepository   $episodes,
        private readonly AlertAckRepository  $acks,
        // TriageRepository injected as mixed so it compiles without triage submodule
        private readonly mixed               $triageRepo = null
    ) {}

    /**
     * Full-page handle: processes ACK POSTs, then returns view data.
     * @return array<string,mixed>
     */
    public function handle(int $facilityId, ?int $userId): array
    {
        // Prune expired acks ~1/10 requests to avoid a cron dependency
        if (random_int(1, 10) === 1) {
            $this->acks->pruneExpired();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action   = (string)($_POST['action'] ?? '');
            $alertKey = trim((string)($_POST['alert_key'] ?? ''));
            $snooze   = max(5, min(240, (int)($_POST['snooze_min'] ?? 30)));

            if ($action === 'ack' && $alertKey !== '' && $userId !== null) {
                $this->acks->ack($alertKey, $facilityId, $userId, $snooze);
            }

            // PRG — redirect back to dashboard
            $qs = http_build_query(['facility_id' => $facilityId]);
            header("Location: alerts.php?{$qs}");
            exit;
        }

        return $this->buildViewData($facilityId, $userId);
    }

    /**
     * JSON-only handle for the auto-refresh polling endpoint.
     * Outputs JSON and exits.
     */
    public function handleJson(int $facilityId, ?int $userId): never
    {
        header('Content-Type: application/json');
        $data = $this->buildViewData($facilityId, $userId);
        echo json_encode([
            'alerts'  => $data['alerts'],
            'summary' => $data['summary'],
            'ts'      => time(),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function buildViewData(int $facilityId, ?int $userId): array
    {
        $boardRows    = $this->episodes->fetchBoard($facilityId);
        $latestVitals = [];

        if ($this->triageRepo !== null && method_exists($this->triageRepo, 'latestByFacility')) {
            $latestVitals = $this->triageRepo->latestByFacility($facilityId);
        }

        $rawAlerts = $this->service->computeAll($boardRows, $latestVitals, $facilityId);

        // Apply snooze filter
        $snoozed = $this->acks->activeSnoozed($facilityId);
        $alerts  = array_values(array_filter($rawAlerts, function (array $a) use ($snoozed): bool {
            $key = AlertAckRepository::key((string)$a['type'], (int)$a['episode_id']);
            return !isset($snoozed[$key]);
        }));

        $summary = AlertService::summarize($alerts);

        return [
            'alerts'     => $alerts,
            'summary'    => $summary,
            'boardRows'  => $boardRows,
            'csrf'       => CsrfUtils::collectCsrfToken(),
            'facilityId' => $facilityId,
        ];
    }
}






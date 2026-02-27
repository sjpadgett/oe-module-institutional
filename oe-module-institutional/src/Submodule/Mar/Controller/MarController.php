<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Mar\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Service\AllergyService;
use OpenEMR\Modules\Institutional\Submodule\Mar\Service\MarService;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;

/**
 * MAR Controller.
 *
 * Handles three distinct views/actions:
 *   GET  mar.php?episode_id=N        — MAR grid for one episode
 *   GET  mar.php?facility_id=N       — Facility-wide pending / overdue list
 *   POST mar.php                     — Record administration / place order / discontinue
 *
 * Allergy checking (AllergyService) is injected optionally.
 * When present it computes warnings on the episode GET view so the nurse
 * sees allergy matches before recording any dose.
 */
final class MarController
{
    private MarService $service;

    public function __construct(
        private readonly MarOrderRepository          $orderRepo,
        private readonly MarAdministrationRepository $adminRepo,
        private readonly EpisodeRepository           $episodeRepo,
        private readonly ?AllergyService             $allergyService = null
    ) {
        $this->service = new MarService($orderRepo, $adminRepo);
    }

    /**
     * Main entry point.  Returns a view-model array for the public page.
     *
     * @return array<string,mixed>
     */
    public function handle(int $facilityId, ?int $userId): array
    {
        $episodeId = isset($_GET['episode_id']) && is_numeric($_GET['episode_id'])
            ? (int)$_GET['episode_id']
            : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($facilityId, $episodeId, $userId);
            // handlePost always redirects
        }

        // GET — build view model
        if ($episodeId !== null && $episodeId > 0) {
            return $this->buildEpisodeView($episodeId, $facilityId);
        }

        return $this->buildFacilityView($facilityId);
    }

    // ----------------------------------------------------------------- POST

    private function handlePost(int $facilityId, ?int $episodeId, ?int $userId): void
    {
        if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
            die('CSRF validation failed');
        }

        $action = (string)($_POST['action'] ?? '');

        // Prefer episode_id from POST for redirect after form submit
        $postEpisodeId = isset($_POST['episode_id']) && is_numeric($_POST['episode_id'])
            ? (int)$_POST['episode_id']
            : $episodeId;

        switch ($action) {
            case 'place_order':
                $this->actionPlaceOrder($facilityId, $userId);
                break;

            case 'discontinue_order':
                $orderId = (int)($_POST['order_id'] ?? 0);
                if ($orderId > 0) {
                    $this->service->discontinueOrder($orderId, $userId);
                }
                break;

            case 'record_admin':
                $this->actionRecordAdmin($userId);
                break;

            case 'give_prn':
                $this->actionGivePrn($postEpisodeId, $facilityId, $userId);
                break;
        }

        $redirect = 'mar.php?facility_id=' . urlencode((string)$facilityId);
        if ($postEpisodeId) {
            $redirect .= '&episode_id=' . urlencode((string)$postEpisodeId);
        }
        header('Location: ' . $redirect);
        exit;
    }

    private function actionPlaceOrder(int $facilityId, ?int $userId): void
    {
        $episodeId = (int)($_POST['episode_id'] ?? 0);
        $pid       = (int)($_POST['pid'] ?? 0);
        if ($episodeId <= 0 || $pid <= 0) {
            return;
        }

        $episode = $this->episodeRepo->fetchOne($episodeId);
        $startDt = (string)($episode['start_datetime'] ?? date('Y-m-d H:i:s'));

        $frequency = strtoupper(trim((string)($_POST['frequency'] ?? '')));
        $isPrn     = ($frequency === 'PRN' || (bool)($_POST['is_prn'] ?? false));

        $this->service->placeOrder(
            $episodeId, $pid, $facilityId,
            (string)($_POST['drug_name']    ?? ''),
            (string)($_POST['dose']         ?? ''),
            (string)($_POST['unit']         ?? ''),
            (string)($_POST['route']        ?? ''),
            $frequency,
            $isPrn,
            $userId,
            ($_POST['instructions'] ?? null) ?: null,
            $startDt,
            24
        );
    }

    private function actionRecordAdmin(?int $userId): void
    {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }
        $this->service->recordAdministration(
            $adminId,
            (string)($_POST['outcome']    ?? 'GIVEN'),
            ($_POST['dose_given']  ?? null) ?: null,
            ($_POST['unit_given']  ?? null) ?: null,
            ($_POST['route_given'] ?? null) ?: null,
            ($_POST['site']        ?? null) ?: null,
            ($_POST['lot_number']  ?? null) ?: null,
            $userId,
            ($_POST['note']        ?? null) ?: null
        );
    }

    private function actionGivePrn(?int $episodeId, int $facilityId, ?int $userId): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $pid     = (int)($_POST['pid']      ?? 0);
        if ($orderId <= 0 || $episodeId === null || $episodeId <= 0 || $pid <= 0) {
            return;
        }
        $this->service->givePrn(
            $orderId, $episodeId, $pid, $facilityId,
            (string)($_POST['drug_name']   ?? ''),
            ($_POST['dose_given']  ?? null) ?: null,
            ($_POST['unit_given']  ?? null) ?: null,
            ($_POST['route_given'] ?? null) ?: null,
            ($_POST['site']        ?? null) ?: null,
            $userId,
            ($_POST['note']        ?? null) ?: null
        );
    }

    // ------------------------------------------------------------------ GET view models

    /**
     * @return array<string,mixed>
     */
    private function buildEpisodeView(int $episodeId, int $facilityId): array
    {
        $episode = $this->episodeRepo->fetchOne($episodeId);
        $grid    = $this->service->buildMarGrid($episodeId);

        // Allergy check against active drug orders
        $allergyWarnings = [];
        if ($this->allergyService !== null && $episode !== null) {
            $pid       = (int)($episode['pid'] ?? 0);
            $drugNames = array_map(
                fn(array $o) => (string)($o['drug_name'] ?? ''),
                $grid
            );
            $drugNames = array_filter($drugNames);
            if ($pid > 0 && !empty($drugNames)) {
                $allergyWarnings = $this->allergyService->checkDrugAllergies($pid, $drugNames);
            }
        }

        return [
            'view'             => 'episode',
            'episode'          => $episode,
            'episode_id'       => $episodeId,
            'facility_id'      => $facilityId,
            'grid'             => $grid,
            'allergy_warnings' => $allergyWarnings,
            'csrf'             => CsrfUtils::collectCsrfToken(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function buildFacilityView(int $facilityId): array
    {
        $overdue = $this->adminRepo->listOverdueByFacility($facilityId);

        return [
            'view'             => 'facility',
            'facility_id'      => $facilityId,
            'overdue'          => $overdue,
            'allergy_warnings' => [],
            'csrf'             => CsrfUtils::collectCsrfToken(),
        ];
    }
}

<?php

/**
 * src/Shared/Submodule/Mar/Controller/MarController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service\AllergyService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service\MarService;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

/**
 * MAR Controller.
 *
 * Handles three distinct views/actions:
 *   GET  mar.php?episode_id=N        — MAR grid for one episode
 *   GET  mar.php?facility_id=N       — Facility-wide pending / overdue list
 *   GET  mar.php?episode_id=N&print=1 — Printable MAR for episode
 *   POST mar.php                     — Record administration / place order /
 *                                      discontinue / amend / extend window
 *
 * Allergy checking (AllergyService) is injected optionally.
 */
final class MarController
{
    private MarService $service;

    public function __construct(
        private readonly MarOrderRepository          $orderRepo,
        private readonly MarAdministrationRepository $adminRepo,
        private readonly EpisodeRepository           $episodeRepo,
        private readonly ?AllergyService             $allergyService = null,
        private readonly ?TaskRepository             $taskRepo = null
    ) {
        $this->service = new MarService($orderRepo, $adminRepo, $taskRepo);
    }

    /**
     * Main entry point. Returns a view-model array for the public page.
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
            $print = isset($_GET['print']) && $_GET['print'] === '1';
            return $this->buildEpisodeView($episodeId, $facilityId, $print);
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

            case 'update_order':
                $this->actionUpdateOrder($userId);
                break;

            case 'discontinue_order':
                $orderId = (int)($_POST['order_id'] ?? 0);
                if ($orderId > 0) {
                    $this->service->discontinueOrder($orderId, $userId);
                }
                break;

            case 'extend_window':
                $orderId = (int)($_POST['order_id'] ?? 0);
                $hours   = max(1, min(72, (int)($_POST['extend_hours'] ?? 24)));
                if ($orderId > 0) {
                    $this->service->extendOrderSlots($orderId, $hours, $userId);
                }
                break;

            case 'record_admin':
                $this->actionRecordAdmin($userId);
                break;

            case 'amend_admin':
                $this->actionAmendAdmin($userId);
                break;

            case 'give_prn':
                $this->actionGivePrn($postEpisodeId, $facilityId, $userId);
                break;

            case 'co_sign':
                $adminId     = (int)($_POST['admin_id']        ?? 0);
                $coSignUserId = (int)($_POST['co_sign_user_id'] ?? 0);
                if ($adminId > 0 && $coSignUserId > 0) {
                    $this->service->coSignAdministration($adminId, $coSignUserId);
                }
                break;

            case 'import_rx':
                $this->actionImportRx($facilityId, $postEpisodeId, $userId);
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

        $frequency           = $this->orderRepo->normalizeFrequency((string)($_POST['frequency'] ?? ''));
        $isPrn               = ($frequency === 'PRN' || (bool)($_POST['is_prn'] ?? false));
        $isHighAlertOverride = (bool)($_POST['is_high_alert'] ?? false);
        $isStat              = !$isPrn && (bool)($_POST['is_stat'] ?? false);

        $this->service->placeOrder(
            $episodeId, $pid, $facilityId,
            trim((string)($_POST['drug_name'] ?? '')),
            trim((string)($_POST['dose'] ?? '')),
            $this->orderRepo->normalizeUnit((string)($_POST['unit'] ?? '')),
            $this->orderRepo->normalizeRoute((string)($_POST['route'] ?? '')),
            $frequency !== '' ? $frequency : 'QD',
            $isPrn,
            $isHighAlertOverride,
            $userId,
            ($_POST['instructions'] ?? null) ?: null,
            $startDt,
            24,
            $isStat
        );
    }

    private function actionUpdateOrder(?int $userId): void
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        if ($orderId <= 0) {
            return;
        }

        $frequency = $this->orderRepo->normalizeFrequency((string)($_POST['frequency'] ?? ''));
        $isPrn = ($frequency === 'PRN' || (bool)($_POST['is_prn'] ?? false));
        $this->service->updateOrder(
            $orderId,
            (string)($_POST['drug_name'] ?? ''),
            (string)($_POST['dose'] ?? ''),
            (string)($_POST['unit'] ?? ''),
            (string)($_POST['route'] ?? ''),
            $frequency,
            $isPrn,
            ($_POST['instructions'] ?? null) ?: null,
            (bool)($_POST['is_high_alert'] ?? false),
            !$isPrn && (bool)($_POST['is_stat'] ?? false)
        );
    }

    private function actionRecordAdmin(?int $userId): void
    {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }
        $witnessUserId = isset($_POST['witness_user_id']) && (int)$_POST['witness_user_id'] > 0
            ? (int)$_POST['witness_user_id'] : null;
        $this->service->recordAdministration(
            $adminId,
            (string)($_POST['outcome']             ?? 'GIVEN'),
            ($_POST['administered_datetime']        ?? null) ?: null,
            ($_POST['dose_given']                   ?? null) ?: null,
            ($_POST['unit_given']                   ?? null) ?: null,
            ($_POST['route_given']                  ?? null) ?: null,
            ($_POST['site']                         ?? null) ?: null,
            ($_POST['lot_number']                   ?? null) ?: null,
            $userId,
            ($_POST['hold_reason']                  ?? null) ?: null,
            ($_POST['note']                         ?? null) ?: null,
            $witnessUserId,
            ($_POST['waste_amount']                 ?? null) ?: null,
            ($_POST['waste_unit']                   ?? null) ?: null,
            $this->collectExceptionFollowUp()
        );
    }

    private function actionAmendAdmin(?int $userId): void
    {
        $adminId = (int)($_POST['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return;
        }
        $witnessUserId = isset($_POST['witness_user_id']) && (int)$_POST['witness_user_id'] > 0
            ? (int)$_POST['witness_user_id'] : null;
        $this->service->amendAdministration(
            $adminId,
            (string)($_POST['outcome']             ?? 'GIVEN'),
            ($_POST['administered_datetime']        ?? null) ?: null,
            ($_POST['dose_given']                   ?? null) ?: null,
            ($_POST['unit_given']                   ?? null) ?: null,
            ($_POST['route_given']                  ?? null) ?: null,
            ($_POST['site']                         ?? null) ?: null,
            ($_POST['lot_number']                   ?? null) ?: null,
            $userId,
            ($_POST['hold_reason']                  ?? null) ?: null,
            ($_POST['note']                         ?? null) ?: null,
            $witnessUserId,
            ($_POST['waste_amount']                 ?? null) ?: null,
            ($_POST['waste_unit']                   ?? null) ?: null,
            $this->collectExceptionFollowUp()
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
            (string)($_POST['drug_name']           ?? ''),
            (bool)($_POST['is_high_alert']          ?? false),
            ($_POST['dose_given']                   ?? null) ?: null,
            ($_POST['unit_given']                   ?? null) ?: null,
            ($_POST['route_given']                  ?? null) ?: null,
            ($_POST['site']                         ?? null) ?: null,
            ($_POST['lot_number']                   ?? null) ?: null,
            $userId,
            ($_POST['administered_datetime']        ?? null) ?: null,
            ($_POST['note']                         ?? null) ?: null
        );
    }

    // ------------------------------------------------------------------ GET view models

    private function actionImportRx(int $facilityId, ?int $episodeId, ?int $userId): void
    {
        $episodeId = (int)($episodeId ?? 0);
        $pid       = (int)($_POST['pid'] ?? 0);
        if ($episodeId <= 0 || $pid <= 0) return;
        $rxIds = array_filter(array_map('intval', (array)($_POST['rx_ids'] ?? [])));
        if (empty($rxIds)) return;
        $allRx = $this->orderRepo->listActivePrescriptions($pid);
        $rxById = [];
        foreach ($allRx as $rx) { $rxById[(int)$rx['id']] = $rx; }
        foreach ($rxIds as $rxId) {
            if (!isset($rxById[$rxId])) continue;
            $rx = $rxById[$rxId];
            $rx['_display_drug'] = trim((string)((($_POST['rx_display_drug'] ?? [])[$rxId] ?? '') ?: ($rx['_display_drug'] ?? $rx['drug'] ?? '')));
            $rx['_dose'] = trim((string)((($_POST['rx_dose'] ?? [])[$rxId] ?? '') ?: ($rx['size'] ?? '')));
            $rx['_unit'] = $this->orderRepo->normalizeUnit((string)((($_POST['rx_unit'] ?? [])[$rxId] ?? '') ?: ($rx['_unit'] ?? $rx['unit'] ?? '')));
            $rx['_route'] = $this->orderRepo->normalizeRoute((string)((($_POST['rx_route'] ?? [])[$rxId] ?? '') ?: ($rx['_route'] ?? $rx['route'] ?? '')));
            $rx['_freq'] = $this->orderRepo->normalizeFrequency((string)((($_POST['rx_frequency'] ?? [])[$rxId] ?? '') ?: ($rx['_freq'] ?? $rx['interval'] ?? '')));
            $rx['_sig'] = trim((string)((($_POST['rx_sig'] ?? [])[$rxId] ?? '') ?: ($rx['_sig'] ?? $rx['note'] ?? '')));
            $orderId = $this->orderRepo->importFromPrescription(
                $episodeId, $pid, $facilityId, $rx, $userId,
                $this->service->isHighAlert((string)($rx['_display_drug'] ?? $rx['drug'] ?? ''))
            );
            if ($orderId > 0) { $this->service->extendOrderSlots($orderId, 24, $userId); }
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function buildEpisodeView(int $episodeId, int $facilityId, bool $print = false): array
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

        $rxPrescriptions = [];
        $importedRxIds   = [];
        if (!$print && $episode !== null) {
            $pid2 = (int)($episode['pid'] ?? 0);
            if ($pid2 > 0) {
                $rxPrescriptions = $this->orderRepo->listActivePrescriptions($pid2);
                $importedRxIds   = $this->orderRepo->listImportedRxIds($episodeId);
            }
        }

        $rxSourceMap = [];
        foreach ($rxPrescriptions as $rxRow) {
            $rxSourceMap[(int)($rxRow['id'] ?? 0)] = $rxRow;
        }
        foreach ($grid as &$orderRow) {
            $rxId = (int)($orderRow['rx_id'] ?? 0);
            if ($rxId > 0 && isset($rxSourceMap[$rxId])) {
                $orderRow['_source_rx'] = $rxSourceMap[$rxId];
            }
        }
        unset($orderRow);

        $workspace = $print ? [] : $this->buildWorkspaceForEpisode($episodeId);
        return [
            'view'             => 'episode',
            'print'            => $print,
            'episode'          => $episode,
            'episode_id'       => $episodeId,
            'facility_id'      => $facilityId,
            'grid'             => $grid,
            'workspace'        => $workspace,
            'order_vocab'      => $this->orderRepo->getOrderVocabulary(),
            'drug_lookup'      => $this->orderRepo->listDrugLookupOptions(),
            'allergy_warnings' => $allergyWarnings,
            'hold_reasons'     => MarService::HOLD_REASONS,
            'csrf'             => CsrfUtils::collectCsrfToken(),
            'rx_prescriptions' => $rxPrescriptions,
            'imported_rx_ids'  => $importedRxIds,
            'rx_source_map'    => $rxSourceMap,
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
            'workspace'        => $this->buildWorkspaceForFacility($facilityId),
            'allergy_warnings' => [],
            'hold_reasons'     => MarService::HOLD_REASONS,
            'csrf'             => CsrfUtils::collectCsrfToken(),
            'rx_prescriptions' => [],
            'imported_rx_ids'  => [],
        ];
    }


    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function buildWorkspaceForEpisode(int $episodeId): array
    {
        return $this->shapeWorkspace(
            $this->adminRepo->listPendingWorkspaceByEpisode($episodeId),
            $this->adminRepo->listAwaitingCoSignByEpisode($episodeId),
            $this->adminRepo->listRecentPrnByEpisode($episodeId),
            $this->taskRepo?->listOpenMarFollowUpByEpisode($episodeId) ?? []
        );
    }

    /**
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function buildWorkspaceForFacility(int $facilityId): array
    {
        return $this->shapeWorkspace(
            $this->adminRepo->listPendingWorkspaceByFacility($facilityId),
            $this->adminRepo->listAwaitingCoSignByFacility($facilityId),
            $this->adminRepo->listRecentPrnByFacility($facilityId),
            $this->taskRepo?->listOpenMarFollowUpByFacility($facilityId) ?? []
        );
    }

    /**
     * @param array<int,array<string,mixed>> $pending
     * @param array<int,array<string,mixed>> $awaitingCoSign
     * @param array<int,array<string,mixed>> $recentPrn
     * @param array<int,array<string,mixed>> $exceptionFollowUp
     * @return array<string,array<int,array<string,mixed>>>
     */
    private function shapeWorkspace(array $pending, array $awaitingCoSign, array $recentPrn, array $exceptionFollowUp = []): array
    {
        $now = time();
        $dueNow = [];
        $dueSoon = [];
        $overdue = [];
        foreach ($pending as $row) {
            $ts = !empty($row['scheduled_datetime']) ? (strtotime((string)$row['scheduled_datetime']) ?: 0) : 0;
            if ($ts <= 0) {
                $dueNow[] = $row;
                continue;
            }
            if ($ts < $now) {
                $overdue[] = $row;
            } elseif ($ts <= $now + (15 * 60)) {
                $dueNow[] = $row;
            } elseif ($ts <= $now + (60 * 60)) {
                $dueSoon[] = $row;
            }
        }
        return [
            'due_now' => array_slice($dueNow, 0, 12),
            'due_soon' => array_slice($dueSoon, 0, 12),
            'overdue' => array_slice($overdue, 0, 12),
            'awaiting_cosign' => array_slice($awaitingCoSign, 0, 12),
            'recent_prn' => array_slice($recentPrn, 0, 12),
            'exception_followup' => array_slice($exceptionFollowUp, 0, 12),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function collectExceptionFollowUp(): array
    {
        $retryLater = !empty($_POST['retry_later']);
        $retryMinutes = $retryLater ? max(0, min(480, (int)($_POST['retry_minutes'] ?? 0))) : 0;
        return [
            'provider_notified' => !empty($_POST['provider_notified']),
            'pharmacy_follow_up' => !empty($_POST['pharmacy_follow_up']),
            'retry_minutes' => $retryMinutes,
        ];
    }

}









<?php

/**
 * src/AssistedLiving/Submodule/AlMar/Controller/AlMarController.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Repository\AlMarRepository;

/**
 * AlMarController
 *
 * Renders the AL MAR as a 5-day rolling window centred on today.
 * The grid shows each standing order as a row, with time slots across columns.
 * PRN medications appear in a separate section below scheduled orders.
 *
 * POST handles:
 *   administer  — record outcome on a scheduled slot (GIVEN / HELD / REFUSED / OMITTED)
 *   amend_admin — correct a previously-documented administration
 *   give_prn    — record an as-needed dose outside the scheduled grid
 *
 * Delegates all writes to AlMarRepository → shared MarService so that
 * high-alert detection, scheduling rules, and allergy logic apply equally
 * across AL, ED, Obs, and BH contexts.
 *
 * Redirects back with flash after POST to prevent double-submit (PRG pattern).
 */
final class AlMarController
{
    public function __construct(
        private readonly AlMarRepository $repo = new AlMarRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $flash = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Fix: module-standard CSRF field name is csrf_token_form
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                $flash = xlt('Security token invalid — please reload and try again.');
            } else {
                $flash = $this->handlePost($episodeId, $pid, $facilityId, $userId);
            }

            // PRG — redirect after POST to prevent double-submit
            $url = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
            $qs  = http_build_query([
                'episode_id'  => $episodeId,
                'pid'         => $pid,
                'flash'       => $flash,
            ]);
            header('Location: ' . $url . '?' . $qs);
            exit;
        }

        // Flash from redirect
        if (!empty($_GET['flash'])) {
            $flash = htmlspecialchars((string)$_GET['flash']);
        }

        // 5-day window: today ± offset (keyboard navigation)
        $windowDays = 5;
        $offset     = (int)($_GET['offset'] ?? 0);
        $dateFrom   = date('Y-m-d', strtotime("today {$offset} days"));
        $dateTo     = date('Y-m-d', strtotime("today +4 days {$offset} days"));

        // Build date column headers
        $dates = [];
        for ($i = 0; $i < $windowDays; $i++) {
            $d = date('Y-m-d', strtotime($dateFrom . " +$i days"));
            $dates[] = [
                'date'     => $d,
                'label'    => date('D n/j', strtotime($d)),
                'is_today' => ($d === date('Y-m-d')),
            ];
        }

        // Load all admins in window and split orders
        $admins          = $this->repo->listAdminsByWindow($episodeId, $dateFrom, $dateTo);
        $scheduledOrders = $this->repo->listActiveOrders($episodeId);
        $scheduled       = array_values(array_filter($scheduledOrders, fn($o) => !(bool)$o['is_prn']));
        $prn             = array_values(array_filter($scheduledOrders, fn($o) => (bool)$o['is_prn']));

        // Index admins by mar_order_id → date → [slots]
        $grid = [];
        foreach ($admins as $a) {
            $orderId              = $a['mar_order_id'];
            $date                 = substr($a['scheduled_datetime'], 0, 10);
            $grid[$orderId][$date][] = $a;
        }

        // Patient context (room, unit for header strip)
        $patient = null;
        if (function_exists('sqlQuery')) {
            $row = sqlQuery(
                "SELECT e.id, e.pid, pd.fname, pd.lname,
                        COALESCE(ale.room,'') AS room,
                        COALESCE(ale.unit,'') AS unit
                 FROM   oei_episode e
                 INNER  JOIN patient_data pd ON pd.pid = e.pid
                 LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
                 WHERE  e.id = ? LIMIT 1",
                [$episodeId]
            );
            $patient = $row ?: null;
        }

        return [
            'flash'       => $flash,
            'csrf'        => CsrfUtils::collectCsrfToken(), // view uses hidden input — never echoed raw
            'patient'     => $patient,
            'dates'       => $dates,
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'offset'      => $offset,
            'scheduled'   => $scheduled,
            'prn'         => $prn,
            'grid'        => $grid,
            'all_admins'  => $admins,
            'workspace'   => $this->repo->buildWorkspace($episodeId),
            'mar_vocab'   => $this->repo->getOrderVocabulary(),
            'hold_reasons'=> $this->repo->holdReasons(),
        ];
    }

    // ------------------------------------------------------------------ POST

    private function handlePost(int $episodeId, int $pid, int $facilityId, ?int $userId): string
    {
        $p      = $_POST;
        $action = (string)($p['action'] ?? '');

        return match ($action) {
            'administer'  => $this->actionAdminister($p, $userId),
            'amend_admin' => $this->actionAmendAdmin($p, $userId),
            'give_prn'    => $this->actionGivePrn($p, $episodeId, $pid, $facilityId, $userId),
            'co_sign'     => $this->actionCoSign($p, $userId),
            default       => xlt('Unknown action.'),
        };
    }

    /**
     * Record outcome on a scheduled slot (GIVEN / HELD / REFUSED / OMITTED).
     */
    private function actionAdminister(array $p, ?int $userId): string
    {
        $admId = (int)($p['administration_id'] ?? 0);
        if ($admId <= 0) {
            return xlt('Invalid administration record.');
        }

        $rawOutcome = (string)($p['outcome'] ?? 'GIVEN');
        $outcome = in_array($rawOutcome, ['GIVEN', 'HELD', 'REFUSED', 'OMITTED', 'NOT_AVAILABLE', 'MISSED'], true)
            ? $rawOutcome : 'GIVEN';
        if ($outcome === 'OMITTED') {
            $outcome = 'MISSED';
        }
        $dose       = trim((string)($p['dose_given']  ?? '')) ?: null;
        $unit       = trim((string)($p['unit_given']  ?? '')) ?: null;
        $route      = trim((string)($p['route_given'] ?? '')) ?: null;
        $site       = trim((string)($p['site']        ?? '')) ?: null;
        $holdReason = trim((string)($p['hold_reason'] ?? '')) ?: null;
        $note       = trim((string)($p['note']        ?? '')) ?: null;

        $witnessUserId = isset($p['witness_user_id']) && (int)$p['witness_user_id'] > 0
                         ? (int)$p['witness_user_id'] : null;
        $wasteAmount   = trim((string)($p['waste_amount'] ?? '')) ?: null;
        $wasteUnit     = trim((string)($p['waste_unit']   ?? '')) ?: null;

        $ok = $this->repo->administer(
            $admId, $outcome, $dose, $unit, $route,
            $site, $holdReason, $note, $userId,
            $witnessUserId, $wasteAmount, $wasteUnit,
            $this->collectExceptionFollowUp($p)
        );

        return $ok
            ? xlt('Administration recorded:') . ' ' . xlt($outcome)
            : xlt('Error recording administration.');
    }

    /**
     * Amend a previously-documented administration (preserves original in note).
     */
    private function actionAmendAdmin(array $p, ?int $userId): string
    {
        $admId = (int)($p['administration_id'] ?? 0);
        if ($admId <= 0) {
            return xlt('Invalid administration record.');
        }

        $rawOutcome = (string)($p['outcome'] ?? 'GIVEN');
        $outcome = in_array($rawOutcome, ['GIVEN', 'HELD', 'REFUSED', 'OMITTED', 'NOT_AVAILABLE', 'MISSED'], true)
                   ? $rawOutcome : 'GIVEN';
        if ($outcome === 'OMITTED') {
            $outcome = 'MISSED';
        }

        $amendWitnessUserId = isset($p['witness_user_id']) && (int)$p['witness_user_id'] > 0
                              ? (int)$p['witness_user_id'] : null;
        $amendWasteAmount   = trim((string)($p['waste_amount'] ?? '')) ?: null;
        $amendWasteUnit     = trim((string)($p['waste_unit']   ?? '')) ?: null;

        $ok = $this->repo->amendAdministration(
            $admId,
            $outcome,
            trim((string)($p['dose_given']  ?? '')) ?: null,
            trim((string)($p['unit_given']  ?? '')) ?: null,
            trim((string)($p['route_given'] ?? '')) ?: null,
            trim((string)($p['site']        ?? '')) ?: null,
            trim((string)($p['hold_reason'] ?? '')) ?: null,
            trim((string)($p['note']        ?? '')) ?: null,
            $userId,
            $amendWitnessUserId, $amendWasteAmount, $amendWasteUnit,
            $this->collectExceptionFollowUp($p)
        );

        return $ok
            ? xlt('Administration amended.')
            : xlt('Error amending administration.');
    }

    /**
     * Record an as-needed (PRN) dose — creates a fresh administration slot
     * and immediately records it as GIVEN via shared MarService::givePrn().
     */
    private function actionGivePrn(array $p, int $episodeId, int $pid, int $facilityId, ?int $userId): string
    {
        $orderId = (int)($p['order_id'] ?? 0);
        if ($orderId <= 0) {
            return xlt('Invalid medication order.');
        }

        $this->repo->givePrn(
            $orderId,
            $episodeId,
            $pid,
            $facilityId,
            (string)($p['drug_name']     ?? ''),
            (bool)($p['is_high_alert']   ?? false),
            trim((string)($p['dose_given']  ?? '')) ?: null,
            trim((string)($p['unit_given']  ?? '')) ?: null,
            trim((string)($p['route_given'] ?? '')) ?: null,
            trim((string)($p['site']        ?? '')) ?: null,
            trim((string)($p['lot_number']  ?? '')) ?: null,
            $userId,
            trim((string)($p['administered_datetime'] ?? '')) ?: null,
            trim((string)($p['note']        ?? '')) ?: null
        );

        $drugName = htmlspecialchars((string)($p['drug_name'] ?? ''));
        return xlt('PRN dose recorded:') . ' ' . $drugName;
    }

    /**
     * @param array<string,mixed> $p
     * @return array<string,mixed>
     */
    private function collectExceptionFollowUp(array $p): array
    {
        $retryLater = !empty($p['retry_later']);
        $retryMinutes = $retryLater ? max(0, min(480, (int)($p['retry_minutes'] ?? 0))) : 0;
        return [
            'provider_notified' => !empty($p['provider_notified']),
            'pharmacy_follow_up' => !empty($p['pharmacy_follow_up']),
            'retry_minutes' => $retryMinutes,
        ];
    }

    private function actionCoSign(array $p, ?int $userId): string
    {
        $admId       = (int)($p['administration_id'] ?? 0);
        $coSignUserId = (int)($p['co_sign_user_id']   ?? 0);
        if ($admId <= 0 || $coSignUserId <= 0) {
            return xlt('Invalid co-sign request.');
        }
        $this->repo->coSign($admId, $coSignUserId);
        return xlt('Co-signature recorded.');
    }
}










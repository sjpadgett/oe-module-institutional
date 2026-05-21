<?php

/**
 * src/Shared/Submodule/Mar/Service/MarService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Mar\Repository\MarAdministrationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

/**
 * MAR Service — orchestrates medication order creation, scheduling, and administration.
 *
 * High-alert drug detection uses a simple keyword list that can be extended in
 * the future by pulling from oei_settings or an external formulary.
 */
final class MarService
{
    /**
     * Drug name keywords that trigger the high_alert flag on administration slots.
     * Case-insensitive substring match.
     */
    private const HIGH_ALERT_KEYWORDS = [
        'insulin', 'heparin', 'warfarin', 'coumadin',
        'morphine', 'hydromorphone', 'fentanyl', 'oxycodone',
        'methadone', 'ketamine', 'vancomycin', 'gentamicin',
        'digoxin', 'lithium', 'potassium chloride', 'kcl',
        'hypertonic saline', '3% nacl', 'concentrated',
        'epinephrine', 'norepinephrine', 'dopamine', 'dobutamine',
        'nitroprusside', 'nicardipine', 'amiodarone',
    ];

    /**
     * Standard scheduled-frequency definitions → [interval_minutes].
     * Unknown frequencies treat the medication as PRN.
     */
    private const FREQUENCY_MINUTES = [
        'Q1H'   => 60,
        'Q2H'   => 120,
        'Q4H'   => 240,
        'Q6H'   => 360,
        'Q8H'   => 480,
        'Q12H'  => 720,
        'QD'    => 1440,
        'DAILY' => 1440,
        'BID'   => 720,
        'TID'   => 480,
        'QID'   => 360,
    ];

    /** Structured reasons a nurse may HOLD a medication. */
    public const HOLD_REASONS = [
        ''                => '— Select reason —',
        'NPO'             => 'Patient NPO',
        'HR_LOW'          => 'HR too low (< threshold)',
        'BP_LOW'          => 'BP too low (< threshold)',
        'RR_LOW'          => 'RR too low (< threshold)',
        'O2_LOW'          => 'SpO2 too low',
        'LAB_PENDING'     => 'Lab result pending',
        'LEVEL_HIGH'      => 'Drug level too high',
        'PATIENT_REFUSED' => 'Patient refused (document separately)',
        'ORDERED_HOLD'    => 'Physician order to hold',
        'NOT_AVAILABLE'   => 'Medication not available',
        'OTHER'           => 'Other (see note)',
    ];

    public function __construct(
        private readonly MarOrderRepository $orders,
        private readonly MarAdministrationRepository $admins,
        private readonly ?TaskRepository $tasks = null
    ) {}

    // ----------------------------------------------------------------- orders

    /**
     * Place a new medication order and immediately generate scheduled slots
     * for the supplied episode window (now → now + windowHours).
     *
     * Returns the new mar_order.id.
     */
    public function placeOrder(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        string $dose,
        string $unit,
        string $route,
        string $frequency,
        bool $isPrn,
        bool $isHighAlertOverride,
        ?int $orderedByUserId,
        ?string $instructions,
        string $episodeStartDatetime,
        int $windowHours = 24,
        bool $isStat = false
    ): int {
        $now = date('Y-m-d H:i:s');

        $isHighAlertFinal = $isHighAlertOverride || $this->isHighAlert($drugName);
        $orderId = $this->orders->create(
            $episodeId, $pid, $facilityId,
            $drugName, $dose, $unit, $route, $frequency,
            $isPrn, $now, $orderedByUserId,
            null, $instructions, $isStat, $isHighAlertFinal
        );

        if ($orderId > 0 && !$isPrn) {
            if ($isStat) {
                // STAT: create one immediate slot right now, then the rolling window
                $this->admins->createScheduled(
                    $orderId, $episodeId, $pid, $facilityId,
                    $now,
                    $isHighAlertOverride || $this->isHighAlert($drugName)
                );
            }
            $this->generateScheduledSlots(
                $orderId, $episodeId, $pid, $facilityId,
                $drugName, $frequency, $now, $windowHours,
                $isHighAlertOverride
            );
        }

        return $orderId;
    }

    /**
     * Discontinue an order and void its PENDING administration slots.
     */
    public function discontinueOrder(int $orderId, ?int $userId): void
    {
        $this->admins->voidPendingForOrder($orderId);
        $this->orders->discontinue($orderId, $userId);
    }

    /**
     * Update an existing MAR order and refresh future pending slots.
     */
    public function updateOrder(
        int $orderId,
        string $drugName,
        string $dose,
        string $unit,
        string $route,
        string $frequency,
        bool $isPrn,
        ?string $instructions,
        bool $isHighAlertOverride,
        bool $isStat = false
    ): bool {
        $order = $this->orders->getById($orderId);
        if ($order === null) {
            return false;
        }

        $isHighAlertFinal = $isHighAlertOverride || $this->isHighAlert($drugName);
        $updated = $this->orders->updateOrder(
            $orderId,
            $drugName,
            $dose,
            $unit,
            $route,
            $frequency,
            $isPrn,
            $instructions,
            $isHighAlertFinal,
            $isStat
        );
        if (!$updated) {
            return false;
        }

        $this->admins->voidPendingForOrder($orderId);
        if (!$isPrn) {
            $now = date('Y-m-d H:i:s');
            if ($isStat) {
                $this->admins->createScheduled(
                    $orderId,
                    (int)$order['episode_id'],
                    (int)$order['pid'],
                    (int)$order['facility_id'],
                    $now,
                    $isHighAlertFinal
                );
            }
            $this->generateScheduledSlots(
                $orderId,
                (int)$order['episode_id'],
                (int)$order['pid'],
                (int)$order['facility_id'],
                $drugName,
                $frequency,
                $now,
                24,
                $isHighAlertFinal
            );
        }
        return true;
    }

    /**
     * Extend a scheduled order's slot window by $hours from the last
     * existing scheduled slot (or now if no slots exist yet).
     *
     * Safe to call repeatedly — only adds future slots, never duplicates.
     */
    public function extendOrderSlots(int $orderId, int $hours, ?int $userId): bool
    {
        $order = $this->orders->getById($orderId);
        if ($order === null || $order['status'] !== 'ACTIVE' || (bool)$order['is_prn']) {
            return false;
        }

        $freqKey = strtoupper(trim((string)$order['frequency']));
        if (!isset(self::FREQUENCY_MINUTES[$freqKey])) {
            return false; // unknown frequency, can't auto-schedule
        }

        // Start from the latest existing slot, or now if none
        $latestSlot  = $this->admins->latestScheduledDatetime($orderId);
        $startTs     = $latestSlot ? (strtotime($latestSlot) ?: time()) : time();
        $endTs       = $startTs + ($hours * 3600);
        $intervalSecs = self::FREQUENCY_MINUTES[$freqKey] * 60;
        $isHighAlert  = $this->isHighAlert((string)$order['drug_name']);

        $t = $startTs + $intervalSecs;
        while ($t <= $endTs) {
            $this->admins->createScheduled(
                $orderId,
                (int)$order['episode_id'],
                (int)$order['pid'],
                (int)$order['facility_id'],
                date('Y-m-d H:i:s', $t),
                $isHighAlert
            );
            $t += $intervalSecs;
        }

        return true;
    }

    // ----------------------------------------------- administration recording

    /**
     * Record a nurse's administration of a scheduled slot.
     *
     * $administeredDatetime may be nurse-supplied (after-the-fact documentation)
     * or null to default to now. Timestamp is always stored for all outcomes.
     *
     * @param array<string,mixed> $followUp
     */
    public function recordAdministration(
        int $adminId,
        string $outcome,
        ?string $administeredDatetime,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $nurseUserId,
        ?string $holdReason,
        ?string $note,
        ?int $witnessUserId = null,
        ?string $wasteAmount = null,
        ?string $wasteUnit = null,
        array $followUp = []
    ): void {
        // Normalise nurse-supplied datetime; fall back to now
        $dt = ($administeredDatetime && strtotime($administeredDatetime))
            ? date('Y-m-d H:i:s', strtotime($administeredDatetime))
            : date('Y-m-d H:i:s');

        $this->admins->record(
            $adminId, $outcome, $dt,
            $doseGiven, $unitGiven, $routeGiven,
            $site, $lotNumber, $nurseUserId,
            $holdReason ?: null, $this->composeExceptionNote($outcome, $note, $followUp),
            $witnessUserId, $wasteAmount, $wasteUnit
        );
        $this->scheduleExceptionFollowUp($adminId, $outcome, $followUp, $nurseUserId);
    }

    /**
     * Amend a previously completed administration row.
     *
     * @param array<string,mixed> $followUp
     */
    public function amendAdministration(
        int $adminId,
        string $outcome,
        ?string $administeredDatetime,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $nurseUserId,
        ?string $holdReason,
        ?string $note,
        ?int $witnessUserId = null,
        ?string $wasteAmount = null,
        ?string $wasteUnit = null,
        array $followUp = []
    ): void {
        $dt = ($administeredDatetime && strtotime($administeredDatetime))
            ? date('Y-m-d H:i:s', strtotime($administeredDatetime))
            : date('Y-m-d H:i:s');

        $this->admins->amend(
            $adminId, $outcome, $dt,
            $doseGiven, $unitGiven, $routeGiven,
            $site, $lotNumber, $nurseUserId,
            $holdReason ?: null, $this->composeExceptionNote($outcome, $note, $followUp),
            $witnessUserId, $wasteAmount, $wasteUnit
        );
        $this->scheduleExceptionFollowUp($adminId, $outcome, $followUp, $nurseUserId);
    }

    /**
     * Create a PRN administration record for a patient who requests an as-needed
     * medication, then immediately record it as given.
     */
    public function givePrn(
        int $marOrderId,
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        bool $isHighAlertOverride,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $nurseUserId,
        ?string $administeredDatetime,
        ?string $note
    ): void {
        $isHighAlert = $isHighAlertOverride || $this->isHighAlert($drugName);
        $adminId     = $this->admins->createPrn($marOrderId, $episodeId, $pid, $facilityId, $isHighAlert);
        if ($adminId > 0) {
            $dt = ($administeredDatetime && strtotime($administeredDatetime))
                ? date('Y-m-d H:i:s', strtotime($administeredDatetime))
                : date('Y-m-d H:i:s');
            $this->admins->record(
                $adminId, 'GIVEN', $dt,
                $doseGiven, $unitGiven, $routeGiven,
                $site, $lotNumber, $nurseUserId,
                null, $note
            );
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Build the MAR view model for a public page:
     * orders grouped with their administration rows.
     *
     * @return array<int,array<string,mixed>> Each entry = order + 'admins' subarray
     */
    public function buildMarGrid(int $episodeId): array
    {
        $orders    = $this->orders->listActiveByEpisode($episodeId);
        $allAdmins = $this->admins->listByEpisode($episodeId);

        // Index admins by mar_order_id
        $indexed = [];
        foreach ($allAdmins as $a) {
            $indexed[(int)$a['mar_order_id']][] = $a;
        }

        foreach ($orders as &$o) {
            $o['admins'] = $indexed[(int)$o['id']] ?? [];
        }
        unset($o);

        return $orders;
    }

    /**
     * @param array<string,mixed> $followUp
     */
    private function composeExceptionNote(string $outcome, ?string $note, array $followUp): ?string
    {
        $base = trim((string)$note);
        if (!$this->isExceptionOutcome($outcome)) {
            return $base !== '' ? $base : null;
        }
        $bits = [];
        if (!empty($followUp['provider_notified'])) {
            $bits[] = 'Provider notified';
        }
        if (!empty($followUp['pharmacy_follow_up'])) {
            $bits[] = 'Pharmacy follow-up requested';
        }
        $retryMinutes = (int)($followUp['retry_minutes'] ?? 0);
        if ($retryMinutes > 0) {
            $bits[] = 'Retry in ' . $retryMinutes . 'm';
        }
        if (empty($bits)) {
            return $base !== '' ? $base : null;
        }
        $prefix = '[Exception follow-up: ' . implode('; ', $bits) . ']';
        return $base !== '' ? $prefix . ' ' . $base : $prefix;
    }

    /**
     * @param array<string,mixed> $followUp
     */
    private function scheduleExceptionFollowUp(int $adminId, string $outcome, array $followUp, ?int $userId): void
    {
        if ($this->tasks === null || !$this->isExceptionOutcome($outcome)) {
            return;
        }
        $admin = $this->admins->getById($adminId);
        if ($admin === null) {
            return;
        }
        $order = $this->orders->getById((int)($admin['mar_order_id'] ?? 0));
        $drugName = (string)($order['drug_name'] ?? 'Medication');
        $dose = (string)($order['dose'] ?? '');
        $unit = (string)($order['unit'] ?? '');
        $route = (string)($order['route'] ?? '');
        $holdLabel = '';
        $holdReason = (string)($admin['hold_reason'] ?? '');
        if ($holdReason !== '') {
            $holdLabel = self::HOLD_REASONS[$holdReason] ?? $holdReason;
        }

        if (!empty($followUp['pharmacy_follow_up'])) {
            $this->tasks->create(
                (int)$admin['episode_id'],
                (int)$admin['pid'],
                isset($admin['eid']) && is_numeric((string)$admin['eid']) ? (int)$admin['eid'] : null,
                (int)$admin['facility_id'],
                'MAR_PHARMACY_FOLLOWUP',
                date('Y-m-d H:i:s'),
                $userId,
                json_encode([
                    'source' => 'MAR',
                    'admin_id' => $adminId,
                    'mar_order_id' => (int)($admin['mar_order_id'] ?? 0),
                    'drug_name' => $drugName,
                    'dose' => $dose,
                    'unit' => $unit,
                    'route' => $route,
                    'task_label' => 'Pharmacy follow-up',
                    'detail' => $holdLabel !== '' ? $holdLabel : $outcome,
                ], JSON_UNESCAPED_SLASHES)
            );
        }

        $retryMinutes = (int)($followUp['retry_minutes'] ?? 0);
        if ($retryMinutes > 0 && in_array($outcome, ['HELD', 'NOT_AVAILABLE', 'MISSED'], true)) {
            $due = date('Y-m-d H:i:s', time() + ($retryMinutes * 60));
            $this->tasks->create(
                (int)$admin['episode_id'],
                (int)$admin['pid'],
                isset($admin['eid']) && is_numeric((string)$admin['eid']) ? (int)$admin['eid'] : null,
                (int)$admin['facility_id'],
                'MAR_RETRY_DOSE',
                $due,
                $userId,
                json_encode([
                    'source' => 'MAR',
                    'admin_id' => $adminId,
                    'mar_order_id' => (int)($admin['mar_order_id'] ?? 0),
                    'drug_name' => $drugName,
                    'dose' => $dose,
                    'unit' => $unit,
                    'route' => $route,
                    'task_label' => 'Retry medication',
                    'detail' => $holdLabel !== '' ? $holdLabel : $outcome,
                ], JSON_UNESCAPED_SLASHES)
            );
        }

        if ($outcome === 'REFUSED' && empty($followUp['provider_notified'])) {
            $this->tasks->create(
                (int)$admin['episode_id'],
                (int)$admin['pid'],
                isset($admin['eid']) && is_numeric((string)$admin['eid']) ? (int)$admin['eid'] : null,
                (int)$admin['facility_id'],
                'MAR_EXCEPTION_REVIEW',
                date('Y-m-d H:i:s'),
                $userId,
                json_encode([
                    'source' => 'MAR',
                    'admin_id' => $adminId,
                    'mar_order_id' => (int)($admin['mar_order_id'] ?? 0),
                    'drug_name' => $drugName,
                    'dose' => $dose,
                    'unit' => $unit,
                    'route' => $route,
                    'task_label' => 'Review refused medication',
                    'detail' => 'Provider follow-up may be needed',
                ], JSON_UNESCAPED_SLASHES)
            );
        }
    }

    private function isExceptionOutcome(string $outcome): bool
    {
        return in_array($outcome, ['HELD', 'REFUSED', 'NOT_AVAILABLE', 'MISSED'], true);
    }

    // -------------------------------------------------- internal slot builder

    /**
     * Generate PENDING administration slots from $startDatetime to
     * $startDatetime + $windowHours based on the frequency string.
     */
    private function generateScheduledSlots(
        int $orderId,
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        string $frequency,
        string $startDatetime,
        int $windowHours,
        bool $isHighAlertOverride = false
    ): void {
        $freqKey = strtoupper(trim($frequency));
        if (!isset(self::FREQUENCY_MINUTES[$freqKey])) {
            // Unknown frequency — skip auto-scheduling; nurses document manually
            return;
        }

        $intervalSecs = self::FREQUENCY_MINUTES[$freqKey] * 60;
        $startTs      = strtotime($startDatetime) ?: time();
        $endTs        = $startTs + ($windowHours * 3600);
        $isHighAlert  = $isHighAlertOverride || $this->isHighAlert($drugName);

        $t = $startTs + $intervalSecs; // first dose is one interval after order time
        while ($t <= $endTs) {
            $this->admins->createScheduled(
                $orderId, $episodeId, $pid, $facilityId,
                date('Y-m-d H:i:s', $t),
                $isHighAlert
            );
            $t += $intervalSecs;
        }
    }

    public function isHighAlert(string $drugName): bool
    {
        $lower = strtolower($drugName);
        foreach (self::HIGH_ALERT_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Record a second-nurse co-signature on a high-alert administration.
     * Delegates to the repository which enforces the GIVEN+high_alert guard.
     */
    public function coSignAdministration(int $adminId, int $coSignUserId): void
    {
        $this->admins->coSign($adminId, $coSignUserId);
    }
}




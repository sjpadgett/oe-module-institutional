<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Mar\Service;

use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarOrderRepository;
use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarAdministrationRepository;

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

    public function __construct(
        private readonly MarOrderRepository $orders,
        private readonly MarAdministrationRepository $admins
    ) {}

    // ----------------------------------------------------------------- orders

    /**
     * Place a new medication order and immediately generate scheduled slots
     * for the supplied episode window (episodeStartDatetime → + windowHours).
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
        ?int $orderedByUserId,
        ?string $instructions,
        string $episodeStartDatetime,
        int $windowHours = 24
    ): int {
        $now = date('Y-m-d H:i:s');

        $orderId = $this->orders->create(
            $episodeId, $pid, $facilityId,
            $drugName, $dose, $unit, $route, $frequency,
            $isPrn, $now, $orderedByUserId,
            null, $instructions
        );

        if ($orderId > 0 && !$isPrn) {
            $this->generateScheduledSlots(
                $orderId, $episodeId, $pid, $facilityId,
                $drugName, $frequency, $now, $windowHours
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

    // ----------------------------------------------- administration recording

    /**
     * Record a nurse's administration of a scheduled slot.
     */
    public function recordAdministration(
        int $adminId,
        string $outcome,
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?string $lotNumber,
        ?int $nurseUserId,
        ?string $note
    ): void {
        $administeredDatetime = in_array($outcome, ['GIVEN'], true) ? date('Y-m-d H:i:s') : null;
        $this->admins->record(
            $adminId, $outcome, $administeredDatetime,
            $doseGiven, $unitGiven, $routeGiven,
            $site, $lotNumber, $nurseUserId, $note
        );
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
        ?string $doseGiven,
        ?string $unitGiven,
        ?string $routeGiven,
        ?string $site,
        ?int $nurseUserId,
        ?string $note
    ): void {
        $isHighAlert = $this->isHighAlert($drugName);
        $adminId = $this->admins->createPrn($marOrderId, $episodeId, $pid, $facilityId, $isHighAlert);
        if ($adminId > 0) {
            $this->admins->record(
                $adminId, 'GIVEN', date('Y-m-d H:i:s'),
                $doseGiven, $unitGiven, $routeGiven,
                $site, null, $nurseUserId, $note
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
        $orders = $this->orders->listActiveByEpisode($episodeId);
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

    // -------------------------------------------------- internal slot builder

    /**
     * Generate PENDING administration slots from now until now + windowHours
     * based on the frequency string.
     */
    private function generateScheduledSlots(
        int $orderId,
        int $episodeId,
        int $pid,
        int $facilityId,
        string $drugName,
        string $frequency,
        string $startDatetime,
        int $windowHours
    ): void {
        $freqKey = strtoupper(trim($frequency));
        if (!isset(self::FREQUENCY_MINUTES[$freqKey])) {
            // Unknown frequency — skip auto-scheduling; nurses document manually
            return;
        }

        $intervalSecs = self::FREQUENCY_MINUTES[$freqKey] * 60;
        $startTs      = strtotime($startDatetime) ?: time();
        $endTs        = $startTs + ($windowHours * 3600);
        $isHighAlert  = $this->isHighAlert($drugName);

        $t = $startTs + $intervalSecs; // first dose is interval after order
        while ($t <= $endTs) {
            $this->admins->createScheduled(
                $orderId, $episodeId, $pid, $facilityId,
                date('Y-m-d H:i:s', $t),
                $isHighAlert
            );
            $t += $intervalSecs;
        }
    }

    private function isHighAlert(string $drugName): bool
    {
        $lower = strtolower($drugName);
        foreach (self::HIGH_ALERT_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }
}

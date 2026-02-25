<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\Diversion\Service;

use OpenEMR\Modules\Institutional\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service\AdtNotificationService;

/**
 * DiversionService
 *
 * Orchestrates diversion status changes.
 *
 * On any status transition:
 *   - Writes to oei_diversion (current) + oei_diversion_history (audit)
 *   - Fires ADT A09 to downstream HL7 listeners when enabled
 *
 * A09 semantics here:
 *   "Cancel Patient Departing" is repurposed as a facility-level
 *   diversion notification — the facility context is carried in the
 *   MSH sending-facility field and EVN reason code.  This is the
 *   accepted community practice for facilities that do not implement
 *   a dedicated Z-segment diversion feed.
 *
 * Valid status values:
 *   OPEN       — accepting all patients normally
 *   DIVERSION  — on full diversion; redirect incoming patients
 *   LIMITED    — accepting with capacity constraints (note in reason)
 *   BYPASS     — trauma/specialty bypass; same as DIVERSION for most ED intents
 *
 * Valid service lines:
 *   ED, ICU, OBS, PSYCH, TRAUMA, PEDS, BURN
 */
final class DiversionService
{
    private const VALID_STATUS = ['OPEN', 'DIVERSION', 'LIMITED', 'BYPASS'];
    private const VALID_LINES  = ['ED', 'ICU', 'OBS', 'PSYCH', 'TRAUMA', 'PEDS', 'BURN'];

    public function __construct(
        private readonly DiversionRepository $repo,
        private readonly AdtNotificationService $adt
    ) {}

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Set diversion status for a facility / service line.
     * Fires ADT A09 when status changes.
     *
     * @throws \InvalidArgumentException  For unrecognised status or service line.
     */
    public function setStatus(
        int $facilityId,
        string $serviceLine,
        string $status,
        ?string $reason,
        ?int $userId
    ): void {
        $serviceLine = strtoupper(trim($serviceLine));
        $status      = strtoupper(trim($status));

        if (!in_array($status, self::VALID_STATUS, true)) {
            throw new \InvalidArgumentException("Unknown diversion status: {$status}");
        }
        if (!in_array($serviceLine, self::VALID_LINES, true)) {
            throw new \InvalidArgumentException("Unknown service line: {$serviceLine}");
        }

        $now   = date('Y-m-d H:i:s');
        $start = ($status !== 'OPEN') ? $now : null;
        $end   = ($status === 'OPEN') ? $now : null;

        $prevStatus = $this->repo->upsert(
            $facilityId, $serviceLine, $status, $reason, $start, $end, $userId
        );

        // Fire A09 only when status actually changed
        if ($prevStatus !== $status) {
            $this->fireAdtA09($facilityId, $serviceLine, $status, $reason, $userId);
        }
    }

    /**
     * Convenience: lift diversion (set to OPEN) for a service line.
     */
    public function liftDiversion(int $facilityId, string $serviceLine, ?int $userId): void
    {
        $this->setStatus($facilityId, $serviceLine, 'OPEN', null, $userId);
    }

    /**
     * Get the current diversion state for a facility across all service lines.
     * Returns an associative array keyed by service_line.
     *
     * @return array<string,array<string,mixed>>
     */
    public function getStatusMap(int $facilityId): array
    {
        $rows = $this->repo->listByFacility($facilityId);
        $map  = [];
        foreach ($rows as $row) {
            $map[(string)$row['service_line']] = $row;
        }
        return $map;
    }

    /**
     * Summary badge for a facility suitable for ED Board / directory display.
     * Returns the worst-case status across all service lines.
     *
     * Priority: DIVERSION > BYPASS > LIMITED > OPEN
     */
    public function worstStatus(int $facilityId): string
    {
        $rows = $this->repo->listByFacility($facilityId);
        if (empty($rows)) {
            return 'OPEN';
        }
        $priority = ['OPEN' => 0, 'LIMITED' => 1, 'BYPASS' => 2, 'DIVERSION' => 3];
        $worst    = 'OPEN';
        foreach ($rows as $row) {
            $s = (string)($row['status'] ?? 'OPEN');
            if (($priority[$s] ?? 0) > ($priority[$worst] ?? 0)) {
                $worst = $s;
            }
        }
        return $worst;
    }

    // ── Internal ─────────────────────────────────────────────────────────────

    private function fireAdtA09(
        int $facilityId,
        string $serviceLine,
        string $status,
        ?string $reason,
        ?int $userId
    ): void {
        // Build a synthetic "episode" carrying facility context for the A09.
        // No real episode ID — this is a facility-level notification.
        $syntheticEpisode = [
            'id'              => 0,
            'pid'             => 0,
            'facility_id'     => $facilityId,
            'type'            => $serviceLine,
            'start_datetime'  => date('Y-m-d H:i:s'),
            'chief_complaint' => ($status === 'OPEN')
                ? "Diversion lifted: {$serviceLine}"
                : "Diversion activated: {$serviceLine} — {$reason}",
            'acuity_esi'      => null,
            'disposition'     => null,
        ];

        // A09 — notifies downstream systems to cancel pending transfers to this facility
        $this->adt->notifyDiversion($syntheticEpisode, $facilityId);
    }
}

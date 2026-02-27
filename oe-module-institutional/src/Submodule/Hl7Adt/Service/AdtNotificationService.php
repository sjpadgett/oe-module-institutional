<?php

namespace OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service;

use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Builder\AdtMessageBuilder;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Repository\Hl7OutboundLogRepository;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Transport\HttpTransport;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Transport\MllpTransport;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

/**
 * Orchestrates ADT message generation and delivery.
 *
 * Designed to be called fire-and-forget from existing service hooks.
 * Errors are caught, logged, and never bubble up to the caller —
 * a failed HL7 send must never block clinical workflow.
 *
 * Configuration (via oei_settings):
 *   hl7_enabled            1|0
 *   hl7_transport          MLLP|HTTP
 *   hl7_mllp_host          hostname or IP
 *   hl7_mllp_port          default 2575
 *   hl7_http_url           full URL for HTTP transport
 *   hl7_http_bearer        optional Bearer token
 *   hl7_sending_app        MSH.3 default OE-INSTITUTIONAL
 *   hl7_sending_facility   MSH.4 default OPENEMR
 *   hl7_receiving_app      MSH.5
 *   hl7_receiving_facility MSH.6
 *   hl7_processing_id      P|T  (production|test)
 */
final class AdtNotificationService
{
    private SettingsRepository      $settings;
    private Hl7OutboundLogRepository $log;

    public function __construct(
        SettingsRepository       $settings,
        Hl7OutboundLogRepository $log
    ) {
        $this->settings = $settings;
        $this->log      = $log;
    }

    // -----------------------------------------------------------------------
    // Public event triggers — call from existing service hooks
    // -----------------------------------------------------------------------

    /** A04 — patient registered / arrived. Call from IntakeService / EpisodeRepository::createArrival(). */
    public function notifyArrival(array $episode, int $facilityId): void
    {
        $this->dispatch('A04', $episode, $facilityId);
    }

    /** A01 — patient admitted to observation. Call from ObsService::startObs(). */
    public function notifyAdmit(array $episode, int $facilityId): void
    {
        $this->dispatch('A01', $episode, $facilityId);
    }

    /** A02 — patient location changed. Call from AdtService::assignLocation(). */
    public function notifyTransfer(array $episode, int $facilityId, ?array $location = null): void
    {
        $this->dispatch('A02', $episode, $facilityId, $location);
    }

    /** A08 — patient info / status updated. Call from EpisodeRepository::appendStatusHistory(). */
    public function notifyUpdate(array $episode, int $facilityId): void
    {
        $this->dispatch('A08', $episode, $facilityId);
    }

    /** A03 — patient discharged / episode closed. Call from EpisodeRepository::closeWithDisposition(). */
    public function notifyDischarge(array $episode, int $facilityId): void
    {
        $this->dispatch('A03', $episode, $facilityId);
    }

    // -----------------------------------------------------------------------
    // Core dispatcher
    // -----------------------------------------------------------------------

    private function dispatch(
        string $eventCode,
        array  $episode,
        int    $facilityId,
        ?array $location = null
    ): void {
        // Enabled check — fail silent and fast
        if ($this->settings->get($facilityId, 'hl7_enabled') !== '1') {
            return;
        }

        $pid = (int)($episode['pid'] ?? 0);
        $eid = (int)($episode['id'] ?? 0);

        // A09 (diversion) is facility-level — patient context is not required
        $requiresPatient = ($eventCode !== 'A09');

        if ($requiresPatient && ($pid <= 0 || $eid <= 0)) {
            return;
        }

        try {
            $patient = [];
            if ($requiresPatient) {
                $patient = $this->fetchPatient($pid);
                if ($patient === null) {
                    return;
                }
            }

            $builder = $this->makeBuilder($facilityId);
            $message = match ($eventCode) {
                'A01' => $builder->buildA01($episode, $patient, $location),
                'A02' => $builder->buildA02($episode, $patient, $location),
                'A03' => $builder->buildA03($episode, $patient, $location),
                'A04' => $builder->buildA04($episode, $patient, $location),
                'A08' => $builder->buildA08($episode, $patient, $location),
                'A09' => $builder->buildA09($episode, $patient),
                default => throw new \InvalidArgumentException("Unknown event code: {$eventCode}"),
            };

            $this->sendAndLog($eventCode, $message, $episode, $facilityId);

        } catch (\Throwable $e) {
            // Log the failure without rethrowing — never block clinical workflow
            $this->log->record(
                $eid, $pid, $facilityId,
                $eventCode,
                'INTERNAL', 'N/A',
                '',
                'ERROR', null,
                substr($e->getMessage(), 0, 1000)
            );
        }
    }

    private function sendAndLog(
        string $eventCode,
        string $message,
        array  $episode,
        int    $facilityId
    ): void {
        $pid       = (int)($episode['pid'] ?? 0);
        $eid       = (int)($episode['id'] ?? 0);
        $transport = strtoupper($this->settings->get($facilityId, 'hl7_transport') ?: 'MLLP');

        $status   = 'ERROR';
        $ack      = null;
        $errMsg   = null;
        $endpoint = '';

        try {
            if ($transport === 'HTTP') {
                $url       = $this->settings->get($facilityId, 'hl7_http_url');
                $bearer    = $this->settings->get($facilityId, 'hl7_http_bearer') ?: null;
                $endpoint  = $url;
                $t         = new HttpTransport($url, 10, [], $bearer ?: null);
                $ack       = $t->send($message);
                $status    = 'SENT';

            } else {
                // Default: MLLP
                $host      = $this->settings->get($facilityId, 'hl7_mllp_host') ?: '127.0.0.1';
                $port      = (int)($this->settings->get($facilityId, 'hl7_mllp_port') ?: '2575');
                $endpoint  = "{$host}:{$port}";
                $t         = new MllpTransport($host, $port, 5);
                $ack       = $t->send($message);
                $status    = MllpTransport::isAcknowledged($ack) ? 'SENT' : 'NACK';
                if ($status === 'NACK') {
                    $errMsg = substr($ack, 0, 500);
                }
            }

        } catch (\Throwable $e) {
            $status = 'ERROR';
            $errMsg = substr($e->getMessage(), 0, 1000);
        }

        $this->log->record(
            $eid, $pid, $facilityId,
            $eventCode, $transport, $endpoint,
            $message, $status, $ack, $errMsg
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeBuilder(int $facilityId): AdtMessageBuilder
    {
        return new AdtMessageBuilder(
            $this->settings->get($facilityId, 'hl7_sending_app')       ?: 'OE-INSTITUTIONAL',
            $this->settings->get($facilityId, 'hl7_sending_facility')   ?: 'OPENEMR',
            $this->settings->get($facilityId, 'hl7_receiving_app')      ?: '',
            $this->settings->get($facilityId, 'hl7_receiving_facility') ?: '',
            $this->settings->get($facilityId, 'hl7_processing_id')      ?: 'P'
        );
    }

    /**
     * A09 — facility diversion notification.
     * Call from DiversionService::fireAdtA09().
     *
     * Unlike other ADT events this is facility-level, not episode-level.
     * The $episode array may be synthetic (id=0, pid=0).
     *
     * @param array<string,mixed> $episode  Synthetic or real episode
     */
    public function notifyDiversion(array $episode, int $facilityId): void
    {
        $this->dispatch('A09', $episode, $facilityId);
    }

    /** @return array<string,mixed>|null */
    private function fetchPatient(int $pid): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT pid, fname, lname, DOB, sex, ss, phone_home, phone_cell,
                    street, city, state, postal_code
             FROM patient_data
             WHERE pid = ? LIMIT 1",
            [$pid]
        );
        return $row ?: null;
    }
}

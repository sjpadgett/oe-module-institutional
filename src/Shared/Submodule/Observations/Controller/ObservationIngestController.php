<?php

/**
 * src/Shared/Submodule/Observations/Controller/ObservationIngestController.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Controller;

use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

/**
 * ObservationIngestController
 *
 * Accepts external observation data and writes it to oei_observation.
 *
 * Supported input formats:
 *
 *   1. FHIR R4 Observation bundle (application/fhir+json)
 *      POST body: FHIR Bundle containing Observation resources.
 *      Maps Observation.code.coding[0].code → obs_type_code (via LOINC lookup).
 *      Uses Observation.id as fhir_id for idempotent re-import.
 *
 *   2. Simplified batch JSON (application/json — module-native format)
 *      POST body: { "observations": [ { episode_id, pid, facility_id,
 *        obs_type_code, observed_at, value_numeric, value_text, unit,
 *        source_type, device_id } ] }
 *      No FHIR mapping needed. Faster for device bridges that already
 *      know the module's obs_type codes.
 *
 * Authentication: validates OpenEMR session (authUserID in $_SESSION)
 * OR a facility API key in the X-OEI-API-Key header stored in oei_settings.
 * Endpoint is called from the API page which handles auth before delegation.
 *
 * Returns JSON: { ok, processed, failed, errors[] }
 */
final class ObservationIngestController
{
    private SharedObservationRepository $repo;

    public function __construct(?SharedObservationRepository $repo = null)
    {
        $this->repo = $repo ?? new SharedObservationRepository();
    }

    /**
     * Handle an ingest request.
     *
     * @param  string  $body         Raw POST body
     * @param  string  $contentType  Value of Content-Type header
     * @param  int     $facilityId   Resolved facility for this request
     * @param  int     $userId       Authenticated user (0 = API key auth)
     * @return array{ok: bool, processed: int, failed: int, errors: string[]}
     */
    public function handle(
        string $body,
        string $contentType,
        int    $facilityId,
        int    $userId
    ): array {
        $body = trim($body);
        if ($body === '') {
            return $this->err('Empty request body');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $this->err('Invalid JSON: ' . json_last_error_msg());
        }

        // Route by content type or payload shape
        if (str_contains($contentType, 'fhir+json') ||
            (isset($decoded['resourceType']) && $decoded['resourceType'] === 'Bundle')) {
            return $this->ingestFhirBundle($decoded, $facilityId, $userId);
        }

        if (isset($decoded['observations']) && is_array($decoded['observations'])) {
            return $this->ingestBatch($decoded['observations'], $facilityId, $userId);
        }

        // Single simplified observation
        if (isset($decoded['obs_type_code']) || isset($decoded['episode_id'])) {
            return $this->ingestBatch([$decoded], $facilityId, $userId);
        }

        return $this->err('Unrecognised payload shape. Expected FHIR Bundle, {observations:[...]}, or single observation object.');
    }

    // ── FHIR R4 Bundle ingest ─────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $bundle
     * @return array{ok: bool, processed: int, failed: int, errors: string[]}
     */
    private function ingestFhirBundle(array $bundle, int $facilityId, int $userId): array
    {
        $entries = $bundle['entry'] ?? [];
        if (!is_array($entries) || count($entries) === 0) {
            // Could be a single Observation resource, not a Bundle
            if (($bundle['resourceType'] ?? '') === 'Observation') {
                $entries = [['resource' => $bundle]];
            } else {
                return $this->err('FHIR Bundle has no entries');
            }
        }

        $rows   = [];
        $errors = [];

        // Pre-load LOINC → code map once
        $loincMap = $this->buildLoincMap();

        foreach ($entries as $idx => $entry) {
            $res = $entry['resource'] ?? $entry;
            if (($res['resourceType'] ?? '') !== 'Observation') {
                continue;
            }

            try {
                $row = $this->mapFhirObservation($res, $loincMap, $facilityId, $userId);
                if ($row) {
                    $rows[] = $row;
                } else {
                    $errors[] = "Entry {$idx}: could not map to known obs_type_code";
                }
            } catch (\Throwable $e) {
                $errors[] = "Entry {$idx}: " . $e->getMessage();
            }
        }

        if (count($rows) === 0) {
            return ['ok' => false, 'processed' => 0, 'failed' => count($errors), 'errors' => $errors];
        }

        $result = $this->repo->recordBatch($rows, 'FHIR');
        return [
            'ok'        => $result['failed'] === 0,
            'processed' => $result['processed'],
            'failed'    => $result['failed'] + count($errors),
            'errors'    => $errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $obs  FHIR Observation resource
     * @param  array<string, string> $loincMap
     * @return array<string, mixed>|null
     */
    private function mapFhirObservation(
        array $obs,
        array $loincMap,
        int   $facilityId,
        int   $userId
    ): ?array {
        // Resolve obs_type_code from LOINC
        $obsTypeCode = null;
        foreach ($obs['code']['coding'] ?? [] as $coding) {
            $system = strtolower((string)($coding['system'] ?? ''));
            $code   = (string)($coding['code'] ?? '');
            if ((str_contains($system, 'loinc') || $system === '') && isset($loincMap[$code])) {
                $obsTypeCode = $loincMap[$code];
                break;
            }
        }

        if ($obsTypeCode === null) {
            return null;
        }

        // Resolve episode/pid from subject reference
        $episodeId = 0;
        $pid       = 0;
        $subjectRef = (string)($obs['subject']['reference'] ?? '');
        // Format: "Patient/123" or "Episode/456"
        if (str_starts_with($subjectRef, 'Patient/')) {
            $pid = (int)substr($subjectRef, 8);
        }
        $contextRef = (string)($obs['context']['reference'] ?? $obs['encounter']['reference'] ?? '');
        if (str_starts_with($contextRef, 'Episode/')) {
            $episodeId = (int)substr($contextRef, 8);
        }

        if ($episodeId <= 0 && $pid <= 0) {
            return null;  // can't write without some patient anchor
        }

        // Observed datetime
        $observedAt = (string)($obs['effectiveDateTime']
            ?? $obs['effectivePeriod']['start']
            ?? $obs['issued']
            ?? date('Y-m-d H:i:s'));
        // Normalise ISO8601 to MySQL datetime
        $observedAt = preg_replace('/T/', ' ', $observedAt);
        $observedAt = preg_replace('/\+\d{2}:\d{2}$|Z$/', '', $observedAt);
        $observedAt = substr($observedAt, 0, 19);

        // Value
        $valueNumeric = null;
        $valueText    = null;
        $unit         = null;

        if (isset($obs['valueQuantity'])) {
            $valueNumeric = (float)$obs['valueQuantity']['value'];
            $unit         = (string)($obs['valueQuantity']['unit'] ?? '');
        } elseif (isset($obs['valueCodeableConcept']['text'])) {
            $valueText = (string)$obs['valueCodeableConcept']['text'];
        } elseif (isset($obs['valueString'])) {
            $valueText = (string)$obs['valueString'];
        }

        $deviceId = null;
        if (isset($obs['device']['reference'])) {
            $deviceId = (string)$obs['device']['reference'];
        }

        return [
            'episode_id'   => $episodeId,
            'pid'          => $pid,
            'facility_id'  => $facilityId,
            'obs_type_code'=> $obsTypeCode,
            'observed_at'  => $observedAt,
            'value_numeric'=> $valueNumeric,
            'value_text'   => $valueText,
            'unit'         => $unit,
            'source_type'  => 'FHIR',
            'device_id'    => $deviceId,
            'fhir_id'      => (string)($obs['id'] ?? ''),
            'user_id'      => $userId,
        ];
    }

    /**
     * Build LOINC code → obs_type_code lookup from oei_obs_type.
     *
     * @return array<string, string>  loinc_code → obs_type code
     */
    private function buildLoincMap(): array
    {
        $types = $this->repo->listTypes();
        $map   = [];
        foreach ($types as $code => $type) {
            if ($type['loinc_code'] !== null) {
                $map[(string)$type['loinc_code']] = $code;
            }
        }
        return $map;
    }

    // ── Simplified batch JSON ingest ──────────────────────────────────────

    /**
     * @param  array<int, array<string, mixed>> $observations
     * @return array{ok: bool, processed: int, failed: int, errors: string[]}
     */
    private function ingestBatch(
        array $observations,
        int   $facilityId,
        int   $userId
    ): array {
        $rows   = [];
        $errors = [];

        foreach ($observations as $idx => $obs) {
            $typeCode  = trim((string)($obs['obs_type_code'] ?? ''));
            $episodeId = (int)($obs['episode_id'] ?? 0);
            $pid       = (int)($obs['pid']        ?? 0);

            if ($typeCode === '') {
                $errors[] = "Row {$idx}: obs_type_code is required";
                continue;
            }
            if ($episodeId <= 0 && $pid <= 0) {
                $errors[] = "Row {$idx}: episode_id or pid is required";
                continue;
            }

            $rows[] = [
                'episode_id'   => $episodeId,
                'pid'          => $pid,
                'facility_id'  => (int)($obs['facility_id'] ?? $facilityId),
                'obs_type_code'=> $typeCode,
                'observed_at'  => (string)($obs['observed_at'] ?? date('Y-m-d H:i:s')),
                'value_numeric'=> isset($obs['value_numeric']) ? (float)$obs['value_numeric'] : null,
                'value_text'   => isset($obs['value_text'])    ? (string)$obs['value_text']   : null,
                'unit'         => isset($obs['unit'])          ? (string)$obs['unit']         : null,
                'source_type'  => (string)($obs['source_type'] ?? 'IMPORT'),
                'device_id'    => isset($obs['device_id'])     ? (string)$obs['device_id']   : null,
                'fhir_id'      => isset($obs['fhir_id'])       ? (string)$obs['fhir_id']     : null,
                'user_id'      => $userId,
            ];
        }

        if (count($rows) === 0) {
            return ['ok' => false, 'processed' => 0, 'failed' => count($errors), 'errors' => $errors];
        }

        $result = $this->repo->recordBatch($rows, 'IMPORT');
        return [
            'ok'        => $result['failed'] === 0 && count($errors) === 0,
            'processed' => $result['processed'],
            'failed'    => $result['failed'] + count($errors),
            'errors'    => $errors,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /** @return array{ok: bool, processed: int, failed: int, errors: string[]} */
    private function err(string $msg): array
    {
        return ['ok' => false, 'processed' => 0, 'failed' => 0, 'errors' => [$msg]];
    }
}




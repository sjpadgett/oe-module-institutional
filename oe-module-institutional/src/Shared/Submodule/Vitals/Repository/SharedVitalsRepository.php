<?php

/**
 * src/Shared/Submodule/Vitals/Repository/SharedVitalsRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Repository;

use OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository;

/**
 * SharedVitalsRepository
 *
 * Canonical read/write path for periodic vitals across all tracks
 * (AL, IP, HBC). Wraps TriageRepository — oei_triage is the storage table.
 *
 * Differences from AlVitalsRepository:
 *   - GCS is a first-class field (not hardcoded null)
 *   - arrivalMode is injected per call, not hardcoded to 'PERIODIC'
 *   - listForEpisode() includes gcs and noted_by in the hydrated row
 *
 * AlVitalsRepository is updated to delegate here so existing callers
 * (IpVitalsController etc.) continue to work without changes.
 */
final class SharedVitalsRepository
{
    private TriageRepository $triage;

    public function __construct()
    {
        $this->triage = new TriageRepository();
    }

    /**
     * Record a vitals set. Returns new row id (0 on failure).
     *
     * @param int|null    $gcs         Null for tracks where GCS is not collected (AL, HBC)
     * @param string      $arrivalMode 'PERIODIC' for monitoring contexts
     */
    public function record(
        int    $episodeId,
        int    $pid,
        int    $facilityId,
        ?int   $bpSystolic,
        ?int   $bpDiastolic,
        ?int   $hr,
        ?int   $rr,
        ?float $tempF,
        ?int   $spo2,
        ?float $weightKg,
        ?int   $painScore,
        ?int   $gcs,
        string $arrivalMode,
        ?string $notes,
        ?int   $userId
    ): int {
        return $this->triage->record(
            episodeId:   $episodeId,
            pid:         $pid,
            eid:         null,
            facilityId:  $facilityId,
            bpSystolic:  $bpSystolic,
            bpDiastolic: $bpDiastolic,
            hr:          $hr,
            rr:          $rr,
            tempF:       $tempF,
            spo2:        $spo2,
            gcs:         $gcs,
            painScore:   $painScore,
            weightKg:    $weightKg,
            arrivalMode: $arrivalMode,
            notes:       $notes,
            userId:      $userId
        );
    }

    /**
     * All vitals for an episode, newest first, with noted_by name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForEpisode(int $episodeId, int $limit = 20): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT t.*,
                    CONCAT(COALESCE(u.fname,''), ' ', COALESCE(u.lname,'')) AS noted_by
             FROM   oei_triage t
             LEFT   JOIN users u ON u.id = t.noted_by_user_id
                                AND u.active = 1 AND u.fname IS NOT NULL
             WHERE  t.episode_id = ?
             ORDER  BY t.noted_datetime DESC, t.id DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = $this->hydrateRow($r);
        }
        return $rows;
    }

    /**
     * Latest single vitals row for an episode.
     *
     * @return array<string, mixed>|null
     */
    public function getLatest(int $episodeId): ?array
    {
        $row = $this->triage->getLatestForEpisode($episodeId);
        return $row ? $this->hydrateRow($row) : null;
    }

    /**
     * Weight readings (non-null), oldest first, for trend chart.
     *
     * @return float[]
     */
    public function weightTrend(int $episodeId, int $limit = 14): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT weight_kg
             FROM   oei_triage
             WHERE  episode_id = ? AND weight_kg IS NOT NULL
             ORDER  BY noted_datetime DESC, id DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = (float)$r['weight_kg'];
        }
        return array_reverse($rows);  // oldest first for chart
    }

    /**
     * Recent N sets (oldest first) for sparkline display.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recentSets(int $episodeId, int $limit = 10): array
    {
        return $this->triage->recentSets($episodeId, $limit);
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function hydrateRow(array $r): array
    {
        return [
            'id'             => (int)$r['id'],
            'set_number'     => (int)($r['set_number'] ?? 0),
            'bp_systolic'    => isset($r['bp_systolic'])  ? (int)$r['bp_systolic']   : null,
            'bp_diastolic'   => isset($r['bp_diastolic']) ? (int)$r['bp_diastolic']  : null,
            'hr'             => isset($r['hr'])            ? (int)$r['hr']            : null,
            'rr'             => isset($r['rr'])            ? (int)$r['rr']            : null,
            'temp_f'         => isset($r['temp_f'])        ? (float)$r['temp_f']      : null,
            'spo2'           => isset($r['spo2'])          ? (int)$r['spo2']          : null,
            'weight_kg'      => isset($r['weight_kg'])     ? (float)$r['weight_kg']   : null,
            'pain_score'     => isset($r['pain_score'])    ? (int)$r['pain_score']    : null,
            'gcs'            => isset($r['gcs'])           ? (int)$r['gcs']           : null,
            'arrival_mode'   => (string)($r['arrival_mode']    ?? ''),
            'notes'          => (string)($r['notes']           ?? ''),
            'noted_datetime' => (string)($r['noted_datetime']  ?? ''),
            'noted_by'       => trim((string)($r['noted_by']   ?? '')),
        ];
    }
}




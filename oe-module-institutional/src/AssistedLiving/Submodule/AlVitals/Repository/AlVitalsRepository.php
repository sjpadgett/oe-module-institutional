<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Repository;

use OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository;

/**
 * AlVitalsRepository
 *
 * Thin wrapper around TriageRepository for AL periodic vitals.
 *
 * AL vitals are stored in oei_triage with arrival_mode = 'PERIODIC'
 * so they are distinguishable from ED triage entries (WALK_IN, EMS, etc.)
 * when queries span multiple episode types.
 *
 * This avoids a separate table while preserving full shared infrastructure:
 *   - TriageService::formatForBoard()
 *   - TriageService::boardSeverity()
 *   - TriageRepository::recentSets() for sparklines
 */
final class AlVitalsRepository
{
    private TriageRepository $triage;

    public function __construct()
    {
        $this->triage = new TriageRepository();
    }

    /**
     * Record a periodic AL vitals check using existing TriageRepository.
     * Returns new row id (0 on failure).
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
        ?string $notes,
        ?int   $userId
    ): int {
        // eid = null for AL (no single encounter per vitals visit)
        // arrivalMode = 'PERIODIC' to flag as AL monitoring record
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
            gcs:         null,        // not used in AL context
            painScore:   $painScore,
            weightKg:    $weightKg,
            arrivalMode: 'PERIODIC',
            notes:       $notes,
            userId:      $userId
        );
    }

    /**
     * All vitals for an episode, newest first.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listForEpisode(int $episodeId, int $limit = 20): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT t.*,
                    CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS noted_by
             FROM   oei_triage t
             LEFT   JOIN users u ON u.id = t.noted_by_user_id
                                AND u.active=1 AND u.fname IS NOT NULL
             WHERE  t.episode_id = ?
             ORDER  BY t.noted_datetime DESC, t.id DESC
             LIMIT  " . (int)$limit,
            [$episodeId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'            => (int)$r['id'],
                'set_number'    => (int)$r['set_number'],
                'bp_systolic'   => $r['bp_systolic']  !== null ? (int)$r['bp_systolic']   : null,
                'bp_diastolic'  => $r['bp_diastolic'] !== null ? (int)$r['bp_diastolic']  : null,
                'hr'            => $r['hr']            !== null ? (int)$r['hr']            : null,
                'rr'            => $r['rr']            !== null ? (int)$r['rr']            : null,
                'temp_f'        => $r['temp_f']        !== null ? (float)$r['temp_f']      : null,
                'spo2'          => $r['spo2']          !== null ? (int)$r['spo2']          : null,
                'weight_kg'     => $r['weight_kg']     !== null ? (float)$r['weight_kg']   : null,
                'pain_score'    => $r['pain_score']    !== null ? (int)$r['pain_score']    : null,
                'arrival_mode'  => (string)($r['arrival_mode'] ?? ''),
                'notes'         => (string)($r['notes'] ?? ''),
                'noted_datetime'=> (string)$r['noted_datetime'],
                'noted_by'      => trim((string)($r['noted_by'] ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Recent N sets for sparklines (oldest first for chart rendering).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentSets(int $episodeId, int $limit = 10): array
    {
        return $this->triage->recentSets($episodeId, $limit);
    }

    /**
     * Latest single vitals row.
     *
     * @return array<string,mixed>|null
     */
    public function getLatest(int $episodeId): ?array
    {
        return $this->triage->getLatestForEpisode($episodeId);
    }

    /**
     * Weight trend: last N weights (non-null), oldest first.
     *
     * @return array<int,float>
     */
    public function weightTrend(int $episodeId, int $limit = 14): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT weight_kg, noted_datetime
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

        return array_reverse($rows); // oldest first for chart
    }
}

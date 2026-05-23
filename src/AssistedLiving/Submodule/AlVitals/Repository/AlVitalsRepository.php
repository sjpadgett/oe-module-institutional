<?php

/**
 * src/AssistedLiving/Submodule/AlVitals/Repository/AlVitalsRepository.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Repository;

use OpenEMR\Modules\Institutional\Shared\Submodule\Triage\Repository\TriageRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Repository\SharedVitalsRepository;

/**
 * AlVitalsRepository
 *
 * Backward-compatibility wrapper. Delegates to SharedVitalsRepository.
 *
 * Prior to the shared vitals layer, this repository contained the write
 * and read logic directly. It is now a thin delegator so that any
 * existing code still referencing AlVitalsRepository (e.g. in tests or
 * team-built extensions) continues to work without modification.
 *
 * AL-specific behaviour preserved:
 *   - arrivalMode is hardcoded to 'PERIODIC'
 *   - gcs is always null (not recorded in AL context)
 *
 * All new code should use SharedVitalsRepository directly.
 */
final class AlVitalsRepository
{
    private SharedVitalsRepository $shared;

    public function __construct()
    {
        $this->shared = new SharedVitalsRepository();
    }

    /**
     * Record a periodic AL vitals check.
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
        return $this->shared->record(
            episodeId:   $episodeId,
            pid:         $pid,
            facilityId:  $facilityId,
            bpSystolic:  $bpSystolic,
            bpDiastolic: $bpDiastolic,
            hr:          $hr,
            rr:          $rr,
            tempF:       $tempF,
            spo2:        $spo2,
            weightKg:    $weightKg,
            painScore:   $painScore,
            gcs:         null,         // not collected in AL
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
        return $this->shared->listForEpisode($episodeId, $limit);
    }

    /**
     * Recent N sets for sparklines (oldest first).
     *
     * @return array<int,array<string,mixed>>
     */
    public function recentSets(int $episodeId, int $limit = 10): array
    {
        return $this->shared->recentSets($episodeId, $limit);
    }

    /**
     * Latest single vitals row.
     *
     * @return array<string,mixed>|null
     */
    public function getLatest(int $episodeId): ?array
    {
        return $this->shared->getLatest($episodeId);
    }

    /**
     * Weight trend: last N weights (non-null), oldest first.
     *
     * @return float[]
     */
    public function weightTrend(int $episodeId, int $limit = 14): array
    {
        return $this->shared->weightTrend($episodeId, $limit);
    }
}




<?php

/**
 * src/Submodule/Triage/Repository/TriageRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Triage\Repository;

final class TriageRepository
{
    /**
     * Insert a new vitals set for an episode.
     * Returns the new row id.
     */
    public function record(
        int $episodeId,
        int $pid,
        ?int $eid,
        int $facilityId,
        ?int $bpSystolic,
        ?int $bpDiastolic,
        ?int $hr,
        ?int $rr,
        ?float $tempF,
        ?int $spo2,
        ?int $gcs,
        ?int $painScore,
        ?float $weightKg,
        ?string $arrivalMode,
        ?string $notes,
        ?int $userId
    ): int {
        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');

        // Derive set number (1st triage, 2nd re-triage, etc.)
        $row = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_triage WHERE episode_id = ?",
            [$episodeId]
        );
        $setNumber = (int)($row['c'] ?? 0) + 1;

        // Compute ESI suggestion from vitals if not already set on episode
        $esiSuggested = $this->suggestEsi($bpSystolic, $bpDiastolic, $hr, $rr, $spo2, $gcs, $painScore);

        sqlStatement(
            "INSERT INTO oei_triage
               (episode_id, pid, eid, facility_id, set_number,
                bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs,
                pain_score, weight_kg, arrival_mode, esi_suggested,
                notes, noted_by_user_id, noted_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $episodeId, $pid, $eid, $facilityId, $setNumber,
                $bpSystolic, $bpDiastolic, $hr, $rr, $tempF, $spo2, $gcs,
                $painScore, $weightKg, $arrivalMode, $esiSuggested,
                $notes, $userId, $now,
            ]
        );

        $idRow = sqlQuery("SELECT LAST_INSERT_ID() AS id");
        return (int)($idRow['id'] ?? 0);
    }

    /**
     * Most recent vitals set for one episode.
     * @return array<string,mixed>|null
     */
    public function getLatestForEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_triage
             WHERE episode_id = ?
             ORDER BY set_number DESC, id DESC
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /**
     * All vitals sets for one episode, oldest first.
     * @return array<int,array<string,mixed>>
     */
    public function listForEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT * FROM oei_triage
             WHERE episode_id = ?
             ORDER BY set_number ASC, id ASC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Most recent vitals for every active episode at a facility.
     * Returns array keyed by episode_id.
     * @return array<int,array<string,mixed>>
     */
    public function latestByFacility(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        // Use a self-join to get only the most recent set per episode
        $res = sqlStatement(
            "SELECT t.*
             FROM oei_triage t
             INNER JOIN (
                 SELECT episode_id, MAX(id) AS max_id
                 FROM oei_triage
                 WHERE facility_id = ?
                 GROUP BY episode_id
             ) latest ON latest.episode_id = t.episode_id AND latest.max_id = t.id",
            [$facilityId]
        );
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $map[(int)$row['episode_id']] = $row;
        }
        return $map;
    }

    /**
     * Vitals trending: last N sets for an episode for sparkline/trend display.
     * @return array<int,array<string,mixed>>
     */
    public function recentSets(int $episodeId, int $limit = 5): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT * FROM oei_triage
             WHERE episode_id = ?
             ORDER BY id DESC
             LIMIT " . (int)$limit,
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return array_reverse($rows); // oldest first for display
    }

    /**
     * Rule-of-thumb ESI suggestion from vitals.
     * ESI 1: GCS ≤ 8 OR SpO2 < 90 OR HR > 150 OR HR < 40 OR SBP < 80
     * ESI 2: SpO2 < 94 OR SBP < 90 OR HR > 130 OR HR < 50 OR GCS 9-12 OR pain 9-10
     * ESI 3: SpO2 94-96 OR abnormal HR/BP without extremes OR pain 7-8
     * ESI 4-5: essentially normal vitals, low pain
     * Returns null if insufficient data.
     */
    private function suggestEsi(
        ?int $sbp, ?int $dbp, ?int $hr, ?int $rr,
        ?int $spo2, ?int $gcs, ?int $pain
    ): ?int {
        if ($gcs !== null && $gcs <= 8) {
            return 1;
        }
        if ($spo2 !== null && $spo2 < 90) {
            return 1;
        }
        if ($hr !== null && ($hr > 150 || $hr < 40)) {
            return 1;
        }
        if ($sbp !== null && $sbp < 80) {
            return 1;
        }

        if ($spo2 !== null && $spo2 < 94) {
            return 2;
        }
        if ($sbp !== null && $sbp < 90) {
            return 2;
        }
        if ($hr !== null && ($hr > 130 || $hr < 50)) {
            return 2;
        }
        if ($gcs !== null && $gcs < 13) {
            return 2;
        }
        if ($pain !== null && $pain >= 9) {
            return 2;
        }

        if (($spo2 !== null && $spo2 < 97) ||
            ($hr !== null && ($hr > 110 || $hr < 55)) ||
            ($sbp !== null && $sbp < 100) ||
            ($pain !== null && $pain >= 7)) {
            return 3;
        }

        if ($pain !== null && $pain >= 4) {
            return 4;
        }

        return null;
    }
}






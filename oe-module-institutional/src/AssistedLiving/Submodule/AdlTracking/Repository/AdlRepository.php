<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Repository;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\AdlLevel;

/**
 * AdlRepository — reads and writes oei_adl_record.
 *
 * One record = one charting session by one aide covering all 7 ADL domains.
 * adl_json stores the domain→level map as compact JSON.
 * adl_score is the precomputed AdlLevel::aggregateScore() for quick board queries.
 */
final class AdlRepository
{
    /**
     * Most recent ADL records for an episode (newest first).
     * @return array<int, array<string,mixed>>
     */
    public function listByEpisode(int $episodeId, int $limit = 10): array
    {
        if (!function_exists('sqlStatement')) { return []; }

        $res = sqlStatement(
            "SELECT r.id, r.episode_id, r.noted_datetime, r.adl_score, r.adl_json,
                    r.notes, CONCAT(u.fname,' ',u.lname) AS noted_by
             FROM   oei_adl_record r
             LEFT   JOIN users u ON u.id = r.noted_by_user_id
                                AND u.active = 1 AND u.username IS NOT NULL AND u.fname IS NOT NULL
             WHERE  r.episode_id = ?
             ORDER  BY r.noted_datetime DESC
             LIMIT  ?",
            [$episodeId, $limit]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $domainLevels = json_decode((string)$r['adl_json'], true) ?: [];
            $rows[] = [
                'id'             => (int)$r['id'],
                'episode_id'     => (int)$r['episode_id'],
                'noted_datetime' => (string)$r['noted_datetime'],
                'adl_score'      => (int)$r['adl_score'],
                'domain_levels'  => $domainLevels,
                'notes'          => (string)($r['notes'] ?? ''),
                'noted_by'       => trim((string)$r['noted_by']),
            ];
        }
        return $rows;
    }

    /**
     * Chart a new ADL session.
     * @param array<string,int> $domainLevels  domain → AdlLevel constant
     */
    public function chart(int $episodeId, int $facilityId, int $userId, array $domainLevels, string $notes): int
    {
        if (!function_exists('sqlInsert')) { return 0; }

        // Validate domain keys
        $valid = [];
        foreach (AdlLevel::validDomains() as $domain) {
            $valid[$domain] = isset($domainLevels[$domain]) ? (int)$domainLevels[$domain] : AdlLevel::DID_NOT_OCCUR;
        }

        $score = AdlLevel::aggregateScore($valid);

        $id = sqlInsert(
            "INSERT INTO oei_adl_record
                (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
             VALUES (?,?,?,NOW(),?,?,?)",
            [$episodeId, $facilityId, $userId, json_encode($valid), $score, $notes]
        );

        // Update the AL episode overlay with the latest score for the board
        sqlStatement(
            "UPDATE oei_al_episode SET last_adl_score = ?, last_adl_datetime = NOW()
             WHERE episode_id = ?",
            [$score, $episodeId]
        );

        return (int)$id;
    }

    /** Facility-wide ADL records requiring charting (no record in last 8h). */
    public function fetchOverdueEpisodes(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) { return []; }

        $res = sqlStatement(
            "SELECT e.id AS episode_id, pd.fname, pd.lname, ale.room, ale.unit,
                    MAX(r.noted_datetime) AS last_charted
             FROM   oei_episode e
             INNER  JOIN patient_data pd ON pd.pid = e.pid
             LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
             LEFT   JOIN oei_adl_record r ON r.episode_id = e.id
             WHERE  e.facility_id = ? AND e.status = 'ACTIVE' AND e.type = 'AL'
             GROUP  BY e.id, pd.fname, pd.lname, ale.room, ale.unit
             HAVING last_charted IS NULL OR last_charted < DATE_SUB(NOW(), INTERVAL 8 HOUR)
             ORDER  BY last_charted ASC",
            [$facilityId]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'episode_id'   => (int)$r['episode_id'],
                'name'         => trim($r['fname'] . ' ' . $r['lname']),
                'room'         => (string)($r['room'] ?? ''),
                'unit'         => (string)($r['unit'] ?? ''),
                'last_charted' => $r['last_charted'] ?: null,
            ];
        }
        return $rows;
    }
}

<?php

/**
 * src/AssistedLiving/Submodule/AlActivity/Repository/AlActivityRepository.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Repository;

/**
 * AlActivityRepository
 *
 * Data layer for the Activity & Engagement Log submodule.
 *
 * attendance_json shape (keyed by episode_id as string):
 *   { "14": {"level": "FULL", "note": "..."}, "17": {"level": "REFUSED", "note": "..."} }
 *
 * Participation levels:
 *   FULL     — attended full session
 *   PARTIAL  — attended part of session (left early, arrived late)
 *   REFUSED  — offered and declined (document reason in note)
 *   ABSENT   — not available (hospital, room rest, off-site)
 */
final class AlActivityRepository
{
    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * All sessions for a facility on a given date, newest first.
     * @return array<int,array<string,mixed>>
     */
    public function getByDate(int $facilityId, string $date): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            'SELECT a.*,
                    u.fname AS user_fname, u.lname AS user_lname
             FROM   oei_activity_log a
             LEFT   JOIN users u ON u.id = a.led_by_user_id
             WHERE  a.facility_id   = ?
               AND  a.activity_date = ?
             ORDER  BY a.start_time ASC, a.id ASC',
            [$facilityId, $date]
        );
        return $this->fetchAll($res);
    }

    /**
     * Sessions across a date range for a facility.
     * @return array<int,array<string,mixed>>
     */
    public function getByDateRange(int $facilityId, string $from, string $to, int $limit = 200): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            'SELECT a.*,
                    u.fname AS user_fname, u.lname AS user_lname
             FROM   oei_activity_log a
             LEFT   JOIN users u ON u.id = a.led_by_user_id
             WHERE  a.facility_id   = ?
               AND  a.activity_date BETWEEN ? AND ?
             ORDER  BY a.activity_date DESC, a.start_time ASC
             LIMIT  ' . (int)$limit,
            [$facilityId, $from, $to]
        );
        return $this->fetchAll($res);
    }

    /**
     * All sessions a specific resident (episode) attended or was recorded in.
     * @return array<int,array<string,mixed>>
     */
    public function getByEpisode(int $episodeId, int $facilityId, int $limit = 60): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        // JSON_CONTAINS_PATH works in MySQL 5.7+/MariaDB 10.2.3+
        $res = sqlStatement(
            'SELECT a.*,
                    u.fname AS user_fname, u.lname AS user_lname
             FROM   oei_activity_log a
             LEFT   JOIN users u ON u.id = a.led_by_user_id
             WHERE  a.facility_id = ?
               AND  JSON_CONTAINS_PATH(a.attendance_json, \'one\', CONCAT(\'$.\', ?))
             ORDER  BY a.activity_date DESC, a.start_time ASC
             LIMIT  ' . (int)$limit,
            [$facilityId, (string)$episodeId]
        );
        return $this->fetchAll($res);
    }

    /**
     * Count of sessions per activity_type in the last N days (for board sparkline).
     * @return array<string,int>
     */
    public function typeSummary(int $facilityId, int $days = 7): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            'SELECT activity_type, COUNT(*) AS cnt
             FROM   oei_activity_log
             WHERE  facility_id   = ?
               AND  activity_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
             GROUP  BY activity_type
             ORDER  BY cnt DESC',
            [$facilityId, $days]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $out[(string)$row['activity_type']] = (int)$row['cnt'];
        }
        return $out;
    }

    /**
     * Participation rates per episode for the last N days (for profile tab).
     * Returns [episode_id => ['total'=>int, 'full'=>int, 'partial'=>int, 'refused'=>int, 'absent'=>int]]
     *
     * @param int[] $episodeIds
     * @return array<int,array<string,int>>
     */
    public function participationRates(array $episodeIds, int $facilityId, int $days = 30): array
    {
        if (!function_exists('sqlStatement') || empty($episodeIds)) {
            return [];
        }
        // Pull recent sessions and compute in PHP (avoids complex JSON GROUP BY)
        $res = sqlStatement(
            'SELECT id, attendance_json
             FROM   oei_activity_log
             WHERE  facility_id   = ?
               AND  activity_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)',
            [$facilityId, $days]
        );
        $rates = [];
        foreach ($episodeIds as $eid) {
            $rates[$eid] = ['total' => 0, 'full' => 0, 'partial' => 0, 'refused' => 0, 'absent' => 0];
        }

        while ($row = sqlFetchArray($res)) {
            $json = json_decode((string)($row['attendance_json'] ?? '{}'), true) ?? [];
            foreach ($episodeIds as $eid) {
                $key = (string)$eid;
                if (!isset($json[$key])) {
                    continue;
                }
                $level = strtolower((string)($json[$key]['level'] ?? ''));
                $rates[$eid]['total']++;
                if (isset($rates[$eid][$level])) {
                    $rates[$eid][$level]++;
                }
            }
        }
        return $rates;
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    /** Insert a new activity session. Returns the new ID. */
    public function insert(
        int     $facilityId,
        string  $date,
        string  $type,
        string  $name,
        string  $startTime,
        int     $duration,
        ?string $location,
        ?int    $userId,
        ?string $ledByName,
        array   $attendance,   // [episode_id => ['level'=>'FULL','note'=>'...']]
        ?string $notes
    ): int {
        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
            return 0;
        }
        $now         = date('Y-m-d H:i:s');
        $attendJson  = json_encode($this->normaliseAttendance($attendance));
        $count       = $this->countPresent($attendance);

        sqlStatement(
            'INSERT INTO oei_activity_log
               (facility_id, activity_date, activity_type, activity_name,
                start_time, duration_minutes, location, led_by_user_id, led_by_name,
                attendance_json, attendance_count, notes, created_datetime, updated_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$facilityId, $date, strtoupper($type), $name,
             $startTime, $duration, $location, $userId, $ledByName,
             $attendJson, $count, $notes, $now, $now]
        );
        $row = sqlQuery('SELECT LAST_INSERT_ID() AS id');
        return (int)($row['id'] ?? 0);
    }

    /** Update attendance and notes for an existing session. */
    public function updateAttendance(
        int    $sessionId,
        int    $facilityId,
        array  $attendance,
        ?string $notes
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now        = date('Y-m-d H:i:s');
        $attendJson = json_encode($this->normaliseAttendance($attendance));
        $count      = $this->countPresent($attendance);

        sqlStatement(
            'UPDATE oei_activity_log
             SET    attendance_json  = ?,
                    attendance_count = ?,
                    notes            = ?,
                    updated_datetime = ?
             WHERE  id = ? AND facility_id = ?',
            [$attendJson, $count, $notes, $now, $sessionId, $facilityId]
        );
    }

    public function getById(int $id, int $facilityId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            'SELECT * FROM oei_activity_log WHERE id = ? AND facility_id = ? LIMIT 1',
            [$id, $facilityId]
        );
        return $row ?: null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @return array<int,array<string,mixed>> */
    private function fetchAll($res): array
    {
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            // Decode attendance JSON into PHP array for template use
            $row['attendance'] = json_decode((string)($row['attendance_json'] ?? '{}'), true) ?? [];
            $rows[] = $row;
        }
        return $rows;
    }

    /** Normalise episode_id keys to strings, ensure level is uppercase. */
    private function normaliseAttendance(array $attendance): array
    {
        $out = [];
        foreach ($attendance as $eid => $item) {
            $out[(string)$eid] = [
                'level' => strtoupper((string)($item['level'] ?? 'ABSENT')),
                'note'  => (string)($item['note'] ?? ''),
            ];
        }
        return $out;
    }

    /** Count FULL + PARTIAL attendees. */
    private function countPresent(array $attendance): int
    {
        $n = 0;
        foreach ($attendance as $item) {
            $l = strtoupper((string)($item['level'] ?? ''));
            if ($l === 'FULL' || $l === 'PARTIAL') {
                $n++;
            }
        }
        return $n;
    }
}




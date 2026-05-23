<?php

/**
 * src/Shared/Submodule/BedMgmt/Repository/EpisodeLocationRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository;

use RuntimeException;

final class EpisodeLocationRepository
{
    /** @return array<string,mixed>|null */
    public function getCurrentForEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT el.id, el.episode_id, el.facility_id, el.location_id, el.location_code,
                    el.start_datetime, el.end_datetime,
                    COALESCE(l.code, el.location_code, '') AS current_code,
                    COALESCE(l.name, '')                   AS current_name,
                    COALESCE(l.unit_name, '')              AS unit_name,
                    COALESCE(l.location_type, '')          AS location_type
             FROM oei_episode_location el
             LEFT JOIN oei_location l ON l.id = el.location_id
             WHERE el.episode_id = ? AND el.end_datetime IS NULL
             ORDER BY el.start_datetime DESC, el.id DESC
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    public function moveEpisode(
        int $episodeId,
        int $pid,
        ?int $eid,
        int $facilityId,
        ?int $locationId,
        ?string $locationCode,
        ?int $userId,
        ?string $note
    ): void {
        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
            return;
        }

        $normalizedCode = $this->normalizeLocationCode($locationCode);
        if ($locationId === null && $normalizedCode === null) {
            throw new RuntimeException('Select a target location or enter an ad-hoc location code.');
        }

        $now = date('Y-m-d H:i:s');
        $this->beginTransaction();

        try {
            $target = $this->resolveTarget($facilityId, $locationId, $normalizedCode);
            $activeRows = $this->listActiveRowsForEpisodeForUpdate($episodeId);
            $current = $activeRows[0] ?? null;

            if (count($activeRows) > 1) {
                $this->closeRowsByIds(
                    array_map(static fn(array $row): int => (int)$row['id'], array_slice($activeRows, 1)),
                    $now,
                    'Auto-closed duplicate active episode location row.'
                );
            }

            if ($target['location_id'] !== null) {
                $occupied = $this->findActiveOccupantByLocationIdForUpdate($facilityId, (int)$target['location_id'], $episodeId);
            } else {
                $occupied = $this->findActiveOccupantByCodeForUpdate($facilityId, (string)$target['location_code'], $episodeId);
            }

            if ($occupied !== null) {
                throw new RuntimeException(sprintf(
                    '%s is already occupied by episode #%d.',
                    (string)$target['label'],
                    (int)$occupied['episode_id']
                ));
            }

            if ($current !== null && $this->sameTarget($current, $target['location_id'], $target['location_code'])) {
                $this->touchRow((int)$current['id'], $userId, $note);
                $this->commit();
                return;
            }

            if ($current !== null) {
                $this->closeRowsByIds([(int)$current['id']], $now, null);
            }

            sqlStatement(
                "INSERT INTO oei_episode_location (
                    episode_id, pid, eid, facility_id, location_id, location_code,
                    start_datetime, end_datetime, user_id, note
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)",
                [
                    $episodeId,
                    $pid,
                    $eid,
                    $facilityId,
                    $target['location_id'],
                    $target['location_code'],
                    $now,
                    $userId,
                    $note,
                ]
            );

            $this->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listCurrentByFacility(int $facilityId, ?string $unitName = null): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT el.id, el.episode_id, el.pid, el.eid, el.facility_id,
                       el.location_id, el.location_code, el.start_datetime, el.end_datetime,
                       COALESCE(l.code, el.location_code, '') AS current_code,
                       COALESCE(l.name, '')                   AS current_name,
                       COALESCE(l.unit_name, '')              AS unit_name,
                       COALESCE(l.location_type, '')          AS location_type
                FROM oei_episode_location el
                LEFT JOIN oei_location l ON l.id = el.location_id
                WHERE el.facility_id = ? AND el.end_datetime IS NULL";
        $params = [$facilityId];
        if ($unitName !== null && $unitName !== '') {
            $sql .= " AND COALESCE(l.unit_name, '') = ?";
            $params[] = $unitName;
        }
        $sql .= " ORDER BY COALESCE(l.unit_name, ''), COALESCE(l.sort_order, 2147483647),
                         COALESCE(l.name, l.code, el.location_code), el.start_datetime DESC, el.id DESC";

        $res = sqlStatement($sql, $params);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecentHistoryByFacility(int $facilityId, ?string $unitName = null, int $limit = 25): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT el.id, el.episode_id, el.pid, el.eid, el.facility_id,
                       el.location_id, el.location_code, el.start_datetime, el.end_datetime, el.note,
                       COALESCE(cur.code, el.location_code, '') AS to_code,
                       COALESCE(cur.name, '')                   AS to_name,
                       COALESCE(cur.unit_name, '')              AS unit_name,
                       (
                           SELECT COALESCE(prevLoc.code, prev.location_code, '')
                           FROM   oei_episode_location prev
                           LEFT JOIN oei_location prevLoc ON prevLoc.id = prev.location_id
                           WHERE  prev.episode_id = el.episode_id
                             AND (prev.start_datetime < el.start_datetime
                                  OR (prev.start_datetime = el.start_datetime AND prev.id < el.id))
                           ORDER BY prev.start_datetime DESC, prev.id DESC
                           LIMIT 1
                       ) AS from_code,
                       (
                           SELECT COALESCE(prevLoc.name, '')
                           FROM   oei_episode_location prev
                           LEFT JOIN oei_location prevLoc ON prevLoc.id = prev.location_id
                           WHERE  prev.episode_id = el.episode_id
                             AND (prev.start_datetime < el.start_datetime
                                  OR (prev.start_datetime = el.start_datetime AND prev.id < el.id))
                           ORDER BY prev.start_datetime DESC, prev.id DESC
                           LIMIT 1
                       ) AS from_name
                FROM oei_episode_location el
                LEFT JOIN oei_location cur ON cur.id = el.location_id
                WHERE el.facility_id = ?";
        $params = [$facilityId];
        if ($unitName !== null && $unitName !== '') {
            $sql .= " AND COALESCE(cur.unit_name, '') = ?";
            $params[] = $unitName;
        }
        $sql .= " ORDER BY el.start_datetime DESC, el.id DESC LIMIT " . (int)$limit;

        $res = sqlStatement($sql, $params);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function beginTransaction(): void
    {
        sqlStatement('START TRANSACTION');
    }

    private function commit(): void
    {
        sqlStatement('COMMIT');
    }

    private function rollback(): void
    {
        try {
            sqlStatement('ROLLBACK');
        } catch (\Throwable) {
        }
    }

    /** @return array<string,mixed> */
    private function resolveTarget(int $facilityId, ?int $locationId, ?string $locationCode): array
    {
        if ($locationId !== null) {
            $row = sqlQuery(
                "SELECT id, code, name, unit_name
                 FROM oei_location
                 WHERE id = ? AND facility_id = ? AND is_active = 1
                 LIMIT 1",
                [$locationId, $facilityId]
            );
            if (!$row) {
                throw new RuntimeException('Selected location is unavailable or inactive.');
            }

            $code = trim((string)($row['code'] ?? ''));
            $name = trim((string)($row['name'] ?? ''));
            $label = $code !== ''
                ? $code . ($name !== '' ? ' — ' . $name : '')
                : ($name !== '' ? $name : 'Selected location');

            return [
                'location_id' => (int)$row['id'],
                'location_code' => $code !== '' ? $code : null,
                'label' => $label,
            ];
        }

        $code = $this->normalizeLocationCode($locationCode);
        if ($code === null) {
            throw new RuntimeException('Select a target location or enter an ad-hoc location code.');
        }

        return [
            'location_id' => null,
            'location_code' => $code,
            'label' => 'Ad-hoc location ' . $code,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function listActiveRowsForEpisodeForUpdate(int $episodeId): array
    {
        $res = sqlStatement(
            "SELECT id, location_id, location_code, start_datetime
             FROM oei_episode_location
             WHERE episode_id = ? AND end_datetime IS NULL
             ORDER BY start_datetime DESC, id DESC
             FOR UPDATE",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed>|null */
    private function findActiveOccupantByLocationIdForUpdate(int $facilityId, int $locationId, int $episodeId): ?array
    {
        $row = sqlQuery(
            "SELECT episode_id
             FROM oei_episode_location
             WHERE facility_id = ?
               AND location_id = ?
               AND end_datetime IS NULL
               AND episode_id <> ?
             ORDER BY start_datetime DESC, id DESC
             LIMIT 1
             FOR UPDATE",
            [$facilityId, $locationId, $episodeId]
        );
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    private function findActiveOccupantByCodeForUpdate(int $facilityId, string $locationCode, int $episodeId): ?array
    {
        $row = sqlQuery(
            "SELECT episode_id
             FROM oei_episode_location
             WHERE facility_id = ?
               AND location_id IS NULL
               AND UPPER(TRIM(COALESCE(location_code, ''))) = ?
               AND end_datetime IS NULL
               AND episode_id <> ?
             ORDER BY start_datetime DESC, id DESC
             LIMIT 1
             FOR UPDATE",
            [$facilityId, strtoupper($locationCode), $episodeId]
        );
        return $row ?: null;
    }

    private function sameTarget(array $current, ?int $locationId, ?string $locationCode): bool
    {
        $currentLocationId = isset($current['location_id']) && $current['location_id'] !== null
            ? (int)$current['location_id']
            : null;
        $currentCode = $this->normalizeLocationCode($current['location_code'] ?? null);

        if ($locationId !== null) {
            return $currentLocationId === $locationId;
        }

        return $currentLocationId === null
            && $currentCode !== null
            && $locationCode !== null
            && $currentCode === $locationCode;
    }

    /** @param list<int> $ids */
    private function closeRowsByIds(array $ids, string $now, ?string $appendNote): void
    {
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE oei_episode_location
                SET end_datetime = ?";
        $params = [$now];

        if ($appendNote !== null && $appendNote !== '') {
            $sql .= ", note = CASE
                        WHEN note IS NULL OR note = '' THEN ?
                        ELSE CONCAT(note, ' | ', ?)
                     END";
            $params[] = $appendNote;
            $params[] = $appendNote;
        }

        $sql .= " WHERE id IN ({$placeholders}) AND end_datetime IS NULL";
        foreach ($ids as $id) {
            $params[] = $id;
        }
        sqlStatement($sql, $params);
    }

    private function touchRow(int $rowId, ?int $userId, ?string $note): void
    {
        $updates = [];
        $params = [];

        if ($userId !== null) {
            $updates[] = 'user_id = ?';
            $params[] = $userId;
        }
        if ($note !== null) {
            $updates[] = 'note = ?';
            $params[] = $note;
        }

        if ($updates === []) {
            return;
        }

        $params[] = $rowId;
        sqlStatement(
            'UPDATE oei_episode_location SET ' . implode(', ', $updates) . ' WHERE id = ? LIMIT 1',
            $params
        );
    }

    private function normalizeLocationCode(?string $locationCode): ?string
    {
        $locationCode = trim((string)$locationCode);
        return $locationCode !== '' ? $locationCode : null;
    }
}




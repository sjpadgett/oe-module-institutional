<?php

namespace OpenEMR\Modules\Institutional\Core\Service;

/**
 * Centralised audit trail writer.
 *
 * All writes go to oei_episode_event so reports and throughput calculations
 * have a single, consistent source. Submodules that previously wrote directly
 * to oei_episode_event should use this instead.
 */
final class AuditService
{
    /** Well-known event type constants. */
    public const EVT_ARRIVE        = 'ARRIVE';
    public const EVT_ROOM          = 'ROOM';
    public const EVT_PROVIDER      = 'PROVIDER';
    public const EVT_TRIAGE        = 'TRIAGE';
    public const EVT_DECISION      = 'DECISION';
    public const EVT_DEPART        = 'DEPART';
    public const EVT_TRANSFER_REQ  = 'TRANSFER_REQUEST';
    public const EVT_TRANSFER_ACCEPT = 'TRANSFER_ACCEPT';
    public const EVT_TRANSFER_SENT = 'TRANSFER_SENT';
    public const EVT_OBS_START     = 'OBS_START';
    public const EVT_OBS_CLOSE     = 'OBS_CLOSE';
    public const EVT_BH_BOARDING   = 'BH_BOARDING';
    public const EVT_STATUS_CHANGE = 'STATUS_CHANGE';
    public const EVT_LOCATION      = 'LOCATION';

    public function record(
        int $episodeId,
        int $pid,
        int $facilityId,
        string $eventType,
        ?int $userId = null,
        ?string $note = null,
        ?int $eid = null,
        ?string $eventDatetime = null
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $dt = $eventDatetime ?? date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_episode_event
                (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [$episodeId, $pid, $eid, $facilityId, $eventType, $dt, $userId, $note]
        );
    }

    /**
     * Fetch all events for an episode, newest first.
     * @return array<int,array<string,mixed>>
     */
    public function forEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, event_type, event_datetime, user_id, note
             FROM oei_episode_event
             WHERE episode_id = ?
             ORDER BY event_datetime DESC, id DESC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch the first occurrence of each event type for a set of episode IDs.
     * Returns a map of episode_id -> [event_type -> datetime].
     *
     * @param int[] $episodeIds
     * @return array<int,array<string,string>>
     */
    public function firstEventsByEpisode(array $episodeIds, array $eventTypes): array
    {
        if (empty($episodeIds) || empty($eventTypes) || !function_exists('sqlStatement')) {
            return [];
        }
        $idPlaceholders   = implode(',', array_fill(0, count($episodeIds), '?'));
        $typePlaceholders = implode(',', array_fill(0, count($eventTypes), '?'));
        $params = array_merge(array_values($episodeIds), array_values($eventTypes));

        $res = sqlStatement(
            "SELECT episode_id, event_type, MIN(event_datetime) AS first_dt
             FROM oei_episode_event
             WHERE episode_id IN ({$idPlaceholders})
               AND event_type IN ({$typePlaceholders})
             GROUP BY episode_id, event_type",
            $params
        );
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $map[(int)$row['episode_id']][(string)$row['event_type']] = (string)$row['first_dt'];
        }
        return $map;
    }
}

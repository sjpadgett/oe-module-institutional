<?php

namespace OpenEMR\Modules\Institutional\Core\Repository;

use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service\AdtNotificationService;

final class EpisodeRepository
{
    public function __construct(
        private readonly ?AdtNotificationService $adt = null
    ) {}

    public function createArrival(int $pid, int $facilityId, ?string $chiefComplaint, ?int $esi, ?int $userId): int
    {
        $now = date('Y-m-d H:i:s');

        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) {
            return 0;
        }

        sqlStatement(
            "INSERT INTO oei_episode (pid, facility_id, type, start_datetime, chief_complaint, acuity_esi, last_status_update)
             VALUES (?, ?, 'ED', ?, ?, ?, ?)",
            [$pid, $facilityId, $now, $chiefComplaint, $esi, $now]
        );

        $idRow     = sqlQuery("SELECT LAST_INSERT_ID() AS id");
        $episodeId = (int)($idRow['id'] ?? 0);

        $this->appendStatusHistory($episodeId, 'WAITING', $userId, null, $now);

        // ── A04 Register ─────────────────────────────────────────────────────
        if ($episodeId > 0 && $this->adt !== null) {
            $episode = $this->fetchOne($episodeId);
            if ($episode !== null) {
                $this->adt->notifyArrival($episode, $facilityId);
            }
        }

        return $episodeId;
    }

    public function appendStatusHistory(int $episodeId, string $statusCode, ?int $userId, ?string $note = null, ?string $now = null): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = $now ?: date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note)
             VALUES (?, ?, ?, ?, ?)",
            [$episodeId, $statusCode, $userId, $now, $note]
        );
        $this->updateLastStatus($episodeId, $now);

        // ── A08 Update ────────────────────────────────────────────────────────
        // Skip WAITING — that fires as A04 from createArrival already.
        if ($this->adt !== null && $statusCode !== 'WAITING') {
            $episode = $this->fetchOne($episodeId);
            if ($episode !== null) {
                $this->adt->notifyUpdate($episode, (int)$episode['facility_id']);
            }
        }
    }

    public function updateLastStatus(int $episodeId, string $now): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement("UPDATE oei_episode SET last_status_update = ? WHERE id = ?", [$now, $episodeId]);
    }

    public function setType(int $episodeId, string $type, string $now): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        sqlStatement("UPDATE oei_episode SET type = ?, last_status_update = ? WHERE id = ?", [$type, $now, $episodeId]);
    }

    public function closeWithDisposition(int $episodeId, string $disposition, string $now): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }

        // Fetch before closing so we still have ACTIVE status for the message
        $episode = $this->fetchOne($episodeId);

        sqlStatement(
            "UPDATE oei_episode SET disposition = ?, status = 'CLOSED', end_datetime = ?, last_status_update = ? WHERE id = ?",
            [$disposition, $now, $now, $episodeId]
        );

        // ── A03 Discharge ─────────────────────────────────────────────────────
        if ($episode !== null && $this->adt !== null) {
            $episode['disposition'] = $disposition;
            $episode['end_datetime'] = $now;
            $this->adt->notifyDischarge($episode, (int)$episode['facility_id']);
        }
    }

    /** Fetch a single episode row by ID. */
    public function fetchOne(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT id, pid, eid, facility_id, type, status, start_datetime, end_datetime,
                    chief_complaint, acuity_esi, disposition, provider_user_id, arrival_mode
             FROM oei_episode WHERE id = ? LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchBoard(int $facilityId, int $limit = 200): array
    {
        $sql = "SELECT
                    e.id, e.pid, e.eid, e.facility_id, e.type, e.start_datetime, e.chief_complaint,
                    e.acuity_esi, e.provider_user_id, e.status, e.disposition, e.last_status_update,
                    l.name AS location_name,
                    l.location_type AS location_type,
                    bhs.observation_level AS bh_observation_level,
                    bhs.risk_suicide AS bh_risk_suicide,
                    bhs.risk_violence AS bh_risk_violence,
                    bhs.elopement_risk AS bh_elopement_risk,
                    bhs.is_involuntary AS bh_involuntary,
                    op.protocol_key AS obs_protocol_key,
                    (
                        SELECT t.task_type FROM oei_task t
                        WHERE t.episode_id = e.id AND t.status = 'OPEN'
                        ORDER BY t.due_datetime ASC, t.id ASC
                        LIMIT 1
                    ) AS next_task_type,
                    (
                        SELECT t.due_datetime FROM oei_task t
                        WHERE t.episode_id = e.id AND t.status = 'OPEN'
                        ORDER BY t.due_datetime ASC, t.id ASC
                        LIMIT 1
                    ) AS next_task_due,
                    (
                        SELECT sh.status_code
                        FROM oei_episode_status_history sh
                        WHERE sh.episode_id = e.id
                        ORDER BY sh.set_datetime DESC, sh.id DESC
                        LIMIT 1
                    ) AS workflow_status
                FROM oei_episode e
                LEFT JOIN oei_bh_boarding bh ON bh.episode_id = e.id
                LEFT JOIN oei_episode_location h
                  ON h.episode_id = e.id AND h.end_datetime IS NULL
                LEFT JOIN oei_location l
                  ON l.id = h.location_id
                LEFT JOIN oei_bh_safety bhs
                  ON bhs.episode_id = e.id
                LEFT JOIN oei_obs_plan op
                  ON op.episode_id = e.id AND op.status = 'ACTIVE'
                WHERE e.facility_id = ? AND e.status = 'ACTIVE'
                ORDER BY e.start_datetime DESC
                LIMIT " . (int)$limit;

        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement($sql, [$facilityId]);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Fetch episodes by date range (start_datetime).
     * @return array<int,array<string,mixed>>
     */
    public function fetchByDateRange(int $facilityId, string $start, string $end, int $limit = 1000): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT
                    e.id, e.pid, e.eid, e.facility_id, e.type, e.start_datetime, e.end_datetime,
                    e.disposition, e.status, e.chief_complaint, e.acuity_esi,
                    e.provider_user_id, e.triage_completed_datetime, e.triage_datetime,
                    bh.placement_status AS bh_status
                FROM oei_episode e
                LEFT JOIN oei_bh_boarding bh ON bh.episode_id = e.id
                WHERE e.facility_id = ? AND e.start_datetime >= ? AND e.start_datetime <= ?
                ORDER BY e.start_datetime DESC
                LIMIT " . (int)$limit;

        $res  = sqlStatement($sql, [$facilityId, $start, $end]);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}

<?php

/**
 * src/Submodule/Downtime/Controller/DowntimeController.php
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

namespace OpenEMR\Modules\Institutional\Submodule\Downtime\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Downtime\Service\DowntimeSnapshotService;
use OpenEMR\Modules\Institutional\Submodule\Downtime\Service\DowntimeSyncService;

/**
 * DowntimeController
 *
 * Routes:
 *   GET  downtime_snapshot.php?facility_id=N              → JSON snapshot
 *   POST downtime_sync.php     action=sync                → process queue
 *   GET  downtime.php?facility_id=N                       → config + status page
 *   GET  downtime.php?facility_id=N&view=queue            → sync queue table
 */
final class DowntimeController
{
    public function __construct(
        private readonly DowntimeSnapshotService $snapshot,
        private readonly DowntimeSyncService     $sync
    ) {}

    // ── Snapshot API ─────────────────────────────────────────────────────────

    /**
     * Output the facility snapshot as JSON and exit.
     * Called by downtime_snapshot.php.
     */
    public function serveSnapshot(int $facilityId): never
    {
        $data = $this->snapshot->build($facilityId);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-OEI-Snapshot-Generated: ' . $data['generated']);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // ── Sync endpoint ─────────────────────────────────────────────────────────

    /**
     * Accept a batch of offline queue entries from the browser and persist them,
     * then immediately process any PENDING rows.
     * Called by downtime_sync.php (POST).
     *
     * Expects JSON body:
     * {
     *   "facility_id": 1,
     *   "entries": [
     *     { "entry_type": "ARRIVAL", "payload": {...}, "captured_client": "2026-03-01T14:22:00Z" },
     *     ...
     *   ]
     * }
     *
     * @return array{ok: bool, inserted: int, processed: int, synced: int, failed: int}
     */
    public function handleSync(int $facilityId, ?int $userId): array
    {
        if (!function_exists('sqlStatement')) {
            return ['ok' => false, 'inserted' => 0, 'processed' => 0, 'synced' => 0, 'failed' => 0];
        }

        $body = (string)file_get_contents('php://input');
        $json = json_decode($body, true);

        $entries  = is_array($json['entries'] ?? null) ? $json['entries'] : [];
        $now      = date('Y-m-d H:i:s');
        $inserted = 0;

        foreach ($entries as $entry) {
            $entryType = strtoupper(trim((string)($entry['entry_type'] ?? '')));
            $payload   = $entry['payload'] ?? [];
            $captured  = trim((string)($entry['captured_client'] ?? $now));

            if (!in_array($entryType, ['ARRIVAL', 'VITALS', 'STATUS_NOTE', 'TASK_NOTE'], true)) {
                continue;
            }

            sqlStatement(
                "INSERT INTO oei_downtime_sync_queue
                   (facility_id, entry_type, payload_json, captured_client, queued_datetime,
                    status, submitted_by_user_id)
                 VALUES (?, ?, ?, ?, ?, 'PENDING', ?)",
                [
                    $facilityId,
                    $entryType,
                    json_encode($payload, JSON_UNESCAPED_UNICODE),
                    $captured,
                    $now,
                    $userId,
                ]
            );
            $inserted++;
        }

        $result = $this->sync->processFacilityQueue($facilityId, $userId);

        return array_merge(['ok' => true, 'inserted' => $inserted], $result);
    }

    // ── Status / config page data ─────────────────────────────────────────────

    /**
     * Returns view data for downtime.php.
     *
     * @return array<string,mixed>
     */
    public function handlePage(int $facilityId, ?int $userId): array
    {
        $view = (string)($_GET['view'] ?? 'status');

        $pending   = $this->countQueue($facilityId, 'PENDING');
        $synced    = $this->countQueue($facilityId, 'SYNCED');
        $failed    = $this->countQueue($facilityId, 'FAILED');

        $queueRows = [];
        if ($view === 'queue') {
            $queueRows = $this->fetchQueueRows($facilityId, 100);
        }

        return [
            'facility_id' => $facilityId,
            'view'        => $view,
            'pending'     => $pending,
            'synced'      => $synced,
            'failed'      => $failed,
            'queue_rows'  => $queueRows,
            'csrf'        => CsrfUtils::collectCsrfToken(),
        ];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function countQueue(int $facilityId, string $status): int
    {
        if (!function_exists('sqlQuery')) {
            return 0;
        }
        $row = sqlQuery(
            "SELECT COUNT(*) AS n FROM oei_downtime_sync_queue WHERE facility_id=? AND status=?",
            [$facilityId, $status]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchQueueRows(int $facilityId, int $limit): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res  = sqlStatement(
            "SELECT q.*, u.fname, u.lname
             FROM oei_downtime_sync_queue q
             LEFT JOIN users u ON u.id = q.submitted_by_user_id
             WHERE q.facility_id = ?
             ORDER BY q.queued_datetime DESC
             LIMIT " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }
}




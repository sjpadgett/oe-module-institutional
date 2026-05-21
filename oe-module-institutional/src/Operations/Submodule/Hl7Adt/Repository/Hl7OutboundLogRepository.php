<?php

/**
 * src/Operations/Submodule/Hl7Adt/Repository/Hl7OutboundLogRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Operations\Submodule\Hl7Adt\Repository;

final class Hl7OutboundLogRepository
{
    public function record(
        int     $episodeId,
        int     $pid,
        int     $facilityId,
        string  $eventType,       // A01, A02, A03, A04, A08
        string  $transportType,   // MLLP, HTTP
        string  $endpoint,        // host:port or URL
        string  $messageBody,
        string  $status,          // SENT, ERROR, NACK
        ?string $ackBody,
        ?string $errorMessage
    ): void {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_hl7_outbound_log
               (episode_id, pid, facility_id, event_type, transport_type, endpoint,
                message_body, status, ack_body, error_message, sent_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$episodeId, $pid, $facilityId, $eventType, $transportType,
             $endpoint, $messageBody, $status, $ackBody, $errorMessage, $now]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecent(int $facilityId, int $limit = 100): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, episode_id, pid, event_type, transport_type, endpoint,
                    status, error_message, sent_datetime
             FROM oei_hl7_outbound_log
             WHERE facility_id = ?
             ORDER BY id DESC
             LIMIT " . (int)$limit,
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function listForEpisode(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, event_type, transport_type, status, error_message, sent_datetime
             FROM oei_hl7_outbound_log
             WHERE episode_id = ?
             ORDER BY id DESC",
            [$episodeId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed>|null Full message body for a log entry */
    public function getDetail(int $logId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT * FROM oei_hl7_outbound_log WHERE id = ? LIMIT 1",
            [$logId]
        );
        return $row ?: null;
    }

    /** @return array{sent:int, error:int, nack:int} Last 24h summary */
    public function summary24h(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return ['sent' => 0, 'error' => 0, 'nack' => 0];
        }
        $since = date('Y-m-d H:i:s', strtotime('-24 hours'));
        $res = sqlStatement(
            "SELECT status, COUNT(*) AS c
             FROM oei_hl7_outbound_log
             WHERE facility_id = ? AND sent_datetime >= ?
             GROUP BY status",
            [$facilityId, $since]
        );
        $out = ['sent' => 0, 'error' => 0, 'nack' => 0];
        while ($row = sqlFetchArray($res)) {
            $key = strtolower((string)($row['status'] ?? ''));
            if (isset($out[$key])) {
                $out[$key] = (int)$row['c'];
            }
        }
        return $out;
    }
}




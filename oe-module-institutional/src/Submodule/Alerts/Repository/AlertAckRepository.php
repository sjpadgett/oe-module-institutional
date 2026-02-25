<?php

namespace OpenEMR\Modules\Institutional\Submodule\Alerts\Repository;

/**
 * Stores per-user alert acknowledgements so a charge nurse can dismiss
 * an alert for a fixed snooze period without it bouncing back immediately.
 *
 * Uses oei_alert_ack table (see alerts.sql).
 * Falls back gracefully if table does not yet exist.
 */
final class AlertAckRepository
{
    private const DEFAULT_SNOOZE_MIN = 30;

    /**
     * Record an acknowledgement.
     * alert_key is a stable string derived from type + episode_id, e.g. "LWBS_RISK:42"
     */
    public function ack(string $alertKey, int $facilityId, int $userId, int $snoozeMin = self::DEFAULT_SNOOZE_MIN): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $expires = date('Y-m-d H:i:s', time() + $snoozeMin * 60);
        $now     = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_alert_ack (alert_key, facility_id, user_id, acked_datetime, expires_datetime)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               acked_datetime=VALUES(acked_datetime),
               expires_datetime=VALUES(expires_datetime)",
            [$alertKey, $facilityId, $userId, $now, $expires]
        );
    }

    /**
     * Returns set of currently-snoozed alert keys for a facility.
     * @return array<string,true>
     */
    public function activeSnoozed(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        try {
            $res = sqlStatement(
                "SELECT alert_key FROM oei_alert_ack
                 WHERE facility_id = ? AND expires_datetime > NOW()",
                [$facilityId]
            );
            $keys = [];
            while ($row = sqlFetchArray($res)) {
                $keys[(string)$row['alert_key']] = true;
            }
            return $keys;
        } catch (\Throwable $e) {
            // Table may not exist on old installs — degrade gracefully
            return [];
        }
    }

    /** Prune expired acks (call occasionally, e.g. in the dashboard controller). */
    public function pruneExpired(): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        try {
            sqlStatement("DELETE FROM oei_alert_ack WHERE expires_datetime < NOW()");
        } catch (\Throwable $e) {
            // silence
        }
    }

    /** Build a stable key for a given alert. */
    public static function key(string $type, int $episodeId): string
    {
        return $type . ':' . $episodeId;
    }
}

<?php

namespace OpenEMR\Modules\Institutional\Submodule\Timeline\Repository;

/**
 * Aggregates all institutional event sources into a single chronological
 * timeline for one episode.
 *
 * Sources joined:
 *   oei_episode_event          – ARRIVE, ROOM, PROVIDER, DECISION, DEPART, OBS_*, BH_*
 *   oei_episode_status_history – WAITING, ROOMED, PROVIDER, READY_DISPO, CLOSED …
 *   oei_patient_location_history – room moves
 *   oei_triage                 – each vitals set
 *   oei_task                   – completed tasks
 *   oei_mar_administration     – GIVEN/HELD/REFUSED outcomes (optional)
 *   oei_ereferral              – sent/accepted/declined (optional)
 */
final class TimelineRepository
{
    /**
     * Returns entries sorted ascending (oldest first).
     *
     * Each entry shape:
     *   [
     *     'ts'       => int        unix timestamp for sort
     *     'datetime' => string     Y-m-d H:i:s for display
     *     'source'   => string     ARRIVAL | STATUS | LOCATION | VITALS | TASK | MAR | REFERRAL | EVENT
     *     'icon'     => string     Bootstrap icon class (bi-*)
     *     'label'    => string     human-readable summary
     *     'detail'   => string     secondary line (nullable)
     *     'severity' => string     '' | 'warning' | 'danger' | 'success' | 'info'
     *     'user_id'  => int|null
     *   ]
     *
     * @return array<int,array<string,mixed>>
     */
    public function forEpisode(int $episodeId): array
    {
        $entries = [];

        $entries = array_merge(
            $entries,
            $this->loadEpisodeEvents($episodeId),
            $this->loadStatusHistory($episodeId),
            $this->loadLocationHistory($episodeId),
            $this->loadVitals($episodeId),
            $this->loadTasks($episodeId),
            $this->loadMar($episodeId),
            $this->loadReferral($episodeId)
        );

        // Sort ascending by timestamp, stable by source for same-second entries
        usort($entries, function (array $a, array $b): int {
            $diff = $a['ts'] <=> $b['ts'];
            if ($diff !== 0) return $diff;
            // Secondary: prefer ARRIVAL first, TASK/MAR last for same-second ties
            $order = ['ARRIVAL' => 0, 'EVENT' => 1, 'STATUS' => 2, 'LOCATION' => 3,
                      'VITALS' => 4, 'TASK' => 5, 'MAR' => 6, 'REFERRAL' => 7];
            return ($order[$a['source']] ?? 99) <=> ($order[$b['source']] ?? 99);
        });

        return $entries;
    }

    // -------------------------------------------------------------------------

    private function loadEpisodeEvents(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT event_type, event_datetime, user_id, note
             FROM oei_episode_event
             WHERE episode_id = ?
             ORDER BY event_datetime ASC, id ASC",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $type = (string)($row['event_type'] ?? '');
            [$label, $icon, $sev] = $this->eventMeta($type, (string)($row['note'] ?? ''));
            $out[] = $this->entry(
                (string)$row['event_datetime'], 'EVENT', $icon, $label,
                (string)($row['note'] ?? ''), $sev, (int)($row['user_id'] ?? 0) ?: null
            );
        }
        return $out;
    }

    private function loadStatusHistory(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT status_code, set_datetime, set_by_user_id, note
             FROM oei_episode_status_history
             WHERE episode_id = ?
             ORDER BY set_datetime ASC, id ASC",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $code = (string)($row['status_code'] ?? '');
            $sev  = match ($code) {
                'CLOSED'      => 'success',
                'WAITING'     => 'warning',
                'READY_DISPO' => 'info',
                default       => '',
            };
            $out[] = $this->entry(
                (string)$row['set_datetime'], 'STATUS', 'bi-arrow-right-circle',
                "Status: {$code}", (string)($row['note'] ?? ''), $sev,
                (int)($row['set_by_user_id'] ?? 0) ?: null
            );
        }
        return $out;
    }

    private function loadLocationHistory(int $episodeId): array
    {
        if (!function_exists('sqlStatement')) return [];

        // Try oei_patient_location_history first (AdtLite), fallback to oei_episode_location (BedMgmt)
        $table = $this->tableExists('oei_patient_location_history') ? 'oei_patient_location_history' : null;
        if ($table === null && !$this->tableExists('oei_episode_location')) return [];

        if ($table === 'oei_patient_location_history') {
            $res = sqlStatement(
                "SELECT plh.start_datetime, plh.reason, l.name AS location_name
                 FROM oei_patient_location_history plh
                 LEFT JOIN oei_location l ON l.id = plh.location_id
                 WHERE plh.episode_id = ?
                 ORDER BY plh.start_datetime ASC, plh.id ASC",
                [$episodeId]
            );
        } else {
            $res = sqlStatement(
                "SELECT el.start_datetime, el.note AS reason, l.name AS location_name
                 FROM oei_episode_location el
                 LEFT JOIN oei_location l ON l.id = el.location_id
                 WHERE el.episode_id = ?
                 ORDER BY el.start_datetime ASC, el.id ASC",
                [$episodeId]
            );
        }

        $out = [];
        while ($row = sqlFetchArray($res)) {
            $loc  = (string)($row['location_name'] ?? 'Unknown location');
            $reason = (string)($row['reason'] ?? '');
            $out[] = $this->entry(
                (string)$row['start_datetime'], 'LOCATION', 'bi-geo-alt',
                "Moved to: {$loc}", $reason, '', null
            );
        }
        return $out;
    }

    private function loadVitals(int $episodeId): array
    {
        if (!function_exists('sqlStatement') || !$this->tableExists('oei_triage')) return [];
        $res = sqlStatement(
            "SELECT set_number, noted_datetime, noted_by_user_id,
                    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, esi_suggested
             FROM oei_triage
             WHERE episode_id = ?
             ORDER BY set_number ASC, id ASC",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $parts = [];
            if ($row['bp_systolic'] !== null) $parts[] = "BP {$row['bp_systolic']}/{$row['bp_diastolic']}";
            if ($row['hr']          !== null) $parts[] = "HR {$row['hr']}";
            if ($row['spo2']        !== null) $parts[] = "SpO₂ {$row['spo2']}%";
            if ($row['temp_f']      !== null) $parts[] = "T {$row['temp_f']}°F";
            if ($row['rr']          !== null) $parts[] = "RR {$row['rr']}";
            if ($row['gcs']         !== null) $parts[] = "GCS {$row['gcs']}";
            if ($row['pain_score']  !== null) $parts[] = "Pain {$row['pain_score']}/10";
            $detail = implode('  ·  ', $parts);
            $esi    = $row['esi_suggested'] ? "  (ESI suggested: {$row['esi_suggested']})" : '';
            $setN   = (int)$row['set_number'];
            $label  = "Vitals set #{$setN}{$esi}";

            // Flag abnormal vitals
            $sev = '';
            if (($row['spo2'] !== null && (int)$row['spo2'] < 90) ||
                ($row['bp_systolic'] !== null && (int)$row['bp_systolic'] < 80) ||
                ($row['gcs'] !== null && (int)$row['gcs'] <= 8)) {
                $sev = 'danger';
            } elseif (($row['spo2'] !== null && (int)$row['spo2'] < 94) ||
                      ($row['bp_systolic'] !== null && (int)$row['bp_systolic'] < 90)) {
                $sev = 'warning';
            }

            $out[] = $this->entry(
                (string)$row['noted_datetime'], 'VITALS', 'bi-heart-pulse',
                $label, $detail, $sev,
                (int)($row['noted_by_user_id'] ?? 0) ?: null
            );
        }
        return $out;
    }

    private function loadTasks(int $episodeId): array
    {
        if (!function_exists('sqlStatement') || !$this->tableExists('oei_task')) return [];
        // oei_task schema: status = 'COMPLETE', completion user = assigned_to_user_id
        $res = sqlStatement(
            "SELECT task_type, completed_datetime, assigned_to_user_id, due_datetime
             FROM oei_task
             WHERE episode_id = ? AND status = 'COMPLETE' AND completed_datetime IS NOT NULL
             ORDER BY completed_datetime ASC, id ASC",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $type = (string)($row['task_type'] ?? '');
            $due  = (string)($row['due_datetime'] ?? '');
            $dueTs  = $due ? strtotime($due) : 0;
            $doneTs = strtotime((string)$row['completed_datetime']);
            $lateMin = ($dueTs && $doneTs && $doneTs > $dueTs)
                ? (int)round(($doneTs - $dueTs) / 60) : 0;
            $detail = $lateMin > 0 ? "{$lateMin}m late" : '';
            $out[] = $this->entry(
                (string)$row['completed_datetime'], 'TASK', 'bi-check-circle',
                "Task completed: {$type}", $detail,
                $lateMin > 30 ? 'warning' : 'success',
                (int)($row['assigned_to_user_id'] ?? 0) ?: null
            );
        }
        return $out;
    }

    private function loadMar(int $episodeId): array
    {
        if (!function_exists('sqlStatement') || !$this->tableExists('oei_mar_administration')) return [];
        $res = sqlStatement(
            "SELECT ma.outcome, ma.administered_datetime, ma.administered_by_user_id,
                    mo.drug_name, mo.dose, mo.unit AS dose_unit, ma.is_high_alert
             FROM oei_mar_administration ma
             JOIN oei_mar_order mo ON mo.id = ma.mar_order_id
             WHERE ma.episode_id = ?
               AND ma.outcome IN ('GIVEN','HELD','REFUSED','MISSED')
               AND ma.administered_datetime IS NOT NULL
             ORDER BY ma.administered_datetime ASC, ma.id ASC",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            $outcome   = (string)($row['outcome'] ?? '');
            $drug      = (string)($row['drug_name'] ?? '');
            $dose      = trim((string)($row['dose'] ?? '') . ' ' . (string)($row['dose_unit'] ?? ''));
            $highAlert = (bool)($row['is_high_alert'] ?? false);
            $sev = match ($outcome) {
                'GIVEN'   => $highAlert ? 'warning' : 'success',
                'HELD', 'REFUSED', 'MISSED' => 'warning',
                default   => '',
            };
            $label  = "MAR {$outcome}: {$drug}";
            $detail = $dose . ($highAlert ? '  ⚠ HIGH ALERT' : '');
            $out[]  = $this->entry(
                (string)$row['administered_datetime'], 'MAR', 'bi-capsule',
                $label, $detail, $sev,
                (int)($row['administered_by_user_id'] ?? 0) ?: null
            );
        }
        return $out;
    }

    private function loadReferral(int $episodeId): array
    {
        if (!function_exists('sqlStatement') || !$this->tableExists('oei_ereferral')) return [];
        $res = sqlStatement(
            "SELECT status, referral_type, destination_name, sent_datetime, sent_by_user_id,
                    response_datetime, response_by_name
             FROM oei_ereferral
             WHERE episode_id = ?
             LIMIT 1",
            [$episodeId]
        );
        $out = [];
        while ($row = sqlFetchArray($res)) {
            if (!empty($row['sent_datetime'])) {
                $dest = (string)($row['destination_name'] ?? '');
                $out[] = $this->entry(
                    (string)$row['sent_datetime'], 'REFERRAL', 'bi-send',
                    "Referral sent" . ($dest ? " → {$dest}" : ''),
                    (string)($row['referral_type'] ?? ''), 'info',
                    (int)($row['sent_by_user_id'] ?? 0) ?: null
                );
            }
            if (!empty($row['response_datetime'])) {
                $status = (string)($row['status'] ?? '');
                $by     = (string)($row['response_by_name'] ?? '');
                $sev    = $status === 'ACCEPTED' ? 'success' : 'warning';
                $out[]  = $this->entry(
                    (string)$row['response_datetime'], 'REFERRAL', 'bi-reply',
                    "Referral {$status}" . ($by ? " — {$by}" : ''),
                    '', $sev, null
                );
            }
        }
        return $out;
    }

    // -------------------------------------------------------------------------

    private function entry(
        string  $datetime,
        string  $source,
        string  $icon,
        string  $label,
        string  $detail,
        string  $severity,
        ?int    $userId
    ): array {
        return [
            'ts'       => strtotime($datetime) ?: 0,
            'datetime' => $datetime,
            'source'   => $source,
            'icon'     => $icon,
            'label'    => $label,
            'detail'   => $detail,
            'severity' => $severity,
            'user_id'  => $userId,
        ];
    }

    /**
     * Map well-known event types to display meta.
     * @return array{0:string,1:string,2:string} [label, icon, severity]
     */
    private function eventMeta(string $type, string $note): array
    {
        return match ($type) {
            'ARRIVE'           => ["Patient arrived",               'bi-door-open',         'info'],
            'ROOM'             => ["Roomed",                        'bi-geo-alt-fill',       ''],
            'PROVIDER'         => ["Provider seen",                 'bi-person-check',       'success'],
            'TRIAGE'           => ["Triage completed",              'bi-clipboard-pulse',    ''],
            'DECISION'         => ["Disposition decision",          'bi-check2-square',      'info'],
            'DEPART'           => ["Patient departed",              'bi-box-arrow-right',    'success'],
            'OBS_START'        => ["Observation started",           'bi-clock-history',      'info'],
            'OBS_CLOSE'        => ["Observation closed",            'bi-clock-fill',         'success'],
            'BH_BOARDING'      => ["BH boarding initiated",         'bi-hospital',           'warning'],
            'BH_ACCEPTED'      => ["BH placement accepted: {$note}", 'bi-check-circle-fill', 'success'],
            'BH_TRANSPORT'     => ["BH transport: {$note}",         'bi-truck',              'success'],
            'EMTALA_COMPLETE'  => ["EMTALA checklist complete",     'bi-shield-check',       'success'],
            'TRANSFER_REQUEST' => ["Transfer requested",            'bi-arrow-right-square', 'warning'],
            'TRANSFER_ACCEPT'  => ["Transfer accepted",             'bi-check-all',          'success'],
            'TRANSFER_SENT'    => ["Patient transferred",           'bi-send-check',         'success'],
            'STATUS_CHANGE'    => ["Status changed: {$note}",       'bi-arrow-right-circle', ''],
            'LOCATION'         => ["Location change: {$note}",      'bi-geo-alt',            ''],
            default            => [$type,                           'bi-circle',             ''],
        };
    }

    private function tableExists(string $table): bool
    {
        if (!function_exists('sqlQuery')) return false;
        $row = sqlQuery(
            "SELECT COUNT(*) AS c FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1",
            [$table]
        );
        return (int)($row['c'] ?? 0) > 0;
    }
}



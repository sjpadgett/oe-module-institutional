<?php

/**
 * src/Submodule/Alerts/Service/AlertService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Alerts\Service;

use OpenEMR\Modules\Institutional\Submodule\Mar\Repository\MarAdministrationRepository;

/**
 * Computes structured alerts consumable by the dashboard polling loop.
 *
 * Alert shape:
 *   [
 *     'type'       => string  TASK_OVERDUE | LWBS_RISK | BH_BOARDING_DWELL |
 *                             OBS_RUNWAY   | VITALS_DETERIORATION | NO_VITALS |
 *                             MAR_OVERDUE
 *     'severity'   => 'WARNING' | 'CRITICAL'
 *     'episode_id' => int
 *     'pid'        => int
 *     'message'    => string  human-readable
 *     'detail'     => string  secondary detail line
 *     'minutes'    => int|null
 *     'group'      => string  board group key for UI bucketing
 *   ]
 */
final class AlertService
{
    private int   $lwbsThresholdMin;
    private int   $boardingAlertHours;
    private int   $obsRunwayWarningHours;
    private int   $noVitalsWarningMin;
    private int   $noVitalsCriticalMin;
    private int   $marGraceMinutes;

    public function __construct(
        int $lwbsThresholdMin       = 120,
        int $boardingAlertHours     = 4,
        int $obsRunwayWarningHours  = 6,
        int $noVitalsWarningMin     = 60,
        int $noVitalsCriticalMin    = 120,
        int $marGraceMinutes        = 15
    ) {
        $this->lwbsThresholdMin      = $lwbsThresholdMin;
        $this->boardingAlertHours    = $boardingAlertHours;
        $this->obsRunwayWarningHours = $obsRunwayWarningHours;
        $this->noVitalsWarningMin    = $noVitalsWarningMin;
        $this->noVitalsCriticalMin   = $noVitalsCriticalMin;
        $this->marGraceMinutes       = $marGraceMinutes;
    }

    // -----------------------------------------------------------------------
    // Primary entry point
    // -----------------------------------------------------------------------

    /**
     * Compute and merge all alert types.
     *
     * @param  array<int,array<string,mixed>> $boardRows     EpisodeRepository::fetchBoard()
     * @param  array<int,array<string,mixed>> $latestVitals  TriageRepository::latestByFacility()
     *                                                       keyed by episode_id; empty if triage not installed
     * @param  int    $facilityId
     * @return array<int,array<string,mixed>>  sorted: CRITICAL first, then by minutes desc
     */
    public function computeAll(array $boardRows, array $latestVitals, int $facilityId): array
    {
        $alerts = array_merge(
            $this->computeForBoard($boardRows, $latestVitals),
            $this->computeBoardingAlerts($facilityId),
            $this->computeObsRunwayAlerts($facilityId),
            $this->computeMarAlerts($facilityId),
            $this->computeSepsisAlerts($latestVitals),
            $this->computeObsBillingAlerts($facilityId)
        );

        // Sort: CRITICAL before WARNING, then by minutes descending
        usort($alerts, function (array $a, array $b): int {
            $sevScore = fn(string $s): int => $s === 'CRITICAL' ? 1 : 0;
            $sA = $sevScore($a['severity']);
            $sB = $sevScore($b['severity']);
            if ($sA !== $sB) {
                return $sB <=> $sA;
            }
            return ($b['minutes'] ?? 0) <=> ($a['minutes'] ?? 0);
        });

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Board-row based alerts (LWBS, overdue tasks, vitals)
    // -----------------------------------------------------------------------

    /**
     * @param  array<int,array<string,mixed>> $boardRows
     * @param  array<int,array<string,mixed>> $latestVitals  keyed by episode_id
     * @return array<int,array<string,mixed>>
     */
    public function computeForBoard(array $boardRows, array $latestVitals = []): array
    {
        $now    = time();
        $alerts = [];

        foreach ($boardRows as $row) {
            $episodeId = (int)($row['id'] ?? 0);
            $pid       = (int)($row['pid'] ?? 0);
            $status    = (string)($row['workflow_status'] ?? $row['status'] ?? '');
            $startTs   = !empty($row['start_datetime']) ? strtotime((string)$row['start_datetime']) : null;

            // -- LWBS risk -----------------------------------------------
            if ($startTs && $status === 'WAITING' && empty($row['location_name'])) {
                $mins = (int)round(($now - $startTs) / 60);
                if ($mins >= $this->lwbsThresholdMin) {
                    $alerts[] = $this->makeAlert(
                        'LWBS_RISK',
                        $mins >= $this->lwbsThresholdMin * 2 ? 'CRITICAL' : 'WARNING',
                        $episodeId, $pid,
                        "Waiting {$mins}m without a room",
                        'LWBS risk — no location assigned',
                        $mins, 'lwbs'
                    );
                }
            }

            // -- Overdue tasks -------------------------------------------
            $nextDue  = $row['next_task_due']  ?? null;
            $nextType = $row['next_task_type'] ?? '';
            if ($nextDue) {
                $dueTs = strtotime((string)$nextDue);
                if ($dueTs && $dueTs < $now) {
                    $overdueMins = (int)round(($now - $dueTs) / 60);
                    $alerts[] = $this->makeAlert(
                        'TASK_OVERDUE',
                        $overdueMins > 30 ? 'CRITICAL' : 'WARNING',
                        $episodeId, $pid,
                        "Task overdue {$overdueMins}m",
                        htmlspecialchars((string)$nextType),
                        $overdueMins, 'tasks'
                    );
                }
            }

            // -- Vitals: no vitals recorded since arrival ----------------
            $vitals = $latestVitals[$episodeId] ?? null;
            if ($startTs) {
                if ($vitals === null) {
                    $minsSinceArrival = (int)round(($now - $startTs) / 60);
                    if ($minsSinceArrival >= $this->noVitalsWarningMin) {
                        $alerts[] = $this->makeAlert(
                            'NO_VITALS',
                            $minsSinceArrival >= $this->noVitalsCriticalMin ? 'CRITICAL' : 'WARNING',
                            $episodeId, $pid,
                            "No vitals recorded ({$minsSinceArrival}m since arrival)",
                            'Triage may be outstanding',
                            $minsSinceArrival, 'vitals'
                        );
                    }
                } else {
                    // -- Vitals: deterioration from last recorded set ----
                    foreach ($this->checkVitalsDegradation($vitals, $episodeId, $pid) as $a) {
                        $alerts[] = $a;
                    }

                    // -- Vitals: stale re-check (obs patients) ----------
                    if (($row['type'] ?? '') === 'OBS') {
                        $lastVitalTs = strtotime((string)($vitals['noted_datetime'] ?? ''));
                        if ($lastVitalTs) {
                            $staleMin = (int)round(($now - $lastVitalTs) / 60);
                            if ($staleMin > 240) {  // > 4 hours for obs
                                $alerts[] = $this->makeAlert(
                                    'VITALS_STALE',
                                    $staleMin > 480 ? 'CRITICAL' : 'WARNING',
                                    $episodeId, $pid,
                                    "Vitals {$staleMin}m old (OBS patient)",
                                    'Re-check due',
                                    $staleMin, 'vitals'
                                );
                            }
                        }
                    }
                }
            }
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // BH boarding dwell
    // -----------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function computeBoardingAlerts(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $thresholdDatetime = date('Y-m-d H:i:s', time() - $this->boardingAlertHours * 3600);
        $res = sqlStatement(
            "SELECT b.episode_id, b.pid, b.placement_status, e.start_datetime
             FROM oei_bh_boarding b
             JOIN oei_episode e ON e.id = b.episode_id
             WHERE b.facility_id = ?
               AND b.placement_status IN ('SEARCHING','PENDING')
               AND e.start_datetime <= ?",
            [$facilityId, $thresholdDatetime]
        );

        $alerts = [];
        $now = time();

        while ($row = sqlFetchArray($res)) {
            $startTs = strtotime((string)$row['start_datetime']);
            $hours   = $startTs ? round(($now - $startTs) / 3600, 1) : null;
            $mins    = $hours !== null ? (int)round($hours * 60) : null;
            $alerts[] = $this->makeAlert(
                'BH_BOARDING_DWELL',
                ($hours !== null && $hours >= $this->boardingAlertHours * 2) ? 'CRITICAL' : 'WARNING',
                (int)$row['episode_id'], (int)$row['pid'],
                "BH boarding {$hours}h",
                'Status: ' . htmlspecialchars((string)$row['placement_status']),
                $mins, 'boarding'
            );
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Obs runway
    // -----------------------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function computeObsRunwayAlerts(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT op.episode_id, e.pid, op.start_datetime, op.target_hours, op.runway_hours, op.protocol_key
             FROM oei_obs_plan op
             JOIN oei_episode e ON e.id = op.episode_id
             WHERE op.facility_id = ? AND op.status = 'ACTIVE'",
            [$facilityId]
        );

        $alerts = [];
        $now    = time();

        while ($row = sqlFetchArray($res)) {
            $startTs     = strtotime((string)$row['start_datetime']);
            $targetSecs  = (int)$row['target_hours']  * 3600;
            $runwaySecs  = (int)$row['runway_hours']   * 3600;
            if (!$startTs) {
                continue;
            }

            $elapsed   = $now - $startTs;
            $remaining = $targetSecs - $elapsed;

            if ($remaining > 0 && $remaining <= $runwaySecs) {
                $remMins = (int)round($remaining / 60);
                $alerts[] = $this->makeAlert(
                    'OBS_RUNWAY',
                    $remaining <= ($this->obsRunwayWarningHours * 1800) ? 'CRITICAL' : 'WARNING',
                    (int)$row['episode_id'], (int)$row['pid'],
                    "Obs runway: {$remMins}m remaining",
                    'Protocol: ' . htmlspecialchars((string)$row['protocol_key']),
                    $remMins, 'obs'
                );
            }

            // Overrun: past target
            if ($remaining <= 0) {
                $overMins = (int)round(abs($remaining) / 60);
                $alerts[] = $this->makeAlert(
                    'OBS_RUNWAY',
                    'CRITICAL',
                    (int)$row['episode_id'], (int)$row['pid'],
                    "Obs OVERRUN by {$overMins}m",
                    'Disposition decision needed',
                    $overMins, 'obs'
                );
            }
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // MAR overdue medications
    // -----------------------------------------------------------------------

    /**
     * Surface overdue medication administration slots from oei_mar_administration.
     * High-alert drugs and slots > 30 min overdue escalate to CRITICAL.
     * Requires MAR submodule tables (oei_mar_administration, oei_mar_order).
     * Returns empty array gracefully if MAR tables are not yet installed.
     *
     * @return array<int,array<string,mixed>>
     */
    public function computeMarAlerts(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        // Guard: skip silently if MAR tables not yet migrated
        $tableCheck = sqlQuery(
            "SELECT COUNT(*) AS c FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = 'oei_mar_administration'
             LIMIT 1"
        );
        if (!$tableCheck || (int)($tableCheck['c'] ?? 0) === 0) {
            return [];
        }

        $repo  = new MarAdministrationRepository();
        $rows  = $repo->listOverdueByFacility($facilityId, $this->marGraceMinutes);
        $now   = time();
        $alerts = [];

        foreach ($rows as $r) {
            $scheduledTs = strtotime((string)$r['scheduled_datetime']);
            $min = $scheduledTs ? (int)round(($now - $scheduledTs) / 60) : 0;
            $isHighAlert = (bool)($r['is_high_alert'] ?? false);

            // CRITICAL if: high-alert drug OR overdue > 30 min
            $severity = ($isHighAlert || $min > 30) ? 'CRITICAL' : 'WARNING';

            $detail = $isHighAlert ? 'HIGH-ALERT medication' : 'Scheduled administration pending';

            $alerts[] = $this->makeAlert(
                'MAR_OVERDUE',
                $severity,
                (int)$r['episode_id'],
                (int)$r['pid'],
                "Overdue med: " . htmlspecialchars((string)$r['drug_name']) . " by {$min}m",
                $detail,
                $min,
                'mar'
            );
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Vitals deterioration checks
    // -----------------------------------------------------------------------

    /**
     * @param  array<string,mixed> $v  latest vitals row from oei_triage
     * @return array<int,array<string,mixed>>
     */
    private function checkVitalsDegradation(array $v, int $episodeId, int $pid): array
    {
        $alerts = [];

        $sbp  = isset($v['bp_systolic'])  && $v['bp_systolic']  !== '' ? (int)$v['bp_systolic']  : null;
        $hr   = isset($v['hr'])           && $v['hr']           !== '' ? (int)$v['hr']           : null;
        $spo2 = isset($v['spo2'])         && $v['spo2']         !== '' ? (int)$v['spo2']         : null;
        $gcs  = isset($v['gcs'])          && $v['gcs']          !== '' ? (int)$v['gcs']          : null;
        $rr   = isset($v['rr'])           && $v['rr']           !== '' ? (int)$v['rr']           : null;
        $temp = isset($v['temp_f'])       && $v['temp_f']       !== '' ? (float)$v['temp_f']     : null;

        $age = null;
        if (!empty($v['noted_datetime'])) {
            $ts  = strtotime((string)$v['noted_datetime']);
            $age = $ts ? (int)round((time() - $ts) / 60) : null;
        }
        $ageStr = $age !== null ? " ({$age}m ago)" : '';

        // SpO2
        if ($spo2 !== null) {
            if ($spo2 < 90) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'CRITICAL', $episodeId, $pid,
                    "Critical SpO₂ {$spo2}%{$ageStr}", 'Immediate respiratory assessment', $age, 'vitals');
            } elseif ($spo2 < 94) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    "Low SpO₂ {$spo2}%{$ageStr}", 'Monitor closely', $age, 'vitals');
            }
        }

        // BP systolic
        if ($sbp !== null) {
            if ($sbp < 80) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'CRITICAL', $episodeId, $pid,
                    "Critical hypotension SBP {$sbp}{$ageStr}", 'Shock protocol consideration', $age, 'vitals');
            } elseif ($sbp < 90) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    "Hypotension SBP {$sbp}{$ageStr}", 'IV access / fluid bolus', $age, 'vitals');
            } elseif ($sbp >= 180) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    "Hypertensive SBP {$sbp}{$ageStr}", 'Reassess medication / symptoms', $age, 'vitals');
            }
        }

        // HR
        if ($hr !== null) {
            if ($hr < 40 || $hr > 150) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'CRITICAL', $episodeId, $pid,
                    "Critical HR {$hr}bpm{$ageStr}", $hr < 40 ? 'Severe bradycardia' : 'Severe tachycardia', $age, 'vitals');
            } elseif ($hr < 50 || $hr > 130) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    "Abnormal HR {$hr}bpm{$ageStr}", $hr < 50 ? 'Bradycardia' : 'Tachycardia', $age, 'vitals');
            }
        }

        // GCS
        if ($gcs !== null) {
            if ($gcs <= 8) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'CRITICAL', $episodeId, $pid,
                    "Critical GCS {$gcs}{$ageStr}", 'Airway management consideration', $age, 'vitals');
            } elseif ($gcs <= 12) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    "Depressed GCS {$gcs}{$ageStr}", 'Neuro reassessment', $age, 'vitals');
            }
        }

        // RR
        if ($rr !== null && ($rr < 8 || $rr > 30)) {
            $sev = ($rr < 6 || $rr > 35) ? 'CRITICAL' : 'WARNING';
            $alerts[] = $this->makeAlert('VITALS_DETERIORATION', $sev, $episodeId, $pid,
                "Abnormal RR {$rr}{$ageStr}", $rr < 8 ? 'Bradypnea' : 'Tachypnea', $age, 'vitals');
        }

        // Temp
        if ($temp !== null) {
            if ($temp >= 104.0) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'CRITICAL', $episodeId, $pid,
                    'Hyperpyrexia ' . number_format($temp, 1) . "°F{$ageStr}", 'Aggressive cooling', $age, 'vitals');
            } elseif ($temp >= 101.5) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    'Fever ' . number_format($temp, 1) . "°F{$ageStr}", 'Workup / antipyretic', $age, 'vitals');
            } elseif ($temp < 96.8) {
                $alerts[] = $this->makeAlert('VITALS_DETERIORATION', 'WARNING', $episodeId, $pid,
                    'Hypothermia ' . number_format($temp, 1) . "°F{$ageStr}", 'Active warming', $age, 'vitals');
            }
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function makeAlert(
        string $type, string $severity, int $episodeId, int $pid,
        string $message, string $detail, ?int $minutes, string $group
    ): array {
        return [
            'type'       => $type,
            'severity'   => $severity,
            'episode_id' => $episodeId,
            'pid'        => $pid,
            'message'    => $message,
            'detail'     => $detail,
            'minutes'    => $minutes,
            'group'      => $group,
        ];
    }

    // -----------------------------------------------------------------------
    // Summary helpers for dashboard header
    // -----------------------------------------------------------------------

    /**
     * @param  array<int,array<string,mixed>> $alerts
     * @return array{critical:int, warning:int, by_group:array<string,int>}
     */
    // -----------------------------------------------------------------------
    // Sepsis / qSOFA scoring
    // -----------------------------------------------------------------------

    /**
     * Compute qSOFA-based sepsis risk alerts from latest vitals.
     *
     * qSOFA criteria (each = 1 point):
     *   GCS < 15      (altered mentation)
     *   RR  >= 22     (tachypnea)
     *   SBP <= 100    (hypotension)
     *
     * Score >= 2  → SEPSIS_RISK alert
     * Score == 3  → CRITICAL; Score == 2 → WARNING
     *
     * @param  array<int,array<string,mixed>> $latestVitals  keyed by episode_id
     * @return array<int,array<string,mixed>>
     */
    public function computeSepsisAlerts(array $latestVitals): array
    {
        $alerts = [];

        foreach ($latestVitals as $episodeId => $v) {
            $gcs = isset($v['gcs']) && $v['gcs'] !== '' ? (int)$v['gcs'] : null;
            $rr  = isset($v['rr'])  && $v['rr']  !== '' ? (int)$v['rr']  : null;
            $sbp = isset($v['bp_systolic']) && $v['bp_systolic'] !== '' ? (int)$v['bp_systolic'] : null;

            $score    = 0;
            $criteria = [];

            if ($gcs !== null && $gcs < 15)  { $score++; $criteria[] = "GCS {$gcs}"; }
            if ($rr  !== null && $rr  >= 22) { $score++; $criteria[] = "RR {$rr}";  }
            if ($sbp !== null && $sbp <= 100) { $score++; $criteria[] = "SBP {$sbp}"; }

            if ($score < 2) {
                continue;
            }

            $severity = $score >= 3 ? 'CRITICAL' : 'WARNING';
            $detail   = 'qSOFA ' . $score . '/3: ' . implode(', ', $criteria);
            $pid      = (int)($v['pid'] ?? 0);

            $alerts[] = $this->makeAlert(
                'SEPSIS_RISK',
                $severity,
                (int)$episodeId,
                $pid,
                'Sepsis risk: qSOFA ' . $score . '/3',
                $detail,
                $score * 10,   // synthetic minutes — keeps sort stable
                'vitals'
            );
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Observation billing flags (2-midnight rule)
    // -----------------------------------------------------------------------

    /**
     * Surface OBS billing alerts from ObsBillingService.
     * Requires obs_billing feature flag enabled in manifest (checked in service).
     *
     * @return array<int,array<string,mixed>>
     */
    public function computeObsBillingAlerts(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        // Guard: only run if oei_obs_plan table exists
        $check = sqlQuery(
            "SELECT COUNT(*) AS c FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'oei_obs_plan' LIMIT 1"
        );
        if ((int)($check['c'] ?? 0) === 0) {
            return [];
        }

        $billingService = new \OpenEMR\Modules\Institutional\Submodule\ObsBilling\Service\ObsBillingService();
        return $billingService->computeBillingAlerts($facilityId);
    }

    // -----------------------------------------------------------------------
    // Summary helpers
    // -----------------------------------------------------------------------

    public static function summarize(array $alerts): array
    {
        $critical = 0;
        $warning  = 0;
        $byGroup  = [];

        foreach ($alerts as $a) {
            if ($a['severity'] === 'CRITICAL') {
                $critical++;
            } else {
                $warning++;
            }
            $g = (string)($a['group'] ?? 'other');
            $byGroup[$g] = ($byGroup[$g] ?? 0) + 1;
        }

        return ['critical' => $critical, 'warning' => $warning, 'by_group' => $byGroup];
    }
}






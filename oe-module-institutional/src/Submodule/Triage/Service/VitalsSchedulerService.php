<?php

/**
 * src/Submodule/Triage/Service/VitalsSchedulerService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Triage\Service;

use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Submodule\Settings\Repository\SettingsRepository;

/**
 * VitalsSchedulerService
 *
 * Auto-schedules VITALS_CHECK tasks for an episode when triggered.
 *
 * Call sites:
 *   - AdtService::assignLocation()   → on rooming (standard ED vitals Q2H)
 *   - ObsService::startObs()         → on obs start (Q4H per protocol, configurable)
 *
 * Behaviour:
 *   - Reads vitals_interval_ed_min  (default 120 = Q2H) from oei_settings
 *   - Reads vitals_interval_obs_min (default 240 = Q4H) from oei_settings
 *   - Reads vitals_window_hours     (default 12)         from oei_settings
 *   - Checks oei_task for existing VITALS_CHECK tasks on this episode;
 *     does NOT re-schedule if tasks already exist (idempotent on re-apply)
 *   - Creates tasks via TaskRepository::create() with dedup guard
 *   - Gracefully returns 0 if the tasks table does not exist yet
 */
final class VitalsSchedulerService
{
    public function __construct(
        private readonly TaskRepository     $taskRepo,
        private readonly SettingsRepository $settings
    ) {}

    /**
     * Schedule Q2H vitals checks from now for a standard ED episode.
     * Returns number of tasks created.
     */
    public function scheduleForEd(
        int  $episodeId,
        int  $pid,
        ?int $eid,
        int  $facilityId,
        ?int $userId
    ): int {
        $cfg = $this->settings->all($facilityId);
        $intervalMin = (int)($cfg['vitals_interval_ed_min'] ?? 120);
        $windowHours = (int)($cfg['vitals_window_hours']   ?? 12);
        return $this->schedule($episodeId, $pid, $eid, $facilityId, $userId, $intervalMin, $windowHours);
    }

    /**
     * Schedule Q4H vitals checks from now for an observation episode.
     * Typically called from ObsService::startObs(); protocol tasks already
     * contain VITALS_Q4H so we only schedule here when the obs protocol
     * does NOT define its own vitals tasks.
     * Returns number of tasks created.
     */
    public function scheduleForObs(
        int  $episodeId,
        int  $pid,
        ?int $eid,
        int  $facilityId,
        ?int $userId,
        bool $protocolHasVitals = false
    ): int {
        if ($protocolHasVitals) {
            // Protocol engine already handles vitals tasks — don't double-schedule
            return 0;
        }
        $cfg = $this->settings->all($facilityId);
        $intervalMin = (int)($cfg['vitals_interval_obs_min'] ?? 240);
        $windowHours = (int)($cfg['vitals_window_hours']     ?? 24);
        return $this->schedule($episodeId, $pid, $eid, $facilityId, $userId, $intervalMin, $windowHours);
    }

    // -------------------------------------------------------------------------

    /**
     * Core scheduler: creates VITALS_CHECK tasks every $intervalMin minutes
     * for $windowHours hours from now, skipping any due times that already
     * have an open or done VITALS_CHECK task within a ±5-minute window.
     */
    private function schedule(
        int  $episodeId,
        int  $pid,
        ?int $eid,
        int  $facilityId,
        ?int $userId,
        int  $intervalMin,
        int  $windowHours
    ): int {
        if ($intervalMin <= 0 || $windowHours <= 0) {
            return 0;
        }
        if (!function_exists('sqlStatement')) {
            return 0;
        }
        // Defensive table guard
        $tableOk = sqlQuery(
            "SELECT COUNT(*) AS c FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'oei_task' LIMIT 1"
        );
        if ((int)($tableOk['c'] ?? 0) === 0) {
            return 0;
        }

        $nowTs     = time();
        $endTs     = $nowTs + ($windowHours * 3600);
        $interval  = $intervalMin * 60;
        $tolerance = 300; // 5-minute dedup window

        // Fetch existing VITALS_CHECK tasks for this episode (open or done)
        $existingTs = [];
        $res = sqlStatement(
            "SELECT due_datetime FROM oei_task
             WHERE episode_id = ? AND task_type = 'VITALS_CHECK'",
            [$episodeId]
        );
        while ($row = sqlFetchArray($res)) {
            $ts = strtotime((string)($row['due_datetime'] ?? ''));
            if ($ts) $existingTs[] = $ts;
        }

        $created = 0;
        $t = $nowTs + $interval;
        while ($t <= $endTs) {
            // Check if an existing task is within tolerance of this slot
            $hasNearby = false;
            foreach ($existingTs as $existTs) {
                if (abs($existTs - $t) <= $tolerance) {
                    $hasNearby = true;
                    break;
                }
            }
            if (!$hasNearby) {
                $this->taskRepo->create(
                    $episodeId, $pid, $eid, $facilityId,
                    'VITALS_CHECK',
                    date('Y-m-d H:i:s', $t),
                    $userId
                );
                $existingTs[] = $t; // update local list so loop stays consistent
                $created++;
            }
            $t += $interval;
        }
        return $created;
    }
}







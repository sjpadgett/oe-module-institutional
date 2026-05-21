<?php

/**
 * src/Shared/Submodule/Tasks/Service/TaskService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

final class TaskService
{
    public function __construct(private readonly TaskRepository $repo) {}

    /**
     * Schedule baseline obs tasks for a given window (now → now+hours).
     * Tasks defined by $definition['tasks'] each with every_minutes or at_minutes.
     */
    public function scheduleFromDefinition(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        array $definition, int $runwayHours, ?int $userId,
        ?string $fromDatetime = null
    ): int {
        $startTs  = $fromDatetime ? (strtotime($fromDatetime) ?: time()) : time();
        $windowEnd = $startTs + ($runwayHours * 3600);
        $tasks    = $definition['tasks'] ?? [];
        $generated = 0;

        foreach ($tasks as $td) {
            if (!is_array($td) || empty($td['type'])) continue;
            $type = (string)$td['type'];

            if (isset($td['every_minutes']) && is_numeric($td['every_minutes'])) {
                $interval = (int)$td['every_minutes'] * 60;
                if ($interval <= 0) continue;
                $t = $startTs + $interval;
                while ($t <= $windowEnd) {
                    $this->repo->create($episodeId, $pid, $eid, $facilityId, $type, date('Y-m-d H:i:s', $t), $userId);
                    $generated++;
                    $t += $interval;
                }
            } elseif (isset($td['at_minutes']) && is_array($td['at_minutes'])) {
                foreach ($td['at_minutes'] as $m) {
                    if (!is_numeric($m)) continue;
                    $t = $startTs + ((int)$m * 60);
                    if ($t < $startTs || $t > $windowEnd) continue;
                    $this->repo->create($episodeId, $pid, $eid, $facilityId, $type, date('Y-m-d H:i:s', $t), $userId);
                    $generated++;
                }
            }
        }
        return $generated;
    }

    public function scheduleDefaultObs(
        int $episodeId, int $pid, ?int $eid, int $facilityId, ?int $userId, int $hours = 24
    ): void {
        $def = [
            'tasks' => [
                ['type' => 'VITALS_Q4H',   'every_minutes' => 240],
                ['type' => 'REASSESS_Q2H', 'every_minutes' => 120],
            ]
        ];
        $this->scheduleFromDefinition($episodeId, $pid, $eid, $facilityId, $def, $hours, $userId);
    }

    public function scheduleDefaultBhSafety(
        int $episodeId, int $pid, ?int $eid, int $facilityId, string $level, ?int $userId
    ): void {
        $intervalMap = ['ONE_TO_ONE' => 60, 'Q15' => 15, 'Q30' => 30, 'Q60' => 60];
        $interval = $intervalMap[$level] ?? 60;
        $def = ['tasks' => [['type' => 'BH_CHECK_' . $level, 'every_minutes' => $interval]]];
        $this->scheduleFromDefinition($episodeId, $pid, $eid, $facilityId, $def, 4, $userId);
    }
}




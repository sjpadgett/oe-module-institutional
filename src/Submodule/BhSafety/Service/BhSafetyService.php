<?php

/**
 * src/Submodule/BhSafety/Service/BhSafetyService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\BhSafety\Service;

use OpenEMR\Modules\Institutional\Submodule\BhSafety\Repository\BhSafetyRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Service\TaskService;

final class BhSafetyService
{
    private ?TaskService $taskService;

    public function __construct(
        private readonly BhSafetyRepository $repo,
        private readonly ?TaskRepository    $taskRepo
    ) {
        $this->taskService = $taskRepo ? new TaskService($taskRepo) : null;
    }

    public function setBhSafety(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $level, int $involuntary, int $violence, int $suicide, int $elopement,
        array $precautions, ?int $userId
    ): void {
        $precJson = !empty($precautions) ? json_encode($precautions) : null;
        $this->repo->upsert($episodeId, $pid, $eid, $facilityId,
            $level, $involuntary, $violence, $suicide, $elopement, $precJson, $userId);

        if ($this->taskService && $level !== 'NONE') {
            $this->taskService->scheduleDefaultBhSafety($episodeId, $pid, $eid, $facilityId, $level, $userId);
        }
    }
}






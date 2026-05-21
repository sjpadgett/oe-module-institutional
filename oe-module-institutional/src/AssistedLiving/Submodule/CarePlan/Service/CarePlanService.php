<?php

/**
 * src/AssistedLiving/Submodule/CarePlan/Service/CarePlanService.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Service;

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Repository\CarePlanRepository;

final class CarePlanService
{
    public function __construct(private readonly CarePlanRepository $repo) {}

    /** Page data: goals, activities, care team. */
    public function pageData(int $episodeId, int $pid): array
    {
        $entries  = $this->repo->fetchByEpisode($episodeId);
        $careTeam = $this->repo->fetchCareTeam($pid);

        $goals      = array_filter($entries, fn($e) => $e['care_plan_type'] === 'goal');
        $activities = array_filter($entries, fn($e) => $e['care_plan_type'] === 'activity');

        return [
            'goals'      => array_values($goals),
            'activities' => array_values($activities),
            'care_team'  => $careTeam,
            'total'      => count($entries),
        ];
    }

    public function addGoal(int $episodeId, string $description, string $proposedDate, int $userId): bool
    {
        return $this->repo->addEntry(
            $episodeId, 'goal', $description, '', '', 'active',
            $proposedDate ?: null, $userId
        );
    }

    public function addActivity(int $episodeId, string $description, string $proposedDate, int $userId): bool
    {
        return $this->repo->addEntry(
            $episodeId, 'activity', $description, '', '', 'active',
            $proposedDate ?: null, $userId
        );
    }

    public function updateStatus(int $entryId, string $status): void
    {
        $this->repo->updateStatus($entryId, $status);
    }
}




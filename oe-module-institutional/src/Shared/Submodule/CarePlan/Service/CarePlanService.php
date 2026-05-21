<?php

/**
 * src/Shared/Submodule/CarePlan/Service/CarePlanService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Repository\CarePlanRepository;

/**
 * Shared CarePlanService
 *
 * Business logic layer for cross-context care plan display and writes.
 * Works for AL, IP, ED, OBS, and BH episodes.
 */
final class CarePlanService
{
    public function __construct(private readonly CarePlanRepository $repo) {}

    /**
     * Full page data for a care plan panel.
     *
     * @return array{goals:list<array>,activities:list<array>,
     *             care_team:array,total:int,
     *             encounter_id:int|null,has_encounter:bool,
     *             launch_url:string} encounter_id is the OpenEMR encounter number
     */
    public function pageData(int $episodeId, string $episodeType, int $pid): array
    {
        $entries     = $this->repo->fetchByEpisode($episodeId, $episodeType);
        $careTeam    = $this->repo->fetchCareTeam($pid);
        $encounterId = $this->repo->resolveEncounter($episodeId, $episodeType);

        $goals      = array_values(array_filter($entries, fn($e) => $e['care_plan_type'] === 'goal'));
        $activities = array_values(array_filter($entries, fn($e) => $e['care_plan_type'] === 'activity'));

        return [
            'goals'        => $goals,
            'activities'   => $activities,
            'care_team'    => $careTeam,
            'total'        => count($entries),
            'encounter_id' => $encounterId,
            'has_encounter' => $encounterId !== null,
            'launch_url'   => $this->buildLaunchUrl($pid, $encounterId),
        ];
    }

    /**
     * Summary counts for embedding in profile panels.
     *
     * @return array{goals:int,activities:int,completed:int,goals_preview:list<array>}
     */
    public function summary(int $episodeId, string $episodeType): array
    {
        $entries = $this->repo->fetchByEpisode($episodeId, $episodeType);

        $goals      = array_values(array_filter($entries, fn($e) => $e['care_plan_type'] === 'goal'));
        $activities = array_values(array_filter($entries, fn($e) => $e['care_plan_type'] === 'activity'));
        $completed  = array_filter($entries, fn($e) => $e['plan_status'] === 'completed');

        return [
            'goals'         => count($goals),
            'activities'    => count($activities),
            'completed'     => count($completed),
            'goals_preview' => array_slice($goals, 0, 3),
        ];
    }

    public function addGoal(
        int $episodeId, string $episodeType,
        string $description, string $proposedDate, int $userId
    ): bool {
        return $this->repo->addEntry(
            $episodeId, $episodeType, 'goal',
            $description, '', '', 'active',
            $proposedDate ?: null, $userId
        );
    }

    public function addActivity(
        int $episodeId, string $episodeType,
        string $description, string $proposedDate, int $userId
    ): bool {
        return $this->repo->addEntry(
            $episodeId, $episodeType, 'activity',
            $description, '', '', 'active',
            $proposedDate ?: null, $userId
        );
    }

    public function updateStatus(int $entryId, string $status): void
    {
        $this->repo->updateStatus($entryId, $status);
    }

    /**
     * Build the OpenEMR native care_plan form URL for new entries.
     * Returns '' when encounter is null (button must be disabled).
     */
    public function buildLaunchUrl(int $pid, ?int $encounterId): string
    {
        if ($encounterId === null) {
            return '';
        }
        $base = $GLOBALS['webroot'] ?? '';
        return "{$base}/interface/forms/care_plan/new.php"
             . "?id=0&pid={$pid}&encounter={$encounterId}";
    }

    /**
     * Build the edit URL for an existing care plan entry.
     */
    public function buildEditUrl(int $pid, ?int $encounterId, int $formId): string
    {
        if ($encounterId === null) {
            return '';
        }
        $base = $GLOBALS['webroot'] ?? '';
        return "{$base}/interface/forms/care_plan/new.php"
             . "?id={$formId}&pid={$pid}&encounter={$encounterId}";
    }
}






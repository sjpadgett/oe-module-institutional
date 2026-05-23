<?php

/**
 * src/Shared/Submodule/CarePlan/Controller/CarePlanController.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Service\EncounterResolver;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;
use OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Repository\CarePlanRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\CarePlan\Service\CarePlanService;

/**
 * Shared CarePlanController
 *
 * Handles GET display + inline POST (add goal / add activity / update status)
 * for any episode type.
 *
 * Used by public/shared/care_plan.php.
 */
final class CarePlanController
{
    private readonly CarePlanService $service;

    public function __construct()
    {
        $repo          = new CarePlanRepository(new EncounterResolver(), new FormsRegistrar());
        $this->service = new CarePlanService($repo);
    }

    /**
     * @param int    $episodeId
     * @param string $episodeType 'AL'|'IP'|'ED'|'OBS'|'BH'
     * @param int    $pid
     * @param int    $userId
     * @return array{goals:list<array>,activities:list<array>,care_team:array,
     *             total:int,encounter_id:int|null,has_encounter:bool,
     *             launch_url:string,flash:string,episodeId:int,
     *             pid:int,episodeType:string} encounter_id is the OpenEMR encounter number
     */
    public function handle(
        int    $episodeId,
        string $episodeType,
        int    $pid,
        int    $userId
    ): array {
        $flash = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action      = (string)($_POST['action'] ?? '');
            $description = trim((string)($_POST['description'] ?? ''));
            $proposed    = trim((string)($_POST['proposed_date'] ?? ''));

            if ($action === 'add_goal' && $description !== '') {
                $this->service->addGoal($episodeId, $episodeType, $description, $proposed, $userId);
                $flash = xlt('Goal added.');
            } elseif ($action === 'add_activity' && $description !== '') {
                $this->service->addActivity($episodeId, $episodeType, $description, $proposed, $userId);
                $flash = xlt('Intervention added.');
            } elseif ($action === 'update_status') {
                $entryId = (int)($_POST['entry_id'] ?? 0);
                $status  = trim((string)($_POST['status'] ?? ''));
                if ($entryId > 0 && $status !== '') {
                    $this->service->updateStatus($entryId, $status);
                    $flash = xlt('Status updated.');
                }
            }
        }

        $data                = $this->service->pageData($episodeId, $episodeType, $pid);
        $data['flash']       = $flash;
        $data['episodeId']   = $episodeId;
        $data['pid']         = $pid;
        $data['episodeType'] = $episodeType;
        return $data;
    }
}






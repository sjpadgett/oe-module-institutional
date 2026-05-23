<?php

/**
 * src/Shared/Submodule/CareTeam/Controller/CareTeamController.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Repository\CareTeamRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\CareTeam\Service\CareTeamService;

/**
 * CareTeamController
 *
 * Handles the care team panel page (GET display + POST add/remove member).
 *
 * Unlike care plans and clinical notes, care teams have no native OpenEMR
 * form (no interface/forms/care_team/).  This controller provides the only
 * management UI.  Writes go directly to care_teams + care_team_member
 * via CareTeamRepository.
 *
 * Used by public/shared/care_team.php.
 */
final class CareTeamController
{
    private readonly CareTeamService $service;

    public function __construct()
    {
        $this->service = new CareTeamService(new CareTeamRepository());
    }

    /**
     * @param int $pid        patient_data.pid
     * @param int $episodeId  oei_episode.id (used for back-link only)
     * @param int $userId     Acting user
     * @return array{team:array|null,members:list<array>,roles:list<array>,
     *             staff:list<array>,pid:int,episodeId:int,flash:string,flash_type:string}
     */
    public function handle(int $pid, int $episodeId, int $userId): array
    {
        $flash      = '';
        $flashType  = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = (string)($_POST['action'] ?? '');

            if ($action === 'add_member') {
                $role          = trim((string)($_POST['role'] ?? ''));
                $membUserId    = (int)($_POST['member_user_id'] ?? 0) ?: null;
                $providerSince = trim((string)($_POST['provider_since'] ?? '')) ?: null;
                $note          = trim((string)($_POST['note'] ?? ''));

                if ($role !== '') {
                    $ok = $this->service->addMember(
                        $pid, $role, $membUserId, null, null,
                        $providerSince, $note, $userId
                    );
                    $flash     = $ok ? xlt('Team member added.') : xlt('Could not add member — role may already exist.');
                    $flashType = $ok ? 'success' : 'warning';
                } else {
                    $flash     = xlt('Role is required.');
                    $flashType = 'danger';
                }
            } elseif ($action === 'remove_member') {
                $memberId = (int)($_POST['member_id'] ?? 0);
                if ($memberId > 0) {
                    $this->service->removeMember($memberId, $userId);
                    $flash = xlt('Member removed from care team.');
                }
            }
        }

        $data               = $this->service->pageData($pid);
        $data['episodeId']  = $episodeId;
        $data['flash']      = $flash;
        $data['flash_type'] = $flashType;
        return $data;
    }
}




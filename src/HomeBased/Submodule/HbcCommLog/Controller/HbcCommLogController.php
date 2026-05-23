<?php

/**
 * src/HomeBased/Submodule/HbcCommLog/Controller/HbcCommLogController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcCommLog\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcCommLog\Repository\HbcCommLogRepository;

final class HbcCommLogController
{
    public function __construct(
        private readonly HbcCommLogRepository $repo = new HbcCommLogRepository()
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $episodeId, int $pid, int $facilityId, int $userId): array
    {
        $flash = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = trim((string) ($_POST['action'] ?? ''));

            if ($action === 'create') {
                $commType    = strtoupper(trim((string) ($_POST['comm_type'] ?? 'PHONE_OUT')));
                $contactRole = strtoupper(trim((string) ($_POST['contact_role'] ?? 'OTHER')));
                $contactName = trim((string) ($_POST['contact_name'] ?? ''));
                $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
                $subject     = trim((string) ($_POST['subject'] ?? ''));
                $summary     = trim((string) ($_POST['summary'] ?? ''));
                $outcome     = trim((string) ($_POST['outcome'] ?? ''));
                $followup    = !empty($_POST['followup_needed']);
                $followupNote = trim((string) ($_POST['followup_note'] ?? ''));
                $commDt      = trim((string) ($_POST['comm_datetime'] ?? ''));

                if ($commDt === '') {
                    $commDt = date('Y-m-d H:i:s');
                } else {
                    $commDt = date('Y-m-d H:i:s', strtotime($commDt) ?: time());
                }

                if (!array_key_exists($commType, HbcCommLogRepository::commTypes())) {
                    $commType = 'OTHER';
                }
                if (!array_key_exists($contactRole, HbcCommLogRepository::contactRoles())) {
                    $contactRole = 'OTHER';
                }

                $id = $this->repo->create(
                    $episodeId, $pid, $facilityId,
                    $commType, $contactRole,
                    $contactName !== '' ? $contactName : null,
                    $contactPhone !== '' ? $contactPhone : null,
                    $subject !== '' ? $subject : null,
                    $summary !== '' ? $summary : null,
                    $outcome !== '' ? $outcome : null,
                    $followup,
                    $followupNote !== '' ? $followupNote : null,
                    $commDt,
                    $userId > 0 ? $userId : null
                );

                if ($id > 0) {
                    $flash = xlt('Communication logged.');
                } else {
                    $error = xlt('Failed to save communication. Please try again.');
                }
            }
        }

        // Dual mode: episode-specific or facility-wide
        if ($episodeId > 0) {
            $entries = $this->repo->listByEpisode($episodeId, 40);
            $pending = $this->repo->countPendingFollowups($episodeId);
        } else {
            $entries = $this->repo->listByFacility($facilityId, 60);
            $pending = $this->repo->countPendingFollowupsByFacility($facilityId);
        }

        return [
            'flash'             => $flash,
            'error'             => $error,
            'entries'           => $entries,
            'pending_followups' => $pending,
            'comm_types'        => HbcCommLogRepository::commTypes(),
            'contact_roles'     => HbcCommLogRepository::contactRoles(),
            'facility_wide'     => ($episodeId === 0),
        ];
    }
}







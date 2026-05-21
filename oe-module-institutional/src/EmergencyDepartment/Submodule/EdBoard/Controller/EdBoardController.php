<?php

/**
 * src/EmergencyDepartment/Submodule/EdBoard/Controller/EdBoardController.php
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

namespace OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\EdBoard\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Domain\Disposition;
use OpenEMR\Modules\Institutional\Core\Domain\EpisodeStatus;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Core\Ui\Flash;
use OpenEMR\Modules\Institutional\Shared\Submodule\AdtLite\Service\AdtService;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\LocationRepository;

// was AdtLite
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsCore\Service\ObsService;

final class EdBoardController
{
    public function __construct(
        private readonly EpisodeRepository $episodes,
        private readonly LocationRepository $locations,
        private readonly AdtService $adtService,
        private readonly ObsService $obsService
    )
    {
    }

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
    {
        $csrf = CsrfUtils::collectCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $this->processPost($facilityId, $userId);
            header("Location: ed_board.php?facility_id=" . urlencode((string)$facilityId));
            exit;
        }

        return [
            'rows' => $this->episodes->fetchBoard($facilityId),
            'locations' => $this->locations->listActive($facilityId),
            'csrf' => $csrf,
            'allowed_statuses' => EpisodeStatus::allowedForBoard(),
            'allowed_dispos' => Disposition::allowed(),
        ];
    }

    private function processPost(int $facilityId, ?int $userId): void
    {
        $action = (string)($_POST['action'] ?? '');
        $episodeId = (int)($_POST['episode_id'] ?? 0);
        $pid = (int)($_POST['pid'] ?? 0);
        $eidRaw = trim((string)($_POST['eid'] ?? ''));
        $eid = ctype_digit($eidRaw) && $eidRaw !== '' ? (int)$eidRaw : null;
        $now = date('Y-m-d H:i:s');

        switch ($action) {
            case 'arrival':
                $pid = (int)($_POST['pid'] ?? 0);
                $chief = trim((string)($_POST['chief_complaint'] ?? '')) ?: null;
                $esiRaw = trim((string)($_POST['acuity_esi'] ?? ''));
                $esi = is_numeric($esiRaw) ? (int)$esiRaw : null;
                if ($pid > 0) {
                    $this->episodes->createArrival($pid, $facilityId, $chief, $esi, $userId);
                }
                break;

            case 'stamp_room':
                if ($episodeId > 0) {
                    $this->episodes->appendStatusHistory($episodeId, 'ROOMED', $userId, null, $now);
                }
                break;

            case 'stamp_provider':
                if ($episodeId > 0) {
                    $this->episodes->appendStatusHistory($episodeId, 'PROVIDER', $userId, null, $now);
                }
                break;

            case 'assign_location':
                $locIdRaw = (string)($_POST['location_id'] ?? '');
                $locId = is_numeric($locIdRaw) && $locIdRaw !== '' ? (int)$locIdRaw : null;
                if ($episodeId > 0 && $pid > 0) {
                    try {
                        $this->adtService->assignLocation($episodeId, $pid, $eid, $facilityId, $locId, 'ROOMED', $userId);
                        Flash::addSuccess(xlt('Location assigned.'));
                    } catch (\RuntimeException $e) {
                        Flash::addError($e->getMessage());
                    } catch (\Throwable) {
                        Flash::addError(xlt('Unable to assign location.'));
                    }
                }
                break;

            case 'set_status':
                $code = strtoupper(trim((string)($_POST['status_code'] ?? '')));
                if ($episodeId > 0 && $code !== '') {
                    $this->episodes->appendStatusHistory($episodeId, $code, $userId, null, $now);
                }
                break;

            case 'start_obs':
                $protocolKey = strtoupper(trim((string)($_POST['protocol_key'] ?? 'GENERAL_OBS')));
                if ($episodeId > 0 && $pid > 0) {
                    $this->obsService->startObs($episodeId, $pid, $eid, $facilityId, $protocolKey, $userId);
                }
                break;

            case 'set_disposition':
                $dispo = strtoupper(trim((string)($_POST['disposition'] ?? '')));
                if ($episodeId > 0 && $dispo !== '') {
                    $this->episodes->closeWithDisposition($episodeId, $dispo, $now);
                }
                break;
        }
    }
}




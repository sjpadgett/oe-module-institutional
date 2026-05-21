<?php

/**
 * src/Submodule/BedMgmt/Controller/BedMgmtController.php
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

namespace OpenEMR\Modules\Institutional\Submodule\BedMgmt\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Core\Ui\Flash;
use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Submodule\BedMgmt\Repository\LocationRepository;

final class BedMgmtController
{
    public function __construct(
        private readonly LocationRepository        $locations,
        private readonly EpisodeLocationRepository $episodeLocations,
        private readonly EpisodeRepository         $episodes
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId, ?string $selectedUnit = null): array
    {
        $csrf = CsrfUtils::collectCsrfToken();
        $selectedUnit = trim((string)$selectedUnit);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $selectedUnit = trim((string)($_POST['unit'] ?? $selectedUnit));
            $this->processPost($facilityId, $userId);
            header('Location: ' . $this->redirectUrl($facilityId, $selectedUnit));
            exit;
        }

        $currentLoc        = $this->episodeLocations->listCurrentByFacility($facilityId, $selectedUnit ?: null);
        $locByEpisode      = [];
        $occupiedLocationIds = [];
        foreach ($currentLoc as $r) {
            $epId = (int)($r['episode_id'] ?? 0);
            $locByEpisode[$epId] = $r;
            $locId = (int)($r['location_id'] ?? 0);
            if ($locId > 0) {
                $occupiedLocationIds[$locId] = $epId;
            }
        }

        return [
            'csrf'               => $csrf,
            'selectedUnit'       => $selectedUnit,
            'units'              => $this->locations->listUnits($facilityId),
            'locations'          => $this->locations->listActive($facilityId, $selectedUnit ?: null),
            'episodes'           => $this->episodes->fetchBoard($facilityId),
            'locByEpisode'       => $locByEpisode,
            'occupiedLocationIds'=> $occupiedLocationIds,
            'history'            => $this->episodeLocations->listRecentHistoryByFacility($facilityId, $selectedUnit ?: null, 25),
        ];
    }

    private function processPost(int $facilityId, ?int $userId): void
    {
        $action = (string)($_POST['action'] ?? '');

        if ($action === 'save_location') {
            $code   = trim((string)($_POST['code']          ?? ''));
            $name   = trim((string)($_POST['name']          ?? ''));
            $type   = trim((string)($_POST['location_type'] ?? 'ROOM'));
            $unit   = trim((string)($_POST['unit_name']     ?? '')) ?: null;
            $sort   = (int)($_POST['sort_order']            ?? 0);
            $active = !empty($_POST['is_active']) ? 1 : 0;
            $notes  = trim((string)($_POST['notes']         ?? '')) ?: null;

            if ($code !== '' && $name !== '') {
                $this->locations->upsert($facilityId, $code, $name, $type, $unit, $sort, $active, $notes);
                Flash::addSuccess(xlt('Location saved.'));
            } else {
                Flash::addError(xlt('Location code and name are required.'));
            }
            return;
        }

        if ($action === 'move_episode') {
            $episodeId = (int)($_POST['episode_id']    ?? 0);
            $pid       = (int)($_POST['pid']           ?? 0);
            $eidRaw    = (string)($_POST['eid']        ?? '');
            $eid       = is_numeric($eidRaw) ? (int)$eidRaw : null;
            $locIdRaw  = (string)($_POST['location_id'] ?? '');
            $locId     = is_numeric($locIdRaw) ? (int)$locIdRaw : null;
            $locCode   = trim((string)($_POST['location_code'] ?? '')) ?: null;
            $note      = trim((string)($_POST['note']          ?? '')) ?: null;

            if ($episodeId <= 0 || $pid <= 0 || ($locId === null && $locCode === null)) {
                Flash::addError(xlt('Select a target location or enter an ad-hoc location code.'));
                return;
            }

            try {
                $this->episodeLocations->moveEpisode(
                    $episodeId,
                    $pid,
                    $eid,
                    $facilityId,
                    $locId,
                    $locCode,
                    $userId,
                    $note
                );
                Flash::addSuccess(xlt('Location assignment saved.'));
            } catch (\RuntimeException $e) {
                Flash::addError($e->getMessage());
            } catch (\Throwable) {
                Flash::addError(xlt('Unable to save location assignment.'));
            }
        }
    }

    private function redirectUrl(int $facilityId, string $selectedUnit): string
    {
        $url = 'bed_management.php?facility_id=' . urlencode((string)$facilityId);
        if ($selectedUnit !== '') {
            $url .= '&unit=' . urlencode($selectedUnit);
        }
        return $url;
    }
}




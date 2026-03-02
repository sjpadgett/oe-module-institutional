<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\BedMgmt\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
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
    public function handle(int $facilityId, ?int $userId): array
    {
        $csrf = CsrfUtils::collectCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $this->processPost($facilityId, $userId);
            header('Location: bed_board.php?facility_id=' . urlencode((string)$facilityId));
            exit;
        }

        $currentLoc   = $this->episodeLocations->listCurrentByFacility($facilityId);
        $locByEpisode = [];
        foreach ($currentLoc as $r) {
            $locByEpisode[(int)$r['episode_id']] = $r;
        }

        return [
            'csrf'         => $csrf,
            'locations'    => $this->locations->listActive($facilityId),
            'episodes'     => $this->episodes->fetchBoard($facilityId),
            'locByEpisode' => $locByEpisode,
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
            }
            return;
        }

        if ($action === 'move_episode') {
            $episodeId = (int)($_POST['episode_id']   ?? 0);
            $pid       = (int)($_POST['pid']          ?? 0);
            $eidRaw    = (string)($_POST['eid']        ?? '');
            $eid       = is_numeric($eidRaw) ? (int)$eidRaw : null;
            $locIdRaw  = (string)($_POST['location_id'] ?? '');
            $locId     = is_numeric($locIdRaw) ? (int)$locIdRaw : null;
            $locCode   = trim((string)($_POST['location_code'] ?? '')) ?: null;
            $note      = trim((string)($_POST['note']          ?? '')) ?: null;

            if ($episodeId > 0 && $pid > 0) {
                $this->episodeLocations->moveEpisode(
                    $episodeId, $pid, $eid, $facilityId, $locId, $locCode, $userId, $note
                );
            }
        }
    }
}

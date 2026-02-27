<?php

namespace OpenEMR\Modules\Institutional\Submodule\AdtLite\Service;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Repository\LocationHistoryRepository;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service\AdtNotificationService;
use OpenEMR\Modules\Institutional\Submodule\Triage\Service\VitalsSchedulerService;

final class AdtService
{
    public function __construct(
        private readonly EpisodeRepository          $episodes,
        private readonly LocationHistoryRepository  $history,
        private readonly ?LocationRepository        $locations        = null,
        private readonly ?AdtNotificationService    $adt              = null,
        private readonly ?VitalsSchedulerService    $vitalsScheduler  = null
    ) {}

    public function assignLocation(
        int    $episodeId,
        int    $pid,
        ?int   $eid,
        int    $facilityId,
        ?int   $locationId,
        string $reason = 'ROOMED',
        ?int   $userId = null
    ): void {
        $now = date('Y-m-d H:i:s');
        $this->history->closeOpenHistory($episodeId, $now);
        $this->history->openHistory($pid, $eid, $facilityId, $episodeId, $locationId, $now, $reason);
        $this->episodes->updateLastStatus($episodeId, $now);

        // ── Auto-schedule vitals checks on rooming ────────────────────────────
        if ($this->vitalsScheduler !== null && $locationId !== null) {
            $this->vitalsScheduler->scheduleForEd($episodeId, $pid, $eid, $facilityId, $userId);
        }

        // ── A02 Transfer ──────────────────────────────────────────────────────
        if ($this->adt !== null) {
            $episode  = $this->episodes->fetchOne($episodeId);
            $location = null;
            if ($locationId !== null && $this->locations !== null) {
                $location = $this->locations->findById($locationId);
            }
            if ($episode !== null) {
                $this->adt->notifyTransfer($episode, $facilityId, $location);
            }
        }
    }
}



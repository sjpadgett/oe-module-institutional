<?php

namespace OpenEMR\Modules\Institutional\Submodule\ObsStay\Service;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Hl7Adt\Service\AdtNotificationService;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Service\ObsProtocolEngine;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Service\TaskService;
use OpenEMR\Modules\Institutional\Submodule\Triage\Service\VitalsSchedulerService;

final class ObsService
{
    public function __construct(
        private readonly EpisodeRepository       $episodes,
        private readonly ?TaskService            $taskService,
        private readonly ?ObsProtocolEngine      $protocolEngine,
        private readonly ?AdtNotificationService $adt             = null,
        private readonly ?VitalsSchedulerService $vitalsScheduler = null
    ) {}

    public function startObs(
        int    $episodeId,
        int    $pid,
        ?int   $eid,
        int    $facilityId,
        string $protocolKey,
        ?int   $userId
    ): void {
        // ── Apply protocol (creates plan + runway tasks) ──────────────────────
        $protocolHasVitals = false;
        if ($this->protocolEngine !== null) {
            // Check BEFORE applying so we know if protocol owns vitals tasks
            $protocolHasVitals = $this->protocolDefinesVitals($facilityId, $protocolKey);
            $this->protocolEngine->apply($episodeId, $pid, $eid, $facilityId, $protocolKey, $userId);
        }

        // ── Update episode type to OBS ────────────────────────────────────────
        $now = date('Y-m-d H:i:s');
        $this->episodes->setType($episodeId, 'OBS', $now);
        $this->episodes->appendStatusHistory($episodeId, 'OBS', $userId);

        // ── Auto-schedule vitals if protocol doesn't already cover them ───────
        // GENERAL_OBS and CHEST_PAIN both define VITALS_Q4H — scheduler skips.
        // Custom protocols without vitals tasks get them automatically.
        if ($this->vitalsScheduler !== null) {
            $this->vitalsScheduler->scheduleForObs(
                $episodeId, $pid, $eid, $facilityId, $userId, $protocolHasVitals
            );
        }

        // ── A01 Admit ─────────────────────────────────────────────────────────
        if ($this->adt !== null) {
            $episode = $this->episodes->fetchOne($episodeId);
            if ($episode !== null) {
                $this->adt->notifyAdmit($episode, $facilityId);
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Returns true if the named protocol defines any task type containing 'VITALS'.
     * Reads oei_protocol.definition_json directly — no side effects.
     */
    private function protocolDefinesVitals(int $facilityId, string $protocolKey): bool
    {
        if (!function_exists('sqlQuery')) return false;
        $row = sqlQuery(
            "SELECT definition_json FROM oei_protocol
             WHERE facility_id = ? AND protocol_key = ? LIMIT 1",
            [$facilityId, $protocolKey]
        );
        if (!$row || empty($row['definition_json'])) return false;
        $def = json_decode((string)$row['definition_json'], true);
        if (!is_array($def)) return false;
        foreach ((array)($def['tasks'] ?? []) as $task) {
            if (is_array($task) && stripos((string)($task['type'] ?? ''), 'VITALS') !== false) {
                return true;
            }
        }
        return false;
    }
}



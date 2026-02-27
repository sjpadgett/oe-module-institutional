<?php
namespace OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Service;

use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\Submodule\Tasks\Repository\TaskRepository;

final class ObsProtocolEngine
{
    public function __construct(
        private readonly ProtocolRepository $protos,
        private readonly ObsPlanRepository  $plans,
        private readonly ?TaskRepository    $tasks
    ) {}

    public function apply(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $protocolKey, ?int $userId
    ): void {
        $row = $this->protos->get($facilityId, $protocolKey);
        if (!$row) {
            $this->protos->ensureDefaultProtocols($facilityId, $userId);
            $row = $this->protos->get($facilityId, $protocolKey);
        }
        if (!$row) return;

        $def = json_decode((string)$row['definition_json'], true);
        if (!is_array($def)) return;

        $targetHours  = (int)($def['target_hours']  ?? 24);
        $runwayHours  = (int)($def['runway_hours']   ?? 6);
        $protocolJson = (string)$row['definition_json'];

        $this->plans->upsert($episodeId, $pid, $eid, $facilityId,
            $protocolKey, $targetHours, $runwayHours, $protocolJson, $userId);

        if ($this->tasks) {
            $plan = $this->plans->getByEpisode($episodeId);
            $from = $plan ? (string)($plan['start_datetime'] ?? null) : null;
            $this->generateOnlyRunway($episodeId, $pid, $eid, $facilityId, $def, $runwayHours, $userId, $from);
        }
    }

    public function generateOnlyRunway(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        array $definition, int $runwayHours, ?int $userId,
        ?string $fromDatetime = null
    ): int {
        if (!$this->tasks) return 0;
        $startTs   = $fromDatetime ? (strtotime($fromDatetime) ?: time()) : time();
        $windowEnd = time() + ($runwayHours * 3600);
        $generated = 0;
        $tasks     = $definition['tasks'] ?? [];

        foreach ($tasks as $td) {
            if (!is_array($td) || empty($td['type'])) continue;
            $type = (string)$td['type'];

            if (isset($td['every_minutes']) && is_numeric($td['every_minutes'])) {
                $interval = (int)$td['every_minutes'] * 60;
                if ($interval <= 0) continue;
                $t = $startTs + $interval;
                while ($t <= $windowEnd) {
                    $this->tasks->create($episodeId, $pid, $eid, $facilityId, $type, date('Y-m-d H:i:s', $t), $userId);
                    $generated++;
                    $t += $interval;
                }
            } elseif (isset($td['at_minutes']) && is_array($td['at_minutes'])) {
                foreach ($td['at_minutes'] as $m) {
                    if (!is_numeric($m)) continue;
                    $t = $startTs + ((int)$m * 60);
                    if ($t > $windowEnd) continue;
                    $this->tasks->create($episodeId, $pid, $eid, $facilityId, $type, date('Y-m-d H:i:s', $t), $userId);
                    $generated++;
                }
            }
        }
        return $generated;
    }
}



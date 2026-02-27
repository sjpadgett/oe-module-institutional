<?php
namespace OpenEMR\Modules\Institutional\Submodule\Throughput\Controller;

use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;

final class ThroughputController
{
    public function __construct(
        private readonly EpisodeEventRepository $events,
        private readonly DispositionRepository $dispos
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, string $start, string $end, array $episodes): array
    {
        if (empty($episodes)) {
            return ['rows' => [], 'summary' => $this->emptySummary()];
        }

        $episodeIds = array_map(fn($e) => (int)$e['id'], $episodes);
        $eventMap   = $this->events->firstEventMap($episodeIds);
        $dispoMap   = $this->dispos->fetchForEpisodes($episodeIds);

        $rows = [];
        $metrics = ['room' => [], 'provider' => [], 'decision' => [], 'depart' => [],
                    'bh_accepted' => [], 'bh_transport' => []];

        foreach ($episodes as $e) {
            $eid     = (int)$e['id'];
            $arrival = (string)($e['start_datetime'] ?? '');
            $arrTs   = $arrival ? strtotime($arrival) : 0;

            $evts = $eventMap[$eid] ?? [];
            $d    = $dispoMap[$eid] ?? [];

            $roomDt     = $evts['ROOM']       ?? $evts['ROOMED'] ?? null;
            $provDt     = $evts['PROVIDER']   ?? null;
            $decDt      = $evts['DECISION']   ?? ($d['decision_datetime'] ?? null);
            $depDt      = $evts['DEPART']     ?? ($d['depart_datetime']   ?? null);
            $bhAccDt    = $evts['BH_ACCEPTED']  ?? null;
            $bhTransDt  = $evts['BH_TRANSPORT'] ?? null;

            $row = [
                'episode_id'           => $eid,
                'pid'                  => $e['pid']   ?? '',
                'type'                 => $e['type']  ?? '',
                'arrival'              => $arrival,
                'disposition'          => $d['disposition_code'] ?? ($e['disposition'] ?? ''),
                'door_to_room_min'     => $this->minDiff($arrTs, $roomDt),
                'door_to_provider_min' => $this->minDiff($arrTs, $provDt),
                'door_to_decision_min' => $this->minDiff($arrTs, $decDt),
                'door_to_depart_min'   => $this->minDiff($arrTs, $depDt),
            ];
            $rows[] = $row;

            if ($row['door_to_room_min'] !== null)     $metrics['room'][]        = $row['door_to_room_min'];
            if ($row['door_to_provider_min'] !== null) $metrics['provider'][]    = $row['door_to_provider_min'];
            if ($row['door_to_decision_min'] !== null) $metrics['decision'][]    = $row['door_to_decision_min'];
            if ($row['door_to_depart_min'] !== null)   $metrics['depart'][]      = $row['door_to_depart_min'];
            if ($arrTs && $bhAccDt)   $metrics['bh_accepted'][]  = $this->minDiff($arrTs, $bhAccDt);
            if ($arrTs && $bhTransDt) $metrics['bh_transport'][] = $this->minDiff($arrTs, $bhTransDt);
        }

        $avg = fn(array $a): ?int => $a ? (int)round(array_sum($a) / count($a)) : null;

        return [
            'rows' => $rows,
            'summary' => [
                'count'                  => count($episodes),
                'avg_door_to_room'       => $avg($metrics['room']),
                'avg_door_to_provider'   => $avg($metrics['provider']),
                'avg_door_to_decision'   => $avg($metrics['decision']),
                'avg_door_to_depart'     => $avg($metrics['depart']),
                'avg_door_to_bh_accepted'  => $avg($metrics['bh_accepted']),
                'avg_door_to_bh_transport' => $avg($metrics['bh_transport']),
            ],
        ];
    }

    private function minDiff(int $arrTs, ?string $dt): ?int
    {
        if (!$arrTs || !$dt) return null;
        $ts = strtotime($dt);
        if (!$ts || $ts < $arrTs) return null;
        return (int)round(($ts - $arrTs) / 60);
    }

    private function emptySummary(): array
    {
        return ['count' => 0, 'avg_door_to_room' => null, 'avg_door_to_provider' => null,
                'avg_door_to_decision' => null, 'avg_door_to_depart' => null,
                'avg_door_to_bh_accepted' => null, 'avg_door_to_bh_transport' => null];
    }
}



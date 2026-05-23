<?php

/**
 * src/EmergencyDepartment/Submodule/Downtime/Service/DowntimeSnapshotService.php
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

namespace OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Downtime\Service;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\EpisodeLocationRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\BedMgmt\Repository\LocationRepository;
use OpenEMR\Modules\Institutional\EmergencyDepartment\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Tasks\Repository\TaskRepository;

/**
 * DowntimeSnapshotService
 *
 * Assembles a JSON-serialisable snapshot of all data the offline viewer needs.
 * Runs entirely through the existing repositories — no new SQL lives here.
 *
 * Snapshot structure
 * ------------------
 * {
 *   "generated":   "2026-03-01T14:22:00Z",
 *   "facility_id": 1,
 *   "episodes":    [...],   // enriched board rows (patient name, location, elapsed)
 *   "tasks":       [...],   // open tasks
 *   "locations":   [...],   // active physical locations
 *   "loc_map":     {...},   // episode_id (string) -> location row
 *   "diversion":   [...],   // per-service-line diversion status
 *   "settings":    {...}    // thresholds needed for colour-coding offline
 * }
 */
final class DowntimeSnapshotService
{
    public function __construct(
        private readonly EpisodeRepository         $episodes,
        private readonly TaskRepository            $tasks,
        private readonly LocationRepository        $locations,
        private readonly EpisodeLocationRepository $epLocations,
        private readonly DiversionRepository       $diversion,
        private readonly SettingsRepository        $settings
    ) {}

    /**
     * Build and return the full snapshot array.
     *
     * @return array<string,mixed>
     */
    public function build(int $facilityId): array
    {
        $episodeRows = $this->episodes->fetchBoard($facilityId);
        $taskRows    = $this->tasks->listOpenByFacility($facilityId);
        $locRows     = $this->locations->listActive($facilityId);
        $locCurrent  = $this->epLocations->listCurrentByFacility($facilityId);
        $divRows     = $this->diversion->listByFacility($facilityId);
        $cfg         = $this->settings->all($facilityId);

        // episode_id → location row
        $locMap = [];
        foreach ($locCurrent as $r) {
            $locMap[(string)(int)$r['episode_id']] = $r;
        }

        // patient names for all active episodes
        $patientNames = $this->fetchPatientNames($episodeRows);

        // enrich episodes
        $enriched = [];
        foreach ($episodeRows as $ep) {
            $epId = (int)$ep['id'];
            $pid  = (int)$ep['pid'];
            $ep['_patient_name']    = $patientNames[$pid]          ?? '';
            $ep['_location_code']   = (string)($locMap[(string)$epId]['code'] ?? '');
            $ep['_location_name']   = (string)($locMap[(string)$epId]['name'] ?? '');
            $ep['_elapsed_minutes'] = $this->elapsedMinutes((string)($ep['start_datetime'] ?? ''));
            $enriched[] = $ep;
        }

        return [
            'generated'   => gmdate('Y-m-d\TH:i:s\Z'),
            'facility_id' => $facilityId,
            'episodes'    => $enriched,
            'tasks'       => $taskRows,
            'locations'   => $locRows,
            'loc_map'     => $locMap,
            'diversion'   => $divRows,
            'settings'    => [
                'door_to_provider_target_min' => (int)($cfg['door_to_provider_target_min'] ?? 60),
                'lwbs_threshold_min'          => (int)($cfg['lwbs_threshold_min']          ?? 120),
                'door_to_room_target_min'     => (int)($cfg['door_to_room_target_min']     ?? 30),
                'boarding_alert_hours'        => (int)($cfg['boarding_alert_hours']        ?? 4),
            ],
        ];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Fetch patient first+last name for every pid referenced in the episode list.
     *
     * @param  array<int,array<string,mixed>> $episodes
     * @return array<int,string>  pid => "Lname, Fname"
     */
    private function fetchPatientNames(array $episodes): array
    {
        if (empty($episodes) || !function_exists('sqlStatement')) {
            return [];
        }
        $pids = array_unique(array_filter(array_map(
            static fn ($ep) => (int)($ep['pid'] ?? 0),
            $episodes
        )));
        if (empty($pids)) {
            return [];
        }
        $in  = implode(',', array_fill(0, count($pids), '?'));
        $res = sqlStatement(
            "SELECT pid, lname, fname FROM patient_data WHERE pid IN ({$in})",
            array_values($pids)
        );
        $map = [];
        while ($row = sqlFetchArray($res)) {
            $map[(int)$row['pid']] = trim((string)$row['lname'] . ', ' . (string)$row['fname']);
        }
        return $map;
    }

    private function elapsedMinutes(string $start): int
    {
        $ts = strtotime($start);
        return $ts ? (int)floor((time() - $ts) / 60) : 0;
    }
}




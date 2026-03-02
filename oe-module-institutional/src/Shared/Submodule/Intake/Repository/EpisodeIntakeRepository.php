<?php
namespace OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Repository;

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;

final class EpisodeIntakeRepository
{
    public function __construct(private readonly EpisodeRepository $episodes) {}

    public function create(
        int $pid, int $facilityId, string $arrivalMode,
        ?int $esi, ?string $chiefComplaint, ?string $triageNote, ?int $userId
    ): int {
        if (!function_exists('sqlStatement') || !function_exists('sqlQuery')) return 0;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_episode
               (pid, facility_id, type, start_datetime, chief_complaint, acuity_esi,
                arrival_mode, triage_note, last_status_update, created_by_user_id, created_datetime)
             VALUES (?, ?, 'ED', ?, ?, ?, ?, ?, ?, ?, ?)",
            [$pid, $facilityId, $now, $chiefComplaint, $esi, $arrivalMode, $triageNote, $now, $userId, $now]
        );
        $idRow = sqlQuery("SELECT LAST_INSERT_ID() AS id");
        $episodeId = (int)($idRow['id'] ?? 0);
        if ($episodeId) {
            $this->episodes->appendStatusHistory($episodeId, 'WAITING', $userId, null, $now);
        }
        return $episodeId;
    }
}

<?php

/**
 * src/Submodule/BhBoarding/Repository/BhBoardingRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\BhBoarding\Repository;

final class BhBoardingRepository
{
    /** @return array<string,mixed>|null */
    public function getByEpisode(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery("SELECT * FROM oei_bh_boarding WHERE episode_id=? LIMIT 1", [$episodeId]);
        return $row ?: null;
    }

    public function upsert(
        int $episodeId, int $pid, ?int $eid, int $facilityId,
        string $placementStatus, ?string $acceptingFacility,
        ?string $acceptedDatetime, ?string $transportMethod, ?string $transportDatetime,
        ?string $legalStatus, ?string $suicideRisk, ?string $violenceRisk,
        int $emtalaComplete, ?string $checklistJson, ?string $notes, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_bh_boarding
               (episode_id,pid,eid,facility_id,placement_status,accepting_facility,
                accepted_datetime,transport_method,transport_datetime,
                legal_status,suicide_risk,violence_risk,emtala_complete,checklist_json,notes,
                updated_by_user_id,updated_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               placement_status=VALUES(placement_status), accepting_facility=VALUES(accepting_facility),
               accepted_datetime=VALUES(accepted_datetime), transport_method=VALUES(transport_method),
               transport_datetime=VALUES(transport_datetime), legal_status=VALUES(legal_status),
               suicide_risk=VALUES(suicide_risk), violence_risk=VALUES(violence_risk),
               emtala_complete=VALUES(emtala_complete), checklist_json=VALUES(checklist_json),
               notes=VALUES(notes), updated_by_user_id=VALUES(updated_by_user_id),
               updated_datetime=VALUES(updated_datetime)",
            [$episodeId,$pid,$eid,$facilityId,$placementStatus,$acceptingFacility,
             $acceptedDatetime,$transportMethod,$transportDatetime,
             $legalStatus,$suicideRisk,$violenceRisk,$emtalaComplete,$checklistJson,$notes,
             $userId,$now]
        );
    }
}






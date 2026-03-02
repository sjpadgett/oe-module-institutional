<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Repository;

/**
 * ResidentIntakeRepository
 *
 * Handles AL admission. Creates:
 *   1. oei_episode (type='AL', status='ACTIVE')
 *   2. oei_al_episode overlay (room, unit, care_level, fall_risk)
 *   3. A form_encounter record — this becomes the encounter_id for
 *      care plan entries (form_care_plan uses encounter FK).
 *
 * The form_encounter creation ensures care plan entries written during
 * the AL stay are discoverable via standard OpenEMR chart queries.
 */
final class ResidentIntakeRepository
{
    /**
     * Create a new AL episode and return the episode_id.
     * Returns 0 on failure.
     */
    public function admitResident(
        int    $pid,
        int    $facilityId,
        int    $userId,
        string $room,
        string $unit,
        string $careLevel,
        string $fallRiskLevel,
        int    $fallRiskScore,
        string $admitReason,
        string $admitDatetime
    ): int {
        if (!function_exists('sqlInsert')) { return 0; }

        // 1. Create OpenEMR encounter for care plan anchoring
        $encounterId = sqlInsert(
            "INSERT INTO form_encounter
                (date, onset_date, reason, facility, pid, provider_id,
                 facility_id, billing_facility, encounter, pos_code)
             VALUES (?,?,?,'Assisted Living',?,?,?,?,FLOOR(RAND()*900000+100000),'60')",
            [
                $admitDatetime, $admitDatetime,
                $admitReason ?: 'AL Admission',
                $pid, $userId, $facilityId, $facilityId,
            ]
        );

        // 2. Create oei_episode
        $episodeId = sqlInsert(
            "INSERT INTO oei_episode
                (pid, facility_id, status, type, start_datetime,
                 created_by_user_id, created_datetime)
             VALUES (?,?,'ACTIVE','AL',?,?,NOW())",
            [$pid, $facilityId, $admitDatetime, $userId]
        );

        if (!$episodeId) { return 0; }

        // 3. Create oei_al_episode overlay
        sqlInsert(
            "INSERT INTO oei_al_episode
                (episode_id, pid, facility_id, encounter_id, room, unit,
                 care_level, fall_risk_level, fall_risk_score, admit_reason,
                 created_datetime)
             VALUES (?,?,?,?,?,?,?,?,?,?,NOW())",
            [
                $episodeId, $pid, $facilityId, $encounterId,
                $room, $unit, $careLevel, $fallRiskLevel, $fallRiskScore,
                $admitReason,
            ]
        );

        return (int)$episodeId;
    }

    /** Check if a patient already has an active AL episode at this facility. */
    public function hasActiveEpisode(int $pid, int $facilityId): bool
    {
        if (!function_exists('sqlQuery')) { return false; }
        $r = sqlQuery(
            "SELECT COUNT(*) AS c FROM oei_episode
             WHERE pid = ? AND facility_id = ? AND status = 'ACTIVE' AND type = 'AL'",
            [$pid, $facilityId]
        );
        return (int)($r['c'] ?? 0) > 0;
    }
}

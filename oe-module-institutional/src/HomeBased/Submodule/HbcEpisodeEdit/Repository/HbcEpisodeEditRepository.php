<?php

/**
 * src/HomeBased/Submodule/HbcEpisodeEdit/Repository/HbcEpisodeEditRepository.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcEpisodeEdit\Repository;

/**
 * HbcEpisodeEditRepository — reads/writes editable fields on oei_hbc_episode.
 *
 * This is the only write boundary for episode-level data after intake.
 * Visit-level data is handled by HbcVisitRepository.
 */
final class HbcEpisodeEditRepository
{
    /** @return array<string,mixed>|null */
    public function fetchEditable(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT
                e.id            AS episode_id,
                e.pid,
                e.facility_id,
                e.status        AS episode_status,
                pd.fname,
                pd.lname,
                hbc.referral_status,
                hbc.urgency,
                hbc.referral_source,
                hbc.referral_reason,
                hbc.primary_diagnosis,
                hbc.primary_icd10,
                hbc.primary_clinician_user_id,
                hbc.service_address_line1,
                hbc.service_address_line2,
                hbc.service_city,
                hbc.service_state_province,
                hbc.service_postal_code,
                hbc.service_country,
                hbc.access_notes,
                hbc.caregiver_name,
                hbc.caregiver_phone,
                hbc.caregiver_relationship,
                hbc.payer_name,
                hbc.authorization_notes,
                hbc.cert_period_start,
                hbc.cert_period_end,
                hbc.authorized_visits_per_week,
                CONCAT(COALESCE(u.fname,''),' ',COALESCE(u.lname,'')) AS clinician_name
             FROM oei_episode e
             JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             JOIN patient_data pd     ON pd.pid = e.pid
             LEFT JOIN users u        ON u.id = hbc.primary_clinician_user_id AND u.active = 1
             WHERE e.id = ? AND e.type = 'HBC'
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }

    public function update(int $episodeId, array $data): bool
    {
        if (!function_exists('sqlStatement')) {
            return false;
        }

        sqlStatement(
            "UPDATE oei_hbc_episode SET
                urgency                   = ?,
                referral_source           = ?,
                referral_reason           = ?,
                primary_diagnosis         = ?,
                primary_icd10             = ?,
                primary_clinician_user_id = ?,
                service_address_line1     = ?,
                service_address_line2     = ?,
                service_city              = ?,
                service_state_province    = ?,
                service_postal_code       = ?,
                service_country           = ?,
                access_notes              = ?,
                caregiver_name            = ?,
                caregiver_phone           = ?,
                caregiver_relationship    = ?,
                payer_name                = ?,
                authorization_notes       = ?,
                cert_period_start         = ?,
                cert_period_end           = ?,
                authorized_visits_per_week = ?
             WHERE episode_id = ?",
            [
                $data['urgency'],
                $data['referral_source'],
                $data['referral_reason'],
                $data['primary_diagnosis'],
                $data['primary_icd10'],
                $data['primary_clinician_user_id'] > 0 ? $data['primary_clinician_user_id'] : null,
                $data['service_address_line1'],
                $data['service_address_line2'],
                $data['service_city'],
                $data['service_state_province'],
                $data['service_postal_code'],
                $data['service_country'],
                $data['access_notes'],
                $data['caregiver_name'],
                $data['caregiver_phone'],
                $data['caregiver_relationship'],
                $data['payer_name'],
                $data['authorization_notes'],
                $data['cert_period_start'] ?: null,
                $data['cert_period_end'] ?: null,
                $data['authorized_visits_per_week'] !== null && $data['authorized_visits_per_week'] !== ''
                    ? (int) $data['authorized_visits_per_week'] : null,
                $episodeId,
            ]
        );

        return true;
    }

    /** @return array<int,array{id:int,name:string}> */
    public function listClinicians(): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT id, CONCAT(fname,' ',lname) AS name
             FROM users WHERE active=1 AND authorized=1
             ORDER BY lname ASC, fname ASC"
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = ['id' => (int) $r['id'], 'name' => trim((string) ($r['name'] ?? ''))];
        }
        return $rows;
    }
}




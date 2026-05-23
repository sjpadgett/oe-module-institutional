<?php

/**
 * src/Core/Service/EncounterResolver.php
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

namespace OpenEMR\Modules\Institutional\Core\Service;

/**
 * EncounterResolver — single point for resolving any episode to its
 * OpenEMR encounter NUMBER (form_encounter.encounter column).
 *
 * IMPORTANT: Returns the encounter NUMBER from the sequences table —
 * NOT form_encounter.id (the auto-increment row PK).
 * The encounter number is what form_care_plan.encounter,
 * form_clinical_notes.encounter, and forms.encounter all reference.
 *
 * Storage per episode type:
 *   AL  → oei_al_episode.encounter_id  (set at admission)
 *   IP  → oei_ip_episode.encounter_id  (set at admission)
 *   ED / OBS / BH → oei_episode.eid    (set at OpenEMR patient intake)
 *   HBC           → oei_hbc_episode.encounter_id  (set at referral intake)
 *
 * Returns null when no encounter has been linked yet.
 * Callers must handle null gracefully (empty panel + informational message).
 */
final class EncounterResolver
{
    /**
     * @param int    $episodeId
     * @param string $episodeType  'AL' | 'IP' | 'ED' | 'OBS' | 'BH' | 'HBC'
     * @return int|null  encounter NUMBER, or null if not yet linked
     */
    public function resolve(int $episodeId, string $episodeType): ?int
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }

        return match (strtoupper(trim($episodeType))) {
            'AL'            => $this->resolveAl($episodeId),
            'IP'            => $this->resolveIp($episodeId),
            'HBC'           => $this->resolveHbc($episodeId),
            'ED','OBS','BH' => $this->resolveEid($episodeId),
            default         => null,
        };
    }

    private function resolveAl(int $episodeId): ?int
    {
        $r = sqlQuery(
            'SELECT encounter_id FROM oei_al_episode WHERE episode_id = ? LIMIT 1',
            [$episodeId]
        );
        return ($r && !empty($r['encounter_id'])) ? (int)$r['encounter_id'] : null;
    }

    private function resolveIp(int $episodeId): ?int
    {
        $r = sqlQuery(
            'SELECT encounter_id FROM oei_ip_episode WHERE episode_id = ? LIMIT 1',
            [$episodeId]
        );
        return ($r && !empty($r['encounter_id'])) ? (int)$r['encounter_id'] : null;
    }

    private function resolveHbc(int $episodeId): ?int
    {
        $r = sqlQuery(
            'SELECT encounter_id FROM oei_hbc_episode WHERE episode_id = ? LIMIT 1',
            [$episodeId]
        );
        return ($r && !empty($r['encounter_id'])) ? (int)$r['encounter_id'] : null;
    }

    private function resolveEid(int $episodeId): ?int
    {
        $r = sqlQuery(
            'SELECT eid FROM oei_episode WHERE id = ? LIMIT 1',
            [$episodeId]
        );
        return ($r && !empty($r['eid'])) ? (int)$r['eid'] : null;
    }
}







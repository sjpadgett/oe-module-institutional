<?php

/**
 * src/Inpatient/Submodule/IpFallRisk/Controller/IpFallRiskController.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpFallRisk\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Controller\FallRiskController;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Repository\FallRiskRepository;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

/**
 * IpFallRiskController
 *
 * Wraps FallRiskController for inpatient episodes.
 * Key differences from AL:
 *   - Patient context query reads oei_ip_episode (bed/unit/attending) instead of oei_al_episode.
 *   - FallRiskRepository::record() still writes oei_fall_risk_assessment correctly.
 *     The UPDATE oei_al_episode at the end of that method silently no-ops for IP episodes
 *     (no matching row) — this is acceptable; the IP profile reads directly from
 *     oei_fall_risk_assessment via IpProfileRepository::fetchFallRiskSummary().
 *   - Reassessment threshold stays at 28 days (same clinical standard).
 */
final class IpFallRiskController
{
    private FallRiskController $inner;

    public function __construct()
    {
        $this->inner = new FallRiskController(new FallRiskRepository());
    }

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $facilityId, ?int $userId): array
    {
        // Let the shared controller handle POST + history + scoring
        $data = $this->inner->handle($episodeId, $facilityId, $userId);

        // Override patient context with IP episode data
        $data['patient'] = $this->fetchIpPatientContext($episodeId);

        return $data;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function fetchIpPatientContext(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT e.id, e.pid,
                    pd.fname, pd.lname,
                    COALESCE(ip.bed,  '') AS room,
                    COALESCE(ip.unit, '') AS unit,
                    'LOW'                 AS current_risk_level,
                    0                     AS current_risk_score
             FROM   oei_episode e
             INNER  JOIN patient_data pd ON pd.pid = e.pid
             LEFT   JOIN oei_ip_episode ip ON ip.episode_id = e.id
             WHERE  e.id = ? LIMIT 1",
            [$episodeId]
        );
        if (!$row) {
            return null;
        }
        // Overlay actual latest assessment if it exists
        $latest = sqlQuery(
            "SELECT risk_level, total_score
             FROM   oei_fall_risk_assessment
             WHERE  episode_id = ?
             ORDER  BY assessed_datetime DESC, id DESC
             LIMIT  1",
            [$episodeId]
        );
        if ($latest) {
            $row['current_risk_level'] = (string)$latest['risk_level'];
            $row['current_risk_score'] = (int)$latest['total_score'];
        }
        return $row;
    }
}




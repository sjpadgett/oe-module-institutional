<?php

/**
 * src/HomeBased/Submodule/HbcFallRisk/Controller/HbcFallRiskController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcFallRisk\Controller;

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Controller\FallRiskController;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Repository\FallRiskRepository;

/**
 * HbcFallRiskController
 *
 * Wraps shared FallRiskController for Home-Based Care episodes.
 * Identical pattern to IpFallRiskController — only the patient context
 * query differs (reads oei_hbc_episode instead of oei_ip_episode).
 *
 * FallRiskRepository::record() writes to oei_fall_risk_assessment correctly.
 * The trailing UPDATE oei_al_episode silently no-ops for HBC episodes —
 * acceptable, as HBC profile reads fall risk directly from
 * oei_fall_risk_assessment via HbcProfileRepository.
 */
final class HbcFallRiskController
{
    private FallRiskController $inner;

    public function __construct()
    {
        $this->inner = new FallRiskController(new FallRiskRepository());
    }

    /** @return array<string,mixed> */
    public function handle(int $episodeId, int $facilityId, ?int $userId): array
    {
        $data            = $this->inner->handle($episodeId, $facilityId, $userId);
        $data['patient'] = $this->fetchHbcPatientContext($episodeId);
        return $data;
    }

    /** @return array<string,mixed>|null */
    private function fetchHbcPatientContext(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) { return null; }
        $row = sqlQuery(
            "SELECT e.id, e.pid,
                    pd.fname, pd.lname,
                    COALESCE(hbc.service_city,'')           AS room,
                    COALESCE(hbc.service_state_province,'') AS unit,
                    'LOW' AS current_risk_level,
                    0     AS current_risk_score
             FROM   oei_episode e
             INNER  JOIN patient_data pd     ON pd.pid = e.pid
             LEFT   JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             WHERE  e.id = ? LIMIT 1",
            [$episodeId]
        );
        if (!$row) { return null; }
        $latest = sqlQuery(
            "SELECT risk_level, total_score
             FROM   oei_fall_risk_assessment
             WHERE  episode_id = ?
             ORDER  BY assessed_datetime DESC, id DESC LIMIT 1",
            [$episodeId]
        );
        if ($latest) {
            $row['current_risk_level'] = (string)$latest['risk_level'];
            $row['current_risk_score'] = (int)$latest['total_score'];
        }
        return $row;
    }
}




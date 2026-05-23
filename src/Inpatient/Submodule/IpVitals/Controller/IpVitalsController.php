<?php

/**
 * src/Inpatient/Submodule/IpVitals/Controller/IpVitalsController.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpVitals\Controller;

use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Controller\SharedVitalsController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain\VitalsThresholdConfig;

/**
 * IpVitalsController
 *
 * Builds the IP-specific VitalsThresholdConfig from oei_settings
 * (tighter acute norms: BP 180/80, HR 120/45, SpO2 90/94, GCS enabled),
 * delegates all recording and alerting to SharedVitalsController,
 * then fills in the IP patient context (bed/unit from oei_ip_episode).
 */
final class IpVitalsController
{
    private SharedVitalsController $shared;
    private SettingsRepository     $settings;

    public function __construct(
        ?SharedVitalsController $shared   = null,
        ?SettingsRepository     $settings = null
    ) {
        $this->shared   = $shared   ?? new SharedVitalsController();
        $this->settings = $settings ?? new SettingsRepository();
    }

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $cfg  = $this->buildConfig($facilityId);
        $data = $this->shared->handle($episodeId, $pid, $facilityId, $userId, $cfg);

        // IP patient context — bed and unit from oei_ip_episode
        $data['patient']    = $this->fetchPatientContext($episodeId);
        $data['thresholds'] = $this->configToThresholdArray($cfg);

        return $data;
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function buildConfig(int $facilityId): VitalsThresholdConfig
    {
        $cfg = $this->settings->all($facilityId);

        return VitalsThresholdConfig::forIp(
            bpHigh:       (int)($cfg['ip_bp_systolic_high'] ?? 180),
            bpLow:        (int)($cfg['ip_bp_systolic_low']  ?? 80),
            hrHigh:       (int)($cfg['ip_hr_high']          ?? 120),
            hrLow:        (int)($cfg['ip_hr_low']           ?? 45),
            spo2Critical: (int)($cfg['ip_spo2_critical']    ?? 90),
            spo2Low:      (int)($cfg['ip_spo2_low']         ?? 94),
        );
    }

    /**
     * Expose thresholds as the same array shape the IP vitals page already
     * uses for its colour-coded history table (no page change required).
     *
     * @return array{bp_high:int,bp_low:int,hr_high:int,hr_low:int,spo2_critical:int,spo2_low:int}
     */
    private function configToThresholdArray(VitalsThresholdConfig $cfg): array
    {
        return [
            'bp_high'       => $cfg->bpHigh,
            'bp_low'        => $cfg->bpLow,
            'hr_high'       => $cfg->hrHigh,
            'hr_low'        => $cfg->hrLow,
            'spo2_critical' => $cfg->spo2Critical,
            'spo2_low'      => $cfg->spo2Low,
        ];
    }

    /** @return array<string,mixed>|null */
    private function fetchPatientContext(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT e.id, e.pid, pd.fname, pd.lname,
                    COALESCE(ip.bed,  '') AS room,
                    COALESCE(ip.unit, '') AS unit
             FROM   oei_episode e
             INNER  JOIN patient_data pd ON pd.pid = e.pid
             LEFT   JOIN oei_ip_episode ip ON ip.episode_id = e.id
             WHERE  e.id = ? LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }
}




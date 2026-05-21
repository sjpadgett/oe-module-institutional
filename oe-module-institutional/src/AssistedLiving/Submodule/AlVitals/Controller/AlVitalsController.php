<?php

/**
 * src/AssistedLiving/Submodule/AlVitals/Controller/AlVitalsController.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Controller;

use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Controller\SharedVitalsController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain\VitalsThresholdConfig;

/**
 * AlVitalsController
 *
 * Builds the AL-specific VitalsThresholdConfig from oei_settings,
 * delegates all recording and alerting to SharedVitalsController,
 * then fills in the AL patient context (room/unit from oei_al_episode).
 */
final class AlVitalsController
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

        // AL patient context — room and unit from oei_al_episode
        $data['patient'] = $this->fetchPatientContext($episodeId);

        return $data;
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function buildConfig(int $facilityId): VitalsThresholdConfig
    {
        $cfg = $this->settings->all($facilityId);

        return VitalsThresholdConfig::forAl(
            bpHigh:           (int)  ($cfg['al_bp_systolic_high']     ?? 160),
            bpLow:            (int)  ($cfg['al_bp_systolic_low']      ?? 90),
            hrHigh:           (int)  ($cfg['al_hr_high']              ?? 110),
            hrLow:            (int)  ($cfg['al_hr_low']               ?? 50),
            spo2Critical:     (int)  ($cfg['al_spo2_critical']        ?? 93),
            spo2Low:          (int)  ($cfg['al_spo2_low']             ?? 96),
            weightGainAlertKg:(float)($cfg['al_weight_gain_alert_kg'] ?? 0.9),
        );
    }

    /** @return array<string,mixed>|null */
    private function fetchPatientContext(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT e.id, e.pid, pd.fname, pd.lname,
                    COALESCE(ale.room,'') AS room,
                    COALESCE(ale.unit,'') AS unit
             FROM   oei_episode e
             INNER  JOIN patient_data pd ON pd.pid = e.pid
             LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE  e.id = ? LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }
}




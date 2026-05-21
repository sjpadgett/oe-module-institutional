<?php

/**
 * src/HomeBased/Submodule/HbcVitals/Controller/HbcVitalsController.php
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

namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVitals\Controller;

use OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository\SettingsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Controller\SharedVitalsController;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain\VitalsThresholdConfig;

/**
 * HbcVitalsController
 *
 * Builds an HBC-specific VitalsThresholdConfig — same AL-derived norms
 * (home-based care patient population matches residential norms, not
 * acute inpatient norms). Reads the same al_* settings keys as AL.
 *
 * Delegates all recording and alerting to SharedVitalsController,
 * then fills in the HBC patient context (service address / clinician
 * from oei_hbc_episode).
 *
 * No longer wraps AlVitalsController — both now share the same neutral
 * SharedVitalsController without either track owning the other.
 */
final class HbcVitalsController
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

        // HBC patient context — service city/state from oei_hbc_episode
        $data['patient'] = $this->fetchPatientContext($episodeId);

        return $data;
    }

    // ── Private ───────────────────────────────────────────────────────────

    private function buildConfig(int $facilityId): VitalsThresholdConfig
    {
        // HBC uses the same al_* settings keys — clinical norms match AL
        $cfg = $this->settings->all($facilityId);

        return VitalsThresholdConfig::forHbc(
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
            "SELECT e.id, e.pid,
                    pd.fname, pd.lname,
                    COALESCE(hbc.service_city,'')           AS room,
                    COALESCE(hbc.service_state_province,'') AS unit
             FROM   oei_episode e
             JOIN   patient_data pd     ON pd.pid = e.pid
             LEFT   JOIN oei_hbc_episode hbc ON hbc.episode_id = e.id
             WHERE  e.id = ? LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }
}




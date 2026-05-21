<?php

/**
 * src/Shared/Submodule/Vitals/Domain/VitalsThresholdConfig.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain;

/**
 * VitalsThresholdConfig
 *
 * Immutable value object carrying all clinical alert thresholds and
 * feature flags for one track's vitals session.
 *
 * Each track controller builds this from its own oei_settings keys
 * and passes it to SharedVitalsController. The shared layer never
 * reads oei_settings directly — thresholds are always injected.
 *
 * Universal thresholds (RR, Temp) are constants on VitalsAlertService
 * and are not configurable per facility because they have no clinical
 * justification for per-setting variation.
 *
 * Track defaults:
 *   AL  — bp 160/90, hr 110/50, spo2 93/96, weightGain 0.9 kg, no GCS
 *   IP  — bp 180/80, hr 120/45, spo2 90/94, no weight gain, GCS enabled
 *   HBC — same as AL (home setting matches residential norms)
 */
final class VitalsThresholdConfig
{
    public function __construct(
        // Blood pressure (systolic)
        public readonly int   $bpHigh,
        public readonly int   $bpLow,

        // Heart rate (bpm)
        public readonly int   $hrHigh,
        public readonly int   $hrLow,

        // Pulse oximetry
        public readonly int   $spo2Critical,   // red alert
        public readonly int   $spo2Low,         // yellow warning

        // Weight gain threshold — 0.0 disables the alert entirely
        // Used for AL/HBC CHF fluid-retention monitoring
        public readonly float $weightGainAlertKg = 0.0,

        // GCS monitoring — enabled for IP acute care only
        public readonly bool  $showGcs = false,

        // arrival_mode tag written to oei_triage
        // ED triage uses WALK_IN / EMS; periodic monitoring uses PERIODIC
        public readonly string $arrivalMode = 'PERIODIC',
    ) {}

    // ── Named constructors for each track ────────────────────────────────

    /**
     * AL defaults — frail elderly residential norms.
     * Overridden by oei_settings al_* keys when available.
     */
    public static function forAl(
        int   $bpHigh          = 160,
        int   $bpLow           = 90,
        int   $hrHigh          = 110,
        int   $hrLow           = 50,
        int   $spo2Critical    = 93,
        int   $spo2Low         = 96,
        float $weightGainAlertKg = 0.9
    ): self {
        return new self(
            bpHigh:            $bpHigh,
            bpLow:             $bpLow,
            hrHigh:            $hrHigh,
            hrLow:             $hrLow,
            spo2Critical:      $spo2Critical,
            spo2Low:           $spo2Low,
            weightGainAlertKg: $weightGainAlertKg,
            showGcs:           false,
            arrivalMode:       'PERIODIC',
        );
    }

    /**
     * IP defaults — tighter acute clinical norms.
     * Overridden by oei_settings ip_* keys when available.
     */
    public static function forIp(
        int $bpHigh       = 180,
        int $bpLow        = 80,
        int $hrHigh       = 120,
        int $hrLow        = 45,
        int $spo2Critical = 90,
        int $spo2Low      = 94
    ): self {
        return new self(
            bpHigh:            $bpHigh,
            bpLow:             $bpLow,
            hrHigh:            $hrHigh,
            hrLow:             $hrLow,
            spo2Critical:      $spo2Critical,
            spo2Low:           $spo2Low,
            weightGainAlertKg: 0.0,    // IP does not track weight gain
            showGcs:           true,
            arrivalMode:       'PERIODIC',
        );
    }

    /**
     * HBC defaults — same clinical norms as AL.
     * Home-based care patient population matches residential AL norms,
     * not acute inpatient norms.
     */
    public static function forHbc(
        int   $bpHigh          = 160,
        int   $bpLow           = 90,
        int   $hrHigh          = 110,
        int   $hrLow           = 50,
        int   $spo2Critical    = 93,
        int   $spo2Low         = 96,
        float $weightGainAlertKg = 0.9
    ): self {
        return new self(
            bpHigh:            $bpHigh,
            bpLow:             $bpLow,
            hrHigh:            $hrHigh,
            hrLow:             $hrLow,
            spo2Critical:      $spo2Critical,
            spo2Low:           $spo2Low,
            weightGainAlertKg: $weightGainAlertKg,
            showGcs:           false,
            arrivalMode:       'PERIODIC',
        );
    }
}




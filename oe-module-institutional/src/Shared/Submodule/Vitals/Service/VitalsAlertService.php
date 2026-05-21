<?php

/**
 * src/Shared/Submodule/Vitals/Service/VitalsAlertService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain\VitalsThresholdConfig;

/**
 * VitalsAlertService
 *
 * Pure function: vitals values + config → clinical alert strings.
 * No database access. No side effects. Fully testable.
 *
 * Universal thresholds (no per-facility variation clinically justified):
 *   RR:   < 8 or > 24 breaths/min
 *   Temp: < 96.0°F or > 100.4°F (fever threshold for all institutional settings)
 *
 * Track-specific thresholds come from VitalsThresholdConfig:
 *   BP systolic, HR, SpO2 — differ between AL/HBC and IP
 *
 * Track-specific features:
 *   Weight gain alert — AL and HBC only (CHF/fluid retention watch)
 *   GCS alert         — IP only
 */
final class VitalsAlertService
{
    // ── Universal thresholds — no per-facility variation ─────────────────
    private const RR_LOW    = 8;
    private const RR_HIGH   = 24;
    private const TEMP_LOW  = 96.0;
    private const TEMP_HIGH = 100.4;
    private const GCS_CRITICAL = 8;

    /**
     * Generate clinical alerts for a single vitals entry.
     *
     * @param array{
     *   bp_systolic:  int|null,
     *   bp_diastolic: int|null,
     *   hr:           int|null,
     *   rr:           int|null,
     *   temp_f:       float|null,
     *   spo2:         int|null,
     *   weight_kg:    float|null,
     *   pain_score:   int|null,
     *   gcs:          int|null,
     * } $vitals
     *
     * @param float[] $prevWeights  Previous weight readings (oldest→newest) for
     *                              weight-gain delta check. Pass [] when not applicable.
     *
     * @return string[]  Translated alert messages ready for display.
     */
    public function generate(
        array                $vitals,
        VitalsThresholdConfig $cfg,
        array                $prevWeights = []
    ): array {
        $alerts = [];

        $bp  = $vitals['bp_systolic']  ?? null;
        $dbp = $vitals['bp_diastolic'] ?? null;
        $hr  = $vitals['hr']           ?? null;
        $rr  = $vitals['rr']           ?? null;
        $tmp = $vitals['temp_f']       ?? null;
        $o2  = $vitals['spo2']         ?? null;
        $wt  = $vitals['weight_kg']    ?? null;
        $gcs = $vitals['gcs']          ?? null;

        // ── Blood pressure ────────────────────────────────────────────────
        if ($bp !== null) {
            if ($bp > $cfg->bpHigh || $bp < $cfg->bpLow) {
                $dia = $dbp !== null ? "/{$dbp}" : '';
                $alerts[] = xlt('Blood pressure out of range:') . " {$bp}{$dia} mmHg";
            }
        }

        // ── Heart rate ────────────────────────────────────────────────────
        if ($hr !== null && ($hr > $cfg->hrHigh || $hr < $cfg->hrLow)) {
            $alerts[] = xlt('Heart rate out of range:') . " {$hr} bpm";
        }

        // ── Respiratory rate — universal thresholds ───────────────────────
        if ($rr !== null && ($rr > self::RR_HIGH || $rr < self::RR_LOW)) {
            $alerts[] = xlt('Respiratory rate out of range:') . " {$rr} /min";
        }

        // ── Temperature — universal thresholds ───────────────────────────
        if ($tmp !== null && ($tmp > self::TEMP_HIGH || $tmp < self::TEMP_LOW)) {
            $alerts[] = xlt('Temperature out of range:') . " {$tmp}°F";
        }

        // ── SpO2 — two-level alert (critical vs low) ──────────────────────
        if ($o2 !== null) {
            if ($o2 < $cfg->spo2Critical) {
                $alerts[] = xlt('SpO₂ critically low:') . " {$o2}%";
            } elseif ($o2 < $cfg->spo2Low) {
                $alerts[] = xlt('SpO₂ below normal:') . " {$o2}%";
            }
        }

        // ── Weight gain alert (AL/HBC — CHF/fluid retention watch) ────────
        if ($cfg->weightGainAlertKg > 0.0 && $wt !== null && count($prevWeights) >= 1) {
            $prevWeight = end($prevWeights);  // most recent previous reading
            if ($prevWeight !== false && ($wt - $prevWeight) >= $cfg->weightGainAlertKg) {
                $gain = number_format($wt - $prevWeight, 1);
                $alerts[] = xlt('Weight gain exceeds threshold since last check:')
                    . " +{$gain} kg — " . xlt('assess for fluid retention.');
            }
        }

        // ── GCS alert (IP only) ───────────────────────────────────────────
        if ($cfg->showGcs && $gcs !== null && $gcs <= self::GCS_CRITICAL) {
            $alerts[] = xlt('GCS critically low:') . " {$gcs}/15 — " . xlt('consider airway management.');
        }

        return $alerts;
    }
}




<?php

/**
 * src/AssistedLiving/Domain/FallRiskLevel.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Domain;

/**
 * FallRiskLevel — Morse Fall Scale risk stratification.
 *
 * Morse Fall Scale total score ranges (standard cut-offs):
 *   0–24   → Low Risk
 *   25–44  → Moderate Risk
 *   45+    → High Risk
 *
 * Stored as the computed tier, not the raw score, to keep the board
 * fast. The raw Morse score lives in oei_al_episode.fall_risk_score.
 */
final class FallRiskLevel
{
    public const LOW      = 'LOW';
    public const MODERATE = 'MODERATE';
    public const HIGH     = 'HIGH';

    private const LABELS = [
        self::LOW      => 'Low Risk',
        self::MODERATE => 'Moderate Risk',
        self::HIGH     => 'High Risk',
    ];

    private const BADGE_CLASSES = [
        self::LOW      => 'success',
        self::MODERATE => 'warning',
        self::HIGH     => 'danger',
    ];

    private const MORSE_THRESHOLDS = [
        self::LOW      => [0, 24],
        self::MODERATE => [25, 44],
        self::HIGH     => [45, PHP_INT_MAX],
    ];

    public static function fromMorseScore(int $score): string
    {
        foreach (self::MORSE_THRESHOLDS as $level => [$min, $max]) {
            if ($score >= $min && $score <= $max) {
                return $level;
            }
        }
        return self::LOW;
    }

    public static function label(string $level): string
    {
        return self::LABELS[$level] ?? 'Unknown';
    }

    public static function badge(string $level): string
    {
        return self::BADGE_CLASSES[$level] ?? 'secondary';
    }

    public static function all(): array
    {
        return array_keys(self::LABELS);
    }
}




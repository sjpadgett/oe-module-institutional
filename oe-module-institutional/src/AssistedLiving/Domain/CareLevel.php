<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Domain;

/**
 * CareLevel — AL resident care intensity tier.
 *
 * Drives staffing ratio and billing level for state AL licensing.
 * Derived from ADL aggregate score (AdlLevel::aggregateScore):
 *   Tier 1 (Low):    0–7   — mostly independent
 *   Tier 2 (Medium): 8–17  — mixed assistance
 *   Tier 3 (High):   18+   — extensive/total dependence across domains
 *
 * Facilities may override the tier manually via ResidentIntake review.
 */
final class CareLevel
{
    public const TIER_1 = 'TIER_1';
    public const TIER_2 = 'TIER_2';
    public const TIER_3 = 'TIER_3';

    private const LABELS = [
        self::TIER_1 => 'Level 1 — Low',
        self::TIER_2 => 'Level 2 — Medium',
        self::TIER_3 => 'Level 3 — High',
    ];

    private const BADGE_CLASSES = [
        self::TIER_1 => 'success',
        self::TIER_2 => 'warning',
        self::TIER_3 => 'danger',
    ];

    public static function fromAdlScore(int $score): string
    {
        if ($score <= 7) {
            return self::TIER_1;
        }
        if ($score <= 17) {
            return self::TIER_2;
        }
        return self::TIER_3;
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

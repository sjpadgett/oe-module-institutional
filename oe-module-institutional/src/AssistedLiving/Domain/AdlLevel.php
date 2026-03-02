<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Domain;

/**
 * AdlLevel — Activities of Daily Living functional classification.
 *
 * Maps to MDS 3.0 ADL self-performance coding (CMS-regulated):
 *   0 = Independent
 *   1 = Supervision (oversight only, no physical help)
 *   2 = Limited Assistance (guided maneuvering, non-weight-bearing)
 *   3 = Extensive Assistance (weight-bearing support)
 *   4 = Total Dependence (full performance by staff)
 *   8 = Activity Did Not Occur
 */
final class AdlLevel
{
    public const INDEPENDENT      = 0;
    public const SUPERVISION      = 1;
    public const LIMITED_ASSIST   = 2;
    public const EXTENSIVE_ASSIST = 3;
    public const TOTAL_DEPENDENCE = 4;
    public const DID_NOT_OCCUR    = 8;

    public const DOMAINS = [
        'bathing'    => 'Bathing',
        'dressing'   => 'Dressing',
        'grooming'   => 'Grooming',
        'transfer'   => 'Transfer',
        'ambulation' => 'Ambulation',
        'eating'     => 'Eating',
        'toileting'  => 'Toileting',
    ];

    private const LABELS = [
        self::INDEPENDENT      => 'Independent',
        self::SUPERVISION      => 'Supervision',
        self::LIMITED_ASSIST   => 'Limited Assist',
        self::EXTENSIVE_ASSIST => 'Extensive Assist',
        self::TOTAL_DEPENDENCE => 'Total Dependence',
        self::DID_NOT_OCCUR    => 'Did Not Occur',
    ];

    private const BADGE_CLASSES = [
        self::INDEPENDENT      => 'success',
        self::SUPERVISION      => 'info',
        self::LIMITED_ASSIST   => 'warning',
        self::EXTENSIVE_ASSIST => 'oei-orange',
        self::TOTAL_DEPENDENCE => 'danger',
        self::DID_NOT_OCCUR    => 'secondary',
    ];

    public static function label(int $level): string
    {
        return self::LABELS[$level] ?? 'Unknown';
    }

    public static function badge(int $level): string
    {
        return self::BADGE_CLASSES[$level] ?? 'secondary';
    }

    public static function validLevels(): array
    {
        return array_keys(self::LABELS);
    }

    public static function validDomains(): array
    {
        return array_keys(self::DOMAINS);
    }

    /**
     * Aggregate care load 0–28. DID_NOT_OCCUR counts as 0.
     * Used by CareLevel::fromAdlScore() to gate care tier.
     */
    public static function aggregateScore(array $domainLevels): int
    {
        $score = 0;
        foreach ($domainLevels as $level) {
            $l = (int)$level;
            $score += ($l === self::DID_NOT_OCCUR) ? 0 : min($l, self::TOTAL_DEPENDENCE);
        }
        return $score;
    }
}

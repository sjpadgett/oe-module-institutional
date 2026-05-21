<?php

/**
 * src/HomeBased/Domain/HbcReferralStatus.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Domain;

final class HbcReferralStatus
{
    public const NEW       = 'NEW';
    public const TRIAGED   = 'TRIAGED';
    public const SCHEDULED = 'SCHEDULED';
    public const ACTIVE    = 'ACTIVE';
    public const CLOSED    = 'CLOSED';
    public const DECLINED  = 'DECLINED';

    private const LABELS = [
        self::NEW       => 'New Referral',
        self::TRIAGED   => 'Triaged',
        self::SCHEDULED => 'Scheduled',
        self::ACTIVE    => 'Active',
        self::CLOSED    => 'Closed',
        self::DECLINED  => 'Declined',
    ];

    private const BADGE = [
        self::NEW       => 'bg-warning text-dark',
        self::TRIAGED   => 'bg-info text-dark',
        self::SCHEDULED => 'bg-primary',
        self::ACTIVE    => 'bg-success',
        self::CLOSED    => 'bg-secondary',
        self::DECLINED  => 'bg-danger',
    ];

    public static function all(): array   { return array_keys(self::LABELS); }
    public static function label(string $s): string { return self::LABELS[$s] ?? $s; }
    public static function badge(string $s): string { return self::BADGE[$s]  ?? 'bg-secondary'; }
}




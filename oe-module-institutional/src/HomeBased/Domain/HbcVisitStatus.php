<?php

/**
 * src/HomeBased/Domain/HbcVisitStatus.php
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

final class HbcVisitStatus
{
    public const SCHEDULED = 'SCHEDULED';
    public const EN_ROUTE  = 'EN_ROUTE';
    public const ARRIVED   = 'ARRIVED';
    public const COMPLETE  = 'COMPLETE';
    public const MISSED    = 'MISSED';
    public const REFUSED   = 'REFUSED';
    public const CANCELED  = 'CANCELED';

    private const LABELS = [
        self::SCHEDULED => 'Scheduled',
        self::EN_ROUTE  => 'En Route',
        self::ARRIVED   => 'Arrived',
        self::COMPLETE  => 'Complete',
        self::MISSED    => 'Missed',
        self::REFUSED   => 'Refused',
        self::CANCELED  => 'Canceled',
    ];

    private const BADGE = [
        self::SCHEDULED => 'bg-secondary',
        self::EN_ROUTE  => 'bg-info text-dark',
        self::ARRIVED   => 'bg-primary',
        self::COMPLETE  => 'bg-success',
        self::MISSED    => 'bg-danger',
        self::REFUSED   => 'bg-warning text-dark',
        self::CANCELED  => 'bg-secondary',
    ];

    /** Statuses the board's quick-advance button can move to */
    private const NEXT = [
        self::SCHEDULED => self::EN_ROUTE,
        self::EN_ROUTE  => self::ARRIVED,
        self::ARRIVED   => self::COMPLETE,
    ];

    public static function all(): array   { return array_keys(self::LABELS); }
    public static function label(string $s): string { return self::LABELS[$s] ?? $s; }
    public static function badge(string $s): string { return self::BADGE[$s]  ?? 'bg-secondary'; }
    public static function next(string $s): ?string { return self::NEXT[$s]   ?? null; }
    public static function isFinal(string $s): bool {
        return in_array($s, [self::COMPLETE, self::MISSED, self::REFUSED, self::CANCELED], true);
    }
}




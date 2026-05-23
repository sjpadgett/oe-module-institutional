<?php

/**
 * src/HomeBased/Domain/HbcVisitType.php
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

final class HbcVisitType
{
    public const SN    = 'SN';
    public const PT    = 'PT';
    public const OT    = 'OT';
    public const ST    = 'ST';
    public const MSW   = 'MSW';
    public const HHA   = 'HHA';
    public const MD    = 'MD';
    public const OTHER = 'OTHER';

    private const LABELS = [
        self::SN    => 'Skilled Nursing',
        self::PT    => 'Physical Therapy',
        self::OT    => 'Occupational Therapy',
        self::ST    => 'Speech Therapy',
        self::MSW   => 'Medical Social Work',
        self::HHA   => 'Home Health Aide',
        self::MD    => 'Physician / House Call',
        self::OTHER => 'Other',
    ];

    private const BADGE = [
        self::SN    => 'bg-primary',
        self::PT    => 'bg-success',
        self::OT    => 'bg-success',
        self::ST    => 'bg-info text-dark',
        self::MSW   => 'bg-warning text-dark',
        self::HHA   => 'bg-secondary',
        self::MD    => 'bg-danger',
        self::OTHER => 'bg-secondary',
    ];

    public static function all(): array { return array_keys(self::LABELS); }
    public static function label(string $type): string { return self::LABELS[$type] ?? $type; }
    public static function badge(string $type): string { return self::BADGE[$type] ?? 'bg-secondary'; }
    public static function short(string $type): string { return $type; }
}




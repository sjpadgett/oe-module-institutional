<?php

/**
 * src/Inpatient/Domain/HospitalService.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Domain;

/**
 * HospitalService — inpatient service line classification.
 *
 * Maps to oei_ip_episode.service ENUM.
 * Used for floor board filtering and badge display.
 */
final class HospitalService
{
    public const MED_SURG  = 'MED_SURG';
    public const TELEMETRY = 'TELEMETRY';
    public const ORTHO     = 'ORTHO';
    public const NEURO     = 'NEURO';
    public const OB        = 'OB';
    public const PEDS      = 'PEDS';
    public const ICU       = 'ICU';
    public const ONCOLOGY  = 'ONCOLOGY';
    public const CARDIAC   = 'CARDIAC';
    public const OTHER     = 'OTHER';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::MED_SURG,
            self::TELEMETRY,
            self::ORTHO,
            self::NEURO,
            self::OB,
            self::PEDS,
            self::ICU,
            self::ONCOLOGY,
            self::CARDIAC,
            self::OTHER,
        ];
    }

    public static function label(string $code): string
    {
        return match (strtoupper($code)) {
            self::MED_SURG  => xlt('Medical / Surgical'),
            self::TELEMETRY => xlt('Telemetry'),
            self::ORTHO     => xlt('Orthopedics'),
            self::NEURO     => xlt('Neurology'),
            self::OB        => xlt('OB / Labor & Delivery'),
            self::PEDS      => xlt('Pediatrics'),
            self::ICU       => xlt('Intensive Care'),
            self::ONCOLOGY  => xlt('Oncology'),
            self::CARDIAC   => xlt('Cardiac / Cath Lab'),
            self::OTHER     => xlt('Other / General'),
            default         => htmlspecialchars($code),
        };
    }

    public static function badgeClass(string $code): string
    {
        return match (strtoupper($code)) {
            self::ICU                      => 'text-bg-danger',
            self::TELEMETRY, self::CARDIAC => 'text-bg-warning',
            self::OB, self::PEDS           => 'text-bg-info',
            self::ONCOLOGY                 => 'text-bg-purple',
            default                        => 'text-bg-secondary',
        };
    }
}




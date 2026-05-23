<?php

/**
 * src/Inpatient/Domain/AdmissionType.php
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
 * AdmissionType — inpatient admission classification.
 *
 * Maps to oei_ip_episode.admission_type ENUM.
 * Badge colors reflect clinical urgency for the floor board.
 */
final class AdmissionType
{
    public const ELECTIVE  = 'ELECTIVE';
    public const URGENT    = 'URGENT';
    public const EMERGENCY = 'EMERGENCY';
    public const NEWBORN   = 'NEWBORN';
    public const TRAUMA    = 'TRAUMA';

    /** @return string[] */
    public static function all(): array
    {
        return [
            self::ELECTIVE,
            self::URGENT,
            self::EMERGENCY,
            self::NEWBORN,
            self::TRAUMA,
        ];
    }

    public static function label(string $code): string
    {
        return match (strtoupper($code)) {
            self::ELECTIVE  => xlt('Elective'),
            self::URGENT    => xlt('Urgent'),
            self::EMERGENCY => xlt('Emergency'),
            self::NEWBORN   => xlt('Newborn'),
            self::TRAUMA    => xlt('Trauma'),
            default         => htmlspecialchars($code),
        };
    }

    public static function badgeClass(string $code): string
    {
        return match (strtoupper($code)) {
            self::EMERGENCY, self::TRAUMA => 'text-bg-danger',
            self::URGENT                  => 'text-bg-warning',
            self::NEWBORN                 => 'text-bg-info',
            default                       => 'text-bg-secondary',
        };
    }
}




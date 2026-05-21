<?php

/**
 * src/Core/Domain/Disposition.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Core\Domain;

final class Disposition
{
    public const DISCHARGE = 'DISCHARGE';
    public const TRANSFER = 'TRANSFER';
    public const ADMIT = 'ADMIT';
    public const LWBS = 'LWBS';
    public const ELOPE = 'ELOPE';
    public const EXPIRE = 'EXPIRE';

    /** @return string[] */
    public static function allowed(): array
    {
        return [self::DISCHARGE, self::TRANSFER, self::ADMIT, self::LWBS, self::ELOPE, self::EXPIRE];
    }
}




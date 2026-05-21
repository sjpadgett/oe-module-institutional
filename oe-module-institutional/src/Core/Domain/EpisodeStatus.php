<?php

/**
 * src/Core/Domain/EpisodeStatus.php
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

final class EpisodeStatus
{
    public const WAITING = 'WAITING';
    public const ROOMED = 'ROOMED';
    public const PROVIDER = 'PROVIDER';
    public const RESULTS = 'RESULTS';
    public const READY_DISPO = 'READY_DISPO';
    public const OBS = 'OBS';
    public const CLOSED = 'CLOSED';

    /** @return string[] */
    public static function allowedForBoard(): array
    {
        return [self::WAITING, self::ROOMED, self::PROVIDER, self::RESULTS, self::READY_DISPO, self::OBS];
    }
}




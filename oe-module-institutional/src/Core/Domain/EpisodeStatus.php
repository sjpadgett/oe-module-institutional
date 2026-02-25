<?php

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

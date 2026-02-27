<?php

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



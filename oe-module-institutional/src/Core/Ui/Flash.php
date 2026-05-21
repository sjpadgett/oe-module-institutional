<?php

/**
 * src/Core/Ui/Flash.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Core\Ui;

final class Flash
{
    private const KEY = 'oei_flash_messages';

    /** @return array<int,array{type:string,message:string}> */
    public static function consume(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);
        return is_array($messages) ? $messages : [];
    }

    public static function addError(string $message): void   { self::add('error',   $message); }
    public static function addSuccess(string $message): void { self::add('success', $message); }
    public static function addInfo(string $message): void    { self::add('info',    $message); }

    private static function add(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION[self::KEY]) || !is_array($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = [];
        }
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }
}




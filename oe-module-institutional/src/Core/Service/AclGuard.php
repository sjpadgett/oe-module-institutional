<?php

namespace OpenEMR\Modules\Institutional\Core\Service;

final class AclGuard
{
    public static function check(string $section, string $acl): bool
    {
        if (!class_exists('OpenEMR\Common\Acl\AclMain')) {
            return true;
        }
        return (bool)\OpenEMR\Common\Acl\AclMain::aclCheckCore($section, $acl);
    }

    public static function requireSection(string $section, string $acl): void
    {
        if (!self::check($section, $acl)) {
            http_response_code(403);
            die(function_exists('xlt') ? xlt('Access denied') : 'Access denied');
        }
    }

    public static function requirePatients(): void
    {
        self::requireSection('patients', 'med');
    }

    public static function requireAdmin(): void
    {
        self::requireSection('admin', 'users');
    }
}



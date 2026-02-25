<?php

namespace OpenEMR\Modules\Institutional\Core\Ui;

use OpenEMR\Modules\Institutional\Manifest\Manifest;

final class ViewHelper
{
    public static function bootstrap5Href(Manifest $manifest): string
    {
        $mode = (string)($manifest->ui['bootstrap5_mode'] ?? 'cdn');
        if ($mode !== 'cdn') {
            return '';
        }
        return 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';
    }

    public static function humanElapsed(string $start): string
    {
        $startTs = strtotime($start);
        if (!$startTs) {
            return '';
        }
        $delta = time() - $startTs;
        if ($delta < 60) {
            return $delta . 's';
        }
        $mins = (int)floor($delta / 60);
        if ($mins < 60) {
            return $mins . 'm';
        }
        $hours = (int)floor($mins / 60);
        $mins2 = $mins % 60;
        if ($hours < 24) {
            return $hours . 'h ' . $mins2 . 'm';
        }
        $days = (int)floor($hours / 24);
        $hours2 = $hours % 24;
        return $days . 'd ' . $hours2 . 'h';
    }

    /** Emit a Bootstrap 5 alert for flash messages stored in session. */
    public static function flashHtml(): string
    {
        if (!isset($_SESSION['oei_flash'])) {
            return '';
        }
        $msg = htmlspecialchars((string)$_SESSION['oei_flash']['msg']);
        $type = htmlspecialchars((string)($_SESSION['oei_flash']['type'] ?? 'info'));
        unset($_SESSION['oei_flash']);
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
            . $msg
            . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }

    public static function setFlash(string $msg, string $type = 'success'): void
    {
        $_SESSION['oei_flash'] = ['msg' => $msg, 'type' => $type];
    }
}

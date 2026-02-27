<?php
/** Renders flash messages. Include after _bootstrap.php. */

use OpenEMR\Modules\Institutional\Core\Ui\Flash;

$_oei_flash = Flash::consume();
foreach ($_oei_flash as $_m) {
    $_t = (string)($_m['type'] ?? 'info');
    $_msg = (string)($_m['message'] ?? '');
    if ($_msg === '') {
        continue;
    }
    $cls = match ($_t) {
        'error'   => 'alert-danger',
        'success' => 'alert-success',
        'warning' => 'alert-warning',
        default   => 'alert-info',
    };
    echo '<div class="alert ' . htmlspecialchars($cls) . ' py-2 my-2" role="alert">' . $_msg . '</div>';
}
unset($_oei_flash, $_m, $_t, $_msg, $cls);



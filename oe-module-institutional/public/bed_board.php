<?php

/**
 * public/bed_board.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

require_once __DIR__ . '/_bootstrap.php';

$query = [
    'facility_id' => (string)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1)),
];

$unit = trim((string)($_GET['unit'] ?? ''));
if ($unit !== '') {
    $query['unit'] = $unit;
}

header('Location: bed_management.php?' . http_build_query($query), true, 302);
exit;




<?php

/**
 * Public entry point for the Institutional Module.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 *
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2024 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once dirname(__DIR__, 4) . '/globals.php';

use OpenEMR\Core\Header;

$moduleWebRoot = $GLOBALS['webroot'] . '/interface/modules/custom_modules/oe-module-institutional/public';

?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Institutional Module'); ?></title>
    <?php Header::setupHeader(); ?>
    <link rel="stylesheet" href="<?php echo $moduleWebRoot; ?>/css/institutional.css">
</head>
<body>
<div class="container-fluid">
    <h2><?php echo xlt('Institutional Module'); ?></h2>
    <p><?php echo xlt('Institutional settings for E.R. and hospital environments.'); ?></p>
</div>
<script src="<?php echo $moduleWebRoot; ?>/js/institutional.js"></script>
</body>
</html>

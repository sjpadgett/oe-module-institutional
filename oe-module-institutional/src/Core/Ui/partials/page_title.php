<?php

/**
 * src/Core/Ui/partials/page_title.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

/** Usage: $pageTitle = xlt('My Page'); require __DIR__ . '/../src/Core/Ui/partials/page_title.php'; */
if (!isset($pageTitle) || $pageTitle === '') {
    return;
}
?>
<div class="d-flex align-items-center justify-content-between my-2">
  <h3 class="m-0"><?= htmlspecialchars((string)$pageTitle) ?></h3>
</div>




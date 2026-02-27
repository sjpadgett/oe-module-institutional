<?php
/** Usage: $pageTitle = xlt('My Page'); require __DIR__ . '/../src/Core/Ui/partials/page_title.php'; */
if (!isset($pageTitle) || $pageTitle === '') {
    return;
}
?>
<div class="d-flex align-items-center justify-content-between my-2">
  <h3 class="m-0"><?= htmlspecialchars((string)$pageTitle) ?></h3>
</div>



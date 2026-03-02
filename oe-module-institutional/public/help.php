<?php

require_once __DIR__ . '/_bootstrap.php';

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

$pageTitle = xlt('About / Help');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';

$moduleRoot = dirname(__DIR__);
$aboutPath = $moduleRoot . '/docs/HELP.md';
$smokePath = $moduleRoot . '/docs/SMOKE_TEST.md';
$seedPath  = $moduleRoot . '/sql/dev_seed.sql';

$download = $_GET['download'] ?? '';
if ($download === 'about' && is_file($aboutPath)) {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="HELP.md"');
    readfile($aboutPath);
    exit;
}
if ($download === 'smoke' && is_file($smokePath)) {
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="SMOKE_TEST.md"');
    readfile($smokePath);
    exit;
}
if ($download === 'seed' && is_file($seedPath)) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="dev_seed.sql"');
    readfile($seedPath);
    exit;
}

$about = is_file($aboutPath) ? (file_get_contents($aboutPath) ?: '') : '';
$smoke = is_file($smokePath) ? (file_get_contents($smokePath) ?: '') : '';
$seed  = is_file($seedPath) ? (file_get_contents($seedPath) ?: '') : '';

?>
<div class="container-fluid px-0">

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong><?= xlt('About') ?></strong>
      <a class="btn btn-sm btn-outline-primary" href="help.php?download=about"><?= xlt('Download') ?></a>
    </div>
    <div class="card-body">
      <p class="text-muted mb-2">
        <?= xlt('This page is intended to grow as the module evolves. Add notes, workflows, and implementation guidance here.') ?>
      </p>
      <pre class="small mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($about) ?></pre>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong><?= xlt('Smoke Test') ?></strong>
      <a class="btn btn-sm btn-outline-primary" href="help.php?download=smoke"><?= xlt('Download') ?></a>
    </div>
    <div class="card-body">
      <p class="text-muted mb-2">
        <?= xlt('Use this checklist to validate the core workflow after install.') ?>
      </p>
      <pre class="small mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($smoke) ?></pre>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <strong><?= xlt('Dev Seed SQL') ?></strong>
      <a class="btn btn-sm btn-outline-primary" href="help.php?download=seed"><?= xlt('Download') ?></a>
    </div>
    <div class="card-body">
      <p class="text-muted mb-2">
        <?= xlt('Optional dev-only seed data for locations and facility directory.') ?>
      </p>
      <pre class="small mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($seed) ?></pre>
    </div>
  </div>

</div>

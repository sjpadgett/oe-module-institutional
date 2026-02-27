<?php

require_once __DIR__ . '/_bootstrap.php';
require __DIR__ . '/../src/Core/Ui/partials/flash.php';

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Timeline\Controller\TimelineController;
use OpenEMR\Modules\Institutional\Submodule\Timeline\Repository\TimelineRepository;

if (!$manifest->featureEnabled('timeline')) {
    die(xlt("Episode Timeline is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId  = isset($_GET['episode_id']) && is_numeric($_GET['episode_id'])
    ? (int)$_GET['episode_id'] : null;

$controller = new TimelineController(new EpisodeRepository(), new TimelineRepository());
$data       = $controller->handle($facilityId, $episodeId);

$href = institutional_bootstrap5_href($manifest);

// ── helpers ───────────────────────────────────────────────────────────────────

function tl_icon(string $source): string {
    return match ($source) {
        'VITALS'   => 'bi-heart-pulse',
        'TASK'     => 'bi-check-circle',
        'MAR'      => 'bi-capsule',
        'STATUS'   => 'bi-arrow-right-circle',
        'LOCATION' => 'bi-geo-alt',
        'REFERRAL' => 'bi-send',
        'EVENT'    => 'bi-circle-fill',
        default    => 'bi-circle',
    };
}

function tl_badge_class(string $sev): string {
    return match ($sev) {
        'danger'  => 'bg-danger',
        'warning' => 'bg-warning text-dark',
        'success' => 'bg-success',
        'info'    => 'bg-info text-dark',
        default   => 'bg-secondary',
    };
}

function tl_dot_class(string $sev): string {
    return match ($sev) {
        'danger'  => 'border-danger bg-danger',
        'warning' => 'border-warning bg-warning',
        'success' => 'border-success bg-success',
        'info'    => 'border-info bg-info',
        default   => 'border-secondary bg-white',
    };
}

function tl_elapsed(string $dt1, string $dt2): string {
    $diff = abs(strtotime($dt2) - strtotime($dt1));
    if ($diff < 60)   return "{$diff}s";
    if ($diff < 3600) return round($diff / 60) . 'm';
    return number_format($diff / 3600, 1) . 'h';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Episode Timeline') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* ── Timeline track ─────────────────────────────────────────── */
    .tl-track { position: relative; padding-left: 2.25rem; }
    .tl-track::before {
      content: '';
      position: absolute;
      left: .85rem;
      top: 0;
      bottom: 0;
      width: 2px;
      background: #dee2e6;
    }
    .tl-dot {
      position: absolute;
      left: 0;
      width: 1.75rem;
      height: 1.75rem;
      border-radius: 50%;
      border: 2px solid;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .7rem;
      z-index: 1;
    }
    .tl-entry { position: relative; padding-bottom: 1.1rem; }
    .tl-entry:last-child { padding-bottom: 0; }
    .tl-entry:last-child .tl-track::before { display: none; }
    .tl-time  { font-size: .75rem; color: #6c757d; white-space: nowrap; }
    .tl-label { font-weight: 500; font-size: .9rem; }
    .tl-detail { font-size: .78rem; color: #495057; }
    .tl-gap   { font-size: .7rem; color: #adb5bd; padding: .15rem 0 .15rem 2.25rem; }
    .source-badge { font-size: .65rem; }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Episode Timeline') ?></h1>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
      <?php if ($data['episodeId']): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="disposition.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$data['episodeId']) ?>"><?= xlt('Disposition') ?></a>
        <a class="btn btn-sm btn-outline-secondary"
           href="triage.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$data['episodeId']) ?>"><?= xlt('Vitals') ?></a>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-3">

    <!-- Sidebar: episode list -->
    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt('Active Episodes') ?></div>
        <div class="list-group list-group-flush" style="max-height:75vh;overflow-y:auto;">
          <?php foreach ($data['boardRows'] as $e):
                $eId  = (int)$e['id'];
                $isAct = ($eId === $data['episodeId']);
                ?>
          <a class="list-group-item list-group-item-action py-2 <?= $isAct ? 'active' : '' ?>"
             href="timeline.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$eId) ?>">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold small">#<?= htmlspecialchars((string)$eId) ?> &middot; PID <?= htmlspecialchars((string)$e['pid']) ?></div>
                <div class="small opacity-75 text-truncate" style="max-width:160px;">
                  <?= htmlspecialchars((string)($e['chief_complaint'] ?? '')) ?>
                </div>
              </div>
              <div class="text-end small">
                <span class="badge text-bg-secondary"><?= htmlspecialchars((string)($e['type'] ?? '')) ?></span>
                <?php if (!empty($e['acuity_esi'])): ?>
                  <span class="badge text-bg-info">ESI <?= htmlspecialchars((string)$e['acuity_esi']) ?></span>
                <?php endif; ?>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
          <?php if (empty($data['boardRows'])): ?>
            <div class="list-group-item text-muted small"><?= xlt('No active episodes') ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Main: timeline -->
    <div class="col-12 col-lg-9">
      <?php if (!$data['episodeId']): ?>
        <div class="alert alert-info"><?= xlt('Select an episode to view its timeline.') ?></div>
      <?php elseif (empty($data['entries'])): ?>
        <div class="alert alert-secondary"><?= xlt('No events recorded for this episode yet.') ?></div>
      <?php else: ?>

          <?php
            $entries   = $data['entries'];
            $userNames = $data['userNames'];
            $selected  = $data['selected'];
            $firstTs   = $entries[0]['ts'] ?? 0;
            $prevDt    = null;
            ?>

      <!-- Episode header card -->
      <div class="card shadow-sm mb-3">
        <div class="card-body py-2 d-flex flex-wrap gap-3 align-items-center">
          <div>
            <span class="text-muted small"><?= xlt('Episode') ?></span>
            <strong class="ms-1">#<?= htmlspecialchars((string)$data['episodeId']) ?></strong>
          </div>
          <?php if (!empty($selected['chief_complaint'])): ?>
          <div>
            <span class="text-muted small"><?= xlt('CC') ?></span>
            <span class="ms-1"><?= htmlspecialchars((string)$selected['chief_complaint']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($selected['acuity_esi'])): ?>
          <div>
            <span class="badge text-bg-info">ESI <?= htmlspecialchars((string)$selected['acuity_esi']) ?></span>
          </div>
          <?php endif; ?>
          <?php if (!empty($selected['start_datetime'])): ?>
          <div class="ms-auto text-muted small">
                <?= xlt('Arrived') ?>: <?= htmlspecialchars((string)$selected['start_datetime']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Timeline -->
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Chronological Events') ?></span>
          <span class="badge text-bg-secondary"><?= count($entries) ?> <?= xlt('events') ?></span>
        </div>
        <div class="card-body py-3 px-3">

          <?php foreach ($entries as $i => $e):
                $sev   = (string)($e['severity'] ?? '');
                $dotCls = tl_dot_class($sev);
                $icon  = (string)($e['icon'] ?? tl_icon((string)$e['source']));
                $userId = $e['user_id'] ?? null;
                $userName = $userId ? ($userNames[$userId] ?? "User #{$userId}") : null;

          // Gap annotation between entries
                $gap = '';
                if ($prevDt && $e['ts'] - strtotime($prevDt) > 600) {
                    $gap = tl_elapsed($prevDt, (string)$e['datetime']);
                }
                $prevDt = (string)$e['datetime'];
                ?>
                <?php if ($gap): ?>
          <div class="tl-gap">
            <i class="bi bi-clock me-1"></i><?= htmlspecialchars($gap) ?> <?= xlt('elapsed') ?>
          </div>
          <?php endif; ?>

          <div class="tl-entry">
            <div class="tl-track">
              <div class="tl-dot <?= htmlspecialchars($dotCls) ?>">
                <i class="bi <?= htmlspecialchars($icon) ?> <?= $sev ? 'text-white' : 'text-secondary' ?>" style="font-size:.7rem;"></i>
              </div>
              <div class="d-flex align-items-start gap-2 ps-1">
                <div class="flex-grow-1">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="tl-label"><?= htmlspecialchars((string)$e['label']) ?></span>
                    <?php if ($sev): ?>
                      <span class="badge <?= tl_badge_class($sev) ?> source-badge"><?= htmlspecialchars(strtoupper($sev)) ?></span>
                    <?php endif; ?>
                    <span class="badge text-bg-light border text-muted source-badge"><?= htmlspecialchars((string)$e['source']) ?></span>
                  </div>
                  <?php if (!empty($e['detail'])): ?>
                    <div class="tl-detail mt-1"><?= htmlspecialchars((string)$e['detail']) ?></div>
                  <?php endif; ?>
                  <div class="tl-time mt-1">
                    <?= htmlspecialchars((string)$e['datetime']) ?>
                    <?php if ($firstTs && $e['ts'] >= $firstTs): ?>
                      &middot; <span title="<?= xlt('Since arrival') ?>">+<?= tl_elapsed(date('Y-m-d H:i:s', $firstTs), (string)$e['datetime']) ?></span>
                    <?php endif; ?>
                    <?php if ($userName): ?>
                      &middot; <?= htmlspecialchars($userName) ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>

        </div>
      </div>

      <?php endif; ?>
    </div><!-- /col -->
  </div><!-- /row -->
</div>
</body>
</html>



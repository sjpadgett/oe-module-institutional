<?php

/**
 * public/bh_packet.php
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

// Flash messages
require __DIR__ . '/../src/Core/Ui/partials/flash.php';
use OpenEMR\Modules\Institutional\BehavioralHealth\Submodule\BhBoarding\Repository\BhBoardingRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Repository\EpisodeDocumentRepository;

if (!$manifest->featureEnabled('bh_boarding')) {
    die(xlt('Institutional BH Boarding is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId  = (int)($_GET['episode_id'] ?? 0);
if ($episodeId <= 0) {
    die(xlt('Missing episode_id'));
}

$repo = new BhBoardingRepository();
$bh   = $repo->getByEpisode($episodeId) ?: [];

// Load attached documents if the submodule is enabled
$documents = [];
if ($manifest->featureEnabled('episode_documents')) {
    $docRepo   = new EpisodeDocumentRepository();
    $documents = $docRepo->listForEpisode($episodeId);
}

$href = institutional_bootstrap5_href($manifest);

$check = [];
if (!empty($bh['checklist_json'])) {
    $tmp = json_decode((string)$bh['checklist_json'], true);
    if (is_array($tmp)) $check = $tmp;
}

function yn(bool $b): string { return $b ? xlt('Yes') : xlt('No'); }

function mimeIconPkt(string $mime): string
{
    return match (true) {
        str_contains($mime, 'pdf')   => '📄',
        str_contains($mime, 'image') => '🖼️',
        default                      => '📎',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>BH Transfer Packet</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    @media print {
      .no-print { display: none !important; }
      .card { break-inside: avoid; }
    }
  </style>
</head>
<body class="bg-white">
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 mb-0"><?= xlt('BH Transfer Packet') ?></h1>
    <div class="d-flex gap-2 no-print">
      <?php if ($manifest->featureEnabled('episode_documents')): ?>
        <a class="btn btn-sm btn-outline-primary"
           href="episode_documents.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">
            <?= xlt('Manage Documents') ?>
        </a>
      <?php endif; ?>
      <button class="btn btn-sm btn-outline-secondary" onclick="window.print()"><?= xlt('Print') ?></button>
    </div>
  </div>

  <!-- Transfer summary -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><?= xlt('Transfer Summary') ?></div>
    <div class="card-body">
      <div><strong><?= xlt('Episode') ?>:</strong> <?= htmlspecialchars((string)$episodeId) ?></div>
      <div><strong><?= xlt('Accepting Facility') ?>:</strong> <?= htmlspecialchars((string)($bh['accepting_facility'] ?? '')) ?></div>
      <div><strong><?= xlt('Accepted') ?>:</strong> <?= htmlspecialchars((string)($bh['accepted_datetime'] ?? '')) ?></div>
      <div><strong><?= xlt('Transport') ?>:</strong> <?= htmlspecialchars((string)($bh['transport_method'] ?? '')) ?> <?= htmlspecialchars((string)($bh['transport_datetime'] ?? '')) ?></div>
      <div><strong><?= xlt('Legal Status') ?>:</strong> <?= htmlspecialchars((string)($bh['legal_status'] ?? '')) ?></div>
      <div><strong><?= xlt('Notes') ?>:</strong> <?= htmlspecialchars((string)($bh['notes'] ?? '')) ?></div>
    </div>
  </div>

  <!-- Checklist -->
  <div class="card mb-3">
    <div class="card-header fw-semibold"><?= xlt('Checklist') ?></div>
    <div class="card-body">
      <ul class="mb-0">
        <li><?= xlt('Provider note / MDM complete') ?>: <strong><?= htmlspecialchars(yn(!empty($check['mdm_complete']))) ?></strong></li>
        <li><?= xlt('Labs printed/sent') ?>: <strong><?= htmlspecialchars(yn(!empty($check['labs_printed']))) ?></strong></li>
        <li><?= xlt('Imaging sent') ?>: <strong><?= htmlspecialchars(yn(!empty($check['imaging_sent']))) ?></strong></li>
        <li><?= xlt('Med reconciliation complete') ?>: <strong><?= htmlspecialchars(yn(!empty($check['meds_reconciled']))) ?></strong></li>
        <li><?= xlt('Consent/transfer acceptance documented') ?>: <strong><?= htmlspecialchars(yn(!empty($check['consent_signed']))) ?></strong></li>
        <li><?= xlt('Nursing report given') ?>: <strong><?= htmlspecialchars(yn(!empty($check['nursing_report']))) ?></strong></li>
        <li><?= xlt('Transfer form completed') ?>: <strong><?= htmlspecialchars(yn(!empty($check['transfer_form']))) ?></strong></li>
      </ul>
    </div>
  </div>

  <!-- Attached documents -->
  <?php if ($manifest->featureEnabled('episode_documents')): ?>
  <div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span class="fw-semibold"><?= xlt('Attached Documents') ?></span>
      <span class="text-muted small"><?= count($documents) ?> <?= xlt('file(s)') ?></span>
    </div>
        <?php if (empty($documents)): ?>
      <div class="card-body text-muted small">
            <?= xlt('No documents attached.') ?>
        <a class="no-print" href="episode_documents.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>">
            <?= xlt('Attach documents →') ?>
        </a>
      </div>
    <?php else: ?>
      <div class="list-group list-group-flush">
        <?php foreach ($documents as $doc): ?>
          <div class="list-group-item d-flex align-items-center gap-2 py-2">
            <span><?= mimeIconPkt((string)$doc['mime_type']) ?></span>
            <div class="flex-grow-1">
              <a href="episode_documents.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>&action=serve&doc_id=<?= (int)$doc['id'] ?>"
                 target="_blank" class="fw-semibold text-decoration-none no-print">
                <?= htmlspecialchars((string)$doc['label']) ?>
              </a>
              <span class="d-none d-print-inline fw-semibold"><?= htmlspecialchars((string)$doc['label']) ?></span>
              <div class="text-muted small">
                <?= htmlspecialchars((string)($doc['doc_type'] ?? '')) ?>
                · <?= htmlspecialchars(substr((string)$doc['uploaded_datetime'], 0, 16)) ?>
                <?php if ($doc['notes']): ?>
                  · <?= htmlspecialchars((string)$doc['notes']) ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
</body>
</html>




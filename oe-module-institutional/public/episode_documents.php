<?php

/**
 * public/episode_documents.php
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

use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Controller\EpisodeDocumentController;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Repository\EpisodeDocumentRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\EpisodeDocuments\Service\EpisodeDocumentService;
use OpenEMR\Common\Csrf\CsrfUtils;

if (!$manifest->featureEnabled('episode_documents')) {
    die(xlt('Episode Documents is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$episodeId  = (int)($_GET['episode_id'] ?? 0);
$userId     = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : null;

// Load episode list for sidebar
$episodeRepo = new EpisodeRepository();
$episodes    = $episodeRepo->fetchBoard($facilityId);

// Default to first episode if none specified
if ($episodeId <= 0 && !empty($episodes)) {
    $episodeId = (int)($episodes[0]['id'] ?? 0);
}

// Resolve selected episode
$selected = null;
foreach ($episodes as $e) {
    if ((int)$e['id'] === $episodeId) {
        $selected = $e;
        break;
    }
}
if (!$selected) {
    die(xlt('No active episode selected'));
}

$pid = (int)($selected['pid'] ?? 0);

// ── Episode type resolution for context-aware nav ─────────────────────────
$episodeType  = strtoupper((string)($selected['type'] ?? 'ED'));
$_oei_ip_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/ip/';
$_oei_pub_base = rtrim($GLOBALS['webroot'] ?? '', '/')
    . '/interface/modules/custom_modules/oe-module-institutional/public/';
// Variables needed by ip_patient_nav.php when $episodeType === 'IP'
$activePage = 'documents';

// Context-aware back URL — resolves to the correct profile page
$_oei_edoc_backUrl = match ($episodeType) {
    'AL'  => $_oei_pub_base . 'al/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'IP'  => $_oei_ip_base  . 'profile.php?episode_id='    . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'HBC' => $_oei_pub_base . 'hbc/profile.php?episode_id='. $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    default => $_oei_pub_base . 'ed_board.php?facility_id=' . $facilityId,
};
$_oei_edoc_backLabel = match ($episodeType) {
    'AL'  => xlt('Resident Profile'),
    'IP'  => xlt('IP Profile'),
    'HBC' => xlt('HBC Profile'),
    default => xlt('ED Board'),
};

// Boot controller
$repo       = new EpisodeDocumentRepository();
$service    = new EpisodeDocumentService($repo);
$controller = new EpisodeDocumentController($repo, $service);
$data       = $controller->handle($episodeId, $pid, $facilityId, $userId);

$csrf = CsrfUtils::collectCsrfToken();
$_edocPids = array_values(array_unique(array_filter(array_map(fn($e)=>(int)($e['pid']??0), $episodes??[]))));
$_edocPatientNames = oei_patient_names($_edocPids);
$href = institutional_bootstrap5_href($manifest);

// ── Helpers ──────────────────────────────────────────────────────────────────
function fmtSize(int $bytes): string
{
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1_048_576)  return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1_048_576, 1) . ' MB';
}

function mimeIcon(string $mime): string
{
    return match (true) {
        str_contains($mime, 'pdf')   => '📄',
        str_contains($mime, 'image') => '🖼️',
        str_contains($mime, 'word')  => '📝',
        str_contains($mime, 'excel') || str_contains($mime, 'spreadsheet') => '📊',
        default                      => '📎',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Episode Documents') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .doc-row:hover { background: #f8f9ff; }
    .drop-zone {
      border: 2px dashed #adb5bd;
      border-radius: 8px;
      padding: 2rem;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
    }
    .drop-zone.dragover { border-color: #0d6efd; background: #f0f5ff; }
    .type-badge { font-size: .72rem; }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
<?php if ($episodeType === 'IP'): ?>
    <?php require __DIR__ . '/../src/Inpatient/Ui/partials/ip_patient_nav.php'; ?>
<?php endif; ?>

  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt('Episode Documents') ?></h1>
    <div class="d-flex gap-2">
      <?php if ($manifest->featureEnabled('bh_boarding')): ?>
        <a class="btn btn-sm btn-outline-secondary"
           href="bh_packet.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>"><?= xlt('BH Packet') ?></a>
      <?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary"
         href="<?= htmlspecialchars($_oei_edoc_backUrl) ?>">
        ← <?= htmlspecialchars($_oei_edoc_backLabel) ?>
      </a>
    </div>
  </div>

  <?php if ($data['message']): ?>
    <div class="alert alert-success alert-dismissible py-2">
        <?= htmlspecialchars($data['message']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <?php if ($data['error']): ?>
    <div class="alert alert-danger alert-dismissible py-2">
        <?= htmlspecialchars($data['error']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <div class="row g-3">

    <!-- ── Episode sidebar ─────────────────────────────────────────────── -->
    <div class="col-12 col-lg-3">
      <div class="card shadow-sm">
        <div class="card-header small fw-semibold"><?= xlt('Active Episodes') ?></div>
        <div class="list-group list-group-flush" style="max-height:420px;overflow-y:auto;">
          <?php foreach ($episodes as $e):
                $active  = ((int)$e['id'] === $episodeId);
                $docCount = $repo->countForEpisode((int)$e['id']);
                ?>
            <a class="list-group-item list-group-item-action py-2 <?= $active ? 'active' : '' ?>"
               href="episode_documents.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$e['id']) ?>">
              <div class="d-flex justify-content-between align-items-start">
                <div>
                  <div class="fw-semibold small">#<?= htmlspecialchars((string)$e['id']) ?> <?= oei_fmt_patient((int)($e['pid']??0), $_edocPatientNames) ?></div>
                  <div class="small opacity-75 text-truncate" style="max-width:140px;">
                    <?= htmlspecialchars((string)($e['chief_complaint'] ?? '—')) ?>
                  </div>
                </div>
                <?php if ($docCount > 0): ?>
                  <span class="badge <?= $active ? 'bg-white text-primary' : 'text-bg-primary' ?>">
                    <?= $docCount ?>
                  </span>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Type summary -->
      <?php if (!empty($data['summary'])): ?>
      <div class="card shadow-sm mt-3">
        <div class="card-header small fw-semibold"><?= xlt('By Type') ?></div>
        <div class="list-group list-group-flush">
            <?php foreach ($data['summary'] as $type => $count): ?>
            <div class="list-group-item d-flex justify-content-between py-1 small">
              <span><?= htmlspecialchars($data['types'][$type] ?? $type) ?></span>
              <span class="badge text-bg-secondary"><?= $count ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── Main panel ──────────────────────────────────────────────────── -->
    <div class="col-12 col-lg-9">

      <!-- Upload card -->
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold"><?= xlt('Attach Document') ?></div>
        <div class="card-body">
          <form method="post" enctype="multipart/form-data" id="uploadForm">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="post_action"    value="upload">

            <div class="row g-3">
              <!-- Drop zone / file picker -->
              <div class="col-12">
                <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                  <div class="text-muted mb-2">📎 <?= xlt('Drag & drop a file here, or click to browse') ?></div>
                  <div class="text-muted small"><?= xlt('PDF, images, Word, Excel — max 20 MB') ?></div>
                  <div id="fileChosen" class="mt-2 fw-semibold text-primary" style="display:none;"></div>
                </div>
                <input type="file" id="fileInput" name="upload_file" class="d-none"
                       accept=".pdf,.jpg,.jpeg,.png,.gif,.tif,.tiff,.txt,.doc,.docx,.xls,.xlsx">
              </div>

              <div class="col-12 col-md-5">
                <label class="form-label"><?= xlt('Document Type') ?></label>
                <select name="doc_type" class="form-select">
                  <?php foreach ($data['types'] as $val => $label): ?>
                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12 col-md-7">
                <label class="form-label"><?= xlt('Label') ?>
                  <span class="text-muted fw-normal small">(<?= xlt('optional — defaults to filename') ?>)</span>
                </label>
                <input type="text" name="label" class="form-control"
                       placeholder="<?= xla('e.g., Transfer authorization — St. Mary\'s') ?>">
              </div>

              <div class="col-12">
                <label class="form-label"><?= xlt('Notes') ?>
                  <span class="text-muted fw-normal small">(<?= xlt('optional') ?>)</span>
                </label>
                <input type="text" name="notes" class="form-control"
                       placeholder="<?= xla('Brief note about this document') ?>">
              </div>

              <div class="col-12">
                <button class="btn btn-primary" id="uploadBtn" disabled>
                  <?= xlt('Upload Document') ?>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- Document list -->
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span class="fw-semibold"><?= xlt('Attached Documents') ?></span>
          <span class="text-muted small"><?= count($data['documents']) ?> <?= xlt('file(s)') ?></span>
        </div>

        <?php if (empty($data['documents'])): ?>
          <div class="card-body text-center text-muted py-5">
            <?= xlt('No documents attached to this episode yet.') ?>
          </div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light" style="font-size:.8rem;">
              <tr>
                <th style="width:2rem;"></th>
                <th><?= xlt('Label') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Size') ?></th>
                <th><?= xlt('Uploaded') ?></th>
                <th><?= xlt('By') ?></th>
                <th><?= xlt('Notes') ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($data['documents'] as $doc): ?>
              <tr class="doc-row">
                <td class="text-center" style="font-size:1.1rem;">
                  <?= mimeIcon((string)$doc['mime_type']) ?>
                </td>
                <td>
                  <a href="episode_documents.php?facility_id=<?= urlencode((string)$facilityId) ?>&episode_id=<?= urlencode((string)$episodeId) ?>&action=serve&doc_id=<?= (int)$doc['id'] ?>"
                     target="_blank" class="fw-semibold text-decoration-none">
                    <?= htmlspecialchars((string)$doc['label']) ?>
                  </a>
                  <div class="text-muted" style="font-size:.7rem;"><?= htmlspecialchars((string)$doc['original_name']) ?></div>
                </td>
                <td>
                  <span class="badge text-bg-secondary type-badge">
                    <?= htmlspecialchars($data['types'][$doc['doc_type']] ?? (string)$doc['doc_type']) ?>
                  </span>
                </td>
                <td class="text-muted small text-nowrap"><?= fmtSize((int)$doc['file_size']) ?></td>
                <td class="text-muted small text-nowrap"><?= htmlspecialchars(substr((string)$doc['uploaded_datetime'], 0, 16)) ?></td>
                <td class="small text-nowrap">
                  <?= htmlspecialchars(trim((string)($doc['lname'] ?? '') . ', ' . (string)($doc['fname'] ?? ''), ', ') ?: '—') ?>
                </td>
                <td class="small text-muted text-truncate" style="max-width:140px;">
                  <?= htmlspecialchars((string)($doc['notes'] ?? '')) ?>
                </td>
                <td>
                  <form method="post" onsubmit="return confirm('<?= xla('Remove this document?') ?>')">
                    <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="post_action"     value="delete">
                    <input type="hidden" name="doc_id"          value="<?= (int)$doc['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="<?= xla('Remove') ?>">✕</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /col main -->
  </div><!-- /row -->
</div><!-- /container -->

<script>
// ── Drag & drop + file picker ────────────────────────────────────────────────
const dropZone  = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const fileChosen = document.getElementById('fileChosen');
const uploadBtn  = document.getElementById('uploadBtn');

function onFileSelected(file) {
    if (!file) return;
    fileChosen.textContent = '✓ ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    fileChosen.style.display = '';
    uploadBtn.disabled = false;
}

fileInput.addEventListener('change', () => onFileSelected(fileInput.files[0]));

dropZone.addEventListener('dragover', e => {
    e.preventDefault();
    dropZone.classList.add('dragover');
});
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    const dt = e.dataTransfer;
    if (dt.files.length) {
        // Assign dropped file to the input
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(dt.files[0]);
        fileInput.files = dataTransfer.files;
        onFileSelected(dt.files[0]);
    }
});
</script>
</body>
</html>
















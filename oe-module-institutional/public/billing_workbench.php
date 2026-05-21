<?php

/**
 * public/billing_workbench.php
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

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\Billing\Service\BillingWorkbenchService;

if (!$manifest->featureEnabled('institutional_billing')) {
    die(xlt('Billing Workbench is disabled by manifest'));
}

$facilityId = $_oei_facilityId ?? (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$userId = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
$service = new BillingWorkbenchService();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
        die('CSRF validation failed');
    }
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'add_line') {
        $service->addLedgerLine($facilityId, [
            'episode_id' => $_POST['episode_id'] ?? null,
            'pid' => $_POST['pid'] ?? null,
            'service_date' => $_POST['service_date'] ?? null,
            'billing_path' => $_POST['billing_path'] ?? 'MODULE_LEDGER',
            'line_category' => $_POST['line_category'] ?? 'SERVICE',
            'status' => $_POST['status'] ?? 'READY',
            'charge_code' => $_POST['charge_code'] ?? null,
            'description' => $_POST['description'] ?? null,
            'quantity' => $_POST['quantity'] ?? 1,
            'unit_price' => $_POST['unit_price'] ?? 0,
            'review_reason' => $_POST['review_reason'] ?? null,
            'notes' => $_POST['notes'] ?? null,
        ], $userId);
        $notice = 'added';
    } elseif ($action === 'set_status') {
        $service->setLedgerStatus(
            $facilityId,
            (int)($_POST['line_id'] ?? 0),
            (string)($_POST['new_status'] ?? ''),
            $userId,
            trim((string)($_POST['review_reason'] ?? '')) ?: null
        );
        $notice = 'updated';
    } elseif ($action === 'stage_all') {
        $service->stageClaimCandidates($facilityId, $userId);
        $notice = 'staged';
    } elseif ($action === 'stage_one') {
        $service->stageOneCandidate(
            $facilityId,
            (string)($_POST['candidate_type'] ?? ''),
            (int)($_POST['episode_id'] ?? 0),
            (string)($_POST['service_date'] ?? ''),
            $userId
        );
        $notice = 'staged';
    } elseif ($action === 'release_line') {
        $service->releaseLedgerLine($facilityId, (int)($_POST['line_id'] ?? 0), (string)($_POST['target'] ?? ''), $userId);
        $notice = 'released';
    } elseif ($action === 'release_ready_path') {
        $service->releaseReadyByPath($facilityId, (string)($_POST['billing_path'] ?? ''), $userId);
        $notice = 'released';
    }
    header('Location: billing_workbench.php?facility_id=' . urlencode((string)$facilityId) . ($notice !== '' ? '&notice=' . urlencode($notice) : ''));
    exit;
}

$notice = (string)($_GET['notice'] ?? '');
$filterStatus = strtoupper(trim((string)($_GET['line_status'] ?? '')));
$filterPath = strtoupper(trim((string)($_GET['line_path'] ?? '')));
$selectedBatchKey = trim((string)($_GET['batch_key'] ?? ''));
$csrf = CsrfUtils::collectCsrfToken();
$summary = $service->summary($facilityId);
$claimCandidates = $service->claimCandidates($facilityId);
$ledgerLines = $service->ledgerLines($facilityId, $filterStatus, $filterPath);
$exceptions = $service->billingExceptions($facilityId);
$aging = $service->agingSummary($facilityId);
$episodeFinancials = $service->episodeFinancialSummary($facilityId);
$releaseBatches = $service->releaseBatchHistory($facilityId);
$batchLines = $selectedBatchKey !== '' ? $service->batchLines($facilityId, $selectedBatchKey) : [];
$mode = $service->billingModeMeta($facilityId);
$templates = $service->quickAddTemplates($facilityId);
$href = institutional_bootstrap5_href($manifest);

$scriptDir = dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/interface/modules/custom_modules/oe-module-institutional/public/billing_workbench.php'));
$interfaceRoot = preg_replace('~/modules/custom_modules/oe-module-institutional/public$~', '', $scriptDir) ?: '/interface';
$billingReportUrl = $interfaceRoot . '/billing/billing_report.php';
$ub04Url = $interfaceRoot . '/billing/ub04_form.php';

function bill_badge(string $path): string
{
    return match ($path) {
        'CLAIM_MANAGER' => '<span class="badge text-bg-primary">Claim Manager</span>',
        'PROFESSIONAL_REVIEW' => '<span class="badge text-bg-info text-dark">Professional Review</span>',
        default => '<span class="badge text-bg-secondary">Module Ledger</span>',
    };
}
function line_status_badge(string $status): string
{
    return match ($status) {
        'READY' => '<span class="badge text-bg-success">Ready</span>',
        'HOLD' => '<span class="badge text-bg-warning text-dark">Hold</span>',
        'RELEASED' => '<span class="badge text-bg-primary">Released</span>',
        'VOID' => '<span class="badge text-bg-danger">Void</span>',
        default => '<span class="badge text-bg-secondary">Draft</span>',
    };
}
function release_target_badge(?string $target): string
{
    $target = (string)$target;
    return match ($target) {
        'UB04' => '<span class="badge text-bg-primary">UB04</span>',
        'BILLING_MANAGER' => '<span class="badge text-bg-primary">Billing Manager</span>',
        'PROFESSIONAL' => '<span class="badge text-bg-info text-dark">Professional</span>',
        'STATEMENT' => '<span class="badge text-bg-success">Statement</span>',
        'LEDGER' => '<span class="badge text-bg-secondary">Ledger</span>',
        default => '<span class="badge text-bg-light text-dark border">Pending</span>',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= xlt('Billing Workbench') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <style>
    .kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #6c757d; }
    .kpi-val { font-size: 1.5rem; font-weight: 700; line-height: 1; }
    .wb-note { border-left: 4px solid #0d6efd; background: rgba(13,110,253,.06); }
    .mono-mini { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .76rem; }
    .tpl-btn { white-space: nowrap; }
  </style>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h4 mb-0"><?= xlt('Institutional Billing Workbench') ?></h1>
      <div class="text-muted small"><?= xlt('Hybrid Phase 4: batch drilldown, workbench filters, and guided billing workflow') ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-sm btn-outline-primary" href="<?= htmlspecialchars($billingReportUrl) ?>"><?= xlt('Open Billing Manager') ?></a>
      <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($ub04Url) ?>"><?= xlt('UB04 Form') ?></a>
      <form method="post" class="d-inline">
        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
        <input type="hidden" name="action" value="stage_all">
        <button type="submit" class="btn btn-sm btn-primary"><?= xlt('Stage Suggested Claims') ?></button>
      </form>
    </div>
  </div>

  <?php if ($notice !== ''): ?>
    <div class="alert alert-success py-2 small">
      <?= htmlspecialchars(match ($notice) {
          'added' => xlt('Ledger line added.'),
          'updated' => xlt('Ledger line updated.'),
          'staged' => xlt('Claim staging refreshed.'),
          'released' => xlt('Release action completed.'),
          default => xlt('Workbench updated.'),
      }) ?>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4 wb-note">
    <div class="card-body py-3">
      <div class="fw-semibold mb-1"><?= xlt('Facility billing mode') ?>: <?= htmlspecialchars((string)$mode['label']) ?></div>
      <div class="small text-muted"><?= htmlspecialchars((string)$mode['detail']) ?></div>
      <div class="small mt-1"><span class="fw-semibold"><?= xlt('Claim focus') ?>:</span> <?= htmlspecialchars((string)$mode['claim_focus']) ?></div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('How to use Billing Workbench') ?></span>
      <span class="small text-muted"><?= xlt('Recommended Phase 4 workflow') ?></span>
    </div>
    <div class="card-body">
      <ol class="mb-2">
        <li><?= xlt('Stage claim candidates to create claim-review ledger lines for institutional and professional review work.') ?></li>
        <li><?= xlt('Use Billing exceptions to fix hold, draft, and incomplete claim-review lines before release.') ?></li>
        <li><?= xlt('Add module ledger lines for private-pay, recurring, supply, and adjustment charges that do not belong in claim staging.') ?></li>
        <li><?= xlt('Release ready lines by path when they are prepared for Billing Manager, UB04, professional review, ledger, or statement follow-up.') ?></li>
        <li><?= xlt('Use Release batch history and batch detail to verify what was sent in each release action.') ?></li>
      </ol>
      <div class="small text-muted"><?= xlt('Tip: institutional paths usually stage first and release later. Ledger-first facilities can add recurring and private-pay lines directly, then release to Statement or Ledger follow-up when reviewed.') ?></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php
      $cards = [
          ['Claim Candidates', (int)$summary['claim_candidates'], ''],
          ['Institutional Claims', (int)$summary['institutional_claims'], 'text-primary'],
          ['Professional Review', (int)$summary['professional_claims'], 'text-info'],
          ['Ledger Ready', (int)$summary['ready_lines'], 'text-success'],
          ['Ready Amount', '$' . number_format((float)($summary['ready_total'] ?? 0), 2), 'text-success'],
          ['Hold Amount', '$' . number_format((float)($summary['hold_total'] ?? 0), 2), 'text-warning'],
          ['Released Amount', '$' . number_format((float)($summary['released_total'] ?? 0), 2), 'text-primary'],
          ['Exceptions', (int)$summary['exception_lines'], 'text-warning'],
          ['Staged Lines', (int)$summary['staged_lines'], 'text-primary'],
          ['Claim Release Ready', (int)$summary['claim_release_ready'], 'text-primary'],
          ['Professional Release Ready', (int)$summary['professional_release_ready'], 'text-info'],
      ];
      foreach ($cards as [$label, $value, $class]): ?>
      <div class="col-6 col-md-3 col-xl-2">
        <div class="card shadow-sm h-100"><div class="card-body text-center"><div class="kpi-label"><?= xlt($label) ?></div><div class="kpi-val <?= $class ?>"><?= htmlspecialchars((string)$value) ?></div></div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Claim candidates') ?></span>
          <span class="small text-muted"><?= xlt('Stage institutional and professional review lines without leaving the workbench.') ?></span>
        </div>
        <div class="card-body border-bottom py-2">
          <form method="get" class="row g-2 align-items-end">
            <input type="hidden" name="facility_id" value="<?= (int)$facilityId ?>">
            <div class="col-12 col-md-4">
              <label class="form-label small mb-1"><?= xlt('Filter Status') ?></label>
              <select name="line_status" class="form-select form-select-sm">
                <option value=""><?= xlt('All statuses') ?></option>
                <?php foreach (['DRAFT','READY','HOLD','RELEASED','VOID'] as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>"<?= $filterStatus === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label small mb-1"><?= xlt('Filter Path') ?></label>
              <select name="line_path" class="form-select form-select-sm">
                <option value=""><?= xlt('All paths') ?></option>
                <?php foreach (['CLAIM_MANAGER','PROFESSIONAL_REVIEW','MODULE_LEDGER'] as $opt): ?>
                  <option value="<?= htmlspecialchars($opt) ?>"<?= $filterPath === $opt ? ' selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
              <button type="submit" class="btn btn-sm btn-outline-secondary"><?= xlt('Apply Filters') ?></button>
              <a class="btn btn-sm btn-outline-light border" href="billing_workbench.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Clear') ?></a>
            </div>
          </form>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Date') ?></th>
                <th><?= xlt('Patient') ?></th>
                <th><?= xlt('Type') ?></th>
                <th><?= xlt('Claim Family') ?></th>
                <th><?= xlt('Route') ?></th>
                <th><?= xlt('Action') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($claimCandidates as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$row['service_date']) ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string)($row['patient_name'] ?: ('PID ' . (int)$row['pid']))) ?></div>
                    <div class="mono-mini text-muted">#<?= (int)$row['episode_id'] ?> · <?= htmlspecialchars((string)$row['source_label']) ?></div>
                  </td>
                  <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)$row['episode_type']) ?></span></td>
                  <td><?= htmlspecialchars((string)$row['claim_family']) ?></td>
                  <td><?= bill_badge((string)$row['recommended_path']) ?></td>
                  <td>
                    <?php if (!empty($row['staged'])): ?>
                      <span class="badge text-bg-success"><?= xlt('Staged') ?></span>
                    <?php else: ?>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                        <input type="hidden" name="action" value="stage_one">
                        <input type="hidden" name="candidate_type" value="<?= htmlspecialchars((string)$row['candidate_type']) ?>">
                        <input type="hidden" name="episode_id" value="<?= (int)$row['episode_id'] ?>">
                        <input type="hidden" name="service_date" value="<?= htmlspecialchars((string)$row['service_date']) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-primary"><?= xlt('Stage') ?></button>
                      </form>
                    <?php endif; ?>
                    <div class="small text-muted mt-1"><?= htmlspecialchars((string)$row['recommended_action']) ?></div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$claimCandidates): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= xlt('No current claim candidates for this facility.') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Billing exceptions') ?></span>
          <span class="small text-muted"><?= xlt('Fix hold lines and incomplete claim/review lines here.') ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Date') ?></th>
                <th><?= xlt('Patient') ?></th>
                <th><?= xlt('Reason') ?></th>
                <th><?= xlt('Status') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($exceptions as $row): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$row['service_date']) ?></td>
                  <td><div class="fw-semibold"><?= htmlspecialchars((string)($row['patient_name'] ?: ('PID ' . (int)$row['pid']))) ?></div><div class="small text-muted"><?= htmlspecialchars((string)$row['description']) ?></div></td>
                  <td class="small text-muted"><?= htmlspecialchars((string)$row['exception_reason']) ?></td>
                  <td><?= line_status_badge((string)$row['status']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$exceptions): ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?= xlt('No current billing exceptions.') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-12 col-xl-5">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Aging watch') ?></span>
          <span class="small text-muted"><?= xlt('Ready and hold balances that may need attention before release.') ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Bucket') ?></th>
                <th><?= xlt('Ready') ?></th>
                <th><?= xlt('Ready Amount') ?></th>
                <th><?= xlt('Hold') ?></th>
                <th><?= xlt('Hold Amount') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($aging as $bucket): ?>
                <tr>
                  <td class="fw-semibold"><?= htmlspecialchars((string)$bucket['bucket']) ?></td>
                  <td><?= (int)$bucket['ready_count'] ?></td>
                  <td>$<?= htmlspecialchars(number_format((float)$bucket['ready_total'], 2)) ?></td>
                  <td><?= (int)$bucket['hold_count'] ?></td>
                  <td>$<?= htmlspecialchars(number_format((float)$bucket['hold_total'], 2)) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$aging): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= xlt('No aging data yet.') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-12 col-xl-7">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center justify-content-between">
          <span><?= xlt('Release batch history') ?></span>
          <span class="small text-muted"><?= xlt('Every release action now writes a batch key for follow-up and export tracking.') ?></span>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Batch') ?></th>
                <th><?= xlt('Target') ?></th>
                <th><?= xlt('Lines') ?></th>
                <th><?= xlt('Amount') ?></th>
                <th><?= xlt('Released') ?></th>
                <th><?= xlt('Action') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($releaseBatches as $batch): ?>
                <tr>
                  <td class="mono-mini fw-semibold"><?= htmlspecialchars((string)$batch['release_batch_key']) ?></td>
                  <td><?= release_target_badge((string)($batch['release_target'] ?? '')) ?></td>
                  <td><?= (int)$batch['line_count'] ?></td>
                  <td>$<?= htmlspecialchars(number_format((float)$batch['total_amount'], 2)) ?></td>
                  <td><?= htmlspecialchars((string)$batch['released_datetime']) ?></td>
                  <td><a class="btn btn-sm btn-outline-secondary" href="billing_workbench.php?facility_id=<?= urlencode((string)$facilityId) ?>&batch_key=<?= urlencode((string)$batch['release_batch_key']) ?>"><?= xlt('View batch') ?></a></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$releaseBatches): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= xlt('No released batches yet.') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <?php if ($selectedBatchKey !== ''): ?>
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Release batch detail') ?> <span class="mono-mini"><?= htmlspecialchars($selectedBatchKey) ?></span></span>
      <a class="btn btn-sm btn-outline-secondary" href="billing_workbench.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Clear batch view') ?></a>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Date') ?></th>
            <th><?= xlt('Patient / Episode') ?></th>
            <th><?= xlt('Description') ?></th>
            <th><?= xlt('Path') ?></th>
            <th><?= xlt('Target') ?></th>
            <th><?= xlt('Amount') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($batchLines as $line): ?>
            <tr>
              <td><?= htmlspecialchars((string)$line['service_date']) ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string)($line['patient_name'] ?: ('PID ' . (int)$line['pid']))) ?></div>
                <div class="mono-mini text-muted">#<?= (int)$line['episode_id'] ?><?php if (!empty($line['source_label'])): ?> · <?= htmlspecialchars((string)$line['source_label']) ?><?php endif; ?></div>
              </td>
              <td><?= htmlspecialchars((string)$line['description']) ?></td>
              <td><?= bill_badge((string)$line['billing_path']) ?></td>
              <td><?= release_target_badge((string)($line['release_target'] ?? '')) ?></td>
              <td>$<?= htmlspecialchars(number_format((float)$line['total_amount'], 2)) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$batchLines): ?>
            <tr><td colspan="6" class="text-center text-muted py-4"><?= xlt('No lines found for this batch key.') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
      <span><?= xlt('Episode financial summary') ?></span>
      <span class="small text-muted"><?= xlt('Use this to spot high-balance episodes before release or statement generation.') ?></span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th><?= xlt('Patient / Episode') ?></th>
            <th><?= xlt('Type') ?></th>
            <th><?= xlt('Outstanding') ?></th>
            <th><?= xlt('Released') ?></th>
            <th><?= xlt('Ready') ?></th>
            <th><?= xlt('Hold') ?></th>
            <th><?= xlt('Last Service') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($episodeFinancials as $row): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string)($row['patient_name'] ?: ('PID ' . (int)$row['pid']))) ?></div>
                <div class="mono-mini text-muted">#<?= (int)$row['episode_id'] ?><?php if (!empty($row['context_key'])): ?> · <?= htmlspecialchars((string)$row['context_key']) ?><?php endif; ?></div>
              </td>
              <td><span class="badge text-bg-secondary"><?= htmlspecialchars((string)($row['episode_type'] ?: 'N/A')) ?></span></td>
              <td>$<?= htmlspecialchars(number_format((float)$row['outstanding_amount'], 2)) ?></td>
              <td>$<?= htmlspecialchars(number_format((float)$row['released_amount'], 2)) ?></td>
              <td><?= (int)$row['ready_count'] ?></td>
              <td><?= (int)$row['hold_count'] ?></td>
              <td><?= htmlspecialchars((string)$row['latest_service_date']) ?></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$episodeFinancials): ?>
            <tr><td colspan="7" class="text-center text-muted py-4"><?= xlt('No episode financial summary available yet.') ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-12 col-xl-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header"><?= xlt('Purpose-aware quick add templates') ?></div>
        <div class="card-body d-flex flex-wrap gap-2">
          <?php foreach ($templates as $tpl): ?>
            <button type="button"
                    class="btn btn-sm btn-outline-secondary tpl-btn"
                    data-tpl-desc="<?= htmlspecialchars((string)$tpl['desc']) ?>"
                    data-tpl-category="<?= htmlspecialchars((string)$tpl['category']) ?>"
                    data-tpl-path="<?= htmlspecialchars((string)$tpl['path']) ?>"
                    data-tpl-price="<?= htmlspecialchars((string)$tpl['price']) ?>">
              <?= htmlspecialchars((string)$tpl['label']) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card shadow-sm">
        <div class="card-header"><?= xlt('Add module ledger line') ?></div>
        <div class="card-body">
          <form method="post" class="row g-2" id="ledger-add-form">
            <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
            <input type="hidden" name="facility_id" value="<?= (int)$facilityId ?>">
            <input type="hidden" name="action" value="add_line">
            <div class="col-6">
              <label class="form-label"><?= xlt('Service Date') ?></label>
              <input type="date" name="service_date" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Status') ?></label>
              <select name="status" class="form-select">
                <option value="READY"><?= xlt('Ready') ?></option>
                <option value="HOLD"><?= xlt('Hold') ?></option>
                <option value="DRAFT"><?= xlt('Draft') ?></option>
              </select>
            </div>
            <div class="col-6"><label class="form-label"><?= xlt('Episode ID') ?></label><input type="number" name="episode_id" class="form-control"></div>
            <div class="col-6"><label class="form-label"><?= xlt('PID') ?></label><input type="number" name="pid" class="form-control"></div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Billing Path') ?></label>
              <select name="billing_path" class="form-select">
                <option value="MODULE_LEDGER"><?= xlt('Module Ledger') ?></option>
                <option value="CLAIM_MANAGER"><?= xlt('Claim Manager') ?></option>
                <option value="PROFESSIONAL_REVIEW"><?= xlt('Professional Review') ?></option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label"><?= xlt('Category') ?></label>
              <select name="line_category" class="form-select">
                <option value="SERVICE"><?= xlt('Service') ?></option>
                <option value="RECURRING"><?= xlt('Recurring') ?></option>
                <option value="PRIVATE_PAY"><?= xlt('Private Pay') ?></option>
                <option value="SUPPLY"><?= xlt('Supply') ?></option>
                <option value="ADJUSTMENT"><?= xlt('Adjustment') ?></option>
              </select>
            </div>
            <div class="col-12"><label class="form-label"><?= xlt('Charge Code') ?></label><input type="text" name="charge_code" class="form-control"></div>
            <div class="col-12"><label class="form-label"><?= xlt('Description') ?></label><input type="text" name="description" class="form-control" required></div>
            <div class="col-6"><label class="form-label"><?= xlt('Quantity') ?></label><input type="number" step="0.01" min="0.01" name="quantity" class="form-control" value="1.00"></div>
            <div class="col-6"><label class="form-label"><?= xlt('Unit Price') ?></label><input type="number" step="0.01" min="0" name="unit_price" class="form-control" value="0.00"></div>
            <div class="col-12"><label class="form-label"><?= xlt('Review Reason') ?></label><input type="text" name="review_reason" class="form-control"></div>
            <div class="col-12"><label class="form-label"><?= xlt('Notes') ?></label><textarea name="notes" class="form-control" rows="3"></textarea></div>
            <div class="col-12 d-grid"><button class="btn btn-primary" type="submit"><?= xlt('Add ledger line') ?></button></div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-8">
      <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
          <span><?= xlt('Module ledger / release queue (Phase 4)') ?></span>
          <div class="d-flex gap-2 flex-wrap">
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
              <input type="hidden" name="action" value="release_ready_path">
              <input type="hidden" name="billing_path" value="CLAIM_MANAGER">
              <button type="submit" class="btn btn-sm btn-outline-primary"><?= xlt('Release claim-manager batch') ?></button>
            </form>
            <form method="post" class="d-inline">
              <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
              <input type="hidden" name="action" value="release_ready_path">
              <input type="hidden" name="billing_path" value="PROFESSIONAL_REVIEW">
              <button type="submit" class="btn btn-sm btn-outline-info"><?= xlt('Release professional batch') ?></button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th><?= xlt('Date') ?></th>
                <th><?= xlt('Patient / Episode') ?></th>
                <th><?= xlt('Description') ?></th>
                <th><?= xlt('Path') ?></th>
                <th><?= xlt('Status') ?></th>
                <th><?= xlt('Release Target') ?></th>
                <th><?= xlt('Amount') ?></th>
                <th><?= xlt('Actions') ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ledgerLines as $line): ?>
                <tr>
                  <td><?= htmlspecialchars((string)$line['service_date']) ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string)($line['patient_name'] ?: ('PID ' . (int)$line['pid']))) ?></div>
                    <div class="mono-mini text-muted">#<?= (int)$line['episode_id'] ?><?php if (!empty($line['source_label'])): ?> · <?= htmlspecialchars((string)$line['source_label']) ?><?php endif; ?></div>
                  </td>
                  <td>
                    <div><?= htmlspecialchars((string)$line['description']) ?></div>
                    <?php if (!empty($line['review_reason'])): ?><div class="small text-muted"><?= htmlspecialchars((string)$line['review_reason']) ?></div><?php endif; ?>
                  </td>
                  <td><?= bill_badge((string)$line['billing_path']) ?></td>
                  <td><?= line_status_badge((string)$line['status']) ?></td>
                  <td><?= release_target_badge((string)($line['release_target'] ?? '')) ?><?php if (!empty($line['release_batch_key'])): ?><div class="mono-mini text-muted mt-1"><?= htmlspecialchars((string)$line['release_batch_key']) ?></div><?php endif; ?></td>
                  <td>$<?= htmlspecialchars(number_format((float)$line['total_amount'], 2)) ?></td>
                  <td>
                    <?php if ((string)$line['status'] !== 'RELEASED'): ?>
                      <div class="d-flex gap-1 flex-wrap">
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                          <input type="hidden" name="new_status" value="READY">
                          <button type="submit" class="btn btn-sm btn-outline-success"><?= xlt('Ready') ?></button>
                        </form>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                          <input type="hidden" name="new_status" value="HOLD">
                          <input type="hidden" name="review_reason" value="<?= htmlspecialchars((string)($line['review_reason'] ?: 'Manual hold from workbench')) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-warning"><?= xlt('Hold') ?></button>
                        </form>
                        <?php $target = (string)($line['release_target'] ?: (($line['billing_path'] === 'PROFESSIONAL_REVIEW') ? 'PROFESSIONAL' : (($mode['release_target'] ?? 'BILLING_MANAGER')))); ?>
                        <form method="post" class="d-inline">
                          <input type="hidden" name="csrf_token_form" value="<?= htmlspecialchars((string)$csrf) ?>">
                          <input type="hidden" name="action" value="release_line">
                          <input type="hidden" name="line_id" value="<?= (int)$line['id'] ?>">
                          <input type="hidden" name="target" value="<?= htmlspecialchars($target) ?>">
                          <button type="submit" class="btn btn-sm btn-outline-primary"><?= xlt('Release') ?></button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="small text-muted"><?= xlt('Released') ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$ledgerLines): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><?= xlt('No module ledger lines yet.') ?></td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  document.querySelectorAll('.tpl-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const form = document.getElementById('ledger-add-form');
      if (!form) return;
      form.querySelector('[name="description"]').value = btn.dataset.tplDesc || '';
      form.querySelector('[name="line_category"]').value = btn.dataset.tplCategory || 'SERVICE';
      form.querySelector('[name="billing_path"]').value = btn.dataset.tplPath || 'MODULE_LEDGER';
      form.querySelector('[name="unit_price"]').value = btn.dataset.tplPrice || '0.00';
    });
  });
</script>
</body>
</html>









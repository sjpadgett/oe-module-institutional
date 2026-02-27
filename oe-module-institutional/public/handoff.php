<?php

require_once __DIR__ . '/_bootstrap.php';

use OpenEMR\Modules\Institutional\Submodule\Handoff\Repository\HandoffRepository;

if (!$manifest->featureEnabled('handoff')) {
    die(xlt('Handoff Report is disabled by manifest'));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));

$settingsRepo = new SettingsRepository();
$facilitySettings = $settingsRepo->all($facilityId);
if (isset($triageStandard) && method_exists($triageStandard, 'applyColorOverridesFromSettings')) {
    $triageStandard->applyColorOverridesFromSettings($facilitySettings);
}


$repo = new HandoffRepository();
$rows = $repo->fetchHandoff($facilityId);

$now = new DateTimeImmutable();
$printed = $now->format('F j, Y  g:i A');

$href = institutional_bootstrap5_href($manifest);

// ----------------------------------------------------------------- helpers

/**
 * Format vitals for handoff table.
 * Returns compact string like "130/80  HR 88  RR 18  SpO₂ 98%  T 98.6°F"
 */
function handoff_vitals(array $row): string
{
    $parts = [];

    if (!empty($row['bp_systolic']) && !empty($row['bp_diastolic'])) {
        $sbp = (int)$row['bp_systolic'];
        $dbp = (int)$row['bp_diastolic'];
        $flag = $sbp <= 100 ? ' ⚠' : '';
        $parts[] = "<span class='hv-bp" . ($sbp <= 100 ? ' hv-warn' : '') . "'>{$sbp}/{$dbp}{$flag}</span>";
    }
    if (!empty($row['hr'])) {
        $hr = (int)$row['hr'];
        $cls = ($hr > 130 || $hr < 50) ? ' hv-warn' : '';
        $parts[] = "<span class='hv{$cls}'>HR {$hr}</span>";
    }
    if (!empty($row['rr'])) {
        $rr = (int)$row['rr'];
        $cls = $rr >= 22 ? ' hv-warn' : '';
        $parts[] = "<span class='hv{$cls}'>RR {$rr}</span>";
    }
    if (!empty($row['spo2'])) {
        $sp = (int)$row['spo2'];
        $cls = $sp < 94 ? ' hv-crit' : ($sp < 97 ? ' hv-warn' : '');
        $parts[] = "<span class='hv{$cls}'>SpO₂ {$sp}%</span>";
    }
    if (!empty($row['temp_f'])) {
        $t = (float)$row['temp_f'];
        $cls = ($t >= 101.5 || $t < 96.8) ? ' hv-warn' : '';
        $parts[] = "<span class='hv{$cls}'>T " . number_format($t, 1) . "°F</span>";
    }
    if (!empty($row['gcs'])) {
        $g = (int)$row['gcs'];
        $cls = $g <= 8 ? ' hv-crit' : ($g < 13 ? ' hv-warn' : '');
        $parts[] = "<span class='hv{$cls}'>GCS {$g}</span>";
    }

    if (empty($parts)) {
        return '<span class="text-muted">—</span>';
    }

    return implode(' ', $parts);
}

/**
 * Compute qSOFA score from handoff row.
 */
function handoff_qsofa(array $row): int
{
    $score = 0;
    $gcs = isset($row['gcs']) && $row['gcs'] !== '' ? (int)$row['gcs'] : null;
    $rr = isset($row['rr']) && $row['rr'] !== '' ? (int)$row['rr'] : null;
    $sbp = isset($row['bp_systolic']) && $row['bp_systolic'] !== '' ? (int)$row['bp_systolic'] : null;

    if ($gcs !== null && $gcs < 15) $score++;
    if ($rr !== null && $rr >= 22) $score++;
    if ($sbp !== null && $sbp <= 100) $score++;

    return $score;
}

/**
 * Format elapsed time since arrival.
 */
function handoff_elapsed(string $dt): string
{
    $ts = strtotime($dt);
    if (!$ts) return '—';
    $mins = (int)round((time() - $ts) / 60);
    if ($mins < 60) return $mins . 'm';
    $h = intdiv($mins, 60);
    $m = $mins % 60;
    return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= xlt('Shift Handoff Report') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($href): ?>
        <link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
    <style>
      @media print {
        .no-print {
          display: none !important;
        }

        body {
          background: white !important;
          font-size: 10pt;
        }

        .handoff-table {
          font-size: 8.5pt;
        }

        .page-break {
          page-break-after: always;
        }

        h1 {
          font-size: 14pt;
        }
      }

      body {
        background: #f8f9fa;
      }

      .handoff-header {
        background: #1e2740;
        color: #fff;
        padding: 12px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
      }

      .handoff-table {
        font-size: .82rem;
      }

      .handoff-table th {
        background: #e9ecef;
        font-size: .75rem;
        text-transform: uppercase;
        letter-spacing: .04em;
      }

      .handoff-table td {
        vertical-align: middle;
      }

      .hv-warn {
        color: #856404;
        font-weight: 600;
      }

      .hv-crit {
        color: #842029;
        font-weight: 700;
      }

      .qsofa-2 {
        background: #fff3cd;
      }

      .qsofa-3 {
        background: #f8d7da;
      }

      .esi-1 {
        background: #842029;
        color: #fff;
      }

      .esi-2 {
        background: #dc3545;
        color: #fff;
      }

      .esi-3 {
        background: #ffc107;
        color: #000;
      }

      .esi-4 {
        background: #198754;
        color: #fff;
      }

      .esi-5 {
        background: #0dcaf0;
        color: #000;
      }
      <?= $triageStandard->cssRules() ?>

      .task-overdue {
        color: #dc3545;
        font-weight: 600;
      }

      .mar-pending {
        color: #856404;
        font-weight: 600;
      }

      .sep-row {
        background: #f0f4ff;
        font-size: .7rem;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .06em;
      }

      .vitals-age {
        font-size: .7rem;
        color: #6c757d;
        display: block;
      }
    </style>
</head>
<body>

    <!-- Print header (visible on screen too) -->
    <div class="handoff-header no-print">
        <div>
            <div class="fw-bold fs-5"><?= xlt('Shift Handoff Report') ?></div>
            <div class="small opacity-75"><?= xlt('Printed:') ?> <?= htmlspecialchars($printed) ?></div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-light btn-sm" onclick="window.print()">🖨 <?= xlt('Print') ?></button>
            <a class="btn btn-outline-light btn-sm"
                href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('ED Board') ?></a>
            <a class="btn btn-outline-light btn-sm"
                href="handoff.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt('Refresh') ?></a>
        </div>
    </div>

    <!-- Print-only header -->
    <div style="display:none;" class="d-print-block mb-2 border-bottom pb-2">
        <strong style="font-size:14pt;"><?= xlt('Shift Handoff Report') ?></strong>
        <span style="float:right; font-size:9pt;"><?= htmlspecialchars($printed) ?></span>
    </div>

    <div class="container-fluid py-3">

        <?php if (empty($rows)): ?>
            <div class="alert alert-info"><?= xlt('No active episodes at this time.') ?></div>
        <?php else: ?>

            <!-- Summary line -->
            <?php
            $totalEpisodes = count($rows);
            $pendingMar = array_sum(array_column($rows, 'pending_mar_count'));
            $sepsisCount = count(array_filter($rows, fn($r) => handoff_qsofa($r) >= 2));
            $overdueCount = count(array_filter($rows, function ($r) {
                $due = $r['next_task_due'] ?? null;
                return $due && strtotime((string)$due) < time();
            }));
            ?>
            <div class="d-flex gap-3 mb-3 flex-wrap align-items-center no-print">
                <span class="badge text-bg-primary fs-6"><?= $totalEpisodes ?> <?= xlt('Patients') ?></span>
                <?php if ($sepsisCount > 0): ?>
                    <span class="badge text-bg-danger"><?= $sepsisCount ?> <?= xlt('Sepsis Risk (qSOFA ≥2)') ?></span>
                <?php endif; ?>
                <?php if ($pendingMar > 0): ?>
                    <span class="badge text-bg-warning text-dark"><?= $pendingMar ?> <?= xlt('MAR Pending') ?></span>
                <?php endif; ?>
                <?php if ($overdueCount > 0): ?>
                    <span class="badge text-bg-danger"><?= $overdueCount ?> <?= xlt('Tasks Overdue') ?></span>
                <?php endif; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm handoff-table align-middle">
                    <thead>
                    <tr>
                        <th style="width:60px;"><?= xlt('Room') ?></th>
                        <th style="width:60px;"><?= xlt('Episode') ?></th>
                        <th style="width:50px;"><?= htmlspecialchars($triageStandard->columnLabel()) ?></th>
                        <th style="width:50px;"><?= xlt('Time') ?></th>
                        <th><?= xlt('Chief Complaint') ?></th>
                        <th><?= xlt('Status') ?></th>
                        <th style="min-width:240px;"><?= xlt('Last Vitals') ?></th>
                        <th style="width:80px;"><?= xlt('qSOFA') ?></th>
                        <th><?= xlt('Next Task') ?></th>
                        <th style="width:60px;"><?= xlt('MAR') ?></th>
                        <th><?= xlt('Nurse') ?></th>
                        <th><?= xlt('Provider') ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $lastRoom = null;
                    foreach ($rows as $r):
                        $room = (string)($r['location_name'] ?? '');
                        $qsofa = handoff_qsofa($r);
                        $rowClass = match (true) {
                            $qsofa >= 3 => 'qsofa-3',
                            $qsofa >= 2 => 'qsofa-2',
                            default => '',
                        };

                        // Room divider
                        if ($room !== $lastRoom):
                            $lastRoom = $room;
                            ?>
                            <tr class="sep-row">
                                <td colspan="12">
                                    <?= $room !== '' ? htmlspecialchars($room) : xlt('Unassigned') ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr class="<?= $rowClass ?>">
                            <td class="fw-semibold">
                                <?= htmlspecialchars($room ?: '—') ?>
                            </td>
                            <td>
                                <a href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>#ep<?= (int)$r['id'] ?>"
                                    class="no-print text-decoration-none">
                                    #<?= (int)$r['id'] ?>
                                </a>
                                <span class="d-none d-print-inline">#<?= (int)$r['id'] ?></span>
                                <div class="text-muted" style="font-size:.7rem;">PID <?= (int)$r['pid'] ?></div>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($r['acuity_esi'])): ?>
                                    <span class="badge <?= htmlspecialchars($triageStandard->badgeClass((int)$r['acuity_esi'])) ?>"><?= htmlspecialchars($triageStandard->shortLabel((int)$r['acuity_esi'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-nowrap">
                                <?= htmlspecialchars(handoff_elapsed((string)($r['start_datetime'] ?? ''))) ?>
                            </td>
                            <td><?= htmlspecialchars((string)($r['chief_complaint'] ?? '—')) ?></td>
                            <td>
                                <?php $ws = (string)($r['workflow_status'] ?? ''); ?>
                                <span class="badge text-bg-light border text-dark" style="font-size:.72rem;">
                        <?= htmlspecialchars($ws ?: '—') ?>
              </span>
                            </td>
                            <td>
                                <?= handoff_vitals($r) ?>
                                <?php if (!empty($r['vitals_datetime'])): ?>
                                    <span class="vitals-age">
                                    <?= htmlspecialchars(institutional_human_elapsed((string)$r['vitals_datetime'])) ?>
                                    <?= xlt('ago') ?>
                </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center fw-bold">
                                <?php if ($qsofa >= 2): ?>
                                    <span class="text-danger"><?= $qsofa ?>/3 ⚠</span>
                                <?php elseif ($qsofa === 1): ?>
                                    <span class="text-warning"><?= $qsofa ?>/3</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $taskType = (string)($r['next_task_type'] ?? '');
                                $taskDue = (string)($r['next_task_due'] ?? '');
                                if ($taskType !== '' && $taskDue !== ''):
                                    $isOverdue = strtotime($taskDue) < time();
                                    ?>
                                    <span class="<?= $isOverdue ? 'task-overdue' : '' ?>">
                                    <?= htmlspecialchars($taskType) ?>
                                        <?php if ($isOverdue): ?>⚠<?php endif; ?>
                </span>
                                    <div style="font-size:.7rem; color:#6c757d;">
                                        <?= htmlspecialchars(substr($taskDue, 0, 16)) ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php $mc = (int)($r['pending_mar_count'] ?? 0); ?>
                                <?php if ($mc > 0): ?>
                                    <span class="badge text-bg-warning text-dark mar-pending"><?= $mc ?></span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?php $nn = trim((string)($r['nurse_name'] ?? '')); ?>
                                <?= $nn !== '' ? htmlspecialchars($nn) : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="small">
                                <?php $pn = trim((string)($r['provider_name'] ?? '')); ?>
                                <?= $pn !== '' ? htmlspecialchars($pn) : '<span class="text-muted">—</span>' ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-muted small mt-2 no-print">
                <?= count($rows) ?> <?= xlt('active patients') ?> &mdash;
                <?= xlt('Generated') ?> <?= htmlspecialchars($printed) ?>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>



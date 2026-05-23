<?php

/**
 * public/shared/observations.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

/**
 * public/shared/observations.php — Observation History & Trends
 *
 * Two modes, same URL:
 *
 *   Facility mode  (no ?episode_id)
 *     Entry point from Tracking → Observations menu.
 *     Shows a facility-wide dashboard of recent observations grouped by patient.
 *     Time window filter: Last 24h / 7 days / 30 days.
 *     Each row links to the episode view for that patient.
 *
 *   Episode mode   (?episode_id=N)
 *     Full reading history + Chart.js trend chart for a single episode.
 *     Filter by type and time window.
 *     Back button routes by episode type (AL/IP/HBC profile or ED board).
 *
 * Feature gate: observations
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

if (!$manifest->featureEnabled('observations')) {
    oei_exit_with_alert(xlt('Observations feature is not enabled for this facility.'), 'info');
}

$episodeId = (int)($_GET['episode_id'] ?? 0);
$pid = (int)($_GET['pid'] ?? 0);
$facilityId = $_oei_facilityId ?? 1;
$obsRepo = new SharedObservationRepository();
$allTypes = $obsRepo->listTypes();

// Back-links use relative paths (../type/page) — safe regardless of webroot config
// Relative self-reference — safe on any server
$baseUrl = 'observations.php';
$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark';

// ═════════════════════════════════════════════════════════════════════════════
//  FACILITY MODE — no episode_id in URL
// ═════════════════════════════════════════════════════════════════════════════
if ($episodeId === 0) {

    $days = (int)($_GET['days'] ?? 24);
    $hours = match ($days) {
        7 => 168,
        30 => 720,
        default => 24
    };
    $label = match ($days) {
        7 => xlt('Last 7 days'),
        30 => xlt('Last 30 days'),
        default => xlt('Last 24 hours')
    };

    $flagged = $obsRepo->listFlagged($facilityId, $hours, 500);

    // Batch patient names
    $pids = array_unique(array_filter(array_map(fn($r) => (int)($r['pid'] ?? 0), $flagged)));
    $names = function_exists('oei_patient_names') && $pids
        ? oei_patient_names(array_values($pids)) : [];

    // Group by episode_id for display
    $byEpisode = [];
    foreach ($flagged as $row) {
        $eid = (int)($row['episode_id'] ?? 0);
        if (!isset($byEpisode[$eid])) {
            $byEpisode[$eid] = ['pid' => (int)($row['pid'] ?? 0), 'rows' => []];
        }
        $byEpisode[$eid]['rows'][] = $row;
    }

    $pageTitle = xlt('Observations — Facility Dashboard');
    ?>
    <!DOCTYPE html>
    <html lang="en" data-bs-theme="<?= htmlspecialchars($_oei_theme ?? 'light') ?>">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
        <style>
          .panel-card {
            border-left: 3px solid #457b9d;
          }

          .source-fhir {
            background: #0d6efd !important;
          }

          .source-device {
            background: #198754 !important;
          }

          .source-import {
            background: #0dcaf0 !important;
            color: #000 !important;
          }

          .source-manual {
            background: #6c757d !important;
          }

          .obs-row-flagged td {
            background: rgba(220, 53, 69, .06);
          }
        </style>
    </head>
    <body class="<?= $__bgClass ?>">
        <div class="container-fluid p-3" style="max-width:1100px">

            <!-- Header -->
            <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
                <div>
                    <h5 class="mb-0">&#128225; <?= xlt('Extended Observations') ?></h5>
                    <div class="text-muted small"><?= xlt('Facility dashboard') ?> &nbsp;·&nbsp; <?= htmlspecialchars($label) ?></div>
                </div>
                <div class="ms-auto d-flex gap-2 flex-wrap align-items-center">
                    <!-- Time window filter -->
                    <div class="btn-group btn-group-sm">
                        <?php foreach ([24 => xlt('24h'), 7 => xlt('7d'), 30 => xlt('30d')] as $d => $lbl): ?>
                            <a href="<?= htmlspecialchars($baseUrl . '?facility_id=' . $facilityId . '&days=' . $d) ?>"
                                class="btn btn-outline-secondary <?= $days === $d ? 'active' : '' ?>">
                                <?= $lbl ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($flagged) > 0): ?>
                        <span class="badge bg-danger">&#9888; <?= count($flagged) ?> <?= xlt('flagged readings') ?></span>
                    <?php else: ?>
                        <span class="badge bg-success">&#10003; <?= xlt('No flagged readings') ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($byEpisode)): ?>
                <!-- Clean state -->
                <div class="card panel-card">
                    <div class="card-body text-center py-5">
                        <div style="font-size:2.5rem">&#10003;</div>
                        <h5 class="mt-2 text-success"><?= xlt('No flagged observations') ?></h5>
                        <p class="text-muted mb-0">
                            <?= xlt('No observation readings exceeded alert bounds during the selected window.') ?>
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Flagged observations grouped by patient/episode -->
                <?php foreach ($byEpisode as $eid => $ep):
                    $epPid = (int)$ep['pid'];
                    $patName = $names[$epPid] ?? ('PID ' . $epPid);
                    $episodeUrl = htmlspecialchars($baseUrl . '?episode_id=' . $eid . '&pid=' . $epPid . '&facility_id=' . $facilityId);
                    $epRows = $ep['rows'];
                    ?>
                    <div class="card panel-card mb-3">
                        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
                            <span class="fw-semibold"><?= htmlspecialchars($patName) ?></span>
                            <span class="text-muted small"><?= xlt('Episode') ?> #<?= $eid ?></span>
                            <span class="badge bg-danger ms-1">
        <?= count($epRows) ?> <?= xlt('flagged') ?>
      </span>
                            <a href="<?= $episodeUrl ?>" class="btn btn-sm btn-outline-primary ms-auto">
                                <?= xlt('Full history') ?> &rarr;
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                                <thead class="table-light">
                                <tr>
                                    <th><?= xlt('Date/Time') ?></th>
                                    <th><?= xlt('Type') ?></th>
                                    <th><?= xlt('Value') ?></th>
                                    <th><?= xlt('Range') ?></th>
                                    <th><?= xlt('Source') ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($epRows as $row):
                                    $src = (string)($row['source_type'] ?? 'MANUAL');
                                    $srcClass = match ($src) {
                                        'FHIR' => 'source-fhir',
                                        'DEVICE' => 'source-device',
                                        'IMPORT' => 'source-import',
                                        default => 'source-manual',
                                    };
                                    $valStr = '';
                                    if ($row['value_numeric'] !== null) {
                                        $valStr = rtrim(rtrim(number_format((float)$row['value_numeric'], 3, '.', ''), '0'), '.');
                                        if (!empty($row['unit'])) {
                                            $valStr .= ' ' . $row['unit'];
                                        }
                                    } elseif (!empty($row['value_text'])) {
                                        $valStr = (string)$row['value_text'];
                                    }
                                    $loStr = $row['alert_low'] !== null ? number_format((float)$row['alert_low'], 1) : '—';
                                    $hiStr = $row['alert_high'] !== null ? number_format((float)$row['alert_high'], 1) : '—';
                                    ?>
                                    <tr class="obs-row-flagged">
                                        <td class="text-muted text-nowrap">
                                            <?= htmlspecialchars(substr((string)($row['observed_datetime'] ?? ''), 0, 16)) ?>
                                        </td>
                                        <td class="fw-semibold">
                                            <?= htmlspecialchars((string)($row['display_name'] ?? $row['obs_type_code'] ?? '')) ?>
                                        </td>
                                        <td class="text-danger fw-bold">
                                            <?= htmlspecialchars($valStr) ?: '<span class="text-muted">—</span>' ?>
                                        </td>
                                        <td class="text-muted small text-nowrap">
                                            <?= htmlspecialchars($loStr) ?> – <?= htmlspecialchars($hiStr) ?>
                                        </td>
                                        <td>
            <span class="badge <?= $srcClass ?>" style="font-size:.65rem">
              <?= htmlspecialchars($src) ?>
            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <?= institutional_bootstrap5_js_tag() ?>
    </body>
    </html>
    <?php
    // Facility mode complete — stop here
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
//  EPISODE MODE — episode_id provided
// ═════════════════════════════════════════════════════════════════════════════

// Resolve pid and episode type
$episodeType = '';
if (function_exists('sqlQuery')) {
    $epRow = sqlQuery(
        'SELECT pid, type FROM oei_episode WHERE id = ? LIMIT 1',
        [$episodeId]
    );
    if ($epRow) {
        if ($pid === 0) {
            $pid = (int)$epRow['pid'];
        }
        $episodeType = strtoupper((string)($epRow['type'] ?? ''));
    }
}

// Back URL by episode type
$backUrl = match ($episodeType) {
    'AL'  => '../al/profile.php?episode_id='  . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'HBC' => '../hbc/profile.php?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    'IP'  => '../ip/profile.php?episode_id='  . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId,
    default => '../ed_board.php?facility_id=' . $facilityId,
};

// Filters
$selectedType = trim((string)($_GET['type'] ?? ''));
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90, 0], true)) {
    $days = 30;
}

// Fetch + date-filter
$limitPerFetch = ($days === 0) ? 2000 : min(2000, $days * 50);
$filterCodes = $selectedType !== '' ? [$selectedType] : [];
$rawRows = $obsRepo->listForEpisode($episodeId, $limitPerFetch, $filterCodes);
$cutoff = $days > 0 ? strtotime("-{$days} days") : 0;
$rows = array_values(array_filter($rawRows, function ($r) use ($cutoff) {
    if ($cutoff === 0) return true;
    $ts = strtotime((string)($r['observed_datetime'] ?? ''));
    return $ts !== false && $ts >= $cutoff;
}));

// Chart data
$chartData = [];
$chartLabel = '';
if ($selectedType !== '' && !empty($allTypes[$selectedType])) {
    $trendRows = $obsRepo->trend($episodeId, $selectedType, 200);
    if ($days > 0) {
        $trendRows = array_values(array_filter($trendRows,
            fn($r) => strtotime((string)$r['datetime']) >= $cutoff
        ));
    }
    $chartData = array_column($trendRows, 'value');
    $chartLabel = (string)($allTypes[$selectedType]['display_name'] ?? $selectedType);
}

// Summary counts
$flaggedCount = count(array_filter($rows, fn($r) => !empty($r['is_flagged'])));
$deviceCount = count(array_filter($rows, fn($r) => ($r['source_type'] ?? '') === 'DEVICE'));
$fhirCount = count(array_filter($rows, fn($r) => ($r['source_type'] ?? '') === 'FHIR'));

// Patient name
$patientName = '';
if ($pid > 0 && function_exists('oei_patient_names')) {
    $names = oei_patient_names([$pid]);
    $patientName = $names[$pid] ?? '';
}

$pageTitle = xlt('Observations') . ($patientName ? ' — ' . htmlspecialchars($patientName) : '');
$qBase = '?episode_id=' . $episodeId . '&pid=' . $pid . '&facility_id=' . $facilityId;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($_oei_theme ?? 'light') ?>">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
    <style>
      .panel-card {
        border-left: 3px solid #457b9d;
      }

      .source-fhir {
        background: #0d6efd !important;
      }

      .source-device {
        background: #198754 !important;
      }

      .source-import {
        background: #0dcaf0 !important;
        color: #000 !important;
      }

      .source-manual {
        background: #6c757d !important;
      }

      .filter-bar {
        background: var(--bs-body-tertiary);
        border-radius: .375rem;
        padding: .75rem 1rem;
      }

      .chart-wrap {
        position: relative;
        height: 200px;
      }

      .obs-value-flagged {
        color: #dc3545;
        font-weight: 700;
      }

      .obs-row-flagged td {
        background: rgba(220, 53, 69, .06);
      }
    </style>
</head>
<body class="<?= $__bgClass ?>">
    <div class="container-fluid p-3" style="max-width:1100px">

        <!-- Header -->
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <a href="<?= htmlspecialchars($backUrl) ?>" class="btn btn-sm btn-outline-secondary">
                &larr; <?= xlt('Profile') ?>
            </a>
            <a href="<?= htmlspecialchars($baseUrl . '?facility_id=' . $facilityId) ?>"
                class="btn btn-sm btn-outline-secondary">
                &#127968; <?= xlt('Facility view') ?>
            </a>
            <div>
                <h5 class="mb-0">&#128225; <?= xlt('Extended Observations') ?></h5>
                <?php if ($patientName): ?>
                    <div class="text-muted small">
                        <?= htmlspecialchars($patientName) ?> &nbsp;·&nbsp; <?= xlt('Episode') ?> #<?= $episodeId ?>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Summary pills -->
            <div class="ms-auto d-flex gap-2 flex-wrap">
                <span class="badge bg-secondary"><?= count($rows) ?> <?= xlt('readings') ?></span>
                <?php if ($flaggedCount > 0): ?>
                    <span class="badge bg-danger">&#9888; <?= $flaggedCount ?> <?= xlt('flagged') ?></span>
                <?php endif; ?>
                <?php if ($deviceCount > 0): ?>
                    <span class="badge bg-success">&#128225; <?= $deviceCount ?> <?= xlt('device') ?></span>
                <?php endif; ?>
                <?php if ($fhirCount > 0): ?>
                    <span class="badge bg-primary">FHIR <?= $fhirCount ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar mb-3">
            <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="row g-2 align-items-end">
                <input type="hidden" name="episode_id" value="<?= $episodeId ?>">
                <input type="hidden" name="pid" value="<?= $pid ?>">
                <input type="hidden" name="facility_id" value="<?= $facilityId ?>">

                <div class="col-sm-5 col-md-4">
                    <label class="form-label form-label-sm fw-semibold mb-1"><?= xlt('Observation type') ?></label>
                    <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value=""><?= xlt('All types') ?></option>
                        <?php foreach ($allTypes as $code => $t): ?>
                            <option value="<?= htmlspecialchars($code) ?>" <?= $code === $selectedType ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['display_name']) ?>
                                <?php if ($t['default_unit']): ?>(<?= htmlspecialchars($t['default_unit']) ?>)<?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-sm-4 col-md-3">
                    <label class="form-label form-label-sm fw-semibold mb-1"><?= xlt('Time window') ?></label>
                    <select name="days" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="7" <?= $days === 7 ? 'selected' : '' ?>><?= xlt('Last 7 days') ?></option>
                        <option value="30" <?= $days === 30 ? 'selected' : '' ?>><?= xlt('Last 30 days') ?></option>
                        <option value="90" <?= $days === 90 ? 'selected' : '' ?>><?= xlt('Last 90 days') ?></option>
                        <option value="0" <?= $days === 0 ? 'selected' : '' ?>><?= xlt('All time') ?></option>
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><?= xlt('Filter') ?></button>
                    <a href="<?= htmlspecialchars($baseUrl . $qBase) ?>"
                        class="btn btn-sm btn-outline-secondary ms-1"><?= xlt('Reset') ?></a>
                </div>
            </form>
        </div>

        <!-- Trend chart -->
        <?php if ($selectedType !== '' && count($chartData) > 1): ?>
            <div class="card panel-card mb-3">
                <div class="card-header small fw-semibold">
                    &#128200; <?= xlt('Trend') ?> — <?= htmlspecialchars($chartLabel) ?>
                    <?php if (!empty($allTypes[$selectedType]['default_unit'])): ?>
                        <span class="text-muted">(<?= htmlspecialchars($allTypes[$selectedType]['default_unit']) ?>)</span>
                    <?php endif; ?>
                </div>
                <div class="card-body py-2">
                    <div class="chart-wrap">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
            <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
            <script>
                (function () {
                    var data = <?= json_encode(array_values($chartData), JSON_NUMERIC_CHECK) ?>;
                    var lo = <?= json_encode($allTypes[$selectedType]['alert_low'] ?? null) ?>;
                    var hi = <?= json_encode($allTypes[$selectedType]['alert_high'] ?? null) ?>;
                    new Chart(document.getElementById('trendChart'), {
                        type: 'line',
                        data: {
                            labels: data.map(function () {
                                return '';
                            }),
                            datasets: [{
                                data: data,
                                borderColor: '#457b9d',
                                backgroundColor: 'rgba(69,123,157,.08)',
                                pointBackgroundColor: data.map(function (v) {
                                    if (lo !== null && v < lo) return '#dc3545';
                                    if (hi !== null && v > hi) return '#dc3545';
                                    return '#457b9d';
                                }),
                                pointRadius: data.length > 60 ? 2 : 4,
                                tension: 0.3,
                                fill: true,
                            }]
                        },
                        options: {
                            animation: false,
                            plugins: {
                                legend: {display: false},
                                tooltip: {
                                    callbacks: {
                                        label: function (ctx) {
                                            var v = ctx.raw, flag = '';
                                            if (lo !== null && v < lo) flag = ' \u26a0 LOW';
                                            if (hi !== null && v > hi) flag = ' \u26a0 HIGH';
                                            return '<?= addslashes($chartLabel) ?>: ' + v + flag;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {display: false},
                                y: {grid: {color: 'rgba(128,128,128,.12)'}, ticks: {font: {size: 11}}}
                            }
                        }
                    });
                })();
            </script>
        <?php elseif ($selectedType !== '' && count($chartData) <= 1): ?>
            <div class="alert alert-info small mb-3">
                <?= xlt('Not enough data points for a trend chart. At least 2 readings of this type are needed.') ?>
            </div>
        <?php endif; ?>

        <!-- Readings table -->
        <?php if (empty($rows)): ?>
            <div class="card panel-card">
                <div class="card-body text-center py-5 text-muted">
                    <?= xlt('No observations found for the selected filters.') ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card panel-card">
                <div class="card-header small fw-semibold">
                    <?= xlt('Readings') ?> <span class="text-muted ms-2">(<?= count($rows) ?>)</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0" style="font-size:.82rem">
                        <thead class="table-light">
                        <tr>
                            <th><?= xlt('Date/Time') ?></th>
                            <th><?= xlt('Type') ?></th>
                            <th><?= xlt('Value') ?></th>
                            <th><?= xlt('Range') ?></th>
                            <th><?= xlt('Source') ?></th>
                            <th><?= xlt('Status') ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row):
                            $isFlagged = !empty($row['is_flagged']);
                            $src = (string)($row['source_type'] ?? 'MANUAL');
                            $srcClass = match ($src) {
                                'FHIR' => 'source-fhir',
                                'DEVICE' => 'source-device',
                                'IMPORT' => 'source-import',
                                default => 'source-manual',
                            };
                            $valStr = '';
                            if ($row['value_numeric'] !== null) {
                                $valStr = rtrim(rtrim(number_format((float)$row['value_numeric'], 3, '.', ''), '0'), '.');
                                if (!empty($row['unit'])) {
                                    $valStr .= ' ' . $row['unit'];
                                }
                            } elseif (!empty($row['value_text'])) {
                                $valStr = (string)$row['value_text'];
                            }
                            $loStr = $row['alert_low'] !== null ? number_format((float)$row['alert_low'], 1) : '—';
                            $hiStr = $row['alert_high'] !== null ? number_format((float)$row['alert_high'], 1) : '—';
                            ?>
                            <tr class="<?= $isFlagged ? 'obs-row-flagged' : '' ?>">
                                <td class="text-muted text-nowrap">
                                    <?= htmlspecialchars(substr((string)($row['observed_datetime'] ?? ''), 0, 16)) ?>
                                </td>
                                <td class="fw-semibold">
                                    <?= htmlspecialchars((string)($row['display_name'] ?? $row['obs_type_code'] ?? '')) ?>
                                </td>
                                <td class="<?= $isFlagged ? 'obs-value-flagged' : '' ?>">
                                    <?= $valStr !== '' ? htmlspecialchars($valStr) : '<span class="text-muted">—</span>' ?>
                                </td>
                                <td class="text-muted small text-nowrap">
                                    <?= htmlspecialchars($loStr) ?> – <?= htmlspecialchars($hiStr) ?>
                                </td>
                                <td>
            <span class="badge <?= $srcClass ?>" style="font-size:.65rem">
              <?= htmlspecialchars($src) ?>
            </span>
                                    <?php if (!empty($row['device_id'])): ?>
                                        <span class="text-muted ms-1" style="font-size:.7rem"
                                            title="<?= htmlspecialchars((string)$row['device_id']) ?>">&#128225;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isFlagged): ?>
                                        <span class="badge bg-danger" style="font-size:.65rem">&#9888; <?= xlt('Flagged') ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success" style="font-size:.65rem">&#10003; <?= xlt('Normal') ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div><!-- /container -->
    <?= institutional_bootstrap5_js_tag() ?>
</body>
</html>













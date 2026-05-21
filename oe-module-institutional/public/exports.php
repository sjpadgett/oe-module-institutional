<?php

/**
 * public/exports.php
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
$pageTitle = xlt('Exports');
require __DIR__ . '/../src/Core/Ui/partials/page_title.php';
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\TransferTracking\Repository\TransferRepository;

if (!$manifest->featureEnabled('admin_exports')) {
    die(xlt("Exports is disabled by manifest"));
}

$facilityId = (int)($_GET['facility_id'] ?? ($GLOBALS['facility_default_id'] ?? 1));
$action = (string)($_GET['action'] ?? '');

$episodeRepo = new EpisodeRepository();
$transferRepo = new TransferRepository();

$csrf = CsrfUtils::collectCsrfToken();

$today = date('Y-m-d');
$start = (string)($_GET['start'] ?? $today);
$end = (string)($_GET['end'] ?? $today);

function asDateTimeStart(string $d): string { return $d . " 00:00:00"; }
function asDateTimeEnd(string $d): string { return $d . " 23:59:59"; }

if ($action === 'csv_throughput') {
    // very lightweight: export episodes by date range (start_datetime) plus disposition and bh status columns already available
    $rows = $episodeRepo->fetchByDateRange($facilityId, asDateTimeStart($start), asDateTimeEnd($end), 2000);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="institutional_throughput_' . $start . '_to_' . $end . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['episode_id','pid','type','start_datetime','end_datetime','disposition','bh_status','chief_complaint']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'] ?? '',
            $r['pid'] ?? '',
            $r['type'] ?? '',
            $r['start_datetime'] ?? '',
            $r['end_datetime'] ?? '',
            $r['disposition'] ?? '',
            $r['bh_status'] ?? '',
            $r['chief_complaint'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

if ($action === 'csv_transfers') {
    $rows = $transferRepo->listRecentByFacility($facilityId, asDateTimeStart($start), asDateTimeEnd($end), 2000);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="institutional_transfers_' . $start . '_to_' . $end . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['episode_id','pid','transfer_type','status','receiving_name','requested','accepted','transport','updated']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['episode_id'] ?? '',
            $r['pid'] ?? '',
            $r['transfer_type'] ?? '',
            $r['status'] ?? '',
            $r['receiving_name'] ?? '',
            $r['requested_datetime'] ?? '',
            $r['accepted_datetime'] ?? '',
            $r['transport_datetime'] ?? '',
            $r['updated_datetime'] ?? '',
        ]);
    }
    fclose($out);
    exit;
}

// ── AL Census export ──────────────────────────────────────────────────────
if ($action === 'csv_al_census') {
    if (!$manifest->featureEnabled('al_board')) {
        die(xlt('AL Board is not enabled.'));
    }
    if (!function_exists('sqlStatement')) { die('DB unavailable'); }

    $res = sqlStatement(
        "SELECT e.id AS episode_id,
                e.pid,
                pd.lname, pd.fname, pd.DOB, pd.sex,
                ale.room, ale.unit, ale.care_level,
                ale.fall_risk_level, ale.fall_risk_score,
                ale.last_adl_score,
                DATEDIFF(NOW(), e.start_datetime) AS los_days,
                e.start_datetime AS admit_date,
                ale.admit_reason
         FROM oei_episode e
         JOIN oei_al_episode ale ON ale.episode_id = e.id
         JOIN patient_data pd ON pd.pid = e.pid
         WHERE e.facility_id = ? AND e.status = 'ACTIVE'
         ORDER BY ale.unit ASC, ale.room ASC",
        [$facilityId]
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="al_census_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['episode_id','pid','last_name','first_name','dob','sex',
                   'room','unit','care_level','fall_risk_level','fall_risk_score',
                   'last_adl_score','los_days','admit_date','admit_reason']);
    while ($r = sqlFetchArray($res)) {
        fputcsv($out, [
            $r['episode_id'], $r['pid'], $r['lname'], $r['fname'],
            $r['DOB'], $r['sex'], $r['room'], $r['unit'],
            $r['care_level'], $r['fall_risk_level'], $r['fall_risk_score'],
            $r['last_adl_score'], $r['los_days'], $r['admit_date'], $r['admit_reason'],
        ]);
    }
    fclose($out);
    exit;
}

// ── AL Incident Report export (for state regulatory reporting) ─────────────
if ($action === 'csv_al_incidents') {
    if (!$manifest->featureEnabled('al_incident')) {
        die(xlt('AL Incident reporting is not enabled.'));
    }
    if (!function_exists('sqlStatement')) { die('DB unavailable'); }

    $res = sqlStatement(
        "SELECT i.id AS incident_id,
                i.incident_datetime, i.incident_type, i.severity,
                e.pid, pd.lname, pd.fname,
                ale.room, ale.unit,
                i.location_description, i.narrative, i.corrective_action,
                i.reported_state, i.mandatory_report_sent,
                i.created_datetime
         FROM oei_incident i
         JOIN oei_episode e ON e.id = i.episode_id
         JOIN patient_data pd ON pd.pid = e.pid
         LEFT JOIN oei_al_episode ale ON ale.episode_id = e.id
         WHERE i.facility_id = ?
           AND i.incident_datetime BETWEEN ? AND ?
         ORDER BY i.incident_datetime DESC",
        [$facilityId, asDateTimeStart($start), asDateTimeEnd($end)]
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="al_incidents_' . $start . '_to_' . $end . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['incident_id','incident_datetime','incident_type','severity',
                   'pid','last_name','first_name','room','unit',
                   'location','narrative','corrective_action',
                   'reported_state','mandatory_report_sent','created_datetime']);
    while ($r = sqlFetchArray($res)) {
        fputcsv($out, [
            $r['incident_id'], $r['incident_datetime'], $r['incident_type'], $r['severity'],
            $r['pid'], $r['lname'], $r['fname'], $r['room'], $r['unit'],
            $r['location_description'], $r['narrative'], $r['corrective_action'],
            $r['reported_state'], $r['mandatory_report_sent'] ? 'Yes' : 'No',
            $r['created_datetime'],
        ]);
    }
    fclose($out);
    exit;
}

// ── IP Daily Census export ────────────────────────────────────────────────
if ($action === 'csv_ip_census') {
    if (!$manifest->featureEnabled('ip_board')) {
        die(xlt('IP Board is not enabled.'));
    }
    if (!function_exists('sqlStatement')) { die('DB unavailable'); }

    $res = sqlStatement(
        "SELECT e.id AS episode_id,
                e.pid,
                pd.lname, pd.fname, pd.DOB, pd.sex,
                ip.bed, ip.unit, ip.service, ip.admission_type,
                COALESCE(u.fname,'') AS attending_fname,
                COALESCE(u.lname,'') AS attending_lname,
                ip.admitting_diagnosis, ip.admitting_icd10,
                DATEDIFF(NOW(), e.start_datetime) AS los_days,
                ip.expected_los_days,
                e.start_datetime AS admit_datetime,
                e.status
         FROM oei_episode e
         JOIN oei_ip_episode ip ON ip.episode_id = e.id
         JOIN patient_data pd ON pd.pid = e.pid
         LEFT JOIN users u ON u.id = ip.attending_user_id
         WHERE e.facility_id = ? AND e.status = 'ACTIVE'
         ORDER BY ip.unit ASC, ip.bed ASC",
        [$facilityId]
    );

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ip_census_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['episode_id','pid','last_name','first_name','dob','sex',
                   'bed','unit','service','admission_type',
                   'attending','admitting_diagnosis','icd10',
                   'los_days','expected_los_days','admit_datetime']);
    while ($r = sqlFetchArray($res)) {
        fputcsv($out, [
            $r['episode_id'], $r['pid'], $r['lname'], $r['fname'],
            $r['DOB'], $r['sex'], $r['bed'], $r['unit'],
            $r['service'], $r['admission_type'],
            trim($r['attending_fname'] . ' ' . $r['attending_lname']),
            $r['admitting_diagnosis'], $r['admitting_icd10'],
            $r['los_days'], $r['expected_los_days'], $r['admit_datetime'],
        ]);
    }
    fclose($out);
    exit;
}

$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
  <link rel="stylesheet" href="<?= institutional_theme_css_href() ?>">
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark text-light' : 'bg-light text-dark'; ?>
<body class="<?= $__bgClass ?>">
<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><?= xlt("Exports") ?></h1>
    <a class="btn btn-sm btn-outline-secondary" href="ed_board.php?facility_id=<?= urlencode((string)$facilityId) ?>"><?= xlt("ED Board") ?></a>
  </div>

  <div class="card shadow-sm">
    <div class="card-header"><?= xlt("CSV Exports") ?></div>
    <div class="card-body">
      <form method="get" class="row g-2">
        <input type="hidden" name="facility_id" value="<?= htmlspecialchars((string)$facilityId) ?>">
        <div class="col-12 col-md-3">
          <label class="form-label"><?= xlt("Start Date") ?></label>
          <input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>">
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label"><?= xlt("End Date") ?></label>
          <input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>">
        </div>
        <div class="col-12 col-md-6 d-flex align-items-end gap-2">
          <button name="action" value="csv_throughput" class="btn btn-outline-primary"><?= xlt("Download Throughput CSV") ?></button>
          <button name="action" value="csv_transfers" class="btn btn-outline-primary"><?= xlt("Download Transfers CSV") ?></button>
          <?php if ($manifest->featureEnabled('al_board')): ?>
          <button name="action" value="csv_al_census" class="btn btn-outline-success"><?= xlt("AL Resident Census") ?></button>
          <?php endif; ?>
          <?php if ($manifest->featureEnabled('ip_board')): ?>
          <button name="action" value="csv_ip_census" class="btn btn-outline-success"><?= xlt("IP Daily Census") ?></button>
          <?php endif; ?>
        </div>
      </form>
      <div class="form-text mt-2"><?= xlt("AL Census and IP Census export current active episodes only. Date range applies to Throughput, Transfers, and Incidents.") ?></div>
      <?php if ($manifest->featureEnabled('al_incident')): ?>
      <div class="mt-3 pt-3 border-top">
        <div class="fw-semibold small mb-2"><?= xlt("Incident Reporting (date range applies)") ?></div>
        <button name="action" value="csv_al_incidents" class="btn btn-outline-warning btn-sm">
          <?= xlt("AL Incident Report CSV") ?>
        </button>
        <div class="form-text"><?= xlt("Includes incident type, severity, mandatory report status — suitable for state regulatory submissions.") ?></div>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</body>
</html>













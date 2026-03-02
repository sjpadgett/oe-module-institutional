<?php

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

$href = institutional_bootstrap5_href($manifest);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Exports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php if ($href): ?><link href="<?= htmlspecialchars($href) ?>" rel="stylesheet"><?php endif; ?>
</head>
<?php $__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light'; ?>
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
        </div>
      </form>
      <div class="form-text mt-2"><?= xlt("This is a starter export set. Next: add filters and include computed intervals.") ?></div>
    </div>
  </div>

</div>
</body>
</html>

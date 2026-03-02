<?php
/**
 * public/al/handoff.php — AL Shift Handoff Report
 *
 * Facility-wide shift summary for outgoing → incoming staff.
 * Used at every shift change (Day 7a–3p / Evening 3p–11p / Night 11p–7a).
 *
 * Contains one row per active resident with:
 *   Room · Care level · Fall risk · Vitals snapshot · ADL score
 *   Pending MAR items · Recent incidents · Discharge plan status
 *   Primary care plan goal · Clinical flags
 *
 * Print-optimised: a dedicated print stylesheet hides controls
 * and renders a clean single-column document.
 *
 * No episode_id required — facility-wide by design.
 */

require_once __DIR__ . '/../_bootstrap.php';

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Controller\AlHandoffController;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Repository\AlHandoffRepository;

if (!$manifest->featureEnabled('al_handoff')) {
    oei_exit_with_alert(xlt('Shift Handoff is not enabled.'), 'info');
}

$facilityId = $_oei_facilityId ?? 1;

// Detect shift from current time
$hour  = (int)date('G');
$shift = $hour >= 7 && $hour < 15
    ? 'day'
    : ($hour >= 15 && $hour < 23 ? 'evening' : 'night');
$shift = $_GET['shift'] ?? $shift;
$shiftLabels = [
    'day'     => xlt('Day Shift — 7:00 AM – 3:00 PM'),
    'evening' => xlt('Evening Shift — 3:00 PM – 11:00 PM'),
    'night'   => xlt('Night Shift — 11:00 PM – 7:00 AM'),
];

$controller = new AlHandoffController(new AlHandoffRepository());
$vm         = $controller->handle($facilityId, $shift);

$rows    = $vm['rows'];
$summary = $vm['summary'];

$__bgClass = ($_oei_theme ?? 'light') === 'dark' ? 'bg-dark' : 'bg-light';

// Flag icon helpers
$flagIcons = [
    'flag_mar_overdue'      => ['🔴', 'Meds overdue'],
    'flag_incident'         => ['🚨', 'Incident this week'],
    'flag_discharge'        => ['🚪', 'Discharge pending'],
    'flag_high_care'        => ['🏥', 'High care (Tier 3)'],
    'flag_fall_risk'        => ['⚠️', 'High fall risk'],
    'flag_fall_reassess_due'=> ['📋', 'Fall reassess due'],
];

$careBadge = ['TIER_1' => 'success', 'TIER_2' => 'warning', 'TIER_3' => 'danger'];
$careLabel = ['TIER_1' => 'L1', 'TIER_2' => 'L2', 'TIER_3' => 'L3'];
$riskBadge = ['LOW' => 'success', 'MODERATE' => 'warning', 'HIGH' => 'danger'];

$dispLabel = [
    'HOME_DISCHARGE'    => 'Home d/c',
    'SNF_TRANSFER'      => 'SNF xfer',
    'HOSPITAL_TRANSFER' => 'Hospital',
    'HOSPITAL_EVAL'     => 'Hosp eval',
    'AMA_DEPARTURE'     => 'AMA',
    'FAMILY_REMOVAL'    => 'Family removal',
    'DECEASED'          => 'Deceased',
];
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_oei_theme ?? 'light' ?>">
<head>
  <meta charset="utf-8">
  <title><?= xlt('AL Shift Handoff') ?> — <?= htmlspecialchars(date('F j, Y')) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    /* ── Screen ─────────────────────────────────────────────── */
    .row-normal  { border-left: 3px solid transparent; }
    .row-caution { border-left: 3px solid #ffc107; background: var(--bs-warning-bg-subtle) !important; }
    .row-alert   { border-left: 3px solid #dc3545; background: var(--bs-danger-bg-subtle)  !important; }
    .flag-chip   { display:inline-flex; align-items:center; gap:.2rem;
                   font-size:.65rem; padding:.1rem .4rem; border-radius:999px;
                   background:var(--bs-danger-bg-subtle); color:var(--bs-danger-text-emphasis);
                   white-space:nowrap; margin:.1rem; }
    .vitals-str  { font-size:.75rem; font-family:monospace; white-space:nowrap; }
    .goal-text   { font-size:.72rem; max-width:240px; line-height:1.3; }
    .shift-btn   { border-radius:999px; }
    .shift-btn.active { background:#4a7c59; color:#fff; border-color:#4a7c59; }

    /* ── Print ──────────────────────────────────────────────── */
    @media print {
      .no-print  { display:none !important; }
      body       { background:#fff !important; color:#000 !important; font-size:9pt; }
      .container-fluid { padding:0; }
      table      { font-size:8pt; }
      th, td     { padding:3px 5px !important; }
      .row-caution { background:#fffde7 !important; }
      .row-alert   { background:#ffebee !important; }
      .flag-chip   { border:1px solid #ccc; background:none; color:#333; font-size:7pt; }
      .card        { border:1px solid #ddd; box-shadow:none; }
      a            { text-decoration:none; color:inherit; }
    }
  </style>
</head>
<body class="<?= $__bgClass ?>">
<div class="container-fluid px-3 pt-2">

  <!-- ── Header ──────────────────────────────────────────────────────────── -->
  <div class="d-flex align-items-start gap-3 mb-3 no-print">
    <div>
      <h5 class="mb-0 fw-bold">🏡 <?= xlt('AL Shift Handoff Report') ?></h5>
      <div class="text-muted small"><?= htmlspecialchars($vm['printed']) ?></div>
    </div>
    <div class="ms-auto d-flex gap-2 align-items-center">
      <!-- Shift selector -->
      <?php foreach ($shiftLabels as $sk => $sl): ?>
      <a href="?facility_id=<?= $facilityId ?>&shift=<?= $sk ?>"
         class="btn btn-sm btn-outline-secondary shift-btn <?= $sk === $shift ? 'active' : '' ?>">
        <?= htmlspecialchars($sk === 'day' ? '☀️ Day' : ($sk === 'evening' ? '🌆 Eve' : '🌙 Night')) ?>
      </a>
      <?php endforeach; ?>
      <button onclick="window.print()" class="btn btn-sm btn-outline-secondary">🖨 <?= xlt('Print') ?></button>
      <a href="board.php?facility_id=<?= $facilityId ?>" class="btn btn-sm btn-outline-secondary">← <?= xlt('Board') ?></a>
    </div>
  </div>

  <!-- Print header (hidden on screen) -->
  <div class="d-none d-print-block mb-2">
    <h4 class="mb-0">Assisted Living — Shift Handoff Report</h4>
    <div><?= htmlspecialchars($shiftLabels[$shift] ?? '') ?> &nbsp;·&nbsp; <?= htmlspecialchars($vm['printed']) ?></div>
    <hr>
  </div>

  <!-- ── Summary strip ────────────────────────────────────────────────────── -->
  <div class="row g-2 mb-3">
    <?php
      $summaryItems = [
          ['label' => 'Residents',       'val' => $summary['total_residents'],    'col' => 'primary'],
          ['label' => 'Fall Risk ≥ Mod', 'val' => $summary['at_risk_count'],      'col' => 'warning'],
          ['label' => 'High Care (T3)',  'val' => $summary['high_care_count'],     'col' => 'danger'],
          ['label' => 'Meds Overdue',    'val' => $summary['total_pending_mar'],   'col' => ($summary['total_pending_mar'] > 0 ? 'danger' : 'success')],
          ['label' => 'D/C Planned',     'val' => $summary['pending_discharges'], 'col' => 'info'],
      ];
    ?>
    <?php foreach ($summaryItems as $si): ?>
    <div class="col">
      <div class="card text-center py-2">
        <div class="fs-4 fw-bold text-<?= $si['col'] ?>"><?= $si['val'] ?></div>
        <div class="text-muted" style="font-size:.72rem"><?= xlt($si['label']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Main table ───────────────────────────────────────────────────────── -->
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-dark">
            <tr>
              <th><?= xlt('Room') ?></th>
              <th><?= xlt('Resident') ?></th>
              <th><?= xlt('Care') ?></th>
              <th><?= xlt('Fall') ?></th>
              <th><?= xlt('Day') ?></th>
              <th><?= xlt('Vitals') ?></th>
              <th><?= xlt('ADL') ?></th>
              <th><?= xlt('MAR') ?></th>
              <th><?= xlt('Inc') ?></th>
              <th><?= xlt('D/C') ?></th>
              <th><?= xlt('Goal / Flags') ?></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
            <tr>
              <td colspan="11" class="text-center text-muted py-4">
                <?= xlt('No active residents.') ?>
              </td>
            </tr>
            <?php endif; ?>
            <?php
              $lastUnit = null;
            ?>
            <?php foreach ($rows as $r): ?>
              <?php
                $unit = $r['unit'] ?? '';
                $sv   = $r['severity'];
                $rowClass = $sv === 2 ? 'row-alert' : ($sv === 1 ? 'row-caution' : 'row-normal');
                $cl   = $r['care_level'] ?? 'TIER_1';
                $fr   = $r['fall_risk_level'] ?? 'LOW';
              ?>
              <?php if ($unit !== $lastUnit): ?>
                <?php $lastUnit = $unit; ?>
                <tr class="table-secondary no-print">
                  <td colspan="11" class="small fw-semibold py-1 px-2">
                    🏢 <?= htmlspecialchars($unit ?: xlt('Unassigned')) ?>
                  </td>
                </tr>
              <?php endif; ?>
              <tr class="<?= $rowClass ?>">
                <!-- Room -->
                <td class="fw-semibold"><?= htmlspecialchars($r['room'] ?? '—') ?></td>

                <!-- Name + age/sex -->
                <td>
                  <a class="text-decoration-none fw-semibold no-print"
                     href="profile.php?episode_id=<?= (int)$r['episode_id'] ?>&pid=<?= (int)$r['pid'] ?>&facility_id=<?= $facilityId ?>">
                    <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>
                  </a>
                  <span class="d-none d-print-inline fw-semibold">
                    <?= htmlspecialchars($r['fname'] . ' ' . $r['lname']) ?>
                  </span>
                  <div class="text-muted" style="font-size:.7rem">
                    <?= (int)$r['age'] ?>y <?= htmlspecialchars($r['sex'] ?? '') ?>
                  </div>
                </td>

                <!-- Care level -->
                <td class="text-center">
                  <span class="badge bg-<?= $careBadge[$cl] ?? 'secondary' ?>">
                    <?= $careLabel[$cl] ?? $cl ?>
                  </span>
                </td>

                <!-- Fall risk -->
                <td class="text-center">
                  <?php if ($r['fall_risk_level'] ?? ''): ?>
                  <span class="badge bg-<?= $riskBadge[$fr] ?? 'secondary' ?>"
                        title="Score <?= (int)($r['fall_risk_score'] ?? 0) ?>">
                    <?= htmlspecialchars(substr($fr, 0, 3)) ?>
                  </span>
                  <?php else: ?>—<?php endif; ?>
                </td>

                <!-- Days resident -->
                <td class="text-center text-muted small"><?= (int)$r['days_resident'] ?>d</td>

                <!-- Vitals -->
                <td>
                  <div class="vitals-str"><?= htmlspecialchars($r['vitals_summary']) ?></div>
                  <?php if ($r['vitals_datetime'] ?? ''): ?>
                  <div style="font-size:.65rem" class="text-muted">
                    <?= htmlspecialchars(date('M j H:i', strtotime($r['vitals_datetime']))) ?>
                  </div>
                  <?php endif; ?>
                </td>

                <!-- ADL -->
                <td class="text-center small">
                  <?php if ($r['last_adl_score'] !== null): ?>
                    <?= (int)$r['last_adl_score'] ?>/28
                    <div style="font-size:.65rem" class="text-muted"><?= htmlspecialchars($r['adl_label']) ?></div>
                  <?php else: ?>—<?php endif; ?>
                </td>

                <!-- MAR pending -->
                <td class="text-center">
                  <?php $mar = (int)($r['pending_mar_count'] ?? 0); ?>
                  <?php if ($mar > 0): ?>
                    <span class="badge bg-danger"><?= $mar ?></span>
                  <?php else: ?>
                    <span class="text-success small">✓</span>
                  <?php endif; ?>
                </td>

                <!-- Incidents this week -->
                <td class="text-center">
                  <?php $inc = (int)($r['recent_incident_count'] ?? 0); ?>
                  <?php if ($inc > 0): ?>
                    <span class="badge bg-warning text-dark"><?= $inc ?></span>
                  <?php else: ?>—<?php endif; ?>
                </td>

                <!-- Discharge plan -->
                <td class="small">
                  <?php if ($r['pending_disposition'] ?? ''): ?>
                    <?php $dispCode = $r['pending_disposition']; ?>
                    <span class="badge bg-<?= in_array($dispCode, ['HOSPITAL_EVAL','HOSPITAL_TRANSFER']) ? 'danger' : 'secondary' ?>">
                      <?= htmlspecialchars($dispLabel[$dispCode] ?? $dispCode) ?>
                    </span>
                    <?php if ($r['pending_destination'] ?? ''): ?>
                    <div style="font-size:.65rem" class="text-muted text-truncate" style="max-width:100px">
                      <?= htmlspecialchars($r['pending_destination']) ?>
                    </div>
                    <?php endif; ?>
                  <?php else: ?>—<?php endif; ?>
                </td>

                <!-- Goal + Flags -->
                <td>
                  <?php if ($r['care_plan_goal'] ?? ''): ?>
                  <div class="goal-text text-muted">
                    <?= htmlspecialchars(substr($r['care_plan_goal'], 0, 100)) ?>
                    <?= strlen($r['care_plan_goal']) > 100 ? '…' : '' ?>
                  </div>
                  <?php endif; ?>
                  <?php foreach ($r['flags'] as $fKey => $_): ?>
                    <?php if (isset($flagIcons[$fKey])): ?>
                    <span class="flag-chip" title="<?= htmlspecialchars($flagIcons[$fKey][1]) ?>">
                      <?= $flagIcons[$fKey][0] ?> <?= htmlspecialchars(xlt($flagIcons[$fKey][1])) ?>
                    </span>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Legend -->
  <div class="d-flex gap-3 flex-wrap mt-2 small text-muted no-print">
    <span>Row colour: <span class="badge" style="background:#ffc107;color:#000">⚠ 1–2 flags</span>
    <span class="badge bg-danger ms-1">🔴 3+ flags</span></span>
    <span>MAR ✓ = all meds current · MAR <span class="badge bg-danger">N</span> = N doses overdue</span>
    <span>Fall: <span class="badge bg-success">LOW</span>
          <span class="badge bg-warning text-dark">MOD</span>
          <span class="badge bg-danger">HIG</span></span>
  </div>

  <!-- Print signature block (hidden on screen) -->
  <div class="d-none d-print-block mt-4">
    <div class="row">
      <div class="col-6">
        Outgoing: _________________________ Time: _______
      </div>
      <div class="col-6">
        Incoming: _________________________ Time: _______
      </div>
    </div>
  </div>

</div><!-- /container -->
</body>
</html>

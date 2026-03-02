<?php
/**
 * public/smoke_test.php — Institutional Module Smoke Test Suite
 *
 * Tests three layers in order:
 *   1. SCHEMA  — every expected table exists, every expected column is present
 *   2. CLASS   — every Repository class loads and exposes its expected methods
 *   3. QUERY   — every critical repository query runs without SQL error
 *   4. DATA    — demo seed row counts are within expected ranges
 *
 * Run from browser: http://localhost/openemr/interface/modules/custom_modules/
 *                   oe-module-institutional/public/smoke_test.php
 * Run from CLI:     php public/smoke_test.php
 *
 * Returns HTTP 200 + "ALL PASS" or lists every failure.
 * Add ?verbose=1 to see all PASS results too.
 *
 * Safe to run on production — read-only queries only.
 *
 * Ground truth: institutional_all_source.txt (project knowledge)
 * Last updated: v0.14.2
 */

require_once __DIR__ . '/_bootstrap.php';

$verbose = isset($_GET['verbose']) || (php_sapi_name() === 'cli' && in_array('--verbose', $argv ?? []));
$facilityId = 1;

// ─── Test runner ─────────────────────────────────────────────────────────────

$results = [];
$failCount = 0;

function smoke_pass(string $group, string $name, string $detail = ''): void {
    global $results, $verbose;
    $results[] = ['pass', $group, $name, $detail];
}

function smoke_fail(string $group, string $name, string $detail): void {
    global $results, $failCount;
    $results[] = ['fail', $group, $name, $detail];
    $failCount++;
}

function smoke_cols(string $table): array {
    if (!function_exists('sqlStatement')) return [];
    $res = sqlStatement("SHOW COLUMNS FROM `{$table}`");
    $cols = [];
    while ($row = sqlFetchArray($res)) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

function smoke_count(string $sql, array $params = []): int {
    if (!function_exists('sqlQuery')) return -1;
    try {
        $row = sqlQuery($sql, $params);
        return (int) array_values($row)[0];
    } catch (\Throwable $e) {
        return -1;
    }
}

// ─── 1. SCHEMA TESTS ─────────────────────────────────────────────────────────
// Every column list derived directly from institutional_all_source.txt schema.

$SCHEMA = [

    'oei_episode' => [
        'id','pid','eid','facility_id','type','start_datetime','end_datetime',
        'disposition','status','chief_complaint','acuity_esi','provider_user_id',
        'triage_completed_datetime','last_status_update','arrival_mode',
        'triage_datetime','triage_note','created_by_user_id','created_datetime',
        'assigned_nurse_user_id','assigned_provider_user_id',
    ],

    'oei_al_episode' => [
        'id','episode_id','pid','facility_id','encounter_id',
        'room','unit','care_level','fall_risk_level','fall_risk_score',
        'admit_reason','last_adl_score','last_adl_datetime','created_datetime',
    ],

    'oei_adl_record' => [
        'id','episode_id','facility_id','noted_by_user_id',
        'noted_datetime','adl_json','adl_score','notes',
    ],

    'oei_triage' => [
        'id','episode_id','pid','eid','facility_id','set_number',
        'bp_systolic','bp_diastolic','hr','rr','temp_f','spo2','gcs',
        'pain_score','weight_kg','arrival_mode','esi_suggested',
        'notes','noted_by_user_id','noted_datetime',
    ],

    'oei_mar_order' => [
        'id','episode_id','pid','facility_id','drug_name','dose','unit',
        'route','frequency','is_prn','status','ordered_datetime',
        'discontinued_datetime','ordered_by_user_id','discontinued_by_user_id',
        'rx_id','instructions','created_datetime','updated_datetime',
    ],

    'oei_mar_administration' => [
        'id','mar_order_id','episode_id','pid','facility_id',
        'scheduled_datetime','administered_datetime','outcome',
        'dose_given','unit_given','route_given','site','lot_number',
        'hold_reason','administered_by_user_id','note',
        'is_high_alert','created_datetime','updated_datetime',
    ],

    'oei_incident' => [
        'id','episode_id','facility_id','reported_by_user_id',
        'incident_type','severity','incident_datetime','location_description',
        'narrative','corrective_action','reported_state',
        'mandatory_report_sent','created_datetime',
    ],

    'oei_fall_risk_assessment' => [
        'id','episode_id','facility_id','assessed_by_user_id',
        'assessed_datetime','mfs_fall_history','mfs_secondary_dx',
        'mfs_ambulatory_aid','mfs_iv_heparin_lock','mfs_gait',
        'mfs_mental_status','total_score','risk_level','notes','created_datetime',
    ],

    'oei_episode_disposition' => [
        'id','episode_id','pid','eid','facility_id','disposition_code',
        'destination','decision_datetime','depart_datetime',
        'admit_flag','notes','updated_by_user_id','updated_datetime',
    ],

    'oei_activity_log' => [
        'id','facility_id','activity_date','activity_type','activity_name',
        'start_time','duration_minutes','location',
        'led_by_user_id','led_by_name','attendance_json','attendance_count',
        'notes','created_datetime','updated_datetime',
    ],

    // form_care_plan columns used by the module
    'form_care_plan_smoke' => [], // checked separately below
];

// Special: form_care_plan (OpenEMR core table — check only what we USE)
$FORM_CARE_PLAN_COLS_USED = [
    'id','pid','encounter','description','care_plan_type','plan_status',
    'proposed_date','date_end',
];

foreach ($SCHEMA as $table => $expectedCols) {
    if ($table === 'form_care_plan_smoke') continue;
    $actual = smoke_cols($table);
    if (empty($actual)) {
        smoke_fail('SCHEMA', $table, "Table does not exist or SHOW COLUMNS failed");
        continue;
    }
    $missing = array_diff($expectedCols, $actual);
    $extra   = [];   // We don't fail on extra columns — migrations add columns over time
    if ($missing) {
        smoke_fail('SCHEMA', $table, "Missing columns: " . implode(', ', $missing));
    } else {
        smoke_pass('SCHEMA', $table, count($actual) . " columns present");
    }
}

// form_care_plan special check
$fcpActual = smoke_cols('form_care_plan');
$fcpMissing = array_diff($FORM_CARE_PLAN_COLS_USED, $fcpActual);
if ($fcpMissing) {
    smoke_fail('SCHEMA', 'form_care_plan (used cols)', "Missing: " . implode(', ', $fcpMissing));
} else {
    smoke_pass('SCHEMA', 'form_care_plan (used cols)', "All required columns present");
}

// ─── 2. CLASS / METHOD TESTS ──────────────────────────────────────────────────
// Verify autoload resolves and each method_exists check passes.

$CLASSES = [
    // ResidentBoard
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Repository\ResidentBoardRepository::class => [
        'fetchActiveResidents', 'fetchUnitSummary',
    ],
    // ResidentIntake
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentIntake\Repository\ResidentIntakeRepository::class => [
        'admitResident', 'hasActiveEpisode',
    ],
    // ResidentProfile
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentProfile\Repository\ResidentProfileRepository::class => [
        'fetchHeader', 'fetchVitalsHistory', 'fetchAdlHistory',
        'fetchCarePlanSummary', 'fetchMarToday', 'fetchRecentIncidents',
        'fetchLatestFallRisk', 'fetchCareTeam',
    ],
    // AlVitals
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Repository\AlVitalsRepository::class => [
        'record', 'listForEpisode', 'getLatest', 'weightTrend',
    ],
    // AlMar
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Repository\AlMarRepository::class => [
        'listActiveOrders', 'listAllOrders', 'listAdminsByWindow', 'administer',
    ],
    // AdlTracking
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Repository\AdlRepository::class => [
        'listByEpisode', 'chart', 'fetchOverdueEpisodes',
    ],
    // CarePlan
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Repository\CarePlanRepository::class => [
        'fetchByEpisode', 'addEntry', 'updateStatus', 'fetchCareTeam',
    ],
    // FallRisk
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Repository\FallRiskRepository::class => [
        'listByEpisode', 'getLatest', 'record', 'daysSinceLastAssessment',
    ],
    // IncidentReport
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Repository\IncidentRepository::class => [
        'listByFacility', 'create', 'markReported', 'fetchOne',
    ],
    // AlDischarge
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Repository\AlDischargeRepository::class => [
        'getPlan', 'savePlan', 'confirmDeparture', 'getResidentHeader', 'getRecentDischarges',
    ],
    // AlActivity
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Repository\AlActivityRepository::class => [
        'getByDate', 'getByDateRange', 'getByEpisode', 'typeSummary',
        'participationRates', 'insert', 'updateAttendance', 'getById',
    ],
    // AlHandoff
    \OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Repository\AlHandoffRepository::class => [
        'fetchHandoff', 'fetchSummary',
    ],
];

foreach ($CLASSES as $className => $methods) {
    $short = (new \ReflectionClass($className))->getShortName();
    if (!class_exists($className)) {
        smoke_fail('CLASS', $short, "Class not found: $className");
        continue;
    }
    $missing = [];
    foreach ($methods as $m) {
        if (!method_exists($className, $m)) {
            $missing[] = $m;
        }
    }
    if ($missing) {
        smoke_fail('CLASS', $short, "Missing methods: " . implode(', ', $missing));
    } else {
        smoke_pass('CLASS', $short, count($methods) . " methods verified");
    }
}

// ─── 3. QUERY TESTS ──────────────────────────────────────────────────────────
// Run each repository's primary query against the live DB.
// These catch column-name bugs that PHP lint cannot catch.

if (function_exists('sqlStatement')) {

    $QUERIES = [

        'ResidentBoard::fetchActiveResidents' => [
            "SELECT e.id AS episode_id, e.pid, pd.fname, pd.lname,
                    COALESCE(ale.room,'') AS room,
                    COALESCE(ale.unit,'') AS unit,
                    COALESCE(ale.care_level,'TIER_1') AS care_level,
                    COALESCE(ale.fall_risk_level,'LOW') AS fall_risk_level,
                    COALESCE(ale.fall_risk_score,0) AS fall_risk_score,
                    DATEDIFF(NOW(), e.start_datetime) AS days_resident
             FROM oei_episode e
             INNER JOIN patient_data pd    ON pd.pid = e.pid
             LEFT  JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE e.facility_id = ? AND e.status = 'ACTIVE' AND e.type = 'AL'
             LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::fetchHandoff (key cols)' => [
            "SELECT e.id, pd.fname, pd.lname,
                    ale.room, ale.unit, ale.care_level,
                    ale.fall_risk_level, ale.fall_risk_score, ale.admit_reason,
                    DATEDIFF(NOW(), e.start_datetime) AS days_resident
             FROM oei_episode e
             INNER JOIN patient_data pd    ON pd.pid = e.pid
             LEFT  JOIN oei_al_episode ale ON ale.episode_id = e.id
             WHERE e.facility_id = ? AND e.type = 'AL' AND e.status = 'ACTIVE'
             LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::vitals subquery' => [
            "SELECT t.bp_systolic, t.bp_diastolic, t.hr, t.rr,
                    t.temp_f, t.spo2, t.weight_kg, t.noted_datetime
             FROM oei_triage t
             WHERE t.episode_id IN (
                 SELECT id FROM oei_episode
                 WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
             )
             ORDER BY t.id DESC LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::adl subquery' => [
            "SELECT ar.adl_score, ar.noted_datetime
             FROM oei_adl_record ar
             WHERE ar.episode_id IN (
                 SELECT id FROM oei_episode
                 WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
             )
             ORDER BY ar.noted_datetime DESC LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::MAR subquery' => [
            "SELECT COUNT(*) AS cnt
             FROM oei_mar_administration ma
             WHERE ma.outcome = 'PENDING'
               AND ma.scheduled_datetime <= NOW()
               AND ma.episode_id IN (
                   SELECT id FROM oei_episode
                   WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
               )",
            [$facilityId],
        ],

        'AlHandoff::incidents subquery' => [
            "SELECT COUNT(*) AS cnt
             FROM oei_incident inc
             WHERE inc.incident_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND inc.episode_id IN (
                   SELECT id FROM oei_episode
                   WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
               )",
            [$facilityId],
        ],

        'AlHandoff::disposition subquery' => [
            "SELECT d.disposition_code, d.destination
             FROM oei_episode_disposition d
             WHERE d.episode_id IN (
                 SELECT id FROM oei_episode
                 WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
             )
             LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::care_plan subquery' => [
            "SELECT SUBSTR(cp.description, 1, 120) AS goal
             FROM form_care_plan cp
             WHERE cp.care_plan_type = 'goal'
               AND cp.plan_status    = 'active'
               AND cp.pid IN (
                   SELECT pid FROM oei_episode
                   WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
               )
             ORDER BY cp.date DESC, cp.id DESC LIMIT 1",
            [$facilityId],
        ],

        'AlHandoff::fall reassessment subquery' => [
            "SELECT DATEDIFF(NOW(), fra.assessed_datetime) AS days_ago
             FROM oei_fall_risk_assessment fra
             WHERE fra.episode_id IN (
                 SELECT id FROM oei_episode
                 WHERE facility_id = ? AND type = 'AL' AND status = 'ACTIVE'
             )
             ORDER BY fra.assessed_datetime DESC LIMIT 1",
            [$facilityId],
        ],

        'AlActivity::getByDate' => [
            "SELECT a.id, a.activity_type, a.activity_name,
                    a.start_time, a.duration_minutes, a.location,
                    a.attendance_json, a.attendance_count, a.notes
             FROM oei_activity_log a
             WHERE a.facility_id   = ?
               AND a.activity_date = CURDATE()
             ORDER BY a.start_time ASC LIMIT 1",
            [$facilityId],
        ],

        'AlActivity::typeSummary' => [
            "SELECT activity_type, COUNT(*) AS cnt
             FROM oei_activity_log
             WHERE facility_id   = ?
               AND activity_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             GROUP BY activity_type ORDER BY cnt DESC",
            [$facilityId],
        ],

        'AlActivity::getByEpisode (JSON_CONTAINS_PATH)' => [
            "SELECT id FROM oei_activity_log
             WHERE facility_id = ?
               AND JSON_CONTAINS_PATH(attendance_json, 'one', '$.14')
             LIMIT 1",
            [$facilityId],
        ],

        'FallRisk::listByEpisode' => [
            "SELECT id, assessed_datetime, total_score, risk_level,
                    mfs_fall_history, mfs_gait, mfs_mental_status
             FROM oei_fall_risk_assessment
             WHERE episode_id = 14
             ORDER BY assessed_datetime DESC LIMIT 1",
            [],
        ],

        'Incident::listByFacility' => [
            "SELECT id, episode_id, incident_type, severity,
                    incident_datetime, narrative, reported_state, mandatory_report_sent
             FROM oei_incident
             WHERE facility_id = ?
             ORDER BY incident_datetime DESC LIMIT 1",
            [$facilityId],
        ],

        'Disposition::getPlan' => [
            "SELECT id, disposition_code, destination,
                    decision_datetime, depart_datetime, notes
             FROM oei_episode_disposition
             WHERE episode_id = 14 LIMIT 1",
            [],
        ],

        'CarePlan::fetchByEpisode' => [
            "SELECT id, care_plan_type, description,
                    plan_status, proposed_date, date_end
             FROM form_care_plan
             WHERE pid = 50
             ORDER BY date DESC LIMIT 1",
            [],
        ],

    ];

    foreach ($QUERIES as $label => $spec) {
        [$sql, $params] = $spec;
        try {
            $res = sqlStatement($sql, $params);
            if ($res === false) {
                smoke_fail('QUERY', $label, "sqlStatement returned false");
            } else {
                smoke_pass('QUERY', $label);
            }
        } catch (\Throwable $e) {
            smoke_fail('QUERY', $label, $e->getMessage());
        }
    }

} else {
    smoke_fail('QUERY', 'ALL', "sqlStatement() not available — OpenEMR not bootstrapped");
}

// ─── 4. DATA INTEGRITY TESTS ──────────────────────────────────────────────────
// Verify demo seed data is present with expected minimum counts.

if (function_exists('sqlQuery')) {
    $DATA = [
        'AL episodes (type=AL, status=ACTIVE)' => [
            "SELECT COUNT(*) FROM oei_episode WHERE facility_id=? AND type='AL' AND status='ACTIVE'",
            [$facilityId], 5, 5,
        ],
        'AL overlays (oei_al_episode)' => [
            "SELECT COUNT(*) FROM oei_al_episode WHERE facility_id=?",
            [$facilityId], 5, 20,
        ],
        'ADL records' => [
            "SELECT COUNT(*) FROM oei_adl_record WHERE facility_id=?",
            [$facilityId], 5, 100,
        ],
        'Vitals (AL periodic)' => [
            "SELECT COUNT(*) FROM oei_triage t
             JOIN oei_episode e ON e.id = t.episode_id
             WHERE e.facility_id=? AND e.type='AL'",
            [$facilityId], 5, 500,
        ],
        'MAR orders (AL)' => [
            "SELECT COUNT(*) FROM oei_mar_order mo
             JOIN oei_episode e ON e.id = mo.episode_id
             WHERE e.facility_id=? AND e.type='AL'",
            [$facilityId], 5, 200,
        ],
        'Fall risk assessments' => [
            "SELECT COUNT(*) FROM oei_fall_risk_assessment fra
             JOIN oei_episode e ON e.id = fra.episode_id
             WHERE e.facility_id=?",
            [$facilityId], 5, 100,
        ],
        'Incidents (AL)' => [
            "SELECT COUNT(*) FROM oei_incident WHERE facility_id=?",
            [$facilityId], 1, 50,
        ],
        'Discharge plans (AL)' => [
            "SELECT COUNT(*) FROM oei_episode_disposition d
             JOIN oei_episode e ON e.id = d.episode_id
             WHERE e.facility_id=? AND e.type='AL'",
            [$facilityId], 2, 20,
        ],
        'Activity sessions' => [
            "SELECT COUNT(*) FROM oei_activity_log WHERE facility_id=?",
            [$facilityId], 1, 1000,
        ],
        'Care plan entries (AL pids 50-54)' => [
            "SELECT COUNT(*) FROM form_care_plan WHERE pid BETWEEN 50 AND 54",
            [], 5, 200,
        ],
    ];

    foreach ($DATA as $label => [$sql, $params, $min, $max]) {
        $cnt = smoke_count($sql, $params);
        if ($cnt < 0) {
            smoke_fail('DATA', $label, "Query failed");
        } elseif ($cnt < $min) {
            smoke_fail('DATA', $label, "Expected >= $min rows, got $cnt — seed data missing?");
        } elseif ($cnt > $max) {
            smoke_fail('DATA', $label, "Expected <= $max rows, got $cnt — unexpected data?");
        } else {
            smoke_pass('DATA', $label, "$cnt rows");
        }
    }
}

// ─── 5. MANIFEST FEATURE FLAGS ────────────────────────────────────────────────

$expectedFeatures = [
    'al_board','al_intake','al_care_plan','al_adl','al_incident',
    'al_profile','al_vitals','al_fall_risk','al_mar',
    'al_discharge','al_activity','al_handoff',
];
foreach ($expectedFeatures as $feat) {
    if ($manifest->featureEnabled($feat)) {
        smoke_pass('MANIFEST', $feat);
    } else {
        smoke_fail('MANIFEST', $feat, "Feature disabled or missing in manifest.json");
    }
}

// ─── RENDER ───────────────────────────────────────────────────────────────────

$totalPass = count(array_filter($results, fn($r) => $r[0] === 'pass'));
$totalFail = $failCount;
$totalRun  = count($results);

$allGood = $totalFail === 0;

if (php_sapi_name() === 'cli') {
    // ── CLI output ──
    echo "\n=== oe-module-institutional Smoke Test ===\n";
    foreach ($results as [$status, $group, $name, $detail]) {
        if ($status === 'fail' || $verbose) {
            $line = sprintf("  [%s] %-12s %s", strtoupper($status), $group, $name);
            if ($detail) $line .= " — $detail";
            echo $line . "\n";
        }
    }
    echo "\n";
    echo ($allGood ? "✓ ALL PASS" : "✗ FAILURES: $totalFail") . " ($totalPass/$totalRun passed)\n\n";
    exit($allGood ? 0 : 1);
}

// ── Browser output ──
$theme = ($_oei_theme ?? 'light');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $theme ?>">
<head>
  <meta charset="utf-8">
  <title>Smoke Tests — oe-module-institutional</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .group-badge { font-size:.7rem; width:80px; display:inline-block; text-align:center; }
    .detail      { font-size:.78rem; color:var(--bs-secondary-color); }
    .result-row.fail { background:var(--bs-danger-bg-subtle); }
    .result-row.pass { display: <?= $verbose ? 'table-row' : 'none' ?>; }
  </style>
</head>
<body>
<div class="container py-4" style="max-width:900px">

  <h4 class="mb-1">🧪 oe-module-institutional — Smoke Tests</h4>
  <div class="text-muted small mb-3">v0.14.x · facility_id=<?= $facilityId ?> · <?= date('Y-m-d H:i:s') ?></div>

  <!-- Summary banner -->
  <div class="alert <?= $allGood ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center gap-3 mb-3">
    <span style="font-size:1.8rem"><?= $allGood ? '✅' : '❌' ?></span>
    <div>
      <strong><?= $allGood ? 'ALL TESTS PASSED' : "$totalFail TEST(S) FAILED" ?></strong>
      <div class="small"><?= $totalPass ?>/<?= $totalRun ?> passed
        <?php if (!$verbose): ?>
          · <a href="?verbose=1">Show all results</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Results table -->
  <?php
    $byGroup = [];
    foreach ($results as $r) {
        $byGroup[$r[1]][] = $r;
    }
  ?>
  <?php foreach ($byGroup as $group => $groupResults): ?>
    <?php
      $groupFails = count(array_filter($groupResults, fn($r) => $r[0] === 'fail'));
      $groupBadge = $groupFails > 0 ? 'danger' : 'success';
    ?>
    <div class="card mb-3">
      <div class="card-header d-flex align-items-center gap-2 py-2">
        <span class="badge bg-<?= $groupBadge ?> group-badge"><?= $group ?></span>
        <span class="fw-semibold"><?= $group ?></span>
        <span class="ms-auto text-muted small">
          <?= count($groupResults) - $groupFails ?>/<?= count($groupResults) ?>
        </span>
      </div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <tbody>
          <?php foreach ($groupResults as [$status, , $name, $detail]): ?>
            <tr class="result-row <?= $status ?>">
              <td style="width:30px" class="text-center ps-2">
                <?= $status === 'pass' ? '<span class="text-success">✓</span>' : '<span class="text-danger fw-bold">✗</span>' ?>
              </td>
              <td><?= htmlspecialchars($name) ?>
                <?php if ($detail): ?>
                  <div class="detail"><?= htmlspecialchars($detail) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="text-muted small">
    Add <code>?verbose=1</code> to show all passing tests.
    All queries are read-only — safe on production.
  </div>

</div>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * oe-module-institutional — Smoke Tests
 *
 * Sections:
 *   1. SCHEMA   — every oei_* table and required columns exist
 *   2. AUTOLOAD — every domain class resolves via PSR-4
 *   3. METHODS  — every expected public method exists
 *   4. DATA     — demo seed row counts within expected ranges
 *   5. MANIFEST — every feature flag enabled in manifest.json
 *   6. PATHS    — critical files and directories on disk
 *
 * Every failure is also written to the PHP error log via error_log().
 *
 * Run from browser: …/oe-module-institutional/public/smoke_test.php
 * Add ?verbose=1 to show passing rows too.
 * Run from CLI: php public/smoke_test.php [--verbose]
 *
 * Read-only queries — safe for production.
 * Ground truth: real source scan + institutional_all_source.txt
 * Last updated: v0.15.0
 */

require_once __DIR__ . '/_bootstrap.php';

$verbose    = isset($_GET['verbose']) || (php_sapi_name() === 'cli' && in_array('--verbose', $argv ?? []));
$facilityId = 1;

// ─── Test runner ─────────────────────────────────────────────────────────────

$results   = [];
$failCount = 0;

function smoke_pass(string $group, string $name, string $detail = ''): void {
    global $results;
    $results[] = ['pass', $group, $name, $detail];
}

function smoke_fail(string $group, string $name, string $detail): void {
    global $results, $failCount;
    $results[]  = ['fail', $group, $name, $detail];
    $failCount++;
    error_log("[OEI SMOKE FAIL] [{$group}] {$name} — {$detail}");
}

function smoke_cols(string $table): array {
    if (!function_exists('sqlStatement')) return [];
    try {
        $res  = sqlStatement("SHOW COLUMNS FROM `{$table}`");
        $cols = [];
        while ($row = sqlFetchArray($res)) {
            $cols[] = $row['Field'];
        }
        return $cols;
    } catch (\Throwable $e) {
        error_log("[OEI SMOKE FAIL] [SCHEMA] SHOW COLUMNS failed for {$table}: " . $e->getMessage());
        return [];
    }
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
// Column names verified against actual CREATE TABLE definitions in database.sql

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

    // ── Columns verified against actual DB (not inferred) ────────────────────

    'oei_ereferral' => [
        'id','episode_id','pid','eid','facility_id',
        'referral_type','status','priority',
        'destination_directory_id','destination_name','destination_fax',
        'destination_phone','destination_address',
        'reason_for_referral','clinical_summary','services_requested',
        'medications_summary','followup_instructions',
        'sent_datetime','sent_by_user_id','send_method',
        'response_datetime','response_by_name','response_notes',
        'created_by_user_id','created_datetime','updated_datetime',
    ],

    'oei_episode_document' => [
        'id','episode_id','pid','facility_id','doc_type','label',
        'original_name','mime_type','file_size','storage_path',
        'uploaded_by_user_id','uploaded_datetime','is_deleted','notes',
    ],

    'oei_task' => [
        'id','episode_id','pid','eid','facility_id',
        'task_type','due_datetime','completed_datetime',
        'assigned_to_user_id','status','payload_json',
        'created_by_user_id','created_datetime',
    ],

    'oei_location' => [
        'id','facility_id','code','name','location_type',
        'status','unit_name','is_active','sort_order','notes',
    ],

    'oei_episode_location' => [
        'id','episode_id','pid','eid','facility_id',
        'location_id','location_code','start_datetime','end_datetime',
        'user_id','note',
    ],

    'oei_protocol' => [
        'id','facility_id','protocol_key','label','version',
        'enabled','definition_json','updated_by_user_id','updated_datetime',
    ],

    'oei_obs_plan' => [
        'id','episode_id','pid','eid','facility_id',
        'protocol_key','status','start_datetime',
        'protocol_json','updated_by_user_id','updated_datetime',
    ],

    'oei_settings' => [
        'id','facility_id','setting_key','setting_value','updated_datetime',
    ],

    'oei_user_context' => [
        'id','user_id','facility_id','context_key','updated_datetime',
    ],

    'oei_transfer' => [
        'id','episode_id','pid','eid','facility_id',
        'transfer_type','reason',
        'receiving_directory_id','receiving_name',
        'requested_datetime','accepted_datetime','transport_datetime',
        'status','checklist_json','notes',
        'updated_by_user_id','updated_datetime',
    ],

    'oei_hl7_outbound_log' => [
        'id','episode_id','pid','facility_id',
        'event_type','transport_type','endpoint',
        'message_body','ack_body','status','error_message','sent_datetime',
    ],

    'oei_diversion' => [
        'id','facility_id','service_line','status','reason',
        'diversion_start','diversion_end',
        'updated_by_user_id','updated_datetime',
    ],

    'oei_bh_safety' => [
        'id','episode_id','pid','eid','facility_id',
        'observation_level','is_involuntary',
        'risk_violence','risk_suicide','elopement_risk',
        'precautions_json','updated_by_user_id','updated_datetime',
    ],

    'oei_bh_boarding' => [
        'id','episode_id','pid','eid','facility_id',
        'legal_status','suicide_risk','violence_risk','placement_status',
        'accepting_facility','accepted_datetime',
        'transport_method','transport_datetime',
        'emtala_complete','checklist_json','notes',
        'updated_by_user_id','updated_datetime',
    ],

    'oei_alert_ack' => [
        'id','alert_key','facility_id','user_id',
        'acked_datetime','expires_datetime',
    ],

    'oei_facility_directory' => [
        'id','facility_id','name','service_type',
        'phone','fax','email','address',
        'hours','notes','is_active','sort_order',
    ],

    'oei_activity_log' => [
        'id','facility_id','activity_date','activity_type','activity_name',
        'start_time','duration_minutes','location',
        'led_by_user_id','led_by_name','attendance_json','attendance_count',
        'notes','created_datetime','updated_datetime',
    ],

    'oei_schema_version' => [
        'version','applied_datetime',
    ],
];

foreach ($SCHEMA as $table => $expectedCols) {
    $actual  = smoke_cols($table);
    if (empty($actual)) {
        smoke_fail('SCHEMA', $table, 'Table missing or SHOW COLUMNS failed');
        continue;
    }
    $missing = array_diff($expectedCols, $actual);
    if ($missing) {
        smoke_fail('SCHEMA', $table, 'Missing columns: ' . implode(', ', $missing));
    } else {
        smoke_pass('SCHEMA', $table, count($actual) . ' columns present');
    }
}

// form_care_plan (OpenEMR core table — only the columns we USE)
$fcpCols     = smoke_cols('form_care_plan');
$fcpRequired = ['id','pid','encounter','description','care_plan_type','plan_status','proposed_date','date_end'];
$fcpMissing  = array_diff($fcpRequired, $fcpCols);
if ($fcpMissing) {
    smoke_fail('SCHEMA', 'form_care_plan (used cols)', 'Missing: ' . implode(', ', $fcpMissing));
} else {
    smoke_pass('SCHEMA', 'form_care_plan (used cols)', 'All required columns present');
}

// ─── 2 & 3. AUTOLOAD + METHOD TESTS ──────────────────────────────────────────
// Method names verified by direct scan of source files in staging tree.
// Reflects v0.15.0 domain namespace layout.

$NS = 'OpenEMR\\Modules\\Institutional\\';

$CLASSES = [

    // ── Core ──────────────────────────────────────────────────────────────────
    $NS.'Core\\Repository\\EpisodeRepository' => [
        'fetchBoard','fetchOne','createArrival','closeWithDisposition','appendStatusHistory',
    ],
    $NS.'Core\\Repository\\UserRepository' => [
        'fetchNurses','fetchProviders','namesByIds','fetchStaff',
    ],
    $NS.'Core\\Service\\AuditService' => [
        'record','forEpisode','firstEventsByEpisode',
    ],
    $NS.'Core\\Migration\\MigrationRunner' => [
        'runPending','appliedVersions','currentVersion',
    ],

    // ── AssistedLiving ────────────────────────────────────────────────────────
    $NS.'AssistedLiving\\Submodule\\ResidentBoard\\Repository\\ResidentBoardRepository' => [
        'fetchActiveResidents','fetchUnitSummary',
    ],
    $NS.'AssistedLiving\\Submodule\\ResidentIntake\\Repository\\ResidentIntakeRepository' => [
        'admitResident','hasActiveEpisode',
    ],
    $NS.'AssistedLiving\\Submodule\\ResidentProfile\\Repository\\ResidentProfileRepository' => [
        'fetchHeader','fetchVitalsHistory','fetchAdlHistory',
        'fetchCarePlanSummary','fetchMarToday','fetchRecentIncidents',
        'fetchLatestFallRisk','fetchCareTeam',
    ],
    $NS.'AssistedLiving\\Submodule\\AlVitals\\Repository\\AlVitalsRepository' => [
        'record','listForEpisode','getLatest','weightTrend',
    ],
    $NS.'AssistedLiving\\Submodule\\AlMar\\Repository\\AlMarRepository' => [
        'listActiveOrders','listAllOrders','listAdminsByWindow',
    ],
    $NS.'AssistedLiving\\Submodule\\FallRisk\\Repository\\FallRiskRepository' => [
        'record','getLatest','listByEpisode',
    ],
    $NS.'AssistedLiving\\Submodule\\CarePlan\\Repository\\CarePlanRepository' => [
        'fetchByEpisode','addEntry','updateStatus','fetchCareTeam',
    ],
    $NS.'AssistedLiving\\Submodule\\IncidentReport\\Repository\\IncidentRepository' => [
        'create','listByFacility','markReported','fetchOne',
    ],
    $NS.'AssistedLiving\\Submodule\\AdlTracking\\Repository\\AdlRepository' => [
        'listByEpisode','chart','fetchOverdueEpisodes',
    ],
    $NS.'AssistedLiving\\Submodule\\AlDischarge\\Repository\\AlDischargeRepository' => [
        'getPlan','savePlan','confirmDeparture','getResidentHeader',
    ],
    $NS.'AssistedLiving\\Submodule\\AlHandoff\\Repository\\AlHandoffRepository' => [
        'fetchHandoff','fetchSummary',
    ],
    $NS.'AssistedLiving\\Submodule\\AlActivity\\Repository\\AlActivityRepository' => [
        'getByDate','getByDateRange','getByEpisode','insert',
    ],

    // ── EmergencyDepartment ───────────────────────────────────────────────────
    $NS.'EmergencyDepartment\\Submodule\\EdBoard\\Controller\\EdBoardController' => [
        'handle',
    ],
    $NS.'EmergencyDepartment\\Submodule\\Diversion\\Repository\\DiversionRepository' => [
        'getCurrent','upsert','listByFacility',
    ],
    $NS.'EmergencyDepartment\\Submodule\\Diversion\\Service\\DiversionService' => [
        'setStatus','liftDiversion','getStatusMap','worstStatus',
    ],
    $NS.'EmergencyDepartment\\Submodule\\Downtime\\Service\\DowntimeSnapshotService' => [
        'build',
    ],
    $NS.'EmergencyDepartment\\Submodule\\Downtime\\Service\\DowntimeSyncService' => [
        'processRow','processFacilityQueue',
    ],

    // ── ObservationStay ───────────────────────────────────────────────────────
    $NS.'ObservationStay\\Submodule\\ObsCore\\Service\\ObsService' => [
        'startObs',
    ],
    $NS.'ObservationStay\\Submodule\\ObsProtocols\\Repository\\ProtocolRepository' => [
        'listEnabled','ensureDefaultProtocols','upsert','get',
    ],
    $NS.'ObservationStay\\Submodule\\ObsProtocols\\Repository\\ObsPlanRepository' => [
        'getByEpisode','upsert','listActive',
    ],
    $NS.'ObservationStay\\Submodule\\ObsProtocols\\Service\\ObsProtocolEngine' => [
        'apply','generateOnlyRunway',
    ],
    $NS.'ObservationStay\\Submodule\\ObsBilling\\Service\\ObsBillingService' => [
        'fetchObsBillingStatus','computeBillingAlerts',
    ],
    $NS.'ObservationStay\\Submodule\\CmsQuality\\Repository\\CmsMeasureRepository' => [
        'computeAll',
    ],

    // ── Operations ────────────────────────────────────────────────────────────
    $NS.'Operations\\Submodule\\Settings\\Repository\\SettingsRepository' => [
        'get','set','setMany','all',
    ],
    $NS.'Operations\\Submodule\\Scorecard\\Repository\\ScorecardRepository' => [
        'byProvider','dailyVolume','providerNames',
    ],
    $NS.'Operations\\Submodule\\Scorecard\\Service\\ScorecardService' => [
        'build',
    ],
    $NS.'Operations\\Submodule\\FacilityDirectory\\Repository\\FacilityDirectoryRepository' => [
        'listActive','get','upsert',
    ],
    $NS.'Operations\\Submodule\\MultiFacility\\Repository\\MultiFacilityRepository' => [
        'fetchAll',
    ],
    $NS.'Operations\\Submodule\\Hl7Adt\\Repository\\Hl7OutboundLogRepository' => [
        'record','listRecent','listForEpisode','summary24h',
    ],
    $NS.'Operations\\Submodule\\Hl7Adt\\Service\\AdtNotificationService' => [
        'notifyArrival','notifyAdmit','notifyTransfer','notifyUpdate','notifyDischarge',
    ],

    // ── Shared ────────────────────────────────────────────────────────────────
    $NS.'Shared\\Submodule\\AdtLite\\Repository\\LocationRepository' => [
        'listAll','listActive','create','update',
    ],
    $NS.'Shared\\Submodule\\AdtLite\\Repository\\LocationHistoryRepository' => [
        'closeOpenHistory','openHistory',
    ],
    $NS.'Shared\\Submodule\\AdtLite\\Service\\AdtService' => [
        'assignLocation',
    ],
    $NS.'Shared\\Submodule\\Alerts\\Repository\\AlertAckRepository' => [
        'ack','activeSnoozed','pruneExpired',
    ],
    $NS.'Shared\\Submodule\\Alerts\\Service\\AlertService' => [
        'computeAll','computeForBoard','computeMarAlerts','computeSepsisAlerts',
    ],
    $NS.'Shared\\Submodule\\Assignment\\Repository\\AssignmentRepository' => [
        'assign','getForEpisode','listWithAssignments',
    ],
    $NS.'Shared\\Submodule\\BedMgmt\\Repository\\LocationRepository' => [
        'listActive','upsert',
    ],
    $NS.'Shared\\Submodule\\BedMgmt\\Repository\\EpisodeLocationRepository' => [
        'getCurrentForEpisode','moveEpisode','listCurrentByFacility',
    ],
    $NS.'Shared\\Submodule\\Disposition\\Repository\\DispositionRepository' => [
        'getByEpisode','upsert','fetchForEpisodes',
    ],
    $NS.'Shared\\Submodule\\Disposition\\Repository\\EpisodeEventRepository' => [
        'addEvent','firstEventMap','forEpisode',
    ],
    $NS.'Shared\\Submodule\\EpisodeDocuments\\Repository\\EpisodeDocumentRepository' => [
        'create','listForEpisode','findById','softDelete','countForEpisode','typeSummary',
    ],
    $NS.'Shared\\Submodule\\EpisodeDocuments\\Service\\EpisodeDocumentService' => [
        'upload','serve','deleteFile',
    ],
    $NS.'Shared\\Submodule\\EReferral\\Repository\\EReferralRepository' => [
        'getByEpisode','upsert','markSent','recordResponse','listByFacility',
    ],
    $NS.'Shared\\Submodule\\EReferral\\Service\\EReferralService' => [
        'draftFromDisposition','applyEdit',
    ],
    $NS.'Shared\\Submodule\\Handoff\\Repository\\HandoffRepository' => [
        'fetchHandoff',
    ],
    $NS.'Shared\\Submodule\\Handoff\\Service\\HandoffService' => [
        'formatVitals','qsofa','computeSummary',
    ],
    $NS.'Shared\\Submodule\\Intake\\Repository\\EpisodeIntakeRepository' => [
        'create',
    ],
    $NS.'Shared\\Submodule\\Intake\\Service\\IntakeService' => [
        'createEpisode',
    ],
    $NS.'Shared\\Submodule\\Mar\\Repository\\MarOrderRepository' => [
        'listByEpisode','listActiveByEpisode','getById','create','discontinue',
    ],
    $NS.'Shared\\Submodule\\Mar\\Repository\\MarAdministrationRepository' => [
        'listByEpisode','listPendingByEpisode','listOverdueByFacility',
        'createScheduled','createPrn','record','amend','voidPendingForOrder',
    ],
    $NS.'Shared\\Submodule\\Mar\\Service\\MarService' => [
        'buildMarGrid','placeOrder','discontinueOrder','recordAdministration','givePrn',
    ],
    $NS.'Shared\\Submodule\\Tasks\\Repository\\TaskRepository' => [
        'listByEpisode','listOpenByFacility','create','complete',
    ],
    $NS.'Shared\\Submodule\\Tasks\\Service\\TaskService' => [
        'scheduleFromDefinition','scheduleDefaultObs',
    ],
    $NS.'Shared\\Submodule\\Timeline\\Repository\\TimelineRepository' => [
        'forEpisode',
    ],
    $NS.'Shared\\Submodule\\Throughput\\Service\\ThroughputService' => [
        'compute',
    ],
    $NS.'Shared\\Submodule\\TransferTracking\\Repository\\TransferRepository' => [
        'getByEpisode','upsert','updateStatus','listRecentByFacility',
    ],
    $NS.'Shared\\Submodule\\Trends\\Repository\\TrendRepository' => [
        'computeTrends','computeHeatmap',
    ],
    $NS.'Shared\\Submodule\\Trends\\Service\\TrendsService' => [
        'buildViewModel',
    ],
    $NS.'Shared\\Submodule\\Triage\\Repository\\TriageRepository' => [
        'record','getLatestForEpisode','listForEpisode','latestByFacility',
    ],
    $NS.'Shared\\Submodule\\Triage\\Service\\TriageService' => [
        'recordVitals',
    ],
    $NS.'Shared\\Submodule\\Triage\\Service\\VitalsSchedulerService' => [
        'scheduleForEd','scheduleForObs',
    ],

    // ── BehavioralHealth ──────────────────────────────────────────────────────
    $NS.'BehavioralHealth\\Submodule\\BhSafety\\Repository\\BhSafetyRepository' => [
        'getByEpisode','upsert','listRecentByFacility',
    ],
    $NS.'BehavioralHealth\\Submodule\\BhSafety\\Service\\BhSafetyService' => [
        'setBhSafety',
    ],
    $NS.'BehavioralHealth\\Submodule\\BhBoarding\\Repository\\BhBoardingRepository' => [
        'getByEpisode','upsert',
    ],
];

foreach ($CLASSES as $fqcn => $methods) {
    $short  = substr(strrchr($fqcn, '\\'), 1);
    $parts  = explode('\\', str_replace($NS, '', $fqcn));
    $domain = $parts[0];

    if (!class_exists($fqcn)) {
        smoke_fail('AUTOLOAD', "{$domain}\\{$short}", "Class not found: {$fqcn}");
        continue;
    }
    smoke_pass('AUTOLOAD', "{$domain}\\{$short}");

    foreach ($methods as $method) {
        if (!method_exists($fqcn, $method)) {
            smoke_fail('METHODS', "{$short}::{$method}()", "Method missing on {$fqcn}");
        } else {
            smoke_pass('METHODS', "{$short}::{$method}()");
        }
    }
}

// ─── 4. DATA TESTS ────────────────────────────────────────────────────────────

$DATA_CHECKS = [
    ['oei_episode rows',            "SELECT COUNT(*) FROM oei_episode",             0, 300],
    ['oei_al_episode rows',         "SELECT COUNT(*) FROM oei_al_episode",           0, 100],
    ['oei_location rows',           "SELECT COUNT(*) FROM oei_location",             0, 200],
    ['oei_triage rows',             "SELECT COUNT(*) FROM oei_triage",               0, 500],
    ['oei_mar_order rows',          "SELECT COUNT(*) FROM oei_mar_order",            0, 500],
    ['oei_mar_administration rows', "SELECT COUNT(*) FROM oei_mar_administration",   0, 2000],
    ['oei_episode_document rows',   "SELECT COUNT(*) FROM oei_episode_document",     0, 500],
    ['oei_task rows',               "SELECT COUNT(*) FROM oei_task",                 0, 1000],
    ['oei_ereferral rows',          "SELECT COUNT(*) FROM oei_ereferral",            0, 200],
    ['oei_protocol rows',           "SELECT COUNT(*) FROM oei_protocol",             1, 50],
    ['oei_settings rows',           "SELECT COUNT(*) FROM oei_settings",             0, 200],
    ['oei_facility_directory rows', "SELECT COUNT(*) FROM oei_facility_directory",   0, 200],
];

foreach ($DATA_CHECKS as [$label, $sql, $min, $max]) {
    $cnt = smoke_count($sql);
    if ($cnt < 0) {
        smoke_fail('DATA', $label, 'Query failed (DB not connected?)');
    } elseif ($cnt < $min) {
        smoke_fail('DATA', $label, "Expected >= {$min} rows, got {$cnt} — seed may not have run");
    } elseif ($cnt > $max) {
        smoke_fail('DATA', $label, "Expected <= {$max} rows, got {$cnt} — unexpected data?");
    } else {
        smoke_pass('DATA', $label, "{$cnt} rows");
    }
}

// oei_schema_version — must have at least the initial version applied
$schemaVer = smoke_count("SELECT COUNT(*) FROM oei_schema_version");
if ($schemaVer < 1) {
    smoke_fail('DATA', 'oei_schema_version populated',
        "No versions recorded — run table.sql + migrations");
} else {
    // Show the most recently applied version
    if (function_exists('sqlQuery')) {
        try {
            $row = sqlQuery("SELECT version, applied_datetime FROM oei_schema_version ORDER BY applied_datetime DESC LIMIT 1");
            smoke_pass('DATA', 'oei_schema_version populated',
                "Latest: v{$row['version']} applied {$row['applied_datetime']} ({$schemaVer} total)");
        } catch (\Throwable $e) {
            smoke_pass('DATA', 'oei_schema_version populated', "{$schemaVer} versions recorded");
        }
    }
}

// ─── 5. MANIFEST FEATURE FLAGS ────────────────────────────────────────────────

$expectedFeatures = [
    // Core workflows
    'edt_board','intake','triage','tasks','mar','disposition','ereferral',
    'episode_documents','assignment','handoff','throughput','timeline',
    'transfer_tracking','command_center','alerts','scorecard','trends',
    // ED
    'diversion','downtime',
    // BH
    'bh_safety','bh_boarding',
    // OBS
    'obs_protocols','obs_episodes','obs_billing','obs_stay',
    // Reporting
    'cms_quality',
    // Admin
    'context_manager','bed_mgmt','adt_lite','facility_directory',
    'hl7_adt','admin_exports','settings','multi_facility','mts_triage','smoke_test',
    // Assisted Living
    'al_board','al_intake','al_care_plan','al_adl','al_incident',
    'al_profile','al_vitals','al_fall_risk','al_mar',
    'al_discharge','al_activity','al_handoff',
];

foreach ($expectedFeatures as $feat) {
    if ($manifest->featureEnabled($feat)) {
        smoke_pass('MANIFEST', $feat);
    } else {
        smoke_fail('MANIFEST', $feat, 'Feature disabled or missing in manifest.json');
    }
}

// ─── 6. DISK PATH TESTS ───────────────────────────────────────────────────────

$moduleRoot = dirname(__DIR__);

$PATHS = [
    'manifest.json'                         => $moduleRoot . '/manifest.json',
    'composer.json'                         => $moduleRoot . '/composer.json',
    'openemr.bootstrap.php'                 => $moduleRoot . '/openemr.bootstrap.php',
    'src/Bootstrap.php'                     => $moduleRoot . '/src/Bootstrap.php',
    'src/Core/Repository/EpisodeRepository' => $moduleRoot . '/src/Core/Repository/EpisodeRepository.php',
    'src/Shared/ domain'                    => $moduleRoot . '/src/Shared',
    'src/EmergencyDepartment/ domain'       => $moduleRoot . '/src/EmergencyDepartment',
    'src/ObservationStay/ domain'           => $moduleRoot . '/src/ObservationStay',
    'src/Operations/ domain'                => $moduleRoot . '/src/Operations',
    'src/BehavioralHealth/ domain'          => $moduleRoot . '/src/BehavioralHealth',
    'src/AssistedLiving/ domain'            => $moduleRoot . '/src/AssistedLiving',
    'public/_bootstrap.php'                 => $moduleRoot . '/public/_bootstrap.php',
    'public/ed_board.php'                   => $moduleRoot . '/public/ed_board.php',
    'public/mar.php'                        => $moduleRoot . '/public/mar.php',
    'public/episode_documents.php'          => $moduleRoot . '/public/episode_documents.php',
    'public/ereferral.php'                  => $moduleRoot . '/public/ereferral.php',
    'table.sql'                             => $moduleRoot . '/table.sql',
    'sql/migrations/ dir'                   => $moduleRoot . '/sql/migrations',
    'sql/migrations/0001_initial_schema'    => $moduleRoot . '/sql/migrations/0001_initial_schema.sql',
    'sql/migrations/0002_assisted_living'   => $moduleRoot . '/sql/migrations/0002_assisted_living.sql',
    'sql/migrations/0003_al_fall_risk'      => $moduleRoot . '/sql/migrations/0003_al_fall_risk.sql',
    'sql/migrations/0004_al_activity_log'   => $moduleRoot . '/sql/migrations/0004_al_activity_log.sql',
    'openemr-module.json'                   => $moduleRoot . '/openemr-module.json',
    'src/Core/Migration/MigrationRunner'    => $moduleRoot . '/src/Core/Migration/MigrationRunner.php',
];

foreach ($PATHS as $label => $path) {
    if (file_exists($path)) {
        smoke_pass('PATHS', $label);
    } else {
        smoke_fail('PATHS', $label, "Not found: {$path}");
    }
}

// src/Submodule/ — backward-compat stubs; warn but don't fail
$oldStubDir = $moduleRoot . '/src/Submodule';
if (is_dir($oldStubDir)) {
    smoke_pass('PATHS', 'src/Submodule/ (stub)',
        'Stub dir present — expected during migration; run composer dump-autoload if autoload issues occur');
} else {
    smoke_pass('PATHS', 'src/Submodule/ removed', 'Domain reorganisation fully clean');
}

// ─── RENDER ───────────────────────────────────────────────────────────────────

$totalPass = count(array_filter($results, fn($r) => $r[0] === 'pass'));
$totalFail = $failCount;
$totalRun  = count($results);
$allGood   = ($totalFail === 0);

// Summary always written to PHP error log
error_log(sprintf(
    '[OEI SMOKE] v0.15.0 facility=%d — %s (%d/%d passed, %d failed)',
    $facilityId,
    $allGood ? 'ALL PASS' : 'FAILURES',
    $totalPass, $totalRun, $totalFail
));

if (php_sapi_name() === 'cli') {
    echo "\n=== oe-module-institutional Smoke Test v0.15.0 ===\n";
    foreach ($results as [$status, $group, $name, $detail]) {
        if ($status === 'fail' || $verbose) {
            $line = sprintf('  [%s] %-12s %s', strtoupper($status), $group, $name);
            if ($detail) $line .= " — {$detail}";
            echo $line . "\n";
        }
    }
    echo "\n";
    echo ($allGood ? '✓ ALL PASS' : "✗ FAILURES: {$totalFail}") . " ({$totalPass}/{$totalRun} passed)\n\n";
    exit($allGood ? 0 : 1);
}

// ── Browser output ────────────────────────────────────────────────────────────
$theme = ($_oei_theme ?? 'light');

$groups = [];
foreach ($results as $r) {
    $groups[$r[1]][] = $r;
}

$groupStats = [];
foreach ($groups as $g => $rows) {
    $groupStats[$g] = [
        'pass' => count(array_filter($rows, fn($r) => $r[0] === 'pass')),
        'fail' => count(array_filter($rows, fn($r) => $r[0] === 'fail')),
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $theme ?>">
<head>
  <meta charset="utf-8">
  <title>Smoke Tests — oe-module-institutional v0.15.0</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="<?= institutional_bootstrap5_href($manifest) ?>">
  <style>
    .group-badge { font-size:.7rem; width:86px; display:inline-block; text-align:center; }
    .detail      { font-size:.78rem; color:var(--bs-secondary-color); }
    .result-row.fail { background:var(--bs-danger-bg-subtle); }
    .result-row.pass { display: <?= $verbose ? 'table-row' : 'none' ?>; }
    .section-header { cursor:pointer; user-select:none; }
    .section-header:hover { background:var(--bs-secondary-bg); }
  </style>
</head>
<body>
<div class="container py-4" style="max-width:960px">

  <h4 class="mb-1">🧪 oe-module-institutional — Smoke Tests</h4>
  <div class="text-muted small mb-3">
    v0.15.0 &middot; facility_id=<?= $facilityId ?> &middot; <?= date('Y-m-d H:i:s') ?>
    &middot; Failures logged to PHP error log
    <?php if (!$verbose): ?>&middot; <a href="?verbose=1">Show all</a><?php endif; ?>
  </div>

  <div class="alert <?= $allGood ? 'alert-success' : 'alert-danger' ?> d-flex align-items-center gap-3 mb-4">
    <span style="font-size:2rem"><?= $allGood ? '✅' : '❌' ?></span>
    <div>
      <strong><?= $allGood ? 'All tests passed' : "{$totalFail} test(s) failed" ?></strong>
      <div class="small"><?= $totalPass ?>/<?= $totalRun ?> passed across 6 sections</div>
    </div>
  </div>

  <!-- Per-section summary cards -->
  <div class="row g-2 mb-4">
    <?php foreach ($groupStats as $g => $s): ?>
      <div class="col-6 col-md-4 col-lg-2">
        <div class="card text-center border-<?= $s['fail'] > 0 ? 'danger' : 'success' ?>">
          <div class="card-body py-2 px-1">
            <div class="fw-semibold small"><?= htmlspecialchars($g) ?></div>
            <div class="fs-5 <?= $s['fail'] > 0 ? 'text-danger' : 'text-success' ?>">
              <?= $s['fail'] > 0 ? $s['fail'] . ' ✗' : '✓' ?>
            </div>
            <div class="text-muted" style="font-size:.7rem"><?= $s['pass'] ?>/<?= $s['pass'] + $s['fail'] ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Detail sections -->
  <?php foreach ($groups as $groupName => $rows):
    $gStats      = $groupStats[$groupName];
    $hasFailures = $gStats['fail'] > 0;
    $anchorId    = 'grp-' . preg_replace('/\W+/', '_', $groupName);
  ?>
  <div class="card shadow-sm mb-3">
    <div class="card-header section-header d-flex justify-content-between align-items-center"
         data-bs-toggle="collapse" data-bs-target="#<?= $anchorId ?>">
      <span class="fw-semibold">
        <?= htmlspecialchars($groupName) ?>
        <?php if ($hasFailures): ?>
          <span class="badge text-bg-danger ms-2"><?= $gStats['fail'] ?> failed</span>
        <?php else: ?>
          <span class="badge text-bg-success ms-2">all pass</span>
        <?php endif; ?>
      </span>
      <small class="text-muted"><?= $gStats['pass'] ?>/<?= $gStats['pass'] + $gStats['fail'] ?> ▾</small>
    </div>
    <div class="collapse <?= ($hasFailures || $verbose) ? 'show' : '' ?>" id="<?= $anchorId ?>">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <tbody>
          <?php foreach ($rows as [$status, $group, $name, $detail]):
            if ($status === 'pass' && !$verbose) continue; ?>
            <tr class="result-row <?= $status ?>">
              <td style="width:90px">
                <span class="badge group-badge text-bg-<?= $status === 'pass' ? 'success' : 'danger' ?>">
                  <?= strtoupper($status) ?>
                </span>
              </td>
              <td class="small">
                <?= htmlspecialchars($name) ?>
                <?php if ($detail): ?>
                  <div class="detail"><?= htmlspecialchars($detail) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$verbose && $gStats['fail'] === 0): ?>
            <tr><td colspan="2" class="text-muted small py-2 ps-3">
              All <?= $gStats['pass'] ?> checks passed &mdash;
              <a href="?verbose=1#<?= $anchorId ?>">show</a>
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

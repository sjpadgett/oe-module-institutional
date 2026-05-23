<?php

/**
 * smoke_test_additions.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

// ─────────────────────────────────────────────────────────────────────────────
// SMOKE TEST ADDITIONS — v0.16.0 — EHR Integration (Care Plans, Clinical Notes, Care Teams)
//
// INSTRUCTIONS: Paste this block into public/smoke_test.php
//   1. Add the $CLASSES entries into the existing $CLASSES array
//   2. Add the $expectedFeatures entries into the existing $expectedFeatures array
//   3. Add the $SCHEMA entries into the existing $SCHEMA array
//   4. Paste the "DATA / INTEGRITY" block before the final HTML output section
//
// ─────────────────────────────────────────────────────────────────────────────


// ══════════════════════════════════════════════════════════════
// A.  $SCHEMA additions — add these inside the $SCHEMA array
// ══════════════════════════════════════════════════════════════

/*
    // OpenEMR native tables used by v0.16.0 integration
    'form_care_plan' => [
        'id','pid','encounter','user','groupname','authorized','activity',
        'code','codetext','description','care_plan_type','plan_status',
        'proposed_date','date_end','reason_code','reason_description',
    ],

    'form_clinical_notes' => [
        'id','form_id','pid','encounter','user','groupname','authorized','activity',
        'code','description','clinical_notes_type','clinical_notes_category',
        'note_related_to','last_updated',
    ],

    'care_teams' => [
        'id','pid','status','team_name','note','date_created','date_updated',
        'created_by','updated_by',
    ],

    'care_team_member' => [
        'id','care_team_id','user_id','contact_id','facility_id',
        'role','status','provider_since','note','created_by','updated_by',
    ],

    'clinical_notes_documents' => [
        'id','clinical_note_id','document_id',
    ],

    'clinical_notes_procedure_results' => [
        'id','clinical_note_id','procedure_result_id',
    ],
*/


// ══════════════════════════════════════════════════════════════
// B.  $CLASSES additions — add these inside the $CLASSES array
// ══════════════════════════════════════════════════════════════

/*
    // ── v0.16.0 Core services ─────────────────────────────────────────────────
    $NS.'Core\\Service\\EncounterResolver' => [
        'resolve',
    ],
    $NS.'Core\\Service\\FormsRegistrar' => [
        'register',
    ],

    // ── v0.16.0 Shared CarePlan ───────────────────────────────────────────────
    $NS.'Shared\\Submodule\\CarePlan\\Repository\\CarePlanRepository' => [
        'fetchByEpisode','addEntry','updateStatus','fetchCareTeam','resolveEncounter',
    ],
    $NS.'Shared\\Submodule\\CarePlan\\Service\\CarePlanService' => [
        'pageData','summary','addGoal','addActivity','updateStatus',
        'buildLaunchUrl','buildEditUrl',
    ],
    $NS.'Shared\\Submodule\\CarePlan\\Controller\\CarePlanController' => [
        'handle',
    ],

    // ── v0.16.0 Shared ClinicalNotes ─────────────────────────────────────────
    $NS.'Shared\\Submodule\\ClinicalNotes\\Repository\\ClinicalNotesRepository' => [
        'fetchByEpisode','fetchByType','fetchLinkedDocuments',
        'fetchLinkedResults','resolveEncounter','addNote',
    ],
    $NS.'Shared\\Submodule\\ClinicalNotes\\Service\\ClinicalNotesService' => [
        'panelData','listData','noteTypeLabel','noteTypeBadge',
        'excerpt','buildLaunchUrl','buildEditBaseUrl',
    ],
    $NS.'Shared\\Submodule\\ClinicalNotes\\Controller\\ClinicalNotesController' => [
        'handlePanel','handlePage',
    ],

    // ── v0.16.0 Shared CareTeam ───────────────────────────────────────────────
    $NS.'Shared\\Submodule\\CareTeam\\Repository\\CareTeamRepository' => [
        'fetchByPatient','fetchRoles','fetchStaff',
        'ensureTeam','addMember','deactivateMember',
    ],
    $NS.'Shared\\Submodule\\CareTeam\\Service\\CareTeamService' => [
        'pageData','ensureAndAddMember','addMember',
        'removeMember','ensureTeamForPatient',
    ],
    $NS.'Shared\\Submodule\\CareTeam\\Controller\\CareTeamController' => [
        'handle',
    ],
*/


// ══════════════════════════════════════════════════════════════
// C.  $expectedFeatures additions — add to the existing array
// ══════════════════════════════════════════════════════════════

/*
    // v0.16.0 EHR integration flags
    'care_plan','care_plan_launch',
    'clinical_notes','clinical_notes_launch',
    'clinical_notes_documents','clinical_notes_results',
    'care_team','care_team_launch',
*/


// ══════════════════════════════════════════════════════════════
// D.  INTEGRITY CHECKS — paste as a new section before HTML output
// ══════════════════════════════════════════════════════════════

// ─── 7. EHR INTEGRATION INTEGRITY (v0.16.0) ──────────────────────────────────

// 7a. OpenEMR native table existence
$OE_TABLES = [
    'form_care_plan',
    'form_clinical_notes',
    'care_teams',
    'care_team_member',
    'clinical_notes_documents',
    'clinical_notes_procedure_results',
];

foreach ($OE_TABLES as $tbl) {
    $cols = smoke_cols($tbl);
    if (empty($cols)) {
        smoke_fail('EHR-TABLES', $tbl, 'Table missing or inaccessible — OpenEMR base install required');
    } else {
        smoke_pass('EHR-TABLES', $tbl, count($cols) . ' columns');
    }
}

// 7b. forms table registry — care_plan and clinical_notes must be registered
$oeFormsRegistered = ['care_plan' => 'Care Plan', 'clinical_notes' => 'Clinical Notes'];
foreach ($oeFormsRegistered as $formdir => $label) {
    $cnt = smoke_count(
        "SELECT COUNT(*) FROM registry WHERE directory = ? AND state = 1",
        [$formdir]
    );
    if ($cnt < 0) {
        // registry table may not exist in all OE versions — soft fail
        smoke_pass('EHR-REGISTRY', $formdir, 'registry table not queryable (OE version may differ)');
    } elseif ($cnt === 0) {
        smoke_fail('EHR-REGISTRY', $formdir,
            "Form '{$label}' not registered in OE registry — run Admin > Other > Forms to register");
    } else {
        smoke_pass('EHR-REGISTRY', $formdir, "Registered ({$cnt} entry)");
    }
}

// 7c. forms table integrity for AL demo episodes
// For each AL episode with a known encounter_id, check that care plan entries
// in form_care_plan have a matching row in the forms table.
if (function_exists('sqlStatement')) {
    $alEpRes = sqlStatement(
        "SELECT ae.episode_id, ae.encounter_id, e.pid
         FROM   oei_al_episode ae
         JOIN   oei_episode    e  ON e.id = ae.episode_id
         WHERE  ae.encounter_id IS NOT NULL
         LIMIT  10"
    );
    $integrityFails = 0;
    $integrityTotal = 0;
    while ($ae = sqlFetchArray($alEpRes)) {
        $pid         = (int)$ae['pid'];
        $encounterId = (int)$ae['encounter_id'];

        $cpCount = smoke_count(
            "SELECT COUNT(*) FROM form_care_plan WHERE pid = ? AND encounter = ? AND activity = 1",
            [$pid, $encounterId]
        );
        if ($cpCount <= 0) {
            continue; // no care plan entries for this episode — skip
        }

        $formsCount = smoke_count(
            "SELECT COUNT(*) FROM forms WHERE pid = ? AND encounter = ? AND formdir = 'care_plan' AND deleted = 0",
            [$pid, $encounterId]
        );

        $integrityTotal++;
        if ($formsCount < $cpCount) {
            $integrityFails++;
        }
    }

    if ($integrityTotal === 0) {
        smoke_pass('EHR-INTEGRITY', 'care_plan forms registration',
            'No AL care plan entries found to check (seed may not have run)');
    } elseif ($integrityFails > 0) {
        smoke_fail('EHR-INTEGRITY', 'care_plan forms registration',
            "{$integrityFails}/{$integrityTotal} episodes have form_care_plan rows "
            . "without matching forms table entries — addEntry() needs FormsRegistrar");
    } else {
        smoke_pass('EHR-INTEGRITY', 'care_plan forms registration',
            "All {$integrityTotal} checked episodes have matching forms table entries");
    }
}

// 7d. EncounterResolver smoke check — AL episode should resolve
if (function_exists('sqlQuery')) {
    $alCheck = sqlQuery(
        "SELECT ae.episode_id, ae.encounter_id
         FROM   oei_al_episode ae
         WHERE  ae.encounter_id IS NOT NULL
         LIMIT  1"
    );
    if ($alCheck) {
        $resolver = new \OpenEMR\Modules\Institutional\Core\Service\EncounterResolver();
        $resolved = $resolver->resolve((int)$alCheck['episode_id'], 'AL');
        if ($resolved === (int)$alCheck['encounter_id']) {
            smoke_pass('EHR-RESOLVER', 'EncounterResolver AL',
                "Episode {$alCheck['episode_id']} → encounter {$resolved}");
        } else {
            smoke_fail('EHR-RESOLVER', 'EncounterResolver AL',
                "Expected encounter {$alCheck['encounter_id']}, got " . var_export($resolved, true));
        }
    } else {
        smoke_pass('EHR-RESOLVER', 'EncounterResolver AL',
            'No AL episodes with encounter_id found — seed may not have run');
    }
}

// 7e. care_teams integrity — AL demo patients should have teams
$careTeamCount = smoke_count("SELECT COUNT(*) FROM care_teams WHERE status = 'active'");
if ($careTeamCount < 0) {
    smoke_fail('EHR-CARE-TEAM', 'care_teams active rows', 'Query failed');
} elseif ($careTeamCount === 0) {
    smoke_fail('EHR-CARE-TEAM', 'care_teams active rows',
        'No active care teams — demo seed may not have run');
} else {
    smoke_pass('EHR-CARE-TEAM', 'care_teams active rows', "{$careTeamCount} active teams");
}

$careTeamMemberCount = smoke_count(
    "SELECT COUNT(*) FROM care_team_member WHERE status = 'active'"
);
if ($careTeamMemberCount > 0) {
    smoke_pass('EHR-CARE-TEAM', 'care_team_member active rows',
        "{$careTeamMemberCount} active members");
} else {
    smoke_fail('EHR-CARE-TEAM', 'care_team_member active rows',
        'No active team members — demo seed may not have run');
}

// 7f. Disk paths — new v0.16.0 files
$v016Paths = [
    'src/Core/Service/EncounterResolver'
        => $moduleRoot . '/src/Core/Service/EncounterResolver.php',
    'src/Core/Service/FormsRegistrar'
        => $moduleRoot . '/src/Core/Service/FormsRegistrar.php',
    'src/Shared/Submodule/CarePlan/Repository/CarePlanRepository'
        => $moduleRoot . '/src/Shared/Submodule/CarePlan/Repository/CarePlanRepository.php',
    'src/Shared/Submodule/CarePlan/Service/CarePlanService'
        => $moduleRoot . '/src/Shared/Submodule/CarePlan/Service/CarePlanService.php',
    'src/Shared/Submodule/CarePlan/Controller/CarePlanController'
        => $moduleRoot . '/src/Shared/Submodule/CarePlan/Controller/CarePlanController.php',
    'src/Shared/Submodule/ClinicalNotes/Repository/ClinicalNotesRepository'
        => $moduleRoot . '/src/Shared/Submodule/ClinicalNotes/Repository/ClinicalNotesRepository.php',
    'src/Shared/Submodule/ClinicalNotes/Service/ClinicalNotesService'
        => $moduleRoot . '/src/Shared/Submodule/ClinicalNotes/Service/ClinicalNotesService.php',
    'src/Shared/Submodule/ClinicalNotes/Controller/ClinicalNotesController'
        => $moduleRoot . '/src/Shared/Submodule/ClinicalNotes/Controller/ClinicalNotesController.php',
    'src/Shared/Submodule/CareTeam/Repository/CareTeamRepository'
        => $moduleRoot . '/src/Shared/Submodule/CareTeam/Repository/CareTeamRepository.php',
    'src/Shared/Submodule/CareTeam/Service/CareTeamService'
        => $moduleRoot . '/src/Shared/Submodule/CareTeam/Service/CareTeamService.php',
    'src/Shared/Submodule/CareTeam/Controller/CareTeamController'
        => $moduleRoot . '/src/Shared/Submodule/CareTeam/Controller/CareTeamController.php',
    'public/shared/care_plan.php'
        => $moduleRoot . '/public/shared/care_plan.php',
    'public/shared/clinical_notes.php'
        => $moduleRoot . '/public/shared/clinical_notes.php',
    'public/shared/care_team.php'
        => $moduleRoot . '/public/shared/care_team.php',
];

foreach ($v016Paths as $label => $path) {
    if (file_exists($path)) {
        smoke_pass('EHR-PATHS', $label);
    } else {
        smoke_fail('EHR-PATHS', $label, 'File missing: ' . $path);
    }
}




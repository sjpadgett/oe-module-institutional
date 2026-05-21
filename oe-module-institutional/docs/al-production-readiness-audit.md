# AL / SNF Production Readiness Audit
**Module:** oe-module-institutional  
**Source truth:** institutional_all_source.txt (v0.15.1 staging)  
**Date:** 2026-03-02

---

## Verdict: ✅ PRODUCTION READY — with 3 items to fix before go-live

All 12 AL submodules are present and wired end-to-end. Three issues
were found: one is a data-integrity risk, two are housekeeping.

---

## Layer-by-Layer Audit

### 1. Database Schema

| Table | Source | Status |
|---|---|---|
| `oei_al_episode` | migration 0002 + table.sql | ✅ |
| `oei_adl_record` | migration 0002 + table.sql | ✅ |
| `oei_incident` | migration 0002 + table.sql | ✅ |
| `oei_fall_risk_assessment` | migration 0003 | ✅ |
| `oei_activity_log` | migration 0004 | ✅ |
| `oei_triage` (AL vitals via PERIODIC) | table.sql (shared) | ✅ |
| `oei_mar_order` / `oei_mar_administration` | table.sql (shared) | ✅ |
| `oei_episode_disposition` | table.sql (shared — used by AlDischarge) | ✅ |
| `form_care_plan` | OpenEMR core — required columns verified in smoke test | ✅ |

All 5 AL-specific tables are created by versioned migrations and
guarded with `CREATE TABLE IF NOT EXISTS`. Fresh installs and
upgrades are both safe.

**⚠️ Issue 1 — LOW: `oei_fall_risk_assessment` uses `INT UNSIGNED`**  
Migration 0003 creates `id`, `episode_id`, `facility_id` as
`INT UNSIGNED` while every other AL table uses `bigint(20) UNSIGNED`.
MySQL silently handles the join type mismatch today, but it is
inconsistent and will need correcting before the dataset grows large
or if strict mode is ever enabled.

**Fix:** In migration 0003, change `INT UNSIGNED` → `bigint(20) UNSIGNED`
for those three columns. Create a migration 0005 that runs
`ALTER TABLE oei_fall_risk_assessment MODIFY COLUMN ...` for existing
deployments.

---

### 2. Migrations Coverage

| File | Version | Content | Status |
|---|---|---|---|
| `0001_initial_schema.sql` | 1.0.0 | version marker only | ✅ |
| `0002_assisted_living.sql` | 1.1.0 | al_episode, adl_record, incident | ✅ |
| `0003_al_fall_risk.sql` | 1.2.0 | fall_risk_assessment | ✅ |
| `0004_al_activity_log.sql` | 1.3.0 | activity_log | ✅ |

MigrationRunner scans `sql/migrations/` directly by filename pattern —
this works correctly. All four files are idempotent (`INSERT IGNORE`
into `oei_schema_version`).

**⚠️ Issue 2 — MEDIUM: `manifest.json` migrations array is stale**  
The manifest still lists the legacy flat files:
```json
"migrations": [
    "table.sql", "sql/context.sql", "sql/assisted_living.sql",
    "sql/al_phase2.sql", "sql/al_discharge_seed.sql", "sql/al_activity.sql"
]
```
These are not what MigrationRunner uses — it reads `sql/migrations/`
directly. The list is dead code today, but if any future code (or a
new OpenEMR module manager) reads `manifest.json` to apply schema, it
would run the wrong files. It is also misleading to any developer
reading the manifest.

**Fix:** Replace the `migrations` array with the four versioned files:
```json
"migrations": [
]
```
Also bump `"version": "0.15.0"` → `"0.15.1"`.

---

### 3. PHP Source Classes

All 12 AL submodules verified present with correct namespace and methods:

| Submodule | Controller | Service | Repository | Methods smoke-tested |
|---|---|---|---|---|
| ResidentBoard | ✅ | ✅ | ✅ | fetchActiveResidents, fetchUnitSummary |
| ResidentIntake | ✅ | ✅ | ✅ | admitResident, hasActiveEpisode |
| ResidentProfile | ✅ | — | ✅ | fetchHeader, fetchVitalsHistory, fetchAdlHistory, fetchCarePlanSummary, fetchMarToday, fetchRecentIncidents, fetchLatestFallRisk, fetchCareTeam |
| AdlTracking | ✅ | ✅ | ✅ | listByEpisode, chart, fetchOverdueEpisodes |
| AlVitals | ✅ | — | ✅ (wraps TriageRepository) | record, listForEpisode, getLatest, weightTrend |
| FallRisk | ✅ | — | ✅ | record, getLatest, listByEpisode |
| CarePlan | ✅ | ✅ | ✅ | fetchByEpisode, addEntry, updateStatus, fetchCareTeam |
| IncidentReport | ✅ | ✅ | ✅ | create, listByFacility, markReported, fetchOne |
| AlMar | ✅ | — | ✅ (wraps shared MAR) | listActiveOrders, listAllOrders, listAdminsByWindow |
| AlDischarge | ✅ | — | ✅ | getPlan, savePlan, confirmDeparture, getResidentHeader |
| AlHandoff | ✅ | — | ✅ | fetchHandoff, fetchSummary |
| AlActivity | ✅ | — | ✅ | getByDate, getByDateRange, getByEpisode, insert |

Domain enums verified: `AdlLevel`, `CareLevel`, `FallRiskLevel`, `IncidentType` — all present.

---

### 4. Public Entry Pages

All 12 pages verified in `public/al/`:

| Page | Feature gate | Controller used | Status |
|---|---|---|---|
| board.php | `al_board` | ResidentBoardController | ✅ |
| intake.php | `al_intake` | ResidentIntakeController | ✅ |
| profile.php | `al_profile` | ResidentProfileController | ✅ |
| adl.php | `al_adl` | AdlController | ✅ |
| vitals.php | `al_vitals` | AlVitalsController | ✅ |
| fall_risk.php | `al_fall_risk` | FallRiskController | ✅ |
| care_plan.php | `al_care_plan` | CarePlanController | ✅ |
| incident.php | `al_incident` | IncidentController | ✅ |
| al_mar.php | `al_mar` | AlMarController | ✅ |
| discharge.php | `al_discharge` | AlDischargeController | ✅ |
| handoff.php | `al_handoff` | AlHandoffController | ✅ |
| activity.php | `al_activity` | AlActivityController | ✅ |

Every page uses `require_once _bootstrap.php`, checks CSRF, and gates
on `$manifest->featureEnabled()` before instantiating its controller.
None contain SQL or business logic directly.

---

### 5. Feature Flags (manifest.json)

All 12 AL flags confirmed `true` in manifest.json:

```
al_board ✅   al_intake ✅    al_care_plan ✅  al_adl ✅
al_profile ✅  al_vitals ✅   al_fall_risk ✅  al_mar ✅
al_incident ✅ al_discharge ✅ al_activity ✅  al_handoff ✅
```

All 12 are also surfaced in the `ASSISTED_LIVING` CareContext
feature list in `ContextManifest.php` (the current/newer version).

---

### 6. Cross-Cutting Dependencies

| Dependency | How AL uses it | Status |
|---|---|---|
| `oei_episode` (Core) | Master episode for every AL resident | ✅ |
| `EpisodeRepository::closeWithDisposition()` | AlDischarge fires HL7 A03 automatically on departure confirmation | ✅ |
| `TriageRepository` (Shared) | AlVitalsRepository wraps it with `arrival_mode='PERIODIC'` | ✅ |
| `MarOrderRepository` + `MarService` (Shared) | AlMarRepository delegates all writes, including allergy checks and high-alert detection | ✅ |
| `form_care_plan` + `form_encounter` (OpenEMR core) | ResidentIntakeRepository creates a form_encounter at admission; encounter_id stored in oei_al_episode; required for CCDA/FHIR export | ✅ |
| `AuditService` | Discharge and intake events recorded to oei_episode_event | ✅ |
| `AlHandoffRepository::fetchHandoff` | Full 18-column correlated-subquery confirmed — includes `days_since_fall_reassess`, `pending_mar_count`, `recent_incident_count`, `pending_disposition`, `care_plan_goal` | ✅ |
| Smoke test | All 12 AL feature flags + all 5 AL schema tables explicitly checked | ✅ |

---

### 7. Clinical Workflow Data-Integrity Risk

**⚠️ Issue 3 — MEDIUM: Silent care plan loss if form_encounter INSERT fails at admission**

`ResidentIntakeRepository::admitResident()` creates three records in
sequence: `oei_episode`, `oei_al_episode`, `form_encounter`. If the
`form_encounter` INSERT fails (e.g., a required OpenEMR column is
missing in a non-standard OE build), the episode is still created but
`oei_al_episode.encounter_id` is stored as 0 or NULL.

`ResidentProfileRepository::fetchCarePlanSummary()` short-circuits and
returns empty arrays when `$encounterId === 0`. The result: the resident
is admitted, vitals and ADL work, but the care plan tab silently shows
no entries — with no error surfaced to the user.

**Fix:** Add a post-admit validation check in `ResidentIntakeService::admit()`:
```php
if ($episodeId > 0) {
    // Verify encounter_id was set
    $ep = $this->repo->getAlEpisode($episodeId);
    if (empty($ep['encounter_id'])) {
        // Log warning — care plan will not function
        error_log('[OEI] AL admission ep=' . $episodeId . ' has no encounter_id — care plan disabled');
    }
}
```
Or: surface it in `smoke_test.php` as a DATA check —
`SELECT COUNT(*) FROM oei_al_episode WHERE encounter_id IS NULL OR encounter_id = 0`.

---

## Summary

| # | Severity | Item | Action |
|---|---|---|---|
| 1 | LOW | `oei_fall_risk_assessment` uses INT vs bigint | Add migration 0005 to ALTER column types |
| 2 | MEDIUM | `manifest.json` migrations array lists legacy files | Update to `sql/migrations/` paths + bump version to 0.15.1 |
| 3 | MEDIUM | Care plan silently empty if form_encounter fails at admission | Add post-admit encounter_id check in service or smoke test |

No blockers. Items 2 and 3 are the ones to address before first real
residents are admitted on a live system.

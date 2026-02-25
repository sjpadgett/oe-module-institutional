# Institutional Module for OpenEMR (`oe-module-institutional`)

## What this is
OpenEMR is strong in outpatient care, but many communities—especially **rural hospitals**, **critical access hospitals**, and other **institutional settings**—need lightweight, practical workflows for:

- **Rural ED + urgent intake**
- **Short stay / Observation**
- **Behavioral Health (BH) hold + safety monitoring**
- Simple, auditable episode-based tracking without requiring a full inpatient ADT stack

`oe-module-institutional` is an installable Composer module that implements these workflows as **submodules** and keeps compatibility with OpenEMR's:
- Module Manager install conventions (`table.sql`)
- Menu event system (via `openemr.bootstrap.php` + `Bootstrap.php`)
- Dependencies and upgrade-safe patterns (no core patching required)

## Current working features (baseline)
### Submodules (manifest-driven)
- **ED Tracking Board**
  - Show active episodes, elapsed time, workflow status
  - Assign locations, set status
  - Start Observation
  - Close with disposition
- **Episode Intake**
  - Search patient by name/DOB/phone/PID
  - Create ED episode (arrival mode, ESI, chief complaint, triage note)
  - Redirect to ED board
- **Locations (ADT-lite)**
  - Manage institutional locations (ED rooms, obs beds, BH safe rooms)
- **Tasks**
  - Facility open tasks list + “Complete”
  - Observation baseline tasks auto-generated on Start Obs
- **BH Safety**
  - Episode-level BH observation level + risk flags
  - Quick-set BH level from ED board
  - BH check tasks runway auto-generated when Tasks enabled

## Near-term roadmap (team-friendly)
- **Obs Protocols**
  - Protocol-driven task bundles, milestones, discharge readiness
- **BH Hold**
  - Legal status, clearance checklist, sitter tracking, handoff log
- **Location Workflow**
  - Transfers/moves, occupancy dashboard, dirty/blocked workflows
- **Reporting**
  - Door-to-room, LOS, LWBS, obs conversion, BH checks completion

## How to help
- UI/UX alignment with OpenEMR workflows
- Task bundle configurability (facility + protocol)
- Reporting views + metrics
- Hardening and test coverage
- Documentation, demos, onboarding improvements

Feature 1 — Episode Timeline
TimelineRepository runs 7 independent queries (episode events, status history, location history, vitals, tasks, MAR, e-referral) and merges them into one array sorted ascending. Each entry carries a severity (danger/warning/success/info) derived from clinical significance — SpO₂ < 90 is danger, a late task is warning, a completed transfer is success. Gap annotations appear between entries separated by more than 10 minutes.
TimelineController batch-loads user display names for all user_id values in one users query rather than N+1 lookups.
timeline.php renders a vertical CSS track timeline with coloured dot markers, "since arrival" elapsed time on every event, source badges (VITALS / MAR / STATUS / etc.), and an episode sidebar for quick switching.

Feature 2 — Vitals Scheduling
VitalsSchedulerService has two public methods: scheduleForEd() (Q2H, 12h window on rooming) and scheduleForObs() (Q4H, 24h window on obs start). Both read interval and window from oei_settings so each facility can tune them. A ±5-minute dedup window prevents double-creating tasks if the service is called twice. Fails silently (returns 0) if oei_task doesn't exist yet.
Patched AdtService adds an optional $vitalsScheduler constructor parameter — existing callers that don't inject it continue working unmodified. Vitals are scheduled after openHistory() fires, so the room assignment timestamp and first vitals due time are properly ordered.
Patched ObsService inspects the protocol's definition_json for any task type containing VITALS before deciding whether to schedule. The two GENERAL_OBS and CHEST_PAIN built-in protocols both define VITALS_Q4H tasks, so the scheduler correctly skips them. Custom protocols without vitals tasks get them automatically.

Feature 3 — Staff Assignment
AssignmentRepository adds the two oei_episode columns via migration, reads staff lists with nurse/provider discrimination (job_title/usergroup heuristic for nurses, authorized=1 or non-empty NPI for providers), and returns the facility board with names joined in a single query.
AssignmentController handles both standard POST/PRG and an inline JSON POST (?json=1 in POST body) for the board widget path you can wire later.
assignments.php shows all active episodes in a table with current assignments as coloured badges, and a Bootstrap modal with pre-populated dropdowns on the edit pencil. JSON GET endpoint at ?json=1&episode_id=N returns current assignment for the board to read.
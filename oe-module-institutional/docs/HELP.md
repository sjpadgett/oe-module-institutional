# Institutional Module — About / Help

This module adds institutional-focused workflows to OpenEMR (rural ED + short stay / observation), with optional Behavioral Health tracks.

## Current submodules (v0.1.x)

### Tracking
- **ED Tracking Board** (`public/ed_board.php`)
  - View active episodes and key operational fields (location, acuity, next task, obs plan, BH flags when enabled).
- **BH Boarding Board** *(optional)* (`public/bh_boarding.php`)
  - Tracks BH placement status and boarding-related workflow when the `bh_boarding` feature is enabled.

### Operations
- **Intake** (`public/intake.php`)
  - Create an episode for a patient (PID) and optionally link to an encounter (EID).
- **Tasks** (`public/tasks.php`)
  - Lightweight task list tied to episode(s) (next task/due date surfaced on boards).
- **Transfers** (`public/transfers.php`)
  - Track transfer requests/acceptance/transport with a receiving facility directory selector.
- **Locations** (`public/locations.php`)
  - Create/manage institutional locations (rooms/beds/units) used by Bed Board and episode location history.

### Protocols
- **Obs Protocols** (`public/obs_protocols.php`)
  - Define observation protocol templates (protocol key, name, properties).
- **Obs Episodes / Plans** (`public/obs_episodes.php`)
  - Attach an observation plan to an episode (protocol key, targets/runway).

### Admin / Reporting
- **Facility Directory** (`public/facility_directory.php`)
  - Maintain a directory of receiving facilities/services used by Transfers.
- **Exports** (`public/exports.php`)
  - Basic CSV exports for operational review (episodes, throughput, etc.).


## Menu structure (current)

Institutional
- Tracking
  - ED Tracking Board
  - BH Boarding Board *(optional)*
- Operations
  - Intake
  - Tasks
  - Transfers
  - Locations
  - Bed Board
- Protocols
  - Obs Protocols
  - Obs Episodes / Plans
- Admin
  - Facility Directory
  - Exports
  - About / Help

Notes:
- Items appear based on enabled features in `manifest.json`.
- BH items are optional and may be hidden if BH features are disabled.

## Optional Behavioral Health features
These are intentionally optional so the module remains institutional-first:
- **BH Safety** (`public/bh_safety.php`) – observation level / risk flags (when enabled)
- **BH Boarding** (`public/bh_boarding.php`) – placement tracking (when enabled)

## Demo / testing
- Smoke test checklist: `docs/SMOKE_TEST.md`
- Dev seed SQL: `sql/dev_seed.sql`

## Notes
- Schema is defined in `table.sql` (install-time). No schema changes are performed at runtime.
- Menu structure and feature toggles are driven by `manifest.json`.

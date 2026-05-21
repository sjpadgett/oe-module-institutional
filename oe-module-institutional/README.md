# oe-module-institutional

Institutional workflows for OpenEMR (starting with **rural ED**, **short stay / observation**, and **behavioral health safety**) implemented as an installable Composer module with **manifest-driven submodules**.

## Docs
- Overview: `docs/OVERVIEW.md`
- Demo script: `docs/DEMO.md`
- Contributing / onboarding: `docs/CONTRIBUTING.md`
- Roadmap + starter issues: `docs/ROADMAP.md`

## Install (quick)
1. Copy into OpenEMR:
   `interface/modules/custom_modules/oe-module-institutional`
2. In module directory: `composer install`
3. OpenEMR: Admin → Modules → Manage Modules → Install/Enable **Institutional**
4. Tables are created via `table.sql`

---

## This update
- Menu fix applied to `Bootstrap::addInstitutionalMenu()` per your pasted method
- ED Board now supports:
  - workflow status changes (writes `oei_episode_status_history`)
  - Start Observation (sets episode type `OBS` + appends `OBS` status history)
  - Disposition + close (sets `oei_episode.disposition`, closes episode, appends `CLOSED` history)
- Workflow status displayed from latest status history row (subquery)

## Next after this
Tasks submodule auto-generation on OBS start (vitals/reassess) and then BH safety overlay.


## Tasks submodule
- When you click **Start Obs** on the ED Board, the module auto-generates baseline Observation tasks:
  - `VITALS_Q4H` (next 24 hours)
  - `REASSESS_Q2H` (next 24 hours)
- View and complete tasks via **Institutional → Tasks**


## BH safety overlay
- Enable via manifest `bh_safety: true`
- Adds an episode-level BH observation level (`NONE`, `Q60`, `Q30`, `Q15`, `ONE_TO_ONE`) stored in `oei_bh_safety`
- Quick-set from ED Board and full list view via **Institutional → BH Safety**
- If Tasks enabled, generates BH check tasks for the next 4 hours (de-duped).


## Episode Intake submodule
- Manifest feature: `intake`
- Menu: **Institutional → Episode Intake**
- Patient search (name/DOB/phone/PID) then create ED episode (arrival mode, ESI, chief complaint, triage note)
- Redirects to ED board after creation

## Obs Protocols submodule
- Menu: **Institutional → Obs Protocols** (manage protocol templates)
- Menu: **Institutional → Obs Episodes** (view active obs plans)
- Start Obs applies default `GENERAL_OBS` protocol and generates task runway (if Tasks enabled)
- ED Board shows Obs protocol badge and next due task


### Obs Episode View
- `public/obs_episode.php?episode_id=...` shows protocol, elapsed time, time remaining, next due task, overdue count
- Lets you apply/change protocol and generate runway tasks


## Obs Start Picker
- Feature: `obs_start_picker`
- Adds protocol selector to **Start Obs** so staff can pick Chest Pain/TIA/etc at the moment obs begins.
- Applies protocol and generates runway tasks.


## Milestones + Extend Runway
- Milestones are derived from protocol JSON:
  - Prefer `milestones: [...]` entries, otherwise tasks with `at_minutes` are treated as milestones.
- Obs Episode View shows upcoming milestones and their task status.
- **Extend runway** generates additional tasks for the next runway window (de-duped).


## Disposition + Throughput
- Menu: **Institutional → Disposition** to set disposition, destination, decision/depart times (records events)
- Menu: **Institutional → Throughput** to view daily KPIs and per-episode metrics
- ED Board includes quick stamps for ROOM and PROVIDER events and a link to Disposition.


## BH Boarding / Transfer
- Menu: **Institutional → BH Boarding** to track psychiatric boarding / transfer workflow.
- Captures placement status, legal status, risk flags, acceptance/transport timestamps, EMTALA completion, and a transfer checklist.
- Events: `BH_ACCEPTED`, `BH_TRANSPORT`, `EMTALA_COMPLETE` feed the Throughput dashboard.


## Quick demo (5 minutes)

1. Install via Module Manager (fresh DB or dev reset).
2. Institutional → Episode Intake: create an active episode.
3. Institutional → Locations (Operations): add a few locations (ED01, OBS1) and mark active.
4. Institutional → Bed Board: assign the episode to a location.
5. Institutional → Transfers: create a transfer to a facility directory entry.
6. Institutional → Exports: download CSV to validate the pipeline.


## Developer docs
- Smoke test: `docs/SMOKE_TEST.md`
- Dev seed SQL: `sql/dev_seed.sql`

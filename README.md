# Institutional — OpenEMR Module

**Institutional care workflows for OpenEMR.** This module adds purpose-built clinical tracks — Emergency Department, Observation Stay, Behavioral Health, Assisted Living, Inpatient, and Home-Based Care — on top of the OpenEMR open-source EHR. Features are turned on or off per facility through a manifest-driven feature-flag system, menus adapt to clinical context, and the schema upgrades itself through versioned migrations.

- **Module name:** `oe-module-institutional`
- **License:** GNU General Public License v3 (see [`LICENSE`](LICENSE))
- **Requires:** OpenEMR 8.0+, PHP 8.2+
- **UI:** Bootstrap 5.3 (bundled locally — no CDN dependency)

---

## Why this module

OpenEMR is excellent at the longitudinal ambulatory record. Institutional care — an ED tracking board, an observation-stay clock, a behavioral-health boarding view, an assisted-living resident board, an eMAR with high-alert handling — needs a different set of real-time, location-aware, throughput-oriented tools. This module supplies them as a set of opt-in submodules that integrate with OpenEMR's existing patient, user, encounter, and forms tables rather than replacing them.

Design principles, in keeping with OpenEMR's own philosophy:

- **Plain PHP, minimal dependencies.** No application framework. The only runtime dependency is Bootstrap 5.3, vendored locally.
- **Manifest-driven.** Every feature is a flag. A facility runs only what it needs.
- **Additive, not destructive.** All institutional data lives in `oei_*` tables; integration with OpenEMR happens through the documented form and registry layers.
- **Self-verifying.** Built-in onboarding and smoke-test pages report install health without requiring database access.

---

## Clinical tracks

| Track | What it covers | Primary entry page |
|-------|----------------|--------------------|
| **Emergency Department (ED)** | Real-time tracking board, triage, ADT-lite, throughput, charge-nurse alerts | `public/ed_board.php` |
| **Observation Stay (OBS)** | Observation protocols, episode clock, observation billing | `public/context_manager.php` → OBS |
| **Behavioral Health (BH)** | Safety checks, BH boarding view, transfer tracking | `public/bh_boarding.php` |
| **Assisted Living (AL)** | Resident board, intake, ADL tracking, fall risk, incidents, activity log, nursing MAR | `public/al/board.php` |
| **Inpatient (IP)** | Floor board, admission, shared vitals and observations | `public/ip/board.php` |
| **Home-Based Care (HBC)** | Visit board, referrals, scheduling, communication log, offline/mobile visit capture | `public/hbc/board.php` |

Cross-track capabilities include an eMAR with high-alert detection and waste/witness documentation, care plans and clinical notes written through OpenEMR's native form layer, multi-facility context switching, a unified Command Center, HL7 ADT messaging, and admin data exports.

The **Context Manager** (`public/context_manager.php`) is the recommended starting point: it presents each enabled care context as a card and routes to the appropriate board.

---

## Requirements

- **OpenEMR** 8.0.0 or newer
- **PHP** 8.2.0 or newer
- **MySQL / MariaDB** (as required by your OpenEMR install)
- **Composer** (to install the Bootstrap dependency)

---

## Installation

These steps assume a standard OpenEMR layout where custom modules live under
`interface/modules/custom_modules/`.

### 1. Place the module

Clone (or copy) the module into the custom-modules directory so the path is:

```
<openemr>/interface/modules/custom_modules/oe-module-institutional/
```

```bash
cd <openemr>/interface/modules/custom_modules/
git clone https://github.com/sjpadgett/oe-module-institutional.git
```

The clone produces a correctly-named `oe-module-institutional/` directory — the
exact path OpenEMR expects.

### 2. Install dependencies

From the module directory, install the front-end dependency:

```bash
cd oe-module-institutional
composer install
```

This pulls Bootstrap 5.3 into `vendor/`. The module is configured for **local**
Bootstrap (`bootstrap5_mode: local`) and several pages load the bundled CSS/JS
directly, so the Bootstrap assets must be present under `vendor/` at runtime —
no external CDN is contacted. If you installed from a packaged release archive
that already includes `vendor/`, you can skip this step.

### 3. Register and enable the module in OpenEMR

1. Log in to OpenEMR as an administrator.
2. Go to **Modules → Manage Modules** (Administration).
3. Find **Institutional** in the list of unregistered modules, then **Register** and **Enable** it.

On enable, OpenEMR runs the module's setup
(`OpenEMR\Modules\Institutional\Bootstrap::subscribeToEvents`). This wires the
module into OpenEMR's event and menu system and starts the schema installer.

### 4. Database schema (installed automatically)

You do not run any SQL by hand for a new install. When you enable the module in
Module Manager (step 3), OpenEMR runs the module's install schema —
**`table.sql`** — which creates the full set of `oei_*` tables in their current
form. A fresh install is complete after this — there is no separate migration
step, because `table.sql` already reflects the final schema.

> If you installed an earlier build and your `oei_*` tables pre-date the current
> schema, see [`docs/UPGRADE.md`](docs/UPGRADE.md) for upgrade options.

### 5. Verify the install

Two built-in pages confirm a healthy install — both are read-only and safe to
run against production:

- **Setup Checklist** — `public/onboarding.php`
  Live PASS / WARN / FAIL checks for schema, facility configuration, locations,
  and HL7 state. Designed so an implementer can validate a new facility before
  go-live without database access.

- **Smoke Tests** — `public/smoke_test.php`
  Verifies every `oei_*` table and column, PSR-4 autoloading, expected service
  methods, manifest feature flags, and critical file paths. Add `?verbose=1` to
  see passing rows too, or run from the CLI:

  ```bash
  php public/smoke_test.php --verbose
  ```

A fresh install will show "no data yet" warnings on the boards — that is normal.
Configure facilities, rooms, and beds via **Bed Management** before go-live, or
load the demo data below for a populated training instance.

---

## Demo data (training / evaluation)

For demos, evaluation, and training, the module ships a set of SQL seed files
under `sql/` that populate the boards across all clinical tracks with realistic
fictional patients, episodes, care teams, vitals, MAR orders, observations,
billing lines, care plans, and clinical notes — so every board renders with
live-looking content.

The seeds are split into one base file plus three companions:

| Seed file | Populates | Prerequisite |
|-----------|-----------|--------------|
| `sql/institutional-demo-seed-stable.sql` | Base demo: facilities, patients, and ED/OBS/BH/AL/IP/HBC episodes across the core `oei_*` tables | A schema-complete install (`table.sql`) |
| `sql/observations-demo-seed.sql` | 8 observation scenarios (CGM, CHF, INR, sepsis watch, COPD, home cardiac, etc.) that drive board badges and profile panels | `oei_observation` / `oei_obs_type` present (in `table.sql`) |
| `sql/oei_billing_demo_seed_upsert.sql` | Institutional billing lines (`oei_billing_line`) | Billing tables present (in `table.sql`) |
| `sql/institutional-demo-seed-hbc-upsert.sql` | Extra Home-Based Care cases: a queued referral, a scheduled first visit, and an active case with visit history | HBC tables present (in `table.sql`) |

Because `table.sql` already creates every table these seeds touch, all four run
cleanly against a fresh install. The base seed uses `INSERT IGNORE` /
upserts on OpenEMR core tables so it never overwrites existing production rows,
and all four are safe to re-run.

### Loading the demo seed

> ⚠️ **Never run demo data against a real patient database.** The seed inserts
> fictional patients and episodes. Use a fresh evaluation/training OpenEMR
> instance only.

1. Complete the standard install (steps 1–3 above) so the schema is in place.

2. Apply the seeds against the same database, base first, then the companions:

   ```bash
   # from the module directory, against your OpenEMR database
   mysql -u <user> -p <openemr_db> < sql/institutional-demo-seed-stable.sql
   mysql -u <user> -p <openemr_db> < sql/observations-demo-seed.sql
   mysql -u <user> -p <openemr_db> < sql/oei_billing_demo_seed_upsert.sql
   mysql -u <user> -p <openemr_db> < sql/institutional-demo-seed-hbc-upsert.sql
   ```

   The base seed must run first; the three companions can run in any order after
   it. For a minimal demo, the base seed alone is enough — add the companions to
   showcase observations, billing, and the extended HBC cases.

3. Open the **Smoke Tests** page (`public/smoke_test.php`). The DATA section
   checks demo seed row counts against expected ranges; once the seeds have run,
   those rows report as present rather than "seed may not have run."

4. Open the **Context Manager** (`public/context_manager.php`) and visit each
   board — ED, AL, IP, HBC, BH, OBS — to confirm populated content. A guided
   tour of the seeded scenarios is in
   [`docs/demo-walkthrough.md`](docs/demo-walkthrough.md).

### Removing demo data

Demo records live in the module's `oei_*` tables (and fictional patients in
OpenEMR's patient tables). The cleanest reset for an evaluation instance is to
restore from a pre-seed database snapshot, or to rebuild the evaluation database
and re-run the install without the seed.

---

## Configuration

Features are controlled by the module manifest. The `features` block in
`manifest.json` enables or disables each submodule; menus and Context Manager
cards appear only for enabled features. This lets a single codebase serve a
small assisted-living facility and a full multi-track hospital from the same
install, each seeing only what it has turned on.

Per-facility setup (facility identity, locations, rooms, beds, HL7 state) is
done through the Admin pages — **Bed Management**, **Facility Directory**,
**Settings**, and **ADT Lite** — surfaced under the **Institutional** top menu
once the module is enabled.

---

## Project layout

```
oe-module-institutional/
├── openemr-module.json     # OpenEMR module manifest (install_sql, setup class, versions)
├── manifest.json           # Feature flags, menus, UI mode
├── composer.json           # PSR-4 autoload + Bootstrap 5.3 dependency
├── openemr.bootstrap.php    # Module bootstrap entry
├── LICENSE                 # GNU General Public License v3
├── table.sql               # Install schema — all oei_* tables (run on enable)
├── sql/
│   ├── institutional-demo-seed-stable.sql      # base demo seed
│   ├── observations-demo-seed.sql              # observation scenarios
│   ├── oei_billing_demo_seed_upsert.sql        # billing demo
│   └── institutional-demo-seed-hbc-upsert.sql  # extra HBC cases
├── src/                    # PSR-4: OpenEMR\Modules\Institutional\
│   ├── Core/               # Repositories, resolvers, services
│   ├── Shared/             # Canonical cross-track services/repositories
│   ├── EmergencyDepartment/
│   ├── ObservationStay/
│   ├── BehavioralHealth/
│   ├── AssistedLiving/
│   ├── Inpatient/
│   ├── HomeBased/
│   ├── Operations/
│   └── Manifest/
└── public/                 # Clinical boards, admin pages, onboarding & smoke tests
    ├── al/  ip/  hbc/       # track-specific board pages
    └── ...
```

The codebase is roughly 90 public pages and 270+ domain classes, all autoloaded
through PSR-4 under the `OpenEMR\Modules\Institutional\` namespace.

---

## Contributing

This module follows OpenEMR's coding conventions: plain PHP, no added
frameworks, minimal dependencies for long-term maintainability. Every source
file carries a GPLv3 header. Schema changes are made directly in `table.sql`
(the single install schema), and the version stamp in `oei_schema_version`
should be bumped to match.

Before submitting changes, run the smoke tests (`public/smoke_test.php`) and the
setup checklist (`public/onboarding.php`) to confirm no regressions in schema,
autoloading, or feature wiring.

---

## License

This project is released under the **GNU General Public License version 3**.
See the [`LICENSE`](LICENSE) file for the full text. OpenEMR itself is GPL, and
this module is offered freely to the OpenEMR community under the same terms.




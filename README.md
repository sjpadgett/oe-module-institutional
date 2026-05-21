# Institutional — OpenEMR Module

**Institutional care workflows for OpenEMR.** This module adds purpose-built clinical tracks — Emergency Department, Observation Stay, Behavioral Health, Assisted Living, Inpatient, and Home-Based Care — on top of the OpenEMR open-source EHR. Features are turned on or off per facility through a manifest-driven feature-flag system, menus adapt to clinical context, and the schema upgrades itself through versioned migrations.

- **Module name:** `oe-module-institutional`
- **License:** GNU General Public License v3 (see [`LICENSE`](LICENSE))
- **Requires:** OpenEMR 8.0+, PHP 8.1+
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
- **PHP** 8.1.0 or newer
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
git clone <your-repo-url> oe-module-institutional
```

### 2. Install dependencies

From the module directory, install the vendored front-end dependency:

```bash
cd oe-module-institutional
composer install
```

This pulls Bootstrap 5.3 into `vendor/`. The module is configured for **local**
Bootstrap (`bootstrap5_mode: local`), so no external CDN is contacted at runtime.

### 3. Register and enable the module in OpenEMR

1. Log in to OpenEMR as an administrator.
2. Go to **Modules → Manage Modules** (Administration).
3. Find **Institutional** in the list of unregistered modules, then **Register** and **Enable** it.

On enable, OpenEMR runs the module's setup
(`OpenEMR\Modules\Institutional\Bootstrap::subscribeToEvents`). This wires the
module into OpenEMR's event and menu system and starts the schema installer.

### 4. Install / upgrade the database schema

The module ships its schema as:

- **`table.sql`** — the full install schema (the manifest's `install_sql`)
- **`sql/migrations/NNNN_description.sql`** — versioned, ordered upgrade steps

On bootstrap, the built-in **`MigrationRunner`** applies any pending files from
`sql/migrations/` in ascending filename order. Each migration is guarded by an
`INSERT IGNORE` into `oei_schema_version`, so re-running is always a safe no-op.
In most installs the schema therefore sets itself up the first time the module
loads after enabling.

If you prefer to apply SQL manually (or are scripting a deployment), run the
install schema once and then the migrations in order:

```bash
# from the module directory, against your OpenEMR database
mysql -u <user> -p <openemr_db> < table.sql
for f in sql/migrations/*.sql; do
  echo "Applying $f"
  mysql -u <user> -p <openemr_db> < "$f"
done
```

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

For demos, evaluation, and training, the module can be loaded with a demo seed
that populates the boards across all clinical tracks with realistic episodes,
care teams, vitals, MAR orders, care plans, and clinical notes — covering a set
of representative clinical scenarios so every board renders with live-looking
content.

> **Confirm the seed entry point for your build.** The demo seed ships as a SQL
> file under `sql/` (alongside the install schema and migrations). Depending on
> how you package releases, this is typically a file such as
> `sql/institutional-demo-seed-stable.sql` (or a dated equivalent). Set the path below to match the
> seed file actually included in your repository.

### Loading the demo seed

> ⚠️ **Never run demo data against a real patient database.** The seed inserts
> fictional patients and episodes. Use a fresh evaluation/training OpenEMR
> instance only.

1. Complete the standard install (steps 1–4 above) so the schema and migrations
   are in place.

2. Apply the demo seed SQL against the same database:

   ```bash
   # from the module directory — adjust the filename to your seed file
   mysql -u <user> -p <openemr_db> < sql/institutional-demo-seed-stable.sql
   ```

3. Open the **Smoke Tests** page (`public/smoke_test.php`). The DATA section
   checks demo seed row counts against expected ranges; once the seed has run,
   those rows report as present rather than "seed may not have run."

4. Open the **Context Manager** (`public/context_manager.php`) and visit each
   board — ED, AL, IP, HBC, BH, OBS — to confirm populated content.

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
├── manifest.json           # Feature flags, menus, UI mode, migration list
├── composer.json           # PSR-4 autoload + Bootstrap 5.3 dependency
├── openemr.bootstrap.php    # Module bootstrap entry
├── LICENSE                 # GNU General Public License v3
├── table.sql               # Install schema (oei_* tables)        [not in source dump]
├── sql/
│   ├── migrations/         # NNNN_description.sql, applied in order
│   └── ...                 # demo seed and supporting SQL
├── src/                    # PSR-4: OpenEMR\Modules\Institutional\
│   ├── Core/               # Migration runner, repositories, resolvers
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
file carries a GPLv3 header. Schema changes are made as new
`sql/migrations/NNNN_description.sql` files (never by editing applied
migrations), so existing installs upgrade cleanly on the next bootstrap.

Before submitting changes, run the smoke tests (`public/smoke_test.php`) and the
setup checklist (`public/onboarding.php`) to confirm no regressions in schema,
autoloading, or feature wiring.

---

## License

This project is released under the **GNU General Public License version 3**.
See the [`LICENSE`](LICENSE) file for the full text. OpenEMR itself is GPL, and
this module is offered freely to the OpenEMR community under the same terms.

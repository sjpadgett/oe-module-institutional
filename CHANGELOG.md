# Changelog

All notable changes to **oe-module-institutional** are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

_Changes landed on `main` but not yet tagged go here. Move them under a new
version heading when you cut a release (see "Releasing" in
`docs/CONTRIBUTING.md`)._

## [0.40.0]

First fully documented release. The module provides institutional care
workflows for OpenEMR across six clinical tracks, installed and schema-managed
through OpenEMR's Module Manager.

### Added
- Six clinical tracks: Emergency Department, Observation Stay, Behavioral
  Health, Assisted Living, Inpatient, and Home-Based Care.
- Manifest-driven feature flags — submodules and menu items enable/disable per
  facility via `manifest.json`.
- eMAR with high-alert detection and waste/witness documentation.
- Extended observations layer (`oei_observation` / `oei_obs_type`) driving board
  badges and profile panels.
- Institutional billing lines (`oei_billing_line`).
- Home-Based Care: referrals, scheduling, communication log, certification-period
  compliance, supervisory-visit flag, and offline/mobile visit capture.
- Assisted Living: resident board, intake, ADL tracking, fall-risk assessment,
  incidents, activity log, and nursing MAR.
- Multi-facility context switching and a unified Command Center.
- HL7 ADT messaging and admin data exports.
- Built-in onboarding checklist (`public/onboarding.php`) and smoke tests
  (`public/smoke_test.php`).
- Demo seed set under `sql/` (base plus observations, billing, and HBC
  companions) for training and evaluation instances.
- Full GPLv3 license headers across all source files and a root `LICENSE`.

### Changed
- Install schema (`table.sql`) now reflects the complete current set of `oei_*`
  tables, so fresh installs require no migrations.
- Repository structure flattened so the module lives at the repository root.

### Notes
- `sql/migrations/` is retained for upgrading existing pre-0.40 installs only;
  new installs apply `table.sql` directly via Module Manager.

[Unreleased]: https://github.com/sjpadgett/oe-module-institutional/compare/v0.40.0...HEAD
[0.40.0]: https://github.com/sjpadgett/oe-module-institutional/releases/tag/v0.40.0

# Contributing Guide

## Quick start
1. Fork the repo / copy module into your OpenEMR dev environment
2. `composer install` in module root
3. Enable module in OpenEMR Module Manager

## Project structure
- `openemr.bootstrap.php` + `src/Bootstrap.php`
  - Registers namespace and hooks menu events (OpenEMR MenuEvent)
- `manifest.json`
  - Feature flags and menu entries (submodules can be enabled/disabled here)
- `table.sql`
  - Tables created by Module Manager
- `public/*.php`
  - Minimal entrypoints that load OpenEMR globals and call controllers
- `src/Submodule/<Name>/...`
  - Submodule code: `Controller`, `Service`, `Repository`

## Adding a new submodule (pattern)
1. Create folders:
   - `src/Submodule/<YourSubmodule>/Controller`
   - `src/Submodule/<YourSubmodule>/Service`
   - `src/Submodule/<YourSubmodule>/Repository`
2. Add a `public/<page>.php` entrypoint that:
   - includes `public/_bootstrap.php`
   - checks feature flag: `$manifest->featureEnabled('<feature>')`
   - instantiates controller/service/repo
3. Add feature flag + menu item in `manifest.json`:
```json
"features": { "<feature>": true },
"menus": { "items": [ { "feature": "<feature>", "label": "My Page", "url": "/public/<page>.php" } ] }
```
4. If you need tables, add them to `table.sql` (Module Manager will handle install)

## Coding guidelines
- Keep dependencies minimal and OpenEMR-compatible
- Prefer `Repository` classes that wrap `sqlStatement/sqlQuery` for now
- Keep UI pages simple; Bootstrap 5 via CDN currently (isolated to module)
- Avoid breaking changes to existing submodules; expand incrementally

## Good first issues
- Intake: add “create new patient” flow (optional, behind feature flag)
- ED board: better OpenEMR look/feel and patient context links
- Tasks: “episode view” filter + due-soon highlighting
- Obs: protocol bundles + milestones view
- BH: expand flags and checklist tasks

## Releasing

Releases are cut by pushing a version tag. The `Release` GitHub Action then
builds the distributable archive (with `vendor/` bundled) and attaches it to a
GitHub Release automatically — see `.github/workflows/release.yml`.

1. Make sure `main` is green (the `CI` workflow passes) and working-tree clean.
2. Bump the version in **both** `openemr-module.json` and `manifest.json` to the
   new version (they must match).
3. Move the `## [Unreleased]` entries in `CHANGELOG.md` under a new
   `## [x.y.z]` heading, and update the compare/tag links at the bottom.
4. If dependencies changed, refresh the lock so the release build is
   reproducible: `composer update && git add composer.lock`.
5. Commit the version bump and changelog:
   ```bash
   git commit -am "Release vX.Y.Z"
   git push origin main
   ```
6. Tag and push the tag — this triggers the release build:
   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
7. The Action produces `oe-module-institutional-X.Y.Z.zip` on the release page.
   Confirm it unzips to a single `oe-module-institutional/` folder containing
   `vendor/`.

Use semantic versioning: patch for fixes, minor for backward-compatible
features, major for breaking changes. The tag (`vX.Y.Z`) must match the version
in the manifests.

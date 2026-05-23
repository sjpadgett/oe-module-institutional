# Institutional Module Upgrade Notes

This module relies on `table.sql` for **fresh installs** via OpenEMR Module Manager.

## Important
`CREATE TABLE IF NOT EXISTS ...` does **not** modify existing tables.  
If you installed an earlier build and your database tables pre-exist, you must upgrade them explicitly.

## Recommended upgrade approach (dev/testing)
For a clean dev environment, the simplest option is:

1. Uninstall the module in Module Manager
2. Drop the module tables (all `oei_*` tables) in the database
3. Reinstall the module so Module Manager re-applies `table.sql`

## Minimal manual upgrade: oei_location (Bed Board)
If you already have `oei_location` but it lacks the new columns (e.g. `code`), run:

```sql
ALTER TABLE oei_location
  ADD COLUMN code VARCHAR(20) NOT NULL DEFAULT '' AFTER facility_id,
  ADD COLUMN location_type VARCHAR(20) NOT NULL DEFAULT 'ROOM' AFTER name,
  ADD COLUMN unit_name VARCHAR(40) NULL AFTER location_type,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER unit_name,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_active,
  ADD COLUMN notes VARCHAR(255) NULL AFTER sort_order;

ALTER TABLE oei_location
  ADD UNIQUE KEY uniq_oei_loc_fac_code (facility_id, code);

CREATE INDEX idx_oei_loc_fac_active ON oei_location (facility_id, is_active, sort_order);
```

If your existing `oei_location` table is an older ADT-lite schema with different columns, **drop and recreate** is recommended for now.

## Why we do it this way
We keep schema authoritative in `table.sql` so installs are deterministic and match project expectations.

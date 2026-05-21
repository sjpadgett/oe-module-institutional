-- =============================================================================
-- Migration: 0007_bed_mgmt_active_location_guardrails.sql
-- Version:   1.6.0
-- Purpose:   Reconcile duplicate active bed/location assignments so the latest
--            active row remains open for each episode and each occupied target.
--
-- Safe to run multiple times. Rows already closed remain untouched.
-- =============================================================================

INSERT IGNORE INTO oei_schema_version (version, applied_datetime)
VALUES ('1.6.0', NOW());

-- -----------------------------------------------------------------------------
-- 1) Keep only the newest active row per episode.
-- -----------------------------------------------------------------------------
UPDATE oei_episode_location l
JOIN (
    SELECT episode_id, MAX(id) AS keep_id
    FROM oei_episode_location
    WHERE end_datetime IS NULL
    GROUP BY episode_id
    HAVING COUNT(*) > 1
) x
  ON x.episode_id = l.episode_id
SET l.end_datetime = NOW(),
    l.note = CASE
        WHEN l.note IS NULL OR l.note = '' THEN 'Auto-closed duplicate active episode location row (migration 0007).'
        ELSE CONCAT(l.note, ' | Auto-closed duplicate active episode location row (migration 0007).')
    END
WHERE l.end_datetime IS NULL
  AND l.id <> x.keep_id;

-- -----------------------------------------------------------------------------
-- 2) Keep only the newest active row for each occupied catalog location.
-- -----------------------------------------------------------------------------
UPDATE oei_episode_location l
JOIN (
    SELECT facility_id, location_id, MAX(id) AS keep_id
    FROM oei_episode_location
    WHERE end_datetime IS NULL
      AND location_id IS NOT NULL
    GROUP BY facility_id, location_id
    HAVING COUNT(*) > 1
) x
  ON x.facility_id = l.facility_id
 AND x.location_id = l.location_id
SET l.end_datetime = NOW(),
    l.note = CASE
        WHEN l.note IS NULL OR l.note = '' THEN 'Auto-closed duplicate active occupied location row (migration 0007).'
        ELSE CONCAT(l.note, ' | Auto-closed duplicate active occupied location row (migration 0007).')
    END
WHERE l.end_datetime IS NULL
  AND l.id <> x.keep_id;

-- -----------------------------------------------------------------------------
-- 3) Keep only the newest active row for ad-hoc location codes when no
--    catalog location_id was supplied.
-- -----------------------------------------------------------------------------
UPDATE oei_episode_location l
JOIN (
    SELECT facility_id, location_code, MAX(id) AS keep_id
    FROM oei_episode_location
    WHERE end_datetime IS NULL
      AND location_id IS NULL
      AND location_code IS NOT NULL
      AND location_code <> ''
    GROUP BY facility_id, location_code
    HAVING COUNT(*) > 1
) x
  ON x.facility_id = l.facility_id
 AND x.location_code = l.location_code
SET l.end_datetime = NOW(),
    l.note = CASE
        WHEN l.note IS NULL OR l.note = '' THEN 'Auto-closed duplicate active ad-hoc location row (migration 0007).'
        ELSE CONCAT(l.note, ' | Auto-closed duplicate active ad-hoc location row (migration 0007).')
    END
WHERE l.end_datetime IS NULL
  AND l.id <> x.keep_id;

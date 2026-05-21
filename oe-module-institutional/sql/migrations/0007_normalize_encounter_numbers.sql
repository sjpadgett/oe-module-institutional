-- =============================================================================
-- Migration 0007 — Normalize AL/IP encounter_id to encounter NUMBER
-- oe-module-institutional
--
-- Purpose:
--   1. Convert legacy AL/IP overlay rows that stored form_encounter.id
--      into the OpenEMR encounter NUMBER (form_encounter.encounter).
--   2. Backfill a matching 'newpatient' row in forms for AL/IP admission
--      encounters so they are visible in the OpenEMR encounter form list.
--
-- Safe to re-run:
--   - Updates only rows that still appear to hold form_encounter.id.
--   - Backfill uses NOT EXISTS guards to avoid duplicate forms rows.
-- =============================================================================

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.6.0', NOW());

-- -----------------------------------------------------------------------------
-- 1) Normalize AL encounter_id values
-- -----------------------------------------------------------------------------
UPDATE `oei_al_episode` ae
JOIN `form_encounter` fe
  ON fe.`id`  = ae.`encounter_id`
 AND fe.`pid` = ae.`pid`
LEFT JOIN `form_encounter` fe_num
  ON fe_num.`encounter` = ae.`encounter_id`
 AND fe_num.`pid`       = ae.`pid`
SET ae.`encounter_id` = fe.`encounter`
WHERE ae.`encounter_id` IS NOT NULL
  AND fe.`encounter`   IS NOT NULL
  AND fe_num.`id`      IS NULL;

-- -----------------------------------------------------------------------------
-- 2) Normalize IP encounter_id values (only if oei_ip_episode exists)
-- -----------------------------------------------------------------------------
SET @oei_has_ip_table := (
    SELECT COUNT(*)
      FROM information_schema.tables
     WHERE table_schema = DATABASE()
       AND table_name   = 'oei_ip_episode'
);

SET @oei_sql := IF(
    @oei_has_ip_table > 0,
    "UPDATE `oei_ip_episode` ip
      JOIN `form_encounter` fe
        ON fe.`id`  = ip.`encounter_id`
       AND fe.`pid` = ip.`pid`
      LEFT JOIN `form_encounter` fe_num
        ON fe_num.`encounter` = ip.`encounter_id`
       AND fe_num.`pid`       = ip.`pid`
      SET ip.`encounter_id` = fe.`encounter`
      WHERE ip.`encounter_id` IS NOT NULL
        AND fe.`encounter`   IS NOT NULL
        AND fe_num.`id`      IS NULL",
    'SELECT 1'
);
PREPARE stmt FROM @oei_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- -----------------------------------------------------------------------------
-- 3) Backfill forms/newpatient rows for AL admissions
-- -----------------------------------------------------------------------------
INSERT INTO `forms`
    (`date`, `encounter`, `form_name`, `form_id`,
     `pid`, `user`, `groupname`, `authorized`, `deleted`, `formdir`)
SELECT
    fe.`date`,
    fe.`encounter`,
    'New Patient Encounter' AS `form_name`,
    fe.`id`                 AS `form_id`,
    fe.`pid`,
    'admin'                 AS `user`,
    'Default'               AS `groupname`,
    1                       AS `authorized`,
    0                       AS `deleted`,
    'newpatient'            AS `formdir`
FROM `oei_al_episode` ae
JOIN `form_encounter` fe
  ON fe.`pid`       = ae.`pid`
 AND fe.`encounter` = ae.`encounter_id`
WHERE ae.`encounter_id` IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
        FROM `forms` f
       WHERE f.`formdir` = 'newpatient'
         AND f.`form_id` = fe.`id`
         AND f.`deleted` = 0
  );

-- -----------------------------------------------------------------------------
-- 4) Backfill forms/newpatient rows for IP admissions (if table exists)
-- -----------------------------------------------------------------------------
SET @oei_sql := IF(
    @oei_has_ip_table > 0,
    "INSERT INTO `forms`
        (`date`, `encounter`, `form_name`, `form_id`,
         `pid`, `user`, `groupname`, `authorized`, `deleted`, `formdir`)
     SELECT
        fe.`date`,
        fe.`encounter`,
        'New Patient Encounter' AS `form_name`,
        fe.`id`                 AS `form_id`,
        fe.`pid`,
        'admin'                 AS `user`,
        'Default'               AS `groupname`,
        1                       AS `authorized`,
        0                       AS `deleted`,
        'newpatient'            AS `formdir`
     FROM `oei_ip_episode` ip
     JOIN `form_encounter` fe
       ON fe.`pid`       = ip.`pid`
      AND fe.`encounter` = ip.`encounter_id`
     WHERE ip.`encounter_id` IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
             FROM `forms` f
            WHERE f.`formdir` = 'newpatient'
              AND f.`form_id` = fe.`id`
              AND f.`deleted` = 0
       )",
    'SELECT 1'
);
PREPARE stmt FROM @oei_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

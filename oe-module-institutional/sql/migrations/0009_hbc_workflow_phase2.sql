-- 0009_hbc_workflow_phase2.sql
-- Idempotent HBC workflow/schema upgrade for existing installs.

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'window_start_datetime'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `window_start_datetime` datetime DEFAULT NULL COMMENT ''Optional arrival window start'' AFTER `scheduled_datetime`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'window_end_datetime'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `window_end_datetime` datetime DEFAULT NULL COMMENT ''Optional arrival window end'' AFTER `window_start_datetime`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'route_sequence'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `route_sequence` smallint(5) UNSIGNED DEFAULT NULL COMMENT ''Optional daily route order'' AFTER `window_end_datetime`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'travel_notes'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `travel_notes` varchar(255) DEFAULT NULL COMMENT ''Parking, gate, arrival preference, route notes'' AFTER `route_sequence`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'med_reconciliation_status'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `med_reconciliation_status` enum(''NOT_DONE'',''NO_CHANGES'',''UPDATED'',''ISSUES_FOUND'') NOT NULL DEFAULT ''NOT_DONE'' AFTER `mileage_miles`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'med_reconciliation_summary'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `med_reconciliation_summary` text DEFAULT NULL AFTER `med_reconciliation_status`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'wound_summary'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `wound_summary` text DEFAULT NULL AFTER `med_reconciliation_summary`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'procedure_summary'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `procedure_summary` text DEFAULT NULL AFTER `wound_summary`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'home_safety_summary'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `home_safety_summary` text DEFAULT NULL AFTER `procedure_summary`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'care_coordination_needed'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `care_coordination_needed` tinyint(1) NOT NULL DEFAULT 0 AFTER `home_safety_summary`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'care_coordination_summary'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `care_coordination_summary` text DEFAULT NULL AFTER `care_coordination_needed`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'followup_plan'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `followup_plan` text DEFAULT NULL AFTER `care_coordination_summary`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'next_visit_due_date'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `next_visit_due_date` date DEFAULT NULL AFTER `followup_plan`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'next_visit_type'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD COLUMN `next_visit_type` enum(''SN'',''PT'',''OT'',''ST'',''MSW'',''HHA'',''MD'',''OTHER'') DEFAULT NULL AFTER `next_visit_due_date`')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND INDEX_NAME = 'idx_hbcv_route'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD KEY `idx_hbcv_route` (`facility_id`, `route_sequence`, `scheduled_datetime`)')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND INDEX_NAME = 'idx_hbcv_followup'),'SELECT 1','ALTER TABLE `oei_hbc_visit` ADD KEY `idx_hbcv_followup` (`facility_id`, `next_visit_due_date`)')
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`) VALUES ('0009', NOW());

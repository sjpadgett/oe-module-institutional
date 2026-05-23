-- 0013_institutional_billing_phase2.sql

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'review_reason'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `review_reason` varchar(120) DEFAULT NULL AFTER `status`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'source_label'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `source_label` varchar(80) DEFAULT NULL AFTER `external_ref`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'release_target'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `release_target` enum (''BILLING_MANAGER'',''UB04'',''PROFESSIONAL'',''LEDGER'',''STATEMENT'') DEFAULT NULL AFTER `notes`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'released_by_user_id'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `released_by_user_id` bigint(20) UNSIGNED DEFAULT NULL AFTER `updated_by_user_id`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'released_datetime'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `released_datetime` datetime DEFAULT NULL AFTER `updated_datetime`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND INDEX_NAME = 'idx_oei_bill_release'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD KEY `idx_oei_bill_release` (`facility_id`,`release_target`,`status`,`service_date`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0013', NOW());

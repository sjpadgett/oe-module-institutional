-- 0014_institutional_billing_phase3.sql

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND COLUMN_NAME = 'release_batch_key'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD COLUMN `release_batch_key` varchar(40) DEFAULT NULL AFTER `release_target`'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' AND INDEX_NAME = 'idx_oei_bill_batch'),
        'SELECT 1',
        'ALTER TABLE `oei_billing_line` ADD KEY `idx_oei_bill_batch` (`facility_id`,`release_batch_key`,`released_datetime`)'
    )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0014', NOW());

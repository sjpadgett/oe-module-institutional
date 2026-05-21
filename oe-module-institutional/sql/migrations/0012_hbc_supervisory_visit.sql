-- 0012_hbc_supervisory_visit.sql
-- Adds is_supervisory flag to oei_hbc_visit for HHA regulatory compliance.
-- When an HHA visit is completed, a supervisory SN visit must occur within 14 days.
-- Idempotent.

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND COLUMN_NAME = 'is_supervisory'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD COLUMN `is_supervisory` tinyint(1) NOT NULL DEFAULT 0 COMMENT ''1 = this visit is a supervisory oversight visit (RN supervising HHA)'' AFTER `travel_notes`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_visit' AND INDEX_NAME = 'idx_hbcv_supervisory'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_visit` ADD KEY `idx_hbcv_supervisory` (`episode_id`, `is_supervisory`, `status`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`) VALUES ('0012', NOW());

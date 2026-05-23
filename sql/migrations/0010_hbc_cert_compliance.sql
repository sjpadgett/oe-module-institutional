-- 0010_hbc_cert_compliance.sql
-- Adds authorized visit frequency to HBC episode for cert period compliance tracking.
-- Idempotent.

SET @sql = (
    SELECT IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_hbc_episode' AND COLUMN_NAME = 'authorized_visits_per_week'),
        'SELECT 1',
        'ALTER TABLE `oei_hbc_episode` ADD COLUMN `authorized_visits_per_week` smallint(5) UNSIGNED DEFAULT NULL COMMENT ''Payer-authorized visit frequency per week'' AFTER `cert_period_end`'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`) VALUES ('0010', NOW());

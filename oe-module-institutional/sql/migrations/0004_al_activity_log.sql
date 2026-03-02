-- =============================================================================
-- Migration 0004 — AL Activity Log
-- oe-module-institutional
-- Version: 1.3.0
-- Applies: oei_activity_log
-- =============================================================================

CREATE TABLE IF NOT EXISTS `oei_activity_log`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `activity_date`    date                NOT NULL,
    `activity_type`    varchar(60)         NOT NULL,
    `activity_name`    varchar(120)        NOT NULL,
    `start_time`       time                DEFAULT NULL,
    `duration_minutes` smallint(5) UNSIGNED DEFAULT NULL,
    `location`         varchar(80)         DEFAULT NULL,
    `led_by_user_id`   bigint(20) UNSIGNED DEFAULT NULL,
    `led_by_name`      varchar(80)         DEFAULT NULL,
    `attendance_json`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin
                           DEFAULT NULL CHECK (`attendance_json` IS NULL OR json_valid(`attendance_json`)),
    `attendance_count` smallint(5) UNSIGNED DEFAULT 0,
    `notes`            text                DEFAULT NULL,
    `created_datetime` datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_datetime` datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_activity_facility_date` (`facility_id`, `activity_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.3.0', NOW());

-- =============================================================================
-- Migration 0003 — AL Fall Risk Assessment
-- oe-module-institutional
-- Version: 1.2.0
-- Applies: oei_fall_risk_assessment
-- =============================================================================

CREATE TABLE IF NOT EXISTS `oei_fall_risk_assessment`
(
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`          INT UNSIGNED NOT NULL,
    `facility_id`         INT UNSIGNED NOT NULL,
    `assessed_by_user_id` INT UNSIGNED DEFAULT NULL,
    `assessed_datetime`   DATETIME     NOT NULL,
    `mfs_fall_history`    TINYINT(1)   NOT NULL DEFAULT 0,
    `mfs_secondary_dx`    TINYINT(1)   NOT NULL DEFAULT 0,
    `mfs_ambulatory_aid`  TINYINT(2)   NOT NULL DEFAULT 0,
    `mfs_iv_heparin_lock` TINYINT(1)   NOT NULL DEFAULT 0,
    `mfs_gait`            TINYINT(2)   NOT NULL DEFAULT 0,
    `mfs_mental_status`   TINYINT(1)   NOT NULL DEFAULT 0,
    `total_score`         TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
    `risk_level`          VARCHAR(10)  NOT NULL DEFAULT 'LOW',
    `notes`               TEXT         DEFAULT NULL,
    `created_datetime`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_fall_risk_episode` (`episode_id`, `assessed_datetime`),
    KEY `idx_fall_risk_facility` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.2.0', NOW());

-- 0010_facility_profile.sql
-- Normalized facility install-state profile for beta setup/runtime.

CREATE TABLE IF NOT EXISTS `oei_facility_profile`
(
    `facility_id`            bigint(20) UNSIGNED NOT NULL,
    `user_id`                bigint(20) UNSIGNED NOT NULL DEFAULT 0,
    `installed_purpose`      varchar(30)         NOT NULL DEFAULT '',
    `facility_name`          varchar(100)        NOT NULL DEFAULT '',
    `institutional_enabled`  tinyint(1)          NOT NULL DEFAULT 0,
    `default_context`        varchar(30)         NOT NULL DEFAULT 'FULL',
    `home_page`              varchar(200)        NOT NULL DEFAULT '',
    `enabled_contexts_json`  text                         DEFAULT NULL,
    `feature_overrides_json` longtext                     DEFAULT NULL,
    `setup_completed`        tinyint(1)          NOT NULL DEFAULT 0,
    `setup_step`             tinyint(1)          NOT NULL DEFAULT 0,
    `updated_by_user_id`     bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`       datetime            NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`facility_id`, `user_id`),
    KEY `idx_oei_fp_user` (`user_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-facility institutional profile. Written by Setup Wizard. Does not replace oei_settings.';

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0010', NOW());

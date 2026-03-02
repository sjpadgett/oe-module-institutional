-- =============================================================================
-- Migration 0002 — Assisted Living Foundation
-- oe-module-institutional
-- Version: 1.1.0
-- Applies: oei_al_episode, oei_adl_record, oei_incident
-- =============================================================================

CREATE TABLE IF NOT EXISTS `oei_al_episode`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED NOT NULL,
    `pid`              bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `encounter_id`     bigint(20) UNSIGNED DEFAULT NULL,
    `room`             varchar(20)         DEFAULT NULL,
    `unit`             varchar(40)         DEFAULT NULL,
    `care_level`       varchar(30)         DEFAULT NULL,
    `fall_risk_level`  varchar(20)         DEFAULT NULL,
    `fall_risk_score`  tinyint(3) UNSIGNED DEFAULT 0,
    `admit_reason`     text                DEFAULT NULL,
    `last_adl_score`   tinyint(3) UNSIGNED DEFAULT NULL,
    `last_adl_datetime` datetime           DEFAULT NULL,
    `created_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_al_episode` (`episode_id`),
    KEY `idx_al_facility` (`facility_id`, `created_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_adl_record`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `noted_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `noted_datetime`   datetime            NOT NULL,
    `adl_json`         longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`adl_json`)),
    `adl_score`        tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
    `notes`            text                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_adl_episode` (`episode_id`, `noted_datetime`),
    KEY `idx_oei_adl_facility` (`facility_id`, `noted_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_incident`
(
    `id`                    bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`            bigint(20) UNSIGNED NOT NULL,
    `facility_id`           bigint(20) UNSIGNED NOT NULL,
    `reported_by_user_id`   bigint(20) UNSIGNED DEFAULT NULL,
    `incident_type`         varchar(60)         NOT NULL,
    `severity`              varchar(20)         NOT NULL DEFAULT 'MODERATE',
    `incident_datetime`     datetime            NOT NULL,
    `location_description`  varchar(120)        DEFAULT NULL,
    `narrative`             text                DEFAULT NULL,
    `corrective_action`     text                DEFAULT NULL,
    `reported_state`        tinyint(1)          NOT NULL DEFAULT 0,
    `mandatory_report_sent` tinyint(1)          NOT NULL DEFAULT 0,
    `created_datetime`      datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_incident_episode` (`episode_id`),
    KEY `idx_incident_facility` (`facility_id`, `incident_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.1.0', NOW());

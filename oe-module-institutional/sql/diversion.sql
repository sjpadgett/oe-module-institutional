-- oe-module-institutional: Diversion Status migration
-- Version: 0.10.0
-- Adds facility-level diversion tracking per service line.

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- Current diversion status per facility × service line
-- One row per (facility_id, service_line) — updated in-place, history preserved below.
CREATE TABLE IF NOT EXISTS `oei_diversion`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `service_line`        varchar(40)         NOT NULL DEFAULT 'ED'
                              COMMENT 'ED | ICU | OBS | PSYCH | TRAUMA | PEDS | BURN',
    `status`              varchar(20)         NOT NULL DEFAULT 'OPEN'
                              COMMENT 'OPEN | DIVERSION | LIMITED | BYPASS',
    `reason`              varchar(255)                 DEFAULT NULL
                              COMMENT 'Free-text reason shown in facility directory',
    `diversion_start`     datetime                     DEFAULT NULL,
    `diversion_end`       datetime                     DEFAULT NULL,
    `updated_by_user_id`  bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`    datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_diversion_service` (`facility_id`, `service_line`),
    KEY `idx_oei_diversion_facility`        (`facility_id`, `status`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Current diversion status per facility and service line';

-- Full audit trail — every status change is appended here.
CREATE TABLE IF NOT EXISTS `oei_diversion_history`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `service_line`        varchar(40)         NOT NULL,
    `previous_status`     varchar(20)                  DEFAULT NULL,
    `new_status`          varchar(20)         NOT NULL,
    `reason`              varchar(255)                 DEFAULT NULL,
    `diversion_start`     datetime                     DEFAULT NULL,
    `diversion_end`       datetime                     DEFAULT NULL,
    `changed_by_user_id`  bigint(20) UNSIGNED          DEFAULT NULL,
    `changed_datetime`    datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_div_hist_facility` (`facility_id`, `service_line`, `changed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Audit log of all diversion status changes';

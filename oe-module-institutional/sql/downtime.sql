-- oe-module-institutional: Downtime Mode migration
-- Version: 0.10.0
-- Adds offline write queue for sync after network restoration.

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- Offline write queue: captures new arrivals / vitals / status notes
-- entered while the network was unavailable.
-- Each row is processed once and then status set to SYNCED or FAILED.
CREATE TABLE IF NOT EXISTS `oei_downtime_sync_queue`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `entry_type`       varchar(30)         NOT NULL
                           COMMENT 'ARRIVAL | VITALS | STATUS_NOTE | TASK_NOTE',
    `payload_json`     mediumtext          NOT NULL
                           COMMENT 'Raw JSON captured by browser',
    `captured_client`  datetime            NOT NULL
                           COMMENT 'Client-side timestamp from the browser',
    `queued_datetime`  datetime            NOT NULL
                           COMMENT 'Server-receipt datetime on sync POST',
    `synced_datetime`  datetime                     DEFAULT NULL,
    `status`           varchar(20)         NOT NULL DEFAULT 'PENDING'
                           COMMENT 'PENDING | SYNCED | FAILED | SKIPPED',
    `result_note`      varchar(255)                 DEFAULT NULL
                           COMMENT 'Error message or resultant ID on success',
    `submitted_by_user_id` bigint(20) UNSIGNED      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dt_queue_facility` (`facility_id`, `status`, `queued_datetime`),
    KEY `idx_oei_dt_queue_type`     (`entry_type`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Offline write queue — rows written by browser during network outage';

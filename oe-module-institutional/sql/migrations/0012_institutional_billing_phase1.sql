-- 0012_institutional_billing_phase1.sql

CREATE TABLE IF NOT EXISTS `oei_billing_line`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `episode_id`         bigint(20) UNSIGNED          DEFAULT NULL,
    `pid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `context_key`        varchar(30)                  DEFAULT NULL,
    `episode_type`       varchar(10)                  DEFAULT NULL,
    `billing_path`       enum ('CLAIM_MANAGER','MODULE_LEDGER','PROFESSIONAL_REVIEW') NOT NULL DEFAULT 'MODULE_LEDGER',
    `line_category`      enum ('PRIVATE_PAY','RECURRING','SERVICE','SUPPLY','ADJUSTMENT','CLAIM_STAGING') NOT NULL DEFAULT 'SERVICE',
    `status`             enum ('DRAFT','READY','HOLD','RELEASED','VOID') NOT NULL DEFAULT 'DRAFT',
    `service_date`       date                         NOT NULL,
    `charge_code`        varchar(40)                  DEFAULT NULL,
    `description`        varchar(255)                 NOT NULL,
    `quantity`           decimal(10,2)               NOT NULL DEFAULT 1.00,
    `unit_price`         decimal(12,2)               NOT NULL DEFAULT 0.00,
    `total_amount`       decimal(12,2)               NOT NULL DEFAULT 0.00,
    `external_ref`       varchar(80)                  DEFAULT NULL,
    `notes`              text                         DEFAULT NULL,
    `created_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`   datetime                     NOT NULL,
    `updated_datetime`   datetime                     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_bill_facility_status` (`facility_id`, `status`, `service_date`),
    KEY `idx_oei_bill_episode` (`episode_id`, `service_date`),
    KEY `idx_oei_bill_pid` (`pid`, `service_date`),
    KEY `idx_oei_bill_path` (`facility_id`, `billing_path`, `service_date`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Institutional billing ledger and billing-workbench staging lines';

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0012', NOW());

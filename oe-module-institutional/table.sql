SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE IF NOT EXISTS `oei_alert_ack`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_key`        varchar(120)        NOT NULL COMMENT 'e.g. LWBS_RISK:42',
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `acked_datetime`   datetime            NOT NULL,
    `expires_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_alert_ack` (`alert_key`, `facility_id`),
    KEY `idx_oei_alert_ack_exp` (`facility_id`, `expires_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user alert snooze/acknowledgement records';

CREATE TABLE IF NOT EXISTS `oei_bh_boarding`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `legal_status`       varchar(40)                  DEFAULT NULL,
    `suicide_risk`       varchar(20)                  DEFAULT NULL,
    `violence_risk`      varchar(20)                  DEFAULT NULL,
    `placement_status`   varchar(30)         NOT NULL DEFAULT 'SEARCHING',
    `accepting_facility` varchar(120)                 DEFAULT NULL,
    `accepted_datetime`  datetime                     DEFAULT NULL,
    `transport_method`   varchar(40)                  DEFAULT NULL,
    `transport_datetime` datetime                     DEFAULT NULL,
    `emtala_complete`    tinyint(1)          NOT NULL DEFAULT 0,
    `checklist_json`     mediumtext                   DEFAULT NULL,
    `notes`              varchar(255)                 DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_bh_episode` (`episode_id`),
    KEY `idx_oei_bh_facility` (`facility_id`, `placement_status`, `updated_datetime`),
    KEY `idx_oei_bh_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_bh_safety`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `observation_level`  varchar(20)         NOT NULL DEFAULT 'NONE',
    `is_involuntary`     tinyint(1)          NOT NULL DEFAULT 0,
    `risk_violence`      tinyint(1)          NOT NULL DEFAULT 0,
    `risk_suicide`       tinyint(1)          NOT NULL DEFAULT 0,
    `elopement_risk`     tinyint(1)          NOT NULL DEFAULT 0,
    `precautions_json`   mediumtext                   DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_bh_episode` (`episode_id`),
    KEY `idx_oei_bh_facility` (`facility_id`, `observation_level`),
    KEY `idx_oei_bh_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_episode`
(
    `id`                        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pid`                       bigint(20) UNSIGNED NOT NULL,
    `eid`                       bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`               bigint(20) UNSIGNED NOT NULL,
    `type`                      varchar(10)         NOT NULL DEFAULT 'ED',
    `start_datetime`            datetime            NOT NULL,
    `end_datetime`              datetime                     DEFAULT NULL,
    `disposition`               varchar(20)                  DEFAULT NULL,
    `status`                    varchar(20)         NOT NULL DEFAULT 'ACTIVE',
    `chief_complaint`           varchar(255)                 DEFAULT NULL,
    `acuity_esi`                tinyint(3) UNSIGNED          DEFAULT NULL,
    `provider_user_id`          bigint(20) UNSIGNED          DEFAULT NULL,
    `triage_completed_datetime` datetime                     DEFAULT NULL,
    `last_status_update`        datetime                     DEFAULT NULL,
    `arrival_mode`              varchar(30)                  DEFAULT NULL,
    `triage_datetime`           datetime                     DEFAULT NULL,
    `triage_note`               varchar(255)                 DEFAULT NULL,
    `created_by_user_id`        bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`          datetime                     DEFAULT NULL,
    `assigned_nurse_user_id`    int(11)                      DEFAULT NULL,
    `assigned_provider_user_id` int(11)                      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_episode_active` (`facility_id`, `status`, `start_datetime`),
    KEY `idx_oei_episode_pid` (`pid`),
    KEY `idx_oei_episode_eid` (`eid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_episode_disposition`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `disposition_code`   varchar(30)         NOT NULL,
    `destination`        varchar(60)                  DEFAULT NULL,
    `decision_datetime`  datetime                     DEFAULT NULL,
    `depart_datetime`    datetime                     DEFAULT NULL,
    `admit_flag`         tinyint(1)          NOT NULL DEFAULT 0,
    `notes`              varchar(255)                 DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_disp_episode` (`episode_id`),
    KEY `idx_oei_disp_facility` (`facility_id`, `disposition_code`, `updated_datetime`),
    KEY `idx_oei_disp_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_episode_document`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED NOT NULL,
    `pid`                 bigint(20) UNSIGNED NOT NULL,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `doc_type`            varchar(40)         NOT NULL DEFAULT 'GENERAL' COMMENT 'GENERAL|TRANSFER_PACKET|PHYSICIAN_ORDER|LAB|IMAGING|CONSENT|ID|INSURANCE|OTHER',
    `label`               varchar(180)        NOT NULL COMMENT 'User-visible name',
    `original_name`       varchar(255)        NOT NULL COMMENT 'Original uploaded filename',
    `mime_type`           varchar(100)        NOT NULL DEFAULT 'application/octet-stream',
    `file_size`           int(10) UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Bytes',
    `storage_path`        varchar(500)        NOT NULL COMMENT 'Path relative to OE site document root',
    `uploaded_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `uploaded_datetime`   datetime            NOT NULL,
    `notes`               varchar(255)                 DEFAULT NULL,
    `is_deleted`          tinyint(1)          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_oei_doc_episode` (`episode_id`, `is_deleted`, `uploaded_datetime`),
    KEY `idx_oei_doc_facility` (`facility_id`, `doc_type`, `uploaded_datetime`),
    KEY `idx_oei_doc_pid` (`pid`, `uploaded_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Episode-linked document attachments';

CREATE TABLE IF NOT EXISTS `oei_episode_event`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `event_type`     varchar(40)         NOT NULL,
    `event_datetime` datetime            NOT NULL,
    `user_id`        bigint(20) UNSIGNED DEFAULT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_evt_episode` (`episode_id`, `event_type`, `event_datetime`),
    KEY `idx_oei_evt_facility` (`facility_id`, `event_type`, `event_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_episode_location`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `location_id`    bigint(20) UNSIGNED DEFAULT NULL,
    `location_code`  varchar(20)         DEFAULT NULL,
    `start_datetime` datetime            NOT NULL,
    `end_datetime`   datetime            DEFAULT NULL,
    `user_id`        bigint(20) UNSIGNED DEFAULT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_ep_loc_episode` (`episode_id`, `start_datetime`),
    KEY `idx_oei_ep_loc_facility` (`facility_id`, `start_datetime`),
    KEY `idx_oei_ep_loc_active` (`facility_id`, `end_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_episode_status_history`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `status_code`    varchar(30)         NOT NULL,
    `set_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `set_datetime`   datetime            NOT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_episode_status_episode` (`episode_id`, `set_datetime`),
    KEY `idx_oei_episode_status_code` (`status_code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_ereferral`
(
    `id`                       int(10) UNSIGNED                                        NOT NULL AUTO_INCREMENT,
    `episode_id`               int(10) UNSIGNED                                        NOT NULL,
    `pid`                      bigint(20)                                              NOT NULL,
    `eid`                      bigint(20)                                                       DEFAULT NULL,
    `facility_id`              int(10) UNSIGNED                                        NOT NULL,
    `referral_type`            enum ('DISCHARGE','TRANSFER','BH_PLACEMENT')            NOT NULL DEFAULT 'DISCHARGE',
    `status`                   enum ('DRAFT','SENT','ACCEPTED','DECLINED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    `priority`                 enum ('ROUTINE','URGENT','EMERGENT')                    NOT NULL DEFAULT 'ROUTINE',
    `destination_directory_id` int(10) UNSIGNED                                                 DEFAULT NULL,
    `destination_name`         varchar(200)                                                     DEFAULT NULL,
    `destination_fax`          varchar(30)                                                      DEFAULT NULL,
    `destination_phone`        varchar(30)                                                      DEFAULT NULL,
    `destination_address`      text                                                             DEFAULT NULL,
    `reason_for_referral`      text                                                             DEFAULT NULL,
    `clinical_summary`         text                                                             DEFAULT NULL,
    `services_requested`       text                                                             DEFAULT NULL,
    `medications_summary`      text                                                             DEFAULT NULL,
    `followup_instructions`    text                                                             DEFAULT NULL,
    `sent_datetime`            datetime                                                         DEFAULT NULL,
    `sent_by_user_id`          int(11)                                                          DEFAULT NULL,
    `send_method`              enum ('MANUAL','FAX','DIRECT','PRINT')                  NOT NULL DEFAULT 'MANUAL',
    `response_datetime`        datetime                                                         DEFAULT NULL,
    `response_by_name`         varchar(120)                                                     DEFAULT NULL,
    `response_notes`           text                                                             DEFAULT NULL,
    `created_by_user_id`       int(11)                                                          DEFAULT NULL,
    `created_datetime`         datetime                                                NOT NULL,
    `updated_datetime`         datetime                                                NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ereferral_episode` (`episode_id`),
    KEY `idx_ereferral_facility` (`facility_id`),
    KEY `idx_ereferral_status` (`status`),
    KEY `idx_ereferral_pid` (`pid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_facility_directory`
(
    `id`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`  bigint(20) UNSIGNED NOT NULL,
    `name`         varchar(120)        NOT NULL,
    `service_type` varchar(40)         NOT NULL DEFAULT 'GENERAL',
    `phone`        varchar(30)                  DEFAULT NULL,
    `fax`          varchar(30)                  DEFAULT NULL,
    `email`        varchar(120)                 DEFAULT NULL,
    `address`      varchar(255)                 DEFAULT NULL,
    `hours`        varchar(80)                  DEFAULT NULL,
    `notes`        varchar(255)                 DEFAULT NULL,
    `is_active`    tinyint(1)          NOT NULL DEFAULT 1,
    `sort_order`   int(11)             NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dir_fac_active` (`facility_id`, `is_active`, `sort_order`),
    KEY `idx_oei_dir_service` (`facility_id`, `service_type`, `is_active`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_hl7_outbound_log`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `event_type`     varchar(4)          NOT NULL COMMENT 'A01|A02|A03|A04|A08',
    `transport_type` varchar(10)         NOT NULL COMMENT 'MLLP|HTTP|INTERNAL',
    `endpoint`       varchar(500)        NOT NULL,
    `message_body`   mediumtext          NOT NULL,
    `ack_body`       mediumtext DEFAULT NULL,
    `status`         varchar(10)         NOT NULL COMMENT 'SENT|NACK|ERROR',
    `error_message`  text       DEFAULT NULL,
    `sent_datetime`  datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_hl7_log_facility` (`facility_id`, `sent_datetime`),
    KEY `idx_hl7_log_episode` (`episode_id`),
    KEY `idx_hl7_log_status` (`facility_id`, `status`, `sent_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='HL7 v2 ADT outbound message log';

CREATE TABLE IF NOT EXISTS `oei_location`
(
    `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`   bigint(20) UNSIGNED NOT NULL,
    `code`          varchar(20)         NOT NULL,
    `name`          varchar(80)         NOT NULL,
    `location_type` varchar(20)         NOT NULL DEFAULT 'ROOM',
    `status`        varchar(20)         NOT NULL DEFAULT 'AVAILABLE',
    `unit_name`     varchar(40)                  DEFAULT NULL,
    `is_active`     tinyint(1)          NOT NULL DEFAULT 1,
    `sort_order`    int(11)             NOT NULL DEFAULT 0,
    `notes`         varchar(255)                 DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_loc_fac_code` (`facility_id`, `code`),
    KEY `idx_oei_loc_fac_active` (`facility_id`, `is_active`, `sort_order`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_mar_administration`
(
    `id`                      int(10) UNSIGNED                                                   NOT NULL AUTO_INCREMENT,
    `mar_order_id`            int(10) UNSIGNED                                                   NOT NULL,
    `episode_id`              int(10) UNSIGNED                                                   NOT NULL,
    `pid`                     bigint(20)                                                         NOT NULL,
    `facility_id`             int(10) UNSIGNED                                                   NOT NULL,
    `scheduled_datetime`      datetime                                                                    DEFAULT NULL,
    `administered_datetime`   datetime                                                                    DEFAULT NULL,
    `outcome`                 enum ('PENDING','GIVEN','HELD','REFUSED','NOT_AVAILABLE','MISSED') NOT NULL DEFAULT 'PENDING',
    `dose_given`              varchar(80)                                                                 DEFAULT NULL,
    `unit_given`              varchar(30)                                                                 DEFAULT NULL,
    `route_given`             varchar(60)                                                                 DEFAULT NULL,
    `site`                    varchar(80)                                                                 DEFAULT NULL,
    `lot_number`              varchar(60)                                                                 DEFAULT NULL,
    `administered_by_user_id` int(11)                                                                     DEFAULT NULL,
    `note`                    text                                                                        DEFAULT NULL,
    `is_high_alert`           tinyint(1)                                                         NOT NULL DEFAULT 0,
    `created_datetime`        datetime                                                           NOT NULL,
    `updated_datetime`        datetime                                                           NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mar_admin_order` (`mar_order_id`),
    KEY `idx_mar_admin_episode` (`episode_id`),
    KEY `idx_mar_admin_facility` (`facility_id`),
    KEY `idx_mar_admin_outcome` (`outcome`),
    KEY `idx_mar_admin_scheduled` (`scheduled_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_mar_order`
(
    `id`                      int(10) UNSIGNED                           NOT NULL AUTO_INCREMENT,
    `episode_id`              int(10) UNSIGNED                           NOT NULL,
    `pid`                     bigint(20)                                 NOT NULL,
    `facility_id`             int(10) UNSIGNED                           NOT NULL,
    `drug_name`               varchar(200)                               NOT NULL,
    `dose`                    varchar(80)                                NOT NULL,
    `unit`                    varchar(30)                                NOT NULL DEFAULT '',
    `route`                   varchar(60)                                NOT NULL DEFAULT '',
    `frequency`               varchar(80)                                NOT NULL DEFAULT '',
    `is_prn`                  tinyint(1)                                 NOT NULL DEFAULT 0,
    `status`                  enum ('ACTIVE','DISCONTINUED','COMPLETED') NOT NULL DEFAULT 'ACTIVE',
    `ordered_datetime`        datetime                                   NOT NULL,
    `discontinued_datetime`   datetime                                            DEFAULT NULL,
    `ordered_by_user_id`      int(11)                                             DEFAULT NULL,
    `discontinued_by_user_id` int(11)                                             DEFAULT NULL,
    `rx_id`                   int(11)                                             DEFAULT NULL,
    `instructions`            text                                                DEFAULT NULL,
    `created_datetime`        datetime                                   NOT NULL,
    `updated_datetime`        datetime                                   NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mar_order_episode` (`episode_id`),
    KEY `idx_mar_order_facility` (`facility_id`),
    KEY `idx_mar_order_status` (`status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_obs_plan`
(
    `id`                 bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED  NOT NULL,
    `pid`                bigint(20) UNSIGNED  NOT NULL,
    `eid`                bigint(20) UNSIGNED           DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED  NOT NULL,
    `protocol_key`       varchar(80)          NOT NULL,
    `status`             varchar(20)          NOT NULL DEFAULT 'ACTIVE',
    `start_datetime`     datetime             NOT NULL,
    `target_hours`       smallint(5) UNSIGNED NOT NULL DEFAULT 24,
    `runway_hours`       smallint(5) UNSIGNED NOT NULL DEFAULT 6,
    `protocol_json`      mediumtext           NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED           DEFAULT NULL,
    `updated_datetime`   datetime             NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_obs_episode` (`episode_id`),
    KEY `idx_oei_obs_active` (`facility_id`, `status`, `start_datetime`),
    KEY `idx_oei_obs_protocol` (`facility_id`, `protocol_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_patient_location_history`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `episode_id`     bigint(20) UNSIGNED DEFAULT NULL,
    `location_id`    bigint(20) UNSIGNED DEFAULT NULL,
    `start_datetime` datetime            NOT NULL,
    `end_datetime`   datetime            DEFAULT NULL,
    `reason`         varchar(30)         NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_loc_hist_pid` (`pid`, `start_datetime`),
    KEY `idx_oei_loc_hist_episode` (`episode_id`, `start_datetime`),
    KEY `idx_oei_loc_hist_location` (`location_id`, `start_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_protocol`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `protocol_key`       varchar(80)         NOT NULL,
    `label`              varchar(255)        NOT NULL,
    `version`            varchar(20)         NOT NULL DEFAULT '1',
    `enabled`            tinyint(1)          NOT NULL DEFAULT 1,
    `definition_json`    mediumtext          NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_protocol` (`facility_id`, `protocol_key`),
    KEY `idx_oei_protocol_enabled` (`facility_id`, `enabled`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_schema_version`
(
    `version`          varchar(20) NOT NULL,
    `applied_datetime` datetime    NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_settings`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `setting_key`        varchar(80)         NOT NULL,
    `setting_value`      text                NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_settings` (`facility_id`, `setting_key`),
    KEY `idx_oei_settings_fac` (`facility_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_task`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED NOT NULL,
    `pid`                 bigint(20) UNSIGNED NOT NULL,
    `eid`                 bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `task_type`           varchar(50)         NOT NULL,
    `due_datetime`        datetime            NOT NULL,
    `completed_datetime`  datetime                     DEFAULT NULL,
    `assigned_to_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `status`              varchar(20)         NOT NULL DEFAULT 'OPEN',
    `payload_json`        mediumtext                   DEFAULT NULL,
    `created_by_user_id`  bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`    datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_task_due` (`facility_id`, `status`, `due_datetime`),
    KEY `idx_oei_task_episode` (`episode_id`, `status`, `due_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_transfer`
(
    `id`                     bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`             bigint(20) UNSIGNED NOT NULL,
    `pid`                    bigint(20) UNSIGNED NOT NULL,
    `eid`                    bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`            bigint(20) UNSIGNED NOT NULL,
    `transfer_type`          varchar(30)         NOT NULL DEFAULT 'TRANSFER',
    `reason`                 varchar(120)                 DEFAULT NULL,
    `receiving_directory_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `receiving_name`         varchar(120)                 DEFAULT NULL,
    `requested_datetime`     datetime                     DEFAULT NULL,
    `accepted_datetime`      datetime                     DEFAULT NULL,
    `transport_datetime`     datetime                     DEFAULT NULL,
    `status`                 varchar(30)         NOT NULL DEFAULT 'PENDING',
    `checklist_json`         mediumtext                   DEFAULT NULL,
    `notes`                  varchar(255)                 DEFAULT NULL,
    `updated_by_user_id`     bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`       datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_transfer_episode` (`episode_id`),
    KEY `idx_oei_transfer_fac_status` (`facility_id`, `status`, `updated_datetime`),
    KEY `idx_oei_transfer_fac_time` (`facility_id`, `requested_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_triage`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED NOT NULL,
    `pid`              bigint(20) UNSIGNED NOT NULL,
    `eid`              bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `set_number`       tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=initial triage, 2=re-triage, etc.',
    `bp_systolic`      smallint(5) UNSIGNED         DEFAULT NULL,
    `bp_diastolic`     smallint(5) UNSIGNED         DEFAULT NULL,
    `hr`               smallint(5) UNSIGNED         DEFAULT NULL COMMENT 'bpm',
    `rr`               tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'breaths per minute',
    `temp_f`           decimal(5, 2)                DEFAULT NULL COMMENT 'degrees Fahrenheit',
    `spo2`             tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'percent 0-100',
    `gcs`              tinyint(3) UNSIGNED          DEFAULT NULL COMMENT '3-15',
    `pain_score`       tinyint(3) UNSIGNED          DEFAULT NULL COMMENT '0-10',
    `weight_kg`        decimal(6, 2)                DEFAULT NULL,
    `arrival_mode`     varchar(20)                  DEFAULT NULL COMMENT 'WALKIN|EMS|TRANSFER|POLICE|WHEELCHAIR|STRETCHER',
    `esi_suggested`    tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'auto-computed from vitals 1-5',
    `notes`            text                         DEFAULT NULL,
    `noted_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `noted_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_triage_episode` (`episode_id`),
    KEY `idx_oei_triage_facility` (`facility_id`, `noted_datetime`),
    KEY `idx_oei_triage_pid` (`pid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Triage vitals sets; multiple per episode for re-triage support';

-- ============================================================
-- oei_user_context  —  per-user care context preference
-- Created by oe-module-institutional v0.9.7
--
-- One row per (user_id, facility_id).
-- context_key: ED_ACUTE | OBS_STAY | BH | OPERATIONS | FULL
-- ============================================================

CREATE TABLE IF NOT EXISTS `oei_user_context`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `context_key`      varchar(30)         NOT NULL DEFAULT 'ED_ACUTE',
    `updated_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ctx_user_fac` (`user_id`, `facility_id`),
    KEY `idx_oei_ctx_facility` (`facility_id`, `context_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
    COMMENT = 'Per-user care context preference per facility';

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;


-- Offline write queue: captures new arrivals / vitals / status notes
-- entered while the network was unavailable.
-- Each row is processed once and then status set to SYNCED or FAILED.
CREATE TABLE IF NOT EXISTS `oei_downtime_sync_queue`
(
    `id`                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`          bigint(20) UNSIGNED NOT NULL,
    `entry_type`           varchar(30)         NOT NULL
        COMMENT 'ARRIVAL | VITALS | STATUS_NOTE | TASK_NOTE',
    `payload_json`         mediumtext          NOT NULL
        COMMENT 'Raw JSON captured by browser',
    `captured_client`      datetime            NOT NULL
        COMMENT 'Client-side timestamp from the browser',
    `queued_datetime`      datetime            NOT NULL
        COMMENT 'Server-receipt datetime on sync POST',
    `synced_datetime`      datetime                     DEFAULT NULL,
    `status`               varchar(20)         NOT NULL DEFAULT 'PENDING'
        COMMENT 'PENDING | SYNCED | FAILED | SKIPPED',
    `result_note`          varchar(255)                 DEFAULT NULL
        COMMENT 'Error message or resultant ID on success',
    `submitted_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dt_queue_facility` (`facility_id`, `status`, `queued_datetime`),
    KEY `idx_oei_dt_queue_type` (`entry_type`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
    COMMENT = 'Offline write queue — rows written by browser during network outage';

-- Current diversion status per facility × service line
-- One row per (facility_id, service_line) — updated in-place, history preserved below.
CREATE TABLE IF NOT EXISTS `oei_diversion`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `service_line`       varchar(40)         NOT NULL DEFAULT 'ED'
        COMMENT 'ED | ICU | OBS | PSYCH | TRAUMA | PEDS | BURN',
    `status`             varchar(20)         NOT NULL DEFAULT 'OPEN'
        COMMENT 'OPEN | DIVERSION | LIMITED | BYPASS',
    `reason`             varchar(255)                 DEFAULT NULL
        COMMENT 'Free-text reason shown in facility directory',
    `diversion_start`    datetime                     DEFAULT NULL,
    `diversion_end`      datetime                     DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_diversion_service` (`facility_id`, `service_line`),
    KEY `idx_oei_diversion_facility` (`facility_id`, `status`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
    COMMENT = 'Current diversion status per facility and service line';

-- Full audit trail — every status change is appended here.
CREATE TABLE IF NOT EXISTS `oei_diversion_history`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `service_line`       varchar(40)         NOT NULL,
    `previous_status`    varchar(20)         DEFAULT NULL,
    `new_status`         varchar(20)         NOT NULL,
    `reason`             varchar(255)        DEFAULT NULL,
    `diversion_start`    datetime            DEFAULT NULL,
    `diversion_end`      datetime            DEFAULT NULL,
    `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `changed_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_div_hist_facility` (`facility_id`, `service_line`, `changed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
    COMMENT = 'Audit log of all diversion status changes';

ALTER TABLE `oei_mar_administration`
    ADD COLUMN IF NOT EXISTS `hold_reason` varchar(60) DEFAULT NULL
        COMMENT 'Structured HELD reason code — see MarService::HOLD_REASONS'
        AFTER `lot_number`;


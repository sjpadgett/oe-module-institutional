SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `openemr`
--

-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oei_activity_log`
(
    `id`               bigint(20) UNSIGNED                                NOT NULL AUTO_INCREMENT,
    `facility_id`      bigint(20) UNSIGNED                                NOT NULL,
    `activity_date`    date                                               NOT NULL,
    `activity_type`    varchar(40)                                        NOT NULL COMMENT 'SOCIAL_GROUP|MUSIC|EXERCISE|COGNITIVE|OUTDOOR|DEVOTIONAL|CRAFT|INDIVIDUAL_VISIT|DINING_SOCIAL|THERAPY_PT|THERAPY_OT|THERAPY_ST|OTHER',
    `activity_name`    varchar(120)                                       NOT NULL COMMENT 'Specific name, e.g. "Morning Stretch", "Bingo", "Guitar Singalong"',
    `start_time`       time                                               NOT NULL,
    `duration_minutes` smallint(5) UNSIGNED                               NOT NULL DEFAULT 30,
    `location`         varchar(60)                                                 DEFAULT NULL COMMENT 'e.g. "Community Room", "Courtyard", "Room 202"',
    `led_by_user_id`   bigint(20) UNSIGNED                                         DEFAULT NULL COMMENT 'FK → users.id (activity coordinator / aide)',
    `led_by_name`      varchar(80)                                                 DEFAULT NULL COMMENT 'Denormalised name for display when user is no longer active',
    `attendance_json`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_object() COMMENT 'episode_id → {level, note} participation map' CHECK (json_valid(`attendance_json`)),
    `attendance_count` tinyint(3) UNSIGNED                                NOT NULL DEFAULT 0 COMMENT 'Cached count of FULL+PARTIAL attendees for fast board display',
    `notes`            text                                                        DEFAULT NULL COMMENT 'Session-level notes (themes, outcomes, observations)',
    `created_datetime` datetime                                           NOT NULL,
    `updated_datetime` datetime                                           NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_activity_facility_date` (`facility_id`, `activity_date`),
    KEY `idx_oei_activity_type` (`facility_id`, `activity_type`, `activity_date`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL activity & engagement session log';

CREATE TABLE IF NOT EXISTS `oei_adl_record`
(
    `id`               bigint(20) UNSIGNED                                NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED                                NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`      bigint(20) UNSIGNED                                NOT NULL,
    `noted_by_user_id` bigint(20) UNSIGNED                                         DEFAULT NULL COMMENT 'FK → users.id (aide/nurse)',
    `noted_datetime`   datetime                                           NOT NULL,
    `adl_json`         longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'domain→level map, e.g. {"bathing":2,"dressing":1,...}' CHECK (json_valid(`adl_json`)),
    `adl_score`        tinyint(3) UNSIGNED                                NOT NULL DEFAULT 0 COMMENT 'Aggregate 0–28; see AdlLevel::aggregateScore()',
    `notes`            text                                                        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_adl_episode` (`episode_id`, `noted_datetime`),
    KEY `idx_oei_adl_facility` (`facility_id`, `noted_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='ADL charting sessions; one row per aide session covering all 7 domains';

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

CREATE TABLE IF NOT EXISTS `oei_al_episode`
(
    `id`                bigint(20) UNSIGNED               NOT NULL AUTO_INCREMENT,
    `episode_id`        bigint(20) UNSIGNED               NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`               bigint(20) UNSIGNED               NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`       bigint(20) UNSIGNED               NOT NULL,
    `encounter_id`      bigint(20) UNSIGNED                        DEFAULT NULL COMMENT 'FK → form_encounter.id — anchors form_care_plan entries',
    `room`              varchar(20)                                DEFAULT NULL,
    `unit`              varchar(40)                                DEFAULT NULL,
    `care_level`        enum ('TIER_1','TIER_2','TIER_3') NOT NULL DEFAULT 'TIER_1' COMMENT 'CareLevel domain: Low / Medium / High',
    `fall_risk_level`   enum ('LOW','MODERATE','HIGH')    NOT NULL DEFAULT 'LOW' COMMENT 'Morse Fall Scale tier',
    `fall_risk_score`   tinyint(3) UNSIGNED               NOT NULL DEFAULT 0 COMMENT 'Raw Morse Fall Scale total (0-125)',
    `admit_reason`      varchar(255)                               DEFAULT NULL,
    `last_adl_score`    tinyint(3) UNSIGNED                        DEFAULT NULL COMMENT 'Cached aggregate ADL score from latest oei_adl_record',
    `last_adl_datetime` datetime                                   DEFAULT NULL,
    `created_datetime`  datetime                          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_al_episode` (`episode_id`),
    KEY `idx_oei_al_facility` (`facility_id`, `care_level`),
    KEY `idx_oei_al_room` (`facility_id`, `unit`, `room`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL-specific overlay on oei_episode';

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

CREATE TABLE IF NOT EXISTS `oei_billing_line`
(
    `id`                  bigint(20) UNSIGNED                                                              NOT NULL AUTO_INCREMENT,
    `facility_id`         bigint(20) UNSIGNED                                                              NOT NULL,
    `episode_id`          bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `pid`                 bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `eid`                 bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `context_key`         varchar(30)                                                                               DEFAULT NULL,
    `episode_type`        varchar(10)                                                                               DEFAULT NULL,
    `billing_path`        enum ('CLAIM_MANAGER','MODULE_LEDGER','PROFESSIONAL_REVIEW')                     NOT NULL DEFAULT 'MODULE_LEDGER',
    `line_category`       enum ('PRIVATE_PAY','RECURRING','SERVICE','SUPPLY','ADJUSTMENT','CLAIM_STAGING') NOT NULL DEFAULT 'SERVICE',
    `status`              enum ('DRAFT','READY','HOLD','RELEASED','VOID')                                  NOT NULL DEFAULT 'DRAFT',
    `review_reason`       varchar(120)                                                                              DEFAULT NULL,
    `service_date`        date                                                                             NOT NULL,
    `charge_code`         varchar(40)                                                                               DEFAULT NULL,
    `description`         varchar(255)                                                                     NOT NULL,
    `quantity`            decimal(10, 2)                                                                   NOT NULL DEFAULT 1.00,
    `unit_price`          decimal(12, 2)                                                                   NOT NULL DEFAULT 0.00,
    `total_amount`        decimal(12, 2)                                                                   NOT NULL DEFAULT 0.00,
    `external_ref`        varchar(80)                                                                               DEFAULT NULL,
    `source_label`        varchar(80)                                                                               DEFAULT NULL,
    `notes`               text                                                                                      DEFAULT NULL,
    `release_target`      enum ('BILLING_MANAGER','UB04','PROFESSIONAL','LEDGER','STATEMENT')                       DEFAULT NULL,
    `release_batch_key`   varchar(40)                                                                               DEFAULT NULL,
    `created_by_user_id`  bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `updated_by_user_id`  bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `released_by_user_id` bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `created_datetime`    datetime                                                                         NOT NULL,
    `updated_datetime`    datetime                                                                         NOT NULL,
    `released_datetime`   datetime                                                                                  DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_bill_facility_status` (`facility_id`, `status`, `service_date`),
    KEY `idx_oei_bill_episode` (`episode_id`, `service_date`),
    KEY `idx_oei_bill_pid` (`pid`, `service_date`),
    KEY `idx_oei_bill_path` (`facility_id`, `billing_path`, `service_date`),
    KEY `idx_oei_bill_release` (`facility_id`, `release_target`, `status`, `service_date`),
    KEY `idx_oei_bill_batch` (`facility_id`, `release_batch_key`, `released_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Institutional billing ledger and billing-workbench staging lines';

CREATE TABLE IF NOT EXISTS `oei_diversion`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `service_line`       varchar(40)         NOT NULL DEFAULT 'ED' COMMENT 'ED | ICU | OBS | PSYCH | TRAUMA | PEDS | BURN',
    `status`             varchar(20)         NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN | DIVERSION | LIMITED | BYPASS',
    `reason`             varchar(255)                 DEFAULT NULL COMMENT 'Free-text reason shown in facility directory',
    `diversion_start`    datetime                     DEFAULT NULL,
    `diversion_end`      datetime                     DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_diversion_service` (`facility_id`, `service_line`),
    KEY `idx_oei_diversion_facility` (`facility_id`, `status`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Current diversion status per facility and service line';

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
  COLLATE = utf8mb4_general_ci COMMENT ='Audit log of all diversion status changes';

CREATE TABLE IF NOT EXISTS `oei_downtime_sync_queue`
(
    `id`                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`          bigint(20) UNSIGNED NOT NULL,
    `entry_type`           varchar(30)         NOT NULL COMMENT 'ARRIVAL | VITALS | STATUS_NOTE | TASK_NOTE',
    `payload_json`         mediumtext          NOT NULL COMMENT 'Raw JSON captured by browser',
    `captured_client`      datetime            NOT NULL COMMENT 'Client-side timestamp from the browser',
    `queued_datetime`      datetime            NOT NULL COMMENT 'Server-receipt datetime on sync POST',
    `synced_datetime`      datetime                     DEFAULT NULL,
    `status`               varchar(20)         NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING | SYNCED | FAILED | SKIPPED',
    `result_note`          varchar(255)                 DEFAULT NULL COMMENT 'Error message or resultant ID on success',
    `submitted_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dt_queue_facility` (`facility_id`, `status`, `queued_datetime`),
    KEY `idx_oei_dt_queue_type` (`entry_type`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Offline write queue — rows written by browser during network outage';

CREATE TABLE IF NOT EXISTS `oei_episode`
(
    `id`                        bigint(20) UNSIGNED                    NOT NULL AUTO_INCREMENT,
    `pid`                       bigint(20) UNSIGNED                    NOT NULL,
    `eid`                       bigint(20) UNSIGNED                             DEFAULT NULL,
    `facility_id`               bigint(20) UNSIGNED                    NOT NULL,
    `type`                      enum ('ED','OBS','BH','AL','IP','HBC') NOT NULL DEFAULT 'ED' COMMENT 'Care setting type — HBC = Home-Based Care',
    `start_datetime`            datetime                               NOT NULL,
    `end_datetime`              datetime                                        DEFAULT NULL,
    `disposition`               varchar(20)                                     DEFAULT NULL,
    `status`                    varchar(20)                            NOT NULL DEFAULT 'ACTIVE',
    `chief_complaint`           varchar(255)                                    DEFAULT NULL,
    `acuity_esi`                tinyint(3) UNSIGNED                             DEFAULT NULL,
    `provider_user_id`          bigint(20) UNSIGNED                             DEFAULT NULL,
    `triage_completed_datetime` datetime                                        DEFAULT NULL,
    `last_status_update`        datetime                                        DEFAULT NULL,
    `arrival_mode`              varchar(30)                                     DEFAULT NULL,
    `triage_datetime`           datetime                                        DEFAULT NULL,
    `triage_note`               varchar(255)                                    DEFAULT NULL,
    `created_by_user_id`        bigint(20) UNSIGNED                             DEFAULT NULL,
    `created_datetime`          datetime                                        DEFAULT NULL,
    `assigned_nurse_user_id`    int(11)                                         DEFAULT NULL,
    `assigned_provider_user_id` int(11)                                         DEFAULT NULL,
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

CREATE TABLE IF NOT EXISTS `oei_fall_risk_assessment`
(
    `id`                  bigint(20) UNSIGNED            NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED            NOT NULL,
    `facility_id`         bigint(20) UNSIGNED            NOT NULL,
    `assessed_by_user_id` bigint(20) UNSIGNED                     DEFAULT NULL,
    `assessed_datetime`   datetime                       NOT NULL,
    `mfs_fall_history`    tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 25=Yes',
    `mfs_secondary_dx`    tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 15=Yes',
    `mfs_ambulatory_aid`  tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=None/bed-rest/nurse, 15=Crutches/cane/walker, 30=Furniture',
    `mfs_iv_heparin_lock` tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 20=Yes',
    `mfs_gait`            tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=Normal/bedrest, 10=Weak, 20=Impaired',
    `mfs_mental_status`   tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=Knows own limits, 15=Forgets limitations',
    `total_score`         tinyint(3) UNSIGNED            NOT NULL COMMENT 'Sum of 6 items (0-125)',
    `risk_level`          enum ('LOW','MODERATE','HIGH') NOT NULL,
    `notes`               text                                    DEFAULT NULL,
    `created_datetime`    datetime                       NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_fra_episode` (`episode_id`),
    KEY `idx_fra_facility` (`facility_id`),
    KEY `idx_fra_datetime` (`assessed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='Morse Fall Scale reassessment history for AL episodes';

CREATE TABLE IF NOT EXISTS `oei_hbc_comm_log`
(
    `id`               bigint(20) UNSIGNED                                                                                                                       NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED                                                                                                                       NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`              bigint(20) UNSIGNED                                                                                                                       NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`      bigint(20) UNSIGNED                                                                                                                       NOT NULL,
    `comm_type`        enum ('PHONE_OUT','PHONE_IN','FAX','SECURE_MSG','IN_PERSON','OTHER')                                                                      NOT NULL DEFAULT 'PHONE_OUT',
    `contact_role`     enum ('PCP','SPECIALIST','PHARMACY','FAMILY','CAREGIVER','DME_SUPPLIER','PAYER','HOME_HEALTH_AGENCY','HOSPICE','SOCIAL_SERVICES','OTHER') NOT NULL DEFAULT 'OTHER',
    `contact_name`     varchar(120)                                                                                                                                       DEFAULT NULL,
    `contact_phone`    varchar(40)                                                                                                                                        DEFAULT NULL,
    `subject`          varchar(255)                                                                                                                                       DEFAULT NULL,
    `summary`          text                                                                                                                                               DEFAULT NULL,
    `outcome`          varchar(255)                                                                                                                                       DEFAULT NULL COMMENT 'Result: left voicemail, confirmed, order placed, etc.',
    `followup_needed`  tinyint(1)                                                                                                                                NOT NULL DEFAULT 0,
    `followup_note`    varchar(255)                                                                                                                                       DEFAULT NULL,
    `comm_datetime`    datetime                                                                                                                                  NOT NULL DEFAULT current_timestamp() COMMENT 'When the communication occurred',
    `user_id`          bigint(20) UNSIGNED                                                                                                                                DEFAULT NULL COMMENT 'FK → users.id — who logged it',
    `created_datetime` datetime                                                                                                                                  NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hcl_episode` (`episode_id`),
    KEY `idx_hcl_pid` (`pid`),
    KEY `idx_hcl_facility` (`facility_id`),
    KEY `idx_hcl_datetime` (`comm_datetime`),
    KEY `idx_hcl_followup` (`episode_id`, `followup_needed`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Home-Based Care communication log — calls, faxes, messages';

CREATE TABLE IF NOT EXISTS `oei_hbc_episode`
(
    `id`                         bigint(20) UNSIGNED                                             NOT NULL AUTO_INCREMENT,
    `episode_id`                 bigint(20) UNSIGNED                                             NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`                        bigint(20) UNSIGNED                                             NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`                bigint(20) UNSIGNED                                             NOT NULL,
    `encounter_id`               bigint(20) UNSIGNED                                                      DEFAULT NULL COMMENT 'OpenEMR encounter NUMBER (form_encounter.encounter) — anchors care plan entries',
    `referral_source`            varchar(120)                                                             DEFAULT NULL COMMENT 'Free text or coded: GP, Hospital, Self, Family, Agency, etc.',
    `referral_reason`            varchar(255)                                                             DEFAULT NULL,
    `referral_status`            enum ('NEW','TRIAGED','SCHEDULED','ACTIVE','CLOSED','DECLINED') NOT NULL DEFAULT 'NEW',
    `urgency`                    enum ('ROUTINE','URGENT','EMERGENT')                            NOT NULL DEFAULT 'ROUTINE',
    `referral_datetime`          datetime                                                                 DEFAULT NULL,
    `soc_datetime`               datetime                                                                 DEFAULT NULL COMMENT 'Start of Care — first clinical visit date',
    `service_address_line1`      varchar(120)                                                             DEFAULT NULL,
    `service_address_line2`      varchar(120)                                                             DEFAULT NULL,
    `service_city`               varchar(80)                                                              DEFAULT NULL,
    `service_state_province`     varchar(80)                                                              DEFAULT NULL,
    `service_postal_code`        varchar(20)                                                              DEFAULT NULL,
    `service_country`            varchar(60)                                                              DEFAULT NULL,
    `access_notes`               varchar(255)                                                             DEFAULT NULL COMMENT 'Gate code, parking, dog, key location, etc.',
    `caregiver_name`             varchar(120)                                                             DEFAULT NULL,
    `caregiver_phone`            varchar(40)                                                              DEFAULT NULL,
    `caregiver_relationship`     varchar(60)                                                              DEFAULT NULL COMMENT 'Spouse, Child, Friend, Home carer, etc.',
    `primary_clinician_user_id`  bigint(20) UNSIGNED                                                      DEFAULT NULL COMMENT 'FK → users.id',
    `primary_diagnosis`          varchar(255)                                                             DEFAULT NULL,
    `primary_icd10`              varchar(20)                                                              DEFAULT NULL,
    `payer_name`                 varchar(120)                                                             DEFAULT NULL,
    `authorization_notes`        text                                                                     DEFAULT NULL,
    `cert_period_start`          date                                                                     DEFAULT NULL,
    `cert_period_end`            date                                                                     DEFAULT NULL,
    `authorized_visits_per_week` smallint(5) UNSIGNED                                                     DEFAULT NULL COMMENT 'Payer-authorized visit frequency per week',
    `created_datetime`           datetime                                                        NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_hbc_episode` (`episode_id`),
    KEY `idx_hbc_pid` (`pid`),
    KEY `idx_hbc_facility` (`facility_id`),
    KEY `idx_hbc_clinician` (`primary_clinician_user_id`),
    KEY `idx_hbc_status` (`referral_status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Home-Based Care episode overlay — one row per HBC episode';

CREATE TABLE IF NOT EXISTS `oei_hbc_visit`
(
    `id`                         bigint(20) UNSIGNED                                                              NOT NULL AUTO_INCREMENT,
    `episode_id`                 bigint(20) UNSIGNED                                                              NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`                        bigint(20) UNSIGNED                                                              NOT NULL,
    `facility_id`                bigint(20) UNSIGNED                                                              NOT NULL,
    `visit_type`                 enum ('SN','PT','OT','ST','MSW','HHA','MD','OTHER')                              NOT NULL DEFAULT 'SN',
    `clinician_user_id`          bigint(20) UNSIGNED                                                                       DEFAULT NULL COMMENT 'FK → users.id',
    `scheduled_datetime`         datetime                                                                                  DEFAULT NULL,
    `window_start_datetime`      datetime                                                                                  DEFAULT NULL COMMENT 'Optional arrival window start',
    `window_end_datetime`        datetime                                                                                  DEFAULT NULL COMMENT 'Optional arrival window end',
    `route_sequence`             smallint(5) UNSIGNED                                                                      DEFAULT NULL COMMENT 'Optional daily route order',
    `travel_notes`               varchar(255)                                                                              DEFAULT NULL COMMENT 'Parking, gate, arrival preference, route notes',
    `is_supervisory`             tinyint(1)                                                                       NOT NULL DEFAULT 0 COMMENT '1 = this visit is a supervisory oversight visit (RN supervising HHA)',
    `actual_start_datetime`      datetime                                                                                  DEFAULT NULL,
    `actual_end_datetime`        datetime                                                                                  DEFAULT NULL,
    `status`                     enum ('SCHEDULED','EN_ROUTE','ARRIVED','COMPLETE','MISSED','REFUSED','CANCELED') NOT NULL DEFAULT 'SCHEDULED',
    `actual_lat`                 decimal(10, 7)                                                                            DEFAULT NULL COMMENT 'GPS lat at visit start — nullable, never required',
    `actual_lng`                 decimal(10, 7)                                                                            DEFAULT NULL COMMENT 'GPS lng at visit start — nullable, never required',
    `draft_data`                 text                                                                                      DEFAULT NULL COMMENT 'JSON — partial form data saved from mobile field (not yet finalised)',
    `is_draft`                   tinyint(1)                                                                       NOT NULL DEFAULT 0 COMMENT '1 = clinician saved draft from field; 0 = finalised or not started',
    `patient_signature_obtained` tinyint(1)                                                                       NOT NULL DEFAULT 0,
    `patient_signature_datetime` datetime                                                                                  DEFAULT NULL,
    `patient_signature_data`     mediumtext                                                                                DEFAULT NULL COMMENT 'Base64 PNG from canvas — stored here to keep visit record self-contained',
    `visit_note`                 text                                                                                      DEFAULT NULL,
    `outcome_summary`            varchar(255)                                                                              DEFAULT NULL,
    `mileage_miles`              decimal(6, 2)                                                                             DEFAULT NULL,
    `med_reconciliation_status`  enum ('NOT_DONE','NO_CHANGES','UPDATED','ISSUES_FOUND')                          NOT NULL DEFAULT 'NOT_DONE',
    `med_reconciliation_summary` text                                                                                      DEFAULT NULL,
    `wound_summary`              text                                                                                      DEFAULT NULL,
    `procedure_summary`          text                                                                                      DEFAULT NULL,
    `home_safety_summary`        text                                                                                      DEFAULT NULL,
    `care_coordination_needed`   tinyint(1)                                                                       NOT NULL DEFAULT 0,
    `care_coordination_summary`  text                                                                                      DEFAULT NULL,
    `followup_plan`              text                                                                                      DEFAULT NULL,
    `next_visit_due_date`        date                                                                                      DEFAULT NULL,
    `next_visit_type`            enum ('SN','PT','OT','ST','MSW','HHA','MD','OTHER')                                       DEFAULT NULL,
    `created_by_user_id`         bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `created_datetime`           datetime                                                                         NOT NULL DEFAULT current_timestamp(),
    `updated_datetime`           datetime                                                                         NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hbcv_episode` (`episode_id`),
    KEY `idx_hbcv_pid` (`pid`),
    KEY `idx_hbcv_clinician` (`clinician_user_id`),
    KEY `idx_hbcv_scheduled` (`scheduled_datetime`),
    KEY `idx_hbcv_status` (`status`),
    KEY `idx_hbcv_route` (`facility_id`, `route_sequence`, `scheduled_datetime`),
    KEY `idx_hbcv_followup` (`facility_id`, `next_visit_due_date`),
    KEY `idx_hbcv_facility_date` (`facility_id`, `scheduled_datetime`),
    KEY `idx_hbcv_supervisory` (`episode_id`, `is_supervisory`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Home-Based Care visit record — one row per clinical encounter';

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

CREATE TABLE IF NOT EXISTS `oei_incident`
(
    `id`                    bigint(20) UNSIGNED                        NOT NULL AUTO_INCREMENT,
    `episode_id`            bigint(20) UNSIGNED                        NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`           bigint(20) UNSIGNED                        NOT NULL,
    `reported_by_user_id`   bigint(20) UNSIGNED                                 DEFAULT NULL COMMENT 'FK → users.id',
    `incident_type`         varchar(30)                                NOT NULL COMMENT 'IncidentType constant: FALL|FALL_INJURY|ELOPEMENT|MED_ERROR|…',
    `severity`              enum ('LOW','MODERATE','HIGH','CRITICAL')  NOT NULL DEFAULT 'MODERATE',
    `incident_datetime`     datetime                                   NOT NULL COMMENT 'When the incident occurred',
    `location_description`  varchar(120)                                        DEFAULT NULL,
    `narrative`             text                                                DEFAULT NULL COMMENT 'Factual description of what happened',
    `corrective_action`     text                                                DEFAULT NULL COMMENT 'Immediate corrective action taken',
    `reported_state`        enum ('PENDING','REPORTED','NOT_REQUIRED') NOT NULL DEFAULT 'PENDING',
    `mandatory_report_sent` tinyint(1)                                 NOT NULL DEFAULT 0 COMMENT '1 = state notification filed',
    `created_datetime`      datetime                                   NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_incident_episode` (`episode_id`),
    KEY `idx_oei_incident_facility` (`facility_id`, `incident_datetime`),
    KEY `idx_oei_incident_type` (`facility_id`, `incident_type`, `severity`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL incident reports';

CREATE TABLE IF NOT EXISTS `oei_ip_episode`
(
    `id`                  int(10) UNSIGNED                                                                             NOT NULL AUTO_INCREMENT,
    `episode_id`          int(10) UNSIGNED                                                                             NOT NULL COMMENT 'FK → oei_episode.id (UNIQUE — one overlay per episode)',
    `pid`                 bigint(20)                                                                                   NOT NULL COMMENT 'FK → patient_data.pid (denormalised for fast census queries)',
    `facility_id`         int(10) UNSIGNED                                                                             NOT NULL,
    `encounter_id`        bigint(20)                                                                                            DEFAULT NULL COMMENT 'FK → form_encounter.id — anchors care plan and clinical notes',
    `bed`                 varchar(20)                                                                                           DEFAULT NULL COMMENT 'Bed identifier, e.g. "4B-201" or "ICU-3"',
    `unit`                varchar(60)                                                                                           DEFAULT NULL COMMENT 'Unit/floor name, e.g. "Medical/Surgical", "Telemetry"',
    `service`             enum ('MED_SURG','TELEMETRY','ORTHO','NEURO','OB','PEDS','ICU','ONCOLOGY','CARDIAC','OTHER') NOT NULL DEFAULT 'MED_SURG' COMMENT 'Service line — see HospitalService domain class',
    `admission_type`      enum ('ELECTIVE','URGENT','EMERGENCY','NEWBORN','TRAUMA')                                    NOT NULL DEFAULT 'ELECTIVE' COMMENT 'Admission type — see AdmissionType domain class',
    `attending_user_id`   int(11)                                                                                               DEFAULT NULL COMMENT 'FK → users.id (authorized=1) — attending physician',
    `admitting_diagnosis` varchar(255)                                                                                          DEFAULT NULL COMMENT 'Free-text admitting diagnosis description',
    `admitting_icd10`     varchar(20)                                                                                           DEFAULT NULL COMMENT 'ICD-10 code (optional)',
    `expected_los_days`   smallint(5) UNSIGNED                                                                                  DEFAULT NULL COMMENT 'Case manager target length of stay in days',
    `discharge_summary`   text                                                                                                  DEFAULT NULL COMMENT 'Clinical narrative summary written at discharge',
    `created_datetime`    datetime                                                                                     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ip_episode` (`episode_id`),
    KEY `idx_oei_ip_facility` (`facility_id`, `unit`, `bed`),
    KEY `idx_oei_ip_attending` (`attending_user_id`),
    KEY `idx_oei_ip_service` (`facility_id`, `service`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Inpatient Hospital Stay overlay on oei_episode';

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
    `hold_reason`             varchar(60)                                                                 DEFAULT NULL COMMENT 'Structured HELD reason code — see MarService::HOLD_REASONS',
    `administered_by_user_id` int(11)                                                                     DEFAULT NULL,
    `witness_user_id`         int(11)                                                                     DEFAULT NULL COMMENT 'User ID of waste witness (controlled substances)',
    `waste_amount`            varchar(20)                                                                 DEFAULT NULL COMMENT 'Amount wasted (drawn - administered)',
    `waste_unit`              varchar(20)                                                                 DEFAULT NULL COMMENT 'Unit of wasted amount (mg, ml, etc.)',
    `co_sign_user_id`         int(11)                                                                     DEFAULT NULL COMMENT 'Second nurse who co-signed this high-alert administration',
    `co_signed_datetime`      datetime                                                                    DEFAULT NULL COMMENT 'When the co-signature was recorded',
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
    `is_stat`                 tinyint(1)                                 NOT NULL DEFAULT 0,
    `is_high_alert`           tinyint(1)                                 NOT NULL DEFAULT 0 COMMENT 'Auto-detected from drug name keywords at order time',
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
    KEY `idx_mar_order_status` (`status`),
    KEY `idx_mar_order_stat` (`is_stat`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `oei_observation`
(
    `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`        bigint(20) UNSIGNED NOT NULL,
    `pid`               int(11) UNSIGNED    NOT NULL,
    `facility_id`       bigint(20) UNSIGNED NOT NULL,
    `obs_type_code`     varchar(40)         NOT NULL COMMENT 'FK to oei_obs_type.code',
    `observed_datetime` datetime            NOT NULL,
    `value_numeric`     decimal(12, 4)               DEFAULT NULL,
    `value_text`        varchar(255)                 DEFAULT NULL,
    `unit`              varchar(30)                  DEFAULT NULL,
    `source_type`       varchar(20)         NOT NULL DEFAULT 'MANUAL' COMMENT 'MANUAL | DEVICE | IMPORT | FHIR',
    `device_id`         varchar(80)                  DEFAULT NULL COMMENT 'Device identifier from source system',
    `fhir_id`           varchar(100)                 DEFAULT NULL COMMENT 'FHIR Observation.id for dedup on re-import',
    `is_flagged`        tinyint(1)          NOT NULL DEFAULT 0 COMMENT '1 = value outside obs_type alert bounds at write time',
    `noted_by_user_id`  bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`  datetime            NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`, `observed_datetime`),
    KEY `idx_oei_obs_episode` (`episode_id`, `observed_datetime`),
    KEY `idx_oei_obs_pid_type` (`pid`, `obs_type_code`, `observed_datetime`),
    KEY `idx_oei_obs_type` (`obs_type_code`, `facility_id`),
    KEY `idx_oei_obs_flagged` (`facility_id`, `is_flagged`, `observed_datetime`),
    KEY `idx_oei_obs_fhir` (`fhir_id`(40), `observed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='High-frequency and device-originated clinical observations'
    PARTITION BY RANGE (to_days(`observed_datetime`))
        (
        PARTITION p_2024_h1 VALUES LESS THAN (739433) ENGINE =InnoDB,
        PARTITION p_2024_h2 VALUES LESS THAN (739617) ENGINE =InnoDB,
        PARTITION p_2025_q1 VALUES LESS THAN (739707) ENGINE =InnoDB,
        PARTITION p_2025_q2 VALUES LESS THAN (739798) ENGINE =InnoDB,
        PARTITION p_2025_q3 VALUES LESS THAN (739890) ENGINE =InnoDB,
        PARTITION p_2025_q4 VALUES LESS THAN (739982) ENGINE =InnoDB,
        PARTITION p_2026_q1 VALUES LESS THAN (740072) ENGINE =InnoDB,
        PARTITION p_2026_q2 VALUES LESS THAN (740163) ENGINE =InnoDB,
        PARTITION p_2026_q3 VALUES LESS THAN (740255) ENGINE =InnoDB,
        PARTITION p_2026_q4 VALUES LESS THAN (740347) ENGINE =InnoDB,
        PARTITION p_future VALUES LESS THAN MAXVALUE ENGINE =InnoDB
        );

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

CREATE TABLE IF NOT EXISTS `oei_obs_type`
(
    `code`         varchar(40)  NOT NULL,
    `display_name` varchar(100) NOT NULL,
    `loinc_code`   varchar(20)           DEFAULT NULL COMMENT 'LOINC code for FHIR interoperability',
    `category`     varchar(30)  NOT NULL DEFAULT 'vital-signs' COMMENT 'FHIR Observation category: vital-signs | laboratory | activity | survey',
    `default_unit` varchar(30)           DEFAULT NULL,
    `value_type`   varchar(10)  NOT NULL DEFAULT 'numeric' COMMENT 'numeric | text | boolean',
    `alert_low`    decimal(10, 3)        DEFAULT NULL COMMENT 'NULL = no low alert',
    `alert_high`   decimal(10, 3)        DEFAULT NULL COMMENT 'NULL = no high alert',
    `is_active`    tinyint(1)   NOT NULL DEFAULT 1,
    `sort_order`   smallint(6)  NOT NULL DEFAULT 100,
    PRIMARY KEY (`code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Reference catalogue of observable types with LOINC codes and alert bounds';

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
  COLLATE = utf8mb4_general_ci COMMENT ='Triage vitals sets';

CREATE TABLE IF NOT EXISTS `oei_user_context`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `context_key`      varchar(30)         NOT NULL DEFAULT 'FULL',
    `updated_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ctx_user_fac` (`user_id`, `facility_id`),
    KEY `idx_oei_ctx_facility` (`facility_id`, `context_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user care context preference per facility';

-- ─────────────────────────────────────────────────────────────────────
-- Schema version stamp
-- Records the schema version installed by this file. Replaces the row
-- formerly written by the migration runner; fresh installs apply table.sql
-- directly via Module Manager, so this is the single source of the version.
-- ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0.40.0', NOW());

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;




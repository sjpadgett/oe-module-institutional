-- Host: localhost
-- Generation Time: Feb 28, 2026 at 05:24 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT = @@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS = @@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION = @@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
-- DEV ONLY: Reset Institutional module tables
-- DROP order: children first to avoid FK issues if added later
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS
    `oei_adl_record`,
    `oei_alert_ack`,
    `oei_al_episode`,
    `oei_bh_boarding`,
    `oei_bh_safety`,
    `oei_diversion`,
    `oei_diversion_history`,
    `oei_downtime_sync_queue`,
    `oei_episode`,
    `oei_episode_disposition`,
    `oei_episode_document`,
    `oei_episode_event`,
    `oei_episode_location`,
    `oei_episode_status_history`,
    `oei_ereferral`,
    `oei_facility_directory`,
    `oei_hl7_outbound_log`,
    `oei_incident`,
    `oei_location`,
    `oei_mar_administration`,
    `oei_mar_order`,
    `oei_obs_plan`,
    `oei_patient_location_history`,
    `oei_protocol`,
    `oei_schema_version`,
    `oei_settings`,
    `oei_task`,
    `oei_transfer`,
    `oei_triage`,
    `oei_user_context`;
SET FOREIGN_KEY_CHECKS = 1;

--
-- Database: `openemr`
--

-- --------------------------------------------------------

--
-- Table structure for table `oei_adl_record`
--

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
  AUTO_INCREMENT = 11
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='ADL charting sessions; one row per aide session covering all 7 domains';

--
-- Dumping data for table `oei_adl_record`
--

INSERT INTO `oei_adl_record` (`id`, `episode_id`, `facility_id`, `noted_by_user_id`, `noted_datetime`, `adl_json`, `adl_score`, `notes`)
VALUES (1, 14, 1, 1, '2026-02-27 16:20:13', '{\"bathing\":4,\"dressing\":3,\"grooming\":3,\"transfer\":3,\"ambulation\":3,\"eating\":2,\"toileting\":4}', 22, 'Night shift: confused, resisted morning care. Bed alarm triggered twice. No fall.'),
       (2, 14, 1, 1, '2026-02-28 16:20:13', '{\"bathing\":4,\"dressing\":3,\"grooming\":2,\"transfer\":3,\"ambulation\":3,\"eating\":2,\"toileting\":3}', 20, 'Day shift: more cooperative after breakfast. Music therapy at 10 AM — calm for 45 min.'),
       (3, 15, 1, 1, '2026-02-27 14:20:13', '{\"bathing\":2,\"dressing\":2,\"grooming\":1,\"transfer\":3,\"ambulation\":3,\"eating\":1,\"toileting\":2}', 14, 'Improving transfer with walker. Rated pain 4/10 post-PT.'),
       (4, 15, 1, 1, '2026-02-28 15:20:13', '{\"bathing\":2,\"dressing\":2,\"grooming\":1,\"transfer\":2,\"ambulation\":3,\"eating\":1,\"toileting\":2}', 13, 'PT this AM — achieved 20 ft ambulation with walker. Good progress.'),
       (5, 16, 1, 1, '2026-02-27 12:20:13', '{\"bathing\":1,\"dressing\":1,\"grooming\":0,\"transfer\":1,\"ambulation\":1,\"eating\":0,\"toileting\":1}', 5, 'Independent with most tasks. Assisted with shower per preference.'),
       (6, 16, 1, 1, '2026-02-28 14:20:13', '{\"bathing\":1,\"dressing\":1,\"grooming\":0,\"transfer\":1,\"ambulation\":1,\"eating\":0,\"toileting\":1}', 5, 'Stable. SpO2 94% on RA — within goal. Inhaler administered on schedule.'),
       (7, 17, 1, 1, '2026-02-27 18:20:13', '{\"bathing\":3,\"dressing\":4,\"grooming\":3,\"transfer\":4,\"ambulation\":3,\"eating\":2,\"toileting\":3}', 22, 'Morning off-period — significant rigidity pre-meds. Levodopa 0710 (within window).'),
       (8, 17, 1, 1, '2026-02-28 17:20:13', '{\"bathing\":3,\"dressing\":3,\"grooming\":3,\"transfer\":4,\"ambulation\":4,\"eating\":2,\"toileting\":3}', 22, 'Post-fall assessment — see incident report. Ambulation suspended pending PT clearance.'),
       (9, 18, 1, 1, '2026-02-27 13:20:13', '{\"bathing\":2,\"dressing\":2,\"grooming\":1,\"transfer\":2,\"ambulation\":2,\"eating\":1,\"toileting\":2}', 12, 'Weight today 142 lbs — up 1.8 lbs from baseline. Within threshold. FBG 162.'),
       (10, 18, 1, 1, '2026-02-28 13:20:13', '{\"bathing\":2,\"dressing\":2,\"grooming\":1,\"transfer\":2,\"ambulation\":2,\"eating\":1,\"toileting\":2}', 12, 'Weight 143 lbs — +2.2 lbs. Notified charge nurse and attending per CHF weight protocol. Furosemide dose reviewed.');

-- --------------------------------------------------------

--
-- Table structure for table `oei_alert_ack`
--

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
  AUTO_INCREMENT = 7
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user alert snooze/acknowledgement records';

--
-- Dumping data for table `oei_alert_ack`
--

INSERT INTO `oei_alert_ack` (`id`, `alert_key`, `facility_id`, `user_id`, `acked_datetime`, `expires_datetime`)
VALUES (1, 'LWBS_RISK:6', 1, 1, '2026-02-28 20:20:11', '2026-02-28 23:20:11'),
       (2, 'SEPSIS_RISK:4', 1, 1, '2026-02-28 20:50:12', '2026-02-28 23:50:12'),
       (3, 'BED_WAIT_ICU:4', 1, 1, '2026-02-28 21:50:12', '2026-02-28 23:20:12'),
       (4, 'LWBS_RISK:10', 1, 1, '2026-02-28 22:05:12', '2026-02-28 22:50:12'),
       (5, 'MAR_OVERDUE:8', 1, 1, '2026-02-28 22:10:12', '2026-02-28 23:20:12'),
       (6, 'BH_BOARDING_DWELL:6', 1, 1, '2026-02-28 22:00:12', '2026-02-28 23:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_al_episode`
--

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
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL-specific overlay on oei_episode; links to form_encounter for care plan anchoring';

--
-- Dumping data for table `oei_al_episode`
--

INSERT INTO `oei_al_episode` (`id`, `episode_id`, `pid`, `facility_id`, `encounter_id`, `room`, `unit`, `care_level`, `fall_risk_level`, `fall_risk_score`, `admit_reason`, `last_adl_score`, `last_adl_datetime`, `created_datetime`)
VALUES (1, 14, 50, 1, 297, '101', 'Wing A', 'TIER_3', 'HIGH', 78, 'Memory care — moderate dementia with behavioral disturbances and fall history', 14, '2026-02-28 16:20:13', '2026-01-12 22:20:13'),
       (2, 15, 51, 1, 298, '104', 'Wing A', 'TIER_2', 'MODERATE', 38, 'Post-hip arthroplasty transition from SNF — PT/OT in progress', 20, '2026-02-28 15:20:13', '2026-01-28 22:20:13'),
       (3, 16, 52, 1, 299, '108', 'Wing A', 'TIER_1', 'LOW', 12, 'COPD management and medication administration assistance', 25, '2026-02-28 14:20:13', '2026-02-10 22:20:13'),
       (4, 17, 53, 1, 300, '201', 'Wing B', 'TIER_3', 'HIGH', 91, 'Advanced Parkinson\'s — fall prevention, dysphagia protocol, daily PT', 10, '2026-02-28 17:20:13', '2025-12-28 22:20:13'),
       (5, 18, 54, 1, 301, '205', 'Wing B', 'TIER_2', 'MODERATE', 32, 'CHF/T2DM — daily weights, fluid restriction, insulin management', 22, '2026-02-28 13:20:13', '2026-02-19 22:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `oei_bh_boarding`
--

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
  AUTO_INCREMENT = 3
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_bh_boarding`
--

INSERT INTO `oei_bh_boarding` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `legal_status`, `suicide_risk`, `violence_risk`, `placement_status`, `accepting_facility`, `accepted_datetime`, `transport_method`, `transport_datetime`, `emtala_complete`, `checklist_json`, `notes`,
                               `updated_by_user_id`, `updated_datetime`)
VALUES (1, 6, 4, NULL, 1, 'VOLUNTARY', 'HIGH', 'LOW', 'SEARCHING', NULL, NULL, NULL, NULL, 1,
        '{\"items\":[{\"label\":\"EMTALA MSE completed\",\"done\":true},{\"label\":\"Insurance verified — MediCal\",\"done\":true},{\"label\":\"Placement calls initiated\",\"done\":true},{\"label\":\"Accepting facility confirmed\",\"done\":false},{\"label\":\"Family notified\",\"done\":true},{\"label\":\"Transport arranged\",\"done\":false}]}',
        'Called Valley BH (full), State Hospital (waitlist — ETA 6-8h), Riverside BH (no voluntary beds). Re-calling Valley BH at next hour.', 1, '2026-02-28 20:20:11'),
       (2, 13, 11, NULL, 1, 'VOLUNTARY', 'MODERATE', 'LOW', 'ACCEPTED', 'Valley Behavioral Health Center', NULL, NULL, NULL, 1,
        '{\"items\":[{\"label\":\"EMTALA MSE completed\",\"done\":true},{\"label\":\"Insurance verified — Blue Shield\",\"done\":true},{\"label\":\"Placement calls initiated\",\"done\":true},{\"label\":\"Accepting facility confirmed\",\"done\":true},{\"label\":\"Family notified\",\"done\":true},{\"label\":\"Transfer paperwork complete\",\"done\":true},{\"label\":\"Transport arranged\",\"done\":true}]}',
        'Valley BH accepted at 0930. Transport: Medvan unit dispatched, ETA 30-45 min. Patient and family updated and relieved.', 1, '2026-02-28 20:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_bh_safety`
--

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
  AUTO_INCREMENT = 4
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_bh_safety`
--

INSERT INTO `oei_bh_safety` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `observation_level`, `is_involuntary`, `risk_violence`, `risk_suicide`, `elopement_risk`, `precautions_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 6, 4, NULL, 1, '1:1', 0, 0, 1, 1,
        '{\"items\":[\"Sharps removed from room and patient\",\"Street clothing searched\",\"Belts and shoelaces removed\",\"1:1 sitter assigned — shift change at 1500\",\"Columbia Suicide Severity Rating Scale completed: High risk\",\"Crisis counselor notified\"]}', 1,
        '2026-02-28 17:30:11'),
       (2, 12, 10, NULL, 1, '1:1', 0, 1, 0, 1, '{\"items\":[\"Wrist restraints applied — combative on arrival\",\"Belongings searched — no sharps found\",\"1:1 nurse monitoring for re-sedation\",\"Elopement risk — patient attempted to leave x1\",\"IV secured with armboard\"]}',
        1, '2026-02-28 21:05:12'),
       (3, 13, 11, NULL, 1, 'Q15', 0, 0, 1, 0, '{\"items\":[\"Voluntary patient — fully cooperative\",\"Belongings searched — no contraband\",\"Q15-minute safety checks\",\"Family at bedside\",\"Columbia SSRS: moderate risk\",\"Crisis counselor evaluation completed\"]}', 1,
        '2026-02-28 14:40:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_diversion`
--

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
  AUTO_INCREMENT = 13
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Current diversion status per facility and service line';

--
-- Dumping data for table `oei_diversion`
--

INSERT INTO `oei_diversion` (`id`, `facility_id`, `service_line`, `status`, `reason`, `diversion_start`, `diversion_end`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'TRAUMA', 'DIVERSION', 'Mass casualty incident diverted — both trauma bays occupied. Redirect all trauma activations to Regional Trauma Center.', '2026-02-28 20:20:10', NULL, 1, '2026-02-28 20:20:10'),
       (2, 1, 'PSYCH', 'DIVERSION', 'No BH holding rooms available. Two patients boarding. Redirect voluntary psych to Valley BH Center.', '2026-02-28 16:20:10', NULL, 1, '2026-02-28 16:20:10'),
       (3, 1, 'ICU', 'LIMITED', 'ICU at 92% capacity. Accepting critical holds only. Call attending before transfer.', '2026-02-28 18:20:10', NULL, 1, '2026-02-28 18:20:10'),
       (4, 1, 'ED', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10'),
       (5, 1, 'OBS', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10'),
       (6, 1, 'PEDS', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10');

-- --------------------------------------------------------

--
-- Table structure for table `oei_diversion_history`
--

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
  AUTO_INCREMENT = 9
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Audit log of all diversion status changes';

--
-- Dumping data for table `oei_diversion_history`
--

INSERT INTO `oei_diversion_history` (`id`, `facility_id`, `service_line`, `previous_status`, `new_status`, `reason`, `diversion_start`, `diversion_end`, `changed_by_user_id`, `changed_datetime`)
VALUES (1, 1, 'TRAUMA', 'OPEN', 'DIVERSION', 'Mass casualty incident — both bays activated', NULL, NULL, 1, '2026-02-28 20:19:41'),
       (2, 1, 'PSYCH', 'OPEN', 'LIMITED', 'BH holding rooms approaching capacity', NULL, NULL, 1, '2026-02-28 14:19:41'),
       (3, 1, 'PSYCH', 'LIMITED', 'DIVERSION', 'No available BH beds. Two patients boarding in ED.', NULL, NULL, 1, '2026-02-28 16:19:41'),
       (4, 1, 'ICU', 'OPEN', 'LIMITED', 'ICU at 92% capacity following overnight admits', NULL, NULL, 1, '2026-02-28 18:19:41'),
       (5, 1, 'TRAUMA', 'OPEN', 'DIVERSION', 'Mass casualty incident — both bays activated', NULL, NULL, 1, '2026-02-28 20:20:10'),
       (6, 1, 'PSYCH', 'OPEN', 'LIMITED', 'BH holding rooms approaching capacity', NULL, NULL, 1, '2026-02-28 14:20:10'),
       (7, 1, 'PSYCH', 'LIMITED', 'DIVERSION', 'No available BH beds. Two patients boarding in ED.', NULL, NULL, 1, '2026-02-28 16:20:10'),
       (8, 1, 'ICU', 'OPEN', 'LIMITED', 'ICU at 92% capacity following overnight admits', NULL, NULL, 1, '2026-02-28 18:20:10');

-- --------------------------------------------------------

--
-- Table structure for table `oei_downtime_sync_queue`
--

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
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Offline write queue — rows written by browser during network outage';

--
-- Dumping data for table `oei_downtime_sync_queue`
--

INSERT INTO `oei_downtime_sync_queue` (`id`, `facility_id`, `entry_type`, `payload_json`, `captured_client`, `queued_datetime`, `synced_datetime`, `status`, `result_note`, `submitted_by_user_id`)
VALUES (1, 1, 'ARRIVAL', '{\"fname\": \"Carlos\", \"lname\": \"Mendez\", \"dob\": \"1985-06-14\", \"chief_complaint\": \"Chest pain, onset 30 min ago\", \"arrival_mode\": \"WALKIN\", \"acuity_esi\": 2}', '2026-02-28 20:52:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12',
        'SYNCED', 'Synced — episode created pid 12', 1),
       (2, 1, 'VITALS', '{\"episode_id\": 4, \"pid\": 2, \"bp_systolic\": 102, \"bp_diastolic\": 64, \"hr\": 118, \"rr\": 22, \"spo2\": 93, \"temp_f\": 101.2}', '2026-02-28 20:54:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED',
        'Synced — triage re-assessment row inserted', 1),
       (3, 1, 'STATUS_NOTE', '{\"episode_id\": 9, \"pid\": 7, \"note\": \"Transport team confirmed. Medic 7 ETA 10 min. Patient stable.\"}', '2026-02-28 20:57:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED', 'Synced — status history entry added', 1),
       (4, 1, 'TASK_NOTE', '{\"episode_id\": 5, \"pid\": 3, \"task_type\": \"STRESS_TEST\", \"note\": \"Patient on treadmill. EKG connected. Baseline HR 72.\"}', '2026-02-28 20:59:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED', 'Synced — task note appended', 1),
       (5, 1, 'VITALS', '{\"episode_id\": 12, \"pid\": 10, \"bp_systolic\": 116, \"bp_diastolic\": 74, \"hr\": 92, \"rr\": 16, \"spo2\": 98, \"note\": \"Post-Narcan 90min check. Stable.\"}', '2026-02-28 22:18:12', '2026-02-28 22:19:12', NULL, 'PENDING', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode`
--

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
  AUTO_INCREMENT = 19
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_episode`
--

INSERT INTO `oei_episode` (`id`, `pid`, `eid`, `facility_id`, `type`, `start_datetime`, `end_datetime`, `disposition`, `status`, `chief_complaint`, `acuity_esi`, `provider_user_id`, `triage_completed_datetime`, `last_status_update`, `arrival_mode`, `triage_datetime`,
                           `triage_note`, `created_by_user_id`, `created_datetime`, `assigned_nurse_user_id`, `assigned_provider_user_id`)
VALUES (1, 2, NULL, 1, 'ED', '2026-02-28 19:19:41', NULL, NULL, 'ACTIVE', 'Altered mental status, fever, productive cough', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 19:19:41', 1, 1),
       (2, 3, NULL, 1, 'OBS', '2026-02-28 00:19:41', NULL, NULL, 'ACTIVE', 'Substernal chest pressure, r/o ACS', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 00:19:41', 1, 1),
       (3, 4, NULL, 1, 'ED', '2026-02-28 17:19:41', NULL, NULL, 'ACTIVE', 'Psychiatric evaluation, suicidal ideation with plan', 3, NULL, NULL, NULL, 'POLICE', NULL, NULL, 1, '2026-02-28 17:19:41', 1, 1),
       (4, 2, NULL, 1, 'ED', '2026-02-28 19:20:10', NULL, NULL, 'ACTIVE', 'Altered mental status, fever, productive cough', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 19:20:10', 1, 1),
       (5, 3, NULL, 1, 'OBS', '2026-02-28 00:20:10', NULL, NULL, 'ACTIVE', 'Substernal chest pressure, r/o ACS', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 00:20:10', 1, 1),
       (6, 4, NULL, 1, 'ED', '2026-02-28 17:20:10', NULL, NULL, 'ACTIVE', 'Psychiatric evaluation, suicidal ideation with plan', 3, NULL, NULL, NULL, 'POLICE', NULL, NULL, 1, '2026-02-28 17:20:10', 1, 1),
       (7, 5, NULL, 1, 'ED', '2026-02-28 21:15:11', NULL, NULL, 'ACTIVE', 'Right ankle pain and swelling after fall', 4, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 21:15:11', 1, 1),
       (8, 6, NULL, 1, 'ED', '2026-02-28 21:55:11', NULL, NULL, 'ACTIVE', 'Acute left-sided weakness and slurred speech — CODE STROKE', 1, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 21:55:11', 1, 1),
       (9, 7, NULL, 1, 'ED', '2026-02-28 20:20:11', NULL, NULL, 'ACTIVE', 'MVA — abdominal pain, hypotension, mechanism of injury', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 20:20:11', 1, 1),
       (10, 8, NULL, 1, 'OBS', '2026-02-28 10:20:11', NULL, NULL, 'ACTIVE', 'COPD exacerbation — increased SOB and productive cough x 3 days', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 10:20:11', 1, 1),
       (11, 9, NULL, 1, 'ED', '2026-02-28 21:35:11', NULL, NULL, 'ACTIVE', 'Fever 103.8°F, dysuria x 2 days — pediatric', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 21:35:11', 1, 1),
       (12, 10, NULL, 1, 'ED', '2026-02-28 20:50:11', NULL, NULL, 'ACTIVE', 'Suspected opioid overdose — unresponsive, Narcan given by EMS', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 20:50:11', 1, 1),
       (13, 11, NULL, 1, 'ED', '2026-02-28 14:20:12', NULL, NULL, 'ACTIVE', 'Major depression, passive suicidal ideation — voluntary psych evaluation', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 14:20:12', 1, 1),
       (14, 50, NULL, 1, 'AL', '2026-01-12 22:20:13', NULL, NULL, 'ACTIVE', 'Memory care placement — moderate dementia, fall history', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-12 22:20:13', NULL, NULL),
       (15, 51, NULL, 1, 'AL', '2026-01-28 22:20:13', NULL, NULL, 'ACTIVE', 'Post-hip-replacement rehab and long-term care transition', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-28 22:20:13', NULL, NULL),
       (16, 52, NULL, 1, 'AL', '2026-02-10 22:20:13', NULL, NULL, 'ACTIVE', 'Independent-living support — COPD management, medication assist', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-10 22:20:13', NULL, NULL),
       (17, 53, NULL, 1, 'AL', '2025-12-28 22:20:13', NULL, NULL, 'ACTIVE', 'Advanced Parkinson\'s — mobility and swallow safety needs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-12-28 22:20:13', NULL, NULL),
       (18, 54, NULL, 1, 'AL', '2026-02-19 22:20:13', NULL, NULL, 'ACTIVE', 'CHF and T2DM — medication management and dietary monitoring', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-19 22:20:13', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode_disposition`
--

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
  AUTO_INCREMENT = 2
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_episode_disposition`
--

INSERT INTO `oei_episode_disposition` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `disposition_code`, `destination`, `decision_datetime`, `depart_datetime`, `admit_flag`, `notes`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 7, 5, NULL, 1, 'DISCHARGE', 'Home with ortho follow-up', '2026-02-28 22:12:11', NULL, 0, 'Grade II lateral ankle sprain. No fracture on Ottawa criteria. PRICE. Ibuprofen. Ortho f/u if not improving in 1 week. Return for increasing swelling, numbness, or instability.',
        1, '2026-02-28 22:12:11');

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode_document`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode_event`
--

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
  AUTO_INCREMENT = 80
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_episode_event`
--

INSERT INTO `oei_episode_event` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES (1, 1, 2, NULL, 1, 'ARRIVE', '2026-02-28 19:19:41', 1, 'EMS arrival — altered, febrile'),
       (2, 1, 2, NULL, 1, 'ROOM', '2026-02-28 19:27:41', 1, 'ED Room 1'),
       (3, 1, 2, NULL, 1, 'PROVIDER', '2026-02-28 19:34:41', 1, 'Sepsis workup initiated'),
       (4, 2, 3, NULL, 1, 'ARRIVE', '2026-02-28 00:19:41', 1, 'Walk-in, self-transport'),
       (5, 2, 3, NULL, 1, 'ROOM', '2026-02-28 00:24:41', 1, 'OBS Bay 1'),
       (6, 2, 3, NULL, 1, 'OBS_START', '2026-02-28 02:19:41', 1, 'Chest pain obs protocol'),
       (7, 3, 4, NULL, 1, 'ARRIVE', '2026-02-28 17:19:41', 1, 'Police escort — public safety call'),
       (8, 3, 4, NULL, 1, 'ROOM', '2026-02-28 17:24:41', 1, 'BH Room 1'),
       (9, 4, 2, NULL, 1, 'ARRIVE', '2026-02-28 19:20:10', 1, 'EMS arrival — altered, febrile'),
       (10, 4, 2, NULL, 1, 'ROOM', '2026-02-28 19:28:10', 1, 'ED Room 1'),
       (11, 4, 2, NULL, 1, 'PROVIDER', '2026-02-28 19:35:10', 1, 'Sepsis workup initiated'),
       (12, 5, 3, NULL, 1, 'ARRIVE', '2026-02-28 00:20:10', 1, 'Walk-in, self-transport'),
       (13, 5, 3, NULL, 1, 'ROOM', '2026-02-28 00:25:10', 1, 'OBS Bay 1'),
       (14, 5, 3, NULL, 1, 'OBS_START', '2026-02-28 02:20:10', 1, 'Chest pain obs protocol'),
       (15, 6, 4, NULL, 1, 'ARRIVE', '2026-02-28 17:20:10', 1, 'Police escort — public safety call'),
       (16, 6, 4, NULL, 1, 'ROOM', '2026-02-28 17:25:10', 1, 'BH Room 1'),
       (17, 8, 6, NULL, 1, 'ARRIVE', '2026-02-28 21:55:11', 1, 'EMS STAT — Code Stroke overhead'),
       (18, 8, 6, NULL, 1, 'ROOM', '2026-02-28 21:58:11', 1, 'Trauma Bay 1'),
       (19, 8, 6, NULL, 1, 'PROVIDER', '2026-02-28 21:59:11', 1, 'Neurology and ED team at bedside'),
       (20, 4, 2, NULL, 1, 'ARRIVAL', '2026-02-28 19:20:12', 1, 'EMS arrival. Altered mental status, fever 103.8°F, rigors.'),
       (21, 4, 2, NULL, 1, 'TRIAGE', '2026-02-28 19:22:12', 1, 'ESI-2. qSOFA score 3. Sepsis protocol activated.'),
       (22, 4, 2, NULL, 1, 'ROOMED', '2026-02-28 19:25:12', 1, 'ED Room 1 — isolation precautions, cultures ordered.'),
       (23, 4, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 19:35:12', 1, '2 sets peripheral blood cultures drawn prior to antibiotics.'),
       (24, 4, 2, NULL, 1, 'ANTIBIOTIC', '2026-02-28 19:45:12', 1, 'Vancomycin 25mg/kg and Pip-Tazo 4.5g IV started. SEP-1 bundle T+0.'),
       (25, 4, 2, NULL, 1, 'LACTATE', '2026-02-28 19:50:12', 1, 'Lactate 4.1 mmol/L — septic shock criteria met. IVF bolus started.'),
       (26, 4, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:55:12', 1, '30mL/kg NS bolus initiated. Running at 150mL/hr.'),
       (27, 4, 2, NULL, 1, 'LAB_RESULT', '2026-02-28 20:20:12', 1, 'WBC 18.4, Procalcitonin 22.6. Repeat lactate ordered.'),
       (28, 4, 2, NULL, 1, 'IMAGING', '2026-02-28 20:30:12', 1, 'CXR: RLL consolidation consistent with pneumonia.'),
       (29, 4, 2, NULL, 1, 'REASSESSMENT', '2026-02-28 21:20:12', 1, 'MAP improved 58→68 after 2L NS. Patient more alert. Repeat lactate 2.8.'),
       (30, 4, 2, NULL, 1, 'DISPOSITION', '2026-02-28 22:00:12', 1, 'Admit to ICU. ICU accepting critical holds only — bed pending.'),
       (31, 5, 3, NULL, 1, 'ARRIVAL', '2026-02-28 00:20:12', 1, 'Walk-in. Central chest pressure 8/10 with exertion, relieved at rest.'),
       (32, 5, 3, NULL, 1, 'TRIAGE', '2026-02-28 00:30:12', 1, 'ESI-3. ACS pathway activated. Aspirin 325mg given.'),
       (33, 5, 3, NULL, 1, 'EKG', '2026-02-28 00:40:12', 1, '12-lead EKG: normal sinus, no ST changes. Serial troponin ordered.'),
       (34, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 01:20:12', 1, 'Troponin T: 0.004 ng/mL (negative). Repeat in 3h.'),
       (35, 5, 3, NULL, 1, 'OBS_START', '2026-02-28 02:20:12', 1, 'Admitted to observation. OBS chest pain protocol started.'),
       (36, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 04:20:12', 1, 'Repeat troponin 3h: 0.006 ng/mL — still negative. Third draw scheduled.'),
       (37, 5, 3, NULL, 1, 'CARDIOLOGY', '2026-02-28 08:20:12', 1, 'Cardiology consult seen. Recommends stress test before discharge.'),
       (38, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 04:20:12', 1, 'Repeat troponin 6h: 0.008 ng/mL — trending stable. No MI.'),
       (39, 5, 3, NULL, 1, 'IMAGING', '2026-02-28 18:20:12', 1, 'Treadmill stress test: negative. 8 METs achieved, no symptoms.'),
       (40, 5, 3, NULL, 1, 'DISPOSITION', '2026-02-28 20:20:12', 1, 'Discharge planned — cardiology agrees, low-risk unstable angina. Aspirin/Statin started.'),
       (41, 6, 4, NULL, 1, 'ARRIVAL', '2026-02-28 17:20:12', 1, 'Police bring-in. Reported suicidal ideation with plan — medication ingestion attempt denied by patient.'),
       (42, 6, 4, NULL, 1, 'TRIAGE', '2026-02-28 17:22:12', 1, 'ESI-3. No acute medical complaint. Calm, guarded. Toxicology ordered.'),
       (43, 6, 4, NULL, 1, 'MEDICAL_CLEARANCE', '2026-02-28 18:20:12', 1, 'Tox screen negative. BMP normal. Medically cleared for psych eval.'),
       (44, 6, 4, NULL, 1, 'BH_SCREEN', '2026-02-28 18:40:12', 1, 'Columbia SSRS: HIGH risk — specific plan, access to means.'),
       (45, 6, 4, NULL, 1, 'INVOLUNTARY', '2026-02-28 18:50:12', 1, 'Involuntary hold placed — physician certification completed.'),
       (46, 6, 4, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 19:20:12', 1, 'Placement calls: Valley BH declined (full). State Hospital on hold.'),
       (47, 6, 4, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 20:50:12', 1, 'Second placement call: Riverside BH — reviewing. No answer x2.'),
       (48, 8, 6, NULL, 1, 'ARRIVAL', '2026-02-28 21:55:12', 1, 'EMS — last known well 35 minutes ago. Right-sided weakness, aphasia.'),
       (49, 8, 6, NULL, 1, 'STROKE_ALERT', '2026-02-28 21:56:12', 1, 'STROKE ALERT activated. Neurology notified. Door-to-CT clock started.'),
       (50, 8, 6, NULL, 1, 'NIHSS', '2026-02-28 21:58:12', 1, 'NIHSS score: 14 (severe). Right arm and leg weakness, expressive aphasia.'),
       (51, 8, 6, NULL, 1, 'IMAGING', '2026-02-28 22:02:12', 1, 'CT head: no hemorrhage. CT angio ordered.'),
       (52, 8, 6, NULL, 1, 'IMAGING', '2026-02-28 22:10:12', 1, 'CT angio: M1 occlusion left MCA — thrombectomy candidate.'),
       (53, 8, 6, NULL, 1, 'TPA_DISCUSSION', '2026-02-28 22:15:12', 1, 'tPA held — LKW 35min, within window but thrombectomy preferred given M1 occlusion.'),
       (54, 9, 7, NULL, 1, 'ARRIVAL', '2026-02-28 20:20:12', 1, 'EMS — MVC 60mph, restrained driver, airbag, +LOC x2min.'),
       (55, 9, 7, NULL, 1, 'TRAUMA_ALERT', '2026-02-28 20:21:12', 1, 'Trauma team activation. Mechanism: high-speed MVC.'),
       (56, 9, 7, NULL, 1, 'FAST_EXAM', '2026-02-28 20:25:12', 1, 'FAST positive — free fluid LUQ and pelvis.'),
       (57, 9, 7, NULL, 1, 'BLOOD_BANK', '2026-02-28 20:28:12', 1, 'MTP activated. O-neg x2 released. Type and cross sent.'),
       (58, 9, 7, NULL, 1, 'MEDICATION', '2026-02-28 20:34:12', 1, 'TXA 1g load given. Permissive hypotension strategy — target SBP 80-90.'),
       (59, 9, 7, NULL, 1, 'IMAGING', '2026-02-28 20:50:12', 1, 'CT abdomen: Grade III splenic laceration, active extravasation.'),
       (60, 9, 7, NULL, 1, 'CONSULT', '2026-02-28 21:00:12', 1, 'Surgical consult: OR on standby. Transfer to Level I Trauma preferred.'),
       (61, 9, 7, NULL, 1, 'TRANSFER_ACCEPT', '2026-02-28 21:50:12', 1, 'Regional Trauma Center accepted. Medic 7 transport ETA 15 min.'),
       (62, 10, 8, NULL, 1, 'ARRIVAL', '2026-02-28 10:20:12', 1, 'Walk-in. Productive cough, increasing SOB x 3 days. SpO2 88% on RA.'),
       (63, 10, 8, NULL, 1, 'MEDICATION', '2026-02-28 10:25:12', 1, 'Albuterol neb started. Ipratropium added. O2 2L NC.'),
       (64, 10, 8, NULL, 1, 'OBS_START', '2026-02-28 11:20:12', 1, 'COPD exacerbation protocol. Target SpO2 > 92%.'),
       (65, 10, 8, NULL, 1, 'LAB_RESULT', '2026-02-28 12:20:12', 1, 'ABG: pH 7.38, pCO2 52, compensated. No acute respiratory failure.'),
       (66, 10, 8, NULL, 1, 'REASSESSMENT', '2026-02-28 16:20:12', 1, 'Peak flow 55% predicted. SpO2 96% on 2L. Improving.'),
       (67, 10, 8, NULL, 1, 'REASSESSMENT', '2026-02-28 21:20:12', 1, 'Peak flow 62% predicted. Meets discharge criteria.'),
       (68, 10, 8, NULL, 1, 'REFERRAL', '2026-02-28 21:50:12', 1, 'E-Referral sent to Home Health for neb management and follow-up.'),
       (69, 12, 10, NULL, 1, 'ARRIVAL', '2026-02-28 20:50:12', 1, 'EMS — found unresponsive. Narcan 0.4mg IM x2 in field.'),
       (70, 12, 10, NULL, 1, 'MEDICATION', '2026-02-28 20:58:12', 1, 'Naloxone infusion 0.4mg/hr started. RR 8→14 at 15min.'),
       (71, 12, 10, NULL, 1, 'RESTRAINT', '2026-02-28 21:02:12', 1, 'Wrist restraints — patient combative post-reversal. Order documented.'),
       (72, 12, 10, NULL, 1, 'LAB_RESULT', '2026-02-28 21:20:12', 1, 'Urine tox: opiates positive. Benzo negative. BAL 0.'),
       (73, 12, 10, NULL, 1, 'REASSESSMENT', '2026-02-28 21:50:12', 1, 'GCS 15. Cooperative. Re-sedation risk continues — half-life monitoring.'),
       (74, 13, 11, NULL, 1, 'ARRIVAL', '2026-02-28 14:20:12', 1, 'Self-presented. Passive SI — \"I don\'t want to be here.\" No plan.'),
       (75, 13, 11, NULL, 1, 'BH_SCREEN', '2026-02-28 15:20:12', 1, 'Columbia SSRS: moderate risk. Crisis counselor evaluation complete.'),
       (76, 13, 11, NULL, 1, 'EMTALA', '2026-02-28 15:20:12', 1, 'MSE complete. EMTALA compliant. Psychiatric determination documented.'),
       (77, 13, 11, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 16:20:12', 1, 'Valley BH, Riverside BH, State Hospital called. Valley BH reviewing.'),
       (78, 13, 11, NULL, 1, 'PLACEMENT_ACCEPT', '2026-02-28 20:50:12', 1, 'Valley BH accepted — unit 3B. Transport Medvan dispatched.'),
       (79, 13, 11, NULL, 1, 'TRANSPORT', '2026-02-28 22:05:12', 1, 'Medvan ETA 15 minutes. Patient notified. Family present.');

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode_location`
--

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
  AUTO_INCREMENT = 13
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_episode_location`
--

INSERT INTO `oei_episode_location` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `location_id`, `location_code`, `start_datetime`, `end_datetime`, `user_id`, `note`)
VALUES (1, 1, 2, NULL, 1, 1, 'ED01', '2026-02-28 19:27:41', NULL, 1, 'Sepsis workup'),
       (2, 2, 3, NULL, 1, 11, 'OBS1', '2026-02-28 00:19:41', NULL, 1, 'OBS admission'),
       (3, 4, 2, NULL, 1, 1, 'ED01', '2026-02-28 19:28:10', NULL, 1, 'Sepsis workup'),
       (4, 5, 3, NULL, 1, 11, 'OBS1', '2026-02-28 00:20:10', NULL, 1, 'OBS admission'),
       (5, 6, 4, NULL, 1, 17, 'PSY1', '2026-02-28 17:25:10', NULL, 1, 'BH holding'),
       (6, 7, 5, NULL, 1, 2, 'ED02', '2026-02-28 21:25:11', NULL, 1, 'Ankle injury'),
       (7, 8, 6, NULL, 1, 9, 'TR01', '2026-02-28 21:58:11', NULL, 1, 'Code Stroke'),
       (8, 9, 7, NULL, 1, 10, 'TR02', '2026-02-28 20:23:11', NULL, 1, 'Trauma activation'),
       (9, 10, 8, NULL, 1, 12, 'OBS2', '2026-02-28 10:25:11', NULL, 1, 'COPD OBS'),
       (10, 11, 9, NULL, 1, 3, 'ED03', '2026-02-28 21:42:11', NULL, 1, 'Peds fever'),
       (11, 12, 10, NULL, 1, 4, 'ED04', '2026-02-28 20:54:11', NULL, 1, 'Opioid OD monitoring'),
       (12, 13, 11, NULL, 1, 15, 'HALL1', '2026-02-28 14:30:12', NULL, 1, 'BH boarding — capacity constraint');

-- --------------------------------------------------------

--
-- Table structure for table `oei_episode_status_history`
--

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
  AUTO_INCREMENT = 61
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_episode_status_history`
--

INSERT INTO `oei_episode_status_history` (`id`, `episode_id`, `status_code`, `set_by_user_id`, `set_datetime`, `note`)
VALUES (1, 1, 'ARRIVE', 1, '2026-02-28 19:19:41', 'Arrived by EMS. Diaphoretic and confused.'),
       (2, 1, 'TRIAGE', 1, '2026-02-28 19:24:41', 'ESI-2 assigned. qSOFA 3/3.'),
       (3, 1, 'ROOMED', 1, '2026-02-28 19:27:41', 'Placed ED Room 1'),
       (4, 1, 'SEPSIS_ALERT', 1, '2026-02-28 19:29:41', 'Sepsis protocol activated — bundle initiated'),
       (5, 1, 'WITH_PROVIDER', 1, '2026-02-28 19:34:41', 'Provider at bedside. Sepsis workup ordered.'),
       (6, 2, 'ARRIVE', 1, '2026-02-28 00:19:41', 'Walk-in. Chest pain onset 2h prior.'),
       (7, 2, 'TRIAGE', 1, '2026-02-28 00:20:41', 'ESI-3. TIMI 3/7. No STEMI on EKG.'),
       (8, 2, 'ROOMED', 1, '2026-02-28 00:24:41', 'OBS Bay 1 — continuous telemetry'),
       (9, 2, 'OBS_START', 1, '2026-02-28 02:19:41', 'Obs status initiated. Chest pain protocol.'),
       (10, 2, 'PENDING_RESULTS', 1, '2026-02-28 14:19:41', 'Awaiting final troponin and cardiology recommendation.'),
       (11, 3, 'ARRIVE', 1, '2026-02-28 17:19:41', 'Police escort. Cooperative. No acute injury.'),
       (12, 3, 'TRIAGE', 1, '2026-02-28 17:20:41', 'ESI-3 BH. Passive SI with plan. Sitter assigned.'),
       (13, 3, 'ROOMED', 1, '2026-02-28 17:24:41', 'BH Room 1 — ligature-reduced. 1:1 sitter.'),
       (14, 3, 'WAITING', 1, '2026-02-28 17:39:41', 'Awaiting BH placement. Calls in progress.'),
       (15, 4, 'ARRIVE', 1, '2026-02-28 19:20:10', 'Arrived by EMS. Diaphoretic and confused.'),
       (16, 4, 'TRIAGE', 1, '2026-02-28 19:25:10', 'ESI-2 assigned. qSOFA 3/3.'),
       (17, 4, 'ROOMED', 1, '2026-02-28 19:28:10', 'Placed ED Room 1'),
       (18, 4, 'SEPSIS_ALERT', 1, '2026-02-28 19:30:10', 'Sepsis protocol activated — bundle initiated'),
       (19, 4, 'WITH_PROVIDER', 1, '2026-02-28 19:35:10', 'Provider at bedside. Sepsis workup ordered.'),
       (20, 5, 'ARRIVE', 1, '2026-02-28 00:20:10', 'Walk-in. Chest pain onset 2h prior.'),
       (21, 5, 'TRIAGE', 1, '2026-02-28 00:21:10', 'ESI-3. TIMI 3/7. No STEMI on EKG.'),
       (22, 5, 'ROOMED', 1, '2026-02-28 00:25:10', 'OBS Bay 1 — continuous telemetry'),
       (23, 5, 'OBS_START', 1, '2026-02-28 02:20:10', 'Obs status initiated. Chest pain protocol.'),
       (24, 5, 'PENDING_RESULTS', 1, '2026-02-28 14:20:10', 'Awaiting final troponin and cardiology recommendation.'),
       (25, 6, 'ARRIVE', 1, '2026-02-28 17:20:10', 'Police escort. Cooperative. No acute injury.'),
       (26, 6, 'TRIAGE', 1, '2026-02-28 17:21:10', 'ESI-3 BH. Passive SI with plan. Sitter assigned.'),
       (27, 6, 'ROOMED', 1, '2026-02-28 17:25:10', 'BH Room 1 — ligature-reduced. 1:1 sitter.'),
       (28, 6, 'WAITING', 1, '2026-02-28 17:40:10', 'Awaiting BH placement. Calls in progress.'),
       (29, 7, 'ARRIVE', 1, '2026-02-28 21:15:11', 'Walk-in — hopped to desk'),
       (30, 7, 'TRIAGE', 1, '2026-02-28 21:20:11', 'ESI-4. Right ankle swelling, NWB.'),
       (31, 7, 'ROOMED', 1, '2026-02-28 21:25:11', 'ED Room 2'),
       (32, 7, 'WITH_PROVIDER', 1, '2026-02-28 21:40:11', 'Provider evaluation complete — lateral sprain'),
       (33, 7, 'READY_DISCHARGE', 1, '2026-02-28 22:15:11', 'Imaging reviewed, pain controlled, discharge instructions printed'),
       (34, 8, 'ARRIVE', 1, '2026-02-28 21:55:11', 'EMS arrival — Code Stroke activated overhead'),
       (35, 8, 'TRIAGE', 1, '2026-02-28 21:57:11', 'ESI-1. Last known well 90 min ago. LKW within tPA window.'),
       (36, 8, 'ROOMED', 1, '2026-02-28 21:58:11', 'Trauma Bay 1 — stroke team assembled'),
       (37, 8, 'WITH_PROVIDER', 1, '2026-02-28 21:59:11', 'Neurology at bedside. NIHSS assessment in progress.'),
       (38, 9, 'ARRIVE', 1, '2026-02-28 20:20:11', 'EMS arrival. Mechanism: 60mph MVC restrained driver, airbag deployed, +LOC x 2 min.'),
       (39, 9, 'TRIAGE', 1, '2026-02-28 20:22:11', 'ESI-2. BP 96/62, HR 124, abdominal guarding.'),
       (40, 9, 'ROOMED', 1, '2026-02-28 20:23:11', 'Trauma Bay 2 — trauma team activated'),
       (41, 9, 'WITH_PROVIDER', 1, '2026-02-28 20:25:11', 'Trauma team evaluation complete. CT abdomen positive.'),
       (42, 9, 'PENDING_TRANSFER', 1, '2026-02-28 21:50:11', 'Transfer accepted. Awaiting transport unit.'),
       (43, 10, 'ARRIVE', 1, '2026-02-28 10:20:11', 'Walk-in. SOB worsening x3 days. Smoking hx 40 pack-years.'),
       (44, 10, 'TRIAGE', 1, '2026-02-28 10:21:11', 'ESI-3. SpO2 88% RA, wheezing bilateral.'),
       (45, 10, 'ROOMED', 1, '2026-02-28 10:25:11', 'OBS Bay 2 — oxygen 2L NC, albuterol neb started'),
       (46, 10, 'OBS_START', 1, '2026-02-28 11:20:11', 'Observation status. COPD exacerbation protocol.'),
       (47, 10, 'PENDING_RESULTS', 1, '2026-02-28 20:20:11', 'Peak flow 62% predicted — improving. Discharge planning.'),
       (48, 11, 'ARRIVE', 1, '2026-02-28 21:35:11', 'Parent brought in. Fever 103.8°F at home x 24h. Dysuria.'),
       (49, 11, 'TRIAGE', 1, '2026-02-28 21:38:11', 'ESI-3. Alert, ill-appearing. Temp 103.8°F.'),
       (50, 11, 'ROOMED', 1, '2026-02-28 21:42:11', 'ED Room 3 — pediatric setup'),
       (51, 11, 'WITH_PROVIDER', 1, '2026-02-28 22:00:11', 'Provider evaluation: UTI suspected. Labs ordered.'),
       (52, 12, 'ARRIVE', 1, '2026-02-28 20:50:11', 'EMS — found unresponsive in public bathroom. Narcan 0.4mg IM x2 in field. GCS 6 → 14 post Narcan.'),
       (53, 12, 'TRIAGE', 1, '2026-02-28 20:52:11', 'ESI-2. Alert, agitated. Pinpoint pupils. Naloxone drip started.'),
       (54, 12, 'ROOMED', 1, '2026-02-28 20:54:11', 'ED Room 4. Wrist restraints applied — patient combative.'),
       (55, 12, 'WITH_PROVIDER', 1, '2026-02-28 21:00:11', 'Provider evaluation. Urine tox + opiates. BH consult ordered.'),
       (56, 13, 'ARRIVE', 1, '2026-02-28 14:20:12', 'Self-presented. Reports passive SI, unable to contract for safety. Supportive family present.'),
       (57, 13, 'TRIAGE', 1, '2026-02-28 14:21:12', 'ESI-3. No acute medical complaints. Calm and cooperative.'),
       (58, 13, 'WAITING', 1, '2026-02-28 14:25:12', 'Waiting — PSY1 and PSY2 both occupied. Placed in hallway.'),
       (59, 13, 'PLACED_IN_HALL', 1, '2026-02-28 14:30:12', 'Hallway Bed 1 — sitter assigned. Privacy curtain.'),
       (60, 13, 'PLACEMENT_ACCEPTED', 1, '2026-02-28 20:50:12', 'Valley BH accepted. Transport ETA 45-60 min.');

-- --------------------------------------------------------

--
-- Table structure for table `oei_ereferral`
--

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
  AUTO_INCREMENT = 6
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_ereferral`
--

INSERT INTO `oei_ereferral` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `referral_type`, `status`, `priority`, `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`, `destination_address`, `reason_for_referral`, `clinical_summary`,
                             `services_requested`, `medications_summary`, `followup_instructions`, `sent_datetime`, `sent_by_user_id`, `send_method`, `response_datetime`, `response_by_name`, `response_notes`, `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES (1, 2, 3, NULL, 1, 'DISCHARGE', 'DRAFT', 'URGENT', 7, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', NULL, 'ACS rule-out complete. Three negative troponins. EKG without acute changes. Requesting cardiology follow-up and outpatient stress test.',
        'Chest pain with TIMI score 3/7. Serial troponins x3 negative. No EKG changes. HTN, DM, hyperlipidemia. Stable for discharge.', 'Outpatient stress test within 72h; lipid panel; medication reconciliation',
        'Aspirin 325 mg PO QD\nMetoprolol 25 mg PO BID\nHeparin 5000 units SQ Q8H (inpatient — discontinue at discharge)', 'Cardiology f/u within 72h. Return precautions for recurrent chest pain, SOB, syncope.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 21:19:41',
        '2026-02-28 21:19:41'),
       (2, 5, 3, NULL, 1, 'DISCHARGE', 'DRAFT', 'URGENT', 7, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', NULL, 'ACS rule-out complete. Three negative troponins. EKG without acute changes. Requesting cardiology follow-up and outpatient stress test.',
        'Chest pain with TIMI score 3/7. Serial troponins x3 negative. No EKG changes. HTN, DM, hyperlipidemia. Stable for discharge.', 'Outpatient stress test within 72h; lipid panel; medication reconciliation',
        'Aspirin 325 mg PO QD\nMetoprolol 25 mg PO BID\nHeparin 5000 units SQ Q8H (inpatient — discontinue at discharge)', 'Cardiology f/u within 72h. Return precautions for recurrent chest pain, SOB, syncope.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 21:20:10',
        '2026-02-28 21:20:10'),
       (3, 7, 5, NULL, 1, 'DISCHARGE', 'DRAFT', 'ROUTINE', 8, 'Orthopedic Associates of Riverside', '(555) 840-6601', '(555) 840-6600', NULL, 'Grade II lateral ankle sprain. Patient requires orthopedic follow-up within 1 week if not improving with conservative management.',
        'Right ankle pain. X-ray: no fracture. Swelling lateral malleolus. Ottawa rules negative. PRICE initiated.', 'Orthopedic evaluation within 1 week; consider MRI if not improving', 'Ketorolac 30 mg IV (one dose, ED only)\nIbuprofen 600 mg PO Q6H x5 days',
        'Weight bear as tolerated with crutches if needed. Ice 20 min Q2H. Elevate. Return if numbness, worsening swelling, inability to bear weight.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 22:15:11', '2026-02-28 22:15:11'),
       (4, 10, 8, NULL, 1, 'DISCHARGE', 'SENT', 'ROUTINE', 6, 'Valley Home Health Agency', '(555) 620-5501', '(555) 620-5500', NULL, 'COPD exacerbation, stabilised. Requires home health for nebulizer management, medication education, and peak flow monitoring.',
        'COPD (GOLD Stage III). Exacerbation treated with systemic steroids, antibiotics, and bronchodilators. SpO2 96% on 2L NC at discharge. Peak flow 62% predicted.', 'Home nebulizer setup and education; peak flow monitoring log; medication reconciliation; PCP notification',
        'Albuterol 2.5 mg neb Q4H (wean to Q8H)\nIpratropium 0.5 mg neb Q6H\nPrednisone 40 mg PO QD x5 days\nAzithromycin 500 mg PO QD x5 days',
        'PCP follow-up within 48h. Return for SpO2 < 90%, increased work of breathing, or altered mental status. Continue home oxygen if on it.', '2026-02-28 21:50:11', 1, 'FAX', NULL, NULL, NULL, 1, '2026-02-28 20:20:11', '2026-02-28 21:50:11'),
       (5, 13, 11, NULL, 1, 'TRANSFER', 'ACCEPTED', 'URGENT', 3, 'Valley Behavioral Health Center', '(555) 340-7701', '(555) 340-7700', NULL,
        'Voluntary psychiatric admission for major depressive disorder with passive suicidal ideation. No acute medical concerns. Stable for transfer.',
        'Pt with MDD, no prior psych hospitalizations. Passive SI, no plan or intent. Columbia SSRS moderate risk. Fully cooperative. Medical clearance complete — normal CBC, BMP, UA, EKG.',
        'Inpatient psychiatric stabilization; medication evaluation; individual and group therapy', 'No current psychiatric medications', 'Continue outpatient therapy after discharge. PCP follow-up within 1 week.', '2026-02-28 19:20:12', 1, '', '2026-02-28 20:50:12',
        'Valley BH Intake Coordinator', 'Accepted — bed available unit 3B. Transport within 60 min.', 1, '2026-02-28 17:20:12', '2026-02-28 20:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_facility_directory`
--

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
  AUTO_INCREMENT = 23
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_facility_directory`
--

INSERT INTO `oei_facility_directory` (`id`, `facility_id`, `name`, `service_type`, `phone`, `fax`, `email`, `address`, `hours`, `notes`, `is_active`, `sort_order`)
VALUES (1, 1, 'Regional Trauma Center', 'TRAUMA', '(555) 900-1100', '(555) 900-1101', NULL, '1200 Trauma Pkwy, Riverside, CA 92501', '24/7', 'Level I Trauma Center. Direct accept line ext 4400.', 1, 10),
       (2, 1, 'St. Michael Medical Center ICU', 'ICU', '(555) 210-4400', '(555) 210-4401', NULL, '800 Medical Center Dr, Riverside, CA 92507', '24/7', 'Accepts direct ICU admits. Intensivist on call 24/7.', 1, 20),
       (3, 1, 'Valley Behavioral Health Center', 'BH', '(555) 340-7700', '(555) 340-7701', NULL, '3300 Valley View Rd, Moreno Valley, CA 92557', 'M-F 8am-8pm; On-call 24/7', 'Adult voluntary and involuntary. 22-bed capacity. Call 3h ahead.', 1, 30),
       (4, 1, 'Regional Stroke & Neuro Center', 'NEURO', '(555) 450-2200', '(555) 450-2201', NULL, '950 Neuroscience Blvd, Riverside, CA 92501', '24/7 Stroke Team', 'Comprehensive Stroke Center. tPA and thrombectomy capable.', 1, 40),
       (5, 1, 'Sunrise Skilled Nursing Facility', 'SNF', '(555) 580-3300', '(555) 580-3301', NULL, '201 Sunrise Terrace, Moreno Valley, CA 92553', 'M-F 8am-5pm', 'Medicare-certified. PT/OT/speech. Med-surg level care.', 1, 50),
       (6, 1, 'Valley Home Health Agency', 'HOME_HEALTH', '(555) 620-5500', '(555) 620-5501', NULL, '1040 Home Care Way, Riverside, CA 92503', 'M-F 8am-6pm; on-call after hours', 'IV therapy, wound care, medication management.', 1, 60),
       (7, 1, 'Mountain Cardiology Group', 'CARDIOLOGY', '(555) 730-8800', '(555) 730-8801', NULL, '505 Heart Lane, Riverside, CA 92506', 'M-F 9am-5pm; on-call 24/7', 'Follow-up within 48-72h for ACS discharge.', 1, 70),
       (8, 1, 'Orthopedic Associates of Riverside', 'ORTHOPEDIC', '(555) 840-6600', '(555) 840-6601', NULL, '720 Bone & Joint Dr, Riverside, CA 92505', 'M-F 8am-5pm', 'Walk-in fracture clinic M/W/F. Fax referral + x-ray CD.', 1, 80),
       (9, 1, 'Mountain Urology Associates', 'UROLOGY', '(555) 950-4400', '(555) 950-4401', NULL, '338 Renal Blvd, Riverside, CA 92504', 'M-F 9am-5pm', 'Stone clinic. Fax CT report. Follow up within 1 week.', 1, 90),
       (10, 1, 'LTACH — Valley Long-Term Acute', 'LTACH', '(555) 160-9900', '(555) 160-9901', NULL, '1800 Long Term Care Ave, Perris, CA 92571', 'M-F 8am-5pm', 'Complex medically ventilator-dependent patients.', 1, 100),
       (11, 1, 'State Psychiatric Hospital', 'BH', '(555) 270-1100', '(555) 270-1101', NULL, '4500 State Hospital Rd, Patton, CA 92369', '24/7 Intake', 'IMD facility. Accepts involuntary holds. Long waitlist.', 1, 110),
       (12, 1, 'Regional Trauma Center', 'TRAUMA', '(555) 900-1100', '(555) 900-1101', NULL, '1200 Trauma Pkwy, Riverside, CA 92501', '24/7', 'Level I Trauma Center. Direct accept line ext 4400.', 1, 10),
       (13, 1, 'St. Michael Medical Center ICU', 'ICU', '(555) 210-4400', '(555) 210-4401', NULL, '800 Medical Center Dr, Riverside, CA 92507', '24/7', 'Accepts direct ICU admits. Intensivist on call 24/7.', 1, 20),
       (14, 1, 'Valley Behavioral Health Center', 'BH', '(555) 340-7700', '(555) 340-7701', NULL, '3300 Valley View Rd, Moreno Valley, CA 92557', 'M-F 8am-8pm; On-call 24/7', 'Adult voluntary and involuntary. 22-bed capacity. Call 3h ahead.', 1, 30),
       (15, 1, 'Regional Stroke & Neuro Center', 'NEURO', '(555) 450-2200', '(555) 450-2201', NULL, '950 Neuroscience Blvd, Riverside, CA 92501', '24/7 Stroke Team', 'Comprehensive Stroke Center. tPA and thrombectomy capable.', 1, 40),
       (16, 1, 'Sunrise Skilled Nursing Facility', 'SNF', '(555) 580-3300', '(555) 580-3301', NULL, '201 Sunrise Terrace, Moreno Valley, CA 92553', 'M-F 8am-5pm', 'Medicare-certified. PT/OT/speech. Med-surg level care.', 1, 50),
       (17, 1, 'Valley Home Health Agency', 'HOME_HEALTH', '(555) 620-5500', '(555) 620-5501', NULL, '1040 Home Care Way, Riverside, CA 92503', 'M-F 8am-6pm; on-call after hours', 'IV therapy, wound care, medication management.', 1, 60),
       (18, 1, 'Mountain Cardiology Group', 'CARDIOLOGY', '(555) 730-8800', '(555) 730-8801', NULL, '505 Heart Lane, Riverside, CA 92506', 'M-F 9am-5pm; on-call 24/7', 'Follow-up within 48-72h for ACS discharge.', 1, 70),
       (19, 1, 'Orthopedic Associates of Riverside', 'ORTHOPEDIC', '(555) 840-6600', '(555) 840-6601', NULL, '720 Bone & Joint Dr, Riverside, CA 92505', 'M-F 8am-5pm', 'Walk-in fracture clinic M/W/F. Fax referral + x-ray CD.', 1, 80),
       (20, 1, 'Mountain Urology Associates', 'UROLOGY', '(555) 950-4400', '(555) 950-4401', NULL, '338 Renal Blvd, Riverside, CA 92504', 'M-F 9am-5pm', 'Stone clinic. Fax CT report. Follow up within 1 week.', 1, 90),
       (21, 1, 'LTACH — Valley Long-Term Acute', 'LTACH', '(555) 160-9900', '(555) 160-9901', NULL, '1800 Long Term Care Ave, Perris, CA 92571', 'M-F 8am-5pm', 'Complex medically ventilator-dependent patients.', 1, 100),
       (22, 1, 'State Psychiatric Hospital', 'BH', '(555) 270-1100', '(555) 270-1101', NULL, '4500 State Hospital Rd, Patton, CA 92369', '24/7 Intake', 'IMD facility. Accepts involuntary holds. Long waitlist.', 1, 110);

-- --------------------------------------------------------

--
-- Table structure for table `oei_hl7_outbound_log`
--

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
  AUTO_INCREMENT = 15
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='HL7 v2 ADT outbound message log';

--
-- Dumping data for table `oei_hl7_outbound_log`
--

INSERT INTO `oei_hl7_outbound_log` (`id`, `episode_id`, `pid`, `facility_id`, `event_type`, `transport_type`, `endpoint`, `message_body`, `ack_body`, `status`, `error_message`, `sent_datetime`)
VALUES (1, 4, 2, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315091500||ADT^A04^ADT_A01|OEI001|T|2.5.1\rEVN|A04|20260315091500\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 19:20:12'),
       (2, 5, 3, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A04^ADT_A01|OEI002|T|2.5.1\rEVN|A04|20260315073000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 00:20:12'),
       (3, 5, 3, 1, 'A01', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A01^ADT_A01|OEI003|T|2.5.1\rEVN|A01|20260315093000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 02:20:12'),
       (4, 8, 6, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315113500||ADT^A04^ADT_A01|OEI004|T|2.5.1\rEVN|A04|20260315113500\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 21:55:12'),
       (5, 9, 7, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315100000||ADT^A04^ADT_A01|OEI005|T|2.5.1\rEVN|A04|20260315100000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 20:20:12'),
       (6, 9, 7, 1, 'A08', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315103000||ADT^A08^ADT_A01|OEI006|T|2.5.1\rEVN|A08|20260315103000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 20:50:12'),
       (7, 10, 8, 1, 'A01', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315031500||ADT^A01^ADT_A01|OEI007|T|2.5.1\rEVN|A01|20260315031500\rPID|1||8^^^OEI^PI||Kim^David||19630425|M\rPV1|1|O|OBS2^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 11:20:12'),
       (8, 4, 2, 1, 'A02', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228192513||ADT^A02^ADT_A01|OEI010|T|2.5.1\rEVN|A02|20260228192513\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 19:25:13'),
       (9, 5, 3, 1, 'A01', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228022013||ADT^A01^ADT_A01|OEI011|T|2.5.1\rEVN|A01|20260228022013\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 02:20:13'),
       (10, 8, 6, 1, 'A02', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228215713||ADT^A02^ADT_A01|OEI012|T|2.5.1\rEVN|A02|20260228215713\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 21:57:13'),
       (11, 9, 7, 1, 'A03', 'HTTP', 'https://rtc.hospital.org/hl7/adt', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228220513||ADT^A03^ADT_A01|OEI013|T|2.5.1\rEVN|A03|20260228220513\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL,
        'SENT', NULL, '2026-02-28 22:05:13'),
       (12, 0, 0, 1, 'A09', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|EMS-DISPATCH|REGIONAL|20260228202013||ADT^A09^ADT_A01|OEI014|T|2.5.1\rEVN|A09|20260228202013\rZDV|TRAUMA|DIVERSION|Mass casualty incident — redirect to Regional Trauma Center',
        NULL, 'SENT', NULL, '2026-02-28 20:20:13'),
       (13, 12, 10, 1, 'A08', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228215013||ADT^A08^ADT_A01|OEI015|T|2.5.1\rEVN|A08|20260228215013\rPID|1||10^^^OEI^PI||Williams^Marcus||19960318|M\rPV1|1|E|ED04^^^OPENEMR', NULL,
        'SENT', NULL, '2026-02-28 21:50:13'),
       (14, 6, 4, 1, 'A04', 'MLLP', 'hl7-backup.hospital.internal:2576', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|BACKUP|FACILITY|20260228172213||ADT^A04^ADT_A01|OEI016|T|2.5.1\rEVN|A04|20260228172213\rPID|1||4^^^OEI^PI||Brooks^Tyler||19900511|M\rPV1|1|E|PSY1^^^OPENEMR', NULL, 'NACK',
        NULL, '2026-02-28 17:22:13');

-- --------------------------------------------------------

--
-- Table structure for table `oei_incident`
--

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
  AUTO_INCREMENT = 3
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL incident reports; mandatory_report_sent tracks state notification status';

--
-- Dumping data for table `oei_incident`
--

INSERT INTO `oei_incident` (`id`, `episode_id`, `facility_id`, `reported_by_user_id`, `incident_type`, `severity`, `incident_datetime`, `location_description`, `narrative`, `corrective_action`, `reported_state`, `mandatory_report_sent`, `created_datetime`)
VALUES (1, 17, 1, 1, 'FALL_INJURY', 'HIGH', '2026-02-28 17:20:13', 'Wing B Room 201 - beside bed',
        'Resident found on floor beside bed during AM care check. Unwitnessed. Alert and oriented x2 on assessment. No LOC. 2cm laceration right forearm, mild bruising right hip. No signs of hip fracture. X-ray ordered. Physician and family notified.',
        'Assisted resident to bed. Wound cleaned and dressed. Neuro checks q1h x4h. Bed in lowest position, floor mat placed. Fall alarm re-evaluated. PT to reassess ambulation safety. Care plan goal updated.', 'PENDING', 0, '2026-02-28 17:50:13'),
       (2, 18, 1, 1, 'MED_ERROR', 'MODERATE', '2026-02-19 20:20:13', 'Wing B Room 205 - medication cart',
        'Resident received furosemide 40mg instead of scheduled 20mg due to look-alike packaging. Error discovered during next-shift MAR review. No acute adverse effects. BP 118/72, HR 74, SpO2 97%. Electrolytes ordered and within normal limits.',
        'Physician notified immediately. Electrolyte panel ordered. Resident monitored q2h x8h. Pharmacy notified. Root cause: look-alike packaging. Corrective action: separate storage, barcode scan protocol initiated.', 'NOT_REQUIRED', 0, '2026-02-19 21:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `oei_location`
--

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
  AUTO_INCREMENT = 39
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_location`
--

INSERT INTO `oei_location` (`id`, `facility_id`, `code`, `name`, `location_type`, `status`, `unit_name`, `is_active`, `sort_order`, `notes`)
VALUES (1, 1, 'ED01', 'ED Room 1', 'ROOM', 'AVAILABLE', 'ED', 1, 10, 'Standard ED room'),
       (2, 1, 'ED02', 'ED Room 2', 'ROOM', 'AVAILABLE', 'ED', 1, 20, 'Standard ED room'),
       (3, 1, 'ED03', 'ED Room 3', 'ROOM', 'AVAILABLE', 'ED', 1, 30, 'Standard ED room'),
       (4, 1, 'ED04', 'ED Room 4', 'ROOM', 'AVAILABLE', 'ED', 1, 40, 'Standard ED room'),
       (5, 1, 'ED05', 'ED Room 5', 'ROOM', 'AVAILABLE', 'ED', 1, 50, 'Standard ED room'),
       (6, 1, 'ED06', 'ED Room 6', 'ROOM', 'AVAILABLE', 'ED', 1, 60, 'Isolation capable'),
       (7, 1, 'ED07', 'ED Room 7', 'ROOM', 'AVAILABLE', 'ED', 1, 70, 'Standard ED room'),
       (8, 1, 'ED08', 'ED Room 8', 'ROOM', 'AVAILABLE', 'ED', 1, 80, 'Standard ED room'),
       (9, 1, 'TR01', 'Trauma Bay 1', 'ROOM', 'AVAILABLE', 'ED', 1, 90, 'Full resuscitation equipment'),
       (10, 1, 'TR02', 'Trauma Bay 2', 'ROOM', 'AVAILABLE', 'ED', 1, 100, 'Full resuscitation equipment'),
       (11, 1, 'OBS1', 'Obs Bay 1', 'OBS', 'AVAILABLE', 'OBS', 1, 110, 'Telemetry monitored'),
       (12, 1, 'OBS2', 'Obs Bay 2', 'OBS', 'AVAILABLE', 'OBS', 1, 120, 'Telemetry monitored'),
       (13, 1, 'OBS3', 'Obs Bay 3', 'OBS', 'AVAILABLE', 'OBS', 1, 130, 'Telemetry monitored'),
       (14, 1, 'OBS4', 'Obs Bay 4', 'OBS', 'AVAILABLE', 'OBS', 1, 140, 'Standard monitoring'),
       (15, 1, 'HALL1', 'Hallway Bed 1', 'ROOM', 'AVAILABLE', 'ED', 1, 150, 'Overflow capacity'),
       (16, 1, 'HALL2', 'Hallway Bed 2', 'ROOM', 'AVAILABLE', 'ED', 1, 160, 'Overflow capacity'),
       (17, 1, 'PSY1', 'BH Room 1', 'ROOM', 'AVAILABLE', 'BH', 1, 170, 'Sitter-equipped, ligature-reduced'),
       (18, 1, 'PSY2', 'BH Room 2', 'ROOM', 'AVAILABLE', 'BH', 1, 180, 'Sitter-equipped, ligature-reduced'),
       (19, 1, 'WAIT', 'Waiting Room', 'WAIT', 'AVAILABLE', 'ED', 1, 190, 'Tracked patients awaiting placement');

-- --------------------------------------------------------

--
-- Table structure for table `oei_mar_administration`
--

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
  AUTO_INCREMENT = 22
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_mar_administration`
--

INSERT INTO `oei_mar_administration` (`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`, `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`, `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `note`,
                                      `is_high_alert`, `created_datetime`, `updated_datetime`)
VALUES (1, 0, 1, 2, 1, '2026-02-28 19:36:41', '2026-02-28 19:39:41', 'GIVEN', '1500', 'mg', 'IV', NULL, NULL, NULL, 1, 'First dose — infused over 90 min', 1, '2026-02-28 19:36:41', '2026-02-28 19:39:41'),
       (2, 0, 1, 2, 1, '2026-03-01 03:36:41', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 19:36:41', '2026-02-28 19:36:41'),
       (3, 0, 1, 2, 1, '2026-02-28 19:39:41', '2026-02-28 19:44:41', 'GIVEN', '3.375', 'g', 'IV', NULL, NULL, NULL, 1, 'First dose after cultures drawn', 0, '2026-02-28 19:39:41', '2026-02-28 19:44:41'),
       (4, 1, 1, 2, 1, '2026-02-28 21:19:41', '2026-02-28 21:21:41', 'GIVEN', '0.05', 'mcg/kg/min', 'IV', NULL, NULL, NULL, 1, 'Started for MAP 58. Titrating up.', 1, '2026-02-28 21:19:41', '2026-02-28 21:21:41'),
       (5, 4, 2, 3, 1, '2026-02-28 02:19:41', '2026-02-28 02:19:41', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen L', NULL, NULL, 1, 'Dose 1', 1, '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (6, 4, 2, 3, 1, '2026-02-28 10:19:41', '2026-02-28 10:19:41', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, 'Dose 2', 1, '2026-02-28 10:19:41', '2026-02-28 10:19:41'),
       (7, 4, 2, 3, 1, '2026-02-28 18:19:41', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 18:19:41', '2026-02-28 18:19:41'),
       (8, 6, 4, 2, 1, '2026-02-28 19:37:10', '2026-02-28 19:40:10', 'GIVEN', '1500', 'mg', 'IV', NULL, NULL, NULL, 1, 'First dose — infused over 90 min', 1, '2026-02-28 19:37:10', '2026-02-28 19:40:10'),
       (9, 6, 4, 2, 1, '2026-03-01 03:37:10', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 19:37:10', '2026-02-28 19:37:10'),
       (10, 7, 4, 2, 1, '2026-02-28 19:40:10', '2026-02-28 19:45:10', 'GIVEN', '3.375', 'g', 'IV', NULL, NULL, NULL, 1, 'First dose after cultures drawn', 0, '2026-02-28 19:40:10', '2026-02-28 19:45:10'),
       (11, 9, 4, 2, 1, '2026-02-28 21:20:10', '2026-02-28 21:22:10', 'GIVEN', '0.05', 'mcg/kg/min', 'IV', NULL, NULL, NULL, 1, 'Started for MAP 58. Titrating up.', 1, '2026-02-28 21:20:10', '2026-02-28 21:22:10'),
       (12, 12, 5, 3, 1, '2026-02-28 02:20:10', '2026-02-28 02:20:10', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen L', NULL, NULL, 1, 'Dose 1', 1, '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (13, 12, 5, 3, 1, '2026-02-28 10:20:10', '2026-02-28 10:20:10', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, 'Dose 2', 1, '2026-02-28 10:20:10', '2026-02-28 10:20:10'),
       (14, 12, 5, 3, 1, '2026-02-28 18:20:10', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 18:20:10', '2026-02-28 18:20:10'),
       (15, 16, 7, 5, 1, '2026-02-28 21:40:11', '2026-02-28 21:42:11', 'GIVEN', '30', 'mg', 'IV', 'Left AC', NULL, NULL, 1, 'Pain 7/10 pre. 3/10 at 20min post.', 0, '2026-02-28 21:40:11', '2026-02-28 21:42:11'),
       (16, 18, 9, 7, 1, '2026-02-28 20:32:11', '2026-02-28 20:34:11', 'GIVEN', '1000', 'mg', 'IV', NULL, NULL, NULL, 1, 'TXA load given 14min post-arrival. BP 94→102 post-fluid.', 1, '2026-02-28 20:32:11', '2026-02-28 20:34:11'),
       (17, 29, 11, 9, 1, '2026-02-28 21:45:11', '2026-02-28 21:46:11', 'GIVEN', '345', 'mg', 'PO', NULL, NULL, NULL, 1, 'Taken well. No vomiting. Temp 101.2°F at 30 min post.', 0, '2026-02-28 21:45:11', '2026-02-28 21:46:11'),
       (18, 29, 12, 10, 1, '2026-02-28 20:58:12', '2026-02-28 21:00:12', 'GIVEN', '0.4', 'mg/hr', 'IV', NULL, NULL, NULL, 1, 'Drip initiated. RR 8→14 at 15min. SpO2 91→97%. Patient awake, combative.', 1, '2026-02-28 20:58:12', '2026-02-28 21:00:12'),
       (19, 9, 4, 2, 1, '2026-02-28 19:40:13', '2026-02-28 19:43:13', 'GIVEN', '1750', 'mg', 'IV', 'Right AC', 'VAN-2024-0891', NULL, 1, 'Infused over 90 min. No red man syndrome. Pre-dose level drawn.', 1, '2026-02-28 19:40:13', '2026-02-28 19:43:13'),
       (20, 9, 4, 2, 1, '2026-02-28 21:00:13', '2026-02-28 21:02:13', 'HELD', NULL, NULL, NULL, NULL, NULL, 'LEVEL_HIGH', 1, 'Pre-dose vancomycin level 22 — holding per pharmacy. Will re-dose when level < 15.', 1, '2026-02-28 21:00:13', '2026-02-28 21:02:13'),
       (21, 25, 10, 8, 1, '2026-02-28 18:20:13', '2026-02-28 18:21:13', 'GIVEN', '2.5', 'mg', 'INH', NULL, NULL, NULL, 1, '[Amended 2026-02-27 07:15:00 by user 1] Original entry: HELD/NPO in error — patient tolerating PO. Corrected to GIVEN after review of physician orders.', 0,
        '2026-02-28 18:20:13', '2026-02-28 18:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `oei_mar_order`
--

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
  AUTO_INCREMENT = 32
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_mar_order`
--

INSERT INTO `oei_mar_order` (`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`, `frequency`, `is_prn`, `status`, `ordered_datetime`, `discontinued_datetime`, `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`, `instructions`, `created_datetime`,
                             `updated_datetime`)
VALUES (1, 1, 2, 1, 'Vancomycin', '1500', 'mg', 'IV', 'Q8H', 0, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Infuse over 90 min. Monitor troughs. Sepsis dosing.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (2, 1, 2, 1, 'Piperacillin-Tazobactam', '3.375', 'g', 'IV', 'Q6H', 0, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Extended infusion over 4h. Start after blood cultures.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (3, 1, 2, 1, 'Normal Saline', '1000', 'mL', 'IV', 'Q4H', 0, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Maintenance fluid. Reassess after each bolus.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (4, 1, 2, 1, 'Norepinephrine', '0.05', 'mcg/kg/min', 'IV', 'CONTINUOUS', 0, 'ACTIVE', '2026-02-28 21:19:41', NULL, 1, NULL, NULL, 'Vasopressor for MAP < 65. Titrate to MAP 65-70. HIGH ALERT.', '2026-02-28 21:19:41', '2026-02-28 21:19:41'),
       (5, 2, 3, 1, 'Aspirin', '325', 'mg', 'PO', 'QD', 0, 'ACTIVE', '2026-02-28 00:19:41', NULL, 1, NULL, NULL, 'Chew first dose. Cardiac dosing.', '2026-02-28 00:19:41', '2026-02-28 00:19:41'),
       (6, 2, 3, 1, 'Metoprolol', '25', 'mg', 'PO', 'BID', 0, 'ACTIVE', '2026-02-28 02:19:41', NULL, 1, NULL, NULL, 'Hold for HR < 55 or SBP < 100.', '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (7, 2, 3, 1, 'Heparin', '5000', 'units', 'SQ', 'Q8H', 0, 'ACTIVE', '2026-02-28 02:19:41', NULL, 1, NULL, NULL, 'DVT prophylaxis. Check aPTT. HIGH ALERT.', '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (8, 2, 3, 1, 'Nitroglycerin', '0.4', 'mg', 'SL', 'PRN', 1, 'ACTIVE', '2026-02-28 00:19:41', NULL, 1, NULL, NULL, 'PRN chest pain. May repeat q5min x3. Hold SBP < 90.', '2026-02-28 00:19:41', '2026-02-28 00:19:41'),
       (9, 4, 2, 1, 'Vancomycin', '1500', 'mg', 'IV', 'Q8H', 0, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Infuse over 90 min. Monitor troughs. Sepsis dosing.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (10, 4, 2, 1, 'Piperacillin-Tazobactam', '3.375', 'g', 'IV', 'Q6H', 0, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Extended infusion over 4h. Start after blood cultures.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (11, 4, 2, 1, 'Normal Saline', '1000', 'mL', 'IV', 'Q4H', 0, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Maintenance fluid. Reassess after each bolus.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (12, 4, 2, 1, 'Norepinephrine', '0.05', 'mcg/kg/min', 'IV', 'CONTINUOUS', 0, 'ACTIVE', '2026-02-28 21:20:10', NULL, 1, NULL, NULL, 'Vasopressor for MAP < 65. Titrate to MAP 65-70. HIGH ALERT.', '2026-02-28 21:20:10', '2026-02-28 21:20:10'),
       (13, 5, 3, 1, 'Aspirin', '325', 'mg', 'PO', 'QD', 0, 'ACTIVE', '2026-02-28 00:20:10', NULL, 1, NULL, NULL, 'Chew first dose. Cardiac dosing.', '2026-02-28 00:20:10', '2026-02-28 00:20:10'),
       (14, 5, 3, 1, 'Metoprolol', '25', 'mg', 'PO', 'BID', 0, 'ACTIVE', '2026-02-28 02:20:10', NULL, 1, NULL, NULL, 'Hold for HR < 55 or SBP < 100.', '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (15, 5, 3, 1, 'Heparin', '5000', 'units', 'SQ', 'Q8H', 0, 'ACTIVE', '2026-02-28 02:20:10', NULL, 1, NULL, NULL, 'DVT prophylaxis. Check aPTT. HIGH ALERT.', '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (16, 5, 3, 1, 'Nitroglycerin', '0.4', 'mg', 'SL', 'PRN', 1, 'ACTIVE', '2026-02-28 00:20:10', NULL, 1, NULL, NULL, 'PRN chest pain. May repeat q5min x3. Hold SBP < 90.', '2026-02-28 00:20:10', '2026-02-28 00:20:10'),
       (17, 7, 5, 1, 'Ketorolac', '30', 'mg', 'IV', 'PRN', 1, 'ACTIVE', '2026-02-28 21:35:11', NULL, 1, NULL, NULL, 'PRN pain > 6/10. Max 5 days. NSAID — note allergy check passed.', '2026-02-28 21:35:11', '2026-02-28 21:35:11'),
       (18, 7, 5, 1, 'Ibuprofen', '600', 'mg', 'PO', 'Q6H', 0, 'ACTIVE', '2026-02-28 22:10:11', NULL, 1, NULL, NULL, 'Discharge prescription x 5 days. Take with food.', '2026-02-28 22:10:11', '2026-02-28 22:10:11'),
       (19, 8, 6, 1, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', '2026-02-28 22:00:11', NULL, 1, NULL, NULL, 'Maintenance. NO glucose-containing fluids in stroke.', '2026-02-28 22:00:11', '2026-02-28 22:00:11'),
       (20, 8, 6, 1, 'Labetalol', '10', 'mg', 'IV', 'PRN', 1, 'ACTIVE', '2026-02-28 22:00:11', NULL, 1, NULL, NULL, 'PRN SBP > 220 (pre-tPA) or > 180 (post-tPA). Give over 2 min.', '2026-02-28 22:00:11', '2026-02-28 22:00:11'),
       (21, 9, 7, 1, 'Tranexamic Acid (TXA)', '1000', 'mg', 'IV', 'ONCE', 0, 'ACTIVE', '2026-02-28 20:30:11', NULL, 1, NULL, NULL, 'Load 1g over 10 min within 3h of injury. HIGH ALERT.', '2026-02-28 20:30:11', '2026-02-28 20:30:11'),
       (22, 9, 7, 1, 'Lactated Ringers', '1000', 'mL', 'IV', 'BOLUS', 0, 'ACTIVE', '2026-02-28 20:25:11', NULL, 1, NULL, NULL, 'Permissive hypotension — target SBP 80-90 until OR.', '2026-02-28 20:25:11', '2026-02-28 20:25:11'),
       (23, 9, 7, 1, 'Morphine', '4', 'mg', 'IV', 'PRN', 1, 'ACTIVE', '2026-02-28 20:40:11', NULL, 1, NULL, NULL, 'PRN pain. Reassess GCS after. MAX 0.1mg/kg.', '2026-02-28 20:40:11', '2026-02-28 20:40:11'),
       (24, 9, 7, 1, 'Packed Red Blood Cells', '1', 'unit', 'IV', 'PRN', 1, 'ACTIVE', '2026-02-28 20:50:11', NULL, 1, NULL, NULL, 'O-neg until type confirmed. MTP protocol.', '2026-02-28 20:50:11', '2026-02-28 20:50:11'),
       (25, 10, 8, 1, 'Albuterol', '2.5', 'mg', 'INH', 'Q4H', 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'Neb treatment. Monitor HR. Wean to Q8H before discharge.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (26, 10, 8, 1, 'Ipratropium', '0.5', 'mg', 'INH', 'Q6H', 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'Neb with first 3 albuterol doses, then PRN.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (27, 10, 8, 1, 'Prednisone', '40', 'mg', 'PO', 'QD', 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'COPD exacerbation — 5-day course. No taper needed.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (28, 10, 8, 1, 'Azithromycin', '500', 'mg', 'PO', 'QD', 0, 'ACTIVE', '2026-02-28 11:20:11', NULL, 1, NULL, NULL, '5-day Z-pack. Atypical coverage for COPD exacerbation.', '2026-02-28 11:20:11', '2026-02-28 11:20:11'),
       (29, 11, 9, 1, 'Acetaminophen', '345', 'mg', 'PO', 'Q6H', 0, 'ACTIVE', '2026-02-28 21:42:11', NULL, 1, NULL, NULL, 'Pediatric dosing: 15mg/kg. Weight 23kg. Max 5 doses/24h.', '2026-02-28 21:42:11', '2026-02-28 21:42:11'),
       (30, 12, 10, 1, 'Naloxone', '0.4', 'mg/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', '2026-02-28 20:58:12', NULL, 1, NULL, NULL, 'Start 0.4mg/hr. Titrate for RR > 12. HIGH ALERT — monitor q15min. Max 2mg/hr. Wean when clinically appropriate.', '2026-02-28 20:58:12',
        '2026-02-28 20:58:12'),
       (31, 12, 10, 1, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', '2026-02-28 20:58:12', NULL, 1, NULL, NULL, 'Maintenance fluid.', '2026-02-28 20:58:12', '2026-02-28 20:58:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_obs_plan`
--

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
  AUTO_INCREMENT = 4
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_obs_plan`
--

INSERT INTO `oei_obs_plan` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `protocol_key`, `status`, `start_datetime`, `target_hours`, `runway_hours`, `protocol_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 2, 3, NULL, 1, 'CHEST_PAIN', 'ACTIVE', '2026-02-28 02:19:41', 24, 4, '{\"protocol_key\":\"CHEST_PAIN\",\"label\":\"Chest Pain / ACS Rule-Out\",\"target_hours\":24,\"runway_hours\":4}', 1, '2026-02-28 02:19:41'),
       (2, 5, 3, NULL, 1, 'CHEST_PAIN', 'ACTIVE', '2026-02-28 02:20:10', 24, 4, '{\"protocol_key\":\"CHEST_PAIN\",\"label\":\"Chest Pain / ACS Rule-Out\",\"target_hours\":24,\"runway_hours\":4}', 1, '2026-02-28 02:20:10'),
       (3, 10, 8, NULL, 1, 'COPD_EXACERBATION', 'ACTIVE', '2026-02-28 11:20:11', 24, 6, '{\"protocol_key\":\"COPD_EXACERBATION\",\"target_hours\":24,\"runway_hours\":6}', 1, '2026-02-28 11:20:11');

-- --------------------------------------------------------

--
-- Table structure for table `oei_patient_location_history`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `oei_protocol`
--

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
  AUTO_INCREMENT = 13
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_protocol`
--

INSERT INTO `oei_protocol` (`id`, `facility_id`, `protocol_key`, `label`, `version`, `enabled`, `definition_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'CHEST_PAIN', 'Chest Pain / ACS Rule-Out', '2.1', 1,
        '{\"protocol_key\":\"CHEST_PAIN\",\"label\":\"Chest Pain / ACS Rule-Out\",\"target_hours\":24,\"runway_hours\":4,\"tasks\":[{\"type\":\"EKG\",\"at_minutes\":[0,360],\"label\":\"12-Lead EKG\"},{\"type\":\"TROPONIN\",\"at_minutes\":[0,360,720],\"label\":\"Serial Troponin\"},{\"type\":\"VITALS_CHECK\",\"every_minutes\":240,\"label\":\"Vitals Q4H\"},{\"type\":\"DISPOSITION_DECISION\",\"at_minutes\":1320,\"label\":\"Cardiology consult or discharge decision\"}]}',
        1, '2026-02-28 22:20:10'),
       (2, 1, 'COPD_EXACERBATION', 'COPD Exacerbation Observation', '1.3', 1,
        '{\"protocol_key\":\"COPD_EXACERBATION\",\"label\":\"COPD Exacerbation Observation\",\"target_hours\":24,\"runway_hours\":6,\"tasks\":[{\"type\":\"SPIROMETRY\",\"at_minutes\":[0,360,720],\"label\":\"Peak Flow / Spirometry\"},{\"type\":\"ABG\",\"at_minutes\":[60],\"label\":\"Arterial Blood Gas\"},{\"type\":\"VITALS_CHECK\",\"every_minutes\":120,\"label\":\"Vitals Q2H\"},{\"type\":\"NEBS\",\"every_minutes\":240,\"label\":\"Albuterol Neb Treatment\"},{\"type\":\"DISPOSITION_DECISION\",\"at_minutes\":1080,\"label\":\"Admit vs discharge decision\"}]}',
        1, '2026-02-28 22:20:10'),
       (3, 1, 'SEPSIS_BUNDLE', 'Sepsis 3-Hour Bundle', '3.0', 1,
        '{\"protocol_key\":\"SEPSIS_BUNDLE\",\"label\":\"Sepsis 3-Hour Bundle\",\"target_hours\":3,\"runway_hours\":0.5,\"tasks\":[{\"type\":\"BLOOD_CULTURE\",\"at_minutes\":[0],\"label\":\"Blood Cultures x2\"},{\"type\":\"LACTATE\",\"at_minutes\":[0],\"label\":\"Serum Lactate\"},{\"type\":\"IV_FLUID\",\"at_minutes\":[0],\"label\":\"30mL/kg IV Fluid Bolus\"},{\"type\":\"ANTIBIOTICS\",\"at_minutes\":[0],\"label\":\"Broad-Spectrum Antibiotics\"},{\"type\":\"VITALS_CHECK\",\"every_minutes\":60,\"label\":\"Vitals Q1H\"}]}',
        1, '2026-02-28 22:20:10'),
       (4, 1, 'STROKE_ALERT', 'Code Stroke Protocol', '2.5', 1,
        '{\"protocol_key\":\"STROKE_ALERT\",\"label\":\"Code Stroke Protocol\",\"target_hours\":1,\"runway_hours\":0.25,\"tasks\":[{\"type\":\"CT_HEAD_STAT\",\"at_minutes\":[0],\"label\":\"Non-contrast CT Head STAT\"},{\"type\":\"CT_ANGIO\",\"at_minutes\":[15],\"label\":\"CTA Head & Neck\"},{\"type\":\"NIHSS_SCORE\",\"at_minutes\":[0],\"label\":\"NIHSS Assessment\"},{\"type\":\"NEUROLOGY_CONSULT\",\"at_minutes\":[0],\"label\":\"Neurology Consult\"},{\"type\":\"IV_ACCESS\",\"at_minutes\":[0],\"label\":\"Two large-bore IVs\"},{\"type\":\"LABS_STAT\",\"at_minutes\":[0],\"label\":\"Coags / CBC / BMP STAT\"},{\"type\":\"TPA_DECISION\",\"at_minutes\":[30],\"label\":\"tPA Eligibility Assessment\"}]}',
        1, '2026-02-28 22:20:10'),
       (5, 1, 'OPIOID_OVERDOSE', 'Opioid Overdose / Naloxone Protocol', '1.1', 1,
        '{\"protocol_key\":\"OPIOID_OVERDOSE\",\"label\":\"Opioid Overdose Protocol\",\"target_hours\":4,\"runway_hours\":1,\"tasks\":[{\"type\":\"VITALS_CHECK\",\"every_minutes\":30,\"label\":\"Vitals Q30min (watch re-sedation)\"},{\"type\":\"NALOXONE_TITRATE\",\"at_minutes\":[0],\"label\":\"Naloxone Drip Titration\"},{\"type\":\"BH_CONSULT\",\"at_minutes\":[60],\"label\":\"Behavioral Health Consult\"},{\"type\":\"NARCAN_EDUCATION\",\"at_minutes\":[180],\"label\":\"Narcan Rx and education if discharge\"}]}',
        1, '2026-02-28 22:20:10'),
       (6, 1, 'PEDIATRIC_FEVER', 'Pediatric Fever Protocol (< 13y)', '1.0', 1,
        '{\"protocol_key\":\"PEDIATRIC_FEVER\",\"label\":\"Pediatric Fever Protocol\",\"target_hours\":4,\"runway_hours\":0.5,\"tasks\":[{\"type\":\"TEMP_RECHECK\",\"at_minutes\":[60],\"label\":\"Temperature Recheck\"},{\"type\":\"UA_RESULT\",\"at_minutes\":[30],\"label\":\"UA / Culture Review\"},{\"type\":\"CBC_REVIEW\",\"at_minutes\":[45],\"label\":\"CBC with Differential\"},{\"type\":\"ANTIPYRETIC\",\"at_minutes\":[0],\"label\":\"Acetaminophen or Ibuprofen\"}]}',
        1, '2026-02-28 22:20:10');

-- --------------------------------------------------------

--
-- Table structure for table `oei_schema_version`
--

CREATE TABLE IF NOT EXISTS `oei_schema_version`
(
    `version`          varchar(20) NOT NULL,
    `applied_datetime` datetime    NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_schema_version`
--

INSERT INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.0.0-demo', '2026-02-28 22:20:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_settings`
--

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
  AUTO_INCREMENT = 34
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_settings`
--

INSERT INTO `oei_settings` (`id`, `facility_id`, `setting_key`, `setting_value`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'facility_name', 'Community Memorial Hospital ED', 1, '2026-02-28 22:20:10'),
       (2, 1, 'door_to_room_target_min', '30', 1, '2026-02-28 22:20:10'),
       (3, 1, 'door_to_provider_target_min', '60', 1, '2026-02-28 22:20:10'),
       (4, 1, 'lwbs_threshold_min', '120', 1, '2026-02-28 22:20:10'),
       (5, 1, 'obs_runway_warning_hours', '4', 1, '2026-02-28 22:20:10'),
       (6, 1, 'boarding_alert_hours', '4', 1, '2026-02-28 22:20:10'),
       (7, 1, 'esi_high_acuity_max', '2', 1, '2026-02-28 22:20:10'),
       (8, 1, 'vitals_interval_ed_min', '120', 1, '2026-02-28 22:20:10'),
       (9, 1, 'vitals_interval_obs_min', '240', 1, '2026-02-28 22:20:10'),
       (10, 1, 'vitals_window_hours', '12', 1, '2026-02-28 22:20:10'),
       (11, 1, 'hl7_enabled', '0', 1, '2026-02-28 22:20:10'),
       (12, 1, 'hl7_transport', 'MLLP', 1, '2026-02-28 22:20:10'),
       (13, 1, 'hl7_mllp_host', '127.0.0.1', 1, '2026-02-28 22:20:10'),
       (14, 1, 'hl7_mllp_port', '2575', 1, '2026-02-28 22:20:10'),
       (15, 1, 'hl7_processing_id', 'T', 1, '2026-02-28 22:20:10'),
       (31, 1, 'triage_color_ESI_1', '{\"bg\": \"#7B0000\", \"fg\": \"#FFFFFF\"}', 1, '2026-02-25 22:20:13'),
       (32, 1, 'triage_color_ESI_2', '{\"bg\": \"#E65100\", \"fg\": \"#FFFFFF\"}', 1, '2026-02-25 22:20:13'),
       (33, 1, 'triage_color_ESI_3', '{\"bg\": \"#F9A825\", \"fg\": \"#212121\"}', 1, '2026-02-25 22:20:13');

-- --------------------------------------------------------

--
-- Table structure for table `oei_task`
--

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
  AUTO_INCREMENT = 67
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_task`
--

INSERT INTO `oei_task` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`, `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`, `created_by_user_id`, `created_datetime`)
VALUES (1, 1, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 20:49:41', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"2 sets before antibiotics — OVERDUE\"}', 1, '2026-02-28 19:27:41'),
       (2, 1, 2, NULL, 1, 'LACTATE', '2026-02-28 20:19:41', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"Serum lactate — sepsis bundle\"}', 1, '2026-02-28 19:27:41'),
       (3, 1, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:39:41', NULL, NULL, 'COMPLETE', '{\"note\":\"30mL/kg NS bolus completed\",\"ml_given\":2400}', 1, '2026-02-28 19:27:41'),
       (4, 1, 2, NULL, 1, 'ANTIBIOTICS', '2026-02-28 19:39:41', NULL, NULL, 'COMPLETE', '{\"note\":\"Vancomycin 1500mg + Pip-Tazo 3.375g started\"}', 1, '2026-02-28 19:27:41'),
       (5, 1, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:49:41', NULL, NULL, 'OPEN', '{\"source\":\"auto\",\"priority\":\"URGENT\"}', 1, '2026-02-28 19:27:41'),
       (6, 1, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 22:49:41', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 19:27:41'),
       (7, 1, 2, NULL, 1, 'CHEST_XRAY', '2026-02-28 21:19:41', NULL, NULL, 'COMPLETE', '{\"result\":\"Bilateral infiltrates consistent with pneumonia / ARDS\"}', 1, '2026-02-28 19:27:41'),
       (8, 1, 2, NULL, 1, 'ICU_BED_REQUEST', '2026-02-28 21:49:41', NULL, NULL, 'OPEN', '{\"priority\":\"URGENT\",\"note\":\"Patient likely needs ICU. Awaiting bed.\"}', 1, '2026-02-28 21:19:41'),
       (9, 2, 3, NULL, 1, 'EKG', '2026-02-28 02:19:41', NULL, NULL, 'COMPLETE', '{\"result\":\"NSR, no acute changes, no STEMI\"}', 1, '2026-02-28 02:19:41'),
       (10, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 02:19:41', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #1\",\"result\":\"0.012 ng/mL (normal < 0.04)\"}', 1, '2026-02-28 02:19:41'),
       (11, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 08:19:41', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #2\",\"result\":\"0.010 ng/mL — negative trend\"}', 1, '2026-02-28 02:19:41'),
       (12, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 14:19:41', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #3\",\"result\":\"0.009 ng/mL — rule-out complete\"}', 1, '2026-02-28 02:19:41'),
       (13, 2, 3, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:19:41', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 02:19:41'),
       (14, 2, 3, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 20:19:41', NULL, NULL, 'OPEN', '{\"label\":\"Cardiology consult completed. Discharge with f/u vs stress test?\"}', 1, '2026-02-28 02:19:41'),
       (15, 4, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 20:50:10', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"2 sets before antibiotics — OVERDUE\"}', 1, '2026-02-28 19:28:10'),
       (16, 4, 2, NULL, 1, 'LACTATE', '2026-02-28 20:20:10', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"Serum lactate — sepsis bundle\"}', 1, '2026-02-28 19:28:10'),
       (17, 4, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:40:10', NULL, NULL, 'COMPLETE', '{\"note\":\"30mL/kg NS bolus completed\",\"ml_given\":2400}', 1, '2026-02-28 19:28:10'),
       (18, 4, 2, NULL, 1, 'ANTIBIOTICS', '2026-02-28 19:40:10', NULL, NULL, 'COMPLETE', '{\"note\":\"Vancomycin 1500mg + Pip-Tazo 3.375g started\"}', 1, '2026-02-28 19:28:10'),
       (19, 4, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:50:10', NULL, NULL, 'OPEN', '{\"source\":\"auto\",\"priority\":\"URGENT\"}', 1, '2026-02-28 19:28:10'),
       (20, 4, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 22:50:10', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 19:28:10'),
       (21, 4, 2, NULL, 1, 'CHEST_XRAY', '2026-02-28 21:20:10', NULL, NULL, 'COMPLETE', '{\"result\":\"Bilateral infiltrates consistent with pneumonia / ARDS\"}', 1, '2026-02-28 19:28:10'),
       (22, 4, 2, NULL, 1, 'ICU_BED_REQUEST', '2026-02-28 21:50:10', NULL, NULL, 'OPEN', '{\"priority\":\"URGENT\",\"note\":\"Patient likely needs ICU. Awaiting bed.\"}', 1, '2026-02-28 21:20:10'),
       (23, 5, 3, NULL, 1, 'EKG', '2026-02-28 02:20:10', NULL, NULL, 'COMPLETE', '{\"result\":\"NSR, no acute changes, no STEMI\"}', 1, '2026-02-28 02:20:10'),
       (24, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 02:20:10', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #1\",\"result\":\"0.012 ng/mL (normal < 0.04)\"}', 1, '2026-02-28 02:20:10'),
       (25, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 08:20:10', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #2\",\"result\":\"0.010 ng/mL — negative trend\"}', 1, '2026-02-28 02:20:10'),
       (26, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 14:20:10', NULL, NULL, 'COMPLETE', '{\"label\":\"Troponin #3\",\"result\":\"0.009 ng/mL — rule-out complete\"}', 1, '2026-02-28 02:20:10'),
       (27, 5, 3, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:20:10', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 02:20:10'),
       (28, 5, 3, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 20:20:10', NULL, NULL, 'OPEN', '{\"label\":\"Cardiology consult completed. Discharge with f/u vs stress test?\"}', 1, '2026-02-28 02:20:10'),
       (29, 6, 4, NULL, 1, 'BH_SAFETY_SCREEN', '2026-02-28 17:30:11', NULL, NULL, 'COMPLETE', '{\"note\":\"Columbia SSRS completed — high risk\"}', 1, '2026-02-28 17:25:11'),
       (30, 6, 4, NULL, 1, 'BH_PLACEMENT_CALL', '2026-02-28 20:20:11', NULL, NULL, 'OPEN', '{\"priority\":\"HIGH\",\"note\":\"Re-call Valley BH — waitlist update\"}', 1, '2026-02-28 17:30:11'),
       (31, 6, 4, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:20:11', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 17:30:11'),
       (32, 6, 4, NULL, 1, 'EMTALA_DOCUMENTATION', '2026-02-28 18:20:11', NULL, NULL, 'COMPLETE', '{\"note\":\"MSE complete, signed\"}', 1, '2026-02-28 17:25:11'),
       (33, 7, 5, NULL, 1, 'X_RAY_ORDER', '2026-02-28 21:40:11', NULL, NULL, 'COMPLETE', '{\"label\":\"Right ankle 3 views\",\"result\":\"No acute fracture. Soft tissue swelling lateral malleolus.\"}', 1, '2026-02-28 21:25:11'),
       (34, 7, 5, NULL, 1, 'X_RAY_REVIEW', '2026-02-28 22:10:11', NULL, NULL, 'COMPLETE', '{\"label\":\"Radiology read reviewed with patient\"}', 1, '2026-02-28 21:25:11'),
       (35, 7, 5, NULL, 1, 'DISCHARGE_INSTRUCTIONS', '2026-02-28 22:15:11', NULL, NULL, 'COMPLETE', '{\"label\":\"Ankle sprain instructions printed and reviewed\"}', 1, '2026-02-28 21:25:11'),
       (36, 8, 6, NULL, 1, 'NIHSS_SCORE', '2026-02-28 22:00:11', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"Initial NIHSS — neurology at bedside\"}', 1, '2026-02-28 21:55:11'),
       (37, 8, 6, NULL, 1, 'CT_HEAD_STAT', '2026-02-28 22:05:11', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"Non-contrast CT head — no contrast if tPA candidate\"}', 1, '2026-02-28 21:55:11'),
       (38, 8, 6, NULL, 1, 'CT_ANGIO', '2026-02-28 22:10:11', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"CTA head and neck for LVO workup\"}', 1, '2026-02-28 21:55:11'),
       (39, 8, 6, NULL, 1, 'LABS_STAT', '2026-02-28 22:00:11', NULL, NULL, 'OPEN', '{\"priority\":\"STAT\",\"note\":\"CBC, BMP, coags, type & screen STAT\"}', 1, '2026-02-28 21:55:11'),
       (40, 8, 6, NULL, 1, 'IV_ACCESS', '2026-02-28 21:58:11', NULL, NULL, 'COMPLETE', '{\"note\":\"Two 18g IVs — right AC and right hand\"}', 1, '2026-02-28 21:55:11'),
       (41, 8, 6, NULL, 1, 'NEUROLOGY_CONSULT', '2026-02-28 21:55:11', NULL, NULL, 'COMPLETE', '{\"note\":\"Neurology on scene — evaluating\"}', 1, '2026-02-28 21:55:11'),
       (42, 8, 6, NULL, 1, 'TPA_DECISION', '2026-02-28 22:25:11', NULL, NULL, 'OPEN', '{\"label\":\"tPA eligibility decision — LKW 90min, CT result pending\"}', 1, '2026-02-28 21:55:11'),
       (43, 9, 7, NULL, 1, 'FAST_EXAM', '2026-02-28 20:25:11', NULL, NULL, 'COMPLETE', '{\"result\":\"FAST positive — free fluid LUQ and pelvis\"}', 1, '2026-02-28 20:23:11'),
       (44, 9, 7, NULL, 1, 'CT_ABDOMEN_STAT', '2026-02-28 20:50:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Grade III splenic laceration. Active extravasation. Surgical consult.\"}', 1, '2026-02-28 20:23:11'),
       (45, 9, 7, NULL, 1, 'BLOOD_BANK', '2026-02-28 20:30:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Type O-neg x2 units released. MTP activated.\"}', 1, '2026-02-28 20:23:11'),
       (46, 9, 7, NULL, 1, 'TRANSFER_ACCEPT', '2026-02-28 21:50:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Regional Trauma Center accepted. Transport ETA 15 min.\"}', 1, '2026-02-28 21:20:11'),
       (47, 9, 7, NULL, 1, 'TRANSFER_TRANSPORT', '2026-02-28 22:35:11', NULL, NULL, 'OPEN', '{\"note\":\"Medic 7 transport. Trauma surgeon standing by at RTC.\"}', 1, '2026-02-28 21:50:11'),
       (48, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 11:20:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Peak flow 38% predicted (baseline)\"}', 1, '2026-02-28 11:20:11'),
       (49, 10, 8, NULL, 1, 'ABG', '2026-02-28 12:20:11', NULL, NULL, 'COMPLETE', '{\"result\":\"pH 7.38, pCO2 52, pO2 68, HCO3 31 — compensated\"}', 1, '2026-02-28 11:20:11'),
       (50, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 17:20:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Peak flow 55% predicted — improving\"}', 1, '2026-02-28 11:20:11'),
       (51, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 21:20:11', NULL, NULL, 'COMPLETE', '{\"result\":\"Peak flow 62% predicted — ready for discharge eval\"}', 1, '2026-02-28 11:20:11'),
       (52, 10, 8, NULL, 1, 'VITALS_CHECK', '2026-02-28 20:20:11', NULL, NULL, 'COMPLETE', '{\"source\":\"auto\"}', 1, '2026-02-28 11:20:11'),
       (53, 10, 8, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 22:50:11', NULL, NULL, 'OPEN', '{\"note\":\"Discharge home with home health if peak flow > 60%\"}', 1, '2026-02-28 20:20:11'),
       (54, 11, 9, NULL, 1, 'UA_RESULT', '2026-02-28 22:30:11', NULL, NULL, 'OPEN', '{\"note\":\"Catheter UA — awaiting result\"}', 1, '2026-02-28 21:42:11'),
       (55, 11, 9, NULL, 1, 'CBC_REVIEW', '2026-02-28 22:40:11', NULL, NULL, 'OPEN', '{\"note\":\"CBC with diff to r/o bacteremia\"}', 1, '2026-02-28 21:42:11'),
       (56, 11, 9, NULL, 1, 'ANTIPYRETIC', '2026-02-28 21:45:11', NULL, NULL, 'COMPLETE', '{\"note\":\"Acetaminophen given — temp 101.2 at 30min post-dose\"}', 1, '2026-02-28 21:42:11'),
       (57, 11, 9, NULL, 1, 'TEMP_RECHECK', '2026-02-28 22:35:11', NULL, NULL, 'OPEN', '{\"source\":\"auto\"}', 1, '2026-02-28 21:42:11'),
       (58, 12, 10, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:50:12', NULL, NULL, 'OPEN', '{\"priority\":\"URGENT\",\"note\":\"Re-sedation watch — Narcan shorter half-life than opiates\"}', 1, '2026-02-28 20:54:12'),
       (59, 12, 10, NULL, 1, 'NALOXONE_TITRATE', '2026-02-28 21:20:12', NULL, NULL, 'COMPLETE', '{\"note\":\"Drip started 0.1mg/hr. Titrating to adequate respiratory rate.\"}', 1, '2026-02-28 20:54:12'),
       (60, 12, 10, NULL, 1, 'BH_CONSULT', '2026-02-28 22:50:12', NULL, NULL, 'OPEN', '{\"priority\":\"ROUTINE\",\"note\":\"BH consult for MOUD discussion when patient cooperative\"}', 1, '2026-02-28 20:54:12'),
       (61, 12, 10, NULL, 1, 'NARCAN_EDUCATION', '2026-03-01 00:20:12', NULL, NULL, 'OPEN', '{\"note\":\"Naloxone Rx and overdose education — defer until patient cooperative\"}', 1, '2026-02-28 20:54:12'),
       (62, 13, 11, NULL, 1, 'BH_SAFETY_SCREEN', '2026-02-28 15:20:12', NULL, NULL, 'COMPLETE', '{\"note\":\"Columbia SSRS — moderate risk, no plan\"}', 1, '2026-02-28 14:25:12'),
       (63, 13, 11, NULL, 1, 'EMTALA_DOCUMENTATION', '2026-02-28 15:20:12', NULL, NULL, 'COMPLETE', '{\"note\":\"MSE complete, signed by attending\"}', 1, '2026-02-28 14:25:12'),
       (64, 13, 11, NULL, 1, 'BH_PLACEMENT_CALL', '2026-02-28 16:20:12', NULL, NULL, 'COMPLETE', '{\"note\":\"Called Valley BH, Riverside BH, State Hospital\"}', 1, '2026-02-28 14:25:12'),
       (65, 13, 11, NULL, 1, 'VITALS_CHECK', '2026-02-28 20:20:12', NULL, NULL, 'COMPLETE', '{\"source\":\"auto\",\"note\":\"Stable vitals x8h\"}', 1, '2026-02-28 14:25:12'),
       (66, 13, 11, NULL, 1, 'TRANSPORT_CONFIRM', '2026-02-28 22:35:12', NULL, NULL, 'OPEN', '{\"note\":\"Confirm Medvan arrival with driver — patient ready\"}', 1, '2026-02-28 20:50:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_transfer`
--

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
  AUTO_INCREMENT = 2
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

--
-- Dumping data for table `oei_transfer`
--

INSERT INTO `oei_transfer` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `transfer_type`, `reason`, `receiving_directory_id`, `receiving_name`, `requested_datetime`, `accepted_datetime`, `transport_datetime`, `status`, `checklist_json`, `notes`, `updated_by_user_id`,
                            `updated_datetime`)
VALUES (1, 9, 7, NULL, 1, 'TRANSFER', 'Grade III splenic laceration requiring surgical intervention', NULL, 'Regional Trauma Center', '2026-02-28 21:20:11', '2026-02-28 21:50:11', NULL, 'ACCEPTED',
        '{\"items\":[{\"label\":\"Accepting physician identified\",\"done\":true},{\"label\":\"Report given to receiving team\",\"done\":true},{\"label\":\"Transfer consent signed\",\"done\":true},{\"label\":\"Records copied\",\"done\":true},{\"label\":\"Transport unit confirmed\",\"done\":true},{\"label\":\"Stable for transport\",\"done\":true}]}',
        'Dr. Reyes at RTC accepted directly. MTP ongoing — Hgb 7.8. 2u pRBC transfused. Transport ETA 15 min.', 1, '2026-02-28 22:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `oei_triage`
--

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
  AUTO_INCREMENT = 16
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Triage vitals sets; multiple per episode for re-triage support';

--
-- Dumping data for table `oei_triage`
--

INSERT INTO `oei_triage` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `set_number`, `bp_systolic`, `bp_diastolic`, `hr`, `rr`, `temp_f`, `spo2`, `gcs`, `pain_score`, `weight_kg`, `arrival_mode`, `esi_suggested`, `notes`, `noted_by_user_id`, `noted_datetime`)
VALUES (1, 1, 2, NULL, 1, 1, 88, 58, 122, 26, 102.40, 92, 12, 5, 82.00, 'EMS', 2,
        'Patient confused, combative on arrival. Diaphoretic. Warm, mottled extremities. Family reports 3-day productive cough, chills, progressive confusion. qSOFA: RR 26, GCS < 15, SBP 88. Sepsis bundle initiated.', 1, '2026-02-28 19:24:41'),
       (2, 2, 3, NULL, 1, 1, 154, 94, 98, 16, 98.60, 97, 15, 7, 78.00, 'WALKIN', 3, 'Substernal pressure radiating to left shoulder x 2h. Diaphoresis. Denies SOB. HTN, DM, hyperlipidemia hx. ASA/metformin at home.', 1, '2026-02-28 00:19:41'),
       (3, 2, 3, NULL, 1, 2, 138, 84, 78, 14, 98.20, 99, 15, 3, 78.00, 'WALKIN', 3, 'Re-triage 12h: chest pressure resolved. Ambulating. Tolerating clear diet. Awaiting final troponin and cardiology input.', 1, '2026-02-28 12:19:41'),
       (4, 4, 2, NULL, 1, 1, 88, 58, 122, 26, 102.40, 92, 12, 5, 82.00, 'EMS', 2,
        'Patient confused, combative on arrival. Diaphoretic. Warm, mottled extremities. Family reports 3-day productive cough, chills, progressive confusion. qSOFA: RR 26, GCS < 15, SBP 88. Sepsis bundle initiated.', 1, '2026-02-28 19:25:10'),
       (5, 5, 3, NULL, 1, 1, 154, 94, 98, 16, 98.60, 97, 15, 7, 78.00, 'WALKIN', 3, 'Substernal pressure radiating to left shoulder x 2h. Diaphoresis. Denies SOB. HTN, DM, hyperlipidemia hx. ASA/metformin at home.', 1, '2026-02-28 00:20:10'),
       (6, 5, 3, NULL, 1, 2, 138, 84, 78, 14, 98.20, 99, 15, 3, 78.00, 'WALKIN', 3, 'Re-triage 12h: chest pressure resolved. Ambulating. Tolerating clear diet. Awaiting final troponin and cardiology input.', 1, '2026-02-28 12:20:10'),
       (7, 6, 4, NULL, 1, 1, 128, 80, 90, 14, 98.30, 99, 15, 0, NULL, 'POLICE', 3,
        'Calm and cooperative. Reports SI — \"I have a plan and the means.\" Denies HI. Last EtOH 8h prior. Chronic depression, hx of one prior attempt 2yo. No acute medical complaints. Patient consented to evaluation.', 1, '2026-02-28 17:21:10'),
       (8, 7, 5, NULL, 1, 1, 116, 72, 80, 14, 98.00, 100, 15, 7, 62.00, 'WALKIN', 4, 'Twisted right ankle on stairs 2h ago. Lateral malleolus swelling and bruising. NWB. Neurovascularly intact. Ottawa rules: negative for fracture criteria. Pain 7/10.', 1, '2026-02-28 21:20:11'),
       (9, 8, 6, NULL, 1, 1, 192, 108, 86, 18, 98.80, 96, 13, 0, 89.00, 'EMS', 1, 'Sudden onset left arm and leg weakness with facial droop and dysarthria. Last known well 0930 — 90 min ago. AF on EKG strip by EMS. BP 192/108. GCS 13. Right gaze deviation. Witnessed by wife.', 1,
        '2026-02-28 21:57:11'),
       (10, 9, 7, NULL, 1, 1, 94, 60, 128, 22, 98.10, 95, 14, 9, 70.00, 'EMS', 2, 'Driver, high-speed MVC, airbag, +LOC x 2 min. Complains left upper quadrant pain. C-collar in place per EMS. GCS 14 (E4V4M6). Abdomen tender LUQ, guarding.', 1, '2026-02-28 20:22:11'),
       (11, 10, 8, NULL, 1, 1, 142, 88, 104, 24, 99.10, 88, 15, 3, 88.00, 'WALKIN', 3, 'COPD patient, severe — FEV1 38% predicted at baseline. SOB worse x 3d. Yellow productive cough. On home nebs and Spiriva. SpO2 88% RA, improved to 94% on 2L NC.', 1, '2026-02-28 10:21:11'),
       (12, 10, 8, NULL, 1, 2, 138, 82, 88, 18, 98.80, 96, 15, 1, 88.00, 'WALKIN', 3, 'Re-triage 6h: SpO2 96% 2L NC. HR improved 88. Wheezes markedly decreased. Patient able to speak full sentences. Peak flow 55% → 62% predicted.', 1, '2026-02-28 16:20:11'),
       (13, 11, 9, NULL, 1, 1, 96, 60, 118, 22, 103.80, 99, 15, 4, 23.00, 'WALKIN', 3,
        'Age 7. Fever 103.8°F, onset 24h ago. Dysuria and increased frequency x 2d. Mild suprapubic tenderness. No CVA tenderness. No vomiting. Tolerating PO. Alert and interactive. PMH: none. No allergies.', 1, '2026-02-28 21:38:11'),
       (14, 12, 10, NULL, 1, 1, 108, 68, 96, 10, 97.60, 91, 6, 0, 78.00, 'EMS', 2, 'Found unresponsive bathroom. EMS: pinpoint pupils, shallow respirations RR 6, O2 sat 84%. Narcan 0.4mg IM x2 — GCS improved to 14. Denies substance use when awake. Paraphernalia found at scene.',
        1, '2026-02-28 20:52:12'),
       (15, 13, 11, NULL, 1, 1, 118, 74, 82, 14, 98.20, 100, 15, 0, NULL, 'WALKIN', 3,
        'Voluntary presentation. Reports passive SI — \"I don\'t want to be here anymore\" — but no plan or intent. Depression x 6 months, recently lost job. No psych history, no substances. Calm, cooperative, insightful. Family supportive and present.', 1,
        '2026-02-28 14:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `oei_user_context`
--

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
  AUTO_INCREMENT = 9
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user care context preference per facility';

--
-- Dumping data for table `oei_user_context`
--

INSERT INTO `oei_user_context` (`id`, `user_id`, `facility_id`, `context_key`, `updated_datetime`)
VALUES (1, 1, 1, 'ED_ACUTE', '2026-02-28 22:20:10'),
       (2, 2, 1, 'OPERATIONS', '2026-02-28 22:20:10'),
       (3, 3, 1, 'OBS_STAY', '2026-02-28 22:20:10'),
       (4, 4, 1, 'BH', '2026-02-28 22:20:10');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT = @OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS = @OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION = @OLD_COLLATION_CONNECTION */;

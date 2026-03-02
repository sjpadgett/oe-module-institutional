-- =============================================================================
-- oe-module-institutional — Assisted Living tables
-- Version: 0.11.0
-- =============================================================================
--
-- INTEGRATION STRATEGY
-- We do NOT create our own care_plan or care_team tables.
-- OpenEMR ships fully certified:
--   form_care_plan     — goal/intervention rows (CCDA, FHIR, eCQM)
--   care_teams         — patient-level care team (USCDI v3 compliant)
--   care_team_member   — member rows with SNOMED-CT roles
--   care_plan_status   — list_options for plan_status values
--
-- Our oei_al_episode links an oei_episode to a form_encounter,
-- which in turn anchors form_care_plan entries for that stay.
-- =============================================================================

-- ── oei_al_episode  —  AL overlay on oei_episode ───────────────────────────
--
-- One row per AL episode. Extends oei_episode with AL-specific metadata.
-- encounter_id FK → form_encounter.id (the admission encounter that anchors
-- care plan entries for this stay).

CREATE TABLE IF NOT EXISTS `oei_al_episode`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`              bigint(20) UNSIGNED NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `encounter_id`     bigint(20) UNSIGNED          DEFAULT NULL
                       COMMENT 'FK → form_encounter.id — anchors form_care_plan entries',
    `room`             varchar(20)                  DEFAULT NULL,
    `unit`             varchar(40)                  DEFAULT NULL,
    `care_level`       enum('TIER_1','TIER_2','TIER_3') NOT NULL DEFAULT 'TIER_1'
                       COMMENT 'CareLevel domain: Low / Medium / High',
    `fall_risk_level`  enum('LOW','MODERATE','HIGH')    NOT NULL DEFAULT 'LOW'
                       COMMENT 'Morse Fall Scale tier',
    `fall_risk_score`  tinyint(3) UNSIGNED          NOT NULL DEFAULT 0
                       COMMENT 'Raw Morse Fall Scale total (0-125)',
    `admit_reason`     varchar(255)                 DEFAULT NULL,
    `last_adl_score`   tinyint(3) UNSIGNED          DEFAULT NULL
                       COMMENT 'Cached aggregate ADL score from latest oei_adl_record',
    `last_adl_datetime` datetime                    DEFAULT NULL,
    `created_datetime` datetime                     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_al_episode` (`episode_id`),
    KEY `idx_oei_al_facility`      (`facility_id`, `care_level`),
    KEY `idx_oei_al_room`          (`facility_id`, `unit`, `room`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'AL-specific overlay on oei_episode; links to form_encounter for care plan anchoring';


-- ── oei_adl_record  —  ADL charting per shift ──────────────────────────────
--
-- One row = one charting session by one aide.
-- adl_json stores domain→level map (MDS 3.0 coding 0/1/2/3/4/8).
-- adl_score = precomputed AdlLevel::aggregateScore() for fast board queries.
-- Domains: bathing, dressing, grooming, transfer, ambulation, eating, toileting

CREATE TABLE IF NOT EXISTS `oei_adl_record`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `noted_by_user_id`   bigint(20) UNSIGNED DEFAULT NULL COMMENT 'FK → users.id (aide/nurse)',
    `noted_datetime`     datetime            NOT NULL,
    `adl_json`           json                NOT NULL
                         COMMENT 'domain→level map, e.g. {"bathing":2,"dressing":1,...}',
    `adl_score`          tinyint(3) UNSIGNED NOT NULL DEFAULT 0
                         COMMENT 'Aggregate 0–28; see AdlLevel::aggregateScore()',
    `notes`              text                DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_adl_episode`  (`episode_id`, `noted_datetime`),
    KEY `idx_oei_adl_facility` (`facility_id`, `noted_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'ADL charting sessions; one row per aide session covering all 7 domains';


-- ── oei_incident  —  AL incident reports ───────────────────────────────────
--
-- State AL licensing requires incident reports (typically within 24–72 h)
-- for falls with injury, elopement, abuse/neglect, and deaths.
-- mandatory_report_sent tracks whether the state notification was filed.

CREATE TABLE IF NOT EXISTS `oei_incident`
(
    `id`                    bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`            bigint(20) UNSIGNED  NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`           bigint(20) UNSIGNED  NOT NULL,
    `reported_by_user_id`   bigint(20) UNSIGNED  DEFAULT NULL COMMENT 'FK → users.id',
    `incident_type`         varchar(30)          NOT NULL
                            COMMENT 'IncidentType constant: FALL|FALL_INJURY|ELOPEMENT|MED_ERROR|…',
    `severity`              enum('LOW','MODERATE','HIGH','CRITICAL') NOT NULL DEFAULT 'MODERATE',
    `incident_datetime`     datetime             NOT NULL
                            COMMENT 'When the incident occurred',
    `location_description`  varchar(120)         DEFAULT NULL,
    `narrative`             text                 DEFAULT NULL
                            COMMENT 'Factual description of what happened',
    `corrective_action`     text                 DEFAULT NULL
                            COMMENT 'Immediate corrective action taken',
    `reported_state`        enum('PENDING','REPORTED','NOT_REQUIRED') NOT NULL DEFAULT 'PENDING',
    `mandatory_report_sent` tinyint(1)           NOT NULL DEFAULT 0
                            COMMENT '1 = state notification filed',
    `created_datetime`      datetime             NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_incident_episode`  (`episode_id`),
    KEY `idx_oei_incident_facility` (`facility_id`, `incident_datetime`),
    KEY `idx_oei_incident_type`     (`facility_id`, `incident_type`, `severity`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'AL incident reports; mandatory_report_sent tracks state notification status';

-- =============================================================================
-- oei_user_context — add ASSISTED_LIVING to allowed context_key values
-- ALTER is safe; ENUM extension is backward-compatible in MySQL/MariaDB.
-- =============================================================================
ALTER TABLE `oei_user_context`
    MODIFY COLUMN `context_key` varchar(30) NOT NULL DEFAULT 'FULL';
-- Note: we use varchar(30) (already the column type) so no ALTER is needed
-- for the column itself — we only need to ensure ASSISTED_LIVING is a valid
-- string value, which CareContext::isValid() enforces in PHP.

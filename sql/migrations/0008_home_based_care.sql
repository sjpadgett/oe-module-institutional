-- =============================================================================
-- 0008_home_based_care.sql
-- Home-Based Care (HBC) module — Phase 1 schema
--
-- Two new overlay tables that follow the established oei_*_episode pattern:
--
--   oei_hbc_episode — episode-level referral data, service address,
--                     caregiver contact, authorization, cert period.
--
--   oei_hbc_visit   — individual visit logistics: one row per scheduled
--                     clinical encounter. Includes offline-draft JSON,
--                     GPS coords for visit verification, and patient
--                     signature tracking.
--
-- Episode type added to oei_episode.type: 'HBC'
--
-- Address model is international (freeform lines + province + country).
-- GPS columns are nullable DECIMAL(10,7) — stored but never required.
-- =============================================================================

ALTER TABLE `oei_episode`
    MODIFY COLUMN `type`
        ENUM('ED','OBS','BH','AL','IP','HBC') NOT NULL DEFAULT 'ED'
        COMMENT 'Care setting type — HBC = Home-Based Care';

-- -----------------------------------------------------------------------------
-- oei_hbc_episode
-- Episode overlay for Home-Based Care. One row per HBC episode.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oei_hbc_episode`
(
    `id`                        bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`                bigint(20) UNSIGNED  NOT NULL  COMMENT 'FK → oei_episode.id',
    `pid`                       bigint(20) UNSIGNED  NOT NULL  COMMENT 'FK → patient_data.pid',
    `facility_id`               bigint(20) UNSIGNED  NOT NULL,
    `encounter_id`              bigint(20) UNSIGNED           DEFAULT NULL
                                    COMMENT 'OpenEMR encounter NUMBER (form_encounter.encounter) — anchors care plan entries',

    -- ── Referral ─────────────────────────────────────────────────────────
    `referral_source`           varchar(120)                  DEFAULT NULL
                                    COMMENT 'Free text or coded: GP, Hospital, Self, Family, Agency, etc.',
    `referral_reason`           varchar(255)                  DEFAULT NULL,
    `referral_status`           ENUM(
                                    'NEW',
                                    'TRIAGED',
                                    'SCHEDULED',
                                    'ACTIVE',
                                    'CLOSED',
                                    'DECLINED'
                                )                    NOT NULL DEFAULT 'NEW',
    `urgency`                   ENUM('ROUTINE','URGENT','EMERGENT') NOT NULL DEFAULT 'ROUTINE',
    `referral_datetime`         datetime                      DEFAULT NULL,
    `soc_datetime`              datetime                      DEFAULT NULL  COMMENT 'Start of Care — first clinical visit date',

    -- ── Service address (international freeform) ─────────────────────────
    `service_address_line1`     varchar(120)                  DEFAULT NULL,
    `service_address_line2`     varchar(120)                  DEFAULT NULL,
    `service_city`              varchar(80)                   DEFAULT NULL,
    `service_state_province`    varchar(80)                   DEFAULT NULL,
    `service_postal_code`       varchar(20)                   DEFAULT NULL,
    `service_country`           varchar(60)                   DEFAULT NULL,
    `access_notes`              varchar(255)                  DEFAULT NULL
                                    COMMENT 'Gate code, parking, dog, key location, etc.',

    -- ── Caregiver / contact ───────────────────────────────────────────────
    `caregiver_name`            varchar(120)                  DEFAULT NULL,
    `caregiver_phone`           varchar(40)                   DEFAULT NULL,
    `caregiver_relationship`    varchar(60)                   DEFAULT NULL
                                    COMMENT 'Spouse, Child, Friend, Home carer, etc.',

    -- ── Clinical assignment ───────────────────────────────────────────────
    `primary_clinician_user_id` bigint(20) UNSIGNED           DEFAULT NULL  COMMENT 'FK → users.id',
    `primary_diagnosis`         varchar(255)                  DEFAULT NULL,
    `primary_icd10`             varchar(20)                   DEFAULT NULL,

    -- ── Authorization / payer ─────────────────────────────────────────────
    `payer_name`                varchar(120)                  DEFAULT NULL,
    `authorization_notes`       text                          DEFAULT NULL,
    `cert_period_start`         date                          DEFAULT NULL,
    `cert_period_end`           date                          DEFAULT NULL,

    `created_datetime`          datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE  KEY `uniq_hbc_episode` (`episode_id`),
    KEY `idx_hbc_pid`          (`pid`),
    KEY `idx_hbc_facility`     (`facility_id`),
    KEY `idx_hbc_clinician`    (`primary_clinician_user_id`),
    KEY `idx_hbc_status`       (`referral_status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Home-Based Care episode overlay — one row per HBC episode';

-- -----------------------------------------------------------------------------
-- oei_hbc_visit
-- Individual visit record. One row per scheduled clinical encounter.
-- This is the primary work unit in the HBC workflow.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `oei_hbc_visit`
(
    `id`                        bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`                bigint(20) UNSIGNED  NOT NULL  COMMENT 'FK → oei_episode.id',
    `pid`                       bigint(20) UNSIGNED  NOT NULL,
    `facility_id`               bigint(20) UNSIGNED  NOT NULL,

    -- ── Visit identity ────────────────────────────────────────────────────
    `visit_type`                ENUM(
                                    'SN',     -- Skilled Nursing
                                    'PT',     -- Physical Therapy
                                    'OT',     -- Occupational Therapy
                                    'ST',     -- Speech Therapy
                                    'MSW',    -- Medical Social Work
                                    'HHA',    -- Home Health Aide
                                    'MD',     -- Physician house call
                                    'OTHER'
                                )                    NOT NULL DEFAULT 'SN',
    `clinician_user_id`         bigint(20) UNSIGNED           DEFAULT NULL  COMMENT 'FK → users.id',

    -- ── Scheduling ────────────────────────────────────────────────────────
    `scheduled_datetime`        datetime                      DEFAULT NULL,
    `actual_start_datetime`     datetime                      DEFAULT NULL,
    `actual_end_datetime`       datetime                      DEFAULT NULL,

    -- ── Status ───────────────────────────────────────────────────────────
    `status`                    ENUM(
                                    'SCHEDULED',
                                    'EN_ROUTE',
                                    'ARRIVED',
                                    'COMPLETE',
                                    'MISSED',
                                    'REFUSED',
                                    'CANCELED'
                                )                    NOT NULL DEFAULT 'SCHEDULED',

    -- ── Visit verification (lightweight EVV-adjacent, not regulatory) ─────
    `actual_lat`                DECIMAL(10, 7)                DEFAULT NULL  COMMENT 'GPS lat at visit start — nullable, never required',
    `actual_lng`                DECIMAL(10, 7)                DEFAULT NULL  COMMENT 'GPS lng at visit start — nullable, never required',

    -- ── Offline draft ─────────────────────────────────────────────────────
    `draft_data`                text                          DEFAULT NULL
                                    COMMENT 'JSON — partial form data saved from mobile field (not yet finalised)',
    `is_draft`                  tinyint(1)           NOT NULL DEFAULT 0
                                    COMMENT '1 = clinician saved draft from field; 0 = finalised or not started',

    -- ── Patient signature ─────────────────────────────────────────────────
    `patient_signature_obtained`    tinyint(1)       NOT NULL DEFAULT 0,
    `patient_signature_datetime`    datetime                  DEFAULT NULL,
    `patient_signature_data`        mediumtext                DEFAULT NULL
                                        COMMENT 'Base64 PNG from canvas — stored here to keep visit record self-contained',

    -- ── Clinical content ──────────────────────────────────────────────────
    `visit_note`                text                          DEFAULT NULL,
    `outcome_summary`           varchar(255)                  DEFAULT NULL,
    `mileage_miles`             DECIMAL(6, 2)                 DEFAULT NULL,

    `created_by_user_id`        bigint(20) UNSIGNED           DEFAULT NULL,
    `created_datetime`          datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_datetime`          datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_hbcv_episode`      (`episode_id`),
    KEY `idx_hbcv_pid`          (`pid`),
    KEY `idx_hbcv_clinician`    (`clinician_user_id`),
    KEY `idx_hbcv_scheduled`    (`scheduled_datetime`),
    KEY `idx_hbcv_status`       (`status`),
    KEY `idx_hbcv_facility_date`(`facility_id`, `scheduled_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Home-Based Care visit record — one row per clinical encounter';

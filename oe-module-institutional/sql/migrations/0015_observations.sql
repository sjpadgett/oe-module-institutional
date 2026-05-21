-- ============================================================================
-- 0015_observations.sql
--
-- Introduces two tables for extended, high-frequency, and device-originated
-- clinical observations that don't fit oei_triage (which is designed for
-- episodic clinician-entered vitals with immediate alerting).
--
-- oei_obs_type  — reference catalogue of observable types with LOINC codes,
--                 units, and configurable alert bounds.
--
-- oei_observation — one row per measurement. Designed for:
--   - RPM/wearable device feeds (high frequency, continuous)
--   - Extended vitals: blood glucose, HRV, stress score, LASI, etc.
--   - Device-imported batches via the FHIR ingest endpoint
--   - Manual extended measurements entered by clinicians
--
-- Volume strategy: partitioned by month (RANGE on TO_DAYS). Older
-- partitions can be archived or exchanged without touching current data.
-- A 2-year rolling window covers most institutional care scenarios.
-- ============================================================================

-- ── oei_obs_type ──────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `oei_obs_type`
(
    `code`          varchar(40)     NOT NULL,
    `display_name`  varchar(100)    NOT NULL,
    `loinc_code`    varchar(20)     DEFAULT NULL   COMMENT 'LOINC code for FHIR interoperability',
    `category`      varchar(30)     NOT NULL DEFAULT 'vital-signs'
                                    COMMENT 'FHIR Observation category: vital-signs | laboratory | activity | survey',
    `default_unit`  varchar(30)     DEFAULT NULL,
    `value_type`    varchar(10)     NOT NULL DEFAULT 'numeric'
                                    COMMENT 'numeric | text | boolean',
    `alert_low`     decimal(10,3)   DEFAULT NULL   COMMENT 'NULL = no low alert',
    `alert_high`    decimal(10,3)   DEFAULT NULL   COMMENT 'NULL = no high alert',
    `is_active`     tinyint(1)      NOT NULL DEFAULT 1,
    `sort_order`    smallint        NOT NULL DEFAULT 100,
    PRIMARY KEY (`code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Reference catalogue of observable types with LOINC codes and alert bounds';

-- ── Seed: standard vitals (mirror of oei_triage fields — for device feeds) ─

INSERT IGNORE INTO `oei_obs_type`
    (`code`,           `display_name`,           `loinc_code`, `category`,     `default_unit`, `value_type`, `alert_low`, `alert_high`, `sort_order`)
VALUES
    ('SBP',            'Systolic Blood Pressure', '8480-6',    'vital-signs',  'mmHg',         'numeric',    80,          180,          10),
    ('DBP',            'Diastolic BP',            '8462-4',    'vital-signs',  'mmHg',         'numeric',    50,          120,          11),
    ('HR',             'Heart Rate',              '8867-4',    'vital-signs',  'bpm',          'numeric',    45,          120,          20),
    ('RR',             'Respiratory Rate',        '9279-1',    'vital-signs',  '/min',         'numeric',    8,           24,           30),
    ('TEMP_F',         'Temperature',             '8310-5',    'vital-signs',  '°F',           'numeric',    96.0,        100.4,        40),
    ('SPO2',           'Oxygen Saturation',       '59408-5',   'vital-signs',  '%',            'numeric',    93,          NULL,         50),
    ('WEIGHT_KG',      'Body Weight',             '29463-7',   'vital-signs',  'kg',           'numeric',    NULL,        NULL,         60),
    ('PAIN',           'Pain Score',              '72514-3',   'vital-signs',  '0-10',         'numeric',    NULL,        7,            70),
    ('GCS',            'Glasgow Coma Scale',      '35088-4',   'vital-signs',  'score',        'numeric',    NULL,        NULL,         80);

-- ── Seed: extended — chronic care, RPM, wearables ──────────────────────────

INSERT IGNORE INTO `oei_obs_type`
    (`code`,           `display_name`,           `loinc_code`, `category`,     `default_unit`, `value_type`, `alert_low`, `alert_high`, `sort_order`)
VALUES
    ('GLUCOSE',        'Blood Glucose',           '2339-0',    'laboratory',   'mg/dL',        'numeric',    70,          250,          110),
    ('GLUCOSE_FASTING','Fasting Blood Glucose',   '1558-6',    'laboratory',   'mg/dL',        'numeric',    70,          126,          111),
    ('HBA1C',          'HbA1c',                   '4548-4',    'laboratory',   '%',            'numeric',    NULL,        7.0,          112),
    ('HRV',            'Heart Rate Variability',  '80404-7',   'vital-signs',  'ms',           'numeric',    NULL,        NULL,         120),
    ('STEPS',          'Step Count',              '55423-8',   'activity',     'steps/day',    'numeric',    NULL,        NULL,         130),
    ('STRESS',         'Stress Score',            NULL,        'survey',       '0-100',        'numeric',    NULL,        75,           140),
    ('SLEEP_H',        'Sleep Duration',          '93832-4',   'activity',     'hours',        'numeric',    5.0,         NULL,         150),
    ('LASI',           'Large Artery Stiffness Index', NULL,   'vital-signs',  NULL,           'numeric',    NULL,        NULL,         160),
    ('REFLECT_IDX',    'Reflection Index',        NULL,        'vital-signs',  '%',            'numeric',    NULL,        NULL,         161),
    ('WEIGHT_LBS',     'Body Weight (lbs)',        '29463-7',   'vital-signs',  'lbs',          'numeric',    NULL,        NULL,         162),
    ('BMI',            'Body Mass Index',          '39156-5',   'vital-signs',  'kg/m²',        'numeric',    NULL,        NULL,         163),
    ('INR',            'INR / Prothrombin Time',   '34714-6',   'laboratory',   'ratio',        'numeric',    NULL,        3.5,          170),
    ('CREATININE',     'Serum Creatinine',         '2160-0',    'laboratory',   'mg/dL',        'numeric',    NULL,        2.0,          171),
    ('POTASSIUM',      'Potassium',                '6298-4',    'laboratory',   'mEq/L',        'numeric',    3.5,         5.5,          172),
    ('SODIUM',         'Sodium',                   '2951-2',    'laboratory',   'mEq/L',        'numeric',    135,         145,          173);

-- ── oei_observation ───────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `oei_observation`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                int(11)    UNSIGNED NOT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `obs_type_code`      varchar(40)         NOT NULL   COMMENT 'FK to oei_obs_type.code',
    `observed_datetime`  datetime            NOT NULL,
    `value_numeric`      decimal(12,4)       DEFAULT NULL,
    `value_text`         varchar(255)        DEFAULT NULL,
    `unit`               varchar(30)         DEFAULT NULL,
    `source_type`        varchar(20)         NOT NULL DEFAULT 'MANUAL'
                                             COMMENT 'MANUAL | DEVICE | IMPORT | FHIR',
    `device_id`          varchar(80)         DEFAULT NULL
                                             COMMENT 'Device identifier from source system',
    `fhir_id`            varchar(100)        DEFAULT NULL
                                             COMMENT 'FHIR Observation.id for dedup on re-import',
    `is_flagged`         tinyint(1)          NOT NULL DEFAULT 0
                                             COMMENT '1 = value outside obs_type alert bounds at write time',
    `noted_by_user_id`   bigint(20) UNSIGNED DEFAULT NULL,
    `created_datetime`   datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`, `observed_datetime`),

    INDEX `idx_oei_obs_episode`   (`episode_id`, `observed_datetime`),
    INDEX `idx_oei_obs_pid_type`  (`pid`, `obs_type_code`, `observed_datetime`),
    INDEX `idx_oei_obs_type`      (`obs_type_code`, `facility_id`),
    INDEX `idx_oei_obs_flagged`   (`facility_id`, `is_flagged`, `observed_datetime`),
    INDEX `idx_oei_obs_fhir`      (`fhir_id`(40), `observed_datetime`)

) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'High-frequency and device-originated clinical observations'

PARTITION BY RANGE (TO_DAYS(`observed_datetime`)) (
    PARTITION p_2024_h1 VALUES LESS THAN (TO_DAYS('2024-07-01')),
    PARTITION p_2024_h2 VALUES LESS THAN (TO_DAYS('2025-01-01')),
    PARTITION p_2025_q1 VALUES LESS THAN (TO_DAYS('2025-04-01')),
    PARTITION p_2025_q2 VALUES LESS THAN (TO_DAYS('2025-07-01')),
    PARTITION p_2025_q3 VALUES LESS THAN (TO_DAYS('2025-10-01')),
    PARTITION p_2025_q4 VALUES LESS THAN (TO_DAYS('2026-01-01')),
    PARTITION p_2026_q1 VALUES LESS THAN (TO_DAYS('2026-04-01')),
    PARTITION p_2026_q2 VALUES LESS THAN (TO_DAYS('2026-07-01')),
    PARTITION p_2026_q3 VALUES LESS THAN (TO_DAYS('2026-10-01')),
    PARTITION p_2026_q4 VALUES LESS THAN (TO_DAYS('2027-01-01')),
    PARTITION p_future   VALUES LESS THAN MAXVALUE
);

-- ── Version record ────────────────────────────────────────────────────────

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0015', NOW());

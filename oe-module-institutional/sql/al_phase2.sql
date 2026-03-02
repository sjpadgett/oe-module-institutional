-- =============================================================================
-- AL Phase 2 Migration + Demo Seed  v0.12.0
-- oe-module-institutional
-- =============================================================================
-- New table:
--   oei_fall_risk_assessment  — Morse Fall Scale history per AL episode
--
-- Demo seed additions (episodes 14-18, pids 50-54):
--   oei_triage            — periodic vitals for all 5 residents
--   oei_mar_order         — standing medication orders
--   oei_mar_administration — administration records
--   oei_fall_risk_assessment — initial + follow-up Morse assessments
--
-- Run AFTER: institutional-demo-seed.sql, demo_seed_al_supplement.sql
-- Safe to re-run (idempotent cleanup at top)
-- =============================================================================

SET SQL_MODE  = 'NO_AUTO_VALUE_ON_ZERO';
SET time_zone = '+00:00';
/*!40101 SET NAMES utf8mb4 */;

-- =============================================================================
-- SCHEMA
-- =============================================================================

CREATE TABLE IF NOT EXISTS `oei_fall_risk_assessment` (
    `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`            INT UNSIGNED NOT NULL,
    `facility_id`           INT UNSIGNED NOT NULL,
    `assessed_by_user_id`   INT UNSIGNED NULL,
    `assessed_datetime`     DATETIME     NOT NULL,

    -- Morse Fall Scale items (standard scoring)
    `mfs_fall_history`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=No, 25=Yes',
    `mfs_secondary_dx`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=No, 15=Yes',
    `mfs_ambulatory_aid`    TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=None/bed-rest/nurse, 15=Crutches/cane/walker, 30=Furniture',
    `mfs_iv_heparin_lock`   TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=No, 20=Yes',
    `mfs_gait`              TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Normal/bedrest, 10=Weak, 20=Impaired',
    `mfs_mental_status`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0=Knows own limits, 15=Forgets limitations',

    `total_score`           TINYINT UNSIGNED NOT NULL COMMENT 'Sum of 6 items (0-125)',
    `risk_level`            ENUM('LOW','MODERATE','HIGH') NOT NULL,
    `notes`                 TEXT NULL,

    `created_datetime`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_fra_episode`   (`episode_id`),
    KEY `idx_fra_facility`  (`facility_id`),
    KEY `idx_fra_datetime`  (`assessed_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Morse Fall Scale reassessment history for AL episodes';

-- =============================================================================
-- IDEMPOTENT CLEANUP
-- =============================================================================

DELETE FROM oei_fall_risk_assessment WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_triage               WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_mar_administration   WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_mar_order            WHERE episode_id IN (14,15,16,17,18);

-- =============================================================================
-- VITALS  (oei_triage — periodic monitoring, not ED triage)
-- Resident mapping:
--   ep 14 / pid 50  Eleanor Hartwell   — dementia/HIGH fall risk
--   ep 15 / pid 51  George Calloway    — post-hip rehab/MODERATE
--   ep 16 / pid 52  Ruth Okonkwo       — COPD/LOW risk  (SpO2 watch)
--   ep 17 / pid 53  Harold Steinberg   — Parkinson's/HIGH (BP orthostatcs)
--   ep 18 / pid 54  Dorothy Vasquez    — CHF/T2DM (daily weights)
-- Notes: arrival_mode='PERIODIC' flags these as routine checks, not ED triage
-- =============================================================================

INSERT INTO `oei_triage`
    (`episode_id`, `pid`, `eid`, `facility_id`, `set_number`,
     `bp_systolic`, `bp_diastolic`, `hr`, `rr`, `temp_f`, `spo2`,
     `gcs`, `pain_score`, `weight_kg`, `arrival_mode`,
     `esi_suggested`, `notes`, `noted_by_user_id`, `noted_datetime`)
VALUES

-- Eleanor Hartwell (ep 14, pid 50) — weekly BP/HR, weight monthly
-- Mild hypertension, dementia (GCS tracked as orientation proxy)
(14,50,NULL,1,1, 148,88,72,16,98.2,97, NULL,1, 58.1,'PERIODIC',NULL,'Morning vitals — cooperative today',        1, DATE_SUB(NOW(), INTERVAL 28 DAY)),
(14,50,NULL,1,2, 152,90,76,17,98.0,96, NULL,2, 57.9,'PERIODIC',NULL,'Slightly elevated BP, restless overnight',   1, DATE_SUB(NOW(), INTERVAL 21 DAY)),
(14,50,NULL,1,3, 145,86,74,16,98.4,97, NULL,1, 58.0,'PERIODIC',NULL,'Stable — responded to name',                1, DATE_SUB(NOW(), INTERVAL 14 DAY)),
(14,50,NULL,1,4, 150,89,78,17,98.1,96, NULL,2, 57.8,'PERIODIC',NULL,'Slightly agitated post-lunch',              1, DATE_SUB(NOW(), INTERVAL  7 DAY)),
(14,50,NULL,1,5, 144,85,71,16,98.3,97, NULL,1, 58.0,'PERIODIC',NULL,'Calm, participated in music therapy',       1, DATE_SUB(NOW(), INTERVAL  1 DAY)),

-- George Calloway (ep 15, pid 51) — post-hip rehab, pain tracking daily
(15,51,NULL,1,1, 128,78,68,15,98.6,98, NULL,6, 80.5,'PERIODIC',NULL,'Post-PT pain 6/10 — icing hip',             1, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(15,51,NULL,1,2, 124,76,66,14,98.8,98, NULL,4, 80.2,'PERIODIC',NULL,'Good PT session — pain improving',          1, DATE_SUB(NOW(), INTERVAL 18 DAY)),
(15,51,NULL,1,3, 126,78,70,15,98.5,99, NULL,3, 79.8,'PERIODIC',NULL,'Pain down to 3/10 — ambulating hallway',   1, DATE_SUB(NOW(), INTERVAL 12 DAY)),
(15,51,NULL,1,4, 122,74,64,15,98.7,99, NULL,3, 79.6,'PERIODIC',NULL,'Independent to bathroom with walker',       1, DATE_SUB(NOW(), INTERVAL  6 DAY)),
(15,51,NULL,1,5, 120,72,62,14,98.9,99, NULL,2, 79.3,'PERIODIC',NULL,'Excellent progress — discharge planning initiated',1,DATE_SUB(NOW(), INTERVAL  1 DAY)),

-- Ruth Okonkwo (ep 16, pid 52) — COPD, SpO2 bid monitoring critical
(16,52,NULL,1,1,  132,82,78,20,98.2,92, NULL,1, 61.4,'PERIODIC',NULL,'SpO2 92% — increased O2 to 2L NC',         1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(16,52,NULL,1,2,  128,80,74,18,98.4,94, NULL,1, 61.3,'PERIODIC',NULL,'SpO2 improved after bronchodilator',        1, DATE_SUB(NOW(), INTERVAL 15 DAY)),
(16,52,NULL,1,3,  130,82,76,19,98.1,93, NULL,1, 61.5,'PERIODIC',NULL,'Mild wheeze — rescue inhaler given',        1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(16,52,NULL,1,4,  126,78,72,17,98.3,95, NULL,0, 61.2,'PERIODIC',NULL,'Good morning — SpO2 95% on room air',       1, DATE_SUB(NOW(), INTERVAL  5 DAY)),
(16,52,NULL,1,5,  124,78,70,17,98.5,95, NULL,0, 61.3,'PERIODIC',NULL,'Stable — walked to dining room independently',1,DATE_SUB(NOW(), INTERVAL  1 DAY)),

-- Harold Steinberg (ep 17, pid 53) — Parkinson's, orthostatic BP pairs
-- Lying → standing BP drops are characteristic; set_number pairs capture this
(17,53,NULL,1,1, 138,86,62,15,98.0,97, NULL,2, 73.2,'PERIODIC',NULL,'Supine BP — pre-stand check',               1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(17,53,NULL,1,2, 108,68,68,15,98.0,97, NULL,2, 73.2,'PERIODIC',NULL,'Standing BP (3 min) — lightheaded reported', 1, DATE_SUB(NOW(), INTERVAL 30 DAY)),
(17,53,NULL,1,3, 136,84,64,15,98.2,97, NULL,2, 73.0,'PERIODIC',NULL,'Supine BP',                                  1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(17,53,NULL,1,4, 112,72,70,15,98.2,97, NULL,2, 73.0,'PERIODIC',NULL,'Standing BP — mild drop, no symptoms',       1, DATE_SUB(NOW(), INTERVAL 20 DAY)),
(17,53,NULL,1,5, 140,86,60,14,98.1,98, NULL,1, 72.8,'PERIODIC',NULL,'Morning vitals — tremor moderate',           1, DATE_SUB(NOW(), INTERVAL  7 DAY)),
(17,53,NULL,1,6, 118,74,66,14,98.1,98, NULL,1, 72.8,'PERIODIC',NULL,'Post-stand — acceptable drop, stable',       1, DATE_SUB(NOW(), INTERVAL  7 DAY)),
(17,53,NULL,1,7, 138,84,62,15,98.3,98, NULL,1, 72.7,'PERIODIC',NULL,'Daily check — good colour, steady gait today',1,DATE_SUB(NOW(), INTERVAL  1 DAY)),

-- Dorothy Vasquez (ep 18, pid 54) — CHF/T2DM, daily weights critical
-- Weight gain > 2 lbs in 24h = fluid retention alert
(18,54,NULL,1,1, 146,90,82,18,98.2,96, NULL,2, 74.5,'PERIODIC',NULL,'Daily weight — baseline set',                1, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(18,54,NULL,1,2, 148,92,84,18,98.1,96, NULL,2, 74.8,'PERIODIC',NULL,'Weight +0.3 kg — ankles mild oedema',        1, DATE_SUB(NOW(), INTERVAL  9 DAY)),
(18,54,NULL,1,3, 150,94,88,19,98.0,95, NULL,3, 75.4,'PERIODIC',NULL,'Weight +0.6 kg — notified RN, BP elevated',  1, DATE_SUB(NOW(), INTERVAL  8 DAY)),
(18,54,NULL,1,4, 142,88,80,17,98.3,96, NULL,2, 74.9,'PERIODIC',NULL,'Post-furosemide — weight down 0.5 kg',        1, DATE_SUB(NOW(), INTERVAL  7 DAY)),
(18,54,NULL,1,5, 138,86,78,17,98.4,97, NULL,1, 74.6,'PERIODIC',NULL,'Stable — oedema resolved, weight trending down',1,DATE_SUB(NOW(), INTERVAL  4 DAY)),
(18,54,NULL,1,6, 136,84,76,16,98.5,97, NULL,1, 74.4,'PERIODIC',NULL,'Good day — blood sugar 124 fasting',         1, DATE_SUB(NOW(), INTERVAL  2 DAY)),
(18,54,NULL,1,7, 138,86,78,17,98.4,97, NULL,1, 74.3,'PERIODIC',NULL,'Daily weight stable — fluid balance maintained',1,DATE_SUB(NOW(), INTERVAL  1 DAY));

-- =============================================================================
-- MAR ORDERS  (standing medications per resident diagnosis)
-- =============================================================================

INSERT INTO `oei_mar_order`
    (`episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`,
     `route`, `frequency`, `is_prn`, `status`,
     `ordered_datetime`, `ordered_by_user_id`, `instructions`)
VALUES

-- Eleanor Hartwell (ep 14, pid 50) — dementia/HTN
(14,50,1,'Donepezil',      '10',   'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 47 DAY),1,'Take at bedtime with or without food'),
(14,50,1,'Memantine',      '10',   'mg',  'PO',  'BID', 0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 47 DAY),1,'Titrate; hold if excessive sedation'),
(14,50,1,'Amlodipine',     '5',    'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 47 DAY),1,'Hold if SBP < 100'),
(14,50,1,'Lorazepam',      '0.5',  'mg',  'PO',  'QD',  1,'ACTIVE', DATE_SUB(NOW(),INTERVAL 47 DAY),1,'PRN agitation — use Behavioural PRN scale; max 1mg/day'),

-- George Calloway (ep 15, pid 51) — post-hip rehab/pain
(15,51,1,'Acetaminophen',  '650',  'mg',  'PO',  'Q6H', 0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 24 DAY),1,'Scheduled; do not exceed 4000mg/day total'),
(15,51,1,'Enoxaparin',     '40',   'mg',  'SQ',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 24 DAY),1,'DVT prophylaxis post-hip; check platelets weekly'),
(15,51,1,'Atorvastatin',   '40',   'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 24 DAY),1,'Take with evening meal'),
(15,51,1,'Oxycodone',      '5',    'mg',  'PO',  'Q6H', 1,'ACTIVE', DATE_SUB(NOW(),INTERVAL 24 DAY),1,'PRN breakthrough pain — pain score ≥ 5 only; wean per protocol'),

-- Ruth Okonkwo (ep 16, pid 52) — COPD
(16,52,1,'Tiotropium',     '18',   'mcg', 'INH', 'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 20 DAY),1,'HandiHaler device — 1 capsule inhaled once daily'),
(16,52,1,'Fluticasone/Salmeterol','250/50','mcg','INH','BID',0,'ACTIVE',DATE_SUB(NOW(),INTERVAL 20 DAY),1,'Shake well; rinse mouth after use'),
(16,52,1,'Albuterol',      '2.5',  'mg',  'NEB', 'Q4H', 1,'ACTIVE', DATE_SUB(NOW(),INTERVAL 20 DAY),1,'PRN wheeze/dyspnea — SpO2 < 92% or RR > 22'),
(16,52,1,'Furosemide',     '20',   'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 20 DAY),1,'Monitor daily weight; hold if weight < 60 kg'),

-- Harold Steinberg (ep 17, pid 53) — Parkinson's/orthostatic
(17,53,1,'Carbidopa-Levodopa','25-100','mg','PO','TID',0,'ACTIVE',DATE_SUB(NOW(),INTERVAL 63 DAY),1,'Give 30 min before meals; avoid high-protein meals'),
(17,53,1,'Pramipexole',    '0.5',  'mg',  'PO',  'TID', 0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 63 DAY),1,'Titrate slowly; monitor for orthostatic hypotension'),
(17,53,1,'Fludrocortisone','0.1',  'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 63 DAY),1,'For orthostatic hypotension — hold if SBP > 160 supine'),
(17,53,1,'Quetiapine',     '12.5', 'mg',  'PO',  'QD',  1,'ACTIVE', DATE_SUB(NOW(),INTERVAL 63 DAY),1,'PRN psychosis/agitation — Parkinson''s psychosis; avoid haloperidol'),

-- Dorothy Vasquez (ep 18, pid 54) — CHF/T2DM
(18,54,1,'Furosemide',     '40',   'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 10 DAY),1,'Hold if weight < 72 kg or SBP < 90; daily weight required'),
(18,54,1,'Lisinopril',     '10',   'mg',  'PO',  'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 10 DAY),1,'Hold if SBP < 100 or K+ > 5.5'),
(18,54,1,'Carvedilol',     '6.25', 'mg',  'PO',  'BID', 0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 10 DAY),1,'Hold if HR < 55 or SBP < 90; take with food'),
(18,54,1,'Metformin',      '500',  'mg',  'PO',  'BID', 0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 10 DAY),1,'With meals; hold if eGFR < 30'),
(18,54,1,'Insulin Glargine','20',  'units','SQ', 'QD',  0,'ACTIVE', DATE_SUB(NOW(),INTERVAL 10 DAY),1,'Bedtime — check fasting glucose; hold if BG < 70');

-- =============================================================================
-- MAR ADMINISTRATIONS  (last 5 days of scheduled doses per resident)
-- Using episode_id + mar_order correlated via INSERT...SELECT
-- =============================================================================

-- Eleanor — Donepezil QD (bedtime) last 5 days
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 14, 50,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '21:00:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '21:04:00'),
    'GIVEN', '10', 'mg', 'PO', 1, 0,
    'Given without difficulty'
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 14 AND o.drug_name = 'Donepezil';

-- Eleanor — Amlodipine QD (morning) last 5 days
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 14, 50,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:00:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:06:00'),
    'GIVEN', '5', 'mg', 'PO', 1, 0, 'Given with breakfast'
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 14 AND o.drug_name = 'Amlodipine';

-- Today's Memantine BID for Eleanor (morning given, evening pending)
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 14, 50,
    TIMESTAMP(CURDATE(), '08:00:00'),
    TIMESTAMP(CURDATE(), '08:08:00'),
    'GIVEN', '10', 'mg', 'PO', 1, 0, 'AM dose given'
FROM oei_mar_order o WHERE o.episode_id = 14 AND o.drug_name = 'Memantine';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `outcome`, `is_high_alert`)
SELECT o.id, 14, 50, TIMESTAMP(CURDATE(), '20:00:00'), 'PENDING', 0
FROM oei_mar_order o WHERE o.episode_id = 14 AND o.drug_name = 'Memantine';

-- George — Acetaminophen Q6H last 3 days (4 doses/day)
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 15, 51,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY),
        CASE t WHEN 0 THEN '06:00:00' WHEN 1 THEN '12:00:00'
               WHEN 2 THEN '18:00:00' ELSE '00:00:00' END),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY),
        CASE t WHEN 0 THEN '06:05:00' WHEN 1 THEN '12:04:00'
               WHEN 2 THEN '18:06:00' ELSE '00:03:00' END),
    'GIVEN', '650', 'mg', 'PO', 1, 0,
    CONCAT('Scheduled dose — pain score at time: ', (3 - t + d))
FROM oei_mar_order o
CROSS JOIN (SELECT 2 d UNION SELECT 1 UNION SELECT 0) days
CROSS JOIN (SELECT 0 t UNION SELECT 1 UNION SELECT 2 UNION SELECT 3) times
WHERE o.episode_id = 15 AND o.drug_name = 'Acetaminophen';

-- Today's Acetaminophen — morning given, midday given, afternoon pending, evening pending
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 15, 51, TIMESTAMP(CURDATE(),'06:00:00'), TIMESTAMP(CURDATE(),'06:04:00'),
    'GIVEN', '650','mg','PO',1,0,'Breakfast dose'
FROM oei_mar_order o WHERE o.episode_id=15 AND o.drug_name='Acetaminophen';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 15, 51, TIMESTAMP(CURDATE(),'12:00:00'), TIMESTAMP(CURDATE(),'12:03:00'),
    'GIVEN', '650','mg','PO',1,0,'Lunch dose'
FROM oei_mar_order o WHERE o.episode_id=15 AND o.drug_name='Acetaminophen';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`, `outcome`, `is_high_alert`)
SELECT o.id, 15, 51, TIMESTAMP(CURDATE(),'18:00:00'), 'PENDING', 0
FROM oei_mar_order o WHERE o.episode_id=15 AND o.drug_name='Acetaminophen';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`, `outcome`, `is_high_alert`)
SELECT o.id, 15, 51, TIMESTAMP(CURDATE(),'00:00:00'), 'PENDING', 0
FROM oei_mar_order o WHERE o.episode_id=15 AND o.drug_name='Acetaminophen';

-- Enoxaparin (high-alert) — George, last 5 days
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `site`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 15, 51,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '09:00:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '09:08:00'),
    'GIVEN', '40', 'mg', 'SQ',
    CASE d WHEN 4 THEN 'LLQ' WHEN 3 THEN 'RLQ' WHEN 2 THEN 'LUQ'
           WHEN 1 THEN 'RUQ' ELSE 'LLQ' END,
    1, 1, 'High-alert: two-nurse check documented'
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 15 AND o.drug_name = 'Enoxaparin';

-- Ruth — Tiotropium QD (morning) last 5 days
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 16, 52,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:30:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:36:00'),
    'GIVEN', '18', 'mcg', 'INH', 1, 0, 'HandiHaler — technique verified'
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 16 AND o.drug_name = 'Tiotropium';

-- Ruth — Albuterol PRN (3 episodes this week)
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 16, 52, dt, dt, 'GIVEN', '2.5', 'mg', 'NEB', 1, 0, note
FROM oei_mar_order o
JOIN (
    SELECT DATE_SUB(NOW(), INTERVAL 5 DAY) dt, 'SpO2 dropped to 91% — rescue neb given; SpO2 94% post' note
    UNION SELECT DATE_SUB(NOW(), INTERVAL 3 DAY), 'Wheeze on exertion — albuterol given, SpO2 93-95%'
    UNION SELECT DATE_SUB(NOW(), INTERVAL 1 DAY), 'Morning wheeze — neb given, resolved in 15 min'
) doses
WHERE o.episode_id = 16 AND o.drug_name = 'Albuterol';

-- Harold — Carbidopa-Levodopa TID last 3 days (timing critical — 30 min before meals)
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 17, 53,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY),
        CASE t WHEN 0 THEN '07:30:00' WHEN 1 THEN '12:30:00' ELSE '17:30:00' END),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY),
        CASE t WHEN 0 THEN '07:33:00' WHEN 1 THEN '12:32:00' ELSE '17:34:00' END),
    'GIVEN', '25-100', 'mg', 'PO', 1, 0,
    '30 min pre-meal — protein diet check done'
FROM oei_mar_order o
CROSS JOIN (SELECT 2 d UNION SELECT 1 UNION SELECT 0) days
CROSS JOIN (SELECT 0 t UNION SELECT 1 UNION SELECT 2) times
WHERE o.episode_id = 17 AND o.drug_name = 'Carbidopa-Levodopa';

-- Harold — today's first two Carbidopa-Levodopa doses, third pending
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 17, 53, TIMESTAMP(CURDATE(),'07:30:00'), TIMESTAMP(CURDATE(),'07:32:00'),
    'GIVEN','25-100','mg','PO',1,0,'AM dose — tremor noted, moderate'
FROM oei_mar_order o WHERE o.episode_id=17 AND o.drug_name='Carbidopa-Levodopa';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 17, 53, TIMESTAMP(CURDATE(),'12:30:00'), TIMESTAMP(CURDATE(),'12:31:00'),
    'GIVEN','25-100','mg','PO',1,0,'Noon dose — good effect, less tremor'
FROM oei_mar_order o WHERE o.episode_id=17 AND o.drug_name='Carbidopa-Levodopa';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`, `outcome`, `is_high_alert`)
SELECT o.id, 17, 53, TIMESTAMP(CURDATE(),'17:30:00'), 'PENDING', 0
FROM oei_mar_order o WHERE o.episode_id=17 AND o.drug_name='Carbidopa-Levodopa';

-- Dorothy — Furosemide QD + Carvedilol BID + Insulin Glargine QD (high-alert) last 5 days
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 18, 54,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:00:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '08:05:00'),
    'GIVEN', '40', 'mg', 'PO', 1, 0,
    CONCAT('Weight this AM: ', ROUND(74.5 + (d * 0.1 - 0.3), 1), ' kg')
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 18 AND o.drug_name = 'Furosemide';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `site`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 18, 54,
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '21:00:00'),
    TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL d DAY), '21:07:00'),
    'GIVEN', '20', 'units', 'SQ',
    CASE d%2 WHEN 0 THEN 'Right abd' ELSE 'Left abd' END,
    1, 1, 'High-alert: dual-nurse verify; BG pre-dose documented'
FROM oei_mar_order o
CROSS JOIN (SELECT 4 d UNION SELECT 3 UNION SELECT 2 UNION SELECT 1 UNION SELECT 0) days
WHERE o.episode_id = 18 AND o.drug_name = 'Insulin Glargine';

-- Dorothy today — morning Furosemide given, Carvedilol BID (AM given, PM pending)
INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 18, 54, TIMESTAMP(CURDATE(),'08:00:00'), TIMESTAMP(CURDATE(),'08:04:00'),
    'GIVEN','40','mg','PO',1,0,'Weight 74.3 kg — stable'
FROM oei_mar_order o WHERE o.episode_id=18 AND o.drug_name='Furosemide';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`,
     `route_given`, `administered_by_user_id`, `is_high_alert`, `note`)
SELECT o.id, 18, 54, TIMESTAMP(CURDATE(),'08:00:00'), TIMESTAMP(CURDATE(),'08:06:00'),
    'GIVEN','6.25','mg','PO',1,0,'With breakfast — HR 78, BP 138/86'
FROM oei_mar_order o WHERE o.episode_id=18 AND o.drug_name='Carvedilol';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`, `outcome`, `is_high_alert`)
SELECT o.id, 18, 54, TIMESTAMP(CURDATE(),'20:00:00'), 'PENDING', 0
FROM oei_mar_order o WHERE o.episode_id=18 AND o.drug_name='Carvedilol';

INSERT INTO `oei_mar_administration`
    (`mar_order_id`, `episode_id`, `pid`, `scheduled_datetime`, `outcome`, `is_high_alert`)
SELECT o.id, 18, 54, TIMESTAMP(CURDATE(),'21:00:00'), 'PENDING', 1
FROM oei_mar_order o WHERE o.episode_id=18 AND o.drug_name='Insulin Glargine';

-- =============================================================================
-- FALL RISK ASSESSMENTS  (Morse Fall Scale — initial at admission + follow-up)
-- =============================================================================

INSERT INTO `oei_fall_risk_assessment`
    (`episode_id`, `facility_id`, `assessed_by_user_id`, `assessed_datetime`,
     `mfs_fall_history`, `mfs_secondary_dx`, `mfs_ambulatory_aid`,
     `mfs_iv_heparin_lock`, `mfs_gait`, `mfs_mental_status`,
     `total_score`, `risk_level`, `notes`)
VALUES
-- Eleanor Hartwell (ep 14) — admission (47 days ago): falls history, dementia, furniture-holding gait
(14,1,1, DATE_SUB(NOW(),INTERVAL 47 DAY), 25,15,30,0,20,15, 105,'HIGH',
 'Admission Morse assessment. Multiple falls at home prior to admission. Dementia — forgets limitations. Furniture-holding gait observed.'),
-- Eleanor 30-day reassessment
(14,1,1, DATE_SUB(NOW(),INTERVAL 17 DAY), 25,15,30,0,20,15, 105,'HIGH',
 '30-day reassessment — no change to tier. Bed alarm in use. Non-slip footwear compliant. Continue HIGH precautions.'),

-- George Calloway (ep 15) — admission: post-surgical, walker, IV port
(15,1,1, DATE_SUB(NOW(),INTERVAL 24 DAY), 0,15,15,20,10,0,  60,'HIGH',
 'Admission: Post-hip arthroplasty. IV heparin lock in situ. Walker required. Weak gait. No prior falls.'),
-- George 14-day reassessment (improving)
(15,1,1, DATE_SUB(NOW(),INTERVAL 10 DAY), 0,15,15,20,10,0,  60,'HIGH',
 '14-day rehab reassessment. Gait improving but still classified weak. IV lock remains. Continues HIGH per protocol until lock removed.'),
-- George current (IV removed, gait improving)
(15,1,1, DATE_SUB(NOW(),INTERVAL  2 DAY), 0,15,15,0,10,0,   40,'MODERATE',
 'IV lock removed — score drops to MODERATE. Gait continues to improve. Upgrade to walker-independent status expected next week.'),

-- Ruth Okonkwo (ep 16) — LOW risk, reassess monthly
(16,1,1, DATE_SUB(NOW(),INTERVAL 20 DAY), 0,15,0,0,0,0,     15,'LOW',
 'Admission Morse. No falls history. COPD only secondary dx. Independent ambulation. LOW risk.'),
(16,1,1, DATE_SUB(NOW(),INTERVAL  5 DAY), 0,15,0,0,0,0,     15,'LOW',
 'Routine reassessment — no change. Continues independent ambulation.'),

-- Harold Steinberg (ep 17) — HIGH from day 1, Parkinson's
(17,1,1, DATE_SUB(NOW(),INTERVAL 63 DAY), 25,15,15,0,20,15, 90,'HIGH',
 'Admission: Parkinson''s with falls history ×3 in prior 6 months. Cane/walker. Impaired shuffling gait. Forgets limitations. Orthostatic hypotension confirmed.'),
(17,1,1, DATE_SUB(NOW(),INTERVAL 33 DAY), 25,15,15,0,20,15, 90,'HIGH',
 '30-day reassessment: No change. Gait training ongoing with PT. Orthostatic protocol in place. Maintain HIGH.'),
(17,1,1, DATE_SUB(NOW(),INTERVAL  3 DAY), 25,15,15,0,10,15, 80,'HIGH',
 '60-day reassessment: Gait slightly improved (impaired→weak with PT). Still HIGH — history and mental status unchanged.'),

-- Dorothy Vasquez (ep 18) — MODERATE, CHF-related weakness
(18,1,1, DATE_SUB(NOW(),INTERVAL 10 DAY), 0,15,0,0,10,0,    25,'MODERATE',
 'Admission: CHF with mild lower-extremity weakness from deconditioning. No falls history. Weak gait. MODERATE precautions.'),
(18,1,1, DATE_SUB(NOW(),INTERVAL  1 DAY), 0,15,0,0,10,0,    25,'MODERATE',
 '1-week reassessment: Stable MODERATE. Oedema resolved — weight bearing slightly improved. Continue precautions.');

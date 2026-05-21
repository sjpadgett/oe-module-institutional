-- =============================================================================
-- oe-module-institutional — OBSERVATION DEMO SEED
-- Companion to institutional-demo-seed-stable.sql
--
-- Run AFTER migration 0015 (oei_observation + oei_obs_type) is applied.
-- Safe: INSERT IGNORE throughout — re-runnable without duplicates.
-- Timestamps use NOW() offsets so data is always "recent" when seed is run.
--
-- 8 CLINICAL SCENARIOS
-- ─────────────────────────────────────────────────────────────────────────────
-- AL-1  Eleanor Hartwell  ep=14 pid=50  Diabetic — CGM, glucose trending up
--       spike to 312 mg/dL + A1c 8.2%  → board badge + profile panel
--
-- AL-2  George Calloway   ep=15 pid=51  CHF — SpO2 declining to 91%,
--       weight gain +3.2 kg over 5 days → board badge + profile panel
--
-- AL-3  Harold Steinberg  ep=17 pid=53  On warfarin — INR rising to 3.9
--       (above therapeutic ceiling 3.5)  → board badge + profile panel
--
-- AL-4  Dorothy Vasquez   ep=18 pid=54  Sleep/stress wearable — sleep <5h,
--       stress score 84/100             → board badge + profile panel
--
-- IP-5  Marcus Delray     ep=19 pid=2   Sepsis watch — SpO2 declining to 88%,
--       HR 124 bpm, temp 102.1°F        → board badge + profile panel
--
-- IP-6  James Okonkwo     ep=22 pid=8   COPD — SpO2 87%, RR 27/min
--       consistently below threshold    → board badge + profile panel
--
-- HBC-7 Bernard Price     ep=25 pid=61  Home cardiac — SBP rising to 186 mmHg,
--       HRV declining 7-day trend       → board badge + profile panel
--
-- HBC-8 Alma Serrano      ep=26 pid=62  Home CGM — glucose instability both
--       hypo (62) and hyper (312) today → board badge + profile panel
-- =============================================================================

SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
/*!40101 SET NAMES utf8mb4 */;
SET FOREIGN_KEY_CHECKS=0;

-- =============================================================================
-- AL-1  Eleanor Hartwell (ep=14, pid=50, fac=1)
--       Type 2 diabetic — CGM wearable, 30-day glucose history
--       Trending up over 2 weeks; latest spike 312 mg/dL (>250 limit) + A1c
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1001, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 30 DAY), 128, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1002, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 26 DAY), 142, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1003, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 22 DAY), 155, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1004, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 18 DAY), 172, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1005, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 14 DAY), 189, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1006, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 10 DAY), 218, 'mg/dL', 'DEVICE', 'CGM-EH-001', 0, NULL, NOW()),
  (1007, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  6 DAY), 261, 'mg/dL', 'DEVICE', 'CGM-EH-001', 1, NULL, NOW()),
  (1008, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  3 DAY), 287, 'mg/dL', 'DEVICE', 'CGM-EH-001', 1, NULL, NOW()),
  (1009, 14, 50, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  4 HOUR), 312, 'mg/dL', 'DEVICE', 'CGM-EH-001', 1, NULL, NOW());

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1010, 14, 50, 1, 'HBA1C', DATE_SUB(NOW(), INTERVAL 3 DAY), 8.2, '%', 'IMPORT', NULL, 1, 1, NOW());

-- =============================================================================
-- AL-2  George Calloway (ep=15, pid=51, fac=1)
--       CHF — SpO2 declining toward critical, weight +3.2 kg over 5 days
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1101, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 7 DAY),  97, '%', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1102, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 6 DAY),  96, '%', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1103, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 5 DAY),  95, '%', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1104, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 4 DAY),  94, '%', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1105, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 3 DAY),  93, '%', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1106, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 2 DAY),  92, '%', 'DEVICE', 'OXIM-GC-002', 1, NULL, NOW()),
  (1107, 15, 51, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 3 HOUR), 91, '%', 'DEVICE', 'OXIM-GC-002', 1, NULL, NOW()),
  (1108, 15, 51, 1, 'WEIGHT_KG', DATE_SUB(NOW(), INTERVAL 5 DAY), 78.2, 'kg', 'DEVICE', 'SCALE-GC-002', 0, NULL, NOW()),
  (1109, 15, 51, 1, 'WEIGHT_KG', DATE_SUB(NOW(), INTERVAL 4 DAY), 79.1, 'kg', 'DEVICE', 'SCALE-GC-002', 0, NULL, NOW()),
  (1110, 15, 51, 1, 'WEIGHT_KG', DATE_SUB(NOW(), INTERVAL 3 DAY), 79.8, 'kg', 'DEVICE', 'SCALE-GC-002', 0, NULL, NOW()),
  (1111, 15, 51, 1, 'WEIGHT_KG', DATE_SUB(NOW(), INTERVAL 2 DAY), 80.6, 'kg', 'DEVICE', 'SCALE-GC-002', 0, NULL, NOW()),
  (1112, 15, 51, 1, 'WEIGHT_KG', DATE_SUB(NOW(), INTERVAL 2 HOUR), 81.4, 'kg', 'DEVICE', 'SCALE-GC-002', 0, NULL, NOW()),
  (1113, 15, 51, 1, 'HR', DATE_SUB(NOW(), INTERVAL 2 DAY),  96, 'bpm', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1114, 15, 51, 1, 'HR', DATE_SUB(NOW(), INTERVAL 1 DAY), 104, 'bpm', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW()),
  (1115, 15, 51, 1, 'HR', DATE_SUB(NOW(), INTERVAL 3 HOUR), 112, 'bpm', 'DEVICE', 'OXIM-GC-002', 0, NULL, NOW());

-- =============================================================================
-- AL-3  Harold Steinberg (ep=17, pid=53, fac=1)
--       On warfarin for AFib — weekly INR labs, rising to 3.9 (>3.5 limit)
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1201, 17, 53, 1, 'INR', DATE_SUB(NOW(), INTERVAL 28 DAY), 2.1, 'ratio', 'IMPORT', 0, 1, NOW()),
  (1202, 17, 53, 1, 'INR', DATE_SUB(NOW(), INTERVAL 21 DAY), 2.4, 'ratio', 'IMPORT', 0, 1, NOW()),
  (1203, 17, 53, 1, 'INR', DATE_SUB(NOW(), INTERVAL 14 DAY), 2.8, 'ratio', 'IMPORT', 0, 1, NOW()),
  (1204, 17, 53, 1, 'INR', DATE_SUB(NOW(), INTERVAL  7 DAY), 3.1, 'ratio', 'IMPORT', 0, 1, NOW()),
  (1205, 17, 53, 1, 'INR', DATE_SUB(NOW(), INTERVAL  6 HOUR), 3.9, 'ratio', 'IMPORT', 1, 1, NOW());

-- =============================================================================
-- AL-4  Dorothy Vasquez (ep=18, pid=54, fac=1)
--       Sleep + stress wearable — repeated short nights, stress spikes
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1301, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL 14 DAY), 6.2, 'hours', 'DEVICE', 'BAND-DV-004', 0, NULL, NOW()),
  (1302, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL 12 DAY), 5.8, 'hours', 'DEVICE', 'BAND-DV-004', 0, NULL, NOW()),
  (1303, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL 10 DAY), 4.9, 'hours', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1304, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL  8 DAY), 4.5, 'hours', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1305, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL  6 DAY), 4.2, 'hours', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1306, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL  4 DAY), 5.3, 'hours', 'DEVICE', 'BAND-DV-004', 0, NULL, NOW()),
  (1307, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL  2 DAY), 3.9, 'hours', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1308, 18, 54, 1, 'SLEEP_H', DATE_SUB(NOW(), INTERVAL  1 DAY), 4.1, 'hours', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1309, 18, 54, 1, 'STRESS',  DATE_SUB(NOW(), INTERVAL  7 DAY), 62, '0-100', 'DEVICE', 'BAND-DV-004', 0, NULL, NOW()),
  (1310, 18, 54, 1, 'STRESS',  DATE_SUB(NOW(), INTERVAL  5 DAY), 71, '0-100', 'DEVICE', 'BAND-DV-004', 0, NULL, NOW()),
  (1311, 18, 54, 1, 'STRESS',  DATE_SUB(NOW(), INTERVAL  3 DAY), 78, '0-100', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW()),
  (1312, 18, 54, 1, 'STRESS',  DATE_SUB(NOW(), INTERVAL  5 HOUR), 84, '0-100', 'DEVICE', 'BAND-DV-004', 1, NULL, NOW());

-- =============================================================================
-- IP-5  Marcus Delray (ep=19, pid=2, fac=1)
--       Infection/sepsis watch — continuous bedside monitoring
--       SpO2 Q4H declining to 88%; HR 124 bpm; temp 102.1°F
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1401, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 44 HOUR), 97, '%', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1402, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 40 HOUR), 96, '%', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1403, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 36 HOUR), 95, '%', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1404, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 32 HOUR), 94, '%', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1405, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 28 HOUR), 93, '%', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1406, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 24 HOUR), 92, '%', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1407, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 20 HOUR), 91, '%', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1408, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 16 HOUR), 90, '%', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1409, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 12 HOUR), 88, '%', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1410, 19, 2, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL  6 HOUR), 89, '%', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1411, 19, 2, 1, 'HR',   DATE_SUB(NOW(), INTERVAL 24 HOUR),  98, 'bpm', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1412, 19, 2, 1, 'HR',   DATE_SUB(NOW(), INTERVAL 16 HOUR), 108, 'bpm', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1413, 19, 2, 1, 'HR',   DATE_SUB(NOW(), INTERVAL  8 HOUR), 117, 'bpm', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1414, 19, 2, 1, 'HR',   DATE_SUB(NOW(), INTERVAL  4 HOUR), 124, 'bpm', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1415, 19, 2, 1, 'TEMP_F', DATE_SUB(NOW(), INTERVAL 20 HOUR),  99.8, '°F', 'DEVICE', 'MONITOR-BED4', 0, NULL, NOW()),
  (1416, 19, 2, 1, 'TEMP_F', DATE_SUB(NOW(), INTERVAL 12 HOUR), 101.4, '°F', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW()),
  (1417, 19, 2, 1, 'TEMP_F', DATE_SUB(NOW(), INTERVAL  4 HOUR), 102.1, '°F', 'DEVICE', 'MONITOR-BED4', 1, NULL, NOW());

-- =============================================================================
-- IP-6  James Okonkwo (ep=22, pid=8, fac=1)
--       COPD exacerbation — SpO2 consistently below 93%, RR >24
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1501, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 72 HOUR), 90, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1502, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 60 HOUR), 89, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1503, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 48 HOUR), 91, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1504, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 36 HOUR), 88, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1505, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL 24 HOUR), 90, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1506, 22, 8, 1, 'SPO2', DATE_SUB(NOW(), INTERVAL  8 HOUR), 87, '%', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW()),
  (1507, 22, 8, 1, 'RR',   DATE_SUB(NOW(), INTERVAL 48 HOUR), 22, '/min', 'DEVICE', 'MONITOR-BED9', 0, NULL, NOW()),
  (1508, 22, 8, 1, 'RR',   DATE_SUB(NOW(), INTERVAL 24 HOUR), 24, '/min', 'DEVICE', 'MONITOR-BED9', 0, NULL, NOW()),
  (1509, 22, 8, 1, 'RR',   DATE_SUB(NOW(), INTERVAL  8 HOUR), 27, '/min', 'DEVICE', 'MONITOR-BED9', 1, NULL, NOW());

-- =============================================================================
-- HBC-7  Bernard Price (ep=25, pid=61, fac=1)
--        Home cardiac — BP cuff DEVICE, SBP rising to 186 mmHg, HRV declining
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1601, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  7 DAY), 145, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1602, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  6 DAY), 152, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1603, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  5 DAY), 148, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1604, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  4 DAY), 162, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1605, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  3 DAY), 171, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1606, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  2 DAY), 178, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1607, 25, 61, 1, 'SBP', DATE_SUB(NOW(), INTERVAL  5 HOUR), 186, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 1, NULL, NOW()),
  (1608, 25, 61, 1, 'DBP', DATE_SUB(NOW(), INTERVAL  7 DAY),  88, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1609, 25, 61, 1, 'DBP', DATE_SUB(NOW(), INTERVAL  5 HOUR), 104, 'mmHg', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1610, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  7 DAY), 48, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1611, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  6 DAY), 45, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1612, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  5 DAY), 43, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1613, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  4 DAY), 41, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1614, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  3 DAY), 38, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1615, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  2 DAY), 36, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW()),
  (1616, 25, 61, 1, 'HRV', DATE_SUB(NOW(), INTERVAL  5 HOUR), 33, 'ms', 'DEVICE', 'BPCUFF-BP-007', 0, NULL, NOW());

-- =============================================================================
-- HBC-8  Alma Serrano (ep=26, pid=62, fac=1)
--        Home CGM via FHIR bridge — glucose instability, hypos and hypers
--        fhir_id values ensure idempotent re-import
-- =============================================================================

INSERT IGNORE INTO `oei_observation`
  (`id`,`episode_id`,`pid`,`facility_id`,`obs_type_code`,`observed_datetime`,
   `value_numeric`,`unit`,`source_type`,`device_id`,`fhir_id`,`is_flagged`,`noted_by_user_id`,`created_datetime`)
VALUES
  (1701, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  7 DAY),  182, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-001', 0, NULL, NOW()),
  (1702, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 163 HOUR), 64, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-002', 1, NULL, NOW()),
  (1703, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  6 DAY),  195, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-003', 0, NULL, NOW()),
  (1704, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 139 HOUR), 271, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-004', 1, NULL, NOW()),
  (1705, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  5 DAY),  178, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-005', 0, NULL, NOW()),
  (1706, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL 115 HOUR), 68, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-006', 1, NULL, NOW()),
  (1707, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  4 DAY),  201, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-007', 0, NULL, NOW()),
  (1708, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  91 HOUR), 58, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-008', 1, NULL, NOW()),
  (1709, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL   3 DAY),  189, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-009', 0, NULL, NOW()),
  (1710, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  67 HOUR), 289, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-010', 1, NULL, NOW()),
  (1711, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL   2 DAY),  162, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-011', 0, NULL, NOW()),
  (1712, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  43 HOUR), 174, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-012', 0, NULL, NOW()),
  (1713, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL  19 HOUR), 312, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-013', 1, NULL, NOW()),
  (1714, 26, 62, 1, 'GLUCOSE', DATE_SUB(NOW(), INTERVAL   2 HOUR),  62, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-014', 1, NULL, NOW()),
  (1715, 26, 62, 1, 'GLUCOSE_FASTING', DATE_SUB(NOW(), INTERVAL  3 DAY), 138, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-015', 1, NULL, NOW()),
  (1716, 26, 62, 1, 'GLUCOSE_FASTING', DATE_SUB(NOW(), INTERVAL  10 HOUR), 142, 'mg/dL', 'FHIR', 'CGM-AS-003', 'fhir-obs-as-016', 1, NULL, NOW());

SET FOREIGN_KEY_CHECKS=1;

-- =============================================================================
-- WHAT YOU WILL SEE AFTER RUNNING THIS SEED
-- =============================================================================
-- Tracking → Observations  (facility dashboard)
--   All 8 patients listed, grouped by episode, flagged readings highlighted
--
-- AL Resident Board  — yellow 📡⚠ badges on:
--   Eleanor Hartwell  (GLUCOSE 312 mg/dL)
--   George Calloway   (SpO2 91%)
--   Harold Steinberg  (INR 3.9)
--   Dorothy Vasquez   (Sleep 4.1h, Stress 84)
--
-- IP Floor Board  — yellow 📡⚠ badges on:
--   Marcus Delray     (SpO2 89%, HR 124 bpm, Temp 102.1°F)
--   James Okonkwo     (SpO2 87%, RR 27/min)
--
-- HBC Visit Board  — yellow 📡⚠ badges on:
--   Bernard Price     (SBP 186 mmHg)
--   Alma Serrano      (Glucose 312 hyper + 62 hypo today)
--
-- Each patient profile page shows the Extended Observations panel
-- with latest reading per type and full history / trend charts.
-- =============================================================================

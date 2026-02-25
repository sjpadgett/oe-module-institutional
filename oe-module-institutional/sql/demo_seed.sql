-- =============================================================================
-- DEV SEED: oe-module-institutional  —  demo data
-- Builds on dev_seed.sql (locations + directory already inserted).
-- All episodes use OpenEMR pid = 2.
-- Run AFTER table.sql and dev_seed.sql.
-- Safe to re-run: episodes are inserted fresh each run (no ON DUPLICATE KEY
-- on episodes so timestamps stay realistic). Clear with TRUNCATE if needed.
-- =============================================================================

SET @FACILITY_ID := 1;
SET @PID := 2;


-- Locations (beds/rooms)
INSERT INTO oei_location (facility_id, code, name, location_type, unit_name, is_active, sort_order, notes)
VALUES (@FACILITY_ID, 'ED01', 'ED Room 1', 'ROOM', 'ED', 1, 10, 'Seed'),
       (@FACILITY_ID, 'ED02', 'ED Room 2', 'ROOM', 'ED', 1, 20, 'Seed'),
       (@FACILITY_ID, 'OBS1', 'Obs Bay 1', 'OBS', 'OBS', 1, 30, 'Seed')
ON DUPLICATE KEY UPDATE name          = VALUES(name),
                        location_type = VALUES(location_type),
                        unit_name     = VALUES(unit_name),
                        is_active     = VALUES(is_active),
                        sort_order    = VALUES(sort_order);

-- Facility directory entries (receiving)
INSERT INTO oei_facility_directory (facility_id, name, service_type, phone, fax, email, address, hours, notes, is_active, sort_order)
VALUES (@FACILITY_ID, 'Regional Hospital ICU', 'ICU', NULL, NULL, NULL, NULL, NULL, 'Seed', 1, 10),
       (@FACILITY_ID, 'Behavioral Health Receiving', 'BH', NULL, NULL, NULL, NULL, NULL, 'Seed', 1, 20)
ON DUPLICATE KEY UPDATE service_type = VALUES(service_type),
                        is_active    = VALUES(is_active),
                        sort_order   = VALUES(sort_order);

-- =============================================================================
-- EPISODE A: Sepsis Risk  —  qSOFA 3/3, high-alert MAR overdue, task overdue
-- Scenario: 58yo with altered MS and fever, 3h in ED with no disposition.
-- Alert triggers: SEPSIS_RISK (CRITICAL), VITALS_DETERIORATION, MAR_OVERDUE (HA)
-- =============================================================================

INSERT INTO oei_episode
(pid, facility_id, type, start_datetime, status,
 chief_complaint, acuity_esi, arrival_mode,
 assigned_nurse_user_id, assigned_provider_user_id,
 created_by_user_id, created_datetime)
VALUES (@PID, @FACILITY_ID, 'ED',
        DATE_SUB(NOW(), INTERVAL 3 HOUR), 'ACTIVE',
        'Altered mental status, fever',
        2, 'EMS',
        NULL, NULL, 1, NOW());

SET @EP_A := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history
    (episode_id, status_code, set_by_user_id, set_datetime, note)
VALUES (@EP_A, 'ARRIVE', 1, DATE_SUB(NOW(), INTERVAL 180 MINUTE), 'Patient arrived by EMS'),
       (@EP_A, 'TRIAGE', 1, DATE_SUB(NOW(), INTERVAL 175 MINUTE), 'ESI 2 assigned'),
       (@EP_A, 'ROOMED', 1, DATE_SUB(NOW(), INTERVAL 170 MINUTE), 'Placed in ED Room 1');

INSERT INTO oei_episode_event
    (episode_id, pid, facility_id, event_type, event_datetime, user_id, note)
VALUES (@EP_A, @PID, @FACILITY_ID, 'ARRIVE', DATE_SUB(NOW(), INTERVAL 180 MINUTE), 1, 'EMS arrival'),
       (@EP_A, @PID, @FACILITY_ID, 'ROOM', DATE_SUB(NOW(), INTERVAL 170 MINUTE), 1, 'ED Room 1');

INSERT INTO oei_episode_location
(episode_id, pid, facility_id,
 location_id, location_code,
 start_datetime, end_datetime, user_id, note)
SELECT @EP_A,
       @PID,
       @FACILITY_ID,
       id,
       code,
       DATE_SUB(NOW(), INTERVAL 170 MINUTE),
       NULL,
       1,
       'Roomed'
FROM oei_location
WHERE facility_id = @FACILITY_ID
  AND code = 'ED01'
LIMIT 1;

INSERT INTO oei_triage
(episode_id, pid, facility_id, set_number,
 bp_systolic, bp_diastolic, hr, rr, temp_f,
 spo2, gcs, pain_score, weight_kg,
 arrival_mode, esi_suggested,
 notes, noted_by_user_id, noted_datetime)
VALUES (@EP_A, @PID, @FACILITY_ID, 1,
        95, 62, 118, 24, 101.8,
        94, 13, 6, 82.0,
        'EMS', 2,
        'Patient confused on arrival. Diaphoretic. Warm to touch. Family reports 2-day history of productive cough and worsening confusion.',
        1, DATE_SUB(NOW(), INTERVAL 175 MINUTE));

INSERT INTO oei_task
(episode_id, pid, facility_id, task_type,
 due_datetime, status, payload_json,
 created_by_user_id, created_datetime)
VALUES (@EP_A, @PID, @FACILITY_ID, 'BLOOD_CULTURE',
        DATE_SUB(NOW(), INTERVAL 90 MINUTE),
        'OPEN', '{"priority":"URGENT","note":"2 sets required before antibiotics"}',
        1, DATE_SUB(NOW(), INTERVAL 170 MINUTE)),
       (@EP_A, @PID, @FACILITY_ID, 'VITALS_CHECK',
        DATE_SUB(NOW(), INTERVAL 30 MINUTE),
        'OPEN', '{"source":"auto"}',
        1, DATE_SUB(NOW(), INTERVAL 170 MINUTE)),
       (@EP_A, @PID, @FACILITY_ID, 'VITALS_CHECK',
        DATE_ADD(NOW(), INTERVAL 90 MINUTE),
        'OPEN', '{"source":"auto"}',
        1, DATE_SUB(NOW(), INTERVAL 170 MINUTE));

INSERT INTO oei_mar_order
(episode_id, pid, facility_id,
 drug_name, dose, unit, route, frequency,
 is_prn, status, ordered_datetime,
 ordered_by_user_id, instructions,
 created_datetime, updated_datetime)
VALUES (@EP_A, @PID, @FACILITY_ID,
        'Vancomycin', '1500', 'mg', 'IV', 'Q8H',
        0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 160 MINUTE),
        1, 'Infuse over 90 min. Monitor renal function.',
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE));

SET @ORD_A1 := LAST_INSERT_ID();

INSERT INTO oei_mar_order
(episode_id, pid, facility_id,
 drug_name, dose, unit, route, frequency,
 is_prn, status, ordered_datetime,
 ordered_by_user_id, instructions,
 created_datetime, updated_datetime)
VALUES (@EP_A, @PID, @FACILITY_ID,
        'Normal Saline', '500', 'mL', 'IV', 'Q4H',
        0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 160 MINUTE),
        1, 'Bolus for hypotension.',
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE));

SET @ORD_A2 := LAST_INSERT_ID();

INSERT INTO oei_mar_administration
(mar_order_id, episode_id, pid, facility_id,
 scheduled_datetime, administered_datetime,
 outcome, dose_given, unit_given, route_given,
 administered_by_user_id, is_high_alert,
 created_datetime, updated_datetime)
VALUES (@ORD_A1, @EP_A, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 160 MINUTE),
        DATE_SUB(NOW(), INTERVAL 158 MINUTE),
        'GIVEN', '1500', 'mg', 'IV',
        1, 1,
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 158 MINUTE)),
       (@ORD_A1, @EP_A, @PID, @FACILITY_ID,
        DATE_ADD(NOW(), INTERVAL 320 MINUTE),
        NULL,
        'PENDING', NULL, NULL, NULL,
        NULL, 1,
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE));

INSERT INTO oei_mar_administration
(mar_order_id, episode_id, pid, facility_id,
 scheduled_datetime,
 outcome, is_high_alert,
 created_datetime, updated_datetime)
VALUES (@ORD_A2, @EP_A, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 45 MINUTE),
        'PENDING', 0,
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE)),
       (@ORD_A2, @EP_A, @PID, @FACILITY_ID,
        DATE_ADD(NOW(), INTERVAL 195 MINUTE),
        'PENDING', 0,
        DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE));

-- =============================================================================
-- EPISODE B: Observation — near runway end (22h of 24h target)
-- Scenario: Chest pain r/o ACS admitted to observation.
-- Alert triggers: OBS_RUNWAY (CRITICAL), MAR_OVERDUE (heparin high-alert)
-- =============================================================================

INSERT INTO oei_episode
(pid, facility_id, type, start_datetime, status,
 chief_complaint, acuity_esi, arrival_mode,
 assigned_nurse_user_id, assigned_provider_user_id,
 created_by_user_id, created_datetime)
VALUES (@PID, @FACILITY_ID, 'OBS',
        DATE_SUB(NOW(), INTERVAL 22 HOUR), 'ACTIVE',
        'Chest pain, r/o ACS',
        3, 'WALKIN',
        NULL, NULL, 1, NOW());

SET @EP_B := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history
    (episode_id, status_code, set_by_user_id, set_datetime, note)
VALUES (@EP_B, 'ARRIVE', 1, DATE_SUB(NOW(), INTERVAL 22 HOUR), 'Walk-in arrival'),
       (@EP_B, 'TRIAGE', 1, DATE_SUB(NOW(), INTERVAL 1319 MINUTE), 'ESI 3'),
       (@EP_B, 'ROOMED', 1, DATE_SUB(NOW(), INTERVAL 1315 MINUTE), 'Placed in Obs Bay 1'),
       (@EP_B, 'OBS_START', 1, DATE_SUB(NOW(), INTERVAL 20 HOUR), 'Observation status started');

INSERT INTO oei_episode_event
    (episode_id, pid, facility_id, event_type, event_datetime, user_id, note)
VALUES (@EP_B, @PID, @FACILITY_ID, 'ARRIVE', DATE_SUB(NOW(), INTERVAL 22 HOUR), 1, 'Walk-in'),
       (@EP_B, @PID, @FACILITY_ID, 'ROOM', DATE_SUB(NOW(), INTERVAL 1315 MINUTE), 1, 'Obs Bay 1'),
       (@EP_B, @PID, @FACILITY_ID, 'OBS_START', DATE_SUB(NOW(), INTERVAL 20 HOUR), 1, 'Chest pain protocol');

INSERT INTO oei_episode_location
(episode_id, pid, facility_id,
 location_id, location_code,
 start_datetime, end_datetime, user_id, note)
SELECT @EP_B,
       @PID,
       @FACILITY_ID,
       id,
       code,
       DATE_SUB(NOW(), INTERVAL 22 HOUR),
       NULL,
       1,
       'Obs admission'
FROM oei_location
WHERE facility_id = @FACILITY_ID
  AND code = 'OBS1'
LIMIT 1;

INSERT INTO oei_triage
(episode_id, pid, facility_id, set_number,
 bp_systolic, bp_diastolic, hr, rr, temp_f,
 spo2, gcs, pain_score, weight_kg,
 arrival_mode, esi_suggested,
 notes, noted_by_user_id, noted_datetime)
VALUES (@EP_B, @PID, @FACILITY_ID, 1,
        148, 92, 96, 16, 98.6,
        98, 15, 7, 91.0,
        'WALKIN', 3,
        'Substernal chest pressure x 2h, radiating to left arm. Diaphoresis. Denies SOB.',
        1, DATE_SUB(NOW(), INTERVAL 22 HOUR)),
       (@EP_B, @PID, @FACILITY_ID, 2,
        142, 88, 82, 15, 98.4,
        99, 15, 4, 91.0,
        'WALKIN', 3,
        'Re-triage 12h. Chest pain improved with nitro. Ambulatory. Tolerating clear liquids.',
        1, DATE_SUB(NOW(), INTERVAL 10 HOUR));

INSERT INTO oei_obs_plan
(episode_id, pid, facility_id,
 protocol_key, status,
 start_datetime, target_hours, runway_hours,
 protocol_json,
 updated_by_user_id, updated_datetime)
VALUES (@EP_B, @PID, @FACILITY_ID,
        'CHEST_PAIN', 'ACTIVE',
        DATE_SUB(NOW(), INTERVAL 20 HOUR), 24, 4,
        '{"protocol_key":"CHEST_PAIN","label":"Chest Pain Observation","target_hours":24,"runway_hours":4,"tasks":[{"type":"EKG","every_minutes":360,"label":"12-lead EKG"},{"type":"TROPONIN","at_minutes":[0,360,720],"label":"Serial Troponins"},{"type":"VITALS_CHECK","every_minutes":240,"label":"Vitals Q4H"}]}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO oei_task
(episode_id, pid, facility_id, task_type,
 due_datetime, status, payload_json,
 created_by_user_id, created_datetime)
VALUES (@EP_B, @PID, @FACILITY_ID, 'TROPONIN',
        DATE_SUB(NOW(), INTERVAL 20 HOUR),
        'COMPLETE', '{"label":"Serial Troponin #1"}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
       (@EP_B, @PID, @FACILITY_ID, 'TROPONIN',
        DATE_SUB(NOW(), INTERVAL 14 HOUR),
        'COMPLETE', '{"label":"Serial Troponin #2"}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
       (@EP_B, @PID, @FACILITY_ID, 'TROPONIN',
        DATE_SUB(NOW(), INTERVAL 8 HOUR),
        'COMPLETE', '{"label":"Serial Troponin #3"}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
       (@EP_B, @PID, @FACILITY_ID, 'DISPOSITION_DECISION',
        DATE_SUB(NOW(), INTERVAL 2 HOUR),
        'OPEN', '{"label":"Cardiology consult or discharge disposition required"}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
       (@EP_B, @PID, @FACILITY_ID, 'VITALS_CHECK',
        DATE_SUB(NOW(), INTERVAL 1 HOUR),
        'OPEN', '{"source":"auto"}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO oei_mar_order
(episode_id, pid, facility_id,
 drug_name, dose, unit, route, frequency,
 is_prn, status, ordered_datetime,
 ordered_by_user_id, instructions,
 created_datetime, updated_datetime)
VALUES (@EP_B, @PID, @FACILITY_ID,
        'Aspirin', '325', 'mg', 'PO', 'QD',
        0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 22 HOUR),
        1, 'Chew first dose.',
        DATE_SUB(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR));

SET @ORD_B1 := LAST_INSERT_ID();

INSERT INTO oei_mar_order
(episode_id, pid, facility_id,
 drug_name, dose, unit, route, frequency,
 is_prn, status, ordered_datetime,
 ordered_by_user_id, instructions,
 created_datetime, updated_datetime)
VALUES (@EP_B, @PID, @FACILITY_ID,
        'Heparin', '5000', 'units', 'SQ', 'Q8H',
        0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 HOUR),
        1, 'Check aPTT Q6H. Hold for platelet < 100k.',
        DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR));

SET @ORD_B2 := LAST_INSERT_ID();

INSERT INTO oei_mar_administration
(mar_order_id, episode_id, pid, facility_id,
 scheduled_datetime, administered_datetime,
 outcome, dose_given, unit_given, route_given, site,
 administered_by_user_id, is_high_alert,
 created_datetime, updated_datetime)
VALUES (@ORD_B1, @EP_B, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 22 HOUR),
        DATE_SUB(NOW(), INTERVAL 22 HOUR),
        'GIVEN', '325', 'mg', 'PO', NULL,
        1, 0,
        DATE_SUB(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR)),
       (@ORD_B2, @EP_B, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 20 HOUR),
        DATE_SUB(NOW(), INTERVAL 20 HOUR),
        'GIVEN', '5000', 'units', 'SQ', 'Abdomen',
        1, 1,
        DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
       (@ORD_B2, @EP_B, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 12 HOUR),
        DATE_SUB(NOW(), INTERVAL 12 HOUR),
        'GIVEN', '5000', 'units', 'SQ', 'Abdomen',
        1, 1,
        DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
       (@ORD_B2, @EP_B, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 4 HOUR),
        NULL,
        'PENDING', NULL, NULL, NULL, NULL,
        NULL, 1,
        DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR));

-- =============================================================================
-- EPISODE C: BH Boarding — no room, LWBS risk, suicide risk flagged
-- Scenario: 34yo voluntary psychiatric crisis, boarding for 5h awaiting placement.
-- Alert triggers: LWBS_RISK (CRITICAL), BH_BOARDING_DWELL (CRITICAL)
-- =============================================================================

INSERT INTO oei_episode
(pid, facility_id, type, start_datetime, status,
 chief_complaint, acuity_esi, arrival_mode,
 assigned_nurse_user_id, assigned_provider_user_id,
 created_by_user_id, created_datetime)
VALUES (@PID, @FACILITY_ID, 'ED',
        DATE_SUB(NOW(), INTERVAL 5 HOUR), 'ACTIVE',
        'Psychiatric evaluation, suicidal ideation',
        3, 'POLICE',
        NULL, NULL, 1, NOW());

SET @EP_C := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history
    (episode_id, status_code, set_by_user_id, set_datetime, note)
VALUES (@EP_C, 'ARRIVE', 1, DATE_SUB(NOW(), INTERVAL 5 HOUR), 'Arrived with police escort'),
       (@EP_C, 'TRIAGE', 1, DATE_SUB(NOW(), INTERVAL 299 MINUTE), 'ESI 3'),
       (@EP_C, 'WAITING', 1, DATE_SUB(NOW(), INTERVAL 295 MINUTE), 'Awaiting BH room/placement');

INSERT INTO oei_episode_event
    (episode_id, pid, facility_id, event_type, event_datetime, user_id, note)
VALUES (@EP_C, @PID, @FACILITY_ID, 'ARRIVE', DATE_SUB(NOW(), INTERVAL 5 HOUR), 1, 'Police escort');

INSERT INTO oei_triage
(episode_id, pid, facility_id, set_number,
 bp_systolic, bp_diastolic, hr, rr, temp_f,
 spo2, gcs, pain_score,
 arrival_mode, esi_suggested,
 notes, noted_by_user_id, noted_datetime)
VALUES (@EP_C, @PID, @FACILITY_ID, 1,
        124, 78, 88, 14, 98.1,
        99, 15, 0,
        'POLICE', 3,
        'Patient calm. Reports passive SI with plan. Denies HI. Last substance use: EtOH 6h ago. No acute medical complaints.',
        1, DATE_SUB(NOW(), INTERVAL 299 MINUTE));

INSERT INTO oei_bh_safety
(episode_id, pid, facility_id,
 observation_level, is_involuntary,
 risk_violence, risk_suicide, elopement_risk,
 precautions_json,
 updated_by_user_id, updated_datetime)
VALUES (@EP_C, @PID, @FACILITY_ID,
        '1:1', 0,
        0, 1, 1,
        '{"items":["Sharps removed","Clothing searched","Belts/laces removed","1:1 sitter assigned"]}',
        1, DATE_SUB(NOW(), INTERVAL 290 MINUTE));

INSERT INTO oei_bh_boarding
(episode_id, pid, facility_id,
 legal_status, suicide_risk, violence_risk,
 placement_status, accepting_facility,
 emtala_complete, checklist_json,
 notes,
 updated_by_user_id, updated_datetime)
VALUES (@EP_C, @PID, @FACILITY_ID,
        'VOLUNTARY', 'HIGH', 'LOW',
        'SEARCHING', NULL,
        0,
        '{"items":[{"label":"EMTALA completed","done":false},{"label":"Insurance verified","done":true},{"label":"Placement calls initiated","done":true},{"label":"Accepting facility confirmed","done":false}]}',
        'Calls placed to 3 facilities. Regional BH at capacity. State hospital waitlist open.',
        1, DATE_SUB(NOW(), INTERVAL 290 MINUTE));

INSERT INTO oei_task
(episode_id, pid, facility_id, task_type,
 due_datetime, status, payload_json,
 created_by_user_id, created_datetime)
VALUES (@EP_C, @PID, @FACILITY_ID, 'BH_PLACEMENT_CALL',
        DATE_SUB(NOW(), INTERVAL 2 HOUR),
        'OPEN', '{"priority":"HIGH","note":"Follow up on State Hospital waitlist"}',
        1, DATE_SUB(NOW(), INTERVAL 290 MINUTE)),
       (@EP_C, @PID, @FACILITY_ID, 'VITALS_CHECK',
        DATE_SUB(NOW(), INTERVAL 1 HOUR),
        'OPEN', '{"source":"auto"}',
        1, DATE_SUB(NOW(), INTERVAL 290 MINUTE));

-- =============================================================================
-- EPISODE D: Standard ED — ankle injury, normal vitals, good workflow
-- Scenario: 28yo sprained ankle, ESI 4, assigned nurse and provider.
-- Demonstrates: normal episode, assignments, allergy match (ketorolac/NSAID)
-- =============================================================================

INSERT INTO oei_episode
(pid, facility_id, type, start_datetime, status,
 chief_complaint, acuity_esi, arrival_mode,
 assigned_nurse_user_id, assigned_provider_user_id,
 created_by_user_id, created_datetime)
VALUES (@PID, @FACILITY_ID, 'ED',
        DATE_SUB(NOW(), INTERVAL 1 HOUR), 'ACTIVE',
        'Right ankle pain after fall',
        4, 'WALKIN',
        1, 1,
        1, NOW());

SET @EP_D := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history
    (episode_id, status_code, set_by_user_id, set_datetime, note)
VALUES (@EP_D, 'ARRIVE', 1, DATE_SUB(NOW(), INTERVAL 60 MINUTE), 'Walk-in'),
       (@EP_D, 'TRIAGE', 1, DATE_SUB(NOW(), INTERVAL 55 MINUTE), 'ESI 4'),
       (@EP_D, 'ROOMED', 1, DATE_SUB(NOW(), INTERVAL 50 MINUTE), 'ED Room 2'),
       (@EP_D, 'PROVIDER_EVALUATION', 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE), 'Provider at bedside');

INSERT INTO oei_episode_event
    (episode_id, pid, facility_id, event_type, event_datetime, user_id, note)
VALUES (@EP_D, @PID, @FACILITY_ID, 'ARRIVE', DATE_SUB(NOW(), INTERVAL 60 MINUTE), 1, 'Walk-in'),
       (@EP_D, @PID, @FACILITY_ID, 'ROOM', DATE_SUB(NOW(), INTERVAL 50 MINUTE), 1, 'ED Room 2'),
       (@EP_D, @PID, @FACILITY_ID, 'PROVIDER', DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 'Provider evaluation started');

INSERT INTO oei_episode_location
(episode_id, pid, facility_id,
 location_id, location_code,
 start_datetime, end_datetime, user_id, note)
SELECT @EP_D,
       @PID,
       @FACILITY_ID,
       id,
       code,
       DATE_SUB(NOW(), INTERVAL 50 MINUTE),
       NULL,
       1,
       'Roomed'
FROM oei_location
WHERE facility_id = @FACILITY_ID
  AND code = 'ED02'
LIMIT 1;

INSERT INTO oei_triage
(episode_id, pid, facility_id, set_number,
 bp_systolic, bp_diastolic, hr, rr, temp_f,
 spo2, gcs, pain_score, weight_kg,
 arrival_mode, esi_suggested,
 notes, noted_by_user_id, noted_datetime)
VALUES (@EP_D, @PID, @FACILITY_ID, 1,
        118, 74, 78, 14, 98.2,
        99, 15, 6, 74.0,
        'WALKIN', 4,
        'Twisted right ankle on stairs 2h ago. Swelling and bruising noted lateral malleolus. Neurovascularly intact distal to injury. NWB.',
        1, DATE_SUB(NOW(), INTERVAL 55 MINUTE));

INSERT INTO oei_task
(episode_id, pid, facility_id, task_type,
 due_datetime, status, payload_json,
 created_by_user_id, created_datetime)
VALUES (@EP_D, @PID, @FACILITY_ID, 'X_RAY_ORDER',
        DATE_SUB(NOW(), INTERVAL 25 MINUTE),
        'COMPLETE', '{"label":"Right ankle 3 views"}',
        1, DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
       (@EP_D, @PID, @FACILITY_ID, 'X_RAY_REVIEW',
        DATE_ADD(NOW(), INTERVAL 10 MINUTE),
        'OPEN', '{"label":"Review radiology read with patient"}',
        1, DATE_SUB(NOW(), INTERVAL 50 MINUTE)),
       (@EP_D, @PID, @FACILITY_ID, 'DISCHARGE_INSTRUCTIONS',
        DATE_ADD(NOW(), INTERVAL 30 MINUTE),
        'OPEN', '{"label":"Print and review ankle sprain discharge instructions"}',
        1, DATE_SUB(NOW(), INTERVAL 50 MINUTE));

INSERT INTO oei_mar_order
(episode_id, pid, facility_id,
 drug_name, dose, unit, route, frequency,
 is_prn, status, ordered_datetime,
 ordered_by_user_id, instructions,
 created_datetime, updated_datetime)
VALUES (@EP_D, @PID, @FACILITY_ID,
        'Ketorolac', '30', 'mg', 'IV', 'PRN',
        1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 35 MINUTE),
        1, 'PRN pain > 6/10. Max 5 days. Avoid in renal impairment.',
        DATE_SUB(NOW(), INTERVAL 35 MINUTE), DATE_SUB(NOW(), INTERVAL 35 MINUTE));

SET @ORD_D1 := LAST_INSERT_ID();

INSERT INTO oei_mar_administration
(mar_order_id, episode_id, pid, facility_id,
 scheduled_datetime, administered_datetime,
 outcome, dose_given, unit_given, route_given, site,
 administered_by_user_id, note, is_high_alert,
 created_datetime, updated_datetime)
VALUES (@ORD_D1, @EP_D, @PID, @FACILITY_ID,
        DATE_SUB(NOW(), INTERVAL 28 MINUTE),
        DATE_SUB(NOW(), INTERVAL 28 MINUTE),
        'GIVEN', '30', 'mg', 'IV', 'Right AC',
        1, 'Pain 7/10 pre-dose, 4/10 post-dose at 15min.', 0,
        DATE_SUB(NOW(), INTERVAL 28 MINUTE), DATE_SUB(NOW(), INTERVAL 28 MINUTE));

INSERT INTO oei_episode_disposition
(episode_id, pid, facility_id,
 disposition_code, destination,
 decision_datetime, admit_flag,
 notes, updated_by_user_id, updated_datetime)
VALUES (@EP_D, @PID, @FACILITY_ID,
        'DISCHARGE', 'Home with follow-up',
        DATE_SUB(NOW(), INTERVAL 5 MINUTE), 0,
        'Likely lateral ankle sprain. No fracture on Ottawa criteria. Weight bear as tolerated. Ortho f/u if not improving in 1 week.',
        1, DATE_SUB(NOW(), INTERVAL 5 MINUTE));

-- =============================================================================
-- SETTINGS: ensure demo-friendly thresholds are set
-- =============================================================================

INSERT INTO oei_settings
    (facility_id, setting_key, setting_value, updated_by_user_id, updated_datetime)
VALUES (@FACILITY_ID, 'lwbs_threshold_min', '120', NULL, NOW()),
       (@FACILITY_ID, 'boarding_alert_hours', '4', NULL, NOW()),
       (@FACILITY_ID, 'obs_runway_warning_hours', '4', NULL, NOW()),
       (@FACILITY_ID, 'vitals_interval_ed_min', '120', NULL, NOW()),
       (@FACILITY_ID, 'vitals_interval_obs_min', '240', NULL, NOW()),
       (@FACILITY_ID, 'vitals_window_hours', '12', NULL, NOW()),
       (@FACILITY_ID, 'hl7_enabled', '0', NULL, NOW()),
       (@FACILITY_ID, 'hl7_processing_id', 'T', NULL, NOW())
ON DUPLICATE KEY UPDATE setting_value    = VALUES(setting_value),
                        updated_datetime = NOW();

-- =============================================================================
-- SCHEMA VERSION RECORD
-- =============================================================================

INSERT IGNORE INTO oei_schema_version (version, applied_datetime)
VALUES ('0.9.4-demo', NOW());

-- =============================================================================
-- SUMMARY (visual reference — remove if your client doesn't support SELECT)
-- =============================================================================

SELECT e.id                                           AS episode_id,
       e.type,
       e.acuity_esi                                   AS esi,
       e.chief_complaint,
       COALESCE(l.name, '(no room)')                  AS room,
       TIMESTAMPDIFF(MINUTE, e.start_datetime, NOW()) AS minutes_in_ed,
       sh.status_code
FROM oei_episode e
         LEFT JOIN oei_episode_location el
                   ON el.episode_id = e.id AND el.end_datetime IS NULL
         LEFT JOIN oei_location l
                   ON l.id = el.location_id
         LEFT JOIN (SELECT episode_id, status_code
                    FROM oei_episode_status_history
                    WHERE (episode_id, id) IN (SELECT episode_id, MAX(id)
                                               FROM oei_episode_status_history
                                               GROUP BY episode_id)) sh ON sh.episode_id = e.id
WHERE e.facility_id = @FACILITY_ID
  AND e.status = 'ACTIVE'
ORDER BY e.start_datetime ASC;

-- =============================================================================
-- DEMO SEED v1.0.0  —  oe-module-institutional
-- =============================================================================
-- Designed for investor and stakeholder demonstrations.
-- Simulates a busy mid-morning ED shift at Community Memorial Hospital with
-- 10 simultaneous patients across every major workflow:
--
--   EP_A  James Wilson, 58M        Sepsis / Pneumonia            3h  ESI-2
--   EP_B  Margaret Chen, 67F       Chest Pain OBS (22h runway)  22h  ESI-3
--   EP_C  Tyler Brooks, 34M        BH Boarding — SI              5h  ESI-3
--   EP_D  Sofia Ramirez, 28F       Ankle Sprain, ready dc        1h  ESI-4
--   EP_E  Robert Patel, 72M        STROKE ALERT — active        25m  ESI-1
--   EP_F  Linda Torres, 45F        MVA Transfer Out              2h  ESI-2
--   EP_G  David Kim, 61M           COPD Obs, near discharge      12h  ESI-3
--   EP_H  Emma Johnson, 7F         Pediatric Fever              45m  ESI-3
--   EP_I  Marcus Williams, 29M     Opioid Overdose / Naloxone   90m  ESI-2
--   EP_J  Patricia Nguyen, 52F     BH Boarding — Accepted        8h  ESI-3
--
-- Features demonstrated:
--   ED Board · Bed Management · Triage · MAR/Administration
--   Observation Protocols · Task Management · BH Safety/Boarding
--   Transfer Tracking · E-Referral (Draft + Sent + Accepted)
--   Diversion Status (Trauma=DIVERSION, PSYCH=DIVERSION, ICU=LIMITED)
--   HL7 ADT Log · Alert Acknowledgement · Care Context · Settings
--
-- PREREQUISITES:
--   1. OpenEMR demo install (patients pid 2-11 must exist or will be inserted)
--   2. Run table.sql, sql/context.sql, sql/diversion.sql first
--   3. Safe to re-run — uses INSERT IGNORE / ON DUPLICATE KEY where safe
--
-- TO RESET BETWEEN DEMOS (uncomment):
-- TRUNCATE oei_episode;            -- cascades via FK or delete manually
-- TRUNCATE oei_episode_status_history;
-- TRUNCATE oei_episode_event;
-- TRUNCATE oei_episode_location;
-- TRUNCATE oei_triage;
-- TRUNCATE oei_task;
-- TRUNCATE oei_mar_order;
-- TRUNCATE oei_mar_administration;
-- TRUNCATE oei_obs_plan;
-- TRUNCATE oei_bh_safety;
-- TRUNCATE oei_bh_boarding;
-- TRUNCATE oei_episode_disposition;
-- TRUNCATE oei_ereferral;
-- TRUNCATE oei_transfer;
-- TRUNCATE oei_hl7_outbound_log;
-- TRUNCATE oei_alert_ack;
-- TRUNCATE oei_diversion;
-- TRUNCATE oei_diversion_history;
-- DELETE FROM oei_schema_version WHERE version LIKE '%-demo';
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- ── Facility variable ─────────────────────────────────────────────────────────
SET @FAC := 1;

-- ── Patient PIDs ──────────────────────────────────────────────────────────────
-- Uses pids 2-11. INSERT IGNORE so existing patient_data is never overwritten.
SET @P1  := 2;    -- James Wilson, 58M
SET @P2  := 3;    -- Margaret Chen, 67F
SET @P3  := 4;    -- Tyler Brooks, 34M
SET @P4  := 5;    -- Sofia Ramirez, 28F
SET @P5  := 6;    -- Robert Patel, 72M
SET @P6  := 7;    -- Linda Torres, 45F
SET @P7  := 8;    -- David Kim, 61M
SET @P8  := 9;    -- Emma Johnson, 7F
SET @P9  := 10;   -- Marcus Williams, 29M
SET @P10 := 11;   -- Patricia Nguyen, 52F

-- ── Demo patient records ──────────────────────────────────────────────────────
INSERT IGNORE INTO patient_data
    (pid, fname, lname, DOB, sex, pubpid, date)
VALUES
    (2,  'James',    'Wilson',    '1967-03-15', 'Male',   'DEMO-002', NOW()),
    (3,  'Margaret', 'Chen',      '1957-08-22', 'Female', 'DEMO-003', NOW()),
    (4,  'Tyler',    'Brooks',    '1990-05-11', 'Male',   'DEMO-004', NOW()),
    (5,  'Sofia',    'Ramirez',   '1996-11-30', 'Female', 'DEMO-005', NOW()),
    (6,  'Robert',   'Patel',     '1952-01-08', 'Male',   'DEMO-006', NOW()),
    (7,  'Linda',    'Torres',    '1979-07-19', 'Female', 'DEMO-007', NOW()),
    (8,  'David',    'Kim',       '1963-04-25', 'Male',   'DEMO-008', NOW()),
    (9,  'Emma',     'Johnson',   '2017-09-03', 'Female', 'DEMO-009', NOW()),
    (10, 'Marcus',   'Williams',  '1995-12-14', 'Male',   'DEMO-010', NOW()),
    (11, 'Patricia', 'Nguyen',    '1972-02-28', 'Female', 'DEMO-011', NOW());


-- =============================================================================
-- SECTION 1: LOCATIONS
-- Full complement: 8 ED rooms, 2 trauma bays, 4 OBS bays, 2 hallway beds,
-- 2 psych/BH holding rooms, 1 waiting area.
-- =============================================================================

INSERT INTO oei_location
    (facility_id, code, name, location_type, unit_name, is_active, sort_order, notes)
VALUES
    -- ED rooms
    (@FAC, 'ED01', 'ED Room 1',     'ROOM', 'ED', 1,  10, 'Standard ED room'),
    (@FAC, 'ED02', 'ED Room 2',     'ROOM', 'ED', 1,  20, 'Standard ED room'),
    (@FAC, 'ED03', 'ED Room 3',     'ROOM', 'ED', 1,  30, 'Standard ED room'),
    (@FAC, 'ED04', 'ED Room 4',     'ROOM', 'ED', 1,  40, 'Standard ED room'),
    (@FAC, 'ED05', 'ED Room 5',     'ROOM', 'ED', 1,  50, 'Standard ED room'),
    (@FAC, 'ED06', 'ED Room 6',     'ROOM', 'ED', 1,  60, 'Isolation capable'),
    (@FAC, 'ED07', 'ED Room 7',     'ROOM', 'ED', 1,  70, 'Standard ED room'),
    (@FAC, 'ED08', 'ED Room 8',     'ROOM', 'ED', 1,  80, 'Standard ED room'),
    -- Trauma bays
    (@FAC, 'TR01', 'Trauma Bay 1',  'ROOM', 'ED', 1,  90, 'Full resuscitation equipment'),
    (@FAC, 'TR02', 'Trauma Bay 2',  'ROOM', 'ED', 1, 100, 'Full resuscitation equipment'),
    -- Observation bays
    (@FAC, 'OBS1', 'Obs Bay 1',     'OBS',  'OBS', 1, 110, 'Telemetry monitored'),
    (@FAC, 'OBS2', 'Obs Bay 2',     'OBS',  'OBS', 1, 120, 'Telemetry monitored'),
    (@FAC, 'OBS3', 'Obs Bay 3',     'OBS',  'OBS', 1, 130, 'Telemetry monitored'),
    (@FAC, 'OBS4', 'Obs Bay 4',     'OBS',  'OBS', 1, 140, 'Standard monitoring'),
    -- Hallway beds (capacity overflow — shows operational pressure)
    (@FAC, 'HALL1', 'Hallway Bed 1', 'ROOM', 'ED', 1, 150, 'Overflow capacity'),
    (@FAC, 'HALL2', 'Hallway Bed 2', 'ROOM', 'ED', 1, 160, 'Overflow capacity'),
    -- BH/Psych holding
    (@FAC, 'PSY1', 'BH Room 1',     'ROOM', 'BH', 1, 170, 'Sitter-equipped, ligature-reduced'),
    (@FAC, 'PSY2', 'BH Room 2',     'ROOM', 'BH', 1, 180, 'Sitter-equipped, ligature-reduced'),
    -- Waiting area placeholder
    (@FAC, 'WAIT', 'Waiting Room',  'WAIT', 'ED', 1, 190, 'Tracked patients awaiting placement')
ON DUPLICATE KEY UPDATE
    name          = VALUES(name),
    location_type = VALUES(location_type),
    unit_name     = VALUES(unit_name),
    is_active     = VALUES(is_active),
    sort_order    = VALUES(sort_order),
    notes         = VALUES(notes);


-- =============================================================================
-- SECTION 2: FACILITY DIRECTORY (receiving / referral destinations)
-- =============================================================================

INSERT INTO oei_facility_directory
    (facility_id, name, service_type, phone, fax, address, hours, notes, is_active, sort_order)
VALUES
    (@FAC, 'Regional Trauma Center',         'TRAUMA',       '(555) 900-1100', '(555) 900-1101',
     '1200 Trauma Pkwy, Riverside, CA 92501', '24/7',
     'Level I Trauma Center. Direct accept line ext 4400.', 1, 10),

    (@FAC, 'St. Michael Medical Center ICU', 'ICU',          '(555) 210-4400', '(555) 210-4401',
     '800 Medical Center Dr, Riverside, CA 92507', '24/7',
     'Accepts direct ICU admits. Intensivist on call 24/7.', 1, 20),

    (@FAC, 'Valley Behavioral Health Center','BH',           '(555) 340-7700', '(555) 340-7701',
     '3300 Valley View Rd, Moreno Valley, CA 92557', 'M-F 8am-8pm; On-call 24/7',
     'Adult voluntary and involuntary. 22-bed capacity. Call 3h ahead.', 1, 30),

    (@FAC, 'Regional Stroke & Neuro Center', 'NEURO',        '(555) 450-2200', '(555) 450-2201',
     '950 Neuroscience Blvd, Riverside, CA 92501', '24/7 Stroke Team',
     'Comprehensive Stroke Center. tPA and thrombectomy capable.', 1, 40),

    (@FAC, 'Sunrise Skilled Nursing Facility','SNF',          '(555) 580-3300', '(555) 580-3301',
     '201 Sunrise Terrace, Moreno Valley, CA 92553', 'M-F 8am-5pm',
     'Medicare-certified. PT/OT/speech. Med-surg level care.', 1, 50),

    (@FAC, 'Valley Home Health Agency',      'HOME_HEALTH',  '(555) 620-5500', '(555) 620-5501',
     '1040 Home Care Way, Riverside, CA 92503', 'M-F 8am-6pm; on-call after hours',
     'IV therapy, wound care, medication management.', 1, 60),

    (@FAC, 'Mountain Cardiology Group',      'CARDIOLOGY',   '(555) 730-8800', '(555) 730-8801',
     '505 Heart Lane, Riverside, CA 92506', 'M-F 9am-5pm; on-call 24/7',
     'Follow-up within 48-72h for ACS discharge.', 1, 70),

    (@FAC, 'Orthopedic Associates of Riverside','ORTHOPEDIC','(555) 840-6600', '(555) 840-6601',
     '720 Bone & Joint Dr, Riverside, CA 92505', 'M-F 8am-5pm',
     'Walk-in fracture clinic M/W/F. Fax referral + x-ray CD.', 1, 80),

    (@FAC, 'Mountain Urology Associates',    'UROLOGY',      '(555) 950-4400', '(555) 950-4401',
     '338 Renal Blvd, Riverside, CA 92504', 'M-F 9am-5pm',
     'Stone clinic. Fax CT report. Follow up within 1 week.', 1, 90),

    (@FAC, 'LTACH — Valley Long-Term Acute', 'LTACH',        '(555) 160-9900', '(555) 160-9901',
     '1800 Long Term Care Ave, Perris, CA 92571', 'M-F 8am-5pm',
     'Complex medically ventilator-dependent patients.', 1, 100),

    (@FAC, 'State Psychiatric Hospital',     'BH',           '(555) 270-1100', '(555) 270-1101',
     '4500 State Hospital Rd, Patton, CA 92369', '24/7 Intake',
     'IMD facility. Accepts involuntary holds. Long waitlist.', 1, 110)
ON DUPLICATE KEY UPDATE
    phone      = VALUES(phone),
    fax        = VALUES(fax),
    address    = VALUES(address),
    hours      = VALUES(hours),
    notes      = VALUES(notes),
    is_active  = VALUES(is_active),
    sort_order = VALUES(sort_order);


-- =============================================================================
-- SECTION 3: OBSERVATION PROTOCOLS
-- =============================================================================

INSERT INTO oei_protocol
    (facility_id, protocol_key, label, version, enabled, definition_json, updated_by_user_id, updated_datetime)
VALUES
(@FAC, 'CHEST_PAIN', 'Chest Pain / ACS Rule-Out', '2.1', 1,
 '{"protocol_key":"CHEST_PAIN","label":"Chest Pain / ACS Rule-Out","target_hours":24,"runway_hours":4,"tasks":[{"type":"EKG","at_minutes":[0,360],"label":"12-Lead EKG"},{"type":"TROPONIN","at_minutes":[0,360,720],"label":"Serial Troponin"},{"type":"VITALS_CHECK","every_minutes":240,"label":"Vitals Q4H"},{"type":"DISPOSITION_DECISION","at_minutes":1320,"label":"Cardiology consult or discharge decision"}]}',
 1, NOW()),

(@FAC, 'COPD_EXACERBATION', 'COPD Exacerbation Observation', '1.3', 1,
 '{"protocol_key":"COPD_EXACERBATION","label":"COPD Exacerbation Observation","target_hours":24,"runway_hours":6,"tasks":[{"type":"SPIROMETRY","at_minutes":[0,360,720],"label":"Peak Flow / Spirometry"},{"type":"ABG","at_minutes":[60],"label":"Arterial Blood Gas"},{"type":"VITALS_CHECK","every_minutes":120,"label":"Vitals Q2H"},{"type":"NEBS","every_minutes":240,"label":"Albuterol Neb Treatment"},{"type":"DISPOSITION_DECISION","at_minutes":1080,"label":"Admit vs discharge decision"}]}',
 1, NOW()),

(@FAC, 'SEPSIS_BUNDLE', 'Sepsis 3-Hour Bundle', '3.0', 1,
 '{"protocol_key":"SEPSIS_BUNDLE","label":"Sepsis 3-Hour Bundle","target_hours":3,"runway_hours":0.5,"tasks":[{"type":"BLOOD_CULTURE","at_minutes":[0],"label":"Blood Cultures x2"},{"type":"LACTATE","at_minutes":[0],"label":"Serum Lactate"},{"type":"IV_FLUID","at_minutes":[0],"label":"30mL/kg IV Fluid Bolus"},{"type":"ANTIBIOTICS","at_minutes":[0],"label":"Broad-Spectrum Antibiotics"},{"type":"VITALS_CHECK","every_minutes":60,"label":"Vitals Q1H"}]}',
 1, NOW()),

(@FAC, 'STROKE_ALERT', 'Code Stroke Protocol', '2.5', 1,
 '{"protocol_key":"STROKE_ALERT","label":"Code Stroke Protocol","target_hours":1,"runway_hours":0.25,"tasks":[{"type":"CT_HEAD_STAT","at_minutes":[0],"label":"Non-contrast CT Head STAT"},{"type":"CT_ANGIO","at_minutes":[15],"label":"CTA Head & Neck"},{"type":"NIHSS_SCORE","at_minutes":[0],"label":"NIHSS Assessment"},{"type":"NEUROLOGY_CONSULT","at_minutes":[0],"label":"Neurology Consult"},{"type":"IV_ACCESS","at_minutes":[0],"label":"Two large-bore IVs"},{"type":"LABS_STAT","at_minutes":[0],"label":"Coags / CBC / BMP STAT"},{"type":"TPA_DECISION","at_minutes":[30],"label":"tPA Eligibility Assessment"}]}',
 1, NOW()),

(@FAC, 'OPIOID_OVERDOSE', 'Opioid Overdose / Naloxone Protocol', '1.1', 1,
 '{"protocol_key":"OPIOID_OVERDOSE","label":"Opioid Overdose Protocol","target_hours":4,"runway_hours":1,"tasks":[{"type":"VITALS_CHECK","every_minutes":30,"label":"Vitals Q30min (watch re-sedation)"},{"type":"NALOXONE_TITRATE","at_minutes":[0],"label":"Naloxone Drip Titration"},{"type":"BH_CONSULT","at_minutes":[60],"label":"Behavioral Health Consult"},{"type":"NARCAN_EDUCATION","at_minutes":[180],"label":"Narcan Rx and education if discharge"}]}',
 1, NOW()),

(@FAC, 'PEDIATRIC_FEVER', 'Pediatric Fever Protocol (< 13y)', '1.0', 1,
 '{"protocol_key":"PEDIATRIC_FEVER","label":"Pediatric Fever Protocol","target_hours":4,"runway_hours":0.5,"tasks":[{"type":"TEMP_RECHECK","at_minutes":[60],"label":"Temperature Recheck"},{"type":"UA_RESULT","at_minutes":[30],"label":"UA / Culture Review"},{"type":"CBC_REVIEW","at_minutes":[45],"label":"CBC with Differential"},{"type":"ANTIPYRETIC","at_minutes":[0],"label":"Acetaminophen or Ibuprofen"}]}',
 1, NOW())

ON DUPLICATE KEY UPDATE
    label          = VALUES(label),
    version        = VALUES(version),
    enabled        = VALUES(enabled),
    definition_json = VALUES(definition_json),
    updated_datetime = NOW();


-- =============================================================================
-- SECTION 4: DIVERSION STATUS
-- Story: Mid-morning surge. Trauma bay just activated mass-casualty diversion.
--        Psych beds full. ICU at limited capacity. ED open but under pressure.
-- =============================================================================

INSERT INTO oei_diversion
    (facility_id, service_line, status, reason, diversion_start, diversion_end,
     updated_by_user_id, updated_datetime)
VALUES
    (@FAC, 'TRAUMA',  'DIVERSION', 'Mass casualty incident diverted — both trauma bays occupied. Redirect all trauma activations to Regional Trauma Center.',
     DATE_SUB(NOW(), INTERVAL 2 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

    (@FAC, 'PSYCH',   'DIVERSION', 'No BH holding rooms available. Two patients boarding. Redirect voluntary psych to Valley BH Center.',
     DATE_SUB(NOW(), INTERVAL 6 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 6 HOUR)),

    (@FAC, 'ICU',     'LIMITED',   'ICU at 92% capacity. Accepting critical holds only. Call attending before transfer.',
     DATE_SUB(NOW(), INTERVAL 4 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 4 HOUR)),

    (@FAC, 'ED',      'OPEN',      NULL,
     DATE_SUB(NOW(), INTERVAL 8 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 8 HOUR)),

    (@FAC, 'OBS',     'OPEN',      NULL,
     DATE_SUB(NOW(), INTERVAL 8 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 8 HOUR)),

    (@FAC, 'PEDS',    'OPEN',      NULL,
     DATE_SUB(NOW(), INTERVAL 8 HOUR),  NULL, 1, DATE_SUB(NOW(), INTERVAL 8 HOUR))

ON DUPLICATE KEY UPDATE
    status           = VALUES(status),
    reason           = VALUES(reason),
    diversion_start  = VALUES(diversion_start),
    diversion_end    = VALUES(diversion_end),
    updated_by_user_id = VALUES(updated_by_user_id),
    updated_datetime = VALUES(updated_datetime);

INSERT INTO oei_diversion_history
    (facility_id, service_line, previous_status, new_status, reason, changed_by_user_id, changed_datetime)
VALUES
    -- TRAUMA: OPEN → DIVERSION 2h ago (MCI event)
    (@FAC, 'TRAUMA', 'OPEN', 'DIVERSION',
     'Mass casualty incident — both bays activated', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR)),

    -- PSYCH: OPEN → LIMITED 8h ago
    (@FAC, 'PSYCH', 'OPEN', 'LIMITED',
     'BH holding rooms approaching capacity', 1, DATE_SUB(NOW(), INTERVAL 8 HOUR)),

    -- PSYCH: LIMITED → DIVERSION 6h ago
    (@FAC, 'PSYCH', 'LIMITED', 'DIVERSION',
     'No available BH beds. Two patients boarding in ED.', 1, DATE_SUB(NOW(), INTERVAL 6 HOUR)),

    -- ICU: OPEN → LIMITED 4h ago
    (@FAC, 'ICU', 'OPEN', 'LIMITED',
     'ICU at 92% capacity following overnight admits', 1, DATE_SUB(NOW(), INTERVAL 4 HOUR));


-- =============================================================================
-- SECTION 5: CARE CONTEXT (user role preferences)
-- =============================================================================

INSERT INTO oei_user_context
    (user_id, facility_id, context_key, updated_datetime)
VALUES
    (1, @FAC, 'ED_ACUTE',   NOW()),
    (2, @FAC, 'OPERATIONS',  NOW()),
    (3, @FAC, 'OBS_STAY',    NOW()),
    (4, @FAC, 'BH',          NOW())
ON DUPLICATE KEY UPDATE
    context_key      = VALUES(context_key),
    updated_datetime = NOW();


-- =============================================================================
-- SECTION 6: SETTINGS (demo-optimised thresholds)
-- =============================================================================

INSERT INTO oei_settings
    (facility_id, setting_key, setting_value, updated_by_user_id, updated_datetime)
VALUES
    (@FAC, 'facility_name',                'Community Memorial Hospital ED', 1, NOW()),
    (@FAC, 'door_to_room_target_min',      '30',    1, NOW()),
    (@FAC, 'door_to_provider_target_min',  '60',    1, NOW()),
    (@FAC, 'lwbs_threshold_min',           '120',   1, NOW()),
    (@FAC, 'obs_runway_warning_hours',     '4',     1, NOW()),
    (@FAC, 'boarding_alert_hours',         '4',     1, NOW()),
    (@FAC, 'esi_high_acuity_max',          '2',     1, NOW()),
    (@FAC, 'vitals_interval_ed_min',       '120',   1, NOW()),
    (@FAC, 'vitals_interval_obs_min',      '240',   1, NOW()),
    (@FAC, 'vitals_window_hours',          '12',    1, NOW()),
    (@FAC, 'hl7_enabled',                  '0',     1, NOW()),
    (@FAC, 'hl7_transport',                'MLLP',  1, NOW()),
    (@FAC, 'hl7_mllp_host',               '127.0.0.1', 1, NOW()),
    (@FAC, 'hl7_mllp_port',               '2575',  1, NOW()),
    (@FAC, 'hl7_processing_id',            'T',     1, NOW())
ON DUPLICATE KEY UPDATE
    setting_value    = VALUES(setting_value),
    updated_datetime = NOW();


-- =============================================================================
-- EPISODE A: James Wilson, 58M — Sepsis / Pneumonia
-- =============================================================================
-- ESI-2 | 3h | Trauma not applicable — ED Room 1
-- Alerts firing: SEPSIS_RISK, VITALS_DETERIORATION, MAR_OVERDUE (Vancomycin)
-- Sepsis bundle protocol active, blood cultures overdue
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P1, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 180 MINUTE), 'ACTIVE',
        'Altered mental status, fever, productive cough', 2, 'EMS',
        1, 1, 1, DATE_SUB(NOW(), INTERVAL 180 MINUTE));
SET @EP_A := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_A, 'ARRIVE',              1, DATE_SUB(NOW(), INTERVAL 180 MINUTE), 'Arrived by EMS. Diaphoretic and confused.'),
    (@EP_A, 'TRIAGE',              1, DATE_SUB(NOW(), INTERVAL 175 MINUTE), 'ESI-2 assigned. qSOFA 3/3.'),
    (@EP_A, 'ROOMED',              1, DATE_SUB(NOW(), INTERVAL 172 MINUTE), 'Placed ED Room 1'),
    (@EP_A, 'SEPSIS_ALERT',        1, DATE_SUB(NOW(), INTERVAL 170 MINUTE), 'Sepsis protocol activated — bundle initiated'),
    (@EP_A, 'WITH_PROVIDER',       1, DATE_SUB(NOW(), INTERVAL 165 MINUTE), 'Provider at bedside. Sepsis workup ordered.');

INSERT INTO oei_episode_event (episode_id, pid, facility_id, event_type, event_datetime, user_id, note) VALUES
    (@EP_A, @P1, @FAC, 'ARRIVE',   DATE_SUB(NOW(), INTERVAL 180 MINUTE), 1, 'EMS arrival — altered, febrile'),
    (@EP_A, @P1, @FAC, 'ROOM',     DATE_SUB(NOW(), INTERVAL 172 MINUTE), 1, 'ED Room 1'),
    (@EP_A, @P1, @FAC, 'PROVIDER', DATE_SUB(NOW(), INTERVAL 165 MINUTE), 1, 'Sepsis workup initiated');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_A, @P1, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 172 MINUTE), NULL, 1, 'Sepsis workup'
    FROM oei_location WHERE facility_id = @FAC AND code = 'ED01' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_A, @P1, @FAC, 1,
        88, 58, 122, 26, 102.4, 92, 12, 5, 82.0, 'EMS', 2,
        'Patient confused, combative on arrival. Diaphoretic. Warm, mottled extremities. Family reports 3-day productive cough, chills, progressive confusion. qSOFA: RR 26, GCS < 15, SBP 88. Sepsis bundle initiated.',
        1, DATE_SUB(NOW(), INTERVAL 175 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_A, @P1, @FAC, 'BLOOD_CULTURE',   DATE_SUB(NOW(), INTERVAL 90 MINUTE),  'OPEN',     '{"priority":"STAT","note":"2 sets before antibiotics — OVERDUE"}',         1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'LACTATE',         DATE_SUB(NOW(), INTERVAL 120 MINUTE), 'OPEN',     '{"priority":"STAT","note":"Serum lactate — sepsis bundle"}',               1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'IV_FLUID',        DATE_SUB(NOW(), INTERVAL 160 MINUTE), 'COMPLETE', '{"note":"30mL/kg NS bolus completed","ml_given":2400}',                    1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'ANTIBIOTICS',     DATE_SUB(NOW(), INTERVAL 160 MINUTE), 'COMPLETE', '{"note":"Vancomycin 1500mg + Pip-Tazo 3.375g started"}',                   1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'VITALS_CHECK',    DATE_SUB(NOW(), INTERVAL 30 MINUTE),  'OPEN',     '{"source":"auto","priority":"URGENT"}',                                    1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'VITALS_CHECK',    DATE_ADD(NOW(), INTERVAL 30 MINUTE),  'OPEN',     '{"source":"auto"}',                                                       1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'CHEST_XRAY',      DATE_SUB(NOW(), INTERVAL 60 MINUTE),  'COMPLETE', '{"result":"Bilateral infiltrates consistent with pneumonia / ARDS"}',       1, DATE_SUB(NOW(), INTERVAL 172 MINUTE)),
    (@EP_A, @P1, @FAC, 'ICU_BED_REQUEST', DATE_SUB(NOW(), INTERVAL 30 MINUTE),  'OPEN',     '{"priority":"URGENT","note":"Patient likely needs ICU. Awaiting bed."}',   1, DATE_SUB(NOW(), INTERVAL 60 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_A, @P1, @FAC, 'Vancomycin',              '1500', 'mg',  'IV', 'Q8H',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 165 MINUTE), 1, 'Infuse over 90 min. Monitor troughs. Sepsis dosing.',                    DATE_SUB(NOW(), INTERVAL 165 MINUTE), DATE_SUB(NOW(), INTERVAL 165 MINUTE)),
    (@EP_A, @P1, @FAC, 'Piperacillin-Tazobactam', '3.375','g',   'IV', 'Q6H',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 165 MINUTE), 1, 'Extended infusion over 4h. Start after blood cultures.',               DATE_SUB(NOW(), INTERVAL 165 MINUTE), DATE_SUB(NOW(), INTERVAL 165 MINUTE)),
    (@EP_A, @P1, @FAC, 'Normal Saline',            '1000', 'mL',  'IV', 'Q4H',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 165 MINUTE), 1, 'Maintenance fluid. Reassess after each bolus.',                         DATE_SUB(NOW(), INTERVAL 165 MINUTE), DATE_SUB(NOW(), INTERVAL 165 MINUTE)),
    (@EP_A, @P1, @FAC, 'Norepinephrine',           '0.05', 'mcg/kg/min', 'IV', 'CONTINUOUS', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 60 MINUTE), 1, 'Vasopressor for MAP < 65. Titrate to MAP 65-70. HIGH ALERT.', DATE_SUB(NOW(), INTERVAL 60 MINUTE), DATE_SUB(NOW(), INTERVAL 60 MINUTE));
SET @ORD_A1 := LAST_INSERT_ID() - 3;
SET @ORD_A2 := LAST_INSERT_ID() - 2;
SET @ORD_A3 := LAST_INSERT_ID() - 1;
SET @ORD_A4 := LAST_INSERT_ID();

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, administered_by_user_id, is_high_alert, note, created_datetime, updated_datetime)
VALUES
    (@ORD_A1, @EP_A, @P1, @FAC, DATE_SUB(NOW(), INTERVAL 163 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE), 'GIVEN',   '1500', 'mg',         'IV', 1, 1, 'First dose — infused over 90 min', DATE_SUB(NOW(), INTERVAL 163 MINUTE), DATE_SUB(NOW(), INTERVAL 160 MINUTE)),
    (@ORD_A1, @EP_A, @P1, @FAC, DATE_ADD(NOW(), INTERVAL 317 MINUTE), NULL,                                  'PENDING', NULL,   NULL,         NULL, NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 163 MINUTE), DATE_SUB(NOW(), INTERVAL 163 MINUTE)),
    (@ORD_A2, @EP_A, @P1, @FAC, DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 155 MINUTE), 'GIVEN',   '3.375','g',          'IV', 1, 0, 'First dose after cultures drawn', DATE_SUB(NOW(), INTERVAL 160 MINUTE), DATE_SUB(NOW(), INTERVAL 155 MINUTE)),
    (@ORD_A4, @EP_A, @P1, @FAC, DATE_SUB(NOW(), INTERVAL 60 MINUTE),  DATE_SUB(NOW(), INTERVAL 58 MINUTE),  'GIVEN',   '0.05', 'mcg/kg/min', 'IV', 1, 1, 'Started for MAP 58. Titrating up.', DATE_SUB(NOW(), INTERVAL 60 MINUTE), DATE_SUB(NOW(), INTERVAL 58 MINUTE));


-- =============================================================================
-- EPISODE B: Margaret Chen, 67F — Chest Pain OBS (22-hour mark)
-- =============================================================================
-- ESI-3 | 22h | OBS Bay 1
-- Alerts firing: OBS_RUNWAY (2h remaining), DISPOSITION_DECISION overdue
-- Troponins x3 negative. Stress test deferred. Cardiology f/u arranged.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P2, @FAC, 'OBS', DATE_SUB(NOW(), INTERVAL 22 HOUR), 'ACTIVE',
        'Substernal chest pressure, r/o ACS', 3, 'WALKIN', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 22 HOUR));
SET @EP_B := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_B, 'ARRIVE',    1, DATE_SUB(NOW(), INTERVAL 22 HOUR),   'Walk-in. Chest pain onset 2h prior.'),
    (@EP_B, 'TRIAGE',    1, DATE_SUB(NOW(), INTERVAL 1319 MINUTE),'ESI-3. TIMI 3/7. No STEMI on EKG.'),
    (@EP_B, 'ROOMED',    1, DATE_SUB(NOW(), INTERVAL 1315 MINUTE),'OBS Bay 1 — continuous telemetry'),
    (@EP_B, 'OBS_START', 1, DATE_SUB(NOW(), INTERVAL 20 HOUR),   'Obs status initiated. Chest pain protocol.'),
    (@EP_B, 'PENDING_RESULTS', 1, DATE_SUB(NOW(), INTERVAL 8 HOUR), 'Awaiting final troponin and cardiology recommendation.');

INSERT INTO oei_episode_event (episode_id, pid, facility_id, event_type, event_datetime, user_id, note) VALUES
    (@EP_B, @P2, @FAC, 'ARRIVE',    DATE_SUB(NOW(), INTERVAL 22 HOUR),   1, 'Walk-in, self-transport'),
    (@EP_B, @P2, @FAC, 'ROOM',      DATE_SUB(NOW(), INTERVAL 1315 MINUTE),1, 'OBS Bay 1'),
    (@EP_B, @P2, @FAC, 'OBS_START', DATE_SUB(NOW(), INTERVAL 20 HOUR),   1, 'Chest pain obs protocol');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_B, @P2, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 22 HOUR), NULL, 1, 'OBS admission'
    FROM oei_location WHERE facility_id = @FAC AND code = 'OBS1' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES
    (@EP_B, @P2, @FAC, 1, 154, 94, 98, 16, 98.6, 97, 15, 7, 78.0, 'WALKIN', 3,
     'Substernal pressure radiating to left shoulder x 2h. Diaphoresis. Denies SOB. HTN, DM, hyperlipidemia hx. ASA/metformin at home.', 1, DATE_SUB(NOW(), INTERVAL 22 HOUR)),
    (@EP_B, @P2, @FAC, 2, 138, 84, 78, 14, 98.2, 99, 15, 3, 78.0, 'WALKIN', 3,
     'Re-triage 12h: chest pressure resolved. Ambulating. Tolerating clear diet. Awaiting final troponin and cardiology input.', 1, DATE_SUB(NOW(), INTERVAL 10 HOUR));

INSERT INTO oei_obs_plan (episode_id, pid, facility_id, protocol_key, status, start_datetime, target_hours, runway_hours, protocol_json, updated_by_user_id, updated_datetime)
VALUES (@EP_B, @P2, @FAC, 'CHEST_PAIN', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 HOUR), 24, 4,
        '{"protocol_key":"CHEST_PAIN","label":"Chest Pain / ACS Rule-Out","target_hours":24,"runway_hours":4}',
        1, DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_B, @P2, @FAC, 'EKG',                 DATE_SUB(NOW(), INTERVAL 20 HOUR),  'COMPLETE', '{"result":"NSR, no acute changes, no STEMI"}',                                  1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'TROPONIN',             DATE_SUB(NOW(), INTERVAL 20 HOUR),  'COMPLETE', '{"label":"Troponin #1","result":"0.012 ng/mL (normal < 0.04)"}',                 1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'TROPONIN',             DATE_SUB(NOW(), INTERVAL 14 HOUR),  'COMPLETE', '{"label":"Troponin #2","result":"0.010 ng/mL — negative trend"}',               1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'TROPONIN',             DATE_SUB(NOW(), INTERVAL 8 HOUR),   'COMPLETE', '{"label":"Troponin #3","result":"0.009 ng/mL — rule-out complete"}',            1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'VITALS_CHECK',         DATE_SUB(NOW(), INTERVAL 1 HOUR),   'OPEN',     '{"source":"auto"}',                                                             1, DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'DISPOSITION_DECISION', DATE_SUB(NOW(), INTERVAL 2 HOUR),   'OPEN',     '{"label":"Cardiology consult completed. Discharge with f/u vs stress test?"}',  1, DATE_SUB(NOW(), INTERVAL 20 HOUR));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_B, @P2, @FAC, 'Aspirin',      '325', 'mg',    'PO', 'QD',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 22 HOUR), 1, 'Chew first dose. Cardiac dosing.',           DATE_SUB(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR)),
    (@EP_B, @P2, @FAC, 'Metoprolol',   '25',  'mg',    'PO', 'BID', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 HOUR), 1, 'Hold for HR < 55 or SBP < 100.',            DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'Heparin',      '5000','units', 'SQ', 'Q8H', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 HOUR), 1, 'DVT prophylaxis. Check aPTT. HIGH ALERT.', DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@EP_B, @P2, @FAC, 'Nitroglycerin','0.4', 'mg',    'SL', 'PRN', 1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 22 HOUR), 1, 'PRN chest pain. May repeat q5min x3. Hold SBP < 90.', DATE_SUB(NOW(), INTERVAL 22 HOUR), DATE_SUB(NOW(), INTERVAL 22 HOUR));
SET @ORD_B_HEP := LAST_INSERT_ID() - 1;

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, site, administered_by_user_id, is_high_alert, note, created_datetime, updated_datetime)
VALUES
    (@ORD_B_HEP, @EP_B, @P2, @FAC, DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR), 'GIVEN',   '5000','units','SQ','Abdomen L', 1, 1, 'Dose 1', DATE_SUB(NOW(), INTERVAL 20 HOUR), DATE_SUB(NOW(), INTERVAL 20 HOUR)),
    (@ORD_B_HEP, @EP_B, @P2, @FAC, DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR), 'GIVEN',   '5000','units','SQ','Abdomen R', 1, 1, 'Dose 2', DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@ORD_B_HEP, @EP_B, @P2, @FAC, DATE_SUB(NOW(), INTERVAL 4 HOUR),  NULL,                              'PENDING', NULL,  NULL,  NULL, NULL,       NULL, 1, NULL,   DATE_SUB(NOW(), INTERVAL 4 HOUR),  DATE_SUB(NOW(), INTERVAL 4 HOUR));

-- E-Referral: Draft to Cardiology Group (auto-populated from MAR)
INSERT INTO oei_ereferral
    (episode_id, pid, eid, facility_id, referral_type, status, priority,
     destination_directory_id, destination_name, destination_fax, destination_phone,
     reason_for_referral, clinical_summary, services_requested, medications_summary,
     followup_instructions, created_by_user_id, created_datetime, updated_datetime)
SELECT @EP_B, @P2, NULL, @FAC, 'DISCHARGE', 'DRAFT', 'URGENT',
       d.id, d.name, d.fax, d.phone,
       'ACS rule-out complete. Three negative troponins. EKG without acute changes. Requesting cardiology follow-up and outpatient stress test.',
       'Chest pain with TIMI score 3/7. Serial troponins x3 negative. No EKG changes. HTN, DM, hyperlipidemia. Stable for discharge.',
       'Outpatient stress test within 72h; lipid panel; medication reconciliation',
       CONCAT('Aspirin 325 mg PO QD', CHAR(10), 'Metoprolol 25 mg PO BID', CHAR(10), 'Heparin 5000 units SQ Q8H (inpatient — discontinue at discharge)'),
       'Cardiology f/u within 72h. Return precautions for recurrent chest pain, SOB, syncope.',
       1, DATE_SUB(NOW(), INTERVAL 1 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR)
FROM oei_facility_directory d
WHERE d.facility_id = @FAC AND d.service_type = 'CARDIOLOGY' LIMIT 1;


-- =============================================================================
-- EPISODE C: Tyler Brooks, 34M — BH Boarding, Suicidal Ideation
-- =============================================================================
-- ESI-3 | 5h | PSY Room 1
-- Alerts firing: LWBS_RISK (5h), BH_BOARDING_DWELL (CRITICAL)
-- 1:1 sitter assigned. Placement calls to 3 facilities. No acceptance yet.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P3, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 5 HOUR), 'ACTIVE',
        'Psychiatric evaluation, suicidal ideation with plan', 3, 'POLICE', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 5 HOUR));
SET @EP_C := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_C, 'ARRIVE',   1, DATE_SUB(NOW(), INTERVAL 5 HOUR),    'Police escort. Cooperative. No acute injury.'),
    (@EP_C, 'TRIAGE',   1, DATE_SUB(NOW(), INTERVAL 299 MINUTE),'ESI-3 BH. Passive SI with plan. Sitter assigned.'),
    (@EP_C, 'ROOMED',   1, DATE_SUB(NOW(), INTERVAL 295 MINUTE),'BH Room 1 — ligature-reduced. 1:1 sitter.'),
    (@EP_C, 'WAITING',  1, DATE_SUB(NOW(), INTERVAL 280 MINUTE),'Awaiting BH placement. Calls in progress.');

INSERT INTO oei_episode_event (episode_id, pid, facility_id, event_type, event_datetime, user_id, note) VALUES
    (@EP_C, @P3, @FAC, 'ARRIVE', DATE_SUB(NOW(), INTERVAL 5 HOUR),    1, 'Police escort — public safety call'),
    (@EP_C, @P3, @FAC, 'ROOM',   DATE_SUB(NOW(), INTERVAL 295 MINUTE),1, 'BH Room 1');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_C, @P3, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 295 MINUTE), NULL, 1, 'BH holding'
    FROM oei_location WHERE facility_id = @FAC AND code = 'PSY1' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_C, @P3, @FAC, 1, 128, 80, 90, 14, 98.3, 99, 15, 0, 'POLICE', 3,
        'Calm and cooperative. Reports SI — "I have a plan and the means." Denies HI. Last EtOH 8h prior. Chronic depression, hx of one prior attempt 2yo. No acute medical complaints. Patient consented to evaluation.',
        1, DATE_SUB(NOW(), INTERVAL 299 MINUTE));

INSERT INTO oei_bh_safety (episode_id, pid, facility_id, observation_level, is_involuntary,
    risk_violence, risk_suicide, elopement_risk, precautions_json, updated_by_user_id, updated_datetime)
VALUES (@EP_C, @P3, @FAC, '1:1', 0, 0, 1, 1,
        '{"items":["Sharps removed from room and patient","Street clothing searched","Belts and shoelaces removed","1:1 sitter assigned — shift change at 1500","Columbia Suicide Severity Rating Scale completed: High risk","Crisis counselor notified"]}',
        1, DATE_SUB(NOW(), INTERVAL 290 MINUTE));

INSERT INTO oei_bh_boarding (episode_id, pid, facility_id, legal_status, suicide_risk, violence_risk,
    placement_status, accepting_facility, emtala_complete, checklist_json, notes, updated_by_user_id, updated_datetime)
VALUES (@EP_C, @P3, @FAC, 'VOLUNTARY', 'HIGH', 'LOW', 'SEARCHING', NULL, 1,
        '{"items":[{"label":"EMTALA MSE completed","done":true},{"label":"Insurance verified — MediCal","done":true},{"label":"Placement calls initiated","done":true},{"label":"Accepting facility confirmed","done":false},{"label":"Family notified","done":true},{"label":"Transport arranged","done":false}]}',
        'Called Valley BH (full), State Hospital (waitlist — ETA 6-8h), Riverside BH (no voluntary beds). Re-calling Valley BH at next hour.',
        1, DATE_SUB(NOW(), INTERVAL 120 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_C, @P3, @FAC, 'BH_SAFETY_SCREEN',    DATE_SUB(NOW(), INTERVAL 290 MINUTE), 'COMPLETE', '{"note":"Columbia SSRS completed — high risk"}',                    1, DATE_SUB(NOW(), INTERVAL 295 MINUTE)),
    (@EP_C, @P3, @FAC, 'BH_PLACEMENT_CALL',   DATE_SUB(NOW(), INTERVAL 2 HOUR),     'OPEN',     '{"priority":"HIGH","note":"Re-call Valley BH — waitlist update"}', 1, DATE_SUB(NOW(), INTERVAL 290 MINUTE)),
    (@EP_C, @P3, @FAC, 'VITALS_CHECK',        DATE_SUB(NOW(), INTERVAL 1 HOUR),     'OPEN',     '{"source":"auto"}',                                               1, DATE_SUB(NOW(), INTERVAL 290 MINUTE)),
    (@EP_C, @P3, @FAC, 'EMTALA_DOCUMENTATION',DATE_SUB(NOW(), INTERVAL 4 HOUR),     'COMPLETE', '{"note":"MSE complete, signed"}',                                  1, DATE_SUB(NOW(), INTERVAL 295 MINUTE));

-- Alert acknowledgement — charge nurse acknowledged the LWBS alert
INSERT INTO oei_alert_ack (alert_key, facility_id, user_id, acked_datetime, expires_datetime)
VALUES (CONCAT('LWBS_RISK:', @EP_C), @FAC, 1, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 1 HOUR))
ON DUPLICATE KEY UPDATE acked_datetime = VALUES(acked_datetime), expires_datetime = VALUES(expires_datetime);


-- =============================================================================
-- EPISODE D: Sofia Ramirez, 28F — Ankle Sprain, Disposition Set
-- =============================================================================
-- ESI-4 | 1h | ED Room 2
-- Good workflow example: provider seen, imaging complete, pain controlled.
-- Ready to discharge. E-Referral DRAFT to Orthopedic Associates.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P4, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 65 MINUTE), 'ACTIVE',
        'Right ankle pain and swelling after fall', 4, 'WALKIN', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 65 MINUTE));
SET @EP_D := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_D, 'ARRIVE',              1, DATE_SUB(NOW(), INTERVAL 65 MINUTE), 'Walk-in — hopped to desk'),
    (@EP_D, 'TRIAGE',              1, DATE_SUB(NOW(), INTERVAL 60 MINUTE), 'ESI-4. Right ankle swelling, NWB.'),
    (@EP_D, 'ROOMED',              1, DATE_SUB(NOW(), INTERVAL 55 MINUTE), 'ED Room 2'),
    (@EP_D, 'WITH_PROVIDER',       1, DATE_SUB(NOW(), INTERVAL 40 MINUTE), 'Provider evaluation complete — lateral sprain'),
    (@EP_D, 'READY_DISCHARGE',     1, DATE_SUB(NOW(), INTERVAL 5 MINUTE),  'Imaging reviewed, pain controlled, discharge instructions printed');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_D, @P4, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 55 MINUTE), NULL, 1, 'Ankle injury'
    FROM oei_location WHERE facility_id = @FAC AND code = 'ED02' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_D, @P4, @FAC, 1, 116, 72, 80, 14, 98.0, 100, 15, 7, 62.0, 'WALKIN', 4,
        'Twisted right ankle on stairs 2h ago. Lateral malleolus swelling and bruising. NWB. Neurovascularly intact. Ottawa rules: negative for fracture criteria. Pain 7/10.',
        1, DATE_SUB(NOW(), INTERVAL 60 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_D, @P4, @FAC, 'X_RAY_ORDER',           DATE_SUB(NOW(), INTERVAL 40 MINUTE), 'COMPLETE', '{"label":"Right ankle 3 views","result":"No acute fracture. Soft tissue swelling lateral malleolus."}', 1, DATE_SUB(NOW(), INTERVAL 55 MINUTE)),
    (@EP_D, @P4, @FAC, 'X_RAY_REVIEW',          DATE_SUB(NOW(), INTERVAL 10 MINUTE), 'COMPLETE', '{"label":"Radiology read reviewed with patient"}',                                                       1, DATE_SUB(NOW(), INTERVAL 55 MINUTE)),
    (@EP_D, @P4, @FAC, 'DISCHARGE_INSTRUCTIONS',DATE_SUB(NOW(), INTERVAL 5 MINUTE),  'COMPLETE', '{"label":"Ankle sprain instructions printed and reviewed"}',                                              1, DATE_SUB(NOW(), INTERVAL 55 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_D, @P4, @FAC, 'Ketorolac',   '30', 'mg', 'IV',  'PRN', 1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 45 MINUTE), 1, 'PRN pain > 6/10. Max 5 days. NSAID — note allergy check passed.', DATE_SUB(NOW(), INTERVAL 45 MINUTE), DATE_SUB(NOW(), INTERVAL 45 MINUTE)),
    (@EP_D, @P4, @FAC, 'Ibuprofen',   '600','mg', 'PO',  'Q6H', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 10 MINUTE), 1, 'Discharge prescription x 5 days. Take with food.', DATE_SUB(NOW(), INTERVAL 10 MINUTE), DATE_SUB(NOW(), INTERVAL 10 MINUTE));
SET @ORD_D1 := LAST_INSERT_ID() - 1;

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, site, administered_by_user_id, note, is_high_alert, created_datetime, updated_datetime)
VALUES (@ORD_D1, @EP_D, @P4, @FAC, DATE_SUB(NOW(), INTERVAL 40 MINUTE), DATE_SUB(NOW(), INTERVAL 38 MINUTE),
        'GIVEN', '30', 'mg', 'IV', 'Left AC', 1, 'Pain 7/10 pre. 3/10 at 20min post.', 0,
        DATE_SUB(NOW(), INTERVAL 40 MINUTE), DATE_SUB(NOW(), INTERVAL 38 MINUTE));

INSERT INTO oei_episode_disposition (episode_id, pid, facility_id, disposition_code, destination, decision_datetime, depart_datetime, admit_flag, notes, updated_by_user_id, updated_datetime)
VALUES (@EP_D, @P4, @FAC, 'DISCHARGE', 'Home with ortho follow-up', DATE_SUB(NOW(), INTERVAL 8 MINUTE), NULL, 0,
        'Grade II lateral ankle sprain. No fracture on Ottawa criteria. PRICE. Ibuprofen. Ortho f/u if not improving in 1 week. Return for increasing swelling, numbness, or instability.',
        1, DATE_SUB(NOW(), INTERVAL 8 MINUTE));

INSERT INTO oei_ereferral (episode_id, pid, eid, facility_id, referral_type, status, priority,
     destination_directory_id, destination_name, destination_fax, destination_phone,
     reason_for_referral, clinical_summary, services_requested, medications_summary, followup_instructions,
     created_by_user_id, created_datetime, updated_datetime)
SELECT @EP_D, @P4, NULL, @FAC, 'DISCHARGE', 'DRAFT', 'ROUTINE',
       d.id, d.name, d.fax, d.phone,
       'Grade II lateral ankle sprain. Patient requires orthopedic follow-up within 1 week if not improving with conservative management.',
       'Right ankle pain. X-ray: no fracture. Swelling lateral malleolus. Ottawa rules negative. PRICE initiated.',
       'Orthopedic evaluation within 1 week; consider MRI if not improving',
       CONCAT('Ketorolac 30 mg IV (one dose, ED only)', CHAR(10), 'Ibuprofen 600 mg PO Q6H x5 days'),
       'Weight bear as tolerated with crutches if needed. Ice 20 min Q2H. Elevate. Return if numbness, worsening swelling, inability to bear weight.',
       1, DATE_SUB(NOW(), INTERVAL 5 MINUTE), DATE_SUB(NOW(), INTERVAL 5 MINUTE)
FROM oei_facility_directory d WHERE d.facility_id = @FAC AND d.service_type = 'ORTHOPEDIC' LIMIT 1;


-- =============================================================================
-- EPISODE E: Robert Patel, 72M — STROKE ALERT (25 minutes ago)
-- =============================================================================
-- ESI-1 | 25min | Trauma Bay 1 (even though trauma is on diversion — stroke
--                 patients use TR01 when no other active trauma)
-- Code Stroke activated. NIHSS in progress. CT Head ordered STAT.
-- Last known well: 90 min ago. tPA window open.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P5, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 25 MINUTE), 'ACTIVE',
        'Acute left-sided weakness and slurred speech — CODE STROKE', 1, 'EMS', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 25 MINUTE));
SET @EP_E := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_E, 'ARRIVE',       1, DATE_SUB(NOW(), INTERVAL 25 MINUTE), 'EMS arrival — Code Stroke activated overhead'),
    (@EP_E, 'TRIAGE',       1, DATE_SUB(NOW(), INTERVAL 23 MINUTE), 'ESI-1. Last known well 90 min ago. LKW within tPA window.'),
    (@EP_E, 'ROOMED',       1, DATE_SUB(NOW(), INTERVAL 22 MINUTE), 'Trauma Bay 1 — stroke team assembled'),
    (@EP_E, 'WITH_PROVIDER',1, DATE_SUB(NOW(), INTERVAL 21 MINUTE), 'Neurology at bedside. NIHSS assessment in progress.');

INSERT INTO oei_episode_event (episode_id, pid, facility_id, event_type, event_datetime, user_id, note) VALUES
    (@EP_E, @P5, @FAC, 'ARRIVE',   DATE_SUB(NOW(), INTERVAL 25 MINUTE), 1, 'EMS STAT — Code Stroke overhead'),
    (@EP_E, @P5, @FAC, 'ROOM',     DATE_SUB(NOW(), INTERVAL 22 MINUTE), 1, 'Trauma Bay 1'),
    (@EP_E, @P5, @FAC, 'PROVIDER', DATE_SUB(NOW(), INTERVAL 21 MINUTE), 1, 'Neurology and ED team at bedside');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_E, @P5, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 22 MINUTE), NULL, 1, 'Code Stroke'
    FROM oei_location WHERE facility_id = @FAC AND code = 'TR01' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_E, @P5, @FAC, 1, 192, 108, 86, 18, 98.8, 96, 13, 0, 89.0, 'EMS', 1,
        'Sudden onset left arm and leg weakness with facial droop and dysarthria. Last known well 0930 — 90 min ago. AF on EKG strip by EMS. BP 192/108. GCS 13. Right gaze deviation. Witnessed by wife.',
        1, DATE_SUB(NOW(), INTERVAL 23 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_E, @P5, @FAC, 'NIHSS_SCORE',       DATE_SUB(NOW(), INTERVAL 20 MINUTE), 'OPEN',     '{"priority":"STAT","note":"Initial NIHSS — neurology at bedside"}',                  1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'CT_HEAD_STAT',      DATE_SUB(NOW(), INTERVAL 15 MINUTE), 'OPEN',     '{"priority":"STAT","note":"Non-contrast CT head — no contrast if tPA candidate"}',  1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'CT_ANGIO',          DATE_SUB(NOW(), INTERVAL 10 MINUTE), 'OPEN',     '{"priority":"STAT","note":"CTA head and neck for LVO workup"}',                     1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'LABS_STAT',         DATE_SUB(NOW(), INTERVAL 20 MINUTE), 'OPEN',     '{"priority":"STAT","note":"CBC, BMP, coags, type & screen STAT"}',                  1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'IV_ACCESS',         DATE_SUB(NOW(), INTERVAL 22 MINUTE), 'COMPLETE', '{"note":"Two 18g IVs — right AC and right hand"}',                                  1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'NEUROLOGY_CONSULT', DATE_SUB(NOW(), INTERVAL 25 MINUTE), 'COMPLETE', '{"note":"Neurology on scene — evaluating"}',                                        1, DATE_SUB(NOW(), INTERVAL 25 MINUTE)),
    (@EP_E, @P5, @FAC, 'TPA_DECISION',      DATE_ADD(NOW(), INTERVAL 5 MINUTE),  'OPEN',     '{"label":"tPA eligibility decision — LKW 90min, CT result pending"}',               1, DATE_SUB(NOW(), INTERVAL 25 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_E, @P5, @FAC, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 MINUTE), 1, 'Maintenance. NO glucose-containing fluids in stroke.',        DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE)),
    (@EP_E, @P5, @FAC, 'Labetalol',     '10',  'mg',    'IV', 'PRN',        1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 20 MINUTE), 1, 'PRN SBP > 220 (pre-tPA) or > 180 (post-tPA). Give over 2 min.', DATE_SUB(NOW(), INTERVAL 20 MINUTE), DATE_SUB(NOW(), INTERVAL 20 MINUTE));


-- =============================================================================
-- EPISODE F: Linda Torres, 45F — MVA, Transfer Out to Trauma Center
-- =============================================================================
-- ESI-2 | 2h | Trauma Bay 2
-- Mechanism: High-speed MVA, restrained driver. Splenic laceration suspected.
-- Transfer PENDING to Regional Trauma Center — accepted, awaiting transport.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P6, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 2 HOUR), 'ACTIVE',
        'MVA — abdominal pain, hypotension, mechanism of injury', 2, 'EMS', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 2 HOUR));
SET @EP_F := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_F, 'ARRIVE',       1, DATE_SUB(NOW(), INTERVAL 120 MINUTE), 'EMS arrival. Mechanism: 60mph MVC restrained driver, airbag deployed, +LOC x 2 min.'),
    (@EP_F, 'TRIAGE',       1, DATE_SUB(NOW(), INTERVAL 118 MINUTE), 'ESI-2. BP 96/62, HR 124, abdominal guarding.'),
    (@EP_F, 'ROOMED',       1, DATE_SUB(NOW(), INTERVAL 117 MINUTE), 'Trauma Bay 2 — trauma team activated'),
    (@EP_F, 'WITH_PROVIDER',1, DATE_SUB(NOW(), INTERVAL 115 MINUTE), 'Trauma team evaluation complete. CT abdomen positive.'),
    (@EP_F, 'PENDING_TRANSFER',1,DATE_SUB(NOW(), INTERVAL 30 MINUTE),'Transfer accepted. Awaiting transport unit.');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_F, @P6, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 117 MINUTE), NULL, 1, 'Trauma activation'
    FROM oei_location WHERE facility_id = @FAC AND code = 'TR02' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_F, @P6, @FAC, 1, 94, 60, 128, 22, 98.1, 95, 14, 9, 70.0, 'EMS', 2,
        'Driver, high-speed MVC, airbag, +LOC x 2 min. Complains left upper quadrant pain. C-collar in place per EMS. GCS 14 (E4V4M6). Abdomen tender LUQ, guarding.',
        1, DATE_SUB(NOW(), INTERVAL 118 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_F, @P6, @FAC, 'FAST_EXAM',       DATE_SUB(NOW(), INTERVAL 115 MINUTE), 'COMPLETE', '{"result":"FAST positive — free fluid LUQ and pelvis"}',                                  1, DATE_SUB(NOW(), INTERVAL 117 MINUTE)),
    (@EP_F, @P6, @FAC, 'CT_ABDOMEN_STAT', DATE_SUB(NOW(), INTERVAL 90 MINUTE),  'COMPLETE', '{"result":"Grade III splenic laceration. Active extravasation. Surgical consult."}',      1, DATE_SUB(NOW(), INTERVAL 117 MINUTE)),
    (@EP_F, @P6, @FAC, 'BLOOD_BANK',      DATE_SUB(NOW(), INTERVAL 110 MINUTE), 'COMPLETE', '{"result":"Type O-neg x2 units released. MTP activated."}',                               1, DATE_SUB(NOW(), INTERVAL 117 MINUTE)),
    (@EP_F, @P6, @FAC, 'TRANSFER_ACCEPT', DATE_SUB(NOW(), INTERVAL 30 MINUTE),  'COMPLETE', '{"result":"Regional Trauma Center accepted. Transport ETA 15 min."}',                     1, DATE_SUB(NOW(), INTERVAL 60 MINUTE)),
    (@EP_F, @P6, @FAC, 'TRANSFER_TRANSPORT',DATE_ADD(NOW(), INTERVAL 15 MINUTE),'OPEN',     '{"note":"Medic 7 transport. Trauma surgeon standing by at RTC."}',                        1, DATE_SUB(NOW(), INTERVAL 30 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_F, @P6, @FAC, 'Tranexamic Acid (TXA)',  '1000', 'mg',  'IV', 'ONCE',       0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 110 MINUTE), 1, 'Load 1g over 10 min within 3h of injury. HIGH ALERT.', DATE_SUB(NOW(), INTERVAL 110 MINUTE), DATE_SUB(NOW(), INTERVAL 110 MINUTE)),
    (@EP_F, @P6, @FAC, 'Lactated Ringers',       '1000', 'mL',  'IV', 'BOLUS',      0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 115 MINUTE), 1, 'Permissive hypotension — target SBP 80-90 until OR.',  DATE_SUB(NOW(), INTERVAL 115 MINUTE), DATE_SUB(NOW(), INTERVAL 115 MINUTE)),
    (@EP_F, @P6, @FAC, 'Morphine',               '4',    'mg',  'IV', 'PRN',        1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 100 MINUTE), 1, 'PRN pain. Reassess GCS after. MAX 0.1mg/kg.',           DATE_SUB(NOW(), INTERVAL 100 MINUTE), DATE_SUB(NOW(), INTERVAL 100 MINUTE)),
    (@EP_F, @P6, @FAC, 'Packed Red Blood Cells',  '1',   'unit','IV', 'PRN',        1, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 90 MINUTE),  1, 'O-neg until type confirmed. MTP protocol.',             DATE_SUB(NOW(), INTERVAL 90 MINUTE), DATE_SUB(NOW(), INTERVAL 90 MINUTE));
SET @ORD_F_TXA := LAST_INSERT_ID() - 3;

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, administered_by_user_id, is_high_alert, note, created_datetime, updated_datetime)
VALUES (@ORD_F_TXA, @EP_F, @P6, @FAC, DATE_SUB(NOW(), INTERVAL 108 MINUTE), DATE_SUB(NOW(), INTERVAL 106 MINUTE),
        'GIVEN', '1000', 'mg', 'IV', 1, 1, 'TXA load given 14min post-arrival. BP 94→102 post-fluid.',
        DATE_SUB(NOW(), INTERVAL 108 MINUTE), DATE_SUB(NOW(), INTERVAL 106 MINUTE));

-- Transfer record
INSERT INTO oei_transfer (episode_id, pid, eid, facility_id, transfer_type, reason,
    receiving_name, requested_datetime, accepted_datetime, status, checklist_json, notes, updated_by_user_id, updated_datetime)
VALUES (@EP_F, @P6, NULL, @FAC, 'TRANSFER', 'Grade III splenic laceration requiring surgical intervention',
        'Regional Trauma Center', DATE_SUB(NOW(), INTERVAL 60 MINUTE), DATE_SUB(NOW(), INTERVAL 30 MINUTE),
        'ACCEPTED',
        '{"items":[{"label":"Accepting physician identified","done":true},{"label":"Report given to receiving team","done":true},{"label":"Transfer consent signed","done":true},{"label":"Records copied","done":true},{"label":"Transport unit confirmed","done":true},{"label":"Stable for transport","done":true}]}',
        'Dr. Reyes at RTC accepted directly. MTP ongoing — Hgb 7.8. 2u pRBC transfused. Transport ETA 15 min.',
        1, DATE_SUB(NOW(), INTERVAL 15 MINUTE));


-- =============================================================================
-- EPISODE G: David Kim, 61M — COPD Exacerbation OBS (12h, near discharge)
-- =============================================================================
-- ESI-3 | 12h | OBS Bay 2
-- OBS protocol 12h in. Peak flow improving. Prednisone and Azithromycin on board.
-- Near-discharge: ereferral SENT to Home Health for follow-up nebs.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P7, @FAC, 'OBS', DATE_SUB(NOW(), INTERVAL 12 HOUR), 'ACTIVE',
        'COPD exacerbation — increased SOB and productive cough x 3 days', 3, 'WALKIN', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 12 HOUR));
SET @EP_G := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_G, 'ARRIVE',    1, DATE_SUB(NOW(), INTERVAL 12 HOUR),  'Walk-in. SOB worsening x3 days. Smoking hx 40 pack-years.'),
    (@EP_G, 'TRIAGE',    1, DATE_SUB(NOW(), INTERVAL 719 MINUTE),'ESI-3. SpO2 88% RA, wheezing bilateral.'),
    (@EP_G, 'ROOMED',    1, DATE_SUB(NOW(), INTERVAL 715 MINUTE),'OBS Bay 2 — oxygen 2L NC, albuterol neb started'),
    (@EP_G, 'OBS_START', 1, DATE_SUB(NOW(), INTERVAL 11 HOUR),  'Observation status. COPD exacerbation protocol.'),
    (@EP_G, 'PENDING_RESULTS', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR),'Peak flow 62% predicted — improving. Discharge planning.');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_G, @P7, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 715 MINUTE), NULL, 1, 'COPD OBS'
    FROM oei_location WHERE facility_id = @FAC AND code = 'OBS2' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES
    (@EP_G, @P7, @FAC, 1, 142, 88, 104, 24, 99.1, 88, 15, 3, 88.0, 'WALKIN', 3,
     'COPD patient, severe — FEV1 38% predicted at baseline. SOB worse x 3d. Yellow productive cough. On home nebs and Spiriva. SpO2 88% RA, improved to 94% on 2L NC.',
     1, DATE_SUB(NOW(), INTERVAL 719 MINUTE)),
    (@EP_G, @P7, @FAC, 2, 138, 82, 88, 18, 98.8, 96, 15, 1, 88.0, 'WALKIN', 3,
     'Re-triage 6h: SpO2 96% 2L NC. HR improved 88. Wheezes markedly decreased. Patient able to speak full sentences. Peak flow 55% → 62% predicted.', 1, DATE_SUB(NOW(), INTERVAL 6 HOUR));

INSERT INTO oei_obs_plan (episode_id, pid, facility_id, protocol_key, status, start_datetime, target_hours, runway_hours, protocol_json, updated_by_user_id, updated_datetime)
VALUES (@EP_G, @P7, @FAC, 'COPD_EXACERBATION', 'ACTIVE', DATE_SUB(NOW(), INTERVAL 11 HOUR), 24, 6,
        '{"protocol_key":"COPD_EXACERBATION","target_hours":24,"runway_hours":6}', 1, DATE_SUB(NOW(), INTERVAL 11 HOUR));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_G, @P7, @FAC, 'SPIROMETRY',   DATE_SUB(NOW(), INTERVAL 11 HOUR), 'COMPLETE', '{"result":"Peak flow 38% predicted (baseline)"}',                 1, DATE_SUB(NOW(), INTERVAL 11 HOUR)),
    (@EP_G, @P7, @FAC, 'ABG',          DATE_SUB(NOW(), INTERVAL 10 HOUR), 'COMPLETE', '{"result":"pH 7.38, pCO2 52, pO2 68, HCO3 31 — compensated"}',   1, DATE_SUB(NOW(), INTERVAL 11 HOUR)),
    (@EP_G, @P7, @FAC, 'SPIROMETRY',   DATE_SUB(NOW(), INTERVAL 5 HOUR),  'COMPLETE', '{"result":"Peak flow 55% predicted — improving"}',                1, DATE_SUB(NOW(), INTERVAL 11 HOUR)),
    (@EP_G, @P7, @FAC, 'SPIROMETRY',   DATE_SUB(NOW(), INTERVAL 1 HOUR),  'COMPLETE', '{"result":"Peak flow 62% predicted — ready for discharge eval"}', 1, DATE_SUB(NOW(), INTERVAL 11 HOUR)),
    (@EP_G, @P7, @FAC, 'VITALS_CHECK', DATE_SUB(NOW(), INTERVAL 2 HOUR),  'COMPLETE', '{"source":"auto"}',                                               1, DATE_SUB(NOW(), INTERVAL 11 HOUR)),
    (@EP_G, @P7, @FAC, 'DISPOSITION_DECISION', DATE_ADD(NOW(), INTERVAL 30 MINUTE), 'OPEN', '{"note":"Discharge home with home health if peak flow > 60%"}', 1, DATE_SUB(NOW(), INTERVAL 2 HOUR));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_G, @P7, @FAC, 'Albuterol',      '2.5', 'mg',  'INH','Q4H', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 HOUR), 1, 'Neb treatment. Monitor HR. Wean to Q8H before discharge.', DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@EP_G, @P7, @FAC, 'Ipratropium',    '0.5', 'mg',  'INH','Q6H', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 HOUR), 1, 'Neb with first 3 albuterol doses, then PRN.',              DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@EP_G, @P7, @FAC, 'Prednisone',     '40',  'mg',  'PO', 'QD',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 HOUR), 1, 'COPD exacerbation — 5-day course. No taper needed.',       DATE_SUB(NOW(), INTERVAL 12 HOUR), DATE_SUB(NOW(), INTERVAL 12 HOUR)),
    (@EP_G, @P7, @FAC, 'Azithromycin',   '500', 'mg',  'PO', 'QD',  0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 11 HOUR), 1, '5-day Z-pack. Atypical coverage for COPD exacerbation.',   DATE_SUB(NOW(), INTERVAL 11 HOUR), DATE_SUB(NOW(), INTERVAL 11 HOUR));

-- E-Referral: SENT to Home Health
INSERT INTO oei_ereferral (episode_id, pid, eid, facility_id, referral_type, status, priority,
     destination_directory_id, destination_name, destination_fax, destination_phone,
     reason_for_referral, clinical_summary, services_requested, medications_summary, followup_instructions,
     sent_datetime, sent_by_user_id, send_method,
     created_by_user_id, created_datetime, updated_datetime)
SELECT @EP_G, @P7, NULL, @FAC, 'DISCHARGE', 'SENT', 'ROUTINE',
       d.id, d.name, d.fax, d.phone,
       'COPD exacerbation, stabilised. Requires home health for nebulizer management, medication education, and peak flow monitoring.',
       'COPD (GOLD Stage III). Exacerbation treated with systemic steroids, antibiotics, and bronchodilators. SpO2 96% on 2L NC at discharge. Peak flow 62% predicted.',
       'Home nebulizer setup and education; peak flow monitoring log; medication reconciliation; PCP notification',
       CONCAT('Albuterol 2.5 mg neb Q4H (wean to Q8H)', CHAR(10), 'Ipratropium 0.5 mg neb Q6H', CHAR(10), 'Prednisone 40 mg PO QD x5 days', CHAR(10), 'Azithromycin 500 mg PO QD x5 days'),
       'PCP follow-up within 48h. Return for SpO2 < 90%, increased work of breathing, or altered mental status. Continue home oxygen if on it.',
       DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 'FAX',
       1, DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 30 MINUTE)
FROM oei_facility_directory d WHERE d.facility_id = @FAC AND d.service_type = 'HOME_HEALTH' LIMIT 1;


-- =============================================================================
-- EPISODE H: Emma Johnson, 7F — Pediatric Fever / Possible UTI
-- =============================================================================
-- ESI-3 | 45min | ED Room 3
-- Fever 103.8°F. Dysuria x 2d. Acetaminophen given. Labs pending.
-- Age-appropriate workflow. Normal vitals after antipyretic.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P8, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 45 MINUTE), 'ACTIVE',
        'Fever 103.8°F, dysuria x 2 days — pediatric', 3, 'WALKIN', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 45 MINUTE));
SET @EP_H := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_H, 'ARRIVE',        1, DATE_SUB(NOW(), INTERVAL 45 MINUTE), 'Parent brought in. Fever 103.8°F at home x 24h. Dysuria.'),
    (@EP_H, 'TRIAGE',        1, DATE_SUB(NOW(), INTERVAL 42 MINUTE), 'ESI-3. Alert, ill-appearing. Temp 103.8°F.'),
    (@EP_H, 'ROOMED',        1, DATE_SUB(NOW(), INTERVAL 38 MINUTE), 'ED Room 3 — pediatric setup'),
    (@EP_H, 'WITH_PROVIDER', 1, DATE_SUB(NOW(), INTERVAL 20 MINUTE), 'Provider evaluation: UTI suspected. Labs ordered.');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_H, @P8, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 38 MINUTE), NULL, 1, 'Peds fever'
    FROM oei_location WHERE facility_id = @FAC AND code = 'ED03' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_H, @P8, @FAC, 1, 96, 60, 118, 22, 103.8, 99, 15, 4, 23.0, 'WALKIN', 3,
        'Age 7. Fever 103.8°F, onset 24h ago. Dysuria and increased frequency x 2d. Mild suprapubic tenderness. No CVA tenderness. No vomiting. Tolerating PO. Alert and interactive. PMH: none. No allergies.',
        1, DATE_SUB(NOW(), INTERVAL 42 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_H, @P8, @FAC, 'UA_RESULT',    DATE_ADD(NOW(), INTERVAL 10 MINUTE), 'OPEN',     '{"note":"Catheter UA — awaiting result"}',                         1, DATE_SUB(NOW(), INTERVAL 38 MINUTE)),
    (@EP_H, @P8, @FAC, 'CBC_REVIEW',   DATE_ADD(NOW(), INTERVAL 20 MINUTE), 'OPEN',     '{"note":"CBC with diff to r/o bacteremia"}',                       1, DATE_SUB(NOW(), INTERVAL 38 MINUTE)),
    (@EP_H, @P8, @FAC, 'ANTIPYRETIC',  DATE_SUB(NOW(), INTERVAL 35 MINUTE), 'COMPLETE', '{"note":"Acetaminophen given — temp 101.2 at 30min post-dose"}',   1, DATE_SUB(NOW(), INTERVAL 38 MINUTE)),
    (@EP_H, @P8, @FAC, 'TEMP_RECHECK', DATE_ADD(NOW(), INTERVAL 15 MINUTE), 'OPEN',     '{"source":"auto"}',                                               1, DATE_SUB(NOW(), INTERVAL 38 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_H, @P8, @FAC, 'Acetaminophen', '345', 'mg', 'PO', 'Q6H', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 38 MINUTE), 1, 'Pediatric dosing: 15mg/kg. Weight 23kg. Max 5 doses/24h.', DATE_SUB(NOW(), INTERVAL 38 MINUTE), DATE_SUB(NOW(), INTERVAL 38 MINUTE));
SET @ORD_H1 := LAST_INSERT_ID();

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, administered_by_user_id, note, is_high_alert, created_datetime, updated_datetime)
VALUES (@ORD_H1, @EP_H, @P8, @FAC, DATE_SUB(NOW(), INTERVAL 35 MINUTE), DATE_SUB(NOW(), INTERVAL 34 MINUTE),
        'GIVEN', '345', 'mg', 'PO', 1, 'Taken well. No vomiting. Temp 101.2°F at 30 min post.', 0,
        DATE_SUB(NOW(), INTERVAL 35 MINUTE), DATE_SUB(NOW(), INTERVAL 34 MINUTE));


-- =============================================================================
-- EPISODE I: Marcus Williams, 29M — Opioid Overdose / Naloxone Drip
-- =============================================================================
-- ESI-2 | 90min | ED Room 4
-- Heroin OD found unresponsive. Naloxone in field x2 doses. Now alert, hostile.
-- Naloxone drip running (HIGH ALERT). BH consult ordered. Re-sedation watch.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P9, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 90 MINUTE), 'ACTIVE',
        'Suspected opioid overdose — unresponsive, Narcan given by EMS', 2, 'EMS', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 90 MINUTE));
SET @EP_I := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_I, 'ARRIVE',        1, DATE_SUB(NOW(), INTERVAL 90 MINUTE), 'EMS — found unresponsive in public bathroom. Narcan 0.4mg IM x2 in field. GCS 6 → 14 post Narcan.'),
    (@EP_I, 'TRIAGE',        1, DATE_SUB(NOW(), INTERVAL 88 MINUTE), 'ESI-2. Alert, agitated. Pinpoint pupils. Naloxone drip started.'),
    (@EP_I, 'ROOMED',        1, DATE_SUB(NOW(), INTERVAL 86 MINUTE), 'ED Room 4. Wrist restraints applied — patient combative.'),
    (@EP_I, 'WITH_PROVIDER', 1, DATE_SUB(NOW(), INTERVAL 80 MINUTE), 'Provider evaluation. Urine tox + opiates. BH consult ordered.');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_I, @P9, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 86 MINUTE), NULL, 1, 'Opioid OD monitoring'
    FROM oei_location WHERE facility_id = @FAC AND code = 'ED04' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score, weight_kg,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_I, @P9, @FAC, 1, 108, 68, 96, 10, 97.6, 91, 6, 0, 78.0, 'EMS', 2,
        'Found unresponsive bathroom. EMS: pinpoint pupils, shallow respirations RR 6, O2 sat 84%. Narcan 0.4mg IM x2 — GCS improved to 14. Denies substance use when awake. Paraphernalia found at scene.',
        1, DATE_SUB(NOW(), INTERVAL 88 MINUTE));

INSERT INTO oei_bh_safety (episode_id, pid, facility_id, observation_level, is_involuntary,
    risk_violence, risk_suicide, elopement_risk, precautions_json, updated_by_user_id, updated_datetime)
VALUES (@EP_I, @P9, @FAC, '1:1', 0, 1, 0, 1,
        '{"items":["Wrist restraints applied — combative on arrival","Belongings searched — no sharps found","1:1 nurse monitoring for re-sedation","Elopement risk — patient attempted to leave x1","IV secured with armboard"]}',
        1, DATE_SUB(NOW(), INTERVAL 75 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_I, @P9, @FAC, 'VITALS_CHECK',       DATE_SUB(NOW(), INTERVAL 30 MINUTE), 'OPEN',     '{"priority":"URGENT","note":"Re-sedation watch — Narcan shorter half-life than opiates"}', 1, DATE_SUB(NOW(), INTERVAL 86 MINUTE)),
    (@EP_I, @P9, @FAC, 'NALOXONE_TITRATE',   DATE_SUB(NOW(), INTERVAL 60 MINUTE), 'COMPLETE', '{"note":"Drip started 0.1mg/hr. Titrating to adequate respiratory rate."}',               1, DATE_SUB(NOW(), INTERVAL 86 MINUTE)),
    (@EP_I, @P9, @FAC, 'BH_CONSULT',         DATE_ADD(NOW(), INTERVAL 30 MINUTE), 'OPEN',     '{"priority":"ROUTINE","note":"BH consult for MOUD discussion when patient cooperative"}', 1, DATE_SUB(NOW(), INTERVAL 86 MINUTE)),
    (@EP_I, @P9, @FAC, 'NARCAN_EDUCATION',   DATE_ADD(NOW(), INTERVAL 120 MINUTE),'OPEN',     '{"note":"Naloxone Rx and overdose education — defer until patient cooperative"}',          1, DATE_SUB(NOW(), INTERVAL 86 MINUTE));

INSERT INTO oei_mar_order (episode_id, pid, facility_id, drug_name, dose, unit, route, frequency, is_prn, status, ordered_datetime, ordered_by_user_id, instructions, created_datetime, updated_datetime)
VALUES
    (@EP_I, @P9, @FAC, 'Naloxone', '0.4', 'mg/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 82 MINUTE), 1, 'Start 0.4mg/hr. Titrate for RR > 12. HIGH ALERT — monitor q15min. Max 2mg/hr. Wean when clinically appropriate.', DATE_SUB(NOW(), INTERVAL 82 MINUTE), DATE_SUB(NOW(), INTERVAL 82 MINUTE)),
    (@EP_I, @P9, @FAC, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 82 MINUTE), 1, 'Maintenance fluid.',  DATE_SUB(NOW(), INTERVAL 82 MINUTE), DATE_SUB(NOW(), INTERVAL 82 MINUTE));
SET @ORD_I_NAL := LAST_INSERT_ID() - 1;

INSERT INTO oei_mar_administration (mar_order_id, episode_id, pid, facility_id, scheduled_datetime, administered_datetime, outcome, dose_given, unit_given, route_given, administered_by_user_id, is_high_alert, note, created_datetime, updated_datetime)
VALUES (@ORD_I_NAL, @EP_I, @P9, @FAC, DATE_SUB(NOW(), INTERVAL 82 MINUTE), DATE_SUB(NOW(), INTERVAL 80 MINUTE),
        'GIVEN', '0.4', 'mg/hr', 'IV', 1, 1, 'Drip initiated. RR 8→14 at 15min. SpO2 91→97%. Patient awake, combative.',
        DATE_SUB(NOW(), INTERVAL 82 MINUTE), DATE_SUB(NOW(), INTERVAL 80 MINUTE));


-- =============================================================================
-- EPISODE J: Patricia Nguyen, 52F — BH Boarding, Placement ACCEPTED
-- =============================================================================
-- ESI-3 | 8h | Hallway Bed 1 (capacity pressure — no BH room available)
-- Voluntary depression / SI. Valley BH Center accepted — awaiting transport.
-- Shows completed BH boarding workflow. E-Referral SENT and accepted.
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status,
     chief_complaint, acuity_esi, arrival_mode,
     assigned_nurse_user_id, assigned_provider_user_id,
     created_by_user_id, created_datetime)
VALUES (@P10, @FAC, 'ED', DATE_SUB(NOW(), INTERVAL 8 HOUR), 'ACTIVE',
        'Major depression, passive suicidal ideation — voluntary psych evaluation', 3, 'WALKIN', 1, 1, 1, DATE_SUB(NOW(), INTERVAL 8 HOUR));
SET @EP_J := LAST_INSERT_ID();

INSERT INTO oei_episode_status_history (episode_id, status_code, set_by_user_id, set_datetime, note) VALUES
    (@EP_J, 'ARRIVE',         1, DATE_SUB(NOW(), INTERVAL 8 HOUR),    'Self-presented. Reports passive SI, unable to contract for safety. Supportive family present.'),
    (@EP_J, 'TRIAGE',         1, DATE_SUB(NOW(), INTERVAL 479 MINUTE),'ESI-3. No acute medical complaints. Calm and cooperative.'),
    (@EP_J, 'WAITING',        1, DATE_SUB(NOW(), INTERVAL 475 MINUTE),'Waiting — PSY1 and PSY2 both occupied. Placed in hallway.'),
    (@EP_J, 'PLACED_IN_HALL', 1, DATE_SUB(NOW(), INTERVAL 470 MINUTE),'Hallway Bed 1 — sitter assigned. Privacy curtain.'),
    (@EP_J, 'PLACEMENT_ACCEPTED', 1, DATE_SUB(NOW(), INTERVAL 90 MINUTE),'Valley BH accepted. Transport ETA 45-60 min.');

INSERT INTO oei_episode_location (episode_id, pid, facility_id, location_id, location_code, start_datetime, end_datetime, user_id, note)
    SELECT @EP_J, @P10, @FAC, id, code, DATE_SUB(NOW(), INTERVAL 470 MINUTE), NULL, 1, 'BH boarding — capacity constraint'
    FROM oei_location WHERE facility_id = @FAC AND code = 'HALL1' LIMIT 1;

INSERT INTO oei_triage (episode_id, pid, facility_id, set_number,
    bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, pain_score,
    arrival_mode, esi_suggested, notes, noted_by_user_id, noted_datetime)
VALUES (@EP_J, @P10, @FAC, 1, 118, 74, 82, 14, 98.2, 100, 15, 0, 'WALKIN', 3,
        'Voluntary presentation. Reports passive SI — "I don''t want to be here anymore" — but no plan or intent. Depression x 6 months, recently lost job. No psych history, no substances. Calm, cooperative, insightful. Family supportive and present.',
        1, DATE_SUB(NOW(), INTERVAL 479 MINUTE));

INSERT INTO oei_bh_safety (episode_id, pid, facility_id, observation_level, is_involuntary,
    risk_violence, risk_suicide, elopement_risk, precautions_json, updated_by_user_id, updated_datetime)
VALUES (@EP_J, @P10, @FAC, 'Q15', 0, 0, 1, 0,
        '{"items":["Voluntary patient — fully cooperative","Belongings searched — no contraband","Q15-minute safety checks","Family at bedside","Columbia SSRS: moderate risk","Crisis counselor evaluation completed"]}',
        1, DATE_SUB(NOW(), INTERVAL 460 MINUTE));

INSERT INTO oei_bh_boarding (episode_id, pid, facility_id, legal_status, suicide_risk, violence_risk,
    placement_status, accepting_facility, emtala_complete, checklist_json, notes, updated_by_user_id, updated_datetime)
VALUES (@EP_J, @P10, @FAC, 'VOLUNTARY', 'MODERATE', 'LOW', 'ACCEPTED', 'Valley Behavioral Health Center', 1,
        '{"items":[{"label":"EMTALA MSE completed","done":true},{"label":"Insurance verified — Blue Shield","done":true},{"label":"Placement calls initiated","done":true},{"label":"Accepting facility confirmed","done":true},{"label":"Family notified","done":true},{"label":"Transfer paperwork complete","done":true},{"label":"Transport arranged","done":true}]}',
        'Valley BH accepted at 0930. Transport: Medvan unit dispatched, ETA 30-45 min. Patient and family updated and relieved.',
        1, DATE_SUB(NOW(), INTERVAL 90 MINUTE));

INSERT INTO oei_task (episode_id, pid, facility_id, task_type, due_datetime, status, payload_json, created_by_user_id, created_datetime)
VALUES
    (@EP_J, @P10, @FAC, 'BH_SAFETY_SCREEN',    DATE_SUB(NOW(), INTERVAL 7 HOUR),   'COMPLETE', '{"note":"Columbia SSRS — moderate risk, no plan"}',                         1, DATE_SUB(NOW(), INTERVAL 475 MINUTE)),
    (@EP_J, @P10, @FAC, 'EMTALA_DOCUMENTATION',DATE_SUB(NOW(), INTERVAL 7 HOUR),   'COMPLETE', '{"note":"MSE complete, signed by attending"}',                              1, DATE_SUB(NOW(), INTERVAL 475 MINUTE)),
    (@EP_J, @P10, @FAC, 'BH_PLACEMENT_CALL',   DATE_SUB(NOW(), INTERVAL 6 HOUR),   'COMPLETE', '{"note":"Called Valley BH, Riverside BH, State Hospital"}',                1, DATE_SUB(NOW(), INTERVAL 475 MINUTE)),
    (@EP_J, @P10, @FAC, 'VITALS_CHECK',        DATE_SUB(NOW(), INTERVAL 2 HOUR),   'COMPLETE', '{"source":"auto","note":"Stable vitals x8h"}',                             1, DATE_SUB(NOW(), INTERVAL 475 MINUTE)),
    (@EP_J, @P10, @FAC, 'TRANSPORT_CONFIRM',   DATE_ADD(NOW(), INTERVAL 15 MINUTE),'OPEN',     '{"note":"Confirm Medvan arrival with driver — patient ready"}',             1, DATE_SUB(NOW(), INTERVAL 90 MINUTE));

-- E-Referral: SENT and ACCEPTED
INSERT INTO oei_ereferral (episode_id, pid, eid, facility_id, referral_type, status, priority,
     destination_directory_id, destination_name, destination_fax, destination_phone,
     reason_for_referral, clinical_summary, services_requested, medications_summary, followup_instructions,
     sent_datetime, sent_by_user_id, send_method,
     response_datetime, response_by_name, response_notes,
     created_by_user_id, created_datetime, updated_datetime)
SELECT @EP_J, @P10, NULL, @FAC, 'TRANSFER', 'ACCEPTED', 'URGENT',
       d.id, d.name, d.fax, d.phone,
       'Voluntary psychiatric admission for major depressive disorder with passive suicidal ideation. No acute medical concerns. Stable for transfer.',
       'Pt with MDD, no prior psych hospitalizations. Passive SI, no plan or intent. Columbia SSRS moderate risk. Fully cooperative. Medical clearance complete — normal CBC, BMP, UA, EKG.',
       'Inpatient psychiatric stabilization; medication evaluation; individual and group therapy',
       'No current psychiatric medications',
       'Continue outpatient therapy after discharge. PCP follow-up within 1 week.',
       DATE_SUB(NOW(), INTERVAL 3 HOUR), 1, 'PHONE',
       DATE_SUB(NOW(), INTERVAL 90 MINUTE), 'Valley BH Intake Coordinator', 'Accepted — bed available unit 3B. Transport within 60 min.',
       1, DATE_SUB(NOW(), INTERVAL 5 HOUR), DATE_SUB(NOW(), INTERVAL 90 MINUTE)
FROM oei_facility_directory d WHERE d.facility_id = @FAC AND d.service_type = 'BH' AND d.name LIKE '%Valley%' LIMIT 1;


-- =============================================================================
-- SECTION 7: HL7 OUTBOUND LOG (shows ADT message history for the board)
-- =============================================================================

INSERT INTO oei_hl7_outbound_log
    (episode_id, pid, facility_id, event_type, transport_type, endpoint,
     message_body, status, sent_datetime)
VALUES
    (@EP_A, @P1, @FAC, 'A04', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315091500||ADT^A04^ADT_A01|OEI001|T|2.5.1\rEVN|A04|20260315091500\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 180 MINUTE)),

    (@EP_B, @P2, @FAC, 'A04', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A04^ADT_A01|OEI002|T|2.5.1\rEVN|A04|20260315073000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 22 HOUR)),

    (@EP_B, @P2, @FAC, 'A01', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A01^ADT_A01|OEI003|T|2.5.1\rEVN|A01|20260315093000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 20 HOUR)),

    (@EP_E, @P5, @FAC, 'A04', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315113500||ADT^A04^ADT_A01|OEI004|T|2.5.1\rEVN|A04|20260315113500\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 25 MINUTE)),

    (@EP_F, @P6, @FAC, 'A04', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315100000||ADT^A04^ADT_A01|OEI005|T|2.5.1\rEVN|A04|20260315100000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 2 HOUR)),

    (@EP_F, @P6, @FAC, 'A08', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315103000||ADT^A08^ADT_A01|OEI006|T|2.5.1\rEVN|A08|20260315103000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 90 MINUTE)),

    (@EP_G, @P7, @FAC, 'A01', 'INTERNAL', 'INTERNAL',
     'MSH|^~\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315031500||ADT^A01^ADT_A01|OEI007|T|2.5.1\rEVN|A01|20260315031500\rPID|1||8^^^OEI^PI||Kim^David||19630425|M\rPV1|1|O|OBS2^^^OPENEMR',
     'SENT', DATE_SUB(NOW(), INTERVAL 11 HOUR));


-- =============================================================================
-- SECTION 8: SCHEMA VERSION
-- =============================================================================

INSERT IGNORE INTO oei_schema_version (version, applied_datetime)
VALUES ('1.0.0-demo', NOW());


-- =============================================================================
-- SUMMARY VIEW — verify episodes loaded correctly
-- =============================================================================

SELECT
    e.id                                            AS episode_id,
    CONCAT(pd.fname, ' ', pd.lname)                AS patient,
    e.type,
    e.acuity_esi                                    AS esi,
    e.chief_complaint,
    COALESCE(l.name, '(unassigned)')                AS room,
    TIMESTAMPDIFF(MINUTE, e.start_datetime, NOW())  AS elapsed_min,
    sh.status_code                                  AS latest_status,
    COUNT(DISTINCT t.id)                            AS tasks,
    COUNT(DISTINCT mo.id)                           AS mar_orders
FROM oei_episode e
LEFT JOIN patient_data pd
    ON pd.pid = e.pid
LEFT JOIN oei_episode_location el
    ON el.episode_id = e.id AND el.end_datetime IS NULL
LEFT JOIN oei_location l
    ON l.id = el.location_id
LEFT JOIN (
    SELECT episode_id, status_code
    FROM oei_episode_status_history
    WHERE (episode_id, id) IN (
        SELECT episode_id, MAX(id) FROM oei_episode_status_history GROUP BY episode_id
    )
) sh ON sh.episode_id = e.id
LEFT JOIN oei_task t
    ON t.episode_id = e.id AND t.status = 'OPEN'
LEFT JOIN oei_mar_order mo
    ON mo.episode_id = e.id AND mo.status = 'ACTIVE'
WHERE e.facility_id = @FAC
  AND e.status = 'ACTIVE'
GROUP BY e.id, pd.fname, pd.lname, e.type, e.acuity_esi, e.chief_complaint, l.name, e.start_datetime, sh.status_code
ORDER BY e.acuity_esi ASC, e.start_datetime ASC;

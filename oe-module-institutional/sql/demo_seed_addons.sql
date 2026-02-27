-- =============================================================================
-- DEMO SEED ADDONS v1.1.0 — oe-module-institutional
-- =============================================================================
-- Run AFTER demo_seed.sql (depends on @EP_A through @EP_J episode IDs).
-- Because MySQL session variables don't persist between sessions, this file
-- re-selects the episode IDs from the database rather than relying on @EP_x.
-- Safe to re-run: uses INSERT IGNORE where possible.
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

SET @FAC := 1;

-- Re-select episode IDs by matching patient + start time proximity
-- (matches the 10 demo patients seeded in demo_seed.sql by PID ordering)
SET @EP_A := (SELECT id FROM oei_episode WHERE pid = 2 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_B := (SELECT id FROM oei_episode WHERE pid = 3 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_C := (SELECT id FROM oei_episode WHERE pid = 4 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_D := (SELECT id FROM oei_episode WHERE pid = 5 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_E := (SELECT id FROM oei_episode WHERE pid = 6 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_F := (SELECT id FROM oei_episode WHERE pid = 7 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_G := (SELECT id FROM oei_episode WHERE pid = 8 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_H := (SELECT id FROM oei_episode WHERE pid = 9 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_I := (SELECT id FROM oei_episode WHERE pid = 10 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);
SET @EP_J := (SELECT id FROM oei_episode WHERE pid = 11 AND facility_id = @FAC ORDER BY id DESC LIMIT 1);

-- PIDs
SET @P1 := 2; SET @P2 := 3; SET @P3 := 4; SET @P4 := 5; SET @P5 := 6;
SET @P6 := 7; SET @P7 := 8; SET @P8 := 9; SET @P9 := 10; SET @P10 := 11;


-- =============================================================================
-- SECTION A: EPISODE TIMELINE EVENTS (oei_episode_event)
-- Populates the Episode Timeline submodule with rich clinical milestones.
-- =============================================================================

INSERT IGNORE INTO oei_episode_event
    (episode_id, pid, facility_id, event_type, event_datetime, user_id, note)
VALUES
-- EP_A: James Wilson — Sepsis / Pneumonia (3h)
(@EP_A, @P1, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 180 MINUTE), 1, 'EMS arrival. Altered mental status, fever 103.8°F, rigors.'),
(@EP_A, @P1, @FAC, 'TRIAGE',          DATE_SUB(NOW(), INTERVAL 178 MINUTE), 1, 'ESI-2. qSOFA score 3. Sepsis protocol activated.'),
(@EP_A, @P1, @FAC, 'ROOMED',          DATE_SUB(NOW(), INTERVAL 175 MINUTE), 1, 'ED Room 1 — isolation precautions, cultures ordered.'),
(@EP_A, @P1, @FAC, 'BLOOD_CULTURE',   DATE_SUB(NOW(), INTERVAL 165 MINUTE), 1, '2 sets peripheral blood cultures drawn prior to antibiotics.'),
(@EP_A, @P1, @FAC, 'ANTIBIOTIC',      DATE_SUB(NOW(), INTERVAL 155 MINUTE), 1, 'Vancomycin 25mg/kg and Pip-Tazo 4.5g IV started. SEP-1 bundle T+0.'),
(@EP_A, @P1, @FAC, 'LACTATE',         DATE_SUB(NOW(), INTERVAL 150 MINUTE), 1, 'Lactate 4.1 mmol/L — septic shock criteria met. IVF bolus started.'),
(@EP_A, @P1, @FAC, 'IV_FLUID',        DATE_SUB(NOW(), INTERVAL 145 MINUTE), 1, '30mL/kg NS bolus initiated. Running at 150mL/hr.'),
(@EP_A, @P1, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 120 MINUTE), 1, 'WBC 18.4, Procalcitonin 22.6. Repeat lactate ordered.'),
(@EP_A, @P1, @FAC, 'IMAGING',         DATE_SUB(NOW(), INTERVAL 110 MINUTE), 1, 'CXR: RLL consolidation consistent with pneumonia.'),
(@EP_A, @P1, @FAC, 'REASSESSMENT',    DATE_SUB(NOW(), INTERVAL 60 MINUTE),  1, 'MAP improved 58→68 after 2L NS. Patient more alert. Repeat lactate 2.8.'),
(@EP_A, @P1, @FAC, 'DISPOSITION',     DATE_SUB(NOW(), INTERVAL 20 MINUTE),  1, 'Admit to ICU. ICU accepting critical holds only — bed pending.'),

-- EP_B: Margaret Chen — Chest Pain OBS (22h)
(@EP_B, @P2, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 22 HOUR),    1, 'Walk-in. Central chest pressure 8/10 with exertion, relieved at rest.'),
(@EP_B, @P2, @FAC, 'TRIAGE',          DATE_SUB(NOW(), INTERVAL 1310 MINUTE),1, 'ESI-3. ACS pathway activated. Aspirin 325mg given.'),
(@EP_B, @P2, @FAC, 'EKG',             DATE_SUB(NOW(), INTERVAL 1300 MINUTE),1, '12-lead EKG: normal sinus, no ST changes. Serial troponin ordered.'),
(@EP_B, @P2, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 1260 MINUTE),1, 'Troponin T: 0.004 ng/mL (negative). Repeat in 3h.'),
(@EP_B, @P2, @FAC, 'OBS_START',       DATE_SUB(NOW(), INTERVAL 20 HOUR),    1, 'Admitted to observation. OBS chest pain protocol started.'),
(@EP_B, @P2, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 18 HOUR),    1, 'Repeat troponin 3h: 0.006 ng/mL — still negative. Third draw scheduled.'),
(@EP_B, @P2, @FAC, 'CARDIOLOGY',      DATE_SUB(NOW(), INTERVAL 14 HOUR),    1, 'Cardiology consult seen. Recommends stress test before discharge.'),
(@EP_B, @P2, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 18 HOUR),    1, 'Repeat troponin 6h: 0.008 ng/mL — trending stable. No MI.'),
(@EP_B, @P2, @FAC, 'IMAGING',         DATE_SUB(NOW(), INTERVAL 4 HOUR),     1, 'Treadmill stress test: negative. 8 METs achieved, no symptoms.'),
(@EP_B, @P2, @FAC, 'DISPOSITION',     DATE_SUB(NOW(), INTERVAL 2 HOUR),     1, 'Discharge planned — cardiology agrees, low-risk unstable angina. Aspirin/Statin started.'),

-- EP_C: Tyler Brooks — BH Boarding / SI (5h)
(@EP_C, @P3, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 300 MINUTE), 1, 'Police bring-in. Reported suicidal ideation with plan — medication ingestion attempt denied by patient.'),
(@EP_C, @P3, @FAC, 'TRIAGE',          DATE_SUB(NOW(), INTERVAL 298 MINUTE), 1, 'ESI-3. No acute medical complaint. Calm, guarded. Toxicology ordered.'),
(@EP_C, @P3, @FAC, 'MEDICAL_CLEARANCE',DATE_SUB(NOW(), INTERVAL 240 MINUTE),1, 'Tox screen negative. BMP normal. Medically cleared for psych eval.'),
(@EP_C, @P3, @FAC, 'BH_SCREEN',       DATE_SUB(NOW(), INTERVAL 220 MINUTE), 1, 'Columbia SSRS: HIGH risk — specific plan, access to means.'),
(@EP_C, @P3, @FAC, 'INVOLUNTARY',     DATE_SUB(NOW(), INTERVAL 210 MINUTE), 1, 'Involuntary hold placed — physician certification completed.'),
(@EP_C, @P3, @FAC, 'PLACEMENT_CALL',  DATE_SUB(NOW(), INTERVAL 180 MINUTE), 1, 'Placement calls: Valley BH declined (full). State Hospital on hold.'),
(@EP_C, @P3, @FAC, 'PLACEMENT_CALL',  DATE_SUB(NOW(), INTERVAL 90 MINUTE),  1, 'Second placement call: Riverside BH — reviewing. No answer x2.'),

-- EP_E: Robert Patel — STROKE ALERT (25min)
(@EP_E, @P5, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 25 MINUTE),  1, 'EMS — last known well 35 minutes ago. Right-sided weakness, aphasia.'),
(@EP_E, @P5, @FAC, 'STROKE_ALERT',    DATE_SUB(NOW(), INTERVAL 24 MINUTE),  1, 'STROKE ALERT activated. Neurology notified. Door-to-CT clock started.'),
(@EP_E, @P5, @FAC, 'NIHSS',           DATE_SUB(NOW(), INTERVAL 22 MINUTE),  1, 'NIHSS score: 14 (severe). Right arm and leg weakness, expressive aphasia.'),
(@EP_E, @P5, @FAC, 'IMAGING',         DATE_SUB(NOW(), INTERVAL 18 MINUTE),  1, 'CT head: no hemorrhage. CT angio ordered.'),
(@EP_E, @P5, @FAC, 'IMAGING',         DATE_SUB(NOW(), INTERVAL 10 MINUTE),  1, 'CT angio: M1 occlusion left MCA — thrombectomy candidate.'),
(@EP_E, @P5, @FAC, 'TPA_DISCUSSION',  DATE_SUB(NOW(), INTERVAL 5 MINUTE),   1, 'tPA held — LKW 35min, within window but thrombectomy preferred given M1 occlusion.'),

-- EP_F: Linda Torres — MVA Transfer (2h)
(@EP_F, @P6, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 120 MINUTE), 1, 'EMS — MVC 60mph, restrained driver, airbag, +LOC x2min.'),
(@EP_F, @P6, @FAC, 'TRAUMA_ALERT',    DATE_SUB(NOW(), INTERVAL 119 MINUTE), 1, 'Trauma team activation. Mechanism: high-speed MVC.'),
(@EP_F, @P6, @FAC, 'FAST_EXAM',       DATE_SUB(NOW(), INTERVAL 115 MINUTE), 1, 'FAST positive — free fluid LUQ and pelvis.'),
(@EP_F, @P6, @FAC, 'BLOOD_BANK',      DATE_SUB(NOW(), INTERVAL 112 MINUTE), 1, 'MTP activated. O-neg x2 released. Type and cross sent.'),
(@EP_F, @P6, @FAC, 'MEDICATION',      DATE_SUB(NOW(), INTERVAL 106 MINUTE), 1, 'TXA 1g load given. Permissive hypotension strategy — target SBP 80-90.'),
(@EP_F, @P6, @FAC, 'IMAGING',         DATE_SUB(NOW(), INTERVAL 90 MINUTE),  1, 'CT abdomen: Grade III splenic laceration, active extravasation.'),
(@EP_F, @P6, @FAC, 'CONSULT',         DATE_SUB(NOW(), INTERVAL 80 MINUTE),  1, 'Surgical consult: OR on standby. Transfer to Level I Trauma preferred.'),
(@EP_F, @P6, @FAC, 'TRANSFER_ACCEPT', DATE_SUB(NOW(), INTERVAL 30 MINUTE),  1, 'Regional Trauma Center accepted. Medic 7 transport ETA 15 min.'),

-- EP_G: David Kim — COPD OBS near discharge (12h)
(@EP_G, @P7, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 12 HOUR),    1, 'Walk-in. Productive cough, increasing SOB x 3 days. SpO2 88% on RA.'),
(@EP_G, @P7, @FAC, 'MEDICATION',      DATE_SUB(NOW(), INTERVAL 715 MINUTE), 1, 'Albuterol neb started. Ipratropium added. O2 2L NC.'),
(@EP_G, @P7, @FAC, 'OBS_START',       DATE_SUB(NOW(), INTERVAL 11 HOUR),    1, 'COPD exacerbation protocol. Target SpO2 > 92%.'),
(@EP_G, @P7, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 10 HOUR),    1, 'ABG: pH 7.38, pCO2 52, compensated. No acute respiratory failure.'),
(@EP_G, @P7, @FAC, 'REASSESSMENT',    DATE_SUB(NOW(), INTERVAL 6 HOUR),     1, 'Peak flow 55% predicted. SpO2 96% on 2L. Improving.'),
(@EP_G, @P7, @FAC, 'REASSESSMENT',    DATE_SUB(NOW(), INTERVAL 1 HOUR),     1, 'Peak flow 62% predicted. Meets discharge criteria.'),
(@EP_G, @P7, @FAC, 'REFERRAL',        DATE_SUB(NOW(), INTERVAL 30 MINUTE),  1, 'E-Referral sent to Home Health for neb management and follow-up.'),

-- EP_I: Marcus Williams — Opioid OD (90min)
(@EP_I, @P9, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 90 MINUTE),  1, 'EMS — found unresponsive. Narcan 0.4mg IM x2 in field.'),
(@EP_I, @P9, @FAC, 'MEDICATION',      DATE_SUB(NOW(), INTERVAL 82 MINUTE),  1, 'Naloxone infusion 0.4mg/hr started. RR 8→14 at 15min.'),
(@EP_I, @P9, @FAC, 'RESTRAINT',       DATE_SUB(NOW(), INTERVAL 78 MINUTE),  1, 'Wrist restraints — patient combative post-reversal. Order documented.'),
(@EP_I, @P9, @FAC, 'LAB_RESULT',      DATE_SUB(NOW(), INTERVAL 60 MINUTE),  1, 'Urine tox: opiates positive. Benzo negative. BAL 0.'),
(@EP_I, @P9, @FAC, 'REASSESSMENT',    DATE_SUB(NOW(), INTERVAL 30 MINUTE),  1, 'GCS 15. Cooperative. Re-sedation risk continues — half-life monitoring.'),

-- EP_J: Patricia Nguyen — BH Boarding, Accepted (8h)
(@EP_J, @P10, @FAC, 'ARRIVAL',         DATE_SUB(NOW(), INTERVAL 8 HOUR),    1, 'Self-presented. Passive SI — "I don''t want to be here." No plan.'),
(@EP_J, @P10, @FAC, 'BH_SCREEN',       DATE_SUB(NOW(), INTERVAL 7 HOUR),    1, 'Columbia SSRS: moderate risk. Crisis counselor evaluation complete.'),
(@EP_J, @P10, @FAC, 'EMTALA',          DATE_SUB(NOW(), INTERVAL 7 HOUR),    1, 'MSE complete. EMTALA compliant. Psychiatric determination documented.'),
(@EP_J, @P10, @FAC, 'PLACEMENT_CALL',  DATE_SUB(NOW(), INTERVAL 6 HOUR),    1, 'Valley BH, Riverside BH, State Hospital called. Valley BH reviewing.'),
(@EP_J, @P10, @FAC, 'PLACEMENT_ACCEPT',DATE_SUB(NOW(), INTERVAL 90 MINUTE), 1, 'Valley BH accepted — unit 3B. Transport Medvan dispatched.'),
(@EP_J, @P10, @FAC, 'TRANSPORT',       DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, 'Medvan ETA 15 minutes. Patient notified. Family present.');


-- =============================================================================
-- SECTION B: ALERT ACKNOWLEDGEMENTS (oei_alert_ack)
-- Shows charge nurse has reviewed and acknowledged specific alerts.
-- =============================================================================

INSERT IGNORE INTO oei_alert_ack (alert_key, facility_id, user_id, acked_datetime, expires_datetime)
VALUES
    -- Sepsis alert for Wilson acknowledged — watching
    (CONCAT('SEPSIS_RISK:', @EP_A),           @FAC, 1, DATE_SUB(NOW(), INTERVAL 90 MINUTE),  DATE_ADD(NOW(), INTERVAL 90 MINUTE)),
    -- ICU bed wait acknowledged
    (CONCAT('BED_WAIT_ICU:', @EP_A),          @FAC, 1, DATE_SUB(NOW(), INTERVAL 30 MINUTE),  DATE_ADD(NOW(), INTERVAL 60 MINUTE)),
    -- LWBS risk for COPD patient cleared — near discharge
    (CONCAT('LWBS_RISK:', @EP_G),             @FAC, 1, DATE_SUB(NOW(), INTERVAL 15 MINUTE),  DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
    -- High-alert MAR overdue ack for stroke patient
    (CONCAT('MAR_OVERDUE:', @EP_E),           @FAC, 1, DATE_SUB(NOW(), INTERVAL 10 MINUTE),  DATE_ADD(NOW(), INTERVAL 60 MINUTE)),
    -- BH boarding dwell for Brooks (EP_C) — 5h
    (CONCAT('BH_BOARDING_DWELL:', @EP_C),     @FAC, 1, DATE_SUB(NOW(), INTERVAL 20 MINUTE),  DATE_ADD(NOW(), INTERVAL 60 MINUTE));


-- =============================================================================
-- SECTION C: DOWNTIME SYNC QUEUE (oei_downtime_sync_queue)
-- Demonstrates the Downtime Mode submodule — offline write queue.
-- Simulates a 12-minute network outage 90 minutes ago. All entries SYNCED.
-- Plus 1 PENDING entry still in queue (connectivity just restored).
-- =============================================================================

INSERT IGNORE INTO oei_downtime_sync_queue
    (facility_id, entry_type, payload_json, captured_client, queued_datetime,
     synced_datetime, status, result_note, submitted_by_user_id)
VALUES
    -- Arrival entered offline during outage
    (@FAC, 'ARRIVAL',
     JSON_OBJECT(
         'fname', 'Carlos', 'lname', 'Mendez', 'dob', '1985-06-14',
         'chief_complaint', 'Chest pain, onset 30 min ago',
         'arrival_mode', 'WALKIN', 'acuity_esi', 2
     ),
     DATE_SUB(NOW(), INTERVAL 88 MINUTE),
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE),
     'SYNCED', 'Synced — episode created pid 12',
     1),

    -- Vitals entered offline for existing patient
    (@FAC, 'VITALS',
     JSON_OBJECT(
         'episode_id', @EP_A, 'pid', @P1,
         'bp_systolic', 102, 'bp_diastolic', 64, 'hr', 118,
         'rr', 22, 'spo2', 93, 'temp_f', 101.2
     ),
     DATE_SUB(NOW(), INTERVAL 86 MINUTE),
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE),
     'SYNCED', 'Synced — triage re-assessment row inserted',
     1),

    -- Status note entered offline
    (@FAC, 'STATUS_NOTE',
     JSON_OBJECT(
         'episode_id', @EP_F, 'pid', @P6,
         'note', 'Transport team confirmed. Medic 7 ETA 10 min. Patient stable.'
     ),
     DATE_SUB(NOW(), INTERVAL 83 MINUTE),
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE),
     'SYNCED', 'Synced — status history entry added',
     1),

    -- Task note entered offline
    (@FAC, 'TASK_NOTE',
     JSON_OBJECT(
         'episode_id', @EP_B, 'pid', @P2,
         'task_type', 'STRESS_TEST', 'note', 'Patient on treadmill. EKG connected. Baseline HR 72.'
     ),
     DATE_SUB(NOW(), INTERVAL 81 MINUTE),
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE),
     'SYNCED', 'Synced — task note appended',
     1),

    -- Still PENDING — connectivity just restored seconds ago
    (@FAC, 'VITALS',
     JSON_OBJECT(
         'episode_id', @EP_I, 'pid', @P9,
         'bp_systolic', 116, 'bp_diastolic', 74, 'hr', 92,
         'rr', 16, 'spo2', 98, 'note', 'Post-Narcan 90min check. Stable.'
     ),
     DATE_SUB(NOW(), INTERVAL 2 MINUTE),
     DATE_SUB(NOW(), INTERVAL 1 MINUTE),
     NULL,
     'PENDING', NULL,
     1);


-- =============================================================================
-- SECTION D: MAR — HOLD REASON, LOT NUMBER, AMENDED RECORD SHOWCASE
-- Demonstrates new MAR completeness features: structured hold reasons,
-- lot numbers, nurse-supplied timestamps, and amend audit trail.
-- =============================================================================

-- Find the Vancomycin order for EP_A (Sepsis patient — high alert)
SET @ORD_VANC := (
    SELECT id FROM oei_mar_order
    WHERE episode_id = @EP_A AND drug_name LIKE '%Vancomycin%'
    LIMIT 1
);

-- Find Albuterol order for COPD patient
SET @ORD_ALBU := (
    SELECT id FROM oei_mar_order
    WHERE episode_id = @EP_G AND drug_name LIKE '%Albuterol%'
    LIMIT 1
);

-- Vancomycin — Q8H slots. Insert a Q8H slot that was HELD (HR too low)
-- and a subsequent slot that was GIVEN with lot number
INSERT IGNORE INTO oei_mar_administration
    (mar_order_id, episode_id, pid, facility_id,
     scheduled_datetime, administered_datetime,
     outcome, dose_given, unit_given, route_given,
     site, lot_number, hold_reason,
     administered_by_user_id, note, is_high_alert,
     created_datetime, updated_datetime)
VALUES
    -- Dose 1: GIVEN with lot number + nurse-supplied time
    (@ORD_VANC, @EP_A, @P1, @FAC,
     DATE_SUB(NOW(), INTERVAL 160 MINUTE),
     DATE_SUB(NOW(), INTERVAL 157 MINUTE),
     'GIVEN', '1750', 'mg', 'IV',
     'Right AC', 'VAN-2024-0891', NULL,
     1, 'Infused over 90 min. No red man syndrome. Pre-dose level drawn.',
     1,
     DATE_SUB(NOW(), INTERVAL 160 MINUTE),
     DATE_SUB(NOW(), INTERVAL 157 MINUTE)),

    -- Dose 2: HELD — level result pending  
    (@ORD_VANC, @EP_A, @P1, @FAC,
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE),
     'HELD', NULL, NULL, NULL,
     NULL, NULL, 'LEVEL_HIGH',
     1, 'Pre-dose vancomycin level 22 — holding per pharmacy. Will re-dose when level < 15.',
     1,
     DATE_SUB(NOW(), INTERVAL 80 MINUTE),
     DATE_SUB(NOW(), INTERVAL 78 MINUTE));

-- Albuterol COPD patient — a dose that was initially documented wrong (HELD)
-- then amended to GIVEN (nurse corrected the record)
INSERT IGNORE INTO oei_mar_administration
    (mar_order_id, episode_id, pid, facility_id,
     scheduled_datetime, administered_datetime,
     outcome, dose_given, unit_given, route_given,
     hold_reason, administered_by_user_id, note, is_high_alert,
     created_datetime, updated_datetime)
VALUES
    -- Q4H slot documented as HELD in error, then amended — amend trail in note
    (@ORD_ALBU, @EP_G, @P7, @FAC,
     DATE_SUB(NOW(), INTERVAL 4 HOUR),
     DATE_SUB(NOW(), INTERVAL 239 MINUTE),
     'GIVEN', '2.5', 'mg', 'INH',
     NULL, 1,
     '[Amended 2026-02-27 07:15:00 by user 1] Original entry: HELD/NPO in error — patient tolerating PO. Corrected to GIVEN after review of physician orders.',
     0,
     DATE_SUB(NOW(), INTERVAL 4 HOUR),
     DATE_SUB(NOW(), INTERVAL 240 MINUTE));


-- =============================================================================
-- SECTION E: SETTINGS — TRIAGE COLOR CUSTOMIZATION
-- Shows that this facility has customized ESI badge colors via the color picker
-- =============================================================================

INSERT INTO oei_settings (facility_id, setting_key, setting_value, updated_by_user_id, updated_datetime)
VALUES
    -- Custom ESI-1 color: deeper red
    (@FAC, 'triage_color_ESI_1', JSON_OBJECT('bg', '#7B0000', 'fg', '#FFFFFF'),
     1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    -- ESI-2 orange (facility preference over default red-orange)
    (@FAC, 'triage_color_ESI_2', JSON_OBJECT('bg', '#E65100', 'fg', '#FFFFFF'),
     1, DATE_SUB(NOW(), INTERVAL 3 DAY)),
    -- ESI-3 deeper yellow with dark text
    (@FAC, 'triage_color_ESI_3', JSON_OBJECT('bg', '#F9A825', 'fg', '#212121'),
     1, DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE
    setting_value        = VALUES(setting_value),
    updated_by_user_id   = VALUES(updated_by_user_id),
    updated_datetime     = VALUES(updated_datetime);


-- =============================================================================
-- SECTION F: ADDITIONAL HL7 ADT LOG ENTRIES
-- Richer HL7 history: A02 room moves, A03 discharge/transfer, A09 diversion
-- =============================================================================

INSERT INTO oei_hl7_outbound_log
    (episode_id, pid, facility_id, event_type, transport_type, endpoint,
     message_body, status, sent_datetime)
VALUES
    -- EP_A: A02 room assignment
    (@EP_A, @P1, @FAC, 'A02', 'MLLP', 'hl7.hospital.internal:2575',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 175 MINUTE), '%Y%m%d%H%i%s'),
            '||ADT^A02^ADT_A01|OEI010|T|2.5.1\rEVN|A02|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 175 MINUTE), '%Y%m%d%H%i%s'),
            '\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR'),
     'SENT', DATE_SUB(NOW(), INTERVAL 175 MINUTE)),

    -- EP_B: A01 OBS admission
    (@EP_B, @P2, @FAC, 'A01', 'MLLP', 'hl7.hospital.internal:2575',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 20 HOUR), '%Y%m%d%H%i%s'),
            '||ADT^A01^ADT_A01|OEI011|T|2.5.1\rEVN|A01|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 20 HOUR), '%Y%m%d%H%i%s'),
            '\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR'),
     'SENT', DATE_SUB(NOW(), INTERVAL 20 HOUR)),

    -- EP_E: A02 trauma bay assignment — urgent
    (@EP_E, @P5, @FAC, 'A02', 'MLLP', 'hl7.hospital.internal:2575',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 23 MINUTE), '%Y%m%d%H%i%s'),
            '||ADT^A02^ADT_A01|OEI012|T|2.5.1\rEVN|A02|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 23 MINUTE), '%Y%m%d%H%i%s'),
            '\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR'),
     'SENT', DATE_SUB(NOW(), INTERVAL 23 MINUTE)),

    -- EP_F: A03 transfer out (pending)
    (@EP_F, @P6, @FAC, 'A03', 'HTTP', 'https://rtc.hospital.org/hl7/adt',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 15 MINUTE), '%Y%m%d%H%i%s'),
            '||ADT^A03^ADT_A01|OEI013|T|2.5.1\rEVN|A03|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 15 MINUTE), '%Y%m%d%H%i%s'),
            '\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR'),
     'SENT', DATE_SUB(NOW(), INTERVAL 15 MINUTE)),

    -- Diversion A09 — TRAUMA DIVERSION activation
    (NULL, NULL, @FAC, 'A09', 'MLLP', 'hl7.hospital.internal:2575',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|EMS-DISPATCH|REGIONAL|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 2 HOUR), '%Y%m%d%H%i%s'),
            '||ADT^A09^ADT_A01|OEI014|T|2.5.1\rEVN|A09|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 2 HOUR), '%Y%m%d%H%i%s'),
            '\rZDV|TRAUMA|DIVERSION|Mass casualty incident — redirect to Regional Trauma Center'),
     'SENT', DATE_SUB(NOW(), INTERVAL 2 HOUR)),

    -- A08 status update — Naloxone patient
    (@EP_I, @P9, @FAC, 'A08', 'MLLP', 'hl7.hospital.internal:2575',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 30 MINUTE), '%Y%m%d%H%i%s'),
            '||ADT^A08^ADT_A01|OEI015|T|2.5.1\rEVN|A08|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 30 MINUTE), '%Y%m%d%H%i%s'),
            '\rPID|1||10^^^OEI^PI||Williams^Marcus||19960318|M\rPV1|1|E|ED04^^^OPENEMR'),
     'SENT', DATE_SUB(NOW(), INTERVAL 30 MINUTE)),

    -- Failed delivery example — NACK response from downstream
    (@EP_C, @P3, @FAC, 'A04', 'MLLP', 'hl7-backup.hospital.internal:2576',
     CONCAT('MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|BACKUP|FACILITY|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 298 MINUTE), '%Y%m%d%H%i%s'),
            '||ADT^A04^ADT_A01|OEI016|T|2.5.1\rEVN|A04|',
            DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 298 MINUTE), '%Y%m%d%H%i%s'),
            '\rPID|1||4^^^OEI^PI||Brooks^Tyler||19900511|M\rPV1|1|E|PSY1^^^OPENEMR'),
     'NACK', DATE_SUB(NOW(), INTERVAL 298 MINUTE));


-- =============================================================================
-- SUMMARY: Verify addon data loaded correctly
-- =============================================================================

SELECT 'Episode Events' AS section, COUNT(*) AS row_count FROM oei_episode_event WHERE facility_id = @FAC
UNION ALL
SELECT 'Alert Acks',    COUNT(*) FROM oei_alert_ack WHERE facility_id = @FAC
UNION ALL
SELECT 'Downtime Queue', COUNT(*) FROM oei_downtime_sync_queue WHERE facility_id = @FAC
UNION ALL
SELECT 'MAR Orders',    COUNT(*) FROM oei_mar_order WHERE facility_id = @FAC AND status = 'ACTIVE'
UNION ALL
SELECT 'MAR Admins',    COUNT(*) FROM oei_mar_administration WHERE facility_id = @FAC
UNION ALL
SELECT 'HL7 Log',       COUNT(*) FROM oei_hl7_outbound_log WHERE facility_id = @FAC
UNION ALL
SELECT 'Settings',      COUNT(*) FROM oei_settings WHERE facility_id = @FAC;

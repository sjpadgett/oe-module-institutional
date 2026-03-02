-- =============================================================================
-- DEMO SEED — Assisted Living (AL context)  v0.11.0
-- oe-module-institutional
-- =============================================================================
-- Standalone: does NOT depend on demo_seed.sql or demo_seed_addons.sql.
-- Inserts 5 AL residents across Wing A and Wing B with:
--   • oei_episode  (type='AL')
--   • oei_al_episode overlay (room, unit, care level, fall risk)
--   • care_teams + care_team_member  (OpenEMR certified tables)
--   • form_encounter  (anchors care plan entries per resident)
--   • form_care_plan  (goals + activities — FHIR/CCDA compatible)
--   • oei_adl_record  (per-shift ADL charting)
--   • oei_incident    (1 fall w/injury, 1 medication error)
--
-- Safe to re-run: uses INSERT IGNORE where applicable.
-- Adjust @FAC if your facility id is not 1.
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

SET @FAC  := 1;
SET @STAFF := 1;   -- users.id of the demo clinician (OpenEMR default admin = 1)

-- =============================================================================
-- IDEMPOTENT CLEANUP  (safe to re-run -- removes previous AL demo data only)
-- Delete in FK-dependency order. patient_data rows kept; INSERT IGNORE handles them.
-- =============================================================================

DELETE FROM oei_incident
    WHERE episode_id IN (SELECT id FROM oei_episode WHERE pid IN (50,51,52,53,54) AND type = 'AL');

DELETE FROM oei_adl_record
    WHERE episode_id IN (SELECT id FROM oei_episode WHERE pid IN (50,51,52,53,54) AND type = 'AL');

DELETE FROM form_care_plan
    WHERE pid IN (50,51,52,53,54);

DELETE ctm FROM care_team_member ctm
    INNER JOIN care_teams ct ON ct.id = ctm.care_team_id
    WHERE ct.pid IN (50,51,52,53,54);

DELETE FROM care_teams
    WHERE pid IN (50,51,52,53,54);

DELETE FROM oei_al_episode
    WHERE pid IN (50,51,52,53,54);

DELETE FROM oei_episode
    WHERE pid IN (50,51,52,53,54) AND type = 'AL';

DELETE FROM form_encounter
    WHERE pid IN (50,51,52,53,54) AND reason = 'AL Admission';

-- =============================================================================
-- DEMO PATIENTS  (INSERT IGNORE — safe if already present)
-- PIDs 50-54 reserved for AL demo to avoid collisions with ED demo (2-11)
-- =============================================================================

INSERT IGNORE INTO patient_data
    (id, pid, fname, lname, DOB, sex, street, city, state, postal_code, country_code,
     phone_home, status, date)
VALUES
    (50, 50, 'Eleanor', 'Hartwell',  '1938-04-12', 'Female', '210 Maple Ave',   'Springfield', 'IL', '62701', 'US', '217-555-0101', 'active', NOW()),
    (51, 51, 'George',  'Calloway',  '1935-11-28', 'Male',   '45 Oak Street',   'Springfield', 'IL', '62702', 'US', '217-555-0102', 'active', NOW()),
    (52, 52, 'Ruth',    'Okonkwo',   '1941-07-03', 'Female', '88 Pine Road',    'Springfield', 'IL', '62703', 'US', '217-555-0103', 'active', NOW()),
    (53, 53, 'Harold',  'Steinberg', '1932-02-17', 'Male',   '301 Elm Drive',   'Springfield', 'IL', '62704', 'US', '217-555-0104', 'active', NOW()),
    (54, 54, 'Dorothy', 'Vasquez',   '1945-09-22', 'Female', '17 Birch Lane',   'Springfield', 'IL', '62705', 'US', '217-555-0105', 'active', NOW());

SET @P1 := 50; -- Eleanor Hartwell  F/87  Wing A-101  TIER_3 / HIGH fall risk
SET @P2 := 51; -- George Calloway   M/90  Wing A-104  TIER_2 / MODERATE
SET @P3 := 52; -- Ruth Okonkwo      F/83  Wing A-108  TIER_1 / LOW
SET @P4 := 53; -- Harold Steinberg  M/93  Wing B-201  TIER_3 / HIGH  (fall w/injury)
SET @P5 := 54; -- Dorothy Vasquez   F/79  Wing B-205  TIER_2 / MODERATE

-- =============================================================================
-- FORM ENCOUNTERS  (anchor for care plan entries)
-- =============================================================================

INSERT INTO form_encounter
    (date, onset_date, reason, facility, pid, provider_id,
     facility_id, billing_facility, encounter, pos_code)
VALUES
    (DATE_SUB(NOW(), INTERVAL 47 DAY), DATE_SUB(NOW(), INTERVAL 47 DAY), 'AL Admission', 'Assisted Living', @P1, @STAFF, @FAC, @FAC, FLOOR(RAND()*900000+100000), '60'),
    (DATE_SUB(NOW(), INTERVAL 31 DAY), DATE_SUB(NOW(), INTERVAL 31 DAY), 'AL Admission', 'Assisted Living', @P2, @STAFF, @FAC, @FAC, FLOOR(RAND()*900000+100000), '60'),
    (DATE_SUB(NOW(), INTERVAL 18 DAY), DATE_SUB(NOW(), INTERVAL 18 DAY), 'AL Admission', 'Assisted Living', @P3, @STAFF, @FAC, @FAC, FLOOR(RAND()*900000+100000), '60'),
    (DATE_SUB(NOW(), INTERVAL 62 DAY), DATE_SUB(NOW(), INTERVAL 62 DAY), 'AL Admission', 'Assisted Living', @P4, @STAFF, @FAC, @FAC, FLOOR(RAND()*900000+100000), '60'),
    (DATE_SUB(NOW(), INTERVAL 9  DAY), DATE_SUB(NOW(), INTERVAL 9  DAY), 'AL Admission', 'Assisted Living', @P5, @STAFF, @FAC, @FAC, FLOOR(RAND()*900000+100000), '60');

SET @ENC1 := (SELECT id FROM form_encounter WHERE pid = @P1 AND reason = 'AL Admission' ORDER BY id DESC LIMIT 1);
SET @ENC2 := (SELECT id FROM form_encounter WHERE pid = @P2 AND reason = 'AL Admission' ORDER BY id DESC LIMIT 1);
SET @ENC3 := (SELECT id FROM form_encounter WHERE pid = @P3 AND reason = 'AL Admission' ORDER BY id DESC LIMIT 1);
SET @ENC4 := (SELECT id FROM form_encounter WHERE pid = @P4 AND reason = 'AL Admission' ORDER BY id DESC LIMIT 1);
SET @ENC5 := (SELECT id FROM form_encounter WHERE pid = @P5 AND reason = 'AL Admission' ORDER BY id DESC LIMIT 1);

-- =============================================================================
-- OEI EPISODES  (type='AL')
-- =============================================================================

INSERT INTO oei_episode
    (pid, facility_id, type, start_datetime, status, chief_complaint,
     created_by_user_id, created_datetime)
VALUES
    (@P1, @FAC, 'AL', DATE_SUB(NOW(), INTERVAL 47 DAY), 'ACTIVE', 'Memory care placement — moderate dementia, fall history',          @STAFF, DATE_SUB(NOW(), INTERVAL 47 DAY)),
    (@P2, @FAC, 'AL', DATE_SUB(NOW(), INTERVAL 31 DAY), 'ACTIVE', 'Post-hip-replacement rehab and long-term care transition',         @STAFF, DATE_SUB(NOW(), INTERVAL 31 DAY)),
    (@P3, @FAC, 'AL', DATE_SUB(NOW(), INTERVAL 18 DAY), 'ACTIVE', 'Independent-living support — COPD management, medication assist',  @STAFF, DATE_SUB(NOW(), INTERVAL 18 DAY)),
    (@P4, @FAC, 'AL', DATE_SUB(NOW(), INTERVAL 62 DAY), 'ACTIVE', 'Advanced Parkinson\'s — mobility and swallow safety needs',       @STAFF, DATE_SUB(NOW(), INTERVAL 62 DAY)),
    (@P5, @FAC, 'AL', DATE_SUB(NOW(), INTERVAL 9  DAY), 'ACTIVE', 'CHF and T2DM — medication management and dietary monitoring',     @STAFF, DATE_SUB(NOW(), INTERVAL 9  DAY));

SET @EP1 := (SELECT id FROM oei_episode WHERE pid = @P1 AND facility_id = @FAC AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP2 := (SELECT id FROM oei_episode WHERE pid = @P2 AND facility_id = @FAC AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP3 := (SELECT id FROM oei_episode WHERE pid = @P3 AND facility_id = @FAC AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP4 := (SELECT id FROM oei_episode WHERE pid = @P4 AND facility_id = @FAC AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP5 := (SELECT id FROM oei_episode WHERE pid = @P5 AND facility_id = @FAC AND type = 'AL' ORDER BY id DESC LIMIT 1);

-- =============================================================================
-- OEI_AL_EPISODE  (AL overlay — room, unit, care level, fall risk)
-- =============================================================================
-- care_level:      TIER_1=low acuity, TIER_2=moderate, TIER_3=high
-- fall_risk_level: LOW(<25), MODERATE(25-44), HIGH(45+)  Morse scale
-- =============================================================================

INSERT INTO oei_al_episode
    (episode_id, pid, facility_id, encounter_id, room, unit,
     care_level, fall_risk_level, fall_risk_score, admit_reason,
     last_adl_score, last_adl_datetime, created_datetime)
VALUES
    (@EP1, @P1, @FAC, @ENC1, '101', 'Wing A', 'TIER_3', 'HIGH',     78, 'Memory care — moderate dementia with behavioral disturbances and fall history',    14, DATE_SUB(NOW(), INTERVAL 6  HOUR), DATE_SUB(NOW(), INTERVAL 47 DAY)),
    (@EP2, @P2, @FAC, @ENC2, '104', 'Wing A', 'TIER_2', 'MODERATE', 38, 'Post-hip arthroplasty transition from SNF — PT/OT in progress',                   20, DATE_SUB(NOW(), INTERVAL 7  HOUR), DATE_SUB(NOW(), INTERVAL 31 DAY)),
    (@EP3, @P3, @FAC, @ENC3, '108', 'Wing A', 'TIER_1', 'LOW',      12, 'COPD management and medication administration assistance',                         25, DATE_SUB(NOW(), INTERVAL 8  HOUR), DATE_SUB(NOW(), INTERVAL 18 DAY)),
    (@EP4, @P4, @FAC, @ENC4, '201', 'Wing B', 'TIER_3', 'HIGH',     91, 'Advanced Parkinson\'s — fall prevention, dysphagia protocol, daily PT',           10, DATE_SUB(NOW(), INTERVAL 5  HOUR), DATE_SUB(NOW(), INTERVAL 62 DAY)),
    (@EP5, @P5, @FAC, @ENC5, '205', 'Wing B', 'TIER_2', 'MODERATE', 32, 'CHF/T2DM — daily weights, fluid restriction, insulin management',                 22, DATE_SUB(NOW(), INTERVAL 9  HOUR), DATE_SUB(NOW(), INTERVAL 9  DAY));

-- =============================================================================
-- CARE TEAMS  (OpenEMR certified — care_teams + care_team_member)
-- =============================================================================

INSERT IGNORE INTO care_teams (pid, status, team_name, note, created_by, updated_by)
VALUES
    (@P1, 'active', 'Eleanor Hartwell Care Team',  'Memory care specialist team — weekly interdisciplinary rounds', @STAFF, @STAFF),
    (@P2, 'active', 'George Calloway Care Team',   'Post-surgical rehab team — PT/OT twice weekly', @STAFF, @STAFF),
    (@P3, 'active', 'Ruth Okonkwo Care Team',      'Respiratory and medication management focus', @STAFF, @STAFF),
    (@P4, 'active', 'Harold Steinberg Care Team',  'Parkinson\'s specialist team — neurology consult monthly', @STAFF, @STAFF),
    (@P5, 'active', 'Dorothy Vasquez Care Team',   'Cardiac/metabolic monitoring team', @STAFF, @STAFF);

SET @CT1 := (SELECT id FROM care_teams WHERE pid = @P1 AND status = 'active' ORDER BY id DESC LIMIT 1);
SET @CT2 := (SELECT id FROM care_teams WHERE pid = @P2 AND status = 'active' ORDER BY id DESC LIMIT 1);
SET @CT3 := (SELECT id FROM care_teams WHERE pid = @P3 AND status = 'active' ORDER BY id DESC LIMIT 1);
SET @CT4 := (SELECT id FROM care_teams WHERE pid = @P4 AND status = 'active' ORDER BY id DESC LIMIT 1);
SET @CT5 := (SELECT id FROM care_teams WHERE pid = @P5 AND status = 'active' ORDER BY id DESC LIMIT 1);

-- Care team members (user_id=1 = demo admin; role from list_options care_team_roles)
INSERT IGNORE INTO care_team_member
    (care_team_id, user_id, role, status, provider_since, note, created_by, updated_by)
VALUES
    (@CT1, @STAFF, 'physician',             'active', DATE_SUB(NOW(), INTERVAL 47 DAY), 'Attending — memory care specialist', @STAFF, @STAFF),
    (@CT1, @STAFF, 'nurse',                 'active', DATE_SUB(NOW(), INTERVAL 47 DAY), 'Primary care aide — day shift',      @STAFF, @STAFF),
    (@CT2, @STAFF, 'physician',             'active', DATE_SUB(NOW(), INTERVAL 31 DAY), 'Orthopedic liaison',                 @STAFF, @STAFF),
    (@CT2, @STAFF, 'therapist',             'active', DATE_SUB(NOW(), INTERVAL 31 DAY), 'PT/OT — twice weekly',               @STAFF, @STAFF),
    (@CT3, @STAFF, 'physician',             'active', DATE_SUB(NOW(), INTERVAL 18 DAY), 'Primary care attending',             @STAFF, @STAFF),
    (@CT3, @STAFF, 'nurse',                 'active', DATE_SUB(NOW(), INTERVAL 18 DAY), 'Medication administration',          @STAFF, @STAFF),
    (@CT4, @STAFF, 'specialist',            'active', DATE_SUB(NOW(), INTERVAL 62 DAY), 'Neurology — monthly consult',        @STAFF, @STAFF),
    (@CT4, @STAFF, 'nurse',                 'active', DATE_SUB(NOW(), INTERVAL 62 DAY), 'Parkinson\'s care aide',             @STAFF, @STAFF),
    (@CT5, @STAFF, 'primary_care_provider', 'active', DATE_SUB(NOW(), INTERVAL 9  DAY), 'CHF/T2DM management',               @STAFF, @STAFF),
    (@CT5, @STAFF, 'dietitian',             'active', DATE_SUB(NOW(), INTERVAL 9  DAY), 'Fluid restriction and meal planning',@STAFF, @STAFF);

-- =============================================================================
-- CARE PLANS  (form_care_plan — OpenEMR certified, CCDA/FHIR compatible)
-- =============================================================================
-- care_plan_type: 'goal' | 'activity'
-- plan_status:    'active' | 'completed' | 'on-hold'
-- =============================================================================

INSERT INTO form_care_plan
    (date, pid, encounter, user, groupname, authorized, activity,
     description, care_plan_type, plan_status, proposed_date)
VALUES
-- Eleanor Hartwell — TIER_3 memory care
    (DATE_SUB(NOW(), INTERVAL 47 DAY), @P1, @ENC1, @STAFF, 'Default', 1, 1,
     'Prevent fall-related injury — maintain bed/chair alarm at all times and non-slip footwear', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 90 DAY)),
    (DATE_SUB(NOW(), INTERVAL 47 DAY), @P1, @ENC1, @STAFF, 'Default', 1, 1,
     'Maintain orientation and reduce agitation through structured daily routine', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 60 DAY)),
    (DATE_SUB(NOW(), INTERVAL 47 DAY), @P1, @ENC1, @STAFF, 'Default', 1, 1,
     'Daily music therapy 10:00–10:30 AM per dementia-care protocol', 'activity', 'active', NULL),
    (DATE_SUB(NOW(), INTERVAL 47 DAY), @P1, @ENC1, @STAFF, 'Default', 1, 1,
     'Fall risk reassessment with Morse Scale every 30 days', 'activity', 'active', DATE_ADD(NOW(), INTERVAL 13 DAY)),

-- George Calloway — TIER_2 post-hip
    (DATE_SUB(NOW(), INTERVAL 31 DAY), @P2, @ENC2, @STAFF, 'Default', 1, 1,
     'Achieve independent ambulation with walker on level surfaces within 60 days of admission', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 29 DAY)),
    (DATE_SUB(NOW(), INTERVAL 31 DAY), @P2, @ENC2, @STAFF, 'Default', 1, 1,
     'PT session Monday/Wednesday/Friday — hip strengthening and gait training', 'activity', 'active', NULL),
    (DATE_SUB(NOW(), INTERVAL 31 DAY), @P2, @ENC2, @STAFF, 'Default', 1, 1,
     'Pain management review — wean from opioids to NSAID/acetaminophen by week 6', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 13 DAY)),

-- Ruth Okonkwo — TIER_1 COPD
    (DATE_SUB(NOW(), INTERVAL 18 DAY), @P3, @ENC3, @STAFF, 'Default', 1, 1,
     'Maintain SpO2 ≥ 92% on room air during routine activities', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 42 DAY)),
    (DATE_SUB(NOW(), INTERVAL 18 DAY), @P3, @ENC3, @STAFF, 'Default', 1, 1,
     'Administer inhaler medications per schedule and document adherence daily', 'activity', 'active', NULL),

-- Harold Steinberg — TIER_3 Parkinson's
    (DATE_SUB(NOW(), INTERVAL 62 DAY), @P4, @ENC4, @STAFF, 'Default', 1, 1,
     'Zero aspiration events — strict thickened-liquid diet and supervised meals', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 28 DAY)),
    (DATE_SUB(NOW(), INTERVAL 62 DAY), @P4, @ENC4, @STAFF, 'Default', 1, 1,
     'Reduce fall frequency — current 2/month target to 0/month via Parkinson\'s mobility protocol', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 60 DAY)),
    (DATE_SUB(NOW(), INTERVAL 62 DAY), @P4, @ENC4, @STAFF, 'Default', 1, 1,
     'Daily PT — balance and gait training; OT — adaptive equipment assessment', 'activity', 'active', NULL),
    (DATE_SUB(NOW(), INTERVAL 62 DAY), @P4, @ENC4, @STAFF, 'Default', 1, 1,
     'Carbidopa/levodopa administered within 30 minutes of scheduled time — track adherence', 'activity', 'active', NULL),

-- Dorothy Vasquez — TIER_2 CHF/T2DM
    (DATE_SUB(NOW(), INTERVAL 9 DAY), @P5, @ENC5, @STAFF, 'Default', 1, 1,
     'Maintain daily weight within 2 lb of baseline — alert provider if exceeded', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 90 DAY)),
    (DATE_SUB(NOW(), INTERVAL 9 DAY), @P5, @ENC5, @STAFF, 'Default', 1, 1,
     'Fasting blood glucose 80-180 mg/dL — daily monitoring and insulin log', 'goal', 'active', DATE_ADD(NOW(), INTERVAL 30 DAY)),
    (DATE_SUB(NOW(), INTERVAL 9 DAY), @P5, @ENC5, @STAFF, 'Default', 1, 1,
     '1500 mL fluid restriction daily; 2g sodium cardiac diet', 'activity', 'active', NULL);

-- =============================================================================
-- ADL RECORDS  (oei_adl_record — MDS 3.0 coding 0=independent, 4=total assist)
-- Domains: bathing, dressing, grooming, transfer, ambulation, eating, toileting
-- =============================================================================

-- Eleanor Hartwell — HIGH dependency (adl_score ~14)
INSERT INTO oei_adl_record (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
VALUES
    (@EP1, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 30 HOUR),
     '{"bathing":4,"dressing":3,"grooming":3,"transfer":3,"ambulation":3,"eating":2,"toileting":4}', 22,
     'Night shift: confused, resisted morning care. Bed alarm triggered twice. No fall.'),
    (@EP1, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 6 HOUR),
     '{"bathing":4,"dressing":3,"grooming":2,"transfer":3,"ambulation":3,"eating":2,"toileting":3}', 20,
     'Day shift: more cooperative after breakfast. Music therapy at 10 AM — calm for 45 min.');

-- George Calloway — MODERATE dependency, improving (post-hip)
INSERT INTO oei_adl_record (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
VALUES
    (@EP2, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 32 HOUR),
     '{"bathing":2,"dressing":2,"grooming":1,"transfer":3,"ambulation":3,"eating":1,"toileting":2}', 14,
     'Improving transfer with walker. Rated pain 4/10 post-PT.'),
    (@EP2, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 7 HOUR),
     '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":3,"eating":1,"toileting":2}', 13,
     'PT this AM — achieved 20 ft ambulation with walker. Good progress.');

-- Ruth Okonkwo — LOW dependency (TIER_1)
INSERT INTO oei_adl_record (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
VALUES
    (@EP3, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 34 HOUR),
     '{"bathing":1,"dressing":1,"grooming":0,"transfer":1,"ambulation":1,"eating":0,"toileting":1}', 5,
     'Independent with most tasks. Assisted with shower per preference.'),
    (@EP3, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 8 HOUR),
     '{"bathing":1,"dressing":1,"grooming":0,"transfer":1,"ambulation":1,"eating":0,"toileting":1}', 5,
     'Stable. SpO2 94% on RA — within goal. Inhaler administered on schedule.');

-- Harold Steinberg — HIGH dependency (Parkinson's, adl_score ~10 with rigidity)
INSERT INTO oei_adl_record (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
VALUES
    (@EP4, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 28 HOUR),
     '{"bathing":3,"dressing":4,"grooming":3,"transfer":4,"ambulation":3,"eating":2,"toileting":3}', 22,
     'Morning off-period — significant rigidity pre-meds. Levodopa 0710 (within window).'),
    (@EP4, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 5 HOUR),
     '{"bathing":3,"dressing":3,"grooming":3,"transfer":4,"ambulation":4,"eating":2,"toileting":3}', 22,
     'Post-fall assessment — see incident report. Ambulation suspended pending PT clearance.');

-- Dorothy Vasquez — MODERATE dependency (CHF/DM)
INSERT INTO oei_adl_record (episode_id, facility_id, noted_by_user_id, noted_datetime, adl_json, adl_score, notes)
VALUES
    (@EP5, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 33 HOUR),
     '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":2,"eating":1,"toileting":2}', 12,
     'Weight today 142 lbs — up 1.8 lbs from baseline. Within threshold. FBG 162.'),
    (@EP5, @FAC, @STAFF, DATE_SUB(NOW(), INTERVAL 9 HOUR),
     '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":2,"eating":1,"toileting":2}', 12,
     'Weight 143 lbs — +2.2 lbs. Notified charge nurse and attending per CHF weight protocol. Furosemide dose reviewed.');

-- =============================================================================
-- INCIDENTS  (oei_incident)
-- =============================================================================

-- Incident 1: Harold Steinberg — unwitnessed fall with minor laceration (HIGH severity)
INSERT INTO oei_incident
    (episode_id, facility_id, reported_by_user_id, incident_type, severity,
     incident_datetime, location_description, narrative, corrective_action,
     reported_state, mandatory_report_sent, created_datetime)
VALUES
    (@EP4, @FAC, @STAFF, 'FALL_INJURY', 'HIGH',
     DATE_SUB(NOW(), INTERVAL 5 HOUR),
     'Wing B Room 201 - beside bed',
     'Resident found on floor beside bed during AM care check. Unwitnessed. Alert and oriented x2 on assessment. No LOC. 2cm laceration right forearm, mild bruising right hip. No signs of hip fracture. X-ray ordered. Physician and family notified.',
     'Assisted resident to bed. Wound cleaned and dressed. Neuro checks q1h x4h. Bed in lowest position, floor mat placed. Fall alarm re-evaluated. PT to reassess ambulation safety. Care plan goal updated.',
     'PENDING', 0, DATE_SUB(NOW(), INTERVAL 270 MINUTE));

-- Incident 2: Dorothy Vasquez — medication administration error (MODERATE severity)
INSERT INTO oei_incident
    (episode_id, facility_id, reported_by_user_id, incident_type, severity,
     incident_datetime, location_description, narrative, corrective_action,
     reported_state, mandatory_report_sent, created_datetime)
VALUES
    (@EP5, @FAC, @STAFF, 'MED_ERROR', 'MODERATE',
     DATE_SUB(NOW(), INTERVAL 218 HOUR),
     'Wing B Room 205 - medication cart',
     'Resident received furosemide 40mg instead of scheduled 20mg due to look-alike packaging. Error discovered during next-shift MAR review. No acute adverse effects. BP 118/72, HR 74, SpO2 97%. Electrolytes ordered and within normal limits.',
     'Physician notified immediately. Electrolyte panel ordered. Resident monitored q2h x8h. Pharmacy notified. Root cause: look-alike packaging. Corrective action: separate storage, barcode scan protocol initiated.',
     'NOT_REQUIRED', 0, DATE_SUB(NOW(), INTERVAL 217 HOUR));

-- =============================================================================
-- VERIFICATION QUERY
-- =============================================================================

SELECT 'AL Residents'    AS section, COUNT(*) AS cnt  FROM oei_episode        WHERE facility_id = @FAC AND type = 'AL'
UNION ALL
SELECT 'AL Overlays',    COUNT(*) FROM oei_al_episode    WHERE facility_id = @FAC
UNION ALL
SELECT 'Care Teams',     COUNT(*) FROM care_teams        WHERE pid IN (@P1,@P2,@P3,@P4,@P5) AND status = 'active'
UNION ALL
SELECT 'Team Members',   COUNT(*) FROM care_team_member  WHERE care_team_id IN (@CT1,@CT2,@CT3,@CT4,@CT5)
UNION ALL
SELECT 'Care Plan Goals/Activities', COUNT(*) FROM form_care_plan WHERE pid IN (@P1,@P2,@P3,@P4,@P5)
UNION ALL
SELECT 'ADL Records',    COUNT(*) FROM oei_adl_record    WHERE facility_id = @FAC AND episode_id IN (@EP1,@EP2,@EP3,@EP4,@EP5)
UNION ALL
SELECT 'Incidents',      COUNT(*) FROM oei_incident      WHERE facility_id = @FAC AND episode_id IN (@EP4,@EP5);

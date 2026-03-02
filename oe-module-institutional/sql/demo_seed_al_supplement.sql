-- =============================================================================
-- DEMO SEED SUPPLEMENT — Assisted Living (AL context)  v0.11.0
-- oe-module-institutional
-- =============================================================================
-- Fills every gap between the consolidated institutional-demo-seed.sql and
-- full AL submodule coverage. Run AFTER institutional-demo-seed.sql.
--
-- AL episodes already seeded (do NOT re-seed):
--   ep 14 → pid 50  Eleanor Hartwell   Wing A-101  TIER_3/HIGH  memory care
--   ep 15 → pid 51  George Calloway    Wing A-104  TIER_2/MOD   post-hip
--   ep 16 → pid 52  Ruth Okonkwo       Wing A-108  TIER_1/LOW   COPD
--   ep 17 → pid 53  Harold Steinberg   Wing B-201  TIER_3/HIGH  Parkinson's
--   ep 18 → pid 54  Dorothy Vasquez    Wing B-205  TIER_2/MOD   CHF/T2DM
--
-- Tables filled here:
--   OpenEMR native: form_encounter, form_care_plan, care_teams, care_team_member
--   oei tables:     oei_episode_status_history, oei_episode_event,
--                   oei_episode_location, oei_task, oei_mar_order,
--                   oei_mar_administration, oei_ereferral, oei_episode_disposition
--
-- ID ranges (continuing from consolidated seed):
--   oei_task                 → 67+
--   oei_mar_order            → 32+
--   oei_mar_administration   → 22+
--   oei_episode_status_history → 61+
--   oei_episode_event        → 80+
--   oei_episode_location     → 13+
--   oei_ereferral            → 2+
--   oei_episode_disposition  → 2+
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- =============================================================================
-- IDEMPOTENT CLEANUP  (safe to re-run)
-- =============================================================================

DELETE FROM care_team_member WHERE care_team_id IN
    (SELECT id FROM care_teams WHERE pid IN (50,51,52,53,54));
DELETE FROM care_teams     WHERE pid IN (50,51,52,53,54);
DELETE FROM form_care_plan WHERE pid IN (50,51,52,53,54);
DELETE FROM form_encounter WHERE pid IN (50,51,52,53,54) AND reason = 'AL Admission';

DELETE FROM oei_episode_status_history WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_episode_event          WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_episode_location       WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_task                   WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_mar_administration     WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_mar_order              WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_ereferral              WHERE episode_id IN (14,15,16,17,18);
DELETE FROM oei_episode_disposition    WHERE episode_id IN (14,15,16,17,18);

-- =============================================================================
-- FORM_ENCOUNTER  (anchor for care plan entries — encounter_ids 297-301)
-- These IDs are already referenced by oei_al_episode in the base seed.
-- =============================================================================

INSERT INTO `form_encounter`
    (`id`, `date`, `onset_date`, `reason`, `facility`, `pid`, `provider_id`,
     `facility_id`, `billing_facility`, `encounter`, `pos_code`)
VALUES
    (297, '2026-01-12 22:20:13', '2026-01-12 22:20:13', 'AL Admission', 'Assisted Living', 50, 1, 1, 1, 1000050, 60),
    (298, '2026-01-28 22:20:13', '2026-01-28 22:20:13', 'AL Admission', 'Assisted Living', 51, 1, 1, 1, 1000051, 60),
    (299, '2026-02-10 22:20:13', '2026-02-10 22:20:13', 'AL Admission', 'Assisted Living', 52, 1, 1, 1, 1000052, 60),
    (300, '2025-12-28 22:20:13', '2025-12-28 22:20:13', 'AL Admission', 'Assisted Living', 53, 1, 1, 1, 1000053, 60),
    (301, '2026-02-19 22:20:13', '2026-02-19 22:20:13', 'AL Admission', 'Assisted Living', 54, 1, 1, 1, 1000054, 60);

-- =============================================================================
-- CARE_TEAMS  (OpenEMR certified — one active team per AL resident)
-- =============================================================================

INSERT INTO `care_teams` (`pid`, `status`, `team_name`, `note`, `created_by`, `updated_by`)
VALUES
    (50, 'active', 'Eleanor Hartwell Care Team',  'Memory care specialist team — weekly interdisciplinary rounds', 1, 1),
    (51, 'active', 'George Calloway Care Team',   'Post-surgical rehab — PT/OT twice weekly', 1, 1),
    (52, 'active', 'Ruth Okonkwo Care Team',       'Respiratory and medication management', 1, 1),
    (53, 'active', 'Harold Steinberg Care Team',  'Parkinson''s specialist team — neurology consult monthly', 1, 1),
    (54, 'active', 'Dorothy Vasquez Care Team',   'Cardiac/metabolic monitoring team', 1, 1);

-- =============================================================================
-- CARE_TEAM_MEMBER  (roles from list_options care_team_roles)
-- =============================================================================

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.id, 1, 'physician', 'active', ct.date_created, 'Attending physician', 1, 1
    FROM care_teams ct WHERE ct.pid IN (50,51,52,53,54) AND ct.status = 'active';

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.id, 1, 'nurse', 'active', ct.date_created,
    CASE ct.pid
        WHEN 50 THEN 'Primary care aide — day shift, memory care certified'
        WHEN 51 THEN 'Post-surgical nursing — PT coordination'
        WHEN 52 THEN 'Medication administration and respiratory monitoring'
        WHEN 53 THEN 'Parkinson''s care aide — daily PT liaison'
        WHEN 54 THEN 'Cardiac monitoring — daily weights and fluid tracking'
    END,
    1, 1
    FROM care_teams ct WHERE ct.pid IN (50,51,52,53,54) AND ct.status = 'active';

-- =============================================================================
-- FORM_CARE_PLAN  (OpenEMR certified — goals and activities, CCDA/FHIR compatible)
-- =============================================================================

INSERT INTO `form_care_plan`
    (`date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
     `description`, `care_plan_type`, `plan_status`, `proposed_date`)
VALUES
-- Eleanor Hartwell (pid=50, enc=297) — TIER_3 memory care / HIGH fall risk
    ('2026-01-12', 50, 297, 1, 'Default', 1, 1,
     'Prevent fall-related injury — bed/chair alarm active at all times, non-slip footwear required',
     'goal', 'active', '2026-04-12'),
    ('2026-01-12', 50, 297, 1, 'Default', 1, 1,
     'Maintain orientation and reduce agitation through structured daily routine and music therapy',
     'goal', 'active', '2026-03-12'),
    ('2026-01-12', 50, 297, 1, 'Default', 1, 1,
     'Daily music therapy session 10:00–10:30 AM per dementia-care protocol',
     'activity', 'active', NULL),
    ('2026-01-12', 50, 297, 1, 'Default', 1, 1,
     'Morse Fall Scale reassessment every 30 days; update care plan if score changes tier',
     'activity', 'active', '2026-03-13'),

-- George Calloway (pid=51, enc=298) — TIER_2 post-hip arthroplasty
    ('2026-01-28', 51, 298, 1, 'Default', 1, 1,
     'Achieve independent ambulation with walker on level surfaces within 60 days of admission',
     'goal', 'active', '2026-03-28'),
    ('2026-01-28', 51, 298, 1, 'Default', 1, 1,
     'Reduce pain score to 3 or below during ambulation; wean opioids by week 6',
     'goal', 'active', '2026-03-10'),
    ('2026-01-28', 51, 298, 1, 'Default', 1, 1,
     'PT session Monday/Wednesday/Friday — hip strengthening and progressive gait training',
     'activity', 'active', NULL),

-- Ruth Okonkwo (pid=52, enc=299) — TIER_1 COPD
    ('2026-02-10', 52, 299, 1, 'Default', 1, 1,
     'Maintain SpO2 at or above 92% on room air during all routine activities',
     'goal', 'active', '2026-04-10'),
    ('2026-02-10', 52, 299, 1, 'Default', 1, 1,
     'Administer scheduled inhalers with adherence documented daily in MAR',
     'activity', 'active', NULL),
    ('2026-02-10', 52, 299, 1, 'Default', 1, 1,
     'Pulmonary reassessment at 30 and 60 days — adjust oxygen threshold if needed',
     'activity', 'active', '2026-03-10'),

-- Harold Steinberg (pid=53, enc=300) — TIER_3 Parkinson's
    ('2025-12-28', 53, 300, 1, 'Default', 1, 1,
     'Zero aspiration events — strict thickened-liquid (nectar consistency) diet, supervised meals',
     'goal', 'active', '2026-03-28'),
    ('2025-12-28', 53, 300, 1, 'Default', 1, 1,
     'Reduce fall frequency from 2 per month to zero via Parkinson''s mobility protocol',
     'goal', 'active', '2026-03-28'),
    ('2025-12-28', 53, 300, 1, 'Default', 1, 1,
     'Daily PT — balance and gait; OT — adaptive equipment assessment weekly',
     'activity', 'active', NULL),
    ('2025-12-28', 53, 300, 1, 'Default', 1, 1,
     'Carbidopa/levodopa administered within 30 minutes of scheduled time — track in MAR',
     'activity', 'active', NULL),

-- Dorothy Vasquez (pid=54, enc=301) — TIER_2 CHF/T2DM
    ('2026-02-19', 54, 301, 1, 'Default', 1, 1,
     'Maintain daily weight within 2 lb of dry weight baseline — notify provider if exceeded',
     'goal', 'active', '2026-05-19'),
    ('2026-02-19', 54, 301, 1, 'Default', 1, 1,
     'Fasting blood glucose 80–180 mg/dL — daily monitoring with insulin sliding scale log',
     'goal', 'active', '2026-03-19'),
    ('2026-02-19', 54, 301, 1, 'Default', 1, 1,
     '1500 mL fluid restriction daily; 2g sodium cardiac diet; daily weight log',
     'activity', 'active', NULL);

-- =============================================================================
-- OEI_EPISODE_STATUS_HISTORY  (continuing from id=60)
-- AL uses simpler status flow: ADMIT → ACTIVE → (DISCHARGE when applicable)
-- =============================================================================

INSERT INTO `oei_episode_status_history`
    (`id`, `episode_id`, `status_code`, `set_by_user_id`, `set_datetime`, `note`)
VALUES
    (61, 14, 'ADMIT',    1, '2026-01-12 22:20:13', 'Memory care admission — escorted from daughter''s vehicle'),
    (62, 14, 'ACTIVE',   1, '2026-01-12 23:00:00', 'Orientation complete. Care plan initiated. Bed alarm activated.'),
    (63, 15, 'ADMIT',    1, '2026-01-28 22:20:13', 'SNF transfer — post right hip arthroplasty day 12'),
    (64, 15, 'ACTIVE',   1, '2026-01-28 23:00:00', 'PT evaluation completed. Gait training protocol started.'),
    (65, 16, 'ADMIT',    1, '2026-02-10 22:20:13', 'Self-referral — COPD exacerbation history, requests medication assistance'),
    (66, 16, 'ACTIVE',   1, '2026-02-10 22:45:00', 'Baseline SpO2 94% RA. Inhaler schedule established.'),
    (67, 17, 'ADMIT',    1, '2025-12-28 22:20:13', 'Family placement — advanced Parkinson''s, caregiver burnout'),
    (68, 17, 'ACTIVE',   1, '2025-12-28 23:30:00', 'Dysphagia assessment complete. Thickened liquids ordered. Fall protocol active.'),
    (69, 18, 'ADMIT',    1, '2026-02-19 22:20:13', 'Cardiology referral — CHF NYHA Class II, T2DM poorly controlled'),
    (70, 18, 'ACTIVE',   1, '2026-02-19 23:00:00', 'Baseline weight 140.2 lbs. FBG 214. Fluid restriction and insulin sliding scale started.');

-- =============================================================================
-- OEI_EPISODE_EVENT  (continuing from id=79)
-- Clinical timeline events for each AL resident
-- =============================================================================

INSERT INTO `oei_episode_event`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES
-- Eleanor Hartwell — ep 14
    (80, 14, 50, NULL, 1, 'ARRIVAL',         '2026-01-12 22:20:13', 1, 'Admitted from home — daughter escort. Moderate dementia, MMSE 14/30. Fall x2 in prior 6 months.'),
    (81, 14, 50, NULL, 1, 'CARE_PLAN_INIT',  '2026-01-13 10:00:00', 1, 'Interdisciplinary care plan meeting. Goals: fall prevention, orientation, behavioral management.'),
    (82, 14, 50, NULL, 1, 'INCIDENT',        '2026-01-28 07:45:00', 1, 'Near-fall during AM transfer. No injury. Bed alarm adjusted. PT notified.'),
    (83, 14, 50, NULL, 1, 'CARE_PLAN_REVIEW','2026-02-12 10:00:00', 1, '30-day review. Morse score stable at 78. Music therapy showing behavioral improvement.'),

-- George Calloway — ep 15
    (84, 15, 51, NULL, 1, 'ARRIVAL',         '2026-01-28 22:20:13', 1, 'SNF transfer day 12 post right hip arthroplasty. Pain 6/10. Full WB with walker tolerated.'),
    (85, 15, 51, NULL, 1, 'PT_SESSION',      '2026-01-30 09:00:00', 1, 'Initial PT assessment. 20 ft ambulation with walker. Hip flexion 65°. Strengthening protocol started.'),
    (86, 15, 51, NULL, 1, 'PT_SESSION',      '2026-02-06 09:00:00', 1, 'Week 2 PT. Ambulation 50 ft. Pain 4/10 post-session. Progressing on schedule.'),
    (87, 15, 51, NULL, 1, 'PT_SESSION',      '2026-02-20 09:00:00', 1, 'Week 4 PT. Ambulation 100 ft with walker. Pain 3/10. Opioid taper initiated.'),

-- Ruth Okonkwo — ep 16
    (88, 16, 52, NULL, 1, 'ARRIVAL',         '2026-02-10 22:20:13', 1, 'Self-referral. COPD GOLD Stage 2. SpO2 94% RA. Inhaler non-adherence at home.'),
    (89, 16, 52, NULL, 1, 'VITALS',          '2026-02-11 08:00:00', 1, 'SpO2 95% RA. HR 78. BP 128/76. Inhalers administered per schedule. Tolerating well.'),
    (90, 16, 52, NULL, 1, 'VITALS',          '2026-02-21 08:00:00', 1, 'SpO2 95% RA. Adherence 100% past 10 days. No exacerbation symptoms.'),

-- Harold Steinberg — ep 17
    (91, 17, 53, NULL, 1, 'ARRIVAL',         '2025-12-28 22:20:13', 1, 'Family placement — advanced Parkinson''s Hoehn & Yahr Stage 3. Dysphagia confirmed. Fall x2/month prior.'),
    (92, 17, 53, NULL, 1, 'INCIDENT',        '2026-02-28 06:15:00', 1, 'Unwitnessed fall beside bed. Alert x2. 2cm forearm laceration. X-ray ordered. PT suspended.'),
    (93, 17, 53, NULL, 1, 'CARE_PLAN_REVIEW','2026-01-28 10:00:00', 1, '30-day review. Zero aspirations. Fall count: 1 (down from 2/month). Mobility protocol adjusted.'),
    (94, 17, 53, NULL, 1, 'MEDICATION',      '2026-02-01 07:00:00', 1, 'Carbidopa/levodopa 7:00 AM — within 30-min window. On-period good response noted by aide.'),

-- Dorothy Vasquez — ep 18
    (95, 18, 54, NULL, 1, 'ARRIVAL',         '2026-02-19 22:20:13', 1, 'Cardiology referral. CHF NYHA Class II. T2DM HbA1c 9.2%. Baseline weight 140.2 lbs.'),
    (96, 18, 54, NULL, 1, 'WEIGHT_CHECK',    '2026-02-22 07:30:00', 1, 'Weight 141.0 lbs (+0.8 lbs). Within threshold. FBG 178. Insulin administered.'),
    (97, 18, 54, NULL, 1, 'WEIGHT_CHECK',    '2026-02-27 07:30:00', 1, 'Weight 143.0 lbs (+2.8 lbs over baseline). Provider notified. Furosemide dose reviewed.'),
    (98, 18, 54, NULL, 1, 'INCIDENT',        '2026-02-20 14:00:00', 1, 'Medication error — furosemide 40mg given instead of 20mg. No adverse effects. Pharmacy notified. Protocol update initiated.');

-- =============================================================================
-- OEI_EPISODE_LOCATION  (continuing from id=12)
-- Wing A rooms: loc_id 13-14 (Wing A, Wing B approximate mapping to existing locations)
-- Use location_code for display since location_id maps to oei_location
-- =============================================================================

INSERT INTO `oei_episode_location`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `location_id`, `location_code`,
     `start_datetime`, `end_datetime`, `user_id`, `note`)
VALUES
    (13, 14, 50, NULL, 1, NULL, 'A-101', '2026-01-12 22:20:13', NULL, 1, 'Wing A Room 101 — memory care'),
    (14, 15, 51, NULL, 1, NULL, 'A-104', '2026-01-28 22:20:13', NULL, 1, 'Wing A Room 104 — rehab'),
    (15, 16, 52, NULL, 1, NULL, 'A-108', '2026-02-10 22:20:13', NULL, 1, 'Wing A Room 108 — standard AL'),
    (16, 17, 53, NULL, 1, NULL, 'B-201', '2025-12-28 22:20:13', NULL, 1, 'Wing B Room 201 — high acuity'),
    (17, 18, 54, NULL, 1, NULL, 'B-205', '2026-02-19 22:20:13', NULL, 1, 'Wing B Room 205 — cardiac monitoring');

-- =============================================================================
-- OEI_TASK  (continuing from id=66)
-- Scheduled rounds, care plan reviews, medication checks for AL residents
-- =============================================================================

INSERT INTO `oei_task`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`,
     `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`,
     `created_by_user_id`, `created_datetime`)
VALUES
-- Eleanor Hartwell — ep 14
    (67, 14, 50, NULL, 1, 'CARE_PLAN_REVIEW',  '2026-03-12 10:00:00', NULL, 1, 'OPEN',
     '{"note":"60-day care plan review — fall risk, behavioral, music therapy outcomes"}', 1, '2026-01-12 23:00:00'),
    (68, 14, 50, NULL, 1, 'FALL_RISK_REASSESS', '2026-03-13 09:00:00', NULL, 1, 'OPEN',
     '{"tool":"Morse","note":"30-day reassessment — re-score and update care plan if tier changes"}', 1, '2026-02-12 10:00:00'),
    (69, 14, 50, NULL, 1, 'ADL_ROUND',          TIMESTAMPADD(HOUR, 2, NOW()), NULL, 1, 'OPEN',
     '{"note":"Evening ADL round — bathing assist and bed alarm check"}', 1, '2026-02-28 06:00:00'),

-- George Calloway — ep 15
    (70, 15, 51, NULL, 1, 'PT_SESSION',          '2026-03-01 09:00:00', NULL, 1, 'OPEN',
     '{"note":"Week 5 PT — target 150 ft ambulation, stair negotiation assessment"}', 1, '2026-01-28 23:00:00'),
    (71, 15, 51, NULL, 1, 'PAIN_ASSESSMENT',     TIMESTAMPADD(HOUR, 1, NOW()), NULL, 1, 'OPEN',
     '{"note":"Post-PT pain score — NRS target 3 or below; document opioid taper progress"}', 1, '2026-02-28 06:00:00'),
    (72, 15, 51, NULL, 1, 'CARE_PLAN_REVIEW',   '2026-03-28 10:00:00', NULL, 1, 'OPEN',
     '{"note":"60-day review — ambulation goal assessment, discharge planning discussion"}', 1, '2026-01-28 23:00:00'),

-- Ruth Okonkwo — ep 16
    (73, 16, 52, NULL, 1, 'VITALS_CHECK',        TIMESTAMPADD(HOUR, 1, NOW()), NULL, 1, 'OPEN',
     '{"note":"Morning vitals — SpO2 target above 92% RA; document inhaler adherence"}', 1, '2026-02-10 22:45:00'),
    (74, 16, 52, NULL, 1, 'PULM_REASSESSMENT',  '2026-03-10 10:00:00', NULL, 1, 'OPEN',
     '{"note":"30-day pulmonary reassessment — spirometry if available, adjust O2 threshold"}', 1, '2026-02-10 22:45:00'),

-- Harold Steinberg — ep 17
    (75, 17, 53, NULL, 1, 'POST_FALL_FOLLOWUP',  TIMESTAMPADD(HOUR, 1, NOW()), NULL, 1, 'OPEN',
     '{"note":"Post-fall neuro check q1h x4 — clear before resuming ambulation"}', 1, '2026-02-28 06:30:00'),
    (76, 17, 53, NULL, 1, 'XRAY_FOLLOWUP',       TIMESTAMPADD(HOUR, 3, NOW()), NULL, 1, 'OPEN',
     '{"note":"Review X-ray results — right forearm and hip; PT clearance for ambulation"}', 1, '2026-02-28 06:30:00'),
    (77, 17, 53, NULL, 1, 'MEDICATION',           TIMESTAMPADD(MINUTE, 45, NOW()), NULL, 1, 'OPEN',
     '{"drug":"Carbidopa/Levodopa","note":"Administer within 30-min window of scheduled time"}', 1, '2026-02-28 06:00:00'),
    (78, 17, 53, NULL, 1, 'CARE_PLAN_REVIEW',    '2026-03-28 10:00:00', NULL, 1, 'OPEN',
     '{"note":"90-day review — fall protocol, aspiration events, mobility reassessment"}', 1, '2025-12-28 23:30:00'),

-- Dorothy Vasquez — ep 18
    (79, 18, 54, NULL, 1, 'WEIGHT_CHECK',         TIMESTAMPADD(HOUR, 1, NOW()), NULL, 1, 'OPEN',
     '{"note":"Daily weight — alert if above 142.2 lbs (2 lb threshold). Document FBG."}', 1, '2026-02-19 23:00:00'),
    (80, 18, 54, NULL, 1, 'MEDICATION',            TIMESTAMPADD(MINUTE, 30, NOW()), NULL, 1, 'OPEN',
     '{"drug":"Furosemide 20mg","note":"Verify correct dose after yesterday medication error — double-check MAR"}', 1, '2026-02-28 06:00:00'),
    (81, 18, 54, NULL, 1, 'CARE_PLAN_REVIEW',     '2026-03-19 10:00:00', NULL, 1, 'OPEN',
     '{"note":"30-day review — weight trend, glycemic control, fluid restriction adherence"}', 1, '2026-02-19 23:00:00');

-- =============================================================================
-- OEI_MAR_ORDER  (continuing from id=31)
-- Long-term standing medications for each AL resident
-- =============================================================================

INSERT INTO `oei_mar_order`
    (`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`,
     `frequency`, `is_prn`, `status`, `ordered_datetime`, `discontinued_datetime`,
     `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`, `instructions`,
     `created_datetime`, `updated_datetime`)
VALUES
-- Eleanor Hartwell — ep 14 (memory care)
    (32, 14, 50, 1, 'Donepezil',          '10',   'mg',    'PO', 'QHS',  0, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'Administer at bedtime with water. Monitor for GI side effects.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),
    (33, 14, 50, 1, 'Memantine',          '10',   'mg',    'PO', 'BID',  0, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'Give with or without food. Morning and evening doses.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),
    (34, 14, 50, 1, 'Quetiapine',         '12.5', 'mg',    'PO', 'QHS',  1, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'PRN agitation only — not to exceed 25mg/24h. Document behavior before and after.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),

-- George Calloway — ep 15 (post-hip)
    (35, 15, 51, 1, 'Acetaminophen',      '650',  'mg',    'PO', 'Q6H',  0, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'Scheduled pain management. Do not exceed 4g/24h.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),
    (36, 15, 51, 1, 'Oxycodone',          '5',    'mg',    'PO', 'Q6H',  1, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'PRN breakthrough pain — use only if acetaminophen insufficient. Taper by week 6.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),
    (37, 15, 51, 1, 'Enoxaparin',         '40',   'mg',    'SQ', 'QD',   0, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'DVT prophylaxis post-arthroplasty. Rotate injection sites. Check PLT weekly.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),

-- Ruth Okonkwo — ep 16 (COPD)
    (38, 16, 52, 1, 'Tiotropium',         '18',   'mcg',   'Inh','QD',   0, 'ACTIVE', '2026-02-11 08:00:00', NULL, 1, NULL, NULL, 'HandiHaler device — 1 capsule inhaled once daily. Morning administration.', '2026-02-11 08:00:00', '2026-02-11 08:00:00'),
    (39, 16, 52, 1, 'Albuterol',          '2.5',  'mg',    'Neb','PRN',  1, 'ACTIVE', '2026-02-11 08:00:00', NULL, 1, NULL, NULL, 'PRN rescue — administer for SpO2 below 90% or acute dyspnea. Max Q4H.', '2026-02-11 08:00:00', '2026-02-11 08:00:00'),

-- Harold Steinberg — ep 17 (Parkinson's)
    (40, 17, 53, 1, 'Carbidopa/Levodopa', '25/100','mg',   'PO', 'TID',  0, 'ACTIVE', '2025-12-29 07:00:00', NULL, 1, NULL, NULL, 'Administer 7AM, 12PM, 5PM — within 30-min window. Give 30 min before meals. Track on-time administration.', '2025-12-29 07:00:00', '2025-12-29 07:00:00'),
    (41, 17, 53, 1, 'Rivastigmine patch', '4.6',  'mg/24h','TD','QD',   0, 'ACTIVE', '2025-12-29 07:00:00', NULL, 1, NULL, NULL, 'Apply to upper arm or chest, rotate sites daily. Remove old patch before applying new.', '2025-12-29 07:00:00', '2025-12-29 07:00:00'),

-- Dorothy Vasquez — ep 18 (CHF/T2DM)
    (42, 18, 54, 1, 'Furosemide',         '20',   'mg',    'PO', 'QAM',  0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'VERIFY dose carefully — 20mg (not 40mg). Administer morning. Monitor daily weight.', '2026-02-20 08:00:00', '2026-02-20 08:00:00'),
    (43, 18, 54, 1, 'Lisinopril',         '10',   'mg',    'PO', 'QD',   0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Hold if SBP below 100. Monitor potassium weekly.', '2026-02-20 08:00:00', '2026-02-20 08:00:00'),
    (44, 18, 54, 1, 'Insulin glargine',   '18',   'units', 'SQ', 'QHS',  0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Administer at bedtime. Sliding scale: FBG 141-180 add 2u, 181-240 add 4u, over 240 notify provider.', '2026-02-20 08:00:00', '2026-02-20 08:00:00'),
    (45, 18, 54, 1, 'Metformin',          '500',  'mg',    'PO', 'BID',  0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Give with meals to reduce GI side effects. Hold if creatinine above 1.4.', '2026-02-20 08:00:00', '2026-02-20 08:00:00');

-- =============================================================================
-- OEI_MAR_ADMINISTRATION  (continuing from id=21)
-- Recent administration records for AL meds
-- =============================================================================

INSERT INTO `oei_mar_administration`
    (`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`,
     `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`,
     `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `note`,
     `created_datetime`, `updated_datetime`)
VALUES
-- Donepezil for Eleanor (mar 32)
    (22, 32, 14, 50, 1, '2026-02-27 21:00:00', '2026-02-27 21:05:00', 'GIVEN', '10', 'mg', 'PO', NULL, NULL, NULL, 1, 'Administered with evening water. Resident cooperative.', '2026-02-27 21:05:00', '2026-02-27 21:05:00'),
    (23, 32, 14, 50, 1, '2026-02-28 21:00:00', NULL, 'SCHEDULED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),

-- Carbidopa/Levodopa for Harold (mar 40) — three doses today
    (24, 40, 17, 53, 1, '2026-02-28 07:00:00', '2026-02-28 07:12:00', 'GIVEN', '25/100', 'mg', 'PO', NULL, NULL, NULL, 1, 'Within 30-min window. Resident in off-period pre-dose; good response observed 45 min post.', '2026-02-28 07:12:00', '2026-02-28 07:12:00'),
    (25, 40, 17, 53, 1, '2026-02-28 12:00:00', NULL, 'SCHEDULED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
    (26, 40, 17, 53, 1, '2026-02-28 17:00:00', NULL, 'SCHEDULED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),

-- Furosemide for Dorothy (mar 42) — yesterday given, today scheduled (note on dose)
    (27, 42, 18, 54, 1, '2026-02-27 08:00:00', '2026-02-27 08:05:00', 'GIVEN', '20', 'mg', 'PO', NULL, NULL, NULL, 1, 'Correct 20mg dose verified by barcode. Weight pre-dose 143.0 lbs.', '2026-02-27 08:05:00', '2026-02-27 08:05:00'),
    (28, 42, 18, 54, 1, '2026-02-28 08:00:00', NULL, 'SCHEDULED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Verify 20mg dose — double check after yesterday med error', '2026-02-28 06:00:00', '2026-02-28 06:00:00'),

-- Insulin glargine for Dorothy (mar 44) — last night given
    (29, 44, 18, 54, 1, '2026-02-27 21:00:00', '2026-02-27 21:08:00', 'GIVEN', '18', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, 'FBG 162 at HS. Base dose 18u. No sliding scale addition needed.', '2026-02-27 21:08:00', '2026-02-27 21:08:00'),
    (30, 44, 18, 54, 1, '2026-02-28 21:00:00', NULL, 'SCHEDULED', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),

-- Tiotropium for Ruth (mar 38)
    (31, 38, 16, 52, 1, '2026-02-28 08:00:00', '2026-02-28 08:10:00', 'GIVEN', '18', 'mcg', 'Inh', NULL, NULL, NULL, 1, 'HandiHaler administered. Technique confirmed. SpO2 95% post-dose.', '2026-02-28 08:10:00', '2026-02-28 08:10:00');

-- =============================================================================
-- OEI_EREFERRAL  (continuing from id=1)
-- AL-specific referrals: neurology consult, specialist transfer
-- =============================================================================

INSERT INTO `oei_ereferral`
    (`episode_id`, `pid`, `eid`, `facility_id`, `referral_type`, `status`, `priority`,
     `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`,
     `destination_address`, `reason_for_referral`, `clinical_summary`,
     `services_requested`, `medications_summary`, `followup_instructions`,
     `sent_datetime`, `sent_by_user_id`, `send_method`,
     `response_datetime`, `response_by_name`, `response_notes`,
     `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES
-- Harold Steinberg — neurology follow-up after fall
    (17, 53, NULL, 1, 'DISCHARGE', 'SENT', 'URGENT',
     NULL, 'Springfield Neurology Associates', '(555) 820-4401', '(555) 820-4400',
     '900 Medical Center Dr, Springfield IL 62701',
     'Advanced Parkinson''s disease — unwitnessed fall with laceration this morning. Requesting urgent neurology review for medication adjustment and fall prevention.',
     'Harold Steinberg, 93M, Parkinson''s Hoehn & Yahr Stage 3. Carbidopa/levodopa TID. Fall this morning beside bed — 2cm forearm laceration, hip bruising, X-ray pending. Fall count 1 this month vs baseline 2/month.',
     'Urgent medication review: consider COMT inhibitor addition or dose timing adjustment. Gait and balance reassessment. PT recommendations.',
     'Carbidopa/Levodopa 25/100mg TID (7AM, 12PM, 5PM); Rivastigmine patch 4.6mg/24h daily',
     'Follow up within 5 business days. Return to AL with updated medication plan.',
     '2026-02-28 09:00:00', 1, 'FAX',
     NULL, NULL, NULL,
     1, '2026-02-28 08:30:00', '2026-02-28 09:00:00'),

-- Dorothy Vasquez — cardiology follow-up for CHF weight gain
    (18, 54, NULL, 1, 'DISCHARGE', 'DRAFT', 'ROUTINE',
     NULL, 'Springfield Heart Center', '(555) 730-9901', '(555) 730-9900',
     '1200 Cardiology Blvd, Springfield IL 62701',
     'CHF NYHA Class II — weight gain 2.8 lbs over 5 days. Requesting cardiology review of diuretic regimen.',
     'Dorothy Vasquez, 79F, CHF NYHA Class II, T2DM HbA1c 9.2%. Daily weights: baseline 140.2 lbs, today 143.0 lbs (+2.8 lbs). Furosemide 20mg QAM. Note: medication error last week (40mg administered once, no adverse effects). FBG trending 140-180 on current insulin regimen.',
     'Echocardiogram if not done within 6 months. Review diuretic dose. BMP with BNP.',
     'Furosemide 20mg QAM; Lisinopril 10mg QD; Insulin glargine 18u QHS; Metformin 500mg BID',
     'Cardiology note to AL facility within 3 business days. Repeat BMP in 1 week.',
     NULL, NULL, 'MANUAL',
     NULL, NULL, NULL,
     1, '2026-02-28 10:00:00', '2026-02-28 10:00:00');

-- =============================================================================
-- OEI_EPISODE_DISPOSITION  (continuing from id=1)
-- AL dispositions are typically planned transfers or hospital admissions
-- =============================================================================

INSERT INTO `oei_episode_disposition`
    (`episode_id`, `pid`, `eid`, `facility_id`, `disposition_code`, `destination`,
     `decision_datetime`, `depart_datetime`, `admit_flag`, `notes`,
     `updated_by_user_id`, `updated_datetime`)
VALUES
-- Harold Steinberg — pending hospital evaluation after fall
    (17, 53, NULL, 1, 'HOSPITAL_EVAL', 'Springfield General ER',
     '2026-02-28 07:00:00', NULL, 0,
     'Pending decision — X-ray results awaited. If hip fracture confirmed, transfer to ED. PT and provider to reassess at noon.',
     1, '2026-02-28 07:15:00'),

-- George Calloway — planned discharge to home at 60-day mark
    (15, 51, NULL, 1, 'HOME_DISCHARGE', 'Home with outpatient PT',
     '2026-02-20 10:00:00', NULL, 0,
     'Planned discharge at 60-day mark per care plan goal. PT to confirm ambulation independence. Family notified to arrange home assessment.',
     1, '2026-02-20 10:00:00');

-- =============================================================================
-- VERIFICATION
-- =============================================================================

SELECT 'form_encounter (AL)'         AS section, COUNT(*) AS cnt FROM form_encounter       WHERE pid IN (50,51,52,53,54) AND reason = 'AL Admission'
UNION ALL
SELECT 'care_teams (AL)',            COUNT(*) FROM care_teams         WHERE pid IN (50,51,52,53,54)
UNION ALL
SELECT 'care_team_member (AL)',      COUNT(*) FROM care_team_member   WHERE care_team_id IN (SELECT id FROM care_teams WHERE pid IN (50,51,52,53,54))
UNION ALL
SELECT 'form_care_plan (AL)',        COUNT(*) FROM form_care_plan     WHERE pid IN (50,51,52,53,54)
UNION ALL
SELECT 'status_history (AL eps)',    COUNT(*) FROM oei_episode_status_history WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'episode_event (AL eps)',     COUNT(*) FROM oei_episode_event          WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'episode_location (AL eps)',  COUNT(*) FROM oei_episode_location       WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'task (AL eps)',              COUNT(*) FROM oei_task                   WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'mar_order (AL eps)',         COUNT(*) FROM oei_mar_order              WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'mar_admin (AL eps)',         COUNT(*) FROM oei_mar_administration     WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'ereferral (AL eps)',         COUNT(*) FROM oei_ereferral              WHERE episode_id IN (14,15,16,17,18)
UNION ALL
SELECT 'disposition (AL eps)',       COUNT(*) FROM oei_episode_disposition    WHERE episode_id IN (14,15,16,17,18);

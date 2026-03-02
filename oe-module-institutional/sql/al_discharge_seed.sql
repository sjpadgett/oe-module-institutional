-- =============================================================================
-- AL Discharge / Transfer Planning Demo Seed  v0.13.0
-- oe-module-institutional
-- =============================================================================
-- Populates oei_episode_disposition with discharge planning data for all 5
-- AL demo residents, demonstrating the full range of AL disposition codes.
--
-- Residents (episodes 14-18, pids 50-54):
--   Eleanor Hartwell  (ep 14) — SNF_TRANSFER planned, awaiting family decision
--   George Calloway   (ep 15) — HOME_DISCHARGE planned at 60-day mark (day 31 now)
--   Ruth Okonkwo      (ep 16) — No disposition plan yet (recently admitted)
--   Harold Steinberg  (ep 17) — HOSPITAL_EVAL (fall, x-ray pending, may return)
--   Dorothy Vasquez   (ep 18) — HOSPITAL_EVAL (CHF decompensation, pending)
--
-- Run AFTER: institutional-demo-seed.sql, demo_seed_al.sql, al_phase2.sql
-- Safe to re-run (DELETE + INSERT for AL episodes only)
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

SET @FAC   := 1;
SET @STAFF := 1;

-- Resolve episode IDs from the demo patients
SET @EP1 := (SELECT id FROM oei_episode WHERE pid = 50 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP2 := (SELECT id FROM oei_episode WHERE pid = 51 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP4 := (SELECT id FROM oei_episode WHERE pid = 53 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP5 := (SELECT id FROM oei_episode WHERE pid = 54 AND type = 'AL' ORDER BY id DESC LIMIT 1);

-- =============================================================================
-- IDEMPOTENT CLEANUP — remove only AL discharge planning rows for demo pids
-- =============================================================================

DELETE FROM oei_episode_disposition
WHERE episode_id IN (@EP1, @EP2, @EP4, @EP5);

DELETE FROM oei_episode_event
WHERE episode_id IN (@EP1, @EP2, @EP4, @EP5)
  AND event_type IN ('DISCHARGE_PLANNED', 'DEPART');

-- =============================================================================
-- Eleanor Hartwell — SNF Transfer (Memory Care)
-- Tier 3 / HIGH risk. Family considering Springfield Memory Care SNF.
-- Plan exists; departure not yet confirmed — episode stays ACTIVE.
-- =============================================================================

INSERT INTO oei_episode_disposition
    (episode_id, pid, eid, facility_id, disposition_code, destination,
     decision_datetime, depart_datetime, admit_flag, notes,
     updated_by_user_id, updated_datetime)
VALUES
    (@EP1, 50, NULL, @FAC,
     'SNF_TRANSFER',
     'Springfield Memory Care & Rehabilitation',
     DATE_SUB(NOW(), INTERVAL 5 DAY),
     NULL,   -- not yet confirmed
     1,      -- admit_flag: SNF admission expected
     'Family meeting held 5 days ago. Daughter Karen (POA) reviewing contract with Springfield Memory Care. Awaiting bed availability (est. 3–7 days). Neurologist discharge summary requested. All financial paperwork submitted to SNF.',
     @STAFF,
     DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO oei_episode_event
    (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
VALUES
    (@EP1, 50, NULL, @FAC,
     'DISCHARGE_PLANNED',
     DATE_SUB(NOW(), INTERVAL 5 DAY),
     @STAFF,
     'SNF_TRANSFER → Springfield Memory Care & Rehabilitation');

-- =============================================================================
-- George Calloway — Home Discharge (Post-hip rehab, PT-cleared)
-- Tier 2 / MODERATE. Planned at 60-day mark — currently day 31.
-- Plan exists but departure date is ~4 weeks away.
-- =============================================================================

INSERT INTO oei_episode_disposition
    (episode_id, pid, eid, facility_id, disposition_code, destination,
     decision_datetime, depart_datetime, admit_flag, notes,
     updated_by_user_id, updated_datetime)
VALUES
    (@EP2, 51, NULL, @FAC,
     'HOME_DISCHARGE',
     'Home — 45 Oak Street, Springfield IL (wife present)',
     DATE_SUB(NOW(), INTERVAL 7 DAY),
     NULL,   -- departure date approx 4 weeks away
     0,
     'PT cleared for home ambulation with walker (20 ft, supervised). Discharge target: day 60 from admission. Home assessment scheduled by OT next week. Wife confirmed able to provide support. Outpatient PT arranged at Springfield Rehab 3×/week post-discharge. No home-health nursing required. Pain well-controlled on oral NSAID.',
     @STAFF,
     DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO oei_episode_event
    (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
VALUES
    (@EP2, 51, NULL, @FAC,
     'DISCHARGE_PLANNED',
     DATE_SUB(NOW(), INTERVAL 7 DAY),
     @STAFF,
     'HOME_DISCHARGE → Home with outpatient PT');

-- =============================================================================
-- Ruth Okonkwo (ep 16, pid 52) — No disposition plan
-- Tier 1 / LOW risk, COPD, admitted 18 days ago. No discharge planned.
-- Nothing to insert — clean slate demonstrates the "no plan" state.
-- =============================================================================

-- =============================================================================
-- Harold Steinberg — Hospital Evaluation (Emergency, fall this morning)
-- Tier 3 / HIGH risk, Parkinson's. X-ray results pending.
-- HOSPITAL_EVAL = pending transfer; may return if hip is intact.
-- =============================================================================

INSERT INTO oei_episode_disposition
    (episode_id, pid, eid, facility_id, disposition_code, destination,
     decision_datetime, depart_datetime, admit_flag, notes,
     updated_by_user_id, updated_datetime)
VALUES
    (@EP4, 53, NULL, @FAC,
     'HOSPITAL_EVAL',
     'Springfield General ER',
     DATE_SUB(NOW(), INTERVAL 5 HOUR),
     NULL,   -- departure not yet confirmed
     0,      -- admit_flag 0: eval only, may return
     'Unwitnessed fall this AM beside bed. 2cm forearm lac (dressed), bruising right hip. Neuro intact, A&Ox2. X-ray hip/pelvis ordered at SGMC. Physician Dr. Chen notified 0715. If hip fracture confirmed: admit orthopedics, episode will be closed as HOSPITAL_TRANSFER. If negative: resident returns by PM — keep episode ACTIVE. PT hold pending results. Family (son David) contacted 0730.',
     @STAFF,
     DATE_SUB(NOW(), INTERVAL 5 HOUR));

INSERT INTO oei_episode_event
    (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
VALUES
    (@EP4, 53, NULL, @FAC,
     'DISCHARGE_PLANNED',
     DATE_SUB(NOW(), INTERVAL 5 HOUR),
     @STAFF,
     'HOSPITAL_EVAL → Springfield General ER (fall, hip x-ray pending)');

-- =============================================================================
-- Dorothy Vasquez — Hospital Evaluation (CHF decompensation)
-- Tier 2 / MODERATE, CHF + T2DM. Weight +2.8 lbs over 5 days.
-- Cardiology referral escalated to same-day ED evaluation.
-- =============================================================================

INSERT INTO oei_episode_disposition
    (episode_id, pid, eid, facility_id, disposition_code, destination,
     decision_datetime, depart_datetime, admit_flag, notes,
     updated_by_user_id, updated_datetime)
VALUES
    (@EP5, 54, NULL, @FAC,
     'HOSPITAL_EVAL',
     'Springfield General ER — Cardiology on-call',
     DATE_SUB(NOW(), INTERVAL 3 HOUR),
     NULL,
     0,
     'CHF decompensation concern: weight gain 2.8 lbs in 5 days despite furosemide 20mg. Legs: mild pitting edema bilateral. SpO2 93% on RA (baseline 96%). Cardiology on-call consulted, recommends same-day eval. BMP + BNP ordered stat. If BNP >500 or significant desaturation: admit cardiology. Current vitals: BP 148/92, HR 88, RR 18, SpO2 93%. Resident cooperative. Daughter Maria notified.',
     @STAFF,
     DATE_SUB(NOW(), INTERVAL 3 HOUR));

INSERT INTO oei_episode_event
    (episode_id, pid, eid, facility_id, event_type, event_datetime, user_id, note)
VALUES
    (@EP5, 54, NULL, @FAC,
     'DISCHARGE_PLANNED',
     DATE_SUB(NOW(), INTERVAL 3 HOUR),
     @STAFF,
     'HOSPITAL_EVAL → Springfield General ER (CHF decompensation)');

-- =============================================================================
-- VERIFICATION
-- =============================================================================

SELECT 'Discharge plans by code' AS section,
       d.disposition_code,
       CONCAT(pd.fname, ' ', pd.lname) AS resident,
       CASE WHEN d.depart_datetime IS NULL THEN 'Planned (pending)' ELSE 'Confirmed' END AS stage
FROM   oei_episode_disposition d
INNER  JOIN oei_episode e  ON e.id  = d.episode_id
INNER  JOIN patient_data pd ON pd.pid = e.pid
WHERE  e.facility_id = @FAC AND e.type = 'AL'
ORDER  BY e.id;

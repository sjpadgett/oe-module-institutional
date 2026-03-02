-- =============================================================================
-- AL Activity & Engagement Log  v0.14.0
-- oe-module-institutional
-- =============================================================================
-- New table: oei_activity_log
--
-- Regulatory context:
--   CMS-certified AL facilities must document resident participation in
--   structured activities. State licensing typically requires:
--     - Evidence of individualized programming aligned with care plan goals
--     - Participation records available for survey review
--     - Documentation of refusals (with reason) and alternatives offered
--
-- Design:
--   One row per activity session. Multi-resident attendance stored as JSON
--   so a group bingo session is one row, not 12 rows with a JOIN table.
--   Per-resident participation level (FULL/PARTIAL/REFUSED/ABSENT) is stored
--   inside the attendance_json keyed by episode_id.
--
--   attendance_json shape:
--   {
--     "14": {"level": "FULL",    "note": ""},
--     "15": {"level": "REFUSED", "note": "Preferred to rest"},
--     "17": {"level": "PARTIAL", "note": "Left after 10 min — fatigue"}
--   }
-- =============================================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+00:00";
/*!40101 SET NAMES utf8mb4 */;

-- =============================================================================
-- TABLE
-- =============================================================================

CREATE TABLE IF NOT EXISTS `oei_activity_log`
(
    `id`                bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`       bigint(20) UNSIGNED NOT NULL,
    `activity_date`     date                NOT NULL,
    `activity_type`     varchar(40)         NOT NULL
                        COMMENT 'SOCIAL_GROUP|MUSIC|EXERCISE|COGNITIVE|OUTDOOR|DEVOTIONAL|CRAFT|INDIVIDUAL_VISIT|DINING_SOCIAL|THERAPY_PT|THERAPY_OT|THERAPY_ST|OTHER',
    `activity_name`     varchar(120)        NOT NULL
                        COMMENT 'Specific name, e.g. "Morning Stretch", "Bingo", "Guitar Singalong"',
    `start_time`        time                NOT NULL,
    `duration_minutes`  smallint(5) UNSIGNED NOT NULL DEFAULT 30,
    `location`          varchar(60)                  DEFAULT NULL
                        COMMENT 'e.g. "Community Room", "Courtyard", "Room 202"',
    `led_by_user_id`    bigint(20) UNSIGNED          DEFAULT NULL
                        COMMENT 'FK → users.id (activity coordinator / aide)',
    `led_by_name`       varchar(80)                  DEFAULT NULL
                        COMMENT 'Denormalised name for display when user is no longer active',
    `attendance_json`   json                NOT NULL DEFAULT (JSON_OBJECT())
                        COMMENT 'episode_id → {level, note} participation map',
    `attendance_count`  tinyint(3) UNSIGNED NOT NULL DEFAULT 0
                        COMMENT 'Cached count of FULL+PARTIAL attendees for fast board display',
    `notes`             text                         DEFAULT NULL
                        COMMENT 'Session-level notes (themes, outcomes, observations)',
    `created_datetime`  datetime            NOT NULL,
    `updated_datetime`  datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_activity_facility_date`   (`facility_id`, `activity_date`),
    KEY `idx_oei_activity_type`            (`facility_id`, `activity_type`, `activity_date`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'AL activity & engagement session log; one row per session, multi-resident attendance in JSON';

-- =============================================================================
-- DEMO SEED
-- =============================================================================
-- Residents (episodes 14-18, pids 50-54):
--   Eleanor Hartwell  (ep 14) — memory care, attends group music/crafts
--   George Calloway   (ep 15) — post-hip rehab, attends PT exercise + dining social
--   Ruth Okonkwo      (ep 16) — COPD, cautious with exertion, attends devotional
--   Harold Steinberg  (ep 17) — Parkinson's, music therapy is care plan goal
--   Dorothy Vasquez   (ep 18) — CHF, enjoys bingo and social dining
-- =============================================================================

SET @FAC   := 1;
SET @STAFF := 1;

-- Resolve episode IDs
SET @EP1 := (SELECT id FROM oei_episode WHERE pid = 50 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP2 := (SELECT id FROM oei_episode WHERE pid = 51 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP3 := (SELECT id FROM oei_episode WHERE pid = 52 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP4 := (SELECT id FROM oei_episode WHERE pid = 53 AND type = 'AL' ORDER BY id DESC LIMIT 1);
SET @EP5 := (SELECT id FROM oei_episode WHERE pid = 54 AND type = 'AL' ORDER BY id DESC LIMIT 1);

-- Idempotent cleanup
DELETE FROM oei_activity_log WHERE facility_id = @FAC;

-- ── TODAY ────────────────────────────────────────────────────────────────────

-- Morning stretch (Harold PARTIAL — fatigue from early fall; Dorothy FULL)
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, CURDATE(), 'EXERCISE', 'Morning Stretch & Balance',
    '09:00:00', 30, 'Community Room', @STAFF, 'J. Rivera CNA',
    JSON_OBJECT(
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','FULL',    'note','Good effort, 15 reps each exercise'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Modified seated version, SpO2 maintained'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','Joined late after fall incident; left after 10 min'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','FULL',    'note','Excellent participation')
    ),
    4,
    'Balance focus today — elastic bands. Harold arrived late due to fall this morning. Ruth used seated modifications to maintain SpO2 > 93%.',
    NOW(), NOW()
);

-- Memory café / cognitive stimulation (Eleanor — her primary care plan activity)
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, CURDATE(), 'COGNITIVE', 'Memory Café — Photo Reminiscence',
    '10:00:00', 45, 'Sunroom', @STAFF, 'M. Torres MSW',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Identified 4/6 historical photos; named several family members'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','FULL',    'note','Engaged with music-era photos; spontaneous storytelling'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','Tired at 30 min; left voluntarily')
    ),
    3,
    'Photo deck from 1960s-70s. Eleanor had a notably good session — best engagement this week. Harold animated when 1950s big band photos shown.',
    NOW(), NOW()
);

-- Lunchtime dining social (all residents except Harold who had hospital eval)
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, CURDATE(), 'DINING_SOCIAL', 'Structured Dining — Resident Lunch',
    '12:00:00', 60, 'Dining Room', @STAFF, 'K. Patel CNA',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Thickened liquids — nectar. Good intake. Ate with encouragement.'),
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','FULL',    'note','Normal diet. Good appetite.'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Low sodium meal; took medications at table'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','ABSENT',  'note','At Springfield General ER — hospital evaluation'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','FULL',    'note','2g sodium cardiac diet. Fluid logged: 300 mL at lunch.')
    ),
    4,
    'Harold absent — hospital evaluation for morning fall. Dorothy fluid intake logged per CHF protocol: 300 mL lunch. Eleanor ate 75% of meal.',
    NOW(), NOW()
);

-- ── YESTERDAY ────────────────────────────────────────────────────────────────

-- Guitar singalong (Harold's care plan music therapy goal)
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'MUSIC', 'Guitar Singalong — Classic Standards',
    '14:00:00', 45, 'Community Room', @STAFF, 'J. Rivera CNA',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Sang along to several songs; calm and happy throughout'),
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','Joined for 20 min between PT sessions'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Requested "Amazing Grace" — led chorus'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','FULL',    'note','Best session this month — identified and named every song; smiled throughout'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','FULL',    'note','Clapped along; noted song from her wedding')
    ),
    5,
    'Exceptional session. Harold identified every song from 1940s-60s — notable improvement in engagement vs. last week. Care plan note: music therapy goal demonstrably achieved this session.',
    DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)
);

-- PT-led exercise (George's rehab core activity)
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'THERAPY_PT', 'Post-Surgical Gait Training',
    '10:00:00', 40, 'Rehab Gym', @STAFF, 'D. Chen DPT',
    JSON_OBJECT(
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','FULL', 'note','30 ft with walker unassisted — new record. PT cleared for outdoor walk next session.')
    ),
    1,
    'George achieved 30 ft independent ambulation with walker. Discharge readiness improving ahead of schedule.',
    DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)
);

-- Afternoon bingo
INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 'SOCIAL_GROUP', 'Afternoon Bingo',
    '15:00:00', 60, 'Community Room', @STAFF, 'J. Rivera CNA',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Won 2 games; bright and engaged'),
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','REFUSED', 'note','Preferred to rest after PT session'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Participated well'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','Tired after 30 min; returned to room'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','FULL',    'note','Won one game; socialised well with Eleanor')
    ),
    4,
    NULL,
    DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)
);

-- ── 2 DAYS AGO ───────────────────────────────────────────────────────────────

INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'DEVOTIONAL', 'Nondenominational Chapel Service',
    '09:30:00', 30, 'Chapel', @STAFF, 'Chaplain B. Owens',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Attentive; requested prayer for family'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Led opening reading'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','FULL',    'note','Joined for first time; expressed gratitude')
    ),
    3, NULL,
    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
),
(
    @FAC, DATE_SUB(CURDATE(), INTERVAL 2 DAY), 'CRAFT', 'Watercolour Painting — Spring Themes',
    '14:00:00', 60, 'Art Room', @STAFF, 'M. Torres MSW',
    JSON_OBJECT(
        CAST(@EP1 AS CHAR), JSON_OBJECT('level','FULL',    'note','Painted flowers; product displayed in room with her permission'),
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','FULL',    'note','Enjoyed fine motor activity; reported hands "feel good"'),
        CAST(@EP4 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','Hand tremor limited painting; used thick brush, adapted well')
    ),
    3,
    'Harold adapted well to thick-brush watercolour — OT note: good fine motor challenge within Parkinson''s capability.',
    DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
);

-- ── OUTDOOR / COURTYARD (3 days ago) ─────────────────────────────────────────

INSERT INTO oei_activity_log
    (facility_id, activity_date, activity_type, activity_name,
     start_time, duration_minutes, location, led_by_user_id, led_by_name,
     attendance_json, attendance_count, notes, created_datetime, updated_datetime)
VALUES (
    @FAC, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'OUTDOOR', 'Courtyard Walk & Garden Visit',
    '10:30:00', 40, 'Courtyard Garden', @STAFF, 'K. Patel CNA',
    JSON_OBJECT(
        CAST(@EP2 AS CHAR), JSON_OBJECT('level','FULL',    'note','Completed full loop with walker unassisted — PT milestone'),
        CAST(@EP3 AS CHAR), JSON_OBJECT('level','FULL',    'note','Seated in garden only per COPD precaution; SpO2 96% on return'),
        CAST(@EP5 AS CHAR), JSON_OBJECT('level','PARTIAL', 'note','10 min walk, then seated per fatigue protocol')
    ),
    3,
    'Good weather. Ruth seated per COPD exertion guideline — SpO2 monitored. George completed full courtyard loop — PT goals ahead of schedule.',
    DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)
);

-- =============================================================================
-- VERIFICATION
-- =============================================================================

SELECT 'Activity sessions' AS section, COUNT(*) AS cnt FROM oei_activity_log WHERE facility_id = @FAC
UNION ALL
SELECT 'Today', COUNT(*) FROM oei_activity_log WHERE facility_id = @FAC AND activity_date = CURDATE()
UNION ALL
SELECT 'Yesterday', COUNT(*) FROM oei_activity_log WHERE facility_id = @FAC AND activity_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY);

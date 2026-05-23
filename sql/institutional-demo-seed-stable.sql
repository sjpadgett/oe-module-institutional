-- =============================================================================
-- oe-module-institutional  v0.17.0  —  COMPLETE DEMO SEED
-- =============================================================================
-- Run once on any OpenEMR 7.0+ Easy Dev Docker instance.
-- Section 1: INSERT IGNORE into OpenEMR core tables (safe on existing installs)
-- Section 2: DROP + CREATE + INSERT 33 oei_* module tables
-- =============================================================================

-- =============================================================================
-- SECTION 1 — OpenEMR Core Tables
-- Columns verified against database.sql schema.
-- INSERT IGNORE throughout — never overwrites existing production data.
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
/*!40101 SET NAMES utf8mb4 */;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- facility
-- Columns: id, name, phone, fax, street, city, state, postal_code,
--          country_code, federal_ein, service_location, billing_location,
--          accepts_assignment, pos_code, attn, domain_identifier,
--          facility_npi, facility_taxonomy, tax_id_type, color,
--          primary_business_entity, facility_code, extra_validation,
--          mail_street, mail_city, mail_state, mail_zip, oid
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `facility`
(`id`, `name`, `phone`, `fax`, `street`, `city`, `state`, `postal_code`,
 `country_code`, `federal_ein`, `service_location`, `billing_location`,
 `accepts_assignment`, `pos_code`, `attn`, `domain_identifier`,
 `facility_npi`, `facility_taxonomy`, `tax_id_type`, `color`,
 `primary_business_entity`, `facility_code`, `extra_validation`,
 `mail_street`, `mail_city`, `mail_state`, `mail_zip`, `oid`)
VALUES (1, 'Community Memorial Hospital', '217-555-0001', '217-555-0002',
        '100 Hospital Drive', 'Springfield', 'IL', '62701',
        'US', '12-3456789', 1, 1, 1, 21,
        NULL, NULL, '1234567890', '207Q00000X', 'EI', '#336699',
        1, 'CMH', 1,
        NULL, NULL, NULL, NULL, '2.16.840.1.113883.4.6.1234567891'),
       (2, 'Home Based Care Center', '217-555-0002', '217-555-0003',
        '200 Hospital Drive', 'Springfield', 'IL', '62701',
        'US', '12-3456788', 1, 1, 1, 21,
        NULL, NULL, '1234567891', '207Q00000X', 'EI', '#336699',
        1, 'HBC', 1,
        NULL, NULL, NULL, NULL, '2.16.840.1.113883.4.6.1234567890'),
       (4, 'Assisted Living Care Center', '217-555-0003', '217-555-0004',
        '300 Hospital Drive', 'Springfield', 'IL', '62701',
        'US', '12-3456790', 1, 1, 1, 21,
        NULL, NULL, '1234567892', '207Q00000X', 'EI', '#336699',
        1, 'ALC', 1,
        NULL, NULL, NULL, NULL, '2.16.840.1.113883.4.6.1234567892');

-- -----------------------------------------------------------------------------
-- users
-- Columns: id, username, password, authorized, info, source,
--          fname, mname, lname, facility, facility_id, see_auth, active,
--          npi, taxonomy, abook_type, default_warehouse, irnpool
-- -----------------------------------------------------------------------------
-- Removed 03/27/26
-- -------------------------------------------
-- patient_data
-- Columns: id, pid, fname, lname, DOB, sex, street, city, state,
--          postal_code, country_code, phone_home, status, date
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `patient_data`
(`id`, `pid`, `fname`, `lname`, `DOB`, `sex`,
 `street`, `city`, `state`, `postal_code`, `country_code`,
 `phone_home`, `status`, `date`)
VALUES (2, 2, 'Marcus', 'Delray', '1958-03-14', 'Male', '412 Elm Street', 'Springfield', 'IL', '62701', 'US', '217-555-0201', 'active', NOW()),
       (3, 3, 'Sandra', 'Kowalski', '1945-07-29', 'Female', '88 Birch Ave', 'Springfield', 'IL', '62702', 'US', '217-555-0202', 'active', NOW()),
       (4, 4, 'Devon', 'Ashford', '1972-11-05', 'Male', '1201 Oak Blvd', 'Springfield', 'IL', '62703', 'US', '217-555-0203', 'active', NOW()),
       (5, 5, 'Patricia', 'Nguyen', '1950-09-18', 'Female', '330 Maple Lane', 'Springfield', 'IL', '62704', 'US', '217-555-0204', 'active', NOW()),
       (6, 6, 'Thomas', 'Blackwell', '1963-01-22', 'Male', '77 Cedar Road', 'Springfield', 'IL', '62705', 'US', '217-555-0205', 'active', NOW()),
       (7, 7, 'Rosa', 'Martinez', '1939-05-11', 'Female', '509 Pine Drive', 'Springfield', 'IL', '62706', 'US', '217-555-0206', 'active', NOW()),
       (8, 8, 'James', 'Okonkwo', '1955-08-30', 'Male', '14 Walnut Court', 'Springfield', 'IL', '62707', 'US', '217-555-0207', 'active', NOW()),
       (9, 9, 'Linda', 'Yamamoto', '1968-12-03', 'Female', '820 Hickory Trail', 'Springfield', 'IL', '62708', 'US', '217-555-0208', 'active', NOW()),
       (10, 10, 'Carlos', 'Rivera', '1980-04-17', 'Male', '63 Sycamore Place', 'Springfield', 'IL', '62709', 'US', '217-555-0209', 'active', NOW()),
       (11, 11, 'Walter', 'Drummond', '1953-06-08', 'Male', '247 Chestnut Street', 'Springfield', 'IL', '62710', 'US', '217-555-0210', 'active', NOW()),
       (50, 50, 'Eleanor', 'Hartwell', '1938-04-12', 'Female', '210 Maple Ave', 'Springfield', 'IL', '62701', 'US', '217-555-0101', 'active', NOW()),
       (51, 51, 'George', 'Calloway', '1935-11-28', 'Male', '45 Oak Street', 'Springfield', 'IL', '62702', 'US', '217-555-0102', 'active', NOW()),
       (52, 52, 'Ruth', 'Okonkwo', '1941-07-03', 'Female', '88 Pine Road', 'Springfield', 'IL', '62703', 'US', '217-555-0103', 'active', NOW()),
       (53, 53, 'Harold', 'Steinberg', '1932-02-17', 'Male', '301 Elm Drive', 'Springfield', 'IL', '62704', 'US', '217-555-0104', 'active', NOW()),
       (54, 54, 'Dorothy', 'Vasquez', '1945-09-22', 'Female', '17 Birch Lane', 'Springfield', 'IL', '62705', 'US', '217-555-0105', 'active', NOW());

-- -----------------------------------------------------------------------------
-- form_encounter
-- Columns: id, date, onset_date, reason, facility, pid, provider_id,
--          facility_id, billing_facility, encounter, pos_code
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `form_encounter`
(`id`, `date`, `onset_date`, `reason`, `facility`, `pid`,
 `provider_id`, `facility_id`, `billing_facility`, `encounter`, `pos_code`)
VALUES (297, '2026-01-12 22:20:13', '2026-01-12 22:20:13', 'AL Admission', 'Assisted Living', 50, 1, 1, 1, 1000050, 60),
       (298, '2026-01-28 22:20:13', '2026-01-28 22:20:13', 'AL Admission', 'Assisted Living', 51, 1, 1, 1, 1000051, 60),
       (299, '2026-02-10 22:20:13', '2026-02-10 22:20:13', 'AL Admission', 'Assisted Living', 52, 1, 1, 1, 1000052, 60),
       (300, '2025-12-28 22:20:13', '2025-12-28 22:20:13', 'AL Admission', 'Assisted Living', 53, 1, 1, 1, 1000053, 60),
       (301, '2026-02-19 22:20:13', '2026-02-19 22:20:13', 'AL Admission', 'Assisted Living', 54, 1, 1, 1, 1000054, 60),
       (302, '2026-02-23 08:30:00', '2026-02-23 08:30:00', 'IP Admission', 'Community Memorial Hospital', 2, 1, 1, 1, 1000019, 21),
       (303, '2026-02-26 14:00:00', '2026-02-26 14:00:00', 'IP Admission', 'Community Memorial Hospital', 5, 1, 1, 1, 1000020, 21),
       (304, '2026-02-26 22:10:00', '2026-02-26 22:10:00', 'IP Admission', 'Community Memorial Hospital', 6, 1, 1, 1, 1000021, 21),
       (305, '2026-02-24 10:45:00', '2026-02-24 10:45:00', 'IP Admission', 'Community Memorial Hospital', 8, 1, 1, 1, 1000022, 21),
       (306, '2026-02-27 16:20:00', '2026-02-27 16:20:00', 'IP Admission', 'Community Memorial Hospital', 11, 1, 1, 1, 1000023, 21);

-- -----------------------------------------------------------------------------
-- forms
-- Columns: date, encounter, form_name, form_id, pid, user, groupname,
--          authorized, deleted, formdir, therapy_group_id
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `forms`
(`date`, `encounter`, `form_name`, `form_id`, `pid`,
 `user`, `groupname`, `authorized`, `deleted`, `formdir`, `therapy_group_id`)
VALUES ('2026-01-12 22:20:13', 1000050, 'New Patient Encounter', 297, 50, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-01-28 22:20:13', 1000051, 'New Patient Encounter', 298, 51, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-10 22:20:13', 1000052, 'New Patient Encounter', 299, 52, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2025-12-28 22:20:13', 1000053, 'New Patient Encounter', 300, 53, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-19 22:20:13', 1000054, 'New Patient Encounter', 301, 54, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-23 08:30:00', 1000019, 'New Patient Encounter', 302, 2, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-26 14:00:00', 1000020, 'New Patient Encounter', 303, 5, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-26 22:10:00', 1000021, 'New Patient Encounter', 304, 6, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-24 10:45:00', 1000022, 'New Patient Encounter', 305, 8, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       ('2026-02-27 16:20:00', 1000023, 'New Patient Encounter', 306, 11, 'admin', 'Default', 1, 0, 'newpatient', NULL);

-- -----------------------------------------------------------------------------
-- care_teams
-- Columns: pid, status, team_name, note, created_by, updated_by
-- -----------------------------------------------------------------------------

DELETE
FROM `care_team_member`
WHERE `care_team_id` IN
      (SELECT `id` FROM `care_teams` WHERE `pid` IN (50, 51, 52, 53, 54));
DELETE
FROM `care_teams`
WHERE `pid` IN (50, 51, 52, 53, 54);

INSERT INTO `care_teams` (`pid`, `status`, `team_name`, `note`, `created_by`, `updated_by`)
VALUES (50, 'active', 'Eleanor Hartwell Care Team', 'Memory care — weekly interdisciplinary rounds', 1, 1),
       (51, 'active', 'George Calloway Care Team', 'Post-surgical rehab — PT/OT twice weekly', 1, 1),
       (52, 'active', 'Ruth Okonkwo Care Team', 'Respiratory and medication management', 1, 1),
       (53, 'active', 'Harold Steinberg Care Team', 'Parkinson''s specialist — neurology monthly', 1, 1),
       (54, 'active', 'Dorothy Vasquez Care Team', 'Cardiac/metabolic monitoring team', 1, 1);

-- -----------------------------------------------------------------------------
-- care_team_member
-- Columns: care_team_id, user_id, role, status, provider_since,
--          note, created_by, updated_by
-- Note: unique key is (care_team_id, user_id, facility_id, contact_id).
--       facility_id and contact_id are NULL here, so user_id must differ per team.
-- -----------------------------------------------------------------------------

INSERT INTO `care_team_member`
(`care_team_id`, `user_id`, `role`, `status`,
 `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`,
       1,
       'physician',
       'active',
       CAST(ct.`date_created` AS DATE),
       'Attending physician',
       1,
       1
FROM `care_teams` ct
WHERE ct.`pid` IN (50, 51, 52, 53, 54)
  AND ct.`status` = 'active';

INSERT INTO `care_team_member`
(`care_team_id`, `user_id`, `role`, `status`,
 `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`,
       2,
       CASE ct.`pid`
           WHEN 50 THEN 'nurse'
           WHEN 51 THEN 'nurse'
           WHEN 52 THEN 'nurse'
           WHEN 53 THEN 'nurse'
           WHEN 54 THEN 'nurse'
           END,
       'active',
       CAST(ct.`date_created` AS DATE),
       CASE ct.`pid`
           WHEN 50 THEN 'Primary care aide — memory care certified'
           WHEN 51 THEN 'Post-surgical nursing — PT coordination'
           WHEN 52 THEN 'Medication administration and respiratory monitoring'
           WHEN 53 THEN 'Parkinson''s care aide — daily PT liaison'
           WHEN 54 THEN 'Cardiac monitoring — daily weights and fluid tracking'
           END,
       1,
       1
FROM `care_teams` ct
WHERE ct.`pid` IN (50, 51, 52, 53, 54)
  AND ct.`status` = 'active';

-- -----------------------------------------------------------------------------
-- form_care_plan
-- Columns: date, pid, encounter, user, groupname, authorized, activity,
--          description, care_plan_type, plan_status, proposed_date
-- Note: form_care_plan.id has NO AUTO_INCREMENT by design (group-id pattern).
--       Omitting id — MySQL will use 0 for each row (one group per encounter).
-- -----------------------------------------------------------------------------


-- -----------------------------------------------------------------------------
-- forms — care_plan registry entries (one per AL encounter)
-- form_id matches form_care_plan.id (shared group id for all rows per encounter)
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `forms`
(`date`, `encounter`, `form_name`, `form_id`, `pid`,
 `user`, `groupname`, `authorized`, `deleted`, `formdir`, `therapy_group_id`)
VALUES ('2026-01-12 22:20:13', 1000050, 'Care Plan', 5001, 50, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       ('2026-01-28 22:20:13', 1000051, 'Care Plan', 5002, 51, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       ('2026-02-10 22:20:13', 1000052, 'Care Plan', 5003, 52, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       ('2025-12-28 22:20:13', 1000053, 'Care Plan', 5004, 53, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       ('2026-02-19 22:20:13', 1000054, 'Care Plan', 5005, 54, 'admin', 'Default', 1, 0, 'care_plan', NULL);
DELETE
FROM `form_care_plan`
WHERE `pid` IN (50, 51, 52, 53, 54);

DELETE
FROM `form_care_plan`
WHERE `pid` IN (50, 51, 52, 53, 54);

INSERT INTO `form_care_plan`
(`id`, `date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
 `description`,
 `care_plan_type`, `plan_status`, `proposed_date`)
VALUES (5001, '2026-01-12', 50, 1000050, 1, 'Default', 1, 1,
        'Prevent fall-related injury — bed/chair alarm active at all times, non-slip footwear required',
        'goal', 'active', '2026-04-12'),
       (5001, '2026-01-12', 50, 1000050, 1, 'Default', 1, 1,
        'Maintain orientation and reduce agitation through structured daily routine and music therapy',
        'goal', 'active', '2026-03-12'),
       (5001, '2026-01-12', 50, 1000050, 1, 'Default', 1, 1,
        'Daily music therapy session 10:00-10:30 AM per dementia-care protocol',
        'activity', 'active', NULL),
       (5001, '2026-01-12', 50, 1000050, 1, 'Default', 1, 1,
        'Morse Fall Scale reassessment every 30 days — update care plan if score changes tier',
        'activity', 'active', '2026-03-13'),
       (5002, '2026-01-28', 51, 1000051, 1, 'Default', 1, 1,
        'Achieve independent ambulation with walker on level surfaces within 60 days of admission',
        'goal', 'active', '2026-03-28'),
       (5002, '2026-01-28', 51, 1000051, 1, 'Default', 1, 1,
        'Reduce pain score to 3 or below during ambulation — wean opioids by week 6',
        'goal', 'active', '2026-03-10'),
       (5002, '2026-01-28', 51, 1000051, 1, 'Default', 1, 1,
        'PT session Monday/Wednesday/Friday — hip strengthening and progressive gait training',
        'activity', 'active', NULL),
       (5003, '2026-02-10', 52, 1000052, 1, 'Default', 1, 1,
        'Maintain SpO2 at or above 92% on room air during all routine activities',
        'goal', 'active', '2026-04-10'),
       (5003, '2026-02-10', 52, 1000052, 1, 'Default', 1, 1,
        'Administer scheduled inhalers with adherence documented daily in MAR',
        'activity', 'active', NULL),
       (5003, '2026-02-10', 52, 1000052, 1, 'Default', 1, 1,
        'Pulmonary reassessment at 30 and 60 days — adjust oxygen threshold if needed',
        'activity', 'active', '2026-03-10'),
       (5004, '2025-12-28', 53, 1000053, 1, 'Default', 1, 1,
        'Zero aspiration events — strict thickened-liquid (nectar consistency) diet, supervised meals',
        'goal', 'active', '2026-03-28'),
       (5004, '2025-12-28', 53, 1000053, 1, 'Default', 1, 1,
        'Reduce fall frequency from 2 per month to zero via Parkinson''s mobility protocol',
        'goal', 'active', '2026-03-28'),
       (5004, '2025-12-28', 53, 1000053, 1, 'Default', 1, 1,
        'Daily PT — balance and gait training and OT — adaptive equipment assessment weekly',
        'activity', 'active', NULL),
       (5004, '2025-12-28', 53, 1000053, 1, 'Default', 1, 1,
        'Carbidopa/levodopa administered within 30 minutes of scheduled time — track in MAR',
        'activity', 'active', NULL),
       (5005, '2026-02-19', 54, 1000054, 1, 'Default', 1, 1,
        'Maintain daily weight within 2 lb of dry weight baseline — notify provider if exceeded',
        'goal', 'active', '2026-05-19'),
       (5005, '2026-02-19', 54, 1000054, 1, 'Default', 1, 1,
        'Fasting blood glucose 80-180 mg/dL — daily monitoring with insulin sliding scale log',
        'goal', 'active', '2026-03-19'),
       (5005, '2026-02-19', 54, 1000054, 1, 'Default', 1, 1,
        '1500 mL fluid restriction daily — 2g sodium cardiac diet — daily weight log',
        'activity', 'active', NULL);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- END SECTION 1
-- =============================================================================


-- =============================================================================
-- SECTION 2 — Module oei_* Tables (DROP + CREATE + INSERT, 33 tables)
-- =============================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
/*!40101 SET NAMES utf8mb4 */;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS
    `oei_schema_version`,
    `oei_settings`,
    `oei_facility_directory`,
    `oei_location`,
    `oei_protocol`,
    `oei_episode`,
    `oei_triage`,
    `oei_episode_location`,
    `oei_patient_location_history`,
    `oei_episode_status_history`,
    `oei_episode_event`,
    `oei_obs_plan`,
    `oei_al_episode`,
    `oei_adl_record`,
    `oei_incident`,
    `oei_mar_order`,
    `oei_mar_administration`,
    `oei_task`,
    `oei_episode_disposition`,
    `oei_ereferral`,
    `oei_episode_document`,
    `oei_transfer`,
    `oei_bh_safety`,
    `oei_bh_boarding`,
    `oei_diversion`,
    `oei_diversion_history`,
    `oei_alert_ack`,
    `oei_hl7_outbound_log`,
    `oei_downtime_sync_queue`,
    `oei_user_context`,
    `oei_activity_log`,
    `oei_fall_risk_assessment`,
    `oei_ip_episode`;

SET FOREIGN_KEY_CHECKS = 1;


-- oei_schema_version
CREATE TABLE IF NOT EXISTS `oei_schema_version`
(
    `version`          varchar(20) NOT NULL,
    `applied_datetime` datetime    NOT NULL,
    PRIMARY KEY (`version`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.0.0-demo', '2026-02-28 22:20:12');

-- oei_settings
CREATE TABLE IF NOT EXISTS `oei_settings`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `setting_key`        varchar(80)         NOT NULL,
    `setting_value`      text                NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_settings` (`facility_id`, `setting_key`),
    KEY `idx_oei_settings_fac` (`facility_id`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_settings` (`id`, `facility_id`, `setting_key`, `setting_value`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'facility_name', 'Community Memorial Hospital ED', 1, '2026-02-28 22:20:10'),
       (2, 1, 'door_to_room_target_min', '30', 1, '2026-02-28 22:20:10'),
       (3, 1, 'door_to_provider_target_min', '60', 1, '2026-02-28 22:20:10'),
       (4, 1, 'lwbs_threshold_min', '120', 1, '2026-02-28 22:20:10'),
       (5, 1, 'obs_runway_warning_hours', '4', 1, '2026-02-28 22:20:10'),
       (6, 1, 'boarding_alert_hours', '4', 1, '2026-02-28 22:20:10'),
       (7, 1, 'esi_high_acuity_max', '2', 1, '2026-02-28 22:20:10'),
       (8, 1, 'vitals_interval_ed_min', '120', 1, '2026-02-28 22:20:10'),
       (9, 1, 'vitals_interval_obs_min', '240', 1, '2026-02-28 22:20:10'),
       (10, 1, 'vitals_window_hours', '12', 1, '2026-02-28 22:20:10'),
       (11, 1, 'hl7_enabled', '0', 1, '2026-02-28 22:20:10'),
       (12, 1, 'hl7_transport', 'MLLP', 1, '2026-02-28 22:20:10'),
       (13, 1, 'hl7_mllp_host', '127.0.0.1', 1, '2026-02-28 22:20:10'),
       (14, 1, 'hl7_mllp_port', '2575', 1, '2026-02-28 22:20:10'),
       (15, 1, 'hl7_processing_id', 'T', 1, '2026-02-28 22:20:10'),
       (31, 1, 'triage_color_ESI_1', '{"bg": "#7B0000", "fg": "#FFFFFF"}', 1, '2026-02-25 22:20:13'),
       (32, 1, 'triage_color_ESI_2', '{"bg": "#E65100", "fg": "#FFFFFF"}', 1, '2026-02-25 22:20:13'),
       (33, 1, 'triage_color_ESI_3', '{"bg": "#F9A825", "fg": "#212121"}', 1, '2026-02-25 22:20:13');

INSERT INTO `oei_settings` (`id`, `facility_id`, `setting_key`, `setting_value`, `updated_by_user_id`, `updated_datetime`)
VALUES (40, 1, 'ip_expected_los_medsurg', '4', 1, '2026-02-28 22:20:10'),
       (41, 1, 'ip_expected_los_telemetry', '3', 1, '2026-02-28 22:20:10'),
       (42, 1, 'ip_expected_los_icu', '7', 1, '2026-02-28 22:20:10'),
       (43, 1, 'ip_expected_los_ortho', '3', 1, '2026-02-28 22:20:10'),
       (44, 1, 'ip_discharge_target_hour', '11', 1, '2026-02-28 22:20:10'),
       (45, 1, 'ip_los_warning_hours', '24', 1, '2026-02-28 22:20:10');

-- oei_facility_directory
CREATE TABLE IF NOT EXISTS `oei_facility_directory`
(
    `id`           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`  bigint(20) UNSIGNED NOT NULL,
    `name`         varchar(120)        NOT NULL,
    `service_type` varchar(40)         NOT NULL DEFAULT 'GENERAL',
    `phone`        varchar(30)                  DEFAULT NULL,
    `fax`          varchar(30)                  DEFAULT NULL,
    `email`        varchar(120)                 DEFAULT NULL,
    `address`      varchar(255)                 DEFAULT NULL,
    `hours`        varchar(80)                  DEFAULT NULL,
    `notes`        varchar(255)                 DEFAULT NULL,
    `is_active`    tinyint(1)          NOT NULL DEFAULT 1,
    `sort_order`   int(11)             NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dir_fac_active` (`facility_id`, `is_active`, `sort_order`),
    KEY `idx_oei_dir_service` (`facility_id`, `service_type`, `is_active`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_facility_directory` (`id`, `facility_id`, `name`, `service_type`, `phone`, `fax`, `email`, `address`, `hours`, `notes`, `is_active`, `sort_order`)
VALUES (1, 1, 'Regional Trauma Center', 'TRAUMA', '(555) 900-1100', '(555) 900-1101', NULL, '1200 Trauma Pkwy, Riverside, CA 92501', '24/7', 'Level I Trauma Center. Direct accept line ext 4400.', 1, 10),
       (2, 1, 'St. Michael Medical Center ICU', 'ICU', '(555) 210-4400', '(555) 210-4401', NULL, '800 Medical Center Dr, Riverside, CA 92507', '24/7', 'Accepts direct ICU admits. Intensivist on call 24/7.', 1, 20),
       (3, 1, 'Valley Behavioral Health Center', 'BH', '(555) 340-7700', '(555) 340-7701', NULL, '3300 Valley View Rd, Moreno Valley, CA 92557', 'M-F 8am-8pm and  On-call 24/7', 'Adult voluntary and involuntary. 22-bed capacity. Call 3h ahead.', 1, 30),
       (4, 1, 'Regional Stroke & Neuro Center', 'NEURO', '(555) 450-2200', '(555) 450-2201', NULL, '950 Neuroscience Blvd, Riverside, CA 92501', '24/7 Stroke Team', 'Comprehensive Stroke Center. tPA and thrombectomy capable.', 1, 40),
       (5, 1, 'Sunrise Skilled Nursing Facility', 'SNF', '(555) 580-3300', '(555) 580-3301', NULL, '201 Sunrise Terrace, Moreno Valley, CA 92553', 'M-F 8am-5pm', 'Medicare-certified. PT/OT/speech. Med-surg level care.', 1, 50),
       (6, 1, 'Valley Home Health Agency', 'HOME_HEALTH', '(555) 620-5500', '(555) 620-5501', NULL, '1040 Home Care Way, Riverside, CA 92503', 'M-F 8am-6pm and  on-call after hours', 'IV therapy, wound care, medication management.', 1, 60),
       (7, 1, 'Mountain Cardiology Group', 'CARDIOLOGY', '(555) 730-8800', '(555) 730-8801', NULL, '505 Heart Lane, Riverside, CA 92506', 'M-F 9am-5pm and  on-call 24/7', 'Follow-up within 48-72h for ACS discharge.', 1, 70),
       (8, 1, 'Orthopedic Associates of Riverside', 'ORTHOPEDIC', '(555) 840-6600', '(555) 840-6601', NULL, '720 Bone & Joint Dr, Riverside, CA 92505', 'M-F 8am-5pm', 'Walk-in fracture clinic M/W/F. Fax referral + x-ray CD.', 1, 80),
       (9, 1, 'Mountain Urology Associates', 'UROLOGY', '(555) 950-4400', '(555) 950-4401', NULL, '338 Renal Blvd, Riverside, CA 92504', 'M-F 9am-5pm', 'Stone clinic. Fax CT report. Follow up within 1 week.', 1, 90),
       (10, 1, 'LTACH — Valley Long-Term Acute', 'LTACH', '(555) 160-9900', '(555) 160-9901', NULL, '1800 Long Term Care Ave, Perris, CA 92571', 'M-F 8am-5pm', 'Complex medically ventilator-dependent patients.', 1, 100),
       (11, 1, 'State Psychiatric Hospital', 'BH', '(555) 270-1100', '(555) 270-1101', NULL, '4500 State Hospital Rd, Patton, CA 92369', '24/7 Intake', 'IMD facility. Accepts involuntary holds. Long waitlist.', 1, 110),
       (12, 1, 'Regional Trauma Center', 'TRAUMA', '(555) 900-1100', '(555) 900-1101', NULL, '1200 Trauma Pkwy, Riverside, CA 92501', '24/7', 'Level I Trauma Center. Direct accept line ext 4400.', 1, 10),
       (13, 1, 'St. Michael Medical Center ICU', 'ICU', '(555) 210-4400', '(555) 210-4401', NULL, '800 Medical Center Dr, Riverside, CA 92507', '24/7', 'Accepts direct ICU admits. Intensivist on call 24/7.', 1, 20),
       (14, 1, 'Valley Behavioral Health Center', 'BH', '(555) 340-7700', '(555) 340-7701', NULL, '3300 Valley View Rd, Moreno Valley, CA 92557', 'M-F 8am-8pm and  On-call 24/7', 'Adult voluntary and involuntary. 22-bed capacity. Call 3h ahead.', 1, 30),
       (15, 1, 'Regional Stroke & Neuro Center', 'NEURO', '(555) 450-2200', '(555) 450-2201', NULL, '950 Neuroscience Blvd, Riverside, CA 92501', '24/7 Stroke Team', 'Comprehensive Stroke Center. tPA and thrombectomy capable.', 1, 40),
       (16, 1, 'Sunrise Skilled Nursing Facility', 'SNF', '(555) 580-3300', '(555) 580-3301', NULL, '201 Sunrise Terrace, Moreno Valley, CA 92553', 'M-F 8am-5pm', 'Medicare-certified. PT/OT/speech. Med-surg level care.', 1, 50),
       (17, 1, 'Valley Home Health Agency', 'HOME_HEALTH', '(555) 620-5500', '(555) 620-5501', NULL, '1040 Home Care Way, Riverside, CA 92503', 'M-F 8am-6pm and  on-call after hours', 'IV therapy, wound care, medication management.', 1, 60),
       (18, 1, 'Mountain Cardiology Group', 'CARDIOLOGY', '(555) 730-8800', '(555) 730-8801', NULL, '505 Heart Lane, Riverside, CA 92506', 'M-F 9am-5pm and  on-call 24/7', 'Follow-up within 48-72h for ACS discharge.', 1, 70),
       (19, 1, 'Orthopedic Associates of Riverside', 'ORTHOPEDIC', '(555) 840-6600', '(555) 840-6601', NULL, '720 Bone & Joint Dr, Riverside, CA 92505', 'M-F 8am-5pm', 'Walk-in fracture clinic M/W/F. Fax referral + x-ray CD.', 1, 80),
       (20, 1, 'Mountain Urology Associates', 'UROLOGY', '(555) 950-4400', '(555) 950-4401', NULL, '338 Renal Blvd, Riverside, CA 92504', 'M-F 9am-5pm', 'Stone clinic. Fax CT report. Follow up within 1 week.', 1, 90),
       (21, 1, 'LTACH — Valley Long-Term Acute', 'LTACH', '(555) 160-9900', '(555) 160-9901', NULL, '1800 Long Term Care Ave, Perris, CA 92571', 'M-F 8am-5pm', 'Complex medically ventilator-dependent patients.', 1, 100),
       (22, 1, 'State Psychiatric Hospital', 'BH', '(555) 270-1100', '(555) 270-1101', NULL, '4500 State Hospital Rd, Patton, CA 92369', '24/7 Intake', 'IMD facility. Accepts involuntary holds. Long waitlist.', 1, 110);

-- oei_location
CREATE TABLE IF NOT EXISTS `oei_location`
(
    `id`            bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`   bigint(20) UNSIGNED NOT NULL,
    `code`          varchar(20)         NOT NULL,
    `name`          varchar(80)         NOT NULL,
    `location_type` varchar(20)         NOT NULL DEFAULT 'ROOM',
    `status`        varchar(20)         NOT NULL DEFAULT 'AVAILABLE',
    `unit_name`     varchar(40)                  DEFAULT NULL,
    `is_active`     tinyint(1)          NOT NULL DEFAULT 1,
    `sort_order`    int(11)             NOT NULL DEFAULT 0,
    `notes`         varchar(255)                 DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_loc_fac_code` (`facility_id`, `code`),
    KEY `idx_oei_loc_fac_active` (`facility_id`, `is_active`, `sort_order`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_location` (`id`, `facility_id`, `code`, `name`, `location_type`, `status`, `unit_name`, `is_active`, `sort_order`, `notes`)
VALUES (1, 1, 'ED01', 'ED Room 1', 'ROOM', 'AVAILABLE', 'ED', 1, 10, 'Standard ED room'),
       (2, 1, 'ED02', 'ED Room 2', 'ROOM', 'AVAILABLE', 'ED', 1, 20, 'Standard ED room'),
       (3, 1, 'ED03', 'ED Room 3', 'ROOM', 'AVAILABLE', 'ED', 1, 30, 'Standard ED room'),
       (4, 1, 'ED04', 'ED Room 4', 'ROOM', 'AVAILABLE', 'ED', 1, 40, 'Standard ED room'),
       (5, 1, 'ED05', 'ED Room 5', 'ROOM', 'AVAILABLE', 'ED', 1, 50, 'Standard ED room'),
       (6, 1, 'ED06', 'ED Room 6', 'ROOM', 'AVAILABLE', 'ED', 1, 60, 'Isolation capable'),
       (7, 1, 'ED07', 'ED Room 7', 'ROOM', 'AVAILABLE', 'ED', 1, 70, 'Standard ED room'),
       (8, 1, 'ED08', 'ED Room 8', 'ROOM', 'AVAILABLE', 'ED', 1, 80, 'Standard ED room'),
       (9, 1, 'TR01', 'Trauma Bay 1', 'ROOM', 'AVAILABLE', 'ED', 1, 90, 'Full resuscitation equipment'),
       (10, 1, 'TR02', 'Trauma Bay 2', 'ROOM', 'AVAILABLE', 'ED', 1, 100, 'Full resuscitation equipment'),
       (11, 1, 'OBS1', 'Obs Bay 1', 'OBS', 'AVAILABLE', 'OBS', 1, 110, 'Telemetry monitored'),
       (12, 1, 'OBS2', 'Obs Bay 2', 'OBS', 'AVAILABLE', 'OBS', 1, 120, 'Telemetry monitored'),
       (13, 1, 'OBS3', 'Obs Bay 3', 'OBS', 'AVAILABLE', 'OBS', 1, 130, 'Telemetry monitored'),
       (14, 1, 'OBS4', 'Obs Bay 4', 'OBS', 'AVAILABLE', 'OBS', 1, 140, 'Standard monitoring'),
       (15, 1, 'HALL1', 'Hallway Bed 1', 'ROOM', 'AVAILABLE', 'ED', 1, 150, 'Overflow capacity'),
       (16, 1, 'HALL2', 'Hallway Bed 2', 'ROOM', 'AVAILABLE', 'ED', 1, 160, 'Overflow capacity'),
       (17, 1, 'PSY1', 'BH Room 1', 'ROOM', 'AVAILABLE', 'BH', 1, 170, 'Sitter-equipped, ligature-reduced'),
       (18, 1, 'PSY2', 'BH Room 2', 'ROOM', 'AVAILABLE', 'BH', 1, 180, 'Sitter-equipped, ligature-reduced'),
       (19, 1, 'WAIT', 'Waiting Room', 'WAIT', 'AVAILABLE', 'ED', 1, 190, 'Tracked patients awaiting placement'),
       (20, 1, 'ICU01', 'ICU Bed 1', 'ICU', 'OCCUPIED', 'ICU', 1, 200, 'Ventilator-capable and  continuous hemodynamic monitoring'),
       (21, 1, 'ICU02', 'ICU Bed 2', 'ICU', 'AVAILABLE', 'ICU', 1, 210, 'Ventilator-capable and  continuous hemodynamic monitoring'),
       (22, 1, 'ICU03', 'ICU Bed 3', 'ICU', 'AVAILABLE', 'ICU', 1, 220, 'Step-down capable'),
       (23, 1, 'MS401', 'Med/Surg 4B-201', 'BED', 'OCCUPIED', 'Med/Surg 4B', 1, 230, 'Standard med/surg'),
       (24, 1, 'MS402', 'Med/Surg 4B-202', 'BED', 'AVAILABLE', 'Med/Surg 4B', 1, 240, 'Standard med/surg'),
       (25, 1, 'MS403', 'Med/Surg 3A-118', 'BED', 'OCCUPIED', 'Med/Surg 3A', 1, 250, 'Standard med/surg'),
       (26, 1, 'TEL03', 'Telemetry 3-03', 'BED', 'OCCUPIED', 'Telemetry 3', 1, 260, 'Continuous cardiac monitoring'),
       (27, 1, 'TEL04', 'Telemetry 3-04', 'BED', 'AVAILABLE', 'Telemetry 3', 1, 270, 'Continuous cardiac monitoring'),
       (28, 1, 'ORT01', 'Ortho 4B-208', 'BED', 'OCCUPIED', 'Ortho 4B', 1, 280, 'Orthopedic post-op unit');

-- oei_protocol
CREATE TABLE IF NOT EXISTS `oei_protocol`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `protocol_key`       varchar(80)         NOT NULL,
    `label`              varchar(255)        NOT NULL,
    `version`            varchar(20)         NOT NULL DEFAULT '1',
    `enabled`            tinyint(1)          NOT NULL DEFAULT 1,
    `definition_json`    mediumtext          NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_protocol` (`facility_id`, `protocol_key`),
    KEY `idx_oei_protocol_enabled` (`facility_id`, `enabled`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_protocol` (`id`, `facility_id`, `protocol_key`, `label`, `version`, `enabled`, `definition_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'CHEST_PAIN', 'Chest Pain / ACS Rule-Out', '2.1', 1,
        '{"protocol_key":"CHEST_PAIN","label":"Chest Pain / ACS Rule-Out","target_hours":24,"runway_hours":4,"tasks":[{"type":"EKG","at_minutes":[0,360],"label":"12-Lead EKG"},{"type":"TROPONIN","at_minutes":[0,360,720],"label":"Serial Troponin"},{"type":"VITALS_CHECK","every_minutes":240,"label":"Vitals Q4H"},{"type":"DISPOSITION_DECISION","at_minutes":1320,"label":"Cardiology consult or discharge decision"}]}',
        1, '2026-02-28 22:20:10'),
       (2, 1, 'COPD_EXACERBATION', 'COPD Exacerbation Observation', '1.3', 1,
        '{"protocol_key":"COPD_EXACERBATION","label":"COPD Exacerbation Observation","target_hours":24,"runway_hours":6,"tasks":[{"type":"SPIROMETRY","at_minutes":[0,360,720],"label":"Peak Flow / Spirometry"},{"type":"ABG","at_minutes":[60],"label":"Arterial Blood Gas"},{"type":"VITALS_CHECK","every_minutes":120,"label":"Vitals Q2H"},{"type":"NEBS","every_minutes":240,"label":"Albuterol Neb Treatment"},{"type":"DISPOSITION_DECISION","at_minutes":1080,"label":"Admit vs discharge decision"}]}',
        1, '2026-02-28 22:20:10'),
       (3, 1, 'SEPSIS_BUNDLE', 'Sepsis 3-Hour Bundle', '3.0', 1,
        '{"protocol_key":"SEPSIS_BUNDLE","label":"Sepsis 3-Hour Bundle","target_hours":3,"runway_hours":0.5,"tasks":[{"type":"BLOOD_CULTURE","at_minutes":[0],"label":"Blood Cultures x2"},{"type":"LACTATE","at_minutes":[0],"label":"Serum Lactate"},{"type":"IV_FLUID","at_minutes":[0],"label":"30mL/kg IV Fluid Bolus"},{"type":"ANTIBIOTICS","at_minutes":[0],"label":"Broad-Spectrum Antibiotics"},{"type":"VITALS_CHECK","every_minutes":60,"label":"Vitals Q1H"}]}',
        1, '2026-02-28 22:20:10'),
       (4, 1, 'STROKE_ALERT', 'Code Stroke Protocol', '2.5', 1,
        '{"protocol_key":"STROKE_ALERT","label":"Code Stroke Protocol","target_hours":1,"runway_hours":0.25,"tasks":[{"type":"CT_HEAD_STAT","at_minutes":[0],"label":"Non-contrast CT Head STAT"},{"type":"CT_ANGIO","at_minutes":[15],"label":"CTA Head & Neck"},{"type":"NIHSS_SCORE","at_minutes":[0],"label":"NIHSS Assessment"},{"type":"NEUROLOGY_CONSULT","at_minutes":[0],"label":"Neurology Consult"},{"type":"IV_ACCESS","at_minutes":[0],"label":"Two large-bore IVs"},{"type":"LABS_STAT","at_minutes":[0],"label":"Coags / CBC / BMP STAT"},{"type":"TPA_DECISION","at_minutes":[30],"label":"tPA Eligibility Assessment"}]}',
        1, '2026-02-28 22:20:10'),
       (5, 1, 'OPIOID_OVERDOSE', 'Opioid Overdose / Naloxone Protocol', '1.1', 1,
        '{"protocol_key":"OPIOID_OVERDOSE","label":"Opioid Overdose Protocol","target_hours":4,"runway_hours":1,"tasks":[{"type":"VITALS_CHECK","every_minutes":30,"label":"Vitals Q30min (watch re-sedation)"},{"type":"NALOXONE_TITRATE","at_minutes":[0],"label":"Naloxone Drip Titration"},{"type":"BH_CONSULT","at_minutes":[60],"label":"Behavioral Health Consult"},{"type":"NARCAN_EDUCATION","at_minutes":[180],"label":"Narcan Rx and education if discharge"}]}',
        1, '2026-02-28 22:20:10'),
       (6, 1, 'PEDIATRIC_FEVER', 'Pediatric Fever Protocol (< 13y)', '1.0', 1,
        '{"protocol_key":"PEDIATRIC_FEVER","label":"Pediatric Fever Protocol","target_hours":4,"runway_hours":0.5,"tasks":[{"type":"TEMP_RECHECK","at_minutes":[60],"label":"Temperature Recheck"},{"type":"UA_RESULT","at_minutes":[30],"label":"UA / Culture Review"},{"type":"CBC_REVIEW","at_minutes":[45],"label":"CBC with Differential"},{"type":"ANTIPYRETIC","at_minutes":[0],"label":"Acetaminophen or Ibuprofen"}]}',
        1, '2026-02-28 22:20:10'),
       (13, 1, 'GENERAL_OBS', 'General Observation', '1', 1,
        '{\n    "target_hours": 24,\n    "runway_hours": 6,\n    "milestones": [\n        {\n            "label": "Reassess 2h",\n            "type": "REASSESS_Q2H",\n            "at_minutes": 120\n        },\n        {\n            "label": "Reassess 4h",\n            "type": "REASSESS_Q2H",\n            "at_minutes": 240\n        }\n    ],\n    "tasks": [\n        {\n            "type": "VITALS_Q4H",\n            "every_minutes": 240\n        },\n        {\n            "type": "REASSESS_Q2H",\n            "every_minutes": 120\n        }\n    ]\n}',
        NULL, '2026-02-28 18:58:37');

-- oei_episode
CREATE TABLE IF NOT EXISTS `oei_episode`
(
    `id`                        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pid`                       bigint(20) UNSIGNED NOT NULL,
    `eid`                       bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`               bigint(20) UNSIGNED NOT NULL,
    `type`                      varchar(10)         NOT NULL DEFAULT 'ED',
    `start_datetime`            datetime            NOT NULL,
    `end_datetime`              datetime                     DEFAULT NULL,
    `disposition`               varchar(20)                  DEFAULT NULL,
    `status`                    varchar(20)         NOT NULL DEFAULT 'ACTIVE',
    `chief_complaint`           varchar(255)                 DEFAULT NULL,
    `acuity_esi`                tinyint(3) UNSIGNED          DEFAULT NULL,
    `provider_user_id`          bigint(20) UNSIGNED          DEFAULT NULL,
    `triage_completed_datetime` datetime                     DEFAULT NULL,
    `last_status_update`        datetime                     DEFAULT NULL,
    `arrival_mode`              varchar(30)                  DEFAULT NULL,
    `triage_datetime`           datetime                     DEFAULT NULL,
    `triage_note`               varchar(255)                 DEFAULT NULL,
    `created_by_user_id`        bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`          datetime                     DEFAULT NULL,
    `assigned_nurse_user_id`    int(11)                      DEFAULT NULL,
    `assigned_provider_user_id` int(11)                      DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_episode_active` (`facility_id`, `status`, `start_datetime`),
    KEY `idx_oei_episode_pid` (`pid`),
    KEY `idx_oei_episode_eid` (`eid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_episode` (`id`, `pid`, `eid`, `facility_id`, `type`, `start_datetime`, `end_datetime`, `disposition`, `status`, `chief_complaint`, `acuity_esi`, `provider_user_id`, `triage_completed_datetime`, `last_status_update`, `arrival_mode`, `triage_datetime`,
                           `triage_note`, `created_by_user_id`, `created_datetime`, `assigned_nurse_user_id`, `assigned_provider_user_id`)
VALUES (1, 2, NULL, 1, 'ED', '2026-02-28 19:19:41', NULL, NULL, 'ACTIVE', 'Altered mental status, fever, productive cough', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 19:19:41', 1, 1),
       (2, 3, NULL, 1, 'OBS', '2026-02-28 00:19:41', NULL, NULL, 'ACTIVE', 'Substernal chest pressure, r/o ACS', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 00:19:41', 1, 1),
       (3, 4, NULL, 1, 'ED', '2026-02-28 17:19:41', NULL, NULL, 'ACTIVE', 'Psychiatric evaluation, suicidal ideation with plan', 3, NULL, NULL, NULL, 'POLICE', NULL, NULL, 1, '2026-02-28 17:19:41', 1, 1),
       (4, 2, NULL, 1, 'ED', '2026-02-28 19:20:10', NULL, NULL, 'ACTIVE', 'Altered mental status, fever, productive cough', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 19:20:10', 1, 1),
       (5, 3, NULL, 1, 'OBS', '2026-02-28 00:20:10', NULL, NULL, 'ACTIVE', 'Substernal chest pressure, r/o ACS', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 00:20:10', 1, 1),
       (6, 4, NULL, 1, 'ED', '2026-02-28 17:20:10', NULL, NULL, 'ACTIVE', 'Psychiatric evaluation, suicidal ideation with plan', 3, NULL, NULL, NULL, 'POLICE', NULL, NULL, 1, '2026-02-28 17:20:10', 1, 1),
       (7, 5, NULL, 1, 'ED', '2026-02-28 21:15:11', NULL, NULL, 'ACTIVE', 'Right ankle pain and swelling after fall', 4, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 21:15:11', 1, 1),
       (8, 6, NULL, 1, 'ED', '2026-02-28 21:55:11', NULL, NULL, 'ACTIVE', 'Acute left-sided weakness and slurred speech — CODE STROKE', 1, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 21:55:11', 1, 1),
       (9, 7, NULL, 1, 'ED', '2026-02-28 20:20:11', NULL, NULL, 'ACTIVE', 'MVA — abdominal pain, hypotension, mechanism of injury', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 20:20:11', 1, 1),
       (10, 8, NULL, 1, 'OBS', '2026-02-28 10:20:11', NULL, NULL, 'ACTIVE', 'COPD exacerbation — increased SOB and productive cough x 3 days', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 10:20:11', 1, 1),
       (11, 9, NULL, 1, 'ED', '2026-02-28 21:35:11', NULL, NULL, 'ACTIVE', 'Fever 103.8°F, dysuria x 2 days — pediatric', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 21:35:11', 1, 1),
       (12, 10, NULL, 1, 'ED', '2026-02-28 20:50:11', NULL, NULL, 'ACTIVE', 'Suspected opioid overdose — unresponsive, Narcan given by EMS', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-28 20:50:11', 1, 1),
       (13, 11, NULL, 1, 'ED', '2026-02-28 14:20:12', NULL, NULL, 'ACTIVE', 'Major depression, passive suicidal ideation — voluntary psych evaluation', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-28 14:20:12', 1, 1),
       (14, 50, NULL, 1, 'AL', '2026-01-12 22:20:13', NULL, NULL, 'ACTIVE', 'Memory care placement — moderate dementia, fall history', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-12 22:20:13', NULL, NULL),
       (15, 51, NULL, 1, 'AL', '2026-01-28 22:20:13', NULL, NULL, 'ACTIVE', 'Post-hip-replacement rehab and long-term care transition', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-01-28 22:20:13', NULL, NULL),
       (16, 52, NULL, 1, 'AL', '2026-02-10 22:20:13', NULL, NULL, 'ACTIVE', 'Independent-living support — COPD management, medication assist', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-10 22:20:13', NULL, NULL),
       (17, 53, NULL, 1, 'AL', '2025-12-28 22:20:13', NULL, NULL, 'ACTIVE', 'Advanced Parkinson''s — mobility and swallow safety needs', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-12-28 22:20:13', NULL, NULL),
       (18, 54, NULL, 1, 'AL', '2026-02-19 22:20:13', NULL, NULL, 'ACTIVE', 'CHF and T2DM — medication management and dietary monitoring', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-19 22:20:13', NULL, NULL),
-- Inpatient episodes
       (19, 2, NULL, 1, 'IP', '2026-02-23 08:30:00', NULL, NULL, 'ACTIVE', 'Acute hypoxic respiratory failure — intubated, now extubated post-day 5', 1, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-23 08:30:00', 1, 1),
       (20, 5, NULL, 1, 'IP', '2026-02-26 14:00:00', NULL, NULL, 'ACTIVE', 'Elective total knee arthroplasty — post-operative Day 2', 2, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-26 14:00:00', 1, 1),
       (21, 6, NULL, 1, 'IP', '2026-02-26 22:10:00', NULL, NULL, 'ACTIVE', 'NSTEMI — post-PCI observation, troponin trending down', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-26 22:10:00', 1, 1),
       (22, 8, NULL, 1, 'IP', '2026-02-24 10:45:00', NULL, NULL, 'ACTIVE', 'Community-acquired pneumonia — IV antibiotics, improving O2 requirement', 3, NULL, NULL, NULL, 'WALKIN', NULL, NULL, 1, '2026-02-24 10:45:00', 1, 1),
       (23, 11, NULL, 1, 'IP', '2026-02-27 16:20:00', NULL, NULL, 'ACTIVE', 'Intertrochanteric hip fracture — post-ORIF Day 1, weight-bearing as tolerated', 2, NULL, NULL, NULL, 'EMS', NULL, NULL, 1, '2026-02-27 16:20:00', 1, 1);

-- oei_triage
CREATE TABLE IF NOT EXISTS `oei_triage`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED NOT NULL,
    `pid`              bigint(20) UNSIGNED NOT NULL,
    `eid`              bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `set_number`       tinyint(3) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=initial triage, 2=re-triage, etc.',
    `bp_systolic`      smallint(5) UNSIGNED         DEFAULT NULL,
    `bp_diastolic`     smallint(5) UNSIGNED         DEFAULT NULL,
    `hr`               smallint(5) UNSIGNED         DEFAULT NULL COMMENT 'bpm',
    `rr`               tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'breaths per minute',
    `temp_f`           decimal(5, 2)                DEFAULT NULL COMMENT 'degrees Fahrenheit',
    `spo2`             tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'percent 0-100',
    `gcs`              tinyint(3) UNSIGNED          DEFAULT NULL COMMENT '3-15',
    `pain_score`       tinyint(3) UNSIGNED          DEFAULT NULL COMMENT '0-10',
    `weight_kg`        decimal(6, 2)                DEFAULT NULL,
    `arrival_mode`     varchar(20)                  DEFAULT NULL COMMENT 'WALKIN|EMS|TRANSFER|POLICE|WHEELCHAIR|STRETCHER',
    `esi_suggested`    tinyint(3) UNSIGNED          DEFAULT NULL COMMENT 'auto-computed from vitals 1-5',
    `notes`            text                         DEFAULT NULL,
    `noted_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `noted_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_triage_episode` (`episode_id`),
    KEY `idx_oei_triage_facility` (`facility_id`, `noted_datetime`),
    KEY `idx_oei_triage_pid` (`pid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Triage vitals sets';

INSERT INTO `oei_triage` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `set_number`, `bp_systolic`, `bp_diastolic`, `hr`, `rr`, `temp_f`, `spo2`, `gcs`, `pain_score`, `weight_kg`, `arrival_mode`, `esi_suggested`, `notes`, `noted_by_user_id`, `noted_datetime`)
VALUES (1, 1, 2, NULL, 1, 1, 88, 58, 122, 26, 102.40, 92, 12, 5, 82.00, 'EMS', 2,
        'Patient confused, combative on arrival. Diaphoretic. Warm, mottled extremities. Family reports 3-day productive cough, chills, progressive confusion. qSOFA: RR 26, GCS < 15, SBP 88. Sepsis bundle initiated.', 1, '2026-02-28 19:24:41'),
       (2, 2, 3, NULL, 1, 1, 154, 94, 98, 16, 98.60, 97, 15, 7, 78.00, 'WALKIN', 3, 'Substernal pressure radiating to left shoulder x 2h. Diaphoresis. Denies SOB. HTN, DM, hyperlipidemia hx. ASA/metformin at home.', 1, '2026-02-28 00:19:41'),
       (3, 2, 3, NULL, 1, 2, 138, 84, 78, 14, 98.20, 99, 15, 3, 78.00, 'WALKIN', 3, 'Re-triage 12h: chest pressure resolved. Ambulating. Tolerating clear diet. Awaiting final troponin and cardiology input.', 1, '2026-02-28 12:19:41'),
       (4, 4, 2, NULL, 1, 1, 88, 58, 122, 26, 102.40, 92, 12, 5, 82.00, 'EMS', 2,
        'Patient confused, combative on arrival. Diaphoretic. Warm, mottled extremities. Family reports 3-day productive cough, chills, progressive confusion. qSOFA: RR 26, GCS < 15, SBP 88. Sepsis bundle initiated.', 1, '2026-02-28 19:25:10'),
       (5, 5, 3, NULL, 1, 1, 154, 94, 98, 16, 98.60, 97, 15, 7, 78.00, 'WALKIN', 3, 'Substernal pressure radiating to left shoulder x 2h. Diaphoresis. Denies SOB. HTN, DM, hyperlipidemia hx. ASA/metformin at home.', 1, '2026-02-28 00:20:10'),
       (6, 5, 3, NULL, 1, 2, 138, 84, 78, 14, 98.20, 99, 15, 3, 78.00, 'WALKIN', 3, 'Re-triage 12h: chest pressure resolved. Ambulating. Tolerating clear diet. Awaiting final troponin and cardiology input.', 1, '2026-02-28 12:20:10'),
       (7, 6, 4, NULL, 1, 1, 128, 80, 90, 14, 98.30, 99, 15, 0, NULL, 'POLICE', 3,
        'Calm and cooperative. Reports SI — "I have a plan and the means." Denies HI. Last EtOH 8h prior. Chronic depression, hx of one prior attempt 2yo. No acute medical complaints. Patient consented to evaluation.', 1, '2026-02-28 17:21:10'),
       (8, 7, 5, NULL, 1, 1, 116, 72, 80, 14, 98.00, 100, 15, 7, 62.00, 'WALKIN', 4, 'Twisted right ankle on stairs 2h ago. Lateral malleolus swelling and bruising. NWB. Neurovascularly intact. Ottawa rules: negative for fracture criteria. Pain 7/10.', 1, '2026-02-28 21:20:11'),
       (9, 8, 6, NULL, 1, 1, 192, 108, 86, 18, 98.80, 96, 13, 0, 89.00, 'EMS', 1, 'Sudden onset left arm and leg weakness with facial droop and dysarthria. Last known well 0930 — 90 min ago. AF on EKG strip by EMS. BP 192/108. GCS 13. Right gaze deviation. Witnessed by wife.', 1,
        '2026-02-28 21:57:11'),
       (10, 9, 7, NULL, 1, 1, 94, 60, 128, 22, 98.10, 95, 14, 9, 70.00, 'EMS', 2, 'Driver, high-speed MVC, airbag, +LOC x 2 min. Complains left upper quadrant pain. C-collar in place per EMS. GCS 14 (E4V4M6). Abdomen tender LUQ, guarding.', 1, '2026-02-28 20:22:11'),
       (11, 10, 8, NULL, 1, 1, 142, 88, 104, 24, 99.10, 88, 15, 3, 88.00, 'WALKIN', 3, 'COPD patient, severe — FEV1 38% predicted at baseline. SOB worse x 3d. Yellow productive cough. On home nebs and Spiriva. SpO2 88% RA, improved to 94% on 2L NC.', 1, '2026-02-28 10:21:11'),
       (12, 10, 8, NULL, 1, 2, 138, 82, 88, 18, 98.80, 96, 15, 1, 88.00, 'WALKIN', 3, 'Re-triage 6h: SpO2 96% 2L NC. HR improved 88. Wheezes markedly decreased. Patient able to speak full sentences. Peak flow 55% → 62% predicted.', 1, '2026-02-28 16:20:11'),
       (13, 11, 9, NULL, 1, 1, 96, 60, 118, 22, 103.80, 99, 15, 4, 23.00, 'WALKIN', 3,
        'Age 7. Fever 103.8°F, onset 24h ago. Dysuria and increased frequency x 2d. Mild suprapubic tenderness. No CVA tenderness. No vomiting. Tolerating PO. Alert and interactive. PMH: none. No allergies.', 1, '2026-02-28 21:38:11'),
       (14, 12, 10, NULL, 1, 1, 108, 68, 96, 10, 97.60, 91, 6, 0, 78.00, 'EMS', 2, 'Found unresponsive bathroom. EMS: pinpoint pupils, shallow respirations RR 6, O2 sat 84%. Narcan 0.4mg IM x2 — GCS improved to 14. Denies substance use when awake. Paraphernalia found at scene.',
        1, '2026-02-28 20:52:12'),
       (15, 13, 11, NULL, 1, 1, 118, 74, 82, 14, 98.20, 100, 15, 0, NULL, 'WALKIN', 3,
        'Voluntary presentation. Reports passive SI — "I don''t want to be here anymore" — but no plan or intent. Depression x 6 months, recently lost job. No psych history, no substances. Calm, cooperative, insightful. Family supportive and present.', 1, '2026-02-28 14:21:12');

-- oei_episode_location
CREATE TABLE IF NOT EXISTS `oei_episode_location`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `location_id`    bigint(20) UNSIGNED DEFAULT NULL,
    `location_code`  varchar(20)         DEFAULT NULL,
    `start_datetime` datetime            NOT NULL,
    `end_datetime`   datetime            DEFAULT NULL,
    `user_id`        bigint(20) UNSIGNED DEFAULT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_ep_loc_episode` (`episode_id`, `start_datetime`),
    KEY `idx_oei_ep_loc_facility` (`facility_id`, `start_datetime`),
    KEY `idx_oei_ep_loc_active` (`facility_id`, `end_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_episode_location` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `location_id`, `location_code`, `start_datetime`, `end_datetime`, `user_id`, `note`)
VALUES (1, 1, 2, NULL, 1, 1, 'ED01', '2026-02-28 19:27:41', NULL, 1, 'Sepsis workup'),
       (2, 2, 3, NULL, 1, 11, 'OBS1', '2026-02-28 00:19:41', NULL, 1, 'OBS admission'),
       (3, 4, 2, NULL, 1, 1, 'ED01', '2026-02-28 19:28:10', NULL, 1, 'Sepsis workup'),
       (4, 5, 3, NULL, 1, 11, 'OBS1', '2026-02-28 00:20:10', NULL, 1, 'OBS admission'),
       (5, 6, 4, NULL, 1, 17, 'PSY1', '2026-02-28 17:25:10', NULL, 1, 'BH holding'),
       (6, 7, 5, NULL, 1, 2, 'ED02', '2026-02-28 21:25:11', NULL, 1, 'Ankle injury'),
       (7, 8, 6, NULL, 1, 9, 'TR01', '2026-02-28 21:58:11', NULL, 1, 'Code Stroke'),
       (8, 9, 7, NULL, 1, 10, 'TR02', '2026-02-28 20:23:11', NULL, 1, 'Trauma activation'),
       (9, 10, 8, NULL, 1, 12, 'OBS2', '2026-02-28 10:25:11', NULL, 1, 'COPD OBS'),
       (10, 11, 9, NULL, 1, 3, 'ED03', '2026-02-28 21:42:11', NULL, 1, 'Peds fever'),
       (11, 12, 10, NULL, 1, 4, 'ED04', '2026-02-28 20:54:11', NULL, 1, 'Opioid OD monitoring'),
       (12, 13, 11, NULL, 1, 15, 'HALL1', '2026-02-28 14:30:12', NULL, 1, 'BH boarding — capacity constraint'),
       (13, 14, 50, NULL, 1, NULL, 'A-101', '2026-01-12 22:20:13', NULL, 1, 'Wing A Room 101 — memory care'),
       (14, 15, 51, NULL, 1, NULL, 'A-104', '2026-01-28 22:20:13', NULL, 1, 'Wing A Room 104 — rehab'),
       (15, 16, 52, NULL, 1, NULL, 'A-108', '2026-02-10 22:20:13', NULL, 1, 'Wing A Room 108 — standard AL'),
       (16, 17, 53, NULL, 1, NULL, 'B-201', '2025-12-28 22:20:13', NULL, 1, 'Wing B Room 201 — high acuity'),
       (17, 18, 54, NULL, 1, NULL, 'B-205', '2026-02-19 22:20:13', NULL, 1, 'Wing B Room 205 — cardiac monitoring');

INSERT INTO `oei_episode_location` (`id`, `episode_id`, `pid`, `eid`, `facility_id`,
                                    `location_id`, `location_code`, `start_datetime`, `end_datetime`, `user_id`, `note`)
VALUES (50, 19, 2, NULL, 1, 20, 'ICU01', '2026-02-23 09:00:00', NULL, 1, 'ICU admission — respiratory failure'),
       (51, 20, 5, NULL, 1, 23, 'MS401', '2026-02-26 15:00:00', NULL, 1, 'Post-op knee arthroplasty'),
       (52, 21, 6, NULL, 1, 26, 'TEL03', '2026-02-26 23:00:00', NULL, 1, 'Post-cath telemetry monitoring'),
       (53, 22, 8, NULL, 1, 25, 'MS403', '2026-02-24 12:00:00', NULL, 1, 'CAP — IV antibiotics'),
       (54, 23, 11, NULL, 1, 28, 'ORT01', '2026-02-27 18:00:00', NULL, 1, 'Post-ORIF hip fracture');

-- oei_patient_location_history
CREATE TABLE IF NOT EXISTS `oei_patient_location_history`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `episode_id`     bigint(20) UNSIGNED DEFAULT NULL,
    `location_id`    bigint(20) UNSIGNED DEFAULT NULL,
    `start_datetime` datetime            NOT NULL,
    `end_datetime`   datetime            DEFAULT NULL,
    `reason`         varchar(30)         NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_loc_hist_pid` (`pid`, `start_datetime`),
    KEY `idx_oei_loc_hist_episode` (`episode_id`, `start_datetime`),
    KEY `idx_oei_loc_hist_location` (`location_id`, `start_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

-- oei_episode_status_history
CREATE TABLE IF NOT EXISTS `oei_episode_status_history`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `status_code`    varchar(30)         NOT NULL,
    `set_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `set_datetime`   datetime            NOT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_episode_status_episode` (`episode_id`, `set_datetime`),
    KEY `idx_oei_episode_status_code` (`status_code`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_episode_status_history` (`id`, `episode_id`, `status_code`, `set_by_user_id`, `set_datetime`, `note`)
VALUES (1, 1, 'ARRIVE', 1, '2026-02-28 19:19:41', 'Arrived by EMS. Diaphoretic and confused.'),
       (2, 1, 'TRIAGE', 1, '2026-02-28 19:24:41', 'ESI-2 assigned. qSOFA 3/3.'),
       (3, 1, 'ROOMED', 1, '2026-02-28 19:27:41', 'Placed ED Room 1'),
       (4, 1, 'SEPSIS_ALERT', 1, '2026-02-28 19:29:41', 'Sepsis protocol activated — bundle initiated'),
       (5, 1, 'WITH_PROVIDER', 1, '2026-02-28 19:34:41', 'Provider at bedside. Sepsis workup ordered.'),
       (6, 2, 'ARRIVE', 1, '2026-02-28 00:19:41', 'Walk-in. Chest pain onset 2h prior.'),
       (7, 2, 'TRIAGE', 1, '2026-02-28 00:20:41', 'ESI-3. TIMI 3/7. No STEMI on EKG.'),
       (8, 2, 'ROOMED', 1, '2026-02-28 00:24:41', 'OBS Bay 1 — continuous telemetry'),
       (9, 2, 'OBS_START', 1, '2026-02-28 02:19:41', 'Obs status initiated. Chest pain protocol.'),
       (10, 2, 'PENDING_RESULTS', 1, '2026-02-28 14:19:41', 'Awaiting final troponin and cardiology recommendation.'),
       (11, 3, 'ARRIVE', 1, '2026-02-28 17:19:41', 'Police escort. Cooperative. No acute injury.'),
       (12, 3, 'TRIAGE', 1, '2026-02-28 17:20:41', 'ESI-3 BH. Passive SI with plan. Sitter assigned.'),
       (13, 3, 'ROOMED', 1, '2026-02-28 17:24:41', 'BH Room 1 — ligature-reduced. 1:1 sitter.'),
       (14, 3, 'WAITING', 1, '2026-02-28 17:39:41', 'Awaiting BH placement. Calls in progress.'),
       (15, 4, 'ARRIVE', 1, '2026-02-28 19:20:10', 'Arrived by EMS. Diaphoretic and confused.'),
       (16, 4, 'TRIAGE', 1, '2026-02-28 19:25:10', 'ESI-2 assigned. qSOFA 3/3.'),
       (17, 4, 'ROOMED', 1, '2026-02-28 19:28:10', 'Placed ED Room 1'),
       (18, 4, 'SEPSIS_ALERT', 1, '2026-02-28 19:30:10', 'Sepsis protocol activated — bundle initiated'),
       (19, 4, 'WITH_PROVIDER', 1, '2026-02-28 19:35:10', 'Provider at bedside. Sepsis workup ordered.'),
       (20, 5, 'ARRIVE', 1, '2026-02-28 00:20:10', 'Walk-in. Chest pain onset 2h prior.'),
       (21, 5, 'TRIAGE', 1, '2026-02-28 00:21:10', 'ESI-3. TIMI 3/7. No STEMI on EKG.'),
       (22, 5, 'ROOMED', 1, '2026-02-28 00:25:10', 'OBS Bay 1 — continuous telemetry'),
       (23, 5, 'OBS_START', 1, '2026-02-28 02:20:10', 'Obs status initiated. Chest pain protocol.'),
       (24, 5, 'PENDING_RESULTS', 1, '2026-02-28 14:20:10', 'Awaiting final troponin and cardiology recommendation.'),
       (25, 6, 'ARRIVE', 1, '2026-02-28 17:20:10', 'Police escort. Cooperative. No acute injury.'),
       (26, 6, 'TRIAGE', 1, '2026-02-28 17:21:10', 'ESI-3 BH. Passive SI with plan. Sitter assigned.'),
       (27, 6, 'ROOMED', 1, '2026-02-28 17:25:10', 'BH Room 1 — ligature-reduced. 1:1 sitter.'),
       (28, 6, 'WAITING', 1, '2026-02-28 17:40:10', 'Awaiting BH placement. Calls in progress.'),
       (29, 7, 'ARRIVE', 1, '2026-02-28 21:15:11', 'Walk-in — hopped to desk'),
       (30, 7, 'TRIAGE', 1, '2026-02-28 21:20:11', 'ESI-4. Right ankle swelling, NWB.'),
       (31, 7, 'ROOMED', 1, '2026-02-28 21:25:11', 'ED Room 2'),
       (32, 7, 'WITH_PROVIDER', 1, '2026-02-28 21:40:11', 'Provider evaluation complete — lateral sprain'),
       (33, 7, 'READY_DISCHARGE', 1, '2026-02-28 22:15:11', 'Imaging reviewed, pain controlled, discharge instructions printed'),
       (34, 8, 'ARRIVE', 1, '2026-02-28 21:55:11', 'EMS arrival — Code Stroke activated overhead'),
       (35, 8, 'TRIAGE', 1, '2026-02-28 21:57:11', 'ESI-1. Last known well 90 min ago. LKW within tPA window.'),
       (36, 8, 'ROOMED', 1, '2026-02-28 21:58:11', 'Trauma Bay 1 — stroke team assembled'),
       (37, 8, 'WITH_PROVIDER', 1, '2026-02-28 21:59:11', 'Neurology at bedside. NIHSS assessment in progress.'),
       (38, 9, 'ARRIVE', 1, '2026-02-28 20:20:11', 'EMS arrival. Mechanism: 60mph MVC restrained driver, airbag deployed, +LOC x 2 min.'),
       (39, 9, 'TRIAGE', 1, '2026-02-28 20:22:11', 'ESI-2. BP 96/62, HR 124, abdominal guarding.'),
       (40, 9, 'ROOMED', 1, '2026-02-28 20:23:11', 'Trauma Bay 2 — trauma team activated'),
       (41, 9, 'WITH_PROVIDER', 1, '2026-02-28 20:25:11', 'Trauma team evaluation complete. CT abdomen positive.'),
       (42, 9, 'PENDING_TRANSFER', 1, '2026-02-28 21:50:11', 'Transfer accepted. Awaiting transport unit.'),
       (43, 10, 'ARRIVE', 1, '2026-02-28 10:20:11', 'Walk-in. SOB worsening x3 days. Smoking hx 40 pack-years.'),
       (44, 10, 'TRIAGE', 1, '2026-02-28 10:21:11', 'ESI-3. SpO2 88% RA, wheezing bilateral.'),
       (45, 10, 'ROOMED', 1, '2026-02-28 10:25:11', 'OBS Bay 2 — oxygen 2L NC, albuterol neb started'),
       (46, 10, 'OBS_START', 1, '2026-02-28 11:20:11', 'Observation status. COPD exacerbation protocol.'),
       (47, 10, 'PENDING_RESULTS', 1, '2026-02-28 20:20:11', 'Peak flow 62% predicted — improving. Discharge planning.'),
       (48, 11, 'ARRIVE', 1, '2026-02-28 21:35:11', 'Parent brought in. Fever 103.8°F at home x 24h. Dysuria.'),
       (49, 11, 'TRIAGE', 1, '2026-02-28 21:38:11', 'ESI-3. Alert, ill-appearing. Temp 103.8°F.'),
       (50, 11, 'ROOMED', 1, '2026-02-28 21:42:11', 'ED Room 3 — pediatric setup'),
       (51, 11, 'WITH_PROVIDER', 1, '2026-02-28 22:00:11', 'Provider evaluation: UTI suspected. Labs ordered.'),
       (52, 12, 'ARRIVE', 1, '2026-02-28 20:50:11', 'EMS — found unresponsive in public bathroom. Narcan 0.4mg IM x2 in field. GCS 6 → 14 post Narcan.'),
       (53, 12, 'TRIAGE', 1, '2026-02-28 20:52:11', 'ESI-2. Alert, agitated. Pinpoint pupils. Naloxone drip started.'),
       (54, 12, 'ROOMED', 1, '2026-02-28 20:54:11', 'ED Room 4. Wrist restraints applied — patient combative.'),
       (55, 12, 'WITH_PROVIDER', 1, '2026-02-28 21:00:11', 'Provider evaluation. Urine tox + opiates. BH consult ordered.'),
       (56, 13, 'ARRIVE', 1, '2026-02-28 14:20:12', 'Self-presented. Reports passive SI, unable to contract for safety. Supportive family present.'),
       (57, 13, 'TRIAGE', 1, '2026-02-28 14:21:12', 'ESI-3. No acute medical complaints. Calm and cooperative.'),
       (58, 13, 'WAITING', 1, '2026-02-28 14:25:12', 'Waiting — PSY1 and PSY2 both occupied. Placed in hallway.'),
       (59, 13, 'PLACED_IN_HALL', 1, '2026-02-28 14:30:12', 'Hallway Bed 1 — sitter assigned. Privacy curtain.'),
       (60, 13, 'PLACEMENT_ACCEPTED', 1, '2026-02-28 20:50:12', 'Valley BH accepted. Transport ETA 45-60 min.'),
       (61, 14, 'ADMIT', 1, '2026-01-12 22:20:13', 'Memory care admission — escorted from daughter''s vehicle'),
       (62, 14, 'ACTIVE', 1, '2026-01-12 23:00:00', 'Orientation complete. Care plan initiated. Bed alarm activated.'),
       (63, 15, 'ADMIT', 1, '2026-01-28 22:20:13', 'SNF transfer — post right hip arthroplasty day 12'),
       (64, 15, 'ACTIVE', 1, '2026-01-28 23:00:00', 'PT evaluation completed. Gait training protocol started.'),
       (65, 16, 'ADMIT', 1, '2026-02-10 22:20:13', 'Self-referral — COPD exacerbation history, requests medication assistance'),
       (66, 16, 'ACTIVE', 1, '2026-02-10 22:45:00', 'Baseline SpO2 94% RA. Inhaler schedule established.'),
       (67, 17, 'ADMIT', 1, '2025-12-28 22:20:13', 'Family placement — advanced Parkinson''s, caregiver burnout'),
       (68, 17, 'ACTIVE', 1, '2025-12-28 23:30:00', 'Dysphagia assessment complete. Thickened liquids ordered. Fall protocol active.'),
       (69, 18, 'ADMIT', 1, '2026-02-19 22:20:13', 'Cardiology referral — CHF NYHA Class II, T2DM poorly controlled'),
       (70, 18, 'ACTIVE', 1, '2026-02-19 23:00:00', 'Baseline weight 140.2 lbs. FBG 214. Fluid restriction and insulin sliding scale started.');

INSERT INTO `oei_episode_status_history` (`id`, `episode_id`, `status_code`, `set_by_user_id`, `set_datetime`, `note`)
VALUES (71, 19, 'ADMITTED', 1, '2026-02-23 08:30:00', 'Emergency admit — acute hypoxic resp failure, intubated in ED'),
       (72, 19, 'INTUBATED', 1, '2026-02-23 09:00:00', 'Oral intubation — 7.5 ETT at 23 cm'),
       (73, 19, 'WEANING', 1, '2026-02-26 08:00:00', 'SBT passed — plan extubation today'),
       (74, 19, 'EXTUBATED', 1, '2026-02-26 14:00:00', 'Successful extubation — SpO2 95% on 4L NC'),
       (75, 19, 'ACTIVE', 1, '2026-02-27 00:00:00', 'Transferred to step-down — tolerating 2L NC'),
       (76, 20, 'ADMITTED', 1, '2026-02-26 14:00:00', 'Elective admission — L TKA, spinal anesthesia, OR time 1h 42min'),
       (77, 20, 'POST_OP', 1, '2026-02-26 17:00:00', 'Recovered to floor — pain 6/10, SpO2 97% RA'),
       (78, 20, 'PT_PROGRESSING', 1, '2026-02-27 10:00:00', 'Ambulated 25 ft with walker Day 1 post-op, flexion 85 degrees'),
       (79, 20, 'DISCHARGE_PLANNING', 1, '2026-02-28 08:00:00', 'PT cleared for home with walker, home health PT arranged'),
       (80, 21, 'ADMITTED', 1, '2026-02-26 22:10:00', 'Urgent admit — NSTEMI, troponin 4.2 on arrival'),
       (81, 21, 'CATH_LAB', 1, '2026-02-27 07:30:00', 'Taken to cath lab — LAD 90% stenosis, DES placed'),
       (82, 21, 'POST_PCI', 1, '2026-02-27 11:00:00', 'Post-PCI to telemetry — stable sinus rhythm, troponin trending down'),
       (83, 21, 'DISCHARGE_PLANNING', 1, '2026-02-28 09:00:00', 'Cardiology: OK for discharge tomorrow, DAPT education completed'),
       (84, 22, 'ADMITTED', 1, '2026-02-24 10:45:00', 'Emergency admit — SpO2 84% RA, RR 28, temp 39.2 C, CAP diagnosed'),
       (85, 22, 'IV_ANTIBIOTICS', 1, '2026-02-24 12:00:00', 'IV ceftriaxone + azithromycin started, O2 3L NC SpO2 92%'),
       (86, 22, 'IMPROVING', 1, '2026-02-26 08:00:00', 'O2 weaned to 1L, afebrile x24h, WBC 11.2'),
       (87, 22, 'DISCHARGE_PLANNING', 1, '2026-02-28 10:00:00', 'Transition to PO antibiotics today, plan discharge tomorrow'),
       (88, 23, 'ADMITTED', 1, '2026-02-27 16:20:00', 'Emergency admit — R intertrochanteric fx, mechanism: fall at home'),
       (89, 23, 'PRE_OP', 1, '2026-02-27 18:00:00', 'Pre-op completed — H&P done, consent signed, OR tomorrow 0700'),
       (90, 23, 'POST_OP', 1, '2026-02-28 11:00:00', 'ORIF completed — 75 min OR time, EBL 250 mL, Hgb 9.2'),
       (91, 23, 'WEIGHT_BEARING', 1, '2026-02-28 15:00:00', 'PT cleared for WBAT with walker, ambulated 15 ft, pain 5/10');

-- oei_episode_event
CREATE TABLE IF NOT EXISTS `oei_episode_event`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `eid`            bigint(20) UNSIGNED DEFAULT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `event_type`     varchar(40)         NOT NULL,
    `event_datetime` datetime            NOT NULL,
    `user_id`        bigint(20) UNSIGNED DEFAULT NULL,
    `note`           varchar(255)        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_evt_episode` (`episode_id`, `event_type`, `event_datetime`),
    KEY `idx_oei_evt_facility` (`facility_id`, `event_type`, `event_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_episode_event` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES (1, 1, 2, NULL, 1, 'ARRIVE', '2026-02-28 19:19:41', 1, 'EMS arrival — altered, febrile'),
       (2, 1, 2, NULL, 1, 'ROOM', '2026-02-28 19:27:41', 1, 'ED Room 1'),
       (3, 1, 2, NULL, 1, 'PROVIDER', '2026-02-28 19:34:41', 1, 'Sepsis workup initiated'),
       (4, 2, 3, NULL, 1, 'ARRIVE', '2026-02-28 00:19:41', 1, 'Walk-in, self-transport'),
       (5, 2, 3, NULL, 1, 'ROOM', '2026-02-28 00:24:41', 1, 'OBS Bay 1'),
       (6, 2, 3, NULL, 1, 'OBS_START', '2026-02-28 02:19:41', 1, 'Chest pain obs protocol'),
       (7, 3, 4, NULL, 1, 'ARRIVE', '2026-02-28 17:19:41', 1, 'Police escort — public safety call'),
       (8, 3, 4, NULL, 1, 'ROOM', '2026-02-28 17:24:41', 1, 'BH Room 1'),
       (9, 4, 2, NULL, 1, 'ARRIVE', '2026-02-28 19:20:10', 1, 'EMS arrival — altered, febrile'),
       (10, 4, 2, NULL, 1, 'ROOM', '2026-02-28 19:28:10', 1, 'ED Room 1'),
       (11, 4, 2, NULL, 1, 'PROVIDER', '2026-02-28 19:35:10', 1, 'Sepsis workup initiated'),
       (12, 5, 3, NULL, 1, 'ARRIVE', '2026-02-28 00:20:10', 1, 'Walk-in, self-transport'),
       (13, 5, 3, NULL, 1, 'ROOM', '2026-02-28 00:25:10', 1, 'OBS Bay 1'),
       (14, 5, 3, NULL, 1, 'OBS_START', '2026-02-28 02:20:10', 1, 'Chest pain obs protocol'),
       (15, 6, 4, NULL, 1, 'ARRIVE', '2026-02-28 17:20:10', 1, 'Police escort — public safety call'),
       (16, 6, 4, NULL, 1, 'ROOM', '2026-02-28 17:25:10', 1, 'BH Room 1'),
       (17, 8, 6, NULL, 1, 'ARRIVE', '2026-02-28 21:55:11', 1, 'EMS STAT — Code Stroke overhead'),
       (18, 8, 6, NULL, 1, 'ROOM', '2026-02-28 21:58:11', 1, 'Trauma Bay 1'),
       (19, 8, 6, NULL, 1, 'PROVIDER', '2026-02-28 21:59:11', 1, 'Neurology and ED team at bedside'),
       (20, 4, 2, NULL, 1, 'ARRIVAL', '2026-02-28 19:20:12', 1, 'EMS arrival. Altered mental status, fever 103.8°F, rigors.'),
       (21, 4, 2, NULL, 1, 'TRIAGE', '2026-02-28 19:22:12', 1, 'ESI-2. qSOFA score 3. Sepsis protocol activated.'),
       (22, 4, 2, NULL, 1, 'ROOMED', '2026-02-28 19:25:12', 1, 'ED Room 1 — isolation precautions, cultures ordered.'),
       (23, 4, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 19:35:12', 1, '2 sets peripheral blood cultures drawn prior to antibiotics.'),
       (24, 4, 2, NULL, 1, 'ANTIBIOTIC', '2026-02-28 19:45:12', 1, 'Vancomycin 25mg/kg and Pip-Tazo 4.5g IV started. SEP-1 bundle T+0.'),
       (25, 4, 2, NULL, 1, 'LACTATE', '2026-02-28 19:50:12', 1, 'Lactate 4.1 mmol/L — septic shock criteria met. IVF bolus started.'),
       (26, 4, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:55:12', 1, '30mL/kg NS bolus initiated. Running at 150mL/hr.'),
       (27, 4, 2, NULL, 1, 'LAB_RESULT', '2026-02-28 20:20:12', 1, 'WBC 18.4, Procalcitonin 22.6. Repeat lactate ordered.'),
       (28, 4, 2, NULL, 1, 'IMAGING', '2026-02-28 20:30:12', 1, 'CXR: RLL consolidation consistent with pneumonia.'),
       (29, 4, 2, NULL, 1, 'REASSESSMENT', '2026-02-28 21:20:12', 1, 'MAP improved 58→68 after 2L NS. Patient more alert. Repeat lactate 2.8.'),
       (30, 4, 2, NULL, 1, 'DISPOSITION', '2026-02-28 22:00:12', 1, 'Admit to ICU. ICU accepting critical holds only — bed pending.'),
       (31, 5, 3, NULL, 1, 'ARRIVAL', '2026-02-28 00:20:12', 1, 'Walk-in. Central chest pressure 8/10 with exertion, relieved at rest.'),
       (32, 5, 3, NULL, 1, 'TRIAGE', '2026-02-28 00:30:12', 1, 'ESI-3. ACS pathway activated. Aspirin 325mg given.'),
       (33, 5, 3, NULL, 1, 'EKG', '2026-02-28 00:40:12', 1, '12-lead EKG: normal sinus, no ST changes. Serial troponin ordered.'),
       (34, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 01:20:12', 1, 'Troponin T: 0.004 ng/mL (negative). Repeat in 3h.'),
       (35, 5, 3, NULL, 1, 'OBS_START', '2026-02-28 02:20:12', 1, 'Admitted to observation. OBS chest pain protocol started.'),
       (36, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 04:20:12', 1, 'Repeat troponin 3h: 0.006 ng/mL — still negative. Third draw scheduled.'),
       (37, 5, 3, NULL, 1, 'CARDIOLOGY', '2026-02-28 08:20:12', 1, 'Cardiology consult seen. Recommends stress test before discharge.'),
       (38, 5, 3, NULL, 1, 'LAB_RESULT', '2026-02-28 04:20:12', 1, 'Repeat troponin 6h: 0.008 ng/mL — trending stable. No MI.'),
       (39, 5, 3, NULL, 1, 'IMAGING', '2026-02-28 18:20:12', 1, 'Treadmill stress test: negative. 8 METs achieved, no symptoms.'),
       (40, 5, 3, NULL, 1, 'DISPOSITION', '2026-02-28 20:20:12', 1, 'Discharge planned — cardiology agrees, low-risk unstable angina. Aspirin/Statin started.'),
       (41, 6, 4, NULL, 1, 'ARRIVAL', '2026-02-28 17:20:12', 1, 'Police bring-in. Reported suicidal ideation with plan — medication ingestion attempt denied by patient.'),
       (42, 6, 4, NULL, 1, 'TRIAGE', '2026-02-28 17:22:12', 1, 'ESI-3. No acute medical complaint. Calm, guarded. Toxicology ordered.'),
       (43, 6, 4, NULL, 1, 'MEDICAL_CLEARANCE', '2026-02-28 18:20:12', 1, 'Tox screen negative. BMP normal. Medically cleared for psych eval.'),
       (44, 6, 4, NULL, 1, 'BH_SCREEN', '2026-02-28 18:40:12', 1, 'Columbia SSRS: HIGH risk — specific plan, access to means.'),
       (45, 6, 4, NULL, 1, 'INVOLUNTARY', '2026-02-28 18:50:12', 1, 'Involuntary hold placed — physician certification completed.'),
       (46, 6, 4, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 19:20:12', 1, 'Placement calls: Valley BH declined (full). State Hospital on hold.'),
       (47, 6, 4, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 20:50:12', 1, 'Second placement call: Riverside BH — reviewing. No answer x2.'),
       (48, 8, 6, NULL, 1, 'ARRIVAL', '2026-02-28 21:55:12', 1, 'EMS — last known well 35 minutes ago. Right-sided weakness, aphasia.'),
       (49, 8, 6, NULL, 1, 'STROKE_ALERT', '2026-02-28 21:56:12', 1, 'STROKE ALERT activated. Neurology notified. Door-to-CT clock started.'),
       (50, 8, 6, NULL, 1, 'NIHSS', '2026-02-28 21:58:12', 1, 'NIHSS score: 14 (severe). Right arm and leg weakness, expressive aphasia.'),
       (51, 8, 6, NULL, 1, 'IMAGING', '2026-02-28 22:02:12', 1, 'CT head: no hemorrhage. CT angio ordered.'),
       (52, 8, 6, NULL, 1, 'IMAGING', '2026-02-28 22:10:12', 1, 'CT angio: M1 occlusion left MCA — thrombectomy candidate.'),
       (53, 8, 6, NULL, 1, 'TPA_DISCUSSION', '2026-02-28 22:15:12', 1, 'tPA held — LKW 35min, within window but thrombectomy preferred given M1 occlusion.'),
       (54, 9, 7, NULL, 1, 'ARRIVAL', '2026-02-28 20:20:12', 1, 'EMS — MVC 60mph, restrained driver, airbag, +LOC x2min.'),
       (55, 9, 7, NULL, 1, 'TRAUMA_ALERT', '2026-02-28 20:21:12', 1, 'Trauma team activation. Mechanism: high-speed MVC.'),
       (56, 9, 7, NULL, 1, 'FAST_EXAM', '2026-02-28 20:25:12', 1, 'FAST positive — free fluid LUQ and pelvis.'),
       (57, 9, 7, NULL, 1, 'BLOOD_BANK', '2026-02-28 20:28:12', 1, 'MTP activated. O-neg x2 released. Type and cross sent.'),
       (58, 9, 7, NULL, 1, 'MEDICATION', '2026-02-28 20:34:12', 1, 'TXA 1g load given. Permissive hypotension strategy — target SBP 80-90.'),
       (59, 9, 7, NULL, 1, 'IMAGING', '2026-02-28 20:50:12', 1, 'CT abdomen: Grade III splenic laceration, active extravasation.'),
       (60, 9, 7, NULL, 1, 'CONSULT', '2026-02-28 21:00:12', 1, 'Surgical consult: OR on standby. Transfer to Level I Trauma preferred.'),
       (61, 9, 7, NULL, 1, 'TRANSFER_ACCEPT', '2026-02-28 21:50:12', 1, 'Regional Trauma Center accepted. Medic 7 transport ETA 15 min.'),
       (62, 10, 8, NULL, 1, 'ARRIVAL', '2026-02-28 10:20:12', 1, 'Walk-in. Productive cough, increasing SOB x 3 days. SpO2 88% on RA.'),
       (63, 10, 8, NULL, 1, 'MEDICATION', '2026-02-28 10:25:12', 1, 'Albuterol neb started. Ipratropium added. O2 2L NC.'),
       (64, 10, 8, NULL, 1, 'OBS_START', '2026-02-28 11:20:12', 1, 'COPD exacerbation protocol. Target SpO2 > 92%.'),
       (65, 10, 8, NULL, 1, 'LAB_RESULT', '2026-02-28 12:20:12', 1, 'ABG: pH 7.38, pCO2 52, compensated. No acute respiratory failure.'),
       (66, 10, 8, NULL, 1, 'REASSESSMENT', '2026-02-28 16:20:12', 1, 'Peak flow 55% predicted. SpO2 96% on 2L. Improving.'),
       (67, 10, 8, NULL, 1, 'REASSESSMENT', '2026-02-28 21:20:12', 1, 'Peak flow 62% predicted. Meets discharge criteria.'),
       (68, 10, 8, NULL, 1, 'REFERRAL', '2026-02-28 21:50:12', 1, 'E-Referral sent to Home Health for neb management and follow-up.'),
       (69, 12, 10, NULL, 1, 'ARRIVAL', '2026-02-28 20:50:12', 1, 'EMS — found unresponsive. Narcan 0.4mg IM x2 in field.'),
       (70, 12, 10, NULL, 1, 'MEDICATION', '2026-02-28 20:58:12', 1, 'Naloxone infusion 0.4mg/hr started. RR 8→14 at 15min.'),
       (71, 12, 10, NULL, 1, 'RESTRAINT', '2026-02-28 21:02:12', 1, 'Wrist restraints — patient combative post-reversal. Order documented.'),
       (72, 12, 10, NULL, 1, 'LAB_RESULT', '2026-02-28 21:20:12', 1, 'Urine tox: opiates positive. Benzo negative. BAL 0.'),
       (73, 12, 10, NULL, 1, 'REASSESSMENT', '2026-02-28 21:50:12', 1, 'GCS 15. Cooperative. Re-sedation risk continues — half-life monitoring.'),
       (74, 13, 11, NULL, 1, 'ARRIVAL', '2026-02-28 14:20:12', 1, 'Self-presented. Passive SI — "I don''t want to be here." No plan.'),
       (75, 13, 11, NULL, 1, 'BH_SCREEN', '2026-02-28 15:20:12', 1, 'Columbia SSRS: moderate risk. Crisis counselor evaluation complete.'),
       (76, 13, 11, NULL, 1, 'EMTALA', '2026-02-28 15:20:12', 1, 'MSE complete. EMTALA compliant. Psychiatric determination documented.'),
       (77, 13, 11, NULL, 1, 'PLACEMENT_CALL', '2026-02-28 16:20:12', 1, 'Valley BH, Riverside BH, State Hospital called. Valley BH reviewing.'),
       (78, 13, 11, NULL, 1, 'PLACEMENT_ACCEPT', '2026-02-28 20:50:12', 1, 'Valley BH accepted — unit 3B. Transport Medvan dispatched.'),
       (79, 13, 11, NULL, 1, 'TRANSPORT', '2026-02-28 22:05:12', 1, 'Medvan ETA 15 minutes. Patient notified. Family present.'),
       (80, 14, 50, NULL, 1, 'ARRIVAL', '2026-01-12 22:20:13', 1, 'Admitted from home — daughter escort. Moderate dementia, MMSE 14/30. Fall x2 in prior 6 months.'),
       (81, 14, 50, NULL, 1, 'CARE_PLAN_INIT', '2026-01-13 10:00:00', 1, 'Interdisciplinary care plan meeting. Goals: fall prevention, orientation, behavioral management.'),
       (82, 14, 50, NULL, 1, 'INCIDENT', '2026-01-28 07:45:00', 1, 'Near-fall during AM transfer. No injury. Bed alarm adjusted. PT notified.'),
       (83, 14, 50, NULL, 1, 'CARE_PLAN_REVIEW', '2026-02-12 10:00:00', 1, '30-day review. Morse score stable at 78. Music therapy showing behavioral improvement.'),
       (84, 15, 51, NULL, 1, 'ARRIVAL', '2026-01-28 22:20:13', 1, 'SNF transfer day 12 post right hip arthroplasty. Pain 6/10. Full WB with walker tolerated.'),
       (85, 15, 51, NULL, 1, 'PT_SESSION', '2026-01-30 09:00:00', 1, 'Initial PT assessment. 20 ft ambulation with walker. Hip flexion 65°. Strengthening protocol started.'),
       (86, 15, 51, NULL, 1, 'PT_SESSION', '2026-02-06 09:00:00', 1, 'Week 2 PT. Ambulation 50 ft. Pain 4/10 post-session. Progressing on schedule.'),
       (87, 15, 51, NULL, 1, 'PT_SESSION', '2026-02-20 09:00:00', 1, 'Week 4 PT. Ambulation 100 ft with walker. Pain 3/10. Opioid taper initiated.'),
       (88, 16, 52, NULL, 1, 'ARRIVAL', '2026-02-10 22:20:13', 1, 'Self-referral. COPD GOLD Stage 2. SpO2 94% RA. Inhaler non-adherence at home.'),
       (89, 16, 52, NULL, 1, 'VITALS', '2026-02-11 08:00:00', 1, 'SpO2 95% RA. HR 78. BP 128/76. Inhalers administered per schedule. Tolerating well.'),
       (90, 16, 52, NULL, 1, 'VITALS', '2026-02-21 08:00:00', 1, 'SpO2 95% RA. Adherence 100% past 10 days. No exacerbation symptoms.'),
       (91, 17, 53, NULL, 1, 'ARRIVAL', '2025-12-28 22:20:13', 1, 'Family placement — advanced Parkinson''s Hoehn & Yahr Stage 3. Dysphagia confirmed. Fall x2/month prior.'),
       (92, 17, 53, NULL, 1, 'INCIDENT', '2026-02-28 06:15:00', 1, 'Unwitnessed fall beside bed. Alert x2. 2cm forearm laceration. X-ray ordered. PT suspended.'),
       (93, 17, 53, NULL, 1, 'CARE_PLAN_REVIEW', '2026-01-28 10:00:00', 1, '30-day review. Zero aspirations. Fall count: 1 (down from 2/month). Mobility protocol adjusted.'),
       (94, 17, 53, NULL, 1, 'MEDICATION', '2026-02-01 07:00:00', 1, 'Carbidopa/levodopa 7:00 AM — within 30-min window. On-period good response noted by aide.'),
       (95, 18, 54, NULL, 1, 'ARRIVAL', '2026-02-19 22:20:13', 1, 'Cardiology referral. CHF NYHA Class II. T2DM HbA1c 9.2%. Baseline weight 140.2 lbs.'),
       (96, 18, 54, NULL, 1, 'WEIGHT_CHECK', '2026-02-22 07:30:00', 1, 'Weight 141.0 lbs (+0.8 lbs). Within threshold. FBG 178. Insulin administered.'),
       (97, 18, 54, NULL, 1, 'WEIGHT_CHECK', '2026-02-27 07:30:00', 1, 'Weight 143.0 lbs (+2.8 lbs over baseline). Provider notified. Furosemide dose reviewed.'),
       (98, 18, 54, NULL, 1, 'INCIDENT', '2026-02-20 14:00:00', 1, 'Medication error — furosemide 40mg given instead of 20mg. No adverse effects. Pharmacy notified. Protocol update initiated.');

INSERT INTO `oei_episode_event` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES (99, 19, 2, NULL, 1, 'ADMIT', '2026-02-23 08:30:00', 1, 'ICU admission — acute hypoxic respiratory failure, intubated ED bay 3'),
       (100, 19, 2, NULL, 1, 'VITALS', '2026-02-23 09:00:00', 1, 'Post-intubation: BP 128/74 HR 92 SpO2 98% FiO2 60% PEEP 8 TV 450'),
       (101, 19, 2, NULL, 1, 'LAB', '2026-02-23 10:30:00', 1, 'ABG: pH 7.38 pCO2 42 pO2 128 HCO3 24 — adequate ventilation'),
       (102, 19, 2, NULL, 1, 'DISCHARGE_PLANNED', '2026-02-28 08:00:00', 1, 'Step-down to ward planned — expected discharge in 2 days'),
       (103, 20, 5, NULL, 1, 'ADMIT', '2026-02-26 14:00:00', 1, 'Elective ortho admission — L TKA pre-op complete'),
       (104, 20, 5, NULL, 1, 'OR_RETURN', '2026-02-26 17:00:00', 1, 'Returned from OR — procedure uncomplicated, wound intact'),
       (105, 20, 5, NULL, 1, 'PT_SESSION', '2026-02-27 09:00:00', 1, 'PT Day 1: ambulated 25 ft with walker, knee flexion 85 degrees'),
       (106, 20, 5, NULL, 1, 'DISCHARGE_PLANNED', '2026-02-28 09:00:00', 1, 'Target discharge tomorrow, home health PT arranged x6 visits'),
       (107, 21, 6, NULL, 1, 'ADMIT', '2026-02-26 22:10:00', 1, 'Urgent cardiology admit — NSTEMI, troponin 4.2, on heparin drip'),
       (108, 21, 6, NULL, 1, 'PROCEDURE', '2026-02-27 07:30:00', 1, 'Cardiac cath: LAD 90%, DES placed, TIMI 3 flow, sheath pulled at 1100'),
       (109, 21, 6, NULL, 1, 'LAB', '2026-02-27 18:00:00', 1, 'Troponin 1.8 — trending down, EKG: no new ST changes, NSR'),
       (110, 21, 6, NULL, 1, 'DISCHARGE_PLANNED', '2026-02-28 09:00:00', 1, 'Cardiology: discharge tomorrow, DAPT/statin/BB/ACEI prescribed'),
       (111, 22, 8, NULL, 1, 'ADMIT', '2026-02-24 10:45:00', 1, 'Emergency admit — SpO2 84% RA, CAP diagnosed, IV abx started'),
       (112, 22, 8, NULL, 1, 'VITALS', '2026-02-24 14:00:00', 1, 'SpO2 94% on 3L NC, RR 22, temp 38.4 C, responding to abx'),
       (113, 22, 8, NULL, 1, 'LAB', '2026-02-26 06:00:00', 1, 'WBC 11.2 (down from 18.4), CRP 64 (down from 220), procalcitonin 1.2'),
       (114, 22, 8, NULL, 1, 'DISCHARGE_PLANNED', '2026-02-28 10:00:00', 1, 'Transition to PO azithromycin, discharge tomorrow'),
       (115, 23, 11, NULL, 1, 'ADMIT', '2026-02-27 16:20:00', 1, 'Emergency admit — R hip fx, X-ray confirmed, ortho consult completed'),
       (116, 23, 11, NULL, 1, 'PRE_OP', '2026-02-27 19:00:00', 1, 'Pre-op: Hgb 11.8, INR 1.1, cardiology cleared, consent signed'),
       (117, 23, 11, NULL, 1, 'OR_RETURN', '2026-02-28 11:00:00', 1, 'ORIF completed — EBL 250 mL, Hgb 9.2, Foley removed'),
       (118, 23, 11, NULL, 1, 'PT_SESSION', '2026-02-28 15:00:00', 1, 'PT Day 1 post-op: WBAT with walker 15 ft, pain 5/10 controlled');

-- oei_obs_plan
CREATE TABLE IF NOT EXISTS `oei_obs_plan`
(
    `id`                 bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED  NOT NULL,
    `pid`                bigint(20) UNSIGNED  NOT NULL,
    `eid`                bigint(20) UNSIGNED           DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED  NOT NULL,
    `protocol_key`       varchar(80)          NOT NULL,
    `status`             varchar(20)          NOT NULL DEFAULT 'ACTIVE',
    `start_datetime`     datetime             NOT NULL,
    `target_hours`       smallint(5) UNSIGNED NOT NULL DEFAULT 24,
    `runway_hours`       smallint(5) UNSIGNED NOT NULL DEFAULT 6,
    `protocol_json`      mediumtext           NOT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED           DEFAULT NULL,
    `updated_datetime`   datetime             NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_obs_episode` (`episode_id`),
    KEY `idx_oei_obs_active` (`facility_id`, `status`, `start_datetime`),
    KEY `idx_oei_obs_protocol` (`facility_id`, `protocol_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_obs_plan` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `protocol_key`, `status`, `start_datetime`, `target_hours`, `runway_hours`, `protocol_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 2, 3, NULL, 1, 'CHEST_PAIN', 'ACTIVE', '2026-02-28 02:19:41', 24, 4, '{"protocol_key":"CHEST_PAIN","label":"Chest Pain / ACS Rule-Out","target_hours":24,"runway_hours":4}', 1, '2026-02-28 02:19:41'),
       (2, 5, 3, NULL, 1, 'CHEST_PAIN', 'ACTIVE', '2026-02-28 02:20:10', 24, 4, '{"protocol_key":"CHEST_PAIN","label":"Chest Pain / ACS Rule-Out","target_hours":24,"runway_hours":4}', 1, '2026-02-28 02:20:10'),
       (3, 10, 8, NULL, 1, 'COPD_EXACERBATION', 'ACTIVE', '2026-02-28 11:20:11', 24, 6, '{"protocol_key":"COPD_EXACERBATION","target_hours":24,"runway_hours":6}', 1, '2026-02-28 11:20:11');

-- oei_al_episode
CREATE TABLE IF NOT EXISTS `oei_al_episode`
(
    `id`                bigint(20) UNSIGNED               NOT NULL AUTO_INCREMENT,
    `episode_id`        bigint(20) UNSIGNED               NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`               bigint(20) UNSIGNED               NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`       bigint(20) UNSIGNED               NOT NULL,
    `encounter_id`      bigint(20) UNSIGNED                        DEFAULT NULL COMMENT 'FK → form_encounter.id — anchors form_care_plan entries',
    `room`              varchar(20)                                DEFAULT NULL,
    `unit`              varchar(40)                                DEFAULT NULL,
    `care_level`        enum ('TIER_1','TIER_2','TIER_3') NOT NULL DEFAULT 'TIER_1' COMMENT 'CareLevel domain: Low / Medium / High',
    `fall_risk_level`   enum ('LOW','MODERATE','HIGH')    NOT NULL DEFAULT 'LOW' COMMENT 'Morse Fall Scale tier',
    `fall_risk_score`   tinyint(3) UNSIGNED               NOT NULL DEFAULT 0 COMMENT 'Raw Morse Fall Scale total (0-125)',
    `admit_reason`      varchar(255)                               DEFAULT NULL,
    `last_adl_score`    tinyint(3) UNSIGNED                        DEFAULT NULL COMMENT 'Cached aggregate ADL score from latest oei_adl_record',
    `last_adl_datetime` datetime                                   DEFAULT NULL,
    `created_datetime`  datetime                          NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_al_episode` (`episode_id`),
    KEY `idx_oei_al_facility` (`facility_id`, `care_level`),
    KEY `idx_oei_al_room` (`facility_id`, `unit`, `room`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL-specific overlay on oei_episode';

INSERT INTO `oei_al_episode` (`id`, `episode_id`, `pid`, `facility_id`, `encounter_id`, `room`, `unit`, `care_level`, `fall_risk_level`, `fall_risk_score`, `admit_reason`, `last_adl_score`, `last_adl_datetime`, `created_datetime`)
VALUES (1, 14, 50, 1, 1000050, '101', 'Wing A', 'TIER_3', 'HIGH', 78, 'Memory care — moderate dementia with behavioral disturbances and fall history', 14, '2026-02-28 16:20:13', '2026-01-12 22:20:13'),
       (2, 15, 51, 1, 1000051, '104', 'Wing A', 'TIER_2', 'MODERATE', 38, 'Post-hip arthroplasty transition from SNF — PT/OT in progress', 20, '2026-02-28 15:20:13', '2026-01-28 22:20:13'),
       (3, 16, 52, 1, 1000052, '108', 'Wing A', 'TIER_1', 'LOW', 12, 'COPD management and medication administration assistance', 25, '2026-02-28 14:20:13', '2026-02-10 22:20:13'),
       (4, 17, 53, 1, 1000053, '201', 'Wing B', 'TIER_3', 'HIGH', 91, 'Advanced Parkinson''s — fall prevention, dysphagia protocol, daily PT', 10, '2026-02-28 17:20:13', '2025-12-28 22:20:13'),
       (5, 18, 54, 1, 1000054, '205', 'Wing B', 'TIER_2', 'MODERATE', 32, 'CHF/T2DM — daily weights, fluid restriction, insulin management', 22, '2026-02-28 13:20:13', '2026-02-19 22:20:13');

-- oei_adl_record
CREATE TABLE IF NOT EXISTS `oei_adl_record`
(
    `id`               bigint(20) UNSIGNED                                NOT NULL AUTO_INCREMENT,
    `episode_id`       bigint(20) UNSIGNED                                NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`      bigint(20) UNSIGNED                                NOT NULL,
    `noted_by_user_id` bigint(20) UNSIGNED                                         DEFAULT NULL COMMENT 'FK → users.id (aide/nurse)',
    `noted_datetime`   datetime                                           NOT NULL,
    `adl_json`         longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'domain→level map, e.g. {"bathing":2,"dressing":1,...}' CHECK (json_valid(`adl_json`)),
    `adl_score`        tinyint(3) UNSIGNED                                NOT NULL DEFAULT 0 COMMENT 'Aggregate 0–28; see AdlLevel::aggregateScore()',
    `notes`            text                                                        DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_adl_episode` (`episode_id`, `noted_datetime`),
    KEY `idx_oei_adl_facility` (`facility_id`, `noted_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='ADL charting sessions; one row per aide session covering all 7 domains';

INSERT INTO `oei_adl_record` (`id`, `episode_id`, `facility_id`, `noted_by_user_id`, `noted_datetime`, `adl_json`, `adl_score`, `notes`)
VALUES (1, 14, 1, 1, '2026-02-27 16:20:13', '{"bathing":4,"dressing":3,"grooming":3,"transfer":3,"ambulation":3,"eating":2,"toileting":4}', 22, 'Night shift: confused, resisted morning care. Bed alarm triggered twice. No fall.'),
       (2, 14, 1, 1, '2026-02-28 16:20:13', '{"bathing":4,"dressing":3,"grooming":2,"transfer":3,"ambulation":3,"eating":2,"toileting":3}', 20, 'Day shift: more cooperative after breakfast. Music therapy at 10 AM — calm for 45 min.'),
       (3, 15, 1, 1, '2026-02-27 14:20:13', '{"bathing":2,"dressing":2,"grooming":1,"transfer":3,"ambulation":3,"eating":1,"toileting":2}', 14, 'Improving transfer with walker. Rated pain 4/10 post-PT.'),
       (4, 15, 1, 1, '2026-02-28 15:20:13', '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":3,"eating":1,"toileting":2}', 13, 'PT this AM — achieved 20 ft ambulation with walker. Good progress.'),
       (5, 16, 1, 1, '2026-02-27 12:20:13', '{"bathing":1,"dressing":1,"grooming":0,"transfer":1,"ambulation":1,"eating":0,"toileting":1}', 5, 'Independent with most tasks. Assisted with shower per preference.'),
       (6, 16, 1, 1, '2026-02-28 14:20:13', '{"bathing":1,"dressing":1,"grooming":0,"transfer":1,"ambulation":1,"eating":0,"toileting":1}', 5, 'Stable. SpO2 94% on RA — within goal. Inhaler administered on schedule.'),
       (7, 17, 1, 1, '2026-02-27 18:20:13', '{"bathing":3,"dressing":4,"grooming":3,"transfer":4,"ambulation":3,"eating":2,"toileting":3}', 22, 'Morning off-period — significant rigidity pre-meds. Levodopa 0710 (within window).'),
       (8, 17, 1, 1, '2026-02-28 17:20:13', '{"bathing":3,"dressing":3,"grooming":3,"transfer":4,"ambulation":4,"eating":2,"toileting":3}', 22, 'Post-fall assessment — see incident report. Ambulation suspended pending PT clearance.'),
       (9, 18, 1, 1, '2026-02-27 13:20:13', '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":2,"eating":1,"toileting":2}', 12, 'Weight today 142 lbs — up 1.8 lbs from baseline. Within threshold. FBG 162.'),
       (10, 18, 1, 1, '2026-02-28 13:20:13', '{"bathing":2,"dressing":2,"grooming":1,"transfer":2,"ambulation":2,"eating":1,"toileting":2}', 12, 'Weight 143 lbs — +2.2 lbs. Notified charge nurse and attending per CHF weight protocol. Furosemide dose reviewed.');

-- oei_incident
CREATE TABLE IF NOT EXISTS `oei_incident`
(
    `id`                    bigint(20) UNSIGNED                        NOT NULL AUTO_INCREMENT,
    `episode_id`            bigint(20) UNSIGNED                        NOT NULL COMMENT 'FK → oei_episode.id',
    `facility_id`           bigint(20) UNSIGNED                        NOT NULL,
    `reported_by_user_id`   bigint(20) UNSIGNED                                 DEFAULT NULL COMMENT 'FK → users.id',
    `incident_type`         varchar(30)                                NOT NULL COMMENT 'IncidentType constant: FALL|FALL_INJURY|ELOPEMENT|MED_ERROR|…',
    `severity`              enum ('LOW','MODERATE','HIGH','CRITICAL')  NOT NULL DEFAULT 'MODERATE',
    `incident_datetime`     datetime                                   NOT NULL COMMENT 'When the incident occurred',
    `location_description`  varchar(120)                                        DEFAULT NULL,
    `narrative`             text                                                DEFAULT NULL COMMENT 'Factual description of what happened',
    `corrective_action`     text                                                DEFAULT NULL COMMENT 'Immediate corrective action taken',
    `reported_state`        enum ('PENDING','REPORTED','NOT_REQUIRED') NOT NULL DEFAULT 'PENDING',
    `mandatory_report_sent` tinyint(1)                                 NOT NULL DEFAULT 0 COMMENT '1 = state notification filed',
    `created_datetime`      datetime                                   NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_incident_episode` (`episode_id`),
    KEY `idx_oei_incident_facility` (`facility_id`, `incident_datetime`),
    KEY `idx_oei_incident_type` (`facility_id`, `incident_type`, `severity`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL incident reports';

INSERT INTO `oei_incident` (`id`, `episode_id`, `facility_id`, `reported_by_user_id`, `incident_type`, `severity`, `incident_datetime`, `location_description`, `narrative`, `corrective_action`, `reported_state`, `mandatory_report_sent`, `created_datetime`)
VALUES (1, 17, 1, 1, 'FALL_INJURY', 'HIGH', '2026-02-28 17:20:13', 'Wing B Room 201 - beside bed',
        'Resident found on floor beside bed during AM care check. Unwitnessed. Alert and oriented x2 on assessment. No LOC. 2cm laceration right forearm, mild bruising right hip. No signs of hip fracture. X-ray ordered. Physician and family notified.',
        'Assisted resident to bed. Wound cleaned and dressed. Neuro checks q1h x4h. Bed in lowest position, floor mat placed. Fall alarm re-evaluated. PT to reassess ambulation safety. Care plan goal updated.', 'PENDING', 0, '2026-02-28 17:50:13'),
       (2, 18, 1, 1, 'MED_ERROR', 'MODERATE', '2026-02-19 20:20:13', 'Wing B Room 205 - medication cart',
        'Resident received furosemide 40mg instead of scheduled 20mg due to look-alike packaging. Error discovered during next-shift MAR review. No acute adverse effects. BP 118/72, HR 74, SpO2 97%. Electrolytes ordered and within normal limits.',
        'Physician notified immediately. Electrolyte panel ordered. Resident monitored q2h x8h. Pharmacy notified. Root cause: look-alike packaging. Corrective action: separate storage, barcode scan protocol initiated.', 'NOT_REQUIRED', 0, '2026-02-19 21:20:13');

-- oei_mar_order
CREATE TABLE IF NOT EXISTS `oei_mar_order`
(
    `id`                      int(10) UNSIGNED                           NOT NULL AUTO_INCREMENT,
    `episode_id`              int(10) UNSIGNED                           NOT NULL,
    `pid`                     bigint(20)                                 NOT NULL,
    `facility_id`             int(10) UNSIGNED                           NOT NULL,
    `drug_name`               varchar(200)                               NOT NULL,
    `dose`                    varchar(80)                                NOT NULL,
    `unit`                    varchar(30)                                NOT NULL DEFAULT '',
    `route`                   varchar(60)                                NOT NULL DEFAULT '',
    `frequency`               varchar(80)                                NOT NULL DEFAULT '',
    `is_prn`                  tinyint(1)                                 NOT NULL DEFAULT 0,
    `is_stat`                 tinyint(1)                                 NOT NULL DEFAULT 0,
    `is_high_alert`           tinyint(1)                                 NOT NULL DEFAULT 0 COMMENT 'Auto-detected from drug name keywords at order time',
    `status`                  enum ('ACTIVE','DISCONTINUED','COMPLETED') NOT NULL DEFAULT 'ACTIVE',
    `ordered_datetime`        datetime                                   NOT NULL,
    `discontinued_datetime`   datetime                                            DEFAULT NULL,
    `ordered_by_user_id`      int(11)                                             DEFAULT NULL,
    `discontinued_by_user_id` int(11)                                             DEFAULT NULL,
    `rx_id`                   int(11)                                             DEFAULT NULL,
    `instructions`            text                                                DEFAULT NULL,
    `created_datetime`        datetime                                   NOT NULL,
    `updated_datetime`        datetime                                   NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mar_order_episode` (`episode_id`),
    KEY `idx_mar_order_facility` (`facility_id`),
    KEY `idx_mar_order_status` (`status`),
    KEY `idx_mar_order_stat` (`is_stat`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_mar_order` (`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`, `frequency`, `is_prn`, `is_stat`, `is_high_alert`, `status`, `ordered_datetime`, `discontinued_datetime`, `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`,
                             `instructions`, `created_datetime`, `updated_datetime`)
VALUES (1, 1, 2, 1, 'Vancomycin', '1500', 'mg', 'IV', 'Q8H', 0, 0, 1, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Infuse over 90 min. Monitor troughs. Sepsis dosing.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (2, 1, 2, 1, 'Piperacillin-Tazobactam', '3.375', 'g', 'IV', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Extended infusion over 4h. Start after blood cultures.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (3, 1, 2, 1, 'Normal Saline', '1000', 'mL', 'IV', 'Q4H', 0, 0, 0, 'ACTIVE', '2026-02-28 19:34:41', NULL, 1, NULL, NULL, 'Maintenance fluid. Reassess after each bolus.', '2026-02-28 19:34:41', '2026-02-28 19:34:41'),
       (4, 1, 2, 1, 'Norepinephrine', '0.05', 'mcg/kg/min', 'IV', 'CONTINUOUS', 0, 0, 1, 'ACTIVE', '2026-02-28 21:19:41', NULL, 1, NULL, NULL, 'Vasopressor for MAP < 65. Titrate to MAP 65-70. HIGH ALERT.', '2026-02-28 21:19:41', '2026-02-28 21:19:41'),
       (5, 2, 3, 1, 'Aspirin', '325', 'mg', 'PO', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-28 00:19:41', NULL, 1, NULL, NULL, 'Chew first dose. Cardiac dosing.', '2026-02-28 00:19:41', '2026-02-28 00:19:41'),
       (6, 2, 3, 1, 'Metoprolol', '25', 'mg', 'PO', 'BID', 0, 0, 0, 'ACTIVE', '2026-02-28 02:19:41', NULL, 1, NULL, NULL, 'Hold for HR < 55 or SBP < 100.', '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (7, 2, 3, 1, 'Heparin', '5000', 'units', 'SQ', 'Q8H', 0, 0, 1, 'ACTIVE', '2026-02-28 02:19:41', NULL, 1, NULL, NULL, 'DVT prophylaxis. Check aPTT. HIGH ALERT.', '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (8, 2, 3, 1, 'Nitroglycerin', '0.4', 'mg', 'SL', 'PRN', 1, 0, 1, 'ACTIVE', '2026-02-28 00:19:41', NULL, 1, NULL, NULL, 'PRN chest pain. May repeat q5min x3. Hold SBP < 90.', '2026-02-28 00:19:41', '2026-02-28 00:19:41'),
       (9, 4, 2, 1, 'Vancomycin', '1500', 'mg', 'IV', 'Q8H', 0, 0, 1, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Infuse over 90 min. Monitor troughs. Sepsis dosing.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (10, 4, 2, 1, 'Piperacillin-Tazobactam', '3.375', 'g', 'IV', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Extended infusion over 4h. Start after blood cultures.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (11, 4, 2, 1, 'Normal Saline', '1000', 'mL', 'IV', 'Q4H', 0, 0, 0, 'ACTIVE', '2026-02-28 19:35:10', NULL, 1, NULL, NULL, 'Maintenance fluid. Reassess after each bolus.', '2026-02-28 19:35:10', '2026-02-28 19:35:10'),
       (12, 4, 2, 1, 'Norepinephrine', '0.05', 'mcg/kg/min', 'IV', 'CONTINUOUS', 0, 0, 1, 'ACTIVE', '2026-02-28 21:20:10', NULL, 1, NULL, NULL, 'Vasopressor for MAP < 65. Titrate to MAP 65-70. HIGH ALERT.', '2026-02-28 21:20:10', '2026-02-28 21:20:10'),
       (13, 5, 3, 1, 'Aspirin', '325', 'mg', 'PO', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-28 00:20:10', NULL, 1, NULL, NULL, 'Chew first dose. Cardiac dosing.', '2026-02-28 00:20:10', '2026-02-28 00:20:10'),
       (14, 5, 3, 1, 'Metoprolol', '25', 'mg', 'PO', 'BID', 0, 0, 0, 'ACTIVE', '2026-02-28 02:20:10', NULL, 1, NULL, NULL, 'Hold for HR < 55 or SBP < 100.', '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (15, 5, 3, 1, 'Heparin', '5000', 'units', 'SQ', 'Q8H', 0, 0, 1, 'ACTIVE', '2026-02-28 02:20:10', NULL, 1, NULL, NULL, 'DVT prophylaxis. Check aPTT. HIGH ALERT.', '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (16, 5, 3, 1, 'Nitroglycerin', '0.4', 'mg', 'SL', 'PRN', 1, 0, 1, 'ACTIVE', '2026-02-28 00:20:10', NULL, 1, NULL, NULL, 'PRN chest pain. May repeat q5min x3. Hold SBP < 90.', '2026-02-28 00:20:10', '2026-02-28 00:20:10'),
       (17, 7, 5, 1, 'Ketorolac', '30', 'mg', 'IV', 'PRN', 1, 0, 0, 'ACTIVE', '2026-02-28 21:35:11', NULL, 1, NULL, NULL, 'PRN pain > 6/10. Max 5 days. NSAID — note allergy check passed.', '2026-02-28 21:35:11', '2026-02-28 21:35:11'),
       (18, 7, 5, 1, 'Ibuprofen', '600', 'mg', 'PO', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-02-28 22:10:11', NULL, 1, NULL, NULL, 'Discharge prescription x 5 days. Take with food.', '2026-02-28 22:10:11', '2026-02-28 22:10:11'),
       (19, 8, 6, 1, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 0, 0, 'ACTIVE', '2026-02-28 22:00:11', NULL, 1, NULL, NULL, 'Maintenance. NO glucose-containing fluids in stroke.', '2026-02-28 22:00:11', '2026-02-28 22:00:11'),
       (20, 8, 6, 1, 'Labetalol', '10', 'mg', 'IV', 'PRN', 1, 0, 0, 'ACTIVE', '2026-02-28 22:00:11', NULL, 1, NULL, NULL, 'PRN SBP > 220 (pre-tPA) or > 180 (post-tPA). Give over 2 min.', '2026-02-28 22:00:11', '2026-02-28 22:00:11'),
       (21, 9, 7, 1, 'Tranexamic Acid (TXA)', '1000', 'mg', 'IV', 'ONCE', 0, 0, 0, 'ACTIVE', '2026-02-28 20:30:11', NULL, 1, NULL, NULL, 'Load 1g over 10 min within 3h of injury. HIGH ALERT.', '2026-02-28 20:30:11', '2026-02-28 20:30:11'),
       (22, 9, 7, 1, 'Lactated Ringers', '1000', 'mL', 'IV', 'BOLUS', 0, 0, 0, 'ACTIVE', '2026-02-28 20:25:11', NULL, 1, NULL, NULL, 'Permissive hypotension — target SBP 80-90 until OR.', '2026-02-28 20:25:11', '2026-02-28 20:25:11'),
       (23, 9, 7, 1, 'Morphine', '4', 'mg', 'IV', 'PRN', 1, 0, 1, 'ACTIVE', '2026-02-28 20:40:11', NULL, 1, NULL, NULL, 'PRN pain. Reassess GCS after. MAX 0.1mg/kg.', '2026-02-28 20:40:11', '2026-02-28 20:40:11'),
       (24, 9, 7, 1, 'Packed Red Blood Cells', '1', 'unit', 'IV', 'PRN', 1, 0, 0, 'ACTIVE', '2026-02-28 20:50:11', NULL, 1, NULL, NULL, 'O-neg until type confirmed. MTP protocol.', '2026-02-28 20:50:11', '2026-02-28 20:50:11'),
       (25, 10, 8, 1, 'Albuterol', '2.5', 'mg', 'INH', 'Q4H', 0, 0, 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'Neb treatment. Monitor HR. Wean to Q8H before discharge.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (26, 10, 8, 1, 'Ipratropium', '0.5', 'mg', 'INH', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'Neb with first 3 albuterol doses, then PRN.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (27, 10, 8, 1, 'Prednisone', '40', 'mg', 'PO', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-28 10:20:11', NULL, 1, NULL, NULL, 'COPD exacerbation — 5-day course. No taper needed.', '2026-02-28 10:20:11', '2026-02-28 10:20:11'),
       (28, 10, 8, 1, 'Azithromycin', '500', 'mg', 'PO', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-28 11:20:11', NULL, 1, NULL, NULL, '5-day Z-pack. Atypical coverage for COPD exacerbation.', '2026-02-28 11:20:11', '2026-02-28 11:20:11'),
       (29, 11, 9, 1, 'Acetaminophen', '345', 'mg', 'PO', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-02-28 21:42:11', NULL, 1, NULL, NULL, 'Pediatric dosing: 15mg/kg. Weight 23kg. Max 5 doses/24h.', '2026-02-28 21:42:11', '2026-02-28 21:42:11'),
       (30, 12, 10, 1, 'Naloxone', '0.4', 'mg/hr', 'IV', 'CONTINUOUS', 0, 0, 0, 'ACTIVE', '2026-02-28 20:58:12', NULL, 1, NULL, NULL, 'Start 0.4mg/hr. Titrate for RR > 12. HIGH ALERT — monitor q15min. Max 2mg/hr. Wean when clinically appropriate.', '2026-02-28 20:58:12',
        '2026-02-28 20:58:12'),
       (31, 12, 10, 1, 'Normal Saline', '125', 'mL/hr', 'IV', 'CONTINUOUS', 0, 0, 0, 'ACTIVE', '2026-02-28 20:58:12', NULL, 1, NULL, NULL, 'Maintenance fluid.', '2026-02-28 20:58:12', '2026-02-28 20:58:12'),
       (32, 14, 50, 1, 'Donepezil', '10', 'mg', 'PO', 'QHS', 0, 0, 0, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'Administer at bedtime with water. Monitor for GI side effects.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),
       (33, 14, 50, 1, 'Memantine', '10', 'mg', 'PO', 'BID', 0, 0, 0, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'Give with or without food. Morning and evening doses.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),
       (34, 14, 50, 1, 'Quetiapine', '12.5', 'mg', 'PO', 'QHS', 1, 0, 0, 'ACTIVE', '2026-01-13 08:00:00', NULL, 1, NULL, NULL, 'PRN agitation only — not to exceed 25mg/24h. Document behavior before and after.', '2026-01-13 08:00:00', '2026-01-13 08:00:00'),
       (35, 15, 51, 1, 'Acetaminophen', '650', 'mg', 'PO', 'Q6H', 0, 0, 0, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'Scheduled pain management. Do not exceed 4g/24h.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),
       (36, 15, 51, 1, 'Oxycodone', '5', 'mg', 'PO', 'Q6H', 1, 0, 0, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'PRN breakthrough pain — use only if acetaminophen insufficient. Taper by week 6.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),
       (37, 15, 51, 1, 'Enoxaparin', '40', 'mg', 'SQ', 'QD', 0, 0, 0, 'ACTIVE', '2026-01-29 08:00:00', NULL, 1, NULL, NULL, 'DVT prophylaxis post-arthroplasty. Rotate injection sites. Check PLT weekly.', '2026-01-29 08:00:00', '2026-01-29 08:00:00'),
       (38, 16, 52, 1, 'Tiotropium', '18', 'mcg', 'Inh', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-11 08:00:00', NULL, 1, NULL, NULL, 'HandiHaler device — 1 capsule inhaled once daily. Morning administration.', '2026-02-11 08:00:00', '2026-02-11 08:00:00'),
       (39, 16, 52, 1, 'Albuterol', '2.5', 'mg', 'Neb', 'PRN', 1, 0, 0, 'ACTIVE', '2026-02-11 08:00:00', NULL, 1, NULL, NULL, 'PRN rescue — administer for SpO2 below 90% or acute dyspnea. Max Q4H.', '2026-02-11 08:00:00', '2026-02-11 08:00:00'),
       (40, 17, 53, 1, 'Carbidopa/Levodopa', '25/100', 'mg', 'PO', 'TID', 0, 0, 0, 'ACTIVE', '2025-12-29 07:00:00', NULL, 1, NULL, NULL, 'Administer 7AM, 12PM, 5PM — within 30-min window. Give 30 min before meals. Track on-time administration.', '2025-12-29 07:00:00',
        '2025-12-29 07:00:00'),
       (41, 17, 53, 1, 'Rivastigmine patch', '4.6', 'mg/24h', 'TD', 'QD', 0, 0, 0, 'ACTIVE', '2025-12-29 07:00:00', NULL, 1, NULL, NULL, 'Apply to upper arm or chest, rotate sites daily. Remove old patch before applying new.', '2025-12-29 07:00:00', '2025-12-29 07:00:00'),
       (42, 18, 54, 1, 'Furosemide', '20', 'mg', 'PO', 'QAM', 0, 0, 0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'VERIFY dose carefully — 20mg (not 40mg). Administer morning. Monitor daily weight.', '2026-02-20 08:00:00', '2026-02-20 08:00:00'),
       (43, 18, 54, 1, 'Lisinopril', '10', 'mg', 'PO', 'QD', 0, 0, 0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Hold if SBP below 100. Monitor potassium weekly.', '2026-02-20 08:00:00', '2026-02-20 08:00:00'),
       (44, 18, 54, 1, 'Insulin glargine', '18', 'units', 'SQ', 'QHS', 0, 0, 1, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Administer at bedtime. Sliding scale: FBG 141-180 add 2u, 181-240 add 4u, over 240 notify provider.', '2026-02-20 08:00:00',
        '2026-02-20 08:00:00'),
       (45, 18, 54, 1, 'Metformin', '500', 'mg', 'PO', 'BID', 0, 0, 0, 'ACTIVE', '2026-02-20 08:00:00', NULL, 1, NULL, NULL, 'Give with meals to reduce GI side effects. Hold if creatinine above 1.4.', '2026-02-20 08:00:00', '2026-02-20 08:00:00');

INSERT INTO `oei_mar_order` (`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`, `frequency`, `is_prn`, `is_stat`, `is_high_alert`, `status`, `ordered_datetime`, `discontinued_datetime`, `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`,
                             `instructions`, `created_datetime`, `updated_datetime`)
VALUES (50, 19, 2, 1, 'Vancomycin', '1250', 'mg', 'IV', 'Q12H', 0, 0, 1,
        'ACTIVE', '2026-02-23 10:00:00', NULL, 1, NULL, NULL,
        'Infuse over 90 min — monitor troughs, target 15-20 mcg/mL', '2026-02-23 10:00:00', '2026-02-23 10:00:00'),
       (51, 19, 2, 1, 'Propofol', '5', 'mcg/kg/min', 'IV', 'Continuous', 0, 0, 1,
        'DISCONTINUED', '2026-02-23 09:00:00', '2026-02-26 14:00:00', 1, 1, NULL,
        'Sedation — titrate to RASS -2 to 0, discontinue prior to extubation attempt', '2026-02-23 09:00:00', '2026-02-26 14:00:00'),
       (52, 19, 2, 1, 'Heparin', '25000', 'units/500mL', 'IV', 'Continuous', 0, 0, 1,
        'ACTIVE', '2026-02-23 12:00:00', NULL, 1, NULL, NULL,
        'DVT prophylaxis — adjust per weight-based nomogram, check aPTT Q6H', '2026-02-23 12:00:00', '2026-02-23 12:00:00'),
       (53, 20, 5, 1, 'Oxycodone', '5', 'mg', 'PO', 'Q4H PRN', 1, 0, 0,
        'ACTIVE', '2026-02-26 15:00:00', NULL, 1, NULL, NULL,
        'PRN pain — hold if RR less than 12 or sedation score greater than 2', '2026-02-26 15:00:00', '2026-02-26 15:00:00'),
       (54, 20, 5, 1, 'Cefazolin', '1', 'g', 'IV', 'Q8H', 0, 0, 0,
        'ACTIVE', '2026-02-26 14:30:00', NULL, 1, NULL, NULL,
        'Peri-operative prophylaxis — 3 doses total (24 h post-op)', '2026-02-26 14:30:00', '2026-02-26 14:30:00'),
       (55, 20, 5, 1, 'Enoxaparin', '40', 'mg', 'SC', 'QD', 0, 0, 0,
        'ACTIVE', '2026-02-27 08:00:00', NULL, 1, NULL, NULL,
        'VTE prophylaxis — 12 h post-op', '2026-02-27 08:00:00', '2026-02-27 08:00:00'),
       (56, 21, 6, 1, 'Heparin', '25000', 'units/500mL', 'IV', 'Continuous', 0, 0, 1,
        'DISCONTINUED', '2026-02-26 22:30:00', '2026-02-27 18:00:00', 1, 1, NULL,
        'Peri-procedural anticoagulation — discontinue 4 h post-sheath pull', '2026-02-26 22:30:00', '2026-02-27 18:00:00'),
       (57, 21, 6, 1, 'Metoprolol Tartrate', '25', 'mg', 'PO', 'BID', 0, 0, 0,
        'ACTIVE', '2026-02-27 06:00:00', NULL, 1, NULL, NULL,
        'Post-MI beta-blockade — hold if HR less than 55 or SBP less than 90', '2026-02-27 06:00:00', '2026-02-27 06:00:00'),
       (58, 21, 6, 1, 'Aspirin', '81', 'mg', 'PO', 'QD', 0, 0, 0,
        'ACTIVE', '2026-02-26 23:00:00', NULL, 1, NULL, NULL,
        'Antiplatelet — do not hold without cardiology approval, indefinite therapy', '2026-02-26 23:00:00', '2026-02-26 23:00:00'),
       (59, 21, 6, 1, 'Atorvastatin', '80', 'mg', 'PO', 'QHS', 0, 0, 0,
        'ACTIVE', '2026-02-27 00:00:00', NULL, 1, NULL, NULL,
        'High-intensity statin post-ACS — take at bedtime, monitor LFTs in 6 weeks', '2026-02-27 00:00:00', '2026-02-27 00:00:00'),
       (60, 22, 8, 1, 'Ceftriaxone', '1', 'g', 'IV', 'QD', 0, 0, 0,
        'ACTIVE', '2026-02-24 12:00:00', NULL, 1, NULL, NULL,
        'CAP coverage — infuse over 30 min, reassess at 48h per culture results', '2026-02-24 12:00:00', '2026-02-24 12:00:00'),
       (61, 22, 8, 1, 'Azithromycin', '500', 'mg', 'IV', 'QD', 0, 0, 0,
        'ACTIVE', '2026-02-24 12:00:00', NULL, 1, NULL, NULL,
        'Atypical coverage — transition to PO when tolerating oral meds', '2026-02-24 12:00:00', '2026-02-24 12:00:00'),
       (62, 22, 8, 1, 'Methylprednisolone', '40', 'mg', 'IV', 'Q12H', 0, 0, 0,
        'ACTIVE', '2026-02-24 13:00:00', NULL, 1, NULL, NULL,
        'Severe CAP adjunct — 5-day course, monitor glucose Q6H', '2026-02-24 13:00:00', '2026-02-24 13:00:00'),
       (63, 23, 11, 1, 'Morphine', '2', 'mg', 'IV', 'Q4H PRN', 1, 0, 1,
        'ACTIVE', '2026-02-27 17:00:00', NULL, 1, NULL, NULL,
        'Acute pain post-ORIF — hold if RR less than 10, naloxone at bedside', '2026-02-27 17:00:00', '2026-02-27 17:00:00'),
       (64, 23, 11, 1, 'Cefazolin', '1', 'g', 'IV', 'Q8H', 0, 0, 0,
        'ACTIVE', '2026-02-27 16:30:00', NULL, 1, NULL, NULL,
        'Peri-operative prophylaxis — 24 h post-op, 3 doses total', '2026-02-27 16:30:00', '2026-02-27 16:30:00'),
       (65, 23, 11, 1, 'Enoxaparin', '40', 'mg', 'SC', 'QD', 0, 0, 0,
        'ACTIVE', '2026-02-28 08:00:00', NULL, 1, NULL, NULL,
        'VTE prophylaxis post-hip fracture — 35 days total course', '2026-02-28 08:00:00', '2026-02-28 08:00:00');

-- oei_mar_administration
CREATE TABLE IF NOT EXISTS `oei_mar_administration`
(
    `id`                      int(10) UNSIGNED                                                   NOT NULL AUTO_INCREMENT,
    `mar_order_id`            int(10) UNSIGNED                                                   NOT NULL,
    `episode_id`              int(10) UNSIGNED                                                   NOT NULL,
    `pid`                     bigint(20)                                                         NOT NULL,
    `facility_id`             int(10) UNSIGNED                                                   NOT NULL,
    `scheduled_datetime`      datetime                                                                    DEFAULT NULL,
    `administered_datetime`   datetime                                                                    DEFAULT NULL,
    `outcome`                 enum ('PENDING','GIVEN','HELD','REFUSED','NOT_AVAILABLE','MISSED') NOT NULL DEFAULT 'PENDING',
    `dose_given`              varchar(80)                                                                 DEFAULT NULL,
    `unit_given`              varchar(30)                                                                 DEFAULT NULL,
    `route_given`             varchar(60)                                                                 DEFAULT NULL,
    `site`                    varchar(80)                                                                 DEFAULT NULL,
    `lot_number`              varchar(60)                                                                 DEFAULT NULL,
    `hold_reason`             varchar(60)                                                                 DEFAULT NULL COMMENT 'Structured HELD reason code — see MarService::HOLD_REASONS',
    `administered_by_user_id` int(11)                                                                     DEFAULT NULL,
    `witness_user_id`         int(11)                                                                     DEFAULT NULL COMMENT 'User ID of waste witness (controlled substances)',
    `waste_amount`            varchar(20)                                                                 DEFAULT NULL COMMENT 'Amount wasted (drawn - administered)',
    `waste_unit`              varchar(20)                                                                 DEFAULT NULL COMMENT 'Unit of wasted amount (mg, ml, etc.)',
    `co_sign_user_id`         int(11)                                                                     DEFAULT NULL COMMENT 'Second nurse who co-signed this high-alert administration',
    `co_signed_datetime`      datetime                                                                    DEFAULT NULL COMMENT 'When the co-signature was recorded',
    `note`                    text                                                                        DEFAULT NULL,
    `is_high_alert`           tinyint(1)                                                         NOT NULL DEFAULT 0,
    `created_datetime`        datetime                                                           NOT NULL,
    `updated_datetime`        datetime                                                           NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mar_admin_order` (`mar_order_id`),
    KEY `idx_mar_admin_episode` (`episode_id`),
    KEY `idx_mar_admin_facility` (`facility_id`),
    KEY `idx_mar_admin_outcome` (`outcome`),
    KEY `idx_mar_admin_scheduled` (`scheduled_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_mar_administration` (`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`, `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`, `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `witness_user_id`,
                                      `waste_amount`, `waste_unit`, `co_sign_user_id`, `co_signed_datetime`, `note`, `is_high_alert`, `created_datetime`, `updated_datetime`)
VALUES (1, 1, 1, 2, 1, '2026-02-28 19:36:41', '2026-02-28 19:39:41', 'GIVEN', '1500', 'mg', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'First dose — infused over 90 min', 1, '2026-02-28 19:36:41', '2026-02-28 19:39:41'),
       (2, 1, 1, 2, 1, '2026-03-01 03:36:41', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 19:36:41', '2026-02-28 19:36:41'),
       (3, 2, 1, 2, 1, '2026-02-28 19:39:41', '2026-02-28 19:44:41', 'GIVEN', '3.375', 'g', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'First dose after cultures drawn', 0, '2026-02-28 19:39:41', '2026-02-28 19:44:41'),
       (4, 4, 1, 2, 1, '2026-02-28 21:19:41', '2026-02-28 21:21:41', 'GIVEN', '0.05', 'mcg/kg/min', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Started for MAP 58. Titrating up.', 1, '2026-02-28 21:19:41', '2026-02-28 21:21:41'),
       (5, 4, 2, 3, 1, '2026-02-28 02:19:41', '2026-02-28 02:19:41', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen L', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Dose 1', 1, '2026-02-28 02:19:41', '2026-02-28 02:19:41'),
       (6, 4, 2, 3, 1, '2026-02-28 10:19:41', '2026-02-28 10:19:41', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Dose 2', 1, '2026-02-28 10:19:41', '2026-02-28 10:19:41'),
       (7, 4, 2, 3, 1, '2026-02-28 18:19:41', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 18:19:41', '2026-02-28 18:19:41'),
       (8, 6, 4, 2, 1, '2026-02-28 19:37:10', '2026-02-28 19:40:10', 'GIVEN', '1500', 'mg', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'First dose — infused over 90 min', 1, '2026-02-28 19:37:10', '2026-02-28 19:40:10'),
       (9, 6, 4, 2, 1, '2026-03-01 03:37:10', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 19:37:10', '2026-02-28 19:37:10'),
       (10, 7, 4, 2, 1, '2026-02-28 19:40:10', '2026-02-28 19:45:10', 'GIVEN', '3.375', 'g', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'First dose after cultures drawn', 0, '2026-02-28 19:40:10', '2026-02-28 19:45:10'),
       (11, 9, 4, 2, 1, '2026-02-28 21:20:10', '2026-02-28 21:22:10', 'GIVEN', '0.05', 'mcg/kg/min', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Started for MAP 58. Titrating up.', 1, '2026-02-28 21:20:10', '2026-02-28 21:22:10'),
       (12, 12, 5, 3, 1, '2026-02-28 02:20:10', '2026-02-28 02:20:10', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen L', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Dose 1', 1, '2026-02-28 02:20:10', '2026-02-28 02:20:10'),
       (13, 12, 5, 3, 1, '2026-02-28 10:20:10', '2026-02-28 10:20:10', 'GIVEN', '5000', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Dose 2', 1, '2026-02-28 10:20:10', '2026-02-28 10:20:10'),
       (14, 12, 5, 3, 1, '2026-02-28 18:20:10', NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-02-28 18:20:10', '2026-02-28 18:20:10'),
       (15, 16, 7, 5, 1, '2026-02-28 21:40:11', '2026-02-28 21:42:11', 'GIVEN', '30', 'mg', 'IV', 'Left AC', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Pain 7/10 pre. 3/10 at 20min post.', 0, '2026-02-28 21:40:11', '2026-02-28 21:42:11'),
       (16, 18, 9, 7, 1, '2026-02-28 20:32:11', '2026-02-28 20:34:11', 'GIVEN', '1000', 'mg', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'TXA load given 14min post-arrival. BP 94→102 post-fluid.', 1, '2026-02-28 20:32:11', '2026-02-28 20:34:11'),
       (17, 29, 11, 9, 1, '2026-02-28 21:45:11', '2026-02-28 21:46:11', 'GIVEN', '345', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Taken well. No vomiting. Temp 101.2°F at 30 min post.', 0, '2026-02-28 21:45:11', '2026-02-28 21:46:11'),
       (18, 29, 12, 10, 1, '2026-02-28 20:58:12', '2026-02-28 21:00:12', 'GIVEN', '0.4', 'mg/hr', 'IV', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Drip initiated. RR 8→14 at 15min. SpO2 91→97%. Patient awake, combative.', 1, '2026-02-28 20:58:12', '2026-02-28 21:00:12'),
       (19, 9, 4, 2, 1, '2026-02-28 19:40:13', '2026-02-28 19:43:13', 'GIVEN', '1750', 'mg', 'IV', 'Right AC', 'VAN-2024-0891', NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Infused over 90 min. No red man syndrome. Pre-dose level drawn.', 1, '2026-02-28 19:40:13',
        '2026-02-28 19:43:13'),
       (20, 9, 4, 2, 1, '2026-02-28 21:00:13', '2026-02-28 21:02:13', 'HELD', NULL, NULL, NULL, NULL, NULL, 'LEVEL_HIGH', 1, NULL, NULL, NULL, NULL, NULL, 'Pre-dose vancomycin level 22 — holding per pharmacy. Will re-dose when level < 15.', 1, '2026-02-28 21:00:13',
        '2026-02-28 21:02:13'),
       (21, 25, 10, 8, 1, '2026-02-28 18:20:13', '2026-02-28 18:21:13', 'GIVEN', '2.5', 'mg', 'INH', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL,
        '[Amended 2026-02-27 07:15:00 by user 1] Original entry: HELD/NPO in error — patient tolerating PO. Corrected to GIVEN after review of physician orders.', 0, '2026-02-28 18:20:13', '2026-02-28 18:20:13'),
       (22, 32, 14, 50, 1, '2026-02-27 21:00:00', '2026-02-27 21:05:00', 'GIVEN', '10', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Administered with evening water. Resident cooperative.', 0, '2026-02-27 21:05:00', '2026-02-27 21:05:00'),
       (23, 32, 14, 50, 1, '2026-02-28 21:00:00', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
       (24, 40, 17, 53, 1, '2026-02-28 07:00:00', '2026-02-28 07:12:00', 'GIVEN', '25/100', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Within 30-min window. Resident in off-period pre-dose and  good response observed 45 min post.', 0, '2026-02-28 07:12:00',
        '2026-02-28 07:12:00'),
       (25, 40, 17, 53, 1, '2026-02-28 12:00:00', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
       (26, 40, 17, 53, 1, '2026-02-28 17:00:00', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
       (27, 42, 18, 54, 1, '2026-02-27 08:00:00', '2026-02-27 08:05:00', 'GIVEN', '20', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'Correct 20mg dose verified by barcode. Weight pre-dose 143.0 lbs.', 0, '2026-02-27 08:05:00', '2026-02-27 08:05:00'),
       (28, 42, 18, 54, 1, '2026-02-28 08:00:00', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Verify 20mg dose — double check after yesterday med error', 0, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
       (29, 44, 18, 54, 1, '2026-02-27 21:00:00', '2026-02-27 21:08:00', 'GIVEN', '18', 'units', 'SQ', 'Abdomen R', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'FBG 162 at HS. Base dose 18u. No sliding scale addition needed.', 0, '2026-02-27 21:08:00', '2026-02-27 21:08:00'),
       (30, 44, 18, 54, 1, '2026-02-28 21:00:00', NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-02-28 06:00:00', '2026-02-28 06:00:00'),
       (31, 38, 16, 52, 1, '2026-02-28 08:00:00', '2026-02-28 08:10:00', 'GIVEN', '18', 'mcg', 'Inh', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL, 'HandiHaler administered. Technique confirmed. SpO2 95% post-dose.', 0, '2026-02-28 08:10:00', '2026-02-28 08:10:00');

INSERT INTO `oei_mar_administration` (`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`, `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`, `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `witness_user_id`,
                                      `waste_amount`, `waste_unit`, `co_sign_user_id`, `co_signed_datetime`, `note`, `is_high_alert`, `created_datetime`, `updated_datetime`)
VALUES (50, 50, 19, 2, 1, '2026-02-23 10:00:00', '2026-02-23 10:15:00', 'GIVEN',
        '1250', 'mg', 'IV', NULL, 'VAN-202601', NULL, 1, NULL, NULL, NULL, NULL, NULL,
        'Infused over 90 min, no red-man reaction', 1, '2026-02-23 10:00:00', '2026-02-23 10:00:00'),
       (51, 50, 19, 2, 1, '2026-02-23 22:00:00', '2026-02-23 22:12:00', 'GIVEN',
        '1250', 'mg', 'IV', NULL, 'VAN-202601', NULL, 1, NULL, NULL, NULL, NULL, NULL,
        'Trough 18.2 mcg/mL — therapeutic, continue current dose', 1, '2026-02-23 22:00:00', '2026-02-23 22:00:00'),
       (52, 52, 19, 2, 1, '2026-02-23 12:00:00', '2026-02-23 12:10:00', 'GIVEN',
        '25000', 'units/500mL', 'IV', 'Right AC', NULL, NULL, 1, 3, '0.2', 'mL', NULL, NULL,
        'Continuous infusion started at 18 units/kg/h, pump verified x2', 1, '2026-02-23 12:00:00', '2026-02-23 12:00:00'),
       (53, 53, 20, 5, 1, '2026-02-26 18:00:00', '2026-02-26 18:05:00', 'GIVEN',
        '5', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL,
        'Pain 7/10 pre-dose, 4/10 at 1 h, tolerating oral meds', 0, '2026-02-26 18:00:00', '2026-02-26 18:00:00'),
       (54, 53, 20, 5, 1, '2026-02-27 02:00:00', '2026-02-27 02:08:00', 'GIVEN',
        '5', 'mg', 'PO', NULL, NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL,
        'Overnight pain 6/10, RR 14, given after reassessment', 0, '2026-02-27 02:00:00', '2026-02-27 02:00:00'),
       (55, 54, 20, 5, 1, '2026-02-26 14:30:00', '2026-02-26 14:40:00', 'GIVEN',
        '1', 'g', 'IV', 'Left AC', NULL, NULL, 1, NULL, NULL, NULL, NULL, NULL,
        'Dose 1 of 3 — peri-operative', 0, '2026-02-26 14:30:00', '2026-02-26 14:30:00'),
       (56, 56, 21, 6, 1, '2026-02-26 22:30:00', '2026-02-26 22:42:00', 'GIVEN',
        '25000', 'units/500mL', 'IV', 'Right PIV', NULL, NULL, 1, 3, '0.5', 'mL', NULL, NULL,
        'Initiated at 18 u/kg/h per nomogram, aPTT ordered for 0600', 1, '2026-02-26 22:30:00', '2026-02-26 22:30:00'),
       (57, 63, 23, 11, 1, '2026-02-27 20:00:00', '2026-02-27 20:06:00', 'GIVEN',
        '2', 'mg', 'IV', 'Right PIV', NULL, NULL, 1, 3, NULL, NULL, NULL, NULL,
        'Post-op pain 8/10, RR 16 pre-dose, monitor 30 min', 1, '2026-02-27 20:00:00', '2026-02-27 20:00:00'),
       (58, 63, 23, 11, 1, '2026-02-28 00:00:00', '2026-02-28 00:10:00', 'HELD',
        NULL, NULL, NULL, NULL, NULL, 'SEDATED', 1, NULL, NULL, NULL, NULL, NULL,
        'Patient asleep, RASS -2, deferred per clinical judgment', 1, '2026-02-28 00:00:00', '2026-02-28 00:00:00'),
       (59, 63, 23, 11, 1, '2026-02-28 04:00:00', '2026-02-28 04:08:00', 'GIVEN',
        '2', 'mg', 'IV', 'Right PIV', NULL, NULL, 1, 3, NULL, NULL, NULL, NULL,
        'Woke with pain 7/10, RR 14, given per order', 1, '2026-02-28 04:00:00', '2026-02-28 04:00:00');

-- oei_task
CREATE TABLE IF NOT EXISTS `oei_task`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED NOT NULL,
    `pid`                 bigint(20) UNSIGNED NOT NULL,
    `eid`                 bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `task_type`           varchar(50)         NOT NULL,
    `due_datetime`        datetime            NOT NULL,
    `completed_datetime`  datetime                     DEFAULT NULL,
    `assigned_to_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `status`              varchar(20)         NOT NULL DEFAULT 'OPEN',
    `payload_json`        mediumtext                   DEFAULT NULL,
    `created_by_user_id`  bigint(20) UNSIGNED          DEFAULT NULL,
    `created_datetime`    datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_task_due` (`facility_id`, `status`, `due_datetime`),
    KEY `idx_oei_task_episode` (`episode_id`, `status`, `due_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_task` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`, `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`, `created_by_user_id`, `created_datetime`)
VALUES (1, 1, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 20:49:41', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"2 sets before antibiotics — OVERDUE"}', 1, '2026-02-28 19:27:41'),
       (2, 1, 2, NULL, 1, 'LACTATE', '2026-02-28 20:19:41', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"Serum lactate — sepsis bundle"}', 1, '2026-02-28 19:27:41'),
       (3, 1, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:39:41', NULL, NULL, 'COMPLETE', '{"note":"30mL/kg NS bolus completed","ml_given":2400}', 1, '2026-02-28 19:27:41'),
       (4, 1, 2, NULL, 1, 'ANTIBIOTICS', '2026-02-28 19:39:41', NULL, NULL, 'COMPLETE', '{"note":"Vancomycin 1500mg + Pip-Tazo 3.375g started"}', 1, '2026-02-28 19:27:41'),
       (5, 1, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:49:41', NULL, NULL, 'OPEN', '{"source":"auto","priority":"URGENT"}', 1, '2026-02-28 19:27:41'),
       (6, 1, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 22:49:41', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 19:27:41'),
       (7, 1, 2, NULL, 1, 'CHEST_XRAY', '2026-02-28 21:19:41', NULL, NULL, 'COMPLETE', '{"result":"Bilateral infiltrates consistent with pneumonia / ARDS"}', 1, '2026-02-28 19:27:41'),
       (8, 1, 2, NULL, 1, 'ICU_BED_REQUEST', '2026-02-28 21:49:41', NULL, NULL, 'OPEN', '{"priority":"URGENT","note":"Patient likely needs ICU. Awaiting bed."}', 1, '2026-02-28 21:19:41'),
       (9, 2, 3, NULL, 1, 'EKG', '2026-02-28 02:19:41', NULL, NULL, 'COMPLETE', '{"result":"NSR, no acute changes, no STEMI"}', 1, '2026-02-28 02:19:41'),
       (10, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 02:19:41', NULL, NULL, 'COMPLETE', '{"label":"Troponin #1","result":"0.012 ng/mL (normal < 0.04)"}', 1, '2026-02-28 02:19:41'),
       (11, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 08:19:41', NULL, NULL, 'COMPLETE', '{"label":"Troponin #2","result":"0.010 ng/mL — negative trend"}', 1, '2026-02-28 02:19:41'),
       (12, 2, 3, NULL, 1, 'TROPONIN', '2026-02-28 14:19:41', NULL, NULL, 'COMPLETE', '{"label":"Troponin #3","result":"0.009 ng/mL — rule-out complete"}', 1, '2026-02-28 02:19:41'),
       (13, 2, 3, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:19:41', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 02:19:41'),
       (14, 2, 3, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 20:19:41', NULL, NULL, 'OPEN', '{"label":"Cardiology consult completed. Discharge with f/u vs stress test?"}', 1, '2026-02-28 02:19:41'),
       (15, 4, 2, NULL, 1, 'BLOOD_CULTURE', '2026-02-28 20:50:10', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"2 sets before antibiotics — OVERDUE"}', 1, '2026-02-28 19:28:10'),
       (16, 4, 2, NULL, 1, 'LACTATE', '2026-02-28 20:20:10', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"Serum lactate — sepsis bundle"}', 1, '2026-02-28 19:28:10'),
       (17, 4, 2, NULL, 1, 'IV_FLUID', '2026-02-28 19:40:10', NULL, NULL, 'COMPLETE', '{"note":"30mL/kg NS bolus completed","ml_given":2400}', 1, '2026-02-28 19:28:10'),
       (18, 4, 2, NULL, 1, 'ANTIBIOTICS', '2026-02-28 19:40:10', NULL, NULL, 'COMPLETE', '{"note":"Vancomycin 1500mg + Pip-Tazo 3.375g started"}', 1, '2026-02-28 19:28:10'),
       (19, 4, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:50:10', NULL, NULL, 'OPEN', '{"source":"auto","priority":"URGENT"}', 1, '2026-02-28 19:28:10'),
       (20, 4, 2, NULL, 1, 'VITALS_CHECK', '2026-02-28 22:50:10', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 19:28:10'),
       (21, 4, 2, NULL, 1, 'CHEST_XRAY', '2026-02-28 21:20:10', NULL, NULL, 'COMPLETE', '{"result":"Bilateral infiltrates consistent with pneumonia / ARDS"}', 1, '2026-02-28 19:28:10'),
       (22, 4, 2, NULL, 1, 'ICU_BED_REQUEST', '2026-02-28 21:50:10', NULL, NULL, 'OPEN', '{"priority":"URGENT","note":"Patient likely needs ICU. Awaiting bed."}', 1, '2026-02-28 21:20:10'),
       (23, 5, 3, NULL, 1, 'EKG', '2026-02-28 02:20:10', NULL, NULL, 'COMPLETE', '{"result":"NSR, no acute changes, no STEMI"}', 1, '2026-02-28 02:20:10'),
       (24, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 02:20:10', NULL, NULL, 'COMPLETE', '{"label":"Troponin #1","result":"0.012 ng/mL (normal < 0.04)"}', 1, '2026-02-28 02:20:10'),
       (25, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 08:20:10', NULL, NULL, 'COMPLETE', '{"label":"Troponin #2","result":"0.010 ng/mL — negative trend"}', 1, '2026-02-28 02:20:10'),
       (26, 5, 3, NULL, 1, 'TROPONIN', '2026-02-28 14:20:10', NULL, NULL, 'COMPLETE', '{"label":"Troponin #3","result":"0.009 ng/mL — rule-out complete"}', 1, '2026-02-28 02:20:10'),
       (27, 5, 3, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:20:10', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 02:20:10'),
       (28, 5, 3, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 20:20:10', NULL, NULL, 'OPEN', '{"label":"Cardiology consult completed. Discharge with f/u vs stress test?"}', 1, '2026-02-28 02:20:10'),
       (29, 6, 4, NULL, 1, 'BH_SAFETY_SCREEN', '2026-02-28 17:30:11', NULL, NULL, 'COMPLETE', '{"note":"Columbia SSRS completed — high risk"}', 1, '2026-02-28 17:25:11'),
       (30, 6, 4, NULL, 1, 'BH_PLACEMENT_CALL', '2026-02-28 20:20:11', NULL, NULL, 'OPEN', '{"priority":"HIGH","note":"Re-call Valley BH — waitlist update"}', 1, '2026-02-28 17:30:11'),
       (31, 6, 4, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:20:11', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 17:30:11'),
       (32, 6, 4, NULL, 1, 'EMTALA_DOCUMENTATION', '2026-02-28 18:20:11', NULL, NULL, 'COMPLETE', '{"note":"MSE complete, signed"}', 1, '2026-02-28 17:25:11'),
       (33, 7, 5, NULL, 1, 'X_RAY_ORDER', '2026-02-28 21:40:11', NULL, NULL, 'COMPLETE', '{"label":"Right ankle 3 views","result":"No acute fracture. Soft tissue swelling lateral malleolus."}', 1, '2026-02-28 21:25:11'),
       (34, 7, 5, NULL, 1, 'X_RAY_REVIEW', '2026-02-28 22:10:11', NULL, NULL, 'COMPLETE', '{"label":"Radiology read reviewed with patient"}', 1, '2026-02-28 21:25:11'),
       (35, 7, 5, NULL, 1, 'DISCHARGE_INSTRUCTIONS', '2026-02-28 22:15:11', NULL, NULL, 'COMPLETE', '{"label":"Ankle sprain instructions printed and reviewed"}', 1, '2026-02-28 21:25:11'),
       (36, 8, 6, NULL, 1, 'NIHSS_SCORE', '2026-02-28 22:00:11', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"Initial NIHSS — neurology at bedside"}', 1, '2026-02-28 21:55:11'),
       (37, 8, 6, NULL, 1, 'CT_HEAD_STAT', '2026-02-28 22:05:11', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"Non-contrast CT head — no contrast if tPA candidate"}', 1, '2026-02-28 21:55:11'),
       (38, 8, 6, NULL, 1, 'CT_ANGIO', '2026-02-28 22:10:11', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"CTA head and neck for LVO workup"}', 1, '2026-02-28 21:55:11'),
       (39, 8, 6, NULL, 1, 'LABS_STAT', '2026-02-28 22:00:11', NULL, NULL, 'OPEN', '{"priority":"STAT","note":"CBC, BMP, coags, type & screen STAT"}', 1, '2026-02-28 21:55:11'),
       (40, 8, 6, NULL, 1, 'IV_ACCESS', '2026-02-28 21:58:11', NULL, NULL, 'COMPLETE', '{"note":"Two 18g IVs — right AC and right hand"}', 1, '2026-02-28 21:55:11'),
       (41, 8, 6, NULL, 1, 'NEUROLOGY_CONSULT', '2026-02-28 21:55:11', NULL, NULL, 'COMPLETE', '{"note":"Neurology on scene — evaluating"}', 1, '2026-02-28 21:55:11'),
       (42, 8, 6, NULL, 1, 'TPA_DECISION', '2026-02-28 22:25:11', NULL, NULL, 'OPEN', '{"label":"tPA eligibility decision — LKW 90min, CT result pending"}', 1, '2026-02-28 21:55:11'),
       (43, 9, 7, NULL, 1, 'FAST_EXAM', '2026-02-28 20:25:11', NULL, NULL, 'COMPLETE', '{"result":"FAST positive — free fluid LUQ and pelvis"}', 1, '2026-02-28 20:23:11'),
       (44, 9, 7, NULL, 1, 'CT_ABDOMEN_STAT', '2026-02-28 20:50:11', NULL, NULL, 'COMPLETE', '{"result":"Grade III splenic laceration. Active extravasation. Surgical consult."}', 1, '2026-02-28 20:23:11'),
       (45, 9, 7, NULL, 1, 'BLOOD_BANK', '2026-02-28 20:30:11', NULL, NULL, 'COMPLETE', '{"result":"Type O-neg x2 units released. MTP activated."}', 1, '2026-02-28 20:23:11'),
       (46, 9, 7, NULL, 1, 'TRANSFER_ACCEPT', '2026-02-28 21:50:11', NULL, NULL, 'COMPLETE', '{"result":"Regional Trauma Center accepted. Transport ETA 15 min."}', 1, '2026-02-28 21:20:11'),
       (47, 9, 7, NULL, 1, 'TRANSFER_TRANSPORT', '2026-02-28 22:35:11', NULL, NULL, 'OPEN', '{"note":"Medic 7 transport. Trauma surgeon standing by at RTC."}', 1, '2026-02-28 21:50:11'),
       (48, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 11:20:11', NULL, NULL, 'COMPLETE', '{"result":"Peak flow 38% predicted (baseline)"}', 1, '2026-02-28 11:20:11'),
       (49, 10, 8, NULL, 1, 'ABG', '2026-02-28 12:20:11', NULL, NULL, 'COMPLETE', '{"result":"pH 7.38, pCO2 52, pO2 68, HCO3 31 — compensated"}', 1, '2026-02-28 11:20:11'),
       (50, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 17:20:11', NULL, NULL, 'COMPLETE', '{"result":"Peak flow 55% predicted — improving"}', 1, '2026-02-28 11:20:11'),
       (51, 10, 8, NULL, 1, 'SPIROMETRY', '2026-02-28 21:20:11', NULL, NULL, 'COMPLETE', '{"result":"Peak flow 62% predicted — ready for discharge eval"}', 1, '2026-02-28 11:20:11'),
       (52, 10, 8, NULL, 1, 'VITALS_CHECK', '2026-02-28 20:20:11', NULL, NULL, 'COMPLETE', '{"source":"auto"}', 1, '2026-02-28 11:20:11'),
       (53, 10, 8, NULL, 1, 'DISPOSITION_DECISION', '2026-02-28 22:50:11', NULL, NULL, 'OPEN', '{"note":"Discharge home with home health if peak flow > 60%"}', 1, '2026-02-28 20:20:11'),
       (54, 11, 9, NULL, 1, 'UA_RESULT', '2026-02-28 22:30:11', NULL, NULL, 'OPEN', '{"note":"Catheter UA — awaiting result"}', 1, '2026-02-28 21:42:11'),
       (55, 11, 9, NULL, 1, 'CBC_REVIEW', '2026-02-28 22:40:11', NULL, NULL, 'OPEN', '{"note":"CBC with diff to r/o bacteremia"}', 1, '2026-02-28 21:42:11'),
       (56, 11, 9, NULL, 1, 'ANTIPYRETIC', '2026-02-28 21:45:11', NULL, NULL, 'COMPLETE', '{"note":"Acetaminophen given — temp 101.2 at 30min post-dose"}', 1, '2026-02-28 21:42:11'),
       (57, 11, 9, NULL, 1, 'TEMP_RECHECK', '2026-02-28 22:35:11', NULL, NULL, 'OPEN', '{"source":"auto"}', 1, '2026-02-28 21:42:11'),
       (58, 12, 10, NULL, 1, 'VITALS_CHECK', '2026-02-28 21:50:12', NULL, NULL, 'OPEN', '{"priority":"URGENT","note":"Re-sedation watch — Narcan shorter half-life than opiates"}', 1, '2026-02-28 20:54:12'),
       (59, 12, 10, NULL, 1, 'NALOXONE_TITRATE', '2026-02-28 21:20:12', NULL, NULL, 'COMPLETE', '{"note":"Drip started 0.1mg/hr. Titrating to adequate respiratory rate."}', 1, '2026-02-28 20:54:12'),
       (60, 12, 10, NULL, 1, 'BH_CONSULT', '2026-02-28 22:50:12', NULL, NULL, 'OPEN', '{"priority":"ROUTINE","note":"BH consult for MOUD discussion when patient cooperative"}', 1, '2026-02-28 20:54:12'),
       (61, 12, 10, NULL, 1, 'NARCAN_EDUCATION', '2026-03-01 00:20:12', NULL, NULL, 'OPEN', '{"note":"Naloxone Rx and overdose education — defer until patient cooperative"}', 1, '2026-02-28 20:54:12'),
       (62, 13, 11, NULL, 1, 'BH_SAFETY_SCREEN', '2026-02-28 15:20:12', NULL, NULL, 'COMPLETE', '{"note":"Columbia SSRS — moderate risk, no plan"}', 1, '2026-02-28 14:25:12'),
       (63, 13, 11, NULL, 1, 'EMTALA_DOCUMENTATION', '2026-02-28 15:20:12', NULL, NULL, 'COMPLETE', '{"note":"MSE complete, signed by attending"}', 1, '2026-02-28 14:25:12'),
       (64, 13, 11, NULL, 1, 'BH_PLACEMENT_CALL', '2026-02-28 16:20:12', NULL, NULL, 'COMPLETE', '{"note":"Called Valley BH, Riverside BH, State Hospital"}', 1, '2026-02-28 14:25:12'),
       (65, 13, 11, NULL, 1, 'VITALS_CHECK', '2026-02-28 20:20:12', NULL, NULL, 'COMPLETE', '{"source":"auto","note":"Stable vitals x8h"}', 1, '2026-02-28 14:25:12'),
       (66, 13, 11, NULL, 1, 'TRANSPORT_CONFIRM', '2026-02-28 22:35:12', NULL, NULL, 'OPEN', '{"note":"Confirm Medvan arrival with driver — patient ready"}', 1, '2026-02-28 20:50:12'),
       (67, 14, 50, NULL, 1, 'CARE_PLAN_REVIEW', '2026-03-12 10:00:00', NULL, 1, 'OPEN', '{"note":"60-day care plan review — fall risk, behavioral, music therapy outcomes"}', 1, '2026-01-12 23:00:00'),
       (68, 14, 50, NULL, 1, 'FALL_RISK_REASSESS', '2026-03-13 09:00:00', NULL, 1, 'OPEN', '{"tool":"Morse","note":"30-day reassessment — re-score and update care plan if tier changes"}', 1, '2026-02-12 10:00:00'),
       (69, 14, 50, NULL, 1, 'ADL_ROUND', '2026-03-01 01:57:27', NULL, 1, 'OPEN', '{"note":"Evening ADL round — bathing assist and bed alarm check"}', 1, '2026-02-28 06:00:00'),
       (70, 15, 51, NULL, 1, 'PT_SESSION', '2026-03-01 09:00:00', NULL, 1, 'OPEN', '{"note":"Week 5 PT — target 150 ft ambulation, stair negotiation assessment"}', 1, '2026-01-28 23:00:00'),
       (71, 15, 51, NULL, 1, 'PAIN_ASSESSMENT', '2026-03-01 00:57:27', NULL, 1, 'OPEN', '{"note":"Post-PT pain score — NRS target 3 or below and  document opioid taper progress"}', 1, '2026-02-28 06:00:00'),
       (72, 15, 51, NULL, 1, 'CARE_PLAN_REVIEW', '2026-03-28 10:00:00', NULL, 1, 'OPEN', '{"note":"60-day review — ambulation goal assessment, discharge planning discussion"}', 1, '2026-01-28 23:00:00'),
       (73, 16, 52, NULL, 1, 'VITALS_CHECK', '2026-03-01 00:57:27', NULL, 1, 'OPEN', '{"note":"Morning vitals — SpO2 target above 92% RA and  document inhaler adherence"}', 1, '2026-02-10 22:45:00'),
       (74, 16, 52, NULL, 1, 'PULM_REASSESSMENT', '2026-03-10 10:00:00', NULL, 1, 'OPEN', '{"note":"30-day pulmonary reassessment — spirometry if available, adjust O2 threshold"}', 1, '2026-02-10 22:45:00'),
       (75, 17, 53, NULL, 1, 'POST_FALL_FOLLOWUP', '2026-03-01 00:57:27', NULL, 1, 'OPEN', '{"note":"Post-fall neuro check q1h x4 — clear before resuming ambulation"}', 1, '2026-02-28 06:30:00'),
       (76, 17, 53, NULL, 1, 'XRAY_FOLLOWUP', '2026-03-01 02:57:27', NULL, 1, 'OPEN', '{"note":"Review X-ray results — right forearm and hip and  PT clearance for ambulation"}', 1, '2026-02-28 06:30:00'),
       (77, 17, 53, NULL, 1, 'MEDICATION', '2026-03-01 00:42:27', NULL, 1, 'OPEN', '{"drug":"Carbidopa/Levodopa","note":"Administer within 30-min window of scheduled time"}', 1, '2026-02-28 06:00:00'),
       (78, 17, 53, NULL, 1, 'CARE_PLAN_REVIEW', '2026-03-28 10:00:00', NULL, 1, 'OPEN', '{"note":"90-day review — fall protocol, aspiration events, mobility reassessment"}', 1, '2025-12-28 23:30:00'),
       (79, 18, 54, NULL, 1, 'WEIGHT_CHECK', '2026-03-01 00:57:27', NULL, 1, 'OPEN', '{"note":"Daily weight — alert if above 142.2 lbs (2 lb threshold). Document FBG."}', 1, '2026-02-19 23:00:00'),
       (80, 18, 54, NULL, 1, 'MEDICATION', '2026-03-01 00:27:27', NULL, 1, 'OPEN', '{"drug":"Furosemide 20mg","note":"Verify correct dose after yesterday medication error — double-check MAR"}', 1, '2026-02-28 06:00:00'),
       (81, 18, 54, NULL, 1, 'CARE_PLAN_REVIEW', '2026-03-19 10:00:00', NULL, 1, 'OPEN', '{"note":"30-day review — weight trend, glycemic control, fluid restriction adherence"}', 1, '2026-02-19 23:00:00');

INSERT INTO `oei_task` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`, `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`, `created_by_user_id`, `created_datetime`)
VALUES (82, 19, 2, NULL, 1, 'LABS', '2026-02-28 06:00:00', '2026-02-28 06:30:00', 1, 'COMPLETE', '{"test":"Vancomycin trough + BMP","result":"Trough 16.4 - therapeutic"}', 1, '2026-02-27 22:00:00'),
       (83, 19, 2, NULL, 1, 'VITALS', '2026-02-28 14:00:00', NULL, 1, 'OPEN', '{"note":"Q4H vitals — monitor for weaning tolerance"}', 1, '2026-02-28 08:00:00'),
       (84, 20, 5, NULL, 1, 'PT_SESSION', '2026-02-28 09:00:00', '2026-02-28 09:45:00', 1, 'COMPLETE', '{"note":"Day 2 PT: 50 ft ambulation, stair evaluation 4 steps, cleared for home"}', 1, '2026-02-27 18:00:00'),
       (85, 20, 5, NULL, 1, 'DISCHARGE_MEDS', '2026-02-28 14:00:00', NULL, 1, 'OPEN', '{"note":"Discharge prescriptions: oxycodone x20, enoxaparin 35-day supply, PT referral"}', 1, '2026-02-28 09:00:00'),
       (86, 21, 6, NULL, 1, 'LABS', '2026-02-28 06:00:00', '2026-02-28 06:30:00', 1, 'COMPLETE', '{"test":"Troponin I + BMP + lipid panel","result":"Troponin 0.9 trending to baseline, LDL 142"}', 1, '2026-02-27 20:00:00'),
       (87, 21, 6, NULL, 1, 'PATIENT_EDUCATION', '2026-02-28 10:00:00', '2026-02-28 10:30:00', 1, 'COMPLETE', '{"note":"DAPT education: do not stop clopidogrel without cardiology approval"}', 1, '2026-02-28 08:00:00'),
       (88, 22, 8, NULL, 1, 'VITALS', '2026-02-28 08:00:00', '2026-02-28 08:10:00', 1, 'COMPLETE', '{"note":"Afebrile 37.1 C, SpO2 96% on 1L NC, RR 18 — improving"}', 1, '2026-02-28 06:00:00'),
       (89, 22, 8, NULL, 1, 'DISCHARGE_MEDS', '2026-02-28 14:00:00', NULL, 1, 'OPEN', '{"note":"PO azithromycin x3 days to complete course, chest X-ray in 6 weeks"}', 1, '2026-02-28 10:00:00'),
       (90, 23, 11, NULL, 1, 'LABS', '2026-02-28 06:00:00', '2026-02-28 06:30:00', 1, 'COMPLETE', '{"test":"CBC post-op","result":"Hgb 9.2 - no transfusion threshold met"}', 1, '2026-02-28 05:00:00'),
       (91, 23, 11, NULL, 1, 'PT_SESSION', '2026-02-28 14:00:00', NULL, 1, 'OPEN', '{"note":"Day 2 post-op PT: advance ambulation, initiate stair training if tolerated"}', 1, '2026-02-28 11:00:00'),
       (92, 23, 11, NULL, 1, 'SOCIAL_WORK', '2026-02-28 10:00:00', '2026-02-28 11:00:00', 1, 'COMPLETE', '{"note":"SNF placement recommended - patient lives alone, family meeting scheduled"}', 1, '2026-02-28 08:00:00');

-- oei_episode_disposition
CREATE TABLE IF NOT EXISTS `oei_episode_disposition`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `disposition_code`   varchar(30)         NOT NULL,
    `destination`        varchar(60)                  DEFAULT NULL,
    `decision_datetime`  datetime                     DEFAULT NULL,
    `depart_datetime`    datetime                     DEFAULT NULL,
    `admit_flag`         tinyint(1)          NOT NULL DEFAULT 0,
    `notes`              varchar(255)                 DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_disp_episode` (`episode_id`),
    KEY `idx_oei_disp_facility` (`facility_id`, `disposition_code`, `updated_datetime`),
    KEY `idx_oei_disp_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_episode_disposition` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `disposition_code`, `destination`, `decision_datetime`, `depart_datetime`, `admit_flag`, `notes`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 7, 5, NULL, 1, 'DISCHARGE', 'Home with ortho follow-up', '2026-02-28 22:12:11', NULL, 0, 'Grade II lateral ankle sprain. No fracture on Ottawa criteria. PRICE. Ibuprofen. Ortho f/u if not improving in 1 week. Return for increasing swelling, numbness, or instability.',
        1, '2026-02-28 22:12:11'),
       (2, 17, 53, NULL, 1, 'HOSPITAL_EVAL', 'Springfield General ER', '2026-02-28 07:00:00', NULL, 0, 'Pending decision — X-ray results awaited. If hip fracture confirmed, transfer to ED. PT and provider to reassess at noon.', 1, '2026-02-28 07:15:00'),
       (3, 15, 51, NULL, 1, 'HOME_DISCHARGE', 'Home with outpatient PT', '2026-02-20 10:00:00', NULL, 0, 'Planned discharge at 60-day mark per care plan goal. PT to confirm ambulation independence. Family notified to arrange home assessment.', 1, '2026-02-20 10:00:00');

INSERT INTO `oei_episode_disposition` (`id`, `episode_id`, `pid`, `eid`, `facility_id`,
                                       `disposition_code`, `destination`, `decision_datetime`, `depart_datetime`, `admit_flag`,
                                       `notes`, `updated_by_user_id`, `updated_datetime`)
VALUES (4, 20, 5, NULL, 1, 'DISCHARGE_HOME', 'Home with home health PT', '2026-02-28 09:00:00', NULL, 0,
        'Walker required, home PT x6 visits arranged, follow-up with orthopedics in 2 weeks',
        1, '2026-02-28 09:00:00'),
       (5, 21, 6, NULL, 1, 'DISCHARGE_HOME', 'Home with outpatient cardiology follow-up', '2026-02-28 09:00:00', NULL, 0,
        'DAPT: aspirin 81mg indefinitely plus clopidogrel 75mg x12 months. Outpatient echo in 4 weeks. Cardiac rehab referral sent.',
        1, '2026-02-28 09:00:00'),
       (6, 23, 11, NULL, 1, 'SNF', 'Valley Rehabilitation Center', '2026-02-28 11:30:00', NULL, 0,
        'Patient lives alone — cannot safely manage WBAT without support. SNF placement for rehab pending insurance auth. Expected SNF stay 3-4 weeks.',
        1, '2026-02-28 11:30:00');

-- oei_ereferral
CREATE TABLE IF NOT EXISTS `oei_ereferral`
(
    `id`                       int(10) UNSIGNED                                        NOT NULL AUTO_INCREMENT,
    `episode_id`               int(10) UNSIGNED                                        NOT NULL,
    `pid`                      bigint(20)                                              NOT NULL,
    `eid`                      bigint(20)                                                       DEFAULT NULL,
    `facility_id`              int(10) UNSIGNED                                        NOT NULL,
    `referral_type`            enum ('DISCHARGE','TRANSFER','BH_PLACEMENT')            NOT NULL DEFAULT 'DISCHARGE',
    `status`                   enum ('DRAFT','SENT','ACCEPTED','DECLINED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
    `priority`                 enum ('ROUTINE','URGENT','EMERGENT')                    NOT NULL DEFAULT 'ROUTINE',
    `destination_directory_id` int(10) UNSIGNED                                                 DEFAULT NULL,
    `destination_name`         varchar(200)                                                     DEFAULT NULL,
    `destination_fax`          varchar(30)                                                      DEFAULT NULL,
    `destination_phone`        varchar(30)                                                      DEFAULT NULL,
    `destination_address`      text                                                             DEFAULT NULL,
    `reason_for_referral`      text                                                             DEFAULT NULL,
    `clinical_summary`         text                                                             DEFAULT NULL,
    `services_requested`       text                                                             DEFAULT NULL,
    `medications_summary`      text                                                             DEFAULT NULL,
    `followup_instructions`    text                                                             DEFAULT NULL,
    `sent_datetime`            datetime                                                         DEFAULT NULL,
    `sent_by_user_id`          int(11)                                                          DEFAULT NULL,
    `send_method`              enum ('MANUAL','FAX','DIRECT','PRINT')                  NOT NULL DEFAULT 'MANUAL',
    `response_datetime`        datetime                                                         DEFAULT NULL,
    `response_by_name`         varchar(120)                                                     DEFAULT NULL,
    `response_notes`           text                                                             DEFAULT NULL,
    `created_by_user_id`       int(11)                                                          DEFAULT NULL,
    `created_datetime`         datetime                                                NOT NULL,
    `updated_datetime`         datetime                                                NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ereferral_episode` (`episode_id`),
    KEY `idx_ereferral_facility` (`facility_id`),
    KEY `idx_ereferral_status` (`status`),
    KEY `idx_ereferral_pid` (`pid`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_ereferral` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `referral_type`, `status`, `priority`, `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`, `destination_address`, `reason_for_referral`, `clinical_summary`,
                             `services_requested`, `medications_summary`, `followup_instructions`, `sent_datetime`, `sent_by_user_id`, `send_method`, `response_datetime`, `response_by_name`, `response_notes`, `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES (1, 2, 3, NULL, 1, 'DISCHARGE', 'DRAFT', 'URGENT', 7, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', NULL, 'ACS rule-out complete. Three negative troponins. EKG without acute changes. Requesting cardiology follow-up and outpatient stress test.',
        'Chest pain with TIMI score 3/7. Serial troponins x3 negative. No EKG changes. HTN, DM, hyperlipidemia. Stable for discharge.', 'Outpatient stress test within 72h and  lipid panel and  medication reconciliation',
        'Aspirin 325 mg PO QD\nMetoprolol 25 mg PO BID\nHeparin 5000 units SQ Q8H (inpatient — discontinue at discharge)', 'Cardiology f/u within 72h. Return precautions for recurrent chest pain, SOB, syncope.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 21:19:41',
        '2026-02-28 21:19:41'),
       (2, 5, 3, NULL, 1, 'DISCHARGE', 'DRAFT', 'URGENT', 7, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', NULL, 'ACS rule-out complete. Three negative troponins. EKG without acute changes. Requesting cardiology follow-up and outpatient stress test.',
        'Chest pain with TIMI score 3/7. Serial troponins x3 negative. No EKG changes. HTN, DM, hyperlipidemia. Stable for discharge.', 'Outpatient stress test within 72h and  lipid panel and  medication reconciliation',
        'Aspirin 325 mg PO QD\nMetoprolol 25 mg PO BID\nHeparin 5000 units SQ Q8H (inpatient — discontinue at discharge)', 'Cardiology f/u within 72h. Return precautions for recurrent chest pain, SOB, syncope.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 21:20:10',
        '2026-02-28 21:20:10'),
       (3, 7, 5, NULL, 1, 'DISCHARGE', 'DRAFT', 'ROUTINE', 8, 'Orthopedic Associates of Riverside', '(555) 840-6601', '(555) 840-6600', NULL, 'Grade II lateral ankle sprain. Patient requires orthopedic follow-up within 1 week if not improving with conservative management.',
        'Right ankle pain. X-ray: no fracture. Swelling lateral malleolus. Ottawa rules negative. PRICE initiated.', 'Orthopedic evaluation within 1 week and  consider MRI if not improving', 'Ketorolac 30 mg IV (one dose, ED only)\nIbuprofen 600 mg PO Q6H x5 days',
        'Weight bear as tolerated with crutches if needed. Ice 20 min Q2H. Elevate. Return if numbness, worsening swelling, inability to bear weight.', NULL, NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 22:15:11', '2026-02-28 22:15:11'),
       (4, 10, 8, NULL, 1, 'DISCHARGE', 'SENT', 'ROUTINE', 6, 'Valley Home Health Agency', '(555) 620-5501', '(555) 620-5500', NULL, 'COPD exacerbation, stabilised. Requires home health for nebulizer management, medication education, and peak flow monitoring.',
        'COPD (GOLD Stage III). Exacerbation treated with systemic steroids, antibiotics, and bronchodilators. SpO2 96% on 2L NC at discharge. Peak flow 62% predicted.',
        'Home nebulizer setup and education and  peak flow monitoring log and  medication reconciliation and  PCP notification', 'Albuterol 2.5 mg neb Q4H (wean to Q8H)\nIpratropium 0.5 mg neb Q6H\nPrednisone 40 mg PO QD x5 days\nAzithromycin 500 mg PO QD x5 days',
        'PCP follow-up within 48h. Return for SpO2 < 90%, increased work of breathing, or altered mental status. Continue home oxygen if on it.', '2026-02-28 21:50:11', 1, 'FAX', NULL, NULL, NULL, 1, '2026-02-28 20:20:11', '2026-02-28 21:50:11'),
       (5, 13, 11, NULL, 1, 'TRANSFER', 'ACCEPTED', 'URGENT', 3, 'Valley Behavioral Health Center', '(555) 340-7701', '(555) 340-7700', NULL,
        'Voluntary psychiatric admission for major depressive disorder with passive suicidal ideation. No acute medical concerns. Stable for transfer.',
        'Pt with MDD, no prior psych hospitalizations. Passive SI, no plan or intent. Columbia SSRS moderate risk. Fully cooperative. Medical clearance complete — normal CBC, BMP, UA, EKG.',
        'Inpatient psychiatric stabilization and  medication evaluation and  individual and group therapy', 'No current psychiatric medications', 'Continue outpatient therapy after discharge. PCP follow-up within 1 week.', '2026-02-28 19:20:12', 1, '', '2026-02-28 20:50:12',
        'Valley BH Intake Coordinator', 'Accepted — bed available unit 3B. Transport within 60 min.', 1, '2026-02-28 17:20:12', '2026-02-28 20:50:12'),
       (6, 17, 53, NULL, 1, 'DISCHARGE', 'SENT', 'URGENT', NULL, 'Springfield Neurology Associates', '(555) 820-4401', '(555) 820-4400', '900 Medical Center Dr, Springfield IL 62701',
        'Advanced Parkinson''s disease — unwitnessed fall with laceration this morning. Requesting urgent neurology review for medication adjustment and fall prevention.',
        'Harold Steinberg, 93M, Parkinson''s Hoehn & Yahr Stage 3. Carbidopa/levodopa TID. Fall this morning beside bed — 2cm forearm laceration, hip bruising, X-ray pending. Fall count 1 this month vs baseline 2/month.',
        'Urgent medication review: consider COMT inhibitor addition or dose timing adjustment. Gait and balance reassessment. PT recommendations.', 'Carbidopa/Levodopa 25/100mg TID (7AM, 12PM, 5PM) and  Rivastigmine patch 4.6mg/24h daily',
        'Follow up within 5 business days. Return to AL with updated medication plan.', '2026-02-28 09:00:00', 1, 'FAX', NULL, NULL, NULL, 1, '2026-02-28 08:30:00', '2026-02-28 09:00:00'),
       (7, 18, 54, NULL, 1, 'DISCHARGE', 'DRAFT', 'ROUTINE', NULL, 'Springfield Heart Center', '(555) 730-9901', '(555) 730-9900', '1200 Cardiology Blvd, Springfield IL 62701',
        'CHF NYHA Class II — weight gain 2.8 lbs over 5 days. Requesting cardiology review of diuretic regimen.',
        'Dorothy Vasquez, 79F, CHF NYHA Class II, T2DM HbA1c 9.2%. Daily weights: baseline 140.2 lbs, today 143.0 lbs (+2.8 lbs). Furosemide 20mg QAM. Note: medication error last week (40mg administered once, no adverse effects). FBG trending 140-180 on current insulin regimen.',
        'Echocardiogram if not done within 6 months. Review diuretic dose. BMP with BNP.', 'Furosemide 20mg QAM and  Lisinopril 10mg QD and  Insulin glargine 18u QHS and  Metformin 500mg BID', 'Cardiology note to AL facility within 3 business days. Repeat BMP in 1 week.', NULL,
        NULL, 'MANUAL', NULL, NULL, NULL, 1, '2026-02-28 10:00:00', '2026-02-28 10:00:00');

INSERT INTO `oei_ereferral` (`id`, `episode_id`, `pid`, `eid`, `facility_id`,
                             `referral_type`, `status`, `priority`,
                             `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`, `destination_address`,
                             `reason_for_referral`, `clinical_summary`, `services_requested`, `medications_summary`, `followup_instructions`,
                             `sent_datetime`, `sent_by_user_id`, `send_method`,
                             `response_datetime`, `response_by_name`, `response_notes`,
                             `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES (8, 23, 11, NULL, 1, 'DISCHARGE', 'SENT', 'ROUTINE',
        1, 'Valley Rehabilitation Center', '555-890-1234', '555-890-1200', '4400 Valley Drive, Springfield',
        'Post-surgical rehabilitation following right intertrochanteric hip fracture ORIF.',
        'Patient is a 72-year-old male admitted 02-27 with R intertrochanteric hip fracture following mechanical fall at home. ORIF completed 02-28 by Dr. Smith. EBL 250 mL, Hgb 9.2 post-op. Pain controlled on scheduled acetaminophen plus PRN morphine. WBAT with walker. Ambulated 15 ft on Day 1 post-op. Lives alone in single-story home.',
        'Skilled PT daily: WBAT gait training, stair negotiation, functional mobility. OT eval for ADL independence. Skilled nursing for wound care and DVT prophylaxis monitoring.',
        'Enoxaparin 40mg SC QD x35-day total course. Acetaminophen 650mg PO Q6H scheduled. Oxycodone 5mg PO Q4H PRN pain. Metoprolol 25mg PO BID. Atorvastatin 20mg PO QHS.',
        'Follow-up with orthopedics at 6 weeks post-op. PT reassessment at 3 weeks. Return to ED for fever over 38.5 C, wound redness, or shortness of breath.',
        '2026-02-28 12:00:00', 1, 'FAX',
        '2026-02-28 14:30:00', 'Mary Chen, Admissions Coordinator',
        'Bed available, admit tomorrow 0900 pending insurance authorization.',
        1, '2026-02-28 11:30:00', '2026-02-28 14:30:00');

-- oei_episode_document
CREATE TABLE IF NOT EXISTS `oei_episode_document`
(
    `id`                  bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED NOT NULL,
    `pid`                 bigint(20) UNSIGNED NOT NULL,
    `facility_id`         bigint(20) UNSIGNED NOT NULL,
    `doc_type`            varchar(40)         NOT NULL DEFAULT 'GENERAL' COMMENT 'GENERAL|TRANSFER_PACKET|PHYSICIAN_ORDER|LAB|IMAGING|CONSENT|ID|INSURANCE|OTHER',
    `label`               varchar(180)        NOT NULL COMMENT 'User-visible name',
    `original_name`       varchar(255)        NOT NULL COMMENT 'Original uploaded filename',
    `mime_type`           varchar(100)        NOT NULL DEFAULT 'application/octet-stream',
    `file_size`           int(10) UNSIGNED    NOT NULL DEFAULT 0 COMMENT 'Bytes',
    `storage_path`        varchar(500)        NOT NULL COMMENT 'Path relative to OE site document root',
    `uploaded_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `uploaded_datetime`   datetime            NOT NULL,
    `notes`               varchar(255)                 DEFAULT NULL,
    `is_deleted`          tinyint(1)          NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_oei_doc_episode` (`episode_id`, `is_deleted`, `uploaded_datetime`),
    KEY `idx_oei_doc_facility` (`facility_id`, `doc_type`, `uploaded_datetime`),
    KEY `idx_oei_doc_pid` (`pid`, `uploaded_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Episode-linked document attachments';

-- oei_transfer
CREATE TABLE IF NOT EXISTS `oei_transfer`
(
    `id`                     bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`             bigint(20) UNSIGNED NOT NULL,
    `pid`                    bigint(20) UNSIGNED NOT NULL,
    `eid`                    bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`            bigint(20) UNSIGNED NOT NULL,
    `transfer_type`          varchar(30)         NOT NULL DEFAULT 'TRANSFER',
    `reason`                 varchar(120)                 DEFAULT NULL,
    `receiving_directory_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `receiving_name`         varchar(120)                 DEFAULT NULL,
    `requested_datetime`     datetime                     DEFAULT NULL,
    `accepted_datetime`      datetime                     DEFAULT NULL,
    `transport_datetime`     datetime                     DEFAULT NULL,
    `status`                 varchar(30)         NOT NULL DEFAULT 'PENDING',
    `checklist_json`         mediumtext                   DEFAULT NULL,
    `notes`                  varchar(255)                 DEFAULT NULL,
    `updated_by_user_id`     bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`       datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_transfer_episode` (`episode_id`),
    KEY `idx_oei_transfer_fac_status` (`facility_id`, `status`, `updated_datetime`),
    KEY `idx_oei_transfer_fac_time` (`facility_id`, `requested_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_transfer` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `transfer_type`, `reason`, `receiving_directory_id`, `receiving_name`, `requested_datetime`, `accepted_datetime`, `transport_datetime`, `status`, `checklist_json`, `notes`, `updated_by_user_id`,
                            `updated_datetime`)
VALUES (1, 9, 7, NULL, 1, 'TRANSFER', 'Grade III splenic laceration requiring surgical intervention', NULL, 'Regional Trauma Center', '2026-02-28 21:20:11', '2026-02-28 21:50:11', NULL, 'ACCEPTED',
        '{"items":[{"label":"Accepting physician identified","done":true},{"label":"Report given to receiving team","done":true},{"label":"Transfer consent signed","done":true},{"label":"Records copied","done":true},{"label":"Transport unit confirmed","done":true},{"label":"Stable for transport","done":true}]}',
        'Dr. Reyes at RTC accepted directly. MTP ongoing — Hgb 7.8. 2u pRBC transfused. Transport ETA 15 min.', 1, '2026-02-28 22:05:11');

-- oei_bh_safety
CREATE TABLE IF NOT EXISTS `oei_bh_safety`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `observation_level`  varchar(20)         NOT NULL DEFAULT 'NONE',
    `is_involuntary`     tinyint(1)          NOT NULL DEFAULT 0,
    `risk_violence`      tinyint(1)          NOT NULL DEFAULT 0,
    `risk_suicide`       tinyint(1)          NOT NULL DEFAULT 0,
    `elopement_risk`     tinyint(1)          NOT NULL DEFAULT 0,
    `precautions_json`   mediumtext                   DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_bh_episode` (`episode_id`),
    KEY `idx_oei_bh_facility` (`facility_id`, `observation_level`),
    KEY `idx_oei_bh_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_bh_safety` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `observation_level`, `is_involuntary`, `risk_violence`, `risk_suicide`, `elopement_risk`, `precautions_json`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 6, 4, NULL, 1, '1:1', 0, 0, 1, 1,
        '{"items":["Sharps removed from room and patient","Street clothing searched","Belts and shoelaces removed","1:1 sitter assigned — shift change at 1500","Columbia Suicide Severity Rating Scale completed: High risk","Crisis counselor notified"]}', 1, '2026-02-28 17:30:11'),
       (2, 12, 10, NULL, 1, '1:1', 0, 1, 0, 1, '{"items":["Wrist restraints applied — combative on arrival","Belongings searched — no sharps found","1:1 nurse monitoring for re-sedation","Elopement risk — patient attempted to leave x1","IV secured with armboard"]}', 1,
        '2026-02-28 21:05:12'),
       (3, 13, 11, NULL, 1, 'Q15', 0, 0, 1, 0, '{"items":["Voluntary patient — fully cooperative","Belongings searched — no contraband","Q15-minute safety checks","Family at bedside","Columbia SSRS: moderate risk","Crisis counselor evaluation completed"]}', 1,
        '2026-02-28 14:40:12');

-- oei_bh_boarding
CREATE TABLE IF NOT EXISTS `oei_bh_boarding`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`         bigint(20) UNSIGNED NOT NULL,
    `pid`                bigint(20) UNSIGNED NOT NULL,
    `eid`                bigint(20) UNSIGNED          DEFAULT NULL,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `legal_status`       varchar(40)                  DEFAULT NULL,
    `suicide_risk`       varchar(20)                  DEFAULT NULL,
    `violence_risk`      varchar(20)                  DEFAULT NULL,
    `placement_status`   varchar(30)         NOT NULL DEFAULT 'SEARCHING',
    `accepting_facility` varchar(120)                 DEFAULT NULL,
    `accepted_datetime`  datetime                     DEFAULT NULL,
    `transport_method`   varchar(40)                  DEFAULT NULL,
    `transport_datetime` datetime                     DEFAULT NULL,
    `emtala_complete`    tinyint(1)          NOT NULL DEFAULT 0,
    `checklist_json`     mediumtext                   DEFAULT NULL,
    `notes`              varchar(255)                 DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_bh_episode` (`episode_id`),
    KEY `idx_oei_bh_facility` (`facility_id`, `placement_status`, `updated_datetime`),
    KEY `idx_oei_bh_pid` (`pid`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `oei_bh_boarding` (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `legal_status`, `suicide_risk`, `violence_risk`, `placement_status`, `accepting_facility`, `accepted_datetime`, `transport_method`, `transport_datetime`, `emtala_complete`, `checklist_json`, `notes`,
                               `updated_by_user_id`, `updated_datetime`)
VALUES (1, 6, 4, NULL, 1, 'VOLUNTARY', 'HIGH', 'LOW', 'SEARCHING', NULL, NULL, NULL, NULL, 1,
        '{"items":[{"label":"EMTALA MSE completed","done":true},{"label":"Insurance verified — MediCal","done":true},{"label":"Placement calls initiated","done":true},{"label":"Accepting facility confirmed","done":false},{"label":"Family notified","done":true},{"label":"Transport arranged","done":false}]}',
        'Called Valley BH (full), State Hospital (waitlist — ETA 6-8h), Riverside BH (no voluntary beds). Re-calling Valley BH at next hour.', 1, '2026-02-28 20:20:11'),
       (2, 13, 11, NULL, 1, 'VOLUNTARY', 'MODERATE', 'LOW', 'ACCEPTED', 'Valley Behavioral Health Center', NULL, NULL, NULL, 1,
        '{"items":[{"label":"EMTALA MSE completed","done":true},{"label":"Insurance verified — Blue Shield","done":true},{"label":"Placement calls initiated","done":true},{"label":"Accepting facility confirmed","done":true},{"label":"Family notified","done":true},{"label":"Transfer paperwork complete","done":true},{"label":"Transport arranged","done":true}]}',
        'Valley BH accepted at 0930. Transport: Medvan unit dispatched, ETA 30-45 min. Patient and family updated and relieved.', 1, '2026-02-28 20:50:12');

-- oei_diversion
CREATE TABLE IF NOT EXISTS `oei_diversion`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `service_line`       varchar(40)         NOT NULL DEFAULT 'ED' COMMENT 'ED | ICU | OBS | PSYCH | TRAUMA | PEDS | BURN',
    `status`             varchar(20)         NOT NULL DEFAULT 'OPEN' COMMENT 'OPEN | DIVERSION | LIMITED | BYPASS',
    `reason`             varchar(255)                 DEFAULT NULL COMMENT 'Free-text reason shown in facility directory',
    `diversion_start`    datetime                     DEFAULT NULL,
    `diversion_end`      datetime                     DEFAULT NULL,
    `updated_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    `updated_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_diversion_service` (`facility_id`, `service_line`),
    KEY `idx_oei_diversion_facility` (`facility_id`, `status`, `updated_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Current diversion status per facility and service line';

INSERT INTO `oei_diversion` (`id`, `facility_id`, `service_line`, `status`, `reason`, `diversion_start`, `diversion_end`, `updated_by_user_id`, `updated_datetime`)
VALUES (1, 1, 'TRAUMA', 'DIVERSION', 'Mass casualty incident diverted — both trauma bays occupied. Redirect all trauma activations to Regional Trauma Center.', '2026-02-28 20:20:10', NULL, 1, '2026-02-28 20:20:10'),
       (2, 1, 'PSYCH', 'DIVERSION', 'No BH holding rooms available. Two patients boarding. Redirect voluntary psych to Valley BH Center.', '2026-02-28 16:20:10', NULL, 1, '2026-02-28 16:20:10'),
       (3, 1, 'ICU', 'LIMITED', 'ICU at 92% capacity. Accepting critical holds only. Call attending before transfer.', '2026-02-28 18:20:10', NULL, 1, '2026-02-28 18:20:10'),
       (4, 1, 'ED', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10'),
       (5, 1, 'OBS', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10'),
       (6, 1, 'PEDS', 'OPEN', NULL, '2026-02-28 14:20:10', NULL, 1, '2026-02-28 14:20:10');

-- oei_diversion_history
CREATE TABLE IF NOT EXISTS `oei_diversion_history`
(
    `id`                 bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`        bigint(20) UNSIGNED NOT NULL,
    `service_line`       varchar(40)         NOT NULL,
    `previous_status`    varchar(20)         DEFAULT NULL,
    `new_status`         varchar(20)         NOT NULL,
    `reason`             varchar(255)        DEFAULT NULL,
    `diversion_start`    datetime            DEFAULT NULL,
    `diversion_end`      datetime            DEFAULT NULL,
    `changed_by_user_id` bigint(20) UNSIGNED DEFAULT NULL,
    `changed_datetime`   datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_div_hist_facility` (`facility_id`, `service_line`, `changed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Audit log of all diversion status changes';

INSERT INTO `oei_diversion_history` (`id`, `facility_id`, `service_line`, `previous_status`, `new_status`, `reason`, `diversion_start`, `diversion_end`, `changed_by_user_id`, `changed_datetime`)
VALUES (1, 1, 'TRAUMA', 'OPEN', 'DIVERSION', 'Mass casualty incident — both bays activated', NULL, NULL, 1, '2026-02-28 20:19:41'),
       (2, 1, 'PSYCH', 'OPEN', 'LIMITED', 'BH holding rooms approaching capacity', NULL, NULL, 1, '2026-02-28 14:19:41'),
       (3, 1, 'PSYCH', 'LIMITED', 'DIVERSION', 'No available BH beds. Two patients boarding in ED.', NULL, NULL, 1, '2026-02-28 16:19:41'),
       (4, 1, 'ICU', 'OPEN', 'LIMITED', 'ICU at 92% capacity following overnight admits', NULL, NULL, 1, '2026-02-28 18:19:41'),
       (5, 1, 'TRAUMA', 'OPEN', 'DIVERSION', 'Mass casualty incident — both bays activated', NULL, NULL, 1, '2026-02-28 20:20:10'),
       (6, 1, 'PSYCH', 'OPEN', 'LIMITED', 'BH holding rooms approaching capacity', NULL, NULL, 1, '2026-02-28 14:20:10'),
       (7, 1, 'PSYCH', 'LIMITED', 'DIVERSION', 'No available BH beds. Two patients boarding in ED.', NULL, NULL, 1, '2026-02-28 16:20:10'),
       (8, 1, 'ICU', 'OPEN', 'LIMITED', 'ICU at 92% capacity following overnight admits', NULL, NULL, 1, '2026-02-28 18:20:10');

-- oei_alert_ack
CREATE TABLE IF NOT EXISTS `oei_alert_ack`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `alert_key`        varchar(120)        NOT NULL COMMENT 'e.g. LWBS_RISK:42',
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `acked_datetime`   datetime            NOT NULL,
    `expires_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_alert_ack` (`alert_key`, `facility_id`),
    KEY `idx_oei_alert_ack_exp` (`facility_id`, `expires_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user alert snooze/acknowledgement records';

INSERT INTO `oei_alert_ack` (`id`, `alert_key`, `facility_id`, `user_id`, `acked_datetime`, `expires_datetime`)
VALUES (1, 'LWBS_RISK:6', 1, 1, '2026-02-28 20:20:11', '2026-02-28 23:20:11'),
       (2, 'SEPSIS_RISK:4', 1, 1, '2026-02-28 20:50:12', '2026-02-28 23:50:12'),
       (3, 'BED_WAIT_ICU:4', 1, 1, '2026-02-28 21:50:12', '2026-02-28 23:20:12'),
       (4, 'LWBS_RISK:10', 1, 1, '2026-02-28 22:05:12', '2026-02-28 22:50:12'),
       (5, 'MAR_OVERDUE:8', 1, 1, '2026-02-28 22:10:12', '2026-02-28 23:20:12'),
       (6, 'BH_BOARDING_DWELL:6', 1, 1, '2026-02-28 22:00:12', '2026-02-28 23:20:12');

-- oei_hl7_outbound_log
CREATE TABLE IF NOT EXISTS `oei_hl7_outbound_log`
(
    `id`             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `episode_id`     bigint(20) UNSIGNED NOT NULL,
    `pid`            bigint(20) UNSIGNED NOT NULL,
    `facility_id`    bigint(20) UNSIGNED NOT NULL,
    `event_type`     varchar(4)          NOT NULL COMMENT 'A01|A02|A03|A04|A08',
    `transport_type` varchar(10)         NOT NULL COMMENT 'MLLP|HTTP|INTERNAL',
    `endpoint`       varchar(500)        NOT NULL,
    `message_body`   mediumtext          NOT NULL,
    `ack_body`       mediumtext DEFAULT NULL,
    `status`         varchar(10)         NOT NULL COMMENT 'SENT|NACK|ERROR',
    `error_message`  text       DEFAULT NULL,
    `sent_datetime`  datetime            NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_hl7_log_facility` (`facility_id`, `sent_datetime`),
    KEY `idx_hl7_log_episode` (`episode_id`),
    KEY `idx_hl7_log_status` (`facility_id`, `status`, `sent_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='HL7 v2 ADT outbound message log';

INSERT INTO `oei_hl7_outbound_log` (`id`, `episode_id`, `pid`, `facility_id`, `event_type`, `transport_type`, `endpoint`, `message_body`, `ack_body`, `status`, `error_message`, `sent_datetime`)
VALUES (1, 4, 2, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315091500||ADT^A04^ADT_A01|OEI001|T|2.5.1\rEVN|A04|20260315091500\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 19:20:12'),
       (2, 5, 3, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A04^ADT_A01|OEI002|T|2.5.1\rEVN|A04|20260315073000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 00:20:12'),
       (3, 5, 3, 1, 'A01', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315073000||ADT^A01^ADT_A01|OEI003|T|2.5.1\rEVN|A01|20260315093000\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 02:20:12'),
       (4, 8, 6, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315113500||ADT^A04^ADT_A01|OEI004|T|2.5.1\rEVN|A04|20260315113500\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 21:55:12'),
       (5, 9, 7, 1, 'A04', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315100000||ADT^A04^ADT_A01|OEI005|T|2.5.1\rEVN|A04|20260315100000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 20:20:12'),
       (6, 9, 7, 1, 'A08', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315103000||ADT^A08^ADT_A01|OEI006|T|2.5.1\rEVN|A08|20260315103000\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 20:50:12'),
       (7, 10, 8, 1, 'A01', 'INTERNAL', 'INTERNAL', 'MSH|^~&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260315031500||ADT^A01^ADT_A01|OEI007|T|2.5.1\rEVN|A01|20260315031500\rPID|1||8^^^OEI^PI||Kim^David||19630425|M\rPV1|1|O|OBS2^^^OPENEMR', NULL, 'SENT', NULL,
        '2026-02-28 11:20:12'),
       (8, 4, 2, 1, 'A02', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228192513||ADT^A02^ADT_A01|OEI010|T|2.5.1\rEVN|A02|20260228192513\rPID|1||2^^^OEI^PI||Wilson^James||19670315|M\rPV1|1|E|ED01^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 19:25:13'),
       (9, 5, 3, 1, 'A01', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228022013||ADT^A01^ADT_A01|OEI011|T|2.5.1\rEVN|A01|20260228022013\rPID|1||3^^^OEI^PI||Chen^Margaret||19570822|F\rPV1|1|O|OBS1^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 02:20:13'),
       (10, 8, 6, 1, 'A02', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228215713||ADT^A02^ADT_A01|OEI012|T|2.5.1\rEVN|A02|20260228215713\rPID|1||6^^^OEI^PI||Patel^Robert||19520108|M\rPV1|1|E|TR01^^^OPENEMR', NULL, 'SENT',
        NULL, '2026-02-28 21:57:13'),
       (11, 9, 7, 1, 'A03', 'HTTP', 'https://rtc.hospital.org/hl7/adt', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228220513||ADT^A03^ADT_A01|OEI013|T|2.5.1\rEVN|A03|20260228220513\rPID|1||7^^^OEI^PI||Torres^Linda||19790719|F\rPV1|1|E|TR02^^^OPENEMR', NULL,
        'SENT', NULL, '2026-02-28 22:05:13'),
       (12, 0, 0, 1, 'A09', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|EMS-DISPATCH|REGIONAL|20260228202013||ADT^A09^ADT_A01|OEI014|T|2.5.1\rEVN|A09|20260228202013\rZDV|TRAUMA|DIVERSION|Mass casualty incident — redirect to Regional Trauma Center',
        NULL, 'SENT', NULL, '2026-02-28 20:20:13'),
       (13, 12, 10, 1, 'A08', 'MLLP', 'hl7.hospital.internal:2575', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|RECEIVING|FACILITY|20260228215013||ADT^A08^ADT_A01|OEI015|T|2.5.1\rEVN|A08|20260228215013\rPID|1||10^^^OEI^PI||Williams^Marcus||19960318|M\rPV1|1|E|ED04^^^OPENEMR', NULL,
        'SENT', NULL, '2026-02-28 21:50:13'),
       (14, 6, 4, 1, 'A04', 'MLLP', 'hl7-backup.hospital.internal:2576', 'MSH|^~\\&|OE-INSTITUTIONAL|OPENEMR|BACKUP|FACILITY|20260228172213||ADT^A04^ADT_A01|OEI016|T|2.5.1\rEVN|A04|20260228172213\rPID|1||4^^^OEI^PI||Brooks^Tyler||19900511|M\rPV1|1|E|PSY1^^^OPENEMR', NULL, 'NACK',
        NULL, '2026-02-28 17:22:13');

-- oei_downtime_sync_queue
CREATE TABLE IF NOT EXISTS `oei_downtime_sync_queue`
(
    `id`                   bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `facility_id`          bigint(20) UNSIGNED NOT NULL,
    `entry_type`           varchar(30)         NOT NULL COMMENT 'ARRIVAL | VITALS | STATUS_NOTE | TASK_NOTE',
    `payload_json`         mediumtext          NOT NULL COMMENT 'Raw JSON captured by browser',
    `captured_client`      datetime            NOT NULL COMMENT 'Client-side timestamp from the browser',
    `queued_datetime`      datetime            NOT NULL COMMENT 'Server-receipt datetime on sync POST',
    `synced_datetime`      datetime                     DEFAULT NULL,
    `status`               varchar(20)         NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING | SYNCED | FAILED | SKIPPED',
    `result_note`          varchar(255)                 DEFAULT NULL COMMENT 'Error message or resultant ID on success',
    `submitted_by_user_id` bigint(20) UNSIGNED          DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_dt_queue_facility` (`facility_id`, `status`, `queued_datetime`),
    KEY `idx_oei_dt_queue_type` (`entry_type`, `status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Offline write queue — rows written by browser during network outage';

INSERT INTO `oei_downtime_sync_queue` (`id`, `facility_id`, `entry_type`, `payload_json`, `captured_client`, `queued_datetime`, `synced_datetime`, `status`, `result_note`, `submitted_by_user_id`)
VALUES (1, 1, 'ARRIVAL', '{"fname": "Carlos", "lname": "Mendez", "dob": "1985-06-14", "chief_complaint": "Chest pain, onset 30 min ago", "arrival_mode": "WALKIN", "acuity_esi": 2}', '2026-02-28 20:52:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED',
        'Synced — episode created pid 12', 1),
       (2, 1, 'VITALS', '{"episode_id": 4, "pid": 2, "bp_systolic": 102, "bp_diastolic": 64, "hr": 118, "rr": 22, "spo2": 93, "temp_f": 101.2}', '2026-02-28 20:54:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED', 'Synced — triage re-assessment row inserted', 1),
       (3, 1, 'STATUS_NOTE', '{"episode_id": 9, "pid": 7, "note": "Transport team confirmed. Medic 7 ETA 10 min. Patient stable."}', '2026-02-28 20:57:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED', 'Synced — status history entry added', 1),
       (4, 1, 'TASK_NOTE', '{"episode_id": 5, "pid": 3, "task_type": "STRESS_TEST", "note": "Patient on treadmill. EKG connected. Baseline HR 72."}', '2026-02-28 20:59:12', '2026-02-28 21:00:12', '2026-02-28 21:02:12', 'SYNCED', 'Synced — task note appended', 1),
       (5, 1, 'VITALS', '{"episode_id": 12, "pid": 10, "bp_systolic": 116, "bp_diastolic": 74, "hr": 92, "rr": 16, "spo2": 98, "note": "Post-Narcan 90min check. Stable."}', '2026-02-28 22:18:12', '2026-02-28 22:19:12', NULL, 'PENDING', NULL, 1);

-- oei_user_context
CREATE TABLE IF NOT EXISTS `oei_user_context`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `context_key`      varchar(30)         NOT NULL DEFAULT 'FULL',
    `updated_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ctx_user_fac` (`user_id`, `facility_id`),
    KEY `idx_oei_ctx_facility` (`facility_id`, `context_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Per-user care context preference per facility';

INSERT INTO `oei_user_context` (`id`, `user_id`, `facility_id`, `context_key`, `updated_datetime`)
VALUES (1, 1, 1, 'ED_ACUTE', '2026-02-28 22:20:10'),
       (2, 2, 1, 'OPERATIONS', '2026-02-28 22:20:10'),
       (3, 3, 1, 'OBS_STAY', '2026-02-28 22:20:10'),
       (4, 4, 1, 'BH', '2026-02-28 22:20:10'),
       (5, 5, 1, 'INPATIENT_STAY', '2026-02-28 22:20:10'),
       (6, 6, 1, 'INPATIENT_STAY', '2026-02-28 22:20:10');

-- oei_activity_log
CREATE TABLE IF NOT EXISTS `oei_activity_log`
(
    `id`               bigint(20) UNSIGNED                                NOT NULL AUTO_INCREMENT,
    `facility_id`      bigint(20) UNSIGNED                                NOT NULL,
    `activity_date`    date                                               NOT NULL,
    `activity_type`    varchar(40)                                        NOT NULL COMMENT 'SOCIAL_GROUP|MUSIC|EXERCISE|COGNITIVE|OUTDOOR|DEVOTIONAL|CRAFT|INDIVIDUAL_VISIT|DINING_SOCIAL|THERAPY_PT|THERAPY_OT|THERAPY_ST|OTHER',
    `activity_name`    varchar(120)                                       NOT NULL COMMENT 'Specific name, e.g. "Morning Stretch", "Bingo", "Guitar Singalong"',
    `start_time`       time                                               NOT NULL,
    `duration_minutes` smallint(5) UNSIGNED                               NOT NULL DEFAULT 30,
    `location`         varchar(60)                                                 DEFAULT NULL COMMENT 'e.g. "Community Room", "Courtyard", "Room 202"',
    `led_by_user_id`   bigint(20) UNSIGNED                                         DEFAULT NULL COMMENT 'FK → users.id (activity coordinator / aide)',
    `led_by_name`      varchar(80)                                                 DEFAULT NULL COMMENT 'Denormalised name for display when user is no longer active',
    `attendance_json`  longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT json_object() COMMENT 'episode_id → {level, note} participation map' CHECK (json_valid(`attendance_json`)),
    `attendance_count` tinyint(3) UNSIGNED                                NOT NULL DEFAULT 0 COMMENT 'Cached count of FULL+PARTIAL attendees for fast board display',
    `notes`            text                                                        DEFAULT NULL COMMENT 'Session-level notes (themes, outcomes, observations)',
    `created_datetime` datetime                                           NOT NULL,
    `updated_datetime` datetime                                           NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_oei_activity_facility_date` (`facility_id`, `activity_date`),
    KEY `idx_oei_activity_type` (`facility_id`, `activity_type`, `activity_date`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='AL activity & engagement session log';

INSERT INTO `oei_activity_log` (`id`, `facility_id`, `activity_date`, `activity_type`,
                                `activity_name`, `start_time`, `duration_minutes`, `location`,
                                `led_by_user_id`, `led_by_name`, `attendance_json`, `attendance_count`,
                                `notes`, `created_datetime`, `updated_datetime`)
VALUES (1, 1, '2026-02-28', 'EXERCISE', 'Morning Stretch and Chair Yoga',
        '09:00:00', 45, 'Community Room A', 1, 'Admin',
        '{"14":{"level":"FULL","note":"Engaged well, completed all seated poses"},"15":{"level":"FULL","note":"Good ROM, encouraged by PT progress"},"16":{"level":"FULL","note":"Independent throughout, helped neighbor"},"17":{"level":"PARTIAL","note":"Attended 20 min, fatigue from medication"},"18":{"level":"FULL","note":"Monitor O2 — 95% throughout, no desaturation"}}',
        5, 'All 5 Wing A/B residents participated. Good energy overall.',
        '2026-02-28 09:00:00', '2026-02-28 10:00:00'),
       (2, 1, '2026-02-27', 'MUSIC', 'Guitar Singalong — 1950s and 60s Favorites',
        '14:00:00', 60, 'Dining Room', 1, 'Admin',
        '{"14":{"level":"FULL","note":"Sang every song, most animated in weeks"},"15":{"level":"FULL","note":"Clapped along, reminded her of her wedding"},"16":{"level":"PARTIAL","note":"Left after 30 min due to fatigue"},"17":{"level":"FULL","note":"Good engagement, less tremor observed during music"}}',
        4, 'Exceptional session. Resident 14 showed marked emotional engagement. Resident 17 tremor visibly reduced during active listening.',
        '2026-02-27 14:00:00', '2026-02-27 15:15:00'),
       (3, 1, '2026-02-26', 'COGNITIVE', 'Current Events Discussion and Trivia',
        '10:00:00', 40, 'Sunroom', 1, 'Admin',
        '{"15":{"level":"FULL","note":"Led discussion, answered 8 of 10 trivia correctly"},"16":{"level":"FULL","note":"Contributed actively, very sharp today"},"18":{"level":"FULL","note":"Focused despite fatigue, weight 143 lbs pre-session"}}',
        3, 'Three residents — high-functioning group. Resident 15 showing strong cognitive reserve.',
        '2026-02-26 10:00:00', '2026-02-26 10:45:00'),
       (4, 1, '2026-02-25', 'SOCIAL_GROUP', 'Family Video Call Facilitation',
        '15:00:00', 30, 'Activity Room', 1, 'Admin',
        '{"14":{"level":"FULL","note":"Video call with daughter in Denver, calm and happy post-call"},"17":{"level":"FULL","note":"Son joined via tablet, practiced swallowing exercises together"}}',
        2, 'Individualized facilitation for two residents. Family engagement strongly positive for resident 14.',
        '2026-02-25 15:00:00', '2026-02-25 15:35:00');

-- oei_fall_risk_assessment
CREATE TABLE IF NOT EXISTS `oei_fall_risk_assessment`
(
    `id`                  bigint(20) UNSIGNED            NOT NULL AUTO_INCREMENT,
    `episode_id`          bigint(20) UNSIGNED            NOT NULL,
    `facility_id`         bigint(20) UNSIGNED            NOT NULL,
    `assessed_by_user_id` bigint(20) UNSIGNED                     DEFAULT NULL,
    `assessed_datetime`   datetime                       NOT NULL,
    `mfs_fall_history`    tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 25=Yes',
    `mfs_secondary_dx`    tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 15=Yes',
    `mfs_ambulatory_aid`  tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=None/bed-rest/nurse, 15=Crutches/cane/walker, 30=Furniture',
    `mfs_iv_heparin_lock` tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=No, 20=Yes',
    `mfs_gait`            tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=Normal/bedrest, 10=Weak, 20=Impaired',
    `mfs_mental_status`   tinyint(3) UNSIGNED            NOT NULL DEFAULT 0 COMMENT '0=Knows own limits, 15=Forgets limitations',
    `total_score`         tinyint(3) UNSIGNED            NOT NULL COMMENT 'Sum of 6 items (0-125)',
    `risk_level`          enum ('LOW','MODERATE','HIGH') NOT NULL,
    `notes`               text                                    DEFAULT NULL,
    `created_datetime`    datetime                       NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_fra_episode` (`episode_id`),
    KEY `idx_fra_facility` (`facility_id`),
    KEY `idx_fra_datetime` (`assessed_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci COMMENT ='Morse Fall Scale reassessment history for AL episodes';

INSERT INTO `oei_fall_risk_assessment` (`id`, `episode_id`, `facility_id`,
                                        `assessed_by_user_id`, `assessed_datetime`,
                                        `mfs_fall_history`, `mfs_secondary_dx`, `mfs_ambulatory_aid`,
                                        `mfs_iv_heparin_lock`, `mfs_gait`, `mfs_mental_status`,
                                        `total_score`, `risk_level`, `notes`, `created_datetime`)
VALUES (1, 14, 1, 1, '2026-01-12 23:00:00', 25, 15, 30, 0, 20, 15, 105, 'HIGH',
        'Memory care — wanders at night, bed alarm required, 2 falls in prior 3 months', '2026-01-12 23:00:00'),
       (2, 14, 1, 1, '2026-02-15 09:00:00', 25, 15, 30, 0, 20, 15, 105, 'HIGH',
        'Post-fall reassessment — score unchanged, hip X-ray negative, bed alarm upgraded to door sensor', '2026-02-15 09:00:00'),
       (3, 14, 1, 1, '2026-02-28 08:00:00', 25, 15, 30, 0, 20, 15, 105, 'HIGH',
        'Weekly reassessment — no change, nocturnally confused, 1:1 at peak agitation periods', '2026-02-28 08:00:00'),
       (4, 15, 1, 1, '2026-01-28 23:00:00', 0, 15, 15, 0, 10, 0, 40, 'MODERATE',
        'Post-hip replacement — using walker, PT 5x per week, improving gait pattern', '2026-01-28 23:00:00'),
       (5, 15, 1, 1, '2026-02-28 10:00:00', 0, 15, 15, 0, 10, 0, 40, 'MODERATE',
        'Stable MODERATE — walker use consistent, achieved 50ft independent gait. PT continuing', '2026-02-28 10:00:00'),
       (6, 16, 1, 1, '2026-02-10 23:00:00', 0, 15, 0, 0, 0, 0, 15, 'LOW',
        'COPD management — independent ADLs, no ambulatory aid, monitors own limits', '2026-02-10 23:00:00'),
       (7, 17, 1, 1, '2025-12-28 23:00:00', 25, 15, 30, 0, 20, 15, 105, 'HIGH',
        'Advanced Parkinson''s disease — festinating gait, impaired postural reflexes, fall 3 weeks ago at home. Mandatory 1:1 ambulation', '2025-12-28 23:00:00'),
       (8, 17, 1, 1, '2026-02-28 11:00:00', 25, 15, 30, 0, 20, 15, 105, 'HIGH',
        'Post-fall: in-facility fall 02-28. No fracture on X-ray. PT consulted for immediate reassessment. Levodopa timing optimized.', '2026-02-28 11:00:00'),
       (9, 18, 1, 1, '2026-02-19 23:00:00', 0, 15, 15, 20, 10, 0, 60, 'MODERATE',
        'CHF and DM — mild gait instability, uses cane, IV heparin-lock for insulin management', '2026-02-19 23:00:00');

-- oei_ip_episode
CREATE TABLE IF NOT EXISTS `oei_ip_episode`
(
    `id`                  int(10) UNSIGNED                                                                             NOT NULL AUTO_INCREMENT,
    `episode_id`          int(10) UNSIGNED                                                                             NOT NULL COMMENT 'FK → oei_episode.id (UNIQUE — one overlay per episode)',
    `pid`                 bigint(20)                                                                                   NOT NULL COMMENT 'FK → patient_data.pid (denormalised for fast census queries)',
    `facility_id`         int(10) UNSIGNED                                                                             NOT NULL,
    `encounter_id`        bigint(20)                                                                                            DEFAULT NULL COMMENT 'FK → form_encounter.id — anchors care plan and clinical notes',
    `bed`                 varchar(20)                                                                                           DEFAULT NULL COMMENT 'Bed identifier, e.g. "4B-201" or "ICU-3"',
    `unit`                varchar(60)                                                                                           DEFAULT NULL COMMENT 'Unit/floor name, e.g. "Medical/Surgical", "Telemetry"',
    `service`             enum ('MED_SURG','TELEMETRY','ORTHO','NEURO','OB','PEDS','ICU','ONCOLOGY','CARDIAC','OTHER') NOT NULL DEFAULT 'MED_SURG' COMMENT 'Service line — see HospitalService domain class',
    `admission_type`      enum ('ELECTIVE','URGENT','EMERGENCY','NEWBORN','TRAUMA')                                    NOT NULL DEFAULT 'ELECTIVE' COMMENT 'Admission type — see AdmissionType domain class',
    `attending_user_id`   int(11)                                                                                               DEFAULT NULL COMMENT 'FK → users.id (authorized=1) — attending physician',
    `admitting_diagnosis` varchar(255)                                                                                          DEFAULT NULL COMMENT 'Free-text admitting diagnosis description',
    `admitting_icd10`     varchar(20)                                                                                           DEFAULT NULL COMMENT 'ICD-10 code (optional)',
    `expected_los_days`   smallint(5) UNSIGNED                                                                                  DEFAULT NULL COMMENT 'Case manager target length of stay in days',
    `discharge_summary`   text                                                                                                  DEFAULT NULL COMMENT 'Clinical narrative summary written at discharge',
    `created_datetime`    datetime                                                                                     NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ip_episode` (`episode_id`),
    KEY `idx_oei_ip_facility` (`facility_id`, `unit`, `bed`),
    KEY `idx_oei_ip_attending` (`attending_user_id`),
    KEY `idx_oei_ip_service` (`facility_id`, `service`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Inpatient Hospital Stay overlay on oei_episode';

INSERT INTO `oei_ip_episode` (`id`, `episode_id`, `pid`, `facility_id`, `encounter_id`,
                              `bed`, `unit`, `service`, `admission_type`, `attending_user_id`,
                              `admitting_diagnosis`, `admitting_icd10`, `expected_los_days`,
                              `discharge_summary`, `created_datetime`)
VALUES (1, 19, 2, 1, 1000019, 'ICU-1', 'ICU', 'ICU', 'EMERGENCY', 1,
        'Acute hypoxic respiratory failure requiring intubation', 'J96.01', 7, NULL, '2026-02-23 08:30:00'),
       (2, 20, 5, 1, 1000020, '4B-201', 'Med/Surg 4B', 'ORTHO', 'ELECTIVE', 1,
        'Left total knee arthroplasty — elective, pain controlled, PT progressing', 'M17.12', 3, NULL, '2026-02-26 14:00:00'),
       (3, 21, 6, 1, 1000021, 'TEL-03', 'Telemetry 3', 'TELEMETRY', 'URGENT', 1,
        'Non-ST elevation myocardial infarction — drug-eluting stent to LAD', 'I21.4', 4, NULL, '2026-02-26 22:10:00'),
       (4, 22, 8, 1, 1000022, '3A-118', 'Med/Surg 3A', 'MED_SURG', 'EMERGENCY', 1,
        'Community-acquired pneumonia — Streptococcus pneumoniae', 'J18.9', 5, NULL, '2026-02-24 10:45:00'),
       (5, 23, 11, 1, 1000023, '4B-208', 'Ortho 4B', 'ORTHO', 'URGENT', 1,
        'Right intertrochanteric hip fracture — ORIF completed', 'S72.141A', 4, NULL, '2026-02-27 16:20:00');

-- =============================================================================
-- SUPPLEMENTAL: Users (roles), users_secure, prescriptions
-- Added: v0.18.0 seed refresh
-- Password for ALL demo accounts: pass
-- Hash: $2a$05$vlnqsGNJuNNQgIKFNFGnkuCiW.eRX08FhMLaY0b7jKa3n7HGvT5jO
-- =============================================================================

-- -----------------------------------------------------------------------------
-- prescriptions — RxNorm drug labels, correct OpenEMR column layout
--
-- Column reference (from real OpenEMR insert):
--   drug          = full RxNorm label  e.g. 'gabapentin 100 MG Oral Capsule'
--   rxnorm_drugcode = RxCUI string
--   form          = 1=tablet  2=film/liquid  3=capsule  4=injection  5=patch  6=inhaler
--   dosage        = per-dose quantity  e.g. '1' (tablet)  '2' (puffs)  '18' (units)
--   size          = strength number    e.g. '100'  '5'  '9.5'
--   unit          = int code  1=mg  2=mcg  3=ml  4=units  7=g  9=IU
--   route         = OpenEMR text  bymouth / injection / inhalation / transdermal / sublingual / nasal
--   interval      = 0=QD 1=BID 2=TID 3=QID 4=Q4H 5=Q6H 6=Q8H 7=Q12H 8=PRN
--
-- normalizeImportedDrugName() strips ' 100 MG Oral Capsule' leaving 'gabapentin' for MAR display.
-- normalizeUnit(int_code_as_string) → 'mg' / 'mcg' / 'units' / 'IU' etc.
-- normalizeRoute('bymouth') → 'PO'  |  'inhalation' → 'INH'  |  'transdermal' → 'TOP'  etc.
-- interval stored as int → normalizeFrequency maps to QD/BID/TID etc.
-- -----------------------------------------------------------------------------

INSERT IGNORE INTO `prescriptions`
(`id`, `patient_id`, `filled_by_id`, `pharmacy_id`,
 `date_added`, `date_modified`,
 `provider_id`, `encounter`, `start_date`, `end_date`,
 `drug`, `drug_id`, `rxnorm_drugcode`,
 `form`, `dosage`, `quantity`, `size`, `unit`,
 `route`, `interval`,
 `substitute`, `refills`, `per_refill`,
 `filled_date`, `medication`, `note`, `active`,
 `datetime`, `user`, `site`, `prescriptionguid`,
 `erx_source`, `erx_uploaded`, `drug_info_erx`, `external_id`,
 `indication`, `prn`, `ntx`, `rtx`, `txDate`,
 `drug_dosage_instructions`, `usage_category`, `usage_category_title`,
 `request_intent`, `request_intent_title`, `created_by`, `updated_by`, `diagnosis`)
VALUES

-- ── pid=3  Sandra Kowalski  (OBS / cardiac — NSTEMI hx, CHADS2)
(1, 3, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-06-01', NULL,
 'aspirin 81 MG Oral Tablet', 0, '243670',
 1, '1', '30', '81', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Cardiac prophylaxis — low dose daily.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-06-01',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(2, 3, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-06-01', NULL,
 'metoprolol succinate 50 MG Extended Release Oral Tablet',
 0, '831484',
 1, '1', '30', '50', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Rate control. Hold HR <55 or SBP <100.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-06-01',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(3, 3, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-06-01', NULL,
 'atorvastatin 40 MG Oral Tablet', 0, '617311',
 1, '1', '30', '40', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'High-intensity statin. Check LFTs annually.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-06-01',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(4, 3, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-06-01', NULL,
 'lisinopril 10 MG Oral Tablet', 0, '314076',
 1, '1', '30', '10', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'ACE inhibitor for LV function.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-06-01',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(5, 3, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-06-01', NULL,
 'clopidogrel 75 MG Oral Tablet', 0, '309362',
 1, '1', '30', '75', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'DAPT. Do not discontinue without cardiology approval.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-06-01',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

-- ── pid=5  Patricia Nguyen  (IP ORTHO — TKA, HTN)
(6, 5, NULL, NULL, NOW(), NOW(),
 5, NULL, '2024-09-10', NULL,
 'atorvastatin 20 MG Oral Tablet', 0, '617310',
 1, '1', '30', '20', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'Moderate-intensity statin.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2024-09-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(7, 5, NULL, NULL, NOW(), NOW(),
 5, NULL, '2024-09-10', NULL,
 'lisinopril 5 MG Oral Tablet', 0, '314073',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'HTN. Monitor K+ and creatinine.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2024-09-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(8, 5, NULL, NULL, NOW(), NOW(),
 5, NULL, '2024-09-10', NULL,
 'metoprolol tartrate 25 MG Oral Tablet', 0, '866511',
 1, '1', '60', '25', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'HTN / rate control.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2024-09-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(9, 5, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'acetaminophen 500 MG Oral Tablet', 0, '198440',
 1, '2', '60', '500', 1,
 'bymouth', 3,
 1, 5, 0, NULL, 0, 'Post-op pain scheduled. Max 4g/day.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

-- ── pid=6  Thomas Blackwell  (IP TELEMETRY — NSTEMI, DES to LAD)
(10, 6, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'aspirin 81 MG Oral Tablet', 0, '243670',
 1, '1', '30', '81', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'DAPT post-stent. Do not hold.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

(11, 6, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'clopidogrel 75 MG Oral Tablet', 0, '309362',
 1, '1', '30', '75', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'DAPT post-DES. 12 months minimum.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

(12, 6, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'atorvastatin 80 MG Oral Tablet', 0, '617312',
 1, '1', '30', '80', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'High-intensity statin post-ACS.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

(13, 6, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'metoprolol succinate 25 MG Extended Release Oral Tablet',
 0, '831482',
 1, '1', '30', '25', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Post-MI beta blockade. Titrate to HR 50-60.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

(14, 6, NULL, NULL, NOW(), NOW(),
 5, NULL, '2026-02-25', NULL,
 'lisinopril 5 MG Oral Tablet', 0, '314073',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Post-MI ACE. Start low, titrate.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-25',
 NULL, 'inpatient', 'Inpatient', 'order', 'Order', 5, 5, NULL),

-- ── pid=7  Rosa Martinez  (ED trauma — elderly, osteoporosis)
(15, 7, NULL, NULL, NOW(), NOW(),
 1, NULL, '2024-01-15', NULL,
 'calcium carbonate 600 MG / cholecalciferol 400 UNT Oral Tablet',
 0, '1145958',
 1, '1', '60', '600', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'Osteoporosis prophylaxis.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2024-01-15',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(16, 7, NULL, NULL, NOW(), NOW(),
 1, NULL, '2024-01-15', NULL,
 'acetaminophen 325 MG Oral Tablet', 0, '198439',
 1, '2', '30', '325', 1,
 'bymouth', 8,
 1, 11, 0, NULL, 0, 'PRN mild pain. Avoid NSAIDs — age/renal risk.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2024-01-15',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

-- ── pid=8  James Okonkwo  (IP PNEUMONIA — COPD Gold III)
(17, 8, NULL, NULL, NOW(), NOW(),
 5, NULL, '2023-05-20', NULL,
 'tiotropium 18 MCG Inhalation Capsule', 0, '896006',
 6, '1', '30', '18', 2,
 'inhalation', 0,
 1, 11, 0, NULL, 0, 'LAMA once daily. HandiHaler technique. Rinse mouth after.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2023-05-20',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(18, 8, NULL, NULL, NOW(), NOW(),
 5, NULL, '2023-05-20', NULL,
 'albuterol 90 MCG/ACTUAT Inhalation Aerosol',
 0, '745678',
 6, '2', '200', '90', 2,
 'inhalation', 8,
 1, 11, 0, NULL, 0, 'SABA rescue PRN dyspnea / bronchospasm.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2023-05-20',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(19, 8, NULL, NULL, NOW(), NOW(),
 5, NULL, '2023-05-20', NULL,
 'fluticasone propionate 250 MCG / salmeterol 50 MCG Inhalation Powder',
 0, '896237',
 6, '2', '60', '250', 2,
 'inhalation', 1,
 1, 11, 0, NULL, 0, 'ICS/LABA maintenance BID. Rinse mouth after each use.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2023-05-20',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

-- ── pid=9  Linda Yamamoto  (ED — UTI, otherwise healthy)
(20, 9, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-11-10', '2025-11-17',
 'nitrofurantoin 100 MG Extended Release Oral Capsule',
 0, '647798',
 3, '1', '14', '100', 1,
 'bymouth', 1,
 1, 0, 0, NULL, 0, 'UTI course — completed. Discontinued.', 0,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-11-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(21, 9, NULL, NULL, NOW(), NOW(),
 1, NULL, '2026-01-05', NULL,
 'ibuprofen 400 MG Oral Tablet', 0, '310965',
 1, '1', '30', '400', 1,
 'bymouth', 8,
 1, 11, 0, NULL, 0, 'PRN mild-moderate pain. Take with food.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-05',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

-- ── pid=10  Carlos Rivera  (ED opioid OD — MOUD)
(22, 10, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-08-14', NULL,
 'buprenorphine 8 MG / naloxone 2 MG Sublingual Film',
 0, '1307056',
 2, '1', '14', '8', 1,
 'sublingual', 1,
 1, 11, 0, NULL, 0, 'MOUD. BID. Dispensed weekly. No concurrent benzodiazepines.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-08-14',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

(23, 10, NULL, NULL, NOW(), NOW(),
 1, NULL, '2025-08-14', NULL,
 'naloxone 4 MG/0.1ML Nasal Spray', 0, '1860487',
 2, '1', '1', '4', 1,
 'nasal', 8,
 1, 5, 0, NULL, 0, 'Rescue kit. Patient and household educated on use.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2025-08-14',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 1, 1, NULL),

-- ── pid=11  Walter Drummond  (IP ORTHO — hip ORIF, AF, DM2, CKD)
(24, 11, NULL, NULL, NOW(), NOW(),
 5, NULL, '2022-03-10', NULL,
 'warfarin sodium 5 MG Oral Tablet', 0, '855332',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'AF anticoagulation. Target INR 2-3. Check weekly.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2022-03-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(25, 11, NULL, NULL, NOW(), NOW(),
 5, NULL, '2022-03-10', NULL,
 'metformin hydrochloride 500 MG Oral Tablet',
 0, '860975',
 1, '1', '60', '500', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'DM2. Hold perioperatively.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2022-03-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(26, 11, NULL, NULL, NOW(), NOW(),
 5, NULL, '2022-03-10', NULL,
 'lisinopril 10 MG Oral Tablet', 0, '314076',
 1, '1', '30', '10', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'HTN / DM renal protection.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2022-03-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

(27, 11, NULL, NULL, NOW(), NOW(),
 5, NULL, '2022-03-10', NULL,
 'atorvastatin 40 MG Oral Tablet', 0, '617311',
 1, '1', '30', '40', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'Statin — AF / DM2 cardiovascular risk.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2022-03-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 5, 5, NULL),

-- ── pid=50  Eleanor Hartwell  (AL — moderate dementia, fall hx)
(28, 50, NULL, NULL, NOW(), NOW(),
 7, 1000050, '2026-01-12', NULL,
 'donepezil hydrochloride 10 MG Oral Tablet',
 0, '997225',
 1, '1', '30', '10', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'Alzheimer dementia. Bedtime dosing reduces GI effects.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-12',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(29, 50, NULL, NULL, NOW(), NOW(),
 7, 1000050, '2026-01-12', NULL,
 'memantine hydrochloride 10 MG Oral Tablet',
 0, '996740',
 1, '1', '60', '10', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'Moderate-severe AD. BID with food.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-12',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(30, 50, NULL, NULL, NOW(), NOW(),
 7, 1000050, '2026-01-12', NULL,
 'quetiapine fumarate 25 MG Oral Tablet', 0, '202433',
 1, '1', '30', '25', 1,
 'bymouth', 4,
 1, 11, 0, NULL, 0, 'Behavioral disturbances. Bedtime. Monitor sedation and fall risk.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-12',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(31, 50, NULL, NULL, NOW(), NOW(),
 7, 1000050, '2026-01-12', NULL,
 'cholecalciferol 1000 UNT Oral Capsule', 0, '316446',
 3, '1', '30', '1000', 9,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Fall risk / bone health supplement.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-12',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

-- ── pid=51  George Calloway  (AL — post-THA, HTN)
(32, 51, NULL, NULL, NOW(), NOW(),
 7, 1000051, '2026-01-28', NULL,
 'acetaminophen 650 MG Extended Release Oral Tablet',
 0, '1148396',
 1, '2', '30', '650', 1,
 'bymouth', 3,
 1, 11, 0, NULL, 0, 'Scheduled pain post-THA. Max 3g/day (age-adjusted).', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-28',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(33, 51, NULL, NULL, NOW(), NOW(),
 7, 1000051, '2026-01-28', '2026-03-15',
 'oxycodone hydrochloride 5 MG Oral Tablet',
 0, '1049502',
 1, '1', '10', '5', 1,
 'bymouth', 8,
 1, 2, 0, NULL, 0, 'PRN breakthrough pain. Taper by week 4.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-28',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(34, 51, NULL, NULL, NOW(), NOW(),
 7, 1000051, '2026-01-28', '2026-03-28',
 'enoxaparin sodium 40 MG/0.4ML Subcutaneous Injection',
 0, '854235',
 4, '1', '35', '40', 1,
 'injection', 0,
 1, 0, 0, NULL, 0, 'VTE prophylaxis post-THA. 35-day course.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-28',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(35, 51, NULL, NULL, NOW(), NOW(),
 7, 1000051, '2026-01-28', NULL,
 'amlodipine besylate 5 MG Oral Tablet', 0, '197361',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'HTN maintenance — calcium channel blocker.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-28',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

-- ── pid=52  Ruth Okonkwo  (AL — severe COPD Gold III)
(36, 52, NULL, NULL, NOW(), NOW(),
 7, 1000052, '2026-02-10', NULL,
 'tiotropium 18 MCG Inhalation Capsule', 0, '896006',
 6, '1', '30', '18', 2,
 'inhalation', 0,
 1, 11, 0, NULL, 0, 'LAMA once daily. HandiHaler technique.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(37, 52, NULL, NULL, NOW(), NOW(),
 7, 1000052, '2026-02-10', NULL,
 'albuterol 90 MCG/ACTUAT Inhalation Aerosol',
 0, '745678',
 6, '2', '200', '90', 2,
 'inhalation', 8,
 1, 11, 0, NULL, 0, 'SABA rescue PRN dyspnea / bronchospasm.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(38, 52, NULL, NULL, NOW(), NOW(),
 7, 1000052, '2026-02-10', NULL,
 'fluticasone propionate 250 MCG/ACTUAT Inhalation Aerosol',
 0, '746763',
 6, '2', '60', '250', 2,
 'inhalation', 1,
 1, 11, 0, NULL, 0, 'ICS BID. Rinse mouth and gargle after use.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(39, 52, NULL, NULL, NOW(), NOW(),
 7, 1000052, '2026-02-10', NULL,
 'prednisone 5 MG Oral Tablet', 0, '312617',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'Low-dose maintenance oral steroid COPD Gold III. Taper failed x2.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-10',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

-- ── pid=53  Harold Steinberg  (AL — Parkinson + mild cognitive impairment)
(40, 53, NULL, NULL, NOW(), NOW(),
 7, 1000053, '2026-02-17', NULL,
 'carbidopa 25 MG / levodopa 100 MG Oral Tablet',
 0, '996685',
 1, '1', '90', '100', 1,
 'bymouth', 2,
 1, 11, 0, NULL, 0, 'PD — TID. Give 30 min before meals. Track ON/OFF periods.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-17',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(41, 53, NULL, NULL, NOW(), NOW(),
 7, 1000053, '2026-02-17', NULL,
 'rivastigmine 9.5 MG/24HR Transdermal System',
 0, '1100185',
 5, '1', '30', '9.5', 1,
 'transdermal', 0,
 1, 11, 0, NULL, 0, 'Parkinson dementia patch. Rotate site daily.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-17',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(42, 53, NULL, NULL, NOW(), NOW(),
 7, 1000053, '2026-02-17', NULL,
 'docusate sodium 100 MG Oral Capsule', 0, '1294361',
 3, '1', '60', '100', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'Constipation prevention — dopaminergic therapy effect.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-02-17',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

-- ── pid=54  Dorothy Vasquez  (AL — CHF EF 35%, DM2, CKD stage 3)
(43, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'furosemide 40 MG Oral Tablet', 0, '313988',
 1, '1', '30', '40', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'CHF diuresis. Monitor daily weight.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(44, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'lisinopril 5 MG Oral Tablet', 0, '314073',
 1, '1', '30', '5', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'CHF / ACE. Monitor K+ — CKD3 caution.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(45, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'carvedilol 6.25 MG Oral Tablet', 0, '200031',
 1, '1', '60', '6.25', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'CHF beta-blockade BID with food.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(46, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'insulin glargine 100 UNT/ML Injectable Solution',
 0, '1551291',
 4, '18', '10', '100', 4,
 'injection', 4,
 1, 11, 0, NULL, 0, 'Basal insulin at bedtime. Target fasting glucose 100-140.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(47, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'metformin hydrochloride 500 MG Oral Tablet',
 0, '860975',
 1, '1', '60', '500', 1,
 'bymouth', 1,
 1, 11, 0, NULL, 0, 'DM2 BID with meals. eGFR >30 — continue with monitoring.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL),

(48, 54, NULL, NULL, NOW(), NOW(),
 7, 1000054, '2026-01-22', NULL,
 'spironolactone 25 MG Oral Tablet', 0, '313096',
 1, '1', '30', '25', 1,
 'bymouth', 0,
 1, 11, 0, NULL, 0, 'CHF aldosterone antagonist. Monitor K+.', 1,
 NULL, NULL, NULL, NULL, 0, 0, NULL, NULL,
 NULL, NULL, 0, NULL, '2026-01-22',
 NULL, 'outpatient', 'Outpatient', 'order', 'Order', 7, 7, NULL);



-- =============================================================================
-- SECTION 3 — Home-Based Care (HBC) demo seed — idempotent upserts
-- =============================================================================
-- Demo patients / episodes:
--   PID 60 / Episode 24  → NEW referral in queue
--   PID 61 / Episode 25  → SCHEDULED first visit (today / near-now)
--   PID 62 / Episode 26  → ACTIVE case with completed visit history,
--                          vitals, fall risk, tasks, MAR, incident,
--                          eReferral, and discharge planning
-- Safe to re-run: deterministic IDs plus targeted refresh for forms/care-plan/
-- clinical-notes/care-team rows owned by these HBC demo patients.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Core OpenEMR rows for HBC demo patients
-- -----------------------------------------------------------------------------

INSERT INTO `patient_data`
(`id`, `pid`, `fname`, `lname`, `DOB`, `sex`,
 `street`, `city`, `state`, `postal_code`, `country_code`,
 `phone_home`, `status`, `date`)
VALUES (60, 60, 'Nora', 'Whitfield', '1942-10-14', 'Female', '125 Willow Creek Drive', 'Springfield', 'IL', '62711', 'US', '217-555-0260', 'active', NOW()),
       (61, 61, 'Bernard', 'Price', '1937-05-09', 'Male', '980 Lakeview Terrace', 'Springfield', 'IL', '62712', 'US', '217-555-0261', 'active', NOW()),
       (62, 62, 'Alma', 'Serrano', '1949-02-21', 'Female', '44 Garden Court', 'Springfield', 'IL', '62713', 'US', '217-555-0262', 'active', NOW())
ON DUPLICATE KEY UPDATE `fname`        = VALUES(`fname`),
                        `lname`        = VALUES(`lname`),
                        `DOB`          = VALUES(`DOB`),
                        `sex`          = VALUES(`sex`),
                        `street`       = VALUES(`street`),
                        `city`         = VALUES(`city`),
                        `state`        = VALUES(`state`),
                        `postal_code`  = VALUES(`postal_code`),
                        `country_code` = VALUES(`country_code`),
                        `phone_home`   = VALUES(`phone_home`),
                        `status`       = VALUES(`status`),
                        `date`         = NOW();

INSERT INTO `form_encounter`
(`id`, `date`, `onset_date`, `reason`, `facility`, `pid`,
 `provider_id`, `facility_id`, `billing_facility`, `encounter`, `pos_code`)
VALUES (307, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 'HBC Referral Intake', 'Home-Based Care', 60, 1, 1, 1, 1000060, 12),
       (308, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'HBC Referral Intake', 'Home-Based Care', 61, 1, 1, 1, 1000061, 12),
       (309, DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 'HBC Start of Care', 'Home-Based Care', 62, 1, 1, 1, 1000062, 12)
ON DUPLICATE KEY UPDATE `date`             = VALUES(`date`),
                        `onset_date`       = VALUES(`onset_date`),
                        `reason`           = VALUES(`reason`),
                        `facility`         = VALUES(`facility`),
                        `pid`              = VALUES(`pid`),
                        `provider_id`      = VALUES(`provider_id`),
                        `facility_id`      = VALUES(`facility_id`),
                        `billing_facility` = VALUES(`billing_facility`),
                        `encounter`        = VALUES(`encounter`),
                        `pos_code`         = VALUES(`pos_code`);

DELETE
FROM `forms`
WHERE `pid` IN (60, 61, 62)
  AND `encounter` IN (1000060, 1000061, 1000062)
  AND `formdir` IN ('newpatient', 'care_plan', 'clinical_notes');

INSERT INTO `forms`
(`date`, `encounter`, `form_name`, `form_id`, `pid`,
 `user`, `groupname`, `authorized`, `deleted`, `formdir`, `therapy_group_id`)
VALUES (DATE_SUB(NOW(), INTERVAL 2 DAY), 1000060, 'New Patient Encounter', 307, 60, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       (DATE_SUB(NOW(), INTERVAL 1 DAY), 1000061, 'New Patient Encounter', 308, 61, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       (DATE_SUB(NOW(), INTERVAL 14 DAY), 1000062, 'New Patient Encounter', 309, 62, 'admin', 'Default', 1, 0, 'newpatient', NULL),
       (DATE_SUB(NOW(), INTERVAL 2 DAY), 1000060, 'Care Plan', 6001, 60, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       (DATE_SUB(NOW(), INTERVAL 1 DAY), 1000061, 'Care Plan', 6002, 61, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       (DATE_SUB(NOW(), INTERVAL 14 DAY), 1000062, 'Care Plan', 6003, 62, 'admin', 'Default', 1, 0, 'care_plan', NULL),
       (DATE_SUB(NOW(), INTERVAL 1 DAY), 1000060, 'Clinical Notes', 6101, 60, 'admin', 'Default', 1, 0, 'clinical_notes', NULL),
       (DATE_SUB(NOW(), INTERVAL 4 HOUR), 1000061, 'Clinical Notes', 6102, 61, 'admin', 'Default', 1, 0, 'clinical_notes', NULL),
       (DATE_SUB(NOW(), INTERVAL 20 HOUR), 1000062, 'Clinical Notes', 6103, 62, 'admin', 'Default', 1, 0, 'clinical_notes', NULL);

-- -----------------------------------------------------------------------------
-- Care team / care plan / clinical notes for HBC demo patients
-- -----------------------------------------------------------------------------

DELETE
FROM `care_team_member`
WHERE `care_team_id` IN (SELECT `id` FROM `care_teams` WHERE `pid` IN (60, 61, 62));
DELETE
FROM `care_teams`
WHERE `pid` IN (60, 61, 62);

INSERT INTO `care_teams`
    (`pid`, `status`, `team_name`, `note`, `created_by`, `updated_by`)
VALUES (60, 'active', 'Nora Whitfield Home-Based Care Team', 'Post-discharge CHF surveillance and first-visit scheduling', 1, 1),
       (61, 'active', 'Bernard Price Home-Based Care Team', 'Urgent first-visit logistics and caregiver coaching', 1, 1),
       (62, 'active', 'Alma Serrano Home-Based Care Team', 'Skilled nursing wound / CHF management with cardiology follow-up', 1, 1);

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`,
       1,
       'physician',
       'active',
       CURDATE(),
       CASE ct.`pid`
           WHEN 60 THEN 'Supervising physician — HBC intake oversight'
           WHEN 61 THEN 'Supervising physician — urgent visit review'
           WHEN 62 THEN 'Supervising physician — wound / CHF plan oversight'
           END,
       1,
       1
FROM `care_teams` ct
WHERE ct.`pid` IN (60, 61, 62)
  AND ct.`status` = 'active';

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`,
       CASE WHEN ct.`pid` = 62 THEN 3 ELSE 2 END,
       'nurse',
       'active',
       CURDATE(),
       CASE ct.`pid`
           WHEN 60 THEN 'Referral triage nurse and scheduling contact'
           WHEN 61 THEN 'Assigned visiting nurse for first home visit'
           WHEN 62 THEN 'Primary field nurse for ongoing wound / CHF follow-up'
           END,
       1,
       1
FROM `care_teams` ct
WHERE ct.`pid` IN (60, 61, 62)
  AND ct.`status` = 'active';

DELETE
FROM `form_care_plan`
WHERE `pid` IN (60, 61, 62)
  AND `encounter` IN (1000060, 1000061, 1000062);

INSERT INTO `form_care_plan`
(`id`, `date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
 `description`, `care_plan_type`, `plan_status`, `proposed_date`)
VALUES (6001, CURDATE(), 60, 1000060, 1, 'Default', 1, 1,
        'Complete first home RN assessment within 48 hours of referral and confirm home safety barriers.',
        'goal', 'active', DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
       (6001, CURDATE(), 60, 1000060, 1, 'Default', 1, 1,
        'Verify daily weights, low-sodium diet understanding, and escalation plan during first visit.',
        'activity', 'active', DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
       (6002, CURDATE(), 61, 1000061, 1, 'Default', 1, 1,
        'Stabilize post-discharge medication regimen and caregiver confidence after first urgent HBC visit.',
        'goal', 'active', DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
       (6002, CURDATE(), 61, 1000061, 1, 'Default', 1, 1,
        'Document full medication reconciliation and reinforce walker / oxygen safety at initial visit.',
        'activity', 'active', DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
       (6003, CURDATE(), 62, 1000062, 1, 'Default', 1, 1,
        'Promote wound healing without infection and keep daily weight variance under 2 lb this cert period.',
        'goal', 'active', DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
       (6003, CURDATE(), 62, 1000062, 1, 'Default', 1, 1,
        'Skilled nursing visit every 3 days for wound care, edema review, and med adherence coaching.',
        'activity', 'active', DATE_ADD(CURDATE(), INTERVAL 21 DAY)),
       (6003, CURDATE(), 62, 1000062, 1, 'Default', 1, 1,
        'Fax cardiology update after each significant weight gain, dyspnea change, or med adjustment.',
        'activity', 'active', DATE_ADD(CURDATE(), INTERVAL 7 DAY));

DELETE
FROM `form_clinical_notes`
WHERE `pid` IN (60, 61, 62)
  AND `encounter` IN (1000060, 1000061, 1000062);

INSERT INTO `form_clinical_notes`
(`id`, `form_id`, `date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
 `code`, `description`, `clinical_notes_type`, `clinical_notes_category`, `note_related_to`, `last_updated`)
VALUES (6101, 6101, DATE_SUB(NOW(), INTERVAL 1 DAY), 60, 1000060, 'admin', 'Default', 1, 1,
        'HBC-REFERRAL', 'Referral reviewed — awaiting first home visit scheduling and caregiver confirmation.',
        'care_note', 'coordination', 'HBC', DATE_SUB(NOW(), INTERVAL 1 DAY)),
       (6102, 6102, DATE_SUB(NOW(), INTERVAL 4 HOUR), 61, 1000061, 'admin', 'Default', 1, 1,
        'HBC-PREVISIT', 'Pre-visit planning note — spouse prefers call 15 minutes before arrival; walker and oxygen concentrator in use.',
        'care_note', 'coordination', 'HBC', DATE_SUB(NOW(), INTERVAL 4 HOUR)),
       (6103, 6103, DATE_SUB(NOW(), INTERVAL 20 HOUR), 62, 1000062, 'admin', 'Default', 1, 1,
        'HBC-FOLLOWUP', 'Follow-up note — wound improving, edema down 1+, cardiology fax sent with updated med list.',
        'care_note', 'progress', 'HBC', DATE_SUB(NOW(), INTERVAL 20 HOUR));

-- -----------------------------------------------------------------------------
-- Ensure HBC tables exist on demo installs
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `oei_hbc_episode`
(
    `id`                        bigint(20) UNSIGNED                                             NOT NULL AUTO_INCREMENT,
    `episode_id`                bigint(20) UNSIGNED                                             NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`                       bigint(20) UNSIGNED                                             NOT NULL COMMENT 'FK → patient_data.pid',
    `facility_id`               bigint(20) UNSIGNED                                             NOT NULL,
    `encounter_id`              bigint(20) UNSIGNED                                                      DEFAULT NULL COMMENT 'OpenEMR encounter NUMBER (form_encounter.encounter) — anchors care plan entries',
    `referral_source`           varchar(120)                                                             DEFAULT NULL COMMENT 'Free text or coded: GP, Hospital, Self, Family, Agency, etc.',
    `referral_reason`           varchar(255)                                                             DEFAULT NULL,
    `referral_status`           enum ('NEW','TRIAGED','SCHEDULED','ACTIVE','CLOSED','DECLINED') NOT NULL DEFAULT 'NEW',
    `urgency`                   enum ('ROUTINE','URGENT','EMERGENT')                            NOT NULL DEFAULT 'ROUTINE',
    `referral_datetime`         datetime                                                                 DEFAULT NULL,
    `soc_datetime`              datetime                                                                 DEFAULT NULL COMMENT 'Start of Care — first clinical visit date',
    `service_address_line1`     varchar(120)                                                             DEFAULT NULL,
    `service_address_line2`     varchar(120)                                                             DEFAULT NULL,
    `service_city`              varchar(80)                                                              DEFAULT NULL,
    `service_state_province`    varchar(80)                                                              DEFAULT NULL,
    `service_postal_code`       varchar(20)                                                              DEFAULT NULL,
    `service_country`           varchar(60)                                                              DEFAULT NULL,
    `access_notes`              varchar(255)                                                             DEFAULT NULL COMMENT 'Gate code, parking, dog, key location, etc.',
    `caregiver_name`            varchar(120)                                                             DEFAULT NULL,
    `caregiver_phone`           varchar(40)                                                              DEFAULT NULL,
    `caregiver_relationship`    varchar(60)                                                              DEFAULT NULL COMMENT 'Spouse, Child, Friend, Home carer, etc.',
    `primary_clinician_user_id` bigint(20) UNSIGNED                                                      DEFAULT NULL COMMENT 'FK → users.id',
    `primary_diagnosis`         varchar(255)                                                             DEFAULT NULL,
    `primary_icd10`             varchar(20)                                                              DEFAULT NULL,
    `payer_name`                varchar(120)                                                             DEFAULT NULL,
    `authorization_notes`       text                                                                     DEFAULT NULL,
    `cert_period_start`         date                                                                     DEFAULT NULL,
    `cert_period_end`           date                                                                     DEFAULT NULL,
    `created_datetime`          datetime                                                        NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_hbc_episode` (`episode_id`),
    KEY `idx_hbc_pid` (`pid`),
    KEY `idx_hbc_facility` (`facility_id`),
    KEY `idx_hbc_clinician` (`primary_clinician_user_id`),
    KEY `idx_hbc_status` (`referral_status`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Home-Based Care episode overlay — one row per HBC episode';

CREATE TABLE IF NOT EXISTS `oei_hbc_visit`
(
    `id`                         bigint(20) UNSIGNED                                                              NOT NULL AUTO_INCREMENT,
    `episode_id`                 bigint(20) UNSIGNED                                                              NOT NULL COMMENT 'FK → oei_episode.id',
    `pid`                        bigint(20) UNSIGNED                                                              NOT NULL,
    `facility_id`                bigint(20) UNSIGNED                                                              NOT NULL,
    `visit_type`                 enum ('SN','PT','OT','ST','MSW','HHA','MD','OTHER')                              NOT NULL DEFAULT 'SN',
    `clinician_user_id`          bigint(20) UNSIGNED                                                                       DEFAULT NULL COMMENT 'FK → users.id',
    `scheduled_datetime`         datetime                                                                                  DEFAULT NULL,
    `window_start_datetime`      datetime                                                                                  DEFAULT NULL COMMENT 'Optional arrival window start',
    `window_end_datetime`        datetime                                                                                  DEFAULT NULL COMMENT 'Optional arrival window end',
    `route_sequence`             smallint(5) UNSIGNED                                                                      DEFAULT NULL COMMENT 'Optional daily route order',
    `travel_notes`               varchar(255)                                                                              DEFAULT NULL COMMENT 'Parking, gate, arrival preference, route notes',
    `actual_start_datetime`      datetime                                                                                  DEFAULT NULL,
    `actual_end_datetime`        datetime                                                                                  DEFAULT NULL,
    `status`                     enum ('SCHEDULED','EN_ROUTE','ARRIVED','COMPLETE','MISSED','REFUSED','CANCELED') NOT NULL DEFAULT 'SCHEDULED',
    `actual_lat`                 decimal(10, 7)                                                                            DEFAULT NULL COMMENT 'GPS lat at visit start — nullable, never required',
    `actual_lng`                 decimal(10, 7)                                                                            DEFAULT NULL COMMENT 'GPS lng at visit start — nullable, never required',
    `draft_data`                 text                                                                                      DEFAULT NULL COMMENT 'JSON — partial form data saved from mobile field (not yet finalised)',
    `is_draft`                   tinyint(1)                                                                       NOT NULL DEFAULT 0 COMMENT '1 = clinician saved draft from field; 0 = finalised or not started',
    `patient_signature_obtained` tinyint(1)                                                                       NOT NULL DEFAULT 0,
    `patient_signature_datetime` datetime                                                                                  DEFAULT NULL,
    `patient_signature_data`     mediumtext                                                                                DEFAULT NULL COMMENT 'Base64 PNG from canvas — stored here to keep visit record self-contained',
    `visit_note`                 text                                                                                      DEFAULT NULL,
    `outcome_summary`            varchar(255)                                                                              DEFAULT NULL,
    `mileage_miles`              decimal(6, 2)                                                                             DEFAULT NULL,
    `med_reconciliation_status`  enum ('NOT_DONE','NO_CHANGES','UPDATED','ISSUES_FOUND')                          NOT NULL DEFAULT 'NOT_DONE',
    `med_reconciliation_summary` text                                                                                      DEFAULT NULL,
    `wound_summary`              text                                                                                      DEFAULT NULL,
    `procedure_summary`          text                                                                                      DEFAULT NULL,
    `home_safety_summary`        text                                                                                      DEFAULT NULL,
    `care_coordination_needed`   tinyint(1)                                                                       NOT NULL DEFAULT 0,
    `care_coordination_summary`  text                                                                                      DEFAULT NULL,
    `followup_plan`              text                                                                                      DEFAULT NULL,
    `next_visit_due_date`        date                                                                                      DEFAULT NULL,
    `next_visit_type`            enum ('SN','PT','OT','ST','MSW','HHA','MD','OTHER')                                       DEFAULT NULL,
    `created_by_user_id`         bigint(20) UNSIGNED                                                                       DEFAULT NULL,
    `created_datetime`           datetime                                                                         NOT NULL DEFAULT current_timestamp(),
    `updated_datetime`           datetime                                                                         NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_hbcv_episode` (`episode_id`),
    KEY `idx_hbcv_pid` (`pid`),
    KEY `idx_hbcv_clinician` (`clinician_user_id`),
    KEY `idx_hbcv_scheduled` (`scheduled_datetime`),
    KEY `idx_hbcv_status` (`status`),
    KEY `idx_hbcv_route` (`facility_id`, `route_sequence`, `scheduled_datetime`),
    KEY `idx_hbcv_followup` (`facility_id`, `next_visit_due_date`),
    KEY `idx_hbcv_facility_date` (`facility_id`, `scheduled_datetime`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci COMMENT ='Home-Based Care visit record — one row per clinical encounter';

INSERT INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('0009', NOW()),
       ('0.23.0-demo-hbc', NOW())
ON DUPLICATE KEY UPDATE `applied_datetime` = VALUES(`applied_datetime`);

-- -----------------------------------------------------------------------------
-- HBC episodes / overlays
-- -----------------------------------------------------------------------------

INSERT INTO `oei_episode`
(`id`, `pid`, `eid`, `facility_id`, `type`, `start_datetime`, `end_datetime`, `disposition`, `status`,
 `chief_complaint`, `acuity_esi`, `provider_user_id`, `triage_completed_datetime`, `last_status_update`,
 `arrival_mode`, `triage_datetime`, `triage_note`, `created_by_user_id`, `created_datetime`,
 `assigned_nurse_user_id`, `assigned_provider_user_id`)
VALUES (24, 60, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL, 'ACTIVE', 'Hospital discharge referral — CHF monitoring and medication reconciliation', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 'TRANSFER', DATE_SUB(NOW(), INTERVAL 2 DAY),
        'Referral received from discharge planner', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 2, 1),
       (25, 61, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, NULL, 'ACTIVE', 'Urgent home-based follow-up — post-pneumonia recovery and med setup', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'WALKIN', DATE_SUB(NOW(), INTERVAL 1 DAY),
        'Caregiver requested urgent first visit', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 2, 1),
       (26, 62, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 14 DAY), NULL, NULL, 'ACTIVE', 'Active home-based skilled nursing — CHF, edema, and lower-leg wound follow-up', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'TRANSFER', DATE_SUB(NOW(), INTERVAL 14 DAY),
        'Started from post-acute referral', 1, DATE_SUB(NOW(), INTERVAL 14 DAY), 3, 1)
ON DUPLICATE KEY UPDATE `pid`                       = VALUES(`pid`),
                        `facility_id`               = VALUES(`facility_id`),
                        `type`                      = VALUES(`type`),
                        `start_datetime`            = VALUES(`start_datetime`),
                        `end_datetime`              = VALUES(`end_datetime`),
                        `disposition`               = VALUES(`disposition`),
                        `status`                    = VALUES(`status`),
                        `chief_complaint`           = VALUES(`chief_complaint`),
                        `provider_user_id`          = VALUES(`provider_user_id`),
                        `last_status_update`        = VALUES(`last_status_update`),
                        `arrival_mode`              = VALUES(`arrival_mode`),
                        `triage_datetime`           = VALUES(`triage_datetime`),
                        `triage_note`               = VALUES(`triage_note`),
                        `created_by_user_id`        = VALUES(`created_by_user_id`),
                        `created_datetime`          = VALUES(`created_datetime`),
                        `assigned_nurse_user_id`    = VALUES(`assigned_nurse_user_id`),
                        `assigned_provider_user_id` = VALUES(`assigned_provider_user_id`);

INSERT INTO `oei_hbc_episode`
(`id`, `episode_id`, `pid`, `facility_id`, `encounter_id`, `referral_source`, `referral_reason`, `referral_status`, `urgency`,
 `referral_datetime`, `soc_datetime`, `service_address_line1`, `service_address_line2`, `service_city`, `service_state_province`,
 `service_postal_code`, `service_country`, `access_notes`, `caregiver_name`, `caregiver_phone`, `caregiver_relationship`,
 `primary_clinician_user_id`, `primary_diagnosis`, `primary_icd10`, `payer_name`, `authorization_notes`, `cert_period_start`,
 `cert_period_end`, `created_datetime`)
VALUES (9101, 24, 60, 1, 1000060, 'Springfield General Discharge Planner', 'Post-discharge CHF monitoring and medication reconciliation', 'NEW', 'ROUTINE',
        DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, '125 Willow Creek Drive', NULL, 'Springfield', 'IL', '62711', 'US', 'Use side gate; small dog secured in back room.', 'Lisa Whitfield', '217-555-0260', 'Daughter',
        1, 'Congestive heart failure with recent fluid overload', 'I50.9', 'Traditional Medicare', 'Needs first home RN visit within 48 hours.', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
       (9102, 25, 61, 1, 1000061, 'Family Medicine Clinic', 'Urgent first home visit after pneumonia discharge; caregiver unsure of medication setup', 'SCHEDULED', 'URGENT',
        DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, '980 Lakeview Terrace', 'Apartment 2B', 'Springfield', 'IL', '62712', 'US', 'Buzz apartment 2B; spouse requests 15-minute arrival call.', 'Martha Price', '217-555-0361', 'Spouse',
        1, 'Pneumonia recovery with deconditioning and polypharmacy risk', 'J18.9', 'Traditional Medicare', 'First visit already approved by PCP; review inhaler / antibiotic completion.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 61 DAY),
        DATE_SUB(NOW(), INTERVAL 1 DAY)),
       (9103, 26, 62, 1, 1000062, 'Valley Home Health Agency Transition Desk', 'Ongoing skilled nursing for CHF, edema, and lower-leg wound care', 'ACTIVE', 'URGENT',
        DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), '44 Garden Court', NULL, 'Springfield', 'IL', '62713', 'US', 'Park in carport; enter through side door; granddaughter works nights.', 'Elena Serrano', '217-555-0462', 'Granddaughter',
        1, 'CHF with chronic venous insufficiency and healing left shin wound', 'I50.9', 'Traditional Medicare', 'Cardiology follow-up requested for recurrent 2-lb weight swings.', DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 48 DAY),
        DATE_SUB(NOW(), INTERVAL 14 DAY))
ON DUPLICATE KEY UPDATE `episode_id`                = VALUES(`episode_id`),
                        `pid`                       = VALUES(`pid`),
                        `facility_id`               = VALUES(`facility_id`),
                        `encounter_id`              = VALUES(`encounter_id`),
                        `referral_source`           = VALUES(`referral_source`),
                        `referral_reason`           = VALUES(`referral_reason`),
                        `referral_status`           = VALUES(`referral_status`),
                        `urgency`                   = VALUES(`urgency`),
                        `referral_datetime`         = VALUES(`referral_datetime`),
                        `soc_datetime`              = VALUES(`soc_datetime`),
                        `service_address_line1`     = VALUES(`service_address_line1`),
                        `service_address_line2`     = VALUES(`service_address_line2`),
                        `service_city`              = VALUES(`service_city`),
                        `service_state_province`    = VALUES(`service_state_province`),
                        `service_postal_code`       = VALUES(`service_postal_code`),
                        `service_country`           = VALUES(`service_country`),
                        `access_notes`              = VALUES(`access_notes`),
                        `caregiver_name`            = VALUES(`caregiver_name`),
                        `caregiver_phone`           = VALUES(`caregiver_phone`),
                        `caregiver_relationship`    = VALUES(`caregiver_relationship`),
                        `primary_clinician_user_id` = VALUES(`primary_clinician_user_id`),
                        `primary_diagnosis`         = VALUES(`primary_diagnosis`),
                        `primary_icd10`             = VALUES(`primary_icd10`),
                        `payer_name`                = VALUES(`payer_name`),
                        `authorization_notes`       = VALUES(`authorization_notes`),
                        `cert_period_start`         = VALUES(`cert_period_start`),
                        `cert_period_end`           = VALUES(`cert_period_end`),
                        `created_datetime`          = VALUES(`created_datetime`);

-- -----------------------------------------------------------------------------
-- HBC visits / lifecycle history
-- -----------------------------------------------------------------------------

INSERT INTO `oei_hbc_visit`
(`id`, `episode_id`, `pid`, `facility_id`, `visit_type`, `clinician_user_id`, `scheduled_datetime`, `window_start_datetime`, `window_end_datetime`,
 `route_sequence`, `travel_notes`, `actual_start_datetime`, `actual_end_datetime`, `status`, `actual_lat`, `actual_lng`, `draft_data`, `is_draft`,
 `patient_signature_obtained`, `patient_signature_datetime`, `patient_signature_data`, `visit_note`, `outcome_summary`, `mileage_miles`,
 `med_reconciliation_status`, `med_reconciliation_summary`, `wound_summary`, `procedure_summary`, `home_safety_summary`,
 `care_coordination_needed`, `care_coordination_summary`, `followup_plan`, `next_visit_due_date`, `next_visit_type`, `created_by_user_id`,
 `created_datetime`, `updated_datetime`)
VALUES (9201, 25, 61, 1, 'SN', 2, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 90 MINUTE), DATE_ADD(NOW(), INTERVAL 3 HOUR),
        1, 'Buzz apartment 2B; spouse requests call 15 minutes before arrival.', NULL, NULL, 'SCHEDULED', NULL, NULL, NULL, 0,
        0, NULL, NULL, NULL, NULL, NULL,
        'NOT_DONE', NULL, NULL, NULL, NULL,
        0, NULL, NULL, NULL, NULL, 1,
        DATE_SUB(NOW(), INTERVAL 30 MINUTE), NOW()),
       (9202, 26, 62, 1, 'SN', 3, DATE_SUB(NOW(), INTERVAL 25 HOUR), DATE_SUB(NOW(), INTERVAL 27 HOUR), DATE_SUB(NOW(), INTERVAL 23 HOUR),
        2, 'Park in carport; enter through side door.', DATE_SUB(NOW(), INTERVAL 25 HOUR), DATE_SUB(NOW(), INTERVAL 24 HOUR), 'COMPLETE', 39.7817000, -89.6501000, NULL, 0,
        1, DATE_SUB(NOW(), INTERVAL 24 HOUR), 'demo-signature-alma',
        'Skilled nursing visit completed. Reinforced CHF zone teaching, reconciled discharge medications, assessed edema, and performed left shin wound care.',
        'Stable at visit end; caregiver verbalized red-flag plan and next-visit expectations.', 12.40,
        'UPDATED',
        'Removed duplicate furosemide instruction from discharge list and clarified daily weight escalation thresholds.',
        'Left shin skin tear 2.1 x 0.8 cm with granulation tissue; no erythema or purulent drainage.',
        'Wound cleansed with saline and dressed with bordered foam; bilateral edema check completed.',
        'Loose throw rugs removed; reinforced walker use, hydration station setup, and nighttime pathway lighting.',
        1,
        'Faxed cardiology summary and updated medication list for review of weight variability.',
        'Repeat SN visit in 3 days; monitor weight trend, edema, and wound healing progress.', DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'SN', 1,
        DATE_SUB(NOW(), INTERVAL 25 HOUR), NOW()),
       (9203, 26, 62, 1, 'PT', 3, DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY),
        4, 'Granddaughter asked to reschedule if patient fatigued after cardiology travel.', NULL, NULL, 'MISSED', NULL, NULL, NULL, 0,
        0, NULL, NULL,
        NULL,
        'Patient unavailable — cardiology office visit ran long; PT to reschedule.', NULL,
        'NOT_DONE', NULL, NULL, NULL, NULL,
        0, NULL, 'Reschedule PT after cardiology plan finalized.', DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'PT', 1,
        DATE_SUB(NOW(), INTERVAL 4 DAY), NOW()),
       (9204, 26, 62, 1, 'SN', 3, DATE_ADD(NOW(), INTERVAL 26 HOUR), DATE_ADD(NOW(), INTERVAL 25 HOUR), DATE_ADD(NOW(), INTERVAL 28 HOUR),
        3, 'Follow-up visit — confirm wound supplies on hand before arrival.', NULL, NULL, 'SCHEDULED', NULL, NULL, NULL, 0,
        0, NULL, NULL, NULL, NULL, NULL,
        'NOT_DONE', NULL, NULL, NULL, NULL,
        0, NULL, NULL, NULL, NULL, 1,
        NOW(), NOW())
ON DUPLICATE KEY UPDATE `episode_id`                 = VALUES(`episode_id`),
                        `pid`                        = VALUES(`pid`),
                        `facility_id`                = VALUES(`facility_id`),
                        `visit_type`                 = VALUES(`visit_type`),
                        `clinician_user_id`          = VALUES(`clinician_user_id`),
                        `scheduled_datetime`         = VALUES(`scheduled_datetime`),
                        `window_start_datetime`      = VALUES(`window_start_datetime`),
                        `window_end_datetime`        = VALUES(`window_end_datetime`),
                        `route_sequence`             = VALUES(`route_sequence`),
                        `travel_notes`               = VALUES(`travel_notes`),
                        `actual_start_datetime`      = VALUES(`actual_start_datetime`),
                        `actual_end_datetime`        = VALUES(`actual_end_datetime`),
                        `status`                     = VALUES(`status`),
                        `actual_lat`                 = VALUES(`actual_lat`),
                        `actual_lng`                 = VALUES(`actual_lng`),
                        `draft_data`                 = VALUES(`draft_data`),
                        `is_draft`                   = VALUES(`is_draft`),
                        `patient_signature_obtained` = VALUES(`patient_signature_obtained`),
                        `patient_signature_datetime` = VALUES(`patient_signature_datetime`),
                        `patient_signature_data`     = VALUES(`patient_signature_data`),
                        `visit_note`                 = VALUES(`visit_note`),
                        `outcome_summary`            = VALUES(`outcome_summary`),
                        `mileage_miles`              = VALUES(`mileage_miles`),
                        `med_reconciliation_status`  = VALUES(`med_reconciliation_status`),
                        `med_reconciliation_summary` = VALUES(`med_reconciliation_summary`),
                        `wound_summary`              = VALUES(`wound_summary`),
                        `procedure_summary`          = VALUES(`procedure_summary`),
                        `home_safety_summary`        = VALUES(`home_safety_summary`),
                        `care_coordination_needed`   = VALUES(`care_coordination_needed`),
                        `care_coordination_summary`  = VALUES(`care_coordination_summary`),
                        `followup_plan`              = VALUES(`followup_plan`),
                        `next_visit_due_date`        = VALUES(`next_visit_due_date`),
                        `next_visit_type`            = VALUES(`next_visit_type`),
                        `created_by_user_id`         = VALUES(`created_by_user_id`),
                        `created_datetime`           = VALUES(`created_datetime`),
                        `updated_datetime`           = VALUES(`updated_datetime`);

INSERT INTO `oei_episode_event`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES (9301, 24, 60, NULL, 1, 'REFERRAL_RECEIVED', DATE_SUB(NOW(), INTERVAL 2 DAY), 1, 'Referral entered from discharge planner — awaiting first scheduling contact.'),
       (9302, 25, 61, NULL, 1, 'REFERRAL_ACCEPTED', DATE_SUB(NOW(), INTERVAL 22 HOUR), 1, 'Urgent referral accepted — spouse confirmed availability today.'),
       (9303, 25, 61, NULL, 1, 'VISIT_SCHEDULED', DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 'SN first visit scheduled with route sequence 1 and arrival window.'),
       (9304, 26, 62, NULL, 1, 'SOC_STARTED', DATE_SUB(NOW(), INTERVAL 12 DAY), 1, 'Start of care completed after post-acute referral.'),
       (9305, 26, 62, NULL, 1, 'VISIT_MISSED', DATE_SUB(NOW(), INTERVAL 4 DAY), 3, 'PT visit missed — patient at outside cardiology appointment.'),
       (9306, 26, 62, NULL, 1, 'VISIT_COMPLETE', DATE_SUB(NOW(), INTERVAL 24 HOUR), 3, 'Skilled nursing follow-up completed with med rec and wound care.'),
       (9307, 26, 62, NULL, 1, 'VISIT_SCHEDULED', NOW(), 1, 'Follow-up SN visit scheduled for tomorrow.')
ON DUPLICATE KEY UPDATE `episode_id`     = VALUES(`episode_id`),
                        `pid`            = VALUES(`pid`),
                        `eid`            = VALUES(`eid`),
                        `facility_id`    = VALUES(`facility_id`),
                        `event_type`     = VALUES(`event_type`),
                        `event_datetime` = VALUES(`event_datetime`),
                        `user_id`        = VALUES(`user_id`),
                        `note`           = VALUES(`note`);

-- -----------------------------------------------------------------------------
-- Supporting HBC capability data: vitals, fall risk, tasks, MAR, incidents,
-- eReferral, and discharge planning
-- -----------------------------------------------------------------------------

INSERT INTO `oei_triage`
(`id`, `episode_id`, `pid`, `eid`, `facility_id`, `set_number`, `bp_systolic`, `bp_diastolic`, `hr`, `rr`, `temp_f`, `spo2`, `gcs`, `pain_score`, `weight_kg`, `arrival_mode`, `esi_suggested`, `notes`, `noted_by_user_id`, `noted_datetime`)
VALUES (9401, 26, 62, NULL, 1, 1, 134, 78, 86, 18, 98.4, 94, 15, 3, 68.40, 'WALKIN', NULL, 'Visit set — mild edema, breathing comfortable at rest.', 3, DATE_SUB(NOW(), INTERVAL 24 HOUR)),
       (9402, 26, 62, NULL, 1, 2, 128, 76, 78, 17, 98.2, 95, 15, 2, 67.90, 'WALKIN', NULL, 'Repeat set after wound care and med review.', 3, DATE_SUB(NOW(), INTERVAL 23 HOUR)),
       (9403, 25, 61, NULL, 1, 1, 142, 84, 92, 20, 98.7, 93, 15, 4, 81.20, 'WALKIN', NULL, 'Pre-visit intake summary from caregiver report.', 2, DATE_SUB(NOW(), INTERVAL 3 HOUR))
ON DUPLICATE KEY UPDATE `episode_id`       = VALUES(`episode_id`),
                        `pid`              = VALUES(`pid`),
                        `facility_id`      = VALUES(`facility_id`),
                        `set_number`       = VALUES(`set_number`),
                        `bp_systolic`      = VALUES(`bp_systolic`),
                        `bp_diastolic`     = VALUES(`bp_diastolic`),
                        `hr`               = VALUES(`hr`),
                        `rr`               = VALUES(`rr`),
                        `temp_f`           = VALUES(`temp_f`),
                        `spo2`             = VALUES(`spo2`),
                        `gcs`              = VALUES(`gcs`),
                        `pain_score`       = VALUES(`pain_score`),
                        `weight_kg`        = VALUES(`weight_kg`),
                        `arrival_mode`     = VALUES(`arrival_mode`),
                        `notes`            = VALUES(`notes`),
                        `noted_by_user_id` = VALUES(`noted_by_user_id`),
                        `noted_datetime`   = VALUES(`noted_datetime`);

INSERT INTO `oei_fall_risk_assessment`
(`id`, `episode_id`, `facility_id`, `assessed_by_user_id`, `assessed_datetime`,
 `mfs_fall_history`, `mfs_secondary_dx`, `mfs_ambulatory_aid`, `mfs_iv_heparin_lock`, `mfs_gait`, `mfs_mental_status`,
 `total_score`, `risk_level`, `notes`, `created_datetime`)
VALUES (9501, 25, 1, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), 25, 15, 15, 0, 10, 15, 80, 'HIGH', 'Walker-dependent, recent deconditioning, forgets limitations when fatigued.', NOW()),
       (9502, 26, 1, 3, DATE_SUB(NOW(), INTERVAL 35 DAY), 25, 15, 15, 0, 10, 15, 80, 'HIGH', 'Reassessment overdue — prior near-fall on bathroom transfer.', NOW())
ON DUPLICATE KEY UPDATE `episode_id`          = VALUES(`episode_id`),
                        `facility_id`         = VALUES(`facility_id`),
                        `assessed_by_user_id` = VALUES(`assessed_by_user_id`),
                        `assessed_datetime`   = VALUES(`assessed_datetime`),
                        `mfs_fall_history`    = VALUES(`mfs_fall_history`),
                        `mfs_secondary_dx`    = VALUES(`mfs_secondary_dx`),
                        `mfs_ambulatory_aid`  = VALUES(`mfs_ambulatory_aid`),
                        `mfs_iv_heparin_lock` = VALUES(`mfs_iv_heparin_lock`),
                        `mfs_gait`            = VALUES(`mfs_gait`),
                        `mfs_mental_status`   = VALUES(`mfs_mental_status`),
                        `total_score`         = VALUES(`total_score`),
                        `risk_level`          = VALUES(`risk_level`),
                        `notes`               = VALUES(`notes`),
                        `created_datetime`    = VALUES(`created_datetime`);

INSERT INTO `oei_task`
(`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`, `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`, `created_by_user_id`, `created_datetime`)
VALUES (9601, 24, 60, NULL, 1, 'HBC_REFERRAL_REVIEW', DATE_ADD(NOW(), INTERVAL 2 HOUR), NULL, 2, 'OPEN', '{"task_label":"Review new HBC referral","detail":"Call daughter to confirm access instructions and preferred arrival window."}', 1, NOW()),
       (9602, 25, 61, NULL, 1, 'HBC_FIRST_VISIT_PREP', DATE_ADD(NOW(), INTERVAL 90 MINUTE), NULL, 2, 'OPEN', '{"task_label":"Prepare first visit","detail":"Bring med-reconciliation worksheet and walker safety checklist."}', 1, NOW()),
       (9603, 26, 62, NULL, 1, 'HBC_COORDINATION_REVIEW', DATE_ADD(NOW(), INTERVAL 4 HOUR), NULL, 1, 'OPEN', '{"visit_id":9202,"task_label":"Care coordination follow-up","detail":"Confirm cardiology office received updated medication list and weight log."}', 3, NOW()),
       (9604, 26, 62, NULL, 1, 'HBC_FOLLOW_UP_VISIT', DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 9 HOUR), NULL, 3, 'OPEN', '{"visit_id":9202,"task_label":"Schedule follow-up visit","detail":"Repeat wound/CHF skilled nursing visit.","next_visit_type":"SN"}', 3, NOW()),
       (9605, 26, 62, NULL, 1, 'HBC_MED_REC_REVIEW', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, 'DONE',
        '{"visit_id":9202,"task_label":"Medication reconciliation review","detail":"Duplicate discharge diuretic instruction resolved with cardiology office."}', 3, DATE_SUB(NOW(), INTERVAL 24 HOUR))
ON DUPLICATE KEY UPDATE `episode_id`          = VALUES(`episode_id`),
                        `pid`                 = VALUES(`pid`),
                        `eid`                 = VALUES(`eid`),
                        `facility_id`         = VALUES(`facility_id`),
                        `task_type`           = VALUES(`task_type`),
                        `due_datetime`        = VALUES(`due_datetime`),
                        `completed_datetime`  = VALUES(`completed_datetime`),
                        `assigned_to_user_id` = VALUES(`assigned_to_user_id`),
                        `status`              = VALUES(`status`),
                        `payload_json`        = VALUES(`payload_json`),
                        `created_by_user_id`  = VALUES(`created_by_user_id`),
                        `created_datetime`    = VALUES(`created_datetime`);

INSERT INTO `oei_mar_order`
(`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`, `frequency`, `is_prn`, `is_stat`, `is_high_alert`, `status`, `ordered_datetime`, `discontinued_datetime`, `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`, `instructions`, `created_datetime`,
 `updated_datetime`)
VALUES (9701, 26, 62, 1, 'furosemide', '40', 'mg', 'PO', 'DAILY', 0, 0, 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, 1, NULL, NULL, 'Hold and call provider for dizziness with SBP < 100 or sudden 2-lb/day weight gain.', DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
       (9702, 26, 62, 1, 'potassium chloride', '20', 'mEq', 'PO', 'DAILY', 0, 0, 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, 1, NULL, NULL, 'Take with food when furosemide administered.', DATE_SUB(NOW(), INTERVAL 12 DAY), NOW())
ON DUPLICATE KEY UPDATE `episode_id`              = VALUES(`episode_id`),
                        `pid`                     = VALUES(`pid`),
                        `facility_id`             = VALUES(`facility_id`),
                        `drug_name`               = VALUES(`drug_name`),
                        `dose`                    = VALUES(`dose`),
                        `unit`                    = VALUES(`unit`),
                        `route`                   = VALUES(`route`),
                        `frequency`               = VALUES(`frequency`),
                        `is_prn`                  = VALUES(`is_prn`),
                        `is_stat`                 = VALUES(`is_stat`),
                        `is_high_alert`           = VALUES(`is_high_alert`),
                        `status`                  = VALUES(`status`),
                        `ordered_datetime`        = VALUES(`ordered_datetime`),
                        `discontinued_datetime`   = VALUES(`discontinued_datetime`),
                        `ordered_by_user_id`      = VALUES(`ordered_by_user_id`),
                        `discontinued_by_user_id` = VALUES(`discontinued_by_user_id`),
                        `rx_id`                   = VALUES(`rx_id`),
                        `instructions`            = VALUES(`instructions`),
                        `created_datetime`        = VALUES(`created_datetime`),
                        `updated_datetime`        = VALUES(`updated_datetime`);

INSERT INTO `oei_mar_administration`
(`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`, `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`, `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `witness_user_id`, `waste_amount`, `waste_unit`,
 `co_sign_user_id`, `co_signed_datetime`, `note`, `is_high_alert`, `created_datetime`, `updated_datetime`)
VALUES (9801, 9701, 26, 62, 1, DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 25 HOUR), 'GIVEN', '40', 'mg', 'PO', NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, NULL, 'Daily dose given before wound care visit.', 0, DATE_SUB(NOW(), INTERVAL 26 HOUR), NOW()),
       (9802, 9702, 26, 62, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending evening potassium dose.', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())
ON DUPLICATE KEY UPDATE `mar_order_id`            = VALUES(`mar_order_id`),
                        `episode_id`              = VALUES(`episode_id`),
                        `pid`                     = VALUES(`pid`),
                        `facility_id`             = VALUES(`facility_id`),
                        `scheduled_datetime`      = VALUES(`scheduled_datetime`),
                        `administered_datetime`   = VALUES(`administered_datetime`),
                        `outcome`                 = VALUES(`outcome`),
                        `dose_given`              = VALUES(`dose_given`),
                        `unit_given`              = VALUES(`unit_given`),
                        `route_given`             = VALUES(`route_given`),
                        `site`                    = VALUES(`site`),
                        `lot_number`              = VALUES(`lot_number`),
                        `hold_reason`             = VALUES(`hold_reason`),
                        `administered_by_user_id` = VALUES(`administered_by_user_id`),
                        `note`                    = VALUES(`note`),
                        `is_high_alert`           = VALUES(`is_high_alert`),
                        `created_datetime`        = VALUES(`created_datetime`),
                        `updated_datetime`        = VALUES(`updated_datetime`);

INSERT INTO `oei_incident`
(`id`, `episode_id`, `facility_id`, `reported_by_user_id`, `incident_type`, `severity`, `incident_datetime`, `location_description`, `narrative`, `corrective_action`, `reported_state`, `mandatory_report_sent`, `created_datetime`)
VALUES (9901, 26, 1, 3, 'FALL_NEAR_MISS', 'LOW', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Bathroom doorway', 'Patient lost balance during transfer but caregiver prevented fall; no injury sustained.',
        'Reinforced walker placement, cleared doorway clutter, and requested repeat fall-risk reassessment.', 'NOT_REQUIRED', 0, DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE `episode_id`            = VALUES(`episode_id`),
                        `facility_id`           = VALUES(`facility_id`),
                        `reported_by_user_id`   = VALUES(`reported_by_user_id`),
                        `incident_type`         = VALUES(`incident_type`),
                        `severity`              = VALUES(`severity`),
                        `incident_datetime`     = VALUES(`incident_datetime`),
                        `location_description`  = VALUES(`location_description`),
                        `narrative`             = VALUES(`narrative`),
                        `corrective_action`     = VALUES(`corrective_action`),
                        `reported_state`        = VALUES(`reported_state`),
                        `mandatory_report_sent` = VALUES(`mandatory_report_sent`),
                        `created_datetime`      = VALUES(`created_datetime`);

INSERT INTO `oei_episode_disposition`
(`id`, `episode_id`, `pid`, `eid`, `facility_id`, `disposition_code`, `destination`, `decision_datetime`, `depart_datetime`, `admit_flag`, `notes`, `updated_by_user_id`, `updated_datetime`)
VALUES (9951, 26, 62, NULL, 1, 'SERVICE_COMPLETED', 'Return to PCP / cardiology management after wound closure', DATE_ADD(NOW(), INTERVAL 10 DAY), NULL, 0, 'Draft closure plan — keep open until wound fully epithelialized and weight stable.', 1, NOW())
ON DUPLICATE KEY UPDATE `episode_id`         = VALUES(`episode_id`),
                        `pid`                = VALUES(`pid`),
                        `eid`                = VALUES(`eid`),
                        `facility_id`        = VALUES(`facility_id`),
                        `disposition_code`   = VALUES(`disposition_code`),
                        `destination`        = VALUES(`destination`),
                        `decision_datetime`  = VALUES(`decision_datetime`),
                        `depart_datetime`    = VALUES(`depart_datetime`),
                        `admit_flag`         = VALUES(`admit_flag`),
                        `notes`              = VALUES(`notes`),
                        `updated_by_user_id` = VALUES(`updated_by_user_id`),
                        `updated_datetime`   = VALUES(`updated_datetime`);

INSERT INTO `oei_ereferral`
(`id`, `episode_id`, `pid`, `eid`, `facility_id`, `referral_type`, `status`, `priority`, `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`, `destination_address`, `reason_for_referral`, `clinical_summary`, `services_requested`,
 `medications_summary`, `followup_instructions`, `sent_datetime`, `sent_by_user_id`, `send_method`, `response_datetime`, `response_by_name`, `response_notes`, `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES (9961, 26, 62, NULL, 1, 'DISCHARGE', 'SENT', 'URGENT', 18, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', '505 Heart Lane, Riverside, CA 92506',
        'Cardiology review after recurrent weight fluctuations during home-based CHF management.',
        'Active HBC patient with improving edema and healing lower-leg wound. Medication reconciliation completed and updated med list attached by fax.',
        'Medication review, outpatient CHF follow-up, weight-gain call thresholds confirmation.',
        'Furosemide 40 mg daily; potassium chloride 20 mEq daily; caregiver keeping daily weight log.',
        'Respond to HBC team within 48 hours with any medication changes or new fluid-management parameters.',
        DATE_SUB(NOW(), INTERVAL 20 HOUR), 1, 'FAX', NULL, NULL, NULL, 1, DATE_SUB(NOW(), INTERVAL 20 HOUR), NOW())
ON DUPLICATE KEY UPDATE `episode_id`               = VALUES(`episode_id`),
                        `pid`                      = VALUES(`pid`),
                        `eid`                      = VALUES(`eid`),
                        `facility_id`              = VALUES(`facility_id`),
                        `referral_type`            = VALUES(`referral_type`),
                        `status`                   = VALUES(`status`),
                        `priority`                 = VALUES(`priority`),
                        `destination_directory_id` = VALUES(`destination_directory_id`),
                        `destination_name`         = VALUES(`destination_name`),
                        `destination_fax`          = VALUES(`destination_fax`),
                        `destination_phone`        = VALUES(`destination_phone`),
                        `destination_address`      = VALUES(`destination_address`),
                        `reason_for_referral`      = VALUES(`reason_for_referral`),
                        `clinical_summary`         = VALUES(`clinical_summary`),
                        `services_requested`       = VALUES(`services_requested`),
                        `medications_summary`      = VALUES(`medications_summary`),
                        `followup_instructions`    = VALUES(`followup_instructions`),
                        `sent_datetime`            = VALUES(`sent_datetime`),
                        `sent_by_user_id`          = VALUES(`sent_by_user_id`),
                        `send_method`              = VALUES(`send_method`),
                        `response_datetime`        = VALUES(`response_datetime`),
                        `response_by_name`         = VALUES(`response_by_name`),
                        `response_notes`           = VALUES(`response_notes`),
                        `created_by_user_id`       = VALUES(`created_by_user_id`),
                        `created_datetime`         = VALUES(`created_datetime`),
                        `updated_datetime`         = VALUES(`updated_datetime`);

SET FOREIGN_KEY_CHECKS = 1;

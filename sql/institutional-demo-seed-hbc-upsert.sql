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
VALUES
    (60, 60, 'Nora',     'Whitfield', '1942-10-14', 'Female', '125 Willow Creek Drive', 'Springfield', 'IL', '62711', 'US', '217-555-0260', 'active', NOW()),
    (61, 61, 'Bernard',  'Price',     '1937-05-09', 'Male',   '980 Lakeview Terrace',   'Springfield', 'IL', '62712', 'US', '217-555-0261', 'active', NOW()),
    (62, 62, 'Alma',     'Serrano',   '1949-02-21', 'Female', '44 Garden Court',        'Springfield', 'IL', '62713', 'US', '217-555-0262', 'active', NOW())
ON DUPLICATE KEY UPDATE
    `fname` = VALUES(`fname`),
    `lname` = VALUES(`lname`),
    `DOB` = VALUES(`DOB`),
    `sex` = VALUES(`sex`),
    `street` = VALUES(`street`),
    `city` = VALUES(`city`),
    `state` = VALUES(`state`),
    `postal_code` = VALUES(`postal_code`),
    `country_code` = VALUES(`country_code`),
    `phone_home` = VALUES(`phone_home`),
    `status` = VALUES(`status`),
    `date` = NOW();

INSERT INTO `form_encounter`
    (`id`, `date`, `onset_date`, `reason`, `facility`, `pid`,
     `provider_id`, `facility_id`, `billing_facility`, `encounter`, `pos_code`)
VALUES
    (307, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 'HBC Referral Intake', 'Home-Based Care', 60, 1, 1, 1, 1000060, 12),
    (308, DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 'HBC Referral Intake', 'Home-Based Care', 61, 1, 1, 1, 1000061, 12),
    (309, DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 'HBC Start of Care', 'Home-Based Care', 62, 1, 1, 1, 1000062, 12)
ON DUPLICATE KEY UPDATE
    `date` = VALUES(`date`),
    `onset_date` = VALUES(`onset_date`),
    `reason` = VALUES(`reason`),
    `facility` = VALUES(`facility`),
    `pid` = VALUES(`pid`),
    `provider_id` = VALUES(`provider_id`),
    `facility_id` = VALUES(`facility_id`),
    `billing_facility` = VALUES(`billing_facility`),
    `encounter` = VALUES(`encounter`),
    `pos_code` = VALUES(`pos_code`);

DELETE FROM `forms`
WHERE `pid` IN (60,61,62)
  AND `encounter` IN (1000060,1000061,1000062)
  AND `formdir` IN ('newpatient', 'care_plan', 'clinical_notes');

INSERT INTO `forms`
    (`date`, `encounter`, `form_name`, `form_id`, `pid`,
     `user`, `groupname`, `authorized`, `deleted`, `formdir`, `therapy_group_id`)
VALUES
    (DATE_SUB(NOW(), INTERVAL 2 DAY), 1000060, 'New Patient Encounter', 307, 60, 'admin', 'Default', 1, 0, 'newpatient', NULL),
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

DELETE FROM `care_team_member`
WHERE `care_team_id` IN (SELECT `id` FROM `care_teams` WHERE `pid` IN (60,61,62));
DELETE FROM `care_teams` WHERE `pid` IN (60,61,62);

INSERT INTO `care_teams`
    (`pid`, `status`, `team_name`, `note`, `created_by`, `updated_by`)
VALUES
    (60, 'active', 'Nora Whitfield Home-Based Care Team', 'Post-discharge CHF surveillance and first-visit scheduling', 1, 1),
    (61, 'active', 'Bernard Price Home-Based Care Team',  'Urgent first-visit logistics and caregiver coaching', 1, 1),
    (62, 'active', 'Alma Serrano Home-Based Care Team',   'Skilled nursing wound / CHF management with cardiology follow-up', 1, 1);

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`, 1, 'physician', 'active', CURDATE(),
       CASE ct.`pid`
         WHEN 60 THEN 'Supervising physician — HBC intake oversight'
         WHEN 61 THEN 'Supervising physician — urgent visit review'
         WHEN 62 THEN 'Supervising physician — wound / CHF plan oversight'
       END,
       1, 1
FROM `care_teams` ct
WHERE ct.`pid` IN (60,61,62) AND ct.`status` = 'active';

INSERT INTO `care_team_member`
    (`care_team_id`, `user_id`, `role`, `status`, `provider_since`, `note`, `created_by`, `updated_by`)
SELECT ct.`id`,
       CASE WHEN ct.`pid` = 62 THEN 3 ELSE 2 END,
       'nurse', 'active', CURDATE(),
       CASE ct.`pid`
         WHEN 60 THEN 'Referral triage nurse and scheduling contact'
         WHEN 61 THEN 'Assigned visiting nurse for first home visit'
         WHEN 62 THEN 'Primary field nurse for ongoing wound / CHF follow-up'
       END,
       1, 1
FROM `care_teams` ct
WHERE ct.`pid` IN (60,61,62) AND ct.`status` = 'active';

DELETE FROM `form_care_plan`
WHERE `pid` IN (60,61,62)
  AND `encounter` IN (1000060,1000061,1000062);

INSERT INTO `form_care_plan`
    (`id`, `date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
     `description`, `care_plan_type`, `plan_status`, `proposed_date`)
VALUES
    (6001, CURDATE(), 60, 1000060, 1, 'Default', 1, 1,
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

DELETE FROM `form_clinical_notes`
WHERE `pid` IN (60,61,62)
  AND `encounter` IN (1000060,1000061,1000062);

INSERT INTO `form_clinical_notes`
    (`id`, `form_id`, `date`, `pid`, `encounter`, `user`, `groupname`, `authorized`, `activity`,
     `code`, `description`, `clinical_notes_type`, `clinical_notes_category`, `note_related_to`, `last_updated`)
VALUES
    (6101, 6101, DATE_SUB(NOW(), INTERVAL 1 DAY), 60, 1000060, 'admin', 'Default', 1, 1,
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
    `route_sequence`             smallint(5) UNSIGNED                                                                     DEFAULT NULL COMMENT 'Optional daily route order',
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
    `med_reconciliation_status`  enum ('NOT_DONE','NO_CHANGES','UPDATED','ISSUES_FOUND')                         NOT NULL DEFAULT 'NOT_DONE',
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
VALUES
    ('0009', NOW()),
    ('0.23.0-demo-hbc', NOW())
ON DUPLICATE KEY UPDATE
    `applied_datetime` = VALUES(`applied_datetime`);

-- -----------------------------------------------------------------------------
-- HBC episodes / overlays
-- -----------------------------------------------------------------------------

INSERT INTO `oei_episode`
    (`id`, `pid`, `eid`, `facility_id`, `type`, `start_datetime`, `end_datetime`, `disposition`, `status`,
     `chief_complaint`, `acuity_esi`, `provider_user_id`, `triage_completed_datetime`, `last_status_update`,
     `arrival_mode`, `triage_datetime`, `triage_note`, `created_by_user_id`, `created_datetime`,
     `assigned_nurse_user_id`, `assigned_provider_user_id`)
VALUES
    (24, 60, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 2 DAY),  NULL, NULL, 'ACTIVE', 'Hospital discharge referral — CHF monitoring and medication reconciliation', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY), 'TRANSFER', DATE_SUB(NOW(), INTERVAL 2 DAY), 'Referral received from discharge planner', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), 2, 1),
    (25, 61, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 1 DAY),  NULL, NULL, 'ACTIVE', 'Urgent home-based follow-up — post-pneumonia recovery and med setup', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'WALKIN', DATE_SUB(NOW(), INTERVAL 1 DAY), 'Caregiver requested urgent first visit', 1, DATE_SUB(NOW(), INTERVAL 1 DAY), 2, 1),
    (26, 62, NULL, 1, 'HBC', DATE_SUB(NOW(), INTERVAL 14 DAY), NULL, NULL, 'ACTIVE', 'Active home-based skilled nursing — CHF, edema, and lower-leg wound follow-up', NULL, 1, NULL, DATE_SUB(NOW(), INTERVAL 1 DAY), 'TRANSFER', DATE_SUB(NOW(), INTERVAL 14 DAY), 'Started from post-acute referral', 1, DATE_SUB(NOW(), INTERVAL 14 DAY), 3, 1)
ON DUPLICATE KEY UPDATE
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `type` = VALUES(`type`),
    `start_datetime` = VALUES(`start_datetime`),
    `end_datetime` = VALUES(`end_datetime`),
    `disposition` = VALUES(`disposition`),
    `status` = VALUES(`status`),
    `chief_complaint` = VALUES(`chief_complaint`),
    `provider_user_id` = VALUES(`provider_user_id`),
    `last_status_update` = VALUES(`last_status_update`),
    `arrival_mode` = VALUES(`arrival_mode`),
    `triage_datetime` = VALUES(`triage_datetime`),
    `triage_note` = VALUES(`triage_note`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `created_datetime` = VALUES(`created_datetime`),
    `assigned_nurse_user_id` = VALUES(`assigned_nurse_user_id`),
    `assigned_provider_user_id` = VALUES(`assigned_provider_user_id`);

INSERT INTO `oei_hbc_episode`
    (`id`, `episode_id`, `pid`, `facility_id`, `encounter_id`, `referral_source`, `referral_reason`, `referral_status`, `urgency`,
     `referral_datetime`, `soc_datetime`, `service_address_line1`, `service_address_line2`, `service_city`, `service_state_province`,
     `service_postal_code`, `service_country`, `access_notes`, `caregiver_name`, `caregiver_phone`, `caregiver_relationship`,
     `primary_clinician_user_id`, `primary_diagnosis`, `primary_icd10`, `payer_name`, `authorization_notes`, `cert_period_start`,
     `cert_period_end`, `created_datetime`)
VALUES
    (9101, 24, 60, 1, 1000060, 'Springfield General Discharge Planner', 'Post-discharge CHF monitoring and medication reconciliation', 'NEW', 'ROUTINE',
     DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, '125 Willow Creek Drive', NULL, 'Springfield', 'IL', '62711', 'US', 'Use side gate; small dog secured in back room.', 'Lisa Whitfield', '217-555-0260', 'Daughter',
     1, 'Congestive heart failure with recent fluid overload', 'I50.9', 'Traditional Medicare', 'Needs first home RN visit within 48 hours.', NULL, NULL, DATE_SUB(NOW(), INTERVAL 2 DAY)),
    (9102, 25, 61, 1, 1000061, 'Family Medicine Clinic', 'Urgent first home visit after pneumonia discharge; caregiver unsure of medication setup', 'SCHEDULED', 'URGENT',
     DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, '980 Lakeview Terrace', 'Apartment 2B', 'Springfield', 'IL', '62712', 'US', 'Buzz apartment 2B; spouse requests 15-minute arrival call.', 'Martha Price', '217-555-0361', 'Spouse',
     1, 'Pneumonia recovery with deconditioning and polypharmacy risk', 'J18.9', 'Traditional Medicare', 'First visit already approved by PCP; review inhaler / antibiotic completion.', DATE_ADD(CURDATE(), INTERVAL 1 DAY), DATE_ADD(CURDATE(), INTERVAL 61 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY)),
    (9103, 26, 62, 1, 1000062, 'Valley Home Health Agency Transition Desk', 'Ongoing skilled nursing for CHF, edema, and lower-leg wound care', 'ACTIVE', 'URGENT',
     DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), '44 Garden Court', NULL, 'Springfield', 'IL', '62713', 'US', 'Park in carport; enter through side door; granddaughter works nights.', 'Elena Serrano', '217-555-0462', 'Granddaughter',
     1, 'CHF with chronic venous insufficiency and healing left shin wound', 'I50.9', 'Traditional Medicare', 'Cardiology follow-up requested for recurrent 2-lb weight swings.', DATE_SUB(CURDATE(), INTERVAL 12 DAY), DATE_ADD(CURDATE(), INTERVAL 48 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY))
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `encounter_id` = VALUES(`encounter_id`),
    `referral_source` = VALUES(`referral_source`),
    `referral_reason` = VALUES(`referral_reason`),
    `referral_status` = VALUES(`referral_status`),
    `urgency` = VALUES(`urgency`),
    `referral_datetime` = VALUES(`referral_datetime`),
    `soc_datetime` = VALUES(`soc_datetime`),
    `service_address_line1` = VALUES(`service_address_line1`),
    `service_address_line2` = VALUES(`service_address_line2`),
    `service_city` = VALUES(`service_city`),
    `service_state_province` = VALUES(`service_state_province`),
    `service_postal_code` = VALUES(`service_postal_code`),
    `service_country` = VALUES(`service_country`),
    `access_notes` = VALUES(`access_notes`),
    `caregiver_name` = VALUES(`caregiver_name`),
    `caregiver_phone` = VALUES(`caregiver_phone`),
    `caregiver_relationship` = VALUES(`caregiver_relationship`),
    `primary_clinician_user_id` = VALUES(`primary_clinician_user_id`),
    `primary_diagnosis` = VALUES(`primary_diagnosis`),
    `primary_icd10` = VALUES(`primary_icd10`),
    `payer_name` = VALUES(`payer_name`),
    `authorization_notes` = VALUES(`authorization_notes`),
    `cert_period_start` = VALUES(`cert_period_start`),
    `cert_period_end` = VALUES(`cert_period_end`),
    `created_datetime` = VALUES(`created_datetime`);

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
VALUES
    (9201, 25, 61, 1, 'SN', 2, DATE_ADD(NOW(), INTERVAL 2 HOUR), DATE_ADD(NOW(), INTERVAL 90 MINUTE), DATE_ADD(NOW(), INTERVAL 3 HOUR),
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
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `visit_type` = VALUES(`visit_type`),
    `clinician_user_id` = VALUES(`clinician_user_id`),
    `scheduled_datetime` = VALUES(`scheduled_datetime`),
    `window_start_datetime` = VALUES(`window_start_datetime`),
    `window_end_datetime` = VALUES(`window_end_datetime`),
    `route_sequence` = VALUES(`route_sequence`),
    `travel_notes` = VALUES(`travel_notes`),
    `actual_start_datetime` = VALUES(`actual_start_datetime`),
    `actual_end_datetime` = VALUES(`actual_end_datetime`),
    `status` = VALUES(`status`),
    `actual_lat` = VALUES(`actual_lat`),
    `actual_lng` = VALUES(`actual_lng`),
    `draft_data` = VALUES(`draft_data`),
    `is_draft` = VALUES(`is_draft`),
    `patient_signature_obtained` = VALUES(`patient_signature_obtained`),
    `patient_signature_datetime` = VALUES(`patient_signature_datetime`),
    `patient_signature_data` = VALUES(`patient_signature_data`),
    `visit_note` = VALUES(`visit_note`),
    `outcome_summary` = VALUES(`outcome_summary`),
    `mileage_miles` = VALUES(`mileage_miles`),
    `med_reconciliation_status` = VALUES(`med_reconciliation_status`),
    `med_reconciliation_summary` = VALUES(`med_reconciliation_summary`),
    `wound_summary` = VALUES(`wound_summary`),
    `procedure_summary` = VALUES(`procedure_summary`),
    `home_safety_summary` = VALUES(`home_safety_summary`),
    `care_coordination_needed` = VALUES(`care_coordination_needed`),
    `care_coordination_summary` = VALUES(`care_coordination_summary`),
    `followup_plan` = VALUES(`followup_plan`),
    `next_visit_due_date` = VALUES(`next_visit_due_date`),
    `next_visit_type` = VALUES(`next_visit_type`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `created_datetime` = VALUES(`created_datetime`),
    `updated_datetime` = VALUES(`updated_datetime`);

INSERT INTO `oei_episode_event`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `event_type`, `event_datetime`, `user_id`, `note`)
VALUES
    (9301, 24, 60, NULL, 1, 'REFERRAL_RECEIVED', DATE_SUB(NOW(), INTERVAL 2 DAY), 1, 'Referral entered from discharge planner — awaiting first scheduling contact.'),
    (9302, 25, 61, NULL, 1, 'REFERRAL_ACCEPTED', DATE_SUB(NOW(), INTERVAL 22 HOUR), 1, 'Urgent referral accepted — spouse confirmed availability today.'),
    (9303, 25, 61, NULL, 1, 'VISIT_SCHEDULED', DATE_SUB(NOW(), INTERVAL 30 MINUTE), 1, 'SN first visit scheduled with route sequence 1 and arrival window.'),
    (9304, 26, 62, NULL, 1, 'SOC_STARTED', DATE_SUB(NOW(), INTERVAL 12 DAY), 1, 'Start of care completed after post-acute referral.'),
    (9305, 26, 62, NULL, 1, 'VISIT_MISSED', DATE_SUB(NOW(), INTERVAL 4 DAY), 3, 'PT visit missed — patient at outside cardiology appointment.'),
    (9306, 26, 62, NULL, 1, 'VISIT_COMPLETE', DATE_SUB(NOW(), INTERVAL 24 HOUR), 3, 'Skilled nursing follow-up completed with med rec and wound care.'),
    (9307, 26, 62, NULL, 1, 'VISIT_SCHEDULED', NOW(), 1, 'Follow-up SN visit scheduled for tomorrow.')
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `eid` = VALUES(`eid`),
    `facility_id` = VALUES(`facility_id`),
    `event_type` = VALUES(`event_type`),
    `event_datetime` = VALUES(`event_datetime`),
    `user_id` = VALUES(`user_id`),
    `note` = VALUES(`note`);

-- -----------------------------------------------------------------------------
-- Supporting HBC capability data: vitals, fall risk, tasks, MAR, incidents,
-- eReferral, and discharge planning
-- -----------------------------------------------------------------------------

INSERT INTO `oei_triage`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `set_number`, `bp_systolic`, `bp_diastolic`, `hr`, `rr`, `temp_f`, `spo2`, `gcs`, `pain_score`, `weight_kg`, `arrival_mode`, `esi_suggested`, `notes`, `noted_by_user_id`, `noted_datetime`)
VALUES
    (9401, 26, 62, NULL, 1, 1, 134, 78, 86, 18, 98.4, 94, 15, 3, 68.40, 'WALKIN', NULL, 'Visit set — mild edema, breathing comfortable at rest.', 3, DATE_SUB(NOW(), INTERVAL 24 HOUR)),
    (9402, 26, 62, NULL, 1, 2, 128, 76, 78, 17, 98.2, 95, 15, 2, 67.90, 'WALKIN', NULL, 'Repeat set after wound care and med review.', 3, DATE_SUB(NOW(), INTERVAL 23 HOUR)),
    (9403, 25, 61, NULL, 1, 1, 142, 84, 92, 20, 98.7, 93, 15, 4, 81.20, 'WALKIN', NULL, 'Pre-visit intake summary from caregiver report.', 2, DATE_SUB(NOW(), INTERVAL 3 HOUR))
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `set_number` = VALUES(`set_number`),
    `bp_systolic` = VALUES(`bp_systolic`),
    `bp_diastolic` = VALUES(`bp_diastolic`),
    `hr` = VALUES(`hr`),
    `rr` = VALUES(`rr`),
    `temp_f` = VALUES(`temp_f`),
    `spo2` = VALUES(`spo2`),
    `gcs` = VALUES(`gcs`),
    `pain_score` = VALUES(`pain_score`),
    `weight_kg` = VALUES(`weight_kg`),
    `arrival_mode` = VALUES(`arrival_mode`),
    `notes` = VALUES(`notes`),
    `noted_by_user_id` = VALUES(`noted_by_user_id`),
    `noted_datetime` = VALUES(`noted_datetime`);

INSERT INTO `oei_fall_risk_assessment`
    (`id`, `episode_id`, `facility_id`, `assessed_by_user_id`, `assessed_datetime`,
     `mfs_fall_history`, `mfs_secondary_dx`, `mfs_ambulatory_aid`, `mfs_iv_heparin_lock`, `mfs_gait`, `mfs_mental_status`,
     `total_score`, `risk_level`, `notes`, `created_datetime`)
VALUES
    (9501, 25, 1, 2, DATE_SUB(NOW(), INTERVAL 2 HOUR), 25, 15, 15, 0, 10, 15, 80, 'HIGH', 'Walker-dependent, recent deconditioning, forgets limitations when fatigued.', NOW()),
    (9502, 26, 1, 3, DATE_SUB(NOW(), INTERVAL 35 DAY), 25, 15, 15, 0, 10, 15, 80, 'HIGH', 'Reassessment overdue — prior near-fall on bathroom transfer.', NOW())
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `facility_id` = VALUES(`facility_id`),
    `assessed_by_user_id` = VALUES(`assessed_by_user_id`),
    `assessed_datetime` = VALUES(`assessed_datetime`),
    `mfs_fall_history` = VALUES(`mfs_fall_history`),
    `mfs_secondary_dx` = VALUES(`mfs_secondary_dx`),
    `mfs_ambulatory_aid` = VALUES(`mfs_ambulatory_aid`),
    `mfs_iv_heparin_lock` = VALUES(`mfs_iv_heparin_lock`),
    `mfs_gait` = VALUES(`mfs_gait`),
    `mfs_mental_status` = VALUES(`mfs_mental_status`),
    `total_score` = VALUES(`total_score`),
    `risk_level` = VALUES(`risk_level`),
    `notes` = VALUES(`notes`),
    `created_datetime` = VALUES(`created_datetime`);

INSERT INTO `oei_task`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `task_type`, `due_datetime`, `completed_datetime`, `assigned_to_user_id`, `status`, `payload_json`, `created_by_user_id`, `created_datetime`)
VALUES
    (9601, 24, 60, NULL, 1, 'HBC_REFERRAL_REVIEW', DATE_ADD(NOW(), INTERVAL 2 HOUR), NULL, 2, 'OPEN', '{"task_label":"Review new HBC referral","detail":"Call daughter to confirm access instructions and preferred arrival window."}', 1, NOW()),
    (9602, 25, 61, NULL, 1, 'HBC_FIRST_VISIT_PREP', DATE_ADD(NOW(), INTERVAL 90 MINUTE), NULL, 2, 'OPEN', '{"task_label":"Prepare first visit","detail":"Bring med-reconciliation worksheet and walker safety checklist."}', 1, NOW()),
    (9603, 26, 62, NULL, 1, 'HBC_COORDINATION_REVIEW', DATE_ADD(NOW(), INTERVAL 4 HOUR), NULL, 1, 'OPEN', '{"visit_id":9202,"task_label":"Care coordination follow-up","detail":"Confirm cardiology office received updated medication list and weight log."}', 3, NOW()),
    (9604, 26, 62, NULL, 1, 'HBC_FOLLOW_UP_VISIT', DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 3 DAY), INTERVAL 9 HOUR), NULL, 3, 'OPEN', '{"visit_id":9202,"task_label":"Schedule follow-up visit","detail":"Repeat wound/CHF skilled nursing visit.","next_visit_type":"SN"}', 3, NOW()),
    (9605, 26, 62, NULL, 1, 'HBC_MED_REC_REVIEW', DATE_SUB(NOW(), INTERVAL 6 HOUR), DATE_SUB(NOW(), INTERVAL 1 HOUR), 1, 'DONE', '{"visit_id":9202,"task_label":"Medication reconciliation review","detail":"Duplicate discharge diuretic instruction resolved with cardiology office."}', 3, DATE_SUB(NOW(), INTERVAL 24 HOUR))
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `eid` = VALUES(`eid`),
    `facility_id` = VALUES(`facility_id`),
    `task_type` = VALUES(`task_type`),
    `due_datetime` = VALUES(`due_datetime`),
    `completed_datetime` = VALUES(`completed_datetime`),
    `assigned_to_user_id` = VALUES(`assigned_to_user_id`),
    `status` = VALUES(`status`),
    `payload_json` = VALUES(`payload_json`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `created_datetime` = VALUES(`created_datetime`);

INSERT INTO `oei_mar_order`
    (`id`, `episode_id`, `pid`, `facility_id`, `drug_name`, `dose`, `unit`, `route`, `frequency`, `is_prn`, `is_stat`, `is_high_alert`, `status`, `ordered_datetime`, `discontinued_datetime`, `ordered_by_user_id`, `discontinued_by_user_id`, `rx_id`, `instructions`, `created_datetime`, `updated_datetime`)
VALUES
    (9701, 26, 62, 1, 'furosemide', '40', 'mg', 'PO', 'DAILY', 0, 0, 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, 1, NULL, NULL, 'Hold and call provider for dizziness with SBP < 100 or sudden 2-lb/day weight gain.', DATE_SUB(NOW(), INTERVAL 12 DAY), NOW()),
    (9702, 26, 62, 1, 'potassium chloride', '20', 'mEq', 'PO', 'DAILY', 0, 0, 0, 'ACTIVE', DATE_SUB(NOW(), INTERVAL 12 DAY), NULL, 1, NULL, NULL, 'Take with food when furosemide administered.', DATE_SUB(NOW(), INTERVAL 12 DAY), NOW())
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `drug_name` = VALUES(`drug_name`),
    `dose` = VALUES(`dose`),
    `unit` = VALUES(`unit`),
    `route` = VALUES(`route`),
    `frequency` = VALUES(`frequency`),
    `is_prn` = VALUES(`is_prn`),
    `is_stat` = VALUES(`is_stat`),
    `is_high_alert` = VALUES(`is_high_alert`),
    `status` = VALUES(`status`),
    `ordered_datetime` = VALUES(`ordered_datetime`),
    `discontinued_datetime` = VALUES(`discontinued_datetime`),
    `ordered_by_user_id` = VALUES(`ordered_by_user_id`),
    `discontinued_by_user_id` = VALUES(`discontinued_by_user_id`),
    `rx_id` = VALUES(`rx_id`),
    `instructions` = VALUES(`instructions`),
    `created_datetime` = VALUES(`created_datetime`),
    `updated_datetime` = VALUES(`updated_datetime`);

INSERT INTO `oei_mar_administration`
    (`id`, `mar_order_id`, `episode_id`, `pid`, `facility_id`, `scheduled_datetime`, `administered_datetime`, `outcome`, `dose_given`, `unit_given`, `route_given`, `site`, `lot_number`, `hold_reason`, `administered_by_user_id`, `witness_user_id`, `waste_amount`, `waste_unit`, `co_sign_user_id`, `co_signed_datetime`, `note`, `is_high_alert`, `created_datetime`, `updated_datetime`)
VALUES
    (9801, 9701, 26, 62, 1, DATE_SUB(NOW(), INTERVAL 26 HOUR), DATE_SUB(NOW(), INTERVAL 25 HOUR), 'GIVEN', '40', 'mg', 'PO', NULL, NULL, NULL, 3, NULL, NULL, NULL, NULL, NULL, 'Daily dose given before wound care visit.', 0, DATE_SUB(NOW(), INTERVAL 26 HOUR), NOW()),
    (9802, 9702, 26, 62, 1, DATE_SUB(NOW(), INTERVAL 1 HOUR), NULL, 'PENDING', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending evening potassium dose.', 0, DATE_SUB(NOW(), INTERVAL 1 HOUR), NOW())
ON DUPLICATE KEY UPDATE
    `mar_order_id` = VALUES(`mar_order_id`),
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `facility_id` = VALUES(`facility_id`),
    `scheduled_datetime` = VALUES(`scheduled_datetime`),
    `administered_datetime` = VALUES(`administered_datetime`),
    `outcome` = VALUES(`outcome`),
    `dose_given` = VALUES(`dose_given`),
    `unit_given` = VALUES(`unit_given`),
    `route_given` = VALUES(`route_given`),
    `site` = VALUES(`site`),
    `lot_number` = VALUES(`lot_number`),
    `hold_reason` = VALUES(`hold_reason`),
    `administered_by_user_id` = VALUES(`administered_by_user_id`),
    `note` = VALUES(`note`),
    `is_high_alert` = VALUES(`is_high_alert`),
    `created_datetime` = VALUES(`created_datetime`),
    `updated_datetime` = VALUES(`updated_datetime`);

INSERT INTO `oei_incident`
    (`id`, `episode_id`, `facility_id`, `reported_by_user_id`, `incident_type`, `severity`, `incident_datetime`, `location_description`, `narrative`, `corrective_action`, `reported_state`, `mandatory_report_sent`, `created_datetime`)
VALUES
    (9901, 26, 1, 3, 'FALL_NEAR_MISS', 'LOW', DATE_SUB(NOW(), INTERVAL 3 DAY), 'Bathroom doorway', 'Patient lost balance during transfer but caregiver prevented fall; no injury sustained.', 'Reinforced walker placement, cleared doorway clutter, and requested repeat fall-risk reassessment.', 'NOT_REQUIRED', 0, DATE_SUB(NOW(), INTERVAL 3 DAY))
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `facility_id` = VALUES(`facility_id`),
    `reported_by_user_id` = VALUES(`reported_by_user_id`),
    `incident_type` = VALUES(`incident_type`),
    `severity` = VALUES(`severity`),
    `incident_datetime` = VALUES(`incident_datetime`),
    `location_description` = VALUES(`location_description`),
    `narrative` = VALUES(`narrative`),
    `corrective_action` = VALUES(`corrective_action`),
    `reported_state` = VALUES(`reported_state`),
    `mandatory_report_sent` = VALUES(`mandatory_report_sent`),
    `created_datetime` = VALUES(`created_datetime`);

INSERT INTO `oei_episode_disposition`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `disposition_code`, `destination`, `decision_datetime`, `depart_datetime`, `admit_flag`, `notes`, `updated_by_user_id`, `updated_datetime`)
VALUES
    (9951, 26, 62, NULL, 1, 'SERVICE_COMPLETED', 'Return to PCP / cardiology management after wound closure', DATE_ADD(NOW(), INTERVAL 10 DAY), NULL, 0, 'Draft closure plan — keep open until wound fully epithelialized and weight stable.', 1, NOW())
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `eid` = VALUES(`eid`),
    `facility_id` = VALUES(`facility_id`),
    `disposition_code` = VALUES(`disposition_code`),
    `destination` = VALUES(`destination`),
    `decision_datetime` = VALUES(`decision_datetime`),
    `depart_datetime` = VALUES(`depart_datetime`),
    `admit_flag` = VALUES(`admit_flag`),
    `notes` = VALUES(`notes`),
    `updated_by_user_id` = VALUES(`updated_by_user_id`),
    `updated_datetime` = VALUES(`updated_datetime`);

INSERT INTO `oei_ereferral`
    (`id`, `episode_id`, `pid`, `eid`, `facility_id`, `referral_type`, `status`, `priority`, `destination_directory_id`, `destination_name`, `destination_fax`, `destination_phone`, `destination_address`, `reason_for_referral`, `clinical_summary`, `services_requested`, `medications_summary`, `followup_instructions`, `sent_datetime`, `sent_by_user_id`, `send_method`, `response_datetime`, `response_by_name`, `response_notes`, `created_by_user_id`, `created_datetime`, `updated_datetime`)
VALUES
    (9961, 26, 62, NULL, 1, 'DISCHARGE', 'SENT', 'URGENT', 18, 'Mountain Cardiology Group', '(555) 730-8801', '(555) 730-8800', '505 Heart Lane, Riverside, CA 92506',
     'Cardiology review after recurrent weight fluctuations during home-based CHF management.',
     'Active HBC patient with improving edema and healing lower-leg wound. Medication reconciliation completed and updated med list attached by fax.',
     'Medication review, outpatient CHF follow-up, weight-gain call thresholds confirmation.',
     'Furosemide 40 mg daily; potassium chloride 20 mEq daily; caregiver keeping daily weight log.',
     'Respond to HBC team within 48 hours with any medication changes or new fluid-management parameters.',
     DATE_SUB(NOW(), INTERVAL 20 HOUR), 1, 'FAX', NULL, NULL, NULL, 1, DATE_SUB(NOW(), INTERVAL 20 HOUR), NOW())
ON DUPLICATE KEY UPDATE
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `eid` = VALUES(`eid`),
    `facility_id` = VALUES(`facility_id`),
    `referral_type` = VALUES(`referral_type`),
    `status` = VALUES(`status`),
    `priority` = VALUES(`priority`),
    `destination_directory_id` = VALUES(`destination_directory_id`),
    `destination_name` = VALUES(`destination_name`),
    `destination_fax` = VALUES(`destination_fax`),
    `destination_phone` = VALUES(`destination_phone`),
    `destination_address` = VALUES(`destination_address`),
    `reason_for_referral` = VALUES(`reason_for_referral`),
    `clinical_summary` = VALUES(`clinical_summary`),
    `services_requested` = VALUES(`services_requested`),
    `medications_summary` = VALUES(`medications_summary`),
    `followup_instructions` = VALUES(`followup_instructions`),
    `sent_datetime` = VALUES(`sent_datetime`),
    `sent_by_user_id` = VALUES(`sent_by_user_id`),
    `send_method` = VALUES(`send_method`),
    `response_datetime` = VALUES(`response_datetime`),
    `response_by_name` = VALUES(`response_by_name`),
    `response_notes` = VALUES(`response_notes`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `created_datetime` = VALUES(`created_datetime`),
    `updated_datetime` = VALUES(`updated_datetime`);

SET FOREIGN_KEY_CHECKS = 1;

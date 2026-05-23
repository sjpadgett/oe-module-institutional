-- ============================================================================
-- institutional-reset.sql
--
-- Part of the oe-module-institutional module.
--
-- Drops ALL institutional (oei_*) tables so the module can be cleanly
-- reinstalled. Intended workflow:
--
--   1. Run this script against your OpenEMR database.
--   2. In OpenEMR: Modules > Manage Modules > Institutional > Unregister.
--   3. Re-register / enable the module — Module Manager applies table.sql,
--      recreating every oei_* table fresh.
--
-- WARNING: This permanently deletes ALL institutional data (episodes, MAR,
-- observations, billing, care plans overlay data, settings, etc.). It does
-- NOT touch OpenEMR core tables. Back up first if the data matters.
--
-- NOTE: Care plan / clinical note rows the module wrote into OpenEMR core
-- tables (forms, form_care_plan, form_clinical_notes, form_encounter) are
-- NOT removed by this script — they live in OpenEMR's own tables. See the
-- optional section at the bottom if you also want to clear demo-seed rows.
--
-- @package   Institutional
-- @link      https://www.opensourcedemr.com
-- @author    Jerry Padgett <sjpadgett@gmail.com>
-- @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
-- @license   GNU General Public License 3
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `oei_activity_log`;
DROP TABLE IF EXISTS `oei_adl_record`;
DROP TABLE IF EXISTS `oei_al_episode`;
DROP TABLE IF EXISTS `oei_alert_ack`;
DROP TABLE IF EXISTS `oei_bh_boarding`;
DROP TABLE IF EXISTS `oei_bh_safety`;
DROP TABLE IF EXISTS `oei_billing_line`;
DROP TABLE IF EXISTS `oei_diversion`;
DROP TABLE IF EXISTS `oei_diversion_history`;
DROP TABLE IF EXISTS `oei_downtime_sync_queue`;
DROP TABLE IF EXISTS `oei_episode`;
DROP TABLE IF EXISTS `oei_episode_disposition`;
DROP TABLE IF EXISTS `oei_episode_document`;
DROP TABLE IF EXISTS `oei_episode_event`;
DROP TABLE IF EXISTS `oei_episode_location`;
DROP TABLE IF EXISTS `oei_episode_status_history`;
DROP TABLE IF EXISTS `oei_ereferral`;
DROP TABLE IF EXISTS `oei_facility_directory`;
DROP TABLE IF EXISTS `oei_facility_profile`;
DROP TABLE IF EXISTS `oei_fall_risk_assessment`;
DROP TABLE IF EXISTS `oei_hbc_comm_log`;
DROP TABLE IF EXISTS `oei_hbc_episode`;
DROP TABLE IF EXISTS `oei_hbc_visit`;
DROP TABLE IF EXISTS `oei_hl7_outbound_log`;
DROP TABLE IF EXISTS `oei_incident`;
DROP TABLE IF EXISTS `oei_ip_episode`;
DROP TABLE IF EXISTS `oei_location`;
DROP TABLE IF EXISTS `oei_mar_administration`;
DROP TABLE IF EXISTS `oei_mar_order`;
DROP TABLE IF EXISTS `oei_obs_plan`;
DROP TABLE IF EXISTS `oei_obs_type`;
DROP TABLE IF EXISTS `oei_observation`;
DROP TABLE IF EXISTS `oei_patient_location_history`;
DROP TABLE IF EXISTS `oei_protocol`;
DROP TABLE IF EXISTS `oei_schema_version`;
DROP TABLE IF EXISTS `oei_settings`;
DROP TABLE IF EXISTS `oei_task`;
DROP TABLE IF EXISTS `oei_transfer`;
DROP TABLE IF EXISTS `oei_triage`;
DROP TABLE IF EXISTS `oei_user_context`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- OPTIONAL — remove demo-seed rows from OpenEMR core tables.
-- Only run this on a demo/training instance. The stable demo seed uses
-- patient pids 50-54 and 60-62 and encounter numbers 1000050-1000062.
-- Uncomment to clear them. DO NOT run on production patient data.
-- ----------------------------------------------------------------------------
-- DELETE FROM forms               WHERE encounter IN (1000050,1000051,1000052,1000053,1000054,1000060,1000061,1000062);
-- DELETE FROM form_care_plan      WHERE encounter IN (1000050,1000051,1000052,1000053,1000054,1000060,1000061,1000062);
-- DELETE FROM form_clinical_notes WHERE encounter IN (1000060,1000061,1000062);
-- DELETE FROM form_encounter      WHERE encounter IN (1000050,1000051,1000052,1000053,1000054,1000060,1000061,1000062);
-- DELETE FROM patient_data        WHERE pid IN (50,51,52,53,54,60,61,62);
-- ============================================================================

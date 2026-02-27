-- DEV ONLY: Reset Institutional module tables
-- DROP order: children first to avoid FK issues if added later
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS
    `oei_alert_ack`,
    `oei_bh_boarding`,
    `oei_bh_safety`,
    `oei_episode`,
    `oei_episode_disposition`,
    `oei_episode_document`,
    `oei_episode_event`,
    `oei_episode_location`,
    `oei_episode_status_history`,
    `oei_ereferral`,
    `oei_facility_directory`,
    `oei_hl7_outbound_log`,
    `oei_location`,
    `oei_mar_administration`,
    `oei_mar_order`,
    `oei_obs_plan`,
    `oei_patient_location_history`,
    `oei_protocol`,
    `oei_settings`,
    `oei_task`,
    `oei_transfer`,
    `oei_triage`;
SET FOREIGN_KEY_CHECKS = 1;



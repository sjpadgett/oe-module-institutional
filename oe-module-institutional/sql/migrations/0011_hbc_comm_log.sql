-- 0011_hbc_comm_log.sql
-- Communication Log for Home-Based Care episodes.
-- Tracks calls/messages to PCP, pharmacy, family, DME, payer, etc.
-- Idempotent.

CREATE TABLE IF NOT EXISTS `oei_hbc_comm_log` (
    `id`                bigint(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
    `episode_id`        bigint(20) UNSIGNED  NOT NULL  COMMENT 'FK → oei_episode.id',
    `pid`               bigint(20) UNSIGNED  NOT NULL  COMMENT 'FK → patient_data.pid',
    `facility_id`       bigint(20) UNSIGNED  NOT NULL,

    `comm_type`         ENUM(
                            'PHONE_OUT',
                            'PHONE_IN',
                            'FAX',
                            'SECURE_MSG',
                            'IN_PERSON',
                            'OTHER'
                        )                    NOT NULL DEFAULT 'PHONE_OUT',
    `contact_role`      ENUM(
                            'PCP',
                            'SPECIALIST',
                            'PHARMACY',
                            'FAMILY',
                            'CAREGIVER',
                            'DME_SUPPLIER',
                            'PAYER',
                            'HOME_HEALTH_AGENCY',
                            'HOSPICE',
                            'SOCIAL_SERVICES',
                            'OTHER'
                        )                    NOT NULL DEFAULT 'OTHER',
    `contact_name`      varchar(120)                  DEFAULT NULL,
    `contact_phone`     varchar(40)                   DEFAULT NULL,
    `subject`           varchar(255)                  DEFAULT NULL,
    `summary`           text                          DEFAULT NULL,
    `outcome`           varchar(255)                  DEFAULT NULL
                            COMMENT 'Result: left voicemail, confirmed, order placed, etc.',
    `followup_needed`   tinyint(1)           NOT NULL DEFAULT 0,
    `followup_note`     varchar(255)                  DEFAULT NULL,
    `comm_datetime`     datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP
                            COMMENT 'When the communication occurred',
    `user_id`           bigint(20) UNSIGNED           DEFAULT NULL  COMMENT 'FK → users.id — who logged it',
    `created_datetime`  datetime             NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_hcl_episode`   (`episode_id`),
    KEY `idx_hcl_pid`       (`pid`),
    KEY `idx_hcl_facility`  (`facility_id`),
    KEY `idx_hcl_datetime`  (`comm_datetime`),
    KEY `idx_hcl_followup`  (`episode_id`, `followup_needed`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Home-Based Care communication log — calls, faxes, messages';

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`) VALUES ('0011', NOW());

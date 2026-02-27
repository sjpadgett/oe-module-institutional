-- ============================================================
-- oei_user_context  —  per-user care context preference
-- Created by oe-module-institutional v0.9.7
--
-- One row per (user_id, facility_id).
-- context_key: ED_ACUTE | OBS_STAY | BH | OPERATIONS | FULL
-- ============================================================

CREATE TABLE IF NOT EXISTS `oei_user_context`
(
    `id`               bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          bigint(20) UNSIGNED NOT NULL,
    `facility_id`      bigint(20) UNSIGNED NOT NULL,
    `context_key`      varchar(30)         NOT NULL DEFAULT 'ED_ACUTE',
    `updated_datetime` datetime            NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_oei_ctx_user_fac` (`user_id`, `facility_id`),
    KEY `idx_oei_ctx_facility` (`facility_id`, `context_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci
  COMMENT = 'Per-user care context preference per facility';



-- MAR completeness improvements
-- Run once; IF NOT EXISTS guards make it safe to re-run.

ALTER TABLE `oei_mar_administration`
    ADD COLUMN IF NOT EXISTS `hold_reason` varchar(60) DEFAULT NULL
        COMMENT 'Structured HELD reason code — see MarService::HOLD_REASONS'
        AFTER `lot_number`;

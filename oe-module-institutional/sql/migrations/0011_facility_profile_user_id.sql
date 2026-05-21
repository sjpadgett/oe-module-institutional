-- ============================================================================
-- 0011_facility_profile_user_id.sql
--
-- Adds user_id to oei_facility_profile so the same facility can have:
--   user_id = 0  → facility-wide default (set by admin via Setup Wizard)
--   user_id > 0  → per-user profile override (set by the user themselves)
--
-- Resolution chain in FacilityProfileRepository::get():
--   1. Look for (facility_id, user_id = me)   — personal override
--   2. Fall back to (facility_id, user_id = 0) — facility default
--
-- NOTE: ADD COLUMN ... DEFAULT 0 already sets all existing rows to user_id=0
-- automatically. The UPDATE statement from an earlier version of this file
-- was removed because it caused a duplicate-key error when the wizard had
-- already inserted user-specific rows between two migration attempts.
-- ============================================================================

-- Step 1: add user_id column with DEFAULT 0
-- Existing rows receive user_id=0 from the DEFAULT — no UPDATE needed.
ALTER TABLE `oei_facility_profile`
    ADD COLUMN IF NOT EXISTS `user_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0
    AFTER `facility_id`;

-- Step 2: change PK from (facility_id) to (facility_id, user_id)
-- Safe to re-run: if already composite it simply re-creates it cleanly.
ALTER TABLE `oei_facility_profile`
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (`facility_id`, `user_id`);

-- Step 3: index for fast per-user queries
ALTER TABLE `oei_facility_profile`
    ADD KEY IF NOT EXISTS `idx_oei_fp_user` (`user_id`);

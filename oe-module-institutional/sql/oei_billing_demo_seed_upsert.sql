-- =============================================================================
-- oe-module-institutional — Billing Demo Seed (UPSERT STYLE)
-- Rerunnable demo data for oei_billing_line
-- Assumes billing migrations 0012, 0013, 0014 are already applied.
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO `oei_billing_line`
(`id`, `facility_id`, `episode_id`, `pid`, `eid`, `context_key`, `episode_type`,
 `billing_path`, `line_category`, `status`, `review_reason`,
 `service_date`, `charge_code`, `description`,
 `quantity`, `unit_price`, `total_amount`,
 `external_ref`, `source_label`, `notes`,
 `release_target`, `release_batch_key`,
 `created_by_user_id`, `updated_by_user_id`, `released_by_user_id`,
 `created_datetime`, `updated_datetime`, `released_datetime`)
VALUES

-- ---------------------------------------------------------------------------
-- INPATIENT / OBS / ED — claims-first examples
-- ---------------------------------------------------------------------------

(9001, 1, 19, 2, NULL, 'INPATIENT_STAY', 'IP',
 'CLAIM_MANAGER', 'CLAIM_STAGING', 'READY', 'Ventilator + ICU day review',
 '2026-02-27', 'IP-DRG-REV', 'ICU daily institutional claim staging',
 1.00, 0.00, 0.00,
 'IP-19-20260227', 'IP stay', 'Ready for UB04 / Billing Manager handoff',
 'UB04', NULL,
 1, 1, NULL,
 '2026-03-01 08:00:00', '2026-03-01 08:00:00', NULL),

(9002, 1, 19, 2, NULL, 'INPATIENT_STAY', 'IP',
 'MODULE_LEDGER', 'SUPPLY', 'READY', NULL,
 '2026-02-27', 'SUP-IVPUMP', 'High-acuity supply charge — IV pump disposables',
 2.00, 48.50, 97.00,
 'IP-19-SUPPLY', 'Supply', 'Non-claim support charge kept in module ledger',
 'LEDGER', NULL,
 1, 1, NULL,
 '2026-03-01 08:05:00', '2026-03-01 08:05:00', NULL),

(9003, 1, 20, 5, NULL, 'INPATIENT_STAY', 'IP',
 'CLAIM_MANAGER', 'CLAIM_STAGING', 'HOLD', 'Missing final discharge/coding review',
 '2026-02-28', 'IP-MEDSURG', 'Med-surg inpatient institutional claim staging',
 1.00, 0.00, 0.00,
 'IP-20-20260228', 'IP stay', 'Hold until final attending discharge summary is signed',
 'BILLING_MANAGER', NULL,
 1, 1, NULL,
 '2026-03-01 08:10:00', '2026-03-01 08:10:00', NULL),

(9004, 1, 2, 3, NULL, 'OBSERVATION_STAY', 'OBS',
 'CLAIM_MANAGER', 'CLAIM_STAGING', 'RELEASED', NULL,
 '2026-02-28', 'OBS-CHEST', 'Observation chest-pain claim staging released',
 1.00, 0.00, 0.00,
 'OBS-2-20260228', 'OBS stay', 'Released to institutional claim workflow',
 'UB04', 'UB4-20260301-0001',
 1, 1, 1,
 '2026-03-01 08:15:00', '2026-03-01 08:30:00', '2026-03-01 08:30:00'),

(9005, 1, 3, 4, NULL, 'ED_BH_BOARDING', 'ED',
 'MODULE_LEDGER', 'ADJUSTMENT', 'HOLD', 'Await behavioral health placement payer review',
 '2026-02-28', 'BH-HOLD', 'BH boarding administrative hold / coverage review',
 1.00, 250.00, 250.00,
 'BH-3-HOLD', 'BH boarding', 'Keep in ledger until payer / placement review is completed',
 'LEDGER', NULL,
 1, 1, NULL,
 '2026-03-01 08:20:00', '2026-03-01 08:20:00', NULL),

-- ---------------------------------------------------------------------------
-- HOME-BASED CARE — professional review + ledger hybrid
-- ---------------------------------------------------------------------------

(9006, 1, 24, 60, NULL, 'HOME_BASED_CARE', 'HBC',
 'PROFESSIONAL_REVIEW', 'CLAIM_STAGING', 'READY', 'Initial home-based visit ready for professional review',
 '2026-03-29', 'HBC-INIT', 'Initial post-discharge home-based visit claim staging',
 1.00, 0.00, 0.00,
 'HBC-24-INIT', 'HBC visit', 'Ready for professional visit review',
 'PROFESSIONAL', NULL,
 1, 1, NULL,
 '2026-03-30 09:00:00', '2026-03-30 09:00:00', NULL),

(9007, 1, 24, 60, NULL, 'HOME_BASED_CARE', 'HBC',
 'MODULE_LEDGER', 'SERVICE', 'READY', NULL,
 '2026-03-29', 'HBC-COORD', 'Care coordination and discharge reconciliation support',
 1.00, 85.00, 85.00,
 'HBC-24-COORD', 'Care coordination', 'Ledger-only operational service charge',
 'LEDGER', NULL,
 1, 1, NULL,
 '2026-03-30 09:05:00', '2026-03-30 09:05:00', NULL),

(9008, 1, 25, 61, NULL, 'HOME_BASED_CARE', 'HBC',
 'PROFESSIONAL_REVIEW', 'CLAIM_STAGING', 'HOLD', 'Urgent first-visit documentation incomplete',
 '2026-03-30', 'HBC-URGENT', 'Urgent first-visit professional review staging',
 1.00, 0.00, 0.00,
 'HBC-25-URGENT', 'HBC visit', 'Hold until mobile note and medication list are finalized',
 'PROFESSIONAL', NULL,
 1, 1, NULL,
 '2026-03-30 09:10:00', '2026-03-30 09:10:00', NULL),

(9009, 1, 26, 62, NULL, 'HOME_BASED_CARE', 'HBC',
 'MODULE_LEDGER', 'SUPPLY', 'READY', NULL,
 '2026-03-30', 'HBC-WOUND', 'Wound care supplies replenishment',
 1.00, 42.00, 42.00,
 'HBC-26-SUPPLY', 'Wound supplies', 'Supplies kept in module ledger',
 'LEDGER', NULL,
 1, 1, NULL,
 '2026-03-30 09:15:00', '2026-03-30 09:15:00', NULL),

(9010, 1, 26, 62, NULL, 'HOME_BASED_CARE', 'HBC',
 'PROFESSIONAL_REVIEW', 'CLAIM_STAGING', 'RELEASED', NULL,
 '2026-03-28', 'HBC-SN-FU', 'Skilled nursing follow-up visit released',
 1.00, 0.00, 0.00,
 'HBC-26-FU', 'HBC visit', 'Released for professional billing review batch',
 'PROFESSIONAL', 'PRO-20260330-0001',
 1, 1, 1,
 '2026-03-30 09:20:00', '2026-03-30 09:45:00', '2026-03-30 09:45:00'),

-- ---------------------------------------------------------------------------
-- ASSISTED LIVING — ledger-first examples
-- ---------------------------------------------------------------------------

(9011, 1, 17, 53, NULL, 'ASSISTED_LIVING', 'AL',
 'MODULE_LEDGER', 'RECURRING', 'READY', NULL,
 '2026-03-01', 'AL-MONTH', 'Monthly assisted living accommodation and support bundle',
 1.00, 3250.00, 3250.00,
 'AL-17-MAR', 'AL monthly bundle', 'Recurring room / board / support bundle',
 'STATEMENT', NULL,
 1, 1, NULL,
 '2026-03-01 07:00:00', '2026-03-01 07:00:00', NULL),

(9012, 1, 17, 53, NULL, 'ASSISTED_LIVING', 'AL',
 'MODULE_LEDGER', 'SERVICE', 'READY', NULL,
 '2026-03-01', 'AL-MEDPASS', 'Medication administration service',
 30.00, 4.00, 120.00,
 'AL-17-MEDPASS', 'AL service', 'Monthly med-pass service total',
 'STATEMENT', NULL,
 1, 1, NULL,
 '2026-03-01 07:05:00', '2026-03-01 07:05:00', NULL),

(9013, 1, 18, 54, NULL, 'ASSISTED_LIVING', 'AL',
 'MODULE_LEDGER', 'SUPPLY', 'HOLD', 'Pending family/private-pay review',
 '2026-03-01', 'AL-SUPPLY', 'Diabetic testing supplies',
 1.00, 36.00, 36.00,
 'AL-18-DM-SUP', 'AL supplies', 'Hold until resident account review is completed',
 'STATEMENT', NULL,
 1, 1, NULL,
 '2026-03-01 07:10:00', '2026-03-01 07:10:00', NULL),

(9014, 1, 18, 54, NULL, 'ASSISTED_LIVING', 'AL',
 'MODULE_LEDGER', 'ADJUSTMENT', 'RELEASED', NULL,
 '2026-02-28', 'AL-CREDIT', 'Resident statement credit adjustment',
 1.00, -25.00, -25.00,
 'AL-18-CREDIT', 'Adjustment', 'Released to resident statement batch',
 'STATEMENT', 'STM-20260301-0001',
 1, 1, 1,
 '2026-03-01 07:15:00', '2026-03-01 07:30:00', '2026-03-01 07:30:00'),

-- ---------------------------------------------------------------------------
-- Extra mixed examples so dashboard sections are populated
-- ---------------------------------------------------------------------------

(9015, 1, 19, 2, NULL, 'INPATIENT_STAY', 'IP',
 'CLAIM_MANAGER', 'CLAIM_STAGING', 'READY', NULL,
 '2026-02-28', 'IP-DISCH', 'Discharge-day institutional claim staging',
 1.00, 0.00, 0.00,
 'IP-19-DISCH', 'IP stay', 'Claim candidate ready',
 'BILLING_MANAGER', NULL,
 1, 1, NULL,
 '2026-03-01 08:40:00', '2026-03-01 08:40:00', NULL),

(9016, 1, 24, 60, NULL, 'HOME_BASED_CARE', 'HBC',
 'MODULE_LEDGER', 'PRIVATE_PAY', 'READY', NULL,
 '2026-03-30', 'HBC-TRAVEL', 'Extended travel / rural mileage support',
 1.00, 30.00, 30.00,
 'HBC-24-TRAVEL', 'Travel', 'Private-pay style line retained in ledger',
 'LEDGER', NULL,
 1, 1, NULL,
 '2026-03-30 09:25:00', '2026-03-30 09:25:00', NULL),

(9017, 1, 2, 3, NULL, 'OBSERVATION_STAY', 'OBS',
 'CLAIM_MANAGER', 'CLAIM_STAGING', 'HOLD', 'Observation coding validation needed',
 '2026-02-28', 'OBS-HOLD', 'Observation claim candidate awaiting coding validation',
 1.00, 0.00, 0.00,
 'OBS-2-HOLD', 'OBS stay', 'Hold for coding validation',
 'UB04', NULL,
 1, 1, NULL,
 '2026-03-01 08:50:00', '2026-03-01 08:50:00', NULL),

(9018, 1, 17, 53, NULL, 'ASSISTED_LIVING', 'AL',
 'MODULE_LEDGER', 'SERVICE', 'READY', NULL,
 '2026-03-02', 'AL-ACTIVITY', 'Enhanced activity participation package',
 1.00, 55.00, 55.00,
 'AL-17-ACT', 'AL service', 'Optional private-pay activity package',
 'STATEMENT', NULL,
 1, 1, NULL,
 '2026-03-02 07:00:00', '2026-03-02 07:00:00', NULL)

ON DUPLICATE KEY UPDATE
    `facility_id` = VALUES(`facility_id`),
    `episode_id` = VALUES(`episode_id`),
    `pid` = VALUES(`pid`),
    `eid` = VALUES(`eid`),
    `context_key` = VALUES(`context_key`),
    `episode_type` = VALUES(`episode_type`),
    `billing_path` = VALUES(`billing_path`),
    `line_category` = VALUES(`line_category`),
    `status` = VALUES(`status`),
    `review_reason` = VALUES(`review_reason`),
    `service_date` = VALUES(`service_date`),
    `charge_code` = VALUES(`charge_code`),
    `description` = VALUES(`description`),
    `quantity` = VALUES(`quantity`),
    `unit_price` = VALUES(`unit_price`),
    `total_amount` = VALUES(`total_amount`),
    `external_ref` = VALUES(`external_ref`),
    `source_label` = VALUES(`source_label`),
    `notes` = VALUES(`notes`),
    `release_target` = VALUES(`release_target`),
    `release_batch_key` = VALUES(`release_batch_key`),
    `created_by_user_id` = VALUES(`created_by_user_id`),
    `updated_by_user_id` = VALUES(`updated_by_user_id`),
    `released_by_user_id` = VALUES(`released_by_user_id`),
    `created_datetime` = VALUES(`created_datetime`),
    `updated_datetime` = VALUES(`updated_datetime`),
    `released_datetime` = VALUES(`released_datetime`);

SET FOREIGN_KEY_CHECKS = 1;

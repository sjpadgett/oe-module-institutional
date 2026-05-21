# 🏡 Home-Based Care Module — Now Available (v0.40.0)

**oe-module-institutional** now includes a complete **Home-Based Care (HBC)** submodule for managing home health, house-call, and community nursing workflows inside OpenEMR.

If your organization delivers care in patients' homes — skilled nursing visits, physical therapy, home health aide services, physician house calls — this module manages the entire lifecycle from referral through discharge, with mobile-first field documentation and offline support.

---

## What It Does

**Referral → Visit → Documentation → Discharge** — a complete end-to-end workflow built for home care operations.

**Referral & Intake** — Accept referrals from hospitals, PCPs, family, or agencies. Capture service address, caregiver contacts, diagnosis, payer authorization, and certification period. The system prevents duplicate active episodes and creates proper OpenEMR encounters automatically.

**Visit Board** — The daily cockpit for field clinicians and supervisors. Shows today's schedule sorted by route sequence, a priority action queue that surfaces patients needing urgent attention (overdue follow-ups, medication issues, cert period expiring, supervisory visits due), and a referral queue for new patients awaiting triage.

**Scheduling** — Single visit or batch/recurring scheduling. Need 3 skilled nursing visits per week for 4 weeks? Select the days, set the number of weeks, and the system creates all 12 visits in one submission. Assign clinicians with day-load visibility, set arrival windows, route sequences, and travel notes. Edit or reassign any scheduled visit without canceling.

**Mobile Visit Workspace** — Designed for phones and tablets in the field. 13 structured documentation sections: clinical narrative, outcome summary, medication reconciliation, wound care, procedures, home safety assessment, care coordination, follow-up planning, inline vitals capture, and patient signature. Autosaves every 30 seconds with localStorage backup.

**Offline Support** — Full offline capability via service worker and IndexedDB. If you lose connectivity during a home visit, drafts and even finalizations queue locally and sync automatically when you're back online.

**Inline Vitals** — Record BP, HR, SpO₂, respiratory rate, temperature, weight, and pain score directly in the visit workspace. Written to the clinical vitals table on finalize — no separate vitals page needed during a visit.

**Certification Period Compliance** — Track payer-authorized service windows with start/end dates and authorized visits per week. The profile shows a compliance progress bar, and expiry alerts surface at 14 days (yellow) and 7 days (red). Expired certs push patients to the top of the action queue.

**Supervisory Visit Tracking** — HHA patients require RN supervisory oversight every 14 days. Flag any visit as supervisory when scheduling, and the system tracks compliance across the handoff report and action queue.

**Communication Log** — Document all external contacts: calls to/from PCP, pharmacy, family, DME suppliers, payers, hospice, social services. Facility-wide view from the top menu, episode-specific view from the patient nav. Filter by contact role or follow-up status.

**Clinician Handoff Report** — One row per active patient with vitals, next visit, MAR status, fall risk, cert period, care plan goals, and clinical flags. Color-coded row highlighting for patients with multiple alerts. Print-optimized for paper handoffs.

**Episode Edit** — Update service address, caregiver info, clinician assignment, diagnosis, payer, cert period, and urgency at any point after intake. All changes are audit-logged.

**Patient Profile** — Central hub showing service snapshot, clinical attention scoring, next visit, latest vitals, open tasks, care plan progress, visit history with duration/mileage, observations, and links to all clinical workflows.

**Shared Workflows** — Full integration with the module's existing shared infrastructure: Care Plan, Clinical Notes, Care Team, eReferral, Documents, Fall Risk Assessment, Incident Reporting, MAR, Tasks, and Observations.

---

## Technical Details

- **OpenEMR 7.0+**, PHP 8.1+
- **Bootstrap 5.3** served locally via Composer (CDN fallback available)
- **Dark/light theme** support with normalized CSS variables across all pages
- **5 HBC migrations** (0008–0012) — all idempotent, safe to re-run
- **Manifest-driven** feature flags — enable/disable any feature per facility
- **Demo seed data** included — 6 HBC episodes across 20 tables for evaluation
- **No external dependencies** beyond what OpenEMR already provides
- **PSR-4 autoloading** with controller/repository/service pattern throughout

---

## Pages Included

| Page | Purpose |
|------|---------|
| Visit Board | Daily schedule, action queue, referral queue, KPIs |
| New Referral | Patient intake with address, caregiver, payer, cert period |
| Schedule Visit | Single or batch/recurring, clinician day load, route planning |
| Visit Workspace | Mobile field documentation with offline + signature |
| Patient Profile | Central hub — snapshot, attention, vitals, tasks, care plan |
| Edit Episode | Update address, clinician, caregiver, cert period, diagnosis |
| Communication Log | External contact tracking with follow-up flags |
| Handoff Report | Facility-wide clinician handoff with print view |
| Vitals | Dedicated vitals recording with history table |
| Fall Risk | Assessment and reassessment tracking |
| Incidents | Home safety, falls, med errors with mandatory report flags |
| Discharge | Episode closure with 7 disposition types |

---

## Getting Started

A **Workflow Guide** (Word document) is available for download that walks through every step from receiving a referral to closing an episode, including a printable visit-day checklist for field clinicians.

📄 **[Download: HBC Workflow Guide](LINK_HERE)**

The guide covers all roles (intake coordinator, supervisor, field clinician, administrator) and includes the batch scheduling workflow, offline documentation procedures, and the clinician handoff process.

---

## Installation

The HBC submodule is part of **oe-module-institutional**. If you're already running the module, update to v0.40.0 and run the new migrations. If you're new to the module:

1. Install to `interface/modules/custom_modules/oe-module-institutional/`
2. Run `composer install` from the module root
3. Execute migrations 0008–0012
4. Enable HBC features in the Manifest Editor
5. Select the "Home-Based Care" context from the context switcher

Feedback, bug reports, and contributions welcome. This has been built and tested on XAMPP with OpenEMR 7.0.2.

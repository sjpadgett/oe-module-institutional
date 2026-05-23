# oe-module-institutional — Level 2 Implementation Guide

Version: 0.17.0 | Platform: OpenEMR 7.0+ on XAMPP or standard Linux LAMP

---

## What is a Level 2 Implementer?

A Level 2 implementer configures, onboards, and supports facilities using this module
without modifying PHP code. All tasks on this page are achievable through the module UI,
manifest flags, and the Settings page.

**Level 1** — Facility super-user (DON, office manager). Handles day-to-day questions
from staff, adds rooms and users, resets care contexts.

**Level 2** — Certified implementer. Configures the module for a new facility, validates
the install, trains Level 1 users, handles configuration changes, and escalates genuine
bugs to Level 3.

**Level 3** — Developer. Schema changes (`table.sql`), PHP code changes, new features.

---

## Step 1 — Choose a Facility Profile

Open **Admin → Manifest Editor** after installation. Click the profile that matches the
facility type, review the pre-selected feature flags, then click Save.

| Profile | Use for |
|---------|---------|
| **AL Only** | Standalone assisted living, memory care, or SNF with no acute ED |
| **ED + OBS + BH** | Hospital emergency department with observation and behavioral health |
| **Inpatient** | Hospital floor (med/surg, telemetry, ICU, ortho) with ED intake routing |
| **AL + Inpatient** | CCRC or continuing-care campus with both AL and acute beds |

Individual flags can be toggled after applying a profile. Changes take effect on the
next page load — no server restart needed.

---

## Step 2 — Configure Facility Settings

Open **Admin → Settings**. Complete in order:

### Facility Identity
Set the **Facility Display Name**. This appears in the multi-facility dashboard and all
page headers. Leave blank to fall back to the OpenEMR facility table name.

### Clinical Thresholds (ED / OBS)
- **Door-to-Room Target** — default 30 min. Adjust to your facility's STEMI/stroke goals.
- **Door-to-Provider Target** — default 60 min.
- **LWBS Alert Threshold** — default 120 min. Alert fires when a patient has waited
  longer than this without being roomed.
- **OBS Runway Warning** — default 6 h. Board highlights OBS patients within this many
  hours of their protocol deadline.
- **BH Boarding Alert** — default 4 h. Alert fires for BH patients boarding longer than
  this without a placement.

### Inpatient Clinical Defaults
These only appear when the **IP Board** feature is enabled.

- **Discharge Target Hour** — The hour of day (0–23) your facility aims to complete
  discharges. Default 11 (= 11:00 AM). A countdown badge on the Discharge Planning page
  turns amber one LOS-warning-window before this time and red within the final hour.
- **LOS Warning Window** — How many hours before the expected LOS the Floor Board badge
  turns amber. Default 24 h.
- **Service-Line LOS Defaults** — Used on the Floor Board when a patient's individual
  expected LOS was not set at admission. Set these to your case-management targets for
  Med/Surg, Telemetry, ICU, and Ortho.

### AL Vitals Alert Thresholds
These only appear when the **AL Board** feature is enabled. Default values match common
clinical guidelines, but frail elderly residents frequently have acceptable baselines
outside acute-care norms. Adjust in consultation with the medical director.

- **BP Systolic High/Low** — default 160 / 90 mmHg
- **Heart Rate High/Low** — default 110 / 50 bpm
- **SpO₂ Critical / Warning** — default 93% / 96%
- **Weight Gain Alert** — default 0.9 kg. Alert fires when a single vitals entry shows
  this much weight gain since the previous reading (CHF fluid retention indicator).

### HL7 ADT Outbound
Leave **disabled** unless the facility has an interface engine (Mirth Connect, Rhapsody,
etc.). When disabled, all clinical workflows function normally — HL7 is purely an
outbound notification layer.

If enabling: set **Processing ID to T (Test)** until end-to-end message delivery is
confirmed, then switch to P (Production). Use the **HL7 Log** page to verify messages
are sending and receiving ACKs.

---

## Step 3 — Configure Rooms and Beds

Open **Admin → Bed Management**. Add locations before any clinical intake begins.

For AL facilities:
- Use ad-hoc location codes (e.g. `A-101`, `B-205`) if rooms are not tracked in a
  catalog. These are entered free-text at intake.
- Or add catalog entries with unit_name = `Wing A`, `Wing B`, etc. for board filtering.

For IP facilities:
- Add bed codes matching your floor layout (e.g. `MS401`, `ICU-1`, `TEL-03`).
- Set `unit_name` on each bed — the Floor Board groups and sorts by unit.

---

## Step 4 — Configure Facility Directory

Open **Admin → Facility Directory**. Add receiving facilities for e-referral and
transfer routing: SNFs, BH placement facilities, specialty referral centers, home health
agencies.

The e-referral auto-draft uses this directory to pre-fill fax number, phone, and address
when the discharge destination name matches a directory entry.

---

## Step 5 — Set Up Users

In OpenEMR's standard user management (Admin → Users):

- **Providers** (physicians, NPs, PAs): set `authorized = 1`. These users appear in
  the attending physician picker at IP admission and in care team assignment.
- **Nurses and aides**: set `authorized = 0`. These users appear in nurse assignment,
  MAR administration, and care team.

After users are created, open **Admin → Context Manager** and assign each user a
care context matching their role:

| Role | Recommended context |
|------|---------------------|
| DON / charge nurse | Operations |
| Floor nurse | Inpatient Stay or Assisted Living |
| ED nurse | ED Acute |
| BH clinician | Behavioral Health |
| Admin / IT | Full Access |

Users without an assigned context default to Full Access.

---

## Step 6 — Run the Onboarding Checklist

Open **Admin → Onboarding**. All items should show PASS or WARN before go-live.

**FAIL items must be resolved.** Common causes:
- *Missing tables* — the install SQL (table.sql) was not run.
  Enable the module in Module Manager, or run `table.sql` manually.
- *AL/IP encounter linkage FAIL* — legacy rows have the wrong encounter_id value.
  Re-link the affected episodes via the episode edit page.

**WARN items** that self-resolve after go-live:
- *No episodes yet* — normal on a fresh install.
- *No care context assignments* — normal if contexts have not been assigned yet.
- *HL7 no messages sent in 24h* — normal if HL7 is disabled or no activity today.

**WARN items that need action before go-live:**
- *No active locations* — add rooms via Bed Management.
- *No provider accounts* — create authorized users before first admission.
- *manifest.json read-only* — `chmod 664` the file so the Manifest Editor can save.

---

## Smoke Test

Open **Admin → Smoke Tests** for a comprehensive schema and integrity check. Run with
`?verbose=1` to see all passing rows, not just failures. Safe on production — read-only.

For a faster post-upgrade sanity check, the Onboarding Checklist covers the clinical
data readiness checks without the full method-and-table inventory.

---

## Demo Training Instance

To set up a populated training environment for staff education:

1. Install OpenEMR on a separate XAMPP or VM instance.
2. Run `sql/institutional-demo-seed-stable.sql` — this seeds 13 ED/OBS patients,
   5 AL residents, 5 IP inpatients, full MAR orders with administration history, care
   plans, incidents, triage vitals, and HL7 event log.
3. Log in as `admin` (password: `pass`).
4. All workflows can be demonstrated without affecting production data.

---

## Common Support Tasks

**A nurse cannot see the Resident Board / Floor Board.**
→ Check their care context in Admin → Context Manager. AL nurses should have
`Assisted Living`; IP nurses should have `Inpatient Stay`.

**Care Plan tab is empty after admitting a patient.**
→ The encounter number was not created correctly. Check the PHP error log for
`[OEI] IP form_encounter INSERT failed` or `encounter number is 0`. Run the
Onboarding Checklist — the AL/IP encounter linkage check will show FAIL.
Resolution: re-link the affected episodes via the episode edit page.

**The Floor Board LOS badges are all green even for long-stay patients.**
→ The per-episode `expected_los_days` was not set at admission. Either set service-line
defaults in Settings → Inpatient Clinical Defaults, or edit the episode via IP Profile
to enter an expected LOS.

**HL7 messages showing NACK in the log.**
→ The integration engine rejected the message format. Check the raw message body via
Admin → HL7 ADT Log. Common causes: wrong MSH.3/4/5/6 identifiers, or the receiving
system requires a specific character encoding.

**A feature is missing from a user's menu.**
→ Either the feature flag is disabled in manifest.json (check Admin → Manifest Editor),
or the user's care context does not surface that feature (check Admin → Context Manager).

**Export CSV is empty.**
→ Date range may not span any data. Try widening the range. AL Census and IP Census
always export current active episodes regardless of date range.

---

## File Locations (XAMPP)

```
Module root:   C:\xampp\htdocs\openemr\interface\modules\custom_modules\oe-module-institutional\
manifest.json: <module root>\manifest.json
Settings:      Stored in oei_settings table (facility_id + setting_key)
Error log:     C:\xampp\apache\logs\error.log  (search for [OEI])
```

---

## Escalation to Level 3

Escalate to the developer (Level 3) when:
- A PHP Fatal Error appears in the error log that is not resolved by the known-issues
  list above.
- A schema change is needed (new feature requiring new columns in `table.sql`).
- An OpenEMR core upgrade breaks a module page.
- A new facility profile or feature flag combination is needed.

When escalating, provide:
1. The full error from the PHP error log (search for `[OEI]` or `PHP Fatal error`).
2. The output of Admin → Smoke Tests with `?verbose=1`.
3. The output of Admin → Onboarding.
4. The manifest.json feature list (visible at the bottom of Admin → Manifest Editor).




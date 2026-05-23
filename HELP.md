# oe-module-institutional — Level 2 Implementation Guide

Version: 0.18.0 | Platform: OpenEMR 7.0+ | PHP 8.1+

---

## What Is a Level 2 Implementer?

A Level 2 implementer configures, onboards, and supports facilities using this module without modifying PHP code. All tasks here are achievable through the module UI, manifest flags, and the Settings page.

**Level 1** — Facility super-user (DON, office manager). Handles day-to-day staff questions, adds rooms and users, resets care contexts.

**Level 2** — Certified implementer. Configures the module for a new facility, validates the install, trains Level 1, handles configuration changes, escalates genuine bugs to Level 3.

**Level 3** — Developer. Schema changes (`table.sql`), PHP code changes, new features.

---

## Step 1 — Choose a Facility Profile

Open **Admin → Manifest Editor** after installation. Click the profile matching the facility type, review the pre-selected flags, then click Save.

| Profile | Use for |
|---|---|
| **AL Only** | Standalone assisted living, memory care, or SNF with no acute ED |
| **ED + OBS + BH** | Hospital emergency department with observation stay and behavioral health |
| **Inpatient** | Hospital floor (med/surg, telemetry, ICU, ortho) with ED intake routing |
| **AL + Inpatient** | CCRC or continuing-care campus with both AL and acute beds |

Individual flags can be toggled after applying a profile. Changes take effect on the next page load — no server restart needed.

---

## Step 2 — Configure Facility Settings

Open **Admin → Settings**.

### Facility Identity
Set the **Facility Display Name** — appears in the multi-facility dashboard and all page headers.

### Clinical Thresholds (ED / OBS)
- **Door-to-Room Target** — default 30 min
- **Door-to-Provider Target** — default 60 min
- **LWBS Alert Threshold** — default 120 min
- **OBS Runway Warning** — default 6 h
- **BH Boarding Alert** — default 4 h

### Inpatient Clinical Defaults *(visible when ip_board is enabled)*
- **Discharge Target Hour** (0–23) — facilities typically aim for 11 AM. A countdown badge on the Discharge Planning page turns amber within the LOS warning window and red in the final hour.
- **LOS Warning Window** — hours before expected LOS the Floor Board badge turns amber. Default 24 h.
- **Service-Line LOS Defaults** — fallback when per-patient expected LOS was not set at admission (Med/Surg, Telemetry, ICU, Ortho).

### AL Vitals Alert Thresholds *(visible when al_board is enabled)*
Frail elderly residents frequently have acceptable baselines outside acute-care norms — adjust with the medical director.
- **BP Systolic High/Low** — default 160 / 90 mmHg
- **Heart Rate High/Low** — default 110 / 50 bpm
- **SpO₂ Critical / Warning** — default 93% / 96%
- **Weight Gain Alert** — default 0.9 kg per reading (CHF fluid retention indicator)

### HL7 ADT Outbound
Leave **disabled** unless the facility has an interface engine. Set **Processing ID to T (Test)** until end-to-end delivery is confirmed, then switch to P (Production). Verify via **Admin → HL7 ADT Log**.

---

## Step 3 — Configure Rooms and Beds

Open **Admin → Bed Management**. Add locations before any clinical intake begins.

- **AL** — ad-hoc codes (e.g. `A-101`) or catalog entries with `unit_name` for board filtering.
- **IP** — bed codes matching your floor (e.g. `MS401`, `ICU-1`). Set `unit_name` on each — the Floor Board groups and sorts by unit.

---

## Step 4 — Configure Facility Directory

Open **Admin → Facility Directory**. Add receiving facilities for e-referral and transfer routing (SNFs, BH placement, specialty referral, home health). The e-referral auto-draft uses this to pre-fill fax, phone, and address.

---

## Step 5 — Set Up Users

In OpenEMR Admin → Users:
- **Providers** (physicians, NPs, PAs): `authorized = 1`
- **Nurses and aides**: `authorized = 0`

Then open **Admin → Context Manager** and assign care contexts:

| Role | Context |
|---|---|
| DON / charge nurse | Operations |
| Floor nurse | Inpatient Stay or Assisted Living |
| ED nurse | ED Acute |
| BH clinician | Behavioral Health |
| Admin / IT | Full Access |

---

## Step 6 — Run the Onboarding Checklist

Open **Admin → Onboarding**. All items should show PASS or WARN before go-live.

**FAIL items must be resolved.**
- *Missing tables* — the install schema (`table.sql`) was not applied; enable the module in Module Manager, or run `table.sql` manually.
- *AL/IP encounter linkage FAIL* — re-link the affected episodes via the episode edit page.

**WARN items that self-resolve after go-live:** No episodes yet · No context assignments · HL7 no messages in 24h.

**WARN items requiring action before go-live:**
- *No active locations* — add rooms via Bed Management.
- *No provider accounts* — create at least one authorized user before first admission.
- *manifest.json read-only* — run `chmod 664 manifest.json`.
- *IP clinical defaults at zero* — set LOS targets in Settings → Inpatient Clinical Defaults.

---

## Smoke Test

Open **Admin → Smoke Tests** with `?verbose=1` for a full schema and integrity check. Read-only — safe on production.

---

## Demo Training Instance

1. Install OpenEMR on a separate XAMPP or VM instance.
2. Run `sql/institutional-demo-seed-stable.sql`.
3. Log in as `admin` / `pass`.

Seeds 13 ED/OBS patients, 5 AL residents, 5 IP inpatients, full MAR history, care plans, incidents, vitals, activity logs, and HL7 event log.

---

## Common Support Tasks

**Nurse cannot see Resident Board / Floor Board.**
Check care context in Admin → Context Manager. AL nurses → `Assisted Living`. IP nurses → `Inpatient Stay`.

**Care Plan tab is empty after admission.**
Check PHP error log for `[OEI] form_encounter INSERT failed`. Run Admin → Onboarding — AL/IP encounter linkage will show FAIL. Fix: re-link the affected episodes via the episode edit page.

**Floor Board LOS badges all green for long-stay patients.**
Per-episode `expected_los_days` not set at admission. Set service-line defaults in Settings → Inpatient Clinical Defaults.

**AL vitals alerts not firing.**
Check Settings → AL Vitals Alert Thresholds. Values of 0 mean settings were never saved — enter values and click Save.

**HL7 NACK in log.**
Check raw message body via Admin → HL7 ADT Log. Common cause: wrong MSH.3/4/5/6 identifiers in Settings.

**Feature missing from user's menu.**
Either the flag is disabled (Admin → Manifest Editor) or the user's context does not surface it (Admin → Context Manager).

**Export CSV is empty.**
Widen the date range. AL Census and IP Census always export current active episodes regardless of date range.

---

## File Locations (XAMPP)

```
Module root:   C:\xampp\htdocs\openemr\interface\modules\custom_modules\oe-module-institutional\
manifest.json: <module root>\manifest.json
Error log:     C:\xampp\apache\logs\error.log  (search [OEI])
```

---

## Escalation to Level 3

Escalate when:
- A PHP Fatal Error is not resolved by the troubleshooting list above.
- A schema change is needed (new columns required in `table.sql`).
- An OpenEMR core upgrade breaks a module page.

Include with every escalation:
1. Full error from PHP error log.
2. Output of Admin → Smoke Tests `?verbose=1`.
3. Output of Admin → Onboarding.
4. Feature list from Admin → Manifest Editor.




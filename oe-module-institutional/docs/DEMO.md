# Demo Guide (5–10 minutes)

This is a simple script to show what the module can do today.

## 1) Install and enable module
- Copy module folder to:
  `interface/modules/custom_modules/oe-module-institutional`
- Run `composer install` in the module directory
- OpenEMR Admin → Modules → Manage Modules → Install/Enable **Institutional**
- Confirm module tables were created via `table.sql`

## 2) Add locations
Menu: Institutional → **Locations**
Create a few:
- ED Room 1 (ED_ROOM)
- ED Room 2 (ED_ROOM)
- Obs Bed 1 (OBS_BED)
- BH Safe Room 1 (BH_SAFE_ROOM)

## 3) Intake a patient
Menu: Institutional → **Episode Intake**
- Search a known patient by name/DOB/phone/PID
- Select the patient
- Enter:
  - Arrival mode: Walk-in / EMS
  - ESI: 2–4
  - Chief complaint: "SOB"
  - Triage note: "O2 sat 91%"
- Submit → redirects to ED board

## 4) Use the ED tracking board
Menu: Institutional → **ED Tracking Board**
- Assign location to ED Room 1
- Set status to PROVIDER / RESULTS / READY_DISPO
- Click **Start Obs** (generates observation tasks if enabled)
- Quick-set BH observation level (Q15 or 1:1) to see BH overlay + BH check tasks

## 5) Show tasks
Menu: Institutional → **Tasks**
- Confirm tasks were created
- Complete one or two tasks

## What to say in the demo
- “We’re building an institutional layer for OpenEMR starting with rural ED + short stay.”
- “Everything is modular and manifest-driven.”
- “No core patches: module manager install, menu events, and tables are managed inside the module.”


## 6) Observation Protocols (Episode View)
- Go to Institutional → Obs Episodes
- Click an Episode ID to open Obs Episode View
- Change protocol and click **Apply & Generate Runway**
- Refresh Tasks to see newly generated runway tasks


### Optional: choose protocol at Start Obs
- On ED Board, when you click **Start Obs**, select a protocol (if Obs Start Picker enabled)
- This applies the selected protocol immediately and generates runway tasks


## 7) Milestones + Extend Runway
- In Obs Episode View, see the **Milestones** card (derived from protocol at_minutes tasks)
- Click **Extend runway** to generate additional tasks for the next runway window
- ED Board also has an **Extend** shortcut on OBS rows


## 8) Disposition + Throughput
- Go to Institutional → Disposition
- Set Decision/Depart time and disposition code
- Go to Institutional → Throughput to see Door→Decision and Door→Depart metrics
- On ED Board, use **Room** and **Provider** buttons to stamp ROOM/PROVIDER events


## 9) BH Boarding / Transfer workflow
- Go to Institutional → **BH Boarding**
- Set placement status, accepting facility, accepted time, transport time/method
- Check off packet checklist + EMTALA complete
- View **Throughput** to see Door→BH Accept and Door→BH Transport
- Use **Print packet** for a simple transfer packet summary


## 10) Bed Board + Transfers + Directory + Exports
- Institutional → **Bed Board**: add locations and assign active episodes
- Institutional → **Facility Directory**: add receiving facilities/services
- Institutional → **Transfers**: track requested/accepted/transport and checklist
- Institutional → **Exports**: download throughput and transfer CSVs for a date range

## e-Referral 
1. Clinician sets disposition in disposition.php with code DISCHARGE or TRANSFER and a destination like "Valley SNF".
2. Auto-draft fires. The next time that episode is opened in ereferral.php, EReferralService::draftFromDisposition() runs and pre-populates: referral type (DISCHARGE/TRANSFER), priority (auto-escalated to URGENT/EMERGENT if ESI ≤ 3 on transfer), clinical summary assembled from chief complaint + ESI + latest vitals from oei_triage, services suggested from episode type, and destination fax/phone resolved from oei_facility_directory by fuzzy name match against what the clinician typed in the Disposition field.
3. Clinician reviews, edits, clicks "Mark as Sent". A <details>/<summary> panel (no JS) lets them pick the send method (Fax, Manual, Print, Direct). Referral moves to SENT status.
4. Print sheet — ?action=print renders a clean, printer-friendly referral document with signature lines. Print it, fax it, or hand it to the patient.
5. Response tracking — once SENT, a response panel appears to record ACCEPTED / DECLINED with the receiving party's name.

## MAR (Medication Administration Record)

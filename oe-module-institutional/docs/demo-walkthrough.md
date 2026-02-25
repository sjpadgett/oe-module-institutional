# oe-module-institutional — Demo Walkthrough
## Patient Arrival → Discharge
**Version 0.9.2 · Standard ED Episode**

---

## Before You Start

You need one existing OpenEMR patient to demo against. Any patient in the system works. Have their name or PID ready. Everything else is driven by the module.

The demo takes **8–12 minutes** end to end. Each step maps to one menu item under **Institutional** in the OpenEMR nav.

---

## Step 1 — Register Arrival
**Menu: Institutional → Episode Intake**

This is the front-door step. A triage clerk or charge nurse uses this when a patient walks in.

1. Type the patient's name, DOB, or PID into the **Find Patient** search box and click **Search**
2. A results table appears — click the radio button next to the correct patient
3. Fill in the three intake fields below the table:
   - **Arrival Mode** → Walk-in
   - **ESI** → 3 (or leave blank — vitals will suggest one automatically in the next step)
   - **Chief Complaint** → `chest pain`
4. Click **Create ED Episode & Add to Board**

The system creates an episode record, sets status to **WAITING**, and redirects to the ED Board. You'll see the patient appear as a new row.

> **What happened:** An `oei_episode` row was inserted, an `oei_episode_status_history` row was written with WAITING, and an HL7 A04 Register message fired if HL7 is enabled.

---

## Step 2 — ED Tracking Board
**Menu: Institutional → ED Tracking Board**

This is the command center. Everything visible on the board for this patient updates in real time as you work through the next steps.

Point out the board columns to your audience:
- **Episode #** — unique visit ID
- **Type** — ED (will change to OBS if you convert later)
- **Elapsed** — live time since arrival (e.g. `14m`)
- **Chief Complaint** and **ESI**
- **Location** — empty until you assign a room
- **Status** — currently WAITING
- **Actions** — the row of inline controls

**Assign a room from the board:**
In the Actions column, find the **Set Room** dropdown. Select any location (e.g. `Bay 3`) and click **Set Room**. The Location column updates immediately.

> You can also quick-add patients directly from the board's **Quick Arrival** form at the top — enter PID + chief complaint + ESI and click **Add to Board**. Useful when intake is bypassed for fast-track patients.

---

## Step 3 — Triage / Vitals
**Menu: Institutional → Triage / Vitals** *(if enabled — may appear as a link from the board)*

Navigate here and select the patient from the left sidebar.

Enter a set of vitals into the form:
| Field | Demo value |
|---|---|
| BP Systolic / Diastolic | 142 / 88 |
| HR | 96 |
| RR | 18 |
| SpO₂ | 97 |
| Temp | 98.6 |
| Pain | 6 |
| Weight | 82 kg |

Click **Save Vitals**.

The yellow banner at the top of the form immediately shows the vitals you just recorded with a severity indicator. If any value is outside normal ranges (e.g. SBP ≥ 180, SpO₂ < 94) it turns red and fires a VITALS_DETERIORATION alert on the charge nurse dashboard.

The system also calculates an **ESI suggestion** from the vitals pattern — it appears as a badge next to the latest vitals line.

---

## Step 4 — Tasks
**Menu: Institutional → Tasks**

Select the episode from the sidebar. The task panel shows any protocol-generated tasks plus a form to add manual ones.

Add a quick demo task:
- **Task Type** → `LAB`
- **Due** → leave as default (now or +30 min)
- Click **Add Task**

The task appears in the list. Click **Complete** next to it. Status flips to DONE with a timestamp.

Back on the ED Board, the **Next Task** column shows overdue tasks in red — good for demonstrating that the board is a live operations view, not just a patient list.

---

## Step 5 — MAR (Medications)
**Menu: Institutional → MAR**

This is the digital paper MAR. Navigate here and select the episode.

**Place an order:**
Expand the **+ Place Medication Order** panel:
- **Drug Name** → `Aspirin`
- **Dose** → `325`
- **Unit** → `mg`
- **Route** → PO
- **Frequency** → QD

Click **Place Order**. Aspirin appears as a medication card. Because QD is a known frequency, the system auto-scheduled one PENDING administration slot for today.

**Record administration:**
In the Aspirin card, find the PENDING row and click **Record** (expands inline):
- **Outcome** → GIVEN
- **Dose Given** → 325 (pre-filled)
- Click **Save**

The row badge flips from PENDING → GIVEN in green.

**High-alert demo (optional):**
Place a second order: Drug Name → `Heparin`, Route → IV, Frequency → Q6H. Save it. The card gets an amber **HIGH ALERT** badge. The Charge Nurse Alerts dashboard will surface a MAR_OVERDUE CRITICAL alert for this drug if a scheduled dose passes 15 minutes without recording.

---

## Step 6 — Observation Stay (optional branch)
**Menu: ED Tracking Board → Start Obs button**

If you want to show the observation workflow, go back to the ED Board. In the patient's Actions column, select a protocol from the protocol dropdown (e.g. **General Observation**) and click **Start Obs**.

The episode type changes from **ED → OBS**. Navigate to **Institutional → Obs Episodes** and select the patient to see:
- The protocol clock (elapsed / remaining hours)
- Auto-generated protocol milestones
- A link to the full Obs Episode detail page

The OBS_RUNWAY alert will appear on the alerts dashboard when the window gets close. This is strong for the investor demo — "the system tells you when an obs patient is about to exceed their window."

Skip this step if you want a pure ED discharge demo.

---

## Step 7 — Disposition
**Menu: Institutional → Disposition**

Select the episode from the left sidebar.

Fill in the disposition form:
| Field | Demo value |
|---|---|
| Disposition | Discharge |
| Destination | `Home` |
| Decision Time | (click now — set to current time) |
| Depart Time | (set to +30 min from now) |
| Notes | `Stable, improved with aspirin` |

Click **Save**.

The system writes two episode events: **DECISION** and **DEPART**. These timestamps feed the Throughput metrics (Door→Decision, Door→Depart). The disposition code is stored against the episode record.

> At this point, E-Referral auto-draft fires in the background. The next step shows it.

---

## Step 8 — E-Referral
**Menu: Institutional → E-Referral**

Select the same episode. Because you just saved a DISCHARGE disposition, the system has already generated a **DRAFT** referral pre-filled with:
- Referral Type: Discharge
- Priority: Routine
- Clinical Summary: chief complaint, ESI, today's vitals pulled from triage
- Services Requested: "Primary care follow-up within 7 days"
- Destination: matched against Facility Directory if `Home` or a facility name was entered

The clinician reviews and edits the free-text fields. In the demo, update **Follow-up Instructions** to `Return to ED if chest pain recurs. PCP follow-up in 5 days.`

Click **Save Draft**, then expand **Mark as Sent** → select **Manual / Phone** → click **Confirm Send**.

Status badge flips: `DRAFT → SENT` in blue.

Click **Print / Fax Sheet** (opens new tab) — a clean printable referral document with signature lines appears. Point out to the audience: "This is what goes in the chart and gets faxed to the receiving provider — generated automatically from everything documented in the visit."

---

## Step 9 — Close the Episode
**Menu: ED Tracking Board**

Back on the board, find the patient row. In the Actions column, open the **Disposition…** dropdown, select **DISCHARGE**, and click **Close** (confirm the dialog).

The episode status flips to **CLOSED**. The row disappears from the Active Episodes board — the patient is discharged.

> **What happened:** `oei_episode.status` → CLOSED, `end_datetime` set to now, HL7 A03 Discharge message fired if enabled.

---

## Step 10 — Throughput Report
**Menu: Institutional → Throughput**

Set the date to today and click **Refresh**. The metrics grid shows the episode you just completed:

| Metric | What it measures |
|---|---|
| Avg Door→Room | Arrival to first location assignment |
| Avg Door→Provider | Arrival to first PROVIDER status |
| Avg Door→Decision | Arrival to disposition decision time |
| Avg Door→Depart | Arrival to depart time |

These pull from the episode events table. For the investor demo, this is the proof point: **"Every click in the system feeds structured, reportable data. Meaningful Use attestation comes from this table."**

---

## Charge Nurse Alerts Dashboard (bonus)
**Menu: Institutional → ED Tracking Board → Alerts tab** *(or your alerts dashboard URL)*

If you left the Heparin order unrecorded for 15+ minutes during the demo, the alerts dashboard will show:
- **MAR_OVERDUE CRITICAL** — "Overdue med: Heparin by Xm" with the HIGH ALERT detail line

Combined with whatever LWBS, task overdue, or obs runway alerts are firing, this is the live operational intelligence story: "One screen, all the things that need immediate attention, prioritized by clinical severity."

---

## Quick Reference — Demo Sequence

```
OpenEMR login
    └─ Institutional › Episode Intake          (register patient, ESI, chief complaint)
    └─ Institutional › ED Tracking Board       (assign room, see live board)
    └─ Institutional › Triage / Vitals         (record BP/HR/SpO2/Pain → ESI suggested)
    └─ Institutional › Tasks                   (add LAB task, mark complete)
    └─ Institutional › MAR                     (order Aspirin QD, record GIVEN)
    └─ [optional] ED Board › Start Obs         (convert to OBS, apply protocol)
    └─ Institutional › Disposition             (DISCHARGE, set decision + depart times)
    └─ Institutional › E-Referral             (auto-draft appears, edit, Send, Print)
    └─ ED Board › Close episode               (DISCHARGE → episode leaves board)
    └─ Institutional › Throughput              (Door→Room, Door→Decision, Door→Depart)
    └─ Alerts Dashboard                        (MAR_OVERDUE, LWBS, OBS_RUNWAY)
```

---

## Demo Tips

**Prepare a patient.** Create a test patient in OpenEMR named something memorable like "Demo Patient, Jane" — search by name in Intake is the cleanest opening move.

**Pre-load the Facility Directory.** Add 2–3 receiving facilities (SNF, rehab, hospital) under Institutional → Facility Directory before the demo. When you type a destination in Disposition, E-Referral will match it and pull the fax/phone automatically — that auto-population lands well with clinical audiences.

**Open two browser windows.** Run the Charge Nurse Alerts dashboard in one, the ED Board in the other. As you work through the steps in the board window, alerts fire in the alerts window in real time — it reads like a live system, not a tour.

**Show the print sheet last.** The E-Referral print view is the strongest closing visual — a formatted document with the patient's vitals, medications, follow-up instructions, and signature lines, generated automatically from the visit. End on that screen.

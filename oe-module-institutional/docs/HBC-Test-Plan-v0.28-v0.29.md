# HBC Upgrade Test Plan — v0.28.0 + v0.29.0

**Module:** oe-module-institutional (Home-Based Care)
**Drops:** hbc-upgrade-vitals-cert-v0.28.0.zip, hbc-upgrade-duration-commlog-v0.29.0.zip
**Platform:** OpenEMR 7.0+ / XAMPP / PHP 8.1+

---

## Pre-Test Setup

1. Backup database before applying drops.
2. Apply v0.28.0 zip overlay, then v0.29.0 zip overlay.
3. Run migrations in order:
   - `sql/migrations/0010_hbc_cert_compliance.sql`
   - `sql/migrations/0011_hbc_comm_log.sql`
4. Verify both migrations registered: `SELECT * FROM oei_schema_version WHERE version IN ('0010','0011');`
5. Add feature flag `"hbc_comm_log": true` to facility manifest.
6. Populate test data on at least one HBC episode:
   - Set `cert_period_start`, `cert_period_end`, `authorized_visits_per_week` on `oei_hbc_episode`:
     ```sql
     UPDATE oei_hbc_episode
     SET cert_period_start = DATE_SUB(CURDATE(), INTERVAL 30 DAY),
         cert_period_end   = DATE_ADD(CURDATE(), INTERVAL 10 DAY),
         authorized_visits_per_week = 3
     WHERE episode_id = <your_test_episode_id>;
     ```
   - Have at least one COMPLETE visit with `actual_start_datetime` and `actual_end_datetime` set.
   - Have at least one SCHEDULED visit for today.

---

## Feature 1: Inline Vitals on Visit Workspace (v0.28.0)

### T1.1 — Vitals accordion renders correctly
- **Navigate:** Visit Board → open a non-finalized visit → Visit Workspace
- **Verify:** A green "Vitals (optional)" collapsible card appears between Follow-up Plan and Signature sections.
- **Verify:** Accordion is collapsed by default on a fresh visit.
- **Verify:** Fields present: BP Sys/Dia, HR, SpO₂%, RR, Temp °F, Wt kg, Pain 0–10.
- **Pass criteria:** All 8 fields render, no layout break on desktop and mobile viewport.

### T1.2 — Vitals badge indicator
- **Action:** Expand the vitals accordion. Enter BP 120/80.
- **Verify:** A green ✓ badge appears on the accordion header.
- **Action:** Clear both BP fields.
- **Verify:** Badge disappears.

### T1.3 — Vitals included in draft save
- **Action:** Enter HR=72, SpO₂=97. Click "Save Draft".
- **Verify:** Autosave indicator shows "Draft saved".
- **Action:** Navigate away to Profile, then reopen the same visit.
- **Verify:** HR and SpO₂ fields are pre-populated from the draft. Accordion auto-expands since vitals are present.

### T1.4 — Vitals included in localStorage backup
- **Action:** Enter Temp=98.6 in the vitals section (do NOT save draft — just type).
- **Action:** Close the browser tab. Reopen the same visit URL.
- **Verify:** Temp field is restored from localStorage.

### T1.5 — Vitals written to oei_triage on finalize
- **Action:** Open a SCHEDULED visit. Fill in: visit note, outcome, BP 130/85, HR 78, SpO₂ 96.
- **Action:** Click "Finalize Visit" → confirm.
- **Verify:** Redirects to profile with "Visit finalized" flash.
- **Verify DB:**
  ```sql
  SELECT * FROM oei_triage
  WHERE episode_id = <episode_id>
  ORDER BY id DESC LIMIT 1;
  ```
  Confirm bp_systolic=130, bp_diastolic=85, hr=78, spo2=96, noted_datetime is recent.

### T1.6 — Finalize without vitals (no oei_triage row created)
- **Action:** Open a SCHEDULED visit. Fill in visit note and outcome. Leave all vitals blank.
- **Action:** Finalize.
- **Verify DB:** No new row in oei_triage for this visit (the `hasAnyVital` guard prevents empty inserts).

### T1.7 — Vitals show on Profile after finalize
- **Action:** After T1.5, go to the patient Profile page.
- **Verify:** The "Latest Vitals" card shows the values from T1.5 (BP 130/85, HR 78, SpO₂ 96%).

### T1.8 — Offline draft with vitals
- **Action:** Open a visit workspace. Enter vitals. Disconnect network (airplane mode or disable adapter).
- **Action:** Click "Save Draft".
- **Verify:** Offline banner appears. Status shows "Offline — queued locally".
- **Action:** Reconnect network.
- **Verify:** Background sync fires. Draft is saved server-side with vitals.

---

## Feature 2: Cert Period Compliance Tracking (v0.28.0)

### T2.1 — Profile service snapshot shows cert data
- **Navigate:** Patient Profile for the test episode with cert data populated.
- **Verify:** Service Snapshot card shows:
  - "Cert Period" section with start → end dates
  - Days-left badge (should show ~10d in secondary/gray)
  - Auth: 3/wk line
  - Used: N in M wks line
  - Progress bar with % of expected

### T2.2 — Cert expiry badge colors
- **Action:** Set `cert_period_end = DATE_ADD(CURDATE(), INTERVAL 5 DAY)` on test episode.
- **Verify:** Profile shows red badge "5d left".
- **Action:** Set `cert_period_end = DATE_ADD(CURDATE(), INTERVAL 12 DAY)`.
- **Verify:** Yellow badge "12d left".
- **Action:** Set `cert_period_end = DATE_SUB(CURDATE(), INTERVAL 2 DAY)`.
- **Verify:** Red "Expired" badge.

### T2.3 — Clinical attention scoring includes cert
- **Navigate:** Profile → Clinical Attention panel.
- **Verify with expired cert:** Panel shows "high" priority with "Cert period has expired" in reasons list.
- **Verify with 5d cert:** Shows "Cert period expires in 5 days" as a reason.
- **Verify with 30d cert:** No cert-related reason appears.

### T2.4 — Board action queue includes cert scoring
- **Navigate:** Visit Board.
- **Verify:** If the test patient's cert is expired or near-expiry, they appear higher in the Priority Action Queue with cert-related reason text.

### T2.5 — Handoff report cert column
- **Navigate:** Handoff report page.
- **Verify:** New "Cert" column visible between "Fall Risk" and "Goal".
- **Verify:** Shows cert end date (month-day), days badge, and auth/wk for the test patient.
- **Verify:** Cert expiring flag chip (📅 Cert expiring) appears in the Flags column when ≤14d.

### T2.6 — No cert data graceful fallback
- **Action:** View Profile/Handoff for an episode that has NO cert_period_end set.
- **Verify:** No cert section renders on profile. Handoff cert column shows "—". No errors.

---

## Feature 3: Visit Duration Display (v0.29.0)

### T3.1 — Board visit card shows duration
- **Navigate:** Visit Board, select a date that has at least one COMPLETE visit.
- **Verify:** Completed visit cards show a ⏱ line with calculated duration (e.g. "1h 23m" or "45m").
- **Verify:** If mileage was recorded, it appears on the same line (e.g. "⏱ 1h 23m · 12.5 mi").
- **Verify:** Non-complete visits (SCHEDULED, EN_ROUTE, ARRIVED) do NOT show a duration line.

### T3.2 — Duration calculation correctness
- **Verify DB:** For a known complete visit:
  ```sql
  SELECT actual_start_datetime, actual_end_datetime,
         TIMESTAMPDIFF(MINUTE, actual_start_datetime, actual_end_datetime) AS expected_min
  FROM oei_hbc_visit WHERE id = <visit_id>;
  ```
  Confirm the displayed duration matches.

### T3.3 — Schedule page duration column
- **Navigate:** Schedule Visit page for an episode.
- **Verify:** "Duration" column appears in the Scheduled & Recent Visits table.
- **Verify:** COMPLETE visits show duration. Others show "—".

### T3.4 — Missing timestamps graceful fallback
- **Action:** If a COMPLETE visit has NULL actual_start_datetime (edge case from quick-advance without arrival step).
- **Verify:** Duration shows "—", no PHP error.

---

## Feature 4: Communication Log (v0.29.0)

### T4.1 — Feature flag gate
- **Action:** Remove `hbc_comm_log` from manifest (set to false). Navigate to comm_log.php.
- **Verify:** Shows "Communication Log is not enabled" alert. No error.
- **Action:** Re-enable the flag.

### T4.2 — Nav tab appears
- **Navigate:** Any HBC patient sub-page (Profile, Vitals, etc.).
- **Verify:** "📞 Comm Log" tab appears in the patient nav strip between existing tabs and Discharge.
- **Action:** Click the tab.
- **Verify:** Navigates to comm_log.php with correct episode_id, pid, facility_id params.

### T4.3 — Empty state
- **Navigate:** Comm Log for an episode with no entries.
- **Verify:** Shows "No communications logged for this episode" info alert.
- **Verify:** "Log Communication" button is visible.

### T4.4 — Create a communication entry
- **Action:** Click "+ Log Communication" button.
- **Verify:** Modal opens with fields: Type, Contact Role, Date/Time, Contact Name, Contact Phone, Subject, Summary, Outcome, Follow-up checkbox + note.
- **Action:** Fill in:
  - Type: Phone (outgoing)
  - Contact Role: PCP
  - Contact Name: Dr. Martinez
  - Subject: Med change review
  - Summary: Discussed increasing lisinopril from 10mg to 20mg per recent BP readings.
  - Outcome: PCP agrees, new Rx to be called in.
  - Check "Follow-up needed", note: Confirm pharmacy received Rx tomorrow.
- **Action:** Click Save.
- **Verify:** Page refreshes with "Communication logged" flash.
- **Verify:** Entry appears in the list with 📞 icon, "PCP" badge, contact info, summary text, yellow follow-up badge.

### T4.5 — Verify DB
```sql
SELECT * FROM oei_hbc_comm_log
WHERE episode_id = <episode_id>
ORDER BY id DESC LIMIT 1;
```
Confirm all fields match input. `followup_needed = 1`. `user_id` = current session user.

### T4.6 — Multiple entries and ordering
- **Action:** Create 3 more entries with different types (Fax, Secure message, In person) and different roles.
- **Verify:** All 4 entries appear in reverse chronological order.

### T4.7 — Role filter
- **Action:** Select "PCP" from the filter dropdown.
- **Verify:** Only PCP entries visible.
- **Action:** Select "All contacts".
- **Verify:** All entries visible again.

### T4.8 — Follow-up filter
- **Action:** Check "Follow-up only" checkbox.
- **Verify:** Only entries with the follow-up flag are visible.
- **Action:** Combine: select "PCP" from role filter AND check "Follow-up only".
- **Verify:** Only PCP entries with follow-up are visible.

### T4.9 — Follow-up count in header
- **Verify:** Header shows "N follow-up needed" in yellow where N = number of entries with followup_needed=1.

### T4.10 — Profile clinical workflows link
- **Navigate:** Patient Profile → Clinical Workflows card.
- **Verify:** "📞 Comm Log" link appears and navigates correctly.

### T4.11 — CSRF validation
- **Action:** Open the modal, tamper with csrf_token_form value in browser dev tools, submit.
- **Verify:** Dies with "CSRF validation failed" — entry is NOT created.

---

## Regression Checks

### R1 — Existing visit finalize still works
- Open a visit, fill in note + outcome (no vitals), finalize.
- Verify: Visit completes, no PHP errors, tasks auto-generated as before.

### R2 — Board quick-advance still works
- On board, click → En Route → Arrived → Complete on a SCHEDULED visit.
- Verify: Status advances correctly with GPS attempt.

### R3 — Offline sync still works
- Disconnect network, finalize a visit with vitals.
- Verify: Queued in IndexedDB. Reconnect → syncs → redirects to profile.

### R4 — Handoff print view
- Navigate to Handoff → click Print.
- Verify: Print layout renders cleanly with the new Cert column. No overflow.

### R5 — Profile page loads without errors
- Load Profile for episodes WITH and WITHOUT cert data, WITH and WITHOUT visits.
- Verify: No PHP warnings, no blank panels, all cards render.

### R6 — Schedule page unaffected
- Schedule a new visit. Verify it appears in the list with "—" duration.
- Cancel a visit. Verify it disappears from the list.

---

## Migration Rollback (if needed)

```sql
-- Rollback 0011
DROP TABLE IF EXISTS oei_hbc_comm_log;
DELETE FROM oei_schema_version WHERE version = '0011';

-- Rollback 0010
ALTER TABLE oei_hbc_episode DROP COLUMN IF EXISTS authorized_visits_per_week;
DELETE FROM oei_schema_version WHERE version = '0010';
```

Note: Rollback of PHP files requires restoring from backup or re-overlaying v0.27.0 source.

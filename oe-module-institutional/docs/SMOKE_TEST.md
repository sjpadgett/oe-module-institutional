# Institutional Module Smoke Test (v0.1.1)

Goal: validate the core workflow end-to-end in ~10 minutes after install.

## Preconditions
- Module installed via OpenEMR **Module Manager**
- Institutional menu visible
- You know your Facility ID (often 1 in dev). If unsure, check Facilities in OpenEMR Admin.

## 1. Create an active episode
1. Institutional → **Intake**
2. Pick a patient (PID) and (optionally) encounter (EID)
3. Submit to create an ACTIVE episode

Expected:
- Success message appears
- Episode appears on ED Tracking Board

## 2. Create locations (beds/rooms)
1. Institutional → **Locations**
2. Add 2–3 locations, e.g.:
   - Code: ED01  Name: ED Room 1  Type: ROOM
   - Code: OBS1  Name: Obs Bay 1  Type: OBS
3. Ensure they are **Active**

Expected:
- Locations list shows new rows

## 3. Assign episode to a location (Bed Board)
1. Institutional → **Bed Board**
2. Find your active episode
3. Assign it to ED01 / OBS1

Expected:
- Bed Board shows episode at the selected location
- ED Tracking Board shows location name/type

## 4. Create a transfer
1. Institutional → **Facility Directory**
2. Add a receiving facility entry (e.g., “Regional Hospital ICU”)
3. Institutional → **Transfers**
4. Select the active episode, choose the receiving facility, set Requested time, Save

Expected:
- Transfer record saved and shows status/timestamps

## 5. Export CSV
1. Institutional → **Exports**
2. Export the date range that includes your test episode

Expected:
- CSV downloads and includes the episode/transfer rows

## 6. Verify error visibility (flash alerts)
1. Open Transfers page without selecting an episode and attempt Save
2. Confirm you see a visible error alert (no silent failures)

If any step fails, capture:
- Exact error message
- URL (page)
- openemr error log line (file + line)

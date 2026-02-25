# OpenEMR Institutional Workflow Module
### Product Capability Summary · v0.9.5 · February 2026

---

## At a Glance

| Submodules | PHP Classes | Clinical Pages | Release |
|:----------:|:-----------:|:--------------:|:-------:|
| 23 | 100+ | 27+ | v0.9.5 |

---

## Executive Summary

The OpenEMR Institutional Workflow Module is a purpose-built clinical operations extension for OpenEMR, the world's most widely deployed open-source electronic health record system. The module transforms OpenEMR into a fully featured institutional workflow platform capable of managing Emergency Department tracking, inpatient observation stays, behavioral health boarding, medication administration, real-time charge nurse alerting, discharge coordination, and HL7 v2 ADT interoperability — all within a single, manifest-driven, drop-in package.

The current release (v0.9.5) delivers a production-ready core across 23 submodules with a clean PSR-4 PHP architecture designed to integrate with OpenEMR's evolving module management framework. All submodules are feature-flagged through a central manifest. v0.9.5 adds CMS pay-for-performance quality measure tracking (OP-1, OP-2, OP-5, SEP-1), CMS 2-midnight rule observation billing compliance, and a multi-facility health system dashboard — expanding the product from a single-facility workflow tool to a health system operations platform.

---

## Problem & Market Opportunity

Community hospitals, critical access facilities, and behavioral health units running OpenEMR lack access to the real-time clinical operations tooling available in enterprise EHR systems — tools that reduce LWBS rates, shorten door-to-provider times, ensure regulatory compliance for observation status billing and EMTALA transfers, and automate transitions-of-care documentation at discharge.

**Key gaps this module addresses:**

1. No real-time ED tracking board in standard OpenEMR
1. No structured observation stay management with CMS-compliant 2-midnight rule enforcement
1. No behavioral health boarding workflow or EMTALA transfer checklist
1. No charge nurse alerting layer — overdue tasks, overdue medications, LWBS risk, sepsis risk, and deteriorating vitals go unnoticed
1. No digital medication administration record — paper MARs create compliance gaps and high-alert drug risks
1. No CMS pay-for-performance quality measure tracking — door-to-ECG, door-to-provider, and sepsis bundle compliance are invisible
1. No multi-facility view — health system administrators have no cross-facility census or alert visibility
1. No HL7 ADT outbound feed — downstream systems receive no admit/transfer/discharge events

---

## Technical Architecture

The module is implemented as a PSR-4 compliant OpenEMR custom module, installable as a single directory drop-in. The architecture separates public-facing pages from business logic through a clean Controller / Service / Repository layering, with all submodules organized under a shared namespace hierarchy.

- **Language:** PHP 8.1+, zero external runtime dependencies
- **Database:** MySQL/MariaDB — 23 tables with safe `IF NOT EXISTS` migrations
- **Frontend:** Bootstrap 5 — responsive, no build step required
- **Feature gating:** JSON manifest — enable/disable any submodule per facility
- **Security:** CSRF validation on every POST, OpenEMR ACL integration, PRG pattern throughout
- **Alerts polling:** Pure Web Audio API tone + 60-second auto-refresh, no WebSocket required
- **Interoperability:** HL7 v2.5.1 ADT over MLLP (TCP) or HTTP — compatible with Mirth Connect, Rhapsody, Azure AHDS, AWS HealthLake

---

## Feature Matrix

### Clinical Operations

| Module | Capability | Status |
|--------|-----------|--------|
| **ED Tracking Board** | Real-time episode board with arrival stamping, location assignment, status workflow (Waiting → Roomed → Provider → Results → Ready Dispo → Obs → Closed), BH safety quick-set, obs start with protocol picker, and disposition close. | Production |
| **Episode Intake** | Patient search (name, DOB, phone, PID) with new episode creation, arrival mode, ESI entry, and chief complaint capture. | Production |
| **Triage / Vitals** | Multi-set vitals capture (BP, HR, RR, SpO₂, Temp, GCS, Pain, Weight) with automatic ESI suggestion, history table, trend indicators, and abnormal value highlighting. | Production |
| **Disposition** | Per-episode disposition form capturing code, destination, decision/depart datetimes, admit flag, notes. Timestamps feed throughput metrics. Triggers automatic E-Referral draft on save. | Production |
| **Tasks** | Protocol-driven task scheduling (every-N-minutes or at-minute patterns), open task list with one-click completion, overdue highlighting. | Production |
| **Staff Assignments** | Nurse and provider assignment per episode. Quick-assign dropdown on ED Board. Assignments management page with JSON endpoint for inline editing. | Production |
| **Episode Timeline** | Chronological event stream per episode across 7 data sources: episode events, status history, location changes, vitals sets, tasks, MAR administrations, and e-referral events. | Production |

### Medication Administration

| Module | Capability | Status |
|--------|-----------|--------|
| **MAR** | Digital medication administration record. Order placement with high-alert drug detection (23 categories). Auto-scheduling from standard frequency codes (QD through Q12H). PRN orders. Inline outcome recording (GIVEN / HELD / REFUSED / MISSED). Facility-wide overdue panel. | Production |
| **Allergy Checking** | Queries OpenEMR lists table before administration. Bidirectional substring match surfaces allergy warning banner on MAR page. Advisory — not a hard block; clinical judgment prevails. | Production |
| **MAR Alerts** | Overdue medication slots surface as MAR_OVERDUE alerts. High-alert drugs and doses >30 min overdue escalate to CRITICAL. 15-minute configurable grace period. | Production |

### Clinical Decision Support

| Module | Capability | Status |
|--------|-----------|--------|
| **Sepsis / qSOFA Scoring** | Auto-computed qSOFA score from latest vitals every alert cycle. Criteria: GCS < 15 (altered mentation), RR ≥ 22, SBP ≤ 100. Score ≥ 2 = SEPSIS_RISK alert; score 3 = CRITICAL; score 2 = WARNING. Detail shows triggering criteria. | Production |
| **Vitals Deterioration** | Per-episode alerts on SpO₂, systolic BP, HR, GCS, and temperature threshold breaches. Stale vitals alerts (>2h ED, >4h OBS). No-vitals-on-arrival alert. | Production |
| **Vitals Scheduling** | Auto-schedules VITALS_CHECK tasks on protocol application. Q2H for ED episodes, Q4H for OBS. Protocol-aware deduplication prevents double-scheduling. | Production |

### Discharge & Transitions of Care

| Module | Capability | Status |
|--------|-----------|--------|
| **E-Referral** | Discharge referral auto-drafted from disposition. Priority auto-escalated for high-acuity transfers. Clinical summary pre-populated from vitals and chief complaint. Facility Directory fuzzy-matched. Full send/response workflow. Print / Fax Sheet. Supports Meaningful Use transitions-of-care attestation. | Production |
| **Shift Handoff** | Printable shift change report: room, ESI, chief complaint, status, last vitals, qSOFA score, next task due, pending MAR count, assigned nurse and provider. Print stylesheet optimised for 8.5pt landscape output. | Production |

### Observation Stay Management

| Module | Capability | Status |
|--------|-----------|--------|
| **Obs Protocol Engine** | JSON-defined protocol templates (target hours, runway hours, milestone tasks). Built-in: General Observation and Chest Pain. Protocol editor UI for custom protocols. | Production |
| **Obs Episodes Board** | Facility-wide active obs plan list with next task type and due time for charge nurse oversight. | Production |
| **Runway Alerts** | Warning when obs window approaches the runway threshold. CRITICAL alert on overrun. | Production |
| **OBS Billing Flags** | CMS 2-Midnight Rule compliance. Classifies each OBS episode: Normal / Approaching 1st Midnight / Approaching 2nd Midnight / Convert to Inpatient (≥48h) / Overrun (≥72h). Timeline progress bar with midnight markers. CRITICAL billing alerts on charge nurse dashboard. | Production |

### Analytics & Reporting

| Module | Capability | Status |
|--------|-----------|--------|
| **Alerts Dashboard** | Dark-mode charge nurse dashboard polling every 60s. Ten alert types: LWBS risk, overdue tasks, MAR overdue, BH boarding dwell, obs runway, vitals deterioration, stale vitals, no-vitals-on-arrival, sepsis risk (qSOFA), and OBS billing flags. Per-alert snooze. Web Audio API alert tone on new criticals. | Production |
| **Throughput** | Daily KPI dashboard: door-to-room, door-to-provider, door-to-decision, door-to-depart averages. BH-specific door-to-accepted and door-to-transport. | Production |
| **Provider Scorecard** | Per-provider performance: volume, D2R, D2P, D2D, D2Depart, LWBS rate, obs rate, obs LOS, ESI distribution. Delta vs. facility average, color-coded vs. targets, daily volume sparkline. | Production |
| **CMS Quality Measures** | Four CMS pay-for-performance measures: Door-to-Room (OP-1, ≤30m), Door-to-Provider (OP-2, ≤60m), Door-to-ECG (OP-5, ≤10m), Sepsis Antibiotic Bundle ≤3h (SEP-1). SVG gauge cards with EXCELLENT/GOOD/FAIR/POOR tier badges. Episode drill-down with met/missed breakdown. No new tables required. | Production |
| **Multi-Facility Dashboard** | Health system view across all registered facilities. Per-facility: census, OBS count, bed occupancy bar, LWBS count, BH boarding count, MAR overdue count, sepsis risk count, avg D2R today. System-wide KPI strip. 60-second auto-refresh. | Production |
| **Exports** | CSV export of throughput and transfer data for offline analysis and regulatory reporting. | Production |

### HL7 v2 ADT Interoperability

| Module | Capability | Status |
|--------|-----------|--------|
| **ADT Message Builder** | HL7 v2.5.1 ADT messages from episode data. Segments: MSH, EVN, PID, PV1, PV2. Events: A01 Admit, A02 Transfer, A03 Discharge, A04 Register, A08 Update. | Production |
| **MLLP Transport** | TCP socket with `0x0B` / `0x1C 0x0D` framing. Parses ACK AA/AE/AR. Compatible with Mirth Connect, Rhapsody, Ensemble. | Production |
| **HTTP Transport** | HTTP/S POST with `Content-Type: application/hl7-v2`. Optional Bearer token for Azure AHDS and AWS HealthLake. | Production |
| **Outbound Log** | Every send persisted with full message, ACK, status, and error detail. Auto-fires A04/A02/A01/A03/A08. Fire-and-forget never blocks clinical workflow. | Production |

### Administration

| Module | Capability | Status |
|--------|-----------|--------|
| **Bed Board** | Location management with episode-to-location assignment, occupancy view, and move history. | Production |
| **Locations** | Location CRUD with type (ED Room, OBS Bed, BH Room) and status management. | Production |
| **Facility Directory** | Receiving facility database for BH boarding, transfer, and E-Referral. Auto-matched by E-Referral to populate destination fax and phone. | Production |
| **Episode Documents** | File attachment to episodes with type classification. Soft-delete, served delivery, upload validation. | Production |
| **Settings** | Per-facility thresholds: LWBS, boarding hours, obs runway, MAR grace, ESI max, D2R/D2P targets, HL7 transport mode, MLLP host/port, HTTP URL, processing ID. | Production |

---

## Full Episode Workflow

A complete, unbroken patient journey from door to discharge with every step documented, timed, and optionally transmitted downstream:

| # | Stage | Actions |
|---:|------|---------|
| 1 | Arrival | Episode Intake → search patient → create ED episode (WAITING, HL7 A04) |
| 2 | Room | ED Board → Set Room → location assigned (ROOMED, HL7 A02) |
| 3 | Triage | Triage → vitals recorded → ESI suggested → qSOFA computed → alerts fire if abnormal |
| 4 | Clinical Work | Tasks → protocol tasks scheduled. MAR → medications ordered, administered, allergy-checked, high-alert drugs flagged. Vitals auto-scheduled by protocol |
| 5 | Obs Stay | ED Board → Start Obs → protocol applied (HL7 A01). OBS Billing page monitors 2-midnight compliance |
| 6 | Disposition | Disposition → code + destination + times saved → E-Referral draft auto-generated |
| 7 | Referral | E-Referral → clinician reviews → Mark as Sent → Print / Fax Sheet |
| 8 | Handoff | Shift Handoff → printable snapshot with vitals, qSOFA, and pending orders |
| 9 | Discharge | ED Board → Close → episode CLOSED, removed from board (HL7 A03) |
| 10 | Reporting | Throughput, Provider Scorecard, and CMS Quality Measures all updated automatically |

---

## Development Roadmap

### Recently Shipped

| Release | Capability | Date |
|---------|-----------|------|
| v0.9.5 | **CMS Quality Measures Dashboard** — four pay-for-performance measures (OP-1, OP-2, OP-5, SEP-1) with SVG gauge cards and episode drill-down | Feb 2026 |
| v0.9.5 | **OBS Billing Flags** — CMS 2-midnight rule monitoring with timeline progress bar and charge nurse billing alerts | Feb 2026 |
| v0.9.5 | **Multi-Facility Dashboard** — health system view with per-facility census, alerts, bed occupancy, and 60s auto-refresh | Feb 2026 |
| v0.9.4 | **Sepsis / qSOFA Scoring** — automatic score from latest vitals; CRITICAL/WARNING alert with criteria detail | Feb 2026 |
| v0.9.4 | **Shift Handoff Report** — printable shift snapshot with qSOFA, pending MAR, and assignment summary | Feb 2026 |
| v0.9.4 | **MAR Allergy Checking** — patient allergy query before every administration; advisory warning banner | Feb 2026 |
| v0.9.3 | **Episode Timeline** — chronological event stream across 7 data sources per episode | Feb 2026 |
| v0.9.3 | **Vitals Auto-Scheduling** — VITALS_CHECK tasks by protocol (Q2H ED, Q4H OBS) | Feb 2026 |
| v0.9.3 | **Staff Assignments** — nurse/provider per episode with quick-assign on ED Board | Feb 2026 |
| v0.9.2 | **Discharge E-Referral** — auto-drafted from disposition with directory-matched destination | Feb 2026 |
| v0.9.1 | **MAR** — digital medication administration with high-alert detection, auto-scheduling, PRN support | Feb 2026 |

### Upcoming

| Release | Capability | Date |
|---------|-----------|------|
| v0.10 | **FHIR R4 Resource Mapping** — Encounter, Location, Observation, Patient resources from the episode model | Q2 2026 |
| v0.10 | **Diversion Status** — facility-level diversion flag with automatic ADT A09 notification | Q2 2026 |
| v0.10 | **Downtime Mode** — offline-capable local storage fallback for network outages | Q3 2026 |
| v1.0 | **FHIR Subscriptions** — push Encounter status change notifications for FHIR-native downstream systems | Q3 2026 |
| v1.0 | **Patient Portal Integration** — estimated wait time and episode status display | Q4 2026 |
| v1.0 | **MAR → Discharge Summary** — medication list auto-populates E-Referral at discharge | Q4 2026 |

---

## Why OpenEMR

1. 100+ million patients worldwide on OpenEMR deployments
1. 35,000+ facilities across 100+ countries — strong foothold in critical access and community hospitals
1. ONC-certified, HIPAA-ready, actively maintained with a large contributor community
1. No per-seat licensing — dramatically lower TCO vs. Epic, Cerner, or Meditech
1. Module marketplace emerging — first-mover advantage for institutional workflow tooling

---

*This document reflects the v0.9.5 release dated February 2026.*  

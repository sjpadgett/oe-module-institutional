# OpenEMR Institutional Workflow Module
### Product Capability Summary · v0.9.2 · February 2026

---

## At a Glance

| Submodules | PHP Classes | Clinical Pages | Release |
|:----------:|:-----------:|:--------------:|:-------:|
| 20 | 92+ | 24+ | v0.9.2 |

---

## Executive Summary

The OpenEMR Institutional Workflow Module is a purpose-built clinical operations extension for OpenEMR, the world's most widely deployed open-source electronic health record system. The module transforms OpenEMR into a fully featured institutional workflow platform capable of managing Emergency Department tracking, inpatient observation stays, behavioral health boarding, medication administration, real-time charge nurse alerting, discharge coordination, and HL7 v2 ADT interoperability — all within a single, manifest-driven, drop-in package.

The current release (v0.9.2) delivers a production-ready core across 20 submodules, with a clean PSR-4 PHP architecture designed to integrate with OpenEMR's evolving module management framework. All submodules are feature-flagged through a central manifest, allowing facilities to enable only the workflows relevant to their care setting. HL7 v2.5.1 ADT messaging is fully wired into the clinical workflow — admit, transfer, and discharge events emit automatically with no additional configuration beyond the integration endpoint.

---

## Problem & Market Opportunity

Community hospitals, critical access facilities, and behavioral health units running OpenEMR lack access to the real-time clinical operations tooling available in enterprise EHR systems — tools that reduce left-without-being-seen (LWBS) rates, shorten door-to-provider times, ensure regulatory compliance for observation status billing and EMTALA transfers, and automate transitions-of-care documentation at discharge.

**Key gaps this module addresses:**

- No real-time ED tracking board in standard OpenEMR
- No structured observation stay management with CMS-compliant 2-midnight rule support
- No behavioral health boarding workflow or EMTALA transfer checklist
- No charge nurse alerting layer — overdue tasks, overdue medications, LWBS risk, and deteriorating vitals go unnoticed
- No digital medication administration record — paper MARs create compliance gaps and high-alert drug risks
- No triage vitals capture with ESI acuity suggestion
- No transitions-of-care documentation at discharge — a Meaningful Use attestation requirement
- No HL7 ADT outbound feed — downstream systems (labs, radiology, billing, HIE) receive no admit/transfer/discharge events

---

## Technical Architecture

The module is implemented as a PSR-4 compliant OpenEMR custom module, installable as a single directory drop-in. The architecture separates public-facing pages from business logic through a clean Controller / Service / Repository layering, with all submodules organized under a shared namespace hierarchy.

- **Language:** PHP 8.1+, zero external runtime dependencies
- **Database:** MySQL/MariaDB — 18 new tables with safe `IF NOT EXISTS` migrations
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
| **ED Tracking Board** | Real-time episode board with arrival stamping, location assignment, status workflow (Waiting → Roomed → Provider → Results → Ready Dispo → Obs → Closed), BH safety quick-set, obs start with protocol picker, and disposition close | Production |
| **Episode Intake** | Patient search (name, DOB, phone, PID) with new episode creation, arrival mode selection, ESI entry, and chief complaint capture | Production |
| **Triage / Vitals** | Multi-set vitals capture (BP, HR, RR, SpO₂, Temp, GCS, Pain, Weight) with automatic ESI suggestion from vitals thresholds, history table, HR and SpO₂ trend indicators, and abnormal value highlighting | Production |
| **Disposition** | Per-episode disposition form capturing code, destination, decision and depart datetimes, admit flag, and notes — timestamps feed throughput metrics. Triggers automatic E-Referral draft on save | Production |
| **Tasks** | Protocol-driven task scheduling (every-N-minutes or at-minute patterns), open task list with one-click completion, overdue highlighting | Production |

### Medication Administration

| Module | Capability | Status |
|--------|-----------|--------|
| **MAR** | Digital medication administration record replacing paper MARs. Medication order placement with high-alert drug detection (insulin, heparin, fentanyl, warfarin, and 18 additional categories). Auto-scheduling of administration slots from standard frequency codes (QD / BID / TID / QID / Q1H–Q12H). PRN orders with on-demand dose recording. Inline outcome recording (GIVEN / HELD / REFUSED / NOT_AVAILABLE / MISSED). Facility-wide overdue panel showing all pending doses across active episodes. High-alert drugs flagged visually with amber highlighting and ⚠ badge | Production |
| **MAR Alerts** | Overdue medication administration slots surface as MAR_OVERDUE alerts on the charge nurse dashboard. High-alert drugs and doses more than 30 minutes overdue escalate to CRITICAL severity. 15-minute grace period configurable per facility. Table guard ensures graceful degradation on facilities without MAR installed | Production |

### Discharge & Transitions of Care

| Module | Capability | Status |
|--------|-----------|--------|
| **E-Referral** | Discharge referral documentation auto-drafted from episode disposition. Referral type auto-mapped from disposition code (DISCHARGE / TRANSFER / BH_PLACEMENT). Priority auto-escalated to URGENT or EMERGENT for transfers with ESI ≤ 3. Clinical summary pre-populated from chief complaint, ESI acuity, and latest triage vitals. Destination resolved against Facility Directory by fuzzy name match — fax and phone auto-filled. Clinician review-and-edit form with Save Draft and Mark as Sent workflow. Send method tracking (Manual, Fax, Direct, Print). Response recording (ACCEPTED / DECLINED / CANCELLED) with receiving party name. Print / Fax Sheet renders a clean paper-ready referral document with signature lines. Supports Meaningful Use transitions-of-care attestation | Production |

### Observation Stay Management

| Module | Capability | Status |
|--------|-----------|--------|
| **Obs Protocol Engine** | JSON-defined protocol templates (target hours, runway hours, milestone tasks). Two built-in protocols: General Observation and Chest Pain. Protocol editor UI for custom protocols | Production |
| **Obs Episode View** | Per-episode obs plan showing protocol, start datetime, target/runway hours, next due task, and protocol application form | Production |
| **Obs Episodes Board** | Facility-wide active obs plan list with next task type and due time for charge nurse oversight | Production |
| **Runway Alerts** | Auto-computed warning when obs window approaches the runway threshold; critical alert on overrun — integrated with the charge nurse dashboard | Production |

### Behavioral Health

| Module | Capability | Status |
|--------|-----------|--------|
| **BH Safety** | Per-episode observation level (One-to-One, Q15, Q30, Q60), involuntary status, suicide/violence/elopement risk flags, precautions checklist, and automatic BH check task scheduling | Production |
| **BH Boarding** | Full boarding workflow: legal status, placement status (Searching / Pending / Accepted), accepting facility, transport method and datetime, EMTALA completion flag, eight-item transfer checklist | Production |
| **BH Packet** | Printable transfer packet page for patient handoff documentation | Production |
| **Transfers** | General transfer tracking with receiving facility (linked to directory), reason, status lifecycle, and transfer checklist | Production |

### Charge Nurse & Analytics

| Module | Capability | Status |
|--------|-----------|--------|
| **Alerts Dashboard** | Dark-mode charge nurse dashboard polling every 60 seconds. Eight alert types: LWBS risk, overdue tasks, overdue medications (MAR), BH boarding dwell, obs runway, vitals deterioration (SpO₂/BP/HR/GCS/RR/Temp), stale vitals, and no-vitals-on-arrival. Per-alert snooze (15m–2h) with database persistence. Web Audio API alert tone on new criticals. Alerts sorted CRITICAL-first then by minutes overdue | Production |
| **Throughput** | Daily KPI dashboard computing door-to-room, door-to-provider, door-to-decision, and door-to-depart averages across a date range. BH-specific door-to-accepted and door-to-transport metrics | Production |
| **Provider Scorecard** | Per-provider performance dashboard with configurable date range (7d / 30d / 90d quick filters). Metrics: visit volume, door-to-room, door-to-provider, door-to-decision, door-to-depart, LWBS rate, obs conversion rate, average obs length of stay, and ESI acuity distribution. Each throughput metric shows delta vs. facility average and is color-coded against configurable targets. Canvas sparkline shows daily volume trend across the selected period. Sortable by any column | Production |
| **Exports** | CSV export of throughput and transfer data for offline analysis and regulatory reporting | Production |

### HL7 v2 ADT Interoperability

| Module | Capability | Status |
|--------|-----------|--------|
| **ADT Message Builder** | Builds fully spec-compliant HL7 v2.5.1 ADT messages from episode data. Segments: MSH, EVN, PID (with MRN, demographics, address, SSN), PV1 (patient class, assigned location, attending NPI lookup, visit number, discharge disposition), PV2 (chief complaint, arrival mode). All five event types covered: A01 Admit, A02 Transfer, A03 Discharge, A04 Register, A08 Update | Production |
| **MLLP Transport** | TCP socket transport with standard `0x0B` / `0x1C 0x0D` MLLP framing. Reads and parses ACK response — distinguishes `AA` (accepted), `AE` (error), and `AR` (rejected). Compatible with Mirth Connect, Rhapsody, Ensemble, and all major hospital integration engines on port 2575 | Production |
| **HTTP Transport** | HTTP/S POST transport with `Content-Type: application/hl7-v2` for cloud integration engines. Optional Bearer token for Azure Health Data Services and AWS HealthLake authentication | Production |
| **Outbound Log** | Every send attempt persisted with full message body, ACK response, status (SENT / NACK / ERROR), and error detail. Admin log viewer with 24-hour summary stats and raw HL7 message modal. Events fire automatically — A04 on arrival, A02 on location assignment, A01 on obs start, A08 on status change, A03 on discharge. Fire-and-forget architecture ensures a failed send never blocks clinical workflow | Production |

### Administration

| Module | Capability | Status |
|--------|-----------|--------|
| **Bed Board** | Location management with episode-to-location assignment, current occupancy view, and move history | Production |
| **Locations** | Location CRUD with type (ED Room, OBS Bed, BH Room, etc.) and status management | Production |
| **Facility Directory** | Receiving facility database for BH boarding, transfer, and E-Referral workflows (name, service type, phone, fax, hours). Auto-matched by E-Referral service to populate referral destination fax and phone | Production |
| **Episode Documents** | File attachment to episodes with document type classification (GENERAL, CONSENT, LAB, IMAGING, TRANSFER, BH_PACKET). Soft-delete, served file delivery, upload size validation | Production |
| **Settings** | Per-facility threshold and integration configuration: LWBS threshold, boarding alert hours, obs runway warning hours, MAR grace minutes, ESI high-acuity max, door-to-room and door-to-provider targets (used by Provider Scorecard for color-coded benchmarking), HL7 transport mode, MLLP host/port, HTTP URL, sending/receiving application identifiers, processing ID (test/production) | Production |

---

## Full Episode Workflow

The module supports a complete, unbroken patient journey from door to discharge with every step documented, timed, and optionally transmitted downstream:

```
1. Arrival          Episode Intake → search patient → create ED episode (status: WAITING)
2. Room Assignment  ED Board → Set Room → location assigned (status: ROOMED, HL7 A02)
3. Triage / Vitals  Triage page → vitals recorded → ESI suggested → alerts fire if abnormal
4. Clinical Work    Tasks → protocol tasks scheduled + completed
                    MAR → medications ordered, administered, high-alert drugs flagged
5. Obs Stay         ED Board → Start Obs → protocol applied → runway clock starts
                    Obs Episode → milestones tracked → runway alert fires near end
6. Disposition      Disposition page → code + destination + times saved
                    → E-Referral draft auto-generated
7. Referral         E-Referral → clinician reviews → edits → Mark as Sent
                    → Print / Fax Sheet for paper handoff
8. Discharge        ED Board → Close → episode CLOSED, removed from board (HL7 A03)
9. Reporting        Throughput → KPI metrics for this visit
                    Provider Scorecard → contributes to provider benchmarks
```

---

## Development Roadmap

### Recently Shipped

| Release | Capability | Shipped |
|---------|-----------|--------|
| v0.9.2 | **Discharge E-Referral** — auto-drafted from disposition, pre-filled with vitals and clinical summary, directory-matched destination, full send/response workflow, printable referral sheet. Supports Meaningful Use transitions-of-care attestation | Feb 2026 |
| v0.9.1 | **MAR — Medication Administration Record** — digital paper MAR with high-alert detection, auto-scheduling, PRN support, facility-wide overdue panel, and MAR_OVERDUE alert type wired into the charge nurse dashboard | Feb 2026 |
| v0.9.0 | **Episode Documents** — file attachment to episodes with type classification, soft-delete, and served file delivery | Feb 2026 |
| v0.8.0 | **Provider Scorecard** — per-provider throughput benchmarking with configurable targets, ESI acuity distribution, and daily volume sparkline | Feb 2026 |
| v0.8.0 | **HL7 ADT Event Hooks** — A01/A02/A03/A04/A08 events wired automatically into the clinical workflow | Feb 2026 |
| v0.7.0 | **HL7 v2.5.1 ADT Feed** — full message builder, MLLP and HTTP transports, outbound log with raw message viewer | Feb 2026 |
| v0.6.0 | **Charge Nurse Alerts Dashboard** — 8 alert types, vitals deterioration engine, per-alert snooze, Web Audio tone | Feb 2026 |
| v0.6.0 | **Triage / Vitals** — multi-set capture with ESI suggestion, trend indicators, abnormal highlighting | Feb 2026 |

### Upcoming

| Release | Capability | Target |
|---------|-----------|--------|
| v0.9.3 | **MAR → Task Integration** — completing a MED_PASS task automatically records a MAR administration slot, closing the loop between protocol scheduling and medication workflow | Q1 2026 |
| v0.9.3 | **MAR → Discharge Summary** — medication list from MAR auto-populates E-Referral medications field at discharge | Q1 2026 |
| v0.10 | **FHIR R4 Resource Mapping** — Encounter, Location, Observation, and Patient resources mapped from the episode model; integration with OpenEMR's native FHIR R4 endpoint | Q2 2026 |
| v0.10 | **Diversion Status** — facility-level diversion flag with automatic ADT A09 notification to downstream systems | Q2 2026 |
| v0.10 | **Downtime Mode** — offline-capable local storage fallback for network outages | Q3 2026 |
| v1.0 | **FHIR Subscriptions** — push Encounter status change notifications as the modern ADT equivalent for FHIR-native downstream consumers | Q3 2026 |
| v1.0 | **Patient Portal Integration** — estimated wait time and episode status display | Q4 2026 |
| v1.0 | **Multi-Facility Dashboard** — enterprise view across all registered facilities | Q4 2026 |

---

## Why OpenEMR

- 100+ million patients worldwide on OpenEMR deployments
- 35,000+ facilities across 100+ countries — strong foothold in critical access and community hospitals
- ONC-certified, HIPAA-ready, actively maintained with a large contributor community
- No per-seat licensing — dramatically lower total cost of ownership vs. Epic, Cerner, or Meditech
- Module marketplace emerging — first-mover advantage for institutional workflow tooling

---

*This document reflects the v0.9.2 release dated February 2026.*

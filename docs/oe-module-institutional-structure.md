# oe-module-institutional — File Structure
**v0.15.1** · PSR-4 Domain Architecture · OpenEMR Custom Module

---

```
oe-module-institutional/
├─ composer.json                          single PSR-4 entry: OpenEMR\Modules\Institutional\ → src/
├─ manifest.json                          49 feature flags + menus + migration list
├─ openemr-module.json                    module manager metadata (name, version, min_oe_version, etc.)
├─ openemr.bootstrap.php                  OE entry point — registers autoload, calls Bootstrap
├─ table.sql                              fresh-install schema (26 oei_* tables)
│
├─ public/                                thin entry points — no SQL, no business logic
│  ├─ _bootstrap.php                      shared include: globals, autoload, ManifestLoader
│  ├─ smoke_test.php                      382-check deployment verifier
│  ├─ sw.js
│  │
│  ├─ al/                                 Assisted Living pages
│  │  ├─ activity.php
│  │  ├─ adl.php
│  │  ├─ al_mar.php
│  │  ├─ board.php
│  │  ├─ care_plan.php
│  │  ├─ discharge.php
│  │  ├─ fall_risk.php
│  │  ├─ handoff.php
│  │  ├─ incident.php
│  │  ├─ intake.php
│  │  ├─ profile.php
│  │  └─ vitals.php
│  │
│  ├─ admin_exports.php
│  ├─ adt.php
│  ├─ alerts.php
│  ├─ assignments.php
│  ├─ bed_board.php
│  ├─ bed_management.php
│  ├─ bh_boarding.php
│  ├─ bh_packet.php
│  ├─ bh_safety.php
│  ├─ bh_safety_set.php
│  ├─ cms_quality.php
│  ├─ command_center.php
│  ├─ context_manager.php
│  ├─ context_switch.php
│  ├─ disposition.php
│  ├─ diversion.php
│  ├─ downtime.php
│  ├─ downtime_snapshot.php
│  ├─ downtime_sync.php
│  ├─ ed_board.php
│  ├─ episode_documents.php
│  ├─ ereferral.php
│  ├─ exports.php
│  ├─ facility_directory.php
│  ├─ handoff.php
│  ├─ help.php
│  ├─ hl7_log.php
│  ├─ index.php
│  ├─ intake.php
│  ├─ locations.php
│  ├─ mar.php
│  ├─ multi_facility.php
│  ├─ obs_apply_protocol.php
│  ├─ obs_billing.php
│  ├─ obs_episode.php
│  ├─ obs_episodes.php
│  ├─ obs_extend_runway.php
│  ├─ obs_protocols.php
│  ├─ scorecard.php
│  ├─ settings.php
│  ├─ tasks.php
│  ├─ throughput.php
│  ├─ timeline.php
│  ├─ transfer_tracking.php
│  ├─ transfers.php
│  ├─ trends.php
│  └─ triage.php
│
├─ sql/
│  ├─ migrations/                         versioned ordered migrations (NEW v0.15.1)
│  │  ├─ 0001_initial_schema.sql          marks v1.0.0 in oei_schema_version
│  │  ├─ 0002_assisted_living.sql         v1.1.0: al_episode, adl_record, incident
│  │  ├─ 0003_al_fall_risk.sql            v1.2.0: fall_risk_assessment
│  │  └─ 0004_al_activity_log.sql         v1.3.0: activity_log
│  │
│  ├─ al_activity.sql                     legacy / dev reference only
│  ├─ al_discharge_seed.sql
│  ├─ al_phase2.sql
│  ├─ assisted_living.sql
│  ├─ demo_seed_al.sql
│  ├─ demo_seed_al_supplement.sql
│  ├─ dev_reset.sql
│  ├─ institutional-demo-seed-stable.sql
│  └─ institutional-demo-seed.sql
│
└─ src/                                   PSR-4 root → OpenEMR\Modules\Institutional\
   ├─ Bootstrap.php                        wires menu listener + MigrationRunner
   │
   ├─ Core/                               shared foundation used by all domains
   │  ├─ Domain/
   │  │  ├─ CareContext.php
   │  │  ├─ Disposition.php
   │  │  ├─ EpisodeStatus.php
   │  │  └─ TriageStandard.php
   │  ├─ Migration/
   │  │  └─ MigrationRunner.php           NEW v0.15.1
   │  ├─ Repository/
   │  │  ├─ ContextRepository.php
   │  │  ├─ EpisodeRepository.php
   │  │  └─ UserRepository.php
   │  ├─ Service/
   │  │  ├─ AclGuard.php
   │  │  ├─ AuditService.php
   │  │  └─ ContextService.php
   │  └─ Ui/
   │     ├─ Flash.php
   │     ├─ ViewHelper.php
   │     └─ partials/
   │        ├─ context_bar.php
   │        ├─ flash.php
   │        └─ page_title.php
   │
   ├─ Manifest/
   │  ├─ ContextManifest.php
   │  ├─ Manifest.php
   │  └─ ManifestLoader.php
   │
   ├─ AssistedLiving/
   │  ├─ Domain/
   │  │  ├─ AdlLevel.php
   │  │  ├─ CareLevel.php
   │  │  ├─ FallRiskLevel.php
   │  │  └─ IncidentType.php
   │  ├─ Submodule/
   │  │  ├─ AdlTracking/
   │  │  │  ├─ Controller/AdlController.php
   │  │  │  ├─ Repository/AdlRepository.php
   │  │  │  └─ Service/AdlService.php
   │  │  ├─ AlActivity/
   │  │  │  ├─ Controller/AlActivityController.php
   │  │  │  └─ Repository/AlActivityRepository.php
   │  │  ├─ AlDischarge/
   │  │  │  ├─ Controller/AlDischargeController.php
   │  │  │  └─ Repository/AlDischargeRepository.php
   │  │  ├─ AlHandoff/
   │  │  │  ├─ Controller/AlHandoffController.php
   │  │  │  └─ Repository/AlHandoffRepository.php
   │  │  ├─ AlMar/
   │  │  │  ├─ Controller/AlMarController.php
   │  │  │  └─ Repository/AlMarRepository.php
   │  │  ├─ AlVitals/
   │  │  │  ├─ Controller/AlVitalsController.php
   │  │  │  └─ Repository/AlVitalsRepository.php
   │  │  ├─ CarePlan/
   │  │  │  ├─ Controller/CarePlanController.php
   │  │  │  ├─ Repository/CarePlanRepository.php
   │  │  │  └─ Service/CarePlanService.php
   │  │  ├─ FallRisk/
   │  │  │  ├─ Controller/FallRiskController.php
   │  │  │  └─ Repository/FallRiskRepository.php
   │  │  ├─ IncidentReport/
   │  │  │  ├─ Controller/IncidentController.php
   │  │  │  ├─ Repository/IncidentRepository.php
   │  │  │  └─ Service/IncidentService.php
   │  │  ├─ ResidentBoard/
   │  │  │  ├─ Controller/ResidentBoardController.php
   │  │  │  ├─ Repository/ResidentBoardRepository.php
   │  │  │  └─ Service/ResidentBoardService.php
   │  │  ├─ ResidentIntake/
   │  │  │  ├─ Controller/ResidentIntakeController.php
   │  │  │  ├─ Repository/ResidentIntakeRepository.php
   │  │  │  └─ Service/ResidentIntakeService.php
   │  │  └─ ResidentProfile/
   │  │     ├─ Controller/ResidentProfileController.php
   │  │     └─ Repository/ResidentProfileRepository.php
   │  └─ Ui/
   │     └─ partials/al_resident_nav.php
   │
   ├─ BehavioralHealth/
   │  └─ Submodule/
   │     ├─ BhBoarding/
   │     │  ├─ Controller/BhBoardingController.php
   │     │  └─ Repository/BhBoardingRepository.php
   │     └─ BhSafety/
   │        ├─ Controller/BhSafetyController.php
   │        ├─ Repository/BhSafetyRepository.php
   │        └─ Service/BhSafetyService.php
   │
   ├─ EmergencyDepartment/
   │  └─ Submodule/
   │     ├─ Diversion/
   │     │  ├─ Controller/DiversionController.php
   │     │  ├─ Repository/DiversionRepository.php
   │     │  └─ Service/DiversionService.php
   │     ├─ Downtime/
   │     │  ├─ Controller/DowntimeController.php
   │     │  └─ Service/
   │     │     ├─ DowntimeSnapshotService.php
   │     │     └─ DowntimeSyncService.php
   │     └─ EdBoard/
   │        └─ Controller/EdBoardController.php
   │
   ├─ ObservationStay/
   │  └─ Submodule/
   │     ├─ CmsQuality/
   │     │  └─ Repository/CmsMeasureRepository.php
   │     ├─ ObsBilling/
   │     │  └─ Service/ObsBillingService.php
   │     ├─ ObsCore/
   │     │  └─ Service/ObsService.php
   │     └─ ObsProtocols/
   │        ├─ Controller/
   │        │  ├─ ObsEpisodeController.php
   │        │  ├─ ObsEpisodesController.php
   │        │  └─ ObsProtocolsController.php
   │        ├─ Repository/
   │        │  ├─ ObsPlanRepository.php
   │        │  └─ ProtocolRepository.php
   │        └─ Service/ObsProtocolEngine.php
   │
   ├─ Operations/
   │  └─ Submodule/
   │     ├─ FacilityDirectory/
   │     │  └─ Repository/FacilityDirectoryRepository.php
   │     ├─ Hl7Adt/
   │     │  ├─ Builder/AdtMessageBuilder.php
   │     │  ├─ Repository/Hl7OutboundLogRepository.php
   │     │  ├─ Service/AdtNotificationService.php
   │     │  └─ Transport/
   │     │     ├─ HttpTransport.php
   │     │     └─ MllpTransport.php
   │     ├─ MultiFacility/
   │     │  └─ Repository/MultiFacilityRepository.php
   │     ├─ Scorecard/
   │     │  ├─ Repository/ScorecardRepository.php
   │     │  └─ Service/ScorecardService.php
   │     └─ Settings/
   │        └─ Repository/SettingsRepository.php
   │
   └─ Shared/
      └─ Submodule/
         ├─ AdtLite/
         │  ├─ Controller/LocationsController.php
         │  ├─ Repository/
         │  │  ├─ LocationHistoryRepository.php
         │  │  └─ LocationRepository.php
         │  └─ Service/AdtService.php
         ├─ Alerts/
         │  ├─ Controller/AlertsController.php
         │  ├─ Repository/AlertAckRepository.php
         │  └─ Service/AlertService.php
         ├─ Assignment/
         │  ├─ Controller/AssignmentController.php
         │  └─ Repository/AssignmentRepository.php
         ├─ BedMgmt/
         │  ├─ Controller/BedMgmtController.php
         │  └─ Repository/
         │     ├─ EpisodeLocationRepository.php
         │     └─ LocationRepository.php
         ├─ Disposition/
         │  ├─ Controller/DispositionController.php
         │  └─ Repository/
         │     ├─ DispositionRepository.php
         │     └─ EpisodeEventRepository.php
         ├─ EpisodeDocuments/
         │  ├─ Controller/EpisodeDocumentController.php
         │  ├─ Repository/EpisodeDocumentRepository.php
         │  └─ Service/EpisodeDocumentService.php
         ├─ EReferral/
         │  ├─ Controller/EReferralController.php
         │  ├─ Repository/EReferralRepository.php
         │  └─ Service/EReferralService.php
         ├─ Handoff/
         │  ├─ Controller/HandoffController.php
         │  ├─ Repository/HandoffRepository.php
         │  └─ Service/HandoffService.php
         ├─ Intake/
         │  ├─ Controller/IntakeController.php
         │  ├─ Repository/
         │  │  ├─ EpisodeIntakeRepository.php
         │  │  └─ PatientRepository.php
         │  └─ Service/IntakeService.php
         ├─ Mar/
         │  ├─ Controller/MarController.php
         │  ├─ Repository/
         │  │  ├─ MarAdministrationRepository.php
         │  │  └─ MarOrderRepository.php
         │  └─ Service/
         │     ├─ AllergyService.php
         │     └─ MarService.php
         ├─ Tasks/
         │  ├─ Controller/TasksController.php
         │  ├─ Repository/TaskRepository.php
         │  └─ Service/TaskService.php
         ├─ Throughput/
         │  ├─ Controller/ThroughputController.php
         │  └─ Service/ThroughputService.php
         ├─ Timeline/
         │  ├─ Controller/TimelineController.php
         │  └─ Repository/TimelineRepository.php
         ├─ TransferTracking/
         │  └─ Repository/TransferRepository.php
         ├─ Trends/
         │  ├─ Controller/TrendsController.php
         │  ├─ Repository/TrendRepository.php
         │  └─ Service/TrendsService.php
         └─ Triage/
            ├─ Controller/TriageController.php
            ├─ Repository/TriageRepository.php
            └─ Service/
               ├─ TriageService.php
               └─ VitalsSchedulerService.php
```

---

## Summary

| Area | Count |
|---|---|
| PHP source files | 195 |
| Public entry points | 67 (55 root + 12 al/) |
| src/ domains | 6 (Core, AssistedLiving, BehavioralHealth, EmergencyDepartment, ObservationStay, Operations, Shared) |
| Shared submodules | 17 |
| AL submodules | 12 |
| Migration files | 4 (sql/migrations/) |
| Feature flags | 49 (manifest.json) |
| DB tables (oei_*) | 26 |

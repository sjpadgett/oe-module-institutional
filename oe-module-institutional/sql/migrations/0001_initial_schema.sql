-- =============================================================================
-- Migration 0001 — Initial Schema
-- oe-module-institutional
-- Version: 1.0.0
-- Applies: All core oei_* tables
-- Source:  table.sql (canonical install script)
-- =============================================================================
-- This migration creates every core table.
-- table.sql remains the canonical install for fresh deployments.
-- This file exists so the MigrationRunner can track that the initial
-- schema was applied via oei_schema_version.
-- =============================================================================

INSERT IGNORE INTO `oei_schema_version` (`version`, `applied_datetime`)
VALUES ('1.0.0', NOW());

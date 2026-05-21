<?php

/**
 * src/Manifest/ManifestWriter.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Manifest;

/**
 * ManifestWriter
 *
 * Persists feature flag changes back to manifest.json without touching
 * any other key (module_id, version, ui, migrations, menus).
 *
 * The web-server user requires write permission on manifest.json.
 * Call canWrite() and surface the result to the admin before attempting save().
 *
 * Thread safety: file_put_contents with LOCK_EX prevents concurrent writes
 * from producing corrupted JSON. PHP request duration is short enough that
 * this is adequate for single-server XAMPP / modest multi-instance deployments.
 */
final class ManifestWriter
{
    public function __construct(private readonly string $manifestPath) {}

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Returns true when the web-server process can write manifest.json.
     * Call this first and show a warning if false — the save form should
     * be read-only until permissions are fixed.
     */
    public function canWrite(): bool
    {
        return is_writable($this->manifestPath);
    }

    /**
     * Returns the raw decoded manifest array, or throws on parse error.
     * @return array<string,mixed>
     */
    public function read(): array
    {
        $raw = file_get_contents($this->manifestPath);
        if ($raw === false) {
            throw new \RuntimeException("Cannot read manifest.json at {$this->manifestPath}");
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException("manifest.json is not valid JSON");
        }
        return $data;
    }

    /**
     * Persist a complete features map back to manifest.json.
     * All other keys are preserved exactly as-is.
     *
     * @param array<string,bool> $features  Key = feature name, value = enabled flag
     * @throws \RuntimeException on read or write failure
     */
    public function saveFeatures(array $features): void
    {
        $data             = $this->read();
        $data['features'] = $features;

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        if ($json === false) {
            throw new \RuntimeException("Failed to encode manifest data as JSON");
        }

        $written = file_put_contents($this->manifestPath, $json, LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException(
                "Cannot write manifest.json — check that the web-server user has write permission on: "
                . $this->manifestPath
            );
        }
    }

    // ── Built-in facility profiles ────────────────────────────────────────────

    /**
     * Returns the named profile as a feature-key → bool map.
     * Unknown keys default to false so the profile is always a complete map.
     *
     * @param  string[] $allKeys  The full list of known feature keys from manifest.json
     * @return array<string,bool>
     */
    public function profile(string $name, array $allKeys): array
    {
        // FULL profile → enable everything
        if ($name === 'FULL') {
            $map = [];
            foreach ($allKeys as $k) {
                $map[$k] = true;
            }
            return $map;
        }

        $on = self::PROFILES[$name] ?? [];
        $map = [];
        foreach ($allKeys as $k) {
            $map[$k] = in_array($k, $on, true);
        }
        return $map;
    }

    /** @return string[] list of available profile names */
    public function profileNames(): array
    {
        return array_keys(self::PROFILES);
    }

    // ── Profile definitions ───────────────────────────────────────────────────
    // Each profile lists only the features that should be ENABLED.
    // Everything omitted is disabled (false).

    private const PROFILES = [

        // ── Assisted Living only ──────────────────────────────────────────────
        // A standalone AL facility with no ED or inpatient workflows.
        // Covers: census → intake → profile → vitals → ADL → fall risk →
        //         care plan → incident → MAR → activity → discharge → handoff.
        'AL_ONLY' => [
            'context_manager',
            'al_board', 'al_intake', 'al_profile', 'al_care_plan',
            'al_adl', 'al_incident', 'al_vitals', 'al_fall_risk',
            'al_mar', 'al_discharge', 'al_activity', 'al_handoff',
            'care_plan', 'care_plan_launch',
            'clinical_notes', 'clinical_notes_launch',
            'clinical_notes_documents', 'clinical_notes_results',
            'care_team', 'care_team_launch',
            'tasks', 'assignment', 'handoff', 'alerts',
            'episode_documents', 'ereferral',
            'bed_mgmt', 'facility_directory', 'settings',
            'admin_exports', 'smoke_test', 'trends', 'cms_quality',
            'mar', 'hl7_adt', 'institutional_billing',
        ],

        // ── Emergency Department + Observation + Behavioral Health ────────────
        // Hospital ED with OBS and BH tracking. No AL or inpatient.
        'ED_OBS_BH' => [
            'context_manager',
            'edt_board', 'intake', 'triage', 'disposition', 'diversion', 'downtime',
            'obs_stay', 'obs_protocols', 'obs_start_picker', 'obs_episodes', 'obs_billing',
            'bh_safety', 'bh_boarding',
            'transfer_tracking',
            'care_plan', 'care_plan_launch',
            'clinical_notes', 'clinical_notes_launch',
            'clinical_notes_documents', 'clinical_notes_results',
            'care_team', 'care_team_launch',
            'tasks', 'assignment', 'handoff', 'alerts',
            'episode_documents', 'ereferral', 'timeline',
            'throughput', 'scorecard', 'trends', 'cms_quality',
            'multi_facility', 'command_center',
            'adt_lite', 'bed_mgmt', 'facility_directory',
            'hl7_adt', 'admin_exports', 'settings', 'smoke_test',
            'mts_triage', 'mar', 'institutional_billing',
        ],

        // ── Inpatient Hospital (with ED intake) ───────────────────────────────
        // Full inpatient floor with ED for admissions routing.
        // No AL-specific pages.
        'INPATIENT' => [
            'context_manager',
            'edt_board', 'intake', 'triage', 'disposition',
            'ip_board', 'ip_admission', 'ip_profile', 'ip_discharge',
            'care_plan', 'care_plan_launch',
            'clinical_notes', 'clinical_notes_launch',
            'clinical_notes_documents', 'clinical_notes_results',
            'care_team', 'care_team_launch',
            'tasks', 'assignment', 'handoff', 'alerts',
            'episode_documents', 'ereferral', 'timeline',
            'throughput', 'scorecard', 'trends', 'cms_quality',
            'command_center', 'multi_facility',
            'adt_lite', 'bed_mgmt', 'facility_directory',
            'hl7_adt', 'admin_exports', 'settings', 'smoke_test',
            'mar', 'ip_vitals', 'ip_fall_risk', 'institutional_billing',
        ],

        // ── Combined AL + Inpatient ───────────────────────────────────────────
        // For a facility that manages both a residential AL wing and an
        // inpatient acute unit (e.g. a CCRC or continuing care campus).
        'AL_INPATIENT' => [
            'context_manager',
            'al_board', 'al_intake', 'al_profile', 'al_care_plan',
            'al_adl', 'al_incident', 'al_vitals', 'al_fall_risk',
            'al_mar', 'al_discharge', 'al_activity', 'al_handoff',
            'ip_board', 'ip_admission', 'ip_profile', 'ip_discharge',
            'care_plan', 'care_plan_launch',
            'clinical_notes', 'clinical_notes_launch',
            'clinical_notes_documents', 'clinical_notes_results',
            'care_team', 'care_team_launch',
            'tasks', 'assignment', 'handoff', 'alerts',
            'episode_documents', 'ereferral', 'timeline',
            'throughput', 'scorecard', 'trends', 'cms_quality',
            'command_center',
            'adt_lite', 'bed_mgmt', 'facility_directory',
            'admin_exports', 'settings', 'smoke_test',
            'mar', 'hl7_adt', 'institutional_billing', 'ip_vitals', 'ip_fall_risk',
        ],

        // ── Home-Based Care ─────────────────────────────────────────────
        'HOME_BASED_CARE' => [
            'context_manager',
            'hbc_board', 'hbc_intake', 'hbc_profile',
            'hbc_visit', 'hbc_schedule', 'hbc_vitals', 'hbc_fall_risk',
            'hbc_handoff', 'hbc_discharge', 'hbc_comm_log',
            'al_vitals', 'al_fall_risk', 'al_incident',
            'care_plan', 'care_plan_launch',
            'clinical_notes', 'clinical_notes_launch',
            'clinical_notes_documents', 'clinical_notes_results',
            'care_team', 'care_team_launch',
            'episode_documents', 'ereferral', 'observations',
            'tasks', 'mar', 'alerts', 'handoff', 'trends',
            'institutional_billing',
            'bed_mgmt', 'facility_directory',
            'hl7_adt', 'admin_exports', 'settings', 'smoke_test',
        ],

    ];
}


















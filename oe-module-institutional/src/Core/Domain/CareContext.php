<?php

/**
 * src/Core/Domain/CareContext.php
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

namespace OpenEMR\Modules\Institutional\Core\Domain;

/**
 * CareContext — care-setting display lenses for the institutional module.
 *
 * A context is a DISPLAY LENS, not an access-control boundary.
 * It controls which manifest features and menu groups are surfaced
 * for a given user/role. Clinicians can still navigate directly to
 * any page they have ACL access to.
 *
 * Feature ↔ group mapping (v0.17.0):
 *
 *   Tracking        : edt_board, alerts, throughput, scorecard,
 *                     timeline, handoff, multi_facility
 *   Operations      : intake, triage, tasks, mar, disposition,
 *                     ereferral, episode_documents, assignment,
 *                     bh_safety, bh_boarding, transfer_tracking,
 *                     command_center
 *   Protocols       : obs_protocols, obs_episodes, obs_billing
 *   Reporting       : cms_quality
 *   Admin           : context_manager, bed_mgmt, adt_lite,
 *                     facility_directory, hl7_adt, admin_exports, settings
 *   Assisted Living : al_board, al_care_plan, al_adl, al_incident, al_intake,
 *                     al_profile, al_vitals, al_fall_risk, al_mar
 *
 * Context resolution order: session cache → DB → DEFAULT_CONTEXT
 */
final class CareContext
{
    // ── Context keys ──────────────────────────────────────────────────────
    public const ED_ACUTE        = 'ED_ACUTE';
    public const OBS_STAY        = 'OBS_STAY';
    public const INPATIENT_STAY  = 'INPATIENT_STAY';
    public const BH              = 'BH';
    public const OPERATIONS      = 'OPERATIONS';
    public const ASSISTED_LIVING = 'ASSISTED_LIVING';
    public const FULL            = 'FULL';
    public const HOME_BASED_CARE = 'HOME_BASED_CARE';

    public const DEFAULT_CONTEXT = self::FULL;

    private const VALID = [
        self::ED_ACUTE,
        self::OBS_STAY,
        self::INPATIENT_STAY,
        self::BH,
        self::OPERATIONS,
        self::ASSISTED_LIVING,
        self::HOME_BASED_CARE,
        self::FULL,
    ];

    public static function isValid(string $key): bool
    {
        return in_array($key, self::VALID, true);
    }

    // ── Context definitions ───────────────────────────────────────────────

    /**
     * @return array<string, array{
     *   label: string, subtitle: string, icon: string,
     *   color: string, color_muted: string, audience: string,
     *   features: string[], menu_groups: string[], badge_color: string
     * }>
     */
    public static function all(): array
    {
        return [

            // ── Emergency Department ──────────────────────────────────────
            self::ED_ACUTE => [
                'label'       => 'Emergency Department',
                'subtitle'    => 'Acute care tracking & triage',
                'icon'        => '🏥',
                'color'       => '#e63946',
                'color_muted' => '#7c1a20',
                'audience'    => 'Charge nurse · ED provider · Triage nurse',
                'features'    => [
                    'edt_board', 'alerts', 'timeline', 'handoff',
                    'intake', 'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'institutional_billing',
                    'bh_safety', 'transfer_tracking',
                    'bed_mgmt', 'adt_lite', 'institutional_billing',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Admin'],
                'badge_color' => 'danger',
            ],

            // ── Observation Stay ──────────────────────────────────────────
            self::OBS_STAY => [
                'label'       => 'Observation Stay',
                'subtitle'    => 'Two-midnight & protocol management',
                'icon'        => '🏥',
                'color'       => '#2a9d8f',
                'color_muted' => '#134f4a',
                'audience'    => 'Hospitalist · OBS nurse · Case manager',
                'features'    => [
                    'alerts', 'timeline', 'throughput', 'handoff',
                    'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'obs_protocols', 'obs_episodes', 'obs_billing', 'institutional_billing',
                    'obs_stay', 'obs_start_picker',
                    'cms_quality',
                    'facility_directory',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin'],
                'badge_color' => 'success',
            ],
            // ── Inpatient Hospital Stay ────────────────────────────────────────────────
            // Covers the full admit -> stay -> discharge arc for med/surg,
            // telemetry, and general inpatient units.
            //
            // Admitting:       intake, ADT Lite, bed management, assignment
            // Stay management: MAR, tasks, care plan, clinical notes, care team,
            //                  episode documents, alerts, timeline, handoff,
            //                  throughput (LOS for case managers)
            // Discharge:       disposition, e-referral, transfer tracking
            // Reporting:       CMS quality, scorecard (attending outcomes)
            //
            // edt_board is included: fetchBoard has no episode-type filter so
            // it serves as the inpatient census until a dedicated floor board
            // is built. triage, BH, and OBS submodules are intentionally absent.
            self::INPATIENT_STAY => [
                'label'       => 'Inpatient Hospital Stay',
                'subtitle'    => 'Admit, stay management & discharge',
                'icon'        => '🏥',
                'color'       => '#457b9d',
                'color_muted' => '#1d3557',
                'audience'    => 'Admitting nurse · Hospitalist · Case manager · Floor nurse',
                'features'    => [
                    // Admitting
                    'intake', 'assignment', 'adt_lite', 'bed_mgmt',
                    // Stay management
                    'tasks', 'mar',
                    'care_plan', 'care_plan_launch',
                    'clinical_notes', 'clinical_notes_launch',
                    'clinical_notes_documents', 'clinical_notes_results',
                    'care_team', 'care_team_launch',
                    'episode_documents',
                    'alerts', 'timeline', 'handoff', 'throughput',
                    // Discharge
                    'disposition', 'ereferral', 'transfer_tracking',
                    // Dedicated inpatient board + admission + profile + discharge
                    'ip_board', 'ip_admission', 'ip_profile', 'ip_discharge',
                    // Reporting
                    'cms_quality', 'scorecard',
                    // Admin
                    'facility_directory', 'settings', 'institutional_billing',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Inpatient', 'Reporting', 'Admin'],
                'badge_color' => 'info',
            ],

            // ── Behavioral Health ─────────────────────────────────────────
            self::BH => [
                'label'       => 'Behavioral Health',
                'subtitle'    => 'BH safety, boarding & crisis coordination',
                'icon'        => '🧠',
                'color'       => '#7b2d8b',
                'color_muted' => '#3d1545',
                'audience'    => 'BH clinician · Social worker · Boarding coordinator',
                'features'    => [
                    'alerts', 'timeline', 'handoff',
                    'intake', 'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'institutional_billing',
                    'bh_safety', 'bh_boarding', 'transfer_tracking',
                    'facility_directory',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Admin'],
                'badge_color' => 'purple',
            ],

            // ── Operations & Admin ────────────────────────────────────────
            self::OPERATIONS => [
                'label'       => 'Operations & Admin',
                'subtitle'    => 'Multi-facility oversight & reporting',
                'icon'        => '📊',
                'color'       => '#f4a261',
                'color_muted' => '#7a4e28',
                'audience'    => 'Nursing director · Admin · Quality officer',
                'features'    => [
                    'edt_board', 'alerts', 'throughput', 'scorecard',
                    'handoff', 'multi_facility',
                    'command_center', 'transfer_tracking',
                    'obs_billing',
                    'cms_quality',
                    'bed_mgmt', 'adt_lite', 'institutional_billing', 'facility_directory',
                    'hl7_adt', 'admin_exports', 'settings', 'smoke_test',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin'],
                'badge_color' => 'warning',
            ],

            // ── Assisted Living ───────────────────────────────────────────
            // Care aide, LPN, AL Director, Activity coordinator.
            // Long-term residency model: census board → ADL charting →
            // care plan management → incident reporting.
            // Reuses: tasks, mar (standing orders), assignment, handoff,
            //         alerts, episode_documents, ereferral.
            // New AL-specific: al_board, al_care_plan, al_adl,
            //                  al_incident, al_intake, al_profile,
            //                  al_vitals, al_fall_risk, al_mar.
            self::ASSISTED_LIVING => [
                'label'       => 'Assisted Living',
                'subtitle'    => 'Resident census, care plans & ADL tracking',
                'icon'        => '🏡',
                'color'       => '#4a7c59',
                'color_muted' => '#243d2c',
                'audience'    => 'Care aide · LPN · AL Director · Activity coordinator',
                'features'    => [
                    // AL-specific submodules (phase 1)
                    'al_board', 'al_care_plan', 'al_adl',
                    'al_incident', 'al_intake',
                    // AL-specific submodules (phase 2)
                    'al_profile', 'al_vitals', 'al_fall_risk', 'al_mar',
            'al_discharge',
            'al_activity',
            'al_handoff',
                    // Shared submodules surfaced in AL context
                    'tasks', 'mar', 'assignment', 'handoff', 'institutional_billing',
                    'alerts', 'episode_documents', 'ereferral',
                    // Admin: room/unit management + settings
                    'bed_mgmt', 'settings',
                ],
                'menu_groups' => ['Assisted Living', 'Operations', 'Admin'],
                'badge_color' => 'success',
            ],

            // ── Home-Based Care ──────────────────────────────────────────────
            self::HOME_BASED_CARE => [
                'label'       => 'Home-Based Care',
                'subtitle'    => 'Community & house-call visit management',
                'icon'        => '🏡',
                'color'       => '#4a7c59',
                'color_muted' => '#243d2c',
                'audience'    => 'Community nurse · Physiotherapist · Case manager · Social worker',
                'features'    => [
                    // HBC-specific submodules
                    'hbc_board', 'hbc_intake', 'hbc_profile',
                    'hbc_visit', 'hbc_schedule', 'hbc_vitals',
                    'hbc_fall_risk', 'hbc_handoff', 'hbc_discharge',
                    'hbc_comm_log',
                    // AL submodules reused by HBC
                    'al_vitals', 'al_fall_risk', 'al_incident',
                    // Shared clinical submodules
                    'care_plan', 'care_plan_launch',
                    'clinical_notes', 'clinical_notes_launch',
                    'care_team', 'care_team_launch',
                    'episode_documents', 'ereferral',
                    'tasks', 'mar', 'alerts', 'observations',
                    // Admin
                    'bed_mgmt', 'settings',
                ],
                'menu_groups' => ['Home-Based Care', 'Tracking', 'Operations', 'Admin'],
                'badge_color' => 'success',
            ],

            // ── Full Access ───────────────────────────────────────────────
            self::FULL => [
                'label'       => 'Full Access',
                'subtitle'    => 'All submodules — no filtering',
                'icon'        => '⚡',
                'color'       => '#4361ee',
                'color_muted' => '#1a2a7c',
                'audience'    => 'Superuser · Module administrator',
                'features'    => ['*'],
                'menu_groups' => ['*'],
                'badge_color' => 'primary',
            ],

        ];
    }

    // ── Surface helpers ───────────────────────────────────────────────────

    /**
     * Returns metadata for one context key.
     * Falls back to DEFAULT_CONTEXT if the key is unknown.
     * @return array<string,mixed>
     */
    public static function meta(string $key): array
    {
        return self::all()[$key] ?? self::all()[self::DEFAULT_CONTEXT];
    }

    /**
     * Whether a manifest feature is surfaced in the given context.
     *
     * Always true for FULL.
     * Features not listed in any context are treated as visible
     * (conservative default — unassigned features don't silently disappear).
     */
    public static function featureSurfaced(string $contextKey, string $feature): bool
    {
        if ($contextKey === self::FULL) {
            return true;
        }
        $features = self::meta($contextKey)['features'] ?? ['*'];
        if (in_array('*', $features, true)) {
            return true;
        }
        return in_array($feature, $features, true);
    }

    /**
     * Whether a menu group is surfaced in the given context.
     * Always true for FULL.
     */
    public static function groupSurfaced(string $contextKey, string $group): bool
    {
        if ($contextKey === self::FULL) {
            return true;
        }
        $groups = self::meta($contextKey)['menu_groups'] ?? ['*'];
        if (in_array('*', $groups, true)) {
            return true;
        }
        return in_array($group, $groups, true);
    }

    /**
     * Returns all valid context keys in display order.
     * @return string[]
     */
    public static function validKeys(): array
    {
        return self::VALID;
    }
}




























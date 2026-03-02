<?php

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
 * Feature ↔ group mapping (v0.11.0):
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
 *   Assisted Living : al_board, al_care_plan, al_adl, al_incident, al_intake
 *
 * Context resolution order: session cache → DB → DEFAULT_CONTEXT
 */
final class CareContext
{
    // ── Context keys ──────────────────────────────────────────────────────
    public const ED_ACUTE        = 'ED_ACUTE';
    public const OBS_STAY        = 'OBS_STAY';
    public const BH              = 'BH';
    public const OPERATIONS      = 'OPERATIONS';
    public const ASSISTED_LIVING = 'ASSISTED_LIVING';
    public const FULL            = 'FULL';

    public const DEFAULT_CONTEXT = self::FULL;

    private const VALID = [
        self::ED_ACUTE,
        self::OBS_STAY,
        self::BH,
        self::OPERATIONS,
        self::ASSISTED_LIVING,
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
                'icon'        => '🚨',
                'color'       => '#e63946',
                'color_muted' => '#7c1a20',
                'audience'    => 'Charge nurse · ED provider · Triage nurse',
                'features'    => [
                    'edt_board', 'alerts', 'timeline', 'handoff',
                    'intake', 'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'bh_safety', 'transfer_tracking',
                    'bed_mgmt', 'adt_lite',
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
                    'obs_protocols', 'obs_episodes', 'obs_billing',
                    'obs_stay', 'obs_start_picker',
                    'cms_quality',
                    'facility_directory',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin'],
                'badge_color' => 'success',
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
                    'bed_mgmt', 'adt_lite', 'facility_directory',
                    'hl7_adt', 'admin_exports', 'settings',
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
            //                  al_incident, al_intake.
            self::ASSISTED_LIVING => [
                'label'       => 'Assisted Living',
                'subtitle'    => 'Resident census, care plans & ADL tracking',
                'icon'        => '🏡',
                'color'       => '#4a7c59',
                'color_muted' => '#243d2c',
                'audience'    => 'Care aide · LPN · AL Director · Activity coordinator',
                'features'    => [
                    // AL-specific submodules
                    'al_board', 'al_care_plan', 'al_adl',
                    'al_incident', 'al_intake',
                    // Shared submodules surfaced in AL context
                    'tasks', 'mar', 'assignment', 'handoff',
                    'alerts', 'episode_documents', 'ereferral',
                    // Admin: room/unit management + settings
                    'bed_mgmt', 'settings',
                ],
                'menu_groups' => ['Assisted Living', 'Operations', 'Admin'],
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

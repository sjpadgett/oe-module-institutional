<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Core\Domain;

/**
 * CareContext — five care-setting lenses for the institutional module.
 *
 * A context is a DISPLAY LENS, not an access-control boundary.
 * It controls which manifest features and menu groups are surfaced
 * for a given user/role. Clinicians can still navigate directly to
 * any page they have ACL access to.
 *
 * Feature ↔ group mapping (corrected manifest v0.9.7):
 *
 *   Tracking  : edt_board, alerts, throughput, scorecard,
 *               timeline, handoff, multi_facility
 *   Operations: intake, triage, tasks, mar, disposition,
 *               ereferral, episode_documents, assignment,
 *               bh_safety, bh_boarding, transfer_tracking,
 *               command_center
 *   Protocols : obs_protocols, obs_episodes, obs_billing
 *   Reporting : cms_quality
 *   Admin     : context_manager, bed_mgmt, adt_lite,
 *               facility_directory, hl7_adt, admin_exports, settings
 *
 *   Diversion : diversion
 *
 * Context resolution order: session cache → DB → DEFAULT_CONTEXT
 */
final class CareContext
{
    // ── Context keys ──────────────────────────────────────────────────────
    public const ED_ACUTE   = 'ED_ACUTE';
    public const OBS_STAY   = 'OBS_STAY';
    public const BH         = 'BH';
    public const OPERATIONS = 'OPERATIONS';
    public const FULL       = 'FULL';

    public const DEFAULT_CONTEXT = self::ED_ACUTE;

    private const VALID = [
        self::ED_ACUTE,
        self::OBS_STAY,
        self::BH,
        self::OPERATIONS,
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
            // Charge nurse, ED provider, Triage nurse.
            // Needs the full per-patient ED workflow: board → triage →
            // tasks/MAR → disposition/transfer. Throughput & scorecard are
            // management tools — not shown here. No OBS protocols.
            self::ED_ACUTE => [
                'label'       => 'Emergency Department',
                'subtitle'    => 'Acute care tracking & triage',
                'icon'        => '🚨',
                'color'       => '#e63946',
                'color_muted' => '#7c1a20',
                'audience'    => 'Charge nurse · ED provider · Triage nurse',
                'features'    => [
                    // Tracking group items surfaced
                    'edt_board', 'alerts', 'timeline', 'handoff',
                    // Operations group items surfaced
                    'intake', 'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'bh_safety', 'transfer_tracking',
                    // Admin group items surfaced (bed/location management)
                    'bed_mgmt', 'adt_lite', 'diversion',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Admin'],
                'badge_color' => 'danger',
            ],

            // ── Observation Stay ──────────────────────────────────────────
            // Hospitalist, OBS nurse, Case manager.
            // Needs OBS protocol management, billing compliance, and the
            // per-patient operations workflow (vitals → MAR → disposition).
            // Throughput is useful for LOS monitoring. ED board not needed.
            self::OBS_STAY => [
                'label'       => 'Observation Stay',
                'subtitle'    => 'Two-midnight & protocol management',
                'icon'        => '🏥',
                'color'       => '#2a9d8f',
                'color_muted' => '#134f4a',
                'audience'    => 'Hospitalist · OBS nurse · Case manager',
                'features'    => [
                    // Tracking group items surfaced
                    'alerts', 'timeline', 'throughput', 'handoff',
                    // Operations group items surfaced
                    'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    // Protocols group — all three OBS items
                    'obs_protocols', 'obs_episodes', 'obs_billing',
                    'obs_stay', 'obs_start_picker',
                    // Reporting group
                    'cms_quality',
                    // Admin: facility directory for transfers/referrals
                    'facility_directory',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin'],
                'badge_color' => 'success',
            ],

            // ── Behavioral Health ─────────────────────────────────────────
            // BH clinician, Social worker, Boarding coordinator.
            // Needs safety assessments, boarding management, and placement
            // workflows. OBS protocols and financial reporting not relevant.
            // Facility directory needed for placement searches.
            self::BH => [
                'label'       => 'Behavioral Health',
                'subtitle'    => 'Safety, boarding & placement workflows',
                'icon'        => '🧠',
                'color'       => '#7b2d8b',
                'color_muted' => '#3d1647',
                'audience'    => 'BH clinician · Social worker · Boarding coordinator',
                'features'    => [
                    // Tracking group items surfaced
                    'alerts', 'timeline', 'handoff',
                    // Operations group — BH-relevant per-patient workflow
                    'intake', 'triage', 'tasks', 'mar', 'disposition',
                    'ereferral', 'episode_documents', 'assignment',
                    'bh_safety', 'bh_boarding', 'transfer_tracking',
                    // Admin: directory for placement facility searches
                    'facility_directory',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Admin'],
                'badge_color' => 'purple',
            ],

            // ── Operations & Admin ────────────────────────────────────────
            // Nursing director, Admin, Quality officer.
            // Needs aggregate dashboards, reporting, and system admin.
            // Per-patient clinical workflows (triage, MAR, etc.) are not
            // surfaced — that would add noise for this role. OBS billing
            // and CMS quality are financial/quality oversight tools.
            self::OPERATIONS => [
                'label'       => 'Operations & Admin',
                'subtitle'    => 'Multi-facility oversight & reporting',
                'icon'        => '📊',
                'color'       => '#f4a261',
                'color_muted' => '#7a4e28',
                'audience'    => 'Nursing director · Admin · Quality officer',
                'features'    => [
                    // Tracking group — oversight dashboards
                    'edt_board', 'alerts', 'throughput', 'scorecard',
                    'handoff', 'multi_facility',
                    // Operations group — command centre & transfers only
                    'command_center', 'transfer_tracking',
                    // Protocols group — billing compliance only
                    'obs_billing',
                    // Reporting group
                    'cms_quality',
                    // Admin group — full admin toolset
                    'bed_mgmt', 'adt_lite', 'facility_directory',
                    'hl7_adt', 'admin_exports', 'settings', 'diversion',
                ],
                'menu_groups' => ['Tracking', 'Operations', 'Protocols', 'Reporting', 'Admin'],
                'badge_color' => 'warning',
            ],

            // ── Full Access ───────────────────────────────────────────────
            // Superuser, Module administrator.
            // Wildcard — no filtering applied.
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
    public static function keys(): array
    {
        return self::VALID;
    }
}

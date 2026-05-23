<?php

/**
 * src/Core/Ui/partials/context_help.php
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

if (!function_exists('oei_context_help_topic')) {
    /**
     * @return array<string,mixed>
     */
    function oei_context_help_topic(string $topic, array $opts = []): array
    {
        $facilityName = trim((string)($opts['facility_name'] ?? ''));
        $safeFacility = $facilityName !== '' ? htmlspecialchars($facilityName) : xlt('this facility');

        $topics = [
            'settings' => [
                'title' => xlt('Settings Help'),
                'summary' => xlt('How facility profile settings work'),
                'intro' => xlt('Settings edits the facility-default profile. This is where you confirm what the selected OpenEMR facility is installed as and how users land inside the module.'),
                'bullets' => [
                    xlt('Facility Installed As is the main choice. Pick the operational purpose for the selected facility, such as Assisted Living, Inpatient, or Home-Based Care.'),
                    xlt('Default Work Mode is the first context used when a user has no saved preference for this facility.'),
                    xlt('Facility Home Page is where users land after the module resolves their facility profile.'),
                    xlt('Available Work Modes limit what the context switcher offers inside this facility.'),
                    xlt('This page saves the facility-default row in oei_facility_profile for the selected facility.'),
                ],
                'tip' => sprintf(xlt('Think of %s as the owner of the app shape. Users may switch work modes inside the facility, but they do not redefine what the facility is installed as.'), $safeFacility),
            ],
            'manifest' => [
                'title' => xlt('Advanced Feature Overrides Help'),
                'summary' => xlt('How advanced feature overrides work'),
                'intro' => xlt('Use this page only after the facility profile is set. It fine-tunes the selected facility’s feature set without changing the facility’s installed purpose.'),
                'bullets' => [
                    xlt('The base manifest.json stays the module capability catalog.'),
                    xlt('Feature changes here are advanced overrides for the selected facility only.'),
                    xlt('Quick Profiles are a fast way to start from a recommended feature bundle, then adjust exceptions.'),
                    xlt('If you are deciding what the facility is installed as, use Settings or Setup Wizard instead of this page.'),
                ],
                'tip' => sprintf(xlt('Good rule: use Settings to define %s, then use this page only for exceptions.'), $safeFacility),
            ],
            'wizard' => [
                'title' => xlt('Setup Wizard Help'),
                'summary' => xlt('How to place a facility into its installed purpose'),
                'intro' => xlt('The Setup Wizard is the guided path for first-time facility setup. It writes the facility-default profile for the selected OpenEMR facility.'),
                'bullets' => [
                    xlt('Step 1 chooses the OpenEMR facility you are configuring.'),
                    xlt('Step 2 chooses what the facility is installed as. That choice drives the recommended default work mode, home page, and available work modes.'),
                    xlt('Step 3 lets you review the generated defaults before saving.'),
                    xlt('Saving marks setup complete for the facility and stores the profile in oei_facility_profile.'),
                ],
                'tip' => sprintf(xlt('Use the wizard when bringing %s online for the first time. Use Settings later for adjustments.'), $safeFacility),
            ],
            'context' => [
                'title' => xlt('Context Manager Help'),
                'summary' => xlt('How work mode switching works'),
                'intro' => xlt('Context Manager only changes the active work mode inside the current facility. It does not change the facility’s installed purpose.'),
                'bullets' => [
                    xlt('Only work modes enabled for this facility profile appear here.'),
                    xlt('The selected work mode is saved per user and per facility.'),
                    xlt('The facility profile still controls the facility’s default work mode and available work modes.'),
                    xlt('If the wrong options appear here, adjust the facility profile in Settings instead of changing this page.'),
                ],
                'tip' => sprintf(xlt('Think of context as a lens for working inside %s, not as a new facility setup.'), $safeFacility),
            ],
        ];

        return $topics[$topic] ?? [
            'title' => xlt('Page Help'),
            'summary' => xlt('How this page works'),
            'intro' => xlt('This page participates in the facility-based Institutional setup flow.'),
            'bullets' => [
                xlt('OpenEMR chooses the facility from the logged-in user.'),
                xlt('The facility profile decides what the app is installed as.'),
                xlt('Context only changes the current work mode inside that facility.'),
            ],
            'tip' => xlt('Use the selected facility as the anchor for setup and day-to-day workflow.'),
        ];
    }
}

if (!function_exists('oei_render_context_help')) {
    /**
     * Render a small page-context help card.
     *
     * @param string $topic settings|manifest|wizard|context
     * @param array<string,mixed> $opts
     */
    function oei_render_context_help(string $topic, array $opts = []): void
    {
        $cfg = oei_context_help_topic($topic, $opts);
        $id = 'oei-help-' . preg_replace('/[^a-z0-9_\-]+/i', '-', $topic);

        echo '<details id="' . htmlspecialchars($id) . '" class="card shadow-sm mb-3">';
        echo '<summary class="card-header d-flex align-items-center justify-content-between gap-2" style="cursor:pointer;list-style:none;">';
        echo '<span class="fw-semibold">&#9432;&nbsp;' . htmlspecialchars((string)$cfg['summary']) . '</span>';
        echo '<span class="small text-muted">' . htmlspecialchars((string)xlt('Expand')) . '</span>';
        echo '</summary>';
        echo '<div class="card-body">';
        echo '<h2 class="h6 mb-2">' . htmlspecialchars((string)$cfg['title']) . '</h2>';
        echo '<p class="text-muted mb-3">' . htmlspecialchars((string)$cfg['intro']) . '</p>';
        echo '<ul class="mb-3">';
        foreach ((array)($cfg['bullets'] ?? []) as $bullet) {
            echo '<li class="mb-2">' . htmlspecialchars((string)$bullet) . '</li>';
        }
        echo '</ul>';
        echo '<div class="alert alert-info py-2 mb-0 small">' . htmlspecialchars((string)$cfg['tip']) . '</div>';
        echo '</div>';
        echo '</details>';
    }
}




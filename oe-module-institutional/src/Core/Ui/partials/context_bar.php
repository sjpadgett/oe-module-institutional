<?php
/**
 * context_bar.php  — care context indicator bar
 *
 * Injected by _bootstrap.php. Outputs a fixed 36px bar at the top of
 * every module page. Uses CSS custom properties set by _bootstrap.php:
 *   --oei-ctx-color  (hex, e.g. #e63946)
 *
 * No JS frameworks. One fetch() call on switch; otherwise zero requests.
 *
 * Requires in scope (set by _bootstrap.php):
 *   $activeContext  string   — CareContext key
 *   $ctxMeta        array    — CareContext::meta($activeContext)
 *   $facilityId     int
 *   $manifest       Manifest
 */

if (!isset($activeContext, $ctxMeta, $facilityId, $manifest)) {
    return;
}

$ctxLabel  = htmlspecialchars((string)($ctxMeta['label'] ?? $activeContext));
$ctxIcon   = htmlspecialchars((string)($ctxMeta['icon'] ?? ''));
$ctxColor  = htmlspecialchars((string)($ctxMeta['color'] ?? '#4361ee'));
$ctxMuted  = htmlspecialchars((string)($ctxMeta['color_muted'] ?? '#1a2a7c'));
$managerUrl = htmlspecialchars(
    '/interface/modules/custom_modules/oe-module-institutional/public/context_manager.php'
    . '?facility_id=' . urlencode((string)$facilityId)
    . '&return=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? ''))
);
?>
<style>
:root {
    --oei-ctx-color: <?= $ctxColor ?>;
    --oei-ctx-muted: <?= $ctxMuted ?>;
    --oei-bar-h: 36px;
}

/* Push page content down so the bar doesn't overlap */
body { padding-top: var(--oei-bar-h) !important; }

#oei-context-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--oei-bar-h);
    z-index: 10000;
    background: linear-gradient(135deg, #0d0f1a 0%, #111827 100%);
    border-bottom: 2px solid var(--oei-ctx-color);
    display: flex;
    align-items: center;
    padding: 0 12px;
    gap: 10px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    font-size: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.45);
    transition: border-color 0.3s ease;
}

#oei-context-bar .ctx-pip {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--oei-ctx-color);
    box-shadow: 0 0 6px var(--oei-ctx-color);
    flex-shrink: 0;
    animation: oei-pulse 2.5s ease-in-out infinite;
}

@keyframes oei-pulse {
    0%, 100% { opacity: 1; box-shadow: 0 0 6px var(--oei-ctx-color); }
    50%       { opacity: 0.7; box-shadow: 0 0 12px var(--oei-ctx-color); }
}

#oei-context-bar .ctx-icon {
    font-size: 14px;
    line-height: 1;
    flex-shrink: 0;
}

#oei-context-bar .ctx-label {
    color: #e2e8f0;
    font-weight: 600;
    letter-spacing: 0.02em;
    white-space: nowrap;
}

#oei-context-bar .ctx-sep {
    color: #374151;
    font-size: 16px;
    flex-shrink: 0;
}

#oei-context-bar .ctx-module-pills {
    display: flex;
    gap: 4px;
    flex-wrap: nowrap;
    overflow: hidden;
    flex: 1;
    min-width: 0;
}

#oei-context-bar .ctx-pill {
    display: inline-flex;
    align-items: center;
    padding: 1px 7px;
    border-radius: 10px;
    background: rgba(255,255,255,0.06);
    color: #9ca3af;
    font-size: 10px;
    white-space: nowrap;
    border: 1px solid rgba(255,255,255,0.08);
    transition: background 0.15s, color 0.15s;
}

#oei-context-bar .ctx-pill:hover {
    background: rgba(255,255,255,0.12);
    color: #e2e8f0;
}

#oei-context-bar .ctx-pill.active {
    background: rgba(255,255,255,0.10);
    color: #e2e8f0;
    border-color: var(--oei-ctx-color);
}

#oei-context-bar .ctx-switch-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 10px;
    border-radius: 6px;
    background: rgba(255,255,255,0.06);
    border: 1px solid rgba(255,255,255,0.14);
    color: #e2e8f0;
    font-size: 11px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    flex-shrink: 0;
    transition: background 0.15s, border-color 0.15s, transform 0.1s;
}

#oei-context-bar .ctx-switch-btn:hover {
    background: var(--oei-ctx-color);
    border-color: var(--oei-ctx-color);
    color: #fff;
    text-decoration: none;
    transform: translateY(-1px);
}

#oei-context-bar .ctx-switch-btn .ctx-caret {
    font-size: 9px;
    opacity: 0.7;
}

/* Quick-switch dropdown overlay */
#oei-ctx-dropdown {
    display: none;
    position: fixed;
    top: var(--oei-bar-h);
    right: 12px;
    background: #111827;
    border: 1px solid #1f2937;
    border-top: 2px solid var(--oei-ctx-color);
    border-radius: 0 0 10px 10px;
    z-index: 9999;
    min-width: 260px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.6);
    padding: 6px 0;
}

#oei-ctx-dropdown.open {
    display: block;
    animation: oei-drop-in 0.18s ease;
}

@keyframes oei-drop-in {
    from { opacity: 0; transform: translateY(-6px); }
    to   { opacity: 1; transform: translateY(0); }
}

.oei-ctx-opt {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 16px;
    cursor: pointer;
    color: #cbd5e1;
    font-size: 12px;
    transition: background 0.12s;
    border-left: 3px solid transparent;
    text-decoration: none;
}

.oei-ctx-opt:hover {
    background: rgba(255,255,255,0.05);
    color: #f1f5f9;
    text-decoration: none;
}

.oei-ctx-opt.current {
    border-left-color: var(--oei-ctx-color);
    background: rgba(255,255,255,0.04);
    color: #f1f5f9;
}

.oei-ctx-opt .opt-icon { font-size: 16px; line-height:1; }

.oei-ctx-opt .opt-text .opt-label {
    font-weight: 600;
    font-size: 12px;
}
.oei-ctx-opt .opt-text .opt-sub {
    font-size: 10px;
    color: #6b7280;
    margin-top: 1px;
}

.oei-ctx-opt .opt-check {
    margin-left: auto;
    color: var(--oei-ctx-color);
    font-size: 14px;
}

.oei-ctx-divider {
    border: none;
    border-top: 1px solid #1f2937;
    margin: 4px 0;
}

.oei-ctx-full-link {
    display: block;
    text-align: center;
    padding: 7px;
    font-size: 11px;
    color: #6b7280;
    cursor: pointer;
    text-decoration: none;
    transition: color 0.12s;
}
.oei-ctx-full-link:hover {
    color: var(--oei-ctx-color);
    text-decoration: none;
}
</style>

<?php
// Build pill list: show current context's active features as pills (max 6 for space)
// NOTE: No 'use' allowed here — this partial is included mid-file.
// Using fully-qualified class name instead.
$ctxFeatures = $ctxMeta['features'] ?? [];
$isFullAccess = ($ctxFeatures === ['*'] || $activeContext === \OpenEMR\Modules\Institutional\Core\Domain\CareContext::FULL);

// Map feature keys to short readable labels
$featureLabels = [
    'edt_board'         => 'ED Board',
    'intake'            => 'Intake',
    'triage'            => 'Triage',
    'tasks'             => 'Tasks',
    'alerts'            => 'Alerts',
    'assignment'        => 'Assignments',
    'handoff'           => 'Handoff',
    'disposition'       => 'Disposition',
    'transfer_tracking' => 'Transfers',
    'bh_safety'         => 'BH Safety',
    'mar'               => 'MAR',
    'ereferral'         => 'eReferral',
    'obs_stay'          => 'OBS Stay',
    'obs_protocols'     => 'Protocols',
    'obs_billing'       => 'OBS Billing',
    'cms_quality'       => 'CMS Quality',
    'bh_boarding'       => 'BH Boarding',
    'multi_facility'    => 'Multi-Facility',
    'scorecard'         => 'Scorecard',
    'throughput'        => 'Throughput',
    'settings'          => 'Settings',
    'facility_directory'=> 'Directory',
    'hl7_adt'           => 'HL7 ADT',
    'admin_exports'     => 'Exports',
    'bed_mgmt'          => 'Beds',
    'command_center'    => 'Command Center',
    'timeline'          => 'Timeline',
    'episode_documents' => 'Documents',
    'obs_episodes'      => 'OBS Episodes',
    'adt_lite'          => 'ADT',
    'obs_start_picker'  => 'OBS Start',
    'diversion'         => 'Diversion',
];

$pills = [];
if ($isFullAccess) {
    $pills = [['label' => 'All Submodules', 'active' => false]];
} else {
    foreach ($ctxFeatures as $f) {
        if (isset($featureLabels[$f]) && $manifest->featureEnabled($f)) {
            $pills[] = ['label' => $featureLabels[$f], 'active' => true];
        }
    }
}
$pillsDisplay = array_slice($pills, 0, 7);
$hiddenCount  = count($pills) - count($pillsDisplay);

// All contexts for quick-switch dropdown
$allContexts = \OpenEMR\Modules\Institutional\Core\Domain\CareContext::all();
?>
<div id="oei-context-bar">
    <div class="ctx-pip"></div>
    <span class="ctx-icon"><?= $ctxIcon ?></span>
    <span class="ctx-label"><?= $ctxLabel ?></span>
    <span class="ctx-sep">|</span>
    <div class="ctx-module-pills" aria-label="Active submodules">
        <?php foreach ($pillsDisplay as $pill): ?>
            <span class="ctx-pill<?= $pill['active'] ? ' active' : '' ?>">
                <?= htmlspecialchars($pill['label']) ?>
            </span>
        <?php endforeach; ?>
        <?php if ($hiddenCount > 0): ?>
            <span class="ctx-pill">+<?= $hiddenCount ?> more</span>
        <?php endif; ?>
    </div>
    <button class="ctx-switch-btn"
            id="oei-ctx-toggle"
            aria-haspopup="true"
            aria-expanded="false"
            type="button">
        <?= xlt('Switch Context') ?> <span class="ctx-caret">▼</span>
    </button>
</div>

<!-- Quick-switch dropdown -->
<div id="oei-ctx-dropdown" role="menu">
    <?php foreach ($allContexts as $key => $meta): ?>
        <?php
        $isCurrent  = ($key === $activeContext);
        $optColor   = htmlspecialchars((string)($meta['color'] ?? '#666'));
        $optLabel   = htmlspecialchars((string)($meta['label'] ?? $key));
        $optSub     = htmlspecialchars((string)($meta['subtitle'] ?? ''));
        $optIcon    = htmlspecialchars((string)($meta['icon'] ?? ''));
        $switchUrl  = htmlspecialchars(
            '/interface/modules/custom_modules/oe-module-institutional/public/context_switch.php'
            . '?context=' . urlencode($key)
            . '&facility_id=' . urlencode((string)$facilityId)
            . '&return=' . urlencode((string)($_SERVER['REQUEST_URI'] ?? ''))
        );
        ?>
        <a href="<?= $switchUrl ?>"
           class="oei-ctx-opt<?= $isCurrent ? ' current' : '' ?>"
           style="<?= $isCurrent ? '--oei-ctx-color:' . $optColor . ';' : '' ?>"
           role="menuitem">
            <span class="opt-icon"><?= $optIcon ?></span>
            <span class="opt-text">
                <div class="opt-label"><?= $optLabel ?></div>
                <div class="opt-sub"><?= $optSub ?></div>
            </span>
            <?php if ($isCurrent): ?>
                <span class="opt-check">✓</span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
    <hr class="oei-ctx-divider">
    <a href="<?= $managerUrl ?>" class="oei-ctx-full-link" role="menuitem">
        ⚙ <?= xlt('Manage context settings') ?> →
    </a>
</div>

<script>
(function () {
    'use strict';
    var btn      = document.getElementById('oei-ctx-toggle');
    var dropdown = document.getElementById('oei-ctx-dropdown');
    if (!btn || !dropdown) return;

    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        var isOpen = dropdown.classList.contains('open');
        dropdown.classList.toggle('open', !isOpen);
        btn.setAttribute('aria-expanded', String(!isOpen));
    });

    document.addEventListener('click', function (e) {
        if (!dropdown.contains(e.target) && e.target !== btn) {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
            btn.focus();
        }
    });
}());
</script>

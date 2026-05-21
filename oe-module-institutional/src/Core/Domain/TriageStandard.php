<?php

/**
 * src/Core/Domain/TriageStandard.php
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
 * TriageStandard
 *
 * Central configuration for triage acuity systems.
 * The acuity integer 1–5 is stored as-is in oei_episode.acuity_esi and
 * oei_triage.acuity_esi regardless of the active standard — the column
 * name is a legacy artifact, the value is always a severity level 1–5.
 * This class controls ONLY how that integer is labeled and colored in
 * the UI. No schema changes are required to switch standards.
 *
 * Supported standards:
 *   ESI  — Emergency Severity Index         (United States / Canada)
 *   MTS  — Manchester Triage System         (UK, Europe, Australasia, Brazil, Portugal)
 *   CTAS — Canadian Triage & Acuity Scale   (Canada)
 *
 * Feature flag:  manifest.json  "mts_triage": true/false
 * Facility setting: oei_settings  triage_standard = ESI | MTS | CTAS
 *
 * When mts_triage is false (the default), _bootstrap.php always creates
 * TriageStandard::fromCode('ESI'), and every method returns values
 * identical to the existing hardcoded ESI strings — zero behavior change.
 *
 * Severity mapping (1 = most severe in all three standards):
 *   ESI 1  Immediate          = MTS 1  Immediate       = CTAS 1  Resuscitation
 *   ESI 2  Emergent           = MTS 2  Very Urgent      = CTAS 2  Emergent
 *   ESI 3  Urgent             = MTS 3  Urgent           = CTAS 3  Urgent
 *   ESI 4  Less Urgent        = MTS 4  Standard         = CTAS 4  Less Urgent
 *   ESI 5  Non-Urgent         = MTS 5  Non-Urgent       = CTAS 5  Non-Urgent
 */
final class TriageStandard
{
    // ── Standard codes ────────────────────────────────────────────────────
    public const ESI = 'ESI';
    public const MTS = 'MTS';
    public const CTAS = 'CTAS';

    // ── Standard definitions ──────────────────────────────────────────────
    private const DEFINITIONS = [

        self::ESI => [
            'name' => 'Emergency Severity Index',
            'short_name' => 'ESI',
            'region' => 'United States · Canada',
            'css_prefix' => 'esi',
            'col_header' => 'ESI',
            'levels' => [
                1 => ['label' => 'ESI 1 — Immediate', 'short' => '1', 'color_bg' => '#b71c1c', 'color_fg' => '#ffffff'],
                2 => ['label' => 'ESI 2 — Emergent', 'short' => '2', 'color_bg' => '#e65100', 'color_fg' => '#ffffff'],
                3 => ['label' => 'ESI 3 — Urgent', 'short' => '3', 'color_bg' => '#f9a825', 'color_fg' => '#000000'],
                4 => ['label' => 'ESI 4 — Less Urgent', 'short' => '4', 'color_bg' => '#2e7d32', 'color_fg' => '#ffffff'],
                5 => ['label' => 'ESI 5 — Non-Urgent', 'short' => '5', 'color_bg' => '#1565c0', 'color_fg' => '#ffffff'],
            ],
        ],

        self::MTS => [
            'name' => 'Manchester Triage System',
            'short_name' => 'MTS',
            'region' => 'United Kingdom · Europe · Australasia · Brazil · Portugal',
            'css_prefix' => 'mts',
            'col_header' => 'MTS',
            'levels' => [
                1 => ['label' => 'MTS 1 — Immediate', 'short' => 'I', 'color_bg' => '#b71c1c', 'color_fg' => '#ffffff'],
                2 => ['label' => 'MTS 2 — Very Urgent', 'short' => 'VU', 'color_bg' => '#e65100', 'color_fg' => '#ffffff'],
                3 => ['label' => 'MTS 3 — Urgent', 'short' => 'U', 'color_bg' => '#f9a825', 'color_fg' => '#000000'],
                4 => ['label' => 'MTS 4 — Standard', 'short' => 'S', 'color_bg' => '#2e7d32', 'color_fg' => '#ffffff'],
                5 => ['label' => 'MTS 5 — Non-Urgent', 'short' => 'NU', 'color_bg' => '#0277bd', 'color_fg' => '#ffffff'],
            ],
        ],

        self::CTAS => [
            'name' => 'Canadian Triage & Acuity Scale',
            'short_name' => 'CTAS',
            'region' => 'Canada',
            'css_prefix' => 'ctas',
            'col_header' => 'CTAS',
            'levels' => [
                1 => ['label' => 'CTAS 1 — Resuscitation', 'short' => 'R', 'color_bg' => '#b71c1c', 'color_fg' => '#ffffff'],
                2 => ['label' => 'CTAS 2 — Emergent', 'short' => 'E', 'color_bg' => '#e65100', 'color_fg' => '#ffffff'],
                3 => ['label' => 'CTAS 3 — Urgent', 'short' => 'U', 'color_bg' => '#f9a825', 'color_fg' => '#000000'],
                4 => ['label' => 'CTAS 4 — Less Urgent', 'short' => 'LU', 'color_bg' => '#2e7d32', 'color_fg' => '#ffffff'],
                5 => ['label' => 'CTAS 5 — Non-Urgent', 'short' => 'NU', 'color_bg' => '#1565c0', 'color_fg' => '#ffffff'],
            ],
        ],

    ];

    // ── Constructor ───────────────────────────────────────────────────────

    private string $standard;

    /**
     * Color overrides loaded from facility settings.
     * Shape: [standardCode => [level => ['bg' => '#RRGGBB', 'fg' => '#RRGGBB']]]
     */
    private array $colorOverrides = [];

    public function __construct(string $standard = self::ESI)
    {
        $this->standard = array_key_exists($standard, self::DEFINITIONS)
            ? $standard
            : self::ESI;
    }

    public static function fromCode(string $code): self
    {
        return new self($code);
    }

    /**
     * Load triage badge color overrides from a facility settings array.
     *
     * Expected keys:
     *   triage_color_ESI_1 ... triage_color_ESI_5
     *   triage_color_MTS_1 ... triage_color_MTS_5
     *   triage_color_CTAS_1 ... triage_color_CTAS_5
     */
    public function applyColorOverridesFromSettings(array $settings): void
    {
        $this->colorOverrides = [];
        foreach ([self::ESI, self::MTS, self::CTAS] as $std) {
            for ($lvl = 1; $lvl <= 5; $lvl++) {
                $key = "triage_color_{$std}_{$lvl}";
                if (!isset($settings[$key])) {
                    continue;
                }
                $bg = strtoupper(trim((string)$settings[$key]));
                if (!preg_match('/^#[0-9A-F]{6}$/', $bg)) {
                    continue; // ignore invalid
                }
                $this->colorOverrides[$std][$lvl] = [
                    'bg' => $bg,
                    'fg' => self::idealTextColor($bg),
                ];
            }
        }
    }

    /**
     * Returns the background/foreground colors for a standard+level
     * after applying overrides (if any).
     *
     * @return array{bg: string, fg: string}
     */
    public function levelColors(string $standard, int $level): array
    {
        $def = self::DEFINITIONS[$standard] ?? self::DEFINITIONS[self::ESI];
        $lvlDef = $def['levels'][$level] ?? ($def['levels'][1] ?? ['color_bg' => '#999999', 'color_fg' => '#ffffff']);
        $bg = (string)($lvlDef['color_bg'] ?? '#999999');
        $fg = (string)($lvlDef['color_fg'] ?? '#ffffff');

        if (isset($this->colorOverrides[$standard][$level])) {
            $bg = $this->colorOverrides[$standard][$level]['bg'];
            $fg = $this->colorOverrides[$standard][$level]['fg'];
        }

        return ['bg' => $bg, 'fg' => $fg];
    }

    /**
     * Choose black/white text for readability on a background color.
     */
    public static function idealTextColor(string $bgHex): string
    {
        $hex = ltrim($bgHex, '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // perceived luminance
        $l = (0.299 * $r + 0.587 * $g + 0.114 * $b);
        return ($l > 160) ? '#000000' : '#ffffff';
    }


    // ── Identity ──────────────────────────────────────────────────────────

    public function getStandard(): string
    {
        return $this->standard;
    }

    public function getName(): string
    {
        return self::DEFINITIONS[$this->standard]['name'];
    }

    public function getShortName(): string
    {
        return self::DEFINITIONS[$this->standard]['short_name'];
    }

    public function getRegion(): string
    {
        return self::DEFINITIONS[$this->standard]['region'];
    }

    public function isEsi(): bool
    {
        return $this->standard === self::ESI;
    }

    /**
     * Label for the acuity column header on the ED Board and other tables.
     * Returns "ESI", "MTS", or "CTAS".
     */
    public function columnLabel(): string
    {
        return (string)(self::DEFINITIONS[$this->standard]['col_header'] ?? 'ESI');
    }

    // ── Per-level rendering ───────────────────────────────────────────────

    /**
     * CSS class for an acuity badge.
     * Returns "esi-1".."esi-5", "mts-1".."mts-5", "ctas-1".."ctas-5".
     * Unknown levels return "esi-x" (universal neutral fallback, always defined).
     */
    public function badgeClass(int $level): string
    {
        $prefix = self::DEFINITIONS[$this->standard]['css_prefix'] ?? 'esi';
        if ($level >= 1 && $level <= 5) {
            return "{$prefix}-{$level}";
        }
        return 'esi-x';
    }

    /**
     * Short label shown inside the badge (e.g. "1", "VU", "R").
     * Returns "?" for invalid levels.
     */
    public function shortLabel(int $level): string
    {
        if ($level < 1 || $level > 5) {
            return '?';
        }
        return (string)(self::DEFINITIONS[$this->standard]['levels'][$level]['short'] ?? (string)$level);
    }

    /**
     * Full level label (e.g. "MTS 2 — Very Urgent").
     */
    public function levelLabel(int $level): string
    {
        if ($level < 1 || $level > 5) {
            return $this->standard . ' ' . $level;
        }
        return (string)(self::DEFINITIONS[$this->standard]['levels'][$level]['label'] ?? "{$this->standard} {$level}");
    }

    // ── HTML helpers ──────────────────────────────────────────────────────

    /**
     * Returns <option> elements for the acuity <select> on the triage form.
     * Uses xlt() for translatable blank option.
     *
     * @param int|null $selected currently selected level (null = none selected)
     */
    public function selectOptions(?int $selected = null): string
    {
        $html = '<option value="">' . (function_exists('xlt') ? xlt('-- Select --') : '-- Select --') . '</option>';
        foreach (self::DEFINITIONS[$this->standard]['levels'] as $level => $def) {
            $sel = ($selected !== null && $selected === $level) ? ' selected' : '';
            $html .= '<option value="' . (int)$level . '"' . $sel . '>'
                . htmlspecialchars((string)$def['label'])
                . '</option>' . "\n";
        }
        return $html;
    }

    /**
     * Emits a <style> block adding CSS classes for the active standard.
     *
     * For ESI: returns '' (classes are already hardcoded in every page's
     * embedded CSS — no extra output needed, zero overhead).
     *
     * For MTS / CTAS: injects .mts-1 .. .mts-5 or .ctas-1 .. .ctas-5
     * alongside the existing .esi-x classes. Call this once per page,
     * ideally just before </head> or inside the <style> block.
     */
    public function cssBlock(): string
    {
        if ($this->standard === self::ESI) {
            return '';  // ESI classes hardcoded in every page — no-op
        }
        $prefix = self::DEFINITIONS[$this->standard]['css_prefix'];
        $lines = ["<style>"];
        foreach (self::DEFINITIONS[$this->standard]['levels'] as $level => $def) {
            $colors = $this->levelColors($this->standard, (int)$level);
            $bg = $colors['bg'];
            $fg = $colors['fg'];
            $lines[] = "  .{$prefix}-{$level} { background: {$bg}; color: {$fg}; }";
        }
        $lines[] = "</style>";
        return implode("\n", $lines) . "\n";
    }

    /**
     * Always emits a complete <style> block for the active standard's badge
     * classes, regardless of which standard is active.
     *
     * Use this on pages that do NOT have hardcoded ESI CSS (e.g. settings.php).
     * Pages that already have .esi-1..5 hardcoded should use cssBlock() instead
     * to avoid duplicates.
     */
    public function stylesheetHtml(): string
    {
        return "<style>\n" . $this->cssRules() . "</style>\n";
    }

    /**
     * Emits raw CSS rules (no <style> wrapper) for the active standard.
     * Always includes all 5 levels — including ESI.
     *
     * Use this INSIDE an existing <style> block, e.g.:
     *   <style>
     *     .my-other-rule { ... }
     *     <?= $triageStandard->cssRules() ?>
     *   </style>
     *
     * This is the correct method for triage.php, handoff.php and any page
     * that already has its own <style> block open.
     */
    public function cssRules(): string
    {
        $prefix = self::DEFINITIONS[$this->standard]['css_prefix'];
        $lines = [];
        foreach (self::DEFINITIONS[$this->standard]['levels'] as $level => $def) {
            $colors = $this->levelColors($this->standard, (int)$level);
            $bg = $colors['bg'];
            $fg = $colors['fg'];
            $lines[] = "  .{$prefix}-{$level} { background: {$bg}; color: {$fg}; }";
        }
        return implode("\n", $lines) . "\n";
    }

    /**
     * Returns a JSON string of all standards with their level colors and labels.
     * Used by the settings page JS to update the preview dynamically when the
     * standard select changes — without a round-trip to the server.
     *
     * Shape:
     * {
     *   "ESI": {
     *     "name": "Emergency Severity Index",
     *     "short_name": "ESI",
     *     "css_prefix": "esi",
     *     "levels": {
     *       "1": { "label": "ESI 1 — Immediate", "short": "1",
     *              "color_bg": "#b71c1c", "color_fg": "#ffffff" },
     *       ...
     *     }
     *   },
     *   ...
     * }
     */
    public static function definitionsJson(): string
    {
        $out = [];
        foreach (self::DEFINITIONS as $code => $def) {
            $out[$code] = [
                'name' => $def['name'],
                'short_name' => $def['short_name'],
                'region' => $def['region'],
                'css_prefix' => $def['css_prefix'],
                'levels' => $def['levels'],
            ];
        }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    // ── Static helpers ────────────────────────────────────────────────────

    /**
     * All valid standard codes.
     *
     * @return string[]
     */
    public static function validCodes(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * All definitions for settings UI rendering.
     *
     * @return array<string, array{name: string, short_name: string, region: string}>
     */
    public static function allDefinitions(): array
    {
        $out = [];
        foreach (self::DEFINITIONS as $code => $def) {
            $out[$code] = [
                'name' => $def['name'],
                'short_name' => $def['short_name'],
                'region' => $def['region'],
            ];
        }
        return $out;
    }
}




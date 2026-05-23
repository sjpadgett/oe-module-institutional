<?php

/**
 * src/Shared/Submodule/Handoff/Service/HandoffService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Handoff\Service;

/**
 * HandoffService
 *
 * Clinical formatting and summary computation for the shift handoff report.
 * All methods are pure functions with no database access — rendering only.
 */
final class HandoffService
{
    /**
     * Format vitals from a handoff row into a compact HTML string.
     * e.g. "130/80  HR 88  RR 18  SpO₂ 98%  T 98.6°F"
     * Abnormal values are wrapped in warning/critical spans.
     *
     * @param array<string,mixed> $row
     */
    public function formatVitals(array $row): string
    {
        $parts = [];

        if (!empty($row['bp_systolic']) && !empty($row['bp_diastolic'])) {
            $sbp   = (int)$row['bp_systolic'];
            $dbp   = (int)$row['bp_diastolic'];
            $flag  = $sbp <= 100 ? ' ⚠' : '';
            $cls   = $sbp <= 100 ? ' hv-warn' : '';
            $parts[] = "<span class='hv-bp{$cls}'>{$sbp}/{$dbp}{$flag}</span>";
        }
        if (!empty($row['hr'])) {
            $hr    = (int)$row['hr'];
            $cls   = ($hr > 130 || $hr < 50) ? ' hv-warn' : '';
            $parts[] = "<span class='hv{$cls}'>HR {$hr}</span>";
        }
        if (!empty($row['rr'])) {
            $rr    = (int)$row['rr'];
            $cls   = $rr >= 22 ? ' hv-warn' : '';
            $parts[] = "<span class='hv{$cls}'>RR {$rr}</span>";
        }
        if (!empty($row['spo2'])) {
            $sp    = (int)$row['spo2'];
            $cls   = $sp < 94 ? ' hv-crit' : ($sp < 97 ? ' hv-warn' : '');
            $parts[] = "<span class='hv{$cls}'>SpO₂ {$sp}%</span>";
        }
        if (!empty($row['temp_f'])) {
            $t     = (float)$row['temp_f'];
            $cls   = ($t >= 101.5 || $t < 96.8) ? ' hv-warn' : '';
            $parts[] = "<span class='hv{$cls}'>T " . number_format($t, 1) . "°F</span>";
        }
        if (!empty($row['gcs'])) {
            $g     = (int)$row['gcs'];
            $cls   = $g <= 8 ? ' hv-crit' : ($g < 13 ? ' hv-warn' : '');
            $parts[] = "<span class='hv{$cls}'>GCS {$g}</span>";
        }

        return $parts ? implode(' ', $parts) : '<span class="text-muted">—</span>';
    }

    /**
     * Compute qSOFA score (0–3) from vitals in a handoff row.
     *
     * @param array<string,mixed> $row
     */
    public function qsofa(array $row): int
    {
        $score = 0;
        $gcs   = isset($row['gcs'])          && $row['gcs']          !== '' ? (int)$row['gcs']          : null;
        $rr    = isset($row['rr'])            && $row['rr']            !== '' ? (int)$row['rr']            : null;
        $sbp   = isset($row['bp_systolic'])   && $row['bp_systolic']   !== '' ? (int)$row['bp_systolic']   : null;

        if ($gcs !== null && $gcs < 15)  $score++;
        if ($rr  !== null && $rr  >= 22) $score++;
        if ($sbp !== null && $sbp <= 100) $score++;

        return $score;
    }

    /**
     * Format elapsed time since arrival as a human-readable string.
     * e.g. "2h 15m" or "45m"
     */
    public function elapsed(string $dt): string
    {
        $ts = strtotime($dt);
        if (!$ts) {
            return '—';
        }
        $mins = (int)round((time() - $ts) / 60);
        if ($mins < 60) {
            return $mins . 'm';
        }
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $h . 'h' . ($m > 0 ? ' ' . $m . 'm' : '');
    }

    /**
     * Compute summary badge counts for the handoff header bar.
     *
     * @param  array<int,array<string,mixed>> $rows
     * @return array{total:int, sepsis:int, pending_mar:int, overdue_tasks:int, cosign_needed:int, mar_followup:int}
     */
    public function computeSummary(array $rows): array
    {
        $pendingMar   = 0;
        $sepsisCount  = 0;
        $overdueCount = 0;
        $cosignNeeded = 0;
        $marFollowup  = 0;
        $now = time();

        foreach ($rows as $r) {
            $pendingMar   += (int)($r['pending_mar_count'] ?? 0);
            $cosignNeeded += (int)($r['awaiting_cosign_count'] ?? 0);
            $marFollowup  += (int)($r['mar_followup_count'] ?? 0);

            if ($this->qsofa($r) >= 2) {
                $sepsisCount++;
            }

            $due = $r['next_task_due'] ?? null;
            if ($due && strtotime((string)$due) < $now) {
                $overdueCount++;
            }
        }

        return [
            'total'         => count($rows),
            'sepsis'        => $sepsisCount,
            'pending_mar'   => $pendingMar,
            'overdue_tasks' => $overdueCount,
            'cosign_needed' => $cosignNeeded,
            'mar_followup'  => $marFollowup,
        ];
    }
}







<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Controller;

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlHandoff\Repository\AlHandoffRepository;

/**
 * AlHandoffController
 *
 * Thin controller: fetches the snapshot and enriches each row with
 * computed flags that the template uses for visual highlighting.
 *
 * Clinical flags computed per row:
 *   flag_mar_overdue    — any pending MAR items past scheduled time
 *   flag_fall_overdue   — fall risk reassessment due (> 30 days)
 *   flag_weight_alert   — not computable here (requires vitals trend);
 *                         marked true when weight_kg delta logic run elsewhere
 *   flag_discharge      — pending discharge plan exists
 *   flag_incident       — incident this week
 *   flag_high_care      — TIER_3 care level
 *   flag_count          — total active flags (drives row highlight severity)
 */
final class AlHandoffController
{
    /** Days before fall reassessment is considered overdue. */
    private const FALL_REASSESS_DUE_DAYS = 30;

    public function __construct(
        private readonly AlHandoffRepository $repo
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, string $shift): array
    {
        $rows    = $this->repo->fetchHandoff($facilityId);
        $summary = $this->repo->fetchSummary($facilityId);
        $printed = (new \DateTimeImmutable())->format('F j, Y  g:i A');

        $enriched = [];
        foreach ($rows as $row) {
            $enriched[] = $this->enrichRow($row);
        }

        return [
            'rows'    => $enriched,
            'summary' => $summary,
            'shift'   => $shift,
            'printed' => $printed,
        ];
    }

    /** @param array<string,mixed> $row */
    private function enrichRow(array $row): array
    {
        $flags = [];

        if ((int)($row['pending_mar_count'] ?? 0) > 0) {
            $flags['flag_mar_overdue'] = true;
        }
        if ((int)($row['recent_incident_count'] ?? 0) > 0) {
            $flags['flag_incident'] = true;
        }
        if (!empty($row['pending_disposition'])) {
            $flags['flag_discharge'] = true;
        }
        if ($row['care_level'] === 'TIER_3') {
            $flags['flag_high_care'] = true;
        }
        if ($row['fall_risk_level'] === 'HIGH') {
            $flags['flag_fall_risk'] = true;
        }
        $daysSince = $row['days_since_fall_reassess'] ?? null;
        if ($daysSince !== null && (int)$daysSince >= self::FALL_REASSESS_DUE_DAYS) {
            $flags['flag_fall_reassess_due'] = true;
        }

        $row['flags']      = $flags;
        $row['flag_count'] = count($flags);

        // Row severity for colour-coding: 0=normal, 1=caution, 2=alert
        $row['severity'] = $row['flag_count'] >= 3 ? 2 : ($row['flag_count'] >= 1 ? 1 : 0);

        // Friendly vitals string for compact display
        $row['vitals_summary'] = $this->formatVitals($row);

        // ADL score label
        $adl = $row['last_adl_score'] ?? null;
        $row['adl_label'] = $adl !== null
            ? ((int)$adl >= 22 ? 'Independent' : ((int)$adl >= 14 ? 'Moderate assist' : 'High assist'))
            : '—';

        return $row;
    }

    private function formatVitals(array $row): string
    {
        $parts = [];
        if (!empty($row['bp_sys']) && !empty($row['bp_dia'])) {
            $parts[] = $row['bp_sys'] . '/' . $row['bp_dia'];
        }
        if (!empty($row['hr'])) {
            $parts[] = 'HR ' . (int)$row['hr'];
        }
        if (!empty($row['spo2'])) {
            $parts[] = 'SpO₂ ' . (int)$row['spo2'] . '%';
        }
        return $parts ? implode('  ', $parts) : '—';
    }
}

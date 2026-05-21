<?php

/**
 * src/HomeBased/Submodule/HbcHandoff/Controller/HbcHandoffController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcHandoff\Controller;

use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcHandoff\Repository\HbcHandoffRepository;

/**
 * HbcHandoffController
 *
 * Thin controller: fetches the snapshot and enriches each row with
 * computed clinical flags that the template uses for row highlighting.
 *
 * Flags per row:
 *   flag_mar_overdue      — any pending MAR items past scheduled time
 *   flag_fall_reassess    — fall risk reassessment due (> 30 days)
 *   flag_discharge        — pending closure plan exists
 *   flag_incident         — incident this week
 *   flag_urgent           — urgency = EMERGENT
 *   flag_no_visit_soon    — no visit scheduled in next 72 hours
 *   flag_count            — total flags (drives row highlight severity)
 */
final class HbcHandoffController
{
    private const FALL_REASSESS_DUE_DAYS = 30;
    private const VISIT_SOON_HOURS       = 72;

    public function __construct(
        private readonly HbcHandoffRepository $repo = new HbcHandoffRepository()
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId): array
    {
        $rows    = $this->repo->fetchHandoff($facilityId);
        $summary = $this->repo->fetchSummary($facilityId);
        $printed = (new \DateTimeImmutable())->format('F j, Y  g:i A');

        $enriched = array_map(function (array $row): array {
            $flags = [];

            // MAR overdue
            $flags['flag_mar_overdue'] = ((int)($row['pending_mar_count'] ?? 0)) > 0;

            // Fall reassessment overdue
            $daysFall = $row['days_since_fall_reassess'] ?? null;
            $flags['flag_fall_reassess'] = ($daysFall !== null
                && (int)$daysFall >= self::FALL_REASSESS_DUE_DAYS);

            // Closure plan pending
            $flags['flag_discharge'] = !empty($row['pending_disposition']);

            // Incident this week
            $flags['flag_incident'] = ((int)($row['recent_incident_count'] ?? 0)) > 0;

            // Emergent urgency
            $flags['flag_urgent'] = strtoupper((string)($row['urgency'] ?? '')) === 'EMERGENT';

            // No visit scheduled within 72h
            $nextDt = $row['next_visit_datetime'] ?? null;
            if ($nextDt) {
                $hoursUntil = (strtotime($nextDt) - time()) / 3600;
                $flags['flag_no_visit_soon'] = $hoursUntil > self::VISIT_SOON_HOURS;
            } else {
                $flags['flag_no_visit_soon'] = true;  // no future visit at all
            }

            // Cert period expiring or expired
            $certEnd = $row['cert_period_end'] ?? null;
            if ($certEnd && strtotime($certEnd) !== false) {
                $certDays = (int) ((strtotime($certEnd) - time()) / 86400);
                $flags['flag_cert_expiring'] = ($certDays <= 14);
            } else {
                $flags['flag_cert_expiring'] = false;
            }

            // Supervisory visit overdue (HHA patients need RN oversight every 14 days)
            $hhaCount = (int)($row['hha_visit_count'] ?? 0);
            $daysSupervisory = $row['days_since_supervisory'] ?? null;
            $flags['flag_supervisory_due'] = ($hhaCount > 0
                && ($daysSupervisory === null || (int)$daysSupervisory >= 14));

            $flags['flag_count'] = count(array_filter($flags));

            return array_merge($row, $flags);
        }, $rows);

        return [
            'rows'    => $enriched,
            'summary' => $summary,
            'printed' => $printed,
        ];
    }
}










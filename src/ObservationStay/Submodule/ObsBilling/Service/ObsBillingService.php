<?php

/**
 * src/ObservationStay/Submodule/ObsBilling/Service/ObsBillingService.php
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

namespace OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsBilling\Service;

/**
 * ObsBillingService
 *
 * Enforces CMS 2-Midnight Rule compliance for observation episodes.
 *
 * The 2-Midnight Rule (42 CFR §412.3):
 *   - An OBS stay LESS than 2 midnights is generally reimbursed as outpatient.
 *   - A stay CROSSING 2 midnights should be converted to INPATIENT admission
 *     to capture higher DRG reimbursement and avoid OBS underpayment.
 *
 * Midnight boundaries are counted from oei_obs_plan.start_datetime.
 * This service intentionally works off obs_plan start (when OBS was formally
 * initiated) rather than episode start_datetime (arrival) to match clinical
 * admission billing practice.
 *
 * Status definitions:
 *   NORMAL           < 20h — well within OBS window
 *   APPROACHING_1    20–36h — approaching first midnight
 *   APPROACHING_2    36–47h — approaching 2-midnight threshold
 *   CONVERSION_DUE   ≥ 48h  — should be converted to inpatient
 *   OVERRUN          ≥ 72h  — severely overrun, immediate action required
 */
final class ObsBillingService
{
    private const STATUS_NORMAL        = 'NORMAL';
    private const STATUS_APPROACHING_1 = 'APPROACHING_1';
    private const STATUS_APPROACHING_2 = 'APPROACHING_2';
    private const STATUS_CONVERSION    = 'CONVERSION_DUE';
    private const STATUS_OVERRUN       = 'OVERRUN';

    // Hour thresholds
    private const H_APPROACHING_1 = 20;
    private const H_APPROACHING_2 = 36;
    private const H_CONVERSION    = 48;
    private const H_OVERRUN       = 72;

    /**
     * Fetch all active OBS plans for a facility and compute 2-midnight status.
     *
     * @return array<int,array<string,mixed>>  Each entry has:
     *   episode_id, pid, obs_start, elapsed_hours, elapsed_min,
     *   midnights_crossed, status, severity, action_label,
     *   protocol_key, chief_complaint
     */
    public function fetchObsBillingStatus(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $res = sqlStatement(
            "SELECT
                op.episode_id,
                op.pid,
                op.protocol_key,
                op.start_datetime     AS obs_start,
                op.target_hours,
                e.chief_complaint,
                e.acuity_esi,
                e.assigned_provider_user_id,
                l.name                AS location_name,
                CONCAT(COALESCE(pu.fname,''), ' ', COALESCE(pu.lname,'')) AS provider_name
             FROM oei_obs_plan op
             JOIN oei_episode e
                ON e.id = op.episode_id
             LEFT JOIN oei_episode_location el
                ON el.episode_id = op.episode_id AND el.end_datetime IS NULL
             LEFT JOIN oei_location l
                ON l.id = el.location_id
             LEFT JOIN users pu
                ON pu.id = e.assigned_provider_user_id
             WHERE op.facility_id = ?
               AND op.status = 'ACTIVE'
             ORDER BY op.start_datetime ASC",
            [$facilityId]
        );

        $rows = [];
        $now  = time();

        while ($row = sqlFetchArray($res)) {
            $startTs = strtotime((string)$row['obs_start']);
            if (!$startTs) {
                continue;
            }

            $elapsedSec  = $now - $startTs;
            $elapsedHours = $elapsedSec / 3600;
            $elapsedMin  = (int)round($elapsedSec / 60);

            // Count midnights crossed since obs_start
            $midnights = $this->midnightsCrossed($startTs, $now);

            // Determine status and urgency
            [$status, $severity, $actionLabel] = $this->classify($elapsedHours);

            $rows[] = [
                'episode_id'     => (int)$row['episode_id'],
                'pid'            => (int)$row['pid'],
                'protocol_key'   => (string)$row['protocol_key'],
                'obs_start'      => (string)$row['obs_start'],
                'elapsed_hours'  => round($elapsedHours, 1),
                'elapsed_min'    => $elapsedMin,
                'target_hours'   => (int)$row['target_hours'],
                'midnights'      => $midnights,
                'status'         => $status,
                'severity'       => $severity,
                'action_label'   => $actionLabel,
                'chief_complaint'=> (string)$row['chief_complaint'],
                'acuity_esi'     => $row['acuity_esi'],
                'location_name'  => (string)$row['location_name'],
                'provider_name'  => trim((string)$row['provider_name']),
            ];
        }

        return $rows;
    }

    /**
     * Compute 2-midnight billing alerts for AlertService::computeAll().
     *
     * @return array<int,array<string,mixed>>  Standard alert shape
     */
    public function computeBillingAlerts(int $facilityId): array
    {
        $rows   = $this->fetchObsBillingStatus($facilityId);
        $alerts = [];

        foreach ($rows as $r) {
            if ($r['status'] === self::STATUS_NORMAL) {
                continue;
            }

            $severity = in_array($r['status'], [self::STATUS_CONVERSION, self::STATUS_OVERRUN])
                ? 'CRITICAL' : 'WARNING';

            $alerts[] = [
                'type'       => 'OBS_BILLING_FLAG',
                'severity'   => $severity,
                'episode_id' => $r['episode_id'],
                'pid'        => $r['pid'],
                'message'    => $r['action_label'] . ' — ' . $r['elapsed_hours'] . 'h elapsed',
                'detail'     => $r['midnights'] . ' midnight(s) crossed · Protocol: ' . $r['protocol_key'],
                'minutes'    => $r['elapsed_min'],
                'group'      => 'billing',
                'key'        => 'OBS_BILLING_FLAG:' . $r['episode_id'],
            ];
        }

        return $alerts;
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    /**
     * Count calendar midnight crossings between two unix timestamps.
     */
    private function midnightsCrossed(int $startTs, int $endTs): int
    {
        $count       = 0;
        $startDate   = new \DateTimeImmutable('@' . $startTs);
        $endDate     = new \DateTimeImmutable('@' . $endTs);
        $current     = new \DateTimeImmutable($startDate->format('Y-m-d') . ' 23:59:59');

        while ($current < $endDate) {
            $count++;
            $current = $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * @return array{0:string, 1:string, 2:string} [status, severity, actionLabel]
     */
    private function classify(float $hours): array
    {
        if ($hours >= self::H_OVERRUN) {
            return [
                self::STATUS_OVERRUN,
                'CRITICAL',
                'OBS severely overrun — immediate billing review required',
            ];
        }
        if ($hours >= self::H_CONVERSION) {
            return [
                self::STATUS_CONVERSION,
                'CRITICAL',
                'Convert to inpatient admission (2-midnight threshold crossed)',
            ];
        }
        if ($hours >= self::H_APPROACHING_2) {
            return [
                self::STATUS_APPROACHING_2,
                'WARNING',
                'Approaching 2-midnight threshold — evaluate for inpatient conversion',
            ];
        }
        if ($hours >= self::H_APPROACHING_1) {
            return [
                self::STATUS_APPROACHING_1,
                'WARNING',
                'First midnight crossed — monitor for 2-midnight rule',
            ];
        }

        return [self::STATUS_NORMAL, 'INFO', 'Within normal OBS window'];
    }

    /**
     * Human-readable elapsed time for display.
     */
    public static function formatElapsed(float $hours): string
    {
        $h = (int)floor($hours);
        $m = (int)round(($hours - $h) * 60);
        return $m > 0 ? "{$h}h {$m}m" : "{$h}h";
    }

    /**
     * Hours remaining until 2-midnight threshold (48h).
     */
    public static function hoursToConversion(float $elapsedHours): float
    {
        return max(0.0, self::H_CONVERSION - $elapsedHours);
    }
}




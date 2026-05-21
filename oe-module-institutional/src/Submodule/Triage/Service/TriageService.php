<?php

/**
 * src/Submodule/Triage/Service/TriageService.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Submodule\Triage\Service;

use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;

final class TriageService
{
    public function __construct(private readonly TriageRepository $repo) {}

    /**
     * Validates, saves vitals, returns the new triage id and any alerts.
     * @return array{id:int, esi_suggested:int|null, alerts:string[]}
     */
    public function recordVitals(
        int    $episodeId,
        int    $pid,
        ?int   $eid,
        int    $facilityId,
        ?int   $bpSystolic,
        ?int   $bpDiastolic,
        ?int   $hr,
        ?int   $rr,
        ?float $tempF,
        ?int   $spo2,
        ?int   $gcs,
        ?int   $painScore,
        ?float $weightKg,
        ?string $arrivalMode,
        ?string $notes,
        ?int   $userId
    ): array {
        $alerts = $this->flagAbnormals(
            $bpSystolic, $bpDiastolic, $hr, $rr, $tempF, $spo2, $gcs, $painScore
        );

        $id = $this->repo->record(
            $episodeId, $pid, $eid, $facilityId,
            $bpSystolic, $bpDiastolic, $hr, $rr, $tempF, $spo2, $gcs,
            $painScore, $weightKg, $arrivalMode, $notes, $userId
        );

        // Re-fetch to get esi_suggested computed in repository
        $row = $this->repo->getLatestForEpisode($episodeId);
        $esiSuggested = $row ? ($row['esi_suggested'] !== null ? (int)$row['esi_suggested'] : null) : null;

        return ['id' => $id, 'esi_suggested' => $esiSuggested, 'alerts' => $alerts];
    }

    /**
     * Returns formatted vitals string for board display, e.g. "BP 140/90 HR 88 SpO₂ 98%"
     */
    public static function formatForBoard(array $vitals): string
    {
        $parts = [];

        if (!empty($vitals['bp_systolic']) && !empty($vitals['bp_diastolic'])) {
            $parts[] = 'BP ' . (int)$vitals['bp_systolic'] . '/' . (int)$vitals['bp_diastolic'];
        }
        if (!empty($vitals['hr'])) {
            $parts[] = 'HR ' . (int)$vitals['hr'];
        }
        if (!empty($vitals['spo2'])) {
            $parts[] = 'SpO₂ ' . (int)$vitals['spo2'] . '%';
        }
        if (!empty($vitals['temp_f'])) {
            $parts[] = 'T ' . number_format((float)$vitals['temp_f'], 1) . '°F';
        }
        if (!empty($vitals['rr'])) {
            $parts[] = 'RR ' . (int)$vitals['rr'];
        }
        if (isset($vitals['gcs']) && $vitals['gcs'] !== null && $vitals['gcs'] !== '') {
            $parts[] = 'GCS ' . (int)$vitals['gcs'];
        }

        return implode(' · ', $parts);
    }

    /**
     * Returns CSS severity class for board coloring.
     * 'danger' | 'warning' | '' (normal)
     */
    public static function boardSeverity(array $vitals): string
    {
        $sbp  = isset($vitals['bp_systolic'])  ? (int)$vitals['bp_systolic']  : null;
        $hr   = isset($vitals['hr'])           ? (int)$vitals['hr']           : null;
        $spo2 = isset($vitals['spo2'])         ? (int)$vitals['spo2']         : null;
        $gcs  = isset($vitals['gcs'])          ? (int)$vitals['gcs']          : null;

        if (($spo2 !== null && $spo2 < 90) ||
            ($gcs  !== null && $gcs  < 9)  ||
            ($sbp  !== null && $sbp  < 80) ||
            ($hr   !== null && ($hr > 150 || $hr < 40))) {
            return 'danger';
        }

        if (($spo2 !== null && $spo2 < 94) ||
            ($sbp  !== null && $sbp  < 90) ||
            ($gcs  !== null && $gcs  < 13) ||
            ($hr   !== null && ($hr > 130 || $hr < 50))) {
            return 'warning';
        }

        return '';
    }

    /** @return string[] */
    private function flagAbnormals(
        ?int $sbp, ?int $dbp, ?int $hr, ?int $rr,
        ?float $tempF, ?int $spo2, ?int $gcs, ?int $pain
    ): array {
        $flags = [];

        if ($spo2 !== null) {
            if ($spo2 < 90)       $flags[] = 'Critical SpO₂ ' . $spo2 . '%';
            elseif ($spo2 < 94)   $flags[] = 'Low SpO₂ ' . $spo2 . '%';
        }
        if ($sbp !== null) {
            if ($sbp < 80)        $flags[] = 'Critical SBP ' . $sbp;
            elseif ($sbp < 90)    $flags[] = 'Hypotensive SBP ' . $sbp;
            elseif ($sbp >= 180)  $flags[] = 'Hypertensive crisis SBP ' . $sbp;
        }
        if ($hr !== null) {
            if ($hr < 40)         $flags[] = 'Critical bradycardia HR ' . $hr;
            elseif ($hr < 50)     $flags[] = 'Bradycardia HR ' . $hr;
            elseif ($hr > 150)    $flags[] = 'Critical tachycardia HR ' . $hr;
            elseif ($hr > 130)    $flags[] = 'Tachycardia HR ' . $hr;
        }
        if ($rr !== null) {
            if ($rr < 8 || $rr > 30) $flags[] = 'Abnormal RR ' . $rr;
        }
        if ($tempF !== null) {
            if ($tempF >= 104.0)  $flags[] = 'Hyperpyrexia T ' . number_format($tempF, 1) . '°F';
            elseif ($tempF >= 101.5) $flags[] = 'Fever T ' . number_format($tempF, 1) . '°F';
            elseif ($tempF < 96.8)  $flags[] = 'Hypothermia T ' . number_format($tempF, 1) . '°F';
        }
        if ($gcs !== null && $gcs <= 8) {
            $flags[] = 'Critical GCS ' . $gcs;
        }
        if ($pain !== null && $pain >= 9) {
            $flags[] = 'Severe pain ' . $pain . '/10';
        }

        return $flags;
    }
}






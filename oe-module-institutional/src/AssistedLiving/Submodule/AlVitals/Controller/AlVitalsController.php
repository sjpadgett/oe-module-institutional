<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlVitals\Repository\AlVitalsRepository;

/**
 * AlVitalsController
 *
 * POST:  records a new periodic vitals entry using AlVitalsRepository → TriageRepository.
 * GET:   returns history + form scaffold for the page.
 *
 * Abnormal flagging delegates to TriageService::flagAbnormals() (shared logic).
 */
final class AlVitalsController
{
    private AlVitalsRepository $repo;

    public function __construct()
    {
        $this->repo = new AlVitalsRepository();
    }

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $flash   = '';
        $alerts  = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $flash = xlt('Security token invalid. Please try again.');
            } else {
                $result = $this->handlePost($episodeId, $pid, $facilityId, $userId);
                $flash  = $result['flash'];
                $alerts = $result['alerts'];
            }
        }

        // Resolve patient header for display
        $patientRow = null;
        if (function_exists('sqlQuery')) {
            $epRow = sqlQuery(
                "SELECT e.id, e.pid, pd.fname, pd.lname,
                        COALESCE(ale.room,'') AS room,
                        COALESCE(ale.unit,'') AS unit
                 FROM   oei_episode e
                 INNER  JOIN patient_data pd ON pd.pid = e.pid
                 LEFT   JOIN oei_al_episode ale ON ale.episode_id = e.id
                 WHERE  e.id = ? LIMIT 1",
                [$episodeId]
            );
            $patientRow = $epRow ?: null;
        }

        return [
            'flash'       => $flash,
            'alerts'      => $alerts,
            'patient'     => $patientRow,
            'history'     => $this->repo->listForEpisode($episodeId, 20),
            'weight_trend'=> $this->repo->weightTrend($episodeId, 14),
            'latest'      => $this->repo->getLatest($episodeId),
        ];
    }

    /**
     * @return array{flash: string, alerts: string[]}
     */
    private function handlePost(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $p = $_POST;

        $bpSystolic  = isset($p['bp_systolic'])  && $p['bp_systolic']  !== '' ? (int)$p['bp_systolic']  : null;
        $bpDiastolic = isset($p['bp_diastolic']) && $p['bp_diastolic'] !== '' ? (int)$p['bp_diastolic'] : null;
        $hr          = isset($p['hr'])           && $p['hr']           !== '' ? (int)$p['hr']           : null;
        $rr          = isset($p['rr'])           && $p['rr']           !== '' ? (int)$p['rr']           : null;
        $tempF       = isset($p['temp_f'])       && $p['temp_f']       !== '' ? (float)$p['temp_f']     : null;
        $spo2        = isset($p['spo2'])         && $p['spo2']         !== '' ? (int)$p['spo2']         : null;
        $weightKg    = isset($p['weight_kg'])    && $p['weight_kg']    !== '' ? (float)$p['weight_kg']  : null;
        $painScore   = isset($p['pain_score'])   && $p['pain_score']   !== '' ? (int)$p['pain_score']   : null;
        $notes       = trim((string)($p['notes'] ?? ''));

        // At least one vital must be present
        if ($bpSystolic === null && $hr === null && $spo2 === null && $weightKg === null) {
            return ['flash' => xlt('Please enter at least one vital sign.'), 'alerts' => []];
        }

        $id = $this->repo->record(
            $episodeId, $pid, $facilityId,
            $bpSystolic, $bpDiastolic, $hr, $rr, $tempF,
            $spo2, $weightKg, $painScore, $notes, $userId
        );

        if ($id === 0) {
            return ['flash' => xlt('Error saving vitals. Please try again.'), 'alerts' => []];
        }

        // Inline abnormal flagging (mirrors TriageService private thresholds)
        $alerts = [];
        if ($bpSystolic !== null) {
            if ($bpSystolic > 180 || $bpSystolic < 90) {
                $alerts[] = xlt('Blood pressure out of range:') . " $bpSystolic/$bpDiastolic mmHg";
            }
        }
        if ($hr !== null && ($hr > 110 || $hr < 50)) {
            $alerts[] = xlt('Heart rate out of range:') . " $hr bpm";
        }
        if ($rr !== null && ($rr > 24 || $rr < 8)) {
            $alerts[] = xlt('Respiratory rate out of range:') . " $rr /min";
        }
        if ($spo2 !== null && $spo2 < 93) {
            $alerts[] = xlt('SpO₂ critically low:') . " $spo2%";
        } elseif ($spo2 !== null && $spo2 < 96) {
            $alerts[] = xlt('SpO₂ below normal:') . " $spo2%";
        }
        if ($tempF !== null && ($tempF > 101.5 || $tempF < 96.0)) {
            $alerts[] = xlt('Temperature out of range:') . " {$tempF}°F";
        }

        // AL-specific: weight gain alert for CHF residents
        $prev = $this->repo->weightTrend($episodeId, 2);
        if ($weightKg !== null && count($prev) >= 2) {
            $prevWeight = $prev[count($prev) - 2] ?? null;
            if ($prevWeight !== null && ($weightKg - $prevWeight) >= 0.9) {
                $alerts[] = xlt('Weight gain ≥ 0.9 kg since last check — assess for fluid retention.');
            }
        }

        $flash = xlt('Vitals saved successfully.');
        if ($alerts) {
            $flash .= ' ' . xlt('Clinical alert(s) generated — see below.');
        }

        return ['flash' => $flash, 'alerts' => $alerts];
    }
}

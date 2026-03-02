<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentProfile\Controller;

use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentProfile\Repository\ResidentProfileRepository;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\AdlLevel;

/**
 * ResidentProfileController
 *
 * Assembles the complete view-model for the single-resident profile page.
 * Each panel (vitals, ADL, MAR, care plan, incidents, fall risk, care team)
 * is fetched independently so failures in one section don't break others.
 */
final class ResidentProfileController
{
    public function __construct(
        private readonly ResidentProfileRepository $repo = new ResidentProfileRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId): array
    {
        $header = $this->repo->fetchHeader($episodeId);

        if ($header === null) {
            return ['error' => 'Episode not found or not an AL episode.', 'header' => null];
        }

        $pid         = $header['pid'];
        $encounterId = $header['encounter_id'];

        $vitalsHistory  = $this->repo->fetchVitalsHistory($episodeId, 6);
        $adlHistory     = $this->repo->fetchAdlHistory($episodeId, 5);
        $carePlan       = $this->repo->fetchCarePlanSummary($episodeId, $pid, $encounterId);
        $marToday       = $this->repo->fetchMarToday($episodeId);
        $incidents      = $this->repo->fetchRecentIncidents($episodeId, 3);
        $latestFallRisk = $this->repo->fetchLatestFallRisk($episodeId);
        $careTeam       = $this->repo->fetchCareTeam($pid);

        // Compute vitals sparkline data (weight trend + SpO2 trend) oldest→newest
        $sparkWeights = [];
        $sparkSpo2    = [];
        $sparkSbp     = [];
        foreach (array_reverse($vitalsHistory) as $v) {
            if ($v['weight_kg'] !== null) {
                $sparkWeights[] = round($v['weight_kg'], 1);
            }
            if ($v['spo2'] !== null) {
                $sparkSpo2[] = $v['spo2'];
            }
            if ($v['bp_systolic'] !== null) {
                $sparkSbp[] = $v['bp_systolic'];
            }
        }

        // Latest vitals (first element — newest first)
        $latestVitals = $vitalsHistory[0] ?? null;

        // ADL trend: score oldest→newest for sparkline
        $adlTrend = array_reverse(array_column($adlHistory, 'adl_score'));

        // Fall risk next due date (30-day reassessment schedule)
        $fallRiskNextDue = null;
        if ($latestFallRisk) {
            $lastDate = strtotime((string)$latestFallRisk['assessed_datetime']);
            if ($lastDate) {
                $fallRiskNextDue = date('Y-m-d', strtotime('+30 days', $lastDate));
            }
        }

        // Domain helpers for template
        $careLevelLabel    = CareLevel::label($header['care_level']);
        $careLevelBadge    = CareLevel::badge($header['care_level']);
        $fallRiskLabel     = FallRiskLevel::label($header['fall_risk_level']);
        $fallRiskBadge     = FallRiskLevel::badge($header['fall_risk_level']);

        return [
            'error'           => null,
            'header'          => $header,
            'care_level_label'=> $careLevelLabel,
            'care_level_badge'=> $careLevelBadge,
            'fall_risk_label' => $fallRiskLabel,
            'fall_risk_badge' => $fallRiskBadge,
            'latest_vitals'   => $latestVitals,
            'vitals_history'  => $vitalsHistory,
            'spark_weights'   => $sparkWeights,
            'spark_spo2'      => $sparkSpo2,
            'spark_sbp'       => $sparkSbp,
            'adl_history'     => $adlHistory,
            'adl_trend'       => $adlTrend,
            'latest_adl'      => $adlHistory[0] ?? null,
            'care_plan'       => $carePlan,
            'mar_today'       => $marToday,
            'incidents'       => $incidents,
            'latest_fall_risk'=> $latestFallRisk,
            'fall_risk_next_due' => $fallRiskNextDue,
            'care_team'       => $careTeam,
            'adl_level_labels'=> array_map(
                fn(string $d) => AdlLevel::DOMAINS[$d] ?? $d,
                AdlLevel::validDomains()
            ),
        ];
    }
}

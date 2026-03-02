<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Service;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\ResidentBoard\Repository\ResidentBoardRepository;

/**
 * ResidentBoardService
 *
 * Thin orchestration layer between the repository and the board controller.
 * Adds display-ready derived fields without polluting the repository.
 */
final class ResidentBoardService
{
    public function __construct(
        private readonly ResidentBoardRepository $repo
    ) {}

    /**
     * Returns board-ready resident rows with display decorations.
     *
     * @return array{residents: array, units: array, counts: array{total: int, high_risk: int, high_care: int}}
     */
    public function boardData(int $facilityId): array
    {
        $residents = $this->repo->fetchActiveResidents($facilityId);
        $units     = $this->repo->fetchUnitSummary($facilityId);

        // Decorate each row with display helpers
        foreach ($residents as &$r) {
            $r['care_level_label']      = CareLevel::label($r['care_level']);
            $r['care_level_badge']      = CareLevel::badge($r['care_level']);
            $r['fall_risk_label']       = FallRiskLevel::label($r['fall_risk_level']);
            $r['fall_risk_badge']       = FallRiskLevel::badge($r['fall_risk_level']);
            $r['display_name']          = htmlspecialchars($r['fname'] . ' ' . $r['lname']);
            $r['age']                   = $this->computeAge($r['dob']);
            $r['adl_due']               = $this->isAdlDue($r['last_adl_datetime']);
        }
        unset($r);

        $totalResidents  = count($residents);
        $highRiskCount   = count(array_filter($residents, fn($r) => $r['fall_risk_level'] === FallRiskLevel::HIGH));
        $highCareCount   = count(array_filter($residents, fn($r) => $r['care_level'] === CareLevel::TIER_3));

        return [
            'residents' => $residents,
            'units'     => $units,
            'counts'    => [
                'total'     => $totalResidents,
                'high_risk' => $highRiskCount,
                'high_care' => $highCareCount,
            ],
        ];
    }

    private function computeAge(string $dob): int
    {
        if ($dob === '' || $dob === '0000-00-00') {
            return 0;
        }
        try {
            $birth = new \DateTimeImmutable($dob);
            return (int)$birth->diff(new \DateTimeImmutable())->y;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * ADL charting is considered due if the last chart was more than 8 hours ago
     * (typical shift length) or if no chart exists.
     */
    private function isAdlDue(?string $lastChartDatetime): bool
    {
        if ($lastChartDatetime === null) {
            return true;
        }
        $ts = strtotime($lastChartDatetime);
        return $ts === false || (time() - $ts) > (8 * 3600);
    }
}

<?php

/**
 * src/Shared/Submodule/Billing/Service/BillingWorkbenchService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Billing\Service;

use OpenEMR\Modules\Institutional\Core\Repository\FacilityProfileRepository;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsBilling\Service\ObsBillingService;
use OpenEMR\Modules\Institutional\Shared\Submodule\Billing\Repository\BillingWorkbenchRepository;

final class BillingWorkbenchService
{
    public function __construct(
        private readonly ?BillingWorkbenchRepository $repo = null,
        private readonly ?FacilityProfileRepository $profileRepo = null,
    ) {
    }

    private function repo(): BillingWorkbenchRepository
    {
        return $this->repo ?? new BillingWorkbenchRepository();
    }

    private function profileRepo(): FacilityProfileRepository
    {
        return $this->profileRepo ?? new FacilityProfileRepository();
    }

    /** @return array<string,mixed> */
    public function billingModeMeta(int $facilityId): array
    {
        $purpose = (string)($this->profileRepo()->getFacilityDefault($facilityId)['installed_purpose'] ?? '');
        return match ($purpose) {
            FacilityProfileRepository::PURPOSE_AL_ONLY => [
                'label' => 'Ledger-first',
                'detail' => 'Private-pay and recurring service lines stay in the module ledger by default.',
                'claim_focus' => 'Exception / covered-service review only',
                'release_target' => 'STATEMENT',
            ],
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => [
                'label' => 'Hybrid professional + ledger',
                'detail' => 'Completed home visits are surfaced for professional claim review while non-claim charges stay in the module ledger.',
                'claim_focus' => 'Professional visit review',
                'release_target' => 'PROFESSIONAL',
            ],
            FacilityProfileRepository::PURPOSE_ED_OBS_BH, FacilityProfileRepository::PURPOSE_INPATIENT => [
                'label' => 'Claims-first hybrid',
                'detail' => 'Institutional episodes are staged for OpenEMR Billing Manager while the module ledger holds non-claim and exception lines.',
                'claim_focus' => 'UB04 / 837I',
                'release_target' => 'UB04',
            ],
            default => [
                'label' => 'Hybrid institutional + ledger',
                'detail' => 'Use Billing Manager for claimable institutional activity and the module ledger for private-pay, recurring, or exception lines.',
                'claim_focus' => 'Mixed institutional and non-claim',
                'release_target' => 'BILLING_MANAGER',
            ],
        };
    }

    /** @return array<string,mixed> */
    public function summary(int $facilityId): array
    {
        $ledger = $this->repo()->ledgerSummary($facilityId);
        $claims = $this->claimCandidates($facilityId);
        $claimCounts = [
            'claim_candidates' => count($claims),
            'institutional_claims' => 0,
            'professional_claims' => 0,
        ];
        foreach ($claims as $row) {
            if (($row['claim_family'] ?? '') === 'UB04 / 837I') {
                $claimCounts['institutional_claims']++;
            } else {
                $claimCounts['professional_claims']++;
            }
        }
        return $ledger + $claimCounts;
    }

    /** @return array<int,array<string,mixed>> */
    public function claimCandidates(int $facilityId): array
    {
        $rows = [];
        $obsMap = [];
        foreach ((new ObsBillingService())->fetchObsBillingStatus($facilityId) as $obs) {
            $obsMap[(int)$obs['episode_id']] = $obs;
        }

        foreach ($this->repo()->listInstitutionalClaimEpisodes($facilityId) as $row) {
            $type = (string)($row['type'] ?? '');
            $episodeId = (int)($row['episode_id'] ?? 0);
            $claimFamily = 'UB04 / 837I';
            $recommended = 'Review in Billing Manager';
            $detail = (string)($row['status'] ?? '');

            if ($type === 'OBS' && isset($obsMap[$episodeId])) {
                $recommended = (string)($obsMap[$episodeId]['action_label'] ?? 'Review observation billing');
                $detail = (string)($obsMap[$episodeId]['status'] ?? $detail);
            } elseif (($row['status'] ?? '') === 'ACTIVE') {
                $recommended = 'Open episode — monitor until discharge / conversion';
            } elseif (!empty($row['end_datetime'])) {
                $recommended = 'Closed episode — review for release to Billing Manager';
            }

            $rows[] = [
                'candidate_type' => 'EPISODE',
                'episode_id' => $episodeId,
                'pid' => (int)($row['pid'] ?? 0),
                'eid' => isset($row['eid']) ? (int)$row['eid'] : null,
                'episode_type' => $type,
                'patient_name' => trim((string)($row['patient_name'] ?? '')),
                'service_date' => substr((string)($row['start_datetime'] ?? ''), 0, 10),
                'claim_family' => $claimFamily,
                'recommended_path' => 'CLAIM_MANAGER',
                'recommended_action' => $recommended,
                'detail' => $detail,
                'source_label' => (string)($row['chief_complaint'] ?? ''),
                'external_ref' => 'EPISODE:' . $episodeId . ':' . $type,
                'staging_description' => $type . ' episode claim staging',
            ];
        }

        foreach ($this->repo()->listRecentHbcVisits($facilityId) as $row) {
            $serviceDate = substr((string)($row['service_dt'] ?? ''), 0, 10);
            $visitType = (string)($row['visit_type'] ?? 'Visit');
            $rows[] = [
                'candidate_type' => 'HBC_VISIT',
                'episode_id' => (int)($row['episode_id'] ?? 0),
                'pid' => (int)($row['pid'] ?? 0),
                'eid' => isset($row['eid']) ? (int)$row['eid'] : null,
                'episode_type' => 'HBC',
                'patient_name' => trim((string)($row['patient_name'] ?? '')),
                'service_date' => $serviceDate,
                'claim_family' => 'Professional / CMS-1500 review',
                'recommended_path' => 'PROFESSIONAL_REVIEW',
                'recommended_action' => 'Completed visit — review for professional claim posting',
                'detail' => (string)($row['status'] ?? ''),
                'source_label' => $visitType,
                'external_ref' => 'HBCVISIT:' . (int)($row['episode_id'] ?? 0) . ':' . $serviceDate . ':' . $visitType,
                'staging_description' => 'HBC completed visit professional review',
            ];
        }

        $existing = $this->repo()->existingExternalRefs($facilityId, array_map(static fn(array $r): string => (string)($r['external_ref'] ?? ''), $rows));
        foreach ($rows as &$row) {
            $row['staged'] = !empty($existing[(string)($row['external_ref'] ?? '')]);
        }
        unset($row);

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string)($b['service_date'] ?? ''), (string)($a['service_date'] ?? ''));
        });
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function billingExceptions(int $facilityId): array
    {
        return $this->repo()->billingExceptions($facilityId);
    }

    /** @return array<int,array<string,mixed>> */
    public function ledgerLines(int $facilityId, string $status = '', string $billingPath = ''): array
    {
        return $this->repo()->listLedgerLines($facilityId, $status, $billingPath);
    }

    /** @return array<int,array<string,mixed>> */
    public function batchLines(int $facilityId, string $batchKey): array
    {
        return $this->repo()->batchLines($facilityId, $batchKey);
    }

    /** @return array<int,array<string,mixed>> */
    public function agingSummary(int $facilityId): array
    {
        return $this->repo()->agingSummary($facilityId);
    }

    /** @return array<int,array<string,mixed>> */
    public function episodeFinancialSummary(int $facilityId): array
    {
        return $this->repo()->episodeFinancialSummary($facilityId);
    }

    /** @return array<int,array<string,mixed>> */
    public function releaseBatchHistory(int $facilityId): array
    {
        return $this->repo()->releaseBatchHistory($facilityId);
    }

    /** @return array<int,array<string,string>> */
    public function quickAddTemplates(int $facilityId): array
    {
        $purpose = (string)($this->profileRepo()->getFacilityDefault($facilityId)['installed_purpose'] ?? '');
        return match ($purpose) {
            FacilityProfileRepository::PURPOSE_AL_ONLY => [
                ['label' => 'Monthly service bundle', 'category' => 'RECURRING', 'path' => 'MODULE_LEDGER', 'desc' => 'Monthly assisted living service bundle', 'price' => '2500.00'],
                ['label' => 'Medication admin fee', 'category' => 'SERVICE', 'path' => 'MODULE_LEDGER', 'desc' => 'Medication administration service', 'price' => '35.00'],
                ['label' => 'Supply charge', 'category' => 'SUPPLY', 'path' => 'MODULE_LEDGER', 'desc' => 'Resident supply / sundry charge', 'price' => '15.00'],
            ],
            FacilityProfileRepository::PURPOSE_HOME_BASED_CARE => [
                ['label' => 'Care coordination fee', 'category' => 'SERVICE', 'path' => 'MODULE_LEDGER', 'desc' => 'Home-based care coordination service', 'price' => '45.00'],
                ['label' => 'Visit mileage / travel', 'category' => 'ADJUSTMENT', 'path' => 'MODULE_LEDGER', 'desc' => 'Home visit travel / mileage adjustment', 'price' => '22.00'],
                ['label' => 'Supply charge', 'category' => 'SUPPLY', 'path' => 'MODULE_LEDGER', 'desc' => 'In-home supply / dressing charge', 'price' => '18.00'],
            ],
            FacilityProfileRepository::PURPOSE_ED_OBS_BH, FacilityProfileRepository::PURPOSE_INPATIENT => [
                ['label' => 'Late charge adjustment', 'category' => 'ADJUSTMENT', 'path' => 'MODULE_LEDGER', 'desc' => 'Late institutional charge adjustment', 'price' => '0.00'],
                ['label' => 'Comfort item / private-pay', 'category' => 'PRIVATE_PAY', 'path' => 'MODULE_LEDGER', 'desc' => 'Private-pay comfort / convenience item', 'price' => '25.00'],
                ['label' => 'Supply charge', 'category' => 'SUPPLY', 'path' => 'MODULE_LEDGER', 'desc' => 'Additional supply charge', 'price' => '12.00'],
            ],
            default => [
                ['label' => 'Service line', 'category' => 'SERVICE', 'path' => 'MODULE_LEDGER', 'desc' => 'General institutional service line', 'price' => '50.00'],
                ['label' => 'Private-pay line', 'category' => 'PRIVATE_PAY', 'path' => 'MODULE_LEDGER', 'desc' => 'Private-pay / self-pay line', 'price' => '25.00'],
                ['label' => 'Adjustment', 'category' => 'ADJUSTMENT', 'path' => 'MODULE_LEDGER', 'desc' => 'Billing adjustment line', 'price' => '0.00'],
            ],
        };
    }

    /** @param array<string,mixed> $data */
    public function addLedgerLine(int $facilityId, array $data, ?int $userId = null): void
    {
        $this->repo()->addLedgerLine($facilityId, $data, $userId);
    }

    public function setLedgerStatus(int $facilityId, int $lineId, string $status, ?int $userId = null, ?string $reviewReason = null): void
    {
        $this->repo()->setLedgerStatus($facilityId, $lineId, $status, $userId, $reviewReason);
    }

    public function stageClaimCandidates(int $facilityId, ?int $userId = null): int
    {
        $count = 0;
        foreach ($this->claimCandidates($facilityId) as $candidate) {
            if (!empty($candidate['staged'])) {
                continue;
            }
            $this->repo()->stageClaimCandidateLine($facilityId, $candidate, $userId);
            $count++;
        }
        return $count;
    }

    public function stageOneCandidate(int $facilityId, string $candidateType, int $episodeId, string $serviceDate, ?int $userId = null): void
    {
        foreach ($this->claimCandidates($facilityId) as $candidate) {
            if ((string)($candidate['candidate_type'] ?? '') !== $candidateType) {
                continue;
            }
            if ((int)($candidate['episode_id'] ?? 0) !== $episodeId) {
                continue;
            }
            if ((string)($candidate['service_date'] ?? '') !== $serviceDate) {
                continue;
            }
            if (!empty($candidate['staged'])) {
                return;
            }
            $this->repo()->stageClaimCandidateLine($facilityId, $candidate, $userId);
            return;
        }
    }

    public function releaseLedgerLine(int $facilityId, int $lineId, string $target, ?int $userId = null): void
    {
        $this->repo()->releaseLedgerLine($facilityId, $lineId, $target, $userId);
    }

    public function releaseReadyByPath(int $facilityId, string $billingPath, ?int $userId = null): void
    {
        $mode = $this->billingModeMeta($facilityId);
        $target = match ($billingPath) {
            'CLAIM_MANAGER' => (string)($mode['release_target'] ?? 'BILLING_MANAGER'),
            'PROFESSIONAL_REVIEW' => 'PROFESSIONAL',
            default => 'LEDGER',
        };
        $this->repo()->releaseReadyByPath($facilityId, $billingPath, $target, $userId);
    }
}






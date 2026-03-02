<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\EReferral\Repository\EReferralRepository;
use OpenEMR\Modules\Institutional\Operations\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;

/**
 * E-Referral Service.
 *
 * Disposition-to-referral-type mapping:
 *   DISCHARGE → DISCHARGE
 *   TRANSFER  → TRANSFER
 *   ADMIT     → TRANSFER  (inpatient admission = internal transfer referral)
 *
 * oei_triage column names (confirmed from schema):
 *   bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, weight_kg, pain_score
 */
final class EReferralService
{
    private const REFERRAL_DISPOSITIONS = ['DISCHARGE', 'TRANSFER', 'ADMIT'];

    public function __construct(
        private readonly EReferralRepository $repo,
        private readonly FacilityDirectoryRepository $directoryRepo
    ) {}

    /**
     * @param array<string,mixed> $episode
     * @param array<string,mixed> $disposition
     * @param array<string,mixed>|null $triage
     */
    public function draftFromDisposition(
        array $episode,
        array $disposition,
        ?array $triage,
        ?int $userId
    ): void {
        $dispCode = strtoupper((string)($disposition['disposition_code'] ?? ''));
        if (!in_array($dispCode, self::REFERRAL_DISPOSITIONS, true)) {
            return;
        }

        $existing = $this->repo->getByEpisode((int)$episode['id']);
        if ($existing && !in_array($existing['status'], ['DRAFT'], true)) {
            return;
        }

        $episodeId  = (int)$episode['id'];
        $pid        = (int)$episode['pid'];
        $eid        = isset($episode['eid']) && is_numeric($episode['eid']) ? (int)$episode['eid'] : null;
        $facilityId = (int)$episode['facility_id'];

        $type     = $this->mapReferralType($dispCode);
        $priority = $this->mapPriority($dispCode, $episode);

        $destination    = trim((string)($disposition['destination'] ?? ''));
        $directoryId    = null;
        $directoryFax   = null;
        $directoryPhone = null;
        $directoryAddr  = null;

        if ($destination !== '') {
            $match = $this->matchDirectory($facilityId, $destination);
            if ($match) {
                $directoryId    = (int)$match['id'];
                $directoryFax   = (string)($match['fax']     ?? '');
                $directoryPhone = (string)($match['phone']   ?? '');
                $directoryAddr  = (string)($match['address'] ?? '');
            }
        }

        $fields = [
            'referral_type'            => $type,
            'status'                   => 'DRAFT',
            'priority'                 => $priority,
            'destination_directory_id' => $directoryId ?: null,
            'destination_name'         => $destination ?: null,
            'destination_fax'          => $directoryFax  ?: null,
            'destination_phone'        => $directoryPhone ?: null,
            'destination_address'      => $directoryAddr  ?: null,
            'reason_for_referral'      => $this->buildReason($dispCode, $episode, $disposition),
            'clinical_summary'         => $this->buildClinicalSummary($episode, $triage),
            'services_requested'       => $this->suggestServices($dispCode, $episode),
            'medications_summary'      => null,
            'followup_instructions'    => null,
        ];

        $this->repo->upsert($episodeId, $pid, $eid, $facilityId, $fields, $userId);
    }

    /**
     * @param array<string,mixed> $post
     */
    public function applyEdit(
        int $episodeId,
        int $pid,
        ?int $eid,
        int $facilityId,
        array $post,
        ?int $userId
    ): void {
        $dirId = isset($post['destination_directory_id']) && (int)$post['destination_directory_id'] > 0
            ? (int)$post['destination_directory_id']
            : null;

        $dirFax = $dirPhone = $dirAddr = null;
        if ($dirId) {
            $entry = $this->directoryRepo->get($facilityId, $dirId);
            if ($entry) {
                $dirFax   = (string)($entry['fax']     ?? '');
                $dirPhone = (string)($entry['phone']   ?? '');
                $dirAddr  = (string)($entry['address'] ?? '');
            }
        }

        $fields = [
            'referral_type'            => strtoupper(trim((string)($post['referral_type'] ?? 'DISCHARGE'))),
            'status'                   => 'DRAFT',
            'priority'                 => strtoupper(trim((string)($post['priority'] ?? 'ROUTINE'))),
            'destination_directory_id' => $dirId,
            'destination_name'         => trim((string)($post['destination_name']    ?? '')) ?: null,
            'destination_fax'          => $dirFax  ?: (trim((string)($post['destination_fax']   ?? '')) ?: null),
            'destination_phone'        => $dirPhone ?: (trim((string)($post['destination_phone'] ?? '')) ?: null),
            'destination_address'      => $dirAddr  ?: (trim((string)($post['destination_address'] ?? '')) ?: null),
            'reason_for_referral'      => trim((string)($post['reason_for_referral']  ?? '')) ?: null,
            'clinical_summary'         => trim((string)($post['clinical_summary']     ?? '')) ?: null,
            'services_requested'       => trim((string)($post['services_requested']   ?? '')) ?: null,
            'medications_summary'      => trim((string)($post['medications_summary']  ?? '')) ?: null,
            'followup_instructions'    => trim((string)($post['followup_instructions'] ?? '')) ?: null,
        ];

        $this->repo->upsert($episodeId, $pid, $eid, $facilityId, $fields, $userId);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function mapReferralType(string $dispCode): string
    {
        return match ($dispCode) {
            'TRANSFER', 'ADMIT' => 'TRANSFER',
            default             => 'DISCHARGE',
        };
    }

    /** @param array<string,mixed> $episode */
    private function mapPriority(string $dispCode, array $episode): string
    {
        if ($dispCode === 'TRANSFER') {
            $esi = (int)($episode['acuity_esi'] ?? 5);
            if ($esi <= 2) return 'EMERGENT';
            if ($esi === 3) return 'URGENT';
        }
        return 'ROUTINE';
    }

    /**
     * @param array<string,mixed> $episode
     * @param array<string,mixed> $disposition
     */
    private function buildReason(string $dispCode, array $episode, array $disposition): string
    {
        $parts = [];
        $cc   = (string)($episode['chief_complaint'] ?? '');
        $esi  = (string)($episode['acuity_esi'] ?? '');
        $dest = (string)($disposition['destination'] ?? '');
        $note = (string)($disposition['notes'] ?? '');
        if ($cc !== '')   $parts[] = "Chief complaint: {$cc}";
        if ($esi !== '')  $parts[] = "ESI acuity: {$esi}";
        if ($dispCode === 'TRANSFER' && $dest !== '') $parts[] = "Transfer to: {$dest}";
        if ($note !== '') $parts[] = $note;
        return implode('. ', $parts) ?: 'Discharge/transfer referral';
    }

    /**
     * Build clinical summary from episode + latest triage row.
     * Uses confirmed oei_triage column names: hr, rr, temp_f (NOT pulse/respirations/temperature).
     *
     * @param array<string,mixed>      $episode
     * @param array<string,mixed>|null $triage
     */
    private function buildClinicalSummary(array $episode, ?array $triage): string
    {
        $lines = [];
        $episodeType = (string)($episode['type'] ?? 'ED');
        $start       = (string)($episode['start_datetime'] ?? '');
        $esi         = (string)($episode['acuity_esi'] ?? '');
        $cc          = (string)($episode['chief_complaint'] ?? '');

        $lines[] = "Episode type: {$episodeType}" . ($start ? " | Arrival: {$start}" : '');
        if ($cc !== '')  $lines[] = "Chief complaint: {$cc}";
        if ($esi !== '') $lines[] = "ESI acuity: {$esi}";

        if ($triage) {
            $vitals = [];
            // Use actual schema column names: hr, rr, temp_f
            if (!empty($triage['bp_systolic']) && !empty($triage['bp_diastolic'])) {
                $vitals[] = "BP {$triage['bp_systolic']}/{$triage['bp_diastolic']}";
            }
            if (!empty($triage['hr'])) {
                $vitals[] = "HR {$triage['hr']}";
            }
            if (!empty($triage['rr'])) {
                $vitals[] = "RR {$triage['rr']}";
            }
            if (!empty($triage['temp_f'])) {
                $vitals[] = "Temp " . number_format((float)$triage['temp_f'], 1) . "°F";
            }
            if (!empty($triage['spo2'])) {
                $vitals[] = "SpO2 {$triage['spo2']}%";
            }
            if (!empty($triage['gcs'])) {
                $vitals[] = "GCS {$triage['gcs']}";
            }
            if (!empty($triage['weight_kg'])) {
                $vitals[] = "Wt {$triage['weight_kg']} kg";
            }
            if ($vitals) {
                $lines[] = "Vitals: " . implode(', ', $vitals);
            }
        }

        return implode("\n", $lines);
    }

    /** @param array<string,mixed> $episode */
    private function suggestServices(string $dispCode, array $episode): string
    {
        if ($dispCode === 'TRANSFER') return 'Accept transfer, continued monitoring and treatment';
        if ($dispCode === 'ADMIT')    return 'Inpatient admission, ongoing care';
        return (string)($episode['type'] ?? '') === 'OBS'
            ? 'Post-observation follow-up within 7 days, home health if indicated'
            : 'Primary care follow-up within 7 days';
    }

    /** @return array<string,mixed>|null */
    private function matchDirectory(int $facilityId, string $destination): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery(
            "SELECT id, name, fax, phone, address
             FROM oei_facility_directory
             WHERE facility_id = ? AND is_active = 1
               AND LOWER(name) LIKE ?
             ORDER BY sort_order ASC
             LIMIT 1",
            [$facilityId, '%' . strtolower($destination) . '%']
        );
        return $row ?: null;
    }
}

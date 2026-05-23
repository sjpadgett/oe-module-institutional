<?php

/**
 * src/Shared/Submodule/Billing/Repository/BillingWorkbenchRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Billing\Repository;

final class BillingWorkbenchRepository
{
    public function tableReady(): bool
    {
        if (!function_exists('sqlQuery')) {
            return false;
        }
        try {
            $row = sqlQuery(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'oei_billing_line' LIMIT 1"
            );
            return !empty($row);
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listLedgerLines(int $facilityId, string $status = '', string $billingPath = ''): array
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        $sql = "SELECT bl.*, CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name
                  FROM oei_billing_line bl
             LEFT JOIN patient_data pd ON pd.pid = bl.pid
                 WHERE bl.facility_id = ?";
        $params = [$facilityId];
        if ($status !== '') {
            $sql .= " AND bl.status = ?";
            $params[] = $status;
        }
        if ($billingPath !== '') {
            $sql .= " AND bl.billing_path = ?";
            $params[] = $billingPath;
        }
        $sql .= " ORDER BY bl.service_date DESC, bl.id DESC LIMIT 250";
        $res = sqlStatement($sql, $params);
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function batchLines(int $facilityId, string $batchKey): array
    {
        if ($facilityId <= 0 || $batchKey === '' || !$this->tableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT bl.*, CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name
               FROM oei_billing_line bl
          LEFT JOIN patient_data pd ON pd.pid = bl.pid
              WHERE bl.facility_id = ? AND bl.release_batch_key = ?
           ORDER BY bl.service_date DESC, bl.id DESC",
            [$facilityId, $batchKey]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,mixed> */
    public function ledgerSummary(int $facilityId): array
    {
        $out = [
            'total_lines' => 0,
            'ready_lines' => 0,
            'hold_lines' => 0,
            'released_lines' => 0,
            'ledger_total' => 0.0,
            'ready_total' => 0.0,
            'hold_total' => 0.0,
            'released_total' => 0.0,
            'exception_lines' => 0,
            'staged_lines' => 0,
            'claim_release_ready' => 0,
            'professional_release_ready' => 0,
        ];
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlQuery')) {
            return $out;
        }
        try {
            $row = sqlQuery(
                "SELECT COUNT(*) AS total_lines,
                        SUM(CASE WHEN status = 'READY' THEN 1 ELSE 0 END) AS ready_lines,
                        SUM(CASE WHEN status = 'HOLD' THEN 1 ELSE 0 END) AS hold_lines,
                        SUM(CASE WHEN status = 'RELEASED' THEN 1 ELSE 0 END) AS released_lines,
                        COALESCE(SUM(total_amount),0) AS ledger_total,
                        COALESCE(SUM(CASE WHEN status = 'READY' THEN total_amount ELSE 0 END),0) AS ready_total,
                        COALESCE(SUM(CASE WHEN status = 'HOLD' THEN total_amount ELSE 0 END),0) AS hold_total,
                        COALESCE(SUM(CASE WHEN status = 'RELEASED' THEN total_amount ELSE 0 END),0) AS released_total,
                        SUM(CASE WHEN status IN ('HOLD','DRAFT') OR ((billing_path IN ('CLAIM_MANAGER','PROFESSIONAL_REVIEW')) AND (charge_code IS NULL OR charge_code = '' OR total_amount <= 0)) THEN 1 ELSE 0 END) AS exception_lines,
                        SUM(CASE WHEN line_category = 'CLAIM_STAGING' THEN 1 ELSE 0 END) AS staged_lines,
                        SUM(CASE WHEN billing_path = 'CLAIM_MANAGER' AND status = 'READY' THEN 1 ELSE 0 END) AS claim_release_ready,
                        SUM(CASE WHEN billing_path = 'PROFESSIONAL_REVIEW' AND status = 'READY' THEN 1 ELSE 0 END) AS professional_release_ready
                   FROM oei_billing_line
                  WHERE facility_id = ?",
                [$facilityId]
            );
            if (is_array($row)) {
                $out = [
                    'total_lines' => (int)($row['total_lines'] ?? 0),
                    'ready_lines' => (int)($row['ready_lines'] ?? 0),
                    'hold_lines' => (int)($row['hold_lines'] ?? 0),
                    'released_lines' => (int)($row['released_lines'] ?? 0),
                    'ledger_total' => (float)($row['ledger_total'] ?? 0),
                    'ready_total' => (float)($row['ready_total'] ?? 0),
                    'hold_total' => (float)($row['hold_total'] ?? 0),
                    'released_total' => (float)($row['released_total'] ?? 0),
                    'exception_lines' => (int)($row['exception_lines'] ?? 0),
                    'staged_lines' => (int)($row['staged_lines'] ?? 0),
                    'claim_release_ready' => (int)($row['claim_release_ready'] ?? 0),
                    'professional_release_ready' => (int)($row['professional_release_ready'] ?? 0),
                ];
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function listInstitutionalClaimEpisodes(int $facilityId): array
    {
        if ($facilityId <= 0 || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT e.id AS episode_id, e.pid, e.eid, e.type, e.status, e.start_datetime, e.end_datetime,
                    e.disposition, e.chief_complaint,
                    CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name
               FROM oei_episode e
          LEFT JOIN patient_data pd ON pd.pid = e.pid
              WHERE e.facility_id = ?
                AND e.type IN ('ED','OBS','BH','IP')
                AND (e.status = 'ACTIVE' OR e.end_datetime >= DATE_SUB(NOW(), INTERVAL 14 DAY)
                     OR e.start_datetime >= DATE_SUB(NOW(), INTERVAL 14 DAY))
           ORDER BY COALESCE(e.end_datetime, e.start_datetime) DESC
              LIMIT 120",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function listRecentHbcVisits(int $facilityId): array
    {
        if ($facilityId <= 0 || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT hv.id AS visit_id, hv.episode_id, he.pid, e.eid, hv.visit_type, hv.status,
                    COALESCE(hv.actual_end_datetime, hv.actual_start_datetime, hv.scheduled_datetime) AS service_dt,
                    hv.next_visit_due_date, hv.next_visit_type,
                    CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name
               FROM oei_hbc_visit hv
               JOIN oei_hbc_episode he ON he.episode_id = hv.episode_id
               JOIN oei_episode e ON e.id = hv.episode_id
          LEFT JOIN patient_data pd ON pd.pid = he.pid
              WHERE hv.facility_id = ?
                AND hv.status IN ('COMPLETE','REFUSED','MISSED')
                AND COALESCE(hv.actual_end_datetime, hv.actual_start_datetime, hv.scheduled_datetime) >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           ORDER BY service_dt DESC
              LIMIT 120",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<string,bool> */
    public function existingExternalRefs(int $facilityId, array $refs): array
    {
        $out = [];
        $refs = array_values(array_filter(array_map('strval', $refs), static fn(string $v): bool => $v !== ''));
        if ($facilityId <= 0 || !$this->tableReady() || !$refs || !function_exists('sqlStatement')) {
            return $out;
        }
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $res = sqlStatement(
            "SELECT external_ref FROM oei_billing_line WHERE facility_id = ? AND external_ref IN ($placeholders)",
            array_merge([$facilityId], $refs)
        );
        while ($row = sqlFetchArray($res)) {
            $out[(string)($row['external_ref'] ?? '')] = true;
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    public function billingExceptions(int $facilityId): array
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT bl.*, CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name,
                    CASE
                        WHEN bl.status = 'HOLD' AND COALESCE(bl.review_reason,'') <> '' THEN bl.review_reason
                        WHEN bl.status = 'HOLD' THEN 'Line is on hold'
                        WHEN (bl.billing_path IN ('CLAIM_MANAGER','PROFESSIONAL_REVIEW')) AND (bl.charge_code IS NULL OR bl.charge_code = '') THEN 'Charge code needed for claim/review line'
                        WHEN (bl.billing_path IN ('CLAIM_MANAGER','PROFESSIONAL_REVIEW')) AND bl.total_amount <= 0 THEN 'Charge amount should be greater than zero'
                        WHEN bl.description IS NULL OR bl.description = '' THEN 'Description is required'
                        ELSE 'Review recommended'
                    END AS exception_reason
               FROM oei_billing_line bl
          LEFT JOIN patient_data pd ON pd.pid = bl.pid
              WHERE bl.facility_id = ?
                AND (
                    bl.status IN ('HOLD','DRAFT')
                    OR ((bl.billing_path IN ('CLAIM_MANAGER','PROFESSIONAL_REVIEW')) AND (bl.charge_code IS NULL OR bl.charge_code = '' OR bl.total_amount <= 0))
                )
           ORDER BY bl.service_date DESC, bl.id DESC
              LIMIT 120",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function agingSummary(int $facilityId): array
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlQuery')) {
            return [];
        }
        $buckets = [
            ['bucket' => '0-7 days', 'ready_count' => 0, 'hold_count' => 0, 'ready_total' => 0.0, 'hold_total' => 0.0],
            ['bucket' => '8-30 days', 'ready_count' => 0, 'hold_count' => 0, 'ready_total' => 0.0, 'hold_total' => 0.0],
            ['bucket' => '31+ days', 'ready_count' => 0, 'hold_count' => 0, 'ready_total' => 0.0, 'hold_total' => 0.0],
        ];
        try {
            $row = sqlQuery(
                "SELECT
                    SUM(CASE WHEN status = 'READY' AND service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS ready_0_7,
                    SUM(CASE WHEN status = 'HOLD' AND service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS hold_0_7,
                    COALESCE(SUM(CASE WHEN status = 'READY' AND service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END),0) AS ready_total_0_7,
                    COALESCE(SUM(CASE WHEN status = 'HOLD' AND service_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END),0) AS hold_total_0_7,
                    SUM(CASE WHEN status = 'READY' AND service_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ready_8_30,
                    SUM(CASE WHEN status = 'HOLD' AND service_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS hold_8_30,
                    COALESCE(SUM(CASE WHEN status = 'READY' AND service_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END),0) AS ready_total_8_30,
                    COALESCE(SUM(CASE WHEN status = 'HOLD' AND service_date < DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND service_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END),0) AS hold_total_8_30,
                    SUM(CASE WHEN status = 'READY' AND service_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS ready_31,
                    SUM(CASE WHEN status = 'HOLD' AND service_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS hold_31,
                    COALESCE(SUM(CASE WHEN status = 'READY' AND service_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END),0) AS ready_total_31,
                    COALESCE(SUM(CASE WHEN status = 'HOLD' AND service_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END),0) AS hold_total_31
                 FROM oei_billing_line WHERE facility_id = ?",
                [$facilityId]
            );
            if (is_array($row)) {
                $buckets[0]['ready_count'] = (int)($row['ready_0_7'] ?? 0);
                $buckets[0]['hold_count'] = (int)($row['hold_0_7'] ?? 0);
                $buckets[0]['ready_total'] = (float)($row['ready_total_0_7'] ?? 0);
                $buckets[0]['hold_total'] = (float)($row['hold_total_0_7'] ?? 0);
                $buckets[1]['ready_count'] = (int)($row['ready_8_30'] ?? 0);
                $buckets[1]['hold_count'] = (int)($row['hold_8_30'] ?? 0);
                $buckets[1]['ready_total'] = (float)($row['ready_total_8_30'] ?? 0);
                $buckets[1]['hold_total'] = (float)($row['hold_total_8_30'] ?? 0);
                $buckets[2]['ready_count'] = (int)($row['ready_31'] ?? 0);
                $buckets[2]['hold_count'] = (int)($row['hold_31'] ?? 0);
                $buckets[2]['ready_total'] = (float)($row['ready_total_31'] ?? 0);
                $buckets[2]['hold_total'] = (float)($row['hold_total_31'] ?? 0);
            }
        } catch (\Throwable) {
        }
        return $buckets;
    }

    /** @return array<int,array<string,mixed>> */
    public function episodeFinancialSummary(int $facilityId): array
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT COALESCE(bl.episode_id,0) AS episode_id, bl.pid,
                    MAX(bl.context_key) AS context_key, MAX(bl.episode_type) AS episode_type,
                    CONCAT(COALESCE(pd.fname,''), ' ', COALESCE(pd.lname,'')) AS patient_name,
                    COUNT(*) AS line_count,
                    SUM(CASE WHEN bl.status IN ('DRAFT','READY','HOLD') THEN bl.total_amount ELSE 0 END) AS outstanding_amount,
                    SUM(CASE WHEN bl.status = 'RELEASED' THEN bl.total_amount ELSE 0 END) AS released_amount,
                    SUM(CASE WHEN bl.status = 'READY' THEN 1 ELSE 0 END) AS ready_count,
                    SUM(CASE WHEN bl.status = 'HOLD' THEN 1 ELSE 0 END) AS hold_count,
                    SUM(CASE WHEN bl.status = 'RELEASED' THEN 1 ELSE 0 END) AS released_count,
                    MAX(bl.service_date) AS latest_service_date
               FROM oei_billing_line bl
          LEFT JOIN patient_data pd ON pd.pid = bl.pid
              WHERE bl.facility_id = ? AND bl.status <> 'VOID'
           GROUP BY COALESCE(bl.episode_id,0), bl.pid, patient_name
           ORDER BY outstanding_amount DESC, latest_service_date DESC
              LIMIT 120",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function releaseBatchHistory(int $facilityId): array
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT release_batch_key, release_target,
                    COUNT(*) AS line_count,
                    COALESCE(SUM(total_amount),0) AS total_amount,
                    MAX(released_datetime) AS released_datetime,
                    MAX(released_by_user_id) AS released_by_user_id
               FROM oei_billing_line
              WHERE facility_id = ? AND status = 'RELEASED' AND release_batch_key IS NOT NULL AND release_batch_key <> ''
           GROUP BY release_batch_key, release_target
           ORDER BY MAX(released_datetime) DESC
              LIMIT 80",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) {
            $rows[] = $row;
        }
        return $rows;
    }

    private function nextBatchKey(string $target): string
    {
        $prefix = match ($target) {
            'UB04' => 'UB4',
            'BILLING_MANAGER' => 'BM',
            'PROFESSIONAL' => 'PRO',
            'STATEMENT' => 'STM',
            default => 'LED',
        };
        return $prefix . '-' . date('Ymd-His');
    }

    /** @param array<string,mixed> $data */
    public function addLedgerLine(int $facilityId, array $data, ?int $userId = null): void
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return;
        }
        $episodeId = max(0, (int)($data['episode_id'] ?? 0));
        $pid = max(0, (int)($data['pid'] ?? 0));
        $eid = max(0, (int)($data['eid'] ?? 0));
        $episodeType = trim((string)($data['episode_type'] ?? ''));
        if ($episodeId > 0 && function_exists('sqlQuery')) {
            $ep = sqlQuery(
                "SELECT pid, eid, type FROM oei_episode WHERE id = ? LIMIT 1",
                [$episodeId]
            );
            if (is_array($ep)) {
                $pid = $pid > 0 ? $pid : (int)($ep['pid'] ?? 0);
                $eid = $eid > 0 ? $eid : (int)($ep['eid'] ?? 0);
                $episodeType = $episodeType !== '' ? $episodeType : (string)($ep['type'] ?? '');
            }
        }

        $serviceDate = trim((string)($data['service_date'] ?? date('Y-m-d')));
        if ($serviceDate === '') {
            $serviceDate = date('Y-m-d');
        }
        $billingPath = strtoupper(trim((string)($data['billing_path'] ?? 'MODULE_LEDGER')));
        if (!in_array($billingPath, ['CLAIM_MANAGER','MODULE_LEDGER','PROFESSIONAL_REVIEW'], true)) {
            $billingPath = 'MODULE_LEDGER';
        }
        $lineCategory = strtoupper(trim((string)($data['line_category'] ?? 'SERVICE')));
        if (!in_array($lineCategory, ['PRIVATE_PAY','RECURRING','SERVICE','SUPPLY','ADJUSTMENT','CLAIM_STAGING'], true)) {
            $lineCategory = 'SERVICE';
        }
        $status = strtoupper(trim((string)($data['status'] ?? 'READY')));
        if (!in_array($status, ['DRAFT','READY','HOLD','RELEASED','VOID'], true)) {
            $status = 'READY';
        }
        $qty = max(0.01, (float)($data['quantity'] ?? 1));
        $unitPrice = max(0.0, (float)($data['unit_price'] ?? 0));
        $total = round($qty * $unitPrice, 2);
        $chargeCode = trim((string)($data['charge_code'] ?? ''));
        $description = trim((string)($data['description'] ?? ''));
        if ($description === '') {
            return;
        }
        $notes = trim((string)($data['notes'] ?? '')) ?: null;
        $externalRef = trim((string)($data['external_ref'] ?? '')) ?: null;
        $contextKey = trim((string)($data['context_key'] ?? '')) ?: null;
        $sourceLabel = trim((string)($data['source_label'] ?? '')) ?: null;
        $reviewReason = trim((string)($data['review_reason'] ?? '')) ?: null;
        $releaseTarget = trim((string)($data['release_target'] ?? '')) ?: null;
        if (!in_array($releaseTarget, ['BILLING_MANAGER','UB04','PROFESSIONAL','LEDGER','STATEMENT'], true)) {
            $releaseTarget = null;
        }
        $now = date('Y-m-d H:i:s');

        sqlStatement(
            "INSERT INTO oei_billing_line
                (facility_id, episode_id, pid, eid, context_key, episode_type, billing_path, line_category, status,
                 review_reason, service_date, charge_code, description, quantity, unit_price, total_amount, external_ref,
                 source_label, notes, release_target, release_batch_key, created_by_user_id, updated_by_user_id, created_datetime, updated_datetime)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $facilityId,
                $episodeId > 0 ? $episodeId : null,
                $pid > 0 ? $pid : null,
                $eid > 0 ? $eid : null,
                $contextKey,
                $episodeType !== '' ? $episodeType : null,
                $billingPath,
                $lineCategory,
                $status,
                $reviewReason,
                $serviceDate,
                $chargeCode !== '' ? $chargeCode : null,
                $description,
                $qty,
                $unitPrice,
                $total,
                $externalRef,
                $sourceLabel,
                $notes,
                $releaseTarget,
                trim((string)($data['release_batch_key'] ?? '')) ?: null,
                $userId,
                $userId,
                $now,
                $now,
            ]
        );
    }

    /** @param array<string,mixed> $candidate */
    public function stageClaimCandidateLine(int $facilityId, array $candidate, ?int $userId = null): void
    {
        if ($facilityId <= 0 || !$this->tableReady()) {
            return;
        }
        $externalRef = trim((string)($candidate['external_ref'] ?? ''));
        if ($externalRef === '') {
            return;
        }
        if (($this->existingExternalRefs($facilityId, [$externalRef]))[$externalRef] ?? false) {
            return;
        }
        $path = (string)($candidate['recommended_path'] ?? 'MODULE_LEDGER');
        $claimFamily = (string)($candidate['claim_family'] ?? '');
        $releaseTarget = match ($path) {
            'CLAIM_MANAGER' => str_contains($claimFamily, 'UB04') ? 'UB04' : 'BILLING_MANAGER',
            'PROFESSIONAL_REVIEW' => 'PROFESSIONAL',
            default => 'LEDGER',
        };
        $description = (string)($candidate['staging_description'] ?? 'Claim staging line');
        $this->addLedgerLine($facilityId, [
            'episode_id' => $candidate['episode_id'] ?? null,
            'pid' => $candidate['pid'] ?? null,
            'eid' => $candidate['eid'] ?? null,
            'episode_type' => $candidate['episode_type'] ?? null,
            'context_key' => $candidate['context_key'] ?? null,
            'billing_path' => $path,
            'line_category' => 'CLAIM_STAGING',
            'status' => 'READY',
            'review_reason' => $candidate['detail'] ?? null,
            'service_date' => $candidate['service_date'] ?? date('Y-m-d'),
            'charge_code' => $candidate['charge_code'] ?? null,
            'description' => $description,
            'quantity' => 1,
            'unit_price' => 0,
            'external_ref' => $externalRef,
            'source_label' => $candidate['source_label'] ?? null,
            'notes' => $candidate['recommended_action'] ?? null,
            'release_target' => $releaseTarget,
        ], $userId);
    }

    public function setLedgerStatus(int $facilityId, int $lineId, string $status, ?int $userId = null, ?string $reviewReason = null): void
    {
        if ($facilityId <= 0 || $lineId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return;
        }
        $status = strtoupper(trim($status));
        if (!in_array($status, ['DRAFT','READY','HOLD','RELEASED','VOID'], true)) {
            return;
        }
        sqlStatement(
            "UPDATE oei_billing_line
                SET status = ?, review_reason = ?, updated_by_user_id = ?, updated_datetime = ?
              WHERE id = ? AND facility_id = ?",
            [$status, $reviewReason, $userId, date('Y-m-d H:i:s'), $lineId, $facilityId]
        );
    }

    public function releaseLedgerLine(int $facilityId, int $lineId, string $target, ?int $userId = null): void
    {
        if ($facilityId <= 0 || $lineId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return;
        }
        if (!in_array($target, ['BILLING_MANAGER','UB04','PROFESSIONAL','LEDGER','STATEMENT'], true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $batchKey = $this->nextBatchKey($target);
        sqlStatement(
            "UPDATE oei_billing_line
                SET status = 'RELEASED', release_target = ?, release_batch_key = ?, released_datetime = ?, released_by_user_id = ?,
                    updated_by_user_id = ?, updated_datetime = ?
              WHERE id = ? AND facility_id = ?",
            [$target, $batchKey, $now, $userId, $userId, $now, $lineId, $facilityId]
        );
    }

    public function releaseReadyByPath(int $facilityId, string $billingPath, string $target, ?int $userId = null): void
    {
        if ($facilityId <= 0 || !$this->tableReady() || !function_exists('sqlStatement')) {
            return;
        }
        if (!in_array($billingPath, ['CLAIM_MANAGER','PROFESSIONAL_REVIEW','MODULE_LEDGER'], true)) {
            return;
        }
        if (!in_array($target, ['BILLING_MANAGER','UB04','PROFESSIONAL','LEDGER','STATEMENT'], true)) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $batchKey = $this->nextBatchKey($target);
        sqlStatement(
            "UPDATE oei_billing_line
                SET status = 'RELEASED', release_target = ?, release_batch_key = ?, released_datetime = ?, released_by_user_id = ?,
                    updated_by_user_id = ?, updated_datetime = ?
              WHERE facility_id = ? AND billing_path = ? AND status = 'READY'",
            [$target, $batchKey, $now, $userId, $userId, $now, $facilityId, $billingPath]
        );
    }
}






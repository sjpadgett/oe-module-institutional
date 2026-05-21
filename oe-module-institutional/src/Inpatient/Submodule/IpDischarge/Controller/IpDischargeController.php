<?php

/**
 * src/Inpatient/Submodule/IpDischarge/Controller/IpDischargeController.php
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

namespace OpenEMR\Modules\Institutional\Inpatient\Submodule\IpDischarge\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Inpatient\Submodule\IpDischarge\Repository\IpDischargeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

/**
 * IpDischargeController
 *
 * Manages the two-stage inpatient discharge lifecycle:
 *
 *   Stage 1 — Plan  : record disposition code, destination, expected discharge
 *                     date, discharge summary narrative, notes.
 *                     Episode stays ACTIVE. Floor board shows pending badge.
 *
 *   Stage 2 — Confirm: record actual discharge datetime.
 *                      Episode → CLOSED via EpisodeRepository::closeWithDisposition()
 *                      which fires the HL7 A03 Discharge event.
 *
 * IP disposition codes (inpatient-specific, differs from AL):
 *   DISCHARGE_HOME   — discharged to home (with or without home health)
 *   SNF              — transfer to Skilled Nursing Facility
 *   REHAB            — transfer to inpatient rehabilitation
 *   HOSPICE          — transfer to hospice / comfort care
 *   TRANSFER         — transfer to another acute care facility
 *   AMA              — Against Medical Advice self-discharge
 *   EXPIRED          — patient died during admission
 */
final class IpDischargeController
{
    /** @var array<string,array{label:string,icon:string,closes:bool,urgent:bool}> */
    public const CODES = [
        'DISCHARGE_HOME' => ['label' => 'Discharge to Home',              'icon' => '🏠', 'closes' => true,  'urgent' => false],
        'SNF'            => ['label' => 'Transfer to SNF',                'icon' => '🏥', 'closes' => true,  'urgent' => false],
        'REHAB'          => ['label' => 'Inpatient Rehabilitation',       'icon' => '💪', 'closes' => true,  'urgent' => false],
        'HOSPICE'        => ['label' => 'Hospice / Comfort Care',         'icon' => '🕊️', 'closes' => true,  'urgent' => false],
        'TRANSFER'       => ['label' => 'Transfer to Acute Facility',     'icon' => '🚑', 'closes' => true,  'urgent' => false],
        'AMA'            => ['label' => 'Against Medical Advice (AMA)',   'icon' => '⚠️', 'closes' => true,  'urgent' => false],
        'EXPIRED'        => ['label' => 'Expired — Death During Admission','icon' => '✝', 'closes' => true,  'urgent' => false],
    ];

    private const PENDING_CODES = [];   // All IP codes close immediately on confirm

    public function __construct(
        private readonly IpDischargeRepository $repo,
        private readonly EpisodeRepository     $episodeRepo,
        private readonly EpisodeEventRepository $events
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $csrf   = CsrfUtils::collectCsrfToken();
        $flash  = '';
        $error  = '';

        $header = $this->repo->getPatientHeader($episodeId);
        $plan   = $this->repo->getPlan($episodeId);
        $closed = $header && ($header['status'] ?? '') === 'CLOSED';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$closed) {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = trim((string)($_POST['action'] ?? ''));

            if ($action === 'save_plan') {
                [$flash, $error] = $this->handleSavePlan($episodeId, $pid, $facilityId, $userId);
                $plan = $this->repo->getPlan($episodeId);
            }

            if ($action === 'confirm_departure') {
                [$flash, $error] = $this->handleConfirmDeparture(
                    $episodeId, $pid, $facilityId, $userId, $plan
                );
                $plan   = $this->repo->getPlan($episodeId);
                $header = $this->repo->getPatientHeader($episodeId);
                $closed = $header && ($header['status'] ?? '') === 'CLOSED';
            }
        }

        return [
            'csrf'         => $csrf,
            'header'       => $header,
            'plan'         => $plan,
            'closed'       => $closed,
            'flash'        => $flash,
            'error'        => $error,
            'codes'        => self::CODES,
            'pending_codes'=> self::PENDING_CODES,
        ];
    }

    /** @return array{0:string,1:string} [flash, error] */
    private function handleSavePlan(
        int $episodeId, int $pid, int $facilityId, ?int $userId
    ): array {
        $code             = strtoupper(trim((string)($_POST['disposition_code'] ?? '')));
        $destination      = trim((string)($_POST['destination'] ?? '')) ?: null;
        $decision         = trim((string)($_POST['decision_datetime'] ?? ''));
        $notes            = trim((string)($_POST['notes'] ?? '')) ?: null;
        $dischargeSummary = trim((string)($_POST['discharge_summary'] ?? '')) ?: null;

        if (!isset(self::CODES[$code])) {
            return ['', xlt('Invalid disposition code.')];
        }
        if ($code === 'EXPIRED' && empty($notes)) {
            return ['', xlt('Death documentation requires notes (circumstances, time, attending physician).')];
        }

        $decisionSql = $decision ? str_replace('T', ' ', $decision) . ':00' : null;

        $this->repo->savePlan(
            $episodeId, $pid, $facilityId,
            $code, $destination, $decisionSql,
            $notes, $dischargeSummary, $userId
        );

        $this->events->addEvent(
            $episodeId, $pid, null, $facilityId,
            'DISCHARGE_PLANNED',
            $decisionSql ?? date('Y-m-d H:i:s'),
            $userId,
            $code . ($destination ? ' → ' . $destination : '')
        );

        return [xlt('Discharge plan saved.'), ''];
    }

    /** @return array{0:string,1:string} [flash, error] */
    private function handleConfirmDeparture(
        int $episodeId, int $pid, int $facilityId, ?int $userId, ?array $plan
    ): array {
        $depart = trim((string)($_POST['depart_datetime'] ?? ''));
        if (empty($depart)) {
            return ['', xlt('Discharge date/time is required to confirm.')];
        }

        $departSql = str_replace('T', ' ', $depart) . ':00';
        $code      = (string)($plan['disposition_code'] ?? '');

        if (empty($code)) {
            return ['', xlt('Save a discharge plan before confirming discharge.')];
        }

        $this->repo->confirmDeparture($episodeId, $departSql, $userId);

        $this->events->addEvent(
            $episodeId, $pid, null, $facilityId,
            'DEPART',
            $departSql,
            $userId,
            $code . ($plan['destination'] ? ' → ' . $plan['destination'] : '')
        );

        // Closes episode + fires HL7 A03
        $this->episodeRepo->closeWithDisposition($episodeId, $code, $departSql);

        $label = self::CODES[$code]['label'] ?? $code;
        $flash = $code === 'EXPIRED'
            ? xlt('Patient death recorded. Episode closed.')
            : sprintf(xlt('Discharge confirmed: %s. Episode closed.'), $label);

        return [$flash, ''];
    }
}




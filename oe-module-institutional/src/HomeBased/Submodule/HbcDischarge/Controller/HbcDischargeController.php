<?php

/**
 * src/HomeBased/Submodule/HbcDischarge/Controller/HbcDischargeController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcDischarge\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcDischarge\Repository\HbcDischargeRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

/**
 * HbcDischargeController
 *
 * Two-stage HBC service closure lifecycle:
 *
 *   Stage 1 — Plan:    Record disposition code, reason/destination, decision date.
 *                      Episode stays ACTIVE. Profile shows pending closure badge.
 *   Stage 2 — Confirm: Record actual end_datetime.
 *                      Episode transitions to CLOSED via EpisodeRepository::closeWithDisposition().
 *                      HL7 A03 Discharge event fires automatically.
 *
 * HBC disposition codes differ from AL — home care service endings:
 *   SERVICE_COMPLETED — Goals met; patient/clinician agree services are no longer needed
 *   HOSPITAL_TRANSFER — Emergency hospitalisation (may reopen if returns home)
 *   SNF_TRANSFER      — Escalation to SNF, AL, or inpatient level of care
 *   SELF_DISCHARGE    — Patient/family chose to end services voluntarily
 *   NON_COMPLIANT     — Discharged for repeated non-compliance with care plan
 *   PAYER_CLOSED      — Insurance authorisation/benefit exhausted, no renewal
 *   DECEASED          — Death (at home or during transport/hospitalisation)
 */
final class HbcDischargeController
{
    public const CODES = [
        'SERVICE_COMPLETED' => ['label' => 'Service Completed — Goals Met',         'icon' => '✅', 'closes' => true,  'urgent' => false],
        'HOSPITAL_TRANSFER' => ['label' => 'Emergency Hospitalisation',             'icon' => '🚑', 'closes' => true,  'urgent' => true],
        'SNF_TRANSFER'      => ['label' => 'Transfer to SNF / AL / Inpatient',      'icon' => '🏥', 'closes' => true,  'urgent' => false],
        'SELF_DISCHARGE'    => ['label' => 'Patient / Family Self-Discharge',        'icon' => '⚠️', 'closes' => true,  'urgent' => false],
        'NON_COMPLIANT'     => ['label' => 'Discharged — Non-Compliance',           'icon' => '🚫', 'closes' => true,  'urgent' => false],
        'PAYER_CLOSED'      => ['label' => 'Authorization / Benefit Exhausted',     'icon' => '💳', 'closes' => true,  'urgent' => false],
        'DECEASED'          => ['label' => 'Deceased',                              'icon' => '✝',  'closes' => true,  'urgent' => false],
    ];

    private const PENDING_CODES = ['HOSPITAL_TRANSFER'];

    public function __construct(
        private readonly HbcDischargeRepository $repo,
        private readonly EpisodeRepository      $episodeRepo,
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
            'csrf'          => $csrf,
            'header'        => $header,
            'plan'          => $plan,
            'closed'        => $closed,
            'flash'         => $flash,
            'error'         => $error,
            'codes'         => self::CODES,
            'pending_codes' => self::PENDING_CODES,
        ];
    }

    /** @return array{0:string,1:string} [flash, error] */
    private function handleSavePlan(
        int $episodeId, int $pid, int $facilityId, ?int $userId
    ): array {
        $code        = strtoupper(trim((string)($_POST['disposition_code'] ?? '')));
        $destination = trim((string)($_POST['destination'] ?? '')) ?: null;
        $decision    = trim((string)($_POST['decision_datetime'] ?? ''));
        $notes       = trim((string)($_POST['notes'] ?? '')) ?: null;

        if (!isset(self::CODES[$code])) {
            return ['', xlt('Invalid disposition code.')];
        }

        if ($code === 'DECEASED' && empty($notes)) {
            return ['', xlt('Deceased discharge requires notes (circumstances, time, clinician).')];
        }

        $decisionSql = $decision
            ? str_replace('T', ' ', $decision) . ':00'
            : null;

        $this->repo->savePlan($episodeId, $pid, $facilityId,
            $code, $destination, $decisionSql, $notes, $userId);

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
            return ['', xlt('End-of-service date/time is required to confirm closure.')];
        }

        $departSql = str_replace('T', ' ', $depart) . ':00';
        $code      = (string)($plan['disposition_code'] ?? '');

        if (empty($code)) {
            return ['', xlt('Save a discharge plan before confirming closure.')];
        }

        $this->repo->confirmDeparture($episodeId, $departSql, $userId);

        $this->events->addEvent(
            $episodeId, $pid, null, $facilityId,
            'DEPART',
            $departSql,
            $userId,
            $code . ($plan['destination'] ? ' → ' . $plan['destination'] : '')
        );

        // Close episode and fire HL7 A03 via EpisodeRepository
        $this->episodeRepo->closeWithDisposition($episodeId, $code, $departSql);

        $label = self::CODES[$code]['label'] ?? $code;
        $flash = $code === 'DECEASED'
            ? xlt('Patient death recorded. Episode closed.')
            : sprintf(xlt('Closure confirmed: %s. Episode closed.'), $label);

        return [$flash, ''];
    }
}




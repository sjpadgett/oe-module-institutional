<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlDischarge\Repository\AlDischargeRepository;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;

/**
 * AlDischargeController
 *
 * Manages the two-stage AL discharge/transfer lifecycle:
 *
 *   Stage 1 — Plan   : record code + destination + decision date + notes.
 *                       Episode stays ACTIVE. Board shows pending departure badge.
 *   Stage 2 — Confirm: record actual depart_datetime.
 *                       Episode transitions to CLOSED via EpisodeRepository::closeWithDisposition()
 *                       which fires the HL7 A03 Discharge event automatically.
 *
 * AL disposition codes (superset of ED codes, AL-specific meanings):
 *   HOME_DISCHARGE   — planned return home, with or without home-care services
 *   SNF_TRANSFER     — transfer to Skilled Nursing Facility (higher care level)
 *   HOSPITAL_TRANSFER— non-emergency scheduled hospital admission / procedure
 *   HOSPITAL_EVAL    — urgent/emergent transfer for evaluation (pending decision)
 *   AMA_DEPARTURE    — Against Medical/Staff Advice voluntary departure
 *   FAMILY_REMOVAL   — Family removed resident (may be AMA)
 *   DECEASED         — Death in facility; closes episode with death documentation
 */
final class AlDischargeController
{
    /** AL-specific disposition codes with display labels and icons. */
    public const CODES = [
        'HOME_DISCHARGE'    => ['label' => 'Discharge to Home',              'icon' => '🏠', 'closes' => true,  'urgent' => false],
        'SNF_TRANSFER'      => ['label' => 'Transfer to SNF',                'icon' => '🏥', 'closes' => true,  'urgent' => false],
        'HOSPITAL_TRANSFER' => ['label' => 'Hospital Transfer (scheduled)',   'icon' => '🚑', 'closes' => true,  'urgent' => false],
        'HOSPITAL_EVAL'     => ['label' => 'Emergency Hospital Evaluation',  'icon' => '🚨', 'closes' => false, 'urgent' => true],
        'AMA_DEPARTURE'     => ['label' => 'Departure Against Advice',       'icon' => '⚠️', 'closes' => true,  'urgent' => false],
        'FAMILY_REMOVAL'    => ['label' => 'Family Removal',                 'icon' => '👨‍👩‍👧', 'closes' => true,  'urgent' => false],
        'DECEASED'          => ['label' => 'Deceased — Death in Facility',   'icon' => '✝',  'closes' => true,  'urgent' => false],
    ];

    /** Codes that do NOT immediately close the episode (return is possible). */
    private const PENDING_CODES = ['HOSPITAL_EVAL'];

    public function __construct(
        private readonly AlDischargeRepository $repo,
        private readonly EpisodeRepository     $episodeRepo,
        private readonly EpisodeEventRepository $events
    ) {}

    /**
     * @param int      $episodeId
     * @param int      $pid
     * @param int      $facilityId
     * @param int|null $userId
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $flash   = '';
        $error   = '';

        $header  = $this->repo->getResidentHeader($episodeId);
        $plan    = $this->repo->getPlan($episodeId);
        $closed  = $header && ($header['status'] ?? '') === 'CLOSED';

        // ── POST handling ─────────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$closed) {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = trim((string)($_POST['action'] ?? ''));

            if ($action === 'save_plan') {
                [$flash, $error] = $this->handleSavePlan(
                    $episodeId, $pid, $facilityId, $userId
                );
                $plan = $this->repo->getPlan($episodeId);
            }

            if ($action === 'confirm_departure') {
                [$flash, $error] = $this->handleConfirmDeparture(
                    $episodeId, $pid, $facilityId, $userId, $plan
                );
                $plan   = $this->repo->getPlan($episodeId);
                $header = $this->repo->getResidentHeader($episodeId);
                $closed = $header && ($header['status'] ?? '') === 'CLOSED';
            }
        }

        return [
            'csrf'        => $csrf,
            'header'      => $header,
            'plan'        => $plan,
            'closed'      => $closed,
            'flash'        => $flash,
            'error'       => $error,
            'codes'       => self::CODES,
            'pending_codes' => self::PENDING_CODES,
        ];
    }

    // ── Stage 1: Save plan ────────────────────────────────────────────────────

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
            return ['', xlt('Death documentation requires notes (circumstances, time, attending).')];
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

    // ── Stage 2: Confirm departure ────────────────────────────────────────────

    /** @return array{0:string,1:string} [flash, error] */
    private function handleConfirmDeparture(
        int $episodeId, int $pid, int $facilityId, ?int $userId, ?array $plan
    ): array {
        $depart = trim((string)($_POST['depart_datetime'] ?? ''));

        if (empty($depart)) {
            return ['', xlt('Departure date/time is required to confirm.')];
        }

        $departSql = str_replace('T', ' ', $depart) . ':00';

        $code = (string)($plan['disposition_code'] ?? '');
        if (empty($code)) {
            return ['', xlt('Save a discharge plan before confirming departure.')];
        }

        // Stamp depart_datetime on the plan row
        $this->repo->confirmDeparture($episodeId, $departSql, $userId);

        // Log the departure event
        $this->events->addEvent(
            $episodeId, $pid, null, $facilityId,
            'DEPART',
            $departSql,
            $userId,
            $code . ($plan['destination'] ? ' → ' . $plan['destination'] : '')
        );

        // Close the episode — fires HL7 A03 Discharge via EpisodeRepository
        // HOSPITAL_EVAL is a pending transfer; only close if confirmed as departure
        $this->episodeRepo->closeWithDisposition($episodeId, $code, $departSql);

        $label = self::CODES[$code]['label'] ?? $code;

        $flash = $code === 'DECEASED'
            ? xlt('Resident death recorded. Episode closed.')
            : sprintf(xlt('Departure confirmed: %s. Episode closed.'), $label);

        return [$flash, ''];
    }
}

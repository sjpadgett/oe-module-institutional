<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\Submodule\EReferral\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Submodule\EReferral\Repository\EReferralRepository;
use OpenEMR\Modules\Institutional\Submodule\EReferral\Service\EReferralService;
use OpenEMR\Modules\Institutional\Submodule\FacilityDirectory\Repository\FacilityDirectoryRepository;

/**
 * E-Referral Controller.
 *
 * Actions:
 *   GET  ereferral.php?facility_id=N&episode_id=N        — edit/view referral
 *   GET  ereferral.php?facility_id=N&episode_id=N&action=print — printable referral sheet
 *   POST action=save     — save draft edits
 *   POST action=send     — mark as SENT
 *   POST action=respond  — record ACCEPTED / DECLINED
 */
final class EReferralController
{
    public function __construct(
        private readonly EReferralRepository $repo,
        private readonly EReferralService $service,
        private readonly EpisodeRepository $episodeRepo,
        private readonly DispositionRepository $dispositionRepo,
        private readonly FacilityDirectoryRepository $directoryRepo
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $facilityId, int $episodeId, ?int $userId): array
    {
        $message = '';
        $error   = '';

        if (!empty($_GET['msg'])) {
            $message = (string)$_GET['msg'];
        }
        if (!empty($_GET['err'])) {
            $error = (string)$_GET['err'];
        }

        $episode = $this->episodeRepo->fetchOne($episodeId);
        if (!$episode) {
            return ['error' => 'Episode not found.', 'message' => ''];
        }

        $pid = (int)$episode['pid'];
        $eid = isset($episode['eid']) && is_numeric($episode['eid']) ? (int)$episode['eid'] : null;

        // ── POST routing ──────────────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = (string)($_POST['action'] ?? 'save');

            try {
                switch ($action) {
                    case 'save':
                        $this->service->applyEdit($episodeId, $pid, $eid, $facilityId, $_POST, $userId);
                        $message = xlt('Referral saved.');
                        break;

                    case 'send':
                        $this->service->applyEdit($episodeId, $pid, $eid, $facilityId, $_POST, $userId);
                        $this->repo->markSent($episodeId, (string)($_POST['send_method'] ?? 'MANUAL'), $userId);
                        $message = xlt('Referral marked as sent.');
                        break;

                    case 'respond':
                        $outcome = strtoupper(trim((string)($_POST['response_outcome'] ?? '')));
                        $byName  = trim((string)($_POST['response_by_name'] ?? '')) ?: null;
                        $notes   = trim((string)($_POST['response_notes'] ?? '')) ?: null;
                        $this->repo->recordResponse($episodeId, $outcome, $byName, $notes);
                        $message = xlt('Response recorded.');
                        break;
                }
            } catch (\Throwable $e) {
                $error = xlt('Error') . ': ' . htmlspecialchars($e->getMessage());
            }

            $qs = http_build_query([
                'facility_id' => $facilityId,
                'episode_id'  => $episodeId,
                'msg'         => $message,
                'err'         => $error,
            ]);
            header("Location: ereferral.php?{$qs}");
            exit;
        }

        // ── GET — auto-draft if needed ────────────────────────────────────────
        $referral    = $this->repo->getByEpisode($episodeId);
        $disposition = $this->dispositionRepo->getByEpisode($episodeId);

        if (!$referral && $disposition) {
            $triage = $this->fetchLatestTriage($episodeId);
            $this->service->draftFromDisposition($episode, $disposition, $triage, $userId);
            $referral = $this->repo->getByEpisode($episodeId);
        }

        $directory = $this->directoryRepo->listActive($facilityId);

        return [
            'episode'     => $episode,
            'episode_id'  => $episodeId,
            'facility_id' => $facilityId,
            'referral'    => $referral,
            'disposition' => $disposition,
            'directory'   => $directory,
            'csrf'        => CsrfUtils::collectCsrfToken(),
            'message'     => $message,
            'error'       => $error,
        ];
    }

    /**
     * Fetch latest triage vitals for an episode.
     * Uses confirmed oei_triage schema columns:
     *   bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, weight_kg, pain_score
     * Ordered by set_number DESC, id DESC (no recorded_datetime column).
     *
     * @return array<string,mixed>|null
     */
    private function fetchLatestTriage(int $episodeId): ?array
    {
        if (!function_exists('sqlQuery')) {
            return null;
        }
        $row = sqlQuery(
            "SELECT bp_systolic, bp_diastolic, hr, rr, temp_f, spo2, gcs, weight_kg, pain_score
             FROM oei_triage
             WHERE episode_id = ?
             ORDER BY set_number DESC, id DESC
             LIMIT 1",
            [$episodeId]
        );
        return $row ?: null;
    }
}



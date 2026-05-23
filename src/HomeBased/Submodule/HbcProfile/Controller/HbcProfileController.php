<?php

/**
 * src/HomeBased/Submodule/HbcProfile/Controller/HbcProfileController.php
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

namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcProfile\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcProfile\Repository\HbcProfileRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

final class HbcProfileController
{
    public function __construct(
        private readonly HbcProfileRepository $repo = new HbcProfileRepository(),
        private readonly EpisodeEventRepository $events = new EpisodeEventRepository(),
        private readonly SharedObservationRepository $obsRepo = new SharedObservationRepository()
    ) {
    }

    /** @return array<string,mixed> */
    public function handle(int $episodeId, int $facilityId = 0, ?int $userId = null): array
    {
        $flash = '';
        $error = '';
        $csrf  = CsrfUtils::collectCsrfToken();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = trim((string)($_POST['action'] ?? ''));
            if ($action === 'mark_triaged') {
                $headerForAction = $this->repo->fetchHeader($episodeId);
                if ($headerForAction) {
                    $ok = $this->repo->updateReferralStatus($episodeId, 'TRIAGED');
                    if ($ok) {
                        $this->events->addEvent(
                            $episodeId,
                            (int)$headerForAction['pid'],
                            null,
                            $facilityId > 0 ? $facilityId : (int)($headerForAction['facility_id'] ?? 0),
                            'REFERRAL_TRIAGED',
                            date('Y-m-d H:i:s'),
                            $userId,
                            (string)($headerForAction['referral_reason'] ?? '')
                        );
                        $flash = xlt('Referral marked triaged.');
                    } else {
                        $error = xlt('Unable to update referral status.');
                    }
                }
            }
        }

        $header = $this->repo->fetchHeader($episodeId);
        if (!$header) {
            return ['header' => null, 'error' => 'Episode not found or not a Home-Based Care episode.'];
        }

        $pid         = (int)$header['pid'];
        $encounterId = (int)($header['encounter_id'] ?? 0);

        return [
            'csrf'       => $csrf,
            'flash'      => $flash,
            'error'      => $error,
            'header'     => $header,
            'next_visit' => $this->repo->fetchNextVisit($episodeId),
            'visits'     => $this->repo->fetchRecentVisits($episodeId, 8),
            'vitals' => $this->repo->fetchLatestVitals($episodeId),
            'service_snapshot' => $this->repo->fetchServiceSnapshot($episodeId),
            'clinical_attention' => $this->repo->fetchClinicalAttention($episodeId),
            'care_plan' => $this->repo->fetchCarePlanSummary($pid, $encounterId),
            'tasks'        => $this->repo->fetchOpenTasks($episodeId),
            'observations' => $this->obsRepo->latestPerType($episodeId),
        ];
    }
}









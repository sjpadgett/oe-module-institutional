<?php
namespace OpenEMR\Modules\Institutional\Submodule\Disposition\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\DispositionRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;

final class DispositionController
{
    public function __construct(
        private readonly DispositionRepository $repo,
        private readonly EpisodeEventRepository $events
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, int $episodeId, int $pid, ?int $eid, ?int $userId): array
    {
        $csrf = CsrfUtils::collectCsrfToken();
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $code        = strtoupper(trim((string)($_POST['disposition_code'] ?? '')));
            $destination = trim((string)($_POST['destination'] ?? '')) ?: null;
            $decision    = trim((string)($_POST['decision_datetime'] ?? ''));
            $depart      = trim((string)($_POST['depart_datetime'] ?? ''));
            $admitFlag   = !empty($_POST['admit_flag']) ? 1 : 0;
            $notes       = trim((string)($_POST['notes'] ?? '')) ?: null;

            $decisionSql = $decision ? str_replace('T', ' ', $decision) . ':00' : null;
            $departSql   = $depart   ? str_replace('T', ' ', $depart)   . ':00' : null;

            if ($code !== '') {
                $this->repo->upsert($episodeId, $pid, $eid, $facilityId,
                    $code, $destination, $decisionSql, $departSql, $admitFlag, $notes, $userId);

                $now = date('Y-m-d H:i:s');
                if ($decisionSql) {
                    $this->events->addEvent($episodeId, $pid, $eid, $facilityId, 'DECISION', $decisionSql, $userId);
                }
                if ($departSql) {
                    $this->events->addEvent($episodeId, $pid, $eid, $facilityId, 'DEPART', $departSql, $userId);
                }
                $message = xlt('Disposition saved.');
            }
        }

        return [
            'disposition' => $this->repo->getByEpisode($episodeId) ?: [],
            'csrf'        => $csrf,
            'message'     => $message,
        ];
    }
}

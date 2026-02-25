<?php

namespace OpenEMR\Modules\Institutional\Submodule\Triage\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Core\Repository\EpisodeRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Repository\TriageRepository;
use OpenEMR\Modules\Institutional\Submodule\Triage\Service\TriageService;

final class TriageController
{
    public function __construct(
        private readonly TriageRepository $repo,
        private readonly TriageService    $service,
        private readonly EpisodeRepository $episodes
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $episodeId, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $message = '';
        $alerts  = [];

        // Episode list for sidebar selector
        $boardRows = $this->episodes->fetchBoard($facilityId);

        // Default to first active episode if none specified
        if ($episodeId === null && !empty($boardRows)) {
            $episodeId = (int)($boardRows[0]['id'] ?? 0);
        }

        $selected = null;
        foreach ($boardRows as $r) {
            if ((int)$r['id'] === $episodeId) {
                $selected = $r;
                break;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $episodeId = (int)($_POST['episode_id'] ?? $episodeId ?? 0);
            $pid       = (int)($_POST['pid'] ?? ($selected['pid'] ?? 0));
            $eidRaw    = trim((string)($_POST['eid'] ?? ($selected['eid'] ?? '')));
            $eid       = ctype_digit($eidRaw) && $eidRaw !== '' ? (int)$eidRaw : null;

            if ($episodeId > 0 && $pid > 0) {
                $result = $this->service->recordVitals(
                    $episodeId, $pid, $eid, $facilityId,
                    $this->intOrNull($_POST['bp_systolic']  ?? ''),
                    $this->intOrNull($_POST['bp_diastolic'] ?? ''),
                    $this->intOrNull($_POST['hr']           ?? ''),
                    $this->intOrNull($_POST['rr']           ?? ''),
                    $this->floatOrNull($_POST['temp_f']     ?? ''),
                    $this->intOrNull($_POST['spo2']         ?? ''),
                    $this->intOrNull($_POST['gcs']          ?? ''),
                    $this->intOrNull($_POST['pain_score']   ?? ''),
                    $this->floatOrNull($_POST['weight_kg']  ?? ''),
                    trim((string)($_POST['arrival_mode'] ?? '')) ?: null,
                    trim((string)($_POST['notes'] ?? '')) ?: null,
                    $userId
                );
                $alerts  = $result['alerts'];
                $esiSug  = $result['esi_suggested'];
                $message = $esiSug !== null
                    ? xlt('Vitals saved. Suggested ESI: ') . $esiSug
                    : xlt('Vitals saved.');
            }
        }

        $history = $episodeId ? $this->repo->listForEpisode($episodeId) : [];
        $latest  = $episodeId ? $this->repo->getLatestForEpisode($episodeId) : null;

        return [
            'boardRows'  => $boardRows,
            'selected'   => $selected,
            'episodeId'  => $episodeId,
            'history'    => $history,
            'latest'     => $latest,
            'csrf'       => $csrf,
            'message'    => $message,
            'alerts'     => $alerts,
        ];
    }

    private function intOrNull(string $v): ?int
    {
        $v = trim($v);
        return ($v !== '' && is_numeric($v)) ? (int)$v : null;
    }

    private function floatOrNull(string $v): ?float
    {
        $v = trim($v);
        return ($v !== '' && is_numeric($v)) ? (float)$v : null;
    }
}

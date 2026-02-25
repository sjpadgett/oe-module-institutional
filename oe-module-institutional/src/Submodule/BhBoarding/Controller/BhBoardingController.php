<?php
namespace OpenEMR\Modules\Institutional\Submodule\BhBoarding\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\BhBoarding\Repository\BhBoardingRepository;
use OpenEMR\Modules\Institutional\Submodule\Disposition\Repository\EpisodeEventRepository;

final class BhBoardingController
{
    public function __construct(
        private readonly BhBoardingRepository   $repo,
        private readonly EpisodeEventRepository $events
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, int $episodeId, int $pid, ?int $eid, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $prev = $this->repo->getByEpisode($episodeId) ?: [];

            $placementStatus  = strtoupper(trim((string)($_POST['placement_status'] ?? 'SEARCHING')));
            $acceptingFacility= trim((string)($_POST['accepting_facility'] ?? '')) ?: null;
            $acceptedRaw      = trim((string)($_POST['accepted_datetime'] ?? ''));
            $transportMethod  = trim((string)($_POST['transport_method'] ?? '')) ?: null;
            $transportRaw     = trim((string)($_POST['transport_datetime'] ?? ''));
            $legalStatus      = trim((string)($_POST['legal_status'] ?? '')) ?: null;
            $suicideRisk      = strtoupper(trim((string)($_POST['suicide_risk'] ?? ''))) ?: null;
            $violenceRisk     = strtoupper(trim((string)($_POST['violence_risk'] ?? ''))) ?: null;
            $emtalaComplete   = !empty($_POST['emtala_complete']) ? 1 : 0;
            $notes            = trim((string)($_POST['notes'] ?? '')) ?: null;

            $acceptedSql  = $acceptedRaw  ? str_replace('T',' ',$acceptedRaw) . ':00' : null;
            $transportSql = $transportRaw ? str_replace('T',' ',$transportRaw) . ':00' : null;

            $checkKeys = ['mdm_complete','labs_printed','imaging_sent','meds_reconciled',
                          'consent_signed','nursing_report','transfer_form'];
            $check = [];
            foreach ($checkKeys as $k) {
                $check[$k] = !empty($_POST['chk_' . $k]) ? 1 : 0;
            }
            $checkJson = json_encode($check);

            $this->repo->upsert($episodeId,$pid,$eid,$facilityId,$placementStatus,$acceptingFacility,
                $acceptedSql,$transportMethod,$transportSql,$legalStatus,$suicideRisk,$violenceRisk,
                $emtalaComplete,$checkJson,$notes,$userId);

            $now = date('Y-m-d H:i:s');
            if ($acceptedSql && ($prev['accepted_datetime'] ?? null) !== $acceptedSql) {
                $this->events->addEvent($episodeId,$pid,$eid,$facilityId,'BH_ACCEPTED',$acceptedSql,$userId,$acceptingFacility);
            }
            if ($transportSql && ($prev['transport_datetime'] ?? null) !== $transportSql) {
                $this->events->addEvent($episodeId,$pid,$eid,$facilityId,'BH_TRANSPORT',$transportSql,$userId,$transportMethod);
            }
            if ($emtalaComplete && empty($prev['emtala_complete'])) {
                $this->events->addEvent($episodeId,$pid,$eid,$facilityId,'EMTALA_COMPLETE',$now,$userId);
            }

            $message = xlt('BH boarding updated.');
        }

        return [
            'bh'      => $this->repo->getByEpisode($episodeId) ?: [],
            'csrf'    => $csrf,
            'message' => $message,
        ];
    }
}

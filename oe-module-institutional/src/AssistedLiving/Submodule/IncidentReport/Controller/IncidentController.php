<?php
declare(strict_types=1);
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Service\IncidentService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\IncidentReport\Repository\IncidentRepository;

final class IncidentController
{
    private readonly IncidentService $service;

    public function __construct()
    {
        $this->service = new IncidentService(new IncidentRepository());
    }

    public function handle(int $facilityId, int $userId): array
    {
        $flash = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'create') {
                $episodeId = (int)($_POST['episode_id'] ?? 0);
                $data = [
                    'incident_type'        => trim((string)($_POST['incident_type'] ?? '')),
                    'severity'             => trim((string)($_POST['severity'] ?? 'MODERATE')),
                    'incident_datetime'    => trim((string)($_POST['incident_datetime'] ?? date('Y-m-d H:i:s'))),
                    'location_description' => trim((string)($_POST['location_description'] ?? '')),
                    'narrative'            => trim((string)($_POST['narrative'] ?? '')),
                    'corrective_action'    => trim((string)($_POST['corrective_action'] ?? '')),
                ];
                if ($episodeId > 0 && $data['incident_type'] !== '') {
                    $id = $this->service->create($episodeId, $facilityId, $userId, $data);
                    $flash = $id > 0 ? 'Incident report created.' : 'Save failed — please retry.';
                }
            } elseif ($action === 'mark_reported') {
                $incidentId = (int)($_POST['incident_id'] ?? 0);
                if ($incidentId > 0) {
                    $this->service->markReported($incidentId);
                    $flash = 'Incident marked as reported to state.';
                }
            }
        }

        return [
            'incidents'     => $this->service->listForFacility($facilityId),
            'incident_types' => $this->service->incidentTypes(),
            'flash'         => $flash,
        ];
    }
}

<?php

namespace OpenEMR\Modules\Institutional\Submodule\AdtLite\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\AdtLite\Repository\LocationRepository;

final class LocationsController
{
    public function __construct(private readonly LocationRepository $repo)
    {
    }

    /** @return array{rows:array, csrf:string} */
    public function handle(int $facilityId): array
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die("CSRF validation failed");
            }
            $action = (string)($_POST['action'] ?? '');

            $name = trim((string)($_POST['name'] ?? ''));
            $type = trim((string)($_POST['type'] ?? 'ED_ROOM'));
            $status = trim((string)($_POST['status'] ?? 'AVAILABLE'));
            $active = (int)($_POST['active'] ?? 1);

            if ($action === 'create' && $name !== '') {
                $this->repo->create($facilityId, $name, $type, $status);
            } elseif ($action === 'update') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0 && $name !== '') {
                    $this->repo->update($id, $facilityId, $name, $type, $status, $active ? 1 : 0);
                }
            }

            header("Location: locations.php?facility_id=" . urlencode((string)$facilityId));
            exit;
        }

        return ['rows' => $this->repo->listAll($facilityId), 'csrf' => CsrfUtils::collectCsrfToken()];
    }
}



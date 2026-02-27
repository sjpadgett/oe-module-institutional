<?php
namespace OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\ObsProtocols\Repository\ProtocolRepository;

final class ObsProtocolsController
{
    public function __construct(private readonly ProtocolRepository $repo) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
    {
        $csrf = CsrfUtils::collectCsrfToken();
        $message = '';

        $this->repo->ensureDefaultProtocols($facilityId, $userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action  = (string)($_POST['action'] ?? '');
            if ($action === 'save') {
                $key     = strtoupper(trim((string)($_POST['protocol_key'] ?? '')));
                $label   = trim((string)($_POST['label'] ?? ''));
                $version = trim((string)($_POST['version'] ?? '1')) ?: '1';
                $enabled = !empty($_POST['enabled']) ? 1 : 0;
                $defJson = trim((string)($_POST['definition_json'] ?? ''));
                if ($key !== '' && $label !== '') {
                    $decoded = json_decode($defJson, true);
                    if (is_array($decoded)) {
                        $this->repo->upsert($facilityId, $key, $label, $version, $enabled,
                            json_encode($decoded, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), $userId);
                        $message = xlt('Protocol saved.');
                    } else {
                        $message = xlt('Invalid JSON.');
                    }
                }
            }
        }

        return [
            'rows'    => $this->repo->listEnabled($facilityId),
            'csrf'    => $csrf,
            'message' => $message,
        ];
    }
}



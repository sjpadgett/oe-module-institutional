<?php

/**
 * src/ObservationStay/Submodule/ObsProtocols/Controller/ObsEpisodeController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Repository\ProtocolRepository;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Repository\ObsPlanRepository;
use OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Service\ObsProtocolEngine;

final class ObsEpisodeController
{
    public function __construct(
        private readonly ProtocolRepository  $protos,
        private readonly ObsPlanRepository   $plans,
        private readonly ObsProtocolEngine   $engine
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, int $episodeId, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action      = (string)($_POST['action'] ?? '');
            $pid         = (int)($_POST['pid'] ?? 0);
            $eidRaw      = trim((string)($_POST['eid'] ?? ''));
            $eid         = ctype_digit($eidRaw) ? (int)$eidRaw : null;
            $protocolKey = strtoupper(trim((string)($_POST['protocol_key'] ?? 'GENERAL_OBS')));

            if ($action === 'apply' && $pid > 0) {
                $this->engine->apply($episodeId, $pid, $eid, $facilityId, $protocolKey, $userId);
                $message = xlt('Protocol applied.');
            }
        }

        $this->protos->ensureDefaultProtocols($facilityId, $userId);

        return [
            'plan'         => $this->plans->getByEpisode($episodeId),
            'protocolRows' => $this->protos->listEnabled($facilityId),
            'csrf'         => $csrf,
            'message'      => $message,
        ];
    }
}




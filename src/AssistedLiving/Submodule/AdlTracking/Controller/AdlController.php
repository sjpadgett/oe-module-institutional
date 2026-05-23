<?php

/**
 * src/AssistedLiving/Submodule/AdlTracking/Controller/AdlController.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Service\AdlService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Repository\AdlRepository;

final class AdlController
{
    private readonly AdlService $service;

    public function __construct()
    {
        $this->service = new AdlService(new AdlRepository());
    }

    public function handle(int $episodeId, int $facilityId, int $userId): array
    {
        $flash = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $domainLevels = [];
            foreach ($this->service->domains() as $domain => $_) {
                $domainLevels[$domain] = (int)($_POST['adl_' . $domain] ?? 8);
            }
            $notes = trim((string)($_POST['notes'] ?? ''));
            $id = $this->service->chart($episodeId, $facilityId, $userId, $domainLevels, $notes);
            $flash = $id > 0 ? 'ADL chart saved.' : 'Save failed — please retry.';
        }

        return [
            'records'   => $this->service->history($episodeId),
            'domains'   => $this->service->domains(),
            'levels'    => $this->service->levels(),
            'levelLabel'=> fn(int $l) => $this->service->levelLabel($l),
            'flash'     => $flash,
            'episodeId' => $episodeId,
        ];
    }
}




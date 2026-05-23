<?php

/**
 * src/Shared/Submodule/Intake/Controller/IntakeController.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Repository\PatientRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Intake\Service\IntakeService;

final class IntakeController
{
    public function __construct(
        private readonly PatientRepository $patients,
        private readonly IntakeService     $intake
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
    {
        $csrf    = CsrfUtils::collectCsrfToken();
        $q       = trim((string)($_GET['q'] ?? ''));
        $message = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $pid          = (int)($_POST['pid'] ?? 0);
            $arrivalMode  = strtoupper(trim((string)($_POST['arrival_mode'] ?? 'WALKIN')));
            $esiRaw       = trim((string)($_POST['acuity_esi'] ?? ''));
            $esi          = is_numeric($esiRaw) ? (int)$esiRaw : null;
            $chief        = trim((string)($_POST['chief_complaint'] ?? '')) ?: null;
            $triageNote   = trim((string)($_POST['triage_note'] ?? '')) ?: null;

            if ($pid > 0) {
                $episodeId = $this->intake->createEpisode($pid, $facilityId, $arrivalMode, $esi, $chief, $triageNote, $userId);
                header("Location: ed_board.php?facility_id=" . urlencode((string)$facilityId) . "&new_episode=" . (int)$episodeId);
                exit;
            }
            $message = xlt('Please select a patient.');
        }

        return [
            'q'       => $q,
            'results' => $q !== '' ? $this->patients->search($q) : [],
            'csrf'    => $csrf,
            'message' => $message,
        ];
    }
}




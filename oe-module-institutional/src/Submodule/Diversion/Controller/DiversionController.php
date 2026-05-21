<?php

/**
 * src/Submodule/Diversion/Controller/DiversionController.php
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

namespace OpenEMR\Modules\Institutional\Submodule\Diversion\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Submodule\Diversion\Repository\DiversionRepository;
use OpenEMR\Modules\Institutional\Submodule\Diversion\Service\DiversionService;

/**
 * DiversionController
 *
 * GET  diversion.php?facility_id=N   — status dashboard
 * POST action=set                    — change status for a service line
 * POST action=lift                   — lift diversion for a service line
 */
final class DiversionController
{
    public function __construct(
        private readonly DiversionService    $service,
        private readonly DiversionRepository $repo
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $facilityId, ?int $userId): array
    {
        $message = (string)($_GET['msg'] ?? '');
        $error   = (string)($_GET['err'] ?? '');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action  = (string)($_POST['action']       ?? 'set');
            $pLine   = strtoupper(trim((string)($_POST['service_line'] ?? 'ED')));
            $pStatus = strtoupper(trim((string)($_POST['status']       ?? 'OPEN')));
            $pReason = trim((string)($_POST['reason'] ?? '')) ?: null;

            try {
                if ($action === 'lift') {
                    $this->service->liftDiversion($facilityId, $pLine, $userId);
                    $message = xlt('Diversion lifted.');
                } else {
                    $this->service->setStatus($facilityId, $pLine, $pStatus, $pReason, $userId);
                    $message = xlt('Diversion status updated.');
                }
            } catch (\InvalidArgumentException $e) {
                $error = htmlspecialchars($e->getMessage());
            } catch (\Throwable $e) {
                $error = xlt('Error') . ': ' . htmlspecialchars($e->getMessage());
            }

            $qs = http_build_query([
                'facility_id' => $facilityId,
                'msg'         => $message,
                'err'         => $error,
            ]);
            header("Location: diversion.php?{$qs}");
            exit;
        }

        return [
            'facility_id' => $facilityId,
            'status_map'  => $this->service->getStatusMap($facilityId),
            'worst'       => $this->service->worstStatus($facilityId),
            'history'     => $this->repo->historyAllLines($facilityId, 60),
            'csrf'        => CsrfUtils::collectCsrfToken(),
            'message'     => $message,
            'error'       => $error,
        ];
    }
}




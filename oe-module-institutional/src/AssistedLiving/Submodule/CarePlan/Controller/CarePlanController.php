<?php

/**
 * src/AssistedLiving/Submodule/CarePlan/Controller/CarePlanController.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Service\CarePlanService;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\CarePlan\Repository\CarePlanRepository;

final class CarePlanController
{
    private readonly CarePlanService $service;

    public function __construct()
    {
        $this->service = new CarePlanService(new CarePlanRepository());
    }

    public function handle(int $episodeId, int $pid, int $userId): array
    {
        // Generate CSRF token string using the module-standard pattern:
        // controller returns the raw token; view manually wraps it in
        // <input type="hidden" name="csrf_token_form" value="...">
        $csrf  = CsrfUtils::collectCsrfToken();
        $flash = '';
        $flashType = 'success';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                // Soft-fail: show error flash instead of hard die so the page
                // stays usable and the resident context is preserved.
                $flash     = xlt('Security token invalid — please reload the page and try again.');
                $flashType = 'danger';
            } else {
                $action      = (string)($_POST['action'] ?? '');
                $description = trim((string)($_POST['description'] ?? ''));
                $proposed    = trim((string)($_POST['proposed_date'] ?? ''));

                if ($action === 'add_goal' && $description !== '') {
                    $ok    = $this->service->addGoal($episodeId, $description, $proposed, $userId);
                    $flash = $ok ? xlt('Goal added.') : xlt('Error saving goal — please try again.');
                    $flashType = $ok ? 'success' : 'danger';
                } elseif ($action === 'add_activity' && $description !== '') {
                    $ok    = $this->service->addActivity($episodeId, $description, $proposed, $userId);
                    $flash = $ok ? xlt('Intervention added.') : xlt('Error saving intervention — please try again.');
                    $flashType = $ok ? 'success' : 'danger';
                } elseif ($action === 'update_status') {
                    $entryId = (int)($_POST['entry_id'] ?? 0);
                    $status  = trim((string)($_POST['status'] ?? ''));
                    if ($entryId > 0 && $status !== '') {
                        $this->service->updateStatus($entryId, $status);
                        $flash     = xlt('Status updated.');
                        $flashType = 'success';
                    }
                }
            }
        }

        $data              = $this->service->pageData($episodeId, $pid);
        $data['flash']     = $flash;
        $data['flashType'] = $flashType;
        $data['episodeId'] = $episodeId;
        $data['pid']       = $pid;
        $data['csrf']      = $csrf;   // raw token string — view wraps in hidden input

        // Resolve the AL episode's admission encounter number so the view can badge
        // entries created via native OpenEMR (different encounter) with 'OE'.
        $data['episodeEncounter'] = 0;
        if (function_exists('sqlQuery')) {
            $ale = sqlQuery(
                "SELECT encounter_id FROM oei_al_episode WHERE episode_id = ? LIMIT 1",
                [$episodeId]
            );
            $data['episodeEncounter'] = (int)($ale['encounter_id'] ?? 0);
        }

        return $data;
    }
}






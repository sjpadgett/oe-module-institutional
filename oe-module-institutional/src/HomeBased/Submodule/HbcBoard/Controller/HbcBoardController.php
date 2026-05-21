<?php

/**
 * src/HomeBased/Submodule/HbcBoard/Controller/HbcBoardController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcBoard\Controller;

use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcBoard\Repository\HbcBoardRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Observations\Repository\SharedObservationRepository;

/**
 * HbcBoardController
 *
 * GET  board.php?facility_id=N[&date=Y-m-d]  — render board
 * POST board.php action=advance_visit         — status tap
 * POST board.php action=record_gps            — GPS at arrival
 */
final class HbcBoardController
{
    public function __construct(
        private readonly HbcBoardRepository $repo = new HbcBoardRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $facilityId): array
    {
        $date = trim((string)($_GET['date'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }

        // ── JSON endpoints (POST) ──────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string)($_POST['action'] ?? '');

            if ($action === 'advance_visit') {
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: application/json; charset=utf-8');
                $visitId = (int)($_POST['visit_id'] ?? 0);
                $userId  = isset($_SESSION['authUserID']) ? (int)$_SESSION['authUserID'] : 0;
                if ($visitId <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'missing visit_id']);
                    exit;
                }
                $newStatus = $this->repo->advanceVisitStatus($visitId, $userId);
                echo json_encode(['ok' => $newStatus !== null, 'status' => $newStatus]);
                exit;
            }

            if ($action === 'record_gps') {
                while (ob_get_level() > 0) { ob_end_clean(); }
                header('Content-Type: application/json; charset=utf-8');
                $visitId = (int)($_POST['visit_id'] ?? 0);
                $lat     = (float)($_POST['lat'] ?? 0);
                $lng     = (float)($_POST['lng'] ?? 0);
                if ($visitId > 0 && $lat !== 0.0 && $lng !== 0.0) {
                    $this->repo->recordGps($visitId, $lat, $lng);
                    echo json_encode(['ok' => true]);
                } else {
                    echo json_encode(['ok' => false]);
                }
                exit;
            }
        }

        $visits = $this->repo->fetchDayVisits($facilityId, $date);

        // Batch obs counts — one query
        $obsRepo   = new SharedObservationRepository();
        $eIds      = array_values(array_unique(array_filter(
                         array_map(fn($v) => (int)($v['episode_id'] ?? 0), $visits)
                     )));
        $obsCounts = $obsRepo->countFlaggedByEpisodes($eIds);
        foreach ($visits as &$v) {
            $v['obs_flagged_count'] = (int)($obsCounts[(int)($v['episode_id'] ?? 0)] ?? 0);
        }
        unset($v);

        return [
            'date'         => $date,
            'metrics'      => $this->repo->fetchMetrics($facilityId, $date),
            'action_queue' => $this->repo->fetchActionQueue($facilityId, 8),
            'referrals'    => $this->repo->fetchReferralQueue($facilityId),
            'visits'       => $visits,
        ];
    }
}




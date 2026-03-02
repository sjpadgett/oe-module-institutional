<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlMar\Repository\AlMarRepository;

/**
 * AlMarController
 *
 * Renders the AL MAR as a 5-day rolling window centred on today.
 * The grid shows each standing order as a row, with time slots across columns.
 * PRN medications appear in a separate section below scheduled orders.
 *
 * POST handles administration recording (GIVEN / HELD / REFUSED / OMITTED).
 * Redirects back with flash after POST to prevent double-submit.
 */
final class AlMarController
{
    public function __construct(
        private readonly AlMarRepository $repo = new AlMarRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $pid, int $facilityId, ?int $userId): array
    {
        $flash = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $flash = xlt('Security token invalid.');
            } else {
                $flash = $this->handlePost($userId);
            }

            // PRG pattern — redirect after POST
            $url = $_SERVER['REQUEST_URI'] ?? '';
            $sep = str_contains($url, '?') ? '&' : '?';
            header('Location: ' . $url . $sep . 'flash=' . urlencode($flash));
            exit;
        }

        // Flash from redirect
        if (!empty($_GET['flash'])) {
            $flash = htmlspecialchars((string)$_GET['flash']);
        }

        // 5-day window: yesterday through tomorrow + today
        $windowDays = 5;
        $offset     = (int)($_GET['offset'] ?? 0); // navigation offset in days
        $dateFrom   = date('Y-m-d', strtotime("today {$offset} days"));
        $dateTo     = date('Y-m-d', strtotime("today +4 days {$offset} days"));

        // Build date column headers
        $dates = [];
        for ($i = 0; $i < $windowDays; $i++) {
            $d = date('Y-m-d', strtotime($dateFrom . " +$i days"));
            $dates[] = [
                'date'     => $d,
                'label'    => date('D n/j', strtotime($d)),
                'is_today' => ($d === date('Y-m-d')),
            ];
        }

        // Load all admins in window
        $admins = $this->repo->listAdminsByWindow($episodeId, $dateFrom, $dateTo);

        // Separate scheduled vs PRN orders
        $scheduledOrders = $this->repo->listActiveOrders($episodeId);
        $scheduled = array_filter($scheduledOrders, fn($o) => !(bool)$o['is_prn']);
        $prn       = array_filter($scheduledOrders, fn($o) => (bool)$o['is_prn']);

        // Index admins by mar_order_id → date → [slots]
        $grid = [];
        foreach ($admins as $a) {
            $orderId = $a['mar_order_id'];
            $date    = substr($a['scheduled_datetime'], 0, 10);
            $grid[$orderId][$date][] = $a;
        }

        // Patient context
        $patient = null;
        if (function_exists('sqlQuery')) {
            $row = sqlQuery(
                "SELECT e.id, e.pid, pd.fname, pd.lname,
                        COALESCE(ale.room,'') AS room,
                        COALESCE(ale.unit,'') AS unit
                 FROM   oei_episode e
                 INNER  JOIN patient_data pd ON pd.pid=e.pid
                 LEFT   JOIN oei_al_episode ale ON ale.episode_id=e.id
                 WHERE  e.id=? LIMIT 1",
                [$episodeId]
            );
            $patient = $row ?: null;
        }

        return [
            'flash'      => $flash,
            'patient'    => $patient,
            'dates'      => $dates,
            'date_from'  => $dateFrom,
            'date_to'    => $dateTo,
            'offset'     => $offset,
            'scheduled'  => array_values($scheduled),
            'prn'        => array_values($prn),
            'grid'       => $grid,
            'all_admins' => $admins,
            'hold_reasons'=> $this->repo->holdReasons(),
        ];
    }

    private function handlePost(?int $userId): string
    {
        $p    = $_POST;
        $action = (string)($p['action'] ?? '');

        if ($action === 'administer' && !empty($p['administration_id'])) {
            $admId    = (int)$p['administration_id'];
            $outcome  = in_array($p['outcome'] ?? '', ['GIVEN','HELD','REFUSED','OMITTED'], true)
                        ? (string)$p['outcome'] : 'GIVEN';
            $dose     = trim((string)($p['dose_given']  ?? ''));
            $unit     = trim((string)($p['unit_given']  ?? ''));
            $route    = trim((string)($p['route_given'] ?? ''));
            $site     = trim((string)($p['site']        ?? ''));
            $holdReason = trim((string)($p['hold_reason'] ?? ''));
            $note     = trim((string)($p['note']        ?? ''));

            $ok = $this->repo->administer(
                $admId, $outcome,
                $dose ?: null, $unit ?: null, $route ?: null,
                $site ?: null, $holdReason ?: null, $note ?: null,
                $userId
            );

            return $ok
                ? xlt('Administration recorded:') . ' ' . xlt($outcome)
                : xlt('Error recording administration.');
        }

        return xlt('Unknown action.');
    }
}

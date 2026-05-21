<?php

/**
 * src/AssistedLiving/Submodule/FallRisk/Controller/FallRiskController.php
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

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\FallRisk\Repository\FallRiskRepository;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\FallRiskLevel;

/**
 * FallRiskController
 *
 * Handles the Morse Fall Scale reassessment form.
 * POST validates, scores, and saves via FallRiskRepository.
 * GET returns history + patient context for the page.
 */
final class FallRiskController
{
    /** Morse Fall Scale scoring options per item */
    public const MFS_ITEMS = [
        'fall_history' => [
            'label' => 'History of falling within last 3 months',
            'options' => [0 => 'No (0)', 25 => 'Yes (25)'],
        ],
        'secondary_dx' => [
            'label' => 'Secondary diagnosis (≥2 medical diagnoses on chart)',
            'options' => [0 => 'No (0)', 15 => 'Yes (15)'],
        ],
        'ambulatory_aid' => [
            'label' => 'Ambulatory aid',
            'options' => [
                0  => 'None / Bed rest / Nurse assist (0)',
                15 => 'Crutches / Cane / Walker (15)',
                30 => 'Furniture (30)',
            ],
        ],
        'iv_heparin_lock' => [
            'label' => 'IV or heparin lock',
            'options' => [0 => 'No (0)', 20 => 'Yes (20)'],
        ],
        'gait' => [
            'label' => 'Gait / Transferring',
            'options' => [
                0  => 'Normal / Bedrest / Immobile (0)',
                10 => 'Weak (10)',
                20 => 'Impaired (20)',
            ],
        ],
        'mental_status' => [
            'label' => 'Mental status',
            'options' => [
                0  => 'Oriented to own ability (0)',
                15 => 'Forgets limitations (15)',
            ],
        ],
    ];

    public function __construct(
        private readonly FallRiskRepository $repo = new FallRiskRepository()
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function handle(int $episodeId, int $facilityId, ?int $userId): array
    {
        $flash = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token'] ?? '')) {
                $flash = xlt('Security token invalid.');
            } else {
                $flash = $this->handlePost($episodeId, $facilityId, $userId);
            }
        }

        // Patient context
        $patient = null;
        if (function_exists('sqlQuery')) {
            $row = sqlQuery(
                "SELECT e.id, e.pid, pd.fname, pd.lname,
                        COALESCE(ale.room,'') AS room,
                        COALESCE(ale.unit,'') AS unit,
                        COALESCE(ale.fall_risk_level,'LOW') AS current_risk_level,
                        COALESCE(ale.fall_risk_score,0)     AS current_risk_score
                 FROM   oei_episode e
                 INNER  JOIN patient_data pd ON pd.pid=e.pid
                 LEFT   JOIN oei_al_episode ale ON ale.episode_id=e.id
                 WHERE  e.id=? LIMIT 1",
                [$episodeId]
            );
            $patient = $row ?: null;
        }

        $history       = $this->repo->listByEpisode($episodeId, 10);
        $latestAssmt   = $history[0] ?? null;
        $daysSince     = $this->repo->daysSinceLastAssessment($episodeId);
        $reassessAlert = ($daysSince === null || $daysSince >= 28);

        // Pre-fill form with last assessment values for quick re-score
        $prefill = $latestAssmt ?? array_fill_keys(
            ['mfs_fall_history','mfs_secondary_dx','mfs_ambulatory_aid',
             'mfs_iv_heparin_lock','mfs_gait','mfs_mental_status'],
            0
        );

        return [
            'flash'          => $flash,
            'patient'        => $patient,
            'history'        => $history,
            'latest'         => $latestAssmt,
            'days_since'     => $daysSince,
            'reassess_alert' => $reassessAlert,
            'prefill'        => $prefill,
            'mfs_items'      => self::MFS_ITEMS,
            'risk_levels'    => FallRiskLevel::all(),
        ];
    }

    private function handlePost(int $episodeId, int $facilityId, ?int $userId): string
    {
        $p = $_POST;

        // Validate each MFS item is a legal value
        $itemMap = [
            'fall_history'    => [0, 25],
            'secondary_dx'    => [0, 15],
            'ambulatory_aid'  => [0, 15, 30],
            'iv_heparin_lock' => [0, 20],
            'gait'            => [0, 10, 20],
            'mental_status'   => [0, 15],
        ];

        $values = [];
        foreach ($itemMap as $key => $valid) {
            $val = isset($p[$key]) ? (int)$p[$key] : 0;
            if (!in_array($val, $valid, true)) {
                return xlt('Invalid value for') . ' ' . $key . '.';
            }
            $values[$key] = $val;
        }

        $notes = trim((string)($p['notes'] ?? ''));

        $id = $this->repo->record(
            $episodeId, $facilityId, $userId,
            $values['fall_history'],
            $values['secondary_dx'],
            $values['ambulatory_aid'],
            $values['iv_heparin_lock'],
            $values['gait'],
            $values['mental_status'],
            $notes ?: null
        );

        if ($id === 0) {
            return xlt('Error saving assessment. Please try again.');
        }

        $total = array_sum($values);
        $level = FallRiskLevel::fromMorseScore($total);

        return xlt('Assessment saved.') . ' '
            . xlt('Morse score') . ': ' . $total . ' — '
            . FallRiskLevel::label($level) . '.';
    }
}




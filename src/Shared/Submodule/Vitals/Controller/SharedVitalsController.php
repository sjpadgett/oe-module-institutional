<?php

/**
 * src/Shared/Submodule/Vitals/Controller/SharedVitalsController.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Domain\VitalsThresholdConfig;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Repository\SharedVitalsRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Vitals\Service\VitalsAlertService;

/**
 * SharedVitalsController
 *
 * Single implementation of vitals recording logic for all tracks.
 *
 * Each track controller:
 *   1. Builds a VitalsThresholdConfig from its oei_settings keys
 *   2. Calls handle(), receiving the data array
 *   3. Replaces $data['patient'] with its own episode-type query
 *      (oei_al_episode, oei_ip_episode, oei_hbc_episode)
 *
 * This controller never reads oei_settings or queries episode-type
 * tables directly — all track-specific concerns are injected.
 *
 * CSRF field: accepts both 'csrf_token_form' and 'csrf_token' to cover
 * the existing page form field names without requiring page changes.
 *
 * Returns array:
 *   flash        string
 *   alerts       string[]
 *   patient      null  (caller fills this in)
 *   history      array[]
 *   weight_trend float[]
 *   latest       array|null
 */
final class SharedVitalsController
{
    private SharedVitalsRepository $repo;
    private VitalsAlertService     $alertSvc;

    public function __construct(
        ?SharedVitalsRepository $repo     = null,
        ?VitalsAlertService     $alertSvc = null
    ) {
        $this->repo     = $repo     ?? new SharedVitalsRepository();
        $this->alertSvc = $alertSvc ?? new VitalsAlertService();
    }

    /**
     * Handle GET or POST for a vitals page.
     *
     * @return array{
     *   flash:        string,
     *   alerts:       string[],
     *   patient:      null,
     *   history:      array[],
     *   weight_trend: float[],
     *   latest:       array|null,
     * }
     */
    public function handle(
        int                  $episodeId,
        int                  $pid,
        int                  $facilityId,
        ?int                 $userId,
        VitalsThresholdConfig $cfg
    ): array {
        $flash  = '';
        $alerts = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Accept both CSRF field names used across the existing pages
            $csrfToken = $_POST['csrf_token_form'] ?? $_POST['csrf_token'] ?? '';
            if (!CsrfUtils::verifyCsrfToken($csrfToken)) {
                $flash = xlt('Security token invalid. Please try again.');
            } else {
                ['flash' => $flash, 'alerts' => $alerts] =
                    $this->handlePost($episodeId, $pid, $facilityId, $userId, $cfg);
            }
        }

        return [
            'flash'        => $flash,
            'alerts'       => $alerts,
            'patient'      => null,            // caller must supply track-specific context
            'history'      => $this->repo->listForEpisode($episodeId, 20),
            'weight_trend' => $this->repo->weightTrend($episodeId, 14),
            'latest'       => $this->repo->getLatest($episodeId),
        ];
    }

    // ── Private ───────────────────────────────────────────────────────────

    /**
     * @return array{flash: string, alerts: string[]}
     */
    private function handlePost(
        int                  $episodeId,
        int                  $pid,
        int                  $facilityId,
        ?int                 $userId,
        VitalsThresholdConfig $cfg
    ): array {
        $p = $_POST;

        $bpSystolic  = $p['bp_systolic']  !== '' && isset($p['bp_systolic'])  ? (int)$p['bp_systolic']   : null;
        $bpDiastolic = $p['bp_diastolic'] !== '' && isset($p['bp_diastolic']) ? (int)$p['bp_diastolic']  : null;
        $hr          = $p['hr']           !== '' && isset($p['hr'])           ? (int)$p['hr']            : null;
        $rr          = $p['rr']           !== '' && isset($p['rr'])           ? (int)$p['rr']            : null;
        $tempF       = $p['temp_f']       !== '' && isset($p['temp_f'])       ? (float)$p['temp_f']      : null;
        $spo2        = $p['spo2']         !== '' && isset($p['spo2'])         ? (int)$p['spo2']          : null;
        $weightKg    = $p['weight_kg']    !== '' && isset($p['weight_kg'])    ? (float)$p['weight_kg']   : null;
        $painScore   = $p['pain_score']   !== '' && isset($p['pain_score'])   ? (int)$p['pain_score']    : null;
        $gcs         = ($cfg->showGcs && isset($p['gcs']) && $p['gcs'] !== '') ? (int)$p['gcs'] : null;
        $notes       = trim((string)($p['notes'] ?? ''));

        // Require at least one meaningful vital
        if ($bpSystolic === null && $hr === null && $spo2 === null && $weightKg === null) {
            return [
                'flash'  => xlt('Please enter at least one vital sign.'),
                'alerts' => [],
            ];
        }

        $id = $this->repo->record(
            episodeId:   $episodeId,
            pid:         $pid,
            facilityId:  $facilityId,
            bpSystolic:  $bpSystolic,
            bpDiastolic: $bpDiastolic,
            hr:          $hr,
            rr:          $rr,
            tempF:       $tempF,
            spo2:        $spo2,
            weightKg:    $weightKg,
            painScore:   $painScore,
            gcs:         $gcs,
            arrivalMode: $cfg->arrivalMode,
            notes:       $notes,
            userId:      $userId
        );

        if ($id === 0) {
            return [
                'flash'  => xlt('Error saving vitals. Please try again.'),
                'alerts' => [],
            ];
        }

        // Build vitals array for alert service
        $vitals = [
            'bp_systolic'  => $bpSystolic,
            'bp_diastolic' => $bpDiastolic,
            'hr'           => $hr,
            'rr'           => $rr,
            'temp_f'       => $tempF,
            'spo2'         => $spo2,
            'weight_kg'    => $weightKg,
            'pain_score'   => $painScore,
            'gcs'          => $gcs,
        ];

        // Previous weights for gain alert (only fetched when feature is on)
        $prevWeights = ($cfg->weightGainAlertKg > 0.0)
            ? $this->repo->weightTrend($episodeId, 14)
            : [];

        $alerts = $this->alertSvc->generate($vitals, $cfg, $prevWeights);

        $flash = xlt('Vitals saved successfully.');
        if ($alerts) {
            $flash .= ' ' . xlt('Clinical alert(s) generated — see below.');
        }

        return ['flash' => $flash, 'alerts' => $alerts];
    }
}




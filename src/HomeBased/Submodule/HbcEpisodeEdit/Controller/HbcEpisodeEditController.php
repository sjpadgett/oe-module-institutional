<?php

/**
 * src/HomeBased/Submodule/HbcEpisodeEdit/Controller/HbcEpisodeEditController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcEpisodeEdit\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcEpisodeEdit\Repository\HbcEpisodeEditRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\Disposition\Repository\EpisodeEventRepository;

final class HbcEpisodeEditController
{
    public function __construct(
        private readonly HbcEpisodeEditRepository $repo = new HbcEpisodeEditRepository(),
        private readonly EpisodeEventRepository $events = new EpisodeEventRepository()
    ) {}

    /** @return array<string,mixed> */
    public function handle(int $episodeId, int $facilityId, int $userId): array
    {
        $flash = '';
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $urgency = (string) ($_POST['urgency'] ?? 'ROUTINE');
            if (!in_array($urgency, ['ROUTINE', 'URGENT', 'EMERGENT'], true)) {
                $urgency = 'ROUTINE';
            }

            $fields = [
                'urgency'                   => $urgency,
                'referral_source'           => trim((string) ($_POST['referral_source'] ?? '')),
                'referral_reason'           => trim((string) ($_POST['referral_reason'] ?? '')),
                'primary_diagnosis'         => trim((string) ($_POST['primary_diagnosis'] ?? '')),
                'primary_icd10'             => trim((string) ($_POST['primary_icd10'] ?? '')),
                'primary_clinician_user_id' => (int) ($_POST['primary_clinician_user_id'] ?? 0),
                'service_address_line1'     => trim((string) ($_POST['service_address_line1'] ?? '')),
                'service_address_line2'     => trim((string) ($_POST['service_address_line2'] ?? '')),
                'service_city'              => trim((string) ($_POST['service_city'] ?? '')),
                'service_state_province'    => trim((string) ($_POST['service_state_province'] ?? '')),
                'service_postal_code'       => trim((string) ($_POST['service_postal_code'] ?? '')),
                'service_country'           => trim((string) ($_POST['service_country'] ?? '')),
                'access_notes'              => trim((string) ($_POST['access_notes'] ?? '')),
                'caregiver_name'            => trim((string) ($_POST['caregiver_name'] ?? '')),
                'caregiver_phone'           => trim((string) ($_POST['caregiver_phone'] ?? '')),
                'caregiver_relationship'    => trim((string) ($_POST['caregiver_relationship'] ?? '')),
                'payer_name'                => trim((string) ($_POST['payer_name'] ?? '')),
                'authorization_notes'       => trim((string) ($_POST['authorization_notes'] ?? '')),
                'cert_period_start'         => trim((string) ($_POST['cert_period_start'] ?? '')),
                'cert_period_end'           => trim((string) ($_POST['cert_period_end'] ?? '')),
                'authorized_visits_per_week' => trim((string) ($_POST['authorized_visits_per_week'] ?? '')),
            ];

            if ($fields['service_address_line1'] === '') {
                $error = xlt('Service address is required.');
            } else {
                $ok = $this->repo->update($episodeId, $fields);
                if ($ok) {
                    $flash = xlt('Episode updated.');
                    // Log event
                    $header = $this->repo->fetchEditable($episodeId);
                    if ($header) {
                        $this->events->addEvent(
                            $episodeId,
                            (int) $header['pid'],
                            null,
                            $facilityId,
                            'EPISODE_EDITED',
                            date('Y-m-d H:i:s'),
                            $userId,
                            'Episode data updated by user'
                        );
                    }
                } else {
                    $error = xlt('Failed to update episode.');
                }
            }
        }

        return [
            'flash'      => $flash,
            'error'      => $error,
            'episode'    => $this->repo->fetchEditable($episodeId),
            'clinicians' => $this->repo->listClinicians(),
        ];
    }
}




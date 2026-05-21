<?php

/**
 * src/Shared/Submodule/ClinicalNotes/Controller/ClinicalNotesController.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Controller;

use OpenEMR\Modules\Institutional\Core\Service\EncounterResolver;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;
use OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Repository\ClinicalNotesRepository;
use OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Service\ClinicalNotesService;

/**
 * ClinicalNotesController
 *
 * Display-only controller for clinical notes panels.
 * All write paths go through the native OpenEMR clinical_notes form
 * (interface/forms/clinical_notes/new.php).
 *
 * Used by public/shared/clinical_notes.php.
 */
final class ClinicalNotesController
{
    private readonly ClinicalNotesService $service;

    public function __construct()
    {
        $repo          = new ClinicalNotesRepository(new EncounterResolver(), new FormsRegistrar());
        $this->service = new ClinicalNotesService($repo);
    }

    /**
     * Data for the panel (latest 5 notes) embedded in a profile page.
     *
     * @return array{notes:list<array>,total:int,encounter_id:int|null,
     *             has_encounter:bool,launch_url:string,edit_base_url:string,
     *             episodeId:int,pid:int,episodeType:string} encounter_id is the OpenEMR encounter number
     */
    public function handlePanel(
        int    $episodeId,
        string $episodeType,
        int    $pid
    ): array {
        $data                = $this->service->panelData($episodeId, $episodeType, $pid);
        $data['episodeId']   = $episodeId;
        $data['pid']         = $pid;
        $data['episodeType'] = $episodeType;
        return $data;
    }

    /**
     * Full page data for the dedicated clinical_notes.php list view.
     *
     * @return array{notes:list<array>,total:int,encounter_id:int|null,
     *             has_encounter:bool,launch_url:string,edit_base_url:string,
     *             episodeId:int,pid:int,episodeType:string} encounter_id is the OpenEMR encounter number
     */
    public function handlePage(
        int    $episodeId,
        string $episodeType,
        int    $pid
    ): array {
        $data                = $this->service->listData($episodeId, $episodeType, $pid);
        $data['episodeId']   = $episodeId;
        $data['pid']         = $pid;
        $data['episodeType'] = $episodeType;
        return $data;
    }
}






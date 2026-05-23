<?php

/**
 * src/Shared/Submodule/ClinicalNotes/Service/ClinicalNotesService.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Service;

use OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Repository\ClinicalNotesRepository;

/**
 * ClinicalNotesService
 *
 * Business logic for clinical notes panel display.
 * Provides type/category labels, URL builders, and panel data.
 */
final class ClinicalNotesService
{
    /**
     * LOINC note-type codes mapped to display labels.
     * Covers the most common institutional note types.
     */
    private const NOTE_TYPE_LABELS = [
        '11506-3'  => 'Progress Note',
        '34117-2'  => 'Assessment',
        '18842-5'  => 'Discharge Summary',
        '11488-4'  => 'Consult Note',
        '34119-8'  => 'Nursing Note',
        '34900-1'  => 'Social Work Note',
        '34111-5'  => 'Emergency Note',
        '34879-7'  => 'Triage Note',
        '34122-4'  => 'Physician Note',
        '18776-5'  => 'Care Plan Note',
        '59408-5'  => 'Mental Health Assessment',
        '34109-9'  => 'Observation Note',
        // Plain-text fallbacks (when clinical_notes_type is not a LOINC code)
        'Progress Note'          => 'Progress Note',
        'Assessment'             => 'Assessment',
        'Discharge Summary'      => 'Discharge Summary',
        'Consult Note'           => 'Consult Note',
        'Nursing Note'           => 'Nursing Note',
        'Social Work Note'       => 'Social Work Note',
        'Emergency Note'         => 'Emergency Note',
        'Triage Note'            => 'Triage Note',
        'Physician Note'         => 'Physician Note',
        'Mental Health Assessment' => 'Mental Health Assessment',
        'Observation Note'       => 'Observation Note',
    ];

    /** Bootstrap badge colors by note type. */
    private const NOTE_TYPE_BADGE = [
        'Progress Note'          => 'primary',
        'Assessment'             => 'info',
        'Discharge Summary'      => 'purple',
        'Consult Note'           => 'warning',
        'Nursing Note'           => 'success',
        'Social Work Note'       => 'secondary',
        'Emergency Note'         => 'danger',
        'Triage Note'            => 'dark',
        'Physician Note'         => 'primary',
        'Mental Health Assessment'=> 'info',
        'Observation Note'       => 'secondary',
    ];

    /** Default note type pre-selected per episode context. */
    private const CONTEXT_DEFAULT_TYPE = [
        'AL'  => '11506-3', // Progress Note
        'ED'  => '34111-5', // Emergency Note
        'OBS' => '34109-9', // Observation Note
        'BH'  => '59408-5', // Mental Health Assessment
        'IP'  => '11506-3', // Progress Note
    ];

    public function __construct(private readonly ClinicalNotesRepository $repo) {}

    /**
     * Panel data: latest N notes + encounter info + launch URL.
     *
     * @return array{notes:list<array>,total:int,encounter_id:int|null,
     *             has_encounter:bool,launch_url:string,edit_base_url:string} encounter_id is the OpenEMR encounter number
     */
    public function panelData(
        int    $episodeId,
        string $episodeType,
        int    $pid,
        int    $panelLimit = 5
    ): array {
        $notes       = $this->repo->fetchByEpisode($episodeId, $episodeType, $panelLimit);
        $encounterId = $this->repo->resolveEncounter($episodeId, $episodeType);

        // Decorate with display label + badge color
        foreach ($notes as &$n) {
            $n['type_label'] = $this->noteTypeLabel($n['clinical_notes_type']);
            $n['type_badge'] = $this->noteTypeBadge($n['clinical_notes_type']);
            $n['excerpt']    = $this->excerpt($n['description'], 150);
        }
        unset($n);

        return [
            'notes'          => $notes,
            'total'          => count($notes),
            'encounter_id'   => $encounterId,
            'has_encounter'  => $encounterId !== null,
            'launch_url'     => $this->buildLaunchUrl($pid, $encounterId, $episodeType),
            'edit_base_url'  => $this->buildEditBaseUrl($pid, $encounterId),
        ];
    }

    /**
     * Full list data for the dedicated clinical_notes.php page (paginated).
     */
    public function listData(
        int    $episodeId,
        string $episodeType,
        int    $pid,
        int    $limit = 50
    ): array {
        $notes       = $this->repo->fetchByEpisode($episodeId, $episodeType, $limit);
        $encounterId = $this->repo->resolveEncounter($episodeId, $episodeType);

        foreach ($notes as &$n) {
            $n['type_label'] = $this->noteTypeLabel($n['clinical_notes_type']);
            $n['type_badge'] = $this->noteTypeBadge($n['clinical_notes_type']);
            $n['excerpt']    = $this->excerpt($n['description'], 300);
        }
        unset($n);

        return [
            'notes'         => $notes,
            'total'         => count($notes),
            'encounter_id'  => $encounterId,
            'has_encounter' => $encounterId !== null,
            'launch_url'    => $this->buildLaunchUrl($pid, $encounterId, $episodeType),
            'edit_base_url' => $this->buildEditBaseUrl($pid, $encounterId),
        ];
    }

    /** Human-readable note type label from LOINC code or plain-text type. */
    public function noteTypeLabel(string $type): string
    {
        return self::NOTE_TYPE_LABELS[$type] ?? ($type ?: 'Note');
    }

    /** Bootstrap badge color class for the note type. */
    public function noteTypeBadge(string $type): string
    {
        $label = $this->noteTypeLabel($type);
        return self::NOTE_TYPE_BADGE[$label] ?? 'secondary';
    }

    /** Truncate description for panel display. */
    public function excerpt(string $text, int $maxLen = 150): string
    {
        $plain = strip_tags($text);
        if (mb_strlen($plain) <= $maxLen) {
            return $plain;
        }
        return mb_substr($plain, 0, $maxLen) . '…';
    }

    /** OE form URL for a new note, pre-filled with encounter + context default type. */
    public function buildLaunchUrl(int $pid, ?int $encounterId, string $episodeType = 'AL'): string
    {
        if ($encounterId === null) {
            return '';
        }
        $base        = $GLOBALS['webroot'] ?? '';
        $defaultType = self::CONTEXT_DEFAULT_TYPE[strtoupper($episodeType)] ?? '11506-3';
        return "{$base}/interface/forms/clinical_notes/new.php"
             . "?id=0&pid={$pid}&encounter={$encounterId}"
             . "&clinical_notes_type=" . urlencode($defaultType);
    }

    /** Base URL for editing an existing note — append &id=<formId>. */
    public function buildEditBaseUrl(int $pid, ?int $encounterId): string
    {
        if ($encounterId === null) {
            return '';
        }
        $base = $GLOBALS['webroot'] ?? '';
        return "{$base}/interface/forms/clinical_notes/new.php"
             . "?pid={$pid}&encounter={$encounterId}&id=";
    }
}






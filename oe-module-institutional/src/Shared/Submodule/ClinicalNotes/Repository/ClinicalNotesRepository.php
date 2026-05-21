<?php

/**
 * src/Shared/Submodule/ClinicalNotes/Repository/ClinicalNotesRepository.php
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

namespace OpenEMR\Modules\Institutional\Shared\Submodule\ClinicalNotes\Repository;

use OpenEMR\Modules\Institutional\Core\Service\EncounterResolver;
use OpenEMR\Modules\Institutional\Core\Service\FormsRegistrar;

/**
 * ClinicalNotesRepository
 *
 * Read bridge to OpenEMR's form_clinical_notes, clinical_notes_documents,
 * and clinical_notes_procedure_results tables.
 *
 * All new notes are created via the native OpenEMR form
 * (interface/forms/clinical_notes/new.php) — this repository is
 * read-only in normal clinical use.  The programmatic addNote() method
 * is retained for seed data and API use only.
 */
final class ClinicalNotesRepository
{
    public function __construct(
        private readonly EncounterResolver $encounterResolver,
        private readonly FormsRegistrar    $formsRegistrar
    ) {}

    // ── Read ─────────────────────────────────────────────────────────────────

    /**
     * Fetch clinical notes for an episode, newest first.
     *
     * @param  int    $episodeId
     * @param  string $episodeType 'AL'|'IP'|'ED'|'OBS'|'BH'
     * @param  int    $limit       Maximum rows to return (default 20)
     * @return list<array{id:int,form_id:int,clinical_notes_type:string,
     *                   clinical_notes_category:string,description:string,
     *                   code:string,codetext:string,note_related_to:string,
     *                   last_updated:string,user:string,
     *                   doc_count:int,result_count:int}>
     */
    public function fetchByEpisode(
        int    $episodeId,
        string $episodeType,
        int    $limit = 20
    ): array {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $encounterNum = $this->encounterResolver->resolve($episodeId, $episodeType);
        if ($encounterNum === null) {
            return [];
        }

        $ep = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        if (!$ep) {
            return [];
        }

        $res = sqlStatement(
            "SELECT   cn.id, cn.form_id,
                      COALESCE(cn.clinical_notes_type,'')     AS clinical_notes_type,
                      COALESCE(cn.clinical_notes_category,'') AS clinical_notes_category,
                      COALESCE(cn.description,'')             AS description,
                      COALESCE(cn.code,'')                    AS code,
                      COALESCE(cn.codetext,'')                AS codetext,
                      COALESCE(cn.note_related_to,'')         AS note_related_to,
                      cn.last_updated,
                      COALESCE(cn.user,'')                    AS user,
                      (SELECT COUNT(*) FROM clinical_notes_documents cnd
                        WHERE cnd.clinical_note_id = cn.id)   AS doc_count,
                      (SELECT COUNT(*) FROM clinical_notes_procedure_results cnpr
                        WHERE cnpr.clinical_note_id = cn.id)  AS result_count
             FROM     form_clinical_notes cn
             WHERE    cn.pid = ? AND cn.encounter = ? AND cn.activity = 1
             ORDER BY cn.last_updated DESC
             LIMIT    ?",
            [(int)$ep['pid'], $encounterNum, $limit]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                     => (int)$r['id'],
                'form_id'                => (int)$r['form_id'],
                'clinical_notes_type'    => (string)$r['clinical_notes_type'],
                'clinical_notes_category'=> (string)$r['clinical_notes_category'],
                'description'            => (string)$r['description'],
                'code'                   => (string)$r['code'],
                'codetext'               => (string)$r['codetext'],
                'note_related_to'        => (string)$r['note_related_to'],
                'last_updated'           => (string)$r['last_updated'],
                'user'                   => (string)$r['user'],
                'doc_count'              => (int)$r['doc_count'],
                'result_count'           => (int)$r['result_count'],
            ];
        }
        return $rows;
    }

    /**
     * Fetch notes filtered by clinical_notes_type.
     */
    public function fetchByType(
        int    $episodeId,
        string $episodeType,
        string $type,
        int    $limit = 50
    ): array {
        if (!function_exists('sqlStatement')) {
            return [];
        }

        $encounterNum = $this->encounterResolver->resolve($episodeId, $episodeType);
        if ($encounterNum === null) {
            return [];
        }

        $ep = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        if (!$ep) {
            return [];
        }

        $res = sqlStatement(
            "SELECT cn.id, cn.form_id,
                    COALESCE(cn.clinical_notes_type,'')     AS clinical_notes_type,
                    COALESCE(cn.clinical_notes_category,'') AS clinical_notes_category,
                    COALESCE(cn.description,'')             AS description,
                    COALESCE(cn.code,'')                    AS code,
                    cn.last_updated,
                    COALESCE(cn.user,'')                    AS user,
                    (SELECT COUNT(*) FROM clinical_notes_documents cnd
                      WHERE cnd.clinical_note_id = cn.id)   AS doc_count,
                    (SELECT COUNT(*) FROM clinical_notes_procedure_results cnpr
                      WHERE cnpr.clinical_note_id = cn.id)  AS result_count
             FROM   form_clinical_notes cn
             WHERE  cn.pid = ? AND cn.encounter = ?
               AND  cn.clinical_notes_type = ? AND cn.activity = 1
             ORDER BY cn.last_updated DESC
             LIMIT  ?",
            [(int)$ep['pid'], $encounterNum, $type, $limit]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                     => (int)$r['id'],
                'form_id'                => (int)$r['form_id'],
                'clinical_notes_type'    => (string)$r['clinical_notes_type'],
                'clinical_notes_category'=> (string)$r['clinical_notes_category'],
                'description'            => (string)$r['description'],
                'code'                   => (string)$r['code'],
                'last_updated'           => (string)$r['last_updated'],
                'user'                   => (string)$r['user'],
                'doc_count'              => (int)$r['doc_count'],
                'result_count'           => (int)$r['result_count'],
            ];
        }
        return $rows;
    }

    /** Linked documents for a note (clinical_notes_documents). */
    public function fetchLinkedDocuments(int $clinicalNoteId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT cnd.id, cnd.document_id, cnd.created_at,
                    d.url, d.name
             FROM   clinical_notes_documents cnd
             LEFT   JOIN documents d ON d.id = cnd.document_id
             WHERE  cnd.clinical_note_id = ?
             ORDER  BY cnd.created_at DESC",
            [$clinicalNoteId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'          => (int)$r['id'],
                'document_id' => (int)$r['document_id'],
                'created_at'  => (string)$r['created_at'],
                'name'        => (string)($r['name'] ?? ''),
                'url'         => (string)($r['url'] ?? ''),
            ];
        }
        return $rows;
    }

    /** Linked procedure results for a note (clinical_notes_procedure_results). */
    public function fetchLinkedResults(int $clinicalNoteId): array
    {
        if (!function_exists('sqlStatement')) {
            return [];
        }
        $res = sqlStatement(
            "SELECT cnpr.id, cnpr.procedure_result_id, cnpr.created_at
             FROM   clinical_notes_procedure_results cnpr
             WHERE  cnpr.clinical_note_id = ?
             ORDER  BY cnpr.created_at DESC",
            [$clinicalNoteId]
        );
        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'id'                  => (int)$r['id'],
                'procedure_result_id' => (int)$r['procedure_result_id'],
                'created_at'          => (string)$r['created_at'],
            ];
        }
        return $rows;
    }

    /** Return the resolved encounter number — for building OE form URLs. */
    public function resolveEncounter(int $episodeId, string $episodeType): ?int
    {
        return $this->encounterResolver->resolve($episodeId, $episodeType);
    }

    // ── Write (programmatic only — normal UI uses OE native form) ────────────

    /**
     * Programmatically insert a clinical note and register in forms table.
     * Use for seeding / API. Normal clinical input goes through OE form.
     */
    public function addNote(
        int    $episodeId,
        string $episodeType,
        string $noteType,
        string $category,
        string $description,
        string $code,
        int    $userId
    ): bool {
        if (!function_exists('sqlInsert')) {
            return false;
        }

        $encounterNum = $this->encounterResolver->resolve($episodeId, $episodeType);
        if ($encounterNum === null) {
            return false;
        }

        $ep = sqlQuery('SELECT pid FROM oei_episode WHERE id = ? LIMIT 1', [$episodeId]);
        if (!$ep) {
            return false;
        }

        $pid = (int)$ep['pid'];
        $user = $_SESSION['authUser'] ?? 'admin';

        // form_clinical_notes requires form_id = FK to forms.form_id
        // We insert a placeholder 0 first, then register in forms table using
        // the encounter number, then update form_id with the returned forms row id.
        $noteId = (int)sqlInsert(
            "INSERT INTO form_clinical_notes
                 (form_id, date, pid, encounter, user, groupname, authorized, activity,
                  code, description, clinical_notes_type, clinical_notes_category)
             VALUES (0, NOW(), ?, ?, ?, 'Default', 1, 1, ?, ?, ?, ?)",
            [$pid, $encounterNum, $user, $code, $description, $noteType, $category]
        );

        if ($noteId <= 0) {
            return false;
        }

        $this->formsRegistrar->register(
            $pid,
            $encounterNum,
            $noteId,
            'clinical_notes',
            'Clinical Notes',
            $userId
        );

        return true;
    }
}






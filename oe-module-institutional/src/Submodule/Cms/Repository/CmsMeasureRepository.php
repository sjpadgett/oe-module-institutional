<?php

/**
 * src/Submodule/Cms/Repository/CmsMeasureRepository.php
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

namespace OpenEMR\Modules\Institutional\Submodule\Cms\Repository;

/**
 * CmsMeasureRepository
 *
 * Keeps the existing operational/ED timing measures for backward compatibility,
 * while also exposing an institutional-quality dashboard foundation that can be
 * expanded toward native inpatient eCQM / hospital-harm reporting.
 */
final class CmsMeasureRepository
{
    /**
     * Drug name substrings considered antibiotics for SEP-1 bundle compliance.
     * Case-insensitive substring match against oei_mar_order.drug_name.
     */
    private const ANTIBIOTIC_KEYWORDS = [
        'vancomycin',  'piperacillin', 'tazobactam',   'cefazolin',
        'ceftriaxone', 'cefepime',     'meropenem',    'imipenem',
        'azithromycin','levofloxacin', 'ciprofloxacin','metronidazole',
        'clindamycin', 'doxycycline',  'ampicillin',   'amoxicillin',
        'gentamicin',  'tobramycin',   'linezolid',    'daptomycin',
        'tigecycline', 'ertapenem',    'doripenem',    'oxacillin',
        'nafcillin',   'trimethoprim', 'sulfamethoxazole',
    ];

    /**
     * Task type substrings considered an ECG/EKG for door-to-ECG measure.
     */
    private const ECG_TASK_KEYWORDS = ['EKG', 'ECG', '12-LEAD', '12LEAD'];

    /**
     * Common opioid medication keywords for institutional opioid-harm signalling.
     */
    private const OPIOID_KEYWORDS = [
        'morphine', 'hydromorphone', 'dilaudid', 'fentanyl', 'oxycodone',
        'hydrocodone', 'tramadol', 'codeine', 'methadone', 'meperidine',
        'buprenorphine', 'tapentadol', 'oxymorphone', 'nalbuphine',
    ];

    /**
     * Common opioid antagonist keywords for institutional opioid-harm signalling.
     */
    private const OPIOID_ANTAGONIST_KEYWORDS = ['naloxone', 'narcan'];

    /**
     * Core LOINC codes for CMS HWR (CMS529) and HWM (CMS844) hybrid extraction.
     * These are the first-result variables required by the measure specifications.
     * Join path: oei_ip_episode.encounter_id
     *             → procedure_order.encounter_id
     *             → procedure_report.procedure_order_id
     *             → procedure_result.procedure_report_id
     *             WHERE procedure_result.result_code IN (these codes)
     */
    private const HWR_LOINC_CODES = [
        '2951-2',  // Sodium
        '3094-0',  // BUN (Blood Urea Nitrogen)
        '2160-0',  // Creatinine
        '1751-7',  // Albumin
        '6690-2',  // White Blood Cell count
        '718-7',   // Hemoglobin
        '2345-7',  // Glucose
        '2823-3',  // Potassium
        '1920-8',  // AST
        '1742-6',  // ALT
    ];

    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,array{code:string,title:string,list_id:string}|null> */
    private array $ecqmMetaCache = [];

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    /**
     * Compute the existing operational timing / bundle measures.
     *
     * @return array{
     *   door_to_room:     array<string,mixed>,
     *   door_to_provider: array<string,mixed>,
     *   door_to_ecg:      array<string,mixed>,
     *   sepsis_bundle:    array<string,mixed>,
     * }
     */
    public function computeAll(int $facilityId, string $dateFrom, string $dateTo): array
    {
        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';

        return [
            'door_to_room'     => $this->doorToRoom($facilityId, $dateFrom, $dateToEnd),
            'door_to_provider' => $this->doorToProvider($facilityId, $dateFrom, $dateToEnd),
            'door_to_ecg'      => $this->doorToEcg($facilityId, $dateFrom, $dateToEnd),
            'sepsis_bundle'    => $this->sepsisBundle($facilityId, $dateFrom, $dateToEnd),
        ];
    }

    /**
     * Institutional-quality dashboard foundation.
     *
     * This intentionally stops short of claiming full CMS/eCQM execution.
     * It groups:
     *   - existing operational timing measures
     *   - native OpenEMR institutional eCQM targets / status
     *   - readiness scaffolding for source data capture
     *   - a few currently-implementable institutional signals
     *
     * @return array<string,mixed>
     */
    public function computeDashboard(int $facilityId, string $dateFrom, string $dateTo): array
    {
        $operational = $this->computeAll($facilityId, $dateFrom, $dateTo);
        $readiness   = $this->buildReadiness($facilityId, $dateFrom, $dateTo);
        $signals     = $this->buildSignals($facilityId, $dateFrom, $dateTo);
        $catalog     = $this->buildInstitutionalCatalog($readiness, $signals);

        $readinessPcts = array_values(array_filter(array_map(
            static fn(array $item) => $item['pct'] ?? null,
            $readiness
        ), static fn($v) => $v !== null));

        $overallReadiness = $readinessPcts
            ? round(array_sum($readinessPcts) / count($readinessPcts), 1)
            : null;

        $signalFlags = count(array_filter(
            $signals,
            static fn(array $s) => in_array((string)($s['status'] ?? ''), ['WATCH', 'ACTION'], true)
        ));

        return [
            'summary' => [
                'overall_readiness_pct' => $overallReadiness,
                'catalog_count'         => count($catalog),
                'operational_count'     => count($operational),
                'signal_count'          => count($signals),
                'signal_flags'          => $signalFlags,
            ],
            'operational' => $operational,
            'readiness'   => $readiness,
            'signals'     => $signals,
            'catalog'     => $catalog,
        ];
    }

    // ---------------------------------------------------------------------
    // Institutional quality dashboard helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string,array<string,mixed>>
     */
    private function buildReadiness(int $facilityId, string $dateFrom, string $dateTo): array
    {
        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';

        $ipEpisodes = $this->countEpisodesByType($facilityId, $dateFrom, $dateToEnd, ['IP']);
        $alEpisodes = $this->countEpisodesByType($facilityId, $dateFrom, $dateToEnd, ['AL']);
        $institutionalEpisodes = $this->countEpisodesByType($facilityId, $dateFrom, $dateToEnd, ['IP', 'AL']);

        $ipEncounterLinked = $this->countOverlayEpisodes(
            'oei_ip_episode',
            'ip',
            $facilityId,
            $dateFrom,
            $dateToEnd,
            ['IP'],
            'ip.encounter_id IS NOT NULL AND ip.encounter_id > 0'
        );
        $alEncounterLinked = $this->countOverlayEpisodes(
            'oei_al_episode',
            'al',
            $facilityId,
            $dateFrom,
            $dateToEnd,
            ['AL'],
            'al.encounter_id IS NOT NULL AND al.encounter_id > 0'
        );
        $ipVitals = $this->countEpisodesWithTriage($facilityId, $dateFrom, $dateToEnd, ['IP']);
        $instWithMar = $this->countEpisodesWithMar($facilityId, $dateFrom, $dateToEnd, ['IP', 'AL']);
        $alFallAssess = $this->countEpisodesWithFallRiskAssessment($facilityId, $dateFrom, $dateToEnd);
        $closedIp = $this->countClosedIpEpisodes($facilityId, $dateFrom, $dateToEnd);
        $closedIpWithSummary = $this->countClosedIpEpisodes($facilityId, $dateFrom, $dateToEnd, true);
        $ipLabCapture = $this->countEpisodesWithHwrLabs($facilityId, $dateFrom, $dateToEnd);

        $rows = [
            'ip_encounter_linkage' => $this->readinessItem(
                'IP encounter linkage',
                $ipEncounterLinked,
                $ipEpisodes,
                'IP overlays anchored to a native OpenEMR encounter number for care plans, notes, and future extraction.'
            ),
            'al_encounter_linkage' => $this->readinessItem(
                'AL encounter linkage',
                $alEncounterLinked,
                $alEpisodes,
                'AL overlays anchored to a native OpenEMR encounter number for shared clinical content.'
            ),
            'ip_vitals_capture' => $this->readinessItem(
                'IP vitals capture',
                $ipVitals,
                $ipEpisodes,
                'Needed for hybrid HWR/HWM extraction and inpatient harm logic that depends on first-result vitals.'
            ),
            'institutional_mar_capture' => $this->readinessItem(
                'Institutional MAR capture',
                $instWithMar,
                $institutionalEpisodes,
                'Medication administrations are present for IP/AL episodes and can support opioid / high-alert signal work.'
            ),
            'al_fall_assessment_capture' => $this->readinessItem(
                'AL fall-risk assessment workflow',
                $alFallAssess,
                $alEpisodes,
                'Assisted Living fall-risk assessments are a strong precursor for institutional fall / harm workflows.'
            ),
            'ip_discharge_summary_completion' => $this->readinessItem(
                'IP discharge summary completion',
                $closedIpWithSummary,
                $closedIp,
                'Closed inpatient episodes carrying discharge summaries are the cleanest starting point for discharge quality reporting.'
            ),
            'ip_lab_capture' => $this->readinessItem(
                'IP first-result lab capture (HWR/HWM)',
                $ipLabCapture,
                $ipEpisodes,
                'Inpatient episodes with at least one core HWR/HWM lab result (sodium, BUN, creatinine, albumin, WBC, hemoglobin, glucose) in the OpenEMR procedure results table. This is the remaining piece for hybrid extraction.'
            ),
        ];

        // Hybrid HWR/HWM extraction readiness: needs encounter linkage + vitals + labs.
        // All three dimensions are now tracked independently in $rows above.
        $hybridNumerator = min($ipEncounterLinked, $ipVitals, $ipLabCapture);
        $hybridPct = $ipEpisodes > 0
            ? round($hybridNumerator / $ipEpisodes * 100, 1)
            : null;
        $hybridStatus = match (true) {
            $hybridPct === null || $hybridPct <= 0                                          => 'PLANNED',
            $ipLabCapture === 0                                                              => 'PARTIAL',
            $hybridPct >= 80 && $ipLabCapture >= $ipEncounterLinked * 0.8                   => 'READY',
            default                                                                          => 'PARTIAL',
        };
        $hybridNote = $ipLabCapture === 0
            ? 'Encounter linkage and vitals are present; lab results are the missing piece. '
              . 'Labs must be captured via OpenEMR procedure orders so procedure_result rows exist for HWR/HWM LOINC codes.'
            : sprintf(
                'Encounter-linked: %d/%d  ·  Vitals: %d/%d  ·  Labs: %d/%d  ·  All three ready: %d/%d.',
                $ipEncounterLinked, $ipEpisodes,
                $ipVitals, $ipEpisodes,
                $ipLabCapture, $ipEpisodes,
                $hybridNumerator, $ipEpisodes
              );
        $rows['hybrid_extract_readiness'] = [
            'label'      => 'Hybrid HWR/HWM extraction',
            'numerator'  => $hybridNumerator,
            'denominator'=> $ipEpisodes,
            'pct'        => $hybridPct,
            'status'     => $hybridStatus,
            'note'       => $hybridNote,
        ];

        return $rows;
    }

    /**
     * @return array<string,array<string,mixed>>
     */
    private function buildSignals(int $facilityId, string $dateFrom, string $dateTo): array
    {
        return [
            'falls_with_injury'        => $this->fallsWithInjurySignal($facilityId, $dateFrom, $dateTo),
            'opioid_adverse_events'    => $this->opioidAdverseEventSignal($facilityId, $dateFrom, $dateTo),
            'high_alert_cosign'        => $this->highAlertCosignSignal($facilityId, $dateFrom, $dateTo),
            'ip_discharge_completion'  => $this->ipDischargeCompletionSignal($facilityId, $dateFrom, $dateTo),
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $readiness
     * @param array<string,array<string,mixed>> $signals
     * @return array<int,array<string,mixed>>
     */
    private function buildInstitutionalCatalog(array $readiness, array $signals): array
    {
        $catalog = [
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS1017',
                'title'  => 'Hospital Harm - Falls with Injury',
                'status' => $this->tableExists('oei_incident') ? 'READY' : 'PLANNED',
                'note'   => 'Current module source captures incident rows that can anchor fall-with-injury reporting. Current period events: ' . (int)($signals['falls_with_injury']['event_count'] ?? 0) . '.',
            ],
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS816',
                'title'  => 'Hospital Harm - Severe Hypoglycemia',
                'status' => ($readiness['institutional_mar_capture']['pct'] ?? null) !== null ? 'PARTIAL' : 'PLANNED',
                'note'   => 'Hypoglycemic medication administrations can be tracked from MAR, but glucose/lab harm logic is not yet mapped into the module.',
            ],
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS819',
                'title'  => 'Hospital Harm - Opioid-Related Adverse Events',
                'status' => $this->tableExists('oei_mar_order') && $this->tableExists('oei_mar_administration') ? 'READY' : 'PLANNED',
                'note'   => 'This dashboard now surfaces a conservative naloxone-after-opioid signal from MAR data while keeping the measure framed as institutional quality, not final eCQM logic.',
            ],
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS826',
                'title'  => 'Hospital Harm - Pressure Injury',
                'status' => 'PLANNED',
                'note'   => 'No dedicated pressure-injury workflow is mapped yet; this belongs behind future wound / skin integrity capture.',
            ],
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS832',
                'title'  => 'Hospital Harm - Acute Kidney Injury',
                'status' => 'PLANNED',
                'note'   => 'AKI needs serum creatinine / dialysis data mapped into the institutional layer before anything beyond a placeholder is honest.',
            ],
            [
                'group'  => 'Hybrid Extraction',
                'family' => 'CMS529',
                'title'  => 'Hybrid Hospital-Wide Readmission (HWR) extraction',
                'status' => (string)($readiness['hybrid_extract_readiness']['status'] ?? 'PLANNED'),
                'note'   => 'Encounter-linked inpatient episodes plus vitals give you a real head start, but first-result lab extraction still needs to be wired in.',
            ],
            [
                'group'  => 'Hybrid Extraction',
                'family' => 'CMS844',
                'title'  => 'Hybrid Hospital-Wide Mortality (HWM) extraction',
                'status' => (string)($readiness['hybrid_extract_readiness']['status'] ?? 'PLANNED'),
                'note'   => 'Same institutional extraction seam as HWR: encounter-linked IP episodes are in place, labs still remain the missing piece.',
            ],
            [
                'group'  => 'Hospital Harm',
                'family' => 'CMS871',
                'title'  => 'Hospital Harm - Severe Hyperglycemia',
                'status' => ($readiness['ip_vitals_capture']['pct'] ?? null) !== null ? 'PARTIAL' : 'PLANNED',
                'note'   => 'Vitals capture is available, but true hyperglycemia harm logic still depends on mapped glucose results and inpatient-day logic.',
            ],
            [
                'group'  => 'Nutrition / Care Planning',
                'family' => 'CMS986',
                'title'  => 'Global Malnutrition Composite Score',
                'status' => 'PLANNED',
                'note'   => 'This is a strong future fit for encounter-linked inpatient care plans, but it needs explicit nutrition screening / diagnosis capture first.',
            ],
        ];

        foreach ($catalog as &$item) {
            $meta = $this->lookupEcqmMeta($item['family']);
            if ($meta !== null) {
                $item['code'] = $meta['code'];
                $item['title'] = $meta['title'];
                $item['list_id'] = $meta['list_id'];
            } else {
                $item['code'] = $item['family'];
                $item['list_id'] = '';
            }
        }
        unset($item);

        return $catalog;
    }

    private function fallsWithInjurySignal(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!$this->tableExists('oei_incident')) {
            return $this->emptySignal(
                'Falls with injury',
                'CMS1017',
                'PLANNED',
                '—',
                'Incident workflow is not installed.'
            );
        }

        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';
        $res = sqlStatement(
            "SELECT
                i.episode_id,
                i.incident_datetime,
                i.incident_type,
                i.severity,
                e.type AS episode_type,
                e.pid
             FROM oei_incident i
             JOIN oei_episode e ON e.id = i.episode_id
             WHERE i.facility_id = ?
               AND i.incident_datetime BETWEEN ? AND ?
               AND e.type IN ('IP','AL')
               AND (
                    i.incident_type = 'FALL_INJURY'
                    OR (i.incident_type = 'FALL' AND i.severity IN ('MODERATE','HIGH','CRITICAL'))
               )
             ORDER BY i.incident_datetime DESC",
            [$facilityId, $dateFrom, $dateToEnd]
        );

        $rows = [];
        $episodeSet = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'episode_id'         => (int)$r['episode_id'],
                'pid'                => (int)$r['pid'],
                'incident_datetime'  => (string)$r['incident_datetime'],
                'incident_type'      => (string)$r['incident_type'],
                'severity'           => (string)$r['severity'],
                'episode_type'       => (string)$r['episode_type'],
            ];
            $episodeSet[(string)$r['episode_id']] = true;
        }

        $eventCount = count($rows);
        $episodeCount = count($episodeSet);
        $status = $eventCount > 0 ? 'ACTION' : 'GOOD';

        return [
            'label'        => 'Falls with injury',
            'code'         => $this->lookupEcqmCode('CMS1017'),
            'status'       => $status,
            'value'        => (string)$eventCount,
            'subtext'      => $episodeCount . ' episode' . ($episodeCount === 1 ? '' : 's') . ' flagged in the selected period',
            'note'         => 'Counts incident rows already captured in the module. This is an institutional signal, not a finalized CMS day-based ratio.',
            'event_count'  => $eventCount,
            'episode_count'=> $episodeCount,
            'rows'         => $rows,
        ];
    }

    private function opioidAdverseEventSignal(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!$this->tableExists('oei_mar_order') || !$this->tableExists('oei_mar_administration') || !$this->tableExists('oei_ip_episode')) {
            return $this->emptySignal(
                'Opioid-related adverse events',
                'CMS819',
                'PLANNED',
                '—',
                'MAR workflow is not installed.'
            );
        }

        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';
        $opioidWhere = $this->buildLikeWhere('LOWER(opo.drug_name)', self::OPIOID_KEYWORDS);
        $opioidParams = $this->buildLikeParams(self::OPIOID_KEYWORDS);
        $nalWhere = $this->buildLikeWhere('LOWER(nxo.drug_name)', self::OPIOID_ANTAGONIST_KEYWORDS);
        $nalParams = $this->buildLikeParams(self::OPIOID_ANTAGONIST_KEYWORDS);

        $denomRow = sqlQuery(
            "SELECT COUNT(DISTINCT e.id) AS n
             FROM oei_episode e
             JOIN oei_ip_episode ip ON ip.episode_id = e.id
             JOIN oei_mar_administration opa
               ON opa.episode_id = e.id
              AND opa.outcome = 'GIVEN'
              AND opa.administered_datetime IS NOT NULL
             JOIN oei_mar_order opo
               ON opo.id = opa.mar_order_id
              AND ({$opioidWhere})
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?",
            array_merge($opioidParams, [$facilityId, $dateFrom, $dateToEnd])
        );
        $denominator = (int)($denomRow['n'] ?? 0);

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                MIN(opa.administered_datetime) AS opioid_dt,
                MIN(nxa.administered_datetime) AS antagonist_dt,
                MIN(opo.drug_name) AS opioid_name,
                MIN(nxo.drug_name) AS antagonist_name
             FROM oei_episode e
             JOIN oei_ip_episode ip ON ip.episode_id = e.id
             JOIN oei_mar_administration opa
               ON opa.episode_id = e.id
              AND opa.outcome = 'GIVEN'
              AND opa.administered_datetime IS NOT NULL
             JOIN oei_mar_order opo
               ON opo.id = opa.mar_order_id
              AND ({$opioidWhere})
             JOIN oei_mar_administration nxa
               ON nxa.episode_id = e.id
              AND nxa.outcome = 'GIVEN'
              AND nxa.administered_datetime IS NOT NULL
              AND nxa.administered_datetime BETWEEN opa.administered_datetime AND DATE_ADD(opa.administered_datetime, INTERVAL 12 HOUR)
             JOIN oei_mar_order nxo
               ON nxo.id = nxa.mar_order_id
              AND ({$nalWhere})
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid
             ORDER BY antagonist_dt DESC",
            array_merge($opioidParams, $nalParams, [$facilityId, $dateFrom, $dateToEnd])
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $rows[] = [
                'episode_id'       => (int)$r['episode_id'],
                'pid'              => (int)$r['pid'],
                'opioid_dt'        => (string)$r['opioid_dt'],
                'antagonist_dt'    => (string)$r['antagonist_dt'],
                'opioid_name'      => (string)$r['opioid_name'],
                'antagonist_name'  => (string)$r['antagonist_name'],
                'minutes_to_rescue'=> $this->diffMin((string)$r['opioid_dt'], (string)$r['antagonist_dt']),
            ];
        }

        $numerator = count($rows);
        $rate = $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
        $status = $numerator > 0 ? 'ACTION' : ($denominator > 0 ? 'GOOD' : 'WATCH');

        return [
            'label'       => 'Opioid adverse-event signal',
            'code'        => $this->lookupEcqmCode('CMS819'),
            'status'      => $status,
            'value'       => $numerator === 0 ? '0' : (string)$numerator,
            'subtext'     => $denominator . ' opioid-exposed IP episode' . ($denominator === 1 ? '' : 's') . ($rate !== null ? ' · ' . $rate . '%' : ''),
            'note'        => 'Conservative signal: naloxone / Narcan given within 12 hours of an opioid administration in an inpatient episode.',
            'numerator'   => $numerator,
            'denominator' => $denominator,
            'rate_pct'    => $rate,
            'rows'        => $rows,
        ];
    }

    private function highAlertCosignSignal(int $facilityId, string $dateFrom, string $dateTo): array
    {
        if (!$this->tableExists('oei_mar_administration')) {
            return $this->emptySignal('High-alert co-sign compliance', '', 'PLANNED', '—', 'MAR administration workflow is not installed.');
        }

        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';
        $row = sqlQuery(
            "SELECT
                COUNT(*) AS denom,
                SUM(CASE WHEN (a.co_sign_user_id IS NOT NULL OR a.co_signed_datetime IS NOT NULL) THEN 1 ELSE 0 END) AS numer
             FROM oei_mar_administration a
             JOIN oei_episode e ON e.id = a.episode_id
             WHERE e.facility_id = ?
               AND e.type IN ('IP','AL')
               AND a.is_high_alert = 1
               AND a.outcome = 'GIVEN'
               AND a.administered_datetime BETWEEN ? AND ?",
            [$facilityId, $dateFrom, $dateToEnd]
        );

        $denom = (int)($row['denom'] ?? 0);
        $numer = (int)($row['numer'] ?? 0);
        $rate = $denom > 0 ? round($numer / $denom * 100, 1) : null;
        $status = $rate === null ? 'WATCH' : ($rate >= 95 ? 'GOOD' : ($rate >= 80 ? 'WATCH' : 'ACTION'));

        return [
            'label'       => 'High-alert co-sign compliance',
            'code'        => '',
            'status'      => $status,
            'value'       => $rate === null ? '—' : $rate . '%',
            'subtext'     => $numer . ' / ' . $denom . ' high-alert administrations co-signed',
            'note'        => 'Not a native CMS measure, but a strong institutional medication-safety signal from your current MAR workflow.',
            'numerator'   => $numer,
            'denominator' => $denom,
            'rate_pct'    => $rate,
            'rows'        => [],
        ];
    }

    private function ipDischargeCompletionSignal(int $facilityId, string $dateFrom, string $dateTo): array
    {
        $dateToEnd = substr($dateTo, 0, 10) . ' 23:59:59';
        $denom = $this->countClosedIpEpisodes($facilityId, $dateFrom, $dateToEnd);
        $numer = $this->countClosedIpEpisodes($facilityId, $dateFrom, $dateToEnd, true);
        $rate = $denom > 0 ? round($numer / $denom * 100, 1) : null;
        $status = $rate === null ? 'WATCH' : ($rate >= 90 ? 'GOOD' : ($rate >= 70 ? 'WATCH' : 'ACTION'));

        return [
            'label'       => 'IP discharge summary completion',
            'code'        => '',
            'status'      => $status,
            'value'       => $rate === null ? '—' : $rate . '%',
            'subtext'     => $numer . ' / ' . $denom . ' closed inpatient episodes with discharge summaries',
            'note'        => 'This is a practical institutional quality signal and a future bridge into better discharge / care-transition reporting.',
            'numerator'   => $numer,
            'denominator' => $denom,
            'rate_pct'    => $rate,
            'rows'        => [],
        ];
    }

    // ---------------------------------------------------------------------
    // Existing operational measures
    // ---------------------------------------------------------------------

    private function doorToRoom(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-Room', 30);
        }

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime AS arrive_dt,
                MIN(ev_room.event_datetime) AS room_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
               ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_episode_event ev_room
               ON ev_room.episode_id = e.id
              AND ev_room.event_type IN ('ROOM','ROOMED')
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            [$facilityId, $from, $to]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin((string)$r['arrive_dt'], (string)$r['room_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => (string)$r['arrive_dt'],
                    'event_dt'   => (string)$r['room_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 30,
                ];
            }
        }

        return $this->buildMeasure('Door-to-Room', 30, 'OP-1', $rows);
    }

    private function doorToProvider(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-Provider', 60);
        }

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime AS arrive_dt,
                MIN(ev_prov.event_datetime) AS provider_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
               ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_episode_event ev_prov
               ON ev_prov.episode_id = e.id AND ev_prov.event_type = 'PROVIDER'
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            [$facilityId, $from, $to]
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin((string)$r['arrive_dt'], (string)$r['provider_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => (string)$r['arrive_dt'],
                    'event_dt'   => (string)$r['provider_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 60,
                ];
            }
        }

        return $this->buildMeasure('Door-to-Provider', 60, 'OP-2', $rows);
    }

    private function doorToEcg(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Door-to-ECG', 10);
        }

        $whereTask = $this->buildLikeWhere('t.task_type', self::ECG_TASK_KEYWORDS);
        $likeParams = array_map(static fn(string $kw) => '%' . $kw . '%', self::ECG_TASK_KEYWORDS);

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime AS arrive_dt,
                MIN(t.completed_datetime) AS ecg_dt
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
               ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_task t
               ON t.episode_id = e.id
              AND t.status = 'COMPLETE'
              AND t.completed_datetime IS NOT NULL
              AND ({$whereTask})
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            array_merge($likeParams, [$facilityId, $from, $to])
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin((string)$r['arrive_dt'], (string)$r['ecg_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => (string)$r['arrive_dt'],
                    'event_dt'   => (string)$r['ecg_dt'],
                    'minutes'    => $min,
                    'met'        => $min <= 10,
                ];
            }
        }

        return $this->buildMeasure('Door-to-ECG', 10, 'OP-5', $rows);
    }

    private function sepsisBundle(int $facilityId, string $from, string $to): array
    {
        if (!function_exists('sqlStatement')) {
            return $this->emptyMeasure('Sepsis Antibiotic Bundle ≤3h', 180);
        }

        $whereAbx = $this->buildLikeWhere('LOWER(o.drug_name)', self::ANTIBIOTIC_KEYWORDS);
        $likeParams = $this->buildLikeParams(self::ANTIBIOTIC_KEYWORDS);

        $res = sqlStatement(
            "SELECT
                e.id AS episode_id,
                e.pid,
                e.start_datetime,
                ev_arr.event_datetime AS arrive_dt,
                MIN(a.administered_datetime) AS first_abx_dt,
                MIN(o.drug_name) AS drug_name
             FROM oei_episode e
             JOIN oei_episode_event ev_arr
               ON ev_arr.episode_id = e.id AND ev_arr.event_type = 'ARRIVE'
             JOIN oei_mar_administration a
               ON a.episode_id = e.id
              AND a.outcome = 'GIVEN'
              AND a.administered_datetime IS NOT NULL
             JOIN oei_mar_order o
               ON o.id = a.mar_order_id
              AND ({$whereAbx})
             WHERE e.facility_id = ?
               AND e.start_datetime BETWEEN ? AND ?
             GROUP BY e.id, e.pid, e.start_datetime, ev_arr.event_datetime",
            array_merge($likeParams, [$facilityId, $from, $to])
        );

        $rows = [];
        while ($r = sqlFetchArray($res)) {
            $min = $this->diffMin((string)$r['arrive_dt'], (string)$r['first_abx_dt']);
            if ($min !== null && $min >= 0) {
                $rows[] = [
                    'episode_id' => (int)$r['episode_id'],
                    'pid'        => (int)$r['pid'],
                    'arrive_dt'  => (string)$r['arrive_dt'],
                    'event_dt'   => (string)$r['first_abx_dt'],
                    'minutes'    => $min,
                    'drug_name'  => (string)$r['drug_name'],
                    'met'        => $min <= 180,
                ];
            }
        }

        return $this->buildMeasure('Sepsis Antibiotic Bundle ≤3h', 180, 'SEP-1', $rows);
    }

    // ---------------------------------------------------------------------
    // Query helpers / readiness helpers
    // ---------------------------------------------------------------------

    private function readinessItem(string $label, int $numerator, int $denominator, string $note): array
    {
        $pct = $denominator > 0 ? round($numerator / $denominator * 100, 1) : null;
        $status = $pct === null ? 'PLANNED' : ($pct >= 80 ? 'READY' : ($pct > 0 ? 'PARTIAL' : 'PLANNED'));

        return [
            'label'       => $label,
            'numerator'   => $numerator,
            'denominator' => $denominator,
            'pct'         => $pct,
            'status'      => $status,
            'note'        => $note,
        ];
    }

    private function countEpisodesByType(int $facilityId, string $from, string $to, array $types): int
    {
        if (!$this->tableExists('oei_episode')) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $row = sqlQuery(
            "SELECT COUNT(*) AS n
             FROM oei_episode e
             WHERE e.facility_id = ?
               AND e.type IN ({$placeholders})
               AND e.start_datetime BETWEEN ? AND ?",
            array_merge([$facilityId], $types, [$from, $to])
        );
        return (int)($row['n'] ?? 0);
    }

    private function countOverlayEpisodes(string $overlayTable, string $alias, int $facilityId, string $from, string $to, array $types, string $extraWhere = ''): int
    {
        if (!$this->tableExists($overlayTable) || !$this->tableExists('oei_episode')) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT COUNT(DISTINCT e.id) AS n
                FROM oei_episode e
                JOIN {$overlayTable} {$alias} ON {$alias}.episode_id = e.id
                WHERE e.facility_id = ?
                  AND e.type IN ({$placeholders})
                  AND e.start_datetime BETWEEN ? AND ?";
        if ($extraWhere !== '') {
            $sql .= ' AND ' . $extraWhere;
        }
        $row = sqlQuery($sql, array_merge([$facilityId], $types, [$from, $to]));
        return (int)($row['n'] ?? 0);
    }

    private function countEpisodesWithTriage(int $facilityId, string $from, string $to, array $types): int
    {
        if (!$this->tableExists('oei_triage')) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $row = sqlQuery(
            "SELECT COUNT(DISTINCT e.id) AS n
             FROM oei_episode e
             JOIN oei_triage t ON t.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.type IN ({$placeholders})
               AND e.start_datetime BETWEEN ? AND ?",
            array_merge([$facilityId], $types, [$from, $to])
        );
        return (int)($row['n'] ?? 0);
    }

    private function countEpisodesWithMar(int $facilityId, string $from, string $to, array $types): int
    {
        if (!$this->tableExists('oei_mar_administration')) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $row = sqlQuery(
            "SELECT COUNT(DISTINCT e.id) AS n
             FROM oei_episode e
             JOIN oei_mar_administration a ON a.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.type IN ({$placeholders})
               AND e.start_datetime BETWEEN ? AND ?",
            array_merge([$facilityId], $types, [$from, $to])
        );
        return (int)($row['n'] ?? 0);
    }

    private function countEpisodesWithFallRiskAssessment(int $facilityId, string $from, string $to): int
    {
        if (!$this->tableExists('oei_fall_risk_assessment')) {
            return 0;
        }
        $row = sqlQuery(
            "SELECT COUNT(DISTINCT e.id) AS n
             FROM oei_episode e
             JOIN oei_al_episode al ON al.episode_id = e.id
             JOIN oei_fall_risk_assessment fra ON fra.episode_id = e.id
             WHERE e.facility_id = ?
               AND e.type = 'AL'
               AND e.start_datetime BETWEEN ? AND ?",
            [$facilityId, $from, $to]
        );
        return (int)($row['n'] ?? 0);
    }

    private function countClosedIpEpisodes(int $facilityId, string $from, string $to, bool $requireSummary = false): int
    {
        if (!$this->tableExists('oei_ip_episode')) {
            return 0;
        }
        $sql = "SELECT COUNT(DISTINCT e.id) AS n
                FROM oei_episode e
                JOIN oei_ip_episode ip ON ip.episode_id = e.id
                WHERE e.facility_id = ?
                  AND e.type = 'IP'
                  AND e.start_datetime BETWEEN ? AND ?
                  AND (e.end_datetime IS NOT NULL OR (e.disposition IS NOT NULL AND TRIM(e.disposition) <> ''))";
        if ($requireSummary) {
            $sql .= " AND ip.discharge_summary IS NOT NULL AND TRIM(ip.discharge_summary) <> ''";
        }
        $row = sqlQuery($sql, [$facilityId, $from, $to]);
        return (int)($row['n'] ?? 0);
    }

    /**
     * Count IP episodes that have at least one first-result lab value for
     * a core HWR/HWM LOINC code in the OpenEMR procedure results table.
     *
     * Join path:
     *   oei_ip_episode.encounter_id (= form_encounter.encounter number)
     *   → procedure_order.encounter_id
     *   → procedure_report.procedure_order_id
     *   → procedure_result.procedure_report_id
     *   WHERE result_code IN (HWR_LOINC_CODES)
     *     AND result_status IN ('final', 'corrected', 'preliminary', '')
     *
     * Returns 0 gracefully if procedure_result or procedure_order tables are absent.
     */
    private function countEpisodesWithHwrLabs(int $facilityId, string $from, string $to): int
    {
        if (!$this->tableExists('oei_ip_episode')
            || !$this->tableExists('procedure_order')
            || !$this->tableExists('procedure_report')
            || !$this->tableExists('procedure_result')
        ) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count(self::HWR_LOINC_CODES), '?'));

        $row = sqlQuery(
            "SELECT COUNT(DISTINCT e.id) AS n
             FROM oei_episode e
             JOIN oei_ip_episode ip ON ip.episode_id = e.id
             JOIN procedure_order po
               ON po.encounter_id = ip.encounter_id
              AND po.patient_id   = e.pid
              AND po.activity     = 1
             JOIN procedure_report pr
               ON pr.procedure_order_id = po.procedure_order_id
              AND pr.report_status NOT IN ('error', 'canceled')
             JOIN procedure_result pres
               ON pres.procedure_report_id = pr.procedure_report_id
              AND pres.result_code IN ({$placeholders})
              AND pres.result <> ''
             WHERE e.facility_id   = ?
               AND e.type          = 'IP'
               AND e.start_datetime BETWEEN ? AND ?",
            array_merge(self::HWR_LOINC_CODES, [$facilityId, $from, $to])
        );

        return (int)($row['n'] ?? 0);
    }

    private function tableExists(string $table): bool
    {
        if (isset($this->tableExistsCache[$table])) {
            return $this->tableExistsCache[$table];
        }
        if (!function_exists('sqlQuery')) {
            return $this->tableExistsCache[$table] = false;
        }
        $row = sqlQuery(
            "SELECT COUNT(*) AS table_count
               FROM information_schema.tables
              WHERE table_schema = DATABASE()
                AND table_name = ?",
            [$table]
        );
        return $this->tableExistsCache[$table] = (int)($row['table_count'] ?? 0) > 0;
    }

    private function buildLikeWhere(string $column, array $keywords): string
    {
        return implode(' OR ', array_map(static fn() => $column . ' LIKE ?', $keywords));
    }

    /** @return array<int,string> */
    private function buildLikeParams(array $keywords): array
    {
        return array_map(static fn(string $kw) => '%' . strtolower($kw) . '%', $keywords);
    }

    /**
     * @return array{code:string,title:string,list_id:string}|null
     */
    private function lookupEcqmMeta(string $family): ?array
    {
        if (array_key_exists($family, $this->ecqmMetaCache)) {
            return $this->ecqmMetaCache[$family];
        }
        if (!function_exists('sqlQuery') || !$this->tableExists('list_options')) {
            return $this->ecqmMetaCache[$family] = null;
        }
        $row = sqlQuery(
            "SELECT option_id, title, list_id
             FROM list_options
             WHERE list_id IN ('ecqm_2025_reporting','ecqm_2024_reporting','ecqm_2023_reporting')
               AND option_id LIKE ?
             ORDER BY FIELD(list_id, 'ecqm_2025_reporting','ecqm_2024_reporting','ecqm_2023_reporting'), seq
             LIMIT 1",
            [$family . '%']
        );
        if (!$row) {
            return $this->ecqmMetaCache[$family] = null;
        }
        return $this->ecqmMetaCache[$family] = [
            'code'    => (string)$row['option_id'],
            'title'   => (string)$row['title'],
            'list_id' => (string)$row['list_id'],
        ];
    }

    private function lookupEcqmCode(string $family): string
    {
        $meta = $this->lookupEcqmMeta($family);
        return $meta['code'] ?? $family;
    }

    // ---------------------------------------------------------------------
    // Generic helpers
    // ---------------------------------------------------------------------

    private function diffMin(string $from, ?string $to): ?int
    {
        if ($from === '' || $to === null || $to === '') {
            return null;
        }
        $a = strtotime($from);
        $b = strtotime($to);
        if (!$a || !$b) {
            return null;
        }
        return (int)round(($b - $a) / 60);
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function buildMeasure(string $label, int $targetMin, string $cmsId, array $rows): array
    {
        $n = count($rows);
        $nMet = count(array_filter($rows, static fn($r) => !empty($r['met'])));
        $mins = array_values(array_filter(array_map(static fn($r) => $r['minutes'] ?? null, $rows), static fn($v) => $v !== null));

        sort($mins);

        $avg = $n ? (int)round(array_sum($mins) / $n) : null;
        $median = $this->percentile($mins, 50);
        $p90 = $this->percentile($mins, 90);
        $rate = $n ? round($nMet / $n * 100, 1) : null;

        return [
            'label'      => $label,
            'cms_id'     => $cmsId,
            'target_min' => $targetMin,
            'n'          => $n,
            'n_met'      => $nMet,
            'rate_pct'   => $rate,
            'avg_min'    => $avg,
            'median_min' => $median,
            'p90_min'    => $p90,
            'rows'       => $rows,
        ];
    }

    private function emptyMeasure(string $label, int $targetMin): array
    {
        return [
            'label' => $label,
            'cms_id' => '',
            'target_min' => $targetMin,
            'n' => 0,
            'n_met' => 0,
            'rate_pct' => null,
            'avg_min' => null,
            'median_min' => null,
            'p90_min' => null,
            'rows' => [],
        ];
    }

    /**
     * @param array<int,mixed> $sorted
     */
    private function percentile(array $sorted, int $pct): ?int
    {
        $n = count($sorted);
        if ($n === 0) {
            return null;
        }
        $idx = (int)ceil($pct / 100 * $n) - 1;
        return (int)$sorted[max(0, min($idx, $n - 1))];
    }

    private function emptySignal(string $label, string $code, string $status, string $value, string $note): array
    {
        return [
            'label' => $label,
            'code' => $code,
            'status' => $status,
            'value' => $value,
            'subtext' => '',
            'note' => $note,
            'rows' => [],
        ];
    }
}







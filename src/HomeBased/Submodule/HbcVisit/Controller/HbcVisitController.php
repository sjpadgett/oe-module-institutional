<?php

/**
 * src/HomeBased/Submodule/HbcVisit/Controller/HbcVisitController.php
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
namespace OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitStatus;
use OpenEMR\Modules\Institutional\HomeBased\Domain\HbcVisitType;
use OpenEMR\Modules\Institutional\HomeBased\Submodule\HbcVisit\Repository\HbcVisitRepository;

final class HbcVisitController
{
    public function __construct(
        private readonly HbcVisitRepository $repo = new HbcVisitRepository()
    ) {}

    /** @return array{result:array,clinicians:array,visits:array} */
    public function handleSchedule(int $episodeId, int $facilityId, int $userId): array
    {
        $result = ['success' => false, 'visit_id' => 0, 'error' => '', 'submitted' => false];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }

            $action = (string) ($_POST['action'] ?? 'schedule');

            if ($action === 'cancel_visit') {
                $visitId = (int) ($_POST['visit_id'] ?? 0);
                $ok = $visitId > 0 ? $this->repo->cancel($visitId) : false;
                $result = [
                    'success' => $ok,
                    'visit_id' => $visitId,
                    'error' => $ok ? '' : xlt('Unable to cancel visit.'),
                    'submitted' => true,
                ];
            } elseif ($action === 'edit_visit') {
                $visitId = (int) ($_POST['visit_id'] ?? 0);
                if ($visitId > 0) {
                    $editType = (string) ($_POST['edit_visit_type'] ?? '');
                    if ($editType !== '' && !in_array($editType, HbcVisitType::all(), true)) {
                        $editType = null;
                    }
                    $editDt = trim((string) ($_POST['edit_scheduled_datetime'] ?? ''));
                    $editWinStart = trim((string) ($_POST['edit_window_start'] ?? ''));
                    $editWinEnd = trim((string) ($_POST['edit_window_end'] ?? ''));
                    $editRoute = (int) ($_POST['edit_route_sequence'] ?? 0);
                    $editTravel = trim((string) ($_POST['edit_travel_notes'] ?? ''));
                    $editClinician = (int) ($_POST['edit_clinician_user_id'] ?? 0);
                    $editSupervisory = !empty($_POST['edit_is_supervisory']);

                    $ok = $this->repo->updateScheduled(
                        $visitId,
                        $editClinician,
                        $editDt !== '' ? date('Y-m-d H:i:s', strtotime($editDt) ?: time()) : null,
                        $editWinStart !== '' ? date('Y-m-d H:i:s', strtotime($editWinStart) ?: time()) : null,
                        $editWinEnd !== '' ? date('Y-m-d H:i:s', strtotime($editWinEnd) ?: time()) : null,
                        $editRoute > 0 ? $editRoute : null,
                        $editTravel !== '' ? $editTravel : null,
                        $editSupervisory,
                        $editType ?: null,
                    );
                    $result = [
                        'success' => $ok,
                        'visit_id' => $visitId,
                        'error' => $ok ? '' : xlt('Unable to update visit.'),
                        'submitted' => true,
                    ];
                }
            } else {
                $pid = (int) ($_POST['pid'] ?? 0);
                $dtRaw = trim((string) ($_POST['scheduled_datetime'] ?? ''));

                if ($dtRaw === '') {
                    $result = [
                        'success' => false,
                        'visit_id' => 0,
                        'error' => xlt('Scheduled date/time is required.'),
                        'submitted' => true,
                    ];
                } else {
                    $visitType = (string) ($_POST['visit_type'] ?? HbcVisitType::SN);
                    if (!in_array($visitType, HbcVisitType::all(), true)) {
                        $visitType = HbcVisitType::SN;
                    }

                    $dtMysql = date('Y-m-d H:i:s', strtotime($dtRaw) ?: time());
                    $windowStart = trim((string) ($_POST['window_start_datetime'] ?? ''));
                    $windowEnd = trim((string) ($_POST['window_end_datetime'] ?? ''));
                    $routeSequence = (int) ($_POST['route_sequence'] ?? 0);
                    $travelNotes = trim((string) ($_POST['travel_notes'] ?? ''));
                    $clinicianId = (int) ($_POST['clinician_user_id'] ?? 0);

                    $isSupervisory = !empty($_POST['is_supervisory']);

                    $visitId = $this->repo->create(
                        $episodeId,
                        $pid,
                        $facilityId,
                        $visitType,
                        $clinicianId > 0 ? $clinicianId : null,
                        $dtMysql,
                        $userId,
                        $windowStart !== '' ? date('Y-m-d H:i:s', strtotime($windowStart) ?: time()) : null,
                        $windowEnd !== '' ? date('Y-m-d H:i:s', strtotime($windowEnd) ?: time()) : null,
                        $routeSequence > 0 ? $routeSequence : null,
                        $travelNotes !== '' ? $travelNotes : null,
                        $isSupervisory,
                    );

                    if ($visitId > 0 && !empty($_POST['repeat_enabled'])) {
                        // ── Batch/recurring scheduling ──────────────────
                        $repeatWeeks = max(1, min(8, (int) ($_POST['repeat_weeks'] ?? 4)));
                        $repeatDays  = array_map('intval', (array) ($_POST['repeat_days'] ?? []));
                        $repeatDays  = array_filter($repeatDays, fn($d) => $d >= 0 && $d <= 6);

                        if (!empty($repeatDays)) {
                            $anchorTime = date('H:i:s', strtotime($dtMysql));
                            $anchorDate = new \DateTime(substr($dtMysql, 0, 10));
                            $created    = 1; // already created the anchor visit

                            // Generate dates for each selected weekday across N weeks
                            // Start from the day AFTER anchor to avoid duplicate on anchor date
                            $cursor = clone $anchorDate;
                            $cursor->modify('+1 day');
                            $endDate = (clone $anchorDate)->modify('+' . $repeatWeeks . ' weeks');

                            while ($cursor <= $endDate) {
                                $dow = (int) $cursor->format('w'); // 0=Sun ... 6=Sat
                                if (in_array($dow, $repeatDays, true)) {
                                    $batchDt = $cursor->format('Y-m-d') . ' ' . $anchorTime;
                                    $batchWinStart = null;
                                    $batchWinEnd   = null;
                                    if ($windowStart !== '') {
                                        $winOffset = strtotime($windowStart) - strtotime($dtMysql);
                                        $batchWinStart = date('Y-m-d H:i:s', strtotime($batchDt) + $winOffset);
                                    }
                                    if ($windowEnd !== '') {
                                        $winOffset = strtotime($windowEnd) - strtotime($dtMysql);
                                        $batchWinEnd = date('Y-m-d H:i:s', strtotime($batchDt) + $winOffset);
                                    }

                                    $bId = $this->repo->create(
                                        $episodeId, $pid, $facilityId,
                                        $visitType,
                                        $clinicianId > 0 ? $clinicianId : null,
                                        $batchDt, $userId,
                                        $batchWinStart, $batchWinEnd,
                                        $routeSequence > 0 ? $routeSequence : null,
                                        $travelNotes !== '' ? $travelNotes : null,
                                        $isSupervisory,
                                    );
                                    if ($bId > 0) {
                                        $created++;
                                    }
                                }
                                $cursor->modify('+1 day');
                            }

                            $result = [
                                'success' => true,
                                'visit_id' => $visitId,
                                'error' => '',
                                'submitted' => true,
                                'batch_count' => $created,
                            ];
                        } else {
                            $result = [
                                'success' => $visitId > 0,
                                'visit_id' => $visitId,
                                'error' => $visitId > 0 ? '' : xlt('Failed to schedule visit.'),
                                'submitted' => true,
                            ];
                        }
                    } else {
                        $result = [
                            'success' => $visitId > 0,
                            'visit_id' => $visitId,
                            'error' => $visitId > 0 ? '' : xlt('Failed to schedule visit. Please try again.'),
                            'submitted' => true,
                        ];
                    }
                }
            }
        }

        $planningDate = trim((string)($_POST['scheduled_datetime'] ?? ''));
        if ($planningDate === '') {
            $planningDate = date('Y-m-d H:i:s', strtotime('+1 day 09:00'));
        } else {
            $planningDate = date('Y-m-d H:i:s', strtotime($planningDate) ?: time());
        }

        return [
            'result' => $result,
            'clinicians' => $this->repo->listClinicians(),
            'visits' => $this->repo->listByEpisode($episodeId),
            'recommendation' => $this->repo->fetchSchedulingRecommendation($episodeId),
            'day_load' => $this->repo->fetchClinicianDayLoad($facilityId, substr($planningDate, 0, 10)),
            'planning_datetime' => $planningDate,
        ];
    }

    /** @return array{visit:array,draft:array}|null */
    public function handleWorkspace(int $visitId, int $userId): ?array
    {
        $lat = isset($_POST['lat']) && $_POST['lat'] !== '' ? (float) $_POST['lat'] : null;
        $lng = isset($_POST['lng']) && $_POST['lng'] !== '' ? (float) $_POST['lng'] : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'save_draft' || $action === 'finalise') {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                header('Content-Type: application/json; charset=utf-8');

                if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                    echo json_encode(['ok' => false, 'error' => 'csrf']);
                    exit;
                }
                if ($visitId <= 0) {
                    echo json_encode(['ok' => false, 'error' => 'missing visit_id']);
                    exit;
                }

                if ($action === 'save_draft') {
                    $draftFields = [
                        'visit_note' => trim((string) ($_POST['visit_note'] ?? '')),
                        'outcome_summary' => trim((string) ($_POST['outcome_summary'] ?? '')),
                        'mileage_miles' => trim((string) ($_POST['mileage_miles'] ?? '')),
                        'completion_status' => trim((string) ($_POST['completion_status'] ?? HbcVisitStatus::COMPLETE)),
                        'med_reconciliation_status' => trim((string) ($_POST['med_reconciliation_status'] ?? 'NOT_DONE')),
                        'med_reconciliation_summary' => trim((string) ($_POST['med_reconciliation_summary'] ?? '')),
                        'wound_summary' => trim((string) ($_POST['wound_summary'] ?? '')),
                        'procedure_summary' => trim((string) ($_POST['procedure_summary'] ?? '')),
                        'home_safety_summary' => trim((string) ($_POST['home_safety_summary'] ?? '')),
                        'care_coordination_needed' => !empty($_POST['care_coordination_needed']) ? 1 : 0,
                        'care_coordination_summary' => trim((string) ($_POST['care_coordination_summary'] ?? '')),
                        'followup_plan' => trim((string) ($_POST['followup_plan'] ?? '')),
                        'next_visit_due_date' => trim((string) ($_POST['next_visit_due_date'] ?? '')),
                        'next_visit_type' => trim((string) ($_POST['next_visit_type'] ?? '')),
                        'vitals_bp_systolic' => trim((string) ($_POST['vitals_bp_systolic'] ?? '')),
                        'vitals_bp_diastolic' => trim((string) ($_POST['vitals_bp_diastolic'] ?? '')),
                        'vitals_hr' => trim((string) ($_POST['vitals_hr'] ?? '')),
                        'vitals_rr' => trim((string) ($_POST['vitals_rr'] ?? '')),
                        'vitals_spo2' => trim((string) ($_POST['vitals_spo2'] ?? '')),
                        'vitals_temp_f' => trim((string) ($_POST['vitals_temp_f'] ?? '')),
                        'vitals_weight_kg' => trim((string) ($_POST['vitals_weight_kg'] ?? '')),
                        'vitals_pain_score' => trim((string) ($_POST['vitals_pain_score'] ?? '')),
                    ];
                    $this->repo->saveDraft($visitId, json_encode($draftFields, JSON_UNESCAPED_UNICODE), $lat, $lng);
                    echo json_encode(['ok' => true]);
                    exit;
                }

                $sigData = trim((string) ($_POST['signature_data'] ?? ''));
                $sigObtained = $sigData !== '' && str_starts_with($sigData, 'data:image/png;base64,');
                $completionStatus = strtoupper(trim((string) ($_POST['completion_status'] ?? HbcVisitStatus::COMPLETE)));
                if (!in_array($completionStatus, [HbcVisitStatus::COMPLETE, HbcVisitStatus::REFUSED, HbcVisitStatus::MISSED], true)) {
                    $completionStatus = HbcVisitStatus::COMPLETE;
                }
                $medRec = strtoupper(trim((string) ($_POST['med_reconciliation_status'] ?? 'NOT_DONE')));
                if (!in_array($medRec, ['NOT_DONE', 'NO_CHANGES', 'UPDATED', 'ISSUES_FOUND'], true)) {
                    $medRec = 'NOT_DONE';
                }
                $nextVisitType = strtoupper(trim((string) ($_POST['next_visit_type'] ?? '')));
                if ($nextVisitType !== '' && !in_array($nextVisitType, HbcVisitType::all(), true)) {
                    $nextVisitType = '';
                }

                // Collect inline vitals (nullable — only written if any value present)
                $inlineVitals = [
                    'bp_systolic'  => trim((string) ($_POST['vitals_bp_systolic'] ?? '')),
                    'bp_diastolic' => trim((string) ($_POST['vitals_bp_diastolic'] ?? '')),
                    'hr'           => trim((string) ($_POST['vitals_hr'] ?? '')),
                    'rr'           => trim((string) ($_POST['vitals_rr'] ?? '')),
                    'spo2'         => trim((string) ($_POST['vitals_spo2'] ?? '')),
                    'temp_f'       => trim((string) ($_POST['vitals_temp_f'] ?? '')),
                    'weight_kg'    => trim((string) ($_POST['vitals_weight_kg'] ?? '')),
                    'pain_score'   => trim((string) ($_POST['vitals_pain_score'] ?? '')),
                ];

                $ok = $this->repo->finalise(
                    visitId: $visitId,
                    visitNote: trim((string) ($_POST['visit_note'] ?? '')),
                    outcomeSummary: trim((string) ($_POST['outcome_summary'] ?? '')),
                    mileage: trim((string) ($_POST['mileage_miles'] ?? '')) !== '' ? (float) $_POST['mileage_miles'] : null,
                    actualStart: '',
                    actualEnd: date('Y-m-d H:i:s'),
                    completionStatus: $completionStatus,
                    sigObtained: $sigObtained,
                    sigData: $sigObtained ? $sigData : null,
                    lat: $lat,
                    lng: $lng,
                    medReconciliationStatus: $medRec,
                    medReconciliationSummary: trim((string) ($_POST['med_reconciliation_summary'] ?? '')),
                    woundSummary: trim((string) ($_POST['wound_summary'] ?? '')),
                    procedureSummary: trim((string) ($_POST['procedure_summary'] ?? '')),
                    homeSafetySummary: trim((string) ($_POST['home_safety_summary'] ?? '')),
                    careCoordinationNeeded: !empty($_POST['care_coordination_needed']),
                    careCoordinationSummary: trim((string) ($_POST['care_coordination_summary'] ?? '')),
                    followupPlan: trim((string) ($_POST['followup_plan'] ?? '')),
                    nextVisitDueDate: trim((string) ($_POST['next_visit_due_date'] ?? '')) ?: null,
                    nextVisitType: $nextVisitType !== '' ? $nextVisitType : null,
                    userId: $userId > 0 ? $userId : null,
                    vitals: $inlineVitals,
                );
                echo json_encode(['ok' => $ok]);
                exit;
            }
        }

        $visit = $this->repo->fetchOne($visitId);
        if (!$visit) {
            return null;
        }

        $draft = [];
        if (!empty($visit['draft_data'])) {
            $decoded = json_decode((string) $visit['draft_data'], true);
            if (is_array($decoded)) {
                $draft = $decoded;
            }
        }

        return ['visit' => $visit, 'draft' => $draft];
    }

    public function handle(int $facilityId, int $userId): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }
        $action = (string) ($_POST['action'] ?? '');
        $visitId = (int) ($_POST['visit_id'] ?? 0);
        if ($action !== 'save_draft' && $action !== 'finalise') {
            return;
        }
        $this->handleWorkspace($visitId, $userId);
    }
}















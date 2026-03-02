<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Controller;

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AlActivity\Repository\AlActivityRepository;

/**
 * AlActivityController
 *
 * Two modes:
 *   facility  — date-picker view showing all sessions for a date with
 *               per-session attendance grids. Supports logging new sessions.
 *   resident  — per-episode history (30-day) showing participation rates
 *               and session list. Accessed from Resident Nav tab.
 */
final class AlActivityController
{
    /** Valid activity types with display labels and icons. */
    public const TYPES = [
        'SOCIAL_GROUP'    => ['label' => 'Social Group',          'icon' => '🎭'],
        'MUSIC'           => ['label' => 'Music Therapy',         'icon' => '🎵'],
        'EXERCISE'        => ['label' => 'Exercise / Movement',   'icon' => '🏃'],
        'COGNITIVE'       => ['label' => 'Cognitive Stimulation', 'icon' => '🧩'],
        'OUTDOOR'         => ['label' => 'Outdoor / Nature',      'icon' => '🌿'],
        'DEVOTIONAL'      => ['label' => 'Devotional / Chapel',   'icon' => '⛪'],
        'CRAFT'           => ['label' => 'Arts & Crafts',         'icon' => '🎨'],
        'DINING_SOCIAL'   => ['label' => 'Dining Social',         'icon' => '🍽️'],
        'INDIVIDUAL_VISIT'=> ['label' => 'Individual Visit',      'icon' => '🤝'],
        'THERAPY_PT'      => ['label' => 'Physical Therapy',      'icon' => '🦽'],
        'THERAPY_OT'      => ['label' => 'Occupational Therapy',  'icon' => '🔧'],
        'THERAPY_ST'      => ['label' => 'Speech Therapy',        'icon' => '💬'],
        'OTHER'           => ['label' => 'Other',                 'icon' => '📝'],
    ];

    /** Participation levels for the attendance grid. */
    public const LEVELS = [
        'FULL'    => ['label' => 'Full',    'badge' => 'success'],
        'PARTIAL' => ['label' => 'Partial', 'badge' => 'warning'],
        'REFUSED' => ['label' => 'Refused', 'badge' => 'secondary'],
        'ABSENT'  => ['label' => 'Absent',  'badge' => 'light'],
    ];

    public function __construct(
        private readonly AlActivityRepository $repo
    ) {}

    /**
     * Facility-wide mode: show sessions for a date, handle new session POST.
     * @param array<int,array<string,mixed>> $residents  active AL episodes + patient_data
     * @return array<string,mixed>
     */
    public function handleFacility(
        int    $facilityId,
        string $date,
        array  $residents,
        ?int   $userId
    ): array {
        $csrf    = CsrfUtils::collectCsrfToken();
        $flash   = '';
        $error   = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!CsrfUtils::verifyCsrfToken($_POST['csrf_token_form'] ?? '')) {
                die('CSRF validation failed');
            }
            $action = trim((string)($_POST['action'] ?? ''));

            if ($action === 'log_session') {
                [$flash, $error] = $this->handleLogSession($facilityId, $userId, $residents);
            }
            if ($action === 'update_attendance') {
                [$flash, $error] = $this->handleUpdateAttendance($facilityId);
            }
        }

        $sessions = $this->repo->getByDate($facilityId, $date);
        $typeSummary = $this->repo->typeSummary($facilityId, 7);

        // Build a quick episode_id → name map for template use
        $residentMap = [];
        foreach ($residents as $r) {
            $residentMap[(string)$r['episode_id']] = [
                'name' => trim($r['fname'] . ' ' . $r['lname']),
                'room' => $r['room'] ?? '',
            ];
        }

        return [
            'csrf'        => $csrf,
            'flash'       => $flash,
            'error'       => $error,
            'sessions'    => $sessions,
            'residents'   => $residents,
            'residentMap' => $residentMap,
            'date'        => $date,
            'types'       => self::TYPES,
            'levels'      => self::LEVELS,
            'typeSummary' => $typeSummary,
        ];
    }

    /**
     * Resident-specific mode: participation history for nav tab.
     * @return array<string,mixed>
     */
    public function handleResident(
        int $episodeId,
        int $facilityId
    ): array {
        $sessions = $this->repo->getByEpisode($episodeId, $facilityId);

        // Compute participation summary
        $stats = ['total' => 0, 'full' => 0, 'partial' => 0, 'refused' => 0, 'absent' => 0];
        $eidStr = (string)$episodeId;

        foreach ($sessions as $s) {
            $att = $s['attendance'][$eidStr] ?? null;
            if (!$att) {
                continue;
            }
            $stats['total']++;
            $level = strtolower((string)($att['level'] ?? ''));
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
        }

        $participation = $stats['total'] > 0
            ? round(100 * ($stats['full'] + $stats['partial']) / $stats['total'])
            : null;

        return [
            'sessions'      => $sessions,
            'stats'         => $stats,
            'participation' => $participation,
            'types'         => self::TYPES,
            'levels'        => self::LEVELS,
            'episodeId'     => $episodeId,
        ];
    }

    // ── POST handlers ─────────────────────────────────────────────────────────

    /**
     * @param array<int,array<string,mixed>> $residents
     * @return array{0:string,1:string} [flash, error]
     */
    private function handleLogSession(
        int   $facilityId,
        ?int  $userId,
        array $residents
    ): array {
        $type     = trim((string)($_POST['activity_type'] ?? ''));
        $name     = trim((string)($_POST['activity_name'] ?? ''));
        $date     = trim((string)($_POST['activity_date'] ?? date('Y-m-d')));
        $time     = trim((string)($_POST['start_time'] ?? ''));
        $duration = max(5, (int)($_POST['duration_minutes'] ?? 30));
        $location = trim((string)($_POST['location'] ?? '')) ?: null;
        $notes    = trim((string)($_POST['notes'] ?? '')) ?: null;
        $ledBy    = trim((string)($_POST['led_by_name'] ?? '')) ?: null;

        if (!isset(self::TYPES[$type])) {
            return ['', xlt('Select a valid activity type.')];
        }
        if ($name === '') {
            return ['', xlt('Activity name is required.')];
        }

        // Build attendance from posted episode_id_N / level_N pairs
        $attendance = [];
        foreach ($residents as $r) {
            $eid   = (string)$r['episode_id'];
            $level = strtoupper(trim((string)($_POST['level_' . $eid] ?? 'ABSENT')));
            $note  = trim((string)($_POST['anote_' . $eid] ?? ''));
            if (!isset(self::LEVELS[$level])) {
                $level = 'ABSENT';
            }
            $attendance[$eid] = ['level' => $level, 'note' => $note];
        }

        $this->repo->insert(
            $facilityId, $date, $type, $name, $time,
            $duration, $location, $userId, $ledBy, $attendance, $notes
        );

        return [xlt('Activity session logged.'), ''];
    }

    /** @return array{0:string,1:string} [flash, error] */
    private function handleUpdateAttendance(int $facilityId): array
    {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId <= 0) {
            return ['', xlt('Invalid session.')];
        }

        $session = $this->repo->getById($sessionId, $facilityId);
        if (!$session) {
            return ['', xlt('Session not found.')];
        }

        $existing = json_decode((string)($session['attendance_json'] ?? '{}'), true) ?? [];
        $attendance = [];
        foreach ($existing as $eid => $item) {
            $level = strtoupper(trim((string)($_POST['level_' . $eid] ?? $item['level'])));
            $note  = trim((string)($_POST['anote_' . $eid] ?? $item['note'] ?? ''));
            $attendance[$eid] = ['level' => $level, 'note' => $note];
        }
        $notes = trim((string)($_POST['session_notes'] ?? '')) ?: null;

        $this->repo->updateAttendance($sessionId, $facilityId, $attendance, $notes);

        return [xlt('Attendance updated.'), ''];
    }
}

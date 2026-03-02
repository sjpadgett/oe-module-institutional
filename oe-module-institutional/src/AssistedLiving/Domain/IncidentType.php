<?php

declare(strict_types=1);

namespace OpenEMR\Modules\Institutional\AssistedLiving\Domain;

/**
 * IncidentType — AL incident classification for state reporting.
 *
 * Most state AL licensing regulations require incident reports within
 * 24–72 hours for the types below. Codes are stored in oei_incident.type.
 * The SEVERITY constants map to the oei_incident.severity column.
 */
final class IncidentType
{
    // ── Incident types ────────────────────────────────────────────────────
    public const FALL             = 'FALL';
    public const FALL_WITH_INJURY = 'FALL_INJURY';
    public const ELOPEMENT        = 'ELOPEMENT';
    public const MED_ERROR        = 'MED_ERROR';
    public const ALTERCATION      = 'ALTERCATION';
    public const ABUSE_NEGLECT    = 'ABUSE_NEGLECT';
    public const HOSPITALIZATION  = 'HOSPITALIZATION';
    public const DEATH            = 'DEATH';
    public const EQUIPMENT        = 'EQUIPMENT';
    public const OTHER            = 'OTHER';

    // ── Severity tiers ────────────────────────────────────────────────────
    public const SEV_LOW      = 'LOW';
    public const SEV_MODERATE = 'MODERATE';
    public const SEV_HIGH     = 'HIGH';
    public const SEV_CRITICAL = 'CRITICAL';

    private const TYPE_LABELS = [
        self::FALL             => 'Fall (no injury)',
        self::FALL_WITH_INJURY => 'Fall with Injury',
        self::ELOPEMENT        => 'Elopement',
        self::MED_ERROR        => 'Medication Error',
        self::ALTERCATION      => 'Altercation',
        self::ABUSE_NEGLECT    => 'Abuse / Neglect',
        self::HOSPITALIZATION  => 'Hospitalization',
        self::DEATH            => 'Death',
        self::EQUIPMENT        => 'Equipment Failure',
        self::OTHER            => 'Other',
    ];

    /** Types that require mandatory state reporting within 24 hours. */
    private const MANDATORY_REPORT = [
        self::FALL_WITH_INJURY,
        self::ELOPEMENT,
        self::ABUSE_NEGLECT,
        self::DEATH,
    ];

    public static function label(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Unknown';
    }

    public static function all(): array
    {
        return array_keys(self::TYPE_LABELS);
    }

    public static function requiresMandatoryReport(string $type): bool
    {
        return in_array($type, self::MANDATORY_REPORT, true);
    }
}

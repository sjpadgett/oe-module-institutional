<?php

namespace OpenEMR\Modules\Institutional\Operations\Submodule\Settings\Repository;

/**
 * SettingsRepository v2.1 — adds facility_name key.
 *
 * facility_name: display name for the facility on dashboards.
 *   When set, takes priority over the OpenEMR facility table name.
 *   Leave blank to fall back to OpenEMR facility name or "Facility N".
 *   This makes the module fully self-contained for multi-facility identity.
 */
final class SettingsRepository
{
    public static function defaults(): array
    {
        return [
            // Identity — our own table, no OpenEMR dependency
            'facility_name'               => '',

            // Clinical thresholds
            'facility_default_id'         => '1',
            'door_to_room_target_min'     => '30',
            'door_to_provider_target_min' => '60',
            'lwbs_threshold_min'          => '120',
            'obs_runway_warning_hours'    => '6',
            'boarding_alert_hours'        => '4',
            'esi_high_acuity_max'         => '2',

            // HL7 ADT outbound
            'hl7_enabled'                 => '0',
            'hl7_transport'               => 'MLLP',
            'hl7_mllp_host'               => '127.0.0.1',
            'hl7_mllp_port'               => '2575',
            'hl7_http_url'                => '',
            'hl7_http_bearer'             => '',
            'hl7_sending_app'             => 'OE-INSTITUTIONAL',
            'hl7_sending_facility'        => 'OPENEMR',
            'hl7_receiving_app'           => '',
            'hl7_receiving_facility'      => '',
            'hl7_processing_id'           => 'T',

            // Triage standard (requires mts_triage feature flag)
            // Values: ESI | MTS | CTAS
            'triage_standard'             => 'ESI',

            // UI appearance
            // Values: light | dark
            'ui_theme'                    => 'light',
        ];
    }

    public function get(int $facilityId, string $key): string
    {
        if (!function_exists('sqlQuery')) {
            return (string)(self::defaults()[$key] ?? '');
        }
        $row = sqlQuery(
            "SELECT setting_value FROM oei_settings WHERE facility_id=? AND setting_key=? LIMIT 1",
            [$facilityId, $key]
        );
        return $row ? (string)$row['setting_value'] : (string)(self::defaults()[$key] ?? '');
    }

    public function set(int $facilityId, string $key, string $value, ?int $userId = null): void
    {
        if (!function_exists('sqlStatement')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_settings (facility_id, setting_key, setting_value, updated_by_user_id, updated_datetime)
             VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               setting_value=VALUES(setting_value),
               updated_by_user_id=VALUES(updated_by_user_id),
               updated_datetime=VALUES(updated_datetime)",
            [$facilityId, $key, $value, $userId, $now]
        );
    }

    /** @param array<string,string> $values */
    public function setMany(int $facilityId, array $values, ?int $userId = null): void
    {
        foreach ($values as $k => $v) {
            $this->set($facilityId, $k, $v, $userId);
        }
    }

    /** @return array<string,string> */
    public function all(int $facilityId): array
    {
        $result = self::defaults();
        if (!function_exists('sqlStatement')) {
            return $result;
        }
        $res = sqlStatement(
            "SELECT setting_key, setting_value FROM oei_settings WHERE facility_id=?",
            [$facilityId]
        );
        while ($row = sqlFetchArray($res)) {
            $result[(string)$row['setting_key']] = (string)$row['setting_value'];
        }
        return $result;
    }
}

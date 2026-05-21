<?php

/**
 * src/ObservationStay/Submodule/ObsProtocols/Repository/ProtocolRepository.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\ObservationStay\Submodule\ObsProtocols\Repository;

final class ProtocolRepository
{
    private static array $defaultProtocols = [
        'GENERAL_OBS' => [
            'label'   => 'General Observation',
            'version' => '1',
            'definition' => [
                'target_hours' => 24, 'runway_hours' => 6,
                'milestones' => [
                    ['label'=>'Reassess 2h','type'=>'REASSESS_Q2H','at_minutes'=>120],
                    ['label'=>'Reassess 4h','type'=>'REASSESS_Q2H','at_minutes'=>240],
                ],
                'tasks' => [
                    ['type'=>'VITALS_Q4H','every_minutes'=>240],
                    ['type'=>'REASSESS_Q2H','every_minutes'=>120],
                ],
            ],
        ],
        'CHEST_PAIN' => [
            'label'   => 'Chest Pain Observation',
            'version' => '1',
            'definition' => [
                'target_hours' => 24, 'runway_hours' => 6,
                'milestones' => [
                    ['label'=>'Troponin #2','type'=>'TROPONIN','at_minutes'=>180],
                    ['label'=>'Troponin #3','type'=>'TROPONIN','at_minutes'=>360],
                ],
                'tasks' => [
                    ['type'=>'VITALS_Q4H','every_minutes'=>240],
                    ['type'=>'REASSESS_Q2H','every_minutes'=>120],
                    ['type'=>'TROPONIN','at_minutes'=>[0,180,360]],
                ],
            ],
        ],
    ];

    public function ensureDefaultProtocols(int $facilityId, ?int $userId): void
    {
        if (!function_exists('sqlQuery')) return;
        foreach (self::$defaultProtocols as $key => $p) {
            $existing = sqlQuery(
                "SELECT id FROM oei_protocol WHERE facility_id=? AND protocol_key=? LIMIT 1",
                [$facilityId, $key]
            );
            if (!$existing) {
                $this->upsert($facilityId, $key, $p['label'], $p['version'], 1,
                    json_encode($p['definition'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), $userId);
            }
        }
    }

    public function upsert(
        int $facilityId, string $key, string $label, string $version,
        int $enabled, string $definitionJson, ?int $userId
    ): void {
        if (!function_exists('sqlStatement')) return;
        $now = date('Y-m-d H:i:s');
        sqlStatement(
            "INSERT INTO oei_protocol (facility_id,protocol_key,label,version,enabled,definition_json,updated_by_user_id,updated_datetime)
             VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               label=VALUES(label),version=VALUES(version),enabled=VALUES(enabled),
               definition_json=VALUES(definition_json),updated_by_user_id=VALUES(updated_by_user_id),
               updated_datetime=VALUES(updated_datetime)",
            [$facilityId,$key,$label,$version,$enabled,$definitionJson,$userId,$now]
        );
    }

    /** @return array<string,mixed>|null */
    public function get(int $facilityId, string $key): ?array
    {
        if (!function_exists('sqlQuery')) return null;
        $row = sqlQuery(
            "SELECT * FROM oei_protocol WHERE facility_id=? AND protocol_key=? LIMIT 1",
            [$facilityId, $key]
        );
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listEnabled(int $facilityId): array
    {
        if (!function_exists('sqlStatement')) return [];
        $res = sqlStatement(
            "SELECT protocol_key, label, version, updated_datetime FROM oei_protocol
             WHERE facility_id=? AND enabled=1 ORDER BY label ASC",
            [$facilityId]
        );
        $rows = [];
        while ($row = sqlFetchArray($res)) $rows[] = $row;
        return $rows;
    }
}




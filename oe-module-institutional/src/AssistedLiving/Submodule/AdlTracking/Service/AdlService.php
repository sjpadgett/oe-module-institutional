<?php

/**
 * src/AssistedLiving/Submodule/AdlTracking/Service/AdlService.php
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
namespace OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Service;

use OpenEMR\Modules\Institutional\AssistedLiving\Domain\AdlLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Domain\CareLevel;
use OpenEMR\Modules\Institutional\AssistedLiving\Submodule\AdlTracking\Repository\AdlRepository;

final class AdlService
{
    public function __construct(private readonly AdlRepository $repo) {}

    public function history(int $episodeId): array
    {
        $records = $this->repo->listByEpisode($episodeId);
        foreach ($records as &$r) {
            $r['care_level']       = CareLevel::fromAdlScore($r['adl_score']);
            $r['care_level_label'] = CareLevel::label($r['care_level']);
            $r['care_level_badge'] = CareLevel::badge($r['care_level']);
            $r['domain_labels']    = [];
            foreach ($r['domain_levels'] as $domain => $level) {
                $r['domain_labels'][$domain] = AdlLevel::label((int)$level);
            }
        }
        unset($r);
        return $records;
    }

    public function chart(int $episodeId, int $facilityId, int $userId, array $domainLevels, string $notes): int
    {
        return $this->repo->chart($episodeId, $facilityId, $userId, $domainLevels, $notes);
    }

    public function domains(): array { return AdlLevel::DOMAINS; }
    public function levels(): array  { return AdlLevel::validLevels(); }
    public function levelLabel(int $l): string { return AdlLevel::label($l); }
}




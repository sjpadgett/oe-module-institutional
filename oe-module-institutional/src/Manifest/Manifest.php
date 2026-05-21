<?php

/**
 * src/Manifest/Manifest.php
 *
 * Part of the oe-module-institutional module.
 *
 * @package   Institutional
 * @link      https://www.opensourcedemr.com
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2026 Jerry Padgett <sjpadgett@gmail.com>
 * @license   GNU General Public License 3
 */

namespace OpenEMR\Modules\Institutional\Manifest;

final class Manifest
{
    /**
     * @param array<string,bool> $features
     * @param array<string,mixed> $ui
     * @param string[] $migrations
     * @param array<string,mixed> $menus
     */
    public function __construct(
        public readonly string $moduleId,
        public readonly array $features,
        public readonly array $ui,
        public readonly array $migrations,
        public readonly array $menus
    ) {}

    public function featureEnabled(string $name): bool
    {
        return (bool)($this->features[$name] ?? false);
    }

    public function menusTopLabel(): string
    {
        return (string)($this->menus['top_label'] ?? 'Institutional');
    }

    /**
     * @return array<int,array{label:string,url:string,menu_id:string}>
     */
    public function menuItemsEnabled(): array
    {
        $items = $this->menus['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $out = [];
        foreach ($items as $i) {
            if (!is_array($i)) {
                continue;
            }

            $feature = (string)($i['feature'] ?? '');
            if ($feature !== '' && !$this->featureEnabled($feature)) {
                continue;
            }

            $label = (string)($i['label'] ?? '');
            $url   = (string)($i['url'] ?? '');
            if ($label === '' || $url === '') {
                continue;
            }

            $menuId = (string)($i['menu_id'] ?? preg_replace('/[^a-z0-9_]+/i', '_', $feature ?: $label));
            $out[] = ['label' => $label, 'url' => $url, 'menu_id' => $menuId,
                      'group' => (string)($i['group'] ?? ''), 'sort' => (int)($i['sort'] ?? 0),
                      'feature' => $feature];
        }

        return $out;
    }
}







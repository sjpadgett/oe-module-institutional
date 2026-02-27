<?php

namespace OpenEMR\Modules\Institutional\Manifest;

final class ManifestLoader
{
    public static function load(string $moduleRoot): Manifest
    {
        $path = rtrim($moduleRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'manifest.json';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException("Unable to read manifest.json at {$path}");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException("manifest.json is not valid JSON");
        }

        return new Manifest(
            (string)($data['module_id'] ?? 'institutional'),
            (array)($data['features'] ?? []),
            (array)($data['ui'] ?? []),
            array_values((array)($data['migrations'] ?? [])),
            (array)($data['menus'] ?? [])
        );
    }
}

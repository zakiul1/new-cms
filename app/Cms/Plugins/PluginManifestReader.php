<?php

namespace App\Cms\Plugins;

use RuntimeException;

final class PluginManifestReader
{
    public function read(string $pluginDir): PluginManifest
    {
        $slug = basename($pluginDir);
        $path = $pluginDir . DIRECTORY_SEPARATOR . 'plugin.json';

        if (!is_file($path)) {
            throw new RuntimeException("plugin.json missing: {$path}");
        }

        $json = file_get_contents($path);
        $data = json_decode((string)$json, true);

        if (!is_array($data)) {
            throw new RuntimeException("Invalid plugin.json: {$path}");
        }

        return PluginManifest::fromArray($data, $slug);
    }
}

<?php

namespace App\Cms\Plugins;

class PluginSettingsSchema
{
    /**
     * @return array{group:string, fields:array<int,array<string,mixed>>}|null
     */
    public function schema(PluginManifest $manifest): ?array
    {
        $raw = is_array($manifest->raw ?? null) ? $manifest->raw : [];
        $settings = $raw['settings'] ?? null;

        if (!is_array($settings)) {
            return null;
        }

        $group = (string)($settings['group'] ?? ('plugin:' . $manifest->slug));
        $fields = $settings['fields'] ?? [];

        if (!is_array($fields) || $fields === []) {
            return null;
        }

        // normalize fields
        $norm = [];
        foreach ($fields as $f) {
            if (!is_array($f)) continue;

            $key = (string)($f['key'] ?? '');
            $type = (string)($f['type'] ?? 'text');

            if ($key === '') continue;

            $norm[] = [
                'key' => $key,
                'type' => $type,
                'label' => (string)($f['label'] ?? $key),
                'default' => $f['default'] ?? null,
                'options' => $f['options'] ?? null,
                'helper' => (string)($f['helper'] ?? ''),
                'required' => (bool)($f['required'] ?? false),
            ];
        }

        if ($norm === []) return null;

        return ['group' => $group, 'fields' => $norm];
    }
}

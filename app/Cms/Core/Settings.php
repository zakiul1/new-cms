<?php

namespace App\Cms\Core;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Settings
{
    private const CACHE_PREFIX = 'cms:settings:group:'; // + {group}
    private const TABLE_EXISTS_CACHE_KEY = 'cms:table_exists:cms_settings';

    /** @return array<string, mixed> */
    public function all(string $group = 'core'): array
    {
        if (!$this->settingsTableExists()) {
            return [];
        }

        return Cache::rememberForever($this->cacheKey($group), function () use ($group): array {
            $rows = DB::table('cms_settings')
                ->where('group', $group)
                ->get(['key', 'value']);

            $out = [];
            foreach ($rows as $row) {
                $out[(string) $row->key] = $this->decode($row->value);
            }

            return $out;
        });
    }

    public function get(string $key, mixed $default = null, string $group = 'core'): mixed
    {
        $all = $this->all($group);

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $val = $all[$key];

        // treat null / empty string as default
        if ($val === null || $val === '') {
            return $default;
        }

        return $val;
    }

    public function set(string $key, mixed $value, string $group = 'core'): void
    {
        if (!$this->settingsTableExists()) {
            return;
        }

        // ✅ JSON column: always write valid JSON
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            // last resort: store as JSON string
            $json = json_encode((string) $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        DB::table('cms_settings')->updateOrInsert(
            ['group' => $group, 'key' => $key],
            ['value' => $json]
        );

        Cache::forget($this->cacheKey($group));
    }

    public function forget(string $key, string $group = 'core'): void
    {
        if (!$this->settingsTableExists()) {
            return;
        }

        DB::table('cms_settings')
            ->where('group', $group)
            ->where('key', $key)
            ->delete();

        Cache::forget($this->cacheKey($group));
    }

    private function cacheKey(string $group): string
    {
        return self::CACHE_PREFIX . $group;
    }

    private function settingsTableExists(): bool
    {
        return Cache::remember(self::TABLE_EXISTS_CACHE_KEY, now()->addDay(), function () {
            return Schema::hasTable('cms_settings');
        });
    }

    private function decode(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        // DB may return native values depending on driver
        if (is_array($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        $trim = trim($value);
        if ($trim === '') {
            return '';
        }

        $decoded = json_decode($trim, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
}

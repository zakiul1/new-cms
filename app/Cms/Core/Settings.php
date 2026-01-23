<?php

namespace App\Cms\Core;

use App\Models\CmsSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Settings
{
    private const CACHE_KEY = 'cms:settings:all';
    private const TABLE_EXISTS_CACHE_KEY = 'cms:table_exists:cms_settings';

    /** @return array<string, mixed> */
    public function all(): array
    {
        // ✅ prevents crash + avoids repeated information_schema queries
        if (! $this->settingsTableExists()) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return CmsSetting::query()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (! array_key_exists($key, $all)) {
            return $default;
        }

        $value = $all[$key];

        if (! is_string($value)) {
            return $value;
        }

        $trim = trim($value);
        if ($trim === '') {
            return $default;
        }

        // JSON decode arrays/objects
        if (
            ($trim[0] === '[' && str_ends_with($trim, ']')) ||
            ($trim[0] === '{' && str_ends_with($trim, '}'))
        ) {
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        CmsSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value)]
        );

        Cache::forget(self::CACHE_KEY);
    }

    private function settingsTableExists(): bool
    {
        // cache for 1 day (safe + no need manual cache clear)
        return Cache::remember(self::TABLE_EXISTS_CACHE_KEY, now()->addDay(), function () {
            return Schema::hasTable('cms_settings');
        });
    }
}

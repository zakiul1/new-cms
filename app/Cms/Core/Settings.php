<?php

namespace App\Cms\Core;

use App\Models\CmsSetting;
use Illuminate\Support\Facades\Cache;

class Settings
{
    private const CACHE_KEY = 'cms:settings:all';

    /** @return array<string, mixed> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return CmsSetting::query()
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (!array_key_exists($key, $all))
            return $default;

        $value = $all[$key];

        if (!is_string($value))
            return $value;

        $trim = trim($value);
        if ($trim === '')
            return $default;

        // Try JSON decode for arrays/objects
        if (($trim[0] === '[' && str_ends_with($trim, ']')) || ($trim[0] === '{' && str_ends_with($trim, '}'))) {
            $decoded = json_decode($trim, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }


    public function set(string $key, mixed $value): void
    {
        CmsSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value)]
        );

        Cache::forget(self::CACHE_KEY);
    }
}
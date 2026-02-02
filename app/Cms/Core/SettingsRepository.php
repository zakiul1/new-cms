<?php

namespace App\Cms\Core;

use App\Models\CmsSetting;
use Illuminate\Support\Facades\Cache;

class SettingsRepository
{
    public function get(string $group, string $key, mixed $default = null): mixed
    {
        return Cache::remember(
            $this->cacheKey($group, $key),
            now()->addHour(),
            function () use ($group, $key, $default) {
                $row = CmsSetting::query()
                    ->where('group', $group)
                    ->where('key', $key)
                    ->first();

                if (!$row) {
                    return $default;
                }

                $value = $row->value;

                if ($value === null) {
                    return $default;
                }

                // ✅ If value is JSON string, decode (arrays/settings)
                if (is_string($value)) {
                    $trim = trim($value);

                    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                        $decoded = json_decode($trim, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            return $decoded;
                        }
                    }

                    // ✅ Normalize common boolean strings safely
                    $lower = strtolower($trim);
                    if ($lower === 'true')
                        return true;
                    if ($lower === 'false')
                        return false;
                    if ($trim === '1')
                        return true;
                    if ($trim === '0')
                        return false;
                }

                return $value;
            }
        );
    }

    public function set(string $group, string $key, mixed $value): void
    {
        $stored = $value;

        if (is_array($value) || is_object($value)) {
            $stored = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        CmsSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $stored]
        );

        Cache::forget($this->cacheKey($group, $key));
    }

    public function forget(string $group, string $key): void
    {
        CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->delete();

        Cache::forget($this->cacheKey($group, $key));
    }

    private function cacheKey(string $group, string $key): string
    {
        return "cms_settings.{$group}.{$key}";
    }
}
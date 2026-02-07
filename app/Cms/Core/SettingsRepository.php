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

                // ✅ value is already decoded because CmsSetting::$casts['value' => 'json']
                $value = $row->value;

                if ($value === null || $value === '') {
                    return $default;
                }

                // ✅ normalize common boolean-ish strings (only if it is string)
                if (is_string($value)) {
                    $trim = trim($value);
                    $lower = strtolower($trim);

                    if ($lower === 'true')
                        return true;
                    if ($lower === 'false')
                        return false;
                    if ($trim === '1')
                        return true;
                    if ($trim === '0')
                        return false;

                    return $trim;
                }

                return $value;
            }
        );
    }

    public function set(string $group, string $key, mixed $value): void
    {
        // ✅ DO NOT json_encode manually (Eloquent json cast handles it)
        CmsSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
        );

        // ✅ clear BOTH caches:
        // 1) per-key cache used by SettingsRepository
        Cache::forget($this->cacheKey($group, $key));

        // 2) per-group cache used by App\Cms\Core\Settings::all()
        Cache::forget($this->settingsGroupCacheKey($group));
    }

    public function forget(string $group, string $key): void
    {
        CmsSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->delete();

        Cache::forget($this->cacheKey($group, $key));
        Cache::forget($this->settingsGroupCacheKey($group));
    }

    private function cacheKey(string $group, string $key): string
    {
        return "cms_settings.{$group}.{$key}";
    }

    // ✅ MUST match Settings::CACHE_PREFIX + group ("cms:settings:group:" + group)
    private function settingsGroupCacheKey(string $group): string
    {
        return 'cms:settings:group:' . $group;
    }
}
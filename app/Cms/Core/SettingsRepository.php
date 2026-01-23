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

                return $row?->value ?? $default;
            }
        );
    }

    public function set(string $group, string $key, mixed $value): void
    {
        CmsSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value]
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
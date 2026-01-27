<?php

use App\Cms\Core\Settings;

if (!function_exists('plugin_settings_group')) {
    function plugin_settings_group(string $slug): string
    {
        return "plugin:{$slug}";
    }
}

if (!function_exists('plugin_setting')) {
    function plugin_setting(string $slug, string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default, plugin_settings_group($slug));
    }
}

if (!function_exists('set_plugin_setting')) {
    function set_plugin_setting(string $slug, string $key, mixed $value): void
    {
        app(Settings::class)->set($key, $value, plugin_settings_group($slug));
    }
}
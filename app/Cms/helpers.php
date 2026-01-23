<?php

use App\Cms\Assets\AssetManager;
use App\Cms\Hooks\Hooks;

if (!function_exists('cms_hooks')) {
    function cms_hooks(): Hooks
    {
        return app(Hooks::class);
    }
}

if (!function_exists('cms_assets')) {
    function cms_assets(): AssetManager
    {
        return app(AssetManager::class);
    }
}
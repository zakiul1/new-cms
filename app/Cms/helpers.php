<?php

use App\Cms\Assets\AssetManager;
use App\Cms\Hooks\Hooks;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Widgets\SidebarRenderer;

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

if (!function_exists('cms_menu')) {
    function cms_menu(string $locationKey, array $ctx = []): string
    {
        return app(MenuRenderer::class)->renderLocation($locationKey, $ctx);
    }
}

if (!function_exists('cms_sidebar')) {
    function cms_sidebar(string $areaKey, array $ctx = []): string
    {
        return app(SidebarRenderer::class)->render($areaKey, $ctx);
    }
}
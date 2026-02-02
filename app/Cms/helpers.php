<?php

use App\Cms\Assets\AssetManager;
use App\Cms\Content\PermalinkManager;
use App\Cms\Hooks\Hooks;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Widgets\SidebarRenderer;
use App\Models\Post;
use App\Models\Term;

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

// ✅ New URL helpers (WP-like)
if (!function_exists('cms_post_url')) {
    function cms_post_url(Post $post): string
    {
        return app(PermalinkManager::class)->postUrl($post);
    }
}

if (!function_exists('cms_page_url')) {
    function cms_page_url(Post $page): string
    {
        return app(PermalinkManager::class)->pageUrl($page);
    }
}

if (!function_exists('cms_term_url')) {
    function cms_term_url(Term $term): string
    {
        return app(PermalinkManager::class)->termUrl($term);
    }
}
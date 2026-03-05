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

/**
 * =========================================================
 * URL helpers (WP-like)
 * =========================================================
 *
 * These helpers are meant for FRONTEND/public links and will
 * follow your CMS standard (PermalinkManager::normalizePath),
 * which ALWAYS adds trailing "/" except root "/".
 *
 * Use:
 * - cms_post_url($post)
 * - cms_page_url($page)
 * - cms_term_url($term)
 * - cms_slug_url('any/custom/path')  <-- for plugins/multipage/custom types
 */

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

/**
 * Generic helper for anything that is NOT a Post/Page/Term model:
 * - custom post types where you only have a slug
 * - plugin-generated pages
 * - multipage generated links (stored as "/posts-1", etc.)
 *
 * Examples:
 *  cms_slug_url('about')          => http://cms.test/about/
 *  cms_slug_url('/about')         => http://cms.test/about/
 *  cms_slug_url('category/x')     => http://cms.test/category/x/
 *  cms_slug_url('/posts-1')       => http://cms.test/posts-1/
 *  cms_slug_url('/')              => http://cms.test/
 */
if (!function_exists('cms_slug_url')) {
    function cms_slug_url(string $slugOrPath): string
    {
        return app(PermalinkManager::class)->slugUrl($slugOrPath);
    }
}
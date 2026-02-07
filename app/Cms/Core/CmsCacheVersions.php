<?php

namespace App\Cms\Core;

use Illuminate\Support\Facades\Cache;

class CmsCacheVersions
{
    protected function key(string $group, string $id): string
    {
        return "cms:ver:{$group}:{$id}";
    }

    public function get(string $group, string $id): int
    {
        return (int) Cache::get($this->key($group, $id), 1);
    }

    public function bump(string $group, string $id): int
    {
        $k = $this->key($group, $id);
        $current = (int) Cache::get($k, 1);
        $new = $current + 1;

        Cache::forever($k, $new);

        return $new;
    }

    /**
     * Global render version:
     * bump this when theme/plugins/assets or settings that affect frontend HTML output changes.
     */
    public function renderVersion(): int
    {
        return $this->get('render', 'global');
    }

    public function bumpRender(): int
    {
        $v = $this->bump('render', 'global');

        // ✅ Clear cached outputs that depend on frontend render/settings
        $this->forgetFrontendCaches();

        return $v;
    }

    /**
     * Sitemap version (optional, but clean)
     */
    public function sitemapVersion(): int
    {
        return $this->get('sitemap', 'xml');
    }

    public function bumpSitemap(): int
    {
        $v = $this->bump('sitemap', 'xml');

        // If you cache sitemap XML directly, clear it too
        Cache::forget('cms:sitemap:xml:v3');
        Cache::forget('cms:sitemap:xml:v2');

        return $v;
    }

    /**
     * ✅ Backward compatible alias.
     * Your error shows something is calling getRender().
     */
    public function getRender(): int
    {
        return $this->renderVersion();
    }

    /**
     * Central place to clear known frontend caches.
     */
    protected function forgetFrontendCaches(): void
    {
        // Sitemap caches (current + previous)
        Cache::forget('cms:sitemap:xml:v3');
        Cache::forget('cms:sitemap:xml:v2');

        // If you cache robots.txt later, include it here
        Cache::forget('cms:robots:txt:v1');

        // Add more keys here if you introduce them later (menus, widgets, etc.)
    }
}
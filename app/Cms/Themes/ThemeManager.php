<?php

namespace App\Cms\Themes;

use App\Cms\Core\Settings;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use RuntimeException;

class ThemeManager
{
    public function __construct(
        private Settings $settings,
        private ThemeManifestReader $reader,
        private ThemePublisher $publisher,
        private CacheRepository $cache,
    ) {
    }

    /**
     * Return themes keyed by slug (internal use).
     *
     * @return array<string, ThemeManifest> slug => manifest
     */
    public function all(): array
    {
        $themes = $this->discoverKeyed();
        $out = [];

        foreach ($themes as $slug => $t) {
            /** @var ThemeManifest $manifest */
            $manifest = $t['manifest'];
            $out[$slug] = $manifest;
        }

        return $out;
    }

    /**
     * Internal discovery (returns ThemeManifest DTO objects).
     *
     * @return array<string, array{slug:string, path:string, manifest:ThemeManifest}> keyed by slug
     */
    public function discoverKeyed(): array
    {
        return $this->cache->remember('cms.themes.discovered', 3600, function () {
            $base = (string) config('cms.themes_path');

            if (!is_dir($base)) {
                return [];
            }

            $themes = [];

            foreach (File::directories($base) as $dir) {
                try {
                    $manifest = $this->reader->read($dir); // ThemeManifest object
                    $slug = $manifest->slug;

                    $themes[$slug] = [
                        'slug' => $slug,
                        'path' => $dir,
                        'manifest' => $manifest,
                    ];
                } catch (\Throwable $e) {
                    logger()->warning('Theme skipped', [
                        'dir' => $dir,
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return $themes;
        });
    }

    /**
     * Livewire-safe themes list (manifest converted to array).
     *
     * @return array<string, array{slug:string, path:string, manifest:array}>
     */
    public function discoverForUi(): array
    {
        $themes = $this->discoverKeyed();
        $out = [];

        foreach ($themes as $slug => $t) {
            /** @var ThemeManifest $m */
            $m = $t['manifest'];

            $out[$slug] = [
                'slug' => $slug,
                'path' => $t['path'],
                'manifest' => [
                    'name' => $m->name,
                    'slug' => $m->slug,
                    'version' => $m->version,
                    'author' => $m->author,
                    'description' => $m->description,
                    'parent' => $m->parent,
                    'templates' => $m->templates,
                    'menus' => $m->menus,
                    'sidebars' => $m->sidebars,
                    'assets' => $m->assets,

                    // premium UI
                    'screenshot' => $m->raw['screenshot'] ?? null,
                    'latest_version' => $m->raw['latest_version'] ?? null,
                ],
            ];
        }

        return $out;
    }

    public function forgetDiscoveryCache(): void
    {
        $this->cache->forget('cms.themes.discovered');
    }

    public function activeSlug(): string
    {
        $slug = (string) $this->settings->get('active_theme', 'default');
        return $slug !== '' ? $slug : 'default';
    }

    /**
     * Ensure active theme exists, otherwise fallback to default / first available.
     */
    public function ensureActiveThemeValid(): string
    {
        $themes = $this->discoverKeyed();
        $active = $this->activeSlug();

        if (isset($themes[$active])) {
            return $active;
        }

        if (isset($themes['default'])) {
            $this->settings->set('active_theme', 'default');
            return 'default';
        }

        if (!empty($themes)) {
            $first = array_key_first($themes);
            $this->settings->set('active_theme', $first);
            return $first;
        }

        throw new RuntimeException('No valid themes found in /themes.');
    }

    /**
     * Decide which theme should be booted for THIS request.
     * - normal: active theme
     * - customizer preview: ?preview_theme=slug (only for logged-in users)
     */
    private function getBootSlugForRequest(): ?string
    {
        $themes = $this->discoverKeyed();
        if (empty($themes)) {
            return null;
        }

        // base slug = active (validated fallback)
        try {
            $slug = $this->ensureActiveThemeValid();
        } catch (\Throwable) {
            $slug = array_key_first($themes);
        }

        // allow preview theme in customizer
        $preview = (string) request()->query('preview_theme', '');
        if ($preview !== '' && auth()->check() && isset($themes[$preview])) {
            $slug = $preview;
        }

        return $slug;
    }

    /**
     * Enqueue active (or preview) theme assets from theme.json assets.
     * Safe: never crashes.
     */
    public function enqueueActiveThemeAssets(): void
    {
        try {
            $themes = $this->discoverKeyed();
            if (empty($themes)) {
                return;
            }

            $slug = $this->getBootSlugForRequest();
            if (!$slug || !isset($themes[$slug])) {
                return;
            }

            /** @var ThemeManifest $m */
            $m = $themes[$slug]['manifest'];

            $assets = $m->assets ?? [];
            $styles = $assets['styles'] ?? [];
            $scripts = $assets['scripts'] ?? [];

            foreach ($styles as $i => $rel) {
                if (!is_string($rel) || $rel === '') {
                    continue;
                }

                cms_assets()->enqueueStyle(
                    "theme:{$slug}:style:{$i}",
                    asset("themes/{$slug}/" . ltrim($rel, '/')),
                    [],
                    'frontend'
                );
            }

            foreach ($scripts as $i => $item) {
                $src = '';
                $attrs = [];

                if (is_string($item)) {
                    $src = $item;
                } elseif (is_array($item)) {
                    $src = (string) ($item['src'] ?? '');

                    foreach (['defer', 'async', 'type'] as $key) {
                        if (!array_key_exists($key, $item)) {
                            continue;
                        }

                        if (is_bool($item[$key]) && $item[$key] === true) {
                            $attrs[$key] = $key; // defer="defer"
                        } elseif (is_string($item[$key]) && $item[$key] !== '') {
                            $attrs[$key] = $item[$key];
                        }
                    }
                }

                if ($src === '') {
                    continue;
                }

                cms_assets()->enqueueScript(
                    "theme:{$slug}:script:{$i}",
                    asset("themes/{$slug}/" . ltrim($src, '/')),
                    $attrs,
                    'frontend'
                );
            }
        } catch (\Throwable $e) {
            logger()->warning('enqueueActiveThemeAssets failed', ['error' => $e->getMessage()]);
            return;
        }
    }

    public function activate(string $slug): void
    {
        $themes = $this->discoverKeyed();

        if (!isset($themes[$slug])) {
            throw new RuntimeException("Theme not found: {$slug}");
        }

        $this->settings->set('active_theme', $slug);

        // publish assets when activated
        $this->publisher->publish($slug);

        // clear discovery cache so UI shows latest info
        $this->forgetDiscoveryCache();
    }

    /**
     * Boot the active theme (or preview theme for customizer).
     */
    public function bootActiveTheme(): void
    {
        $themes = $this->discoverKeyed();
        if (empty($themes)) {
            logger()->warning('No themes discovered.');
            return;
        }

        $slug = $this->getBootSlugForRequest();
        if (!$slug || !isset($themes[$slug])) {
            logger()->warning('Theme slug missing/unavailable.');
            return;
        }

        $themesBase = rtrim((string) config('cms.themes_path'), DIRECTORY_SEPARATOR);
        $themeViews = $themesBase . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'views';

        if (is_dir($themeViews)) {
            View::addLocation($themeViews);
            View::addNamespace('theme', $themeViews);
        }

        // publish dist if missing
        $publicBase = rtrim((string) config('cms.themes_public_path'), DIRECTORY_SEPARATOR);
        $publicDist = $publicBase . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'dist';

        if (!is_dir($publicDist)) {
            $this->publisher->publish($slug);
        }
    }
}
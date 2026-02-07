<?php

namespace App\Cms\Plugins;

use App\Cms\Core\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PluginManager
{
    public function __construct(
        private readonly Settings $settings,
        private readonly PluginManifestReader $reader,
        private readonly PluginPublisher $publisher,
    ) {}

    /** @return array<string, PluginManifest> keyed by slug */
    public function all(): array
    {
        $out = [];
        foreach ($this->discover() as $item) {
            $out[$item['slug']] = $item['manifest'];
        }
        return $out;
    }

    public function manifest(string $slug): ?PluginManifest
    {
        $all = $this->all();
        return $all[$slug] ?? null;
    }

    /** @return array<int, array{slug:string, path:string, manifest:PluginManifest}> */
    public function discover(): array
    {
        $base = base_path('plugins');
        if (!is_dir($base)) {
            return [];
        }

        $plugins = [];
        foreach (File::directories($base) as $dir) {
            try {
                $manifest = $this->reader->read($dir);
                $plugins[] = [
                    'slug' => $manifest->slug,
                    'path' => $dir,
                    'manifest' => $manifest,
                ];
            } catch (Throwable $e) {
                Log::warning('Plugin skipped', ['dir' => $dir, 'error' => $e->getMessage()]);
            }
        }

        return $plugins;
    }

    /** @return array<int, string> */
    public function enabledSlugs(): array
    {
        $value = $this->settings->get('enabled_plugins', [], 'core');

        return is_array($value)
            ? array_values(array_unique(array_map('strval', $value)))
            : [];
    }

    /**
     * Check if plugin can be enabled.
     *
     * @return array{0:bool,1:string} [ok, reason]
     */
    public function canEnable(string $slug): array
    {
        $m = $this->manifest($slug);
        if (!$m) {
            return [false, "Plugin not found: {$slug}"];
        }

        // Read from raw manifest (works even if DTO doesn't have these as properties)
        $raw = is_array($m->raw ?? null) ? $m->raw : [];

        // Requires (PHP)
        $requires = is_array($raw['requires'] ?? null) ? $raw['requires'] : [];
        $phpReq = (string) ($requires['php'] ?? '');
        if ($phpReq !== '') {
            // supports ">=8.2"
            $min = trim(str_replace(['>=', ' '], '', $phpReq));
            if ($min !== '' && version_compare(PHP_VERSION, $min, '<')) {
                return [false, "Requires PHP {$phpReq}"];
            }
        }

        // Depends
        $depends = $raw['depends'] ?? [];
        if (is_array($depends) && $depends !== []) {
            $enabled = $this->enabledSlugs();
            foreach ($depends as $dep) {
                $dep = (string) $dep;
                if ($dep !== '' && !in_array($dep, $enabled, true)) {
                    return [false, "Requires plugin: {$dep}"];
                }
            }
        }

        return [true, 'OK'];
    }

    public function bootEnabledPlugins(): void
    {
        $enabled = $this->enabledSlugs();
        if ($enabled === []) {
            return;
        }

        $discovered = $this->discover();

        foreach ($discovered as $plugin) {
            if (!in_array($plugin['slug'], $enabled, true)) {
                continue;
            }

            /** @var PluginManifest $m */
            $m = $plugin['manifest'];
            $slug = (string) $m->slug;

            $bootstrapPath = $plugin['path'] . DIRECTORY_SEPARATOR . ($m->bootstrap ?: 'bootstrap.php');

            if (!is_file($bootstrapPath)) {
                Log::warning('Plugin bootstrap missing', ['plugin' => $slug, 'path' => $bootstrapPath]);
                continue;
            }

            // ✅ Publish dist -> public if missing (non-fatal)
            try {
                $this->publisher->publishIfMissing($slug);
            } catch (Throwable $e) {
                Log::warning('Plugin asset publish failed', [
                    'plugin' => $slug,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                require_once $bootstrapPath;

                // clear last error if it now boots successfully
                $this->settings->forget("plugins.{$slug}.last_error", 'core');
            } catch (Throwable $e) {
                Log::error('Plugin boot failed', [
                    'plugin' => $slug,
                    'error' => $e->getMessage(),
                ]);

                // store last error for UI/debug
                $this->settings->set("plugins.{$slug}.last_error", $e->getMessage(), 'core');

                continue;
            }
        }
    }

    public function enable(string $slug): void
    {
        [$ok, $reason] = $this->canEnable($slug);
        if (!$ok) {
            throw new RuntimeException($reason);
        }

        // ensure plugin exists
        if (!$this->manifest($slug)) {
            throw new RuntimeException("Plugin not found: {$slug}");
        }

        $enabled = $this->enabledSlugs();

        if (!in_array($slug, $enabled, true)) {
            // lifecycle first (so if activate fails, we don't persist enabled state)
            try {
                app(PluginLifecycle::class)->activate($slug);
            } catch (Throwable $e) {
                $this->settings->set("plugins.{$slug}.last_error", $e->getMessage(), 'core');
                throw $e;
            }

            $enabled[] = $slug;
            $this->settings->set('enabled_plugins', $enabled, 'core');
        }
    }

    public function disable(string $slug): void
    {
        $enabled = $this->enabledSlugs();

        if (!in_array($slug, $enabled, true)) {
            return;
        }

        // lifecycle (do not block disabling)
        try {
            app(PluginLifecycle::class)->deactivate($slug);
        } catch (Throwable $e) {
            $this->settings->set("plugins.{$slug}.last_error", $e->getMessage(), 'core');
        }

        $enabled = array_values(array_filter($enabled, fn ($s) => $s !== $slug));
        $this->settings->set('enabled_plugins', $enabled, 'core');
    }

    /** Enqueue assets defined in plugin.json assets{} */
    public function enqueuePluginAssets(string $slug, string $group = 'frontend'): void
    {
        $m = $this->manifest($slug);
        if (!$m) {
            return;
        }

        // ✅ Ensure published before generating asset URLs
        try {
            $this->publisher->publishIfMissing($slug);
        } catch (Throwable $e) {
            Log::warning('Plugin asset publish failed (enqueue)', [
                'plugin' => $slug,
                'error' => $e->getMessage(),
            ]);
        }

        $assets = $m->assets ?? [];

        $styles = $assets['styles'] ?? [];
        foreach ($styles as $i => $rel) {
            if (!is_string($rel) || $rel === '') {
                continue;
            }

            cms_assets()->enqueueStyle(
                "plugin:{$slug}:style:{$i}",
                asset("plugins/{$slug}/" . ltrim($rel, '/')),
                [],
                $group
            );
        }

        $scripts = $assets['scripts'] ?? [];
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
                        $attrs[$key] = $key;
                    } elseif (is_string($item[$key]) && $item[$key] !== '') {
                        $attrs[$key] = $item[$key];
                    }
                }
            }

            if ($src === '') {
                continue;
            }

            cms_assets()->enqueueScript(
                "plugin:{$slug}:script:{$i}",
                asset("plugins/{$slug}/" . ltrim($src, '/')),
                $attrs,
                $group
            );
        }
    }
}

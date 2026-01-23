<?php

namespace App\Cms\Plugins;

use App\Cms\Core\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class PluginManager
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * Return plugins keyed by slug (best for UI dropdowns / checkbox lists).
     *
     * @return array<string, array> keyed by slug
     */
    public function all(): array
    {
        $items = $this->discover();

        $out = [];
        foreach ($items as $p) {
            $out[$p['slug']] = $p['manifest'];
        }

        return $out;
    }

    /** @return array<int, array{slug:string, path:string, manifest:array}> */
    public function discover(): array
    {
        $base = base_path('plugins');
        if (!is_dir($base)) {
            return [];
        }

        $plugins = [];
        foreach (File::directories($base) as $dir) {
            $slug = basename($dir);
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'plugin.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            // Ensure manifest always contains slug
            $manifest['slug'] = $manifest['slug'] ?? $slug;

            $plugins[] = ['slug' => $slug, 'path' => $dir, 'manifest' => $manifest];
        }

        return $plugins;
    }

    /** @return array<int, string> */
    public function enabledSlugs(): array
    {
        $value = $this->settings->get('enabled_plugins', []);

        if (is_array($value)) {
            return array_values(array_unique(array_map('strval', $value)));
        }

        return [];
    }

    public function bootEnabledPlugins(): void
    {
        $enabled = $this->enabledSlugs();
        if ($enabled === []) {
            return;
        }

        $all = $this->discover();

        foreach ($all as $plugin) {
            if (!in_array($plugin['slug'], $enabled, true)) {
                continue;
            }

            $bootstrap = $plugin['manifest']['bootstrap'] ?? 'bootstrap.php';
            $bootstrapPath = $plugin['path'] . DIRECTORY_SEPARATOR . $bootstrap;

            if (!is_file($bootstrapPath)) {
                Log::warning('Plugin bootstrap missing', [
                    'plugin' => $plugin['slug'],
                    'path' => $bootstrapPath,
                ]);
                continue;
            }

            try {
                require_once $bootstrapPath;
            } catch (Throwable $e) {
                Log::error('Plugin boot failed', [
                    'plugin' => $plugin['slug'],
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }
    }

    public function enable(string $slug): void
    {
        $enabled = $this->enabledSlugs();

        if (!in_array($slug, $enabled, true)) {
            $enabled[] = $slug;
            $this->settings->set('enabled_plugins', $enabled);
        }
    }

    public function disable(string $slug): void
    {
        $enabled = array_values(array_filter(
            $this->enabledSlugs(),
            fn($s) => $s !== $slug
        ));

        $this->settings->set('enabled_plugins', $enabled);
    }
}
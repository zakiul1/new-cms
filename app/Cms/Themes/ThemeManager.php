<?php

namespace App\Cms\Themes;

use App\Cms\Core\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class ThemeManager
{
    public function __construct(private Settings $settings)
    {
    }

    /**
     * Return themes keyed by slug (best for Filament select).
     *
     * @return array<string, array> keyed by slug
     */
    public function all(): array
    {
        $items = $this->discover();

        $out = [];
        foreach ($items as $t) {
            $out[$t['slug']] = $t['manifest'];
        }

        return $out;
    }

    /** @return array<int, array{slug:string, path:string, manifest:array}> */
    public function discover(): array
    {
        $base = base_path('themes');
        if (!is_dir($base)) {
            return [];
        }

        $themes = [];
        foreach (File::directories($base) as $dir) {
            $slug = basename($dir);
            $manifestPath = $dir . DIRECTORY_SEPARATOR . 'theme.json';
            if (!is_file($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) file_get_contents($manifestPath), true);
            if (!is_array($manifest)) {
                continue;
            }

            // Ensure manifest always contains slug
            $manifest['slug'] = $manifest['slug'] ?? $slug;

            $themes[] = ['slug' => $slug, 'path' => $dir, 'manifest' => $manifest];
        }

        return $themes;
    }

    public function activeSlug(): string
    {
        return (string) $this->settings->get('active_theme', 'default');
    }

    public function activate(string $slug): void
    {
        $this->settings->set('active_theme', $slug);
    }

    public function bootActiveTheme(): void
    {
        $slug = $this->activeSlug();
        $themeViews = base_path("themes/{$slug}/views");

        if (is_dir($themeViews)) {
            View::addLocation($themeViews);
            View::addNamespace('theme', $themeViews);
        }
    }
}
<?php

use App\Cms\Hooks\HookPoints;
use App\Cms\Hooks\Hooks;
use Filament\Panel;
use Livewire\Livewire;

// 1) Load PHP classes (no composer autoload for plugins)
require_once base_path('plugins/siatex/SiatexServiceProvider.php');
require_once base_path('plugins/siatex/src/Filament/Pages/SiatexSliders.php');
require_once base_path('plugins/siatex/src/Livewire/SiatexSliderManager.php');
require_once base_path('plugins/siatex/helpers.php');

// 2) Register provider (views namespace)
app()->register(\Plugins\siatex\SiatexServiceProvider::class);

// 3) Register Livewire component
// ✅ IMPORTANT: must match the Blade tag <livewire:siatex-slider-manager />
Livewire::component('siatex-slider-manager', \Plugins\siatex\Livewire\SiatexSliderManager::class);


// 4) Register Filament page in admin panel via hook (Appearance → Siatex Sliders)
app(Hooks::class)->addAction(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->pages([
        \Plugins\siatex\Filament\Pages\SiatexSliders::class,
    ]);
});

/**
 * Helper: recursive copy directory (overwrite).
 */
if (!function_exists('siatex_copy_dir')) {
    function siatex_copy_dir(string $from, string $to): void
    {
        if (!is_dir($from)) {
            return;
        }

        if (!is_dir($to)) {
            @mkdir($to, 0755, true);
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $targetPath = $to . DIRECTORY_SEPARATOR . $it->getSubPathName();

            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    @mkdir($targetPath, 0755, true);
                }
            } else {
                $parent = dirname($targetPath);
                if (!is_dir($parent)) {
                    @mkdir($parent, 0755, true);
                }
                @copy($file->getPathname(), $targetPath);
            }
        }
    }
}

/**
 * Helper: delete directory recursively.
 */
if (!function_exists('siatex_delete_dir')) {
    function siatex_delete_dir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $rii = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($rii as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        @rmdir($dir);
    }
}

// 5) Frontend assets injection (avoid Filament/admin)
app(Hooks::class)->addFilter('theme.head', function (string $html): string {
    $path = '/' . ltrim(request()->path(), '/');

    // Avoid Filament/Admin
    if ($path === '/lara-admin' || str_starts_with($path, '/lara-admin/')) {
        return $html;
    }

    // ✅ Public paths (what browser can access)
    $publicDir = public_path('plugins/siatex/dist');
    $publicCss = $publicDir . DIRECTORY_SEPARATOR . 'siatex-slider.css';
    $publicJs = $publicDir . DIRECTORY_SEPARATOR . 'siatex-slider.js';

    // ✅ Plugin source dist (what you keep inside plugin)
    $pluginDir = base_path('plugins/siatex/dist');

    // ✅ Publish when missing OR empty (0 bytes)
    $cssMissingOrEmpty = (!is_file($publicCss)) || (filesize($publicCss) === 0);
    $jsMissingOrEmpty = (!is_file($publicJs)) || (filesize($publicJs) === 0);

    // ✅ Optional: allow forcing publish with query param (useful during dev)
    // Example: /?siatex_publish=1
    $forcePublish = request()->boolean('siatex_publish');

    $needsPublish = $forcePublish || $cssMissingOrEmpty || $jsMissingOrEmpty;

    if ($needsPublish && is_dir($pluginDir)) {
        // clean public dir then copy fresh (prevents stale/partial files)
        siatex_delete_dir($publicDir);
        @mkdir($publicDir, 0755, true);

        siatex_copy_dir($pluginDir, $publicDir);

        // refresh values after copy
        clearstatcache(true, $publicCss);
        clearstatcache(true, $publicJs);
    }

    // ✅ If still missing, do NOT inject (avoid 404 spam)
    if (!is_file($publicCss) || !is_file($publicJs)) {
        return $html;
    }

    // ✅ Cache busting (prevents “JS not updated” problems)
    $cssUrl = asset('plugins/siatex/dist/siatex-slider.css') . '?v=' . filemtime($publicCss);
    $jsUrl = asset('plugins/siatex/dist/siatex-slider.js') . '?v=' . filemtime($publicJs);

    // ✅ Prevent duplicates separately
    if (!str_contains($html, 'plugins/siatex/dist/siatex-slider.css')) {
        $html .= "\n<link rel=\"stylesheet\" href=\"{$cssUrl}\">";
    }

    /**
     * ✅ Cinematic Pro dependencies (Swiper + GSAP)
     * Loaded via CDN. Safe to include for all frontend pages.
     * If you prefer local vendor files, replace with asset(...) paths.
     */
    if (!str_contains($html, 'swiper-bundle.min.css')) {
        $html .= "\n<link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css\">";
    }
    if (!str_contains($html, 'gsap.min.js')) {
        $html .= "\n<script src=\"https://cdn.jsdelivr.net/npm/gsap@3.12.5/dist/gsap.min.js\" defer></script>";
    }
    if (!str_contains($html, 'swiper-bundle.min.js')) {
        $html .= "\n<script src=\"https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js\" defer></script>";
    }

    if (!str_contains($html, 'plugins/siatex/dist/siatex-slider.js')) {
        $html .= "\n<script src=\"{$jsUrl}\" defer></script>\n";
    }

    return $html;
}, 20, 1);
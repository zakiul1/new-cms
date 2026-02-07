<?php

use App\Cms\Hooks\Hooks;

// 1) Make sure classes/helpers are available
require_once base_path('plugins/slider/SliderRenderer.php');

// 2) Register view namespace used by SliderRenderer
view()->addNamespace('plugins.slider', base_path('plugins/slider/views'));

// 3) Register the renderer in container
app()->singleton(\Plugins\slider\SliderRenderer::class, fn() => new \Plugins\slider\SliderRenderer());

// 4) Load slider assets in theme <head> via your hook system
$hooks = app(Hooks::class);

$hooks->addFilter('theme.head', function (string $html): string {
    // ✅ IMPORTANT: do not inject frontend slider assets on Filament/admin pages
    // Your admin URLs look like: /admin/...
    $path = ltrim(request()->path(), '/');
    if ($path === 'admin' || str_starts_with($path, 'admin/')) {
        return $html;
    }

    // prevent duplicates if hook runs multiple times
    if (
        str_contains($html, 'plugins/slider/dist/slider.css') ||
        str_contains($html, 'plugins/slider/dist/slider.js')
    ) {
        return $html;
    }

    $css = asset('plugins/slider/dist/slider.css');
    $js = asset('plugins/slider/dist/slider.js');

    return $html
        . "\n<link rel=\"stylesheet\" href=\"{$css}\">"
        . "\n<script src=\"{$js}\" defer></script>\n";
}, 20, 1);

if (!function_exists('slider_render')) {
    function slider_render(string $key, array $opts = []): string
    {
        try {
            return app(\Plugins\slider\SliderRenderer::class)->render($key, $opts) ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
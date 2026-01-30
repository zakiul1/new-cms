<?php

use App\Cms\Hooks\Hooks;

// 1) Make sure classes/helpers are available
require_once base_path('plugins/slider/SliderRenderer.php');

// 2) Register view namespace used by SliderRenderer
view()->addNamespace('plugins.slider', base_path('plugins/slider/views'));

// 3) Register the renderer in container (optional but nice)
app()->singleton(\Plugins\Slider\SliderRenderer::class, fn () => new \Plugins\Slider\SliderRenderer());

// 4) Load slider assets in theme <head> via your hook system
$hooks = app(Hooks::class);

$hooks->addFilter('theme.head', function (string $html): string {
    $css = asset('plugins/slider/dist/slider.css');
    $js  = asset('plugins/slider/dist/slider.js');

    return $html
        . "\n<link rel=\"stylesheet\" href=\"{$css}\">"
        . "\n<script src=\"{$js}\" defer></script>\n";
}, 20, 1);

// 5) Helper
if (! function_exists('slider_render')) {
    function slider_render(string $key, array $opts = []): string
    {
        return app(\Plugins\Slider\SliderRenderer::class)->render($key, $opts);
    }
}

<?php

use Plugins\Slider\SliderRenderer;

if (! function_exists('slider_render')) {
    function slider_render(string $key): string
    {
        return app(SliderRenderer::class)->render($key);
    }
}

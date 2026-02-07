<?php

namespace Plugins\slider;


use App\Models\Media;
use App\Models\Slider;

class SliderRenderer
{
    /**
     * Render slider by key.
     *
     * Usage in theme:
     * {!! slider_render('home-hero') !!}
     */
    public function render(string $key, array $overrideOpts = []): string
    {
        $slider = Slider::query()
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        if (! $slider) {
            return '';
        }

        $slides = $slider->slides()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        if ($slides->isEmpty()) {
            return '';
        }

        // ✅ Build options used by blade + JS
        $settings = is_array($slider->settings_json ?? null) ? $slider->settings_json : [];

        $opts = array_merge([
            'autoplay' => (bool) ($settings['autoplay'] ?? true),
            'delay'    => (int) ($settings['delay'] ?? 6000),
            'height'   => (string) ($settings['height'] ?? 'auto'),
        ], $overrideOpts);

        // ✅ Build media map so blade can do: $mediaMap[$slide->media_id]
        $mediaIds = $slides->pluck('media_id')->filter()->unique()->values()->all();

        $mediaMap = Media::query()
            ->whereIn('id', $mediaIds)
            ->get()
            ->keyBy('id');

        // ✅ IMPORTANT: use the namespace registered in bootstrap.php
       return view('plugins.slider::slider', [
   'slider' => $slider,
   'slides' => $slides,
   'opts' => $opts,
   'mediaMap' => $mediaMap,
])->render();

    }
}

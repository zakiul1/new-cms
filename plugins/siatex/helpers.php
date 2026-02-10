<?php

use App\Cms\Core\SettingsRepository;

if (!function_exists('siatex_enabled')) {
    /**
     * Check if the Siatex plugin is enabled (safe: never throws).
     * NOTE: change 'siatex' below if your plugin slug is different.
     */
    function siatex_enabled(string $slug = 'siatex'): bool
    {
        try {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);
            $enabled = (array) $settings->get('core', 'enabled_plugins', []);

            return in_array($slug, $enabled, true);
        } catch (\Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('siatex_slider_variants')) {
    /**
     * Central place to define all available Siatex slider variants.
     * Add new variants here and they will automatically appear in PageForm (if you use this helper there).
     *
     * key => label
     */
    function siatex_slider_variants(): array
    {
        return [
            'siatex-default' => 'Siatex Default',
            'siatex-cinematic-pro' => 'Siatex Cinematic Pro',
            // Add more variants here later...
        ];
    }
}

if (!function_exists('siatex_slider_render')) {
    /**
     * Safely render a Siatex slider by key.
     * - No errors if Siatex plugin is disabled
     * - No errors if Slider model/table is missing
     * - Supports per-page variant override: ['variant' => 'siatex-cinematic-pro']
     * - Variant is resolved dynamically via siatex_slider_variants()
     */
    function siatex_slider_render(?string $key, array $opts = []): string
    {
        if (!$key) {
            return '';
        }

        // If plugin is disabled, never attempt DB/model/view calls
        if (!siatex_enabled()) {
            return '';
        }

        try {
            // Avoid fatal if Slider model does not exist (or slider module removed)
            if (!class_exists(\App\Models\Slider::class)) {
                return '';
            }

            /** @var \App\Models\Slider|null $slider */
            $slider = \App\Models\Slider::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->first();

            if (!$slider) {
                return '';
            }

            $settings = (array) ($slider->settings_json ?? []);

            // ✅ Allow per-page override, fallback to slider settings, then default
            $variant = (string) ($opts['variant'] ?? $settings['variant'] ?? 'siatex-default');

            // ✅ Normalize old removed variant
            if ($variant === 'siatex-cinematic') {
                $variant = 'siatex-default';
            }

            // ✅ Load slides once (used by all variants)
            $slides = $slider->slides()
                ->where('is_active', true)
                ->with('media')
                ->orderBy('sort_order')
                ->get();

            if ($slides->isEmpty()) {
                return '';
            }

            // ✅ Resolve view dynamically:
            // By convention, a variant "siatex-cinematic-pro" maps to:
            // plugins.siatex::variants.cinematic-pro
            $variantViewName = str_replace('siatex-', '', $variant);
            $view = 'plugins.siatex::variants.' . $variantViewName;

            // ✅ If view missing, fallback to default
            if (!view()->exists($view)) {
                $view = 'plugins.siatex::variants.default';
                if (!view()->exists($view)) {
                    return '';
                }
            }

            return view($view, [
                'slider' => $slider,
                'slides' => $slides,
                'settings' => $settings,
                'opts' => $opts,
                'variant' => $variant,
                'variants' => function_exists('siatex_slider_variants') ? siatex_slider_variants() : [],
            ])->render();

        } catch (\Throwable $e) {
            // Never break frontend due to slider issues
            return '';
        }
    }
}

/**
 * Backward-compatible alias.
 * Your old blades can keep using slider_render('home-hero-3')
 * and it will be safe when Siatex is disabled.
 */
if (!function_exists('slider_render')) {
    function slider_render(string $key): string
    {
        return function_exists('siatex_slider_render')
            ? siatex_slider_render($key)
            : '';
    }
}
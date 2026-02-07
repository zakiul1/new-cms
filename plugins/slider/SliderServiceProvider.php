<?php

namespace Plugins\slider;


use Illuminate\Support\ServiceProvider;

class SliderServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ✅ bind renderer into container
        $this->app->singleton(SliderRenderer::class, fn () => new SliderRenderer());
    }

    public function boot(): void
    {
        // ✅ your plugin has: plugins/slider/views/slider.blade.php
        // so from src/ => go up one directory to plugin root => /views
       $this->loadViewsFrom(__DIR__ . '/../views', 'plugins.slider');

        // Optional: allow theme/app to override plugin view
        // php artisan vendor:publish --tag=plugins-slider-views
        $this->publishes([
            __DIR__ . '/../views' => resource_path('views/vendor/plugins-slider'),
        ], 'plugins-slider-views');
    }
}

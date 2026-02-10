<?php

namespace Plugins\siatex;

use Illuminate\Support\ServiceProvider;

class SiatexServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ✅ Correct: views are in plugins/siatex/views
        $this->loadViewsFrom(__DIR__ . '/views', 'plugins.siatex');

        // Optional publish (fix path also)
        $this->publishes([
            __DIR__ . '/views' => resource_path('views/vendor/plugins-siatex'),
        ], 'plugins-siatex-views');
    }
}
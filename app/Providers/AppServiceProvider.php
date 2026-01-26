<?php

namespace App\Providers;

use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginManager;
use App\Livewire\MediaBrowser;
use App\Models\Post;
use App\Observers\PostObserver;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ✅ Boot enabled plugins EARLY so they can register routes via CMS_ROUTES hook.
        $this->app->booting(function () {
            // In console we still want plugin routes available for route:cache
            if ($this->app->runningInConsole()) {
                $this->app->make(PluginManager::class)->bootEnabledPlugins();
                return;
            }

            // HTTP: respect safe mode
            $request = request();
            $safeMode = $this->app->make(SafeMode::class);
            $safeMode->maybeEnableFromRequest($request);

            if (!$safeMode->isEnabled($request)) {
                $this->app->make(PluginManager::class)->bootEnabledPlugins();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Post::observe(PostObserver::class);

        Livewire::component('media-browser', MediaBrowser::class);
    }
}

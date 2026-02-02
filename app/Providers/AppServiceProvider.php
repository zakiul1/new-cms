<?php

namespace App\Providers;

use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginManager;
use App\Livewire\MediaBrowser;
use App\Models\Post;
use App\Observers\PostObserver;
use App\Observers\PostSearchObserver;
use Illuminate\Support\Facades\Gate;
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
            $pluginManager = $this->app->make(PluginManager::class);

            // In console we still want plugin routes available for route:cache etc.
            if ($this->app->runningInConsole()) {
                $pluginManager->bootEnabledPlugins();
                return;
            }

            // HTTP: respect safe mode
            $request = request();
            $safeMode = $this->app->make(SafeMode::class);
            $safeMode->maybeEnableFromRequest($request);

            if (!$safeMode->isEnabled($request)) {
                $pluginManager->bootEnabledPlugins();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * ✅ SUPER ADMIN BYPASS (Spatie Permission / Filament Shield)
         * Ensures super-admin can see all Filament resources/pages/navigation
         * even if explicit permissions are missing.
         */
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin')
                ? true
                : null;
        });

        // ✅ Model observers
        Post::observe(PostObserver::class);

        // ✅ Search index observer (only if you created it)
        if (class_exists(PostSearchObserver::class)) {
            Post::observe(PostSearchObserver::class);
        }

        // ✅ Livewire components
        Livewire::component('media-browser', MediaBrowser::class);
    }
}
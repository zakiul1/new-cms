<?php

namespace App\Providers;

use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginManager;
use App\Cms\Shortcodes\CoreShortcodes; // ✅ ADD
use App\Livewire\MediaBrowser;
use App\Models\Post;
use App\Observers\PostObserver;
use App\Observers\PostSearchObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
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

    public function boot(): void
    {
        // ✅ SUPER ADMIN BYPASS
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super-admin')
                ? true
                : null;
        });

        // ✅ Model observers
        Post::observe(PostObserver::class);

        if (class_exists(PostSearchObserver::class)) {
            Post::observe(PostSearchObserver::class);
        }

        // ✅ Livewire components
        Livewire::component('media-browser', MediaBrowser::class);

        // ✅ Register CORE shortcodes once (system-level)
        if (function_exists('add_shortcode')) {
            CoreShortcodes::register();
        }
    }
}
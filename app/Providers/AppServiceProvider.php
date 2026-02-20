<?php

namespace App\Providers;

use App\Cms\Core\SafeMode;
use App\Cms\Plugins\PluginManager;
use App\Cms\Shortcodes\CoreShortcodes;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Livewire\MediaBrowser;
use App\Models\Post;
use App\Observers\PostObserver;
use App\Observers\PostSearchObserver;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Livewire\Filament\ToggleFrontendAdminBar;
use App\Models\MenuItem;
use App\Models\MenuAssignment;
use App\Observers\MenuItemCacheObserver;
use App\Observers\MenuAssignmentCacheObserver;

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

        MenuItem::observe(MenuItemCacheObserver::class);
        MenuAssignment::observe(MenuAssignmentCacheObserver::class);
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
        Livewire::component('filament.toggle-frontend-admin-bar', ToggleFrontendAdminBar::class);

        /**
         * ✅ Register CORE shortcodes into ShortcodeRegistry (the one ShortcodeParser uses)
         * NOTE: If you already call CoreShortcodes::register($shortcodes) inside CmsServiceProvider,
         * you can REMOVE this block entirely to avoid double registration.
         */
        $registry = app(ShortcodeRegistry::class);
        CoreShortcodes::register($registry);
    }
}
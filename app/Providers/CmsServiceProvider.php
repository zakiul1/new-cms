<?php

namespace App\Providers;

use App\Cms\Assets\AssetManager;
use App\Cms\Core\SafeMode;
use App\Cms\Core\Settings;
use App\Cms\Hooks\Hooks;
use App\Cms\Hooks\HookPoints;
use App\Cms\Plugins\PluginManager;
use App\Cms\Themes\ThemeManager;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        $this->app->singleton(Hooks::class);
        $this->app->singleton(AssetManager::class);

        $this->app->singleton(PluginManager::class);
        $this->app->singleton(ThemeManager::class);

        $this->app->singleton(SafeMode::class);

        $this->app->singleton(\App\Cms\Content\Blocks\BlockRegistry::class);
        $this->app->singleton(\App\Cms\Content\Blocks\BlockRenderer::class);

    }

    public function boot(): void
    {
        $hooks = $this->app->make(Hooks::class);
        $safeMode = $this->app->make(SafeMode::class);

        // Request may not exist in CLI (composer/artisan)
        $request = $this->app->bound('request') ? $this->app->make('request') : null;

        // Enable safe mode only when session is available
        $safeMode->maybeEnableFromRequest($request);

        // Booting hook
        $hooks->doAction(HookPoints::CMS_BOOT);

        // Load enabled plugins (skip if safe mode OR no request session)
        if (!$safeMode->isEnabled($request)) {
            $this->app->make(PluginManager::class)->bootEnabledPlugins();
        }

        // Boot theme
        $this->app->make(ThemeManager::class)->bootActiveTheme();

        // Enqueue assets hook
        $hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS);

        // Booted hook
        $hooks->doAction(HookPoints::CMS_BOOTED);


        $registry = $this->app->make(\App\Cms\Content\Blocks\BlockRegistry::class);

        $registry->register(
            'paragraph',
            fn(array $data) =>
            view('cms.blocks.paragraph', ['text' => (string) ($data['text'] ?? '')])->render()
        );

        $registry->register(
            'heading',
            fn(array $data) =>
            view('cms.blocks.heading', ['text' => (string) ($data['text'] ?? ''), 'level' => (int) ($data['level'] ?? 2)])->render()
        );

    }



}
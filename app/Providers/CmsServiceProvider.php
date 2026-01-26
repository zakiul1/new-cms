<?php

namespace App\Providers;

use App\Cms\Assets\AssetManager;
use App\Cms\Content\Blocks\BlockRegistry;
use App\Cms\Content\Blocks\BlockRenderer;
use App\Cms\Core\SafeMode;
use App\Cms\Core\Settings;
use App\Cms\Hooks\Hooks;
use App\Cms\Hooks\HookPoints;
use App\Cms\Plugins\PluginManager;
use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManifestReader;
use App\Cms\Themes\ThemeManager;
use App\Cms\Themes\ThemePublisher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class CmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Core
        $this->app->singleton(Settings::class);
        $this->app->singleton(Hooks::class);
        $this->app->singleton(AssetManager::class);
        $this->app->singleton(SafeMode::class);

        // Plugins
        $this->app->singleton(PluginManager::class);

        // Themes (Part 1–4)
        $this->app->singleton(ThemeManifestReader::class);
        $this->app->singleton(ThemePublisher::class);
        $this->app->singleton(ThemeInstaller::class);
        $this->app->singleton(ThemeManager::class);

        // Blocks
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(BlockRenderer::class);
    }

    public function boot(): void
    {
        // ✅ CRITICAL: avoid DB/settings/theme/plugin boot during artisan/composer scripts
        if ($this->app->runningInConsole()) {
            return;
        }

        /** @var Hooks $hooks */
        $hooks = $this->app->make(Hooks::class);

        /** @var SafeMode $safeMode */
        $safeMode = $this->app->make(SafeMode::class);

        // Request exists in HTTP only (we already skipped console)
        $request = $this->app->make('request');

        // Safe mode from request/session (only valid in HTTP)
        $safeMode->maybeEnableFromRequest($request);

        // Booting hook
        $hooks->doAction(HookPoints::CMS_BOOT);

        // Plugins (skip if safe mode)
        if (!$safeMode->isEnabled($request)) {
            $this->app->make(PluginManager::class)->bootEnabledPlugins();
        }

        // Theme (loads views + ensures active theme valid + republishes dist if missing)
        $this->app->make(ThemeManager::class)->bootActiveTheme();
        $this->app->make(ThemeManager::class)->enqueueActiveThemeAssets();


        // Enqueue assets hook
        $hooks->doAction(HookPoints::CMS_ENQUEUE_ASSETS);

        // Register core blocks (HTTP only)
        $this->registerCoreBlocks($this->app);

        // Booted hook
        $hooks->doAction(HookPoints::CMS_BOOTED);
    }

    private function registerCoreBlocks(Application $app): void
    {
        /** @var BlockRegistry $registry */
        $registry = $app->make(BlockRegistry::class);

        $registry->register(
            'paragraph',
            fn(array $data) => view('cms.blocks.paragraph', [
                'text' => (string) ($data['text'] ?? ''),
            ])->render()
        );

        $registry->register(
            'heading',
            fn(array $data) => view('cms.blocks.heading', [
                'text' => (string) ($data['text'] ?? ''),
                'level' => (int) ($data['level'] ?? 2),
            ])->render()
        );
    }
}
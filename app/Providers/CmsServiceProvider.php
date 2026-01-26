<?php

namespace App\Providers;

use App\Cms\Assets\AssetManager;
use App\Cms\Content\Blocks\BlockRegistry;
use App\Cms\Content\Blocks\BlockRenderer;
use App\Cms\Content\Shortcodes\ShortcodeParser;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Cms\Core\SafeMode;
use App\Cms\Core\Settings;
use App\Cms\Hooks\Hooks;
use App\Cms\Hooks\HookPoints;
use App\Cms\Plugins\PluginManifestReader;
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
        $this->app->singleton(PluginManifestReader::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(\App\Cms\Plugins\PluginPublisher::class);
        $this->app->singleton(\App\Cms\Plugins\PluginInstaller::class);
        $this->app->singleton(\App\Cms\Plugins\PluginUninstaller::class);
        $this->app->singleton(\App\Cms\Plugins\PluginLifecycle::class);
        $this->app->singleton(\App\Cms\Plugins\PluginSettingsSchema::class);




        // Themes
        $this->app->singleton(ThemeManifestReader::class);
        $this->app->singleton(ThemePublisher::class);
        $this->app->singleton(ThemeInstaller::class);
        $this->app->singleton(ThemeManager::class);

        // Blocks
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(BlockRenderer::class);

        // Shortcodes
        $this->app->singleton(ShortcodeRegistry::class);
        $this->app->singleton(ShortcodeParser::class);

      $this->app->singleton(PluginPublisher::class);


    }

    public function boot(): void
    {
        /** @var Hooks $hooks */
        $hooks = $this->app->make(Hooks::class);

        // Booting hook (safe in console)
        $hooks->doAction(HookPoints::CMS_BOOT);

        // Content pipeline (shortcodes)
        $this->registerContentPipeline($hooks);

        // ✅ IMPORTANT: Plugins are booted EARLY in AppServiceProvider::register()
        // so they can register routes via CMS_ROUTES.
        // DO NOT boot plugins here.

        // Theme: boot views / publish dist if missing
        $this->app->make(ThemeManager::class)->bootActiveTheme();

        // Core blocks (HTTP only)
        if (!$this->app->runningInConsole()) {
            $this->registerCoreBlocks($this->app);
        }

        // Booted hook
        $hooks->doAction(HookPoints::CMS_BOOTED);
    }

    private function registerContentPipeline(Hooks $hooks): void
    {
        /** @var ShortcodeRegistry $shortcodes */
        $shortcodes = $this->app->make(ShortcodeRegistry::class);

        // Built-in shortcodes
        $shortcodes->register('year', fn () => (string) now()->year);

        $shortcodes->register('button', function (array $attrs, ?string $content) {
            $url = (string) ($attrs['url'] ?? '#');
            $label = $content ?: (string) ($attrs['label'] ?? 'Click');
            $blank = !empty($attrs['blank']);
            $target = $blank ? ' target="_blank" rel="noopener"' : '';

            return '<a href="'.e($url).'"'.$target.' class="btn">'.e($label).'</a>';
        });

        // Apply shortcodes through CMS_THE_CONTENT pipeline
        $hooks->addFilter(HookPoints::CMS_THE_CONTENT, function ($html, $ctx = []) {
            return app(ShortcodeParser::class)->render(
                (string) $html,
                is_array($ctx) ? $ctx : []
            );
        }, 20, 2);
    }

    private function registerCoreBlocks(Application $app): void
    {
        /** @var BlockRegistry $registry */
        $registry = $app->make(BlockRegistry::class);

        $registry->register(
            'paragraph',
            fn (array $data) => view('cms.blocks.paragraph', [
                'text' => (string) ($data['text'] ?? ''),
            ])->render()
        );

        $registry->register(
            'heading',
            fn (array $data) => view('cms.blocks.heading', [
                'text' => (string) ($data['text'] ?? ''),
                'level' => (int) ($data['level'] ?? 2),
            ])->render()
        );
    }
}

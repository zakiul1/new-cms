<?php

namespace App\Providers;

use App\Cms\Assets\AssetManager;
use App\Cms\Content\Blocks\BlockRegistry;
use App\Cms\Content\Blocks\BlockRenderer;
use App\Cms\Content\Shortcodes\ShortcodeParser;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Cms\Core\CmsCacheVersions;
use App\Cms\Core\SafeMode;
use App\Cms\Core\Settings;
use App\Cms\Hooks\Hooks;
use App\Cms\Hooks\HookPoints;
use App\Cms\Menus\MenuRegistry;
use App\Cms\Menus\MenuRenderer;
use App\Cms\Plugins\PluginFileManager;
use App\Cms\Plugins\PluginInstaller;
use App\Cms\Plugins\PluginLifecycle;
use App\Cms\Plugins\PluginManifestReader;
use App\Cms\Plugins\PluginManager;
use App\Cms\Plugins\PluginPublisher;
use App\Cms\Plugins\PluginSettingsSchema;
use App\Cms\Plugins\PluginUninstaller;
use App\Cms\Themes\ThemeInstaller;
use App\Cms\Themes\ThemeManifestReader;
use App\Cms\Themes\ThemeManager;
use App\Cms\Themes\ThemeOptions;
use App\Cms\Themes\ThemePublisher;
use App\Cms\Widgets\SidebarRegistry;
use App\Cms\Widgets\SidebarRenderer;
use App\Cms\Widgets\WidgetRegistry;
use App\Cms\Widgets\Types\MenuWidget;
use App\Cms\Widgets\Types\TextWidget;
use App\Models\Widget;
use App\Models\WidgetPlacement;
use App\Observers\WidgetCacheObserver;
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

        // Cache versions for menus/widgets HTML caching
        $this->app->singleton(CmsCacheVersions::class);

        // Plugins
        $this->app->singleton(PluginManifestReader::class);
        $this->app->singleton(PluginManager::class);
        $this->app->singleton(PluginPublisher::class);
        $this->app->singleton(PluginInstaller::class);
        $this->app->singleton(PluginUninstaller::class);
        $this->app->singleton(PluginLifecycle::class);
        $this->app->singleton(PluginSettingsSchema::class);
        $this->app->singleton(PluginPublisher::class);
        $this->app->singleton(PluginFileManager::class);

        // Themes
        $this->app->singleton(ThemeManifestReader::class);
        $this->app->singleton(ThemePublisher::class);
        $this->app->singleton(ThemeInstaller::class);
        $this->app->singleton(ThemeManager::class);
        $this->app->singleton(ThemeOptions::class);


        // Blocks
        $this->app->singleton(BlockRegistry::class);
        $this->app->singleton(BlockRenderer::class);

        // Shortcodes
        $this->app->singleton(ShortcodeRegistry::class);
        $this->app->singleton(ShortcodeParser::class);

        // Phase 6: Menus & Widgets services
        $this->app->singleton(MenuRegistry::class);
        $this->app->singleton(MenuRenderer::class);

        $this->app->singleton(SidebarRegistry::class);
        $this->app->singleton(WidgetRegistry::class);
        $this->app->singleton(SidebarRenderer::class);
    }

    public function boot(): void
    {
        /** @var Hooks $hooks */
        $hooks = $this->app->make(Hooks::class);

        // Booting hook (safe in console)
        $hooks->doAction(HookPoints::CMS_BOOT);

        // Content pipeline (shortcodes)
        $this->registerContentPipeline($hooks);

        // IMPORTANT: Plugins are booted EARLY in AppServiceProvider::register()
        // so they can register routes via CMS_ROUTES.
        // DO NOT boot plugins here.

        // Theme: boot views / publish dist if missing
        $this->app->make(ThemeManager::class)->bootActiveTheme();

        // ✅ Register built-in/core locations & widget types (NO helper functions)
        $this->registerCoreMenusAndWidgets($hooks);

        // ✅ Let theme + plugins register menu locations, sidebars, widget types
        $hooks->doAction(HookPoints::CMS_REGISTER_MENUS);
        $hooks->doAction(HookPoints::CMS_REGISTER_SIDEBARS);
        $hooks->doAction(HookPoints::CMS_REGISTER_WIDGETS);

        // ✅ Register cache bump observer for widgets (important for sidebar caching)
        $this->registerWidgetCacheObserver();

        // Core blocks (HTTP only)
        if (!$this->app->runningInConsole()) {
            $this->registerCoreBlocks($this->app);
        }

        // Booted hook
        $hooks->doAction(HookPoints::CMS_BOOTED);

        $this->app->singleton(\App\Cms\Seo\SeoRenderer::class);

    }

    /**
     * Register default core menu locations, widget areas, and widget types.
     * Uses Hooks service directly so it works even if helper functions aren't loaded yet.
     */
    private function registerCoreMenusAndWidgets(Hooks $hooks): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;

        $hooks->addAction(HookPoints::CMS_REGISTER_MENUS, function () {
            app(MenuRegistry::class)->register('topbar', 'Top Bar Menu');
            app(MenuRegistry::class)->register('primary', 'Primary Menu');
            app(MenuRegistry::class)->register('footer', 'Footer Menu');
        }, 5, 0);

        $hooks->addAction(HookPoints::CMS_REGISTER_SIDEBARS, function () {
            app(SidebarRegistry::class)->register('sidebar-1', 'Main Sidebar');
            app(SidebarRegistry::class)->register('footer-1', 'Footer Widgets');
        }, 5, 0);

        $hooks->addAction(HookPoints::CMS_REGISTER_WIDGETS, function () {
            $reg = app(WidgetRegistry::class);
            $reg->register(TextWidget::class);
            $reg->register(MenuWidget::class);
        }, 5, 0);
    }

    private function registerWidgetCacheObserver(): void
    {
        // Avoid double registration if provider boots twice in some environments
        static $done = false;
        if ($done)
            return;
        $done = true;

        $observer = $this->app->make(WidgetCacheObserver::class);

        WidgetPlacement::saved(fn(WidgetPlacement $p) => $observer->placementSaved($p));
        WidgetPlacement::deleted(fn(WidgetPlacement $p) => $observer->placementDeleted($p));

        Widget::saved(fn(Widget $w) => $observer->widgetSaved($w));
        Widget::deleted(fn(Widget $w) => $observer->widgetDeleted($w));
    }

    private function registerContentPipeline(Hooks $hooks): void
    {
        /** @var ShortcodeRegistry $shortcodes */
        $shortcodes = $this->app->make(ShortcodeRegistry::class);

        // Built-in shortcodes
        $shortcodes->register('year', fn() => (string) now()->year);

        $shortcodes->register('button', function (array $attrs, ?string $content) {
            $url = (string) ($attrs['url'] ?? '#');
            $label = $content ?: (string) ($attrs['label'] ?? 'Click');
            $blank = !empty($attrs['blank']);
            $target = $blank ? ' target="_blank" rel="noopener"' : '';

            return '<a href="' . e($url) . '"' . $target . ' class="btn">' . e($label) . '</a>';
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
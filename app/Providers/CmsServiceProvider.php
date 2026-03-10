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
use App\Cms\Core\SettingsRepository;
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
use App\Cms\Widgets\Types\CategoriesWidget;
use App\Cms\Widgets\Types\ShortcodeWidget;
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

        // Menus & Widgets services
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

        // Apply runtime media settings from DB
        $this->applyMediaRuntimeSettings();

        // Page template options
        $hooks->addFilter('cms.page_template_options', function (array $opts) {
            if (view()->exists('templates.static-landing-page')) {
                $opts['static-landing-page'] = 'Static Landing Page';
            }

            if (view()->exists('templates.huraira-fashion')) {
                $opts['huraira-fashion'] = 'Huraira Fashion';
            }

            return $opts;
        }, 20, 1);

        // Booting hook (safe in console)
        $hooks->doAction(HookPoints::CMS_BOOT);

        // Content pipeline (shortcodes)
        $this->registerContentPipeline($hooks);

        // Enable shortcodes for widget output
        $hooks->addFilter('cms.sidebar.widget_html', function ($html, $widget, $ctx) use ($hooks) {
            return $hooks->applyFilters(HookPoints::CMS_THE_CONTENT, (string) $html, is_array($ctx) ? $ctx : []);
        }, 20, 3);

        // Theme: boot views / publish dist if missing
        $this->app->make(ThemeManager::class)->bootActiveTheme();

        // Register built-in/core locations & widget types
        $this->registerCoreMenusAndWidgets($hooks);

        // Let theme + plugins register menu locations, sidebars, widget types
        $hooks->doAction(HookPoints::CMS_REGISTER_MENUS);
        $hooks->doAction(HookPoints::CMS_REGISTER_SIDEBARS);
        $hooks->doAction(HookPoints::CMS_REGISTER_WIDGETS);

        // Register cache bump observer for widgets
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
     * Apply saved media settings to runtime config.
     *
     * Canonical keys only:
     * - thumb
     * - small
     * - hero_sm
     * - large
     *
     * No fallback to old medium / medium_large keys.
     */
    private function applyMediaRuntimeSettings(): void
    {
        /** @var SettingsRepository $settings */
        $settings = app(SettingsRepository::class);

        $defaults = (array) config('cms-media.image_variants', []);

        $thumb = (int) $settings->get(
            'core',
            'media_thumbnail_width',
            (int) ($defaults['thumb'] ?? 275)
        );

        $small = (int) $settings->get(
            'core',
            'media_small_width',
            (int) ($defaults['small'] ?? 370)
        );

        $heroSm = (int) $settings->get(
            'core',
            'media_hero_sm_width',
            (int) ($defaults['hero_sm'] ?? 575)
        );

        $large = (int) $settings->get(
            'core',
            'media_large_width',
            (int) ($defaults['large'] ?? 1000)
        );

        config()->set('cms-media.image_variants', [
            'thumb' => max(1, $thumb),
            'small' => max(1, $small),
            'hero_sm' => max(1, $heroSm),
            'large' => max(1, $large),
        ]);
    }

    /**
     * Register default core menu locations, widget areas, and widget types.
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

            $cols = (int) app(SettingsRepository::class)->get('core', 'footer_columns', 3);
            $cols = max(3, min(5, $cols));

            for ($i = 1; $i <= $cols; $i++) {
                app(SidebarRegistry::class)->register("footer-{$i}", "Footer Column {$i}");
            }
        }, 5, 0);

        $hooks->addAction(HookPoints::CMS_REGISTER_WIDGETS, function () {
            $reg = app(WidgetRegistry::class);

            $reg->register(TextWidget::class);
            $reg->register(MenuWidget::class);
            $reg->register(CategoriesWidget::class);

            if (class_exists(ShortcodeWidget::class)) {
                $reg->register(ShortcodeWidget::class);
            }
        }, 5, 0);
    }

    private function registerWidgetCacheObserver(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $observer = $this->app->make(WidgetCacheObserver::class);

        WidgetPlacement::saved(fn(WidgetPlacement $p) => $observer->placementSaved($p));
        WidgetPlacement::deleted(fn(WidgetPlacement $p) => $observer->placementDeleted($p));

        Widget::saved(fn(Widget $w) => $observer->widgetSaved($w));
        Widget::deleted(fn(Widget $w) => $observer->widgetDeleted($w));
    }

    /**
     * This is the correct place to register shortcodes for ShortcodeParser
     */
    private function registerContentPipeline(Hooks $hooks): void
    {
        /** @var ShortcodeRegistry $shortcodes */
        $shortcodes = $this->app->make(ShortcodeRegistry::class);

        \App\Cms\Shortcodes\CoreShortcodes::register($shortcodes);

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
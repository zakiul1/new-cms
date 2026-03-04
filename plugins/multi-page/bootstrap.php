<?php

use App\Cms\Hooks\HookPoints;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use Filament\Panel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;

// Support
require_once __DIR__ . '/src/Support/MultiPageStorage.php';
require_once __DIR__ . '/src/Support/MultiPageGenerator.php';
require_once __DIR__ . '/src/Support/MultiPageResolver.php';

// ✅ Settings + Sitemap
require_once __DIR__ . '/src/Support/MultiPageSettings.php';
require_once __DIR__ . '/src/Support/MultiPageSitemapGenerator.php';

// Filament - Schema (new multipage form, copied from PageForm and trimmed)
require_once __DIR__ . '/src/Filament/Schemas/MultiPageForm.php';

// Filament - Resource (Multi Pages list/create/edit)
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/ListMultiPages.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/CreateMultiPage.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/EditMultiPage.php';

// ✅ NEW: Table config (needed because plugin loads files manually, not via composer autoload)
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Tables/MultiPagesTable.php';

// Filament - Links list page (View List)
require_once __DIR__ . '/src/Filament/Pages/MultiPageLinksPage.php';

// Filament - Submenu pages
require_once __DIR__ . '/src/Filament/Pages/AddMultiPage.php';
require_once __DIR__ . '/src/Filament/Pages/SettingsMultiPages.php';

View::addNamespace('multi-page', __DIR__ . '/views');

/**
 * 1) Replace {segment-N} tokens in rendered content.
 * Runs before shortcodes.
 *
 * ✅ Update:
 * - Prefer RAW CSV values (multipage_segments_raw) for display casing.
 * - Fallback to slugified values (multipage_segments) if raw not available.
 * - Supports BOTH {segment-1} and {{segment-1}} tokens.
 */
add_filter(HookPoints::CMS_THE_CONTENT, function ($html, $ctx = []) {
    $html = (string) $html;

    $segments = request()->attributes->get('multipage_segments_raw');
    if (!is_array($segments) || $segments === []) {
        $segments = request()->attributes->get('multipage_segments');
    }
    if (!is_array($segments) || $segments === []) {
        return $html;
    }

    foreach ($segments as $i => $val) {
        $n = $i + 1;

        $token1 = '{segment-' . $n . '}';
        $token2 = '{{segment-' . $n . '}}';

        $html = str_replace([$token1, $token2], (string) $val, $html);
    }

    return $html;
}, 10, 2);

/**
 * 2) Shortcodes:
 * [segment n="1"], [segment-1]...[segment-10]
 *
 * ✅ Update:
 * - Prefer RAW CSV values for display, fallback to slugified.
 */
add_action(HookPoints::CMS_BOOTED, function () {
    /** @var ShortcodeRegistry $shortcodes */
    $shortcodes = app(ShortcodeRegistry::class);

    $shortcodes->register('segment', function (array $attrs) {
        $n = (int) ($attrs['n'] ?? 1);
        if ($n <= 0) {
            $n = 1;
        }

        $segments = request()->attributes->get('multipage_segments_raw');
        if (!is_array($segments) || $segments === []) {
            $segments = request()->attributes->get('multipage_segments');
        }
        if (!is_array($segments)) {
            return '';
        }

        return e((string) ($segments[$n - 1] ?? ''));
    });

    for ($i = 1; $i <= 10; $i++) {
        $tag = 'segment-' . $i;
        $shortcodes->register($tag, function () use ($i) {
            $segments = request()->attributes->get('multipage_segments_raw');
            if (!is_array($segments) || $segments === []) {
                $segments = request()->attributes->get('multipage_segments');
            }
            if (!is_array($segments)) {
                return '';
            }
            return e((string) ($segments[$i - 1] ?? ''));
        });
    }
});

/**
 * ✅ 2.5) Register MultiPage template option for "Template" dropdown
 */
add_filter('cms.page_template_options', function (array $options) {
    if (View::exists('templates.multipage-attachment')) {
        $options['multipage-attachment'] = 'Multipage (Attachment Style)';
    }
    return $options;
}, 25, 1);

/**
 * 3) Filament admin panel registration
 */
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->resources([
        \Plugins\MultiPage\Filament\Resources\MultiPageResource::class,
    ]);

    $panel->pages([
        \Plugins\MultiPage\Filament\Pages\AddMultiPage::class,
        \Plugins\MultiPage\Filament\Pages\SettingsMultiPages::class,
        \Plugins\MultiPage\Filament\Pages\MultiPageLinksPage::class,
    ]);
}, 10, 1);

/**
 * 4) Frontend routes:
 * - serve sitemap from ROOT (boss request)
 * - keep /multipage-sitemap.xml for backward compatibility
 */
add_action(HookPoints::CMS_ROUTES, function () {

    /**
     * ✅ ROOT sitemap route (Google requirement)
     * Examples:
     *  /static.xml
     *  /static-1.xml
     *  /multipage-sitemap.xml (kept below)
     *
     * This reads the generated file from storage/app/public/<dir>/<name>.xml
     */
    Route::get('/{name}.xml', function (string $name) {
        $settings = \Plugins\MultiPage\Support\MultiPageSettings::load();

        $dir = trim((string) ($settings['sitemaps_dir'] ?? 'sitemaps-multipage'));
        if ($dir === '') {
            $dir = 'sitemaps-multipage';
        }

        // Only allow safe file names: letters, numbers, dash
        $name = trim($name);
        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $name)) {
            abort(404);
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = $dir . '/' . $name . '.xml';

        if (!$disk->exists($path)) {
            abort(404);
        }

        $xml = $disk->get($path);

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    })->where('name', '[A-Za-z0-9\-]+');

    /**
     * ✅ Backward-compatible URL (still works)
     * It serves whatever current settings file_base_name is.
     */
    Route::get('/multipage-sitemap.xml', function () {
        $settings = \Plugins\MultiPage\Support\MultiPageSettings::load();

        $dir = trim((string) ($settings['sitemaps_dir'] ?? 'sitemaps-multipage'));
        $name = trim((string) ($settings['file_base_name'] ?? 'static'));

        if ($dir === '') {
            $dir = 'sitemaps-multipage';
        }
        if ($name === '') {
            $name = 'static';
        }

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = $dir . '/' . $name . '.xml';

        if (!$disk->exists($path)) {
            abort(404);
        }

        $xml = $disk->get($path);

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    });

}, 5, 0);
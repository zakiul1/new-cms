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

// Filament - inject into existing Page Create/Edit right panel
require_once __DIR__ . '/src/Filament/Injectors/InjectPageMultipagePanel.php';

// Filament - Resource (Multi Page list/create/edit)
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/ListMultiPages.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/CreateMultiPage.php';
require_once __DIR__ . '/src/Filament/Resources/MultiPageResource/Pages/EditMultiPage.php';

// Filament - Links list page (View List)
require_once __DIR__ . '/src/Filament/Pages/MultiPageLinksPage.php';

View::addNamespace('multi-page', __DIR__ . '/views');

/**
 * Register the right-panel "Multipage Settings" metabox inside Page create/edit.
 * This is what gives you the same UI workflow as your custom CMS screenshot.
 */
\Plugins\MultiPage\Filament\Injectors\InjectPageMultipagePanel::register();

/**
 * 1) Replace {segment-1} tokens in rendered content (like custom CMS).
 * Run before shortcodes.
 */
add_filter(HookPoints::CMS_THE_CONTENT, function ($html, $ctx = []) {
    $html = (string) $html;

    $segments = request()->attributes->get('multipage_segments');
    if (!is_array($segments) || $segments === []) {
        return $html;
    }

    foreach ($segments as $i => $val) {
        $n = $i + 1;
        $html = str_replace('{segment-' . $n . '}', (string) $val, $html);
    }

    return $html;
}, 10, 2);

/**
 * 2) Shortcodes:
 * [segment n="1"], [segment-1]...[segment-10]
 */
add_action(HookPoints::CMS_BOOTED, function () {
    /** @var ShortcodeRegistry $shortcodes */
    $shortcodes = app(ShortcodeRegistry::class);

    $shortcodes->register('segment', function (array $attrs) {
        $n = (int) ($attrs['n'] ?? 1);
        if ($n <= 0)
            $n = 1;

        $segments = request()->attributes->get('multipage_segments');
        if (!is_array($segments))
            return '';

        return e((string) ($segments[$n - 1] ?? ''));
    });

    for ($i = 1; $i <= 10; $i++) {
        $tag = 'segment-' . $i;
        $shortcodes->register($tag, function () use ($i) {
            $segments = request()->attributes->get('multipage_segments');
            if (!is_array($segments))
                return '';
            return e((string) ($segments[$i - 1] ?? ''));
        });
    }
});

/**
 * 3) Filament admin panel registration:
 * - Registers MultiPageResource (list/create/edit)
 * - Registers MultiPageLinksPage (View List)
 * - Registers a named route for View List button
 */
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {

    $panel->resources([
        \Plugins\MultiPage\Filament\Resources\MultiPageResource::class,
    ]);

    $panel->pages([
        \Plugins\MultiPage\Filament\Pages\MultiPageLinksPage::class,
    ]);


}, 10, 1);

/**
 * 4) Frontend resolver route:
 * Only matches MULTI-segment paths (so it won't steal /{slug} pages).
 * Reads file-based mappings from storage/app/multipage/static-links/*.json
 */
add_action(HookPoints::CMS_ROUTES, function () {
    Route::get('/{path}', function (string $path) {
        if (!str_contains($path, '/')) {
            abort(404);
        }

        $resolver = new \Plugins\MultiPage\Support\MultiPageResolver();
        $response = $resolver->handle(request(), $path);

        if ($response !== null) {
            return $response;
        }

        abort(404);
    })->where(
            'path',
            '^(?!lara-admin(?:/|$)|api(?:/|$)|storage(?:/|$)|sitemap\.xml$|robots\.txt$|customizer(?:/|$)).+/.+$'
        );
}, 5, 0);
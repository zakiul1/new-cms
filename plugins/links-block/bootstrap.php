<?php

use App\Cms\Hooks\HookPoints;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use Filament\Panel;
use Illuminate\Support\Facades\View;

// Manually load classes (no composer autoload in this CMS plugin system)
require_once __DIR__ . '/src/Support/LinksBlockSettings.php';
require_once __DIR__ . '/src/Support/LinksBlockLinkProvider.php';
require_once __DIR__ . '/src/Support/LinksBlockShortcode.php';
require_once __DIR__ . '/src/Filament/Pages/LinksBlockSettingsPage.php';

// Views namespace
View::addNamespace('links-block', __DIR__ . '/views');

/**
 * Register shortcode [linksblock]
 */
add_action(HookPoints::CMS_BOOTED, function () {
    /** @var ShortcodeRegistry $shortcodes */
    $shortcodes = app(ShortcodeRegistry::class);

    $shortcodes->registerWithMeta('linksblock', function (array $atts = [], ?string $content = null, array $ctx = []) {
        return \Plugins\LinksBlock\Support\LinksBlockShortcode::render($atts);
    }, [
        'group' => 'Plugins',
        'description' => 'Renders a multi-column block of internal links from MultiPage sitemap data.',
        'params' => [
            ['name' => 'col', 'type' => 'int', 'default' => 4, 'desc' => 'Desktop columns'],
            ['name' => 'row', 'type' => 'int', 'default' => 5, 'desc' => 'Rows per column (0 = auto)'],
            ['name' => 'mcol', 'type' => 'int', 'default' => 2, 'desc' => 'Mobile columns'],
            ['name' => 'tcol', 'type' => 'int', 'default' => 3, 'desc' => 'Tablet columns'],
            ['name' => 'n', 'type' => 'int', 'default' => 60, 'desc' => 'Total links'],
            ['name' => 'rand', 'type' => 'yes/no', 'default' => 'no', 'desc' => 'Shuffle links'],
            ['name' => 'hide', 'type' => 'yes/no', 'default' => 'no', 'desc' => 'Collapsible block'],
            ['name' => 'new-window', 'type' => 'yes/no', 'default' => 'no', 'desc' => 'Open in new tab'],
            ['name' => 'single-line', 'type' => 'yes/no', 'default' => 'no', 'desc' => 'Single line titles'],
        ],
        'examples' => [
            '[linksblock]',
            '[linksblock col="4" row="5" mcol="2" tcol="3" n="60" rand="yes" hide="yes" new-window="yes" single-line="yes"]',
        ],
    ]);
});

/**
 * Filament admin page
 */
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel) {
    $panel->pages([
        \Plugins\LinksBlock\Filament\Pages\LinksBlockSettingsPage::class,
    ]);
}, 10, 1);
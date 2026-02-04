<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Filament\Panel;

/**
 * Detect "empty" RichEditor content:
 * - null / '' / whitespace
 * - <p><br></p>, <p></p>, etc.
 * - &nbsp;
 */
if (!function_exists('media_defaults_rich_is_empty')) {
    function media_defaults_rich_is_empty($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return blank($value);
        }

        $html = trim($value);
        if ($html === '') {
            return true;
        }

        // Convert &nbsp; and other entities, remove tags
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text); // non-breaking space char
        $text = trim(strip_tags($text));

        return $text === '';
    }
}

/**
 * Auto-fill empty media fields when Edit Media loads
 */
add_filter('media.edit.defaults.fill', function (array $data, $record = null): array {
    $settings = app(Settings::class);
    $group = 'plugins.media-defaults';

    $defaultTitle = trim((string) $settings->get('default_title', '', $group));
    $defaultDesc = trim((string) $settings->get('default_description', '', $group));
    $defaultSubTitle = trim((string) $settings->get('default_sub_title', '', $group));
    $defaultSubDesc = trim((string) $settings->get('default_sub_description', '', $group));

    // Title (TextInput)
    if (!filled($data['title'] ?? null) && $defaultTitle !== '') {
        $data['title'] = $defaultTitle;
    }

    // Description (RichEditor) ✅ fixed
    if (media_defaults_rich_is_empty($data['description'] ?? null) && $defaultDesc !== '') {
        $data['description'] = $defaultDesc;
    }

    // Sub title (TextInput)
    if (!filled(data_get($data, 'meta.frontend.meta_title')) && $defaultSubTitle !== '') {
        data_set($data, 'meta.frontend.meta_title', $defaultSubTitle);
    }

    // Sub description (RichEditor) ✅ fixed
    $currentSubDesc = data_get($data, 'meta.frontend.meta_description');
    if (media_defaults_rich_is_empty($currentSubDesc) && $defaultSubDesc !== '') {
        data_set($data, 'meta.frontend.meta_description', $defaultSubDesc);
    }

    return $data;
}, 20, 2);


/**
 * Register Filament page under Media group
 */
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel): void {
    $viewsPath = base_path('plugins/media-defaults/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('media-defaults', $viewsPath);
    }

    $pageClass = \Plugins\MediaDefaults\Filament\Pages\MediaDefaults::class;

    if (!class_exists($pageClass)) {
        $file = base_path('plugins/media-defaults/src/Filament/Pages/MediaDefaults.php');
        if (is_file($file)) {
            require_once $file;
        }
    }

    if (class_exists($pageClass)) {
        $panel->pages([$pageClass]);
    }
});
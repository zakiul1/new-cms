<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Filament\Panel;

/**
 * ✅ Read HTML from editor value safely.
 * Editor state may sometimes be:
 * - string: "<p>...</p>"
 * - array:  ["html" => "<p>...</p>"]  (or other shapes)
 */
if (!function_exists('media_defaults_html_value')) {
    function media_defaults_html_value($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            $html = $value['html'] ?? $value['value'] ?? $value['content'] ?? '';
            return is_string($html) ? $html : '';
        }

        return '';
    }
}

/**
 * Detect "empty" HTML editor content:
 * - null / '' / whitespace
 * - <p><br></p>, <p></p>, etc.
 * - &nbsp;
 */
if (!function_exists('media_defaults_html_is_empty')) {
    function media_defaults_html_is_empty($value): bool
    {
        $html = trim(media_defaults_html_value($value));

        if ($html === '') {
            return true;
        }

        // Convert &nbsp; and other entities, remove tags
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text); // NBSP char
        $text = trim(strip_tags($text));

        return $text === '';
    }
}

/**
 * Normalize default text -> HTML:
 * - If user stored pure text (no tags), convert newlines to <br> and wrap in <p>
 * - If already HTML, keep it
 */
if (!function_exists('media_defaults_normalize_to_html')) {
    function media_defaults_normalize_to_html(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // If it contains HTML tags already, keep as-is
        if ($value !== strip_tags($value)) {
            return $value;
        }

        // Plain text -> <p> with <br>
        $escaped = e($value);
        $escaped = nl2br($escaped);

        return '<p>' . $escaped . '</p>';
    }
}

/**
 * Basic sanitizer:
 * Allow only a safe subset (same style you used in attachment blade)
 */
if (!function_exists('media_defaults_sanitize_html')) {
    function media_defaults_sanitize_html(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a>';

        return strip_tags($html, $allowed);
    }
}

/**
 * Auto-fill empty media fields when Edit Media loads
 */
add_filter('media.edit.defaults.fill', function (array $data, $record = null): array {
    $settings = app(Settings::class);
    $group = 'plugins.media-defaults';

    $defaultTitle = trim((string) $settings->get('default_title', '', $group));

    $defaultDescRaw = (string) $settings->get('default_description', '', $group);
    $defaultSubTitle = trim((string) $settings->get('default_sub_title', '', $group));
    $defaultSubDescRaw = (string) $settings->get('default_sub_description', '', $group);

    // Normalize + sanitize description defaults
    $defaultDesc = media_defaults_sanitize_html(media_defaults_normalize_to_html($defaultDescRaw));
    $defaultSubDesc = media_defaults_sanitize_html(media_defaults_normalize_to_html($defaultSubDescRaw));

    // Title (TextInput)
    if (!filled($data['title'] ?? null) && $defaultTitle !== '') {
        $data['title'] = $defaultTitle;
    }

    // Description (HTML editor) - may be string/array
    if (media_defaults_html_is_empty($data['description'] ?? null) && $defaultDesc !== '') {
        $data['description'] = $defaultDesc;
    }

    // Sub title (TextInput)
    if (!filled(data_get($data, 'meta.frontend.meta_title')) && $defaultSubTitle !== '') {
        data_set($data, 'meta.frontend.meta_title', $defaultSubTitle);
    }

    // ✅ Sub description (HTML editor) - FIXED (no string cast)
    $currentSubDescRaw = data_get($data, 'meta.frontend.meta_description');
    if (media_defaults_html_is_empty($currentSubDescRaw) && $defaultSubDesc !== '') {
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
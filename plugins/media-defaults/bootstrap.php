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
 * ✅ Get media category term id for a media record.
 * - Uses $media->categories() if it exists (your WP-like taxonomy system)
 * - Falls back to first term relation if needed
 */
if (!function_exists('media_defaults_get_media_category_id')) {
    function media_defaults_get_media_category_id($media): ?int
    {
        if (!$media) {
            return null;
        }

        try {
            // Preferred: categories() relationship (media_category taxonomy)
            if (method_exists($media, 'categories')) {
                $term = $media->categories()
                    ->orderBy('terms.name')
                    ->first();
                return $term?->id ? (int) $term->id : null;
            }

            // Fallback: terms() relationship if categories() doesn't exist
            if (method_exists($media, 'terms')) {
                $term = $media->terms()
                    ->orderBy('terms.name')
                    ->first();
                return $term?->id ? (int) $term->id : null;
            }
        } catch (\Throwable $e) {
            return null;
        }

        return null;
    }
}

/**
 * ✅ Resolve defaults for a given media record (category-wise with global fallback)
 *
 * Settings storage:
 * group: plugins.media-defaults
 * key: category_defaults = [
 *   "12" => [
 *     "default_title" => "...",
 *     "default_description" => "...",
 *     "default_sub_title" => "...",
 *     "default_sub_description" => "..."
 *   ],
 * ]
 */
if (!function_exists('media_defaults_resolve_for_media')) {
    function media_defaults_resolve_for_media($media): array
    {
        $settings = app(Settings::class);
        $group = 'plugins.media-defaults';

        $categoryDefaults = (array) $settings->get('category_defaults', [], $group);

        $categoryId = media_defaults_get_media_category_id($media);

        $cat = [];
        if ($categoryId !== null) {
            $cat = $categoryDefaults[(string) $categoryId] ?? [];
            $cat = is_array($cat) ? $cat : [];
        }

        // Global fallback values
        $globalTitle = trim((string) $settings->get('default_title', '', $group));
        $globalDescRaw = (string) $settings->get('default_description', '', $group);
        $globalSubTitle = trim((string) $settings->get('default_sub_title', '', $group));
        $globalSubDescRaw = (string) $settings->get('default_sub_description', '', $group);

        // Category overrides (if present), otherwise global
        $titleRaw = trim((string) ($cat['default_title'] ?? $globalTitle));
        $descRaw = (string) ($cat['default_description'] ?? $globalDescRaw);
        $subTitleRaw = trim((string) ($cat['default_sub_title'] ?? $globalSubTitle));
        $subDescRaw = (string) ($cat['default_sub_description'] ?? $globalSubDescRaw);

        // Normalize + sanitize HTML fields
        $desc = media_defaults_sanitize_html(media_defaults_normalize_to_html($descRaw));
        $subDesc = media_defaults_sanitize_html(media_defaults_normalize_to_html($subDescRaw));

        return [
            'category_id' => $categoryId,
            'default_title' => $titleRaw,
            'default_description' => $desc,
            'default_sub_title' => $subTitleRaw,
            'default_sub_description' => $subDesc,
        ];
    }
}

/**
 * ✅ Auto-fill empty media fields when Edit Media loads
 * (Now uses category-wise defaults based on the record's category)
 */
add_filter('media.edit.defaults.fill', function (array $data, $record = null): array {
    $defaults = media_defaults_resolve_for_media($record);

    $defaultTitle = trim((string) ($defaults['default_title'] ?? ''));
    $defaultDesc = (string) ($defaults['default_description'] ?? '');
    $defaultSubTitle = trim((string) ($defaults['default_sub_title'] ?? ''));
    $defaultSubDesc = (string) ($defaults['default_sub_description'] ?? '');

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

    // Sub description (HTML editor)
    $currentSubDescRaw = data_get($data, 'meta.frontend.meta_description');
    if (media_defaults_html_is_empty($currentSubDescRaw) && $defaultSubDesc !== '') {
        data_set($data, 'meta.frontend.meta_description', $defaultSubDesc);
    }

    return $data;
}, 20, 2);

/**
 * ✅ Auto-persist defaults on frontend (so you do NOT need to open/edit/save one-by-one)
 *
 * Call this hook with the Media record when rendering attachment page.
 * It fills ONLY missing fields and saves quietly.
 */
add_action('media.attachment.defaults.persist', function ($media): void {
    if (!$media) {
        return;
    }

    $defaults = media_defaults_resolve_for_media($media);

    $changed = false;

    // Title column
    if (!filled($media->title ?? null) && filled($defaults['default_title'] ?? null)) {
        $media->title = (string) $defaults['default_title'];
        $changed = true;
    }

    // Description column (HTML editor)
    if (media_defaults_html_is_empty($media->description ?? null) && filled($defaults['default_description'] ?? null)) {
        $media->description = (string) $defaults['default_description'];
        $changed = true;
    }

    // Meta fields (stored in $media->meta array)
    $meta = $media->meta ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    $currentMetaTitle = trim((string) data_get($meta, 'frontend.meta_title', ''));
    if ($currentMetaTitle === '' && filled($defaults['default_sub_title'] ?? null)) {
        data_set($meta, 'frontend.meta_title', (string) $defaults['default_sub_title']);
        $changed = true;
    }

    $currentMetaDescRaw = data_get($meta, 'frontend.meta_description', null);
    if (media_defaults_html_is_empty($currentMetaDescRaw) && filled($defaults['default_sub_description'] ?? null)) {
        data_set($meta, 'frontend.meta_description', (string) $defaults['default_sub_description']);
        $changed = true;
    }

    if ($changed) {
        $media->meta = $meta;

        // Save without triggering extra UI side-effects
        if (method_exists($media, 'saveQuietly')) {
            $media->saveQuietly();
        } else {
            $media->save();
        }
    }
}, 20, 1);

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
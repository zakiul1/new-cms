<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Filament\Panel;

/**
 * ✅ Read HTML from editor value safely.
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
 * Detect "empty" HTML editor content.
 */
if (!function_exists('media_defaults_html_is_empty')) {
    function media_defaults_html_is_empty($value): bool
    {
        $html = trim(media_defaults_html_value($value));

        if ($html === '') {
            return true;
        }

        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text); // NBSP char
        $text = trim(strip_tags($text));

        return $text === '';
    }
}

/**
 * Normalize default text -> HTML.
 */
if (!function_exists('media_defaults_normalize_to_html')) {
    function media_defaults_normalize_to_html(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if ($value !== strip_tags($value)) {
            return $value;
        }

        $escaped = e($value);
        $escaped = nl2br($escaped);

        return '<p>' . $escaped . '</p>';
    }
}

/**
 * Basic sanitizer.
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
 */
if (!function_exists('media_defaults_get_media_category_id')) {
    function media_defaults_get_media_category_id($media): ?int
    {
        if (!$media) {
            return null;
        }

        try {
            if (method_exists($media, 'categories')) {
                $term = $media->categories()
                    ->orderBy('terms.name')
                    ->first();

                return $term?->id ? (int) $term->id : null;
            }

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

        $globalTitle = trim((string) $settings->get('default_title', '', $group));
        $globalDescRaw = (string) $settings->get('default_description', '', $group);
        $globalSubTitle = trim((string) $settings->get('default_sub_title', '', $group));
        $globalSubDescRaw = (string) $settings->get('default_sub_description', '', $group);

        // Optional plugin-level assets (if you saved them)
        $globalCss = (string) $settings->get('default_assets_css', '', $group);
        $globalJs = (string) $settings->get('default_assets_js', '', $group);

        $titleRaw = trim((string) ($cat['default_title'] ?? $globalTitle));
        $descRaw = (string) ($cat['default_description'] ?? $globalDescRaw);
        $subTitleRaw = trim((string) ($cat['default_sub_title'] ?? $globalSubTitle));
        $subDescRaw = (string) ($cat['default_sub_description'] ?? $globalSubDescRaw);

        $desc = media_defaults_sanitize_html(media_defaults_normalize_to_html($descRaw));
        $subDesc = media_defaults_sanitize_html(media_defaults_normalize_to_html($subDescRaw));

        return [
            'category_id' => $categoryId,
            'default_title' => $titleRaw,
            'default_description' => $desc,
            'default_sub_title' => $subTitleRaw,
            'default_sub_description' => $subDesc,

            // optional
            'default_assets_css' => (string) $globalCss,
            'default_assets_js' => (string) $globalJs,
        ];
    }
}

/**
 * ✅ Frontend-only defaults application (NO DB SAVE)
 *
 * Theme calls:
 *   do_action('media.attachment.defaults.persist', $media);
 *
 * This fills ONLY missing values in-memory.
 */
add_action('media.attachment.defaults.persist', function ($media): void {
    if (!$media) {
        return;
    }

    /**
     * ✅ If preview mode, prefer session-driven state (realtime Filament preview)
     * Your Filament page stores it in session('media_defaults_preview_state')
     */
    $isPreview = request()->query('md_preview') === '1';

    if ($isPreview) {
        $state = session()->get('media_defaults_preview_state');

        if (is_array($state)) {
            $data = $state['data'] ?? null;
            $data = is_array($data) ? $data : [];

            // Title column
            if (!filled($media->title ?? null) && filled($data['default_title'] ?? null)) {
                $media->title = (string) $data['default_title'];
            }

            // Description column (HTML editor)
            if (media_defaults_html_is_empty($media->description ?? null) && filled($data['default_description'] ?? null)) {
                $media->description = media_defaults_sanitize_html(
                    media_defaults_normalize_to_html((string) $data['default_description'])
                );
            }

            // Meta fields
            $meta = $media->meta ?? [];
            if (!is_array($meta)) {
                $meta = [];
            }

            $currentMetaTitle = trim((string) data_get($meta, 'frontend.meta_title', ''));
            if ($currentMetaTitle === '' && filled($data['default_sub_title'] ?? null)) {
                data_set($meta, 'frontend.meta_title', (string) $data['default_sub_title']);
            }

            $currentMetaDescRaw = data_get($meta, 'frontend.meta_description', null);
            if (media_defaults_html_is_empty($currentMetaDescRaw) && filled($data['default_sub_description'] ?? null)) {
                $sub = media_defaults_sanitize_html(
                    media_defaults_normalize_to_html((string) $data['default_sub_description'])
                );
                data_set($meta, 'frontend.meta_description', $sub);
            }

            /**
             * ✅ Optional: inject CSS/JS into media meta assets in preview only
             * (so your controller can read meta.assets.css/js and output)
             */
            $css = (string) ($data['default_assets_css'] ?? '');
            $js = (string) ($data['default_assets_js'] ?? '');

            if ($css !== '') {
                data_set($meta, 'assets.css', $css);
            }
            if ($js !== '') {
                data_set($meta, 'assets.js', $js);
            }

            $media->meta = $meta;

            return; // ✅ stop here (preview overrides DB settings)
        }
        // if session missing, fall through to normal resolve
    }

    // ✅ Normal mode: resolve from settings (category+global)
    $defaults = media_defaults_resolve_for_media($media);

    if (!filled($media->title ?? null) && filled($defaults['default_title'] ?? null)) {
        $media->title = (string) $defaults['default_title'];
    }

    if (media_defaults_html_is_empty($media->description ?? null) && filled($defaults['default_description'] ?? null)) {
        $media->description = (string) $defaults['default_description'];
    }

    $meta = $media->meta ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    $currentMetaTitle = trim((string) data_get($meta, 'frontend.meta_title', ''));
    if ($currentMetaTitle === '' && filled($defaults['default_sub_title'] ?? null)) {
        data_set($meta, 'frontend.meta_title', (string) $defaults['default_sub_title']);
    }

    $currentMetaDescRaw = data_get($meta, 'frontend.meta_description', null);
    if (media_defaults_html_is_empty($currentMetaDescRaw) && filled($defaults['default_sub_description'] ?? null)) {
        data_set($meta, 'frontend.meta_description', (string) $defaults['default_sub_description']);
    }

    $media->meta = $meta;

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
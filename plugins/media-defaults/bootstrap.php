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

        // Optional plugin-level assets
        $globalCss = (string) $settings->get('default_assets_css', '', $group);
        $globalJs = (string) $settings->get('default_assets_js', '', $group);

        // ✅ Default JSON (array|null), global fallback
        $globalCustomJson = $settings->get('default_custom_json', null, $group);

        // ✅ Global SEO defaults
        $globalSeoTitle = trim((string) $settings->get('default_seo_title', '', $group));
        $globalSeoDesc = trim((string) $settings->get('default_seo_description', '', $group));
        $globalSeoCanonical = trim((string) $settings->get('default_seo_canonical', '', $group));
        $globalSeoRobots = trim((string) $settings->get('default_seo_robots', '', $group));
        $globalSeoOgImage = trim((string) $settings->get('default_seo_og_image', '', $group));

        $titleRaw = trim((string) ($cat['default_title'] ?? $globalTitle));
        $descRaw = (string) ($cat['default_description'] ?? $globalDescRaw);
        $subTitleRaw = trim((string) ($cat['default_sub_title'] ?? $globalSubTitle));
        $subDescRaw = (string) ($cat['default_sub_description'] ?? $globalSubDescRaw);

        $desc = media_defaults_sanitize_html(media_defaults_normalize_to_html($descRaw));
        $subDesc = media_defaults_sanitize_html(media_defaults_normalize_to_html($subDescRaw));

        // ✅ category-wise json, fallback to global
        $defaultCustomJson = $cat['default_custom_json'] ?? $globalCustomJson;

        // ✅ category-wise SEO, fallback to global SEO
        $defaultSeo = [
            'title' => trim((string) ($cat['default_seo_title'] ?? $globalSeoTitle)),
            'description' => trim((string) ($cat['default_seo_description'] ?? $globalSeoDesc)),
            'canonical' => trim((string) ($cat['default_seo_canonical'] ?? $globalSeoCanonical)),
            'robots' => trim((string) ($cat['default_seo_robots'] ?? $globalSeoRobots)),
            'og_image' => trim((string) ($cat['default_seo_og_image'] ?? $globalSeoOgImage)),
        ];

        return [
            'category_id' => $categoryId,
            'default_title' => $titleRaw,
            'default_description' => $desc,
            'default_sub_title' => $subTitleRaw,
            'default_sub_description' => $subDesc,

            'default_assets_css' => (string) $globalCss,
            'default_assets_js' => (string) $globalJs,

            'default_custom_json' => $defaultCustomJson, // array|null

            // ✅ NEW
            'default_seo' => $defaultSeo,
        ];
    }
}

/**
 * ✅ Helper: apply SEO defaults into $meta['seo'] only when empty
 */
if (!function_exists('media_defaults_apply_seo_defaults')) {
    function media_defaults_apply_seo_defaults(array &$meta, array $seoDefaults): void
    {
        $seoDefaults = is_array($seoDefaults) ? $seoDefaults : [];

        $seo = data_get($meta, 'seo', []);
        $seo = is_array($seo) ? $seo : [];

        $seoTitle = trim((string) data_get($seo, 'title', ''));
        if ($seoTitle === '' && filled($seoDefaults['title'] ?? null)) {
            data_set($seo, 'title', (string) $seoDefaults['title']);
        }

        $seoDesc = trim((string) data_get($seo, 'description', ''));
        if ($seoDesc === '' && filled($seoDefaults['description'] ?? null)) {
            data_set($seo, 'description', (string) $seoDefaults['description']);
        }

        $seoCanonical = trim((string) data_get($seo, 'canonical', ''));
        if ($seoCanonical === '' && filled($seoDefaults['canonical'] ?? null)) {
            data_set($seo, 'canonical', (string) $seoDefaults['canonical']);
        }

        $seoRobots = trim((string) data_get($seo, 'robots', ''));
        if ($seoRobots === '' && filled($seoDefaults['robots'] ?? null)) {
            data_set($seo, 'robots', (string) $seoDefaults['robots']);
        }

        $seoOg = trim((string) data_get($seo, 'og_image', ''));
        if ($seoOg === '' && filled($seoDefaults['og_image'] ?? null)) {
            data_set($seo, 'og_image', (string) $seoDefaults['og_image']);
        }

        data_set($meta, 'seo', $seo);
    }
}

/**
 * ✅ Frontend-only defaults application (NO DB SAVE)
 */
add_action('media.attachment.defaults.persist', function ($media): void {
    if (!$media) {
        return;
    }

    $isPreview = request()->query('md_preview') === '1';

    // ✅ PREVIEW MODE (session-driven)
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

            // Optional preview CSS/JS
            $css = (string) ($data['default_assets_css'] ?? '');
            $js = (string) ($data['default_assets_js'] ?? '');

            if ($css !== '') {
                data_set($meta, 'assets.css', $css);
            }
            if ($js !== '') {
                data_set($meta, 'assets.js', $js);
            }

            // Custom JSON fallback
            $existingJson = data_get($meta, 'custom_json', null);
            $legacyJson = data_get($meta, 'frontend.custom_json', null);

            if (blank($existingJson) && blank($legacyJson) && !blank($data['default_custom_json'] ?? null)) {
                data_set($meta, 'custom_json', $data['default_custom_json']);
            }

            // ✅ SEO defaults in preview mode
            $seoDefaultsPreview = [
                'title' => trim((string) ($data['default_seo_title'] ?? '')),
                'description' => trim((string) ($data['default_seo_description'] ?? '')),
                'canonical' => trim((string) ($data['default_seo_canonical'] ?? '')),
                'robots' => trim((string) ($data['default_seo_robots'] ?? '')),
                'og_image' => trim((string) ($data['default_seo_og_image'] ?? '')),
            ];
            media_defaults_apply_seo_defaults($meta, $seoDefaultsPreview);

            $media->meta = $meta;

            return; // ✅ stop here in preview mode
        }
        // if session missing, fall through
    }

    // ✅ NORMAL MODE (resolve from settings)
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

    // Custom JSON fallback
    $existingJson = data_get($meta, 'custom_json', null);
    $legacyJson = data_get($meta, 'frontend.custom_json', null);

    if (blank($existingJson) && blank($legacyJson) && !blank($defaults['default_custom_json'] ?? null)) {
        data_set($meta, 'custom_json', $defaults['default_custom_json']);
    }

    // ✅ SEO defaults in normal mode
    $seoDefaults = $defaults['default_seo'] ?? [];
    media_defaults_apply_seo_defaults($meta, is_array($seoDefaults) ? $seoDefaults : []);

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
<?php

use App\Cms\Core\Settings;
use App\Cms\Hooks\HookPoints;
use Filament\Panel;

// Ensure SiatexTag model exists (plugins are not composer-autoloaded)
if (!class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
    $file = base_path('plugins/siatex-tags/src/Models/SiatexTag.php');
    if (is_file($file)) {
        require_once $file;
    }
}

/**
 * ✅ Read HTML from editor value safely.
 */
if (!function_exists('tag_defaults_html_value')) {
    function tag_defaults_html_value($value): string
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
if (!function_exists('tag_defaults_html_is_empty')) {
    function tag_defaults_html_is_empty($value): bool
    {
        $html = trim(tag_defaults_html_value($value));

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
if (!function_exists('tag_defaults_normalize_to_html')) {
    function tag_defaults_normalize_to_html(string $value): string
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
if (!function_exists('tag_defaults_sanitize_html')) {
    function tag_defaults_sanitize_html(string $html): string
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
 * ✅ Resolve defaults for Siatex Tag (global only)
 */
if (!function_exists('tag_defaults_resolve')) {
    function tag_defaults_resolve(): array
    {
        $settings = app(Settings::class);
        $group = 'plugins.tag-defaults';

        $globalTitle = trim((string) $settings->get('default_title', '', $group));
        $globalDescRaw = (string) $settings->get('default_description', '', $group);
        $globalSubTitle = trim((string) $settings->get('default_sub_title', '', $group));
        $globalSubDescRaw = (string) $settings->get('default_sub_description', '', $group);

        // Optional plugin-level assets
        $globalCss = (string) $settings->get('default_assets_css', '', $group);
        $globalJs = (string) $settings->get('default_assets_js', '', $group);

        // JSON (array|null)
        $globalCustomJson = $settings->get('default_custom_json', null, $group);

        // SEO defaults
        $defaultSeo = [
            'title' => trim((string) $settings->get('default_seo_title', '', $group)),
            'description' => trim((string) $settings->get('default_seo_description', '', $group)),
            'canonical' => trim((string) $settings->get('default_seo_canonical', '', $group)),
            'robots' => trim((string) $settings->get('default_seo_robots', '', $group)),
            'og_image' => trim((string) $settings->get('default_seo_og_image', '', $group)),
        ];

        $desc = tag_defaults_sanitize_html(tag_defaults_normalize_to_html($globalDescRaw));
        $subDesc = tag_defaults_sanitize_html(tag_defaults_normalize_to_html($globalSubDescRaw));

        return [
            'default_title' => $globalTitle,
            'default_description' => $desc,
            'default_sub_title' => $globalSubTitle,
            'default_sub_description' => $subDesc,

            'default_assets_css' => (string) $globalCss,
            'default_assets_js' => (string) $globalJs,

            'default_custom_json' => $globalCustomJson,
            'default_seo' => $defaultSeo,
        ];
    }
}

/**
 * ✅ Helper: apply SEO defaults into $meta['seo'] only when empty
 */
if (!function_exists('tag_defaults_apply_seo_defaults')) {
    function tag_defaults_apply_seo_defaults(array &$meta, array $seoDefaults): void
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
 * ✅ Frontend-only defaults application for Siatex Tag (NO DB SAVE)
 */
add_action('siatex.tag.defaults.persist', function ($tag): void {
    if (!$tag) {
        return;
    }

    $isPreview = request()->query('td_preview') === '1';

    // ✅ PREVIEW MODE (session-driven)
    if ($isPreview) {
        $state = session()->get('tag_defaults_preview_state');

        if (is_array($state)) {
            $data = $state['data'] ?? null;
            $data = is_array($data) ? $data : [];

            // Title
            if (!filled($tag->title ?? null) && filled($data['default_title'] ?? null)) {
                $tag->title = (string) $data['default_title'];
            }

            // Content (HTML editor stored in content_json.html)
            $content = $tag->content_json ?? [];
            if (!is_array($content)) {
                $content = [];
            }
            $currentContentHtml = $content['html'] ?? null;
            if (tag_defaults_html_is_empty($currentContentHtml) && filled($data['default_description'] ?? null)) {
                $content['html'] = tag_defaults_sanitize_html(
                    tag_defaults_normalize_to_html((string) $data['default_description'])
                );
                $tag->content_json = $content;
            }

            // Meta
            $meta = $tag->meta_json ?? [];
            if (!is_array($meta)) {
                $meta = [];
            }

            $currentSubTitle = trim((string) data_get($meta, 'subtitle', ''));
            if ($currentSubTitle === '' && filled($data['default_sub_title'] ?? null)) {
                data_set($meta, 'subtitle', (string) $data['default_sub_title']);
            }

            $currentSubDescRaw = data_get($meta, 'sub_description', null);
            if (tag_defaults_html_is_empty($currentSubDescRaw) && filled($data['default_sub_description'] ?? null)) {
                $sub = tag_defaults_sanitize_html(
                    tag_defaults_normalize_to_html((string) $data['default_sub_description'])
                );
                data_set($meta, 'sub_description', $sub);
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
            if (blank($existingJson) && !blank($data['default_custom_json'] ?? null)) {
                data_set($meta, 'custom_json', $data['default_custom_json']);
            }

            // SEO defaults in preview mode
            $seoDefaultsPreview = [
                'title' => trim((string) ($data['default_seo_title'] ?? '')),
                'description' => trim((string) ($data['default_seo_description'] ?? '')),
                'canonical' => trim((string) ($data['default_seo_canonical'] ?? '')),
                'robots' => trim((string) ($data['default_seo_robots'] ?? '')),
                'og_image' => trim((string) ($data['default_seo_og_image'] ?? '')),
            ];
            tag_defaults_apply_seo_defaults($meta, $seoDefaultsPreview);

            $tag->meta_json = $meta;
            return;
        }
        // if session missing, fall through
    }

    // ✅ NORMAL MODE (resolve from settings)
    $defaults = tag_defaults_resolve();

    if (!filled($tag->title ?? null) && filled($defaults['default_title'] ?? null)) {
        $tag->title = (string) $defaults['default_title'];
    }

    $content = $tag->content_json ?? [];
    if (!is_array($content)) {
        $content = [];
    }
    $currentContentHtml = $content['html'] ?? null;
    if (tag_defaults_html_is_empty($currentContentHtml) && filled($defaults['default_description'] ?? null)) {
        $content['html'] = (string) $defaults['default_description'];
        $tag->content_json = $content;
    }

    $meta = $tag->meta_json ?? [];
    if (!is_array($meta)) {
        $meta = [];
    }

    $currentSubTitle = trim((string) data_get($meta, 'subtitle', ''));
    if ($currentSubTitle === '' && filled($defaults['default_sub_title'] ?? null)) {
        data_set($meta, 'subtitle', (string) $defaults['default_sub_title']);
    }

    $currentSubDescRaw = data_get($meta, 'sub_description', null);
    if (tag_defaults_html_is_empty($currentSubDescRaw) && filled($defaults['default_sub_description'] ?? null)) {
        data_set($meta, 'sub_description', (string) $defaults['default_sub_description']);
    }

    // Custom JSON fallback
    $existingJson = data_get($meta, 'custom_json', null);
    if (blank($existingJson) && !blank($defaults['default_custom_json'] ?? null)) {
        data_set($meta, 'custom_json', $defaults['default_custom_json']);
    }

    // SEO defaults in normal mode
    $seoDefaults = $defaults['default_seo'] ?? [];
    tag_defaults_apply_seo_defaults($meta, is_array($seoDefaults) ? $seoDefaults : []);

    $tag->meta_json = $meta;

}, 20, 1);

/**
 * Register Filament page under Media group
 */
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel): void {
    $viewsPath = base_path('plugins/tag-defaults/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('tag-defaults', $viewsPath);
    }

    $pageClass = \Plugins\TagDefaults\Filament\Pages\TagDefaults::class;

    if (!class_exists($pageClass)) {
        $file = base_path('plugins/tag-defaults/src/Filament/Pages/TagDefaults.php');
        if (is_file($file)) {
            require_once $file;
        }
    }

    if (class_exists($pageClass)) {
        $panel->pages([$pageClass]);
    }
});
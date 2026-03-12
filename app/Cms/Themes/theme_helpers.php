<?php

use App\Cms\Core\Settings;
use App\Cms\Themes\ThemeManager;
use App\Models\Media;

if (!function_exists('theme_options')) {
    function theme_options(?string $slug = null): array
    {
        /** @var ThemeManager $tm */
        $tm = app(ThemeManager::class);

        $previewSlug = (string) request()->query('preview_theme', '');
        if ($slug === null && $previewSlug !== '' && auth()->check()) {
            $slug = $previewSlug;
        }

        $slug ??= $tm->activeSlug();

        /** @var Settings $settings */
        $settings = app(Settings::class);

        $saved = $settings->get("theme_options.{$slug}", []);
        $saved = is_array($saved) ? $saved : [];

        $draft = [];
        $useDraft = request()->boolean('customizer') && auth()->check();
        if ($useDraft) {
            $draft = session()->get("theme_customizer.draft.{$slug}", []);
            $draft = is_array($draft) ? $draft : [];
        }

        $siteName = (string) $settings->get('site_name', config('app.name', 'My CMS'), 'core');
        $homepageId = $settings->get('homepage_page_id', null, 'core');
        $defaultFont = theme_default_font_family($slug);

        $defaults = [
            'site_identity' => [
                'site_title' => $siteName,
                'tagline' => '',
                'site_icon_media_id' => null,
                'logo_media_id' => null,
                'logo_width' => 200,
            ],
            'typography' => [
                'headings' => [
                    'font_family' => $defaultFont,
                    'font_weight' => '700',
                    'text_transform' => 'none',
                    'line_height' => '1.2',
                    'letter_spacing' => '0',
                    'color' => '',
                    'h1_size' => '48px',
                    'h2_size' => '40px',
                    'h3_size' => '32px',
                    'h4_size' => '28px',
                    'h5_size' => '24px',
                    'h6_size' => '20px',
                ],
                'strong' => [
                    'font_family' => $defaultFont,
                    'font_weight' => '700',
                    'color' => '',
                ],
                'paragraph' => [
                    'font_family' => $defaultFont,
                    'font_size' => '16px',
                    'font_weight' => '400',
                    'line_height' => '1.7',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'list' => [
                    'font_family' => $defaultFont,
                    'font_size' => '16px',
                    'line_height' => '1.7',
                    'color' => '',
                ],
                'anchor' => [
                    'font_family' => $defaultFont,
                    'color' => '#0f5e9c',
                    'hover_color' => '#0b4c80',
                    'text_decoration' => 'underline',
                ],
            ],
            'homepage' => [
                'mode' => $homepageId ? 'static_page' : 'latest_posts',
                'page_id' => $homepageId ? (int) $homepageId : null,
            ],
            'footer' => [
                'background_color' => '#ffffff',
                'text_color' => '#111827',
                'before_copyright' => '',
                'copyright_area' => '[Y] Your Garments Manufacturing Company. All rights reserved.',
                'second_line' => 'Production Base: Bangladesh | Operations: Canada',
                'text_alignment' => 'center',
                'heading' => '',
                'description' => '',
                'button_text' => '',
                'button_url' => '',
                'show_menu' => true,
                'show_widgets' => true,
                'show_bottom_content' => true,
            ],
            'additional_css' => '',
            'appearance' => [
                'header_layout' => 'left',
                'header_sticky' => true,
                'background' => '#ffffff',
                'text' => '#111827',
                'primary' => '#2f6fa3',
                'accent' => '#0ea5e9',
                'container_width' => 'default',
                'rounded' => true,
                'shadows' => true,
            ],
        ];

        $options = array_replace_recursive($defaults, $saved, $draft);

        $logoWidth = (int) data_get($options, 'site_identity.logo_width', 200);
        $logoWidth = max(20, min(600, $logoWidth));
        data_set($options, 'site_identity.logo_width', $logoWidth);

        foreach ([
            'site_identity.logo_media_id',
            'site_identity.site_icon_media_id',
            'homepage.page_id',
        ] as $key) {
            $value = data_get($options, $key);

            if ($value === '' || $value === false) {
                data_set($options, $key, null);
                continue;
            }

            if (is_numeric($value)) {
                data_set($options, $key, (int) $value);
            }
        }

        foreach ([
            'footer.show_menu',
            'footer.show_widgets',
            'footer.show_bottom_content',
            'appearance.header_sticky',
            'appearance.rounded',
            'appearance.shadows',
        ] as $key) {
            data_set($options, $key, (bool) data_get($options, $key, false));
        }

        return $options;
    }
}

if (!function_exists('theme_fonts_dir')) {
    function theme_fonts_dir(?string $slug = null): ?string
    {
        /** @var ThemeManager $tm */
        $tm = app(ThemeManager::class);

        $slug ??= $tm->activeSlug();

        $candidates = [
            public_path("themes/{$slug}/fonts"),
            public_path("themes/{$slug}/public/fonts"),
            base_path("themes/{$slug}/public/fonts"),
        ];

        foreach ($candidates as $path) {
            if (is_dir($path)) {
                return $path;
            }
        }

        return null;
    }
}

if (!function_exists('theme_fonts_base_url')) {
    function theme_fonts_base_url(?string $slug = null): ?string
    {
        /** @var ThemeManager $tm */
        $tm = app(ThemeManager::class);

        $slug ??= $tm->activeSlug();

        if (is_dir(public_path("themes/{$slug}/fonts"))) {
            return asset("themes/{$slug}/fonts");
        }

        if (is_dir(public_path("themes/{$slug}/public/fonts"))) {
            return asset("themes/{$slug}/public/fonts");
        }

        return null;
    }
}

if (!function_exists('theme_guess_font_family_from_filename')) {
    function theme_guess_font_family_from_filename(string $basename): string
    {
        $name = preg_replace('/[-_](thin|extralight|ultralight|light|regular|normal|book|medium|semibold|demibold|bold|extrabold|ultrabold|black|heavy|italic|oblique|variable|vf)$/i', '', $basename);
        $name = preg_replace('/(?<=\D)(\d{3})(?=$)/', '', (string) $name);
        $name = str_replace(['-', '_'], ' ', (string) $name);
        $name = trim((string) preg_replace('/\s+/', ' ', (string) $name));

        return $name !== '' ? $name : $basename;
    }
}

if (!function_exists('theme_guess_font_face_meta')) {
    function theme_guess_font_face_meta(string $basename): array
    {
        $name = strtolower($basename);

        $style = str_contains($name, 'italic') || str_contains($name, 'oblique')
            ? 'italic'
            : 'normal';

        $weight = '400';

        if (preg_match('/(^|[^0-9])(100|200|300|400|500|600|700|800|900)([^0-9]|$)/', $name, $matches)) {
            $weight = $matches[2];
        } elseif (str_contains($name, 'thin')) {
            $weight = '100';
        } elseif (str_contains($name, 'extralight') || str_contains($name, 'ultralight')) {
            $weight = '200';
        } elseif (str_contains($name, 'light')) {
            $weight = '300';
        } elseif (str_contains($name, 'regular') || str_contains($name, 'normal') || str_contains($name, 'book')) {
            $weight = '400';
        } elseif (str_contains($name, 'medium')) {
            $weight = '500';
        } elseif (str_contains($name, 'semibold') || str_contains($name, 'demibold')) {
            $weight = '600';
        } elseif (str_contains($name, 'bold')) {
            $weight = '700';
        } elseif (str_contains($name, 'extrabold') || str_contains($name, 'ultrabold')) {
            $weight = '800';
        } elseif (str_contains($name, 'black') || str_contains($name, 'heavy')) {
            $weight = '900';
        }

        return [
            'weight' => $weight,
            'style' => $style,
        ];
    }
}

if (!function_exists('theme_font_registry')) {
    function theme_font_registry(?string $slug = null): array
    {
        $fontsDir = theme_fonts_dir($slug);
        $baseUrl = theme_fonts_base_url($slug);

        if (!$fontsDir || !$baseUrl) {
            return [];
        }

        $allowedExtensions = ['woff2', 'woff', 'ttf', 'otf'];
        $registry = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fontsDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = ltrim(str_replace('\\', '/', substr($absolutePath, strlen($fontsDir))), '/');

            if ($relativePath === '') {
                continue;
            }

            $segments = explode('/', $relativePath);
            $basename = pathinfo($relativePath, PATHINFO_FILENAME);

            $family = count($segments) > 1
                ? trim($segments[0])
                : theme_guess_font_family_from_filename($basename);

            if ($family === '') {
                continue;
            }

            $meta = theme_guess_font_face_meta($basename);
            $faceKey = $family . '|' . $meta['weight'] . '|' . $meta['style'];

            if (!isset($registry[$family])) {
                $registry[$family] = [
                    'family' => $family,
                    'stack' => "'" . str_replace("'", "\\'", $family) . "', sans-serif",
                    'faces' => [],
                ];
            }

            if (!isset($registry[$family]['faces'][$faceKey])) {
                $registry[$family]['faces'][$faceKey] = [
                    'weight' => $meta['weight'],
                    'style' => $meta['style'],
                ];
            }

            $registry[$family]['faces'][$faceKey][$extension] = rtrim($baseUrl, '/') . '/' . $relativePath;
        }

        foreach ($registry as $family => $font) {
            $faces = array_values($font['faces']);

            usort($faces, function (array $a, array $b): int {
                $weightCompare = (int) ($a['weight'] ?? 400) <=> (int) ($b['weight'] ?? 400);
                if ($weightCompare !== 0) {
                    return $weightCompare;
                }

                return strcmp((string) ($a['style'] ?? 'normal'), (string) ($b['style'] ?? 'normal'));
            });

            $registry[$family]['faces'] = $faces;
        }

        ksort($registry, SORT_NATURAL | SORT_FLAG_CASE);

        return $registry;
    }
}

if (!function_exists('theme_font_choices')) {
    function theme_font_choices(?string $slug = null): array
    {
        $registry = theme_font_registry($slug);

        return collect($registry)
            ->mapWithKeys(fn(array $font, string $family) => [$family => $family])
            ->all();
    }
}

if (!function_exists('theme_default_font_family')) {
    function theme_default_font_family(?string $slug = null): string
    {
        $choices = theme_font_choices($slug);

        return array_key_first($choices) ?? '';
    }
}

if (!function_exists('theme_font_stack')) {
    function theme_font_stack(?string $fontFamily, ?string $slug = null): string
    {
        $fontFamily = trim((string) $fontFamily);
        $registry = theme_font_registry($slug);

        if ($fontFamily !== '' && isset($registry[$fontFamily]['stack'])) {
            return (string) $registry[$fontFamily]['stack'];
        }

        return 'sans-serif';
    }
}

if (!function_exists('theme_custom_fonts_css')) {
    function theme_custom_fonts_css(?string $slug = null): string
    {
        $registry = theme_font_registry($slug);
        $options = theme_options($slug);

        $headingFamily = trim((string) data_get($options, 'typography.headings.font_family', ''));
        $headingWeight = trim((string) data_get($options, 'typography.headings.font_weight', '700'));

        $bodyFamily = trim((string) data_get($options, 'typography.paragraph.font_family', ''));
        $bodyWeight = trim((string) data_get($options, 'typography.paragraph.font_weight', '400'));

        $strongFamily = trim((string) data_get($options, 'typography.strong.font_family', ''));
        $strongWeight = trim((string) data_get($options, 'typography.strong.font_weight', '700'));

        $anchorFamily = trim((string) data_get($options, 'typography.anchor.font_family', ''));

        $requiredFamilies = array_values(array_unique(array_filter([
            $headingFamily,
            $bodyFamily,
            $strongFamily,
            $anchorFamily,
        ])));

        $requiredWeightsByFamily = [];

        foreach ($requiredFamilies as $family) {
            $requiredWeightsByFamily[$family] = ['400'];
        }

        if ($headingFamily !== '') {
            $requiredWeightsByFamily[$headingFamily][] = $headingWeight !== '' ? $headingWeight : '700';
        }

        if ($bodyFamily !== '') {
            $requiredWeightsByFamily[$bodyFamily][] = $bodyWeight !== '' ? $bodyWeight : '400';
        }

        if ($strongFamily !== '') {
            $requiredWeightsByFamily[$strongFamily][] = $strongWeight !== '' ? $strongWeight : '700';
        }

        if ($anchorFamily !== '') {
            $requiredWeightsByFamily[$anchorFamily][] = '400';
        }

        foreach ($requiredWeightsByFamily as $family => $weights) {
            $requiredWeightsByFamily[$family] = array_values(array_unique(array_filter($weights)));
        }

        $css = '';

        foreach ($requiredFamilies as $family) {
            $font = $registry[$family] ?? null;

            if (!is_array($font)) {
                continue;
            }

            $faces = $font['faces'] ?? [];
            if (!is_array($faces) || $faces === []) {
                continue;
            }

            $neededWeights = $requiredWeightsByFamily[$family] ?? ['400'];

            foreach ($faces as $face) {
                $weight = trim((string) ($face['weight'] ?? '400'));
                $style = trim((string) ($face['style'] ?? 'normal'));

                if (!in_array($weight, $neededWeights, true)) {
                    continue;
                }

                if ($style !== 'normal') {
                    continue;
                }

                $sources = [];

                foreach (['woff2', 'woff', 'ttf', 'otf'] as $extension) {
                    $url = trim((string) ($face[$extension] ?? ''));

                    if ($url === '') {
                        continue;
                    }

                    $format = match ($extension) {
                        'woff2' => 'woff2',
                        'woff' => 'woff',
                        'ttf' => 'truetype',
                        'otf' => 'opentype',
                        default => $extension,
                    };

                    $sources[] = "url('{$url}') format('{$format}')";
                }

                if ($sources === []) {
                    continue;
                }

                $src = implode(",\n       ", $sources);

                $css .= <<<CSS
@font-face{
  font-family:'{$family}';
  src: {$src};
  font-weight: {$weight};
  font-style: {$style};
  font-display: optional;
}

CSS;
            }
        }

        if ($css === '') {
            return '';
        }

        return "<style>\n{$css}</style>";
    }
}

if (!function_exists('theme_media_url')) {
    function theme_media_url(?int $mediaId, string $preferredVariant = 'medium'): ?string
    {
        $mediaId = is_numeric($mediaId) ? (int) $mediaId : 0;

        if ($mediaId <= 0) {
            return null;
        }

        $media = Media::query()
            ->with('variantRecords')
            ->whereKey($mediaId)
            ->first();

        if (!$media) {
            return null;
        }

        if (method_exists($media, 'variantUrl')) {
            $variantUrl = $media->variantUrl($preferredVariant);
            if ($variantUrl) {
                return $variantUrl;
            }
        }

        return method_exists($media, 'url') ? $media->url() : null;
    }
}

if (!function_exists('theme_logo_url')) {
    function theme_logo_url(?string $slug = null): ?string
    {
        $o = theme_options($slug);
        $mediaId = data_get($o, 'site_identity.logo_media_id');

        return theme_media_url(is_numeric($mediaId) ? (int) $mediaId : null, 'medium');
    }
}

if (!function_exists('theme_favicon_url')) {
    function theme_favicon_url(?string $slug = null): ?string
    {
        $o = theme_options($slug);
        $mediaId = data_get($o, 'site_identity.site_icon_media_id');

        return theme_media_url(is_numeric($mediaId) ? (int) $mediaId : null, 'medium');
    }
}

if (!function_exists('theme_favicon_tag')) {
    function theme_favicon_tag(?string $slug = null): string
    {
        $faviconUrl = theme_favicon_url($slug);

        if (!$faviconUrl) {
            return '';
        }

        $escaped = e($faviconUrl);

        return <<<HTML
<link rel="icon" href="{$escaped}" sizes="any">
<link rel="shortcut icon" href="{$escaped}">
<link rel="apple-touch-icon" href="{$escaped}">
HTML;
    }
}

if (!function_exists('theme_logo_width')) {
    function theme_logo_width(?string $slug = null): int
    {
        $o = theme_options($slug);
        $width = (int) data_get($o, 'site_identity.logo_width', 200);

        return max(20, min(600, $width));
    }
}

if (!function_exists('theme_site_title')) {
    function theme_site_title(?string $slug = null): string
    {
        $o = theme_options($slug);

        return (string) data_get($o, 'site_identity.site_title', config('app.name', 'My CMS'));
    }
}

if (!function_exists('theme_tagline')) {
    function theme_tagline(?string $slug = null): string
    {
        $o = theme_options($slug);

        return (string) data_get($o, 'site_identity.tagline', '');
    }
}

if (!function_exists('theme_footer_tokens')) {
    function theme_footer_tokens(string $text, ?string $slug = null): string
    {
        $siteName = theme_site_title($slug);
        $year = date('Y');
        $sitemapUrl = url('/sitemap.xml');

        return str_replace(
            ['[name]', '[Y]', '[sitemap]'],
            [$siteName, $year, '<a href="' . e($sitemapUrl) . '">Sitemap</a>'],
            $text
        );
    }
}

if (!function_exists('theme_footer_data')) {
    function theme_footer_data(?string $slug = null): array
    {
        $o = theme_options($slug);

        $before = (string) data_get($o, 'footer.before_copyright', '');
        $copyright = (string) data_get($o, 'footer.copyright_area', '');
        $secondLine = (string) data_get($o, 'footer.second_line', '');

        return [
            'before_copyright' => $before,
            'copyright_html' => theme_footer_tokens($copyright, $slug),
            'second_line' => $secondLine,
            'text_alignment' => (string) data_get($o, 'footer.text_alignment', 'center'),
            'background_color' => (string) data_get($o, 'footer.background_color', '#ffffff'),
            'text_color' => (string) data_get($o, 'footer.text_color', '#111827'),
            'heading' => (string) data_get($o, 'footer.heading', ''),
            'description' => (string) data_get($o, 'footer.description', ''),
            'button_text' => (string) data_get($o, 'footer.button_text', ''),
            'button_url' => (string) data_get($o, 'footer.button_url', ''),
            'show_menu' => (bool) data_get($o, 'footer.show_menu', true),
            'show_widgets' => (bool) data_get($o, 'footer.show_widgets', true),
            'show_bottom_content' => (bool) data_get($o, 'footer.show_bottom_content', true),
        ];
    }
}

if (!function_exists('theme_customizer_css')) {
    function theme_customizer_css(?string $slug = null): string
    {
        $o = theme_options($slug);

        $appearance = $o['appearance'] ?? [];
        $typography = $o['typography'] ?? [];
        $footer = $o['footer'] ?? [];
        $siteIdentity = $o['site_identity'] ?? [];

        $bg = $appearance['background'] ?? '#ffffff';
        $text = $appearance['text'] ?? '#111827';
        $primary = $appearance['primary'] ?? '#2f6fa3';
        $accent = $appearance['accent'] ?? '#0ea5e9';

        $container = $appearance['container_width'] ?? 'default';
        $containerMax = match ($container) {
            'full' => '100%',
            'wide' => '1280px',
            default => '1140px',
        };

        $rounded = !empty($appearance['rounded']) ? '14px' : '0px';
        $shadow = !empty($appearance['shadows']) ? '0 10px 30px rgba(0,0,0,.08)' : 'none';

        $h = $typography['headings'] ?? [];
        $p = $typography['paragraph'] ?? [];
        $l = $typography['list'] ?? [];
        $s = $typography['strong'] ?? [];
        $a = $typography['anchor'] ?? [];

        $footerBg = $footer['background_color'] ?? '#ffffff';
        $footerText = $footer['text_color'] ?? '#111827';
        $footerAlign = $footer['text_alignment'] ?? 'center';

        $logoWidth = (int) ($siteIdentity['logo_width'] ?? 200);
        $logoWidth = max(20, min(600, $logoWidth));

        $hFontFamily = theme_font_stack($h['font_family'] ?? '', $slug);
        $hFontWeight = $h['font_weight'] ?? '700';
        $hTextTransform = $h['text_transform'] ?? 'none';
        $hLineHeight = $h['line_height'] ?? '1.2';
        $hLetterSpacing = $h['letter_spacing'] ?? '0';
        $hColor = !empty($h['color']) ? $h['color'] : 'inherit';
        $h1Size = $h['h1_size'] ?? '48px';
        $h2Size = $h['h2_size'] ?? '40px';
        $h3Size = $h['h3_size'] ?? '32px';
        $h4Size = $h['h4_size'] ?? '28px';
        $h5Size = $h['h5_size'] ?? '24px';
        $h6Size = $h['h6_size'] ?? '20px';

        $pFontFamily = theme_font_stack($p['font_family'] ?? '', $slug);
        $pFontSize = $p['font_size'] ?? '16px';
        $pFontWeight = $p['font_weight'] ?? '400';
        $pLineHeight = $p['line_height'] ?? '1.7';
        $pLetterSpacing = $p['letter_spacing'] ?? '0';
        $pColor = !empty($p['color']) ? $p['color'] : 'inherit';

        $lFontFamily = theme_font_stack($l['font_family'] ?? '', $slug);
        $lFontSize = $l['font_size'] ?? '16px';
        $lLineHeight = $l['line_height'] ?? '1.7';
        $lColor = !empty($l['color']) ? $l['color'] : 'inherit';

        $sFontFamily = theme_font_stack($s['font_family'] ?? '', $slug);
        $sFontWeight = $s['font_weight'] ?? '700';
        $sColor = !empty($s['color']) ? $s['color'] : 'inherit';

        $aFontFamily = theme_font_stack($a['font_family'] ?? '', $slug);
        $aColor = $a['color'] ?? '#0f5e9c';
        $aHoverColor = $a['hover_color'] ?? '#0b4c80';
        $aTextDecoration = $a['text_decoration'] ?? 'underline';

        $custom = (string) ($o['additional_css'] ?? '');
        $custom = str_replace('</style>', '<\/style>', $custom);

        $fontFaceCss = theme_custom_fonts_css($slug);

        return <<<CSS
{$fontFaceCss}
<style>
:root{
  --cms-bg: {$bg};
  --cms-text: {$text};
  --cms-primary: {$primary};
  --cms-accent: {$accent};
  --cms-container: {$containerMax};
  --cms-radius: {$rounded};
  --cms-shadow: {$shadow};
  --cms-footer-bg: {$footerBg};
  --cms-footer-text: {$footerText};
  --cms-footer-align: {$footerAlign};
  --cms-logo-width: {$logoWidth}px;

  --cms-heading-font-family: {$hFontFamily};
  --cms-heading-font-weight: {$hFontWeight};
  --cms-heading-text-transform: {$hTextTransform};
  --cms-heading-line-height: {$hLineHeight};
  --cms-heading-letter-spacing: {$hLetterSpacing};
  --cms-heading-color: {$hColor};
  --cms-h1-font-size: {$h1Size};
  --cms-h2-font-size: {$h2Size};
  --cms-h3-font-size: {$h3Size};
  --cms-h4-font-size: {$h4Size};
  --cms-h5-font-size: {$h5Size};
  --cms-h6-font-size: {$h6Size};

  --cms-body-font-family: {$pFontFamily};
  --cms-body-font-size: {$pFontSize};
  --cms-body-font-weight: {$pFontWeight};
  --cms-body-line-height: {$pLineHeight};
  --cms-body-letter-spacing: {$pLetterSpacing};
  --cms-body-color: {$pColor};

  --cms-list-font-family: {$lFontFamily};
  --cms-list-font-size: {$lFontSize};
  --cms-list-line-height: {$lLineHeight};
  --cms-list-color: {$lColor};

  --cms-strong-font-family: {$sFontFamily};
  --cms-strong-font-weight: {$sFontWeight};
  --cms-strong-color: {$sColor};

  --cms-link-font-family: {$aFontFamily};
  --cms-link-color: {$aColor};
  --cms-link-hover-color: {$aHoverColor};
  --cms-link-decoration: {$aTextDecoration};
}
html{
  background: var(--cms-bg);
}
body{
  background: var(--cms-bg);
  color: var(--cms-text);
  font-family: var(--cms-body-font-family);
  font-size: var(--cms-body-font-size);
  font-weight: var(--cms-body-font-weight);
  line-height: var(--cms-body-line-height);
  letter-spacing: var(--cms-body-letter-spacing);
}
.cms-container{
  max-width: var(--cms-container);
  margin: 0 auto;
  padding-left: 16px;
  padding-right: 16px;
}
.site-logo img,
.custom-logo img,
.cms-site-logo img,
.logo-img{
  max-width: var(--cms-logo-width);
  width: 100%;
  height: auto;
  display: block;
}

/* Base heading typography */
h1,h2,h3,h4,h5,h6,
.heading,
.entry-title,
.page-title,
.section-title,
.widget-title,
.footer-heading,
.cms-page-title,
.cms-heading-h1,
.cms-heading-h2,
.cms-heading-h3,
.cms-heading-h4,
.cms-heading-h5,
.cms-heading-h6{
  font-family: var(--cms-heading-font-family);
  font-weight: var(--cms-heading-font-weight);
  text-transform: var(--cms-heading-text-transform);
  line-height: var(--cms-heading-line-height);
  letter-spacing: var(--cms-heading-letter-spacing);
  color: var(--cms-heading-color);
}

/* Semantic heading sizes */
h1{ font-size: var(--cms-h1-font-size); }
h2{ font-size: var(--cms-h2-font-size); }
h3{ font-size: var(--cms-h3-font-size); }
h4{ font-size: var(--cms-h4-font-size); }
h5{ font-size: var(--cms-h5-font-size); }
h6{ font-size: var(--cms-h6-font-size); }

/* Utility classes for templates */
.cms-page-title,
.cms-heading-h1{ font-size: var(--cms-h1-font-size); }
.cms-heading-h2{ font-size: var(--cms-h2-font-size); }
.cms-heading-h3{ font-size: var(--cms-h3-font-size); }
.cms-heading-h4{ font-size: var(--cms-h4-font-size); }
.cms-heading-h5{ font-size: var(--cms-h5-font-size); }
.cms-heading-h6{ font-size: var(--cms-h6-font-size); }

/*
  Important:
  do NOT force .page-title/.entry-title/.section-title/.widget-title/.footer-heading
  to h2 size here, because many of them are used on <h1> and were overriding h1 customizer size.
*/
.heading,
.entry-title,
.page-title,
.section-title,
.widget-title,
.footer-heading{
  font-size: inherit;
}

body,
p,
li,
dd,
dt,
blockquote,
figcaption,
label,
input,
textarea,
select,
button,
small,
time,
address,
.footer-description,
.copyright-text{
  font-family: var(--cms-body-font-family);
  font-size: var(--cms-body-font-size);
  font-weight: var(--cms-body-font-weight);
  line-height: var(--cms-body-line-height);
  letter-spacing: var(--cms-body-letter-spacing);
  color: var(--cms-body-color);
}
ul,ol,li{
  font-family: var(--cms-list-font-family);
  font-size: var(--cms-list-font-size);
  line-height: var(--cms-list-line-height);
  color: var(--cms-list-color);
}
strong,b{
  font-family: var(--cms-strong-font-family);
  font-weight: var(--cms-strong-font-weight);
  color: var(--cms-strong-color);
}
a{
  font-family: var(--cms-link-font-family);
  color: var(--cms-link-color);
  text-decoration: var(--cms-link-decoration);
}
a:hover{
  color: var(--cms-link-hover-color);
}
.cms-footer{
  background: var(--cms-footer-bg);
  color: var(--cms-footer-text);
}
.cms-footer .copyright-text,
.cms-footer .footer-description,
.cms-footer .footer-heading{
  text-align: var(--cms-footer-align);
}
.prose p,
.prose li,
.prose ul,
.prose ol,
.prose strong,
.prose b,
.prose a,
.content-area p,
.content-area li,
.entry-content p,
.entry-content li{
  font: inherit;
  color: inherit;
}
{$custom}
</style>
CSS;
    }
}
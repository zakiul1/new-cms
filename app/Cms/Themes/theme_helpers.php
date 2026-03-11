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
                    'font_family' => 'Inter',
                    'font_size' => 'inherit',
                    'font_weight' => '700',
                    'text_transform' => 'none',
                    'line_height' => '1.2',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'strong' => [
                    'font_family' => 'Inter',
                    'font_weight' => '700',
                    'color' => '',
                ],
                'paragraph' => [
                    'font_family' => 'Inter',
                    'font_size' => '16px',
                    'font_weight' => '400',
                    'line_height' => '1.7',
                    'letter_spacing' => '0',
                    'color' => '',
                ],
                'list' => [
                    'font_family' => 'Inter',
                    'font_size' => '16px',
                    'line_height' => '1.7',
                    'color' => '',
                ],
                'anchor' => [
                    'font_family' => 'Inter',
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
            ],
            'additional_css' => '',
            'appearance' => [
                'header_layout' => 'left',
                'header_sticky' => true,
                'background' => '#ffffff',
                'text' => '#111827',
                'primary' => '#f59e0b',
                'accent' => '#0ea5e9',
                'container_width' => 'default',
                'rounded' => true,
                'shadows' => true,
            ],
        ];

        return array_replace_recursive($defaults, $saved, $draft);
    }
}

if (!function_exists('theme_font_registry')) {
    function theme_font_registry(): array
    {
        return [
            'Inter' => [
                'family' => 'Inter',
                'stack' => "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
                'faces' => [],
            ],
            'Roboto' => [
                'family' => 'Roboto',
                'stack' => "'Roboto', Arial, sans-serif",
                'faces' => [
                    [
                        'weight' => '300',
                        'style' => 'normal',
                        'woff2' => asset('fonts/roboto/Roboto-Light.woff2'),
                        'woff' => asset('fonts/roboto/Roboto-Light.woff'),
                    ],
                    [
                        'weight' => '400',
                        'style' => 'normal',
                        'woff2' => asset('fonts/roboto/Roboto-Regular.woff2'),
                        'woff' => asset('fonts/roboto/Roboto-Regular.woff'),
                    ],
                    [
                        'weight' => '500',
                        'style' => 'normal',
                        'woff2' => asset('fonts/roboto/Roboto-Medium.woff2'),
                        'woff' => asset('fonts/roboto/Roboto-Medium.woff'),
                    ],
                ],
            ],
            'Ropa Sans' => [
                'family' => 'Ropa Sans',
                'stack' => "'Ropa Sans', Arial, sans-serif",
                'faces' => [
                    [
                        'weight' => '400',
                        'style' => 'normal',
                        'woff2' => asset('fonts/ropa-sans/RopaSans-Regular.woff2'),
                        'woff' => asset('fonts/ropa-sans/RopaSans-Regular.woff'),
                    ],
                ],
            ],
            'Arial' => [
                'family' => 'Arial',
                'stack' => "Arial, Helvetica, sans-serif",
                'faces' => [],
            ],
            'Georgia' => [
                'family' => 'Georgia',
                'stack' => "Georgia, 'Times New Roman', serif",
                'faces' => [],
            ],
            'System UI' => [
                'family' => 'System UI',
                'stack' => "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
                'faces' => [],
            ],
        ];
    }
}

if (!function_exists('theme_font_stack')) {
    function theme_font_stack(?string $fontFamily): string
    {
        $fontFamily = trim((string) $fontFamily);
        $registry = theme_font_registry();

        if ($fontFamily !== '' && isset($registry[$fontFamily]['stack'])) {
            return (string) $registry[$fontFamily]['stack'];
        }

        return "'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
    }
}

if (!function_exists('theme_custom_fonts_css')) {
    function theme_custom_fonts_css(): string
    {
        $registry = theme_font_registry();
        $css = '';

        foreach ($registry as $font) {
            $family = (string) ($font['family'] ?? '');
            $faces = $font['faces'] ?? [];

            if ($family === '' || !is_array($faces) || $faces === []) {
                continue;
            }

            foreach ($faces as $face) {
                $woff2 = trim((string) ($face['woff2'] ?? ''));
                $woff = trim((string) ($face['woff'] ?? ''));
                $weight = trim((string) ($face['weight'] ?? '400'));
                $style = trim((string) ($face['style'] ?? 'normal'));

                $sources = [];
                if ($woff2 !== '') {
                    $sources[] = "url('{$woff2}') format('woff2')";
                }
                if ($woff !== '') {
                    $sources[] = "url('{$woff}') format('woff')";
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
  font-display: swap;
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

if (!function_exists('theme_logo_width')) {
    function theme_logo_width(?string $slug = null): int
    {
        $o = theme_options($slug);
        $width = (int) data_get($o, 'site_identity.logo_width', 200);

        return $width > 0 ? $width : 200;
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
        $primary = $appearance['primary'] ?? '#f59e0b';
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
        if ($logoWidth <= 0) {
            $logoWidth = 200;
        }

        $hFontFamily = theme_font_stack($h['font_family'] ?? 'Inter');
        $hFontSize = $h['font_size'] ?? 'inherit';
        $hFontWeight = $h['font_weight'] ?? '700';
        $hTextTransform = $h['text_transform'] ?? 'none';
        $hLineHeight = $h['line_height'] ?? '1.2';
        $hLetterSpacing = $h['letter_spacing'] ?? '0';
        $hColor = !empty($h['color']) ? $h['color'] : 'inherit';

        $pFontFamily = theme_font_stack($p['font_family'] ?? 'Inter');
        $pFontSize = $p['font_size'] ?? '16px';
        $pFontWeight = $p['font_weight'] ?? '400';
        $pLineHeight = $p['line_height'] ?? '1.7';
        $pLetterSpacing = $p['letter_spacing'] ?? '0';
        $pColor = !empty($p['color']) ? $p['color'] : 'inherit';

        $lFontFamily = theme_font_stack($l['font_family'] ?? 'Inter');
        $lFontSize = $l['font_size'] ?? '16px';
        $lLineHeight = $l['line_height'] ?? '1.7';
        $lColor = !empty($l['color']) ? $l['color'] : 'inherit';

        $sFontFamily = theme_font_stack($s['font_family'] ?? 'Inter');
        $sFontWeight = $s['font_weight'] ?? '700';
        $sColor = !empty($s['color']) ? $s['color'] : 'inherit';

        $aFontFamily = theme_font_stack($a['font_family'] ?? 'Inter');
        $aColor = $a['color'] ?? '#0f5e9c';
        $aHoverColor = $a['hover_color'] ?? '#0b4c80';
        $aTextDecoration = $a['text_decoration'] ?? 'underline';

        $custom = (string) ($o['additional_css'] ?? '');
        $custom = str_replace('</style>', '<\/style>', $custom);

        $fontFaceCss = theme_custom_fonts_css();

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
}
body{
  background: var(--cms-bg);
  color: var(--cms-text);
}
.cms-container{
  max-width: var(--cms-container);
  margin: 0 auto;
  padding-left: 16px;
  padding-right: 16px;
}
.site-logo img,
.custom-logo img,
.cms-site-logo img{
  max-width: var(--cms-logo-width);
  width: 100%;
  height: auto;
  display: block;
}
h1,h2,h3,h4,h5,h6{
  font-family: {$hFontFamily};
  font-size: {$hFontSize};
  font-weight: {$hFontWeight};
  text-transform: {$hTextTransform};
  line-height: {$hLineHeight};
  letter-spacing: {$hLetterSpacing};
  color: {$hColor};
}
p{
  font-family: {$pFontFamily};
  font-size: {$pFontSize};
  font-weight: {$pFontWeight};
  line-height: {$pLineHeight};
  letter-spacing: {$pLetterSpacing};
  color: {$pColor};
}
ul,ol,li{
  font-family: {$lFontFamily};
  font-size: {$lFontSize};
  line-height: {$lLineHeight};
  color: {$lColor};
}
strong,b{
  font-family: {$sFontFamily};
  font-weight: {$sFontWeight};
  color: {$sColor};
}
a{
  font-family: {$aFontFamily};
  color: {$aColor};
  text-decoration: {$aTextDecoration};
}
a:hover{
  color: {$aHoverColor};
}
.cms-footer{
  background: var(--cms-footer-bg);
  color: var(--cms-footer-text);
}
.cms-footer .copyright-text{
  text-align: var(--cms-footer-align);
}
{$custom}
</style>
CSS;
    }
}
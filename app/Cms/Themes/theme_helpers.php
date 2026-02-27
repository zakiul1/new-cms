<?php

use App\Cms\Core\Settings;
use App\Cms\Themes\ThemeManager;

if (!function_exists('theme_options')) {
    function theme_options(?string $slug = null): array
    {
        /** @var ThemeManager $tm */
        $tm = app(ThemeManager::class);

        // Allow ?preview_theme=slug override only for logged-in users
        $previewSlug = (string) request()->query('preview_theme', '');
        if ($slug === null && $previewSlug !== '' && auth()->check()) {
            $slug = $previewSlug;
        }

        // fallback to active
        $slug ??= $tm->activeSlug();

        // ✅ Defaults (so theme never breaks if a key is missing)
        $defaults = [
            // base
            'primary' => '#f59e0b',
            'accent' => '#0ea5e9',
            'background' => '#ffffff',
            'text' => '#111827',
            'font_family' => 'system',
            'base_font_size' => 16,
            'container_width' => 'default',
            'rounded' => true,
            'shadows' => true,
            'custom_css' => '',

            // header
            'header_layout' => 'left',
            'header_sticky' => true,
            'header_bg' => '#ffffff',
            'header_text' => '#111827',
            'logo_width' => 140,

            // ✅ media-based (new)
            'logo_media_id' => null,
            'favicon_media_id' => null,

            // backward compatibility (old)
            'logo_path' => null,
        ];

        /** @var Settings $settings */
        $settings = app(Settings::class);

        // Saved from DB
        $saved = $settings->get("theme_options.{$slug}", []);
        $saved = is_array($saved) ? $saved : [];

        // ✅ Draft from session (only when customizer preview is on, logged in)
        // Require customizer=1 AND (preview_theme is present OR slug was explicitly passed)
        $useDraft = request()->boolean('customizer')
            && auth()->check()
            && ($previewSlug !== '' || $slug !== $tm->activeSlug());

        if ($useDraft) {
            $draft = session()->get("theme_customizer.draft.{$slug}", []);
            $draft = is_array($draft) ? $draft : [];

            // Merge: defaults <- saved <- draft
            // so draft wins but missing keys still come from saved/defaults
            return array_replace_recursive($defaults, $saved, $draft);
        }

        // Normal mode: defaults <- saved
        return array_replace_recursive($defaults, $saved);
    }
}

if (!function_exists('theme_customizer_css')) {
    function theme_customizer_css(?string $slug = null): string
    {
        $o = theme_options($slug);

        $primary = $o['primary'] ?? '#f59e0b';
        $accent = $o['accent'] ?? '#0ea5e9';
        $bg = $o['background'] ?? '#ffffff';
        $text = $o['text'] ?? '#111827';

        $font = $o['font_family'] ?? 'system';
        $fontCss = match ($font) {
            'inter' => 'Inter, ui-sans-serif, system-ui',
            'poppins' => 'Poppins, ui-sans-serif, system-ui',
            'roboto' => 'Roboto, ui-sans-serif, system-ui',
            default => 'ui-sans-serif, system-ui',
        };

        $base = (int) ($o['base_font_size'] ?? 16);

        $container = $o['container_width'] ?? 'default';
        $containerMax = match ($container) {
            'full' => '100%',
            'narrow' => '960px',
            'wide' => '1280px',
            default => '1280px',
        };

        $radius = !empty($o['rounded']) ? '14px' : '0px';
        $shadow = !empty($o['shadows']) ? '0 10px 30px rgba(0,0,0,.08)' : 'none';

        $custom = (string) ($o['custom_css'] ?? '');

        // Escape closing style tags in custom css (small safety)
        $custom = str_replace('</style>', '<\/style>', $custom);

        return <<<CSS
<style>
:root{
  --cms-primary: {$primary};
  --cms-accent: {$accent};
  --cms-bg: {$bg};
  --cms-text: {$text};
  --cms-font: {$fontCss};
  --cms-base-font-size: {$base}px;
  --cms-container: {$containerMax};
  --cms-radius: {$radius};
  --cms-shadow: {$shadow};
}
body{
  background: var(--cms-bg);
  color: var(--cms-text);
  font-family: var(--cms-font);
  font-size: var(--cms-base-font-size);
}
.cms-container{
  max-width: var(--cms-container);
  margin: 0 auto;
  padding: 0 16px;
}
{$custom}
</style>
CSS;
    }
}
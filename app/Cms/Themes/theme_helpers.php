<?php

use App\Cms\Core\Settings;
use App\Cms\Themes\ThemeManager;

if (!function_exists('theme_options')) {
    function theme_options(?string $slug = null): array
    {
        /** @var ThemeManager $tm */
        $tm = app(ThemeManager::class);

        // If customizer preview is open, allow preview_theme override
        $previewSlug = (string) request()->query('preview_theme', '');
        if ($slug === null && $previewSlug !== '' && auth()->check()) {
            $slug = $previewSlug;
        }

        // fallback to active
        $slug ??= $tm->activeSlug();

        // If in customizer preview mode, use draft from session (admin only)
        if (request()->boolean('customizer') && auth()->check()) {
            $draft = session()->get("theme_customizer.draft.{$slug}", []);
            if (is_array($draft) && !empty($draft)) {
                return $draft;
            }
        }

        /** @var Settings $settings */
        $settings = app(Settings::class);

        $saved = $settings->get("theme_options.{$slug}", []);
        return is_array($saved) ? $saved : [];
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
            'narrow' => '960px',
            'wide' => '1280px',
            default => '1100px',
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
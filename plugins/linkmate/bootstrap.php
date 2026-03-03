<?php

use App\Cms\Hooks\HookPoints;
use App\Models\Media;
use App\Models\Post;
use Filament\Panel;

// ✅ Load plugin classes (no composer needed)
$settingsPageFile = __DIR__ . '/src/Filament/Pages/LinkMateSettings.php';
$linkerFile = __DIR__ . '/src/LinkMate.php';

if (is_file($linkerFile)) {
    require_once $linkerFile;
}
if (is_file($settingsPageFile)) {
    require_once $settingsPageFile;
}

// ✅ Register plugin views namespace
add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel): void {
    $viewsPath = base_path('plugins/linkmate/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('linkmate', $viewsPath);
    }

    $pageClass = \Plugins\LinkMate\Filament\Pages\LinkMateSettings::class;
    if (class_exists($pageClass)) {
        $panel->pages([$pageClass]);
    }
});

// ✅ Attach hooks AFTER CMS booted
add_action(HookPoints::CMS_BOOTED, function (): void {

    /**
     * ✅ 0) RENDER-TIME AUTOLINKING (THIS FIXES MULTIPAGE GENERATED URL)
     *
     * MultiPage content becomes real only after:
     * - {segment-n} replacements
     * - [segment-1] shortcodes
     *
     * Those happen in the CMS pipeline, so LinkMate must run at render-time.
     *
     * Priority:
     * - MultiPage plugin uses CMS_THE_CONTENT priority 10
     * - Shortcodes usually run later
     * We run at 30 to be safely AFTER replacements.
     */
    add_filter(HookPoints::CMS_THE_CONTENT, function ($html, $ctx = []) {
        if (!class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return $html;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        // Determine type from context
        $type = 'post';
        if (is_array($ctx) && isset($ctx['post']) && $ctx['post'] instanceof \App\Models\Post) {
            $type = (string) ($ctx['post']->type ?? 'post');
        } elseif (is_array($ctx) && isset($ctx['media']) && $ctx['media'] instanceof \App\Models\Media) {
            $type = 'media';
        }

        // Respect enabled types
        if (!$lm->isTypeEnabled($type)) {
            return $html;
        }

        return $lm->applyToHtml((string) $html, $type);
    }, 30, 2);

    /**
     * ✅ 1) SAVE-TIME AUTOLINKING (keep for normal posts/pages)
     * BUT skip multipage because multipage is dynamic and should be handled by render-time hook above.
     */
    Post::saving(function (Post $post): void {
        if (!class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        $type = (string) ($post->type ?? 'post');

        // ✅ Multipage must be linked at render-time (dynamic)
        if ($type === 'multipage') {
            return;
        }

        if (!$lm->isTypeEnabled($type)) {
            return;
        }

        $html = (string) $post->content_html;
        if ($html === '') {
            return;
        }

        $newHtml = $lm->applyToHtml($html, $type);

        // only set when changed
        if ($newHtml !== $html) {
            $post->content_html = $newHtml;
        }
    });

    /**
     * ✅ 2) Media (optional like WP attachment description/caption)
     * Save-time linking for media fields is OK.
     */
    Media::saving(function (Media $media): void {
        if (!class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        // Only if enabled for "media"
        if (!$lm->isTypeEnabled('media')) {
            return;
        }

        // Apply to description + caption (safe, WP-like attachment fields)
        if (is_string($media->description) && $media->description !== '') {
            $new = $lm->applyToHtml($media->description, 'media');
            if ($new !== $media->description) {
                $media->description = $new;
            }
        }

        if (is_string($media->caption) && $media->caption !== '') {
            $new = $lm->applyToHtml($media->caption, 'media');
            if ($new !== $media->caption) {
                $media->caption = $new;
            }
        }
    });

    /**
     * ✅ 3) Media Defaults plugin support (frontend-only, no DB save)
     */
    add_action('media.attachment.defaults.persist', function ($media): void {
        if (!$media || !class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        if (!$lm->isTypeEnabled('media')) {
            return;
        }

        // description
        if (is_string($media->description) && $media->description !== '') {
            $media->description = $lm->applyToHtml($media->description, 'media');
        }

        // caption
        if (is_string($media->caption) && $media->caption !== '') {
            $media->caption = $lm->applyToHtml($media->caption, 'media');
        }

        // meta frontend description (Media Defaults uses meta['frontend']['meta_description'])
        $meta = is_array($media->meta ?? null) ? $media->meta : [];
        $mDesc = data_get($meta, 'frontend.meta_description', null);
        if (is_string($mDesc) && $mDesc !== '') {
            data_set($meta, 'frontend.meta_description', $lm->applyToHtml($mDesc, 'media'));
            $media->meta = $meta;
        }
    }, 99);

    /**
     * ✅ 4) Tag Defaults plugin support (frontend-only, no DB save)
     */
    add_action('siatex.tag.defaults.persist', function ($tag): void {
        if (!$tag || !class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        if (!$lm->isTypeEnabled('siatex_tag')) {
            return;
        }

        // Tag HTML usually lives in content_json['html']
        $content = is_array($tag->content_json ?? null) ? $tag->content_json : [];
        $html = $content['html'] ?? null;
        if (is_string($html) && $html !== '') {
            $content['html'] = $lm->applyToHtml($html, 'siatex_tag');
            $tag->content_json = $content;
        }

        // Sub description often lives in meta_json['sub_description']
        $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
        $sub = data_get($meta, 'sub_description', null);
        if (is_string($sub) && $sub !== '') {
            data_set($meta, 'sub_description', $lm->applyToHtml($sub, 'siatex_tag'));
            $tag->meta_json = $meta;
        }
    }, 99);
});
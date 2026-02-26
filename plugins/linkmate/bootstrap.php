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

// ✅ Attach to model saving events AFTER CMS booted
add_action(HookPoints::CMS_BOOTED, function (): void {

    // 1) Posts/Pages (stored in posts table with type=post/page/custom)
    Post::saving(function (Post $post): void {
        if (!class_exists(\Plugins\LinkMate\LinkMate::class)) {
            return;
        }

        /** @var \Plugins\LinkMate\LinkMate $lm */
        $lm = app(\Plugins\LinkMate\LinkMate::class);

        $type = (string) ($post->type ?? 'post');

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

    // 2) Media (optional like WP attachment description/caption)
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
});
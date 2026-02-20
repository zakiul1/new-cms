<?php

use App\Cms\Hooks\HookPoints;
use Filament\Panel;

add_action(HookPoints::FILAMENT_ADMIN_PANEL, function (Panel $panel): void {
    $viewsPath = base_path('plugins/media-name-changer/resources/views');
    if (is_dir($viewsPath)) {
        view()->addNamespace('media-name-changer', $viewsPath);
    }

    // Load classes (plugin folder isn’t composer-autoloaded)
    foreach ([
        base_path('plugins/media-name-changer/src/Jobs/RenameMediaBatch.php'),
        base_path('plugins/media-name-changer/src/Support/MediaRenameService.php'),
        base_path('plugins/media-name-changer/src/Filament/Pages/MediaNameChanger.php'),
    ] as $file) {
        if (is_file($file)) {
            require_once $file;
        }
    }

    $pageClass = \Plugins\MediaNameChanger\Filament\Pages\MediaNameChanger::class;

    if (class_exists($pageClass)) {
        $panel->pages([$pageClass]);
    }
});
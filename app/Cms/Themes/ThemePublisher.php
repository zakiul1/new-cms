<?php

namespace App\Cms\Themes;

use Illuminate\Support\Facades\File;

final class ThemePublisher
{
    public function publish(string $slug): void
    {
        $src = rtrim(config('cms.themes_path'), '/') . "/{$slug}/dist";
        $dst = rtrim(config('cms.themes_public_path'), '/') . "/{$slug}/dist";

        if (!File::isDirectory($src)) {
            // theme might have no dist folder; that's OK
            return;
        }

        File::ensureDirectoryExists(dirname($dst));
        File::deleteDirectory($dst);
        File::copyDirectory($src, $dst);
    }
}
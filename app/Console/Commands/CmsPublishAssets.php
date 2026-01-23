<?php

namespace App\Console\Commands;

use App\Cms\Plugins\PluginManager;
use App\Cms\Themes\ThemeManager;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CmsPublishAssets extends Command
{
    protected $signature = 'cms:publish-assets {--clean : Delete existing published dist folders first}';
    protected $description = 'Publish theme/plugin dist assets into public/themes and public/plugins';

    public function handle(
        Filesystem $files,
        ThemeManager $themes,
        PluginManager $plugins,
    ): int {
        // Themes
        foreach ($themes->discover() as $theme) {
            $slug = $theme['slug'];
            $src = base_path("themes/{$slug}/dist");
            $dst = public_path("themes/{$slug}/dist");

            $this->publishDist($files, $src, $dst);
        }

        // Plugins
        foreach ($plugins->discover() as $plugin) {
            $slug = $plugin['slug'];
            $src = base_path("plugins/{$slug}/dist");
            $dst = public_path("plugins/{$slug}/dist");

            $this->publishDist($files, $src, $dst);
        }

        $this->info('Assets published.');
        return self::SUCCESS;
    }

    private function publishDist(Filesystem $files, string $src, string $dst): void
    {
        if (!$files->isDirectory($src)) {
            return;
        }

        if ($this->option('clean') && $files->isDirectory($dst)) {
            $files->deleteDirectory($dst);
        }

        $files->ensureDirectoryExists($dst);
        $files->copyDirectory($src, $dst);
    }
}
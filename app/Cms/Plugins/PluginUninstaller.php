<?php

namespace App\Cms\Plugins;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PluginUninstaller
{
    public function uninstall(string $slug): void
    {
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            throw new RuntimeException('Invalid slug.');
        }

        $dir = base_path('plugins' . DIRECTORY_SEPARATOR . $slug);
        if (!is_dir($dir)) {
            return;
        }

        File::deleteDirectory($dir);

        // Also delete published assets
        $public = public_path('plugins' . DIRECTORY_SEPARATOR . $slug);
        if (is_dir($public)) {
            File::deleteDirectory($public);
        }
    }
}

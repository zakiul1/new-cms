<?php

namespace App\Cms\Plugins;

use Illuminate\Support\Facades\File;
use RuntimeException;

class PluginPublisher
{
    /**
     * Publish plugin dist assets to public.
     * Copies: plugins/{slug}/dist -> public/plugins/{slug}/dist
     */
    public function publish(string $slug): void
    {
        $pluginsBase = base_path('plugins');
        $src = $pluginsBase . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'dist';

        // Nothing to publish (plugin may not ship assets)
        if (!is_dir($src)) {
            return;
        }

        $publicBase = public_path('plugins');
        $dest = $publicBase . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'dist';

        // Ensure dest exists
        File::ensureDirectoryExists($dest);

        // Copy all dist contents (overwrite)
        // Laravel File::copyDirectory doesn't overwrite well in all cases, so we delete then copy
        if (is_dir($dest)) {
            File::deleteDirectory($dest);
            File::ensureDirectoryExists($dest);
        }

        if (!File::copyDirectory($src, $dest)) {
            throw new RuntimeException("Failed to publish plugin assets for {$slug}");
        }
    }

    /**
     * Publish only if public dist missing.
     */
    public function publishIfMissing(string $slug): void
    {
        $publicDist = public_path('plugins' . DIRECTORY_SEPARATOR . $slug . DIRECTORY_SEPARATOR . 'dist');

        if (!is_dir($publicDist)) {
            $this->publish($slug);
        }
    }
}

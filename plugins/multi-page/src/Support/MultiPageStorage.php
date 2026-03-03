<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageStorage
{
    public const ROOT = 'multipage';

    // CSV uploads for multipage generator
    public const CSVS = self::ROOT . '/csvs';

    // Per-generated-url mapping json files
    public const LINKS = self::ROOT . '/static-links';

    // Per-multipage tracker json files
    public const TRACKERS = self::ROOT . '/static-links-tracker';

    // ✅ Settings storage (for Settings Multipages page)
    public const SETTINGS = self::ROOT . '/settings';

    public static function ensureDirs(): void
    {
        $disk = Storage::disk('local');

        foreach ([self::CSVS, self::LINKS, self::TRACKERS, self::SETTINGS] as $dir) {
            if (!$disk->exists($dir)) {
                $disk->makeDirectory($dir);
            }
        }
    }

    /**
     * Convert a url path like "a/b/c" into a stable, collision-resistant file key.
     *
     * Why hash?
     * - Your previous implementation could collide when "_" exists in a segment:
     *     "/a/b_c" and "/a_b/c" both become "a_b_c"
     * - Collisions overwrite mapping JSON and break dynamic pages.
     */
    public static function pathKey(string $path): string
    {
        $path = '/' . ltrim((string) $path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // readable base
        $base = trim($path, '/');
        if ($base === '') {
            $base = 'home';
        }

        // make filesystem-friendly
        $base = str_replace(['/', '\\'], '_', $base);
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', $base) ?? $base;
        $base = preg_replace('/_+/', '_', $base) ?? $base;
        $base = trim($base, '_');

        // collision-proof suffix
        $hash = substr(sha1($path), 0, 10);

        return $base . '__' . $hash;
    }
}
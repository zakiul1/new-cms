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
     * Convert a url path like "a/b/c" into a file key "a_b_c".
     */
    public static function pathKey(string $path): string
    {
        $path = trim($path, '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;

        return str_replace('/', '_', $path);
    }
}
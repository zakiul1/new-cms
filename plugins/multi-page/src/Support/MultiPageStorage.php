<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageStorage
{
    public const ROOT = 'multipage';
    public const CSVS = self::ROOT . '/csvs';
    public const LINKS = self::ROOT . '/static-links';
    public const TRACKERS = self::ROOT . '/static-links-tracker';

    public static function ensureDirs(): void
    {
        $disk = Storage::disk('local');
        foreach ([self::CSVS, self::LINKS, self::TRACKERS] as $dir) {
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
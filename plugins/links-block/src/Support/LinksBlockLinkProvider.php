<?php

namespace Plugins\LinksBlock\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Plugins\MultiPage\Support\MultiPageStorage;

final class LinksBlockLinkProvider
{
    /**
     * Returns unique, sorted link paths like: "/some-page"
     * Only returns links that actually have a corresponding multipage mapping file,
     * so the frontend won't show URLs that 404.
     *
     * @return array<int,string>
     */
    public static function allLinks(): array
    {
        // If MultiPage plugin not installed, return empty safely
        if (!class_exists(MultiPageStorage::class)) {
            return [];
        }

        // Cache for performance (tracker files can be large)
        return Cache::remember('links_block:all_links:v2', now()->addMinutes(30), function () {
            $disk = Storage::disk('local');

            $trackerDir = MultiPageStorage::TRACKERS;
            if (!$disk->exists($trackerDir)) {
                return [];
            }

            $files = $disk->files($trackerDir);

            $urls = [];
            foreach ($files as $tf) {
                try {
                    $raw = $disk->get($tf);
                    $json = json_decode((string) $raw, true);

                    if (!is_array($json)) {
                        continue;
                    }

                    $generated = $json['generated'] ?? [];
                    if (!is_array($generated)) {
                        continue;
                    }

                    foreach ($generated as $u) {
                        $u = trim((string) $u);
                        if ($u === '') {
                            continue;
                        }

                        // Normalize to "/path" (no trailing slash required here)
                        $path = '/' . ltrim($u, '/');
                        $path = preg_replace('#/+#', '/', $path) ?? $path;
                        $path = rtrim($path, '/');

                        if ($path === '' || $path === '/') {
                            continue;
                        }

                        // ✅ IMPORTANT: only include if mapping exists, otherwise it will 404
                        if (self::mappingExistsForPath($disk, $path)) {
                            $urls[] = $path;
                        }
                    }
                } catch (\Throwable $e) {
                    continue;
                }
            }

            $urls = array_values(array_unique($urls));
            sort($urls);

            return $urls;
        });
    }

    private static function mappingExistsForPath($disk, string $path): bool
    {
        // Primary key used by MultiPageStorage
        $key = MultiPageStorage::pathKey($path);
        $map = MultiPageStorage::LINKS . '/' . $key . '.json';
        if ($disk->exists($map)) {
            return true;
        }

        // Backward compatibility: older key formats (if you ever used them)
        $oldKey = str_replace('/', '_', trim($path, '/'));
        if ($oldKey === '') {
            $oldKey = 'home';
        }
        $mapOld = MultiPageStorage::LINKS . '/' . $oldKey . '.json';

        return $disk->exists($mapOld);
    }
}
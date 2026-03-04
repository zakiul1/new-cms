<?php

namespace Plugins\LinksBlock\Support;

use Illuminate\Support\Facades\Storage;
use Plugins\MultiPage\Support\MultiPageStorage;

final class LinksBlockLinkProvider
{
    /**
     * Returns unique, sorted link paths like: "/some-page" or "some-page"
     *
     * @return array<int,string>
     */
    public static function allLinks(): array
    {
        // MultiPage plugin stores tracker json in local disk
        $disk = Storage::disk('local');

        // If MultiPage plugin not installed, return empty safely
        if (!class_exists(MultiPageStorage::class)) {
            return [];
        }

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
                    if ($u === '') continue;
                    $urls[] = $u;
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        $urls = array_values(array_unique($urls));
        sort($urls);

        return $urls;
    }
}
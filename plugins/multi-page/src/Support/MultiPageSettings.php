<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageSettings
{
    public const SETTINGS_DIR = 'multipage/settings';
    public const SETTINGS_FILE = self::SETTINGS_DIR . '/settings.json';
    public const RAND_KEYS_FILE = self::SETTINGS_DIR . '/rand-keys.txt';
    public const EXTERNAL_LINKS_FILE = self::SETTINGS_DIR . '/external-links.txt';

    public static function defaults(): array
    {
        return [
            'sitemaps_dir' => 'sitemaps-multipage',   // public disk folder
            'max_links_per_file' => 20000,
            'file_base_name' => 'multipage-sitemap',
            'modified_date' => now()->toDateString(),
        ];
    }

    public static function load(): array
    {
        $disk = Storage::disk('local');
        if (!$disk->exists(self::SETTINGS_FILE)) {
            return self::defaults();
        }

        $raw = $disk->get(self::SETTINGS_FILE);
        $arr = json_decode((string) $raw, true);

        if (!is_array($arr)) {
            return self::defaults();
        }

        return array_merge(self::defaults(), $arr);
    }

    public static function save(array $settings): void
    {
        MultiPageStorage::ensureDirs();

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::SETTINGS_DIR);

        $defaults = self::defaults();

        $clean = [
            'sitemaps_dir' => trim((string) ($settings['sitemaps_dir'] ?? $defaults['sitemaps_dir'])),
            'max_links_per_file' => (int) ($settings['max_links_per_file'] ?? $defaults['max_links_per_file']),
            'file_base_name' => trim((string) ($settings['file_base_name'] ?? $defaults['file_base_name'])),
            'modified_date' => trim((string) ($settings['modified_date'] ?? $defaults['modified_date'])),
        ];

        if ($clean['sitemaps_dir'] === '') {
            $clean['sitemaps_dir'] = $defaults['sitemaps_dir'];
        }
        if ($clean['max_links_per_file'] <= 0) {
            $clean['max_links_per_file'] = $defaults['max_links_per_file'];
        }
        if ($clean['file_base_name'] === '') {
            $clean['file_base_name'] = $defaults['file_base_name'];
        }
        if ($clean['modified_date'] === '') {
            $clean['modified_date'] = $defaults['modified_date'];
        }

        $disk->put(self::SETTINGS_FILE, json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function loadRandKeys(): string
    {
        $disk = Storage::disk('local');
        return $disk->exists(self::RAND_KEYS_FILE) ? (string) $disk->get(self::RAND_KEYS_FILE) : '';
    }

    public static function saveRandKeys(string $text): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::SETTINGS_DIR);
        $disk->put(self::RAND_KEYS_FILE, (string) $text);
    }

    public static function loadExternalLinks(): string
    {
        $disk = Storage::disk('local');
        return $disk->exists(self::EXTERNAL_LINKS_FILE) ? (string) $disk->get(self::EXTERNAL_LINKS_FILE) : '';
    }

    public static function saveExternalLinks(string $text): void
    {
        $disk = Storage::disk('local');
        $disk->makeDirectory(self::SETTINGS_DIR);
        $disk->put(self::EXTERNAL_LINKS_FILE, (string) $text);
    }
}
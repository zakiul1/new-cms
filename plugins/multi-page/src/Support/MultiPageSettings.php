<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageSettings
{
    public const SETTINGS_DIR = 'multipage/settings';
    public const SETTINGS_FILE = self::SETTINGS_DIR . '/settings.json';

    public static function defaults(): array
    {
        return [
            /**
             * ✅ If blank => store sitemap at ROOT of public disk
             *    Example: /static.xml
             *
             * If set => store in storage/app/public/<dir> and access via:
             *    /storage/<dir>/static.xml
             */
            'sitemaps_dir' => '',

            'max_links_per_file' => 20000,

            // ✅ boss requested default filename "static"
            'file_base_name' => 'static',

            'modified_date' => now()->toDateString(),

            // Sitemap URL tags
            // changefreq: always, hourly, daily, weekly, monthly, yearly, never
            'changefreq' => 'weekly',

            // ✅ boss requested default priority 0.9
            'priority' => '0.9',

            // plugin-specific
            'company_info' => '',
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

        // ---- sanitize inputs
        $sitemapsDir = trim((string) ($settings['sitemaps_dir'] ?? $defaults['sitemaps_dir']));
        $sitemapsDir = trim($sitemapsDir, '/'); // allow '' (root), remove leading/trailing '/'

        $maxLinks = (int) ($settings['max_links_per_file'] ?? $defaults['max_links_per_file']);

        $fileBase = trim((string) ($settings['file_base_name'] ?? $defaults['file_base_name']));
        $modified = trim((string) ($settings['modified_date'] ?? $defaults['modified_date']));

        $changefreq = trim((string) ($settings['changefreq'] ?? $defaults['changefreq']));
        $priorityRaw = $settings['priority'] ?? $defaults['priority'];

        $companyInfo = (string) ($settings['company_info'] ?? $defaults['company_info']);

        // ---- normalize required
        // ✅ DO NOT force sitemapsDir when blank (blank means ROOT)
        if ($maxLinks <= 0) {
            $maxLinks = (int) $defaults['max_links_per_file'];
        }

        // filename base: keep it non-empty
        if ($fileBase === '') {
            $fileBase = (string) $defaults['file_base_name'];
        }

        // modified date: keep it non-empty
        if ($modified === '') {
            $modified = (string) $defaults['modified_date'];
        }

        // ---- normalize changefreq (must be valid)
        $allowedChangefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        $changefreq = strtolower($changefreq);
        if (!in_array($changefreq, $allowedChangefreq, true)) {
            $changefreq = (string) $defaults['changefreq'];
        }

        // ---- normalize priority (0.0 - 1.0)
        $priority = is_numeric($priorityRaw) ? (float) $priorityRaw : (float) $defaults['priority'];
        if ($priority < 0.0) {
            $priority = 0.0;
        }
        if ($priority > 1.0) {
            $priority = 1.0;
        }
        // keep as string for JSON consistency / UI
        $priority = number_format($priority, 1, '.', '');

        // ---- normalize company_info
        if (!is_string($companyInfo)) {
            $companyInfo = (string) $companyInfo;
        }

        $clean = [
            'sitemaps_dir' => $sitemapsDir, // '' allowed => root
            'max_links_per_file' => $maxLinks,
            'file_base_name' => $fileBase,
            'modified_date' => $modified,
            'changefreq' => $changefreq,
            'priority' => $priority,
            'company_info' => $companyInfo,
        ];

        $disk->put(
            self::SETTINGS_FILE,
            json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
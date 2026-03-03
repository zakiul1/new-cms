<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageSitemapGenerator
{
    /**
     * @return array{index:string, parts:array<int,string>, count:int}
     */
    public function generate(): array
    {
        MultiPageStorage::ensureDirs();

        $settings = MultiPageSettings::load();

        $sitemapsDir = trim((string) ($settings['sitemaps_dir'] ?? ''));
        $maxLinks = (int) ($settings['max_links_per_file'] ?? 20000);
        $baseName = trim((string) ($settings['file_base_name'] ?? ''));
        $modified = trim((string) ($settings['modified_date'] ?? ''));

        // ✅ changefreq + priority from settings
        $changefreq = strtolower(trim((string) ($settings['changefreq'] ?? 'weekly')));
        $priorityRaw = $settings['priority'] ?? '0.5';

        if ($sitemapsDir === '') {
            $sitemapsDir = 'sitemaps-multipage';
        }
        if ($maxLinks <= 0) {
            $maxLinks = 20000;
        }
        if ($baseName === '') {
            $baseName = 'multipage-sitemap';
        }
        if ($modified === '') {
            $modified = now()->toDateString();
        }

        // ✅ normalize changefreq
        $allowedChangefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        if (!in_array($changefreq, $allowedChangefreq, true)) {
            $changefreq = 'weekly';
        }

        // ✅ normalize priority: 0.0 - 1.0
        $priority = is_numeric($priorityRaw) ? (float) $priorityRaw : 0.5;
        if ($priority < 0.0) {
            $priority = 0.0;
        }
        if ($priority > 1.0) {
            $priority = 1.0;
        }
        $priority = number_format($priority, 1, '.', '');

        $diskLocal = Storage::disk('local');
        $diskPublic = Storage::disk('public');

        $diskPublic->makeDirectory($sitemapsDir);

        // Collect all generated URLs from tracker files
        $trackerFiles = $diskLocal->files(MultiPageStorage::TRACKERS);

        $urls = [];
        foreach ($trackerFiles as $tf) {
            $raw = $diskLocal->get($tf);
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
                $urls[] = $u;
            }
        }

        // unique + stable order
        $urls = array_values(array_unique($urls));
        sort($urls);

        $count = count($urls);

        // Build parts
        $parts = [];
        $chunks = $count > 0 ? array_chunk($urls, $maxLinks) : [];

        // Site base (for absolute loc)
        $siteUrl = rtrim((string) config('app.url'), '/');
        if (function_exists('app') && app()->bound(\App\Cms\Core\SettingsRepository::class)) {
            try {
                $settingsRepo = app(\App\Cms\Core\SettingsRepository::class);
                $siteUrl = rtrim((string) $settingsRepo->get('core', 'site_url', $siteUrl), '/');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // ✅ FORCE www ONLY when missing (if already www -> keep)
        $siteUrl = $this->forceWwwIfMissing($siteUrl);

        if (count($chunks) <= 1) {
            // Single urlset
            $xml = $this->renderUrlset($siteUrl, $urls, $modified, $changefreq, $priority);

            $singleName = $baseName . '.xml';
            $diskPublic->put($sitemapsDir . '/' . $singleName, $xml);

            return [
                'index' => '/' . trim('storage/' . $sitemapsDir . '/' . $singleName, '/'),
                'parts' => [],
                'count' => $count,
            ];
        }

        // Multiple: write parts + index
        foreach ($chunks as $i => $chunk) {
            $partName = $baseName . '-' . ($i + 1) . '.xml';
            $xml = $this->renderUrlset($siteUrl, $chunk, $modified, $changefreq, $priority);

            $diskPublic->put($sitemapsDir . '/' . $partName, $xml);
            $parts[] = '/' . trim('storage/' . $sitemapsDir . '/' . $partName, '/');
        }

        $indexName = $baseName . '.xml';
        $indexXml = $this->renderIndex($siteUrl, $parts, $modified);

        $diskPublic->put($sitemapsDir . '/' . $indexName, $indexXml);

        return [
            'index' => '/' . trim('storage/' . $sitemapsDir . '/' . $indexName, '/'),
            'parts' => $parts,
            'count' => $count,
        ];
    }

    private function renderUrlset(
        string $siteUrl,
        array $urls,
        string $modifiedDate,
        string $changefreq,
        string $priority
    ): string {
        $modifiedDate = $this->safeDate($modifiedDate);

        $out = [];
        $out[] = '<?xml version="1.0" encoding="UTF-8"?>';
        $out[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $u) {
            $loc = $siteUrl . '/' . ltrim((string) $u, '/');
            $loc = htmlspecialchars($loc, ENT_QUOTES);

            $out[] = ' <url>';
            $out[] = ' <loc>' . $loc . '</loc>';
            $out[] = ' <lastmod>' . $modifiedDate . '</lastmod>';
            $out[] = ' <changefreq>' . htmlspecialchars($changefreq, ENT_QUOTES) . '</changefreq>';
            $out[] = ' <priority>' . htmlspecialchars($priority, ENT_QUOTES) . '</priority>';
            $out[] = ' </url>';
        }

        $out[] = '</urlset>';

        return implode("\n", $out);
    }

    private function renderIndex(string $siteUrl, array $partUrls, string $modifiedDate): string
    {
        $modifiedDate = $this->safeDate($modifiedDate);

        $out = [];
        $out[] = '
<?xml version="1.0" encoding="UTF-8"?>';
        $out[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($partUrls as $p) {
            // $p is already like "/storage/dir/name.xml" (site-relative)
            $loc = $siteUrl . '/' . ltrim((string) $p, '/');
            $loc = htmlspecialchars($loc, ENT_QUOTES);

            $out[] = ' <sitemap>';
            $out[] = ' <loc>' . $loc . '</loc>';
            $out[] = ' <lastmod>' . $modifiedDate . '</lastmod>';
            $out[] = ' </sitemap>';
        }

        $out[] = '</sitemapindex>';

        return implode("\n", $out);
    }

    private function safeDate(string $d): string
    {
        $d = trim($d);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }
        return now()->toDateString();
    }

    /**
     * If site url already has www -> keep.
     * If not -> force www.
     */
    private function forceWwwIfMissing(string $siteUrl): string
    {
        $siteUrl = rtrim(trim($siteUrl), '/');
        if ($siteUrl === '') {
            return $siteUrl;
        }

        $parts = parse_url($siteUrl);

        // If parse_url fails (rare), fallback simple replace for common cases
        if (!is_array($parts) || empty($parts['host'])) {
            // Example: "example.com" (no scheme) => can't safely parse
// return as-is
            return $siteUrl;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];

        // keep if already www
        if (substr($host, 0, 4) !== 'www.') {
            $host = 'www.' . $host;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        // keep path? usually site_url should be only domain, but we support it safely
        $path = $parts['path'] ?? '';
        $path = $path ? '/' . ltrim($path, '/') : '';

        return $scheme . '://' . $host . $port . rtrim($path, '/');
    }
}
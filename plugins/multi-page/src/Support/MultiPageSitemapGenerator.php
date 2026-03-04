<?php

namespace Plugins\MultiPage\Support;

use Illuminate\Support\Facades\Storage;

final class MultiPageSitemapGenerator
{
    /**
     * @return array{
     *   index:string,
     *   parts:array<int,string>,
     *   count:int,
     *   root_index?:string,
     *   root_parts?:array<int,string>
     * }
     */
    public function generate(): array
    {
        MultiPageStorage::ensureDirs();

        $settings = MultiPageSettings::load();

        // IMPORTANT: allow blank dir => ROOT
        $sitemapsDir = trim((string) ($settings['sitemaps_dir'] ?? ''));
        $sitemapsDir = trim($sitemapsDir, '/');

        $maxLinks = (int) ($settings['max_links_per_file'] ?? 20000);
        $baseName = trim((string) ($settings['file_base_name'] ?? ''));
        $modified = trim((string) ($settings['modified_date'] ?? ''));

        // changefreq + priority from settings
        $changefreq = strtolower(trim((string) ($settings['changefreq'] ?? 'weekly')));
        $priorityRaw = $settings['priority'] ?? '0.9';

        if ($maxLinks <= 0) {
            $maxLinks = 20000;
        }
        if ($baseName === '') {
            $baseName = 'static';
        }
        if ($modified === '') {
            $modified = now()->toDateString();
        }

        // normalize changefreq
        $allowedChangefreq = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];
        if (!in_array($changefreq, $allowedChangefreq, true)) {
            $changefreq = 'weekly';
        }

        // normalize priority: 0.0 - 1.0
        $priority = is_numeric($priorityRaw) ? (float) $priorityRaw : 0.9;
        if ($priority < 0.0) {
            $priority = 0.0;
        }
        if ($priority > 1.0) {
            $priority = 1.0;
        }
        $priority = number_format($priority, 1, '.', '');

        $diskLocal = Storage::disk('local');
        $diskPublic = Storage::disk('public');

        // Only create dir if not root mode
        if ($sitemapsDir !== '') {
            $diskPublic->makeDirectory($sitemapsDir);
        }

        // Helper: where to write on "public" disk
        $writePath = function (string $filename) use ($sitemapsDir): string {
            return $sitemapsDir === '' ? $filename : ($sitemapsDir . '/' . $filename);
        };

        // Helper: public URL that user can open
        // - root mode => /file.xml
        // - folder mode => /storage/<dir>/file.xml
        $publicUrl = function (string $filename) use ($sitemapsDir): string {
            if ($sitemapsDir === '') {
                return '/' . ltrim($filename, '/');
            }
            return '/' . trim('storage/' . $sitemapsDir . '/' . $filename, '/');
        };

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

        // FORCE www ONLY when missing (if already www -> keep)
        $siteUrl = $this->forceWwwIfMissing($siteUrl);

        // -------------------------
        // Single file sitemap
        // -------------------------
        if (count($chunks) <= 1) {
            $xml = $this->renderUrlset($siteUrl, $urls, $modified, $changefreq, $priority);

            $singleName = $baseName . '.xml';
            $diskPublic->put($writePath($singleName), $xml);

            // This is what your "Visit" button should use
            $indexUrl = $publicUrl($singleName);

            return [
                'index' => $indexUrl,
                'parts' => [],
                'count' => $count,

                // optional compatibility fields
                'root_index' => '/' . $singleName,
                'root_parts' => [],
            ];
        }

        // -------------------------
        // Multi-part sitemap + index
        // -------------------------
        $partsForIndex = [];
        $partsForReturn = [];

        foreach ($chunks as $i => $chunk) {
            $partName = $baseName . '-' . ($i + 1) . '.xml';
            $xml = $this->renderUrlset($siteUrl, $chunk, $modified, $changefreq, $priority);

            $diskPublic->put($writePath($partName), $xml);

            $partUrl = $publicUrl($partName);
            $partsForIndex[] = $partUrl;
            $partsForReturn[] = $partUrl;
        }

        $indexName = $baseName . '.xml';
        $indexXml = $this->renderIndex($siteUrl, $partsForIndex, $modified);

        $diskPublic->put($writePath($indexName), $indexXml);

        $indexUrl = $publicUrl($indexName);

        // optional root arrays (boss request)
        $rootParts = [];
        foreach ($chunks as $i => $_) {
            $rootParts[] = '/' . $baseName . '-' . ($i + 1) . '.xml';
        }

        return [
            'index' => $indexUrl,
            'parts' => $partsForReturn,
            'count' => $count,

            'root_index' => '/' . $indexName,
            'root_parts' => $rootParts,
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

    /**
     * @param array<int,string> $partUrls site-relative paths like "/static-1.xml" or "/storage/dir/static-1.xml"
     */
    private function renderIndex(string $siteUrl, array $partUrls, string $modifiedDate): string
    {
        $modifiedDate = $this->safeDate($modifiedDate);

        $out = [];
        $out[] = '
    <?xml version="1.0" encoding="UTF-8"?>';
        $out[] = '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($partUrls as $p) {
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

        // If parse_url fails, return as-is
        if (!is_array($parts) || empty($parts['host'])) {
            return $siteUrl;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'];

        if (substr($host, 0, 4) !== 'www.') {
            $host = 'www.' . $host;
        }

        $port = isset($parts['port']) ? ':' . $parts['port'] : '';

        $path = $parts['path'] ?? '';
        $path = $path ? '/' . ltrim($path, '/') : '';

        return $scheme . '://' . $host . $port . rtrim($path, '/');
    }
}
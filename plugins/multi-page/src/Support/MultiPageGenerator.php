<?php

namespace Plugins\MultiPage\Support;

use App\Models\Post;
use Illuminate\Support\Facades\Storage;

final class MultiPageGenerator
{
    public function __construct()
    {
        MultiPageStorage::ensureDirs();
    }

    /**
     * Generate static link mapping files for a multipage (file-based, no DB for generated URLs).
     *
     * @return array{count:int, tracker_path:string, sample?:array<int,string>}
     */
    public function generateForPage(Post $page): array
    {
        // ✅ MultiPages are their own CPT now
        if ($page->type !== 'multipage') {
            throw new \RuntimeException('Selected record is not a multipage.');
        }

        $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
        $cfg = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];

        $enabled = (bool) ($cfg['enabled'] ?? false);
        if (!$enabled) {
            throw new \RuntimeException('Multi Page is disabled for this multipage.');
        }

        $csvFile = trim((string) ($cfg['csv_file'] ?? ''));
        if ($csvFile === '') {
            throw new \RuntimeException('CSV file not selected.');
        }

        $urlStructure = trim((string) ($cfg['url_structure'] ?? ''));
        if ($urlStructure === '') {
            throw new \RuntimeException('URL structure is missing.');
        }

        $hasHeader = (bool) ($cfg['has_header'] ?? false);

        $defaultSegmentsRaw = trim((string) ($cfg['default_segments'] ?? ''));
        $defaultSegments = $defaultSegmentsRaw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $defaultSegmentsRaw)), fn($v) => $v !== ''));

        $disk = Storage::disk('local');
        $csvPath = MultiPageStorage::CSVS . '/' . $csvFile;

        if (!$disk->exists($csvPath)) {
            throw new \RuntimeException("CSV not found: {$csvFile}");
        }

        $full = $disk->path($csvPath);
        $fh = fopen($full, 'rb');
        if (!$fh) {
            throw new \RuntimeException('Unable to open CSV file.');
        }

        $urls = [];
        $count = 0;

        // optional header skip
        if ($hasHeader) {
            fgetcsv($fh);
        }

        while (($row = fgetcsv($fh)) !== false) {
            if (!is_array($row) || $row === []) {
                continue;
            }

            // Build url from {col1}, {col2} ...
            $url = $urlStructure;

            // slugify every col value
            $segments = [];
            foreach ($row as $idx => $val) {
                $n = $idx + 1;
                $clean = $this->slugify((string) $val);
                $segments[] = $clean;

                $url = str_replace('{col' . $n . '}', $clean, $url);
            }

            // Normalize url
            $url = '/' . trim($url, '/');

            // If url still contains unresolved {colN}, skip row
            if (preg_match('/\{col\d+\}/', $url)) {
                continue;
            }

            // ✅ Base slug must exist
            $baseSlug = (string) ($page->slug ?? '');
            if ($baseSlug === '') {
                continue;
            }

            // Write mapping JSON for this generated URL
            // ✅ SAFETY: avoid collisions (different URLs producing same pathKey)
            $keyBase = MultiPageStorage::pathKey($url);
            $mapPath = MultiPageStorage::LINKS . '/' . $keyBase . '.json';

            $key = $keyBase;

            if ($disk->exists($mapPath)) {
                // If file exists, check if it's for the same URL; if not, suffix with hash.
                $existingRaw = $disk->get($mapPath);
                $existing = json_decode((string) $existingRaw, true);

                $existingUrl = is_array($existing) ? (string) ($existing['url'] ?? '') : '';
                if ($existingUrl !== '' && rtrim($existingUrl, '/') !== rtrim($url, '/')) {
                    $key = $keyBase . '_' . substr(sha1($url), 0, 10);
                    $mapPath = MultiPageStorage::LINKS . '/' . $key . '.json';
                }
            }

            $map = [
                'base_slug' => $baseSlug,
                'base_page_id' => (int) $page->id,
                'url' => $url,
                'replacer' => array_values($segments), // segment-1, segment-2 ...
                'created_at' => now()->toISOString(),
            ];

            $disk->put(
                $mapPath,
                json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            );

            $urls[] = $url;
            $count++;

            if ($count > 200000) { // safety cap
                break;
            }
        }

        fclose($fh);

        // Tracker (per base multipage slug OR fallback to record id)
        $trackerKey = (string) ($page->slug ?? '');
        if ($trackerKey === '') {
            $trackerKey = 'multipage-' . (int) $page->id;
        }

        $tracker = [
            'base_slug' => (string) ($page->slug ?? ''),
            'base_page_id' => (int) $page->id,
            'generated' => $urls,
            'count' => $count,
            'csv_file' => $csvFile,
            'url_structure' => $urlStructure,
            'default_segments' => $defaultSegments,
            'updated_at' => now()->toISOString(),
        ];

        $trackerPath = MultiPageStorage::TRACKERS . '/' . $trackerKey . '.json';
        $disk->put(
            $trackerPath,
            json_encode($tracker, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return [
            'count' => $count,
            'tracker_path' => $trackerPath,
            'sample' => array_slice($urls, 0, 10),
        ];
    }

    public function buildDefaultUrl(string $urlStructure, array $defaultSegments): ?string
    {
        if ($urlStructure === '') {
            return null;
        }

        $url = $urlStructure;

        foreach ($defaultSegments as $i => $val) {
            $n = $i + 1;
            $url = str_replace('{col' . $n . '}', $this->slugify((string) $val), $url);
        }

        if (preg_match('/\{col\d+\}/', $url)) {
            // still has tokens, meaning default segments are not enough
            return null;
        }

        return '/' . trim($url, '/');
    }

    private function slugify(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }

        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s) ?? $s;
        $s = trim($s, '-');
        $s = preg_replace('/-+/', '-', $s) ?? $s;

        return $s;
    }
}
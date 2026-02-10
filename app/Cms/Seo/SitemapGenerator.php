<?php

namespace App\Cms\Seo;

use App\Cms\Content\PermalinkManager;
use App\Cms\Core\SettingsRepository;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

class SitemapGenerator
{
    public function __construct(
        private SettingsRepository $settings,
        private SitemapStorage $storage,
        private PermalinkManager $permalinks,
    ) {
    }

    /**
     * Generate all enabled sitemaps and sitemap index.
     * Deletes old generated files first, then generates fresh.
     *
     * @return array<int, string> paths on public disk
     */
    public function generateAll(): array
    {
        $disk = Storage::disk('public');

        $max = (int) $this->settings->get('seo', 'sitemap_max_links', 1000);
        if ($max < 100) {
            $max = 100;
        }

        // ✅ Always delete old ones first
        $this->deleteAll();

        $generated = [];

        // Settings
        $includePosts = (bool) $this->settings->get('seo', 'sitemap_include_posts', true);
        $includePages = (bool) $this->settings->get('seo', 'sitemap_include_pages', true);
        $includeMedia = (bool) $this->settings->get('seo', 'sitemap_include_media', false);

        // ✅ Priority defaults = 0.9
        $postsPriority = (string) $this->settings->get('seo', 'sitemap_posts_priority', '0.9');
        $pagesPriority = (string) $this->settings->get('seo', 'sitemap_pages_priority', '0.9');
        $mediaPriority = (string) $this->settings->get('seo', 'sitemap_media_priority', '0.9');

        $postsFreq = (string) $this->settings->get('seo', 'sitemap_posts_changefreq', 'weekly');
        $pagesFreq = (string) $this->settings->get('seo', 'sitemap_pages_changefreq', 'monthly');
        $mediaFreq = (string) $this->settings->get('seo', 'sitemap_media_changefreq', 'monthly');

        // Pages sitemap (includes home)
        if ($includePages) {
            $items = $this->buildPagesUrls($pagesPriority, $pagesFreq);

            $generated = array_merge(
                $generated,
                count($items) ? $this->writeSplitSitemaps('pages', $items, $max) : $this->writeEmptySitemap('pages')
            );
        }

        // Posts sitemap
        if ($includePosts) {
            $items = $this->buildPostsUrls($postsPriority, $postsFreq);

            $generated = array_merge(
                $generated,
                count($items) ? $this->writeSplitSitemaps('posts', $items, $max) : $this->writeEmptySitemap('posts')
            );
        }

        // Media sitemap (always create media.xml even if empty)
        if ($includeMedia) {
            $items = $this->buildMediaUrls($mediaPriority, $mediaFreq);

            $generated = array_merge(
                $generated,
                count($items) ? $this->writeSplitSitemaps('media', $items, $max) : $this->writeEmptySitemap('media')
            );
        }

        // Build sitemap INDEX (sitemap.xml) from generated sitemap files
        $indexXml = $this->buildSitemapIndexXml($generated);

        $disk->put($this->storage->indexPath(), $indexXml);
        $generated[] = $this->storage->indexPath();

        $this->settings->set('seo', 'sitemap_last_generated_at', now()->toDateTimeString());

        return $generated;
    }

    /**
     * Delete all sitemap files in configured directory.
     */
    public function deleteAll(): void
    {
        $disk = Storage::disk('public');
        $dir = $this->storage->directory();

        $files = $dir === '' ? $disk->files() : $disk->files($dir);

        foreach ($files as $file) {
            $base = basename($file);

            // delete index + our sitemap parts
            if ($base === 'sitemap.xml' || preg_match('/^(pages|posts|media)(-\d+)?\.xml$/', $base)) {
                $disk->delete($file);
            }
        }
    }

    /**
     * Pages sitemap urls (WP-like home handling).
     *
     * - Always includes "/" (home).
     * - If a static homepage page is selected (core.homepage_page_id),
     *   use its lastmod and DO NOT include it again as "/home".
     *
     * @return array<int, array{loc:string,lastmod:string,changefreq:string,priority:string}>
     */
    private function buildPagesUrls(string $priority, string $changefreq): array
    {
        $base = rtrim((string) config('app.url'), '/');
        $nowAtom = now()->toAtomString();

        // ✅ Homepage id (single system)
        $homepageId = $this->settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $urls = [];

        // ✅ Add "/" first. If homepage page exists, use its updated time.
        if ($homepageId !== null) {
            $homePage = Post::query()
                ->whereKey($homepageId)
                ->where('type', 'page')
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->first(['id', 'updated_at', 'published_at']);

            if ($homePage) {
                $lastmod = ($homePage->updated_at ?? $homePage->published_at ?? now())->toAtomString();

                $urls[] = [
                    'loc' => $base . '/',
                    'lastmod' => $lastmod,
                    'changefreq' => 'daily',
                    'priority' => '1.0',
                ];
            } else {
                // fallback if settings points to missing page
                $urls[] = [
                    'loc' => $base . '/',
                    'lastmod' => $nowAtom,
                    'changefreq' => 'daily',
                    'priority' => '1.0',
                ];
            }
        } else {
            // no homepage selected → include "/" with now()
            $urls[] = [
                'loc' => $base . '/',
                'lastmod' => $nowAtom,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];
        }

        $q = Post::query();

        if (method_exists(Post::class, 'scopeFrontendVisible')) {
            $q->frontendVisible();
        }

        $pages = $q->where('type', 'page')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('updated_at')
            ->get(['id', 'type', 'slug', 'updated_at', 'published_at']);

        foreach ($pages as $p) {
            // ✅ Skip homepage page so it doesn't appear as "/home"
            if ($homepageId !== null && (int) $p->getKey() === $homepageId) {
                continue;
            }

            $lastmod = ($p->updated_at ?? $p->published_at ?? now())->toAtomString();

            $urls[] = [
                'loc' => $this->permalinks->pageUrl($p),
                'lastmod' => $lastmod,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $urls;
    }

    /**
     * ALL posts (public only via frontendVisible).
     *
     * @return array<int, array{loc:string,lastmod:string,changefreq:string,priority:string}>
     */
    private function buildPostsUrls(string $priority, string $changefreq): array
    {
        $q = Post::query();

        if (method_exists(Post::class, 'scopeFrontendVisible')) {
            $q->frontendVisible();
        }

        $posts = $q->where('type', 'post')
            ->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('updated_at')
            ->get(['id', 'type', 'slug', 'updated_at', 'published_at']);

        $urls = [];

        foreach ($posts as $p) {
            $lastmod = ($p->updated_at ?? $p->published_at ?? now())->toAtomString();

            $urls[] = [
                'loc' => $this->permalinks->postUrl($p),
                'lastmod' => $lastmod,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $urls;
    }

    /**
     * ALL media (public only via frontendVisible).
     *
     * @return array<int, array{loc:string,lastmod:string,changefreq:string,priority:string}>
     */
    private function buildMediaUrls(string $priority, string $changefreq): array
    {
        $base = rtrim((string) config('app.url'), '/');

        $q = Media::query();

        if (method_exists(Media::class, 'scopeFrontendVisible')) {
            $q->frontendVisible();
        }

        $mediaItems = $q->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->where('attachment_indexable', true)
            ->orderByDesc('updated_at')
            ->get(['slug', 'updated_at', 'created_at']);

        $urls = [];

        foreach ($mediaItems as $m) {
            $slug = trim((string) $m->slug, '/');
            if ($slug === '') {
                continue;
            }

            $lastmod = ($m->updated_at ?? $m->created_at ?? now())->toAtomString();

            $urls[] = [
                'loc' => $base . '/' . $slug,
                'lastmod' => $lastmod,
                'changefreq' => $changefreq,
                'priority' => $priority,
            ];
        }

        return $urls;
    }

    /**
     * Split list into max links per file, write to public disk and return written paths.
     *
     * @param string $baseName e.g. "posts", "pages"
     * @param array<int, array{loc:string,lastmod:string,changefreq:string,priority:string}> $items
     * @return array<int, string>
     */
    private function writeSplitSitemaps(string $baseName, array $items, int $max): array
    {
        if (count($items) === 0) {
            return [];
        }

        $disk = Storage::disk('public');

        $chunks = array_chunk($items, $max);
        $paths = [];

        foreach ($chunks as $i => $chunk) {
            $suffix = $i === 0 ? '' : ('-' . ($i + 1));
            $filename = "{$baseName}{$suffix}.xml";

            $xml = $this->buildUrlsetXml($chunk);
            $path = $this->storage->path($filename);

            $disk->put($path, $xml);
            $paths[] = $path;
        }

        return $paths;
    }

    /**
     * Always write a sitemap file even if empty (valid empty urlset).
     *
     * @return array<int, string>
     */
    private function writeEmptySitemap(string $baseName): array
    {
        $disk = Storage::disk('public');

        $filename = "{$baseName}.xml";
        $path = $this->storage->path($filename);

        $xml = $this->buildUrlsetXml([]); // empty urlset
        $disk->put($path, $xml);

        return [$path];
    }

    /**
     * Build sitemap index XML. Links ONLY to the generated part files.
     *
     * @param array<int, string> $paths
     */
    private function buildSitemapIndexXml(array $paths): string
    {
        $disk = Storage::disk('public');
        $base = rtrim((string) config('app.url'), '/');

        $dir = trim((string) $this->storage->directory(), '/');
        $prefix = $dir === '' ? '' : ($dir . '/');

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($paths as $path) {
            $filename = basename($path);

            // do not include sitemap.xml inside itself
            if ($filename === 'sitemap.xml') {
                continue;
            }

            $loc = $base . '/' . $prefix . $filename;

            $lastmod = null;
            if ($disk->exists($path)) {
                $ts = $disk->lastModified($path);
                if ($ts) {
                    $lastmod = date(DATE_ATOM, $ts);
                }
            }

            $out .= " <sitemap>\n";
            $out .= ' <loc>' . $this->xmlEscape($loc) . "</loc>\n";
            if ($lastmod) {
                $out .= ' <lastmod>' . $this->xmlEscape($lastmod) . "</lastmod>\n";
            }
            $out .= " </sitemap>\n";
        }

        $out .= "</sitemapindex>\n";

        return $out;
    }

    /**
    * Build a valid sitemap urlset XML.
    * IMPORTANT: XML must start immediately with "
    <?xml" (no spaces/newlines before).
         *
         * @param array<int, array{loc:string,lastmod:string,changefreq:string,priority:string}> $urls
         */
    private function buildUrlsetXml(array $urls): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $u) {
            $out .= " <url>\n";
            $out .= ' <loc>' . $this->xmlEscape($u['loc']) . "</loc>\n";
            $out .= ' <lastmod>' . $this->xmlEscape($u['lastmod']) . "</lastmod>\n";
            $out .= ' <changefreq>' . $this->xmlEscape($u['changefreq']) . "</changefreq>\n";
            $out .= ' <priority>' . $this->xmlEscape($u['priority']) . "</priority>\n";
            $out .= " </url>\n";
        }

        $out .= "</urlset>\n";

        return $out;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
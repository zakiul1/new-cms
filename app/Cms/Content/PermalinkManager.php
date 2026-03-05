<?php

namespace App\Cms\Content;

use App\Cms\Core\SettingsRepository;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Carbon;

class PermalinkManager
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * Canonical base URL for generating absolute URLs.
     * Uses CMS setting (core.site_url) first, falls back to APP_URL.
     */
    private function baseUrl(): string
    {
        $base = (string) $this->settings->get('core', 'site_url', (string) config('app.url'));
        $base = trim($base);

        if ($base === '') {
            $base = (string) config('app.url');
        }

        // Always store base without trailing slash; paths include leading slash.
        return rtrim($base, '/');
    }

    /**
     * Returns:
     *  - 'plain' (special case -> /?p=ID)
     *  - or a structure like '/%year%/%monthnum%/%postname%'
     */
    public function postStructure(): string
    {
        $mode = (string) $this->settings->get('core', 'permalink_mode', 'post_name');

        return match ($mode) {
            'plain' => 'plain',
            'day_name' => '/%year%/%monthnum%/%day%/%postname%',
            'month_name' => '/%year%/%monthnum%/%postname%',
            'numeric' => '/archives/%post_id%',
            'custom' => (string) $this->settings->get('core', 'permalink_custom_structure', '/%postname%'),
            default => '/%postname%',
        };
    }

    public function categoryBase(): string
    {
        $base = (string) $this->settings->get('core', 'category_base', 'category');
        $base = trim($base);

        return trim($base, '/');
    }

    public function tagBase(): string
    {
        $base = (string) $this->settings->get('core', 'tag_base', 'tag');
        $base = trim($base);

        return trim($base, '/');
    }

    /**
     * Generic "slug/path" builder for ANY frontend page.
     * This is the missing piece you need for:
     * - custom post types
     * - plugin-generated pages
     * - multipage generated links (where you only have a slug/path string)
     *
     * Examples:
     *  slugPath('about')        => '/about/'
     *  slugPath('/about')       => '/about/'
     *  slugPath('category/x')   => '/category/x/'
     *  slugPath('/')            => '/'
     */
    public function slugPath(string $slugOrPath): string
    {
        $slugOrPath = trim($slugOrPath);

        if ($slugOrPath === '' || $slugOrPath === '/') {
            return '/';
        }

        return $this->normalizePath('/' . trim($slugOrPath, '/'));
    }

    /**
     * Absolute URL builder for any slug/path (uses core.site_url).
     */
    public function slugUrl(string $slugOrPath): string
    {
        return $this->baseUrl() . $this->slugPath($slugOrPath);
    }

    public function postPath(Post $post, ?string $overrideSlug = null): string
    {
        $structure = $this->postStructure();

        // Plain permalinks stay query-based (no trailing-slash rule needed)
        if ($structure === 'plain') {
            return '/?p=' . $post->id;
        }

        $slug = trim((string) ($overrideSlug ?? $post->slug), '/');
        $dt = $this->postDate($post);

        $replaced = strtr($structure, [
            '%year%' => $dt->format('Y'),
            '%monthnum%' => $dt->format('m'),
            '%day%' => $dt->format('d'),
            '%hour%' => $dt->format('H'),
            '%minute%' => $dt->format('i'),
            '%second%' => $dt->format('s'),
            '%post_id%' => (string) $post->id,
            '%postname%' => $slug,
        ]);

        return $this->normalizePath($replaced);
    }

    /**
     * WP-like behavior:
     * - If this page is selected as "Homepage" (core.homepage_page_id),
     *   then its canonical path is "/" (not "/home/").
     */
    public function pagePath(Post $page, ?string $overrideSlug = null): string
    {
        $homepageId = $this->settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;

        if ($homepageId !== null && (int) $page->getKey() === $homepageId) {
            return '/';
        }

        $slug = trim((string) ($overrideSlug ?? $page->slug), '/');

        return $this->normalizePath($slug === '' ? '/' : '/' . $slug);
    }

    public function termPath(Term $term): string
    {
        $taxonomyKey = Taxonomy::query()->whereKey($term->taxonomy_id)->value('key') ?: 'term';

        $base = match ($taxonomyKey) {
            'category' => $this->categoryBase(),
            'tag' => $this->tagBase(),
            default => $taxonomyKey,
        };

        return $this->normalizePath('/' . $base . '/' . trim((string) $term->slug, '/'));
    }

    /**
     * Absolute URL builders (driven by core.site_url, WP-style).
     */
    public function postUrl(Post $post): string
    {
        return $this->baseUrl() . $this->postPath($post);
    }

    public function pageUrl(Post $page): string
    {
        return $this->baseUrl() . $this->pagePath($page);
    }

    public function termUrl(Term $term): string
    {
        return $this->baseUrl() . $this->termPath($term);
    }

    /**
     * Match incoming URL path (without leading slash) to the current post structure.
     * Returns:
     *  - ['id' => 123]
     *  - ['slug' => 'my-post']
     *  - null
     */
    public function matchPostPath(string $path): ?array
    {
        $structure = $this->postStructure();
        if ($structure === 'plain') {
            return null;
        }

        // Accept both with/without trailing slash (middleware may normalize, but this is safe)
        $path = trim($path, '/');
        $structure = trim($structure, '/');

        $map = [
            '%year%' => '(?P<year>\d{4})',
            '%monthnum%' => '(?P<month>\d{1,2})',
            '%day%' => '(?P<day>\d{1,2})',
            '%hour%' => '(?P<hour>\d{1,2})',
            '%minute%' => '(?P<minute>\d{1,2})',
            '%second%' => '(?P<second>\d{1,2})',
            '%post_id%' => '(?P<id>\d+)',
            '%postname%' => '(?P<slug>[^/]+)',
        ];

        $regex = preg_quote($structure, '#');
        foreach ($map as $tag => $pattern) {
            $regex = str_replace(preg_quote($tag, '#'), $pattern, $regex);
        }

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $m)) {
            return null;
        }

        if (!empty($m['id'])) {
            return ['id' => (int) $m['id']];
        }

        if (!empty($m['slug'])) {
            return ['slug' => (string) $m['slug']];
        }

        return null;
    }

    private function postDate(Post $post): Carbon
    {
        if (!empty($post->published_at)) {
            return Carbon::parse($post->published_at);
        }

        if (!empty($post->created_at)) {
            return Carbon::parse($post->created_at);
        }

        return now();
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');

        // New CMS standard: ALWAYS trailing slash, except root "/"
        return $path !== '/' ? rtrim($path, '/') . '/' : '/';
    }
}
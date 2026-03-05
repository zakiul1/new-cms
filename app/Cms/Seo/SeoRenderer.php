<?php

namespace App\Cms\Seo;

use App\Models\Post;
use Illuminate\Support\Str;

class SeoRenderer
{
    /**
     * Ensure a trailing slash on URLs (keeps query + hash).
     * Does NOT modify file-like URLs (e.g. .xml, .png, .css, .js) to avoid breaking assets.
     */
    protected function ensureTrailingSlash(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return $url;
        }

        $hash = '';
        $query = '';

        // Extract hash first
        if (str_contains($url, '#')) {
            [$url, $hash] = explode('#', $url, 2);
            $hash = '#' . $hash;
        }

        // Then extract query
        if (str_contains($url, '?')) {
            [$url, $query] = explode('?', $url, 2);
            $query = '?' . $query;
        }

        // If it looks like a file path, don't force trailing slash
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $lastSeg = basename($path);
            if ($lastSeg !== '' && str_contains($lastSeg, '.')) {
                return $url . $query . $hash;
            }
        }

        // Root-only URLs should stay as "/"
        $url = rtrim($url, '/') . '/';

        return $url . $query . $hash;
    }

    public function meta(?Post $post = null): string
    {
        // Prefer core.site_url (your CMS canonical base), fallback app.url
        $base = (string) config('app.url');
        $base = trim($base);
        if ($base === '') {
            $base = (string) config('app.url');
        }
        $base = rtrim($base, '/');

        // Defaults
        $title = (string) config('app.name', 'CMS');
        $desc = '';
        $canonical = $this->ensureTrailingSlash($base . '/');
        $robots = 'index, follow';
        $ogImage = null;

        if ($post) {
            $seo = is_array($post->meta_json) ? ($post->meta_json['seo'] ?? []) : [];
            $seo = is_array($seo) ? $seo : [];

            $title = trim((string) ($seo['title'] ?? '')) !== ''
                ? (string) $seo['title']
                : (string) ($post->title ?? $title);

            $desc = trim((string) ($seo['description'] ?? '')) !== ''
                ? (string) $seo['description']
                : (string) ($post->excerpt ?? '');

            $desc = Str::limit(trim(strip_tags($desc)), 160, '');

            $slug = trim((string) ($post->slug ?? ''), '/');

            // If user set canonical manually, respect it but normalize trailing slash (unless file-like)
            $canonical = trim((string) ($seo['canonical'] ?? '')) !== ''
                ? (string) $seo['canonical']
                : ($slug === '' ? $base . '/' : $base . '/' . $slug . '/');

            $canonical = $this->ensureTrailingSlash($canonical);

            $robots = trim((string) ($seo['robots'] ?? '')) !== ''
                ? (string) $seo['robots']
                : $robots;

            $ogImage = trim((string) ($seo['og_image'] ?? '')) !== '' ? (string) $seo['og_image'] : null;
        }

        // Build tags
        $tags = [];
        $tags[] = '<title>' . e($title) . '</title>';
        $tags[] = '<link rel="canonical" href="' . e($canonical) . '">';

        if ($desc !== '') {
            $tags[] = '<meta name="description" content="' . e($desc) . '">';
        }

        $tags[] = '<meta name="robots" content="' . e($robots) . '">';

        // Open Graph
        $tags[] = '<meta property="og:title" content="' . e($title) . '">';
        if ($desc !== '') {
            $tags[] = '<meta property="og:description" content="' . e($desc) . '">';
        }
        $tags[] = '<meta property="og:url" content="' . e($canonical) . '">';
        $tags[] = '<meta property="og:type" content="' . e($post?->type === 'post' ? 'article' : 'website') . '">';

        if ($ogImage) {
            $tags[] = '<meta property="og:image" content="' . e($ogImage) . '">';
        }

        return implode("\n", $tags) . "\n";
    }

    public function jsonLd(?Post $post = null): string
    {
        $base = (string) config('app.url');
        $base = trim($base);
        if ($base === '') {
            $base = (string) config('app.url');
        }
        $base = rtrim($base, '/');

        // Website JSON-LD for home / generic pages
        if (!$post) {
            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => (string) config('app.name', 'CMS'),
                'url' => $this->ensureTrailingSlash($base . '/'),
            ];

            return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }

        $slug = trim((string) ($post->slug ?? ''), '/');
        $url = $slug === '' ? $base . '/' : $base . '/' . $slug . '/';
        $url = $this->ensureTrailingSlash($url);

        if ($post->type === 'post') {
            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => (string) ($post->title ?? ''),
                'description' => Str::limit(trim(strip_tags((string) ($post->excerpt ?? ''))), 160, ''),
                'datePublished' => optional($post->published_at)->toAtomString(),
                'dateModified' => optional($post->updated_at)->toAtomString(),
                'mainEntityOfPage' => [
                    '@type' => 'WebPage',
                    '@id' => $url,
                ],
            ];
        } else {
            $data = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => (string) ($post->title ?? ''),
                'description' => Str::limit(trim(strip_tags((string) ($post->excerpt ?? ''))), 160, ''),
                'url' => $url,
                'dateModified' => optional($post->updated_at)->toAtomString(),
            ];
        }

        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
}
<?php

namespace App\Cms\Seo;

use App\Models\Post;

class Seo
{
    /**
     * Ensure a trailing slash on non-file URLs (keeps query + hash).
     * Does NOT add slash to "file-like" URLs (e.g. .xml, .png, .css, .js).
     */
    protected static function ensureTrailingSlash(string $url): string
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

        $url = rtrim($url, '/') . '/';

        return $url . $query . $hash;
    }

    /**
     * Build <title> + meta tags for a post/page.
     * Call in theme <head>.
     */
    public static function tagsForPost(Post $post, array $ctx = []): string
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];
        $seo = isset($meta['seo']) && is_array($meta['seo']) ? $meta['seo'] : [];

        $siteName = (string) ($ctx['site_name'] ?? config('app.name'));
        $url = (string) ($ctx['url'] ?? self::permalink($post));
        $url = self::ensureTrailingSlash($url);

        // ✅ Use your Filament keys
        $title = trim((string) ($seo['title'] ?? ''));
        if ($title === '') {
            $title = trim((string) ($post->title ?? ''));
        }
        if ($siteName !== '' && $title !== '') {
            // Optional branding - you can remove if you don't want it
            $titleWithBrand = $title . ' | ' . $siteName;
        } else {
            $titleWithBrand = $title;
        }

        $desc = trim((string) ($seo['description'] ?? ''));
        if ($desc === '') {
            $desc = trim((string) ($post->excerpt ?? ''));
        }

        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '') {
            $canonical = $url;
        }
        $canonical = self::ensureTrailingSlash($canonical);

        // ✅ Robots: single string from your Select (or default)
        $robots = trim((string) ($seo['robots'] ?? ''));
        if ($robots === '') {
            $robots = 'index, follow';
        }

        // Social
        $ogTitle = trim((string) ($seo['og_title'] ?? '')) ?: $title;
        $ogDesc = trim((string) ($seo['og_description'] ?? '')) ?: $desc;
        $ogImage = trim((string) ($seo['og_image'] ?? ''));

        // Optional twitter card override
        $twitterCard = trim((string) ($seo['twitter_card'] ?? 'summary_large_image'));

        $type = $post->type === 'page' ? 'website' : 'article';

        $out = '';
        $out .= '<title>' . e($titleWithBrand) . '</title>' . PHP_EOL;

        if ($desc !== '') {
            $out .= '<meta name="description" content="' . e($desc) . '">' . PHP_EOL;
        }

        $out .= '<link rel="canonical" href="' . e($canonical) . '">' . PHP_EOL;
        $out .= '<meta name="robots" content="' . e($robots) . '">' . PHP_EOL;

        // OpenGraph (✅ og:url should match canonical)
        $out .= '<meta property="og:type" content="' . e($type) . '">' . PHP_EOL;
        $out .= '<meta property="og:url" content="' . e($canonical) . '">' . PHP_EOL;
        $out .= '<meta property="og:title" content="' . e($ogTitle) . '">' . PHP_EOL;

        if ($ogDesc !== '') {
            $out .= '<meta property="og:description" content="' . e($ogDesc) . '">' . PHP_EOL;
        }
        if ($ogImage !== '') {
            $out .= '<meta property="og:image" content="' . e($ogImage) . '">' . PHP_EOL;
        }

        // Twitter
        if ($twitterCard !== '') {
            $out .= '<meta name="twitter:card" content="' . e($twitterCard) . '">' . PHP_EOL;
        }
        if ($ogTitle !== '') {
            $out .= '<meta name="twitter:title" content="' . e($ogTitle) . '">' . PHP_EOL;
        }
        if ($ogDesc !== '') {
            $out .= '<meta name="twitter:description" content="' . e($ogDesc) . '">' . PHP_EOL;
        }
        if ($ogImage !== '') {
            $out .= '<meta name="twitter:image" content="' . e($ogImage) . '">' . PHP_EOL;
        }

        return $out;
    }

    /**
     * Adjust permalink rules to match your routing.
     * Current default:
     * - page => /{slug}/
     * - post => /blog/{slug}/
     */
    public static function permalink(Post $post): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $slug = trim((string) ($post->slug ?? ''), '/');

        if ($post->type === 'page') {
            return $slug === '' ? ($base . '/') : ($base . '/' . $slug . '/');
        }

        return $base . '/blog/' . $slug . '/';
    }
}
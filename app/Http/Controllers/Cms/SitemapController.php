<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        // Cache for performance (premium feel)
        $xml = Cache::remember('cms:sitemap:xml:v1', now()->addMinutes(30), function () {
            $base = rtrim((string) config('app.url'), '/');
            $now = now()->toAtomString();

            $urls = [];

            // Home
            $urls[] = [
                'loc' => $base . '/',
                'lastmod' => $now,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];

            $posts = Post::query()
                ->whereIn('type', ['post', 'page'])
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->orderByDesc('updated_at')
                ->get(['type', 'slug', 'updated_at', 'published_at']);

            foreach ($posts as $p) {
                $slug = trim((string) $p->slug, '/');
                $loc = $slug === '' ? ($base . '/') : ($base . '/' . $slug);

                $lastmod = ($p->updated_at ?? $p->published_at ?? now())->toAtomString();

                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => $lastmod,
                    'changefreq' => $p->type === 'page' ? 'monthly' : 'weekly',
                    'priority' => $p->type === 'page' ? '0.7' : '0.6',
                ];
            }

            // Build XML
            $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $out .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            foreach ($urls as $u) {
                $out .= " <url>\n";
                $out .= " <loc>" . e($u['loc']) . "</loc>\n";
                $out .= " <lastmod>" . e($u['lastmod']) . "</lastmod>\n";
                $out .= " <changefreq>" . e($u['changefreq']) . "</changefreq>\n";
                $out .= " <priority>" . e($u['priority']) . "</priority>\n";
                $out .= " </url>\n";
            }

            $out .= "</urlset>\n";

            return $out;
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $base = rtrim((string) config('app.url'), '/');
        $sitemapUrl = $base . '/sitemap.xml';

        $txt = implode("\n", [
            "User-agent: *",
            "Disallow: /admin",
            "Disallow: /livewire",
            "Disallow: /vendor",
            "Disallow: /build",
            "",
            "Sitemap: {$sitemapUrl}",
            "",
        ]);

        return response($txt, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
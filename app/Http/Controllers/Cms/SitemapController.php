<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\PermalinkManager;
use App\Cms\Core\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * GET /sitemap.xml
     */
    public function index(PermalinkManager $permalinks, SettingsRepository $settings): Response
    {
        // Cache key version bump (change when you change sitemap logic)
        $cacheKey = 'cms:sitemap:xml:v3';

        $xml = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($permalinks, $settings) {
            $base = rtrim((string) config('app.url'), '/');
            $nowAtom = now()->toAtomString();

            $urls = [];

            // Home
            $urls[] = [
                'loc' => $base . '/',
                'lastmod' => $nowAtom,
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];

            // Posts + Pages
            $posts = Post::query()
                ->whereIn('type', ['post', 'page'])
                ->where('status', 'published')
                ->where(function ($q) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', now());
                })
                ->orderByDesc('updated_at')
                ->get(['id', 'type', 'slug', 'updated_at', 'published_at']);

            foreach ($posts as $p) {
                $lastmod = ($p->updated_at ?? $p->published_at ?? now())->toAtomString();

                // Use permalink manager so sitemap matches current permalink settings
                $loc = $p->type === 'page'
                    ? $permalinks->pageUrl($p)
                    : $permalinks->postUrl($p);

                $urls[] = [
                    'loc' => $loc,
                    'lastmod' => $lastmod,
                    'changefreq' => $p->type === 'page' ? 'monthly' : 'weekly',
                    'priority' => $p->type === 'page' ? '0.7' : '0.6',
                ];
            }

            // ✅ Attachment pages (Media) – only when enabled + indexable globally and per-item
            $attachmentsEnabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);
            $attachmentsIndexableGlobal = (bool) $settings->get('core', 'attachment_pages_indexable', true);

            if ($attachmentsEnabled && $attachmentsIndexableGlobal) {
                $mediaCategoryTaxonomyId = \App\Models\Taxonomy::query()->where('key', 'media_category')->value('id');

                $mediaItems = Media::query()
                    ->whereNotNull('slug')
                    ->where('slug', '!=', '')
                    ->where('attachment_public', true)
                    ->where('attachment_indexable', true)
                    // ✅ exclude media that belongs to ANY private media_category
                    ->when($mediaCategoryTaxonomyId, function ($q) use ($mediaCategoryTaxonomyId) {
                        $q->whereDoesntHave('terms', function ($t) use ($mediaCategoryTaxonomyId) {
                            $t->where('terms.taxonomy_id', $mediaCategoryTaxonomyId)
                                ->where('terms.visibility', 'private');
                        });
                    })
                    ->orderByDesc('updated_at')
                    ->get(['slug', 'updated_at', 'created_at']);

                foreach ($mediaItems as $m) {
                    $slug = trim((string) $m->slug, '/');
                    if ($slug === '') {
                        continue;
                    }

                    $lastmod = ($m->updated_at ?? $m->created_at ?? now())->toAtomString();

                    $urls[] = [
                        'loc' => $base . '/' . $slug,
                        'lastmod' => $lastmod,
                        'changefreq' => 'monthly',
                        'priority' => '0.3',
                    ];
                }
            }

            // Build XML
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
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * GET /robots.txt (optional)
     */
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

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
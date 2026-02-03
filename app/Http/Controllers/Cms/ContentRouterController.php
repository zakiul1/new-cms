<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Core\SettingsRepository;
use App\Cms\Content\PermalinkManager;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SlugHistory;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Http\Request;

class ContentRouterController extends Controller
{
    /**
     * Home route handler so "Plain" (?p=123) works like WP.
     */
    public function home(Request $request, PermalinkManager $permalinks)
    {
        $p = $request->query('p');

        if (is_numeric($p)) {
            $post = $this->findPublishedPostById((int) $p);

            if ($post) {
                [$css, $js] = $this->extractPostAssets($post);

                return view('post', [
                    'post' => $post,
                    'seo' => $this->buildSeo($post, $request, $permalinks),

                    // ✅ per-page assets
                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,
                ]);
            }
        }

        return view('home');
    }

    public function show(Request $request, string $slug, PermalinkManager $permalinks, SettingsRepository $settings)
    {
        $slug = trim($slug, '/');
        $path = $slug === '' ? '/' : '/' . $slug;

        // Normalize trailing slash (site standard = no trailing slash)
        if ($path !== '/' && str_ends_with($request->getPathInfo(), '/')) {
            return redirect()->to(rtrim($request->getPathInfo(), '/'), 301);
        }

        // ✅ Handle category/tag base dynamically (WP-like bases)
        if ($slug !== '') {
            $first = explode('/', $slug, 2)[0];

            if ($first === $permalinks->categoryBase()) {
                $termSlug = explode('/', $slug, 2)[1] ?? '';
                return $this->renderTermArchive('category', $termSlug);
            }

            if ($first === $permalinks->tagBase()) {
                $termSlug = explode('/', $slug, 2)[1] ?? '';
                return $this->renderTermArchive('tag', $termSlug);
            }
        }

        $now = now();

        // 1) Pages stay WP-style: only single-segment /{slug}
        if ($slug !== '' && !str_contains($slug, '/')) {
            $page = Post::query()
                ->where('type', 'page')
                ->where('slug', $slug)
                ->where('status', 'published')
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                })
                ->first();

            if ($page) {
                $canonicalPath = $permalinks->pagePath($page);
                if ($path !== $canonicalPath) {
                    return redirect()->to($canonicalPath, 301);
                }

                [$css, $js] = $this->extractPostAssets($page);

                return view('page', [
                    'post' => $page,
                    'seo' => $this->buildSeo($page, $request, $permalinks),

                    // ✅ per-page assets
                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,
                ]);
            }
        }

        // 2) Posts via current permalink structure
        $match = $permalinks->matchPostPath($slug);

        $post = null;

        if (is_array($match) && isset($match['id'])) {
            $post = $this->findPublishedPostById((int) $match['id']);
        } elseif (is_array($match) && isset($match['slug'])) {
            $post = $this->findPublishedPostBySlug((string) $match['slug']);
        } else {
            // If structure is /%postname% and user requested single segment, it will match.
            // Otherwise: no post match.
        }

        if ($post) {
            // Canonical redirect to chosen structure (SEO)
            $canonicalPath = $permalinks->postPath($post);
            if ($canonicalPath !== '/?p=' . $post->id) {
                if ($path !== $canonicalPath) {
                    return redirect()->to($canonicalPath, 301);
                }
            }

            [$css, $js] = $this->extractPostAssets($post);

            return view('post', [
                'post' => $post,
                'seo' => $this->buildSeo($post, $request, $permalinks),

                // ✅ per-page assets
                'pageAssetsCss' => $css,
                'pageAssetsJs' => $js,
            ]);
        }

        // 3) Attachment pages (Media) - only after no page and no post
        // Root-level /{media-slug} like your example
        if ($slug !== '' && !str_contains($slug, '/')) {
            $attachmentsEnabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);

            if ($attachmentsEnabled) {
                $media = Media::query()
                    ->where('slug', $slug)
                    ->where('attachment_public', true) // ✅ per-attachment show/hide
                    ->first();

                if ($media) {
                    // Canonical: ensure correct slug path (usually always true)
                    $canonicalPath = '/' . $media->slug;
                    if ($path !== $canonicalPath) {
                        return redirect()->to($canonicalPath, 301);
                    }

                    $globalIndexable = (bool) $settings->get('core', 'attachment_pages_indexable', true);
                    $indexable = $globalIndexable && (bool) $media->attachment_indexable;

                    // Optional: show where this media is used
                    $usedIn = $media->posts()
                        ->whereIn('type', ['post', 'page'])
                        ->where('status', 'published')
                        ->where(function ($q) use ($now) {
                            $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                        })
                        ->latest('published_at')
                        ->limit(12)
                        ->get(['posts.id', 'posts.type', 'posts.title', 'posts.slug', 'posts.published_at']);

                    [$css, $js] = $this->extractMediaAssets($media);

                    return view('attachment', [
                        'media' => $media,
                        'usedIn' => $usedIn,
                        'seo' => $this->buildAttachmentSeo($media, $indexable),

                        // ✅ per-page assets
                        'pageAssetsCss' => $css,
                        'pageAssetsJs' => $js,
                    ]);
                }
            }
        }

        // 4) Slug history fallback (auto-heal redirect row if missing)
        // For multi-segment permalinks, try extracting the %postname% part if possible.
        $oldSlugCandidate = null;

        if (is_array($match) && isset($match['slug'])) {
            $oldSlugCandidate = (string) $match['slug'];
        } elseif ($slug !== '' && !str_contains($slug, '/')) {
            $oldSlugCandidate = $slug;
        }

        if ($oldSlugCandidate !== null && $oldSlugCandidate !== '') {
            $history = SlugHistory::query()
                ->whereIn('entity_type', ['post', 'page'])
                ->where('old_slug', $oldSlugCandidate)
                ->latest('id')
                ->first();

            if ($history) {
                $current = Post::query()->find($history->entity_id);
                if ($current && $current->status === 'published') {
                    $to = $current->type === 'page'
                        ? $permalinks->pagePath($current)
                        : $permalinks->postPath($current);

                    Redirect::query()->updateOrCreate(
                        ['from_path' => $path],
                        ['to_path' => $to, 'status_code' => 301]
                    );

                    return redirect()->to($to, 301);
                }
            }
        }

        abort(404);
    }

    private function buildSeo(Post $post, Request $request, PermalinkManager $permalinks): array
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];
        $seo = isset($meta['seo']) && is_array($meta['seo']) ? $meta['seo'] : [];

        $title = trim((string) ($seo['title'] ?? $post->title ?? config('app.name')));
        $desc = trim((string) ($seo['description'] ?? $post->excerpt ?? ''));

        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '') {
            $canonical = $post->type === 'page'
                ? $permalinks->pageUrl($post)
                : $permalinks->postUrl($post);
        }

        $robots = trim((string) ($seo['robots'] ?? ''));
        if ($robots === '') {
            $robots = 'index, follow';
        }

        $ogImage = trim((string) ($seo['og_image'] ?? ''));

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,

            'og' => [
                'title' => $title,
                'description' => $desc,
                'type' => $post->type === 'page' ? 'website' : 'article',
                'url' => $canonical,
                'image' => $ogImage,
            ],
        ];
    }

    private function buildAttachmentSeo(Media $media, bool $indexable): array
    {
        $meta = is_array($media->meta) ? $media->meta : [];
        $seo = data_get($meta, 'seo', []);
        $seo = is_array($seo) ? $seo : [];

        $fallbackTitle = (string) ($media->title ?: $media->original_filename ?: config('app.name'));

        // For description: prefer saved SEO description; fallback to description/caption (strip tags for SEO)
        $fallbackDesc = (string) ($media->description ?: $media->caption ?: '');
        $fallbackDesc = trim(strip_tags($fallbackDesc));

        $title = trim((string) ($seo['title'] ?? $fallbackTitle));
        $desc = trim((string) ($seo['description'] ?? $fallbackDesc));

        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '') {
            $canonical = url('/' . $media->slug);
        }

        // If SEO robots set, respect it; otherwise use indexable bool.
        $robots = trim((string) ($seo['robots'] ?? ''));
        if ($robots === '') {
            $robots = $indexable ? 'index, follow' : 'noindex, follow';
        }

        $ogImage = trim((string) ($seo['og_image'] ?? ''));

        // If no og_image specified, use image URL if this is image.
        if ($ogImage === '' && method_exists($media, 'isImage') && $media->isImage() && method_exists($media, 'url')) {
            $ogImage = (string) $media->url();
        }

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,
            'og' => [
                'title' => $title,
                'description' => $desc,
                'type' => 'article',
                'url' => $canonical,
                'image' => $ogImage,
            ],
        ];
    }

    private function findPublishedPostById(int $id): ?Post
    {
        $now = now();

        return Post::query()
            ->where('type', 'post')
            ->whereKey($id)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();
    }

    private function findPublishedPostBySlug(string $slug): ?Post
    {
        $now = now();

        return Post::query()
            ->where('type', 'post')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->first();
    }

    private function renderTermArchive(string $taxonomyKey, string $slug)
    {
        $slug = trim($slug, '/');
        abort_if($slug === '', 404);

        $taxonomyId = Taxonomy::query()->where('key', $taxonomyKey)->value('id');
        abort_unless($taxonomyId, 404);

        $term = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('slug', $slug)
            ->firstOrFail();

        $posts = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->whereHas($taxonomyKey === 'category' ? 'categories' : 'tags', fn($q) => $q->whereKey($term->id))
            ->latest('id')
            ->paginate(18);

        return view('archive', [
            'title' => $term->name,
            'term' => $term,
            'posts' => $posts,
        ]);
    }

    /**
     * ✅ Per Post/Page assets from meta_json.assets.{css,js}
     *
     * @return array{0:string,1:string} [css, js]
     */
    private function extractPostAssets(Post $post): array
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];
        $assets = $meta['assets'] ?? [];

        if (!is_array($assets)) {
            $assets = [];
        }

        $css = (string) ($assets['css'] ?? '');
        $js = (string) ($assets['js'] ?? '');

        return [$css, $js];
    }

    /**
     * ✅ Per Media assets from meta.assets.{css,js}
     *
     * @return array{0:string,1:string} [css, js]
     */
    private function extractMediaAssets(Media $media): array
    {
        $meta = is_array($media->meta) ? $media->meta : [];

        $css = (string) data_get($meta, 'assets.css', '');
        $js = (string) data_get($meta, 'assets.js', '');

        return [$css, $js];
    }
}
<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\PermalinkManager;
use App\Cms\Core\SettingsRepository;
use App\Cms\Content\CurrentContentContext;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SlugHistory;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Http\Request;

// ✅ Correct Filament resources (fixes your admin bar edit URLs)
use App\Filament\Resources\Posts\PostResource as FilamentPostResource;
use App\Filament\Resources\Pages\PageResource as FilamentPageResource;
use App\Filament\Resources\MediaResource as FilamentMediaResource;

class ContentRouterController extends Controller
{
    /**
     * Home route handler so "Plain" (?p=123) works like WP.
     */
    public function home(Request $request, PermalinkManager $permalinks, SettingsRepository $settings)
    {
        $p = $request->query('p');

        if (is_numeric($p)) {
            $post = $this->findPublishedPostById((int) $p);

            if ($post) {
                [$css, $js] = $this->extractPostAssets($post);

                return view('post', [
                    'post' => $post,
                    'seo' => $this->buildSeo($post, $request, $permalinks),

                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,

                    // ✅ Post edit URL (safe hard URL)
                    'adminEditUrl' => url('/lara-admin/posts/' . $post->getKey() . '/edit'),
                ]);
            }
        }

        // ✅ Default: dashboard
        $adminEditUrl = url('/lara-admin');

        // ✅ IMPORTANT: read homepage id from the SAME settings class used by home.blade.php
        $settingsLegacy = app(\App\Cms\Core\Settings::class);
        $homepageId = $settingsLegacy->get('homepage_page_id', null, 'core');

        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        if ($homepageId !== null) {
            $homePage = Post::query()
                ->whereKey($homepageId)
                ->where('type', 'page')
                ->first();

            if ($homePage) {
                // ✅ Home edit URL (safe hard URL)
                $adminEditUrl = url('/lara-admin/pages/' . $homePage->getKey() . '/edit');
            }
        }

        return view('home', [
            'privateNotice' => $request->query('private') === '1',
            'privateFrom' => (string) $request->query('from', ''),
            'adminEditUrl' => $adminEditUrl,
        ]);
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
                return $this->renderTermArchive($request, 'category', $termSlug, $path);
            }

            if ($first === $permalinks->tagBase()) {
                $termSlug = explode('/', $slug, 2)[1] ?? '';
                return $this->renderTermArchive($request, 'tag', $termSlug, $path);
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

                    // ✅ Admin bar edit url (PAGE resource)
                    'adminEditUrl' => class_exists(FilamentPageResource::class)
                        ? FilamentPageResource::getUrl('edit', ['record' => $page])
                        : url('/lara-admin'),
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

                // ✅ Admin bar edit url (POST resource)
                'adminEditUrl' => class_exists(FilamentPostResource::class)
                    ? FilamentPostResource::getUrl('edit', ['record' => $post])
                    : url('/lara-admin'),
            ]);
        }

        // 3) Attachment pages (Media) - only after no page and no post
        // Root-level /{media-slug}
        if ($slug !== '' && !str_contains($slug, '/')) {
            $attachmentsEnabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);

            if ($attachmentsEnabled) {
                // ✅ IMPORTANT: do NOT exclude private here; we want to catch it and redirect (not 404)
                $media = Media::query()
                    ->where('slug', $slug)
                    ->where('attachment_public', true) // ✅ per-attachment show/hide
                    ->first();

                if ($media) {
                    // ✅ HARD BLOCK: private category => redirect to admin with notice
                    if ($this->hasPrivateMediaCategory($media)) {
                        return redirect()->to('/lara-admin?private=1&from=' . urlencode($path));
                    }

                    // Canonical: ensure correct slug path
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

                    // ✅ Apply defaults for frontend rendering (NO DB save)
                    // IMPORTANT: do NOT refresh(), otherwise in-memory defaults are lost.
                    if (function_exists('do_action')) {
                        do_action('media.attachment.defaults.persist', $media);

                        // ✅ DO NOT refresh in preview mode, otherwise in-memory preview changes are lost
                        if ($request->query('md_preview') !== '1') {
                            $media->refresh();
                        }
                    }

                    // ✅ Public media categories (for top navigation) + active category
                    $publicMediaCategories = $this->publicMediaCategories();

                    $activeMediaCategory = null;
                    if (method_exists($media, 'categories')) {
                        $activeMediaCategory = $media->categories()
                            ->where('terms.visibility', 'public')
                            ->orderBy('terms.name')
                            ->first();
                    }

                    app(CurrentContentContext::class)->setMedia($media);

                    return view('attachment', [
                        'media' => $media,
                        'usedIn' => $usedIn,
                        'seo' => $this->buildAttachmentSeo($media, $indexable),

                        // ✅ For frontend category navigation
                        'mediaCategories' => $publicMediaCategories,
                        'activeMediaCategory' => $activeMediaCategory,

                        // ✅ per-page assets
                        'pageAssetsCss' => $css,
                        'pageAssetsJs' => $js,

                        // ✅ Admin bar edit url (MEDIA resource)
                        'adminEditUrl' => class_exists(FilamentMediaResource::class)
                            ? FilamentMediaResource::getUrl('edit', ['record' => $media])
                            : url('/lara-admin'),
                    ]);
                }
            }
        }

        // 4) Slug history fallback (auto-heal redirect row if missing)
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

    /**
     * ✅ If media has ANY "media_category" term with visibility=private
     */
    private function hasPrivateMediaCategory(Media $media): bool
    {
        if (method_exists($media, 'categories')) {
            return $media->categories()
                ->where('terms.visibility', 'private')
                ->exists();
        }

        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId || !method_exists($media, 'terms')) {
            return false;
        }

        return $media->terms()
            ->where('terms.taxonomy_id', $taxonomyId)
            ->where('terms.visibility', 'private')
            ->exists();
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

        // ✅ Prefer frontend meta defaults (from plugin) as fallback
        $frontendMetaTitle = trim((string) data_get($meta, 'frontend.meta_title', ''));
        $frontendMetaDescRaw = data_get($meta, 'frontend.meta_description', '');

        $frontendMetaDesc = '';
        if (function_exists('media_defaults_html_value')) {
            $frontendMetaDesc = trim(strip_tags(media_defaults_html_value($frontendMetaDescRaw)));
        } else {
            $frontendMetaDesc = trim(strip_tags((string) $frontendMetaDescRaw));
        }

        $fallbackTitle = (string) (
            $frontendMetaTitle !== ''
            ? $frontendMetaTitle
            : ($media->title ?: $media->original_filename ?: config('app.name'))
        );

        $fallbackDescSource = $frontendMetaDesc !== ''
            ? $frontendMetaDesc
            : ($media->description ?: $media->caption ?: '');

        $fallbackDesc = trim(strip_tags((string) $fallbackDescSource));

        $title = trim((string) ($seo['title'] ?? $fallbackTitle));
        $desc = trim((string) ($seo['description'] ?? $fallbackDesc));

        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '') {
            $canonical = url('/' . $media->slug);
        }

        $robots = trim((string) ($seo['robots'] ?? ''));
        if ($robots === '') {
            $robots = $indexable ? 'index, follow' : 'noindex, follow';
        }

        $ogImage = trim((string) ($seo['og_image'] ?? ''));

        if (
            $ogImage === '' &&
            method_exists($media, 'isImage') &&
            $media->isImage() &&
            method_exists($media, 'url')
        ) {
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

    // ✅ FIXED: now receives $request so $request is defined
    private function renderTermArchive(Request $request, string $taxonomyKey, string $slug, string $requestedPath)
    {
        $slug = trim($slug, '/');
        abort_if($slug === '', 404);

        $taxonomyId = Taxonomy::query()->where('key', $taxonomyKey)->value('id');
        abort_unless($taxonomyId, 404);

        $term = Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('slug', $slug)
            ->firstOrFail();

        // ✅ HARD BLOCK: private term => redirect to admin with notice
        if (($term->visibility ?? 'public') !== 'public') {
            return redirect()->to('/lara-admin?private=1&from=' . urlencode($request->getPathInfo()));
        }

        // ✅ scheduled-safe
        $now = now();

        $posts = Post::query()
            ->where('type', 'post')
            ->where('status', 'published')
            ->where(function ($q) use ($now) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
            })
            ->whereHas($taxonomyKey === 'category' ? 'categories' : 'tags', fn($q) => $q->whereKey($term->id))
            ->latest('id')
            ->paginate(18);

        return view('archive', [
            'title' => $term->name,
            'term' => $term,
            'posts' => $posts,

            // ✅ Archive doesn't map to a single record edit
            'adminEditUrl' => url('/lara-admin'),
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

    private function publicMediaCategories(): array
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');

        if (!$taxonomyId) {
            return [];
        }

        return Term::query()
            ->where('taxonomy_id', $taxonomyId)
            ->where('visibility', 'public')
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(fn(Term $t) => [
                'id' => (int) $t->id,
                'name' => (string) $t->name,
                'slug' => (string) $t->slug,
            ])
            ->all();
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
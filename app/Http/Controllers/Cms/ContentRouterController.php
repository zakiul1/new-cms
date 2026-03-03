<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Content\CurrentContentContext;
use App\Cms\Content\PermalinkManager;
use App\Cms\Core\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\SlugHistory;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// ✅ Correct Filament resources (fixes your admin bar edit URLs)
use App\Filament\Resources\MediaResource as FilamentMediaResource;
use App\Filament\Resources\Pages\PageResource as FilamentPageResource;
use App\Filament\Resources\Posts\PostResource as FilamentPostResource;

class ContentRouterController extends Controller
{
    /**
     * Home route handler so "Plain" (?p=123) works like WP.
     * Also renders the selected homepage page (WP "static front page").
     */
    public function home(Request $request, PermalinkManager $permalinks, SettingsRepository $settings)
    {
        // Plain permalink: /?p=123
        $p = $request->query('p');

        if (is_numeric($p)) {
            $post = $this->findPublishedPostById((int) $p);

            if ($post) {
                [$css, $js] = $this->extractPostAssets($post);

                return view($this->resolveFrontendView($post, fallback: 'post', request: $request), [
                    'post' => $post,
                    'seo' => $this->buildSeo($post, $request, $permalinks),

                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,

                    'adminEditUrl' => class_exists(FilamentPostResource::class)
                        ? FilamentPostResource::getUrl('edit', ['record' => $post])
                        : url('/lara-admin'),
                ]);
            }
        }

        // ✅ One standard homepage system: core.homepage_page_id
        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        if ($homepageId !== null) {
            $now = now();

            $homePage = Post::query()
                ->whereKey($homepageId)
                ->where('type', 'page')
                ->where('status', 'published')
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                })
                ->first();

            if ($homePage) {
                [$css, $js] = $this->extractPostAssets($homePage);

                return view($this->resolveFrontendView($homePage, fallback: 'page', request: $request), [
                    'post' => $homePage,
                    'seo' => $this->buildSeo($homePage, $request, $permalinks),

                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,

                    'adminEditUrl' => class_exists(FilamentPageResource::class)
                        ? FilamentPageResource::getUrl('edit', ['record' => $homePage])
                        : url('/lara-admin'),
                ]);
            }
        }

        return view('home', [
            'privateNotice' => $request->query('private') === '1',
            'privateFrom' => (string) $request->query('from', ''),
            'adminEditUrl' => url('/lara-admin'),
        ]);
    }

    // ✅ FIX: slug can be null when "/" matches catch-all
    public function show(Request $request, ?string $slug = null, PermalinkManager $permalinks, SettingsRepository $settings)
    {
        // Slug is route param; normalize for matching (no leading/trailing slash)
        $slug = trim((string) $slug, '/');
        $path = $request->getPathInfo(); // keeps "/slug/" when present

        // ✅ Normalize to "/" if double slash happens
        if ($path === '//' || $path === '') {
            $path = '/';
        }

        /**
         * ✅ MultiPage generated link resolver (single OR multi segment)
         * IMPORTANT:
         * - Must run BEFORE normal page/post lookup
         * - Prevent recursion (resolver calls this controller again)
         * - If resolver can resolve this URL, return its response
         */
        if (
            $slug !== ''
            && class_exists(\Plugins\MultiPage\Support\MultiPageResolver::class)
            && !$request->attributes->get('multipage_resolving')
        ) {
            try {
                $request->attributes->set('multipage_resolving', true);

                $resolver = new \Plugins\MultiPage\Support\MultiPageResolver();
                $resp = $resolver->handle($request, $slug);

                if ($resp !== null) {
                    return $resp;
                }
            } finally {
                $request->attributes->set('multipage_resolving', false);
            }
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

        // ✅ NEW: Siatex Tags plugin resolver (no "/tag/" prefix)
        // Only for single-segment slugs (same as pages/attachments), and only if plugin model exists.
        if ($slug !== '' && !str_contains($slug, '/') && class_exists(\Plugins\SiatexTags\Models\SiatexTag::class)) {
            $tag = \Plugins\SiatexTags\Models\SiatexTag::query()
                ->where('slug', $slug)
                ->first();

            if ($tag) {
                // Make current tag available to shortcode parsing (context + fallback)
                $request->attributes->set('siatex_tag', $tag);

                $termId = (int) ($tag->media_category_term_id ?? 0);
                $media = collect();

                if ($termId > 0) {
                    $ids = DB::table('termables')
                        ->where('term_id', $termId)
                        ->where('termable_type', \App\Models\Media::class)
                        ->pluck('termable_id')
                        ->map(fn($v) => (int) $v)
                        ->unique()
                        ->values()
                        ->all();

                    if (!empty($ids)) {
                        $media = \App\Models\Media::query()
                            ->whereIn('id', $ids)
                            ->latest('id')
                            ->get();
                    }
                }

                $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
                $seo = is_array($meta['seo'] ?? null) ? $meta['seo'] : [];

                if (!isset($seo['title']) || trim((string) $seo['title']) === '') {
                    $seo['title'] = $tag->title;
                }

                // Optional canonical enforcement: remove trailing slash differences
                $canonicalPath = '/' . trim((string) $tag->slug, '/');
                if ($this->pathsDiffer($path, $canonicalPath)) {
                    return $this->redirectPreserveQuery($request, $canonicalPath, 301);
                }

                return view('siatex-tags::show', [
                    'tag' => $tag,
                    'mediaItems' => $media,
                    'seo' => $seo,
                ]);
            }
        }

        $now = now();

        /**
         * ✅ 1) Pages + MultiPages stay WP-style: only single-segment /{slug}
         * MultiPage CPT: posts.type = 'multipage'
         */
        if ($slug !== '' && !str_contains($slug, '/')) {
            $page = Post::query()
                ->whereIn('type', ['page', 'multipage'])
                ->where('slug', $slug)
                ->where('status', 'published')
                ->where(function ($q) use ($now) {
                    $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                })
                ->first();

            if ($page) {

                /**
                 * ✅ FIX: Do NOT canonical-redirect generated multipage URLs.
                 * The resolver renders base multipage content (slug) on the generated URL path,
                 * so we must keep the current URL (like /t-shirts-importers-in-albuquerque).
                 */
                $isGeneratedMultipage =
                    $page->type === 'multipage'
                    && (bool) $request->attributes->get('multipage_generated', false);

                if (!$isGeneratedMultipage) {
                    // ✅ canonical path - treat multipage same as page
                    $canonicalPath = $permalinks->pagePath($page);
                    if ($this->pathsDiffer($path, $canonicalPath)) {
                        return $this->redirectPreserveQuery($request, $canonicalPath, 301);
                    }
                }

                // ✅ FORCE TEMPLATE FOR SEGMENT REQUESTS (from MultiPageResolver)
                // ✅ FORCE TEMPLATE FOR SEGMENT REQUESTS (from MultiPageResolver)
                $forced = trim((string) $request->attributes->get('cms_forced_template', ''));
                if ($forced !== '') {
                    $metaJson = $page->meta_json;
                    $metaJson = is_array($metaJson) ? $metaJson : [];
                    $metaJson['template'] = $forced;
                    $page->meta_json = $metaJson;
                }

                /**
                 * ✅ FIX: If this is a MultiPage base slug (no generated mapping matched),
                 * inject default segments so [segment-1], [segment-2] work on base page too.
                 */
                if (
                    $page->type === 'multipage'
                    && !$request->attributes->has('multipage_segments')
                ) {
                    $meta = is_array($page->meta_json ?? null) ? $page->meta_json : [];
                    $mp = is_array($meta['multipage'] ?? null) ? $meta['multipage'] : [];

                    $raw = '';
                    foreach (['default_segments', 'default_values', 'defaults'] as $k) {
                        $candidate = trim((string) ($mp[$k] ?? ''));
                        if ($candidate !== '') {
                            $raw = $candidate;
                            break;
                        }
                    }

                    if ($raw !== '') {
                        $defaults = array_values(array_filter(array_map('trim', explode(',', $raw))));
                        if (!empty($defaults)) {
                            $request->attributes->set('multipage_segments', $defaults);
                            $request->attributes->set('multipage_base_slug', (string) $page->slug);
                        }
                    }
                }

                [$css, $js] = $this->extractPostAssets($page);

                // ✅ admin edit URL: multipage goes to MultiPageResource if available
                $adminEditUrl = url('/lara-admin');

                if ($page->type === 'page') {
                    $adminEditUrl = class_exists(FilamentPageResource::class)
                        ? FilamentPageResource::getUrl('edit', ['record' => $page])
                        : url('/lara-admin');
                } elseif ($page->type === 'multipage' && class_exists(\Plugins\MultiPage\Filament\Resources\MultiPageResource::class)) {
                    $adminEditUrl = \Plugins\MultiPage\Filament\Resources\MultiPageResource::getUrl('edit', ['record' => $page], panel: 'admin');
                } else {
                    $adminEditUrl = class_exists(FilamentPageResource::class)
                        ? FilamentPageResource::getUrl('edit', ['record' => $page])
                        : url('/lara-admin');
                }

                return view($this->resolveFrontendView($page, fallback: 'page', request: $request), [
                    'post' => $page,
                    'seo' => $this->buildSeo($page, $request, $permalinks),

                    'pageAssetsCss' => $css,
                    'pageAssetsJs' => $js,

                    'adminEditUrl' => $adminEditUrl,
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
            $canonicalPath = $permalinks->postPath($post);

            // If not plain permalink mode, enforce canonical
            if ($canonicalPath !== '/?p=' . $post->id) {
                if ($this->pathsDiffer($path, $canonicalPath)) {
                    return $this->redirectPreserveQuery($request, $canonicalPath, 301);
                }
            }

            [$css, $js] = $this->extractPostAssets($post);

            return view($this->resolveFrontendView($post, fallback: 'post', request: $request), [
                'post' => $post,
                'seo' => $this->buildSeo($post, $request, $permalinks),

                'pageAssetsCss' => $css,
                'pageAssetsJs' => $js,

                'adminEditUrl' => class_exists(FilamentPostResource::class)
                    ? FilamentPostResource::getUrl('edit', ['record' => $post])
                    : url('/lara-admin'),
            ]);
        }

        // 3) Attachment pages (Media)
        if ($slug !== '' && !str_contains($slug, '/')) {
            $attachmentsEnabled = (bool) $settings->get('core', 'attachment_pages_enabled', false);

            if ($attachmentsEnabled) {
                $media = Media::query()
                    ->where('slug', $slug)
                    ->where('attachment_public', true)
                    ->first();

                if ($media) {
                    if ($this->hasPrivateMediaCategory($media)) {
                        return redirect()->to('/lara-admin?private=1&from=' . urlencode($path));
                    }

                    // ✅ Attachment canonical should also follow trailing-slash standard
                    $canonicalPath = '/' . trim((string) $media->slug, '/');
                    if ($this->pathsDiffer($path, $canonicalPath)) {
                        return $this->redirectPreserveQuery($request, $canonicalPath, 301);
                    }

                    $globalIndexable = (bool) $settings->get('core', 'attachment_pages_indexable', true);
                    $indexable = $globalIndexable && (bool) $media->attachment_indexable;

                    $usedIn = $media->posts()
                        ->whereIn('type', ['post', 'page', 'multipage'])
                        ->where('status', 'published')
                        ->where(function ($q) use ($now) {
                            $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                        })
                        ->latest('published_at')
                        ->limit(12)
                        ->get(['posts.id', 'posts.type', 'posts.title', 'posts.slug', 'posts.published_at']);

                    [$css, $js] = $this->extractMediaAssets($media);

                    if (function_exists('do_action')) {
                        do_action('media.attachment.defaults.persist', $media);
                    }

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

                        'mediaCategories' => $publicMediaCategories,
                        'activeMediaCategory' => $activeMediaCategory,

                        'pageAssetsCss' => $css,
                        'pageAssetsJs' => $js,

                        'adminEditUrl' => class_exists(FilamentMediaResource::class)
                            ? FilamentMediaResource::getUrl('edit', ['record' => $media])
                            : url('/lara-admin'),
                    ]);
                }
            }
        }

        // 4) Slug history fallback
        $oldSlugCandidate = null;

        if (is_array($match) && isset($match['slug'])) {
            $oldSlugCandidate = (string) $match['slug'];
        } elseif ($slug !== '' && !str_contains($slug, '/')) {
            $oldSlugCandidate = $slug;
        }

        if ($oldSlugCandidate !== null && $oldSlugCandidate !== '') {
            $history = SlugHistory::query()
                ->whereIn('entity_type', ['post', 'page', 'multipage'])
                ->where('old_slug', $oldSlugCandidate)
                ->latest('id')
                ->first();

            if ($history) {
                $current = Post::query()->find($history->entity_id);
                if ($current && $current->status === 'published') {
                    $to = in_array($current->type, ['page', 'multipage'], true)
                        ? $permalinks->pagePath($current)
                        : $permalinks->postPath($current);

                    $fromStore = $this->normalizeForLookup($path);

                    Redirect::query()->updateOrCreate(
                        ['from_path' => $fromStore],
                        ['to_path' => $to, 'status_code' => 301]
                    );

                    return $this->redirectPreserveQuery($request, $to, 301);
                }
            }
        }

        abort(404);
    }

    private function pathsDiffer(string $a, string $b): bool
    {
        return $this->normalizeForLookup($a) !== $this->normalizeForLookup($b);
    }

    private function normalizeForLookup(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        if ($path === '//') {
            $path = '/';
        }
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }
        return $path;
    }

    private function redirectPreserveQuery(Request $request, string $to, int $status = 301)
    {
        if ($to !== '' && $to[0] !== '/' && !str_starts_with($to, 'http')) {
            $to = '/' . $to;
        }

        $qs = $request->getQueryString();
        if ($qs) {
            $to .= (str_contains($to, '?') ? '&' : '?') . $qs;
        }

        return redirect()->to($to, $status);
    }

    /**
     * ✅ UPDATED: allow forced template from request attribute (segment pages)
     */
    private function resolveFrontendView(Post $post, string $fallback, ?Request $request = null): string
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];

        $forced = '';
        if ($request) {
            $forced = trim((string) $request->attributes->get('cms_forced_template', ''));
        }

        $template = $forced !== '' ? $forced : trim((string) ($meta['template'] ?? ''));

        if ($template === '') {
            return $fallback;
        }

        $view = 'templates.' . $template;
        return view()->exists($view) ? $view : $fallback;
    }

    // ------------------- Helpers below (unchanged + SEO shortcode helpers) -------------------

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

    private function seoShortcodeText(string $value, array $ctx): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('do_shortcode')) {
            try {
                $value = (string) do_shortcode($value, $ctx);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return trim(strip_tags($value));
    }

    private function seoShortcodeUrl(string $value, array $ctx): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (function_exists('do_shortcode')) {
            try {
                $value = (string) do_shortcode($value, $ctx);
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return trim($value);
    }

    private function buildSeo(Post $post, Request $request, PermalinkManager $permalinks): array
    {
        $meta = is_array($post->meta_json) ? $post->meta_json : [];
        $seo = isset($meta['seo']) && is_array($meta['seo']) ? $meta['seo'] : [];

        $ctx = ['post' => $post];

        $rawTitle = (string) ($seo['title'] ?? $post->title ?? config('app.name'));
        $rawDesc = (string) ($seo['description'] ?? $post->excerpt ?? '');

        $title = $this->seoShortcodeText($rawTitle, $ctx);
        $desc = $this->seoShortcodeText($rawDesc, $ctx);

        $canonicalRaw = (string) ($seo['canonical'] ?? '');
        $canonical = $this->seoShortcodeUrl($canonicalRaw, $ctx);

        if ($canonical === '') {
            // ✅ multipage behaves like page for canonical
            $canonical = in_array($post->type, ['page', 'multipage'], true)
                ? $permalinks->pageUrl($post)
                : $permalinks->postUrl($post);
        }

        $robotsRaw = (string) ($seo['robots'] ?? '');
        $robots = $this->seoShortcodeText($robotsRaw, $ctx);
        if ($robots === '') {
            $robots = 'index, follow';
        }

        $ogImageRaw = (string) ($seo['og_image'] ?? '');
        $ogImage = $this->seoShortcodeUrl($ogImageRaw, $ctx);

        $isPageLike = in_array($post->type, ['page', 'multipage'], true);

        return [
            'title' => $title,
            'description' => $desc,
            'canonical' => $canonical,
            'robots' => $robots,
            'og' => [
                'title' => $title,
                'description' => $desc,
                'type' => $isPageLike ? 'website' : 'article',
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

        $ctx = ['media' => $media];

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

        $rawTitle = (string) ($seo['title'] ?? $fallbackTitle);
        $rawDesc = (string) ($seo['description'] ?? $fallbackDesc);

        $title = $this->seoShortcodeText($rawTitle, $ctx);
        $desc = $this->seoShortcodeText($rawDesc, $ctx);

        $canonicalRaw = (string) ($seo['canonical'] ?? '');
        $canonical = $this->seoShortcodeUrl($canonicalRaw, $ctx);

        if ($canonical === '') {
            /** @var SettingsRepository $settings */
            $settings = app(SettingsRepository::class);

            $base = (string) $settings->get('core', 'site_url', (string) config('app.url'));
            $base = rtrim(trim($base), '/');

            $canonical = $base . '/' . trim((string) $media->slug, '/');
        }

        $robotsRaw = (string) ($seo['robots'] ?? '');
        $robots = $this->seoShortcodeText($robotsRaw, $ctx);
        if ($robots === '') {
            $robots = $indexable ? 'index, follow' : 'noindex, follow';
        }

        $ogImageRaw = (string) ($seo['og_image'] ?? '');
        $ogImage = $this->seoShortcodeUrl($ogImageRaw, $ctx);

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

        if (($term->visibility ?? 'public') !== 'public') {
            return redirect()->to('/lara-admin?private=1&from=' . urlencode($request->getPathInfo()));
        }

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
            'adminEditUrl' => url('/lara-admin'),
        ]);
    }

    /**
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
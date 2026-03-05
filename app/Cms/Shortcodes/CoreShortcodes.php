<?php

namespace App\Cms\Shortcodes;

use App\Cms\Content\CurrentContentContext;
use App\Cms\Content\Shortcodes\ShortcodeRegistry;
use App\Models\Media;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Collection;

class CoreShortcodes
{
    public static function register(ShortcodeRegistry $shortcodes): void
    {
        /**
         * ✅ [h1]
         * Prints current Post/Page/Media title only (no <h1> tag)
         */
        $shortcodes->registerWithMeta('h1', function (array $atts = [], ?string $content = null, array $context = []) {
            $title = self::resolveCurrentTitle($context);
            return $title !== '' ? e($title) : '';
        }, [
            'group' => 'Core',
            'description' => 'Prints current Post/Page/Media title only (no <h1> tag).',
            'params' => [],
            'examples' => ['[h1]'],
        ]);

        /**
         * ✅ [category]
         * Priority:
         * 1) Current category term "product" field (if exists & not empty)
         * 2) Current category term "name"
         */
        $shortcodes->registerWithMeta('category', function (array $atts = [], ?string $content = null, array $context = []) {
            $term = self::resolveCurrentPrimaryTerm($context);

            if (!$term) {
                return '';
            }

            $product = trim((string) ($term->product ?? ''));
            if ($product !== '') {
                return e($product);
            }

            $name = trim((string) ($term->name ?? ''));
            return $name !== '' ? e($name) : '';
        }, [
            'group' => 'Core',
            'description' => 'Shows current category title. Uses term->product first (if not empty), otherwise term->name.',
            'params' => [],
            'examples' => ['[category]'],
        ]);

        /**
         * ✅ [posts] shortcode (unchanged)
         */
        $shortcodes->registerWithMeta('posts', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $count = (int) ($atts['count'] ?? 12);
                $count = max(1, min(50, $count));

                $now = now();

                $query = Post::query()
                    ->where('type', 'post')
                    ->where('status', 'published')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                    })
                    ->inRandomOrder()
                    ->limit($count);

                if (method_exists(Post::class, 'featuredMedia')) {
                    $query->with(['featuredMedia']);
                }

                if (method_exists(Post::class, 'scopeFrontendVisible')) {
                    $query->frontendVisible();
                }

                $posts = $query->get();

                if ($posts->isEmpty()) {
                    return '';
                }

                if (view()->exists('shortcodes.posts-grid')) {
                    return view('shortcodes.posts-grid', ['posts' => $posts])->render();
                }

                return self::fallbackPostsHtml($posts);
            } catch (\Throwable $e) {
                return '<!-- [posts] shortcode error: ' . e($e->getMessage()) . ' -->';
            }
        }, [
            'group' => 'Core',
            'description' => 'Shows a random grid of published posts.',
            'params' => [
                ['name' => 'count', 'type' => 'int', 'default' => 12, 'desc' => 'Number of posts to show (1–50).'],
            ],
            'examples' => [
                '[posts]',
                '[posts count="8"]',
            ],
        ]);

        /**
         * ✅ [products] (UPDATED)
         *
         * Behavior:
         * - Normal (no catid): only PUBLIC categories, uses frontendVisible()
         * - catid=PUBLIC term: same as before
         * - catid=PRIVATE term: still render items (attachment_public only), but does NOT affect sitemap/SEO
         */
        $shortcodes->registerWithMeta('products', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
                if (!$taxonomyId) {
                    return '';
                }

                $limit = (int) ($atts['load'] ?? 12);
                $limit = max(1, min(50, $limit));

                $catId = (int) ($atts['catid'] ?? 0);

                $showPriceBtn = array_key_exists('pricebtn', $atts)
                    ? self::toBool(($atts['pricebtn'] ?? '') === '' ? true : $atts['pricebtn'])
                    : false;

                $columns = (int) ($atts['column'] ?? 4);
                $columns = max(1, min(12, $columns));

                $mobileColumns = (int) ($atts['mcolumn'] ?? 2);
                $mobileColumns = max(1, min(6, $mobileColumns));

                $excludeUncategorized = function ($q) {
                    $q->whereRaw("LOWER(terms.slug) != 'uncategorized'")
                        ->whereRaw("LOWER(terms.name) != 'uncategorized'");
                };

                // Determine if catid points to a PRIVATE category (explicit allow)
                $requestedTerm = null;
                $isPrivateRequested = false;

                if ($catId > 0) {
                    $requestedTerm = Term::query()
                        ->whereKey($catId)
                        ->where('taxonomy_id', $taxonomyId)
                        ->first();

                    if (!$requestedTerm) {
                        return ''; // catid invalid / not in media_category
                    }

                    $isPrivateRequested = strtolower((string) ($requestedTerm->visibility ?? '')) === 'private';
                }

                $query = Media::query();

                if ($isPrivateRequested) {
                    // ✅ Allow private category items ONLY when explicitly requested by shortcode
                    // Keep attachments public, but DO NOT apply frontendVisible() because it blocks private categories.
                    $query->where('attachment_public', true);

                    $query->whereHas('terms', function ($q) use ($catId) {
                        $q->where('terms.id', $catId);
                    });
                } else {
                    // Default behavior (public-only browsing)
                    if (method_exists(Media::class, 'scopeFrontendVisible')) {
                        $query->frontendVisible();
                    } else {
                        $query->where('attachment_public', true);
                    }

                    // Must belong to public media_category terms (exclude uncategorized)
                    $query->whereHas('terms', function ($q) use ($taxonomyId, $excludeUncategorized) {
                        $q->where('terms.taxonomy_id', $taxonomyId)
                            ->where('terms.visibility', 'public');

                        $excludeUncategorized($q);
                    });

                    // If specific public catid is requested, filter to it
                    if ($catId > 0) {
                        $catVisibility = strtolower((string) ($requestedTerm->visibility ?? ''));
                        $catSlug = strtolower((string) ($requestedTerm->slug ?? ''));
                        $catName = strtolower((string) ($requestedTerm->name ?? ''));

                        $catOk = $catVisibility === 'public'
                            && $catSlug !== 'uncategorized'
                            && $catName !== 'uncategorized';

                        if ($catOk) {
                            $query->whereHas('terms', function ($q) use ($catId) {
                                $q->where('terms.id', $catId);
                            });
                        } else {
                            return ''; // requested cat exists but not allowed under public rules
                        }
                    }
                }

                $items = $query
                    ->with(['variantRecords'])
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get();

                if ($items->isEmpty()) {
                    return '';
                }

                if (view()->exists('shortcodes.products-grid')) {
                    return view('shortcodes.products-grid', [
                        'items' => $items,
                        'showPriceBtn' => $showPriceBtn,
                        'columns' => $columns,
                        'mobileColumns' => $mobileColumns,
                    ])->render();
                }

                return '<!-- products: missing view shortcodes.products-grid -->';
            } catch (\Throwable $e) {
                return '<!-- [products] shortcode error: ' . e($e->getMessage()) . ' -->';
            }
        }, [
            'group' => 'Core',
            'description' => 'Shows random products from Media taxonomy category.',
            'params' => [
                ['name' => 'load', 'type' => 'int', 'default' => 12, 'desc' => 'Number of items (1–50).'],
                ['name' => 'catid', 'type' => 'int', 'default' => 0, 'desc' => 'Filter by category term id (optional).'],
                ['name' => 'pricebtn', 'type' => 'bool', 'default' => false, 'desc' => 'Show price button (flag or value).'],
                ['name' => 'column', 'type' => 'int', 'default' => 4, 'desc' => 'Desktop columns (1–12).'],
                ['name' => 'mcolumn', 'type' => 'int', 'default' => 2, 'desc' => 'Mobile columns (1–6).'],
            ],
            'examples' => [
                '[products]',
                '[products load="8" column="4" mcolumn="2"]',
                '[products catid="5" load="12" pricebtn]',
            ],
        ]);

        /**
         * ✅ [logo] (Media Category Logos) (UPDATED)
         *
         * - If catid is PRIVATE => allow render (attachment_public only), but keep it out of sitemap/SEO by leaving frontendVisible() logic untouched elsewhere.
         * - If catid is PUBLIC => same as before (uses frontendVisible()).
         */
        $shortcodes->registerWithMeta('logo', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
                if (!$taxonomyId) {
                    return '';
                }

                // catid REQUIRED (no error page, just message)
                if (!array_key_exists('catid', $atts) || trim((string) ($atts['catid'] ?? '')) === '') {
                    return '<!-- [logo] missing required catid -->'
                        . '<div class="text-sm text-red-600 my-4">Please provide <b>catid</b>. Example: <code>[logo catid="5"]</code></div>';
                }

                $catId = (int) $atts['catid'];
                if ($catId <= 0) {
                    return '<!-- [logo] invalid catid -->'
                        . '<div class="text-sm text-red-600 my-4">Invalid <b>catid</b>. Example: <code>[logo catid="5"]</code></div>';
                }

                // columns
                $columns = (int) ($atts['column'] ?? 4);
                $columns = max(1, min(12, $columns));

                // mobile columns
                $mobile = (int) ($atts['mobile'] ?? 2);
                $mobile = max(1, min(6, $mobile));

                // title (flag or value)
                $showTitle = false;
                if (array_key_exists('title', $atts)) {
                    $v = $atts['title'];
                    $showTitle = ($v === null || $v === '') ? true : self::toBool($v);
                }

                // slider (flag or value)
                $slider = false;
                if (array_key_exists('slider', $atts)) {
                    $v = $atts['slider'];
                    $slider = ($v === null || $v === '') ? true : self::toBool($v);
                }

                // style
                $style = strtolower(trim((string) ($atts['style'] ?? 'square')));
                $style = in_array($style, ['square', 'squire', 'round'], true) ? $style : 'square';
                if ($style === 'squire') {
                    $style = 'square';
                }

                // order: asc default; if "dec" flag exists OR order="dec"
                $orderDir = 'asc';
                if (array_key_exists('dec', $atts)) {
                    $orderDir = 'desc';
                }
                if (array_key_exists('asc', $atts)) {
                    $orderDir = 'asc';
                }
                if (array_key_exists('order', $atts)) {
                    $ov = strtolower(trim((string) ($atts['order'] ?? '')));
                    if (in_array($ov, ['asc', 'desc', 'dec'], true)) {
                        $orderDir = $ov === 'dec' ? 'desc' : $ov;
                    }
                }

                // load: default all; if set, limit
                $limit = null;
                if (array_key_exists('load', $atts) && trim((string) ($atts['load'] ?? '')) !== '') {
                    $limit = (int) $atts['load'];
                    $limit = max(1, min(500, $limit));
                }

                // class: keep default + append user class
                $defaultClass = 'logo_grid';
                $userClass = trim((string) ($atts['class'] ?? ''));
                $userClass = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $userClass);
                $userClass = trim(preg_replace('/\s+/', ' ', $userClass));

                $class = $defaultClass;
                if ($userClass !== '') {
                    $class .= ' ' . $userClass;
                }

                // validate category exists & is media_category (and detect private/public)
                $term = Term::query()
                    ->whereKey($catId)
                    ->where('taxonomy_id', $taxonomyId)
                    ->first();

                if (!$term) {
                    return '<!-- [logo] catid not found -->';
                }

                $isPrivateCategory = strtolower((string) ($term->visibility ?? '')) === 'private';

                $query = Media::query();

                if ($isPrivateCategory) {
                    // ✅ Allow private category items ONLY when explicitly requested by shortcode
                    $query->where('attachment_public', true);
                } else {
                    if (method_exists(Media::class, 'scopeFrontendVisible')) {
                        $query->frontendVisible();
                    } else {
                        $query->where('attachment_public', true);
                    }
                }

                // restrict to category
                $query->whereHas('terms', function ($q) use ($catId) {
                    $q->where('terms.id', $catId);
                });

                // order
                $query->orderBy('id', $orderDir);

                // limit if load provided
                if ($limit !== null) {
                    $query->limit($limit);
                }

                $items = $query->get();

                if ($items->isEmpty()) {
                    return '';
                }

                if (view()->exists('shortcodes.logo-grid')) {
                    return view('shortcodes.logo-grid', [
                        'items' => $items,
                        'column' => $columns,
                        'mobile' => $mobile,
                        'showTitle' => $showTitle,
                        'style' => $style,
                        'class' => $class,
                        'slider' => $slider,
                    ])->render();
                }

                return '<!-- [logo] missing view shortcodes.logo-grid -->';
            } catch (\Throwable $e) {
                return '<!-- [logo] shortcode error: ' . e($e->getMessage()) . ' -->';
            }
        }, [
            'group' => 'Core',
            'description' => 'Shows logos from Media Category (media_category taxonomy) for a given catid.',
            'params' => [
                ['name' => 'catid', 'type' => 'int', 'default' => null, 'desc' => 'Required category term id (media_category).'],
                ['name' => 'column', 'type' => 'int', 'default' => 4, 'desc' => 'Desktop columns (1–12).'],
                ['name' => 'mobile', 'type' => 'int', 'default' => 2, 'desc' => 'Mobile columns (1–6).'],
                ['name' => 'title', 'type' => 'bool', 'default' => false, 'desc' => 'Show logo title (flag or value).'],
                ['name' => 'style', 'type' => 'string', 'default' => 'square', 'desc' => 'square|round (squire accepted as square).'],
                ['name' => 'asc', 'type' => 'flag', 'default' => 'asc', 'desc' => 'Order by ID ascending.'],
                ['name' => 'dec', 'type' => 'flag', 'default' => null, 'desc' => 'Order by ID descending.'],
                ['name' => 'order', 'type' => 'string', 'default' => 'asc', 'desc' => 'asc|desc|dec'],
                ['name' => 'load', 'type' => 'int', 'default' => null, 'desc' => 'Limit items (1–500). Default: all.'],
                ['name' => 'class', 'type' => 'string', 'default' => 'logo_grid', 'desc' => 'Extra CSS classes appended.'],
                ['name' => 'slider', 'type' => 'bool', 'default' => false, 'desc' => 'Enable slider mode (flag or value).'],
            ],
            'examples' => [
                '[logo catid="5"]',
                '[logo catid="5" column="6" mobile="2" title style="round" dec load="12" class="mylogos"]',
                '[logo catid="5" slider]',
            ],
        ]);

        /**
         * ✅ [sp]  (Static Posts)
         */
        $shortcodes->registerWithMeta('sp', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $staticPostClass = '\\Plugins\\StaticPosts\\Models\\StaticPost';
                if (!class_exists($staticPostClass)) {
                    return '';
                }

                // postid (top section)
                $topTitle = '';
                $topContent = '';
                $topImages = [];
                $topVariant = '';

                if (array_key_exists('top', $atts)) {
                    $topVariant = strtolower(trim((string) ($atts['top'] ?? '')));
                }

                $postId = 0;
                if (array_key_exists('postid', $atts) && trim((string) ($atts['postid'] ?? '')) !== '') {
                    $postId = (int) $atts['postid'];
                }

                if ($postId > 0) {
                    $topPost = Post::query()
                        ->whereKey($postId)
                        ->where('type', 'static_post')
                        ->whereRaw('LOWER(status) = ?', ['published'])
                        ->first();

                    if ($topPost) {
                        $topTitle = trim((string) ($topPost->title ?? ''));
                        $topContent = trim((string) (
                            $topPost->content_html
                            ?? data_get($topPost->content_json ?? [], 'html')
                            ?? $topPost->excerpt
                            ?? ''
                        ));

                        // load top images (first 2 only)
                        try {
                            $mediaItems = collect();

                            if (method_exists($topPost, 'featuredMediaPivot')) {
                                $mediaItems = $topPost->featuredMediaPivot()->get();
                            }

                            if ($mediaItems->isEmpty() && method_exists($topPost, 'featuredMedia')) {
                                $one = $topPost->featuredMedia()->first();
                                if ($one) {
                                    $mediaItems = collect([$one]);
                                }
                            }

                            $topImages = $mediaItems
                                ->map(function ($m) {
                                    if (is_object($m) && method_exists($m, 'url')) {
                                        return (string) $m->url();
                                    }
                                    if (is_object($m) && property_exists($m, 'url') && is_string($m->url)) {
                                        return (string) $m->url;
                                    }
                                    if (is_object($m) && property_exists($m, 'path') && is_string($m->path)) {
                                        return (string) $m->path;
                                    }
                                    return null;
                                })
                                ->filter(fn($u) => is_string($u) && trim($u) !== '')
                                ->values()
                                ->take(2)
                                ->all();
                        } catch (\Throwable $e) {
                            $topImages = [];
                        }
                    }
                }

                // catid (grid)
                $hasCatId = array_key_exists('catid', $atts) && trim((string) ($atts['catid'] ?? '')) !== '';
                $catId = $hasCatId ? (int) $atts['catid'] : 0;

                if (!$hasCatId && $postId <= 0) {
                    return '<!-- [sp] missing required catid -->'
                        . '<div class="text-sm text-red-600 my-4">Please provide <b>catid</b> in shortcode. Example: <code>[sp catid="6"]</code></div>';
                }

                if ($hasCatId && $catId <= 0) {
                    return '<!-- [sp] invalid catid -->'
                        . '<div class="text-sm text-red-600 my-4">Invalid <b>catid</b>. Example: <code>[sp catid="6"]</code></div>';
                }

                $columns = (int) ($atts['column'] ?? 3);
                $columns = max(1, min(12, $columns));

                $mobile = (int) ($atts['mobile'] ?? 1);
                $mobile = max(1, min(6, $mobile));

                $limit = (int) ($atts['load'] ?? ($atts['laod'] ?? 6));
                $limit = max(1, min(50, $limit));

                $class = trim((string) ($atts['class'] ?? 'static_posts'));
                $class = $class !== '' ? $class : 'static_posts';

                $showImg = false;
                if (array_key_exists('img', $atts)) {
                    $v = $atts['img'];
                    $showImg = ($v === null || $v === '') ? true : self::toBool($v);
                }

                $showLearnMoreBtn = false;
                if (array_key_exists('lmbtn', $atts)) {
                    $v = $atts['lmbtn'];
                    $showLearnMoreBtn = ($v === null || $v === '') ? true : self::toBool($v);
                }

                $items = collect();

                if ($hasCatId && $catId > 0) {
                    $taxonomyId = null;
                    foreach (['static_category', 'static_post_category', 'static_posts_category'] as $key) {
                        $taxonomyId = Taxonomy::query()->where('key', $key)->value('id');
                        if ($taxonomyId) {
                            break;
                        }
                    }

                    $termQuery = Term::query()->whereKey($catId);
                    if ($taxonomyId) {
                        $termQuery->where('taxonomy_id', $taxonomyId);
                    }
                    $termOk = $termQuery->exists();

                    if ($termOk) {
                        $now = now();

                        $query = $staticPostClass::query()
                            ->whereRaw('LOWER(status) = ?', ['published'])
                            ->where(function ($q) use ($now) {
                                $q->whereNull('published_at')->orWhere('published_at', '<=', $now);
                            })
                            ->whereHas('terms', function ($q) use ($catId) {
                                $q->where('terms.id', $catId);
                            })
                            ->orderByDesc('published_at')
                            ->orderByDesc('id')
                            ->limit($limit);

                        $with = [];
                        if (method_exists($staticPostClass, 'featuredMediaPivot')) {
                            $with[] = 'featuredMediaPivot';
                        }
                        if (method_exists($staticPostClass, 'featuredMedia')) {
                            $with[] = 'featuredMedia';
                        }
                        if (!empty($with)) {
                            $query->with($with);
                        }

                        $items = $query->get();
                    }
                }

                if (view()->exists('shortcodes.static-posts-grid')) {
                    return view('shortcodes.static-posts-grid', [
                        'items' => $items,
                        'column' => $columns,
                        'mobile' => $mobile,
                        'img' => $showImg,
                        'class' => $class,

                        'topTitle' => $topTitle,
                        'topContent' => $topContent,
                        'topVariant' => $topVariant,
                        'topImages' => $topImages,

                        'showLearnMoreBtn' => $showLearnMoreBtn,
                    ])->render();
                }

                return '<!-- [sp] missing view shortcodes.static-posts-grid -->';
            } catch (\Throwable $e) {
                return '<!-- [sp] shortcode error: ' . e($e->getMessage()) . ' -->';
            }
        }, [
            'group' => 'Core',
            'description' => 'Renders Static Posts grid (plugin StaticPosts). Supports optional top section via postid.',
            'params' => [
                ['name' => 'catid', 'type' => 'int', 'default' => null, 'desc' => 'Static category term id (required unless postid is provided).'],
                ['name' => 'postid', 'type' => 'int', 'default' => 0, 'desc' => 'Static post id for top section. If catid missing, renders top section only.'],
                ['name' => 'top', 'type' => 'string', 'default' => '', 'desc' => 'Top variant (e.g. "hybrid").'],
                ['name' => 'column', 'type' => 'int', 'default' => 3, 'desc' => 'Desktop columns (1–12).'],
                ['name' => 'mobile', 'type' => 'int', 'default' => 1, 'desc' => 'Mobile columns (1–6).'],
                ['name' => 'load', 'type' => 'int', 'default' => 6, 'desc' => 'Number of items to load (1–50).'],
                ['name' => 'img', 'type' => 'bool', 'default' => false, 'desc' => 'Show images (flag or value).'],
                ['name' => 'lmbtn', 'type' => 'bool', 'default' => false, 'desc' => 'Show Learn More button (flag or value).'],
                ['name' => 'class', 'type' => 'string', 'default' => 'static_posts', 'desc' => 'CSS class for wrapper.'],
            ],
            'examples' => [
                '[sp catid="6"]',
                '[sp catid="6" column="3" mobile="1" load="6" img]',
                '[sp postid="14" top="hybrid" lmbtn]',
            ],
        ]);
    }

    private static function fallbackPostsHtml(Collection $posts): string
    {
        $html = '<div class="my-6"><div class="grid grid-cols-2 gap-6 lg:grid-cols-4">';

        foreach ($posts as $post) {
            $title = e((string) ($post->title ?? 'Untitled'));

            $url = function_exists('cms_post_url')
                ? cms_post_url($post)
                : (function_exists('cms_slug_url')
                    ? cms_slug_url((string) ($post->slug ?? ''))
                    : url('/' . trim((string) ($post->slug ?? ''), '/') . '/'));

            $html .= '<a class="block text-center" href="' . e($url) . '">';
            $html .= '<div class="aspect-square rounded-xl bg-slate-100"></div>';
            $html .= '<div class="mt-3 text-sm font-semibold text-[#1f5f99]">' . $title . '</div>';
            $html .= '</a>';
        }

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Resolve current title from context or CurrentContentContext.
     */
    private static function resolveCurrentTitle(array $ctx): string
    {
        if (($ctx['post'] ?? null) instanceof Post) {
            return trim((string) ($ctx['post']->title ?? ''));
        }

        if (($ctx['media'] ?? null) instanceof Media) {
            return trim((string) ($ctx['media']->title ?? ''));
        }

        try {
            $current = app(CurrentContentContext::class);

            if (($current->post ?? null) instanceof Post) {
                return trim((string) ($current->post->title ?? ''));
            }

            if (($current->media ?? null) instanceof Media) {
                return trim((string) ($current->media->title ?? ''));
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '';
    }

    /**
     * Get ONE primary term for current post/media.
     */
    private static function resolveCurrentPrimaryTerm(array $ctx): ?Term
    {
        if (($ctx['term'] ?? null) instanceof Term) {
            return $ctx['term'];
        }

        if (($ctx['post'] ?? null) instanceof Post) {
            $post = $ctx['post'];

            try {
                return $post->categories()->orderBy('terms.name')->first();
            } catch (\Throwable $e) {
                return null;
            }
        }

        if (($ctx['media'] ?? null) instanceof Media) {
            return self::mediaPrimaryCategory($ctx['media']);
        }

        try {
            $current = app(CurrentContentContext::class);

            if (($current->media ?? null) instanceof Media) {
                return self::mediaPrimaryCategory($current->media);
            }

            if (($current->post ?? null) instanceof Post) {
                return $current->post->categories()->orderBy('terms.name')->first();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return null;
    }

    private static function mediaPrimaryCategory(Media $media): ?Term
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
        if (!$taxonomyId) {
            return null;
        }

        try {
            return $media->terms()
                ->where('terms.taxonomy_id', $taxonomyId)
                ->orderBy('terms.name')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Convert shortcode boolean values safely.
     */
    private static function toBool($v): bool
    {
        if (is_bool($v)) {
            return $v;
        }

        $s = strtolower(trim((string) $v));
        return in_array($s, ['1', 'true', 'yes', 'y', 'on'], true);
    }
}
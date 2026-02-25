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
        // ✅ Debug
        $shortcodes->register('hello_test', fn() => 'HELLO');

        /**
         * ✅ [h1]
         * Prints current Post/Page/Media title only (no <h1> tag)
         */
        $shortcodes->register('h1', function (array $atts = [], ?string $content = null, array $context = []) {
            $title = self::resolveCurrentTitle($context);
            return $title !== '' ? e($title) : '';
        });

        /**
         * ✅ [category]
         * Priority:
         * 1) Current category term "product" field (if exists & not empty)
         * 2) Current category term "name"
         */
        $shortcodes->register('category', function (array $atts = [], ?string $content = null, array $context = []) {
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
        });

        // ✅ keep your [posts] shortcode unchanged
        $shortcodes->register('posts', function (array $atts = [], ?string $content = null, array $context = []) {
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
        });

        /**
         * ✅ [products] (unchanged)
         */
        $shortcodes->register('products', function (array $atts = [], ?string $content = null, array $context = []) {
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

                $query = Media::query();

                if (method_exists(Media::class, 'scopeFrontendVisible')) {
                    $query->frontendVisible();
                }

                $query->whereHas('terms', function ($q) use ($taxonomyId, $excludeUncategorized) {
                    $q->where('terms.taxonomy_id', $taxonomyId)
                        ->where('terms.visibility', 'public');

                    $excludeUncategorized($q);
                });

                if ($catId > 0) {
                    $catOk = Term::query()
                        ->whereKey($catId)
                        ->where('taxonomy_id', $taxonomyId)
                        ->where('visibility', 'public')
                        ->where(function ($q) use ($excludeUncategorized) {
                            $excludeUncategorized($q);
                        })
                        ->exists();

                    if ($catOk) {
                        $query->whereHas('terms', function ($q) use ($catId) {
                            $q->where('terms.id', $catId);
                        });
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
        });

        /**
         * ✅ [logo] (Media Category Logos)
         *
         * Examples:
         *  - [logo catid="5"]
         *  - [logo catid="5" column="6" mobile="2" title style="round" dec load="12" class="mylogos"]
         *
         * Params:
         *  - catid (required)
         *  - column (default 4)
         *  - mobile (default 2)
         *  - title (bool flag or title="true/false/1/0/yes/no") default false
         *  - asc / dec (default asc)  -> order by ID asc/desc
         *  - load (default all in that category; if provided uses limit)
         *  - style="square|round" (default square)
         *  - class="custom classes" (optional) -> appends to default logo_grid
         */
        $shortcodes->register('logo', function (array $atts = [], ?string $content = null, array $context = []) {
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

                // ✅ class: keep default + append user class
                $defaultClass = 'logo_grid';
                $userClass = trim((string) ($atts['class'] ?? ''));
                $userClass = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $userClass);
                $userClass = trim(preg_replace('/\s+/', ' ', $userClass));

                $class = $defaultClass;
                if ($userClass !== '') {
                    $class .= ' ' . $userClass;
                }

                // validate category exists & is media_category
                $termOk = Term::query()
                    ->whereKey($catId)
                    ->where('taxonomy_id', $taxonomyId)
                    ->exists();

                if (!$termOk) {
                    return '<!-- [logo] catid not found -->';
                }

                $query = Media::query();

                if (method_exists(Media::class, 'scopeFrontendVisible')) {
                    $query->frontendVisible();
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
                        'class' => $class, // ✅ default + custom
                    ])->render();
                }

                return '<!-- [logo] missing view shortcodes.logo-grid -->';
            } catch (\Throwable $e) {
                return '<!-- [logo] shortcode error: ' . e($e->getMessage()) . ' -->';
            }
        });

        /**
         * ✅ [sp]  (Static Posts)
         *
         * Added:
         * - postid="14"  => loads that static_post and passes topTitle/topContent to view (top section)
         * - top="hybrid" => hybrid hero variant with images (uses first 2 featured images)
         * - lmbtn        => show learn more button (button href comes from per-post field in meta_json)
         *
         * Existing:
         * - If postid is provided and catid is missing => we allow rendering top section only.
         */
        $shortcodes->register('sp', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $staticPostClass = '\\Plugins\\StaticPosts\\Models\\StaticPost';
                if (!class_exists($staticPostClass)) {
                    return '';
                }

                // ---------------------------
                // postid (top section)
                // ---------------------------
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
                                    if (is_object($m) && method_exists($m, 'url'))
                                        return (string) $m->url();
                                    if (is_object($m) && property_exists($m, 'url') && is_string($m->url))
                                        return (string) $m->url;
                                    if (is_object($m) && property_exists($m, 'path') && is_string($m->path))
                                        return (string) $m->path;
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
                        if ($taxonomyId)
                            break;
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
                        if (method_exists($staticPostClass, 'featuredMediaPivot'))
                            $with[] = 'featuredMediaPivot';
                        if (method_exists($staticPostClass, 'featuredMedia'))
                            $with[] = 'featuredMedia';
                        if (!empty($with))
                            $query->with($with);

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
        });
    }

    private static function fallbackPostsHtml(Collection $posts): string
    {
        $html = '<div class="my-6"><div class="grid grid-cols-2 gap-6 lg:grid-cols-4">';

        foreach ($posts as $post) {
            $title = e((string) ($post->title ?? 'Untitled'));

            $url = function_exists('cms_post_url')
                ? cms_post_url($post)
                : url('/' . ltrim((string) ($post->slug ?? ''), '/'));

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
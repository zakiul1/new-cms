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
         * ✅ [products]
         *
         * Behavior:
         * - [products] → random products across ALL PUBLIC media categories (NOT one category), default 12
         * - [products catid="9"] → only that category if valid, otherwise fallback to default (no error)
         * - [products load="20"] → 20 items
         * - pricebtn default false; support [products pricebtn] or pricebtn=true/pricebtn="true"
         * - column="4" default 4 (tablet+desktop)
         * - mcolumn="2" default 2 (mobile)
         *
         * NOTE:
         * - For Option A (dynamic columns), Blade uses inline grid-template-columns (supports 5/6/7/...)
         * - So we allow higher limits here (desktop up to 12, mobile up to 6)
         */
        $shortcodes->register('products', function (array $atts = [], ?string $content = null, array $context = []) {
            try {
                $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
                if (!$taxonomyId) {
                    return '';
                }

                // load=
                $limit = (int) ($atts['load'] ?? 12);
                $limit = max(1, min(50, $limit));

                // catid=
                $catId = (int) ($atts['catid'] ?? 0);

                // ✅ pricebtn supports:
                // - [products pricebtn]
                // - [products pricebtn=true]
                // - [products pricebtn="true"]
                // Default false
                $showPriceBtn = array_key_exists('pricebtn', $atts)
                    ? self::toBool(($atts['pricebtn'] ?? '') === '' ? true : $atts['pricebtn'])
                    : false;

                // ✅ columns (md+): default 4, allow up to 12 (Option A supports any number)
                $columns = (int) ($atts['column'] ?? 4);
                $columns = max(1, min(12, $columns));

                // ✅ mobile columns: default 2, allow up to 6
                $mobileColumns = (int) ($atts['mcolumn'] ?? 2);
                $mobileColumns = max(1, min(6, $mobileColumns));

                // exclude uncategorized by name/slug
                $excludeUncategorized = function ($q) {
                    $q->whereRaw("LOWER(terms.slug) != 'uncategorized'")
                        ->whereRaw("LOWER(terms.name) != 'uncategorized'");
                };

                // ---------------------------------------------------
                // Base query: products are Media that have at least 1 PUBLIC media_category term (not uncategorized)
                // ---------------------------------------------------
                $query = Media::query();

                if (method_exists(Media::class, 'scopeFrontendVisible')) {
                    $query->frontendVisible();
                }

                // Must be categorized in public media_category and not uncategorized
                $query->whereHas('terms', function ($q) use ($taxonomyId, $excludeUncategorized) {
                    $q->where('terms.taxonomy_id', $taxonomyId)
                        ->where('terms.visibility', 'public');

                    $excludeUncategorized($q);
                });

                // ---------------------------------------------------
                // If catid is valid, restrict to it; if invalid, ignore it (fallback without error)
                // ---------------------------------------------------
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
            /** @var CurrentContentContext $current */
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
     * ✅ Get ONE primary term (category) for current post/media.
     * - Post: first category ordered by name
     * - Media: first media_category term ordered by name
     */
    private static function resolveCurrentPrimaryTerm(array $ctx): ?Term
    {
        if (($ctx['term'] ?? null) instanceof Term) {
            return $ctx['term'];
        }

        if (($ctx['post'] ?? null) instanceof Post) {
            /** @var Post $post */
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
            /** @var CurrentContentContext $current */
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
     * Accepts: true/false, 1/0, yes/no, on/off
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
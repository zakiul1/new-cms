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
}
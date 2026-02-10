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

        // ✅ [products]
        $shortcodes->register('products', function (array $atts = [], ?string $content = null, array $context = []) {
            $sep = is_string($atts['sep'] ?? null) ? (string) $atts['sep'] : ', ';

            $terms = self::resolveCurrentTerms($context);

            if ($terms->isEmpty()) {
                return '';
            }

            $values = $terms
                ->map(fn(Term $t) => trim((string) ($t->product ?? '')))
                ->filter(fn(string $v) => $v !== '')
                ->values();

            if ($values->isEmpty()) {
                return '';
            }

            return e($values->implode($sep));
        });

        // ✅ [posts] / [posts count=12]
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

                // eager load only if relation exists
                if (method_exists(Post::class, 'featuredMedia')) {
                    $query->with(['featuredMedia']);
                }

                // show only public (if scope exists)
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

                // fallback if view missing
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

    /** @return Collection<int, Term> */
    private static function resolveCurrentTerms(array $ctx): Collection
    {
        if (($ctx['term'] ?? null) instanceof Term) {
            return collect([$ctx['term']]);
        }

        if (($ctx['post'] ?? null) instanceof Post) {
            /** @var Post $post */
            $post = $ctx['post'];

            try {
                return $post->categories()->orderBy('terms.name')->get();
            } catch (\Throwable $e) {
                return collect();
            }
        }

        if (($ctx['media'] ?? null) instanceof Media) {
            return self::mediaCategoryTerms($ctx['media']);
        }

        try {
            /** @var CurrentContentContext $current */
            $current = app(CurrentContentContext::class);

            if (($current->media ?? null) instanceof Media) {
                return self::mediaCategoryTerms($current->media);
            }

            if (($current->post ?? null) instanceof Post) {
                return $current->post->categories()->orderBy('terms.name')->get();
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return collect();
    }

    /** @return Collection<int, Term> */
    private static function mediaCategoryTerms(Media $media): Collection
    {
        $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');

        if (!$taxonomyId) {
            return collect();
        }

        try {
            return $media->terms()
                ->where('terms.taxonomy_id', $taxonomyId)
                ->orderBy('terms.name')
                ->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
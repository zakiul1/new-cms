<?php

namespace App\Cms\Shortcodes;

use App\Cms\Content\CurrentContentContext;
use App\Models\Media;
use App\Models\Post;
use App\Models\Taxonomy;
use App\Models\Term;
use Illuminate\Support\Collection;

class CoreShortcodes
{
    public static function register(): void
    {
        add_shortcode('products', function (array $atts = [], $content = null, array $context = []): string {
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

            // Escape output (safe in HTML)
            return e($values->implode($sep));
        });
    }

    /** @return Collection<int, Term> */
    private static function resolveCurrentTerms(array $ctx): Collection
    {
        // 1) Direct term context (archive pages, etc.)
        if (($ctx['term'] ?? null) instanceof Term) {
            return collect([$ctx['term']]);
        }

        // 2) Post context -> use post categories (taxonomy=category)
        if (($ctx['post'] ?? null) instanceof Post) {
            /** @var Post $post */
            $post = $ctx['post'];

            try {
                return $post->categories()->orderBy('terms.name')->get();
            } catch (\Throwable $e) {
                return collect();
            }
        }

        // 3) Media context -> use media_category taxonomy
        if (($ctx['media'] ?? null) instanceof Media) {
            return self::mediaCategoryTerms($ctx['media']);
        }

        // 4) Fallback: CurrentContentContext (your router sets media; you may set post elsewhere)
        try {
            /** @var CurrentContentContext $current */
            $current = app(CurrentContentContext::class);

            if ($current->media instanceof Media) {
                return self::mediaCategoryTerms($current->media);
            }

            if ($current->post instanceof Post) {
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
<?php

namespace App\Cms\Shortcodes;

use App\Models\Media;
use App\Models\Taxonomy;
use App\Models\Term;

class CoreShortcodes
{
    public static function register(): void
    {
        /**
         * NOTE:
         * Replace `add_shortcode(...)` with YOUR existing shortcode register function
         * if it is different (example: Shortcode::add, Shortcodes::register, etc).
         */
        add_shortcode('products', function (array $atts = [], $content = null, array $context = []) {
            // We want to show CURRENT media category product value on attachment pages
            /** @var Media|null $media */
            $media = $context['media'] ?? null;

            if (!$media instanceof Media) {
                return '';
            }

            $taxonomyId = Taxonomy::query()->where('key', 'media_category')->value('id');
            if (!$taxonomyId) {
                return '';
            }

            /** @var Term|null $term */
            $term = $media->terms()
                ->where('terms.taxonomy_id', $taxonomyId)
                ->orderBy('terms.name')
                ->first();

            if (!$term) {
                return '';
            }

            // ✅ Product field can be either column `product` or in meta.product
            $product = '';

            if (isset($term->product) && is_string($term->product)) {
                $product = trim($term->product);
            }

            if ($product === '') {
                $meta = is_array($term->meta ?? null) ? $term->meta : [];
                $product = is_string($meta['product'] ?? null) ? trim($meta['product']) : '';
            }

            return $product !== '' ? e($product) : '';
        });
    }
}
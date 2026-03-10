<?php

use App\Models\Media;

if (!function_exists('cms_img_srcset')) {
    function cms_img_srcset(Media $media, array $keys = ['thumb', 'small', 'hero_sm', 'large']): string
    {
        if (!$media->isImage()) {
            return '';
        }

        $variants = $media->variantRecords()
            ->whereIn('key', $keys)
            ->get()
            ->sortBy('width');

        $parts = [];

        foreach ($variants as $v) {
            if ($v->width) {
                $parts[] = $v->url() . ' ' . (int) $v->width . 'w';
            }
        }

        // include original as the largest fallback
        if ($media->width) {
            $parts[] = $media->url() . ' ' . (int) $media->width . 'w';
        }

        // unique while preserving order
        $parts = array_values(array_unique($parts));

        return implode(', ', $parts);
    }
}

if (!function_exists('cms_img')) {
    function cms_img(?Media $media, array $attrs = [], string $sizes = '(max-width: 575px) 100vw, (max-width: 1000px) 100vw, 400px'): string
    {
        if (!$media || !$media->isImage()) {
            return '';
        }

        // Prefer new canonical variants
        $src = $media->variantUrl('small')
            ?: $media->variantUrl('hero_sm')
            ?: $media->variantUrl('thumb')
            ?: $media->variantUrl('large')
            ?: $media->url();

        $attr = array_merge([
            'src' => $src,
            'alt' => $media->alt ?: $media->title ?: '',
            'loading' => 'lazy',
            'decoding' => 'async',
            'srcset' => cms_img_srcset($media, ['thumb', 'small', 'hero_sm', 'large']),
            'sizes' => $sizes,
        ], $attrs);

        $html = '<img';

        foreach ($attr as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }

            $html .= ' ' . e($k) . '="' . e((string) $v) . '"';
        }

        $html .= '>';

        return $html;
    }
}
<?php

use App\Models\Media;
use Illuminate\Support\Arr;

if (!function_exists('cms_img')) {
    /**
     * Render an <img> tag with srcset based on media_variants table.
     *
     * @param  Media|int|null  $media
     * @param  string  $srcKey  Which variant to use as "src" fallback (e.g. medium)
     * @param  array<string, mixed>  $attrs
     */
    function cms_img($media, string $srcKey = 'medium', array $attrs = []): string
    {
        if (is_int($media)) {
            $media = Media::query()->with('variantRecords')->find($media);
        }

        if (!$media instanceof Media) {
            return '';
        }

        if (!$media->isImage()) {
            return '';
        }

        $media->loadMissing('variantRecords');

        $variants = $media->variantRecords->all();

        // Build srcset: include only variants that have width
        $srcsetParts = [];
        foreach ($variants as $v) {
            $w = (int) ($v->width ?? 0);
            if ($w > 0) {
                $srcsetParts[] = $v->url() . ' ' . $w . 'w';
            }
        }

        // Choose src: prefer requested key -> else original
        $src = $media->variantUrl($srcKey) ?: $media->url();

        $alt = (string) Arr::get($attrs, 'alt', (string) ($media->alt ?? $media->title ?? ''));
        $sizes = Arr::get($attrs, 'sizes'); // optional

        // Merge attrs
        $final = array_merge([
            'src' => $src,
            'alt' => $alt,
            'loading' => Arr::get($attrs, 'loading', 'lazy'),
            'decoding' => Arr::get($attrs, 'decoding', 'async'),
        ], $attrs);

        if (!empty($srcsetParts)) {
            $final['srcset'] = implode(', ', $srcsetParts);
        }

        if (is_string($sizes) && $sizes !== '') {
            $final['sizes'] = $sizes;
        }

        // Remove non-HTML keys if you passed them
        unset($final['media'], $final['srcKey']);

        // Render attributes safely
        $htmlAttrs = '';
        foreach ($final as $k => $v) {
            if ($v === null || $v === false) {
                continue;
            }
            if ($v === true) {
                $htmlAttrs .= ' ' . e($k);
                continue;
            }
            $htmlAttrs .= ' ' . e($k) . '="' . e((string) $v) . '"';
        }

        return '<img' . $htmlAttrs . '>';
    }
}

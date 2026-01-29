<?php

use App\Models\Media;
use Illuminate\Support\Arr;

if (!function_exists('cms_picture')) {
    /**
     * WP-like <picture> output:
     * - <source type="image/webp" srcset="..."> (if webp variants exist)
     * - <img> fallback uses jpeg srcset + src (or original)
     *
     * @param Media|int|null $media
     * @param array<string,mixed> $attrs
     * @param string $srcKey Which key to use as default src (medium)
     * @param array<string> $keys variant keys to include
     */
    function cms_picture($media, array $attrs = [], string $srcKey = 'medium', array $keys = ['thumb', 'medium', 'large']): string
    {
        if (is_int($media)) {
            $media = Media::query()->with('variantRecords')->find($media);
        }

        if (!$media instanceof Media || !$media->isImage()) {
            return '';
        }

        $media->loadMissing('variantRecords');
        $variants = $media->variantRecords;

        $sizes = Arr::get($attrs, 'sizes');
        $defaultSizes = '(max-width: 768px) 100vw, 768px';

        $renderAttrs = function (array $a): string {
            $out = '';
            foreach ($a as $k => $v) {
                if ($v === null || $v === false) {
                    continue;
                }
                if ($v === true) {
                    $out .= ' ' . e($k);
                    continue;
                }
                $out .= ' ' . e($k) . '="' . e((string) $v) . '"';
            }
            return $out;
        };

        /**
         * Build width-based srcset for a set of formats (e.g. ['webp'] or ['jpeg','jpg']).
         */
        $buildSrcset = function (array $formats, bool $includeOriginal = false) use ($variants, $keys, $media): string {
            $formats = array_map(fn($f) => strtolower((string) $f), $formats);

            $items = $variants
                ->whereIn('key', $keys)
                ->filter(function ($v) use ($formats) {
                    $fmt = strtolower((string) ($v->format ?? ''));
                    return in_array($fmt, $formats, true);
                })
                ->filter(fn($v) => (int) ($v->width ?? 0) > 0)
                ->sortBy(fn($v) => (int) $v->width)
                ->values();

            $parts = [];
            $seenW = [];

            foreach ($items as $v) {
                $w = (int) $v->width;
                if ($w <= 0 || isset($seenW[$w])) {
                    continue;
                }
                $seenW[$w] = true;
                $parts[] = $v->url() . ' ' . $w . 'w';
            }

            // Optionally include the original as the largest candidate (WP-like)
            if ($includeOriginal && (int) ($media->width ?? 0) > 0) {
                $ow = (int) $media->width;

                // only add if not already present at same width
                if (!isset($seenW[$ow])) {
                    $parts[] = $media->url() . ' ' . $ow . 'w';
                }
            }

            return implode(', ', $parts);
        };

        $webpSrcset = $buildSrcset(['webp'], false);
        $jpegSrcset = $buildSrcset(['jpeg', 'jpg'], true);

        // Choose <img src> (jpeg preferred)
        $imgSrc = null;

        // prefer jpeg srcKey
        $jpegPreferred = $variants->first(function ($v) use ($srcKey) {
            return (string) ($v->key ?? '') === $srcKey
                && in_array(strtolower((string) ($v->format ?? '')), ['jpeg', 'jpg'], true);
        });

        if ($jpegPreferred) {
            $imgSrc = $jpegPreferred->url();
        } else {
            // fallback: any srcKey variant (maybe webp) or original
            $any = $variants->firstWhere('key', $srcKey);
            $imgSrc = $any?->url() ?: $media->url();
        }

        $alt = (string) Arr::get($attrs, 'alt', (string) ($media->alt ?? $media->title ?? ''));

        $imgAttrs = array_merge([
            'src' => $imgSrc,
            'alt' => $alt,
            'loading' => Arr::get($attrs, 'loading', 'lazy'),
            'decoding' => Arr::get($attrs, 'decoding', 'async'),
        ], $attrs);

        // Put jpeg srcset on <img> fallback if available
        if ($jpegSrcset !== '') {
            $imgAttrs['srcset'] = $jpegSrcset;
            $imgAttrs['sizes'] = is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes;
        } elseif (is_string($sizes) && $sizes !== '') {
            $imgAttrs['sizes'] = $sizes;
        }

        unset($imgAttrs['media'], $imgAttrs['srcKey'], $imgAttrs['keys']);

        $html = '<picture>';

        if ($webpSrcset !== '') {
            $sourceAttrs = [
                'type' => 'image/webp',
                'srcset' => $webpSrcset,
                'sizes' => is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes,
            ];

            $html .= '<source' . $renderAttrs($sourceAttrs) . '>';
        }

        $html .= '<img' . $renderAttrs($imgAttrs) . '>';
        $html .= '</picture>';

        return $html;
    }
}
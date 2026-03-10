<?php

use App\Models\Media;
use Illuminate\Support\Arr;

if (!function_exists('cms_picture')) {
    /**
     * WebP-first <picture> output:
     * - <source type="image/avif" srcset="..."> (if avif variants exist)
     * - <source type="image/webp" srcset="..."> (if webp variants exist)
     * - <img> fallback prefers webp, then avif, then jpeg/jpg, then original
     *
     * @param Media|int|null $media
     * @param array<string,mixed> $attrs
     * @param string $srcKey Which key to use as default src
     * @param array<string> $keys Variant keys to include
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
         * Build width-based srcset for a set of formats.
         *
         * @param array<int,string> $formats
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

            if ($includeOriginal && (int) ($media->width ?? 0) > 0) {
                $ow = (int) $media->width;

                if (!isset($seenW[$ow])) {
                    $parts[] = $media->url() . ' ' . $ow . 'w';
                }
            }

            return implode(', ', $parts);
        };

        $avifSrcset = $buildSrcset(['avif'], false);
        $webpSrcset = $buildSrcset(['webp'], false);
        $jpegSrcset = $buildSrcset(['jpeg', 'jpg'], false);

        // Choose <img src> and intrinsic dimensions
        $imgSrc = null;
        $imgWidth = null;
        $imgHeight = null;

        // Prefer webp for fallback <img>
        $webpPreferred = $variants->first(function ($v) use ($srcKey) {
            return (string) ($v->key ?? '') === $srcKey
                && strtolower((string) ($v->format ?? '')) === 'webp';
        });

        if ($webpPreferred) {
            $imgSrc = $webpPreferred->url();
            $imgWidth = (int) ($webpPreferred->width ?? 0) ?: null;
            $imgHeight = (int) ($webpPreferred->height ?? 0) ?: null;
        } else {
            $any = $variants
                ->where('key', $srcKey)
                ->sortBy(function ($v) {
                    $fmt = strtolower((string) ($v->format ?? ''));

                    return match ($fmt) {
                        'webp' => 0,
                        'avif' => 1,
                        'jpeg', 'jpg' => 2,
                        'png' => 3,
                        default => 4,
                    };
                })
                ->first();

            if ($any) {
                $imgSrc = $any->url();
                $imgWidth = (int) ($any->width ?? 0) ?: null;
                $imgHeight = (int) ($any->height ?? 0) ?: null;
            } else {
                $imgSrc = $media->url();
            }
        }

        if (!$imgWidth && (int) ($media->width ?? 0) > 0) {
            $imgWidth = (int) $media->width;
        }

        if (!$imgHeight && (int) ($media->height ?? 0) > 0) {
            $imgHeight = (int) $media->height;
        }

        $alt = (string) Arr::get($attrs, 'alt', (string) ($media->alt ?? $media->title ?? ''));

        $imgAttrs = array_merge([
            'src' => $imgSrc,
            'alt' => $alt,
            'loading' => Arr::get($attrs, 'loading', 'lazy'),
            'decoding' => Arr::get($attrs, 'decoding', 'async'),
        ], $attrs);

        if ($imgWidth) {
            $imgAttrs['width'] = $imgWidth;
        }

        if ($imgHeight) {
            $imgAttrs['height'] = $imgHeight;
        }

        // Prefer webp srcset on <img> too, so rendered HTML stays webp-first
        if ($webpSrcset !== '') {
            $imgAttrs['srcset'] = $webpSrcset;
            $imgAttrs['sizes'] = is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes;
        } elseif ($avifSrcset !== '') {
            $imgAttrs['srcset'] = $avifSrcset;
            $imgAttrs['sizes'] = is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes;
        } elseif ($jpegSrcset !== '') {
            $imgAttrs['srcset'] = $jpegSrcset;
            $imgAttrs['sizes'] = is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes;
        } elseif (is_string($sizes) && $sizes !== '') {
            $imgAttrs['sizes'] = $sizes;
        }

        unset($imgAttrs['media'], $imgAttrs['srcKey'], $imgAttrs['keys']);

        $html = '<picture>';

        if ($avifSrcset !== '') {
            $html .= '<source' . $renderAttrs([
                'type' => 'image/avif',
                'srcset' => $avifSrcset,
                'sizes' => is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes,
            ]) . '>';
        }

        if ($webpSrcset !== '') {
            $html .= '<source' . $renderAttrs([
                'type' => 'image/webp',
                'srcset' => $webpSrcset,
                'sizes' => is_string($sizes) && $sizes !== '' ? $sizes : $defaultSizes,
            ]) . '>';
        }

        $html .= '<img' . $renderAttrs($imgAttrs) . '>';
        $html .= '</picture>';

        return $html;
    }
}
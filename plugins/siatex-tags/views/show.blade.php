{{-- plugins/siatex-tags/views/show.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \Plugins\SiatexTags\Models\SiatexTag $tag */
        /** @var \Illuminate\Support\Collection|\App\Models\Media[] $mediaItems */

        // -----------------------------
        // Helpers (similar to attachment)
        // -----------------------------
        $allowedHtml =
            '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>' .
            '<div><span><section><article><header><footer>' .
            '<picture><source><img><button><script>';

        $removeScriptStyleBlocks = function (string $html): string {
            $html = preg_replace('~<\s*script\b[^>]*>.*?<\s*/\s*script\s*>~is', '', $html) ?? $html;
            $html = preg_replace('~<\s*style\b[^>]*>.*?<\s*/\s*style\s*>~is', '', $html) ?? $html;
            return $html;
        };

        $htmlValue = function ($value): string {
            if ($value === null) {
                return '';
            }
            if (is_string($value)) {
                return $value;
            }
            if (is_array($value)) {
                $html = $value['html'] ?? ($value['value'] ?? ($value['content'] ?? ''));
                return is_string($html) ? $html : '';
            }
            if (is_object($value)) {
                $arr = (array) $value;
                $html = $arr['html'] ?? ($arr['value'] ?? ($arr['content'] ?? ''));
                return is_string($html) ? $html : '';
            }
            return '';
        };

        $htmlIsEmpty = function ($value) use ($htmlValue): bool {
            $html = trim($htmlValue($value));
            if ($html === '') {
                return true;
            }

            $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = str_replace("\xc2\xa0", ' ', $text);
            $text = trim(strip_tags($text));

            return $text === '';
        };

        // -----------------------------
        // Tag data
        // -----------------------------
        $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];

        $title = trim((string) $tag->title);
        $subtitle = trim((string) data_get($meta, 'subtitle', ''));
        $subDesc = data_get($meta, 'sub_description', '');

        $contentHtml = '';
        if (is_array($tag->content_json ?? null)) {
            $contentHtml = (string) ($tag->content_json['html'] ?? '');
        } elseif (is_string($tag->content_json ?? null)) {
            $contentHtml = (string) $tag->content_json;
        }

        // Shortcode parser (CMS)
        $parser = app(\App\Cms\Content\Shortcodes\ShortcodeParser::class);
        $shortcodeCtx = ['siatex_tag' => $tag];

        $subDescHtml = $htmlValue($subDesc);

        // URL (no /tag/)
        $tagUrl = url('/' . ltrim((string) $tag->slug, '/'));

        // -----------------------------
        // SEO: use controller $seo if exists, else build from tag meta.seo
        // supports shortcodes (like attachment)
        // -----------------------------
        if (!isset($seo) || !is_array($seo)) {
            $seo = [];
        }

        $tagSeo = data_get($meta, 'seo', []);
        $tagSeo = is_array($tagSeo) ? $tagSeo : [];

        $seoShortcodeText = function (string $value) use ($shortcodeCtx, $removeScriptStyleBlocks): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (function_exists('do_shortcode')) {
                try {
                    $value = (string) do_shortcode($value, $shortcodeCtx);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $value = $removeScriptStyleBlocks($value);
            return trim(strip_tags($value));
        };

        $seoShortcodeUrl = function (string $value) use ($shortcodeCtx, $removeScriptStyleBlocks): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            if (function_exists('do_shortcode')) {
                try {
                    $value = (string) do_shortcode($value, $shortcodeCtx);
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            $value = $removeScriptStyleBlocks($value);
            return trim($value);
        };

        // title/description defaults
        $fallbackDescSource = '';
        if (!$htmlIsEmpty($subDescHtml)) {
            $fallbackDescSource = (string) $subDescHtml;
        } elseif (trim($contentHtml) !== '') {
            $fallbackDescSource = (string) $contentHtml;
        }

        $fallbackDescText = trim(strip_tags($removeScriptStyleBlocks((string) $fallbackDescSource)));

        $seoTitle = $seoShortcodeText((string) ($seo['title'] ?? ($tagSeo['title'] ?? $title)));
        if ($seoTitle === '') {
            $seoTitle = $title;
        }

        $seoDesc = $seoShortcodeText((string) ($seo['description'] ?? ($tagSeo['description'] ?? $fallbackDescText)));

        $seoCanonical = $seoShortcodeUrl((string) ($seo['canonical'] ?? ($tagSeo['canonical'] ?? '')));
        if ($seoCanonical === '') {
            $seoCanonical = $tagUrl;
        }

        $seoRobots = $seoShortcodeText((string) ($seo['robots'] ?? ($tagSeo['robots'] ?? '')));
        if ($seoRobots === '') {
            $seoRobots = 'index, follow';
        }

        // We'll set og image later using hero image if available.
$seoOgImage = $seoShortcodeUrl(
    (string) ($seo['og']['image'] ?? ($tagSeo['og_image'] ?? ($seo['og_image'] ?? ''))),
);

// finalize seo array for layout
$seo = array_merge($seo, [
    'title' => $seoTitle,
    'description' => $seoDesc,
    'canonical' => $seoCanonical,
    'robots' => $seoRobots,
    'og' => array_merge(is_array($seo['og'] ?? null) ? $seo['og'] : [], [
        'title' => $seoTitle,
        'description' => $seoDesc,
        'type' => 'website',
        'url' => $seoCanonical,
        'image' => $seoOgImage,
    ]),
]);

// -----------------------------
// Media selection:
// - Hero: 2 images (random)
// - Related products: max 12 random, excluding hero
// - Related links: max 10 random, excluding hero + related
// - Apply media defaults (same as attachment) for display
// -----------------------------
$allMedia = collect($mediaItems ?? []);

// keep only images (hero needs images)
$imageMedia = $allMedia
    ->filter(function ($m) {
        return $m && method_exists($m, 'isImage') ? $m->isImage() : true;
    })
    ->values();

$hero = $imageMedia->shuffle()->take(2)->values();
$heroIds = $hero->pluck('id')->map(fn($v) => (int) $v)->all();

$pool = $allMedia->reject(fn($m) => in_array((int) ($m->id ?? 0), $heroIds, true))->values();

$related = $pool->shuffle()->take(12)->values();
$relatedIds = $related->pluck('id')->map(fn($v) => (int) $v)->all();

$relatedLinks = $pool
    ->reject(fn($m) => in_array((int) ($m->id ?? 0), $relatedIds, true))
    ->shuffle()
    ->take(10)
    ->values();

// if no og image set, pick from hero
if (($seo['og']['image'] ?? '') === '' && $hero->count()) {
    try {
        $firstHero = $hero->first();
        if ($firstHero && method_exists($firstHero, 'url')) {
            $seo['og']['image'] = (string) $firstHero->url('large');
        }
    } catch (\Throwable $e) {
        // ignore
    }
}

// -----------------------------
// Tag hero text:
// Prefer Sub description; else Content (render shortcodes)
// -----------------------------
$heroTextHtml = '';
if (!$htmlIsEmpty($subDescHtml)) {
    $heroTextHtml = (string) $parser->render((string) $subDescHtml, $shortcodeCtx);
} elseif (trim($contentHtml) !== '') {
            $heroTextHtml = (string) $parser->render((string) $contentHtml, $shortcodeCtx);
        }
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>
            <a class="text-[#1f5f99] hover:underline" href="{{ $tagUrl }}">{{ $title }}</a>
        </nav>
    </div>

    {{-- HERO (design like screenshot: big text left + 2 overlapping images right) --}}
    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-gray-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">

                {{-- TEXT LEFT --}}
                <div class="order-2 min-w-0 lg:order-1 lg:col-span-7">
                    <div class="mt-2 text-xs font-semibold tracking-widest text-slate-700">
                        {{ $subtitle !== '' ? $subtitle : 'YOUR TECH-PACK, OUR PRODUCTION' }}
                    </div>

                    <h1 class="mt-4 break-words text-5xl font-extrabold leading-[1.05] tracking-tight text-slate-900">
                        {{ $title }}
                    </h1>

                    @if (trim($heroTextHtml) !== '')
                        <div class="mt-6 space-y-4 text-justify text-base leading-8 text-slate-700">
                            {!! $heroTextHtml !!}
                        </div>
                    @endif
                </div>

                {{-- IMAGES RIGHT (2 images, overlap) --}}
                <div class="order-1 lg:order-2 lg:col-span-5 lg:sticky lg:top-24 lg:self-start">
                    <div class="relative mx-auto max-w-[520px]">
                        @php
                            $h1 = $hero->get(0);
                            $h2 = $hero->get(1);
                        @endphp

                        @if ($h1)
                            {{-- big image --}}
                            <div class="relative overflow-hidden bg-white shadow-lg">
                                {!! cms_picture($h1, ['class' => 'w-full h-auto object-cover'], 'large', ['medium', 'medium_large', 'large']) !!}
                            </div>
                        @else
                            <div class="h-80 w-full bg-slate-100"></div>
                        @endif

                        @if ($h2)
                            {{-- overlay image --}}
                            <div class="absolute left-[-12%] top-[18%] w-[72%] overflow-hidden bg-white shadow-2xl">
                                {!! cms_picture($h2, ['class' => 'w-full h-auto object-cover'], 'large', ['medium', 'medium_large', 'large']) !!}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- RELATED PRODUCTS + META + RELATED LINKS (same bottom style logic as attachment) --}}
    @if ($related->count() || $relatedLinks->count())
        <section class="bg-white">
            <div class="page-container mx-auto px-4 py-10">

                {{-- RELATED PRODUCTS GRID (max 12, random, hero excluded) --}}
                @if ($related->count())
                    <div class="mt-6 grid grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($related as $r)
                            @php
                                /** @var \App\Models\Media $r */

                                // Apply defaults in-memory (same as attachment)
                                if (function_exists('do_action')) {
                                    do_action('media.attachment.defaults.persist', $r);
                                }

                                $rMeta = $r->meta ?? [];
                                if (is_string($rMeta) && trim($rMeta) !== '') {
                                    $decoded = json_decode($rMeta, true);
                                    $rMeta = is_array($decoded) ? $decoded : [];
                                }
                                if (!is_array($rMeta)) {
                                    $rMeta = [];
                                }

                                $rMetaTitle = trim((string) data_get($rMeta, 'frontend.meta_title', ''));

                                $rTitle =
                                    (string) ($r->title ?:
                                    ($rMetaTitle !== ''
                                        ? $rMetaTitle
                                        : $r->original_filename ?? ''));
                                $rTitle = trim($rTitle) !== '' ? trim($rTitle) : 'Attachment';

                                $rUrl = filled($r->slug) ? url('/' . ltrim((string) $r->slug, '/')) : $r->url();

                                // For Get Price button (same payload style as attachment)
                                $rImage = '';
                                try {
                                    if (method_exists($r, 'url')) {
                                        $rImage = (string) $r->url('medium');
                                    }
                                } catch (\Throwable $e) {
                                    $rImage = '';
                                }
                            @endphp

                            <div class="group text-center">
                                <a href="{{ $rUrl }}" class="block">
                                    <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
                                        {!! cms_picture(
                                            $r,
                                            [
                                                'alt' => e($rTitle),
                                                'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                                'sizes' => '(max-width: 768px) 50vw, 220px',
                                                'loading' => 'lazy',
                                                'decoding' => 'async',
                                            ],
                                            'medium',
                                            ['thumb', 'medium', 'medium_large'],
                                        ) !!}
                                    </div>

                                    <div class="mx-auto mt-4 w-full max-w-[220px] text-slate-700">
                                        <h3 class="text-sm font-semibold leading-snug line-clamp-2">
                                            {{ $rTitle }}
                                        </h3>
                                    </div>
                                </a>

                                <button type="button"
                                    class="cf-get-price mt-3 inline-flex items-center justify-center text-sm font-semibold text-[#1f5f99] underline underline-offset-4 hover:text-[#194f7f]"
                                    data-item-id="{{ (int) $r->id }}" data-item-type="media"
                                    data-item-title="{{ e($rTitle) }}" data-item-url="{{ e($rUrl) }}"
                                    data-item-image="{{ e($rImage) }}">
                                    Get Price
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- BOTTOM: META + RELATED LINKS (attachment-like) --}}
                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $title }}
                        </h2>

                        {{-- Tag content shown again as “meta description block” --}}
                        @if (trim($heroTextHtml) !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $heroTextHtml !!}
                            </div>
                        @endif
                    </div>

                    {{-- Hide on mobile --}}
                    <div class="hidden lg:block lg:col-span-4">
                        <div class="rounded bg-slate-100 p-6">
                            <div class="text-lg font-semibold text-slate-900">Related Links :</div>

                            @if ($relatedLinks->count())
                                <ul class="mt-4 space-y-3 text-sm text-slate-700">
                                    @foreach ($relatedLinks as $q)
                                        @php
                                            /** @var \App\Models\Media $q */

                                            // Apply defaults in-memory (same as attachment)
                                            if (function_exists('do_action')) {
                                                do_action('media.attachment.defaults.persist', $q);
                                            }

                                            $qMeta = $q->meta ?? [];
                                            if (is_string($qMeta) && trim($qMeta) !== '') {
                                                $decoded = json_decode($qMeta, true);
                                                $qMeta = is_array($decoded) ? $decoded : [];
                                            }
                                            if (!is_array($qMeta)) {
                                                $qMeta = [];
                                            }

                                            $qMetaTitle = trim((string) data_get($qMeta, 'frontend.meta_title', ''));

                                            $qTitle =
                                                (string) ($q->title ?:
                                                ($qMetaTitle !== ''
                                                    ? $qMetaTitle
                                                    : $q->original_filename ?? ''));
                                            $qTitle = trim($qTitle) !== '' ? trim($qTitle) : 'Attachment';

                                            $qUrl = filled($q->slug)
                                                ? url('/' . ltrim((string) $q->slug, '/'))
                                                : $q->url();
                                        @endphp

                                        <li
                                            class="flex items-start gap-2 border-b border-slate-200 pb-3 last:border-b-0 last:pb-0">
                                            <span class="mt-[2px] text-slate-500">›</span>
                                            <a href="{{ $qUrl }}" target="_blank" rel="noopener noreferrer"
                                                class="block truncate italic text-slate-700 hover:text-slate-900 hover:underline"
                                                title="{{ $qTitle }}">
                                                {{ $qTitle }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="mt-3 text-sm text-slate-500">
                                    No related links found.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
        </section>
    @endif
@endsection

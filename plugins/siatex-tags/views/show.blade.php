{{-- plugins/siatex-tags/views/show.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \Plugins\SiatexTags\Models\SiatexTag $tag */
        /** @var \Illuminate\Support\Collection|\App\Models\Media[] $mediaItems */

        // -----------------------------
        // Helpers (same style as attachment)
        // -----------------------------
        $allowedHtml =
            '<p><br><b><strong><i><em><u><ul><ol><li><blockquote><a><h1><h2><h3><h4><h5><h6>' .
            '<div><span><section><article><header><footer>' .
            '<picture><source><img>' .
            '<button>' .
            '<script>';

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

        // Shortcode parser (CMS)
        $parser = app(\App\Cms\Content\Shortcodes\ShortcodeParser::class);
        $shortcodeCtx = ['siatex_tag' => $tag];

        // Unified helpers using CMS shortcode parser (works everywhere)
        $renderShortcodeText = function ($value) use ($parser, $shortcodeCtx, $removeScriptStyleBlocks): string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }

            try {
                // ✅ FIX: ShortcodeParser does NOT have parse(); use render()
                $value = (string) $parser->render($value, $shortcodeCtx);
            } catch (\Throwable $e) {
                // ignore
            }

            $value = $removeScriptStyleBlocks($value);

            // Text output only (SEO/title/subtitle)
            return trim(strip_tags($value));
        };

        $renderShortcodeHtml = function ($value) use ($parser, $shortcodeCtx): string {
            $value = (string) $value;
            if (trim($value) === '') {
                return '';
            }

            try {
                return (string) $parser->render($value, $shortcodeCtx);
            } catch (\Throwable $e) {
                return $value;
            }
        };

        // URL (no /tag/)
        $tagUrl = url('/' . ltrim((string) $tag->slug, '/'));

        // -----------------------------
        // ✅ Apply tag defaults on frontend (preview + normal)
        // -----------------------------
        if (function_exists('do_action')) {
            do_action('siatex.tag.defaults.persist', $tag);
        }

        // -----------------------------
        // Tag data (after defaults applied)
        // -----------------------------
        $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
        if (!is_array($meta)) {
            $meta = [];
        }

        // RAW values (may contain shortcodes like [tag])
        $titleRaw = (string) ($tag->title ?? '');
        $subtitleRaw = (string) data_get($meta, 'subtitle', '');

        $subDesc = data_get($meta, 'sub_description', '');
        $subDescHtmlRaw = $htmlValue($subDesc);

        $contentHtmlRaw = '';
        if (is_array($tag->content_json ?? null)) {
            $contentHtmlRaw = (string) ($tag->content_json['html'] ?? '');
        } elseif (is_string($tag->content_json ?? null)) {
            $contentHtmlRaw = (string) $tag->content_json;
        }

        // ✅ Render shortcodes for title/subtitle (text)
        $title = $renderShortcodeText($titleRaw);
        $subtitle = $renderShortcodeText($subtitleRaw);

        // -----------------------------
        // ✅ Breadcrumb category: Home / Category Name / Tag Title
        // Category name is NOT a link
        // -----------------------------
        $categoryName = '';
        try {
            $termId = (int) ($tag->media_category_term_id ?? 0);
            if ($termId > 0) {
                $term = \App\Models\Term::query()->find($termId);
                if ($term && !empty($term->name)) {
                    $categoryName = trim((string) $term->name);
                }
            }
        } catch (\Throwable $e) {
            $categoryName = '';
        }

        // -----------------------------
        // SEO (keep your existing behavior, but use parser consistently)
        // -----------------------------
        if (!isset($seo) || !is_array($seo)) {
            $seo = [];
        }

        $tagSeo = data_get($meta, 'seo', []);
        $tagSeo = is_array($tagSeo) ? $tagSeo : [];

        $fallbackDescSource = '';
        if (!$htmlIsEmpty($subDescHtmlRaw)) {
            $fallbackDescSource = (string) $subDescHtmlRaw;
        } elseif (trim($contentHtmlRaw) !== '') {
            $fallbackDescSource = (string) $contentHtmlRaw;
        }

        $fallbackDescText = trim(strip_tags($removeScriptStyleBlocks((string) $fallbackDescSource)));

        // ✅ SEO Title: use defaults only if tagSeo is empty; and shortcode-render it
        $seoTitleSource = (string) ($seo['title'] ?? ($tagSeo['title'] ?? $titleRaw));
        $seoTitle = $renderShortcodeText($seoTitleSource);
        if ($seoTitle === '') {
            $seoTitle = $title !== '' ? $title : $renderShortcodeText($titleRaw);
        }

        // ✅ SEO Description: shortcode-render to text (already working, keep)
        $seoDescSource = (string) ($seo['description'] ?? ($tagSeo['description'] ?? $fallbackDescText));
        $seoDesc = $renderShortcodeText($seoDescSource);

        // Canonical / Robots / OG
        $seoCanonicalSource = (string) ($seo['canonical'] ?? ($tagSeo['canonical'] ?? ''));
        $seoCanonical = trim($renderShortcodeText($seoCanonicalSource));
        if ($seoCanonical === '') {
            $seoCanonical = $tagUrl;
        }

        $seoRobotsSource = (string) ($seo['robots'] ?? ($tagSeo['robots'] ?? ''));
        $seoRobots = $renderShortcodeText($seoRobotsSource);
        if ($seoRobots === '') {
            $seoRobots = 'index, follow';
        }

        $seoOgImageSource = (string) ($seo['og']['image'] ?? ($tagSeo['og_image'] ?? ($seo['og_image'] ?? '')));
        $seoOgImage = trim($renderShortcodeText($seoOgImageSource));

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
        // ✅ Media selection (same as your current code)
        // -----------------------------
        $allMedia = collect($mediaItems ?? [])
            ->filter(fn($m) => $m instanceof \App\Models\Media)
            ->values();

        $imageMedia = $allMedia->filter(fn($m) => method_exists($m, 'isImage') ? $m->isImage() : false)->values();

        // 1) Hero image (random)
        $heroMedia = $imageMedia->shuffle()->first();
        $heroId = $heroMedia ? (int) $heroMedia->id : 0;

        // candidate pool excluding hero
        $pool = $allMedia->reject(fn($m) => (int) $m->id === $heroId)->values();

        // 2) Related products: random max 12
        $related = $pool->shuffle()->take(12)->values();
        $relatedIds = $related->pluck('id')->map(fn($v) => (int) $v)->all();

        // 3) Related links: start from remaining pool, exclude related
        $relatedLinks = $pool
            ->reject(fn($m) => in_array((int) $m->id, $relatedIds, true))
            ->shuffle()
            ->take(10)
            ->values();

        // If category is small, refill
        $need = 10 - $relatedLinks->count();
        if ($need > 0) {
            $excludeIds = collect([$heroId])
                ->merge($relatedIds)
                ->unique()
                ->values()
                ->all();

            $more = $allMedia
                ->reject(fn($m) => in_array((int) $m->id, $excludeIds, true))
                ->shuffle()
                ->take($need)
                ->values();

            $relatedLinks = $relatedLinks->concat($more)->take(10)->values();
        }

        if ($relatedLinks->isEmpty() && $pool->count() > 0) {
            $relatedLinks = $pool->shuffle()->take(10)->values();
        }

        // If no og image set, pick from hero
        if (($seo['og']['image'] ?? '') === '' && $heroMedia) {
            try {
                if (method_exists($heroMedia, 'url')) {
                    $seo['og']['image'] = (string) $heroMedia->url('large');
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // -----------------------------
        // Hero text: use Tag CONTENT (shortcode-rendered + sanitized)
        // -----------------------------
        $heroTextHtml = '';
        if (trim($contentHtmlRaw) !== '') {
            $heroTextHtml = $renderShortcodeHtml((string) $contentHtmlRaw);
        } elseif (!$htmlIsEmpty($subDescHtmlRaw)) {
            $heroTextHtml = $renderShortcodeHtml((string) $subDescHtmlRaw);
        }

        $heroTextHtml = $removeScriptStyleBlocks((string) $heroTextHtml);
        $heroTextHtml = strip_tags($heroTextHtml, $allowedHtml);

        // Bottom-left content:
        // ✅ MUST be from Sub title + Sub description
        // and if empty => Tag Defaults (already applied by do_action persist)
        $bottomTitleRaw = (string) data_get($meta, 'subtitle', '');
        $bottomTitle = $renderShortcodeText($bottomTitleRaw);
        if ($bottomTitle === '') {
            // final fallback (should rarely happen if defaults are configured)
            $bottomTitle = $title;
        }

        $bottomDescHtml = '';
        $bottomDescRaw = data_get($meta, 'sub_description', '');
        $bottomDescHtmlRaw = $htmlValue($bottomDescRaw);

        if (!$htmlIsEmpty($bottomDescHtmlRaw)) {
            $bottomDescHtml = $renderShortcodeHtml((string) $bottomDescHtmlRaw);
        }
        $bottomDescHtml = $removeScriptStyleBlocks((string) $bottomDescHtml);
        $bottomDescHtml = strip_tags($bottomDescHtml, $allowedHtml);

        // "Get Price" payload
        $tagImage = '';
        try {
            if ($heroMedia && method_exists($heroMedia, 'url')) {
                $tagImage = (string) $heroMedia->url('medium');
            }
        } catch (\Throwable $e) {
            $tagImage = '';
        }
    @endphp

    {{-- Breadcrumb --}}
    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>

            @if (trim($categoryName) !== '')
                <span class="mx-2 text-slate-300">/</span>
                <span class="text-slate-600">{{ $categoryName }}</span>
            @endif

            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    {{-- HERO --}}
    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">

                {{-- IMAGE --}}
                <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                        {!! cms_picture(
                            $heroMedia,
                            [
                                'alt' => e($title),
                                'class' => 'w-full object-contain',
                                'sizes' => '(max-width: 1024px) 100vw, 420px',
                                'loading' => 'eager',
                                'decoding' => 'async',
                                'fetchpriority' => 'high',
                            ],
                            'large',
                            ['medium', 'medium_large', 'large'],
                        ) !!}
                    @else
                        <div class="h-80 w-full bg-slate-100"></div>
                    @endif
                </div>

                {{-- CONTENT --}}
                <div class="order-2 min-w-0 lg:order-1 lg:col-span-6">
                    <div class="h-1 w-20 bg-red-500"></div>

                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        Your Tech-pack, Our production
                    </div>

                    <h1 class="mt-3 break-words text-4xl font-extrabold leading-tight tracking-tight text-[#1f5f99]">
                        {{ $title }}
                    </h1>

                    @if (trim($heroTextHtml) !== '')
                        <div class="mt-4 space-y-4 text-justify text-sm leading-7 text-slate-700">
                            {!! $heroTextHtml !!}
                        </div>
                    @endif

                    <a href="#"
                        class="cf-get-price mt-8 inline-flex items-center rounded bg-[#1f5f99] px-6 py-3 text-sm font-semibold text-white hover:bg-[#194f7f]"
                        data-item-id="{{ (int) $tag->id }}" data-item-type="tag" data-item-title="{{ e($title) }}"
                        data-item-url="{{ e($tagUrl) }}" data-item-image="{{ e($tagImage) }}">
                        Get Price
                    </a>
                </div>

            </div>
        </div>
    </section>

    {{-- RELATED GRID + META + RELATED LINKS --}}
    @if ($related->count() || $relatedLinks->count() || trim($bottomTitle) !== '' || trim($bottomDescHtml) !== '')
        <section class="bg-white">
            <div class="page-container mx-auto px-4 py-10">

                {{-- RELATED PRODUCTS GRID --}}
                @if ($related->count())
                    <div class="my-8 grid grid-cols-2 gap-8 md:grid-cols-3 lg:grid-cols-4">
                        @foreach ($related as $r)
                            @php
                                /** @var \App\Models\Media $r */

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

                {{-- META + RELATED LINKS --}}
                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $bottomTitle !== '' ? $bottomTitle : $title }}
                        </h2>

                        @if (trim($bottomDescHtml) !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $bottomDescHtml !!}
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

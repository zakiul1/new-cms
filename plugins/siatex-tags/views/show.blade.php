{{-- plugins/siatex-tags/views/show.blade.php --}}
@extends('layouts.app')

@section('content')
    @once
        @push('scripts')
            <script src="{{ asset('_contact/cart.js') }}" defer></script>
        @endpush
    @endonce

    @php
        /** @var \Plugins\SiatexTags\Models\SiatexTag $tag */
        /** @var \Illuminate\Support\Collection|\App\Models\Media[] $mediaItems */

        $settingsRepo = app(\App\Cms\Core\SettingsRepository::class);

        $sloganTag = trim((string) $settingsRepo->get('core', 'slogan_tag', 'Your Tech-pack, Our production'));
        if ($sloganTag === '') {
            $sloganTag = 'Your Tech-pack, Our production';
        }

        $productStylePrefix = trim((string) $settingsRepo->get('core', 'product_style_prefix', 'Art:SC'));
        if ($productStylePrefix === '') {
            $productStylePrefix = 'Art:SC';
        }

        $quoteButtonText = trim((string) $settingsRepo->get('core', 'quote_button_text', 'Custom Quote'));
        if ($quoteButtonText === '') {
            $quoteButtonText = 'Custom Quote';
        }

        $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
        if ($quoteButtonLabel === '') {
            $quoteButtonLabel = 'Custom Quote';
        }
        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonLabel)));

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

        $parser = app(\App\Cms\Content\Shortcodes\ShortcodeParser::class);
        $shortcodeCtx = ['siatex_tag' => $tag];

        $renderShortcodeText = function ($value) use ($parser, $shortcodeCtx, $removeScriptStyleBlocks): string {
            $value = trim((string) $value);
            if ($value === '') {
                return '';
            }

            try {
                $value = (string) $parser->render($value, $shortcodeCtx);
            } catch (\Throwable $e) {
            }

            $value = $removeScriptStyleBlocks($value);

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

        $tagUrl = function_exists('cms_slug_url')
            ? cms_slug_url((string) $tag->slug)
            : url('/' . trim((string) $tag->slug, '/') . '/');

        if (function_exists('do_action')) {
            do_action('siatex.tag.defaults.persist', $tag);
        }

        $meta = is_array($tag->meta_json ?? null) ? $tag->meta_json : [];
        if (!is_array($meta)) {
            $meta = [];
        }

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

        $title = trim(strip_tags($renderShortcodeText($titleRaw)));
        if ($title === '') {
            $title = 'Tag';
        }

        $subtitle = trim(strip_tags($renderShortcodeText($subtitleRaw)));

        $heroH1 = $title;

        try {
            /** @var \App\Cms\Core\Settings $settings */
            $settings = app(\App\Cms\Core\Settings::class);

            $defaultH1 = trim((string) $settings->get('default_title', '', 'plugins.tag-defaults'));

            if ($defaultH1 !== '') {
                $heroH1 = trim(strip_tags($renderShortcodeText($defaultH1)));
                if ($heroH1 === '') {
                    $heroH1 = $title;
                }
            }
        } catch (\Throwable $e) {
            $heroH1 = $title;
        }

        $categoryName = '';
        try {
            $termId = (int) ($tag->media_category_term_id ?? 0);
            if ($termId > 0) {
                $term = \App\Models\Term::query()->find($termId);
                if ($term && !empty($term->name)) {
                    $categoryName = trim(strip_tags((string) $term->name));
                }
            }
        } catch (\Throwable $e) {
            $categoryName = '';
        }

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

        $seoTitleSource = (string) ($seo['title'] ?? ($tagSeo['title'] ?? $titleRaw));
        $seoTitle = $renderShortcodeText($seoTitleSource);
        if ($seoTitle === '') {
            $seoTitle = $title;
        }

        $seoDescSource = (string) ($seo['description'] ?? ($tagSeo['description'] ?? $fallbackDescText));
        $seoDesc = $renderShortcodeText($seoDescSource);

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

        $allMedia = collect($mediaItems ?? [])
            ->filter(fn($m) => $m instanceof \App\Models\Media)
            ->values();

        $imageMedia = $allMedia->filter(fn($m) => method_exists($m, 'isImage') ? $m->isImage() : false)->values();

        $heroMedia = $imageMedia->shuffle()->first();
        $heroId = $heroMedia ? (int) $heroMedia->id : 0;

        $pool = $allMedia->reject(fn($m) => (int) $m->id === $heroId)->values();

        $related = $pool->shuffle()->take(12)->values();
        $relatedIds = $related->pluck('id')->map(fn($v) => (int) $v)->all();

        $relatedLinks = $pool
            ->reject(fn($m) => in_array((int) $m->id, $relatedIds, true))
            ->shuffle()
            ->take(10)
            ->values();

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

        if (($seo['og']['image'] ?? '') === '' && $heroMedia) {
            try {
                if (method_exists($heroMedia, 'variantUrl')) {
                    $seo['og']['image'] = (string) ($heroMedia->variantUrl('large') ?: $heroMedia->url());
                } elseif (method_exists($heroMedia, 'url')) {
                    $seo['og']['image'] = (string) $heroMedia->url();
                }
            } catch (\Throwable $e) {
            }
        }

        $heroTextHtml = '';
        if (trim($contentHtmlRaw) !== '') {
            $heroTextHtml = $renderShortcodeHtml((string) $contentHtmlRaw);
        } elseif (!$htmlIsEmpty($subDescHtmlRaw)) {
            $heroTextHtml = $renderShortcodeHtml((string) $subDescHtmlRaw);
        }

        $heroTextHtml = $removeScriptStyleBlocks((string) $heroTextHtml);
        $heroTextHtml = strip_tags($heroTextHtml, $allowedHtml);

        $bottomTitleRaw = (string) data_get($meta, 'subtitle', '');
        $bottomTitle = trim(strip_tags($renderShortcodeText($bottomTitleRaw)));
        if ($bottomTitle === '') {
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

        $tagImage = '';
        $heroPreloadHref = '';
        try {
            if ($heroMedia && method_exists($heroMedia, 'variantUrl')) {
                $tagImage = (string) ($heroMedia->variantUrl('medium') ?: $heroMedia->url());
                $heroPreloadHref =
                    (string) ($heroMedia->variantUrl('hero_sm') ?:
                    $heroMedia->variantUrl('medium') ?:
                    $heroMedia->url());
            } elseif ($heroMedia && method_exists($heroMedia, 'url')) {
                $tagImage = (string) $heroMedia->url();
                $heroPreloadHref = (string) $heroMedia->url();
            }
        } catch (\Throwable $e) {
            $tagImage = '';
            $heroPreloadHref = '';
        }

        $safeTagUrl = trim((string) $tagUrl);
        $safeTagImage = trim((string) $tagImage);
        $heroPreloadHref = trim((string) $heroPreloadHref);
        $tagButtonAriaLabel = trim($quoteButtonLabel . ' for ' . $title);
    @endphp

    @push('head')
        @if ($heroPreloadHref !== '')
            <link rel="preload" as="image" href="{{ $heroPreloadHref }}" imagesizes="(max-width: 1024px) 100vw, 420px"
                fetchpriority="high">
        @endif
    @endpush

    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="breadcrumb-text" aria-label="Breadcrumb">
            <a class="underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                href="{{ url('/') }}">
                Home
            </a>

            @if (trim($categoryName) !== '')
                <span class="mx-2 text-slate-300">/</span>
                <span>{{ $categoryName }}</span>
            @endif

            <span class="mx-2 text-slate-300">/</span>
            <span>{{ $title }}</span>
        </nav>
    </div>

    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">
                <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                        <div class="hero-media-wrap mx-auto w-full max-w-[420px]">
                            {!! cms_picture(
                                $heroMedia,
                                [
                                    'alt' => $title,
                                    'class' => 'hero-media-image w-full object-contain',
                                    'sizes' => '(max-width: 1024px) 100vw, 420px',
                                    'loading' => 'eager',
                                    'decoding' => 'async',
                                    'fetchpriority' => 'high',
                                ],
                                'hero_sm',
                                ['hero_sm', 'medium', 'medium_large'],
                            ) !!}
                        </div>
                    @else
                        <div class="h-80 w-full" aria-hidden="true"></div>
                    @endif
                </div>

                <div class="order-2 min-w-0 lg:order-1 lg:col-span-6">
                    <div class="h-1 w-20 bg-red-500"></div>

                    <div class="page-slogan mt-4">
                        {{ $sloganTag }}
                    </div>

                    <h1 class="page-hero-title mt-3 break-words">
                        {{ $heroH1 }}
                    </h1>

                    @if (trim($heroTextHtml) !== '')
                        <div class="page-hero-content mt-4 space-y-4">
                            {!! $heroTextHtml !!}
                        </div>
                    @endif

                    <button type="button"
                        class="cf-get-price mt-8 inline-flex items-center justify-center rounded bg-[var(--cms-primary)] px-6 py-3 text-center text-sm font-semibold text-white hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                        aria-label="{{ $tagButtonAriaLabel }}" data-default-label="{{ $quoteButtonLabel }}"
                        data-item-id="{{ (int) $tag->id }}" data-item-type="tag" data-item-title="{{ $title }}"
                        data-item-url="{{ $safeTagUrl }}" data-item-image="{{ $safeTagImage }}">
                        {!! $quoteButtonHtml !!}
                    </button>
                </div>
            </div>
        </div>
    </section>

    @if ($related->count() || $relatedLinks->count() || trim($bottomTitle) !== '' || trim($bottomDescHtml) !== '')
        <section class="bg-white">
            <div class="page-container mx-auto px-4 py-10">
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

                                $rawRTitle =
                                    (string) ($r->title ?:
                                    ($rMetaTitle !== ''
                                        ? $rMetaTitle
                                        : $r->original_filename ?? ''));
                                $rTitle = trim(strip_tags($rawRTitle));
                                $rTitle = $rTitle !== '' ? $rTitle : 'Attachment';

                                $rUrl = filled($r->slug)
                                    ? (function_exists('cms_slug_url')
                                        ? cms_slug_url((string) $r->slug)
                                        : url('/' . trim((string) $r->slug, '/') . '/'))
                                    : $r->url();
                                $safeRUrl = trim((string) $rUrl);

                                $rImage = '';
                                try {
                                    if (method_exists($r, 'variantUrl')) {
                                        $rImage = (string) ($r->variantUrl('medium') ?: $r->url());
                                    } elseif (method_exists($r, 'url')) {
                                        $rImage = (string) $r->url();
                                    }
                                } catch (\Throwable $e) {
                                    $rImage = '';
                                }
                                $safeRImage = trim((string) $rImage);

                                $rButtonAriaLabel = trim($quoteButtonLabel . ' for ' . $rTitle);
                            @endphp

                            <div class="group text-center">
                                <a href="{{ $safeRUrl }}"
                                    class="block rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                                    aria-label="{{ $rTitle }}">
                                    <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden">
                                        {!! cms_picture(
                                            $r,
                                            [
                                                'alt' => $rTitle,
                                                'class' => 'h-full w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]',
                                                'sizes' => '(max-width: 768px) 50vw, 220px',
                                                'loading' => 'lazy',
                                                'decoding' => 'async',
                                            ],
                                            'medium',
                                            ['thumb', 'medium', 'medium_large'],
                                        ) !!}
                                    </div>

                                    <div class="related-card-text mx-auto mt-4 w-full max-w-[220px]">
                                        <div
                                            class="text-sm font-medium leading-snug underline underline-offset-4 decoration-[1.5px] group-hover:no-underline">
                                            {{ trim(strip_tags($productStylePrefix . (int) $r->id)) }}
                                        </div>

                                        <p
                                            class="mt-1 text-sm font-semibold leading-snug line-clamp-2 underline underline-offset-4 decoration-[1.5px] group-hover:no-underline">
                                            {{ $rTitle }}
                                        </p>
                                    </div>
                                </a>

                                <button type="button"
                                    class="cf-get-price mt-3 inline-flex items-center justify-center text-center text-sm font-semibold text-[var(--cms-primary)] underline underline-offset-4 hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                                    aria-label="{{ $rButtonAriaLabel }}" data-default-label="{{ $quoteButtonLabel }}"
                                    data-item-id="{{ (int) $r->id }}" data-item-type="media"
                                    data-item-title="{{ $rTitle }}" data-item-url="{{ $safeRUrl }}"
                                    data-item-image="{{ $safeRImage }}">
                                    {!! $quoteButtonHtml !!}
                                </button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-14 grid gap-10 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <h2 class="section-title">
                            {{ $bottomTitle !== '' ? $bottomTitle : $title }}
                        </h2>

                        @if (trim($bottomDescHtml) !== '')
                            <div class="section-body mt-4">
                                {!! $bottomDescHtml !!}
                            </div>
                        @endif
                    </div>

                    <div class="hidden lg:block lg:col-span-4">
                        <div class="rounded bg-slate-100 p-6">
                            <div class="related-links-title">Related Links :</div>

                            @if ($relatedLinks->count())
                                <ul class="mt-4 space-y-3">
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

                                            $rawQTitle =
                                                (string) ($q->title ?:
                                                ($qMetaTitle !== ''
                                                    ? $qMetaTitle
                                                    : $q->original_filename ?? ''));
                                            $qTitle = trim(strip_tags($rawQTitle));
                                            $qTitle = $qTitle !== '' ? $qTitle : 'Attachment';

                                            $qUrl = filled($q->slug)
                                                ? (function_exists('cms_slug_url')
                                                    ? cms_slug_url((string) $q->slug)
                                                    : url('/' . trim((string) $q->slug, '/') . '/'))
                                                : $q->url();
                                            $safeQUrl = trim((string) $qUrl);
                                        @endphp

                                        <li
                                            class="flex items-start gap-2 border-b border-slate-200 pb-3 last:border-b-0 last:pb-0">
                                            <span class="mt-[2px] text-slate-500">›</span>
                                            <a href="{{ $safeQUrl }}" target="_blank" rel="noopener noreferrer"
                                                class="related-link-item block truncate underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                                                title="{{ $qTitle }}">
                                                {{ $qTitle }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="mt-3 empty-related-links">
                                    No related links found.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    <style>
        .breadcrumb-text {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .page-slogan,
        .page-hero-content,
        .page-hero-content p,
        .page-hero-content li,
        .related-card-text,
        .related-card-text p,
        .section-body,
        .section-body p,
        .section-body li,
        .related-link-item,
        .empty-related-links {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .page-hero-title,
        .section-title,
        {
        font-family: var(--cms-heading-font-family);
        font-size: var(--cms-h1-font-size);
        font-weight: var(--cms-heading-font-weight);
        text-transform: var(--cms-heading-text-transform);
        line-height: var(--cms-heading-line-height);
        letter-spacing: var(--cms-heading-letter-spacing);
        color: var(--cms-primary);
        }

        .hero-media-wrap {
            width: 100%;
        }

        .hero-media-image {
            display: block;
            width: 100%;
            height: auto;
            margin-left: auto;
            margin-right: auto;
        }
    </style>
@endsection

{{-- plugins/siatex-tags/views/show.blade.php --}}
@extends('layouts.app')

@section('content')
    @once
        @push('head')
            <link rel="preload" href="{{ asset('_contact/cart.css') }}" as="style" onload="this.onload=null;this.rel='stylesheet'">
            <noscript>
                <link rel="stylesheet" href="{{ asset('_contact/cart.css') }}">
            </noscript>
        @endpush

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
                // ignore
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
                // ignore
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
        try {
            if ($heroMedia && method_exists($heroMedia, 'variantUrl')) {
                $tagImage = (string) ($heroMedia->variantUrl('medium') ?: $heroMedia->url());
            } elseif ($heroMedia && method_exists($heroMedia, 'url')) {
                $tagImage = (string) $heroMedia->url();
            }
        } catch (\Throwable $e) {
            $tagImage = '';
        }

        $safeTagUrl = trim((string) $tagUrl);
        $safeTagImage = trim((string) $tagImage);
        $tagButtonAriaLabel = trim($quoteButtonLabel . ' for ' . $title);
    @endphp

    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>

            @if (trim($categoryName) !== '')
                <span class="mx-2 text-slate-300">/</span>
                <span class="text-slate-600">{{ $categoryName }}</span>
            @endif

            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">

                <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                        {!! cms_picture(
                            $heroMedia,
                            [
                                'alt' => $title,
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
                        <div class="h-80 w-full bg-slate-100" aria-hidden="true"></div>
                    @endif
                </div>

                <div class="order-2 min-w-0 lg:order-1 lg:col-span-6">
                    <div class="h-1 w-20 bg-red-500"></div>

                    <div class="mt-4 text-sm font-semibold text-slate-700">
                        {{ $sloganTag }}
                    </div>

                    <h1 class="mt-3 break-words text-4xl font-extrabold leading-tight tracking-tight text-[#1f5f99]">
                        {{ $heroH1 }}
                    </h1>

                    @if (trim($heroTextHtml) !== '')
                        <div class="mt-4 space-y-4 text-justify text-sm leading-7 text-slate-700">
                            {!! $heroTextHtml !!}
                        </div>
                    @endif

                    <button type="button"
                        class="cf-get-price mt-8 inline-flex items-center justify-center rounded bg-[#1f5f99] px-6 py-3 text-center text-sm font-semibold text-white hover:bg-[#194f7f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
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
                                <a href="{{ $safeRUrl }}" class="block">
                                    <div class="mx-auto aspect-square w-full max-w-[220px] overflow-hidden bg-white">
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

                                    <div class="mx-auto mt-4 w-full max-w-[220px] text-slate-700">
                                        <div class="text-sm font-medium leading-snug text-slate-600">
                                            {{ trim(strip_tags($productStylePrefix . (int) $r->id)) }}
                                        </div>

                                        <p class="mt-1 text-sm font-semibold leading-snug line-clamp-2">
                                            {{ $rTitle }}
                                        </p>
                                    </div>
                                </a>

                                <button type="button"
                                    class="cf-get-price mt-3 inline-flex items-center justify-center text-center text-sm font-semibold text-[#1f5f99] underline underline-offset-4 hover:text-[#194f7f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
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
                        <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                            {{ $bottomTitle !== '' ? $bottomTitle : $title }}
                        </h2>

                        @if (trim($bottomDescHtml) !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $bottomDescHtml !!}
                            </div>
                        @endif
                    </div>

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

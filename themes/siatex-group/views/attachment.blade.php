{{-- themes/siatex-group/views/attachment.blade.php --}}
@extends('layouts.app')

@section('content')
    @once



    @endonce

    @php
        /** @var \App\Models\Media $media */

        $adminEditUrl =
            $adminEditUrl ??
            (class_exists(\App\Filament\Resources\MediaResource::class)
                ? \App\Filament\Resources\MediaResource::getUrl('edit', ['record' => $media])
                : url('/lara-admin'));

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

        $meta = $media->meta ?? [];
        if (is_string($meta) && trim($meta) !== '') {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) {
            $meta = [];
        }

        $settings = app(\App\Cms\Core\SettingsRepository::class);

        $sloganTag = trim((string) $settings->get('core', 'slogan_tag', 'Your Tech-pack, Our production'));
        if ($sloganTag === '') {
            $sloganTag = 'Your Tech-pack, Our production';
        }

        $productStylePrefix = trim((string) $settings->get('core', 'product_style_prefix', 'Art:SC'));
        if ($productStylePrefix === '') {
            $productStylePrefix = 'Art:SC';
        }

        $quoteButtonText = trim((string) $settings->get('core', 'quote_button_text', 'Custom Quote'));
        if ($quoteButtonText === '') {
            $quoteButtonText = 'Custom Quote';
        }

        $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
        if ($quoteButtonLabel === '') {
            $quoteButtonLabel = 'Custom Quote';
        }
        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonLabel)));

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

        $textValue = function ($value): string {
            if ($value === null) {
                return '';
            }
            if (is_string($value)) {
                return $value;
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

        $normalizeToHtml = function (string $value): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }

            if ($value !== strip_tags($value)) {
                return $value;
            }

            return '<p>' . nl2br(e($value)) . '</p>';
        };

        if (function_exists('do_action')) {
            if (request()->query('md_preview') === '1') {
                $previewState = session('media_defaults_preview_state', null);

                if (is_array($previewState)) {
                    $d = $previewState['data'] ?? null;

                    if (is_array($d)) {
                        if (!filled($media->title ?? null) && filled($d['default_title'] ?? null)) {
                            $media->title = (string) $d['default_title'];
                        }

                        if ($htmlIsEmpty($media->description ?? null) && filled($d['default_description'] ?? null)) {
                            $media->description = $normalizeToHtml((string) $d['default_description']);
                        }

                        $m = $media->meta ?? [];
                        if (is_string($m) && trim($m) !== '') {
                            $decoded = json_decode($m, true);
                            $m = is_array($decoded) ? $decoded : [];
                        }
                        if (!is_array($m)) {
                            $m = [];
                        }

                        if (
                            !filled(data_get($m, 'frontend.meta_title', '')) &&
                            filled($d['default_sub_title'] ?? null)
                        ) {
                            data_set($m, 'frontend.meta_title', (string) $d['default_sub_title']);
                        }

                        $curSub = data_get($m, 'frontend.meta_description', null);
                        if ($htmlIsEmpty($curSub) && filled($d['default_sub_description'] ?? null)) {
                            data_set(
                                $m,
                                'frontend.meta_description',
                                $normalizeToHtml((string) $d['default_sub_description']),
                            );
                        }

                        $media->meta = $m;
                    }
                }
            }

            do_action('media.attachment.defaults.persist', $media);

            $meta = $media->meta ?? [];
            if (is_string($meta) && trim($meta) !== '') {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($meta)) {
                $meta = [];
            }
        }

        $metaTitleRaw = (string) data_get($meta, 'frontend.meta_title', '');
        if (function_exists('do_shortcode')) {
            try {
                $metaTitleRaw = do_shortcode($metaTitleRaw, ['media' => $media]);
            } catch (\Throwable $e) {
            }
        }
        $metaTitleRaw = $removeScriptStyleBlocks((string) $metaTitleRaw);
        $metaTitle = trim(strip_tags($metaTitleRaw));

        $metaDescRaw = (string) data_get($meta, 'frontend.meta_description', '');
        if (function_exists('do_shortcode')) {
            try {
                $metaDescRaw = do_shortcode($metaDescRaw, ['media' => $media]);
            } catch (\Throwable $e) {
            }
        }
        $metaDescRaw = $removeScriptStyleBlocks((string) $metaDescRaw);
        $metaDescHtml = strip_tags($metaDescRaw, $allowedHtml);

        $rawTitle = (string) ($media->title ?: $media->original_filename ?? '');
        $title = trim(strip_tags($rawTitle));
        $title = $title !== '' ? $title : 'Attachment';

        $heroDescRaw = (string) ($media->description ?? '');
        if (function_exists('do_shortcode')) {
            try {
                $heroDescRaw = do_shortcode($heroDescRaw, ['media' => $media]);
            } catch (\Throwable $e) {
            }
        }
        $heroDescRaw = $removeScriptStyleBlocks((string) $heroDescRaw);
        $heroDescHtml = strip_tags($heroDescRaw, $allowedHtml);

        $heroCaption = trim(strip_tags($textValue($media->caption ?? '')));

        $heroHtml = '';
        if ($heroDescHtml !== '') {
            $heroHtml = $heroDescHtml;
        } elseif ($heroCaption !== '') {
            $heroHtml = nl2br(e($heroCaption));
        } elseif ($metaDescHtml !== '') {
            $heroHtml = $metaDescHtml;
        }

        $productUrl = filled($media->slug) ? cms_slug_url((string) $media->slug) : url()->current();
        $productImage = '';
        $heroPreloadHref = '';

        try {
            if (method_exists($media, 'variantUrl')) {
                $productImage =
                    (string) ($media->variantUrl('hero_sm') ?: $media->variantUrl('large') ?: $media->url());
                $heroPreloadHref =
                    (string) ($media->variantUrl('hero_sm') ?: $media->variantUrl('large') ?: $media->url());
            } elseif (method_exists($media, 'url')) {
                $productImage = (string) $media->url();
                $heroPreloadHref = (string) $media->url();
            }
        } catch (\Throwable $e) {
            $productImage = '';
            $heroPreloadHref = '';
        }

        $safeProductUrl = trim((string) $productUrl);
        $safeProductImage = trim((string) $productImage);
        $heroPreloadHref = trim((string) $heroPreloadHref);
        $buttonAriaLabel = trim($quoteButtonLabel . ' for ' . $title);

        $mediaCategoryTerm = null;
        $mediaCategoryTaxId = null;
        $mediaCategoryIds = [];

        $parentPost = null;
        $postCategory = null;

        try {
            $mediaCategoryTaxId = \App\Models\Taxonomy::query()->where('key', 'media_category')->value('id');

            if ($mediaCategoryTaxId) {
                $mediaCategoryTerm = $media
                    ->terms()
                    ->where('terms.taxonomy_id', $mediaCategoryTaxId)
                    ->where('terms.visibility', 'public')
                    ->orderBy('terms.name')
                    ->first();

                $mediaCategoryIds = $media
                    ->terms()
                    ->where('terms.taxonomy_id', $mediaCategoryTaxId)
                    ->where('terms.visibility', 'public')
                    ->pluck('terms.id')
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
            }
        } catch (\Throwable $e) {
            $mediaCategoryTaxId = null;
            $mediaCategoryTerm = null;
            $mediaCategoryIds = [];
        }

        try {
            $parentPost = $media->posts()->where('status', 'published')->latest('id')->first();
            if ($parentPost) {
                $postCategory = $parentPost->categories()->first();
            }
        } catch (\Throwable $e) {
            $parentPost = null;
            $postCategory = null;
        }

        $breadcrumbTerm = $mediaCategoryTerm ?: $postCategory;
        $breadcrumbTermUrl = null;
        if ($breadcrumbTerm && filled($breadcrumbTerm->slug ?? null) && function_exists('cms_slug_url')) {
            $breadcrumbTermUrl = cms_slug_url((string) $breadcrumbTerm->slug);
        }

        $related = collect();
        $relatedLinks = collect();

        $fetchSameCategoryRandom = function (array $excludeIds, int $limit) use (
            $mediaCategoryTaxId,
            $mediaCategoryIds,
        ) {
            if (!$mediaCategoryTaxId || empty($mediaCategoryIds)) {
                return collect();
            }

            try {
                return \App\Models\Media::query()
                    ->where('attachment_public', true)
                    ->whereNotIn('id', $excludeIds)
                    ->whereDoesntHave('terms', function ($t) use ($mediaCategoryTaxId) {
                        $t->where('terms.taxonomy_id', $mediaCategoryTaxId)->where('terms.visibility', 'private');
                    })
                    ->whereHas('terms', function ($q) use ($mediaCategoryTaxId, $mediaCategoryIds) {
                        $q->where('terms.taxonomy_id', $mediaCategoryTaxId)
                            ->whereIn('terms.id', $mediaCategoryIds)
                            ->where('terms.visibility', 'public');
                    })
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get()
                    ->filter(fn($m) => $m instanceof \App\Models\Media && $m->isImage())
                    ->unique('id')
                    ->values();
            } catch (\Throwable $e) {
                return collect();
            }
        };

        if ($mediaCategoryTaxId && !empty($mediaCategoryIds)) {
            $related = $fetchSameCategoryRandom([$media->id], 80)
                ->take(12)
                ->values();

            $excludeForLinks = collect([$media->id])
                ->merge($related->pluck('id'))
                ->unique()
                ->values()
                ->all();

            $relatedLinks = $fetchSameCategoryRandom($excludeForLinks, 80)->take(10)->values();

            $need = 10 - $relatedLinks->count();
            if ($need > 0) {
                $more = $fetchSameCategoryRandom([$media->id], 120)
                    ->reject(fn($m) => $relatedLinks->contains('id', $m->id))
                    ->take($need)
                    ->values();

                $relatedLinks = $relatedLinks->concat($more)->take(10)->values();
            }
        }

        $customJsonRaw = data_get($meta, 'custom_json', null);
        if ($customJsonRaw === null || $customJsonRaw === '') {
            $customJsonRaw = data_get($meta, 'frontend.custom_json', null);
        }

        $customJson = null;

        if (is_array($customJsonRaw)) {
            $customJson = $customJsonRaw;
        } elseif (is_string($customJsonRaw)) {
            $str = trim($customJsonRaw);

            if ($str !== '') {
                $openTag = '<' . 'script';
                $closeTag = '</' . 'script' . '>';

                $openPos = stripos($str, $openTag);

                if ($openPos !== false) {
                    $gtPos = strpos($str, '>', $openPos);
                    if ($gtPos !== false) {
                        $endPos = stripos($str, $closeTag, $gtPos + 1);
                        if ($endPos !== false) {
                            $str = substr($str, $gtPos + 1, $endPos - ($gtPos + 1));
                            $str = trim((string) $str);
                        }
                    }
                }

                $decoded = json_decode($str, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $customJson = $decoded;
                }
            }
        }

        $isJsonLd = is_array($customJson) && (isset($customJson['@context']) || isset($customJson['@type']));
        $printJsonLdHere = false;
    @endphp

    @push('head')
        @if ($heroPreloadHref !== '')
            <link rel="preload" as="image" href="{{ $heroPreloadHref }}"
                imagesizes="(max-width: 575px) 275px, (max-width: 767px) 370px, (max-width: 991px) 575px, 1000px"
                fetchpriority="high">
        @endif
    @endpush

    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500" aria-label="Breadcrumb">
            <a class=" underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                href="{{ url('/') }}">
                Home
            </a>

            @if ($breadcrumbTerm)
                <span class="mx-2 text-slate-300">/</span>
                @if ($breadcrumbTermUrl)
                    <a class="underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                        href="{{ $breadcrumbTermUrl }}">
                        {{ $breadcrumbTerm->name }}
                    </a>
                @else
                    <span class="text-slate-600">{{ $breadcrumbTerm->name }}</span>
                @endif
            @endif

            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    <section class="mt-6">
        <div class="cms-container mx-auto px-4 py-10">
            <div class="grid gap-12 bg-slate-50 p-6 md:p-10 lg:grid-cols-12 lg:items-start">
                <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                    @if ($media->isImage())
                        {!! cms_picture(
                            $media,
                            [
                                'alt' => $title,
                                'class' => 'w-full object-contain',
                                'sizes' => '(max-width: 575px) 275px, (max-width: 767px) 370px, (max-width: 991px) 575px, 1000px',
                                'loading' => 'eager',
                                'decoding' => 'async',
                                'fetchpriority' => 'high',
                            ],
                            'hero_sm',
                            ['thumb', 'small', 'hero_sm', 'large'],
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
                        {{ $title }}
                    </h1>

                    @if ($heroHtml !== '')
                        <div class="mt-4 space-y-4 text-justify text-sm leading-7 text-slate-700">
                            {!! $heroHtml !!}
                        </div>
                    @endif

                    <button type="button"
                        class="cf-get-price mt-8 inline-flex items-center justify-center rounded bg-[#1f5f99] px-6 py-3 text-center text-sm font-semibold text-white hover:bg-[#194f7f] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                        aria-label="{{ $buttonAriaLabel }}" data-default-label="{{ $quoteButtonLabel }}"
                        data-item-id="{{ (int) $media->id }}" data-item-type="media" data-item-title="{{ $title }}"
                        data-item-url="{{ $safeProductUrl }}" data-item-image="{{ $safeProductImage }}">
                        {!! $quoteButtonHtml !!}
                    </button>
                </div>
            </div>
        </div>
    </section>

    @if ($related->count() || $relatedLinks->count() || $metaTitle !== '' || $metaDescHtml !== '')
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

                                $rUrl = filled($r->slug) ? cms_slug_url((string) $r->slug) : $r->url();
                                $safeRUrl = trim((string) $rUrl);
                            @endphp

                            <div class="group text-center">
                                <a href="{{ $safeRUrl }}"
                                    class="block rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                                    aria-label="{{ $rTitle }}">
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
                                            'small',
                                            ['thumb', 'small', 'hero_sm'],
                                        ) !!}
                                    </div>

                                    <div class="mx-auto mt-4 w-full max-w-[220px] text-slate-700">
                                        <div
                                            class="text-sm font-medium leading-snug text-slate-600 underline underline-offset-4 decoration-[1.5px] group-hover:no-underline">
                                            {{ trim(strip_tags($productStylePrefix . (int) $r->id)) }}
                                        </div>

                                        <p
                                            class="mt-1 text-sm font-semibold leading-snug line-clamp-2 underline underline-offset-4 decoration-[1.5px] group-hover:no-underline">
                                            {{ $rTitle }}
                                        </p>
                                    </div>
                                </a>

                                @php
                                    $rImage = '';
                                    try {
                                        if (method_exists($r, 'variantUrl')) {
                                            $rImage =
                                                (string) ($r->variantUrl('small') ?:
                                                $r->variantUrl('thumb') ?:
                                                $r->url());
                                        } elseif (method_exists($r, 'url')) {
                                            $rImage = (string) $r->url();
                                        }
                                    } catch (\Throwable $e) {
                                        $rImage = '';
                                    }

                                    $safeRImage = trim((string) $rImage);
                                    $rButtonAriaLabel = trim($quoteButtonLabel . ' for ' . $rTitle);
                                @endphp

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
                            {{ $metaTitle !== '' ? $metaTitle : $title }}
                        </h2>

                        @if ($metaDescHtml !== '')
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-7 text-justify">
                                {!! $metaDescHtml !!}
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

                                            $qUrl = filled($q->slug) ? cms_slug_url((string) $q->slug) : $q->url();
                                            $safeQUrl = trim((string) $qUrl);
                                        @endphp

                                        <li
                                            class="flex items-start gap-2 border-b border-slate-200 pb-3 last:border-b-0 last:pb-0">
                                            <span class="mt-[2px] text-slate-500">›</span>
                                            <a href="{{ $safeQUrl }}"
                                                class="block truncate italic text-slate-700 underline underline-offset-4 decoration-[1.5px] hover:text-slate-900 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
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

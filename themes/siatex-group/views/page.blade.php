@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $settings = app(\App\Cms\Core\SettingsRepository::class);
        $hooks = app(\App\Cms\Hooks\Hooks::class);

        if (!isset($adminEditUrl)) {
            $panelId = 'admin';

            if (($post->type ?? 'post') === 'page' && class_exists(\App\Filament\Resources\Pages\PageResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Pages\PageResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } elseif (class_exists(\App\Filament\Resources\Posts\PostResource::class)) {
                $adminEditUrl = \App\Filament\Resources\Posts\PostResource::getUrl(
                    'edit',
                    ['record' => $post],
                    panel: $panelId,
                );
            } else {
                $adminEditUrl = url('/lara-admin');
            }
        }

        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $isHomepage = $homepageId !== null && $post && (int) $post->getKey() === $homepageId;

        $sloganTag = trim((string) $settings->get('core', 'slogan_tag', ''));
        $quoteButtonText = trim((string) $settings->get('core', 'quote_button_text', 'Custom Quote'));
        if ($quoteButtonText === '') {
            $quoteButtonText = 'Custom Quote';
        }

        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonText)));
        $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
        if ($quoteButtonLabel === '') {
            $quoteButtonLabel = 'Custom Quote';
        }

        $heroTitle = trim(strip_tags((string) data_get($post->meta_json ?? [], 'slider.title', '')));
        $heroRaw = (string) ($post->content_html ?? data_get($post->content_json ?? [], 'html', ''));
        $productRaw = (string) data_get($post->meta_json ?? [], 'product', '');
        $promoRaw = (string) data_get($post->meta_json ?? [], 'sub_description', '');

        $renderEditorContent = function (?string $rawContent) use ($hooks, $post): string {
            $rawContent = (string) $rawContent;

            if (trim($rawContent) === '') {
                return '';
            }

            $filtered = $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, $rawContent, [
                'post' => $post,
            ]);

            try {
                return function_exists('do_shortcode') ? do_shortcode($filtered, ['post' => $post]) : $filtered;
            } catch (\Throwable $e) {
                return $filtered;
            }
        };

        $heroHtml = $renderEditorContent($heroRaw);
        $productHtml = $renderEditorContent($productRaw);
        $promoHtml = $renderEditorContent($promoRaw);

        $hasPostsShortcode = str_contains($heroRaw, '[posts') || str_contains($heroHtml, 'cms-post');

        $featuredMediaItems = collect();

        try {
            if (method_exists($post, 'featuredMediaPivot')) {
                $featuredMediaItems = $post->featuredMediaPivot()->get();
            }

            if ($featuredMediaItems->isEmpty() && method_exists($post, 'featuredMedia') && $post->featuredMedia) {
                $featuredMediaItems =
                    $post->featuredMedia instanceof \Illuminate\Support\Collection
                        ? $post->featuredMedia
                        : collect([$post->featuredMedia]);
            }
        } catch (\Throwable $e) {
            $featuredMediaItems = collect();
        }

        $featuredMediaItems = $featuredMediaItems
            ->filter(fn($m) => $m instanceof \App\Models\Media)
            ->unique(fn($m) => $m->id ?? spl_object_hash($m))
            ->values();

        if ($featuredMediaItems->isNotEmpty()) {
            $featuredMediaItems->loadMissing('variantRecords');
        }

        $hasHeroMedia = $featuredMediaItems->isNotEmpty();
        $hasHeroText = $heroTitle !== '' || trim(strip_tags($heroHtml)) !== '';
        $hasProductContent = trim(strip_tags($productHtml)) !== '';
        $hasPromoContent = trim(strip_tags($promoHtml)) !== '';

        $showCustomHero = $hasHeroText || $hasHeroMedia;

        $pageUrl =
            filled($post->slug ?? null) && function_exists('cms_slug_url')
                ? cms_slug_url((string) $post->slug)
                : url()->current();
        $pageUrl = trim((string) $pageUrl);

        $productImage = '';
        try {
            $heroMediaForButton = $featuredMediaItems->first();
            if ($heroMediaForButton instanceof \App\Models\Media) {
                $productImage =
                    (string) ($heroMediaForButton->variantUrl('hero_sm') ?:
                    $heroMediaForButton->variantUrl('small') ?:
                    $heroMediaForButton->url());
            }
        } catch (\Throwable $e) {
            $productImage = '';
        }
        $productImage = trim((string) $productImage);

        $buttonItemTitle = trim(strip_tags($heroTitle !== '' ? $heroTitle : (string) ($post->title ?? 'Page')));
        if ($buttonItemTitle === '') {
            $buttonItemTitle = 'Page';
        }

        $relatedLinks = collect();

        if (!$isHomepage && class_exists(\App\Models\Media::class) && class_exists(\App\Models\Taxonomy::class)) {
            try {
                $mediaCategoryTaxId = \App\Models\Taxonomy::query()->where('key', 'media_category')->value('id');

                $publicCategoryIds = collect();

                if ($mediaCategoryTaxId && $featuredMediaItems->isNotEmpty()) {
                    $publicCategoryIds = $featuredMediaItems
                        ->flatMap(function ($media) use ($mediaCategoryTaxId) {
                            try {
                                if (method_exists($media, 'categories')) {
                                    return $media
                                        ->categories()
                                        ->where('terms.taxonomy_id', $mediaCategoryTaxId)
                                        ->where('terms.visibility', 'public')
                                        ->pluck('terms.id');
                                }
                            } catch (\Throwable $e) {
                            }

                            return [];
                        })
                        ->filter(fn($id) => is_numeric($id))
                        ->map(fn($id) => (int) $id)
                        ->unique()
                        ->values();
                }

                $excludeIds = $featuredMediaItems
                    ->pluck('id')
                    ->filter(fn($id) => is_numeric($id))
                    ->map(fn($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $query = \App\Models\Media::query()
                    ->where('attachment_public', true)
                    ->when(!empty($excludeIds), fn($q) => $q->whereNotIn('id', $excludeIds));

                if ($mediaCategoryTaxId) {
                    $query->whereDoesntHave('terms', function ($q) use ($mediaCategoryTaxId) {
                        $q->where('terms.taxonomy_id', $mediaCategoryTaxId)->where('terms.visibility', 'private');
                    });

                    if ($publicCategoryIds->isNotEmpty()) {
                        $query->whereHas('terms', function ($q) use ($mediaCategoryTaxId, $publicCategoryIds) {
                            $q->where('terms.taxonomy_id', $mediaCategoryTaxId)
                                ->whereIn('terms.id', $publicCategoryIds->all())
                                ->where('terms.visibility', 'public');
                        });
                    } else {
                        $query->whereHas('terms', function ($q) use ($mediaCategoryTaxId) {
                            $q->where('terms.taxonomy_id', $mediaCategoryTaxId)->where('terms.visibility', 'public');
                        });
                    }
                }

                $relatedLinks = $query
                    ->inRandomOrder()
                    ->limit(10)
                    ->get()
                    ->filter(
                        fn($media) => $media instanceof \App\Models\Media &&
                            method_exists($media, 'isImage') &&
                            $media->isImage(),
                    )
                    ->map(function ($media) {
                        if (function_exists('do_action')) {
                            do_action('media.attachment.defaults.persist', $media);
                        }

                        $meta = $media->meta ?? [];
                        if (is_string($meta) && trim($meta) !== '') {
                            $decoded = json_decode($meta, true);
                            $meta = is_array($decoded) ? $decoded : [];
                        }
                        if (!is_array($meta)) {
                            $meta = [];
                        }

                        $metaTitle = trim((string) data_get($meta, 'frontend.meta_title', ''));
                        $title = trim(
                            strip_tags(
                                (string) ($media->title ?:
                                ($metaTitle !== ''
                                    ? $metaTitle
                                    : $media->original_filename ?? '')),
                            ),
                        );
                        $title = $title !== '' ? $title : 'Attachment';

                        $url =
                            filled($media->slug ?? null) && function_exists('cms_slug_url')
                                ? cms_slug_url((string) $media->slug)
                                : null;

                        if (!$url) {
                            try {
                                $url = method_exists($media, 'url') ? $media->url() : null;
                            } catch (\Throwable $e) {
                                $url = null;
                            }
                        }

                        return [
                            'title' => trim((string) $title),
                            'url' => trim((string) $url),
                        ];
                    })
                    ->filter(fn($item) => !empty($item['title']) && !empty($item['url']))
                    ->take(10)
                    ->values();
            } catch (\Throwable $e) {
                $relatedLinks = collect();
            }
        }

        $currentTitle = trim(strip_tags((string) ($heroTitle !== '' ? $heroTitle : $post->title ?? 'Page')));
        if ($currentTitle === '') {
            $currentTitle = 'Page';
        }

        $breadcrumbParentTitle = null;
        $breadcrumbParentUrl = null;

        try {
            if (method_exists($post, 'terms')) {
                $preferredTaxonomyKeys = [
                    'media_category',
                    'category',
                    'post_category',
                    'product_category',
                    'page_category',
                ];

                $term = $post
                    ->terms()
                    ->with('taxonomy')
                    ->get()
                    ->sortBy(function ($term) use ($preferredTaxonomyKeys) {
                        $key = data_get($term, 'taxonomy.key');
                        $index = array_search($key, $preferredTaxonomyKeys, true);
                        return $index === false ? 999 : $index;
                    })
                    ->first();

                if ($term) {
                    $breadcrumbParentTitle = trim(strip_tags((string) ($term->name ?? ($term->title ?? ''))));
                    if (filled($term->slug ?? null) && function_exists('cms_slug_url')) {
                        $breadcrumbParentUrl = trim((string) cms_slug_url((string) $term->slug));
                    }
                }
            }
        } catch (\Throwable $e) {
            $breadcrumbParentTitle = null;
            $breadcrumbParentUrl = null;
        }

        if (!$breadcrumbParentTitle && $featuredMediaItems->isNotEmpty()) {
            try {
                $firstMedia = $featuredMediaItems->first();

                if ($firstMedia && method_exists($firstMedia, 'categories')) {
                    $firstCategory = $firstMedia->categories()->where('terms.visibility', 'public')->first();

                    if ($firstCategory) {
                        $breadcrumbParentTitle = trim(
                            strip_tags((string) ($firstCategory->name ?? ($firstCategory->title ?? ''))),
                        );
                        if (filled($firstCategory->slug ?? null) && function_exists('cms_slug_url')) {
                            $breadcrumbParentUrl = trim((string) cms_slug_url((string) $firstCategory->slug));
                        }
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        $heroPreloadMedia = $featuredMediaItems->first();
        $heroPreloadHref = null;

        if ($heroPreloadMedia && method_exists($heroPreloadMedia, 'isImage') && $heroPreloadMedia->isImage()) {
            try {
                if (method_exists($heroPreloadMedia, 'variantUrl')) {
                    $heroPreloadHref =
                        $heroPreloadMedia->variantUrl('hero_sm') ?:
                        $heroPreloadMedia->variantUrl('small') ?:
                        $heroPreloadMedia->variantUrl('large') ?:
                        $heroPreloadMedia->url();
                } elseif (method_exists($heroPreloadMedia, 'url')) {
                    $heroPreloadHref = $heroPreloadMedia->url();
                }
            } catch (\Throwable $e) {
                $heroPreloadHref = null;
            }
        }
        $heroPreloadHref = $heroPreloadHref ? trim((string) $heroPreloadHref) : null;

        $shouldLoadCartAssets = !$isHomepage && $showCustomHero;
        $sliderId = 'page-featured-slider-' . ($post->id ?? 'default');
    @endphp

    @if ($isHomepage)
        @push('head')
            <style>
                h1.home-hero-title {
                    font-family: 'Ropa Sans', sans-serif;
                    color: #666;
                    text-transform: uppercase;
                    line-height: 1;
                    letter-spacing: 0;
                    text-align: left;
                    max-width: 370px;
                    margin: 0 0 15px;
                    font-size: clamp(2rem, 5vw, 3rem);
                    font-weight: 400;
                    opacity: 1;
                    visibility: visible;
                    transform: none;
                    animation: none;
                    transition: none;
                    min-height: 2.2em;
                }

                .home-hero-content,
                .home-hero-content p,
                .home-hero-content li {
                    font-family: var(--cms-body-font-family);
                    font-size: clamp(1rem, 1.4vw, 1.25rem);
                    font-weight: 400;
                    line-height: 1.6;
                    letter-spacing: var(--cms-body-letter-spacing);
                    color: #444;
                    font-style: normal;
                }
            </style>
        @endpush
    @endif

    @if ($shouldLoadCartAssets)
        @once
            @push('head')
                <link rel="preload" href="{{ asset('_contact/cart.css') }}" as="style"
                    onload="this.onload=null;this.rel='stylesheet'">
                <noscript>
                    <link rel="stylesheet" href="{{ asset('_contact/cart.css') }}">
                </noscript>
            @endpush

            @push('scripts')
                <script src="{{ asset('_contact/cart.js') }}" defer></script>
            @endpush
        @endonce
    @endif

    @if ($showCustomHero)
        @push('head')
            @if ($heroPreloadHref)
                <link rel="preload" as="image" href="{{ $heroPreloadHref }}" imagesizes="(max-width: 1024px) 100vw, 50vw"
                    fetchpriority="high">
            @endif

            @if ($isHomepage)
                <link rel="preload" href="{{ asset('themes/siatex-group/public/fonts/ropa/ropasans-regular-webfont.woff2') }}"
                    as="font" type="font/woff2" crossorigin>

                <link rel="preload"
                    href="{{ asset('themes/siatex-group/public/fonts/poppins/poppins-regular-webfont.woff2') }}" as="font"
                    type="font/woff2" crossorigin>
            @endif
        @endpush
    @endif

    @if ($featuredMediaItems->count() > 1 && !$isHomepage)
        @push('head')
            <style>
                #{{ $sliderId }} {
                    --hero-arrow-gap: 24px;
                }

                #{{ $sliderId }} .page-featured-slider-track {
                    position: relative;
                    overflow: hidden;
                }

                #{{ $sliderId }} .page-featured-slide {
                    position: absolute;
                    inset: 0;
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity .25s ease;
                }

                #{{ $sliderId }} .page-featured-slide.is-active {
                    position: relative;
                    opacity: 1;
                    pointer-events: auto;
                }

                #{{ $sliderId }} .page-featured-nav {
                    position: absolute;
                    top: 50%;
                    z-index: 10;
                    display: inline-flex;
                    height: 48px;
                    width: 32px;
                    align-items: center;
                    justify-content: center;
                    padding: 0;
                    background: transparent;
                    color: #111;
                    transform: translateY(-50%);
                    transition: opacity .2s ease;
                }

                #{{ $sliderId }} .page-featured-nav:hover {
                    opacity: .7;
                }

                #{{ $sliderId }} .page-featured-prev {
                    left: calc(var(--hero-arrow-gap) * -1.5);
                }

                #{{ $sliderId }} .page-featured-next {
                    right: calc(var(--hero-arrow-gap) * -1.5);
                }

                @media (max-width: 1023.98px) {
                    #{{ $sliderId }} {
                        --hero-arrow-gap: 12px;
                    }
                }

                @media (max-width: 639.98px) {
                    #{{ $sliderId }} .page-featured-nav {
                        width: 28px;
                        height: 44px;
                    }

                    #{{ $sliderId }} .page-featured-prev {
                        left: -6px;
                    }

                    #{{ $sliderId }} .page-featured-next {
                        right: -6px;
                    }
                }
            </style>
        @endpush

        @push('scripts')
            <script>
                (function() {
                    const slider = document.getElementById(@json($sliderId));
                    if (!slider) return;

                    const slides = Array.from(slider.querySelectorAll('.page-featured-slide'));
                    const prevBtn = slider.querySelector('.page-featured-prev');
                    const nextBtn = slider.querySelector('.page-featured-next');

                    if (!slides.length) return;

                    let current = 0;

                    const showSlide = (index) => {
                        current = (index + slides.length) % slides.length;
                        slides.forEach((slide, i) => {
                            slide.classList.toggle('is-active', i === current);
                        });
                    };

                    prevBtn?.addEventListener('click', () => showSlide(current - 1));
                    nextBtn?.addEventListener('click', () => showSlide(current + 1));

                    showSlide(0);
                })();
            </script>
        @endpush
    @endif

    @if (!$isHomepage)
        <div class="cms-container mx-auto px-4 pt-6">
            <nav aria-label="Breadcrumb" class="text-sm text-slate-600">
                <ol class="flex flex-wrap items-center gap-1">
                    <li>
                        <a href="{{ url('/') }}"
                            class="underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                            Home
                        </a>
                    </li>

                    @if (!empty($breadcrumbParentTitle))
                        <li class="text-slate-400">/</li>
                        <li>
                            @if (!empty($breadcrumbParentUrl))
                                <a href="{{ $breadcrumbParentUrl }}"
                                    class="underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                                    {{ $breadcrumbParentTitle }}
                                </a>
                            @else
                                <span>{{ $breadcrumbParentTitle }}</span>
                            @endif
                        </li>
                    @endif

                    <li class="text-slate-400">/</li>
                    <li class="text-slate-800">
                        {{ $currentTitle }}
                    </li>
                </ol>
            </nav>
        </div>
    @endif

    @if ($showCustomHero)
        <section class="mt-5">
            <div class="cms-container mx-auto px-4">
                @if ($isHomepage)
                    <div class="home-hero-shell bg-slate-50">
                        <div class="home-hero-grid grid grid-cols-1 lg:grid-cols-12">
                            <div class="home-hero-content-col order-2 lg:order-1 lg:col-span-5">
                                <div class="home-hero-content-inner">
                                    @if ($heroTitle !== '')
                                        <h1 class="home-hero-title">
                                            {{ $heroTitle }}
                                        </h1>
                                    @endif

                                    @if (trim($heroHtml) !== '')
                                        <div class="cms-content mt-5 sm:mt-6 home-hero-content">
                                            {!! $heroHtml !!}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="home-hero-media-col order-1 lg:order-2 lg:col-span-7">
                                @if ($featuredMediaItems->count() === 1)
                                    @php
                                        $heroMedia = $featuredMediaItems->first();
                                    @endphp

                                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                        <div class="home-hero-media-wrap">
                                            {!! cms_picture(
                                                $heroMedia,
                                                [
                                                    'alt' => $buttonItemTitle,
                                                    'class' => 'home-hero-media-image',
                                                    'sizes' => '(max-width: 1023px) 100vw, 58vw',
                                                    'loading' => 'eager',
                                                    'fetchpriority' => 'high',
                                                    'decoding' => 'async',
                                                ],
                                                'hero_sm',
                                                ['thumb', 'small', 'hero_sm', 'large'],
                                            ) !!}
                                        </div>
                                    @endif
                                @elseif ($featuredMediaItems->count() > 1)
                                    @php
                                        $homeSliderId = 'home-hero-slider-' . ($post->id ?? 'default');
                                    @endphp

                                    <div id="{{ $homeSliderId }}" class="home-hero-slider home-hero-media-wrap relative"
                                        aria-roledescription="carousel">
                                        <style>
                                            #{{ $homeSliderId }} .home-hero-slider-track {
                                                position: relative;
                                                overflow: hidden;
                                                width: 100%;
                                                height: 100%;
                                                min-height: inherit;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slide {
                                                position: absolute;
                                                inset: 0;
                                                opacity: 0;
                                                pointer-events: none;
                                                transition: opacity .7s ease;
                                                height: 100%;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slide.is-active {
                                                position: relative;
                                                opacity: 1;
                                                pointer-events: auto;
                                                z-index: 2;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slide picture,
                                            #{{ $homeSliderId }} .home-hero-slide img {
                                                display: block;
                                                width: 100%;
                                                height: 100%;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slider-dots {
                                                position: absolute;
                                                left: 24px;
                                                right: 24px;
                                                bottom: 18px;
                                                z-index: 4;
                                                display: flex;
                                                align-items: center;
                                                justify-content: center;
                                                gap: 10px;
                                                pointer-events: none;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slider-dot {
                                                appearance: none;
                                                border: 0;
                                                padding: 0;
                                                margin: 0;
                                                width: 34px;
                                                height: 4px;
                                                background: rgba(255, 255, 255, .45);
                                                pointer-events: auto;
                                                cursor: pointer;
                                                transition: background-color .25s ease, opacity .25s ease;
                                            }

                                            #{{ $homeSliderId }} .home-hero-slider-dot.is-active {
                                                background: rgba(255, 255, 255, .95);
                                            }

                                            @media (max-width: 639.98px) {
                                                #{{ $homeSliderId }} .home-hero-slider-dots {
                                                    left: 16px;
                                                    right: 16px;
                                                    bottom: 12px;
                                                    gap: 8px;
                                                }

                                                #{{ $homeSliderId }} .home-hero-slider-dot {
                                                    width: 22px;
                                                }
                                            }
                                        </style>

                                        <div class="home-hero-slider-track">
                                            @foreach ($featuredMediaItems as $index => $heroMedia)
                                                @php
                                                    $isFirstSlide = $index === 0;
                                                @endphp

                                                <div class="home-hero-slide {{ $isFirstSlide ? 'is-active' : '' }}"
                                                    data-slide-index="{{ $index }}"
                                                    aria-hidden="{{ $isFirstSlide ? 'false' : 'true' }}">
                                                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                                        {!! cms_picture(
                                                            $heroMedia,
                                                            [
                                                                'alt' => $buttonItemTitle,
                                                                'class' => 'home-hero-media-image',
                                                                'sizes' => '(max-width: 1023px) 100vw, 58vw',
                                                                'loading' => $isFirstSlide ? 'eager' : 'lazy',
                                                                'fetchpriority' => $isFirstSlide ? 'high' : 'auto',
                                                                'decoding' => 'async',
                                                            ],
                                                            'hero_sm',
                                                            ['thumb', 'small', 'hero_sm', 'large'],
                                                        ) !!}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="home-hero-slider-dots" aria-label="Slide indicators">
                                            @foreach ($featuredMediaItems as $index => $heroMedia)
                                                <button type="button"
                                                    class="home-hero-slider-dot {{ $index === 0 ? 'is-active' : '' }}"
                                                    data-slide-dot="{{ $index }}"
                                                    aria-label="Go to slide {{ $index + 1 }}"
                                                    aria-pressed="{{ $index === 0 ? 'true' : 'false' }}"></button>
                                            @endforeach
                                        </div>
                                    </div>

                                    @push('scripts')
                                        <script>
                                            (function() {
                                                const slider = document.getElementById(@json($homeSliderId));
                                                if (!slider) return;

                                                const slides = Array.from(slider.querySelectorAll('.home-hero-slide'));
                                                const dots = Array.from(slider.querySelectorAll('.home-hero-slider-dot'));
                                                if (slides.length < 2) return;

                                                let current = 0;
                                                let timer = null;
                                                const delay = 4200;

                                                const render = (index) => {
                                                    current = (index + slides.length) % slides.length;

                                                    slides.forEach((slide, i) => {
                                                        const active = i === current;
                                                        slide.classList.toggle('is-active', active);
                                                        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
                                                    });

                                                    dots.forEach((dot, i) => {
                                                        const active = i === current;
                                                        dot.classList.toggle('is-active', active);
                                                        dot.setAttribute('aria-pressed', active ? 'true' : 'false');
                                                    });
                                                };

                                                const next = () => {
                                                    render(current + 1);
                                                };

                                                const start = () => {
                                                    stop();
                                                    timer = window.setInterval(next, delay);
                                                };

                                                const stop = () => {
                                                    if (timer) {
                                                        window.clearInterval(timer);
                                                        timer = null;
                                                    }
                                                };

                                                dots.forEach((dot, index) => {
                                                    dot.addEventListener('click', () => {
                                                        render(index);
                                                        start();
                                                    });
                                                });

                                                slider.addEventListener('mouseenter', stop);
                                                slider.addEventListener('mouseleave', start);
                                                slider.addEventListener('focusin', stop);
                                                slider.addEventListener('focusout', start);

                                                document.addEventListener('visibilitychange', () => {
                                                    if (document.hidden) {
                                                        stop();
                                                    } else {
                                                        start();
                                                    }
                                                });

                                                render(0);
                                                start();
                                            })();
                                        </script>
                                    @endpush
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-slate-50 px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-10 xl:px-12 xl:py-12">
                        <div class="grid grid-cols-1 items-start gap-8 md:gap-10 lg:grid-cols-12 lg:gap-16 xl:gap-20">
                            <div class="order-2 lg:order-1 lg:col-span-6 lg:self-center">
                                <div class="py-1 lg:py-2">
                                    @if ($sloganTag !== '')
                                        <div class="mb-4">
                                            <div class="h-1 w-20 bg-red-500"></div>
                                            <div class="mt-4 text-sm font-semibold text-slate-700">
                                                {{ $sloganTag }}
                                            </div>
                                        </div>
                                    @endif

                                    @if ($heroTitle !== '')
                                        <h1 class="page-hero-title">
                                            {{ $heroTitle }}
                                        </h1>
                                    @endif

                                    @if (trim($heroHtml) !== '')
                                        <div class="cms-content mt-5 sm:mt-6 page-hero-content">
                                            {!! $heroHtml !!}
                                        </div>
                                    @endif

                                    <div class="mt-8">
                                        <button type="button"
                                            class="cf-get-price cursor-pointer inline-flex min-h-[46px] items-center justify-center rounded bg-[var(--cms-primary)] px-6 py-3 text-center text-sm font-semibold !text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                                            aria-label="{{ trim($quoteButtonLabel . ' for ' . $buttonItemTitle) }}"
                                            data-default-label="{{ $quoteButtonLabel }}"
                                            data-item-id="{{ (int) $post->id }}" data-item-type="post"
                                            data-item-title="{{ $buttonItemTitle }}" data-item-url="{{ $pageUrl }}"
                                            data-item-image="{{ $productImage }}">
                                            {!! $quoteButtonHtml !!}
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                                @if ($featuredMediaItems->count() === 1)
                                    @php
                                        $heroMedia = $featuredMediaItems->first();
                                    @endphp

                                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                        <div class="hero-media-wrap mx-auto w-full max-w-[520px] overflow-hidden">
                                            {!! cms_picture(
                                                $heroMedia,
                                                [
                                                    'alt' => $buttonItemTitle,
                                                    'class' =>
                                                        'hero-media-image block w-full h-auto max-h-[280px] object-contain sm:max-h-[380px] lg:max-h-[520px]',
                                                    'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                    'loading' => 'eager',
                                                    'fetchpriority' => 'high',
                                                    'decoding' => 'async',
                                                ],
                                                'hero_sm',
                                                ['thumb', 'small', 'hero_sm', 'large'],
                                            ) !!}
                                        </div>
                                    @endif
                                @elseif ($featuredMediaItems->count() > 1)
                                    <div id="{{ $sliderId }}"
                                        class="page-featured-slider relative mx-auto w-full max-w-[520px]">
                                        <div class="page-featured-slider-track">
                                            @foreach ($featuredMediaItems as $index => $heroMedia)
                                                @php
                                                    $isFirstSlide = $index === 0;
                                                @endphp

                                                <div class="page-featured-slide {{ $isFirstSlide ? 'is-active' : '' }}">
                                                    @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                                        {!! cms_picture(
                                                            $heroMedia,
                                                            [
                                                                'alt' => $buttonItemTitle,
                                                                'class' =>
                                                                    'hero-media-image block w-full h-auto max-h-[280px] object-contain sm:max-h-[380px] lg:max-h-[520px]',
                                                                'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                                'loading' => $isFirstSlide ? 'eager' : 'lazy',
                                                                'fetchpriority' => $isFirstSlide ? 'high' : 'auto',
                                                                'decoding' => 'async',
                                                            ],
                                                            'hero_sm',
                                                            ['thumb', 'small', 'hero_sm', 'large'],
                                                        ) !!}
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>

                                        <button type="button"
                                            class="page-featured-nav page-featured-prev focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                                            aria-label="Previous image">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="28"
                                                viewBox="0 0 14 28" fill="none" aria-hidden="true">
                                                <path d="M11.5 2.5L2.5 14L11.5 25.5" stroke="currentColor"
                                                    stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>

                                        <button type="button"
                                            class="page-featured-nav page-featured-next focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                                            aria-label="Next image">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="28"
                                                viewBox="0 0 14 28" fill="none" aria-hidden="true">
                                                <path d="M2.5 2.5L11.5 14L2.5 25.5" stroke="currentColor"
                                                    stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </section>

        @if ($hasProductContent)
            <section class="mt-5 bg-white">
                <div class="cms-container mx-auto px-4">
                    <div class="cms-content">
                        {!! $productHtml !!}
                    </div>
                </div>
            </section>
        @endif

        @if ($hasPromoContent || (!$isHomepage && $relatedLinks->isNotEmpty()))
            <section class="mb-5 mt-5 bg-white">
                <div class="page-container mx-auto px-4">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start lg:gap-10">
                        <div class="lg:col-span-8">
                            @if ($hasPromoContent)
                                <div class="cms-content">
                                    {!! $promoHtml !!}
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-4 lg:sticky lg:top-24 lg:self-start">
                            @if (!$isHomepage && $relatedLinks->isNotEmpty())
                                <div class="bg-[#f3f3f3] p-6">
                                    <h2 class="mb-4 related-links-title">Related Links :</h2>

                                    <div class="flex flex-col">
                                        @foreach ($relatedLinks as $item)
                                            <a href="{{ $item['url'] }}"
                                                class="related-link-item flex items-start gap-2 border-t border-[#d8d8d8] py-[10px] underline underline-offset-4 decoration-[1.5px] first:border-t-0 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                                                <span class="text-[20px] leading-none text-[#555]">›</span>
                                                <span class="block truncate" title="{{ $item['title'] }}">
                                                    {{ $item['title'] }}
                                                </span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @else
        <section class="bg-white">
            <div class="cms-container mx-auto px-4 pb-12">
                @if ($hasPostsShortcode)
                    <div class="cms-content">
                        {!! $heroHtml !!}
                    </div>
                @else
                    <div class="prose prose-slate max-w-none">
                        {!! $heroHtml !!}
                    </div>
                @endif
            </div>
        </section>
    @endif

    <style>
        .page-hero-title {
            font-family: var(--cms-heading-font-family);
            font-size: var(--cms-h1-font-size);
            font-weight: var(--cms-heading-font-weight);
            text-transform: var(--cms-heading-text-transform);
            line-height: var(--cms-heading-line-height);
            letter-spacing: var(--cms-heading-letter-spacing);
            color: var(--cms-primary);
        }

        .page-hero-content,
        .page-hero-content p,
        .page-hero-content li {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .hero-media-wrap,
        .page-featured-slider {
            width: 100%;
        }

        .hero-media-image {
            display: block;
            width: 100%;
            height: auto;
            margin-left: auto;
            margin-right: auto;
        }

        .related-links-title {
            font-family: var(--cms-heading-font-family);
            font-size: var(--cms-h3-font-size);
            font-weight: var(--cms-heading-font-weight);
            text-transform: var(--cms-heading-text-transform);
            line-height: var(--cms-heading-line-height);
            letter-spacing: var(--cms-heading-letter-spacing);
            color: var(--cms-heading-color);
        }

        .related-link-item,
        .related-link-item span:last-child {
            font-family: var(--cms-body-font-family);
            font-size: var(--cms-body-font-size);
            font-weight: var(--cms-body-font-weight);
            line-height: var(--cms-body-line-height);
            letter-spacing: var(--cms-body-letter-spacing);
            color: var(--cms-body-color);
        }

        .home-hero-shell {
            overflow: hidden;
        }

        .home-hero-content-col,
        .home-hero-media-col {
            min-width: 0;
        }

        .home-hero-content-inner {
            padding: 1.5rem 1.25rem 1.75rem;
        }

        .home-hero-media-wrap {
            position: relative;
            width: 100%;
            overflow: hidden;
            background: #f8fafc;
            min-height: 240px;
        }

        .home-hero-media-image {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (min-width: 640px) {
            .home-hero-content-inner {
                padding: 2rem 1.75rem 2.25rem;
            }

            .home-hero-media-wrap {
                min-height: 360px;
            }
        }

        @media (min-width: 768px) {
            .home-hero-content-inner {
                padding: 2.25rem 2rem 2.5rem;
            }

            .home-hero-media-wrap {
                min-height: 420px;
            }
        }

        @media (max-width: 1023.98px) {
            .home-hero-grid {
                gap: 0;
            }
        }

        @media (min-width: 1024px) {
            .home-hero-shell {
                min-height: 520px;
            }

            .home-hero-grid {
                min-height: 520px;
                align-items: stretch;
            }

            .home-hero-content-inner {
                height: 100%;
                display: flex;
                flex-direction: column;
                justify-content: center;
                padding: 3rem 2.5rem 3rem 2rem;
            }

            .home-hero-media-col,
            .home-hero-media-wrap {
                height: 100%;
                min-height: 520px;
            }

            .home-hero-media-image {
                height: 100%;
            }
        }

        @media (min-width: 1280px) {
            .home-hero-shell {
                min-height: 560px;
            }

            .home-hero-grid {
                min-height: 560px;
            }

            .home-hero-content-inner {
                padding: 3.5rem 3rem 3.5rem 2.5rem;
            }

            .home-hero-media-col,
            .home-hero-media-wrap {
                min-height: 560px;
            }
        }
    </style>
@endsection

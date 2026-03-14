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

        $quoteButtonLabel = trim(strip_tags(str_replace('|', ' ', $quoteButtonText)));
        if ($quoteButtonLabel === '') {
            $quoteButtonLabel = 'Custom Quote';
        }
        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonLabel)));

        $heroTitleRaw = (string) data_get($post->meta_json ?? [], 'slider.title', '');
        $heroTitle = trim(strip_tags($heroTitleRaw));

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
        $safePageUrl = trim((string) $pageUrl);

        $productImage = '';
        try {
            $heroMediaForButton = $featuredMediaItems->first();
            if ($heroMediaForButton instanceof \App\Models\Media) {
                $productImage = (string) ($heroMediaForButton->variantUrl('hero_sm') ?: $heroMediaForButton->url());
            }
        } catch (\Throwable $e) {
            $productImage = '';
        }
        $safeProductImage = trim((string) $productImage);

        $postButtonTitle = trim(strip_tags($heroTitle !== '' ? $heroTitle : (string) ($post->title ?? 'Page')));
        if ($postButtonTitle === '') {
            $postButtonTitle = 'Page';
        }
        $postButtonAriaLabel = trim($quoteButtonLabel . ' for ' . $postButtonTitle);

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
                            'title' => $title,
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
        $heroBackgroundUrl = null;

        if ($heroPreloadMedia && method_exists($heroPreloadMedia, 'isImage') && $heroPreloadMedia->isImage()) {
            try {
                if (method_exists($heroPreloadMedia, 'variantUrl')) {
                    $heroPreloadHref =
                        $heroPreloadMedia->variantUrl('large') ?:
                        $heroPreloadMedia->variantUrl('hero_sm') ?:
                        $heroPreloadMedia->variantUrl('small') ?:
                        $heroPreloadMedia->url();

                    $heroBackgroundUrl =
                        $heroPreloadMedia->variantUrl('large') ?:
                        $heroPreloadMedia->variantUrl('hero_sm') ?:
                        $heroPreloadMedia->variantUrl('small') ?:
                        $heroPreloadMedia->url();
                } elseif (method_exists($heroPreloadMedia, 'url')) {
                    $heroPreloadHref = $heroPreloadMedia->url();
                    $heroBackgroundUrl = $heroPreloadMedia->url();
                }
            } catch (\Throwable $e) {
                $heroPreloadHref = null;
                $heroBackgroundUrl = null;
            }
        }

        $heroPreloadHref = $heroPreloadHref ? trim((string) $heroPreloadHref) : null;
        $heroBackgroundUrl = $heroBackgroundUrl ? trim((string) $heroBackgroundUrl) : null;
        $shouldLoadCartAssets = !$isHomepage && $showCustomHero;
    @endphp

    @if ($isHomepage)
        @push('head')
            <style>
                #site-topbar {
                    display: none !important;
                }

                #site-header {
                    position: absolute;
                    top: 0;
                    left: 0;
                    right: 0;
                    z-index: 60;
                    background: transparent !important;
                    border-bottom: 0 !important;
                    color: #fff !important;
                }

                #site-header a,
                #site-header .cms-menu__link,
                #site-header .site-title,
                #site-header .data-cms-header-actions,
                #site-header .data-cms-header-actions a,
                #site-header .site-header-mobile-email,
                #site-header .site-header-mobile-toggle {
                    color: #fff !important;
                }

                #site-header .site-header-mobile-toggle {
                    border-color: rgba(255, 255, 255, .65) !important;
                }

                #site-header .logo-frame {
                    display: inline-flex !important;
                    align-items: center !important;
                    justify-content: center !important;
                    max-width: 200px;
                    min-width: 110px;
                    padding: 6px 10px;
                    background: rgba(255, 255, 255, 0.92);
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.14);
                }

                #site-header .logo-img,
                #site-header .logo-frame img,
                #site-header .logo-img img,
                #site-header .site-logo-img,
                #site-header .logo-frame picture img {
                    display: block;
                    width: 100%;
                    height: auto;
                    object-fit: contain;
                    filter: none !important;
                }

                h1.atelier-hero-title {
                    margin: 0;
                    font-family: 'Poppins', sans-serif;
                    font-weight: 700;
                    font-size: clamp(2rem, 5vw, 4.5rem);
                    line-height: 1.08;
                    letter-spacing: -0.03em;
                    color: #fff;
                    text-wrap: balance;
                }

                .atelier-hero-subtitle,
                .atelier-hero-subtitle p,
                .atelier-hero-subtitle li {
                    font-family: var(--cms-body-font-family);
                    font-size: clamp(1rem, 1.6vw, 1.25rem);
                    line-height: 1.6;
                    color: rgba(255, 255, 255, .95);
                }

                .atelier-hero-subtitle p:last-child {
                    margin-bottom: 0;
                }

                @media (max-width: 1023.98px) {
                    #site-topbar {
                        display: none !important;
                    }

                    #site-header {
                        top: 0;
                    }

                    #site-header .cms-container {
                        position: relative;
                    }

                    #site-header .site-header-row {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        gap: 12px !important;
                        min-height: 58px !important;
                        padding-top: 6px !important;
                        padding-bottom: 6px !important;
                    }

                    body #site-header>.cms-container>div.flex.items-center.justify-between.gap-4 {
                        padding-top: 6px !important;
                        padding-bottom: 6px !important;
                    }

                    #site-header .logo-frame {
                        display: flex !important;
                        align-items: center !important;
                        max-width: 200px;
                        min-width: 110px;
                    }

                    #site-header .site-header-right {
                        display: flex !important;
                        align-items: center !important;
                        margin-left: auto !important;
                        min-width: 0 !important;
                    }

                    #site-header .data-cms-header-actions {
                        display: none !important;
                    }

                    #site-header .site-header-mobile-tools {
                        display: flex !important;
                        flex-direction: column !important;
                        align-items: flex-end !important;
                        justify-content: center !important;
                        gap: 8px !important;
                        margin-left: auto !important;
                        min-width: 0 !important;
                    }

                    #site-header .site-header-mobile-email {
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: flex-end !important;
                        font-size: 12px !important;
                        font-weight: 600 !important;
                        line-height: 1 !important;
                        text-decoration: none !important;
                        text-align: right !important;
                        max-width: 140px !important;
                        overflow: hidden !important;
                        text-overflow: ellipsis !important;
                        white-space: nowrap !important;
                    }

                    #site-header .site-header-mobile-toggle {
                        display: inline-flex !important;
                        align-items: center !important;
                        justify-content: center !important;
                    }

                    #site-header .cms-header-nav,
                    #site-header nav:not(.mobile-menu):not(.mobile-nav) .cms-menu,
                    #site-header nav:not(.mobile-menu):not(.mobile-nav) {
                        display: none !important;
                    }

                    #site-header button,
                    #site-header .menu-toggle,
                    #site-header .mobile-menu-toggle,
                    #site-header .cms-mobile-toggle,
                    #site-header [aria-label*="menu" i],
                    #site-header [aria-controls*="menu" i] {
                        color: #fff !important;
                        border-color: rgba(255, 255, 255, .65) !important;
                    }
                }

                @media (max-width: 767.98px) {
                    #site-header .logo-frame {
                        max-width: 120px;
                    }

                    #site-header .site-header-mobile-email {
                        font-size: 11px !important;
                        max-width: 112px !important;
                    }

                    h1.atelier-hero-title {
                        font-size: clamp(1.8rem, 7vw, 2.75rem);
                    }

                    .atelier-hero-subtitle,
                    .atelier-hero-subtitle p,
                    .atelier-hero-subtitle li {
                        font-size: 0.98rem;
                        line-height: 1.5;
                    }
                }
            </style>
        @endpush
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const headerRow = document.querySelector(
                        '#site-header > .cms-container > div.flex.items-center.justify-between');

                    if (!headerRow) return;

                    headerRow.classList.remove('pt-12', 'pb-8');
                    headerRow.classList.add('pt-3', 'pb-3');
                });
            </script>
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
        @endonce
    @endif

    @if ($showCustomHero)
        @push('head')
            @if ($heroPreloadHref)
                <link rel="preload" as="image" href="{{ $heroPreloadHref }}" imagesizes="100vw" fetchpriority="high">
            @endif

            @if ($isHomepage)
                <link rel="preload"
                    href="{{ asset('themes/siatex-group/public/fonts/poppins/poppins-semibold-webfont.woff2') }}" as="font"
                    type="font/woff2" crossorigin>

                <link rel="preload"
                    href="{{ asset('themes/siatex-group/public/fonts/poppins/poppins-regular-webfont.woff2') }}" as="font"
                    type="font/woff2" crossorigin>
            @endif
        @endpush
    @endif

    @if (!$isHomepage)
        <div class="cms-container mx-auto px-4 pt-6">
            <nav aria-label="Breadcrumb" class="text-sm text-slate-600">
                <ol class="breadcrumb-list flex flex-wrap items-center gap-1">
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
        @if ($isHomepage)
            <section
                class="atelier-hero relative min-h-screen overflow-hidden bg-slate-900 >
               @if ($heroBackgroundUrl)
<div
            class="atelier-hero-background
                absolute inset-0"
                style="
                background-image: url('{{ e($heroBackgroundUrl) }}');
                background-size: cover;
                background-position: center center;
                background-repeat: no-repeat;
            ">
                </div>
        @endif

        <div class="absolute inset-0 bg-black/45"></div>

        <div class="relative z-10 flex min-h-screen items-center">
            <div class="cms-container mx-auto w-full px-4">
                <div class="mx-auto max-w-5xl pt-28 pb-20 text-center sm:pt-32 sm:pb-24 lg:pt-36 lg:pb-28">
                    @if ($heroTitle !== '')
                        <h1 class="atelier-hero-title">
                            {{ $heroTitle }}
                        </h1>
                    @endif

                    @if (trim($heroHtml) !== '')
                        <div class="atelier-hero-subtitle cms-content mx-auto mt-6 max-w-4xl lg:mt-8">
                            {!! $heroHtml !!}
                        </div>
                    @endif
                </div>
            </div>
        </div>
        </section>
    @else
        <section class="mt-5 bg-white">
            <div class="cms-container mx-auto px-4">
                <div class="bg-[#f3f3f3] px-5 py-6 sm:px-7 sm:py-8 lg:px-10 lg:py-10 xl:px-12 xl:py-12">
                    <div class="grid grid-cols-1 items-start gap-8 lg:min-h-[520px] lg:grid-cols-12 lg:gap-10 xl:gap-14">
                        <div class="order-2 lg:order-1 lg:col-span-6 lg:self-center">
                            <div class="max-w-[420px]">
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
                                    <div class="cms-content page-hero-content mt-5 sm:mt-6">
                                        {!! $heroHtml !!}
                                    </div>
                                @endif

                                <div class="mt-8">
                                    <button type="button"
                                        class="cf-get-price inline-flex min-h-[46px] items-center justify-center rounded bg-[var(--cms-primary)] px-6 py-3 text-center text-sm font-semibold text-white transition hover:opacity-90 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--cms-primary)]"
                                        aria-label="{{ $postButtonAriaLabel }}"
                                        data-default-label="{{ $quoteButtonLabel }}" data-item-id="{{ (int) $post->id }}"
                                        data-item-type="post" data-item-title="{{ $postButtonTitle }}"
                                        data-item-url="{{ $safePageUrl }}" data-item-image="{{ $safeProductImage }}">
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
                                    <div class="hero-media-wrap mx-auto w-full max-w-[520px]">
                                        {!! cms_picture(
                                            $heroMedia,
                                            [
                                                'alt' => $postButtonTitle,
                                                'class' =>
                                                    'hero-media-image block w-full h-auto max-h-[280px] object-contain sm:max-h-[360px] md:max-h-[420px] lg:max-h-[470px] xl:max-h-[500px]',
                                                'sizes' => '(max-width: 1024px) 100vw, 58vw',
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
                                    $sliderId = 'page-featured-slider-' . ($post->id ?? 'default');
                                @endphp

                                <div id="{{ $sliderId }}"
                                    class="page-featured-slider relative mx-auto w-full max-w-[520px]">
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
                                                            'alt' => $postButtonTitle,
                                                            'class' =>
                                                                'hero-media-image block w-full h-auto max-h-[280px] object-contain sm:max-h-[360px] md:max-h-[420px] lg:max-h-[470px] xl:max-h-[500px]',
                                                            'sizes' => '(max-width: 1024px) 100vw, 58vw',
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
                                            <path d="M11.5 2.5L2.5 14L11.5 25.5" stroke="currentColor" stroke-width="1.4"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <button type="button"
                                        class="page-featured-nav page-featured-next focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500"
                                        aria-label="Next image">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="28"
                                            viewBox="0 0 14 28" fill="none" aria-hidden="true">
                                            <path d="M2.5 2.5L11.5 14L2.5 25.5" stroke="currentColor" stroke-width="1.4"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>

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
                                            let ticking = false;

                                            const showSlide = (index) => {
                                                current = (index + slides.length) % slides.length;

                                                if (ticking) return;
                                                ticking = true;

                                                requestAnimationFrame(() => {
                                                    slides.forEach((slide, i) => {
                                                        slide.classList.toggle('is-active', i === current);
                                                    });
                                                    ticking = false;
                                                });
                                            };

                                            prevBtn?.addEventListener('click', () => showSlide(current - 1));
                                            nextBtn?.addEventListener('click', () => showSlide(current + 1));

                                            showSlide(0);
                                        })();
                                    </script>
                                @endpush
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($hasProductContent)
        <section class="mt-5 bg-white">
            <div class="page-container mx-auto px-4">
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
                                <h2 class="related-links-title mb-4">Related Links :</h2>

                                <div class="flex flex-col">
                                    @foreach ($relatedLinks as $item)
                                        <a href="{{ trim((string) $item['url']) }}"
                                            class="related-link-item flex items-start gap-2 border-t border-[#d8d8d8] py-[10px] underline underline-offset-4 decoration-[1.5px] first:border-t-0 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-500">
                                            <span class="text-[20px] leading-none text-[#555]">›</span>
                                            <span class="block truncate"
                                                title="{{ trim(strip_tags((string) $item['title'])) }}">
                                                {{ trim(strip_tags((string) $item['title'])) }}
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
    <section class="bg-white mt-10">
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

        .atelier-hero .cms-content a[href] {
            color: #fff;
        }

        .atelier-hero .cms-content .cta-button {
            margin-top: 1rem;
        }

        .atelier-hero .cms-container {
            position: relative;
            z-index: 2;
        }

        .atelier-hero .max-w-5xl {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
            justify-content: center;
        }

        @media (max-width: 1023.98px) {
            .atelier-hero {
                min-height: 100svh;
            }

            .atelier-hero .max-w-5xl {
                min-height: 100svh;
                padding-top: 96px !important;
                padding-bottom: 48px !important;
                justify-content: center;
            }
        }

        @media (max-width: 767.98px) {
            .atelier-hero .max-w-5xl {
                padding-top: 92px !important;
                padding-bottom: 36px !important;
            }
        }
    </style>
@endsection

@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $settings = app(\App\Cms\Core\SettingsRepository::class);
        $hooks = app(\App\Cms\Hooks\Hooks::class);

        $title = trim((string) ($post->title ?? ''));
        $heroTitle = trim((string) data_get($post->meta_json ?? [], 'slider.title', ''));
        $displayTitle = $heroTitle !== '' ? $heroTitle : $title;

        $homepageId = $settings->get('core', 'homepage_page_id', null);
        $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
        if ($homepageId !== null && $homepageId <= 0) {
            $homepageId = null;
        }

        $isHomepage = $homepageId !== null && $post && (int) $post->getKey() === $homepageId;

        $sloganTag = trim((string) $settings->get('core', 'slogan_tag', 'Your Tech-pack, Our production'));
        if ($sloganTag === '') {
            $sloganTag = 'Your Tech-pack, Our production';
        }

        $quoteButtonText = trim((string) $settings->get('core', 'quote_button_text', 'Custom Quote'));
        if ($quoteButtonText === '') {
            $quoteButtonText = 'Custom Quote';
        }
        $quoteButtonHtml = nl2br(e(str_replace('|', "\n", $quoteButtonText)));

        $adminEditUrl = $adminEditUrl ?? url('/lara-admin');

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

        $heroRaw = (string) ($post->content_html ?? data_get($post->content_json ?? [], 'html', ''));
        $productRaw = (string) data_get($post->meta_json ?? [], 'product', '');
        $promoRaw = (string) data_get($post->meta_json ?? [], 'sub_description', '');
        $subtitle = trim((string) data_get($post->meta_json ?? [], 'subtitle', ''));

        $heroHtml = $renderEditorContent($heroRaw);
        $productHtml = $renderEditorContent($productRaw);
        $promoHtml = $renderEditorContent($promoRaw);

        $hasPostsShortcode = str_contains($heroRaw, '[posts') || str_contains($heroHtml, 'cms-post');

        $featuredMediaItems = collect();

        try {
            if (method_exists($post, 'featuredMediaPivot')) {
                $featuredMediaItems = $post->featuredMediaPivot()->get();
            } elseif (method_exists($post, 'mediaPivot')) {
                $featuredMediaItems = $post
                    ->mediaPivot()
                    ->wherePivot('role', 'featured')
                    ->orderBy('post_media.sort_order')
                    ->get();
            }
        } catch (\Throwable $e) {
            $featuredMediaItems = collect();
        }

        if ($featuredMediaItems->isEmpty() && method_exists($post, 'featuredMedia') && $post->featuredMedia) {
            $featuredMediaItems = collect([$post->featuredMedia]);
        }

        $featuredMediaItems = $featuredMediaItems
            ->filter(fn($m) => $m instanceof \App\Models\Media)
            ->filter(fn($m) => method_exists($m, 'isImage') && $m->isImage())
            ->unique(fn($m) => $m->id ?? spl_object_hash($m))
            ->values();

        $hasHeroMedia = $featuredMediaItems->isNotEmpty();
        $hasHeroText = $displayTitle !== '' || trim(strip_tags($heroHtml)) !== '';
        $hasProductContent = trim(strip_tags($productHtml)) !== '';
        $hasPromoContent = trim(strip_tags($promoHtml)) !== '';
        $showCustomHero = $hasHeroText || $hasHeroMedia;

        $productUrl = function_exists('cms_post_url') ? cms_post_url($post) : url()->current();

        $productImage = '';
        try {
            $heroMediaForButton = $featuredMediaItems->first();
            if ($heroMediaForButton && method_exists($heroMediaForButton, 'url')) {
                $productImage = (string) $heroMediaForButton->url('medium');
            } elseif ($heroMediaForButton && property_exists($heroMediaForButton, 'url')) {
                $productImage = (string) $heroMediaForButton->url;
            }
        } catch (\Throwable $e) {
            $productImage = '';
        }

        $category = null;
        try {
            $category = $post->categories()->first();
        } catch (\Throwable $e) {
            $category = null;
        }

        $postPermalink = function ($p): string {
            try {
                /** @var \App\Cms\Content\PermalinkManager $permalinks */
                $permalinks = app(\App\Cms\Content\PermalinkManager::class);
                return $permalinks->postUrl($p);
            } catch (\Throwable $e) {
                return function_exists('cms_post_url') ? cms_post_url($p) : url()->current();
            }
        };

        $relatedLinks = collect();

        $fetchSameCategoryRandom = function (array $excludeIds, int $limit) use ($category) {
            if (!$category) {
                return collect();
            }

            try {
                return \App\Models\Post::query()
                    ->where('type', 'post')
                    ->where('status', 'published')
                    ->whereNotIn('id', $excludeIds)
                    ->whereHas('categories', function ($q) use ($category) {
                        $q->where('terms.id', (int) $category->id);
                    })
                    ->inRandomOrder()
                    ->limit($limit)
                    ->get()
                    ->unique('id')
                    ->values();
            } catch (\Throwable $e) {
                return collect();
            }
        };

        if (!$isHomepage && $category) {
            $relatedLinks = $fetchSameCategoryRandom([(int) $post->id], 120)
                ->take(10)
                ->values();
        }

        $customJsonRaw =
            data_get($post->meta_json ?? [], 'custom_json', null) ?:
            data_get($post->meta_json ?? [], 'seo.custom_json', null);

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
    @endphp

    <div class="cms-container mx-auto px-4 pt-6">
        <nav class="text-sm text-slate-500">
            <a class="text-[#1f5f99] hover:underline" href="{{ url('/') }}">Home</a>
            <span class="mx-2 text-slate-300">/</span>
            <span class="text-slate-600">{{ $title }}</span>
        </nav>
    </div>

    @if ($showCustomHero)
        <section class="mt-5 bg-white">
            <div class="cms-container mx-auto px-4">
                <div class="bg-slate-50 px-4 py-6 sm:px-6 sm:py-8 lg:px-10 lg:py-10 xl:px-12 xl:py-12">
                    <div class="grid grid-cols-1 items-start gap-8 md:gap-10 lg:grid-cols-12 lg:gap-16 xl:gap-20">
                        <div class="order-1 lg:order-2 lg:col-span-6 lg:sticky lg:top-24 lg:self-start">
                            @if ($featuredMediaItems->count() === 1)
                                @php
                                    $heroMedia = $featuredMediaItems->first();
                                @endphp

                                @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                    <div class="w-full overflow-hidden bg-white">
                                        {!! cms_picture(
                                            $heroMedia,
                                            [
                                                'alt' => e($displayTitle !== '' ? $displayTitle : $title),
                                                'class' => 'block w-full h-auto max-h-[280px] object-contain sm:max-h-[380px] lg:max-h-[520px]',
                                                'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                'loading' => 'eager',
                                                'decoding' => 'async',
                                            ],
                                            'large',
                                            ['medium', 'medium_large', 'large'],
                                        ) !!}
                                    </div>
                                @endif
                            @elseif ($featuredMediaItems->count() > 1)
                                @php
                                    $sliderId = 'post-featured-slider-' . ($post->id ?? 'default');
                                @endphp

                                <div id="{{ $sliderId }}"
                                    class="relative w-full overflow-visible bg-white px-8 sm:px-10">
                                    <div class="relative overflow-hidden">
                                        @foreach ($featuredMediaItems as $index => $heroMedia)
                                            <div class="page-featured-slide {{ $index === 0 ? 'block' : 'hidden' }}">
                                                @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                                    {!! cms_picture(
                                                        $heroMedia,
                                                        [
                                                            'alt' => e($displayTitle !== '' ? $displayTitle : $title),
                                                            'class' => 'block w-full h-auto max-h-[280px] object-contain sm:max-h-[380px] lg:max-h-[520px]',
                                                            'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                            'loading' => $index === 0 ? 'eager' : 'lazy',
                                                            'decoding' => 'async',
                                                        ],
                                                        'large',
                                                        ['medium', 'medium_large', 'large'],
                                                    ) !!}
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>

                                    <button type="button"
                                        class="page-featured-prev absolute left-0 top-1/2 z-10 inline-flex h-12 w-8 -translate-x-6 -translate-y-1/2 items-center justify-center bg-transparent p-0 text-black transition hover:opacity-70 sm:-translate-x-8"
                                        aria-label="Previous image">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="28"
                                            viewBox="0 0 14 28" fill="none">
                                            <path d="M11.5 2.5L2.5 14L11.5 25.5" stroke="currentColor" stroke-width="1.4"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <button type="button"
                                        class="page-featured-next absolute right-0 top-1/2 z-10 inline-flex h-12 w-8 translate-x-6 -translate-y-1/2 items-center justify-center bg-transparent p-0 text-black transition hover:opacity-70 sm:translate-x-8"
                                        aria-label="Next image">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="28"
                                            viewBox="0 0 14 28" fill="none">
                                            <path d="M2.5 2.5L11.5 14L2.5 25.5" stroke="currentColor" stroke-width="1.4"
                                                stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </button>
                                </div>

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
                                                slide.classList.toggle('hidden', i !== current);
                                                slide.classList.toggle('block', i === current);
                                            });
                                        };

                                        prevBtn?.addEventListener('click', () => showSlide(current - 1));
                                        nextBtn?.addEventListener('click', () => showSlide(current + 1));

                                        showSlide(0);
                                    })();
                                </script>
                            @else
                                <div class="h-80 w-full bg-slate-100"></div>
                            @endif
                        </div>

                        <div class="order-2 lg:order-1 lg:col-span-6">
                            <div class="py-1 lg:py-2">
                                @if (!$isHomepage && $sloganTag !== '')
                                    <div class="mb-4">
                                        <div class="h-1 w-20 bg-red-500"></div>
                                        <div class="mt-4 text-sm font-semibold text-slate-700">
                                            {{ $sloganTag }}
                                        </div>
                                    </div>
                                @endif

                                @if ($displayTitle !== '')
                                    <h1
                                        class="text-3xl font-semibold leading-tight tracking-tight text-[#0f4c81] sm:text-4xl lg:text-5xl xl:text-[48px]">
                                        {{ $displayTitle }}
                                    </h1>
                                @endif

                                @if (trim($heroHtml) !== '')
                                    <div class="cms-content mt-5 sm:mt-6 leading-7 text-slate-800">
                                        {!! $heroHtml !!}
                                    </div>
                                @endif

                                <div class="mt-8">
                                    <a href="#"
                                        class="cf-get-price inline-flex min-h-[46px] items-center justify-center rounded bg-[#1f5f99] px-6 py-3 text-center text-sm font-semibold text-white transition hover:bg-[#194f7f]"
                                        data-default-label="{{ strip_tags(str_replace('|', ' ', $quoteButtonText)) }}"
                                        data-item-id="{{ (int) $post->id }}" data-item-type="post"
                                        data-item-title="{{ e($title) }}" data-item-url="{{ e($productUrl) }}"
                                        data-item-image="{{ e($productImage) }}">
                                        {!! $quoteButtonHtml !!}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        @if ($hasProductContent)
            <section class="mt-5 bg-white">
                <div class="page-container mx-auto px-4">
                    <div class="cms-content">
                        {!! $productHtml !!}
                    </div>
                </div>
            </section>
        @endif

        @if ($hasPromoContent || (!$isHomepage && $relatedLinks->isNotEmpty()) || $subtitle !== '')
            <section class="mb-5 mt-5 bg-white">
                <div class="page-container mx-auto px-4">
                    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:items-start lg:gap-10">
                        <div class="lg:col-span-8">
                            @if ($subtitle !== '')
                                <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                                    {{ $subtitle }}
                                </h2>
                            @elseif ($title !== '')
                                <h2 class="text-2xl font-semibold leading-tight text-slate-900">
                                    {{ $title }}
                                </h2>
                            @endif

                            @if ($hasPromoContent)
                                <div class="cms-content mt-4">
                                    {!! $promoHtml !!}
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-4 lg:sticky lg:top-24 lg:self-start">
                            @if (!$isHomepage && $relatedLinks->isNotEmpty())
                                <div class="bg-[#f3f3f3] p-6">
                                    <h3 class="mb-4 text-[22px] font-normal leading-[1.2] text-[#333]">Related Links :</h3>

                                    <div class="flex flex-col">
                                        @foreach ($relatedLinks as $item)
                                            @php
                                                /** @var \App\Models\Post $item */
                                                $itemTitle = trim((string) ($item->title ?? ''));
                                                $itemTitle = $itemTitle !== '' ? $itemTitle : 'Post';
                                                $itemUrl = $postPermalink($item);
                                            @endphp
                                            <a href="{{ $itemUrl }}"
                                                class="flex items-start gap-2 border-t border-[#d8d8d8] py-[10px] text-[16px] italic leading-[1.35] text-[#555] first:border-t-0">
                                                <span class="text-[20px] leading-none text-[#777]">›</span>
                                                <span class="block truncate hover:underline" title="{{ $itemTitle }}">
                                                    {{ $itemTitle }}
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

    @if ($isJsonLd)
        @push('head')
            <script type="application/ld+json">{!! json_encode($customJson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endpush
    @endif
@endsection

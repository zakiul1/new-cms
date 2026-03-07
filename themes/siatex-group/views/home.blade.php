@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post|null $post */
        $hooks = app(\App\Cms\Hooks\Hooks::class);

        $homePost = $post ?? null;

        $heroTitle = '';
        $heroSectionRaw = '';
        $productRaw = '';
        $promoRaw = '';

        $featuredMediaItems = collect();

        if ($homePost) {
            $heroTitle = trim(
                (string) (data_get($homePost->meta_json ?? [], 'slider.title') ?: $homePost->title ?? ''),
            );
            $heroSectionRaw =
                (string) ($homePost->content_html ?? (data_get($homePost->content_json ?? [], 'html') ?? ''));
            $productRaw = (string) (data_get($homePost->meta_json ?? [], 'product') ?? '');
            $promoRaw = (string) (data_get($homePost->meta_json ?? [], 'sub_description') ?? '');

            try {
                if (method_exists($homePost, 'featuredMediaPivot')) {
                    $featuredMediaItems = $homePost->featuredMediaPivot()->get();
                }

                if ($featuredMediaItems->isEmpty() && method_exists($homePost, 'featuredMedia')) {
                    $featuredMediaItems = $homePost->featuredMedia()->get();
                }
            } catch (\Throwable $e) {
                $featuredMediaItems = collect();
            }

            $featuredMediaItems = $featuredMediaItems
                ->filter(fn($m) => $m instanceof \App\Models\Media)
                ->unique(fn($m) => $m->id ?? spl_object_hash($m))
                ->values();
        }

        $renderEditorContent = function (?string $raw, $contextPost) use ($hooks): string {
            $raw = (string) $raw;

            if (trim($raw) === '') {
                return '';
            }

            $filtered = $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, $raw, [
                'post' => $contextPost,
            ]);

            try {
                return function_exists('do_shortcode') ? do_shortcode($filtered, ['post' => $contextPost]) : $filtered;
            } catch (\Throwable $e) {
                return $filtered . "\n<!-- shortcode error: " . e($e->getMessage()) . ' -->';
            }
        };

        $heroSectionHtml = $renderEditorContent($heroSectionRaw, $homePost);
        $productHtml = $renderEditorContent($productRaw, $homePost);
        $promoHtml = $renderEditorContent($promoRaw, $homePost);

        $hasHero = $heroTitle !== '' || trim(strip_tags($heroSectionHtml)) !== '' || $featuredMediaItems->isNotEmpty();
        $hasProduct = trim(strip_tags($productHtml)) !== '';
        $hasPromo = trim(strip_tags($promoHtml)) !== '';
    @endphp

    {{-- Required font for the hero design --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Ropa+Sans&display=swap" rel="stylesheet">

    @if ($homePost && $hasHero)
        <section class="bg-white mt-[20px]">
            <div class="cms-container mx-auto px-4">
                <div class="bg-gray-50 p-6 md:p-10 lg:p-12">
                    <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-8 lg:gap-0">
                        {{-- TEXT --}}
                        <div class="order-2 lg:order-1">
                            <div class="py-4 lg:py-0 lg:pr-14 home-hero-text">
                                @if ($heroTitle !== '')
                                    <h1>{{ $heroTitle }}</h1>
                                @endif

                                @if (trim($heroSectionHtml) !== '')
                                    <div class="mt-6 home-hero-subtitle">
                                        {!! $heroSectionHtml !!}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- IMAGE / SLIDER --}}
                        <div class="order-1 lg:order-2">
                            @if ($featuredMediaItems->count() === 1)
                                @php
                                    $heroMedia = $featuredMediaItems->first();
                                @endphp

                                @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                    <div class="w-full">
                                        {!! cms_picture(
                                            $heroMedia,
                                            [
                                                'alt' => e($heroTitle !== '' ? $heroTitle : $homePost->title ?? 'Home'),
                                                'class' => 'w-full h-64 sm:h-80 lg:h-[420px] object-cover',
                                                'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                'loading' => 'lazy',
                                                'decoding' => 'async',
                                            ],
                                            'large',
                                            ['medium', 'medium_large', 'large'],
                                        ) !!}
                                    </div>
                                @endif
                            @elseif ($featuredMediaItems->count() > 1)
                                @php
                                    $sliderId = 'home-featured-slider-' . ($homePost->id ?? 'default');
                                @endphp

                                <div id="{{ $sliderId }}" class="relative w-full">
                                    <div class="overflow-hidden relative">
                                        @foreach ($featuredMediaItems as $index => $heroMedia)
                                            <div class="home-featured-slide {{ $index === 0 ? 'block' : 'hidden' }}"
                                                data-slide-index="{{ $index }}">
                                                @if ($heroMedia && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                                    {!! cms_picture(
                                                        $heroMedia,
                                                        [
                                                            'alt' => e($heroTitle !== '' ? $heroTitle : $homePost->title ?? 'Home'),
                                                            'class' => 'w-full h-64 sm:h-80 lg:h-[420px] object-cover',
                                                            'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                                            'loading' => 'lazy',
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
                                        class="home-featured-prev absolute left-3 top-1/2 -translate-y-1/2 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:bg-white"
                                        aria-label="Previous image">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M12.79 15.79a.75.75 0 0 1-1.06 0l-5.25-5.25a.75.75 0 0 1 0-1.06l5.25-5.25a.75.75 0 1 1 1.06 1.06L8.07 10l4.72 4.72a.75.75 0 0 1 0 1.06Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>

                                    <button type="button"
                                        class="home-featured-next absolute right-3 top-1/2 -translate-y-1/2 z-10 inline-flex h-11 w-11 items-center justify-center rounded-full bg-white/90 text-gray-700 shadow hover:bg-white"
                                        aria-label="Next image">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                            fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M7.21 4.21a.75.75 0 0 1 1.06 0l5.25 5.25a.75.75 0 0 1 0 1.06l-5.25 5.25a.75.75 0 1 1-1.06-1.06L11.93 10 7.21 5.28a.75.75 0 0 1 0-1.06Z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <script>
                                    (function() {
                                        const slider = document.getElementById(@json($sliderId));
                                        if (!slider) return;

                                        const slides = Array.from(slider.querySelectorAll('.home-featured-slide'));
                                        const prevBtn = slider.querySelector('.home-featured-prev');
                                        const nextBtn = slider.querySelector('.home-featured-next');

                                        if (!slides.length) return;

                                        let current = 0;

                                        const showSlide = (index) => {
                                            current = (index + slides.length) % slides.length;

                                            slides.forEach((slide, i) => {
                                                if (i === current) {
                                                    slide.classList.remove('hidden');
                                                    slide.classList.add('block');
                                                } else {
                                                    slide.classList.remove('block');
                                                    slide.classList.add('hidden');
                                                }
                                            });
                                        };

                                        prevBtn?.addEventListener('click', () => showSlide(current - 1));
                                        nextBtn?.addEventListener('click', () => showSlide(current + 1));

                                        showSlide(0);
                                    })();
                                </script>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </section>
    @endif

    @if ($homePost && $hasProduct)
        <section class="bg-white mt-[20px]">
            <div class="cms-container mx-auto px-4">
                <div class="cms-content">
                    {!! $productHtml !!}
                </div>
            </div>
        </section>
    @endif

    @if ($homePost && $hasPromo)
        <section class="bg-white mt-[20px] mb-[20px]">
            <div class="cms-container mx-auto px-4">
                <div class="cms-content">
                    {!! $promoHtml !!}
                </div>
            </div>
        </section>
    @endif

    @if (!$homePost)
        <section class="bg-white mt-[20px]">
            <div class="cms-container mx-auto px-4 py-10">
                <div class="prose prose-slate max-w-none">
                    {!! $hooks->applyFilters(
                        \App\Cms\Hooks\HookPoints::CMS_THE_CONTENT,
                        '<h1>' .
                            e(config('app.name')) .
                            '</h1><p>Welcome. Set a static homepage in Admin → Settings to show a page here.</p>',
                        ['post' => null],
                    ) !!}
                </div>
            </div>
        </section>
    @endif

    <style>
        .home-hero-text h1,
        .home-hero-text h2 {
            font-size: 48px;
            font-weight: 400;
            color: #666;
            text-transform: uppercase;
            line-height: 1;
            letter-spacing: 0;
            text-align: left;
            max-width: 370px;
            margin-bottom: 15px;
            font-family: 'Ropa Sans', sans-serif;
        }

        .home-hero-subtitle,
        .home-hero-subtitle p {
            color: #374151;
            font-weight: 600;
            font-style: italic;
            line-height: 1.7;
        }

        .home-hero-subtitle>*:first-child {
            margin-top: 0;
        }

        .home-hero-subtitle>*:last-child {
            margin-bottom: 0;
        }
    </style>
@endsection

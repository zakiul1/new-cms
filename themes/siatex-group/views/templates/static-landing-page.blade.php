{{-- themes/<active-theme>/views/templates/static-landing-page.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */
        $hooks = app(\App\Cms\Hooks\Hooks::class);

        // =========================
        // HERO FROM STATIC POSTS: static_category -> Term NAME "slider" -> newest published static_post
        // =========================
        $heroTitle = '';
        $heroSubtitle = '';
        $heroMedia = null;

        try {
            $taxonomy = \App\Models\Taxonomy::query()->where('key', 'static_category')->first();

            if ($taxonomy) {
                $sliderTerm = \App\Models\Term::query()
                    ->where('taxonomy_id', $taxonomy->id)
                    ->whereRaw('LOWER(name) = ?', ['slider'])
                    ->first();

                if ($sliderTerm) {
                    $heroPost = \App\Models\Post::query()
                        ->where('type', 'static_post')
                        ->whereRaw('LOWER(status) = ?', ['published'])
                        ->whereHas('terms', fn($q) => $q->where('terms.id', $sliderTerm->id))
                        ->orderByDesc('published_at')
                        ->orderByDesc('id')
                        ->first();

                    if ($heroPost) {
                        $heroTitle = (string) ($heroPost->title ?? '');
                        $heroSubtitle =
                            (string) ($heroPost->content_html ??
                                (data_get($heroPost->content_json ?? [], 'html') ?? ($heroPost->excerpt ?? '')));

                        // ✅ Get Media model for responsive variants (cms_picture)
                        $img = null;
                        if (method_exists($heroPost, 'featuredMediaPivot')) {
                            $img = $heroPost->featuredMediaPivot()->first();
                        }
                        if (!$img && method_exists($heroPost, 'featuredMedia')) {
                            $img = $heroPost->featuredMedia()->first();
                        }

                        if ($img instanceof \App\Models\Media) {
                            $heroMedia = $img;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $heroTitle = '';
            $heroSubtitle = '';
            $heroMedia = null;
        }

        $showHero = trim($heroTitle) !== '' || trim($heroSubtitle) !== '' || $heroMedia instanceof \App\Models\Media;

        // =========================
        // PAGE CONTENT (filters + shortcodes)
        // =========================
        $raw = (string) ($post->content_html ?? (data_get($post->content_json ?? [], 'html') ?? ''));
        $filtered = $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, $raw, ['post' => $post]);

        try {
            $contentHtml = function_exists('do_shortcode') ? do_shortcode($filtered, ['post' => $post]) : $filtered;
        } catch (\Throwable $e) {
            $contentHtml = $filtered . "\n<!-- shortcode error: " . e($e->getMessage()) . ' -->';
        }

        $hasShortcode = str_contains($raw, '[') || str_contains($filtered, '[');
    @endphp

    {{-- HERO (ONLY if slider term + published static post exists) --}}
    @if ($showHero)
        <section class="bg-white mt-[20px]">
            <div class="cms-container mx-auto px-4 py-10">
                <div class="bg-gray-50 p-12">

                    {{-- Load the font (required, otherwise browser fallback font shows) --}}
                    <link rel="preconnect" href="https://fonts.googleapis.com">
                    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
                    <link href="https://fonts.googleapis.com/css2?family=Ropa+Sans&display=swap" rel="stylesheet">

                    <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-0">
                        {{-- IMAGE (mobile top) --}}
                        <div class="order-1 lg:order-2">
                            @if ($heroMedia instanceof \App\Models\Media && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                <div class="w-full">
                                    {!! cms_picture(
                                        $heroMedia,
                                        [
                                            'alt' => e($heroTitle),
                                            // mimic your old sizing behavior
                                            'class' => 'w-full h-64 sm:h-80 lg:h-[420px] object-cover',
                                            // ✅ responsive hint: full width on mobile, half on desktop
                                            'sizes' => '(max-width: 1024px) 100vw, 50vw',
                                            'loading' => 'lazy',
                                            'decoding' => 'async',
                                        ],
                                        // base variant
                                        'large',
                                        // srcset candidates
                                        ['medium', 'medium_large', 'large'],
                                    ) !!}
                                </div>
                            @endif
                        </div>

                        {{-- TEXT --}}
                        <div class="order-2 lg:order-1">
                            <div class="py-10 lg:py-0 lg:pr-14 slider-text">
                                @if (trim($heroTitle) !== '')
                                    <h1>{{ $heroTitle }}</h1>
                                @endif

                                @if (trim($heroSubtitle) !== '')
                                    <div class="mt-6 leading-relaxed text-gray-700 font-semibold italic">
                                        {!! $heroSubtitle !!}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <style>
                        .slider-text h1,
                        .slider-text h2 {
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
                    </style>
                </div>
            </div>
        </section>
    @endif

    {{-- PAGE CONTENT --}}
    <section class="bg-white ">
        <div class="cms-container mx-auto px-4 pb-12 my-10">
            @if ($hasShortcode)
                <div class="cms-content">
                    {!! $contentHtml !!}
                </div>
            @else
                <div class="prose prose-slate max-w-none">
                    {!! $contentHtml !!}
                </div>
            @endif
        </div>
    </section>
@endsection

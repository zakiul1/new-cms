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
        <section class="mt-[20px] bg-white">
            <div class="cms-container mx-auto px-4 py-10">
                <div class="bg-gray-50 p-12">
                    <div class="grid grid-cols-1 items-center gap-0 lg:grid-cols-2">
                        {{-- IMAGE (mobile top) --}}
                        <div class="order-1 lg:order-2">
                            @if ($heroMedia instanceof \App\Models\Media && method_exists($heroMedia, 'isImage') && $heroMedia->isImage())
                                <div class="w-full">
                                    {!! cms_picture(
                                        $heroMedia,
                                        [
                                            'alt' => e($heroTitle),
                                            'class' => 'w-full h-64 sm:h-80 lg:h-[420px] object-cover',
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
                        </div>

                        {{-- TEXT --}}
                        <div class="order-2 lg:order-1">
                            <div class="slider-text py-10 lg:py-0 lg:pr-14">
                                @if (trim($heroTitle) !== '')
                                    <h1>{{ $heroTitle }}</h1>
                                @endif

                                @if (trim($heroSubtitle) !== '')
                                    <div class="slider-subtitle mt-6">
                                        {!! $heroSubtitle !!}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <style>
                        .slider-text h1,
                        .slider-text h2 {
                            font-family: var(--cms-heading-font-family);
                            font-size: var(--cms-h1-font-size);
                            font-weight: var(--cms-heading-font-weight);
                            text-transform: var(--cms-heading-text-transform);
                            line-height: var(--cms-heading-line-height);
                            letter-spacing: var(--cms-heading-letter-spacing);
                            color: var(--cms-heading-color);
                            text-align: left;
                            max-width: 370px;
                            margin-bottom: 15px;
                        }

                        .slider-subtitle,
                        .slider-subtitle p,
                        .slider-subtitle li {
                            font-family: var(--cms-body-font-family);
                            font-size: var(--cms-body-font-size);
                            font-weight: var(--cms-body-font-weight);
                            line-height: var(--cms-body-line-height);
                            letter-spacing: var(--cms-body-letter-spacing);
                            color: var(--cms-body-color);
                            font-style: italic;
                        }
                    </style>
                </div>
            </div>
        </section>
    @endif

    {{-- PAGE CONTENT --}}
    <section class="bg-white">
        <div class="cms-container mx-auto my-10 px-4 pb-12">
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

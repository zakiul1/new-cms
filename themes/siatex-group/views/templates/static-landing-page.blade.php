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
        $heroImageUrl = '';

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

                        if (is_object($img)) {
                            if (method_exists($img, 'url')) {
                                $heroImageUrl = (string) $img->url();
                            } elseif (property_exists($img, 'url') && is_string($img->url)) {
                                $heroImageUrl = (string) $img->url;
                            } elseif (property_exists($img, 'path') && is_string($img->path)) {
                                $heroImageUrl = (string) $img->path;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $heroTitle = '';
            $heroSubtitle = '';
            $heroImageUrl = '';
        }

        $showHero = trim($heroTitle) !== '' || trim($heroSubtitle) !== '' || trim($heroImageUrl) !== '';

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
        <section class="bg-white">
            {{-- Outer spacing similar to screenshot (more airy, no rounded card) --}}
            <div class="cms-container mx-auto px-4 py-10">
                <div class="bg-gray-50 p-12">
                    {{-- Mobile rule: image always on top (we always render first) --}}
                    {{-- Mobile: image first, Desktop: text left + image right --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 items-center gap-0">

                        {{-- IMAGE (mobile top) --}}
                        <div class="order-1 lg:order-2">
                            @if (trim($heroImageUrl) !== '')
                                <div class="w-full">
                                    <img src="{{ $heroImageUrl }}" alt="{{ e($heroTitle) }}"
                                        class="w-full h-64 sm:h-80 lg:h-[420px] object-cover" loading="lazy"
                                        decoding="async" />
                                </div>
                            @endif
                        </div>

                        {{-- TEXT --}}
                        <div class="order-2 lg:order-1">
                            {{-- This padding/spacing matches your screenshot better --}}
                            <div class="py-10 lg:py-0 lg:pr-14">
                                @if (trim($heroTitle) !== '')
                                    <h1
                                        class="text-[42px] sm:text-[52px] lg:text-[58px] leading-[1.08] font-extrabold tracking-tight text-gray-600 uppercase">
                                        {{ $heroTitle }}
                                    </h1>
                                @endif

                                @if (trim($heroSubtitle) !== '')
                                    <div
                                        class="mt-6 text-[15px] sm:text-[16px] lg:text-[17px] leading-relaxed text-gray-700 font-semibold italic">
                                        {!! $heroSubtitle !!}
                                    </div>
                                @endif
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- PAGE CONTENT --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 pb-12">
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

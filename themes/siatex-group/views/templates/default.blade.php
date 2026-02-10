{{-- themes/<your-theme>/views/templates/default.blade.php --}}
@extends('layouts.app')

@section('content')
    @php
        /** @var \App\Models\Post $post */

        $hooks = app(\App\Cms\Hooks\Hooks::class);

        // 1) Slider Title
        $sliderTitle =
            trim((string) data_get($post->meta_json, 'slider.title', '')) !== ''
                ? (string) data_get($post->meta_json, 'slider.title')
                : (string) ($post->title ?? '');

        // 2) Product Images (role=product)
        $productMedia = collect();

        if ($post && method_exists($post, 'mediaPivot')) {
            $productMedia = $post->mediaPivot()->wherePivot('role', 'product')->orderBy('post_media.sort_order')->get();
        }

        $slides = $productMedia
            ->map(function ($m) {
                if (is_object($m) && method_exists($m, 'url')) {
                    return (string) $m->url();
                }
                if (is_object($m) && property_exists($m, 'url') && is_string($m->url)) {
                    return (string) $m->url;
                }
                if (is_object($m) && property_exists($m, 'path') && is_string($m->path)) {
                    return (string) $m->path;
                }
                return null;
            })
            ->filter(fn($u) => is_string($u) && trim($u) !== '')
            ->values();

        // 3) Duotone
        $duotoneHex = (string) data_get($post->meta_json, 'duotone.color', '');
        $duotoneOpacity = (int) data_get($post->meta_json, 'duotone.opacity', 0);
        $duotoneOpacity = max(0, min(100, $duotoneOpacity));
        $duotoneAlpha = $duotoneOpacity / 100;

        $duotoneRgb = null;
        $hex = ltrim(trim($duotoneHex), '#');
        if (preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $duotoneRgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        }

        // 4) Content: filters + shortcodes
        $raw = (string) data_get($post->content_json, 'html', (string) ($post->content ?? ''));
        $filtered = $hooks->applyFilters(\App\Cms\Hooks\HookPoints::CMS_THE_CONTENT, $raw, ['post' => $post]);

        try {
            $contentHtml = function_exists('do_shortcode') ? do_shortcode($filtered, ['post' => $post]) : $filtered;
        } catch (\Throwable $e) {
            $contentHtml = $filtered . "\n<!-- shortcode error: " . e($e->getMessage()) . ' -->';
        }

        $hasShortcode = str_contains($raw, '[') || str_contains($filtered, '[');
    @endphp

    {{-- HERO / SLIDER --}}
    @if ($slides->count() > 0)
        <section class="cms-hero">
            <div class="cms-hero__wrap" data-slider-count="{{ $slides->count() }}">
                <div class="cms-hero__track" id="cmsHeroTrack">
                    @foreach ($slides as $src)
                        <div class="cms-hero__slide">
                            <img src="{{ $src }}" alt="{{ e($sliderTitle) }}" class="cms-hero__img" loading="lazy"
                                decoding="async" />
                            @if ($duotoneRgb && $duotoneAlpha > 0)
                                <div class="cms-hero__overlay"
                                    style="background: rgba({{ $duotoneRgb[0] }}, {{ $duotoneRgb[1] }}, {{ $duotoneRgb[2] }}, {{ $duotoneAlpha }});">
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="cms-hero__content">
                    <h1 class="cms-hero__title">{{ $sliderTitle }}</h1>


                </div>

                {{--
                    ✅ Navigation icons hidden (commented)
                    If you want back later, uncomment below:
                    <button class="cms-hero__btn cms-hero__btn--prev" ...>‹</button>
                    <button class="cms-hero__btn cms-hero__btn--next" ...>›</button>
                --}}

                {{-- ✅ Indicator: flat bars, gray --}}
                @if ($slides->count() > 1)
                    <div class="cms-hero__bars" id="cmsHeroDots" aria-label="Slider indicators">
                        @for ($i = 0; $i < $slides->count(); $i++)
                            <button type="button" class="cms-hero__bar" data-dot="{{ $i }}"
                                aria-label="Go to slide {{ $i + 1 }}"></button>
                        @endfor
                    </div>
                @endif
            </div>
        </section>
    @else
        <section class="cms-hero cms-hero--empty">
            <div class="cms-hero__content">
                <h1 class="cms-hero__title">{{ $sliderTitle }}</h1>
            </div>
        </section>
    @endif

    {{-- CONTENT (shortcodes supported) --}}
    <section class="bg-white">
        <div class="cms-container mx-auto px-4 py-10">
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

    <style>
        .cms-hero {
            position: relative;
        }

        .cms-hero__wrap {
            position: relative;
            width: 100%;
            height: clamp(380px, 70vh, 720px);
            overflow: hidden;
            background: #0b0b0b;
        }

        .cms-hero__track {
            height: 100%;
            display: flex;
            will-change: transform;
            transform: translate3d(0, 0, 0);
        }

        .cms-hero__slide {
            position: relative;
            min-width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .cms-hero__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transform: translate3d(0, 0, 0);
        }

        .cms-hero__overlay {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .cms-hero__content {
            position: absolute;
            inset: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            text-align: center;
            pointer-events: none;
        }

        .cms-hero__title {
            margin: 0;
            color: #fff;
            font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.03;
            text-shadow: 0 6px 24px rgba(0, 0, 0, 0.45);
            font-size: clamp(40px, 6vw, 92px);
        }

        .cms-hero__scroll {
            position: absolute;
            bottom: 22px;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            letter-spacing: 0.25em;
            opacity: 0.9;
        }

        .cms-hero--empty .cms-hero__content {
            position: relative;
            height: clamp(220px, 40vh, 360px);
            background: #0b0b0b;
        }

        /* ✅ Flat gray indicators */
        .cms-hero__bars {
            position: absolute;
            left: 50%;
            bottom: 16px;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 6;
        }

        .cms-hero__bar {
            width: 34px;
            height: 4px;
            border-radius: 999px;
            border: 0;
            background: rgba(220, 220, 220, 0.55);
            cursor: pointer;
            padding: 0;
        }

        .cms-hero__bar.is-active {
            background: rgba(220, 220, 220, 0.95);
        }

        @media (prefers-reduced-motion: reduce) {
            .cms-hero__track {
                transition: none !important;
            }
        }
    </style>

    @if ($slides->count() > 1)
        <script>
            (function() {
                const wrap = document.querySelector('.cms-hero__wrap');
                const track = document.getElementById('cmsHeroTrack');
                const dotsWrap = document.getElementById('cmsHeroDots');
                if (!wrap || !track) return;

                const total = parseInt(wrap.getAttribute('data-slider-count') || '0', 10);
                if (!total || total < 2) return;

                let index = 0;
                let timer = null;
                let isAnimating = false;

                function goTo(i) {
                    if (isAnimating) return;

                    index = (i + total) % total;
                    isAnimating = true;

                    track.style.transition = 'transform 550ms ease';
                    track.style.transform = `translate3d(${-index * 100}%, 0, 0)`;

                    setActiveDot();

                    window.setTimeout(() => {
                        isAnimating = false;
                    }, 580);
                }

                function next() {
                    goTo(index + 1);
                }

                function setActiveDot() {
                    if (!dotsWrap) return;
                    dotsWrap.querySelectorAll('.cms-hero__bar').forEach((btn) => {
                        const d = parseInt(btn.getAttribute('data-dot') || '0', 10);
                        btn.classList.toggle('is-active', d === index);
                    });
                }

                function startAuto() {
                    stopAuto();
                    timer = window.setInterval(next, 4500);
                }

                function stopAuto() {
                    if (timer) window.clearInterval(timer);
                    timer = null;
                }

                // Bars click
                if (dotsWrap) {
                    dotsWrap.addEventListener('click', (e) => {
                        const t = e.target;
                        if (!(t instanceof HTMLElement)) return;
                        if (!t.classList.contains('cms-hero__bar')) return;

                        const d = parseInt(t.getAttribute('data-dot') || '0', 10);
                        if (!Number.isFinite(d)) return;

                        stopAuto();
                        goTo(d);
                        startAuto();
                    });
                }

                // Pause on hover
                wrap.addEventListener('mouseenter', stopAuto);
                wrap.addEventListener('mouseleave', startAuto);

                // Init
                setActiveDot();
                startAuto();
            })();
        </script>
    @endif
@endsection

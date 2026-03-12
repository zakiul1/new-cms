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

        $productMedia = $productMedia
            ->filter(fn($m) => $m instanceof \App\Models\Media)
            ->unique(fn($m) => $m->id ?? spl_object_hash($m))
            ->values();

        if ($productMedia->isNotEmpty()) {
            $productMedia->loadMissing('variantRecords');
        }

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
    @if ($productMedia->count() > 0)
        <section class="cms-hero cms-container">
            <div class="cms-hero__wrap" data-slider-count="{{ $productMedia->count() }}">
                <div class="cms-hero__track" id="cmsHeroTrack">
                    @foreach ($productMedia as $index => $media)
                        @php
                            $isFirstSlide = $index === 0;
                        @endphp

                        <div class="cms-hero__slide">
                            {!! cms_picture(
                                $media,
                                [
                                    'alt' => e($sliderTitle),
                                    'class' => 'cms-hero__img',
                                    'sizes' => '100vw',
                                    'loading' => $isFirstSlide ? 'eager' : 'lazy',
                                    'fetchpriority' => $isFirstSlide ? 'high' : 'auto',
                                    'decoding' => 'async',
                                ],
                                'hero_sm',
                                ['thumb', 'small', 'hero_sm', 'large'],
                            ) !!}

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

                @if ($productMedia->count() > 1)
                    <div class="cms-hero__bars" id="cmsHeroDots" aria-label="Slider indicators">
                        @for ($i = 0; $i < $productMedia->count(); $i++)
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
            font-family: var(--cms-heading-font-family);
            font-size: var(--cms-h1-font-size);
            font-weight: var(--cms-heading-font-weight);
            text-transform: var(--cms-heading-text-transform);
            line-height: var(--cms-heading-line-height);
            letter-spacing: var(--cms-heading-letter-spacing);
            text-shadow: 0 6px 24px rgba(0, 0, 0, 0.45);
        }

        .cms-hero--empty .cms-hero__content {
            position: relative;
            height: clamp(220px, 40vh, 360px);
            background: #0b0b0b;
        }

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

        @media (max-width: 768px) {
            .cms-hero.cms-container {
                padding-left: 0 !important;
                padding-right: 0 !important;
            }

            .cms-hero__wrap {
                height: clamp(240px, 52vh, 420px);
            }

            .cms-hero__content {
                padding: 16px;
            }

            .cms-hero__title {
                font-size: var(--cms-h1-font-size);
                line-height: var(--cms-heading-line-height);
                letter-spacing: var(--cms-heading-letter-spacing);
            }

            .cms-hero__bars {
                bottom: 12px;
                gap: 8px;
            }

            .cms-hero__bar {
                width: 26px;
                height: 4px;
            }

            .cms-container.mx-auto.px-4.py-10 {
                padding-top: 24px;
                padding-bottom: 24px;
            }
        }

        @media (max-width: 420px) {
            .cms-hero__wrap {
                height: clamp(220px, 48vh, 360px);
            }

            .cms-hero__title {
                font-size: var(--cms-h1-font-size);
            }

            .cms-hero__bar {
                width: 22px;
            }
        }
    </style>

    @if ($productMedia->count() > 1)
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

                wrap.addEventListener('mouseenter', stopAuto);
                wrap.addEventListener('mouseleave', startAuto);

                setActiveDot();
                startAuto();
            })();
        </script>
    @endif
@endsection

@php
    /**
     * Cinematic Pro (GSAP + Swiper)
     *
     * Requires in <head>:
     *  - Swiper CSS
     *  - GSAP JS
     *  - Swiper JS
     *  - siatex-slider.css + siatex-slider.js
     *
     * This template expects your JS to look for:
     *  - [data-slider][data-mode="cinematic-pro"]
     *  - [data-pro-swiper]
     *  - .si-pro__img inside .swiper-slide
     */

    $height = (string) ($settings['height'] ?? '100vh');
    $bgColor = (string) ($settings['bg_color'] ?? '#000000');

    $title = (string) ($settings['title'] ?? '');
    $subtitle = (string) ($settings['subtitle'] ?? '');

    // autoplay delay (ms)
    $delay = (int) ($settings['delay'] ?? 6500);

    // Cinematic Pro motion settings (used by JS via data-attrs)
    $motionDuration = (float) ($settings['motion_duration'] ?? 6.5); // seconds
    $zoomMin = (float) ($settings['zoom_min'] ?? 1.06);
    $zoomMax = (float) ($settings['zoom_max'] ?? 1.18);
    $pan = (float) ($settings['pan_strength'] ?? 2.0); // percent
    $rotate = (float) ($settings['rotate'] ?? 0.6); // degrees
    $fadeMs = (int) ($settings['fade_ms'] ?? 900); // ms

    // controls (OFF by default for cinematic-pro)
    $showNav = (bool) ($settings['show_navigation'] ?? false);
    $showDots = (bool) ($settings['show_indicators'] ?? false);

    $slideCount = $slides->count();

    // ✅ Overlay look tuning (dynamic from admin)
    // expected range: 0.00 - 0.75
    $overlayOpacity = (float) ($settings['overlay_opacity'] ?? 0.38);
    $overlayOpacity = max(0.0, min(0.75, $overlayOpacity));
@endphp

<div class="si-pro relative w-full overflow-hidden" style="background: {{ $bgColor }}; height: {{ $height }};"
    data-slider data-mode="cinematic-pro" data-delay="{{ $delay }}" data-motion-duration="{{ $motionDuration }}"
    data-zoom-min="{{ $zoomMin }}" data-zoom-max="{{ $zoomMax }}" data-pan="{{ $pan }}"
    data-rotate="{{ $rotate }}" data-fade-ms="{{ $fadeMs }}">
    {{-- Swiper --}}
    <div class="swiper h-full w-full" data-pro-swiper>
        <div class="swiper-wrapper">
            @foreach ($slides as $slide)
                @php
                    $imgUrl = $slide->media?->variantUrl('large') ?: $slide->media?->url();
                @endphp

                <div class="swiper-slide h-full w-full">
                    <div class="si-pro__media relative h-full w-full overflow-hidden">
                        @if ($imgUrl)
                            <img src="{{ $imgUrl }}" alt=""
                                class="si-pro__img absolute inset-0 h-full w-full object-cover select-none"
                                draggable="false" loading="lazy" />
                        @else
                            <div class="absolute inset-0 flex items-center justify-center text-white/70">
                                No image
                            </div>
                        @endif

                        {{-- ✅ Overlay layer (controlled by admin overlay_opacity) --}}
                        <div class="si-pro__overlay pointer-events-none absolute inset-0"
                            style="--si-overlay: {{ $overlayOpacity }};"></div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Optional pagination (off by default) --}}
        @if ($showDots && $slideCount > 1)
            <div class="swiper-pagination" data-pro-dots></div>
        @endif

        {{-- Optional navigation (off by default) --}}
        @if ($showNav && $slideCount > 1)
            <button type="button" class="si-pro__btn si-pro__btn--prev" data-pro-prev aria-label="Previous slide">
                {{-- ✅ SVG Left Arrow --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="si-pro__icon" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
            </button>

            <button type="button" class="si-pro__btn si-pro__btn--next" data-pro-next aria-label="Next slide">
                {{-- ✅ SVG Right Arrow --}}
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="si-pro__icon" fill="none"
                    stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 6l6 6-6 6" />
                </svg>
            </button>
        @endif
    </div>

    {{-- Center content --}}
    <div class="si-pro__content absolute inset-0 z-10 grid place-items-center text-center px-6">
        <div class="max-w-6xl">
            @if ($title !== '')
                <h2 class="si-pro__title">
                    {!! nl2br(e($title)) !!}
                </h2>
            @endif

            {{--     @if ($subtitle !== '')
                <div class="si-pro__subtitleWrap">
                    <p class="si-pro__subtitle">
                        {!! nl2br(e($subtitle)) !!}
                    </p>
                </div>
            @endif --}}
        </div>
    </div>
</div>

<style>
    /* --- Image look: slightly “overlaid” like your screenshot --- */
    .si-pro__img {
        filter: brightness(.92) contrast(1.06) saturate(1.08);
        transform: translateZ(0);
        backface-visibility: hidden;
    }

    /* ✅ Overlay darkness is controlled by --si-overlay */
    .si-pro__overlay {
        background: linear-gradient(to bottom,
                rgba(0, 0, 0, calc(var(--si-overlay, .38) + .06)),
                rgba(0, 0, 0, var(--si-overlay, .38)));
    }

    /* --- Title/subtitle typography: match the “big bold white” screenshot --- */
    .si-pro__title {
        margin: 0;
        color: #fff;
        font-weight: 900;
        letter-spacing: -0.015em;
        line-height: 0.98;
        font-size: clamp(44px, 6.2vw, 108px);
        text-shadow: 0 14px 42px rgba(0, 0, 0, .50);
    }

    .si-pro__subtitleWrap {
        margin-top: clamp(10px, 2.2vw, 26px);
    }

    .si-pro__subtitle {
        margin: 0;
        color: rgba(255, 255, 255, 0.95);
        font-weight: 900;
        letter-spacing: -0.01em;
        line-height: 1.02;
        font-size: clamp(34px, 5.0vw, 92px);
        text-shadow: 0 14px 42px rgba(0, 0, 0, .45);
    }

    .si-pro__title,
    .si-pro__subtitle {
        font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, "Noto Sans", "Helvetica Neue", sans-serif;
        font-stretch: condensed;
    }

    /* Optional: improve Swiper pagination dots visibility if enabled */
    .si-pro .swiper-pagination-bullet {
        opacity: .55;
    }

    .si-pro .swiper-pagination-bullet-active {
        opacity: 1;
    }

    /* Optional nav buttons if enabled */
    .si-pro__btn {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        z-index: 20;
        width: 48px;
        height: 48px;
        border-radius: 999px;
        border: 0;
        cursor: pointer;
        background: rgba(255, 255, 255, .88);
        box-shadow: 0 12px 30px rgba(0, 0, 0, .25);
        display: flex;
        align-items: center;
        justify-content: center;
        user-select: none;
        padding: 0;
    }

    .si-pro__btn--prev {
        left: 14px;
    }

    .si-pro__btn--next {
        right: 14px;
    }

    /* ✅ SVG icon sizing/color */
    .si-pro__icon {
        width: 24px;
        height: 24px;
        color: #111827;
        /* gray-900 */
        display: block;
    }
</style>

{{-- resources/views/shortcodes/logo-grid.blade.php --}}

@php
    /**
     * Variables expected from [logo] shortcode:
     *  - $items     : Collection|array of Media items
     *  - $column    : int (desktop columns) default 4  (used for GRID mode)
     *  - $mobile    : int (mobile columns) default 2  (used for GRID mode, and per-view on mobile in SLIDER mode)
     *  - $showTitle : bool (show media title under image)
     *  *  - $style     : string 'square'|'round' (default 'square')
     *  - $class     : string wrapper class (optional)
     *  - $slider    : bool if true => swipe carousel (6 per view on desktop, $mobile per view on mobile)
     */

    $items = $items ?? collect();
    if (is_array($items)) {
        $items = collect($items);
    }

    $column = max(1, min(12, (int) ($column ?? 4)));
    $mobile = max(1, min(6, (int) ($mobile ?? 2)));

    $showTitle = (bool) ($showTitle ?? false);
    $slider = (bool) ($slider ?? false);

    $style = strtolower(trim((string) ($style ?? 'square')));
    if ($style === 'squire') {
        $style = 'square';
    }
    $style = in_array($style, ['square', 'round'], true) ? $style : 'square';

    $class = trim((string) ($class ?? 'logo_grid'));
    $class = $class !== '' ? $class : 'logo_grid';

    $scopeClass =
        'logo-scope-' .
        substr(
            md5(
                $class .
                    '|' .
                    $style .
                    '|' .
                    $column .
                    '|' .
                    $mobile .
                    '|' .
                    ($slider ? '1' : '0') .
                    '|' .
                    uniqid('', true),
            ),
            0,
            10,
        );

    $rootClassAttr = trim($class . ' ' . $scopeClass);

    $gridStyle = "--lg-cols: {$column}; --sm-cols: {$mobile};";
    $sliderStyle = "--per-view-lg: 6; --per-view-sm: {$mobile};";
    $carouselId = 'logo-carousel-' . substr(md5($scopeClass), 0, 10);

    $mobileSizeValue = match ($mobile) {
        1 => '100vw',
        2 => '50vw',
        3 => '33.33vw',
        4 => '25vw',
        5 => '20vw',
        6 => '16.66vw',
        default => '50vw',
    };

    $desktopGridSizeValue = match ($column) {
        1 => '100vw',
        2 => '50vw',
        3 => '33.33vw',
        4 => '25vw',
        5 => '20vw',
        6 => '16.66vw',
        7 => '14.28vw',
        8 => '12.5vw',
        9 => '11.11vw',
        10 => '10vw',
        11 => '9.09vw',
        12 => '8.33vw',
        default => '25vw',
    };

    $gridImageSizes = '(max-width: 767px) ' . $mobileSizeValue . ', ' . $desktopGridSizeValue;
    $sliderImageSizes = '(max-width: 767px) ' . $mobileSizeValue . ', 16.66vw';
@endphp

@if ($items->count() > 0)
    <div class="{{ $rootClassAttr }}">
        @if ($slider)
            {{-- SLIDER MODE --}}
            <div class="logo-carousel-wrap">
                <div class="logo-carousel" id="{{ $carouselId }}" style="{{ $sliderStyle }}">
                    @foreach ($items as $item)
                        @php
                            $title = trim((string) data_get($item, 'title', ''));
                            $alt = $title !== '' ? $title : 'logo';

                            $isMediaImage = false;
                            try {
                                $isMediaImage =
                                    is_object($item) &&
                                    method_exists($item, 'isImage') &&
                                    method_exists($item, 'url') &&
                                    $item->isImage();
                            } catch (\Throwable $e) {
                                $isMediaImage = false;
                            }

                            $imgUrl = '';
                            try {
                                if (!$isMediaImage) {
                                    if (is_object($item) && method_exists($item, 'url')) {
                                        $imgUrl = (string) $item->url();
                                    } elseif (
                                        is_object($item) &&
                                        property_exists($item, 'url') &&
                                        is_string($item->url)
                                    ) {
                                        $imgUrl = (string) $item->url;
                                    } elseif (
                                        is_object($item) &&
                                        property_exists($item, 'path') &&
                                        is_string($item->path)
                                    ) {
                                        $imgUrl = (string) $item->path;
                                    } elseif (is_string(data_get($item, 'url'))) {
                                        $imgUrl = (string) data_get($item, 'url');
                                    } elseif (is_string(data_get($item, 'path'))) {
                                        $imgUrl = (string) data_get($item, 'path');
                                    }
                                }
                            } catch (\Throwable $e) {
                                $imgUrl = '';
                            }

                            if (!$isMediaImage && trim($imgUrl) === '') {
                                continue;
                            }
                        @endphp

                        <div class="logo-slide">
                            <div class="logo-item logo-item--{{ $style }}">
                                <div class="logo-imgwrap logo-imgwrap--{{ $style }}">
                                    @if ($isMediaImage && function_exists('cms_picture'))
                                        {!! cms_picture(
                                            $item,
                                            [
                                                'alt' => $alt,
                                                'class' => $style === 'round' ? 'h-full w-full object-contain' : 'h-auto w-full object-cover',
                                                'sizes' => $sliderImageSizes,
                                                'loading' => 'lazy',
                                                'fetchpriority' => 'low',
                                                'decoding' => 'async',
                                                'draggable' => 'false',
                                            ],
                                            'medium',
                                            ['thumb', 'medium', 'medium_large'],
                                        ) !!}
                                    @else
                                        <img src="{{ $imgUrl }}" alt="{{ e($alt) }}" loading="lazy"
                                            fetchpriority="low" decoding="async" draggable="false" />
                                    @endif
                                </div>

                                @if ($showTitle && $title !== '')
                                    <div class="logo-title logo-title--{{ $style }}">
                                        {{ $title }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            {{-- GRID MODE --}}
            <div class="logo-grid"
                style="{{ $gridStyle }} grid-template-columns: repeat(var(--sm-cols), minmax(0, 1fr));">
                @foreach ($items as $item)
                    @php
                        $title = trim((string) data_get($item, 'title', ''));
                        $alt = $title !== '' ? $title : 'logo';

                        $isMediaImage = false;
                        try {
                            $isMediaImage =
                                is_object($item) &&
                                method_exists($item, 'isImage') &&
                                method_exists($item, 'url') &&
                                $item->isImage();
                        } catch (\Throwable $e) {
                            $isMediaImage = false;
                        }

                        $imgUrl = '';
                        try {
                            if (!$isMediaImage) {
                                if (is_object($item) && method_exists($item, 'url')) {
                                    $imgUrl = (string) $item->url();
                                } elseif (is_object($item) && property_exists($item, 'url') && is_string($item->url)) {
                                    $imgUrl = (string) $item->url;
                                } elseif (
                                    is_object($item) &&
                                    property_exists($item, 'path') &&
                                    is_string($item->path)
                                ) {
                                    $imgUrl = (string) $item->path;
                                } elseif (is_string(data_get($item, 'url'))) {
                                    $imgUrl = (string) data_get($item, 'url');
                                } elseif (is_string(data_get($item, 'path'))) {
                                    $imgUrl = (string) data_get($item, 'path');
                                }
                            }
                        } catch (\Throwable $e) {
                            $imgUrl = '';
                        }

                        if (!$isMediaImage && trim($imgUrl) === '') {
                            continue;
                        }
                    @endphp

                    <div class="logo-item logo-item--{{ $style }}">
                        <div class="logo-imgwrap logo-imgwrap--{{ $style }}">
                            @if ($isMediaImage && function_exists('cms_picture'))
                                {!! cms_picture(
                                    $item,
                                    [
                                        'alt' => $alt,
                                        'class' => $style === 'round' ? 'h-full w-full object-contain' : 'h-auto w-full object-cover',
                                        'sizes' => $gridImageSizes,
                                        'loading' => 'lazy',
                                        'fetchpriority' => 'low',
                                        'decoding' => 'async',
                                    ],
                                    'medium',
                                    ['thumb', 'medium', 'medium_large'],
                                ) !!}
                            @else
                                <img src="{{ $imgUrl }}" alt="{{ e($alt) }}" loading="lazy"
                                    fetchpriority="low" decoding="async" />
                            @endif
                        </div>

                        @if ($showTitle && $title !== '')
                            <div class="logo-title logo-title--{{ $style }}">
                                {{ $title }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        <style>
            .{{ $scopeClass }} .logo-item {
                width: 100%;
                display: flex;
                flex-direction: column;
                align-items: center;
                background: transparent;
                padding: 0;
                user-select: none;
            }

            .{{ $scopeClass }} .logo-imgwrap {
                transition: transform 220ms ease;
                will-change: transform;
            }

            .{{ $scopeClass }} .logo-item:hover .logo-imgwrap {
                transform: scale(1.06);
            }

            .{{ $scopeClass }} .logo-item--square {
                background: #f9f9f9;
                padding: 5px;
            }

            .{{ $scopeClass }} .logo-imgwrap--square {
                width: 100%;
                background: transparent;
                border-radius: 0;
                overflow: hidden;
                display: block;
            }

            .{{ $scopeClass }} .logo-imgwrap--square img,
            .{{ $scopeClass }} .logo-imgwrap--square picture img {
                width: 100%;
                height: auto;
                display: block;
                object-fit: cover;
                -webkit-user-drag: none;
                user-drag: none;
                user-select: none;
                pointer-events: none;
            }

            .{{ $scopeClass }} .logo-item--round {
                background: transparent !important;
                padding: 0 !important;
            }

            .{{ $scopeClass }} .logo-imgwrap--round {
                width: 140px;
                height: 140px;
                border-radius: 9999px;
                overflow: hidden;
                background: #f2f2f2;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .{{ $scopeClass }} .logo-imgwrap--round img,
            .{{ $scopeClass }} .logo-imgwrap--round picture img {
                width: 100%;
                height: 100%;
                object-fit: contain;
                display: block;
                -webkit-user-drag: none;
                user-drag: none;
                user-select: none;
                pointer-events: none;
            }

            .{{ $scopeClass }} .logo-title {
                margin-top: 14px;
                font-size: 16px;
                line-height: 1.2;
                color: #1f5f99;
                text-align: center;
                font-weight: 500;
                user-select: none;
            }

            .{{ $scopeClass }} .logo-title--round {
                font-size: 15px;
            }

            .{{ $scopeClass }} .logo-grid {
                display: grid;
                gap: 20px;
                align-items: center;
                justify-items: center;
                margin-top: 60px;
            }

            @media (min-width: 768px) {
                .{{ $scopeClass }} .logo-grid {
                    grid-template-columns: repeat(var(--lg-cols), minmax(0, 1fr)) !important;
                }
            }

            .{{ $scopeClass }} .logo-carousel-wrap {
                margin-top: 20px;
                background: #ffffff;
                padding: 12px 0;
            }

            .{{ $scopeClass }} .logo-carousel {
                display: flex;
                gap: 22px;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scroll-behavior: auto;
                scrollbar-width: none;
                cursor: grab;
                padding: 12px;
                background: #f9fafb;
            }

            .{{ $scopeClass }} .logo-carousel::-webkit-scrollbar {
                display: none;
            }

            .{{ $scopeClass }} .logo-carousel.is-dragging {
                cursor: grabbing;
            }

            .{{ $scopeClass }} .logo-slide {
                flex: 0 0 auto;
                width: calc((100% - (22px * (var(--per-view-sm) - 1))) / var(--per-view-sm));
            }

            @media (min-width: 768px) {
                .{{ $scopeClass }} .logo-slide {
                    width: calc((100% - (22px * (var(--per-view-lg) - 1))) / var(--per-view-lg));
                }
            }

            .{{ $scopeClass }} .logo-carousel .logo-item--square {
                background: transparent;
                padding: 0;
            }

            .{{ $scopeClass }} .logo-carousel .logo-imgwrap--square {
                background: #efefef;
            }

            @media (max-width: 767px) {
                .{{ $scopeClass }} .logo-grid {
                    gap: 16px;
                    margin-top: 35px;
                }

                .{{ $scopeClass }} .logo-carousel-wrap {
                    margin-top: 35px;
                }

                .{{ $scopeClass }} .logo-carousel {
                    gap: 16px;
                    padding: 10px;
                }

                .{{ $scopeClass }} .logo-imgwrap--round {
                    width: 110px;
                    height: 110px;
                }

                .{{ $scopeClass }} .logo-title {
                    font-size: 14px;
                }
            }
        </style>

        @if ($slider)
            <script>
                (function() {
                    const el = document.getElementById(@json($carouselId));
                    if (!el) return;
                    if (el.dataset.dragBound === '1') return;
                    el.dataset.dragBound = '1';

                    let isDown = false;
                    let startX = 0;
                    let lastX = 0;
                    let lastTime = 0;
                    let velocity = 0;
                    let rafId = null;

                    const now = () => performance.now();

                    const getX = (e) => {
                        if (e.touches && e.touches.length) return e.touches[0].clientX;
                        return e.clientX;
                    };

                    const stopInertia = () => {
                        if (rafId) cancelAnimationFrame(rafId);
                        rafId = null;
                    };

                    const runInertia = () => {
                        stopInertia();
                        const step = () => {
                            velocity *= 0.95;

                            if (Math.abs(velocity) < 0.05) {
                                stopInertia();
                                return;
                            }

                            el.scrollLeft -= velocity * 16;
                            rafId = requestAnimationFrame(step);
                        };
                        rafId = requestAnimationFrame(step);
                    };

                    const onDown = (e) => {
                        isDown = true;
                        el.classList.add('is-dragging');

                        stopInertia();

                        startX = getX(e);
                        lastX = startX;
                        lastTime = now();
                        velocity = 0;
                    };

                    const onMove = (e) => {
                        if (!isDown) return;
                        e.preventDefault();

                        const x = getX(e);
                        const t = now();

                        const dx = x - lastX;
                        const dt = Math.max(1, t - lastTime);

                        el.scrollLeft -= dx;
                        velocity = (dx / dt) * 60;

                        lastX = x;
                        lastTime = t;
                    };

                    const onUp = () => {
                        if (!isDown) return;
                        isDown = false;
                        el.classList.remove('is-dragging');
                        runInertia();
                    };

                    el.addEventListener('mousedown', onDown);
                    window.addEventListener('mousemove', onMove, {
                        passive: false
                    });
                    window.addEventListener('mouseup', onUp);

                    el.addEventListener('touchstart', onDown, {
                        passive: true
                    });
                    el.addEventListener('touchmove', onMove, {
                        passive: false
                    });
                    el.addEventListener('touchend', onUp);
                    el.addEventListener('touchcancel', onUp);

                    el.addEventListener('mouseleave', onUp);

                    el.addEventListener('wheel', function(e) {
                        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) return;
                        e.preventDefault();
                        el.scrollLeft += e.deltaY;
                    }, {
                        passive: false
                    });
                })();
            </script>
        @endif
    </div>
@endif

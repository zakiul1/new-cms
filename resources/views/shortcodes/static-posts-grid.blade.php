{{-- resources/views/shortcodes/static-posts-grid.blade.php --}}

@php
    /**
     * Expected variables passed from shortcode:
     *  - $items  : Collection|array of StaticPost items
     *  - $column : int (desktop columns)
     *  - $mobile : int (mobile columns)
     *  - $img    : bool (show image or not)
     *  - $class  : string (wrapper class)
     *
     * NEW (from CoreShortcodes):
     *  - $topTitle : string (from [sp postid="X"])
     *  - $topContent : string (from [sp postid="X"])
     *  - $topVariant : string (from [sp top="hybrid"])
     *  - $topImages : array of first 2 featured image URLs
     *  - $showLearnMoreBtn : bool (from [sp lmbtn])
     */

    $items = $items ?? collect();
    if (is_array($items)) {
        $items = collect($items);
    }

    $column = (int) ($column ?? 3);
    $mobile = (int) ($mobile ?? 1);
    $img = filter_var($img ?? false, FILTER_VALIDATE_BOOL);

    // wrapper class from shortcode (can contain multiple classes)
    $class = trim((string) ($class ?? 'static_posts'));
    $class = $class !== '' ? $class : 'static_posts';

    $column = max(1, min(12, $column));
    $mobile = max(1, min(4, $mobile));

    $gridStyle = "--sp-cols: {$column}; --sp-cols-mobile: {$mobile};";

    /**
     * ✅ FIX: Prevent duplicate class output
     * - Use first class token to build slug
     * - Append slug only if not already in class
     */
    $firstClass = preg_split('/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY)[0] ?? 'static_posts';
    $firstClass = trim($firstClass) !== '' ? trim($firstClass) : 'static_posts';

    $wrapperClass = \Illuminate\Support\Str::slug($firstClass, '-');

    $rootClassAttr = $class;
    if ($wrapperClass !== '' && !preg_match('/(^|\s)' . preg_quote($wrapperClass, '/') . '(\s|$)/', $rootClassAttr)) {
        $rootClassAttr .= ' ' . $wrapperClass;
    }

    $topTitle = trim((string) ($topTitle ?? ''));
    $topContent = trim((string) ($topContent ?? ''));
    $showTop = $topTitle !== '' || $topContent !== '';

    $topVariant = strtolower(trim((string) ($topVariant ?? '')));
    $topImages = $topImages ?? [];
    if (is_array($topImages)) {
        $topImages = collect($topImages);
    } else {
        $topImages = collect();
    }
    $topImages = $topImages->filter(fn($u) => is_string($u) && trim($u) !== '')->values()->take(2);

    $showLearnMoreBtn = (bool) ($showLearnMoreBtn ?? false);
@endphp

@if ($showTop || $items->count() > 0)
    <div class="{{ $rootClassAttr }}">

        {{-- TOP SECTION --}}
        @if ($showTop)
            @if ($topVariant === 'hybrid')
                @php
                    $img1 = trim((string) ($topImages->get(0) ?? ''));
                    $img2 = trim((string) ($topImages->get(1) ?? ''));
                    $has1 = $img1 !== '';
                    $has2 = $img2 !== '';
                @endphp

                <section class="sp-top-hybrid">
                    <div class="sp-top-hybrid__grid">
                        {{-- LEFT --}}
                        <div class="sp-top-hybrid__left">
                            <div class="sp-top-hybrid__label">Your Tech-pack, Our production</div>

                            @if ($topTitle !== '')
                                <h2 class="sp-top-hybrid__title">{{ $topTitle }}</h2>
                            @endif

                            @if ($topContent !== '')
                                <div class="sp-top-hybrid__content">{!! $topContent !!}</div>
                            @endif
                        </div>

                        {{-- RIGHT --}}
                        <div class="sp-top-hybrid__right">
                            @if ($has1 && $has2)
                                <div class="sp-top-hybrid__stack">
                                    <div class="sp-top-hybrid__img sp-top-hybrid__img--back">
                                        <img src="{{ $img1 }}" alt="" loading="lazy" decoding="async">
                                    </div>
                                    <div class="sp-top-hybrid__img sp-top-hybrid__img--front">
                                        <img src="{{ $img2 }}" alt="" loading="lazy" decoding="async">
                                    </div>
                                </div>
                            @elseif ($has1)
                                <div class="sp-top-hybrid__single">
                                    <img src="{{ $img1 }}" alt="" loading="lazy" decoding="async">
                                </div>
                            @elseif ($has2)
                                <div class="sp-top-hybrid__single">
                                    <img src="{{ $img2 }}" alt="" loading="lazy" decoding="async">
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            @else
                <section class="sp-top">
                    @if ($topTitle !== '')
                        <h2 class="sp-top-title">{{ $topTitle }}</h2>
                    @endif

                    @if ($topContent !== '')
                        <div class="sp-top-content">{!! $topContent !!}</div>
                    @endif
                </section>
            @endif
        @endif

        {{-- GRID SECTION --}}
        @if ($items->count() > 0)
            <div class="sp-grid"
                style="{{ $gridStyle }} grid-template-columns: repeat(var(--sp-cols-mobile), minmax(0, 1fr));">

                @foreach ($items as $item)
                    @php
                        $title = trim((string) data_get($item, 'title', ''));

                        $contentHtml =
                            (string) (data_get($item, 'content_html') ??
                                (data_get($item, 'content_json.html') ??
                                    (data_get($item, 'content') ?? (data_get($item, 'excerpt') ?? ''))));
                        $contentHtml = trim($contentHtml);

                        $imageUrl = '';
                        if ($img) {
                            try {
                                $mObj = null;

                                if (is_object($item) && method_exists($item, 'featuredMediaPivot')) {
                                    $mObj = $item->featuredMediaPivot()->first();
                                }
                                if (!$mObj && is_object($item) && method_exists($item, 'featuredMedia')) {
                                    $mObj = $item->featuredMedia()->first();
                                }

                                if ($mObj) {
                                    if (method_exists($mObj, 'url')) {
                                        $imageUrl = (string) $mObj->url();
                                    } elseif (property_exists($mObj, 'url') && is_string($mObj->url)) {
                                        $imageUrl = (string) $mObj->url;
                                    } elseif (property_exists($mObj, 'path') && is_string($mObj->path)) {
                                        $imageUrl = (string) $mObj->path;
                                    }
                                }
                            } catch (\Throwable $e) {
                                $imageUrl = '';
                            }
                        }

                        $learnMoreUrl = (string) data_get($item, 'meta_json.static_posts.learn_more_url', '');
                        $learnMoreUrl = trim($learnMoreUrl);
                        $learnMoreUrl = $learnMoreUrl !== '' ? $learnMoreUrl : '#';
                    @endphp

                    <article class="sp-item">
                        @if ($img && $imageUrl !== '')
                            <div class="sp-img">
                                <img src="{{ $imageUrl }}" alt="{{ e($title) }}" loading="lazy"
                                    decoding="async">
                            </div>
                        @endif

                        @if ($title !== '')
                            <h3 class="c-product__title">{{ $title }}</h3>
                        @endif

                        @if ($contentHtml !== '')
                            <div class="sp-content">{!! $contentHtml !!}</div>
                        @endif

                        @if ($showLearnMoreBtn)
                            <a class="sp-more" href="{{ $learnMoreUrl }}">Learn more <span
                                    aria-hidden="true">→</span></a>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        <style>
            /* Base typography / color */
            .{{ $wrapperClass }} {
                color: #2c2c2c;
                font-family: 'Source Sans Pro', sans-serif;
                margin: 40px 0px;
            }

            /* ---------------------------
       TOP SECTION - DEFAULT CENTER
       --------------------------- */
            .{{ $wrapperClass }} .sp-top {
                text-align: center;
                max-width: 980px;
                margin: 0 auto 56px auto;
            }

            .{{ $wrapperClass }} .sp-top-title {
                font-size: 36px;
                line-height: 1.2;
                font-weight: 700;
                margin: 0 0 12px 0;
                color: #2c2c2c;
            }

            .{{ $wrapperClass }} .sp-top-content {
                font-size: 18px;
                line-height: 1.7;
                color: #2c2c2c;
            }

            .{{ $wrapperClass }} .sp-top-content p {
                margin: 0;
            }

            /* ---------------------------
       TOP SECTION - HYBRID VARIANT
       - Desktop: 12-col grid => Left 5, Right 7
       - Mobile: overlap style, image top, text bottom
       --------------------------- */
            .{{ $wrapperClass }} .sp-top-hybrid {
                background: #f9f9f9;
                margin: 0 auto 56px auto;
                padding: 0;
            }

            /* ✅ 12-col grid (5 + 7) */
            .{{ $wrapperClass }} .sp-top-hybrid__grid {
                max-width: 1200px;
                margin: 0 auto;
                padding: 40px 35px 40px 35px;
                display: grid;
                grid-template-columns: repeat(12, minmax(0, 1fr));
                gap: 90px;
                align-items: center;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__left {
                grid-column: span 5;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__right {
                grid-column: span 7;
                display: flex;
                justify-content: center;
                align-items: center;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__label {
                font-size: 13px;
                letter-spacing: .08em;
                text-transform: uppercase;
                color: #111;
                margin-bottom: 14px;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__title {
                font-size: 48px;
                line-height: 1.05;
                font-weight: 800;
                margin: 0 0 18px 0;
                color: #111;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__content {
                font-size: 20px;
                line-height: 1.8;
                color: #111;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__content p {
                margin: 0 0 18px 0;
                text-align: justify;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__single img {
                width: 100%;
                height: auto;
                display: block;
                box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            }

            /* Desktop stacked images */
            .{{ $wrapperClass }} .sp-top-hybrid__stack {
                position: relative;
                min-height: 520px;
                padding-top: 22px;
                padding-bottom: 22px;
                width: 100%;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__img {
                position: absolute;
                background: #fff;
                box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
            }

            .{{ $wrapperClass }} .sp-top-hybrid__img img {
                width: 100%;
                height: auto;
                display: block;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__img--back {
                right: 0;
                top: 22px;
                width: 55%;
                z-index: 1;
            }

            .{{ $wrapperClass }} .sp-top-hybrid__img--front {
                left: 0;
                top: 80px;
                width: 60%;
                z-index: 2;
            }

            /* ✅ Mobile: image top, text bottom AND keep overlap */
            @media (max-width: 900px) {
                .{{ $wrapperClass }} .sp-top-hybrid__grid {
                    grid-template-columns: 1fr;
                    gap: 28px;
                    padding: 48px 16px;
                }

                /* full width for both */
                .{{ $wrapperClass }} .sp-top-hybrid__left,
                .{{ $wrapperClass }} .sp-top-hybrid__right {
                    grid-column: span 12;
                }

                /* images first */
                .{{ $wrapperClass }} .sp-top-hybrid__right {
                    order: -1;
                    display: block;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__stack {
                    min-height: auto;
                    padding: 0;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__img {
                    position: relative;
                    left: auto;
                    right: auto;
                    top: auto;
                    width: 100%;
                    margin: 0;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__img--back {
                    width: 100%;
                    z-index: 1;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__img--front {
                    width: 92%;
                    margin: -80px auto 0 auto;
                    z-index: 2;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__title {
                    font-size: 34px;
                }

                .{{ $wrapperClass }} .sp-top-hybrid__content {
                    font-size: 16px;
                }
            }

            /* ---------------------------
       GRID
       --------------------------- */
            .{{ $wrapperClass }} .sp-grid {
                display: grid;
                gap: 64px;
                align-items: start;
            }

            @media (min-width: 768px) {
                .{{ $wrapperClass }} .sp-grid {
                    grid-template-columns: repeat(var(--sp-cols), minmax(0, 1fr)) !important;
                }
            }

            /* Image */
            .{{ $wrapperClass }} .sp-img {
                display: block;
                width: 100%;
                margin-bottom: 25px;
                background: #f9fafb;
            }

            .{{ $wrapperClass }} .sp-img img {
                width: 100%;
                height: 280px;
                object-fit: cover;
                display: block;
            }

            @media (min-width: 1024px) {
                .{{ $wrapperClass }} .sp-img img {
                    height: 320px;
                }
            }

            /* Title design */
            .{{ $wrapperClass }} .c-product__title {
                font-size: 24px;
                font-weight: bold;
                font-style: normal;
                font-stretch: normal;
                line-height: 1.04;
                letter-spacing: normal;
                color: #2c2c2c;
                margin: 1rem 0;
            }

            /* Content text */
            .{{ $wrapperClass }} .sp-content {
                font-size: 16px;
                line-height: 1.7;
                color: #2c2c2c;
            }

            .{{ $wrapperClass }} .sp-content p {
                margin: 0 0 14px 0;
                text-align: justify;
            }

            /* Learn more underline + arrow */
            .{{ $wrapperClass }} .sp-more {
                display: inline-block;
                margin-top: 12px;
                font-size: 18px;
                color: #2c2c2c;
                text-decoration: underline;
                font-weight: 500;
            }

            /* Mobile tuning */
            @media (max-width: 767px) {
                .{{ $wrapperClass }} .sp-top {
                    margin-bottom: 36px;
                    padding: 0 8px;
                }

                .{{ $wrapperClass }} .sp-top-title {
                    font-size: 28px;
                }

                .{{ $wrapperClass }} .sp-grid {
                    gap: 36px;
                }

                .{{ $wrapperClass }} .sp-img img {
                    height: 220px;
                }

                .{{ $wrapperClass }} .sp-content {
                    font-size: 16px;
                }
            }






            /* Make section wide + left aligned like image */
            .why-siatex .sp-top {
                max-width: 1400px;
                margin: 0 auto 56px auto;
                text-align: left;
                padding: 0 20px;
            }

            /* Big bold heading */
            .why-siatex .sp-top-title {
                font-size: 32px;
                line-height: 1.05;
                font-weight: 800;
                letter-spacing: .02em;
                text-transform: uppercase;
                margin: 0 0 34px 0;
            }

            /* 3 columns from the 3 <p> tags */
            .why-siatex .sp-top-content {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 90px;
                font-size: 16px;
                line-height: 1.7;
            }

            /* Each <p> is one column */
            .why-siatex .sp-top-content p {
                margin: 0;
                padding: 0 60px 0 0;
                text-align: left;
            }

            /* Vertical separators between columns */
            .why-siatex .sp-top-content p:nth-child(1),
            .why-siatex .sp-top-content p:nth-child(2) {
                border-right: 2px solid #0e4f7f;
            }

            /* Space after the line for col 2 & 3 text */
            /*     .why-siatex .sp-top-content p:nth-child(2),
            .why-siatex .sp-top-content p:nth-child(3) {
                padding-left: 60px;
            } */

            /* Nice spacing between bullet lines made with <br> */
            .why-siatex .sp-top-content p br {
                display: block;
                content: "";
                margin-top: 22px;
            }

            /* Responsive: stack on mobile */
            @media (max-width: 991px) {
                .why-siatex .sp-top-title {
                    font-size: 40px;
                }

                .why-siatex .sp-top-content {
                    grid-template-columns: 1fr;
                    gap: 28px;
                    font-size: 18px;
                }

                .why-siatex .sp-top-content p {
                    border-right: 0 !important;
                    padding: 0 !important;
                }

                /* Optional: horizontal separator on mobile */
                .why-siatex .sp-top-content p:not(:last-child) {
                    border-bottom: 2px solid #0e4f7f;
                    padding-bottom: 18px !important;
                    margin-bottom: 18px;
                }

                .why-siatex .sp-top-content p br {
                    margin-top: 14px;
                }
            }
        </style>
    </div>
@endif

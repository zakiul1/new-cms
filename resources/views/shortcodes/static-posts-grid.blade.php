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
     *  - $topTitle : string
     *  - $topContent : string
     *  - $topVariant : string
     *  - $topMediaItems : Collection of first 2 featured Media items
     *  - $showLearnMoreBtn : bool
     */

    $items = $items ?? collect();
    if (is_array($items)) {
        $items = collect($items);
    }

    $column = max(1, min(12, (int) ($column ?? 3)));
    $mobile = max(1, min(4, (int) ($mobile ?? 1)));
    $img = filter_var($img ?? false, FILTER_VALIDATE_BOOL);

    $settings = app(\App\Cms\Core\SettingsRepository::class);

    $sloganTag = trim((string) $settings->get('core', 'slogan_tag', 'Your Tech-pack, Our production'));
    if ($sloganTag === '') {
        $sloganTag = 'Your Tech-pack, Our production';
    }

    $class = trim((string) ($class ?? 'static_posts'));
    $class = $class !== '' ? $class : 'static_posts';

    $gridStyle = "--sp-cols: {$column}; --sp-cols-mobile: {$mobile};";

    $firstClass = preg_split('/\s+/', $class, -1, PREG_SPLIT_NO_EMPTY)[0] ?? 'static_posts';
    $firstClass = trim($firstClass) !== '' ? trim($firstClass) : 'static_posts';
    $wrapperClass = \Illuminate\Support\Str::slug($firstClass, '-');

    $rootClassAttr = trim('sp-shortcode ' . $class);
    if ($wrapperClass !== '' && !preg_match('/(^|\s)' . preg_quote($wrapperClass, '/') . '(\s|$)/', $rootClassAttr)) {
        $rootClassAttr .= ' ' . $wrapperClass;
    }

    $topTitle = trim((string) ($topTitle ?? ''));
    $topContent = trim((string) ($topContent ?? ''));
    $showTop = $topTitle !== '' || $topContent !== '';

    $topVariant = strtolower(trim((string) ($topVariant ?? '')));

    $topMediaItems = $topMediaItems ?? collect();
    if (is_array($topMediaItems)) {
        $topMediaItems = collect($topMediaItems);
    }

    $topMediaItems = $topMediaItems->filter(fn($m) => $m instanceof \App\Models\Media)->values()->take(2);

    $topMediaBack = $topMediaItems->get(0);
    $topMediaFront = $topMediaItems->get(1);

    $showLearnMoreBtn = (bool) ($showLearnMoreBtn ?? false);

    $mobileSizeValue = match ($mobile) {
        1 => '100vw',
        2 => '50vw',
        3 => '33.33vw',
        4 => '25vw',
        default => '100vw',
    };

    $desktopSizeValue = match ($column) {
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
        default => '33.33vw',
    };

    $gridImageSizes = '(max-width: 767px) ' . $mobileSizeValue . ', ' . $desktopSizeValue;
    $topHybridSizes = '(max-width: 900px) 100vw, 50vw';
@endphp

@if ($showTop || $items->count() > 0)
    <div class="page-container">
        <div class="{{ $rootClassAttr }}">

            {{-- TOP SECTION --}}
            @if ($showTop)
                @if ($topVariant === 'hybrid')
                    <section class="sp-top-hybrid">
                        <div class="sp-top-hybrid__grid">
                            <div class="sp-top-hybrid__left">
                                <div class="sp-top-hybrid__label">{{ $sloganTag }}</div>

                                @if ($topTitle !== '')
                                    <h2 class="sp-top-hybrid__title">{{ $topTitle }}</h2>
                                @endif

                                @if ($topContent !== '')
                                    <div class="sp-top-hybrid__content">{!! $topContent !!}</div>
                                @endif
                            </div>

                            <div class="sp-top-hybrid__right">
                                @if ($topMediaBack instanceof \App\Models\Media && $topMediaFront instanceof \App\Models\Media)
                                    <div class="sp-top-hybrid__stack">
                                        <div class="sp-top-hybrid__img sp-top-hybrid__img--back">
                                            @if (function_exists('cms_picture'))
                                                {!! cms_picture(
                                                    $topMediaBack,
                                                    [
                                                        'alt' => $topTitle !== '' ? $topTitle : 'Featured image',
                                                        'loading' => 'lazy',
                                                        'fetchpriority' => 'low',
                                                        'decoding' => 'async',
                                                        'sizes' => $topHybridSizes,
                                                    ],
                                                    'hero_sm',
                                                    ['hero_sm', 'medium', 'medium_large'],
                                                ) !!}
                                            @else
                                                <img src="{{ $topMediaBack->variantUrl('hero_sm', 'jpeg') ?: $topMediaBack->variantUrl('hero_sm') ?: $topMediaBack->variantUrl('medium') ?: $topMediaBack->url() }}"
                                                    alt="{{ e($topTitle !== '' ? $topTitle : 'Featured image') }}"
                                                    loading="lazy" fetchpriority="low" decoding="async">
                                            @endif
                                        </div>

                                        <div class="sp-top-hybrid__img sp-top-hybrid__img--front">
                                            @if (function_exists('cms_picture'))
                                                {!! cms_picture(
                                                    $topMediaFront,
                                                    [
                                                        'alt' => $topTitle !== '' ? $topTitle : 'Featured image',
                                                        'loading' => 'eager',
                                                        'fetchpriority' => 'high',
                                                        'decoding' => 'async',
                                                        'sizes' => $topHybridSizes,
                                                    ],
                                                    'hero_sm',
                                                    ['hero_sm', 'medium', 'medium_large'],
                                                ) !!}
                                            @else
                                                <img src="{{ $topMediaFront->variantUrl('hero_sm', 'jpeg') ?: $topMediaFront->variantUrl('hero_sm') ?: $topMediaFront->variantUrl('medium') ?: $topMediaFront->url() }}"
                                                    alt="{{ e($topTitle !== '' ? $topTitle : 'Featured image') }}"
                                                    loading="eager" fetchpriority="high" decoding="async">
                                            @endif
                                        </div>
                                    </div>
                                @elseif ($topMediaBack instanceof \App\Models\Media)
                                    <div class="sp-top-hybrid__single">
                                        @if (function_exists('cms_picture'))
                                            {!! cms_picture(
                                                $topMediaBack,
                                                [
                                                    'alt' => $topTitle !== '' ? $topTitle : 'Featured image',
                                                    'loading' => 'eager',
                                                    'fetchpriority' => 'high',
                                                    'decoding' => 'async',
                                                    'sizes' => $topHybridSizes,
                                                ],
                                                'hero_sm',
                                                ['hero_sm', 'medium', 'medium_large'],
                                            ) !!}
                                        @else
                                            <img src="{{ $topMediaBack->variantUrl('hero_sm', 'jpeg') ?: $topMediaBack->variantUrl('hero_sm') ?: $topMediaBack->variantUrl('medium') ?: $topMediaBack->url() }}"
                                                alt="{{ e($topTitle !== '' ? $topTitle : 'Featured image') }}"
                                                loading="eager" fetchpriority="high" decoding="async">
                                        @endif
                                    </div>
                                @elseif ($topMediaFront instanceof \App\Models\Media)
                                    <div class="sp-top-hybrid__single">
                                        @if (function_exists('cms_picture'))
                                            {!! cms_picture(
                                                $topMediaFront,
                                                [
                                                    'alt' => $topTitle !== '' ? $topTitle : 'Featured image',
                                                    'loading' => 'eager',
                                                    'fetchpriority' => 'high',
                                                    'decoding' => 'async',
                                                    'sizes' => $topHybridSizes,
                                                ],
                                                'hero_sm',
                                                ['hero_sm', 'medium', 'medium_large'],
                                            ) !!}
                                        @else
                                            <img src="{{ $topMediaFront->variantUrl('hero_sm', 'jpeg') ?: $topMediaFront->variantUrl('hero_sm') ?: $topMediaFront->variantUrl('medium') ?: $topMediaFront->url() }}"
                                                alt="{{ e($topTitle !== '' ? $topTitle : 'Featured image') }}"
                                                loading="eager" fetchpriority="high" decoding="async">
                                        @endif
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

                            $featuredMedia = null;

                            if ($img) {
                                try {
                                    if (
                                        is_object($item) &&
                                        method_exists($item, 'relationLoaded') &&
                                        $item->relationLoaded('featuredMediaPivot') &&
                                        $item->featuredMediaPivot
                                    ) {
                                        $featuredMedia =
                                            $item->featuredMediaPivot instanceof \Illuminate\Support\Collection
                                                ? $item->featuredMediaPivot->first()
                                                : $item->featuredMediaPivot;
                                    }

                                    if (
                                        !$featuredMedia &&
                                        is_object($item) &&
                                        method_exists($item, 'relationLoaded') &&
                                        $item->relationLoaded('featuredMedia') &&
                                        $item->featuredMedia
                                    ) {
                                        $featuredMedia =
                                            $item->featuredMedia instanceof \Illuminate\Support\Collection
                                                ? $item->featuredMedia->first()
                                                : $item->featuredMedia;
                                    }

                                    if (
                                        !$featuredMedia &&
                                        is_object($item) &&
                                        method_exists($item, 'featuredMediaPivot')
                                    ) {
                                        $featuredMedia = $item->featuredMediaPivot()->first();
                                    }

                                    if (!$featuredMedia && is_object($item) && method_exists($item, 'featuredMedia')) {
                                        $featuredMedia = $item->featuredMedia()->first();
                                    }
                                } catch (\Throwable $e) {
                                    $featuredMedia = null;
                                }
                            }

                            $imageUrl = '';
                            if ($img && $featuredMedia instanceof \App\Models\Media) {
                                try {
                                    $imageUrl =
                                        (string) ($featuredMedia->variantUrl('hero_sm', 'jpeg') ?:
                                        $featuredMedia->variantUrl('hero_sm') ?:
                                        $featuredMedia->variantUrl('medium') ?:
                                        $featuredMedia->url());
                                } catch (\Throwable $e) {
                                    $imageUrl = '';
                                }
                            }

                            $learnMoreUrl = trim((string) data_get($item, 'meta_json.static_posts.learn_more_url', ''));
                            $learnMoreUrl = $learnMoreUrl !== '' ? $learnMoreUrl : null;
                        @endphp

                        <article class="sp-item">
                            @if ($img && $featuredMedia instanceof \App\Models\Media)
                                <div class="sp-img">
                                    @if (function_exists('cms_picture'))
                                        {!! cms_picture(
                                            $featuredMedia,
                                            [
                                                'alt' => $title !== '' ? $title : 'Post image',
                                                'class' => 'w-full h-full object-cover',
                                                'sizes' => $gridImageSizes,
                                                'loading' => 'lazy',
                                                'fetchpriority' => 'low',
                                                'decoding' => 'async',
                                            ],
                                            'thumb',
                                            ['thumb', 'hero_sm', 'medium'],
                                        ) !!}
                                    @else
                                        <img src="{{ $imageUrl }}"
                                            alt="{{ e($title !== '' ? $title : 'Post image') }}" loading="lazy"
                                            fetchpriority="low" decoding="async">
                                    @endif
                                </div>
                            @elseif ($img && $imageUrl !== '')
                                <div class="sp-img">
                                    <img src="{{ $imageUrl }}"
                                        alt="{{ e($title !== '' ? $title : 'Post image') }}" loading="lazy"
                                        fetchpriority="low" decoding="async">
                                </div>
                            @elseif ($img)
                                <div class="sp-img" aria-hidden="true"></div>
                            @endif

                            @if ($title !== '')
                                <p class="c-product__title">{{ $title }}</p>
                            @endif

                            @if ($contentHtml !== '')
                                <div class="sp-content">{!! $contentHtml !!}</div>
                            @endif

                            @if ($showLearnMoreBtn && !empty($learnMoreUrl))
                                <a class="sp-more focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                                    href="{{ $learnMoreUrl }}">
                                    Learn more <span aria-hidden="true">→</span>
                                </a>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif

            <style>
                .sp-shortcode {
                    color: #2c2c2c;
                    font-family: 'Source Sans Pro', sans-serif;
                    margin: 20px 0;
                }

                .sp-shortcode .sp-top {
                    text-align: center;
                    max-width: 980px;
                    margin: 0 auto 56px auto;
                }

                .sp-shortcode .sp-top-title {
                    font-size: 36px;
                    line-height: 1.2;
                    font-weight: 700;
                    margin: 0 0 12px 0;
                    color: #2c2c2c;
                }

                .sp-shortcode .sp-top-content {
                    font-size: 18px;
                    line-height: 1.7;
                    color: #2c2c2c;
                }

                .sp-shortcode .sp-top-content p {
                    margin: 0;
                }

                .sp-shortcode .sp-top-hybrid {
                    background: #f9f9f9;
                    margin: 0 auto 56px auto;
                    padding: 0;
                }

                .sp-shortcode .sp-top-hybrid__grid {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 40px 35px 80px 35px;
                    display: grid;
                    grid-template-columns: repeat(12, minmax(0, 1fr));
                    gap: 90px;
                    align-items: center;
                }

                .sp-shortcode .sp-top-hybrid__left {
                    grid-column: span 5;
                }

                .sp-shortcode .sp-top-hybrid__right {
                    grid-column: span 7;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                }

                .sp-shortcode .sp-top-hybrid__label {
                    font-size: 13px;
                    letter-spacing: .08em;
                    text-transform: uppercase;
                    color: #111;
                    margin-bottom: 14px;
                    position: relative;
                }

                .sp-shortcode .sp-top-hybrid__label::after {
                    content: "";
                    position: absolute;
                    top: -17px;
                    left: 0;
                    width: 85px;
                    height: 3px;
                    background: #ec2227;
                }

                .sp-shortcode .sp-top-hybrid__title {
                    font-size: 48px;
                    line-height: 1.05;
                    font-weight: 800;
                    margin: 0 0 18px 0;
                    color: #111;
                }

                .sp-shortcode .sp-top-hybrid__content {
                    line-height: 1.8;
                    color: #111;
                }

                .sp-shortcode .sp-top-hybrid__content p {
                    margin: 0 0 18px 0;
                    text-align: justify;
                }

                .sp-shortcode .sp-top-hybrid__single img {
                    width: 100%;
                    height: auto;
                    display: block;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
                }

                .sp-shortcode .sp-top-hybrid__stack {
                    position: relative;
                    min-height: 520px;
                    padding-top: 22px;
                    padding-bottom: 22px;
                    width: 100%;
                }

                .sp-shortcode .sp-top-hybrid__img {
                    position: absolute;
                    background: #fff;
                    box-shadow: 0 10px 30px rgba(0, 0, 0, .12);
                }

                .sp-shortcode .sp-top-hybrid__img img {
                    width: 100%;
                    height: auto;
                    display: block;
                }

                .sp-shortcode .sp-top-hybrid__img--back {
                    right: 0;
                    top: 22px;
                    width: 55%;
                    z-index: 1;
                }

                .sp-shortcode .sp-top-hybrid__img--front {
                    left: 0;
                    top: 80px;
                    width: 60%;
                    z-index: 2;
                }

                @media (max-width: 900px) {
                    .sp-shortcode .sp-top-hybrid__grid {
                        grid-template-columns: 1fr;
                        gap: 28px;
                        padding: 48px 16px;
                    }

                    .sp-shortcode .sp-top-hybrid__left,
                    .sp-shortcode .sp-top-hybrid__right {
                        grid-column: span 12;
                    }

                    .sp-shortcode .sp-top-hybrid__right {
                        order: -1;
                        display: block;
                    }

                    .sp-shortcode .sp-top-hybrid__stack {
                        min-height: auto;
                        padding: 0;
                    }

                    .sp-shortcode .sp-top-hybrid__img {
                        position: relative;
                        left: auto;
                        right: auto;
                        top: auto;
                        width: 100%;
                        margin: 0;
                    }

                    .sp-shortcode .sp-top-hybrid__img--back {
                        width: 100%;
                        z-index: 1;
                    }

                    .sp-shortcode .sp-top-hybrid__img--front {
                        width: 92%;
                        margin: -80px auto 0 auto;
                        z-index: 2;
                    }

                    .sp-shortcode .sp-top-hybrid__title {
                        font-size: 34px;
                    }

                    .sp-shortcode .sp-top-hybrid__content {
                        font-size: 16px;
                    }
                }

                .sp-shortcode .sp-grid {
                    display: grid;
                    gap: 64px;
                    align-items: start;
                }

                @media (min-width: 768px) {
                    .sp-shortcode .sp-grid {
                        grid-template-columns: repeat(var(--sp-cols), minmax(0, 1fr)) !important;
                    }
                }

                .sp-shortcode .sp-img {
                    display: block;
                    width: 100%;
                    margin-bottom: 25px;
                    background: #f9fafb;
                }

                .sp-shortcode .sp-img img {
                    width: 100%;
                    height: 280px;
                    object-fit: cover;
                    display: block;
                }

                @media (min-width: 1024px) {
                    .sp-shortcode .sp-img img {
                        height: 320px;
                    }
                }

                .sp-shortcode .c-product__title {
                    font-size: 24px;
                    font-weight: bold;
                    font-style: normal;
                    font-stretch: normal;
                    line-height: 1.04;
                    letter-spacing: normal;
                    color: #2c2c2c;
                    margin: 1rem 0;
                }

                .sp-shortcode .sp-content {
                    line-height: 1.7;
                    color: #2c2c2c;
                }

                .sp-shortcode .sp-content p {
                    margin: 0 0 14px 0;
                    text-align: justify;
                }

                .sp-shortcode .sp-more {
                    display: inline-block;
                    margin-top: 12px;
                    font-size: 18px;
                    color: #1f2937;
                    font-weight: 500;
                }

                .sp-shortcode .sp-more:hover {
                    color: #0f4c81;
                }

                @media (max-width: 767px) {
                    .sp-shortcode .sp-top {
                        margin-bottom: 36px;
                        padding: 0 8px;
                    }

                    .sp-shortcode .sp-top-title {
                        font-size: 28px;
                    }

                    .sp-shortcode .sp-grid {
                        gap: 36px;
                    }

                    .sp-shortcode .sp-img img {
                        height: 220px;
                    }

                    .sp-shortcode .sp-content {
                        font-size: 16px;
                    }
                }

                .why-siatex .sp-top {
                    max-width: 1400px;
                    margin: 0 auto 56px auto;
                    text-align: left;
                    padding: 0 20px;
                }

                .why-siatex .sp-top-title {
                    font-size: 32px;
                    line-height: 1.05;
                    font-weight: 800;
                    letter-spacing: .02em;
                    text-transform: uppercase;
                    margin: 0 0 34px 0;
                }

                .why-siatex .sp-top-content {
                    display: grid;
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                    gap: 90px;
                    line-height: 1.7;
                }

                .why-siatex .sp-top-content p {
                    margin: 0;
                    padding: 0 60px 0 0;
                    text-align: left;
                }

                .why-siatex .sp-top-content p:nth-child(1),
                .why-siatex .sp-top-content p:nth-child(2) {
                    border-right: 2px solid #0e4f7f;
                }

                .why-siatex .sp-top-content p br {
                    display: block;
                    content: "";
                    margin-top: 22px;
                }

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
    </div>
@endif

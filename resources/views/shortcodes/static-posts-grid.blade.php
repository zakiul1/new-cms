{{-- resources/views/shortcodes/static-posts-grid.blade.php --}}

@php
    /**
     * Expected variables passed from shortcode:
     *  - $items  : Collection|array of StaticPost items
     *  - $column : int (desktop columns)
     *  - $mobile : int (mobile columns)
     *  - $img    : bool (show image or not)
     *  - $class  : string (wrapper class)
     */

    $items = $items ?? collect();
    if (is_array($items)) {
        $items = collect($items);
    }

    $column = (int) ($column ?? 3);
    $mobile = (int) ($mobile ?? 1);
    $img = filter_var($img ?? false, FILTER_VALIDATE_BOOL);
    $class = (string) ($class ?? 'static_posts');

    $column = max(1, min(12, $column));
    $mobile = max(1, min(4, $mobile));

    // CSS variables allow dynamic columns without generating Tailwind classes
    $gridStyle = "--sp-cols: {$column}; --sp-cols-mobile: {$mobile};";
    $wrapperClass = \Illuminate\Support\Str::slug($class, '-');
@endphp

@if ($items->count() > 0)
    <div class="{{ $class }} {{ $wrapperClass }}">
        {{-- Grid --}}
        <div class="sp-grid"
            style="{{ $gridStyle }} grid-template-columns: repeat(var(--sp-cols-mobile), minmax(0, 1fr));">
            @foreach ($items as $item)
                @php
                    // Title
                    $title = trim((string) data_get($item, 'title', ''));

                    // URL
                    $url = '';
                    try {
                        if (is_object($item) && method_exists($item, 'url')) {
                            $url = (string) $item->url();
                        } elseif (!empty($item->slug)) {
                            // Your static posts frontend is /static/{slug}
                            $url = url('/static/' . ltrim((string) $item->slug, '/'));
                        }
                    } catch (\Throwable $e) {
                        $url = '';
                    }

                    // CONTENT (you wanted content, not excerpt/description)
                    // Try: content_html -> content_json.html -> excerpt fallback
                    $contentHtml =
                        (string) (data_get($item, 'content_html') ??
                            (data_get($item, 'content') ??
                                (data_get($item, 'content_json.html') ?? (data_get($item, 'excerpt') ?? ''))));

                    // remove empty tags spacing
                    $contentHtml = trim($contentHtml);

                    // First image (safe)
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
                @endphp

                @if ($img)
                    {{-- =========================
                         DESIGN #2 (Image + big title + content + Learn more)
                         ========================= --}}
                    <article class="sp-item sp-item--img">
                        @if ($imageUrl !== '')
                            <a href="{{ $url ?: '#' }}" class="sp-img">
                                <img src="{{ $imageUrl }}" alt="{{ e($title) }}" loading="lazy" decoding="async">
                            </a>
                        @endif

                        @if ($title !== '')
                            <h3 class="sp-title sp-title--img">
                                @if ($url !== '')
                                    <a href="{{ $url }}">{{ $title }}</a>
                                @else
                                    {{ $title }}
                                @endif
                            </h3>
                        @endif

                        @if ($contentHtml !== '')
                            <div class="sp-content sp-content--img">
                                {!! $contentHtml !!}
                            </div>
                        @endif

                        @if ($url !== '')
                            <a class="sp-more" href="{{ $url }}">Learn more <span
                                    aria-hidden="true">→</span></a>
                        @endif
                    </article>
                @else
                    {{-- =========================
                         DESIGN #1 (Text only, like your screenshot)
                         ========================= --}}
                    <article class="sp-item sp-item--text">
                        @if ($title !== '')
                            <h3 class="sp-title sp-title--text">
                                @if ($url !== '')
                                    <a href="{{ $url }}">{{ $title }}</a>
                                @else
                                    {{ $title }}
                                @endif
                            </h3>
                        @endif

                        @if ($contentHtml !== '')
                            <div class="sp-content sp-content--text">
                                {!! $contentHtml !!}
                            </div>
                        @endif

                        @if ($url !== '')
                            <a class="sp-more" href="{{ $url }}">Learn More...</a>
                        @endif
                    </article>
                @endif
            @endforeach
        </div>

        {{-- Styles: match your 2 screenshots --}}
        <style>
            /* Grid */
            .{{ $wrapperClass }} .sp-grid {
                display: grid;
                gap: 48px;
                align-items: start;
            }

            @media (min-width: 768px) {
                .{{ $wrapperClass }} .sp-grid {
                    grid-template-columns: repeat(var(--sp-cols), minmax(0, 1fr)) !important;
                }
            }

            /* Reset default prose spacing inside content */
            .{{ $wrapperClass }} .sp-content p {
                margin: 0;
            }

            /* -------------------------
               DESIGN #1: TEXT ONLY
               ------------------------- */
            .{{ $wrapperClass }} .sp-item--text {
                padding: 0;
                border: 0;
                background: transparent;
            }

            .{{ $wrapperClass }} .sp-title--text {
                font-size: 22px;
                line-height: 1.2;
                font-weight: 700;
                color: #0f2a4a;
                margin: 0 0 10px 0;
            }

            .{{ $wrapperClass }} .sp-title--text a {
                color: inherit;
                text-decoration: none;
            }

            .{{ $wrapperClass }} .sp-title--text a:hover {
                text-decoration: underline;
            }

            .{{ $wrapperClass }} .sp-content--text {
                font-size: 16px;
                line-height: 1.6;
                color: #123b63;
            }

            .{{ $wrapperClass }} .sp-more {
                display: inline-block;
                margin-top: 12px;
                font-size: 16px;
                color: #123b63;
                text-decoration: underline;
                font-weight: 500;
            }

            /* -------------------------
               DESIGN #2: IMAGE CARDS
               ------------------------- */
            .{{ $wrapperClass }} .sp-item--img {
                padding: 0;
                border: 0;
                background: transparent;
            }

            .{{ $wrapperClass }} .sp-img {
                display: block;
                width: 100%;
                margin-bottom: 18px;
            }

            .{{ $wrapperClass }} .sp-img img {
                width: 100%;
                height: 260px;
                object-fit: cover;
                display: block;
            }

            @media (min-width: 1024px) {
                .{{ $wrapperClass }} .sp-img img {
                    height: 320px;
                }
            }

            .{{ $wrapperClass }} .sp-title--img {
                font-size: 40px;
                line-height: 1.1;
                font-weight: 800;
                margin: 0 0 14px 0;
                color: #111;
            }

            .{{ $wrapperClass }} .sp-title--img a {
                color: inherit;
                text-decoration: none;
            }

            .{{ $wrapperClass }} .sp-title--img a:hover {
                text-decoration: underline;
            }

            .{{ $wrapperClass }} .sp-content--img {
                font-size: 18px;
                line-height: 1.7;
                color: #333;
            }

            .{{ $wrapperClass }} .sp-content--img p {
                margin: 0 0 10px 0;
            }

            .{{ $wrapperClass }} .sp-item--img .sp-more {
                margin-top: 16px;
                font-size: 18px;
                color: #222;
                text-decoration: underline;
                font-weight: 500;
            }

            /* Small screens spacing */
            @media (max-width: 767px) {
                .{{ $wrapperClass }} .sp-grid {
                    gap: 28px;
                }

                .{{ $wrapperClass }} .sp-title--img {
                    font-size: 28px;
                }

                .{{ $wrapperClass }} .sp-img img {
                    height: 220px;
                }
            }
        </style>
    </div>
@endif

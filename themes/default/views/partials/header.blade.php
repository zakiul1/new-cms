@php
    $o = theme_options();

    $layout = data_get($o, 'appearance.header_layout', $o['header_layout'] ?? 'left');
    $sticky = (bool) data_get($o, 'appearance.header_sticky', $o['header_sticky'] ?? true);

    $bg = data_get($o, 'appearance.background', $o['header_bg'] ?? '#ffffff');
    $text = data_get($o, 'appearance.text', $o['header_text'] ?? '#111827');

    $siteTitle = theme_site_title();
    $tagline = theme_tagline();

    $logoWidth = (int) theme_logo_width();
    if ($logoWidth <= 0) {
        $logoWidth = 200;
    }

    $logoUrl = theme_logo_url();
    $faviconUrl = theme_favicon_url();

    // Use real intrinsic logo dimensions when known.
    // Fallback matches the image ratio seen in Lighthouse.
    $logoIntrinsicWidth = 300;
    $logoIntrinsicHeight = 103;

    // Backward compatibility
    if (!$logoUrl) {
        $legacyLogoMediaId = (int) ($o['logo_media_id'] ?? 0);
        if ($legacyLogoMediaId > 0) {
            $m = \App\Models\Media::query()->with('variantRecords')->whereKey($legacyLogoMediaId)->first();
            if ($m && method_exists($m, 'isImage') && $m->isImage()) {
                $logoUrl = method_exists($m, 'variantUrl')
                    ? ($m->variantUrl('small') ?:
                    $m->variantUrl('thumb') ?:
                    $m->url())
                    : $m->url();

                $meta = $m->meta ?? [];
                if (is_string($meta) && trim($meta) !== '') {
                    $decoded = json_decode($meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($meta)) {
                    $meta = [];
                }

                $logoIntrinsicWidth = (int) ($m->width ?? data_get($meta, 'width', $logoIntrinsicWidth));
                $logoIntrinsicHeight = (int) ($m->height ?? data_get($meta, 'height', $logoIntrinsicHeight));

                if ($logoIntrinsicWidth <= 0) {
                    $logoIntrinsicWidth = 300;
                }
                if ($logoIntrinsicHeight <= 0) {
                    $logoIntrinsicHeight = 103;
                }
            }
        }
    }

    if (!$logoUrl) {
        $logoPath = $o['logo_path'] ?? null;
        $logoUrl =
            is_string($logoPath) && $logoPath !== ''
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath)
                : null;
    }

    $layoutClass = match ($layout) {
        'center' => 'cms-layout-center',
        'split' => 'cms-layout-split',
        default => 'cms-layout-left',
    };

    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
@endphp

@if ($faviconUrl)
    @push('head')
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endpush
@endif

@push('head')
    <style>
        .cms-header {
            background: {{ $bg }};
            color: {{ $text }};
        }

        .cms-header a {
            color: inherit;
            text-decoration: none;
        }

        .cms-header-inner {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 14px 0;
        }

        .cms-header-left,
        .cms-header-center,
        .cms-header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .cms-header-nav {
            display: flex;
            align-items: center;
            gap: 14px;
            font-size: 14px;
            opacity: .95;
        }

        .cms-header-nav .cms-menu {
            list-style: none;
            display: flex;
            gap: 14px;
            margin: 0;
            padding: 0;
        }

        .cms-header-nav .cms-menu__link {
            font-size: 14px;
            opacity: .95;
        }

        .cms-layout-left .cms-header-inner {
            justify-content: space-between;
        }

        .cms-layout-center .cms-header-inner {
            justify-content: center;
            flex-direction: column;
            gap: 10px;
            padding: 16px 0;
        }

        .cms-layout-split .cms-header-inner {
            justify-content: space-between;
        }

        .cms-layout-split .cms-header-center {
            justify-content: center;
            flex: 1;
        }

        .cms-layout-split .cms-header-left,
        .cms-layout-split .cms-header-right {
            min-width: 140px;
        }

        /* CLS-safe logo area */
        .cms-site-logo {
            display: inline-block;
            width: {{ $logoWidth }}px;
            flex: 0 0 {{ $logoWidth }}px;
        }

        .cms-logo-frame {
            display: block;
            width: 100%;
            aspect-ratio: {{ $logoIntrinsicWidth }} / {{ $logoIntrinsicHeight }};
        }

        .logo-img {
            display: block;
            width: 100%;
            height: auto;
        }

        /* Keep above-the-fold header UI stable */
        .cms-header,
        .cms-header-nav,
        .cms-header-nav .cms-menu__link,
        .cms-site-title,
        .cms-site-tagline {
            font-family: Arial, Helvetica, sans-serif;
        }

        .cms-site-title {
            font-weight: 700;
            letter-spacing: -0.02em;
            font-size: 18px;
        }

        .cms-site-tagline {
            font-size: 14px;
            opacity: .8;
        }

        .data-cms-header-actions {
            min-height: 24px;
            align-items: center;
            white-space: nowrap;
        }
    </style>
@endpush

<header class="cms-header {{ $layoutClass }}" style="{{ $sticky ? 'position:sticky;top:0;z-index:40;' : '' }}">
    <div class="cms-container">
        <div class="cms-header-inner">
            {{-- LEFT --}}
            <div class="cms-header-left">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.left.before', '') !!}

                @if ($layout !== 'center')
                    <a href="{{ url('/') }}" class="cms-logo cms-site-logo" aria-label="{{ $siteTitle }}">
                        @if ($logoUrl)
                            <span class="cms-logo-frame">
                                <img src="{{ $logoUrl }}" alt="{{ $siteTitle }}" class="logo-img"
                                    width="{{ $logoIntrinsicWidth }}" height="{{ $logoIntrinsicHeight }}"
                                    style="width: 100%; height: auto;" decoding="async" fetchpriority="high">
                            </span>
                        @else
                            <div class="flex flex-col">
                                <span class="cms-site-title">{{ $siteTitle }}</span>
                                @if ($tagline !== '')
                                    <span class="cms-site-tagline">{{ $tagline }}</span>
                                @endif
                            </div>
                        @endif
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.left.after', '') !!}
            </div>

            {{-- CENTER --}}
            <div class="cms-header-center">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.center.before', '') !!}

                @if ($layout === 'center')
                    <div class="flex flex-col items-center">
                        <a href="{{ url('/') }}" class="cms-logo cms-site-logo" aria-label="{{ $siteTitle }}">
                            @if ($logoUrl)
                                <span class="cms-logo-frame">
                                    <img src="{{ $logoUrl }}" alt="{{ $siteTitle }}" class="logo-img"
                                        width="{{ $logoIntrinsicWidth }}" height="{{ $logoIntrinsicHeight }}"
                                        style="width: 100%; height: auto;" decoding="async" fetchpriority="high">
                                </span>
                            @else
                                <span class="cms-site-title">{{ $siteTitle }}</span>
                            @endif
                        </a>

                        @if ($tagline !== '')
                            <div class="cms-site-tagline mt-1">
                                {{ $tagline }}
                            </div>
                        @endif
                    </div>
                @endif

                <nav class="cms-header-nav" aria-label="Primary navigation">
                    @if (trim($menuHtml) !== '')
                        {!! $menuHtml !!}
                    @else
                        <strong>Dynamic Menu Not set yet</strong>
                    @endif
                </nav>

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.center.after', '') !!}
            </div>

            {{-- RIGHT --}}
            <div class="cms-header-right">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.right', '') !!}
            </div>
        </div>
    </div>
</header>

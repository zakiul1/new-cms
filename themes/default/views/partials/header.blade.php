@php
    $o = theme_options();

    $layout = data_get($o, 'appearance.header_layout', $o['header_layout'] ?? 'left');
    $sticky = (bool) data_get($o, 'appearance.header_sticky', $o['header_sticky'] ?? true);

    $bg = data_get($o, 'appearance.background', $o['header_bg'] ?? '#ffffff');
    $text = data_get($o, 'appearance.text', $o['header_text'] ?? '#111827');

    $siteTitle = theme_site_title();
    $tagline = theme_tagline();

    $logoWidth = theme_logo_width();
    $logoUrl = theme_logo_url();
    $faviconUrl = theme_favicon_url();

    // Backward compatibility
    if (!$logoUrl) {
        $legacyLogoMediaId = (int) ($o['logo_media_id'] ?? 0);
        if ($legacyLogoMediaId > 0) {
            $m = \App\Models\Media::query()->with('variantRecords')->whereKey($legacyLogoMediaId)->first();
            if ($m && method_exists($m, 'isImage') && $m->isImage()) {
                $logoUrl = method_exists($m, 'variantUrl') ? ($m->variantUrl('medium') ?: $m->url()) : $m->url();
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

<header class="cms-header {{ $layoutClass }}"
    style="
        background: {{ $bg }};
        color: {{ $text }};
        {{ $sticky ? 'position:sticky;top:0;z-index:40;' : '' }}
    ">
    <div class="cms-container">
        <div class="cms-header-inner">
            {{-- LEFT --}}
            <div class="cms-header-left">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.left.before', '') !!}

                @if ($layout !== 'center')
                    <a href="{{ url('/') }}" class="cms-logo cms-site-logo" aria-label="{{ $siteTitle }}">
                        @if ($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $siteTitle }}"
                                style="width: {{ $logoWidth }}px; height: auto; display:block;">
                        @else
                            <div class="flex flex-col">
                                <span class="cms-site-title">{{ $siteTitle }}</span>
                                @if ($tagline !== '')
                                    <span class="cms-site-tagline text-sm opacity-80">{{ $tagline }}</span>
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
                                <img src="{{ $logoUrl }}" alt="{{ $siteTitle }}"
                                    style="width: {{ $logoWidth }}px; height: auto; display:block;">
                            @else
                                <span class="cms-site-title">{{ $siteTitle }}</span>
                            @endif
                        </a>

                        @if ($tagline !== '')
                            <div class="cms-site-tagline mt-1 text-sm opacity-80">
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

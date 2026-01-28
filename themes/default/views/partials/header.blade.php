@php
    $o = theme_options();

    // Layout: left | center | split
    $layout = $o['header_layout'] ?? 'left';
    $sticky = !empty($o['header_sticky']);

    // Colors (from customizer)
    $bg = $o['header_bg'] ?? '#ffffff';
    $text = $o['header_text'] ?? '#111827';

    // Logo
    $logo = $o['logo_path'] ?? null;
    $logoUrl = $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null;
    $logoWidth = (int) ($o['logo_width'] ?? 140);
    if ($logoWidth <= 0) $logoWidth = 140;

    $layoutClass = match ($layout) {
        'center' => 'cms-layout-center',
        'split' => 'cms-layout-split',
        default => 'cms-layout-left',
    };

    // Menu HTML (WP-like)
    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
@endphp

<header
    class="cms-header {{ $layoutClass }}"
    style="
        background: {{ $bg }};
        color: {{ $text }};
        {{ $sticky ? 'position:sticky;top:0;' : '' }}
    "
>
    <div class="cms-container">
        <div class="cms-header-inner">
            {{-- LEFT --}}
            <div class="cms-header-left">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.left.before', '') !!}

                @if ($layout !== 'center')
                    <a href="{{ url('/') }}" class="cms-logo" aria-label="Home">
                        @if ($logoUrl)
                            <img
                                src="{{ $logoUrl }}"
                                alt="Logo"
                                style="width: {{ $logoWidth }}px; height: auto; display:block;"
                            >
                        @else
                            <span class="cms-site-title">{{ config('app.name', 'CMS') }}</span>
                        @endif
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.left.after', '') !!}
            </div>

            {{-- CENTER --}}
            <div class="cms-header-center">
                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.center.before', '') !!}

                @if ($layout === 'center')
                    <a href="{{ url('/') }}" class="cms-logo" aria-label="Home">
                        @if ($logoUrl)
                            <img
                                src="{{ $logoUrl }}"
                                alt="Logo"
                                style="width: {{ $logoWidth }}px; height: auto; display:block;"
                            >
                        @else
                            <span class="cms-site-title">{{ config('app.name', 'CMS') }}</span>
                        @endif
                    </a>
                @endif

                {{-- ✅ Dynamic menu (fallback if empty) --}}
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

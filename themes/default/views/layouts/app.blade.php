<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'CMS')</title>

    {!! cms_assets()->renderStyles('frontend') !!}
    {!! theme_customizer_css() !!}

    <style>
        /* Header uses CSS vars from theme_customizer_css() */
        .cms-header {
            background: var(--cms-header-bg, #ffffff);
            color: var(--cms-header-text, #111827);
            border-bottom: 1px solid rgba(0, 0, 0, .06);
            z-index: 50;
        }

        /* sticky on/off controlled by inline style class below */
        .cms-header a {
            color: inherit;
            text-decoration: none;
        }

        .cms-container {
            max-width: var(--cms-container, 1100px);
            margin: 0 auto;
            padding: 0 16px;
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

        .cms-logo img {
            width: var(--cms-logo-width, 140px);
            height: auto;
            display: block;
        }

        .cms-site-title {
            font-weight: 800;
            letter-spacing: -0.02em;
            font-size: 18px;
        }

        /* Layout modes */
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
    </style>
</head>

@php
    $o = theme_options();
    $layout = $o['header_layout'] ?? 'left'; // left|center|split
    $sticky = !empty($o['header_sticky']);

    $logo = $o['logo_path'] ?? null;
    $logoUrl = $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : null;

    $layoutClass = match ($layout) {
        'center' => 'cms-layout-center',
        'split' => 'cms-layout-split',
        default => 'cms-layout-left',
    };
@endphp

<body>
    {{-- ✅ Premium Header --}}
    <header class="cms-header {{ $layoutClass }}" style="{{ $sticky ? 'position:sticky;top:0;' : '' }}">
        <div class="cms-container">
            <div class="cms-header-inner">
                {{-- LEFT --}}
                <div class="cms-header-left">
                    @if ($layout !== 'center')
                        <a href="{{ url('/') }}" class="cms-logo">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo">
                            @else
                                <span class="cms-site-title">CMS</span>
                            @endif
                        </a>
                    @endif
                </div>

                {{-- CENTER --}}
                <div class="cms-header-center">
                    @if ($layout === 'center')
                        <a href="{{ url('/') }}" class="cms-logo">
                            @if ($logoUrl)
                                <img src="{{ $logoUrl }}" alt="Logo">
                            @else
                                <span class="cms-site-title">CMS</span>
                            @endif
                        </a>
                    @endif

                    {{-- menu placeholder (later: dynamic menu) --}}
                    <nav class="cms-header-nav">
                        <a href="{{ url('/') }}">Home</a>
                    </nav>
                </div>

                {{-- RIGHT --}}
                <div class="cms-header-right">
                    {{-- keep empty for now (later: search, CTA, login) --}}
                </div>
            </div>
        </div>
    </header>

    {{-- Page content --}}
    @yield('content')

    {!! cms_assets()->renderScripts('frontend') !!}
</body>

</html>

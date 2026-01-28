<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php
        // $seo is passed from ContentRouterController (post/page)
        // fallback values if $seo isn't present (home/other pages)
        $seoTitle = $seo['title'] ?? trim((string) view()->yieldContent('title', 'CMS'));
        $seoDesc = $seo['description'] ?? null;
        $seoCanonical = $seo['canonical'] ?? null;
        $seoRobots = $seo['robots'] ?? null;

        $og = $seo['og'] ?? [];
        $ogTitle = $og['title'] ?? $seoTitle;
        $ogDesc = $og['description'] ?? $seoDesc;
        $ogType = $og['type'] ?? 'website';
        $ogUrl = $og['url'] ?? $seoCanonical;
        $ogImage = $og['image'] ?? null;

        $tw = $seo['twitter'] ?? [];
        $twCard = $tw['card'] ?? 'summary_large_image';
        $twTitle = $tw['title'] ?? $ogTitle;
        $twDesc = $tw['description'] ?? $ogDesc;
        $twImage = $tw['image'] ?? $ogImage;

        $jsonld = $seo['jsonld'] ?? null;
    @endphp

    <title>{{ $seoTitle }}</title>

    @if (!empty($seoDesc))
        <meta name="description" content="{{ $seoDesc }}">
    @endif

    @if (!empty($seoCanonical))
        <link rel="canonical" href="{{ $seoCanonical }}">
    @endif

    @if (!empty($seoRobots))
        <meta name="robots" content="{{ $seoRobots }}">
    @endif

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $ogTitle }}">
    @if (!empty($ogDesc))
        <meta property="og:description" content="{{ $ogDesc }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    @if (!empty($ogUrl))
        <meta property="og:url" content="{{ $ogUrl }}">
    @endif
    @if (!empty($ogImage))
        <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Twitter --}}
    <meta name="twitter:card" content="{{ $twCard }}">
    <meta name="twitter:title" content="{{ $twTitle }}">
    @if (!empty($twDesc))
        <meta name="twitter:description" content="{{ $twDesc }}">
    @endif
    @if (!empty($twImage))
        <meta name="twitter:image" content="{{ $twImage }}">
    @endif

    {{-- Optional JSON-LD --}}
    @if (is_array($jsonld))
        <script type="application/ld+json">
            {!! json_encode($jsonld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
        </script>
    @endif

    {!! cms_assets()->renderStyles('frontend') !!}
    {!! theme_customizer_css() !!}

    {{-- Header + menu CSS (theme-wide) --}}
    <style>
        .cms-container{ max-width: var(--cms-container, 1100px); margin:0 auto; padding:0 16px; }

        .cms-header{
            background: var(--cms-header-bg, #ffffff);
            color: var(--cms-header-text, #111827);
            border-bottom: 1px solid rgba(0,0,0,.06);
            z-index: 50;
        }
        .cms-header a{ color: inherit; text-decoration:none; }

        .cms-header-inner{ display:flex; align-items:center; gap:16px; padding:14px 0; }
        .cms-header-left,.cms-header-center,.cms-header-right{ display:flex; align-items:center; gap:14px; }

        .cms-header-nav{ display:flex; align-items:center; gap:14px; font-size:14px; opacity:.95; }

        /* MenuRenderer outputs ul/li; keep it inline */
        .cms-header-nav .cms-menu{ list-style:none; display:flex; gap:14px; margin:0; padding:0; }
        .cms-header-nav .cms-menu__link{ font-size:14px; opacity:.95; }

        .cms-logo img{ width: var(--cms-logo-width, 140px); height:auto; display:block; }
        .cms-site-title{ font-weight:800; letter-spacing:-0.02em; font-size:18px; }

        /* Layout modes */
        .cms-layout-left .cms-header-inner{ justify-content:space-between; }
        .cms-layout-center .cms-header-inner{ justify-content:center; flex-direction:column; gap:10px; padding:16px 0; }
        .cms-layout-split .cms-header-inner{ justify-content:space-between; }
        .cms-layout-split .cms-header-center{ justify-content:center; flex:1; }
        .cms-layout-split .cms-header-left,.cms-layout-split .cms-header-right{ min-width:140px; }
    </style>
</head>

<body>
    {{-- ✅ Header partial --}}
    @include('theme::partials.header')

    {{-- ✅ Plugin hook: top-of-page sections (Hero etc.) --}}
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.front.top', '') !!}

    {{-- Page content --}}
    @yield('content')

    {{-- ✅ Plugin hook: before footer --}}
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.front.bottom', '') !!}

    {{-- ✅ Footer partial --}}
    @include('theme::partials.footer')

    {!! cms_assets()->renderScripts('frontend') !!}
</body>
</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

@php($hooks = app(\App\Cms\Hooks\Hooks::class))

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('cms.partials.seo')

    {{-- ✅ Frontend one CSS + one JS (Vite builds + minifies) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {!! theme_customizer_css() !!}

    @if (!empty($pageAssetsCss))
        <style id="page-custom-css">
            {!! $pageAssetsCss !!}
        </style>
    @endif

    @stack('head')

    {!! $hooks->applyFilters('theme.head', '') !!}
</head>

<body class="min-h-screen bg-white text-slate-900 antialiased">
    {!! $hooks->applyFilters('theme.body.before', '') !!}

    @include('partials.topbar')
    @include('partials.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')

    @if (!empty($pageAssetsJs))
        <script id="page-custom-js">
            {!! $pageAssetsJs !!}
        </script>
    @endif

    {!! $hooks->applyFilters('theme.body.after', '') !!}
</body>

</html>

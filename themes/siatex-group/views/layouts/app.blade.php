<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- ✅ SEO partial --}}
    @include('cms.partials.seo')

    {!! theme_customizer_css() !!}

    {{-- ✅ Tailwind (quick test). Replace with your compiled Tailwind CSS later. --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Theme JS (keep if you use it) --}}
    <script src="{{ asset('themes/siatex-group/dist/theme.js') }}" defer></script>

    @stack('head')

    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.head', '') !!}
</head>

<body class="min-h-screen bg-white text-slate-900 antialiased">
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.body.before', '') !!}

    @include('partials.topbar')
    @include('partials.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    @stack('scripts')

    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.body.after', '') !!}
</body>

</html>

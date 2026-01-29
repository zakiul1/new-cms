<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'Siatex') }}</title>

    {!! theme_customizer_css() !!}

    {{-- Theme dist css --}}
    <link rel="stylesheet" href="{{ asset('themes/siatex-group/dist/theme.css') }}">

    {{-- Theme dist js --}}
    <script src="{{ asset('themes/siatex-group/dist/theme.js') }}" defer></script>

    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.head', '') !!}
</head>

<body class="siatex">
    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.body.before', '') !!}

    @include('partials.topbar')
    @include('partials.header')

    <main class="cms-main">
        @yield('content')
    </main>

    @include('partials.footer')

    {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.body.after', '') !!}
</body>

</html>

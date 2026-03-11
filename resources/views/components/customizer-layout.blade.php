<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Theme Customizer</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>

<body class="m-0 min-h-screen overflow-hidden bg-[#dcdcdd] antialiased">
    <div id="customizer-app" class="min-h-screen">
        {{ $slot }}
    </div>

    @livewireScripts
    @stack('scripts')
</body>

</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

@php
    $hooks = app(\App\Cms\Hooks\Hooks::class);

    // Filament panel id from your AdminPanelProvider ->id('admin')
    $panelId = 'admin';

    // ✅ Global toggle from CMS Settings (default ON)
    $adminBarEnabled = true;
    try {
        $settingsRepo = app(\App\Cms\Core\SettingsRepository::class);
        $adminBarEnabled = (bool) $settingsRepo->get('core', 'frontend_admin_bar_enabled', true);
    } catch (\Throwable $e) {
        $adminBarEnabled = true;
    }

    // ✅ Show bar only when Filament user is logged in (most reliable)
    $isFilamentLoggedIn = false;
    try {
        $isFilamentLoggedIn = class_exists(\Filament\Facades\Filament::class)
            ? \Filament\Facades\Filament::auth()->check()
            : auth()->check();
    } catch (\Throwable $e) {
        $isFilamentLoggedIn = auth()->check();
    }

    $showAdminBar = $adminBarEnabled && $isFilamentLoggedIn;

    // ✅ Dashboard URL: try named route first, fallback to /lara-admin
    $adminDashboardUrl = url('/lara-admin');
    try {
        $adminDashboardUrl = route("filament.{$panelId}.pages.dashboard");
    } catch (\Throwable $e) {
        // keep fallback
    }

    // ✅ Controller may pass $adminEditUrl, but if not, compute fallback here.
    $adminEditUrl = isset($adminEditUrl) ? (string) $adminEditUrl : '';
    $adminEditUrl = trim($adminEditUrl);

    // ✅ If Home page and edit url not provided, compute it from SettingsRepository
    if ($adminEditUrl === '' && request()->routeIs('cms.home')) {
        try {
            $homeId = $settingsRepo->get('core', 'homepage_page_id', null);
            $homeId = is_numeric($homeId) ? (int) $homeId : 0;

            if ($homeId > 0) {
                $adminEditUrl = url('/lara-admin/pages/' . $homeId . '/edit');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // ✅ Final target: edit if available else dashboard
    $adminTargetUrl = $adminEditUrl !== '' ? $adminEditUrl : $adminDashboardUrl;

    // ✅ Favicon (Theme Customizer option)
    $o = theme_options();
    $faviconId = (int) ($o['favicon_media_id'] ?? 0);
    $favicon = $faviconId ? \App\Models\Media::query()->whereKey($faviconId)->first() : null;
    $faviconUrl = $favicon ? $favicon->url() : null;
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @include('cms.partials.seo')

    {{-- ✅ Favicon --}}
    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('themes/siatex-group/dist/theme.css') }}">
    {!! theme_customizer_css() !!}

    {{-- ✅ IMPORTANT: Render CMS enqueued frontend styles (plugins use this) --}}
    {!! function_exists('cms_assets') ? cms_assets()->renderStyles('frontend') : '' !!}

    @if (!empty($pageAssetsCss))
        <style id="page-custom-css">
            {!! $pageAssetsCss !!}
        </style>
    @endif

    {{-- ✅ Capture @stack('head') output and pass to theme.head --}}
    @php ob_start(); @endphp
    @stack('head')
    @php $stackHead = ob_get_clean(); @endphp

    {!! $hooks->applyFilters('theme.head', $stackHead) !!}
</head>

<body class="min-h-screen bg-white text-slate-900 antialiased">
    {!! $hooks->applyFilters('theme.body.before', '') !!}

    {{-- ✅ Frontend Admin Bar (WordPress-like) --}}
    @if ($showAdminBar)
        <div id="cms-admin-bar"
            style="position:fixed;top:0;left:0;right:0;z-index:99999;height:32px;
                    background:#1d2327;color:#fff;font:13px/32px system-ui,-apple-system,Segoe UI,Roboto,sans-serif;">
            <div style="max-width:1280px;margin:0 auto;padding:0 12px;display:flex;gap:14px;align-items:center;">
                <a href="{{ $adminDashboardUrl }}" style="color:#fff;text-decoration:none;font-weight:600;">
                    Admin
                </a>

                <span style="opacity:.75;">Viewing site</span>

                <div style="margin-left:auto;display:flex;gap:12px;align-items:center;">
                    <a href="{{ $adminTargetUrl }}" style="color:#72aee6;text-decoration:none;">
                        {{ $adminEditUrl !== '' ? 'Edit' : 'Dashboard' }}
                    </a>

                    <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                        @csrf
                        <button type="submit"
                            style="background:transparent;border:0;color:#fff;cursor:pointer;padding:0;">
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- push site down like WP admin bar --}}
        <div style="height:32px;"></div>
    @endif

    @include('partials.topbar')
    @include('partials.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

    {{-- ✅ IMPORTANT: Render CMS enqueued frontend scripts (plugins use this) --}}
    {!! function_exists('cms_assets') ? cms_assets()->renderScripts('frontend') : '' !!}

    @stack('scripts')

    @if (!empty($pageAssetsJs))
        <script id="page-custom-js">
            {!! $pageAssetsJs !!}
        </script>
    @endif

    {!! $hooks->applyFilters('theme.body.after', '') !!}
</body>

</html>

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

@php
    $hooks = app(\App\Cms\Hooks\Hooks::class);

    // Filament panel id from your AdminPanelProvider ->id('admin')
    $panelId = 'admin';

    // Global toggle from CMS Settings (default ON)
    $adminBarEnabled = true;
    try {
        $settingsRepo = app(\App\Cms\Core\SettingsRepository::class);
        $adminBarEnabled = (bool) $settingsRepo->get('core', 'frontend_admin_bar_enabled', true);
    } catch (\Throwable $e) {
        $adminBarEnabled = true;
    }

    // Show bar only when Filament user is logged in
    $isFilamentLoggedIn = false;
    try {
        $isFilamentLoggedIn = class_exists(\Filament\Facades\Filament::class)
            ? \Filament\Facades\Filament::auth()->check()
            : auth()->check();
    } catch (\Throwable $e) {
        $isFilamentLoggedIn = auth()->check();
    }

    $showAdminBar = $adminBarEnabled && $isFilamentLoggedIn;

    // Dashboard URL: try named route first, fallback to /lara-admin
    $adminDashboardUrl = url('/lara-admin');
    try {
        $adminDashboardUrl = route("filament.{$panelId}.pages.dashboard");
    } catch (\Throwable $e) {
        // keep fallback
    }

    // Controller may pass $adminEditUrl, but if not, compute fallback here
    $adminEditUrl = isset($adminEditUrl) ? (string) $adminEditUrl : '';
    $adminEditUrl = trim($adminEditUrl);

    // If Home page and edit url not provided, compute it from SettingsRepository
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

    // Final target: edit if available else dashboard
    $adminTargetUrl = $adminEditUrl !== '' ? $adminEditUrl : $adminDashboardUrl;

    // Theme customization values
    $themeOptions = theme_options();
    $faviconUrl = theme_favicon_url();
    $logoUrl = theme_logo_url();

    // Site identity fallbacks
    $siteTitle = $themeOptions['site_identity']['site_title'] ?? config('app.name');
    $siteTagline = $themeOptions['site_identity']['tagline'] ?? '';

    // WP-like admin bar height
    $adminBarHeight = $showAdminBar ? 32 : 0;

    /*
    |--------------------------------------------------------------------------
    | Body classes for theme templates / homepage / post types
    |--------------------------------------------------------------------------
    */
    $bodyClasses = ['min-h-screen', 'bg-white', 'text-slate-900', 'antialiased'];

    if ($showAdminBar) {
        $bodyClasses[] = 'has-admin-bar';
    }

    try {
        if (request()->routeIs('cms.home')) {
            $bodyClasses[] = 'home';
            $bodyClasses[] = 'front-page';
        }

        if (isset($post) && $post instanceof \App\Models\Post) {
            $postType = trim((string) ($post->type ?? 'post'));
            if ($postType !== '') {
                $bodyClasses[] = 'post-type-' . \Illuminate\Support\Str::slug($postType);
            }

            $metaJson = $post->meta_json ?? [];
            if (is_string($metaJson) && trim($metaJson) !== '') {
                $decoded = json_decode($metaJson, true);
                $metaJson = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($metaJson)) {
                $metaJson = [];
            }

            $template = trim((string) (data_get($metaJson, 'template', '') ?: data_get($metaJson, '_template', '')));
            if ($template !== '') {
                $templateSlug = \Illuminate\Support\Str::slug($template);
                $bodyClasses[] = 'template-' . $templateSlug;
                $bodyClasses[] = 'page-template-' . $templateSlug;
            }

            $homepageId = null;
            try {
                $homepageId = $settingsRepo->get('core', 'homepage_page_id', null);
                $homepageId = is_numeric($homepageId) ? (int) $homepageId : null;
            } catch (\Throwable $e) {
                $homepageId = null;
            }

            if ($homepageId && (int) $post->getKey() === $homepageId) {
                $bodyClasses[] = 'home';
                $bodyClasses[] = 'front-page';
            }
        }
    } catch (\Throwable $e) {
        // keep default classes
    }

    $bodyClassString = implode(' ', array_values(array_unique(array_filter($bodyClasses))));
@endphp

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @include('cms.partials.seo', [
        'seo' => $seo ?? [],
        'post' => $post ?? null,
        'media' => $media ?? null,
        'tag' => $tag ?? null,
    ])

    @if ($faviconUrl)
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style id="theme-inline-css">
        .page-container {
            max-width: var(--page-container, 1140px);
            margin: auto;
            padding: 0 15px;
        }

        .sc-tag-current {
            font-weight: 400 !important;
        }

        .sc-tag-link {
            font-weight: 400 !important;
            text-decoration: none;
        }

        .sc-tag-link:hover {
            text-decoration: underline;
        }

        /* Header/layout safety styles only.
           Keep actual header visual design inside partials/header.blade.php */
        .site-header-nav,
        .site-header-nav * {
            box-sizing: border-box;
        }

        .site-header-nav {
            min-width: 0;
        }

        .site-header-nav .mega-menu-panel {
            max-width: 100%;
        }
    </style>

    {!! theme_customizer_css() !!}

    {!! function_exists('cms_assets') ? cms_assets()->renderStyles('frontend') : '' !!}

    @if (!empty($pageAssetsCss))
        <style id="page-custom-css">
            {!! $pageAssetsCss !!}
        </style>
    @endif

    @php ob_start(); @endphp
    @stack('head')
    @php $stackHead = ob_get_clean(); @endphp

    {!! $hooks->applyFilters('theme.head', $stackHead) !!}

    <style>
        :root {
            --cms-adminbar-h: {{ $adminBarHeight }}px;
        }

        html {
            scroll-padding-top: var(--cms-adminbar-h);
        }

        body {
            margin: 0;
        }

        #cms-admin-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 99999;
            height: 32px;
            background: #1d2327;
            color: #fff;
            font: 13px/32px system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }

        body.has-admin-bar {
            padding-top: var(--cms-adminbar-h);
        }
    </style>
</head>

<body class="{{ $bodyClassString }}">
    {!! $hooks->applyFilters('theme.body.before', '') !!}

    @if ($showAdminBar)
        <div id="cms-admin-bar">
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
    @endif

    {{-- Old topbar intentionally removed --}}
    @include('partials.header')

    <main class="flex-1">
        @yield('content')
    </main>

    @include('partials.footer')

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

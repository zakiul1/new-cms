@props([
    'livewire' => null,
])

@php
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    @class([
        'fi',
        'dark' => filament()->hasDarkModeForced(),
    ])
>
    <head>
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_START, scopes: $renderHookScopes) }}

        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        @if ($favicon = filament()->getFavicon())
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        @php
            $title = trim(strip_tags($livewire?->getTitle() ?? ''));
            $brandName = trim(strip_tags(filament()->getBrandName()));
        @endphp

        <title>
            {{ filled($title) ? $title : null }}
            {{ filled($brandName) && filled($title) ? ' - ' : null }}
            {{ filled($brandName) ? $brandName : null }}
        </title>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_BEFORE, scopes: $renderHookScopes) }}

        <style>
            [x-cloak=''],
            [x-cloak='x-cloak'],
            [x-cloak='1'] {
                display: none !important;
            }

            [x-cloak='inline-flex'] {
                display: inline-flex !important;
            }

            @media (max-width: 1023px) {
                [x-cloak='-lg'] {
                    display: none !important;
                }
            }

            @media (min-width: 1024px) {
                [x-cloak='lg'] {
                    display: none !important;
                }
            }
        </style>

        @filamentStyles

        {{ filament()->getTheme()->getHtml() }}
        {{ filament()->getFontHtml() }}
        {{ filament()->getMonoFontHtml() }}
        {{ filament()->getSerifFontHtml() }}

        <style>
            :root {
                --font-family: '{!! filament()->getFontFamily() !!}';
                --mono-font-family: '{!! filament()->getMonoFontFamily() !!}';
                --serif-font-family: '{!! filament()->getSerifFontFamily() !!}';
                --sidebar-width: {{ filament()->getSidebarWidth() }};
                --collapsed-sidebar-width: {{ filament()->getCollapsedSidebarWidth() }};
                --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};

                --wp-adminbar-height: 32px;
                --wp-adminbar-bg: #1d2327;
                --wp-sidebar-bg: #1d2327;
                --wp-sidebar-hover: #2c3338;
                --wp-sidebar-active: #2271b1;
                --wp-sidebar-accent: #72aee6;
                --wp-content-bg: #f0f0f1;
                --wp-panel-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
                --wp-font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;

                --wp-field-border: #8c8f94;
                --wp-field-border-soft: #dcdcde;
                --wp-field-bg: #ffffff;
                --wp-field-focus: #2271b1;
                --wp-text: #1d2327;
                --wp-muted: #646970;
            }

            .fi,
            .fi-body {
                font-family: var(--wp-font-family);
                background: var(--wp-content-bg);
            }

            .dark .fi,
            .dark .fi-body {
                background: var(--wp-content-bg);
            }

            .fi-body {
                color: var(--wp-text);
            }

            .fi-topbar-ctn {
                position: sticky;
                top: 0;
                z-index: 60;
            }

            .fi-topbar {
                min-height: var(--wp-adminbar-height) !important;
                height: var(--wp-adminbar-height);
                padding: 0 8px !important;
                gap: 8px;
                border-bottom: 1px solid #0c0d0e !important;
                background: var(--wp-adminbar-bg) !important;
                box-shadow: none !important;
                color: #f0f0f1 !important;
            }

            .fi-topbar *,
            .fi-topbar a,
            .fi-topbar button,
            .fi-topbar svg {
                color: #f0f0f1;
            }

            .fi-topbar .fi-topbar-start,
            .fi-topbar .fi-topbar-end {
                gap: 8px;
            }

            .fi-wp-adminbar-brand-ctn {
                display: flex;
                align-items: center;
                height: 100%;
            }

            .fi-wp-adminbar-brand {
                display: inline-flex;
                align-items: center;
                height: 100%;
                padding: 0 10px;
                color: #f0f0f1 !important;
                font-size: 13px;
                font-weight: 600;
                text-decoration: none;
                white-space: nowrap;
            }

            .fi-wp-adminbar-brand:hover {
                background: #2c3338;
                color: var(--wp-sidebar-accent) !important;
            }

            .fi-wp-adminbar-actions {
                display: flex;
                align-items: center;
                gap: 6px;
            }

            .fi-wp-adminbar-actions .fi-btn,
            .fi-wp-adminbar-actions .fi-icon-btn,
            .fi-topbar .fi-btn,
            .fi-topbar .fi-icon-btn {
                min-height: 24px !important;
                height: 24px;
                padding: 0 8px !important;
                border-color: transparent !important;
                border-radius: 2px !important;
                background: transparent !important;
                box-shadow: none !important;
                font-size: 12px !important;
                color: #f0f0f1 !important;
            }

            .fi-topbar .fi-btn:hover,
            .fi-topbar .fi-icon-btn:hover {
                background: #2c3338 !important;
                color: var(--wp-sidebar-accent) !important;
            }

            .fi-topbar .fi-global-search-ctn {
                margin-inline-start: auto;
            }

            .fi-topbar .fi-global-search {
                max-width: 240px;
            }

            .fi-topbar .fi-global-search-field,
            .fi-topbar .fi-input-wrp {
                min-height: 24px !important;
            }

            .fi-topbar .fi-input-wrp {
                border: 1px solid var(--wp-field-border) !important;
                border-radius: 2px !important;
                background: #fff !important;
                box-shadow: none !important;
            }

            .fi-topbar .fi-input,
            .fi-topbar input[type='search'],
            .fi-topbar input[type='text'] {
                min-height: 24px !important;
                height: 24px !important;
                padding-block: 0 !important;
                font-size: 12px !important;
                color: var(--wp-text) !important;
                background: transparent !important;
            }

            .fi-topbar .fi-input::placeholder,
            .fi-topbar input[type='search']::placeholder,
            .fi-topbar input[type='text']::placeholder,
            .fi-topbar .fi-input-wrp svg {
                color: var(--wp-muted) !important;
            }

            .fi-user-menu-trigger.fi-wp-user-menu-trigger {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 24px;
                padding: 0 6px 0 8px;
                border-radius: 2px;
            }

            .fi-user-menu-trigger.fi-wp-user-menu-trigger:hover {
                background: #2c3338;
            }

            .fi-wp-user-menu-greeting {
                font-size: 12px;
                white-space: nowrap;
                color: #f0f0f1 !important;
            }

            .fi-main-ctn {
                background: var(--wp-content-bg);
                position: relative;
                z-index: 1;
            }

            .fi-main {
                width: 100%;
                max-width: none !important;
                padding: 16px 20px 24px !important;
                background: transparent !important;
                position: relative;
                z-index: 1;
            }

            .fi-page,
            .fi-body {
                background: var(--wp-content-bg);
            }

            .fi-page-content {
                position: relative;
                z-index: 1;
            }

            .fi-wp-admin-sidebar {
                position: sticky;
                top: var(--wp-adminbar-height);
                height: calc(100vh - var(--wp-adminbar-height));
                overflow: visible !important;
                border-inline-end: none !important;
                background: var(--wp-sidebar-bg) !important;
                box-shadow: none !important;
                color: #f0f0f1 !important;
                z-index: 80;
            }

            .fi-wp-admin-sidebar .fi-sidebar-nav,
            .fi-wp-admin-nav-ctn {
                min-height: 100%;
                height: 100%;
                overflow-y: auto !important;
                overflow-x: visible !important;
                padding: 0 !important;
                scrollbar-width: thin;
                position: relative;
            }

            .fi-wp-admin-nav {
                margin: 0;
                padding: 8px 0 12px;
                list-style: none;
                overflow: visible !important;
            }

            .fi-wp-admin-item {
                position: relative;
                margin: 0;
                overflow: visible !important;
            }

            .fi-wp-admin-link {
                display: flex;
                align-items: center;
                gap: 10px;
                min-height: 34px;
                padding: 0 10px;
                border-inline-start: 4px solid transparent;
                color: #f0f0f1 !important;
                text-decoration: none;
                font-size: 13px;
                line-height: 1.3;
                box-shadow: none !important;
            }

            .fi-wp-admin-link:hover {
                background: var(--wp-sidebar-hover);
                color: var(--wp-sidebar-accent) !important;
            }

            .fi-wp-admin-item.is-current > .fi-wp-admin-link {
                background: var(--wp-sidebar-active);
                color: #fff !important;
            }

            .fi-wp-admin-item:hover > .fi-wp-admin-link {
                background: #2c3338;
                color: #72aee6 !important;
            }

            .fi-wp-admin-item.is-current:hover > .fi-wp-admin-link {
                background: #2271b1;
                color: #fff !important;
            }

            .fi-wp-admin-link-icon,
            .fi-wp-admin-link-icon--empty {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 18px;
                min-width: 18px;
                height: 18px;
            }

            .fi-wp-admin-link-label {
                flex: 1 1 auto;
                min-width: 0;
            }

            .fi-wp-admin-link-arrow {
                width: 14px;
                height: 14px;
                opacity: 0.85;
            }

            .fi-wp-admin-inline-submenu {
                display: block;
                background: #2c3338;
            }

            .fi-wp-admin-submenu {
                margin: 0;
                padding: 6px 0;
                list-style: none;
            }

            .fi-wp-admin-submenu--inline {
                padding: 6px 0;
                border-top: 1px solid rgba(255, 255, 255, 0.06);
            }

            .fi-wp-admin-submenu-item {
                margin: 0;
            }

            .fi-wp-admin-submenu-link {
                display: block;
                padding: 8px 14px;
                padding-inline-start: calc(14px + (var(--wp-menu-depth, 0) * 14px));
                color: #c3c4c7 !important;
                text-decoration: none;
                font-size: 13px;
                white-space: nowrap;
            }

            .fi-wp-admin-submenu-link:hover {
                background: #1d2327;
                color: #fff !important;
            }

            .fi-wp-admin-submenu--inline .fi-wp-admin-submenu-link {
                padding-left: 36px;
                color: #c3c4c7 !important;
            }

            .fi-wp-admin-submenu--inline .fi-wp-admin-submenu-item.is-current > .fi-wp-admin-submenu-link {
                background: transparent;
                color: #fff !important;
                font-weight: 600;
            }

            .fi-wp-admin-submenu-item.is-current > .fi-wp-admin-submenu-link {
                background: #2271b1;
                color: #fff !important;
            }

            .fi-page-header-main-ctn {
	padding: 0;
}

 .fi-wp-admin-flyout {
    position: fixed !important;
    top: 0;
    left: 0;
    z-index: 99999 !important;
    min-width: 200px;
    max-width: 320px;
    max-height: calc(100vh - 20px);
    overflow-y: auto;
    overflow-x: hidden;
    background: #2c3338;
    border-inline-start: 0 !important;
    box-shadow: 0 3px 8px rgba(0, 0, 0, 0.24);
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
    transform: translate3d(0, 0, 0);
}

.fi-wp-admin-flyout.is-open {
    opacity: 1 !important;
    visibility: visible !important;
    pointer-events: auto !important;
}

            .fi-wp-admin-flyout-title {
                padding: 10px 14px 8px;
                border-bottom: 1px solid rgba(255, 255, 255, 0.08);
                color: #f0f0f1;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }

            .fi-section,
            .fi-ta-ctn,
            .fi-dropdown-panel,
            .fi-modal-window,
            .fi-btn,
            .fi-icon-btn,
            .fi-badge,
            .fi-wi-widget {
                border-radius: 3px !important;
            }

            .fi-section,
            .fi-ta-ctn,
            .fi-dropdown-panel,
            .fi-modal-window,
            .fi-wi-widget {
                box-shadow: var(--wp-panel-shadow) !important;
            }

            .fi-main h1,
            .fi-page-heading {
                font-weight: 500;
            }

            .fi-sidebar-close-overlay {
                background: rgba(0, 0, 0, 0.35) !important;
            }

            .fi-main .fi-section,
            .fi-main .fi-ta-ctn,
            .fi-main .fi-wi-widget,
            .fi-main .fi-fo-tabs,
            .fi-main .fi-in-entry,
            .fi-main .fi-fo-section,
            .fi-main .fi-dropdown-panel,
            .fi-main .fi-modal-window {
                background: #fff !important;
                border: 1px solid var(--wp-field-border-soft) !important;
                box-shadow: var(--wp-panel-shadow) !important;
            }

            .fi-main .fi-input-wrp {
                border: 1px solid var(--wp-field-border) !important;
                background: var(--wp-field-bg) !important;
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                border-radius: 3px !important;
            }

            .fi-main input[type='text'],
            .fi-main input[type='email'],
            .fi-main input[type='number'],
            .fi-main input[type='url'],
            .fi-main input[type='password'],
            .fi-main input[type='search'],
            .fi-main input[type='date'],
            .fi-main input[type='datetime-local'],
            .fi-main textarea,
            .fi-main select {
                background: #fff !important;
                color: var(--wp-text) !important;
            }

            .fi-main textarea,
            .fi-main .fi-textarea,
            .fi-main .tiptap.ProseMirror,
            .fi-main .ProseMirror {
                border: 1px solid var(--wp-field-border) !important;
                background: #fff !important;
                box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.04) !important;
                border-radius: 3px !important;
            }

            .fi-main .fi-input-wrp:focus-within,
            .fi-main textarea:focus,
            .fi-main select:focus,
            .fi-main input:focus,
            .fi-main .tiptap.ProseMirror:focus,
            .fi-main .ProseMirror:focus {
                border-color: var(--wp-field-focus) !important;
                box-shadow: 0 0 0 1px var(--wp-field-focus) !important;
                outline: none !important;
            }

            .fi-main .fi-fo-field-wrp {
                margin-bottom: 14px;
            }

            .fi-main .fi-fo-field-wrp-label label,
            .fi-main .fi-fo-field-wrp-label span,
            .fi-main label {
                color: var(--wp-text) !important;
                font-weight: 500;
            }

            .fi-main .fi-fo-rich-editor,
            .fi-main .fi-fo-rich-editor .fi-input-wrp,
            .fi-main .fi-fo-rich-editor .tiptap-wrapper {
                border: 1px solid var(--wp-field-border) !important;
                background: #fff !important;
                box-shadow: none !important;
            }

            .fi-main .fi-fo-rich-editor-toolbar {
                background: #f6f7f7 !important;
                border-bottom: 1px solid var(--wp-field-border-soft) !important;
            }

            .fi-main .fi-fo-builder-item,
            .fi-main .fi-fo-repeater-item {
                border: 1px solid var(--wp-field-border-soft) !important;
                background: #fff !important;
            }

            .fi-main .fi-fo-placeholder,
            .fi-main .text-gray-400,
            .fi-main .text-gray-500,
            .fi-main .text-gray-600 {
                color: var(--wp-muted) !important;
            }

            .fi-main .fi-btn {
                box-shadow: none !important;
            }

            .fi-main .fi-fo-tabs [role='tab'],
            .fi-main .fi-tabs [role='tab'] {
                border-radius: 0 !important;
            }

            .fi-main .fi-fo-field-wrp-error .fi-input-wrp,
            .fi-main .fi-fo-field-wrp-error textarea,
            .fi-main .fi-fo-field-wrp-error select,
            .fi-main .fi-fo-field-wrp-error .fi-textarea,
            .fi-main .fi-fo-field-wrp-error .tiptap.ProseMirror,
            .fi-main .fi-fo-field-wrp-error .ProseMirror {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 1px #d63638 !important;
            }

            .fi-main .fi-fo-field-wrp-error .fi-input-wrp:focus-within,
            .fi-main .fi-fo-field-wrp-error textarea:focus,
            .fi-main .fi-fo-field-wrp-error select:focus,
            .fi-main .fi-fo-field-wrp-error input:focus,
            .fi-main .fi-fo-field-wrp-error .tiptap.ProseMirror:focus,
            .fi-main .fi-fo-field-wrp-error .ProseMirror:focus {
                border-color: #d63638 !important;
                box-shadow: 0 0 0 1px #d63638 !important;
            }

            .fi-main .fi-fo-field-wrp-error label,
            .fi-main .fi-fo-field-wrp-error .fi-fo-field-wrp-label,
            .fi-main .fi-fo-field-wrp-error .fi-fo-field-wrp-label span {
                color: #d63638 !important;
            }

            .fi-main .fi-fo-field-wrp-error-message,
            .fi-main .fi-fo-field-wrp-helper-text.text-danger-600,
            .fi-main .text-danger-600,
            .fi-main .text-danger-500,
            .fi-main .text-red-600,
            .fi-main .text-red-500 {
                color: #d63638 !important;
                font-size: 12px !important;
                font-weight: 500;
            }

            @media (max-width: 1023px) {
                .fi-topbar .fi-global-search {
                    max-width: 160px;
                }

                .fi-wp-admin-sidebar {
                    position: fixed;
                    top: var(--wp-adminbar-height);
                    height: calc(100vh - var(--wp-adminbar-height));
                }

                .fi-wp-admin-sidebar .fi-sidebar-nav,
                .fi-wp-admin-nav-ctn {
                    overflow-y: auto !important;
                    overflow-x: hidden !important;
                }

                .fi-wp-admin-flyout {
                    opacity: 0 !important;
                    visibility: hidden !important;
                    pointer-events: none !important;
                }

                .fi-wp-admin-inline-submenu {
                    background: #2c3338;
                }

                .fi-wp-admin-link-arrow {
                    transform: rotate(90deg);
                }
            }
        </style>

        @stack('styles')

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_AFTER, scopes: $renderHookScopes) }}

        @if (! filament()->hasDarkMode())
            <script>
                localStorage.setItem('theme', 'light')
            </script>
        @elseif (filament()->hasDarkModeForced())
            <script>
                localStorage.setItem('theme', 'dark')
            </script>
        @else
            <script>
                const loadDarkMode = () => {
                    window.theme = localStorage.getItem('theme') ?? @js(filament()->getDefaultThemeMode()->value)

                    if (
                        window.theme === 'dark' ||
                        (window.theme === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)').matches)
                    ) {
                        document.documentElement.classList.add('dark')
                    }
                }

                loadDarkMode()

                document.addEventListener('livewire:navigated', loadDarkMode)
            </script>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_END, scopes: $renderHookScopes) }}
    </head>

    <body
        {{
            $attributes
                ->merge($livewire?->getExtraBodyAttributes() ?? [], escape: false)
                ->class([
                    'fi-body',
                    'fi-panel-' . filament()->getId(),
                ])
        }}
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_START, scopes: $renderHookScopes) }}

        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_BEFORE, scopes: $renderHookScopes) }}

        @filamentScripts(withCore: true)

        @if (filament()->hasBroadcasting() && config('filament.broadcasting.echo'))
            <script data-navigate-once>
                window.Echo = new window.EchoFactory(@js(config('filament.broadcasting.echo')))

                window.dispatchEvent(new CustomEvent('EchoLoaded'))
            </script>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <script>
                loadDarkMode()
            </script>
        @endif

        @stack('scripts')

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER, scopes: $renderHookScopes) }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_END, scopes: $renderHookScopes) }}
    </body>
</html>
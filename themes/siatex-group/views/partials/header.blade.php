@php
    $settings = app(\App\Cms\Core\Settings::class);

    $o = theme_options();

    $siteTitle = theme_site_title();
    $tagline = theme_tagline();

    $logoId = (int) data_get($o, 'site_identity.logo_media_id', 0);
    $logo = $logoId ? \App\Models\Media::query()->with('variantRecords')->whereKey($logoId)->first() : null;

    $logoUrl = theme_logo_url();
    $logoWidth = (int) theme_logo_width();
    if ($logoWidth <= 0) {
        $logoWidth = 200;
    }

    $logoNaturalWidth = 0;
    $logoNaturalHeight = 0;

    if ($logo instanceof \App\Models\Media) {
        try {
            $logoNaturalWidth = (int) ($logo->width ?? 0);
            $logoNaturalHeight = (int) ($logo->height ?? 0);

            if ($logoNaturalWidth <= 0 || $logoNaturalHeight <= 0) {
                $meta = $logo->meta ?? [];

                if (is_string($meta) && trim($meta) !== '') {
                    $decoded = json_decode($meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                }

                if (is_array($meta)) {
                    $logoNaturalWidth = (int) (data_get($meta, 'width', 0) ?: data_get($meta, 'image.width', 0));
                    $logoNaturalHeight = (int) (data_get($meta, 'height', 0) ?: data_get($meta, 'image.height', 0));
                }
            }
        } catch (\Throwable $e) {
            $logoNaturalWidth = 0;
            $logoNaturalHeight = 0;
        }
    }

    if ($logoNaturalWidth <= 0) {
        $logoNaturalWidth = 300;
    }

    if ($logoNaturalHeight <= 0) {
        $logoNaturalHeight = 103;
    }

    $logoHeight = (int) round(($logoNaturalHeight / $logoNaturalWidth) * $logoWidth);
    if ($logoHeight <= 0) {
        $logoHeight = 40;
    }

    $logoSizes = $logoWidth . 'px';
    $logoVariantKey = 'thumb';
    $logoVariantKeys = ['thumb', 'small', 'hero_sm'];

    $headerMenuHtml = '';

    try {
        $headerMenuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
    } catch (\Throwable $e) {
        $headerMenuHtml = 'ERROR: ' . e($e->getMessage());
    }

    $headerIconsHtml = '';
    try {
        $headerIconsHtml = (string) app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '');
    } catch (\Throwable $e) {
        $headerIconsHtml = '';
    }
@endphp

<style>
    :root {
        --site-header-logo-max-lg: {{ $logoWidth }}px;
        --site-header-logo-max-sm: {{ max(130, (int) round($logoWidth * 0.84)) }}px;
        --site-header-row-h-lg: 116px;
        --site-header-row-h-sm: 84px;
        --site-header-shell-radius: 0px;
        --site-header-shell-bg-top: rgba(255, 255, 255, 0.82);
        --site-header-shell-bg-scrolled: #ffffff;
        --site-header-shell-border-top: rgba(255, 255, 255, 0.10);
        --site-header-shell-border-scrolled: rgba(15, 23, 42, 0.08);
        --site-header-shell-shadow-top: 0 1px 0 rgba(15, 23, 42, 0.04);
        --site-header-shell-shadow-scrolled: 0 10px 24px rgba(15, 23, 42, 0.08);
        --site-header-link-color: #111827;
        --site-header-link-hover: #c62828;
        --site-header-panel-border: #d32f2f;
        --site-header-transition: 260ms cubic-bezier(0.22, 1, 0.36, 1);
    }

    #site-header {
        position: fixed;
        top: var(--cms-adminbar-h, 0px);
        left: 0;
        right: 0;
        z-index: 70;
        width: 100%;
        background: transparent;
        transition: background-color var(--site-header-transition), box-shadow var(--site-header-transition);
        will-change: transform;
    }

    /* full width + white background on scroll */
    #site-header.is-compact {
        background: #ffffff;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }

    #site-header.is-compact .cms-container {
        max-width: 100%;
        padding-left: 0;
        padding-right: 0;
    }

    .site-header-shell {
        position: relative;
        background: var(--site-header-shell-bg-top);
        border-bottom: 1px solid var(--site-header-shell-border-top);
        box-shadow: var(--site-header-shell-shadow-top);
        border-radius: var(--site-header-shell-radius);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        transition:
            background-color var(--site-header-transition),
            box-shadow var(--site-header-transition),
            border-color var(--site-header-transition);
    }

    #site-header.is-compact .site-header-shell {
        background: var(--site-header-shell-bg-scrolled);
        border-bottom-color: var(--site-header-shell-border-scrolled);
        box-shadow: none;
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
    }

    .site-header-main {
        display: grid;
        grid-template-columns: auto minmax(0, 1fr) auto;
        align-items: center;
        gap: 28px;
        min-height: var(--site-header-row-h-lg);
        transition:
            min-height var(--site-header-transition),
            gap var(--site-header-transition),
            padding var(--site-header-transition);
    }

    #site-header.is-compact .site-header-main {
        min-height: var(--site-header-row-h-sm);
        gap: 24px;
        padding-left: 24px;
        padding-right: 24px;
    }

    .site-header-brand {
        display: flex;
        align-items: center;
        min-width: 0;
        flex: 0 0 auto;
    }

    .site-header-brand>a {
        display: inline-flex;
        align-items: center;
        min-width: 0;
        max-width: 100%;
    }

    .logo-frame {
        width: var(--site-header-logo-max-lg);
        max-width: 100%;
        aspect-ratio: {{ $logoNaturalWidth }} / {{ $logoNaturalHeight }};
        flex: 0 0 auto;
        display: block;
        overflow: hidden;
        transition: width var(--site-header-transition), transform var(--site-header-transition);
        will-change: width, transform;
    }

    .logo-img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: contain;
    }

    #site-header.is-compact .logo-frame {
        width: var(--site-header-logo-max-sm);
    }

    .site-header-nav-wrap {
        min-width: 0;
        display: flex;
        align-items: center;
        justify-content: flex-start;
    }

    .site-header-nav {
        width: 100%;
        position: relative;
        overflow: visible;
    }

    .site-header-nav nav,
    .site-header-nav .cms-header-nav {
        width: 100%;
    }

    .site-header-nav ul {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .site-header-nav a {
        text-decoration: none;
    }

    .site-header-nav .cms-menu--depth-0,
    .site-header-nav nav>ul,
    .site-header-nav .menu-root {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 30px;
        margin: 0;
        padding: 0;
        list-style: none;
        flex-wrap: nowrap;
        width: 100%;
    }

    .site-header-nav .cms-menu__item--depth-0,
    .site-header-nav nav>ul>li,
    .site-header-nav .menu-root>li {
        position: relative;
        flex: 0 0 auto;
    }

    .site-header-nav .cms-menu__item--depth-0>.cms-menu__link,
    .site-header-nav nav>ul>li>a,
    .site-header-nav .menu-root>li>a {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 0;
        font-size: 16px;
        font-weight: 500;
        line-height: 1.25;
        color: var(--site-header-link-color);
        white-space: nowrap;
        transition: color 180ms ease;
    }

    .site-header-nav .cms-menu__item--depth-0>.cms-menu__link:hover,
    .site-header-nav .cms-menu__item--depth-0>.cms-menu__link:focus-visible,
    .site-header-nav nav>ul>li>a:hover,
    .site-header-nav .menu-root>li>a:hover {
        color: var(--site-header-link-hover);
    }

    .site-header-nav .cms-menu__item--depth-0.menu-item-has-children>.cms-menu__link,
    .site-header-nav nav>ul>li.menu-item-has-children>a,
    .site-header-nav .menu-root>li.menu-item-has-children>a {
        padding-right: 2px;
    }

    .site-header-nav .cms-menu__indicator {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: currentColor;
        line-height: 1;
        transform: translateY(1px);
    }

    .site-header-nav .cms-menu__indicator-svg {
        width: 14px;
        height: 14px;
        display: block;
    }

    .site-header-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 16px;
        min-width: 0;
        flex: 0 0 auto;
    }

    .site-header-actions:empty {
        display: none;
    }

    .site-header-actions>* {
        flex: 0 0 auto;
    }

    .site-header-actions a,
    .site-header-actions button,
    .site-header-actions [role="button"] {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 22px;
        height: 22px;
        color: #111827;
        text-decoration: none;
        background: transparent;
        border: 0;
        padding: 0;
        line-height: 1;
        transition: color 180ms ease, transform 180ms ease;
    }

    .site-header-actions a:hover,
    .site-header-actions button:hover,
    .site-header-actions [role="button"]:hover {
        color: var(--site-header-link-hover);
        transform: translateY(-1px);
    }

    .site-header-actions svg,
    .site-header-actions img {
        width: 21px;
        height: 21px;
        max-width: 21px;
        max-height: 21px;
        display: block;
    }

    .site-header-nav .cms-menu__item:not(.mega-menu-item)>.cms-menu--depth-1:not(.mega-menu-panel),
    .site-header-nav .sub-menu {
        display: none;
        position: absolute;
        top: calc(100% + 10px);
        left: 0;
        min-width: 240px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        padding: 10px 0;
        z-index: 80;
    }

    .site-header-nav .cms-menu__item:not(.mega-menu-item):hover>.cms-menu--depth-1:not(.mega-menu-panel),
    .site-header-nav .cms-menu__item:not(.mega-menu-item):focus-within>.cms-menu--depth-1:not(.mega-menu-panel),
    .site-header-nav li:hover>.sub-menu,
    .site-header-nav li:focus-within>.sub-menu {
        display: block;
    }

    .site-header-nav .cms-menu--depth-1:not(.mega-menu-panel) .cms-menu__item,
    .site-header-nav .sub-menu li {
        position: relative;
    }

    .site-header-nav .cms-menu--depth-1:not(.mega-menu-panel) .cms-menu__link,
    .site-header-nav .sub-menu a {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 10px 16px;
        color: #0f172a;
        line-height: 1.35;
    }

    .site-header-nav .cms-menu--depth-1:not(.mega-menu-panel) .cms-menu__link:hover,
    .site-header-nav .sub-menu a:hover {
        background: #f8fafc;
        color: var(--site-header-link-hover);
    }

    .site-header-nav .cms-menu--depth-2:not(.mega-menu-links),
    .site-header-nav .sub-menu .sub-menu {
        display: none;
        position: absolute;
        top: -10px;
        left: calc(100% + 2px);
        min-width: 240px;
        background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 16px 40px rgba(15, 23, 42, 0.12);
        padding: 10px 0;
        z-index: 85;
    }

    .site-header-nav .cms-menu--depth-1:not(.mega-menu-panel) .cms-menu__item:hover>.cms-menu--depth-2:not(.mega-menu-links),
    .site-header-nav .cms-menu--depth-1:not(.mega-menu-panel) .cms-menu__item:focus-within>.cms-menu--depth-2:not(.mega-menu-links),
    .site-header-nav .sub-menu li:hover>.sub-menu,
    .site-header-nav .sub-menu li:focus-within>.sub-menu {
        display: block;
    }

    .site-header-nav .mega-menu-item {
        position: static !important;
    }

    .site-header-nav .mega-menu-item>.cms-menu__link,
    .site-header-nav .mega-menu-item>a {
        position: relative;
    }

    .site-header-nav .mega-menu-panel {
        display: none;
        position: absolute;
        left: 0;
        right: 0;
        top: calc(100% + 2px);
        width: 100%;
        min-width: 0;
        max-width: 100%;
        background: #fff;
        border-top: 2px solid var(--site-header-panel-border);
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10);
        padding: 18px 18px 16px;
        z-index: 90;
        grid-template-columns: repeat(var(--cms-mega-columns, 5), minmax(0, 1fr));
        gap: 18px;
        align-items: start;
    }

    .site-header-nav .mega-menu-item:hover>.mega-menu-panel,
    .site-header-nav .mega-menu-item:focus-within>.mega-menu-panel {
        display: grid;
    }

    .site-header-nav .mega-menu-column {
        min-width: 0;
    }

    .site-header-nav .mega-menu-column>.mega-menu-links {
        display: block;
    }

    .site-header-nav .mega-menu-links {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .site-header-nav .mega-menu-links>.cms-menu__item {
        display: block;
        margin: 0;
        padding: 0;
    }

    .site-header-nav .mega-menu-links>.mega-menu-group>.cms-menu__link,
    .site-header-nav .mega-menu-links>.cms-menu__item--depth-2.menu-item-has-children>.cms-menu__link,
    .site-header-nav .mega-menu-links>.cms-menu__item>.mega-menu__heading-link {
        display: block;
        padding: 0 0 8px;
        margin: 0 0 8px;
        border-bottom: 1px solid rgba(15, 23, 42, 0.08);
        font-size: 15px;
        line-height: 1.35;
        font-weight: 600;
        color: #243244;
    }

    .site-header-nav .mega-menu-links>.mega-menu-group>.cms-menu__link:hover,
    .site-header-nav .mega-menu-links>.cms-menu__item--depth-2.menu-item-has-children>.cms-menu__link:hover,
    .site-header-nav .mega-menu-links>.cms-menu__item>.mega-menu__heading-link:hover {
        color: var(--site-header-link-hover);
        background: transparent;
    }

    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu__link {
        display: block;
        color: #475569;
        padding: 5px 0;
        line-height: 1.35;
        font-size: 14px;
        font-weight: 400;
        background: transparent;
    }

    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu__link:hover {
        color: var(--site-header-link-hover);
    }

    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-3,
    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-2 {
        display: block;
        position: static;
        min-width: 0;
        border: 0;
        box-shadow: none;
        padding: 0;
        background: transparent;
        margin: 0;
    }

    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-3>.cms-menu__item>.cms-menu__link,
    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-2>.cms-menu__item>.cms-menu__link {
        display: block;
        padding: 5px 0;
        color: #475569;
        font-size: 14px;
        font-weight: 400;
        background: transparent;
    }

    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-3>.cms-menu__item>.cms-menu__link:hover,
    .site-header-nav .mega-menu-links>.cms-menu__item>.cms-menu--depth-2>.cms-menu__item>.cms-menu__link:hover {
        color: var(--site-header-link-hover);
        background: transparent;
    }

    .site-header-nav .mega-menu-links>.cont-menu>.cms-menu__link,
    .site-header-nav .mega-menu-links>.mega-menu-continuation>.cms-menu__link {
        font-weight: 500;
        color: #243244;
        padding-top: 10px;
    }

    .site-header-nav .mega-menu-links>.cont-menu:first-child>.cms-menu__link,
    .site-header-nav .mega-menu-links>.mega-menu-continuation:first-child>.cms-menu__link {
        padding-top: 0;
    }

    .site-header-nav .mega-menu-links>.cont-menu>.cms-menu--depth-3,
    .site-header-nav .mega-menu-links>.mega-menu-continuation>.cms-menu--depth-3 {
        display: block;
        position: static;
        min-width: 0;
        border: 0;
        box-shadow: none;
        padding: 0;
        background: transparent;
        margin: 0;
    }

    .site-header-nav .mega-menu-panel .cms-menu__indicator {
        display: none;
    }

    .site-header-nav .mega-menu-item.mega-2>.mega-menu-panel {
        --cms-mega-columns: 2;
    }

    .site-header-nav .mega-menu-item.mega-3>.mega-menu-panel {
        --cms-mega-columns: 3;
    }

    .site-header-nav .mega-menu-item.mega-4>.mega-menu-panel {
        --cms-mega-columns: 4;
    }

    .site-header-nav .mega-menu-item.mega-5>.mega-menu-panel {
        --cms-mega-columns: 5;
    }

    .site-header-nav .mega-menu-item.mega-6>.mega-menu-panel {
        --cms-mega-columns: 6;
    }

    @media (max-width: 1199px) {
        .site-header-main {
            gap: 20px;
        }

        .site-header-nav .cms-menu--depth-0,
        .site-header-nav nav>ul,
        .site-header-nav .menu-root {
            gap: 22px;
        }

        .site-header-nav .mega-menu-panel {
            gap: 14px;
            padding: 16px 16px 14px;
        }
    }

    @media (max-width: 991px) {
        :root {
            --site-header-row-h-lg: 90px;
            --site-header-row-h-sm: 76px;
        }

        .site-header-main {
            grid-template-columns: auto 1fr auto;
            gap: 14px;
        }

        .logo-frame {
            width: min(var(--site-header-logo-max-lg), 180px);
        }

        #site-header.is-compact .logo-frame {
            width: min(var(--site-header-logo-max-sm), 150px);
        }

        .site-header-nav-wrap {
            overflow-x: auto;
            justify-content: flex-start;
            scrollbar-width: thin;
        }

        .site-header-nav .cms-menu--depth-0,
        .site-header-nav nav>ul,
        .site-header-nav .menu-root {
            justify-content: flex-start;
            gap: 18px;
            min-width: max-content;
            padding-bottom: 4px;
        }

        .site-header-nav .mega-menu-panel {
            display: none !important;
        }
    }

    @media (max-width: 767px) {
        :root {
            --site-header-row-h-lg: 78px;
            --site-header-row-h-sm: 72px;
        }

        .site-header-main {
            gap: 10px;
        }

        .logo-frame {
            width: min(var(--site-header-logo-max-lg), 150px);
        }

        #site-header.is-compact .logo-frame {
            width: min(var(--site-header-logo-max-sm), 132px);
        }

        .site-header-actions {
            gap: 10px;
        }

        .site-header-actions a,
        .site-header-actions button,
        .site-header-actions [role="button"] {
            width: 20px;
            height: 20px;
        }

        .site-header-actions svg,
        .site-header-actions img {
            width: 20px;
            height: 20px;
            max-width: 20px;
            max-height: 20px;
        }

        .site-header-nav .cms-menu__item--depth-0>.cms-menu__link,
        .site-header-nav nav>ul>li>a,
        .site-header-nav .menu-root>li>a {
            font-size: 15px;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        #site-header,
        .site-header-shell,
        .site-header-main,
        .logo-frame,
        .site-header-actions a,
        .site-header-actions button,
        .site-header-actions [role="button"] {
            transition: none !important;
        }
    }
</style>

<header id="site-header">
    <div class="cms-container mx-auto px-4">
        <div class="site-header-shell">
            <div class="site-header-main">
                <div class="site-header-brand">
                    <a href="{{ url('/') }}"
                        class="no-link-affordance flex items-center gap-3 rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                        aria-label="{{ $siteTitle }}">
                        @if ($logo instanceof \App\Models\Media)
                            <span class="logo-frame">
                                {!! cms_picture(
                                    $logo,
                                    [
                                        'alt' => $siteTitle,
                                        'class' => 'logo-img',
                                        'style' => 'width:100%;height:100%;object-fit:contain;',
                                        'width' => $logoNaturalWidth,
                                        'height' => $logoNaturalHeight,
                                        'sizes' => $logoSizes,
                                        'loading' => 'eager',
                                        'fetchpriority' => 'high',
                                        'decoding' => 'async',
                                    ],
                                    $logoVariantKey,
                                    $logoVariantKeys,
                                ) !!}
                            </span>
                        @elseif ($logoUrl)
                            <span class="logo-frame">
                                <img class="logo-img" src="{{ $logoUrl }}" alt="{{ $siteTitle }}"
                                    width="{{ $logoNaturalWidth }}" height="{{ $logoNaturalHeight }}"
                                    style="width:100%;height:100%;object-fit:contain;" loading="eager"
                                    fetchpriority="high" decoding="async">
                            </span>
                        @else
                            <div class="flex flex-col">
                                <span class="text-xl font-extrabold tracking-tight">{{ $siteTitle }}</span>
                                @if ($tagline !== '')
                                    <span class="text-sm opacity-80">{{ $tagline }}</span>
                                @endif
                            </div>
                        @endif
                    </a>
                </div>

                <div class="site-header-nav-wrap">
                    @if (is_string($headerMenuHtml) && trim($headerMenuHtml) !== '')
                        <div class="site-header-nav">
                            <nav class="cms-header-nav" aria-label="Primary navigation">
                                {!! $headerMenuHtml !!}
                            </nav>
                        </div>
                    @endif
                </div>

                @if (is_string($headerIconsHtml) && trim($headerIconsHtml) !== '')
                    <div class="site-header-actions" aria-label="Header actions">
                        {!! $headerIconsHtml !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</header>

@push('scripts')
    <script>
        (function() {
            const initHeaderCompact = () => {
                const header = document.getElementById('site-header');
                if (!header || header.dataset.compactInit === '1') return;

                header.dataset.compactInit = '1';

                const compactThreshold = 30;
                let ticking = false;
                let lastCompact = null;

                const applyState = () => {
                    const y = window.scrollY || window.pageYOffset || 0;
                    const shouldCompact = y > compactThreshold;

                    if (shouldCompact !== lastCompact) {
                        header.classList.toggle('is-compact', shouldCompact);
                        lastCompact = shouldCompact;
                    }

                    ticking = false;
                };

                const onScroll = () => {
                    if (ticking) return;
                    ticking = true;
                    window.requestAnimationFrame(applyState);
                };

                applyState();

                window.addEventListener('scroll', onScroll, {
                    passive: true
                });
                window.addEventListener('resize', onScroll, {
                    passive: true
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initHeaderCompact);
            } else {
                initHeaderCompact();
            }
        })();
    </script>
@endpush

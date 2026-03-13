@php
    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('topbar');
    if (trim($menuHtml) === '') {
        $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
    }

    $instanceId = 'topbar-' . substr(md5($menuHtml), 0, 8);

    $mobileMenuBtnId = $instanceId . '-mobileMenuBtn';
    $mobileMenuId = $instanceId . '-mobileMenu';
    $mobileMenuOverlayId = $instanceId . '-mobileMenuOverlay';
    $mobileMenuCloseBtnId = $instanceId . '-mobileMenuCloseBtn';
    $mobileMenuLabelId = $instanceId . '-mobileMenuLabel';

    $desktopMenuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu topbar-menu hidden md:flex flex-wrap items-center gap-6"',
            'class="cms-menu__link topbar-link underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml,
    );

    $mobileMenuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu topbar-mobile-menu mt-4 space-y-2"',
            'class="cms-menu__link topbar-link block py-2 underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml,
    );
@endphp

@if (trim($menuHtml) !== '')
    @push('head')
        <style>
            #site-topbar {
                min-height: 44px;
                background: var(--cms-primary, #1f5f99);
            }

            .topbar-menu,
            .topbar-mobile-menu,
            .topbar-link,
            .topbar-email,
            .topbar-panel-title {
                font-family: var(--cms-body-font-family);
                font-size: var(--cms-body-font-size);
                font-weight: var(--cms-body-font-weight);
                line-height: var(--cms-body-line-height);
                letter-spacing: var(--cms-body-letter-spacing);
            }

            .topbar-menu,
            .topbar-mobile-menu,
            .topbar-link,
            .topbar-email,
            .topbar-panel-title {
                color: #fff;
            }

            .topbar-link:hover,
            .topbar-link:focus-visible {
                color: #fff;
                text-decoration: underline;
            }
        </style>
    @endpush

    <div id="site-topbar">
        <div class="cms-container mx-auto px-4">
            <div class="py-2">
                <nav aria-label="Top navigation" class="flex items-center justify-between gap-3">
                    <button type="button" id="{{ $mobileMenuBtnId }}"
                        class="inline-flex items-center justify-center rounded p-2 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/50 md:hidden"
                        aria-label="Open menu" aria-controls="{{ $mobileMenuId }}" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    {!! $desktopMenuHtml !!}

                    <div class="topbar-email whitespace-nowrap md:hidden">
                        <a href="mailto:sales@siatex.com"
                            class="topbar-link underline underline-offset-4 decoration-[1.5px] hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white transition">
                            sales@siatex.com
                        </a>
                    </div>
                </nav>
            </div>
        </div>

        <div id="{{ $mobileMenuOverlayId }}" class="fixed inset-0 z-[9998] hidden bg-black/60" aria-hidden="true"></div>

        <div id="{{ $mobileMenuId }}" class="fixed inset-y-0 left-0 z-[9999] hidden w-[85%] max-w-[360px] shadow-2xl"
            style="background: var(--cms-primary, #1f5f99);" aria-hidden="true" aria-modal="true" role="dialog"
            aria-labelledby="{{ $mobileMenuLabelId }}">
            <div class="flex items-center justify-between border-b border-white/15 px-4 py-4">
                <div id="{{ $mobileMenuLabelId }}" class="topbar-panel-title font-semibold">
                    Menu
                </div>

                <button type="button" id="{{ $mobileMenuCloseBtnId }}"
                    class="inline-flex items-center justify-center rounded p-2 text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/50"
                    aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto px-4 py-4" style="max-height: calc(100vh - 56px);">
                {!! $mobileMenuHtml !!}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function() {
                const btn = document.getElementById(@json($mobileMenuBtnId));
                const closeBtn = document.getElementById(@json($mobileMenuCloseBtnId));
                const menu = document.getElementById(@json($mobileMenuId));
                const overlay = document.getElementById(@json($mobileMenuOverlayId));

                if (!btn || !menu || !overlay || !closeBtn) return;
                if (btn.dataset.bound === '1') return;
                btn.dataset.bound = '1';

                let isOpen = false;

                const applyState = (open) => {
                    isOpen = open;
                    menu.classList.toggle('hidden', !open);
                    overlay.classList.toggle('hidden', !open);
                    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
                    overlay.setAttribute('aria-hidden', open ? 'false' : 'true');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    document.documentElement.classList.toggle('overflow-hidden', open);
                };

                const openMenu = () => {
                    if (isOpen) return;
                    applyState(true);
                    closeBtn.focus();
                };

                const closeMenu = () => {
                    if (!isOpen) return;
                    applyState(false);
                    btn.focus();
                };

                btn.addEventListener('click', openMenu);
                closeBtn.addEventListener('click', closeMenu);
                overlay.addEventListener('click', closeMenu);

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') closeMenu();
                });
            })();
        </script>
    @endpush
@endif

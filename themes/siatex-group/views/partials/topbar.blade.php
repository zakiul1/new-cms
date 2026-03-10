@php
    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('topbar');
    if (trim($menuHtml) === '') {
        $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
    }

    // Desktop menu styles (hidden on mobile)
    $desktopMenuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu hidden md:flex flex-wrap items-center gap-6 text-sm text-white/90"',
            'class="cms-menu__link underline underline-offset-4 decoration-[1.5px] hover:text-white hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70 transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml,
    );

    // Mobile menu styles (vertical)
    $mobileMenuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu mt-4 space-y-2 text-white/90"',
            'class="cms-menu__link block py-2 underline underline-offset-4 decoration-[1.5px] hover:text-white hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70 transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml,
    );
@endphp

@if (trim($menuHtml) !== '')
    {{-- NOTE: Topbar is fixed by layout shell now, so no sticky here --}}
    <div id="site-topbar" class="bg-[#2f6fa3]">
        <div class="cms-container mx-auto px-4">
            <div class="py-2">
                <nav aria-label="Top navigation" class="flex items-center justify-between gap-3">
                    {{-- Mobile hamburger --}}
                    <button type="button" id="mobileMenuBtn"
                        class="inline-flex items-center justify-center rounded p-2 text-white/90 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40 md:hidden"
                        aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    {{-- Desktop menu --}}
                    {!! $desktopMenuHtml !!}

                    {{-- Mobile email only --}}
                    <div class="whitespace-nowrap text-xs text-white/90 md:hidden">
                        <a href="mailto:sales@siatex.com"
                            class="underline underline-offset-4 decoration-[1.5px] hover:text-white hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70 transition">
                            sales@siatex.com
                        </a>
                    </div>
                </nav>
            </div>
        </div>

        {{-- Mobile menu: overlay + panel --}}
        <div id="mobileMenuOverlay" class="fixed inset-0 z-[9998] hidden bg-black/60" aria-hidden="true"></div>

        <div id="mobileMenu"
            class="fixed inset-y-0 left-0 z-[9999] hidden w-[85%] max-w-[360px] bg-[#2f6fa3] shadow-2xl"
            aria-hidden="true">
            <div class="flex items-center justify-between border-b border-white/10 px-4 py-4">
                <div class="font-semibold text-white">Menu</div>

                <button type="button" id="mobileMenuCloseBtn"
                    class="inline-flex items-center justify-center rounded p-2 text-white/90 hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-white/40"
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

    <script>
        (function() {
            const btn = document.getElementById('mobileMenuBtn');
            const closeBtn = document.getElementById('mobileMenuCloseBtn');
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');

            if (!btn || !menu || !overlay || !closeBtn) return;
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';

            let isOpen = false;
            let ticking = false;

            const applyState = (open) => {
                if (ticking) return;
                ticking = true;

                requestAnimationFrame(() => {
                    isOpen = open;

                    menu.classList.toggle('hidden', !open);
                    overlay.classList.toggle('hidden', !open);
                    menu.setAttribute('aria-hidden', open ? 'false' : 'true');
                    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                    document.documentElement.classList.toggle('overflow-hidden', open);

                    ticking = false;
                });
            };

            const openMenu = () => {
                if (isOpen) return;
                applyState(true);
            };

            const closeMenu = () => {
                if (!isOpen) return;
                applyState(false);
            };

            btn.addEventListener('click', openMenu);
            closeBtn.addEventListener('click', closeMenu);
            overlay.addEventListener('click', closeMenu);

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') closeMenu();
            });
        })();
    </script>
@endif

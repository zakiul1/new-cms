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
            'class="cms-menu__link hover:text-white transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml,
    );

    // Mobile menu styles (vertical)
    $mobileMenuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu mt-4 space-y-2 text-white/90"',
            'class="cms-menu__link block py-2 hover:text-white transition"',
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
                    {{-- ✅ Mobile hamburger --}}
                    <button type="button" id="mobileMenuBtn"
                        class="md:hidden inline-flex items-center justify-center rounded p-2 text-white/90 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40"
                        aria-label="Open menu" aria-controls="mobileMenu" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    {{-- ✅ Desktop menu --}}
                    {!! $desktopMenuHtml !!}

                    {{-- ✅ Mobile email only --}}
                    <div class="md:hidden text-xs text-white/90 whitespace-nowrap">
                        <a href="mailto:sales@siatex.com" class="hover:text-white transition">
                            sales@siatex.com
                        </a>
                    </div>
                </nav>
            </div>
        </div>

        {{-- ✅ Mobile menu: overlay + panel (covers full height like your screenshot) --}}
        <div id="mobileMenuOverlay" class="hidden fixed inset-0 bg-black/60 z-[9998]"></div>

        <div id="mobileMenu"
            class="hidden fixed inset-y-0 left-0 w-[85%] max-w-[360px] bg-[#2f6fa3] z-[9999] shadow-2xl">
            <div class="px-4 py-4 flex items-center justify-between border-b border-white/10">
                <div class="text-white font-semibold">Menu</div>

                <button type="button" id="mobileMenuCloseBtn"
                    class="inline-flex items-center justify-center rounded p-2 text-white/90 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/40"
                    aria-label="Close menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-4 py-4 overflow-y-auto" style="max-height: calc(100vh - 56px);">
                {!! $mobileMenuHtml !!}
            </div>
        </div>
    </div>

    {{-- ✅ JS: safe (no duplicates if partial renders twice) --}}
    <script>
        (function() {
            const btn = document.getElementById('mobileMenuBtn');
            const closeBtn = document.getElementById('mobileMenuCloseBtn');
            const menu = document.getElementById('mobileMenu');
            const overlay = document.getElementById('mobileMenuOverlay');

            if (!btn || !menu || !overlay || !closeBtn) return;
            if (btn.dataset.bound === '1') return; // prevent double-binding
            btn.dataset.bound = '1';

            const openMenu = () => {
                menu.classList.remove('hidden');
                overlay.classList.remove('hidden');
                btn.setAttribute('aria-expanded', 'true');
                document.documentElement.classList.add('overflow-hidden');
            };

            const closeMenu = () => {
                menu.classList.add('hidden');
                overlay.classList.add('hidden');
                btn.setAttribute('aria-expanded', 'false');
                document.documentElement.classList.remove('overflow-hidden');
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

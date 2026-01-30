@php
    $footerMenu = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('footer');
    $footerWidgets = app(\App\Cms\Widgets\SidebarRenderer::class)->render('footer-1');

    $footerMenu = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu flex flex-wrap items-center gap-5 text-sm"',
            'class="cms-menu__link text-slate-600 hover:text-slate-900 transition"',
            'class="cms-menu__item"',
        ],
        $footerMenu
    );
@endphp

<footer class="border-t border-slate-200 bg-slate-50">
    <div class="cms-container mx-auto px-4 py-10">
        @if (trim($footerWidgets) !== '')
            <div class="mb-8 grid gap-6 md:grid-cols-3">
                {!! $footerWidgets !!}
            </div>
        @endif

        <div class="flex flex-col items-start justify-between gap-4 border-t border-slate-200 pt-6 md:flex-row md:items-center">
            @if (trim($footerMenu) !== '')
                <nav aria-label="Footer navigation">
                    {!! $footerMenu !!}
                </nav>
            @endif

            <div class="text-sm text-slate-500">
                © {{ date('Y') }} {{ config('app.name', 'Siatex') }}. All rights reserved.
            </div>
        </div>
    </div>
</footer>

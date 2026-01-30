@php
    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('topbar');
    if (trim($menuHtml) === '') {
        $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
    }

    // Add Tailwind classes into the renderer HTML (it outputs cms-menu classes)
    $menuHtml = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu flex flex-wrap items-center gap-6 text-sm text-white/90"',
            'class="cms-menu__link hover:text-white transition"',
            'class="cms-menu__item"',
        ],
        $menuHtml
    );
@endphp

@if (trim($menuHtml) !== '')
    <div class="bg-[#2f6fa3]">
        <div class="cms-container mx-auto px-4">
            <div class="py-2">
                <nav aria-label="Top navigation">
                    {!! $menuHtml !!}
                </nav>
            </div>
        </div>
    </div>
@endif

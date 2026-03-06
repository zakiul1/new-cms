@php
    use App\Cms\Core\SettingsRepository;
    use App\Cms\Hooks\HookPoints;
    use App\Cms\Hooks\Hooks;

    $settings = app(SettingsRepository::class);
    $menuRenderer = app(\App\Cms\Menus\MenuRenderer::class);
    $sidebarRenderer = app(\App\Cms\Widgets\SidebarRenderer::class);
    $hooks = app(Hooks::class);

    $footerMenu = $menuRenderer->renderLocation('footer');

    $footerMenu = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu flex flex-wrap items-center gap-5 text-sm"',
            'class="cms-menu__link text-slate-600 hover:text-slate-900 transition"',
            'class="cms-menu__item"',
        ],
        $footerMenu,
    );

    $footerColumns = max(3, min(5, (int) $settings->get('core', 'footer_columns', 3)));
    $footerColumnHtml = [];

    for ($i = 1; $i <= $footerColumns; $i++) {
        $html = (string) $sidebarRenderer->render("footer-{$i}");
        if (trim($html) !== '') {
            $footerColumnHtml[] = $html;
        }
    }

    $bottomFooterRaw = (string) $settings->get('core', 'footer_bottom_content', '');
    $bottomFooterHtml =
        trim($bottomFooterRaw) !== ''
            ? (string) $hooks->applyFilters(HookPoints::CMS_THE_CONTENT, $bottomFooterRaw, [])
            : '';

    $gridClass = match ($footerColumns) {
        4 => 'md:grid-cols-2 xl:grid-cols-4',
        5 => 'md:grid-cols-2 xl:grid-cols-5',
        default => 'md:grid-cols-2 xl:grid-cols-3',
    };
@endphp

<footer class="border-t border-slate-200 bg-slate-50">
    <div class="cms-container mx-auto px-4 py-10">
        @if (!empty($footerColumnHtml))
            <div class="mb-8 grid gap-6 {{ $gridClass }}">
                @foreach ($footerColumnHtml as $columnHtml)
                    <div class="footer-column">
                        {!! $columnHtml !!}
                    </div>
                @endforeach
            </div>
        @endif

        @if (trim($bottomFooterHtml) !== '')
            <div class="border-t border-slate-200 pt-8">
                <div class="prose prose-slate max-w-none">
                    {!! $bottomFooterHtml !!}
                </div>
            </div>
        @endif

        <div
            class="mt-8 flex flex-col items-start justify-between gap-4 border-t border-slate-200 pt-6 md:flex-row md:items-center">
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

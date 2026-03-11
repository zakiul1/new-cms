@php
    use App\Cms\Core\SettingsRepository;
    use App\Cms\Hooks\HookPoints;
    use App\Cms\Hooks\Hooks;

    $settings = app(SettingsRepository::class);
    $menuRenderer = app(\App\Cms\Menus\MenuRenderer::class);
    $sidebarRenderer = app(\App\Cms\Widgets\SidebarRenderer::class);
    $hooks = app(Hooks::class);

    $footerData = theme_footer_data();

    $bg = $footerData['background_color'] ?? '#ffffff';
    $text = $footerData['text_color'] ?? '#111827';
    $align = $footerData['text_alignment'] ?? 'center';

    $siteName = theme_site_title();

    $beforeCopyright = (string) ($footerData['before_copyright'] ?? '');
    $copyrightHtml = (string) ($footerData['copyright_html'] ?? '');
    $secondLine = (string) ($footerData['second_line'] ?? '');

    $footerMenu = $menuRenderer->renderLocation('footer');

    $footerMenu = str_replace(
        ['class="cms-menu"', 'class="cms-menu__link"', 'class="cms-menu__item"'],
        [
            'class="cms-menu flex flex-wrap items-center gap-5 text-sm"',
            'class="cms-menu__link transition hover:opacity-80"',
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

<footer class="cms-footer border-t"
    style="background: {{ $bg }}; color: {{ $text }}; border-color: rgba(0,0,0,.12);">
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
            <div class="border-t pt-8" style="border-color: rgba(0,0,0,.12);">
                <div class="prose max-w-none">
                    {!! $bottomFooterHtml !!}
                </div>
            </div>
        @endif

        <div class="mt-8 border-t pt-6" style="border-color: rgba(0,0,0,.12); text-align: {{ $align }};">
            @if ($beforeCopyright !== '')
                <div class="mb-3">
                    {!! nl2br(e($beforeCopyright)) !!}
                </div>
            @endif

            @if (trim($footerMenu) !== '')
                <nav aria-label="Footer navigation" class="mb-4">
                    {!! $footerMenu !!}
                </nav>
            @endif

            @if ($copyrightHtml !== '')
                <div class="copyright-text">
                    {!! $copyrightHtml !!}
                </div>
            @else
                <div class="copyright-text">
                    © {{ date('Y') }} {{ $siteName }}. All rights reserved.
                </div>
            @endif

            @if ($secondLine !== '')
                <div class="mt-2 opacity-80">
                    {{ $secondLine }}
                </div>
            @endif
        </div>
    </div>
</footer>

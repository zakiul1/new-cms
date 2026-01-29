@php
    $footerMenu = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('footer');
    $footerWidgets = app(\App\Cms\Widgets\SidebarRenderer::class)->render('footer-1');
@endphp

<footer class="siatex-footer">
    <div class="cms-container">
        @if (trim($footerWidgets) !== '')
            <div class="siatex-footer__widgets">
                {!! $footerWidgets !!}
            </div>
        @endif

        @if (trim($footerMenu) !== '')
            <div class="siatex-footer__menu">
                {!! $footerMenu !!}
            </div>
        @endif

        <div class="siatex-footer__copy">
            © {{ date('Y') }} {{ config('app.name', 'Siatex') }}. All rights reserved.
        </div>
    </div>
</footer>

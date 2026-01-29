@php
    $settings = app(\App\Cms\Core\Settings::class);

    $phone = (string) $settings->get('contact_phone', '', 'core');
    $email = (string) $settings->get('contact_email', '', 'core');

    $o = theme_options();

    // logo from media picker (id)
    $logoId = (int) ($o['logo_media_id'] ?? 0);
    $logo = $logoId ? \App\Models\Media::query()->whereKey($logoId)->first() : null;
    $logoUrl = $logo ? $logo->url() : null;
    $logoWidth = (int) ($o['logo_width'] ?? 160);
    if ($logoWidth <= 0) {
        $logoWidth = 160;
    }

    $menuHtml = app(\App\Cms\Menus\MenuRenderer::class)->renderLocation('primary');
@endphp

<header class="siatex-header">
    <div class="cms-container">
        <div class="siatex-header__row">
            <div class="siatex-header__brand">
                <a href="{{ url('/') }}" class="siatex-logo" aria-label="Home">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo" style="width: {{ $logoWidth }}px; height:auto;">
                    @else
                        <span class="siatex-site-title">{{ config('app.name', 'Siatex') }}</span>
                    @endif
                </a>
            </div>

            <div class="siatex-header__contact">
                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}" class="siatex-contact__item">
                        <span class="siatex-contact__icon">☎</span>
                        <span class="siatex-contact__text">{{ $phone }}</span>
                    </a>
                @endif

                @if ($email !== '')
                    <a href="mailto:{{ $email }}" class="siatex-contact__item">
                        <span class="siatex-contact__icon">✉</span>
                        <span class="siatex-contact__text">{{ $email }}</span>
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '') !!}
            </div>
        </div>

        <div class="siatex-nav">
            <nav aria-label="Primary navigation">
                @if (trim($menuHtml) !== '')
                    {!! $menuHtml !!}
                @else
                    <div class="siatex-nav__empty">Menu not set</div>
                @endif
            </nav>
        </div>
    </div>
</header>

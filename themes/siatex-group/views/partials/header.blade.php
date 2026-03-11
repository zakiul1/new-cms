@php
    $settings = app(\App\Cms\Core\Settings::class);
    $status = (string) $settings->get('status', '', 'core');
    $phone = (string) $settings->get('contact_phone', '', 'core');
    $email = (string) $settings->get('contact_email', '', 'core');

    $o = theme_options();

    $siteTitle = theme_site_title();
    $tagline = theme_tagline();

    $logoId = (int) data_get($o, 'site_identity.logo_media_id', 0);
    $logo = $logoId ? \App\Models\Media::query()->with('variantRecords')->whereKey($logoId)->first() : null;

    $logoUrl = theme_logo_url();
    $logoWidth = theme_logo_width();

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

    $logoHeight = 40;
    if ($logoNaturalWidth > 0 && $logoNaturalHeight > 0) {
        $calculatedHeight = (int) round(($logoNaturalHeight / $logoNaturalWidth) * $logoWidth);
        if ($calculatedHeight > 0) {
            $logoHeight = $calculatedHeight;
        }
    }

    $logoSizes = $logoWidth . 'px';
    $logoVariantKey = 'thumb';
    $logoVariantKeys = ['thumb', 'hero_sm', 'medium'];

    $faviconUrl = theme_favicon_url();
@endphp

@if ($faviconUrl)
    @push('head')
        <link rel="icon" href="{{ $faviconUrl }}">
        <link rel="shortcut icon" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @endpush
@endif

<header id="site-header" class="bg-white">
    <div class="cms-container mx-auto px-4">
        <div class="flex items-center justify-between gap-4 pt-12 pb-8">
            <a href="{{ url('/') }}"
                class="no-link-affordance flex items-center gap-3 rounded-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99]"
                aria-label="{{ $siteTitle }}">
                @if ($logo instanceof \App\Models\Media)
                    {!! cms_picture(
                        $logo,
                        [
                            'alt' => $siteTitle,
                            'class' => 'logo-img',
                            'style' => 'width: ' . $logoWidth . 'px; height:auto;',
                            'width' => $logoWidth,
                            'height' => $logoHeight,
                            'sizes' => $logoSizes,
                            'loading' => 'eager',
                            'fetchpriority' => 'high',
                            'decoding' => 'async',
                        ],
                        $logoVariantKey,
                        $logoVariantKeys,
                    ) !!}
                @elseif ($logoUrl)
                    <img class="logo-img" src="{{ $logoUrl }}" alt="{{ $siteTitle }}"
                        width="{{ $logoWidth }}" height="{{ $logoHeight }}"
                        style="width: {{ $logoWidth }}px; height:auto;" loading="eager" fetchpriority="high"
                        decoding="async">
                @else
                    <div class="flex flex-col">
                        <span class="text-xl font-extrabold tracking-tight">{{ $siteTitle }}</span>
                        @if ($tagline !== '')
                            <span class="text-sm opacity-80">{{ $tagline }}</span>
                        @endif
                    </div>
                @endif
            </a>

            <div class="data-cms-header-actions flex items-center gap-6 text-sm">
                @if ($status !== '')
                    <div class="hidden opacity-80 md:block">
                        {{ $status }}
                    </div>
                @endif

                @if ($phone !== '')
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                        class="hidden items-center gap-2 underline decoration-[1.5px] underline-offset-4 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99] md:flex">
                        <span aria-hidden="true">📞</span>
                        <span class="font-medium">{{ $phone }}</span>
                    </a>
                @endif

                @if ($email !== '')
                    <a href="mailto:{{ $email }}"
                        class="hidden items-center gap-2 underline decoration-[1.5px] underline-offset-4 hover:no-underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1f5f99] md:flex">
                        <span aria-hidden="true">✉️</span>
                        <span class="font-medium">{{ $email }}</span>
                    </a>
                @endif

                {!! app(\App\Cms\Hooks\Hooks::class)->applyFilters('theme.header.icons', '') !!}
            </div>
        </div>
    </div>
</header>
